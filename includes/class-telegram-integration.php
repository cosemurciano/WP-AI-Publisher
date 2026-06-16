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
		$update  = (array) $request->get_json_params();
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

		// Chat allowlist (when configured).
		$allowed = wpai_publisher_get_telegram_allowed_chat_ids();
		if ( ! empty( $allowed ) && ! in_array( $chat_id, $allowed, true ) ) {
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

		// Defer the slow draft generation to WP-Cron and nudge it to run soon.
		if ( ! wp_next_scheduled( self::CRON_HOOK, array( $idea_id, $chat_id ) ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK, array( $idea_id, $chat_id ) );
		}
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}

		$this->maybe_reply( $chat_id, __( '✍️ Idea ricevuta! Sto generando la bozza, ti avviso appena è pronta.', 'wp-ai-publisher' ) );

		return new WP_REST_Response( array( 'ok' => true, 'idea_created' => true, 'idea_id' => $idea_id ), 200 );
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
		$token = wpai_publisher_get_telegram_bot_token();
		if ( '' === $token || '' === (string) $chat_id || '' === trim( (string) $text ) ) {
			return;
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
			$this->logger->warning( __( 'Invio risposta Telegram non riuscito.', 'wp-ai-publisher' ), array( 'source' => 'telegram', 'event' => 'reply_failed', 'chat_id' => (string) $chat_id, 'message' => $response->get_error_message() ) );
		}
	}
}
