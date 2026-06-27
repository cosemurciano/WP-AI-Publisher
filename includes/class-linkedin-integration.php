<?php
/**
 * LinkedIn company-page auto-publishing on article publish.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shares a post to a LinkedIn organization (company page) when it is published.
 *
 * Mirrors the Facebook/Instagram integrations: a per-post checkbox opts the
 * post in, the share runs in background on publish (WP-Cron, anti-duplicate),
 * and the LinkedIn share URN/URL is stored on the post. Token via the
 * WPAIP_LINKEDIN_ACCESS_TOKEN constant (never in the DB).
 */
class Linkedin_Integration {

	const CRON_HOOK    = 'wpai_publisher_linkedin_publish';
	const META_SHARE   = '_wpai_linkedin_share';
	const META_DONE    = '_wpai_linkedin_shared_at';
	const META_URN     = '_wpai_linkedin_urn';
	const META_ERROR   = '_wpai_linkedin_error';

	/**
	 * Logger service.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * AI provider adapter (optional AI captions).
	 *
	 * @var AI_Provider_Adapter
	 */
	private $ai_provider;

	/**
	 * Constructor.
	 *
	 * @param Logger              $logger Logger.
	 * @param AI_Provider_Adapter $ai_provider AI adapter.
	 */
	public function __construct( Logger $logger, AI_Provider_Adapter $ai_provider ) {
		$this->logger      = $logger;
		$this->ai_provider = $ai_provider;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 10, 3 );
		add_action( self::CRON_HOOK, array( $this, 'publish_to_linkedin' ), 10, 1 );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_post_wpai_publisher_linkedin_test', array( $this, 'handle_test_connection' ) );
	}

	/**
	 * Whether the integration is enabled and minimally configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		$settings = wpai_publisher_get_settings();
		return ! empty( $settings['linkedin_enabled'] )
			&& '' !== trim( (string) ( $settings['linkedin_org_id'] ?? '' ) )
			&& '' !== wpai_publisher_get_linkedin_access_token();
	}

	/**
	 * Default value for the per-post share checkbox.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function default_share_for_post( $post_id ) {
		$settings = wpai_publisher_get_settings();
		if ( empty( $settings['linkedin_default_share'] ) ) {
			return false;
		}
		return '1' === (string) get_post_meta( $post_id, '_wpai_publisher_generated', true );
	}

	/* ---------------------------------------------------------------------
	 * Editor meta box
	 * ------------------------------------------------------------------- */

	/**
	 * Register the editor meta box.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box(
			'wpai-linkedin-share',
			__( 'WP AI Publisher — LinkedIn', 'wp-ai-publisher' ),
			array( $this, 'render_meta_box' ),
			'post',
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		$post_id = (int) $post->ID;
		$stored  = get_post_meta( $post_id, self::META_SHARE, true );
		$checked = ( '' === $stored ) ? $this->default_share_for_post( $post_id ) : ( '1' === (string) $stored );
		wp_nonce_field( 'wpai_publisher_linkedin_meta', 'wpai_publisher_linkedin_meta_nonce' );

		echo '<label><input type="checkbox" name="wpai_linkedin_share" value="1" ' . checked( $checked, true, false ) . '> ' . esc_html__( 'Condividi su LinkedIn alla pubblicazione', 'wp-ai-publisher' ) . '</label>';

		if ( ! $this->is_configured() ) {
			echo '<p class="description">' . esc_html__( 'Configura l’ID organizzazione e il token in Impostazioni → LinkedIn.', 'wp-ai-publisher' ) . '</p>';
		}

		$urn = (string) get_post_meta( $post_id, self::META_URN, true );
		$err = (string) get_post_meta( $post_id, self::META_ERROR, true );
		if ( '' !== $urn ) {
			$url = 'https://www.linkedin.com/feed/update/' . rawurlencode( $urn );
			echo '<p class="description">' . esc_html__( 'Pubblicato su LinkedIn:', 'wp-ai-publisher' ) . ' <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $urn ) . '</a></p>';
		} elseif ( '' !== $err ) {
			echo '<p class="description" style="color:#b32d2e;">' . esc_html__( 'Ultimo errore:', 'wp-ai-publisher' ) . ' ' . esc_html( $err ) . '</p>';
		}
	}

	/**
	 * Persist the meta box checkbox.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function save_meta_box( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['wpai_publisher_linkedin_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpai_publisher_linkedin_meta_nonce'] ) ), 'wpai_publisher_linkedin_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( $post instanceof WP_Post && 'post' !== $post->post_type ) {
			return;
		}
		update_post_meta( $post_id, self::META_SHARE, isset( $_POST['wpai_linkedin_share'] ) ? '1' : '0' );
	}

	/* ---------------------------------------------------------------------
	 * Publish
	 * ------------------------------------------------------------------- */

	/**
	 * Schedule the share on publish.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function on_transition_post_status( $new_status, $old_status, $post ) {
		if ( ! ( $post instanceof WP_Post ) || 'post' !== $post->post_type ) {
			return;
		}
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( ! $this->is_configured() ) {
			return;
		}
		if ( '1' !== (string) get_post_meta( $post->ID, self::META_SHARE, true ) ) {
			return;
		}
		if ( '' !== (string) get_post_meta( $post->ID, self::META_URN, true ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK, array( (int) $post->ID ) ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK, array( (int) $post->ID ) );
		}
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Cron callback: publish the post to the LinkedIn organization.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function publish_to_linkedin( $post_id ) {
		$post_id = absint( $post_id );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status || ! $this->is_configured() ) {
			return;
		}
		if ( '' !== (string) get_post_meta( $post_id, self::META_URN, true ) ) {
			return; // anti-duplicate.
		}

		$settings = wpai_publisher_get_settings();
		$org_id   = trim( (string) $settings['linkedin_org_id'] );
		$token    = wpai_publisher_get_linkedin_access_token();
		$link     = (string) get_permalink( $post_id );
		$message  = $this->build_message( $post, (array) $settings );

		$body = array(
			'author'          => 'urn:li:organization:' . $org_id,
			'lifecycleState'  => 'PUBLISHED',
			'specificContent' => array(
				'com.linkedin.ugc.ShareContent' => array(
					'shareCommentary'    => array( 'text' => $message ),
					'shareMediaCategory' => 'ARTICLE',
					'media'              => array(
						array(
							'status'      => 'READY',
							'originalUrl' => $link,
							'title'       => array( 'text' => wp_trim_words( get_the_title( $post_id ), 25, '' ) ),
						),
					),
				),
			),
			'visibility'      => array( 'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC' ),
		);

		$response = wp_remote_post(
			'https://api.linkedin.com/v2/ugcPosts',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization'             => 'Bearer ' . $token,
					'Content-Type'              => 'application/json',
					'X-Restli-Protocol-Version' => '2.0.0',
				),
				'body'    => (string) wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->record_error( $post_id, $response->get_error_message() );
			return;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$urn  = (string) wp_remote_retrieve_header( $response, 'x-restli-id' );
		if ( '' === $urn ) {
			$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$urn     = is_array( $decoded ) ? (string) ( $decoded['id'] ?? '' ) : '';
		}
		if ( $code < 200 || $code >= 300 || '' === $urn ) {
			$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$detail  = is_array( $decoded ) && isset( $decoded['message'] ) ? (string) $decoded['message'] : ( 'HTTP ' . $code );
			$this->record_error( $post_id, $detail );
			return;
		}

		update_post_meta( $post_id, self::META_URN, $urn );
		update_post_meta( $post_id, self::META_DONE, current_time( 'mysql' ) );
		delete_post_meta( $post_id, self::META_ERROR );
		$this->logger->info( __( 'Articolo condiviso su LinkedIn.', 'wp-ai-publisher' ), array( 'source' => 'linkedin', 'event' => 'shared', 'post_id' => $post_id, 'urn' => $urn ) );
	}

	/**
	 * Store and log a share error.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $message Error message.
	 * @return void
	 */
	private function record_error( $post_id, $message ) {
		update_post_meta( $post_id, self::META_ERROR, sanitize_text_field( (string) $message ) );
		$this->logger->warning( __( 'Condivisione LinkedIn non riuscita.', 'wp-ai-publisher' ), array( 'source' => 'linkedin', 'event' => 'share_failed', 'post_id' => absint( $post_id ), 'message' => (string) $message ) );
	}

	/* ---------------------------------------------------------------------
	 * Message
	 * ------------------------------------------------------------------- */

	/**
	 * Build the LinkedIn post text.
	 *
	 * @param WP_Post             $post Post.
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private function build_message( $post, $settings ) {
		$post_id    = (int) $post->ID;
		$title      = get_the_title( $post_id );
		$meta_desc  = (string) get_post_meta( $post_id, '_aioseo_description', true );
		$excerpt    = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : '';
		$link       = (string) get_permalink( $post_id );
		$hashtags   = $this->build_hashtags( $post_id );

		$custom_prompt = trim( (string) get_post_meta( $post_id, '_wpai_social_linkedin', true ) );
		if ( '' !== $custom_prompt ) {
			$caption = $this->generate_ai_caption( $title, $meta_desc ?: $excerpt, $hashtags, $link, $custom_prompt );
			if ( '' !== $caption ) {
				return $caption;
			}
		}
		if ( ! empty( $settings['linkedin_use_ai_caption'] ) ) {
			$caption = $this->generate_ai_caption( $title, $meta_desc ?: $excerpt, $hashtags, $link );
			if ( '' !== $caption ) {
				return $caption;
			}
		}

		$template = (string) ( $settings['linkedin_message_template'] ?? '' );
		if ( '' === trim( $template ) ) {
			$template = "{title}\n\n{meta_description}\n\n{hashtags}\n🔗 {link}";
		}
		$message = strtr(
			$template,
			array(
				'{title}'            => $title,
				'{meta_title}'       => $title,
				'{meta_description}' => '' !== $meta_desc ? $meta_desc : $excerpt,
				'{excerpt}'          => $excerpt,
				'{link}'             => $link,
				'{hashtags}'         => $hashtags,
			)
		);
		$message = preg_replace( "/\n{3,}/", "\n\n", (string) $message );
		return trim( (string) $message );
	}

	/**
	 * Build a hashtag string from the post tags.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function build_hashtags( $post_id ) {
		$tags = get_the_tags( $post_id );
		if ( ! is_array( $tags ) ) {
			return '';
		}
		$out = array();
		foreach ( array_slice( $tags, 0, 5 ) as $tag ) {
			$slug = preg_replace( '/[^\p{L}\p{N}]/u', '', (string) $tag->name );
			if ( '' !== $slug ) {
				$out[] = '#' . $slug;
			}
		}
		return implode( ' ', $out );
	}

	/**
	 * Generate a social caption via the AI (best effort).
	 *
	 * @param string $title Title.
	 * @param string $description Description.
	 * @param string $hashtags Hashtags.
	 * @param string $link Link.
	 * @param string $custom_prompt Optional per-article prompt.
	 * @return string
	 */
	private function generate_ai_caption( $title, $description, $hashtags, $link, $custom_prompt = '' ) {
		if ( ! method_exists( $this->ai_provider, 'generate_short_text' ) ) {
			return '';
		}
		if ( '' !== trim( (string) $custom_prompt ) ) {
			$prompt = trim( (string) $custom_prompt ) . "\n\n" . sprintf( 'Contesto: Titolo "%s". Descrizione: %s. NON inserire il link (verrà aggiunto a parte).', $title, $description );
		} else {
			$prompt = sprintf( 'Scrivi un breve testo per un post LinkedIn aziendale che promuove questo articolo. Tono professionale, 2-4 frasi, in italiano. Includi un invito alla lettura. NON inserire il link (verrà aggiunto a parte). Titolo: %s. Descrizione: %s. Hashtag suggeriti: %s.', $title, $description, $hashtags );
		}
		$result = $this->ai_provider->generate_short_text( $prompt, __( 'Sei un social media manager. Rispondi solo con il testo del post.', 'wp-ai-publisher' ) );
		if ( is_wp_error( $result ) ) {
			return '';
		}
		$caption = trim( (string) $result );
		if ( '' !== $hashtags && false === strpos( $caption, '#' ) ) {
			$caption .= "\n\n" . $hashtags;
		}
		$caption .= "\n🔗 " . $link;
		return $caption;
	}

	/* ---------------------------------------------------------------------
	 * Test connection
	 * ------------------------------------------------------------------- */

	/**
	 * Admin action: verify the LinkedIn organization connection.
	 *
	 * @return void
	 */
	public function handle_test_connection() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		check_admin_referer( 'wpai_publisher_linkedin_test' );

		$settings = wpai_publisher_get_settings();
		$org_id   = trim( (string) ( $settings['linkedin_org_id'] ?? '' ) );
		$token    = wpai_publisher_get_linkedin_access_token();
		if ( '' === $org_id || '' === $token ) {
			$this->finish_test( __( 'Configura l’ID organizzazione e il token (costante WPAIP_LINKEDIN_ACCESS_TOKEN).', 'wp-ai-publisher' ), false );
		}

		$response = wp_remote_get(
			'https://api.linkedin.com/v2/organizations/' . rawurlencode( $org_id ) . '?projection=(id,localizedName)',
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Bearer ' . $token, 'X-Restli-Protocol-Version' => '2.0.0' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			$this->finish_test( $response->get_error_message(), false );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) || isset( $data['message'] ) ) {
			$detail = is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : ( 'HTTP ' . $code );
			$this->finish_test( sprintf( __( 'Connessione fallita: %s', 'wp-ai-publisher' ), $detail ), false );
		}
		$name = (string) ( $data['localizedName'] ?? '' );
		$this->finish_test( sprintf( __( '✅ Connessa all’organizzazione: %s', 'wp-ai-publisher' ), '' !== $name ? $name : $org_id ), true );
	}

	/**
	 * Store the test result and redirect to settings.
	 *
	 * @param string $message Message.
	 * @param bool   $ok Success.
	 * @return void
	 */
	private function finish_test( $message, $ok ) {
		set_transient(
			'wpai_publisher_linkedin_notice_' . get_current_user_id(),
			array( 'ok' => (bool) $ok, 'message' => sanitize_text_field( (string) $message ) ),
			60
		);
		wp_safe_redirect( add_query_arg( 'wpai_notice', 'linkedin_test', admin_url( 'admin.php?page=wp-ai-publisher-settings' ) ) );
		exit;
	}
}
