<?php
/**
 * Instagram (Business) auto-publishing on article publish.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publishes a post's featured image to an Instagram Business account on publish.
 *
 * Mirrors {@see Facebook_Integration}: a per-post checkbox opts a post in and
 * the share runs on WP-Cron (non-blocking, anti-duplicate). Instagram requires
 * an image (the post must have a featured image) and links in the caption are
 * not clickable, so the permalink is appended as text. Publishing uses the
 * two-step Graph API flow: create a media container, then publish it.
 */
class Instagram_Integration {

	const CRON_HOOK      = 'wpai_publisher_instagram_publish';
	const META_SHARE     = '_wpai_ig_share';
	const META_DONE      = '_wpai_ig_shared_at';
	const META_MEDIA_ID  = '_wpai_ig_media_id';
	const META_PERMALINK = '_wpai_ig_permalink';
	const META_ERROR     = '_wpai_ig_error';
	const GRAPH_VERSION  = 'v19.0';

	/**
	 * Logger service.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * AI provider adapter (for optional AI captions).
	 *
	 * @var AI_Provider_Adapter
	 */
	private $ai_provider;

	/**
	 * Constructor.
	 *
	 * @param Logger              $logger Logger service.
	 * @param AI_Provider_Adapter $ai_provider AI provider adapter.
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
		add_action( self::CRON_HOOK, array( $this, 'publish_to_instagram' ), 10, 1 );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_post_wpai_publisher_instagram_test', array( $this, 'handle_test_connection' ) );
	}

	/**
	 * Whether the integration is enabled and minimally configured.
	 *
	 * @return bool
	 */
	private function is_configured() {
		$settings = wpai_publisher_get_settings();
		return ! empty( $settings['instagram_enabled'] )
			&& '' !== trim( (string) ( $settings['instagram_user_id'] ?? '' ) )
			&& '' !== wpai_publisher_get_instagram_access_token();
	}

