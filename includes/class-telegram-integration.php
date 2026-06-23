<?php
/**
 * Telegram integration: inbound webhook → content idea + draft, with reply.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receives Telegram bot updates and turns them into ideas/drafts.
 *
 * Flow: Telegram POSTs an update to our REST endpoint → we authenticate it
 * (secret token + chat allowlist), create the idea immediately and schedule
 * the (slow) draft generation on WP-Cron, replying quickly so Telegram does
 * not retry. When the draft is ready, we send a Telegram message back with the
 * edit link.
 */
class Telegram_Integration {

	const REST_NAMESPACE = 'wp-ai-publisher/v1';
	const REST_ROUTE     = '/telegram';
	const CRON_HOOK      = 'wpai_publisher_telegram_process_idea';

	/**
	 * Content ideas service.
	 *
	 * @var Content_Ideas
	 */
	private $content_ideas;

	/**
	 * Logger service.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Content_Ideas $content_ideas Content ideas service.
	 * @param Logger        $logger Logger service.
	 */
	public function __construct( Content_Ideas $content_ideas, Logger $logger ) {
		$this->content_ideas = $content_ideas;
		$this->logger        = $logger;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( self::CRON_HOOK, array( $this, 'process_idea_async' ), 10, 2 );
		add_action( 'admin_post_wpai_publisher_telegram_set_webhook', array( $this, 'handle_set_webhook' ) );
		add_action( 'admin_post_wpai_publisher_telegram_webhook_info', array( $this, 'handle_webhook_info' ) );
		add_action( 'admin_post_wpai_publisher_telegram_send_help', array( $this, 'handle_send_help' ) );
		add_filter( 'wpai_publisher_forced_category_ids', array( $this, 'filter_forced_category_ids' ), 10, 2 );
		add_action( 'wpai_publisher_idea_draft_created', array( $this, 'notify_bulk_import_draft' ), 10, 2 );
	}

	/**
	 * Notify allowed Telegram chats when a bulk-imported idea's draft is created.
	 *
	 * Only ideas flagged by the bulk importer are announced; the flag is consumed
	 * once the message is sent.
	 *
	 * @param int $idea_id Idea ID.
	 * @param int $post_id Draft post ID.
	 * @return void
	 */
	public function notify_bulk_import_draft( $idea_id, $post_id ) {
		$idea_id = absint( $idea_id );
		$post_id = absint( $post_id );
		if ( $idea_id <= 0 || $post_id <= 0 || ! class_exists( __NAMESPACE__ . '\\Bulk_Import' ) ) {
			return;
		}

		$option = Bulk_Import::NOTIFY_OPTION;
		$list   = get_option( $option, array() );
		$list   = is_array( $list ) ? array_map( 'absint', $list ) : array();
		if ( ! in_array( $idea_id, $list, true ) ) {
			return;
		}

		// Consume the flag so we never notify twice.
		update_option( $option, array_values( array_diff( $list, array( $idea_id ) ) ), false );

		$chat_ids = wpai_publisher_get_telegram_allowed_chat_ids();
		if ( empty( $chat_ids ) || '' === wpai_publisher_get_telegram_bot_token() ) {
			return;
		}

		$title     = get_the_title( $post_id );
		$edit_link = get_edit_post_link( $post_id, '' );
		$text      = sprintf(
			/* translators: 1: post title, 2: edit URL. */
			__( "✅ Bozza creata (importazione massiva): %1\$s\n%2\$s", 'wp-ai-publisher' ),
			'' !== $title ? $title : sprintf( __( 'Bozza #%d', 'wp-ai-publisher' ), $post_id ),
			$edit_link ? $edit_link : ''
		);

		foreach ( $chat_ids as $chat_id ) {
			$this->send_message( $chat_id, $text );
		}
	}

	/**
	 * Provide the categories chosen via the Telegram inline keyboard for an idea.
	 *
	 * @param array<int,int> $forced  Current forced IDs.
	 * @param int            $idea_id Idea ID.
	 * @return array<int,int>
	 */
	public function filter_forced_category_ids( $forced, $idea_id ) {
		$state = $this->get_state( (int) $idea_id );
		if ( is_array( $state ) && ! empty( $state['categories'] ) && is_array( $state['categories'] ) ) {
			return array_values( array_filter( array_map( 'absint', $state['categories'] ) ) );
		}
		return $forced;
	}