	/**
	 * Default value for the per-post "share" checkbox.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function default_share_for_post( $post_id ) {
		$settings = wpai_publisher_get_settings();
		if ( empty( $settings['instagram_default_share'] ) ) {
			return false;
		}
		return '1' === (string) get_post_meta( $post_id, '_wpai_publisher_generated', true );
	}

	/**
	 * Register the editor meta box.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box(
			'wpai-instagram-share',
			__( 'WP AI Publisher — Instagram', 'wp-ai-publisher' ),
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
		wp_nonce_field( 'wpai_publisher_ig_meta', 'wpai_publisher_ig_meta_nonce' );

		echo '<label><input type="checkbox" name="wpai_ig_share" value="1" ' . checked( $checked, true, false ) . '> ' . esc_html__( 'Condividi su Instagram alla pubblicazione', 'wp-ai-publisher' ) . '</label>';

		if ( ! $this->is_configured() ) {
			echo '<p class="description">' . esc_html__( 'Configura l’account Instagram e il token in Impostazioni → Instagram per abilitare la condivisione.', 'wp-ai-publisher' ) . '</p>';
		}
		if ( ! has_post_thumbnail( $post_id ) ) {
			echo '<p class="description">' . esc_html__( 'Instagram richiede un’immagine: imposta l’immagine in evidenza, altrimenti la condivisione verrà saltata.', 'wp-ai-publisher' ) . '</p>';
		}

		$done      = (string) get_post_meta( $post_id, self::META_DONE, true );
		$media_id  = (string) get_post_meta( $post_id, self::META_MEDIA_ID, true );
		$permalink = (string) get_post_meta( $post_id, self::META_PERMALINK, true );
		$ig_err    = (string) get_post_meta( $post_id, self::META_ERROR, true );
		if ( '' !== $media_id ) {
			$url = '' !== $permalink ? $permalink : 'https://www.instagram.com/';
			echo '<p class="description">' . esc_html__( 'Pubblicato su Instagram:', 'wp-ai-publisher' ) . ' <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( '' !== $permalink ? __( 'apri post', 'wp-ai-publisher' ) : $media_id ) . '</a>';
			if ( '' !== $done ) {
				echo ' <span>(' . esc_html( $done ) . ')</span>';
			}
			echo '</p>';
		} elseif ( '' !== $ig_err ) {
			echo '<p class="description" style="color:#b32d2e;">' . esc_html__( 'Ultimo errore:', 'wp-ai-publisher' ) . ' ' . esc_html( $ig_err ) . '</p>';
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
		if ( ! isset( $_POST['wpai_publisher_ig_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpai_publisher_ig_meta_nonce'] ) ), 'wpai_publisher_ig_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( $post instanceof WP_Post && 'post' !== $post->post_type ) {
			return;
		}
		update_post_meta( $post_id, self::META_SHARE, isset( $_POST['wpai_ig_share'] ) ? '1' : '0' );
	}

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
		if ( '' !== (string) get_post_meta( $post->ID, self::META_MEDIA_ID, true ) ) {
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
	 * Cron callback: publish the post's featured image to Instagram.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function publish_to_instagram( $post_id ) {
		$post_id = absint( $post_id );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status ) {
			return;
		}
		if ( ! $this->is_configured() ) {
			return;
		}
		// Anti-duplicate lock.
		if ( '' !== (string) get_post_meta( $post_id, self::META_MEDIA_ID, true ) ) {
			return;
		}

		$image_url = '';
		if ( has_post_thumbnail( $post_id ) ) {
			$image_url = (string) wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'large' );
		}
		if ( '' === $image_url ) {
			$this->record_error( $post_id, __( 'Immagine in evidenza mancante: Instagram richiede un’immagine.', 'wp-ai-publisher' ) );
			return;
		}

		$settings = wpai_publisher_get_settings();
		$ig_user  = trim( (string) $settings['instagram_user_id'] );
		$token    = wpai_publisher_get_instagram_access_token();
		$caption  = $this->build_caption( $post, (array) $settings );

		// Step 1: create the media container.
		$container = wp_remote_post(
			'https://graph.facebook.com/' . self::GRAPH_VERSION . '/' . $ig_user . '/media',
			array(
				'timeout' => 45,
				'body'    => array( 'image_url' => $image_url, 'caption' => $caption, 'access_token' => $token ),
			)
		);
		$creation_id = $this->extract_id( $container, $post_id );
		if ( '' === $creation_id ) {
			return;
		}

		// Step 2: publish the container.
		$publish = wp_remote_post(
			'https://graph.facebook.com/' . self::GRAPH_VERSION . '/' . $ig_user . '/media_publish',
			array(
				'timeout' => 45,
				'body'    => array( 'creation_id' => $creation_id, 'access_token' => $token ),
			)
		);
		$media_id = $this->extract_id( $publish, $post_id );
		if ( '' === $media_id ) {
			return;
		}

		update_post_meta( $post_id, self::META_MEDIA_ID, $media_id );
		update_post_meta( $post_id, self::META_DONE, current_time( 'mysql' ) );
		delete_post_meta( $post_id, self::META_ERROR );

		$permalink = $this->fetch_permalink( $media_id, $token );
		if ( '' !== $permalink ) {
			update_post_meta( $post_id, self::META_PERMALINK, $permalink );
		}

		$this->logger->info( __( 'Articolo condiviso su Instagram.', 'wp-ai-publisher' ), array( 'source' => 'instagram', 'event' => 'shared', 'post_id' => $post_id, 'ig_media_id' => $media_id ) );
	}

	/**
	 * Extract an id from a Graph API response, recording errors on failure.
	 *
	 * @param array|\WP_Error $response Response.
	 * @param int             $post_id Post ID.
	 * @return string Id or empty string on failure.
	 */
	private function extract_id( $response, $post_id ) {
		if ( is_wp_error( $response ) ) {
			$this->record_error( $post_id, $response->get_error_message() );
			return '';
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$detail = is_array( $data ) && isset( $data['error']['message'] ) ? (string) $data['error']['message'] : ( 'HTTP ' . $code );
			$this->record_error( $post_id, $detail );
			return '';
		}
		$id = (string) ( $data['id'] ?? '' );
		if ( '' === $id ) {
			$this->record_error( $post_id, __( 'Risposta Instagram senza ID.', 'wp-ai-publisher' ) );
		}
		return $id;
	}

	/**
	 * Fetch the public permalink of a published media (best effort).
	 *
	 * @param string $media_id Media ID.
	 * @param string $token Access token.
	 * @return string
	 */
	private function fetch_permalink( $media_id, $token ) {
		$response = wp_remote_get(
			add_query_arg(
				array( 'fields' => 'permalink', 'access_token' => $token ),
				'https://graph.facebook.com/' . self::GRAPH_VERSION . '/' . rawurlencode( $media_id )
			),
			array( 'timeout' => 20 )
		);
		if ( is_wp_error( $response ) ) {
			return '';
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? (string) ( $data['permalink'] ?? '' ) : '';
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
		$this->logger->warning( __( 'Condivisione Instagram non riuscita.', 'wp-ai-publisher' ), array( 'source' => 'instagram', 'event' => 'share_failed', 'post_id' => absint( $post_id ), 'message' => (string) $message ) );
	}

	/**
	 * Build the caption for the Instagram post.
	 *
	 * @param WP_Post             $post Post.
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private function build_caption( $post, $settings ) {
		$post_id    = (int) $post->ID;
		$title      = get_the_title( $post_id );
		$meta_title = (string) get_post_meta( $post_id, '_aioseo_title', true );
		$meta_desc  = (string) get_post_meta( $post_id, '_aioseo_description', true );
		$excerpt    = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : '';
		$link       = (string) get_permalink( $post_id );
		$hashtags   = $this->build_hashtags( $post_id );

		$custom_prompt = trim( (string) get_post_meta( $post_id, '_wpai_social_instagram', true ) );
		if ( '' !== $custom_prompt && method_exists( $this->ai_provider, 'generate_short_text' ) ) {
			$caption = $this->generate_ai_caption( $title, $meta_desc ? $meta_desc : $excerpt, $hashtags, $link, $custom_prompt );
			if ( '' !== $caption ) {
				return $this->trim_caption( $caption );
			}
		}
		if ( ! empty( $settings['instagram_use_ai_caption'] ) && method_exists( $this->ai_provider, 'generate_short_text' ) ) {
			$caption = $this->generate_ai_caption( $title, $meta_desc ? $meta_desc : $excerpt, $hashtags, $link );
			if ( '' !== $caption ) {
				return $this->trim_caption( $caption );
			}
		}

		$template = (string) ( $settings['instagram_caption_template'] ?? '' );
		if ( '' === trim( $template ) ) {
			$template = "{title}\n\n{meta_description}\n\n{hashtags}\n\n🔗 {link}";
		}
		$replacements = array(
			'{title}'            => $title,
			'{meta_title}'       => '' !== $meta_title ? $meta_title : $title,
			'{meta_description}' => '' !== $meta_desc ? $meta_desc : $excerpt,
			'{excerpt}'          => $excerpt,
			'{link}'             => $link,
			'{hashtags}'         => $hashtags,
		);
		$caption = strtr( $template, $replacements );
		$caption = preg_replace( "/\n{3,}/", "\n\n", (string) $caption );
		return $this->trim_caption( trim( (string) $caption ) );
	}

	/**
	 * Enforce Instagram's caption length limit (2200 chars).
	 *
	 * @param string $caption Caption.
	 * @return string
	 */
	private function trim_caption( $caption ) {
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $caption ) > 2200 ) {
			return mb_substr( $caption, 0, 2200 );
		}
		return $caption;
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
		foreach ( array_slice( $tags, 0, 10 ) as $tag ) {
			$slug = preg_replace( '/[^\p{L}\p{N}]/u', '', (string) $tag->name );
			if ( '' !== $slug ) {
				$out[] = '#' . $slug;
			}
		}
		return implode( ' ', $out );
	}

	/**
	 * Generate a caption via the AI (best effort).
	 *
	 * @param string $title Title.
	 * @param string $description Description.
	 * @param string $hashtags Hashtags.
	 * @param string $link Link.
	 * @return string Caption or empty string on failure.
	 */
	private function generate_ai_caption( $title, $description, $hashtags, $link, $custom_prompt = '' ) {
		if ( '' !== trim( (string) $custom_prompt ) ) {
			$prompt = trim( (string) $custom_prompt ) . "\n\n" . sprintf(
				"Contesto: Titolo \"%s\". Descrizione: %s. Su Instagram i link non sono cliccabili: NON scrivere l'URL (verrà aggiunto a parte).",
				$title,
				$description
			);
		} else {
			$prompt = sprintf(
				"Scrivi una caption per un post Instagram che promuove questo articolo. Tono coinvolgente, in italiano, 2-4 frasi, con qualche emoji pertinente e una call-to-action. Su Instagram i link non sono cliccabili: invita a leggere l'articolo dal link in bio o dal sito. NON scrivere l'URL (verrà aggiunto a parte). Titolo: %s. Descrizione: %s. Hashtag suggeriti: %s.",
				$title,
				$description,
				$hashtags
			);
		}
		$result = $this->ai_provider->generate_short_text( $prompt, __( 'Sei un social media manager esperto di Instagram. Rispondi solo con il testo della caption.', 'wp-ai-publisher' ) );
		if ( is_wp_error( $result ) ) {
			$this->logger->warning( __( 'Caption AI per Instagram non generata; uso il template.', 'wp-ai-publisher' ), array( 'source' => 'instagram', 'event' => 'ai_caption_failed', 'message' => $result->get_error_message() ) );
			return '';
		}
		$caption = trim( (string) $result );
		if ( '' !== $hashtags && false === strpos( $caption, '#' ) ) {
			$caption .= "\n\n" . $hashtags;
		}
		$caption .= "\n\n🔗 " . $link;
		return $caption;
	}

	/**
	 * Admin action: verify the Instagram account connection.
	 *
	 * @return void
	 */
	public function handle_test_connection() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		check_admin_referer( 'wpai_publisher_instagram_test' );

		$settings = wpai_publisher_get_settings();
		$ig_user  = trim( (string) ( $settings['instagram_user_id'] ?? '' ) );
		$token    = wpai_publisher_get_instagram_access_token();

		if ( '' === $ig_user || '' === $token ) {
			$this->finish_test( __( 'Configura l’ID account Instagram e il token (costante WPAIP_INSTAGRAM_ACCESS_TOKEN o quella di Facebook).', 'wp-ai-publisher' ), false );
		}

		$response = wp_remote_get(
			add_query_arg(
				array( 'fields' => 'username,followers_count', 'access_token' => $token ),
				'https://graph.facebook.com/' . self::GRAPH_VERSION . '/' . rawurlencode( $ig_user )
			),
			array( 'timeout' => 20 )
		);
		if ( is_wp_error( $response ) ) {
			$this->finish_test( $response->get_error_message(), false );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || isset( $data['error'] ) ) {
			$detail = is_array( $data ) && isset( $data['error']['message'] ) ? (string) $data['error']['message'] : __( 'risposta non valida', 'wp-ai-publisher' );
			$this->finish_test( sprintf( __( 'Connessione fallita: %s', 'wp-ai-publisher' ), $detail ), false );
		}
		$username = (string) ( $data['username'] ?? '' );
		$this->finish_test( sprintf( __( '✅ Connesso all’account Instagram: @%s', 'wp-ai-publisher' ), '' !== $username ? $username : $ig_user ), true );
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
			'wpai_publisher_instagram_notice_' . get_current_user_id(),
			array( 'ok' => (bool) $ok, 'message' => sanitize_text_field( (string) $message ) ),
			60
		);
		wp_safe_redirect( add_query_arg( 'wpai_notice', 'instagram_test', admin_url( 'admin.php?page=wp-ai-publisher-settings' ) ) );
		exit;
	}
}