	/**
	 * The public REST URL Telegram should POST updates to.
	 *
	 * @return string
	 */
	public function get_webhook_url() {
		return esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) );
	}

	/**
	 * Admin action: register the webhook on Telegram (setWebhook).
	 *
	 * @return void
	 */
	public function handle_set_webhook() {
		$this->guard_admin_action( 'wpai_publisher_telegram_set_webhook' );

		$token  = wpai_publisher_get_telegram_bot_token();
		$secret = wpai_publisher_get_telegram_secret_token();
		if ( '' === $token || '' === $secret ) {
			$this->finish_admin_action( __( 'Configura prima il token bot e il secret (costanti WPAIP_TELEGRAM_BOT_TOKEN e WPAIP_TELEGRAM_SECRET).', 'wp-ai-publisher' ), false );
		}

		$response = wp_remote_post(
			'https://api.telegram.org/bot' . $token . '/setWebhook',
			array(
				'timeout' => 20,
				'body'    => array(
					'url'          => $this->get_webhook_url(),
					'secret_token' => $secret,
				),
			)
		);
		$this->finish_admin_action_from_response( $response, __( 'Webhook registrato correttamente.', 'wp-ai-publisher' ) );
	}

	/**
	 * Admin action: read the current webhook status (getWebhookInfo).
	 *
	 * @return void
	 */
	public function handle_webhook_info() {
		$this->guard_admin_action( 'wpai_publisher_telegram_webhook_info' );

		$token = wpai_publisher_get_telegram_bot_token();
		if ( '' === $token ) {
			$this->finish_admin_action( __( 'Token bot non configurato (costante WPAIP_TELEGRAM_BOT_TOKEN).', 'wp-ai-publisher' ), false );
		}

		$response = wp_remote_get(
			'https://api.telegram.org/bot' . $token . '/getWebhookInfo',
			array( 'timeout' => 20 )
		);
		if ( is_wp_error( $response ) ) {
			$this->finish_admin_action( $response->get_error_message(), false );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['ok'] ) ) {
			$this->finish_admin_action( __( 'Risposta non valida da Telegram.', 'wp-ai-publisher' ), false );
		}
		$info       = is_array( $body['result'] ?? null ) ? $body['result'] : array();
		$url        = (string) ( $info['url'] ?? '' );
		$pending    = (int) ( $info['pending_update_count'] ?? 0 );
		$last_error = (string) ( $info['last_error_message'] ?? '' );
		$message    = '' === $url
			? __( 'Nessun webhook impostato.', 'wp-ai-publisher' )
			: sprintf( __( 'Webhook attivo su %1$s — update in attesa: %2$d.', 'wp-ai-publisher' ), $url, $pending );
		if ( '' !== $last_error ) {
			$message .= ' ' . sprintf( __( 'Ultimo errore: %s', 'wp-ai-publisher' ), $last_error );
		}
		$this->finish_admin_action( $message, '' !== $url && '' === $last_error );
	}

	/**
	 * Capability + nonce guard for the admin webhook actions.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private function guard_admin_action( $action ) {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Turn a Telegram HTTP response into an admin notice and redirect back.
	 *
	 * @param array|WP_Error $response Response.
	 * @param string         $success_message Message on success.
	 * @return void
	 */
	private function finish_admin_action_from_response( $response, $success_message ) {
		if ( is_wp_error( $response ) ) {
			$this->finish_admin_action( $response->get_error_message(), false );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( is_array( $body ) && ! empty( $body['ok'] ) ) {
			$this->finish_admin_action( $success_message, true );
		}
		$detail = is_array( $body ) ? (string) ( $body['description'] ?? '' ) : '';
		$this->finish_admin_action( '' !== $detail ? $detail : __( 'Operazione non riuscita.', 'wp-ai-publisher' ), false );
	}

	/**
	 * Store the result in a per-user transient and redirect to the settings page.
	 *
	 * @param string $message Human-readable result.
	 * @param bool   $ok Whether the action succeeded.
	 * @return void
	 */
	private function finish_admin_action( $message, $ok ) {
		set_transient(
			'wpai_publisher_telegram_notice_' . get_current_user_id(),
			array( 'ok' => (bool) $ok, 'message' => sanitize_text_field( (string) $message ) ),
			60
		);
		wp_safe_redirect( add_query_arg( 'wpai_notice', 'telegram_webhook', admin_url( 'admin.php?page=wp-ai-publisher-settings' ) ) );
		exit;
	}

	/**
	 * Admin action: send the usage instructions to the allowed Telegram chats.
	 *
	 * @return void
	 */
	public function handle_send_help() {
		$this->guard_admin_action( 'wpai_publisher_telegram_send_help' );

		if ( '' === wpai_publisher_get_telegram_bot_token() ) {
			$this->finish_admin_action( __( 'Token bot non configurato (costante WPAIP_TELEGRAM_BOT_TOKEN).', 'wp-ai-publisher' ), false );
		}
		$chat_ids = wpai_publisher_get_telegram_allowed_chat_ids();
		if ( empty( $chat_ids ) ) {
			$this->finish_admin_action( __( 'Aggiungi almeno una Chat ID autorizzata per inviare le istruzioni.', 'wp-ai-publisher' ), false );
		}

		$text = $this->get_help_message();
		$sent = 0;
		foreach ( $chat_ids as $chat_id ) {
			if ( $this->send_message( $chat_id, $text ) ) {
				$sent++;
			}
		}

		if ( $sent > 0 ) {
			$this->finish_admin_action( sprintf( _n( 'Istruzioni inviate a %d chat.', 'Istruzioni inviate a %d chat.', $sent, 'wp-ai-publisher' ), $sent ), true );
		}
		$this->finish_admin_action( __( 'Invio delle istruzioni non riuscito. Controlla token, Chat ID e connettività verso api.telegram.org.', 'wp-ai-publisher' ), false );
	}

	/**
	 * Build the usage instructions message sent to Telegram.
	 *
	 * @return string
	 */
	private function get_help_message() {
		$lines = array(
			__( '🤖 WP AI Publisher — come creare una bozza', 'wp-ai-publisher' ),
			'',
			__( 'Scrivi qui un messaggio con l’argomento dell’articolo che vuoi creare.', 'wp-ai-publisher' ),
			__( 'Esempio: “Guida alla scelta del nome a dominio per un blog WordPress”.', 'wp-ai-publisher' ),
			'',
			__( 'Cosa succede:', 'wp-ai-publisher' ),
			__( '1) Se la scelta interattiva è attiva, ti mostro i pulsanti per scegliere la Tipologia articolo e le Categorie.', 'wp-ai-publisher' ),
			__( '2) Premi “Genera bozza”: creo l’articolo e la bozza in background.', 'wp-ai-publisher' ),
			__( '3) Ti rispondo con il titolo e il link alla bozza.', 'wp-ai-publisher' ),
			'',
			__( 'Consigli:', 'wp-ai-publisher' ),
			__( '• Usa argomenti chiari e specifici per risultati migliori.', 'wp-ai-publisher' ),
			__( '• Un messaggio = una bozza.', 'wp-ai-publisher' ),
			__( '• I messaggi che iniziano con “/” vengono ignorati.', 'wp-ai-publisher' ),
			__( '• Se la scelta interattiva è disattivata, uso la Tipologia predefinita e le categorie scelte dall’AI.', 'wp-ai-publisher' ),
		);
		return implode( "\n", $lines );
	}

	/**
	 * Whether the integration is enabled and minimally configured.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		$settings = wpai_publisher_get_settings();
		return ! empty( $settings['telegram_enabled'] ) && '' !== wpai_publisher_get_telegram_secret_token();
	}

	/**
	 * Register the REST route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => array( $this, 'authenticate' ),
			)
		);
	}

	/**
	 * Authenticate the inbound Telegram webhook via the secret token header.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function authenticate( $request ) {
		if ( ! $this->is_enabled() ) {
			return new WP_Error( 'wpai_telegram_disabled', __( 'Integrazione Telegram non attiva.', 'wp-ai-publisher' ), array( 'status' => 404 ) );
		}
		$expected = wpai_publisher_get_telegram_secret_token();
		$provided = (string) $request->get_header( 'x_telegram_bot_api_secret_token' );
		if ( '' === $expected || ! hash_equals( $expected, $provided ) ) {
			return new WP_Error( 'wpai_telegram_unauthorized', __( 'Token segreto Telegram non valido.', 'wp-ai-publisher' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * Handle a Telegram update: create the idea and schedule async generation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		$update = (array) $request->get_json_params();

		// Inline-keyboard button taps arrive as callback queries.
		if ( isset( $update['callback_query'] ) && is_array( $update['callback_query'] ) ) {
			return $this->handle_callback_query( $update['callback_query'] );
		}

		$message = isset( $update['message'] ) && is_array( $update['message'] ) ? $update['message'] : array();
		if ( empty( $message ) && isset( $update['channel_post'] ) && is_array( $update['channel_post'] ) ) {
			$message = $update['channel_post'];
		}

		$text    = trim( (string) ( $message['text'] ?? '' ) );
		$chat    = isset( $message['chat'] ) && is_array( $message['chat'] ) ? $message['chat'] : array();
		$chat_id = (string) ( $chat['id'] ?? '' );

		// Always answer 200 so Telegram does not retry; report status in body.
		if ( '' === $chat_id || '' === $text ) {
			return new WP_REST_Response( array( 'ok' => true, 'skipped' => 'empty_message' ), 200 );
		}

		if ( ! $this->chat_is_allowed( $chat_id ) ) {
			$this->logger->warning( __( 'Messaggio Telegram da chat non autorizzata.', 'wp-ai-publisher' ), array( 'source' => 'telegram', 'event' => 'unauthorized_chat', 'chat_id' => $chat_id ) );
			return new WP_REST_Response( array( 'ok' => true, 'skipped' => 'chat_not_allowed' ), 200 );
		}

		// Ignore bot commands like /start.
		if ( '/' === substr( $text, 0, 1 ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'skipped' => 'command' ), 200 );
		}

		$settings = wpai_publisher_get_settings();
		$idea_id  = $this->content_ideas->create_idea_programmatic(
			array(
				'topic'           => $text,
				'language'        => (string) ( $settings['telegram_language'] ?? 'it' ),
				'article_type_id' => absint( $settings['telegram_article_type_id'] ?? 0 ),
				'notes'           => sprintf( 'Telegram chat %s', $chat_id ),
			)
		);

		if ( is_wp_error( $idea_id ) ) {
			$this->logger->warning( __( 'Creazione idea da Telegram non riuscita.', 'wp-ai-publisher' ), array( 'source' => 'telegram', 'event' => 'idea_failed', 'chat_id' => $chat_id, 'error_code' => $idea_id->get_error_code(), 'message' => $idea_id->get_error_message() ) );
			$this->maybe_reply( $chat_id, sprintf( __( '⚠️ Non sono riuscito a creare l’idea: %s', 'wp-ai-publisher' ), $idea_id->get_error_message() ) );
			return new WP_REST_Response( array( 'ok' => true, 'idea_created' => false ), 200 );
		}

		$idea_id = (int) $idea_id;
		$this->logger->info( __( 'Idea creata da Telegram.', 'wp-ai-publisher' ), array( 'source' => 'telegram', 'event' => 'idea_created', 'chat_id' => $chat_id, 'idea_id' => $idea_id ) );

		// Interactive flow: ask the user to pick the article type / categories
		// with inline buttons before generating.
		if ( ! empty( $settings['telegram_interactive'] ) && $this->start_interactive_selection( $idea_id, $chat_id ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'idea_created' => true, 'idea_id' => $idea_id, 'mode' => 'interactive' ), 200 );
		}

		// Non-interactive: generate right away.
		$this->enqueue_generation( $idea_id, $chat_id );
		$this->maybe_reply( $chat_id, __( '✍️ Idea ricevuta! Sto generando la bozza, ti avviso appena è pronta.', 'wp-ai-publisher' ) );

		return new WP_REST_Response( array( 'ok' => true, 'idea_created' => true, 'idea_id' => $idea_id ), 200 );
	}

	/**
	 * Whether a chat is allowed (empty allowlist = allow any).
	 *
	 * @param string $chat_id Chat ID.
	 * @return bool
	 */
	private function chat_is_allowed( $chat_id ) {
		$allowed = wpai_publisher_get_telegram_allowed_chat_ids();
		return empty( $allowed ) || in_array( (string) $chat_id, $allowed, true );
	}

	/**
	 * Schedule the (slow) draft generation on WP-Cron and nudge it to run soon.
	 *
	 * @param int    $idea_id Idea ID.
	 * @param string $chat_id Chat ID.
	 * @return void
	 */
	private function enqueue_generation( $idea_id, $chat_id ) {
		if ( ! wp_next_scheduled( self::CRON_HOOK, array( (int) $idea_id, (string) $chat_id ) ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK, array( (int) $idea_id, (string) $chat_id ) );
		}
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Start the interactive selection: send the article-type (or category) keyboard.
	 *
	 * @param int    $idea_id Idea ID.
	 * @param string $chat_id Chat ID.
	 * @return bool True when an interactive prompt was sent.
	 */
	private function start_interactive_selection( $idea_id, $chat_id ) {
		$types = wpai_publisher_article_types_enabled() && function_exists( 'wpai_publisher_get_active_article_types_safe' )
			? wpai_publisher_get_active_article_types_safe()
			: array();

		$this->set_state( $idea_id, array( 'chat_id' => (string) $chat_id, 'categories' => array(), 'article_type_id' => 0 ) );

		if ( ! empty( $types ) ) {
			$keyboard = $this->build_article_type_keyboard( $idea_id, $types );
			return $this->send_message_with_markup( $chat_id, __( '📝 Idea ricevuta! Scegli la *Tipologia articolo*:', 'wp-ai-publisher' ), $keyboard );
		}

		// No article types: jump to categories (or generate if none).
		return $this->prompt_categories_or_generate( $idea_id, $chat_id );
	}

	/**
	 * Handle an inline-keyboard button tap (callback query).
	 *
	 * @param array<string,mixed> $cb Callback query payload.
	 * @return WP_REST_Response
	 */
	private function handle_callback_query( $cb ) {
		$data       = (string) ( $cb['data'] ?? '' );
		$cb_id      = (string) ( $cb['id'] ?? '' );
		$msg        = isset( $cb['message'] ) && is_array( $cb['message'] ) ? $cb['message'] : array();
		$chat       = isset( $msg['chat'] ) && is_array( $msg['chat'] ) ? $msg['chat'] : array();
		$chat_id    = (string) ( $chat['id'] ?? '' );
		$message_id = (int) ( $msg['message_id'] ?? 0 );

		if ( '' === $chat_id || ! $this->chat_is_allowed( $chat_id ) ) {
			$this->answer_callback_query( $cb_id, __( 'Non autorizzato.', 'wp-ai-publisher' ) );
			return new WP_REST_Response( array( 'ok' => true, 'skipped' => 'chat_not_allowed' ), 200 );
		}

		$parts   = explode( ':', $data );
		$prefix  = (string) ( $parts[0] ?? '' );
		$idea_id = absint( $parts[1] ?? 0 );
		$value   = isset( $parts[2] ) ? absint( $parts[2] ) : 0;

		$state = $this->get_state( $idea_id );
		if ( empty( $state ) ) {
			$this->answer_callback_query( $cb_id, __( 'Sessione scaduta: invia di nuovo il messaggio.', 'wp-ai-publisher' ) );
			return new WP_REST_Response( array( 'ok' => true, 'skipped' => 'expired' ), 200 );
		}

		switch ( $prefix ) {
			case 'at': // Article type chosen.
				if ( $value > 0 && $this->content_ideas->assign_article_type( $idea_id, $value ) ) {
					$state['article_type_id'] = $value;
					$this->set_state( $idea_id, $state );
				}
				$this->answer_callback_query( $cb_id, __( 'Tipologia impostata.', 'wp-ai-publisher' ) );
				$this->prompt_categories_or_generate( $idea_id, $chat_id, $message_id );
				break;

			case 'ct': // Toggle a category.
				$selected = array_map( 'absint', (array) ( $state['categories'] ?? array() ) );
				if ( in_array( $value, $selected, true ) ) {
					$selected = array_values( array_diff( $selected, array( $value ) ) );
				} else {
					$selected[] = $value;
				}
				$state['categories'] = array_values( array_unique( $selected ) );
				$this->set_state( $idea_id, $state );
				$this->edit_message_reply_markup( $chat_id, $message_id, $this->build_category_keyboard( $idea_id, $state['categories'], $this->get_selectable_categories( $idea_id ) ) );
				$this->answer_callback_query( $cb_id, '' );
				break;

			case 'sk': // Skip categories (let the AI choose).
				$state['categories'] = array();
				$this->set_state( $idea_id, $state );
				$this->answer_callback_query( $cb_id, __( 'Procedo senza categorie scelte.', 'wp-ai-publisher' ) );
				$this->finalize_generation( $idea_id, $chat_id, $message_id );
				break;

			case 'go': // Generate with the current selection.
				$this->answer_callback_query( $cb_id, __( 'Genero la bozza…', 'wp-ai-publisher' ) );
				$this->finalize_generation( $idea_id, $chat_id, $message_id );
				break;

			default:
				$this->answer_callback_query( $cb_id, '' );
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Show the category keyboard, or generate directly when there are none.
	 *
	 * @param int    $idea_id Idea ID.
	 * @param string $chat_id Chat ID.
	 * @param int    $message_id Message to edit (0 = send a new message).
	 * @return bool
	 */
	private function prompt_categories_or_generate( $idea_id, $chat_id, $message_id = 0 ) {
		$categories = $this->get_selectable_categories( $idea_id );
		if ( empty( $categories ) ) {
			$this->finalize_generation( $idea_id, $chat_id, $message_id );
			return true;
		}
		$state    = $this->get_state( $idea_id );
		$selected = array_map( 'absint', (array) ( $state['categories'] ?? array() ) );
		$keyboard = $this->build_category_keyboard( $idea_id, $selected, $categories );
		$text     = __( '📂 Scegli le *Categorie* (anche più di una), poi premi *Genera bozza*:', 'wp-ai-publisher' );
		if ( $message_id > 0 ) {
			return $this->edit_message_text( $chat_id, $message_id, $text, $keyboard );
		}
		return $this->send_message_with_markup( $chat_id, $text, $keyboard );
	}

	/**
	 * Finalize: confirm on Telegram and enqueue the draft generation.
	 *
	 * The selection state transient is kept so the generation can read the
	 * chosen categories (via the wpai_publisher_forced_category_ids filter); it
	 * is removed after generation in process_idea_async().
	 *
	 * @param int    $idea_id Idea ID.
	 * @param string $chat_id Chat ID.
	 * @param int    $message_id Message to edit (0 = send a new message).
	 * @return void
	 */
	private function finalize_generation( $idea_id, $chat_id, $message_id = 0 ) {
		$text = __( '✍️ Sto generando la bozza, ti avviso appena è pronta.', 'wp-ai-publisher' );
		if ( $message_id > 0 ) {
			$this->edit_message_text( $chat_id, $message_id, $text, null );
		} else {
			$this->maybe_reply( $chat_id, $text );
		}
		$this->enqueue_generation( $idea_id, $chat_id );
	}

	/**
	 * Build the article-type inline keyboard.
	 *
	 * @param int                      $idea_id Idea ID.
	 * @param array<int,array<string,mixed>> $types Active article types.
	 * @return array<string,mixed>
	 */
	private function build_article_type_keyboard( $idea_id, $types ) {
		$rows = array();
		foreach ( $types as $type ) {
			$name = trim( (string) ( $type['name'] ?? '' ) );
			$id   = absint( $type['id'] ?? 0 );
			if ( '' === $name || 0 === $id ) {
				continue;
			}
			$rows[] = array( array( 'text' => $name, 'callback_data' => 'at:' . $idea_id . ':' . $id ) );
		}
		return array( 'inline_keyboard' => $rows );
	}

	/**
	 * Build the category multi-select inline keyboard.
	 *
	 * @param int                            $idea_id Idea ID.
	 * @param array<int,int>                 $selected Selected category IDs.
	 * @param array<int,array<string,mixed>> $categories Selectable categories.
	 * @return array<string,mixed>
	 */
	private function build_category_keyboard( $idea_id, $selected, $categories ) {
		$selected = array_map( 'absint', (array) $selected );
		$rows     = array();
		$buffer   = array();
		foreach ( $categories as $category ) {
			$id    = absint( $category['id'] ?? 0 );
			$name  = trim( (string) ( $category['name'] ?? '' ) );
			if ( 0 === $id || '' === $name ) {
				continue;
			}
			$label    = ( in_array( $id, $selected, true ) ? '✅ ' : '▫️ ' ) . $name;
			$buffer[] = array( 'text' => $label, 'callback_data' => 'ct:' . $idea_id . ':' . $id );
			if ( 2 === count( $buffer ) ) {
				$rows[]  = $buffer;
				$buffer = array();
			}
		}
		if ( ! empty( $buffer ) ) {
			$rows[] = $buffer;
		}
		$rows[] = array(
			array( 'text' => __( '⏭️ Salta', 'wp-ai-publisher' ), 'callback_data' => 'sk:' . $idea_id ),
			array( 'text' => __( '✅ Genera bozza', 'wp-ai-publisher' ), 'callback_data' => 'go:' . $idea_id ),
		);
		return array( 'inline_keyboard' => $rows );
	}

	/**
	 * Categories the user can choose for an idea (respecting the article type
	 * restriction when present); capped for keyboard size.
	 *
	 * @param int $idea_id Idea ID.
	 * @return array<int,array{id:int,name:string}>
	 */
	private function get_selectable_categories( $idea_id ) {
		if ( ! function_exists( 'get_categories' ) ) {
			return array();
		}
		$state       = $this->get_state( $idea_id );
		$type_id     = absint( $state['article_type_id'] ?? 0 );
		$allowed_ids = array();
		if ( $type_id > 0 && function_exists( 'wpai_publisher_get_article_type_config_safe' ) && function_exists( 'wpai_publisher_resolve_allowed_category_ids' ) ) {
			$config   = wpai_publisher_get_article_type_config_safe( $type_id );
			$boundary = wpai_publisher_resolve_allowed_category_ids( is_array( $config ) ? $config : array() );
			if ( ! empty( $boundary['has_restriction'] ) ) {
				$allowed_ids = array_map( 'absint', (array) ( $boundary['ids'] ?? array() ) );
			}
		}

		$categories = get_categories( array( 'hide_empty' => false, 'number' => 50, 'orderby' => 'count', 'order' => 'DESC' ) );
		$out        = array();
		foreach ( (array) $categories as $category ) {
			$id = (int) ( $category->term_id ?? 0 );
			if ( $id <= 0 || ( ! empty( $allowed_ids ) && ! in_array( $id, $allowed_ids, true ) ) ) {
				continue;
			}
			$out[] = array( 'id' => $id, 'name' => sanitize_text_field( (string) $category->name ) );
		}
		return array_slice( $out, 0, 24 );
	}

	/**
	 * Selection-state transient helpers.
	 *
	 * @param int $idea_id Idea ID.
	 * @return string
	 */
	private function state_key( $idea_id ) {
		return 'wpai_pub_tg_state_' . absint( $idea_id );
	}

	/**
	 * @param int $idea_id Idea ID.
	 * @return array<string,mixed>
	 */
	private function get_state( $idea_id ) {
		$state = get_transient( $this->state_key( $idea_id ) );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * @param int                 $idea_id Idea ID.
	 * @param array<string,mixed> $state State.
	 * @return void
	 */
	private function set_state( $idea_id, $state ) {
		set_transient( $this->state_key( $idea_id ), $state, DAY_IN_SECONDS );
	}

	/**
	 * @param int $idea_id Idea ID.
	 * @return void
	 */
	private function delete_state( $idea_id ) {
		delete_transient( $this->state_key( $idea_id ) );
	}

	/**
	 * POST a method to the Telegram Bot API.
	 *
	 * @param string              $method Telegram API method.
	 * @param array<string,mixed> $body Request body.
	 * @return array<string,mixed>|false
	 */
	private function api_post( $method, $body ) {
		$token = wpai_publisher_get_telegram_bot_token();
		if ( '' === $token ) {
			return false;
		}
		$response = wp_remote_post(
			'https://api.telegram.org/bot' . $token . '/' . $method,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => (string) wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			$this->logger->warning( __( 'Chiamata Telegram non riuscita.', 'wp-ai-publisher' ), array( 'source' => 'telegram', 'event' => 'api_failed', 'method' => $method, 'message' => $response->get_error_message() ) );
			return false;
		}
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $decoded ) ? $decoded : false;
	}

	/**
	 * Send a message with an inline keyboard.
	 *
	 * @param string              $chat_id Chat ID.
	 * @param string              $text Text.
	 * @param array<string,mixed> $reply_markup Inline keyboard.
	 * @return bool
	 */
	private function send_message_with_markup( $chat_id, $text, $reply_markup ) {
		$res = $this->api_post(
			'sendMessage',
			array(
				'chat_id'                  => $chat_id,
				'text'                     => $text,
				'parse_mode'               => 'Markdown',
				'reply_markup'             => $reply_markup,
				'disable_web_page_preview' => true,
			)
		);
		return is_array( $res ) && ! empty( $res['ok'] );
	}

	/**
	 * Edit a message's text (and optionally its keyboard).
	 *
	 * @param string                   $chat_id Chat ID.
	 * @param int                      $message_id Message ID.
	 * @param string                   $text Text.
	 * @param array<string,mixed>|null $reply_markup Inline keyboard or null to drop it.
	 * @return bool
	 */
	private function edit_message_text( $chat_id, $message_id, $text, $reply_markup = null ) {
		$body = array(
			'chat_id'                  => $chat_id,
			'message_id'               => $message_id,
			'text'                     => $text,
			'parse_mode'               => 'Markdown',
			'disable_web_page_preview' => true,
		);
		if ( null !== $reply_markup ) {
			$body['reply_markup'] = $reply_markup;
		}
		$res = $this->api_post( 'editMessageText', $body );
		return is_array( $res ) && ! empty( $res['ok'] );
	}

	/**
	 * Replace a message's inline keyboard.
	 *
	 * @param string              $chat_id Chat ID.
	 * @param int                 $message_id Message ID.
	 * @param array<string,mixed> $reply_markup Inline keyboard.
	 * @return bool
	 */
	private function edit_message_reply_markup( $chat_id, $message_id, $reply_markup ) {
		$res = $this->api_post(
			'editMessageReplyMarkup',
			array( 'chat_id' => $chat_id, 'message_id' => $message_id, 'reply_markup' => $reply_markup )
		);
		return is_array( $res ) && ! empty( $res['ok'] );
	}

	/**
	 * Acknowledge a callback query (stops the Telegram loading spinner).
	 *
	 * @param string $cb_id Callback query ID.
	 * @param string $text Optional toast text.
	 * @return void
	 */
	private function answer_callback_query( $cb_id, $text = '' ) {
		if ( '' === (string) $cb_id ) {
			return;
		}
		$body = array( 'callback_query_id' => $cb_id );
		if ( '' !== $text ) {
			$body['text'] = $text;
		}
		$this->api_post( 'answerCallbackQuery', $body );
	}

	/**
	 * Cron callback: generate the draft and reply with the result.
	 *
	 * @param int    $idea_id Idea ID.
	 * @param string $chat_id Telegram chat ID for the reply.
	 * @return void
	 */
	public function process_idea_async( $idea_id, $chat_id = '' ) {
		$idea_id = absint( $idea_id );
		$chat_id = (string) $chat_id;
		if ( $idea_id <= 0 ) {
			return;
		}

		$result  = $this->content_ideas->process_idea_to_draft( $idea_id );
		$success = is_array( $result ) && ! empty( $result['success'] );
		$post_id = is_array( $result ) ? absint( $result['post_id'] ?? 0 ) : 0;

		if ( $success && $post_id > 0 ) {
			// Build the edit URL directly: get_edit_post_link() needs a current
			// user/capabilities, which are absent in the cron context.
			$edit_link = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
			$title     = get_the_title( $post_id );
			$this->maybe_reply( $chat_id, sprintf( __( "✅ Bozza creata: %1\$s\n%2\$s", 'wp-ai-publisher' ), $title, $edit_link ) );
		} else {
			$message = is_array( $result ) ? (string) ( $result['message'] ?? '' ) : '';
			$this->maybe_reply( $chat_id, sprintf( __( '⚠️ Generazione bozza non riuscita: %s', 'wp-ai-publisher' ), '' !== $message ? $message : __( 'errore sconosciuto', 'wp-ai-publisher' ) ) );
		}

		// Selection state is no longer needed once generation has run.
		$this->delete_state( $idea_id );
	}

	/**
	 * Send a reply to a Telegram chat (when replies are enabled and configured).
	 *
	 * @param string $chat_id Chat ID.
	 * @param string $text Message text.
	 * @return void
	 */
	private function maybe_reply( $chat_id, $text ) {
		$settings = wpai_publisher_get_settings();
		if ( empty( $settings['telegram_reply_enabled'] ) ) {
			return;
		}
		$this->send_message( $chat_id, $text );
	}

	/**
	 * Send a message to a Telegram chat.
	 *
	 * @param string $chat_id Chat ID.
	 * @param string $text Message text.
	 * @return bool True when Telegram accepted the message.
	 */
	private function send_message( $chat_id, $text ) {
		$token = wpai_publisher_get_telegram_bot_token();
		if ( '' === $token || '' === (string) $chat_id || '' === trim( (string) $text ) ) {
			return false;
		}

		$response = wp_remote_post(
			'https://api.telegram.org/bot' . $token . '/sendMessage',
			array(
				'timeout' => 15,
				'body'    => array(
					'chat_id'                  => $chat_id,
					'text'                     => $text,
					'disable_web_page_preview' => true,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->warning( __( 'Invio messaggio Telegram non riuscito.', 'wp-ai-publisher' ), array( 'source' => 'telegram', 'event' => 'send_failed', 'chat_id' => (string) $chat_id, 'message' => $response->get_error_message() ) );
			return false;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) && ! empty( $body['ok'] );
	}
}
