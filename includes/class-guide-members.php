<?php
/**
 * Front-end membership for the Guide Assistant: dedicated role, registration,
 * login and a public "my guides" area. Members never reach wp-admin.
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
 * Guide membership manager.
 */
class Guide_Members {

	const ROLE        = 'wpai_guide_member';
	const META_SAVED  = '_wpai_guide_saved';
	const NONCE_REG   = 'wpai_guide_register';

	/**
	 * DB service.
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * Guide assistant (config + table access).
	 *
	 * @var Guide_Assistant
	 */
	private $assistant;

	/**
	 * Constructor.
	 *
	 * @param DB              $db DB service.
	 * @param Guide_Assistant $assistant Guide assistant.
	 */
	public function __construct( DB $db, Guide_Assistant $assistant ) {
		$this->db        = $db;
		$this->assistant = $assistant;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'maybe_create_role' ) );

		add_shortcode( 'wpai_guide_register', array( $this, 'render_register' ) );
		add_shortcode( 'wpai_guide_login', array( $this, 'render_login' ) );
		add_shortcode( 'wpai_guide_account', array( $this, 'render_account' ) );

		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );

		add_action( 'admin_post_nopriv_wpai_guide_register', array( $this, 'handle_register' ) );
		add_action( 'admin_post_wpai_guide_register', array( $this, 'handle_register' ) );
		add_action( 'admin_post_wpai_guide_member_remove', array( $this, 'handle_remove_saved' ) );

		// Keep members out of wp-admin and hide the toolbar for them.
		add_action( 'admin_init', array( $this, 'block_admin_access' ) );
		add_action( 'after_setup_theme', array( $this, 'maybe_hide_admin_bar' ) );
	}

	/* ---------------------------------------------------------------------
	 * Role + admin lockout
	 * ------------------------------------------------------------------- */

	/**
	 * Create the member role once.
	 *
	 * @return void
	 */
	public function maybe_create_role() {
		if ( get_role( self::ROLE ) ) {
			return;
		}
		add_role( self::ROLE, __( 'Membro Guide', 'wp-ai-publisher' ), array( 'read' => true ) );
	}

	/**
	 * Whether the current user is a guide member (and nothing more privileged).
	 *
	 * @return bool
	 */
	private function current_user_is_member() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$user = wp_get_current_user();
		return in_array( self::ROLE, (array) $user->roles, true ) && ! user_can( $user, 'edit_posts' );
	}

	/**
	 * Redirect members away from wp-admin to their public account area.
	 *
	 * @return void
	 */
	public function block_admin_access() {
		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ! $this->current_user_is_member() ) {
			return;
		}
		$config  = $this->assistant->get_config();
		$account = absint( $config['account_page_id'] );
		$target  = $account ? get_permalink( $account ) : home_url( '/' );
		wp_safe_redirect( $target ? $target : home_url( '/' ) );
		exit;
	}

	/**
	 * Hide the admin bar for members.
	 *
	 * @return void
	 */
	public function maybe_hide_admin_bar() {
		if ( $this->current_user_is_member() ) {
			show_admin_bar( false );
		}
	}

	/* ---------------------------------------------------------------------
	 * Shortcodes
	 * ------------------------------------------------------------------- */

	/**
	 * Registration form.
	 *
	 * @return string
	 */
	public function render_register() {
		$config = $this->assistant->get_config();
		if ( empty( $config['enable_membership'] ) ) {
			return '';
		}
		if ( is_user_logged_in() ) {
			return $this->logged_in_box( $config );
		}

		$notice = $this->get_notice();
		ob_start();
		?>
		<div class="wpai-guide-auth">
			<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_html below. ?>
			<form class="wpai-guide-auth__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpai_guide_register">
				<?php wp_nonce_field( self::NONCE_REG ); ?>
				<p><label><?php echo esc_html__( 'Nome', 'wp-ai-publisher' ); ?><br><input type="text" name="display_name" required></label></p>
				<p><label><?php echo esc_html__( 'Email', 'wp-ai-publisher' ); ?><br><input type="email" name="email" required></label></p>
				<p><label><?php echo esc_html__( 'Password', 'wp-ai-publisher' ); ?><br><input type="password" name="password" autocomplete="new-password" required></label></p>
				<input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;">
				<p><button type="submit" class="wpai-guide-auth__submit"><?php echo esc_html__( 'Registrati', 'wp-ai-publisher' ); ?></button></p>
			</form>
			<?php if ( absint( $config['login_page_id'] ) ) : ?>
				<p class="wpai-guide-auth__alt"><?php echo esc_html__( 'Hai già un account?', 'wp-ai-publisher' ); ?> <a href="<?php echo esc_url( get_permalink( absint( $config['login_page_id'] ) ) ); ?>"><?php echo esc_html__( 'Accedi', 'wp-ai-publisher' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Login form.
	 *
	 * @return string
	 */
	public function render_login() {
		$config = $this->assistant->get_config();
		if ( empty( $config['enable_membership'] ) ) {
			return '';
		}
		if ( is_user_logged_in() ) {
			return $this->logged_in_box( $config );
		}

		$account = absint( $config['account_page_id'] );
		ob_start();
		?>
		<div class="wpai-guide-auth">
			<?php echo $this->get_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			wp_login_form(
				array(
					'redirect'       => $account ? get_permalink( $account ) : home_url( '/' ),
					'label_username' => __( 'Email o nome utente', 'wp-ai-publisher' ),
					'label_log_in'   => __( 'Accedi', 'wp-ai-publisher' ),
				)
			);
			?>
			<?php if ( absint( $config['register_page_id'] ) ) : ?>
				<p class="wpai-guide-auth__alt"><?php echo esc_html__( 'Non hai un account?', 'wp-ai-publisher' ); ?> <a href="<?php echo esc_url( get_permalink( absint( $config['register_page_id'] ) ) ); ?>"><?php echo esc_html__( 'Registrati', 'wp-ai-publisher' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Account area: list of the user's saved guides.
	 *
	 * @return string
	 */
	public function render_account() {
		$config = $this->assistant->get_config();
		if ( empty( $config['enable_membership'] ) ) {
			return '';
		}
		if ( ! is_user_logged_in() ) {
			$login = absint( $config['login_page_id'] ) ?: absint( $config['register_page_id'] );
			$url   = $login ? get_permalink( $login ) : wp_login_url();
			return '<div class="wpai-guide-auth"><p>' . esc_html__( 'Accedi per vedere le tue guide.', 'wp-ai-publisher' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Accedi', 'wp-ai-publisher' ) . '</a></p></div>';
		}

		$user  = wp_get_current_user();
		$rows  = $this->get_saved_guides( $user->ID );
		$this->enqueue_account_assets();

		ob_start();
		?>
		<div class="wpai-guide-account">
			<div class="wpai-guide-account__head">
				<span><?php echo esc_html( sprintf( __( 'Ciao %s', 'wp-ai-publisher' ), $user->display_name ) ); ?></span>
				<a class="wpai-guide-account__logout" href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>"><?php echo esc_html__( 'Esci', 'wp-ai-publisher' ); ?></a>
			</div>
			<h3><?php echo esc_html__( 'Le tue guide', 'wp-ai-publisher' ); ?></h3>
			<?php if ( empty( $rows ) ) : ?>
				<p><?php echo esc_html__( 'Non hai ancora salvato nessuna guida.', 'wp-ai-publisher' ); ?></p>
			<?php else : ?>
				<ul class="wpai-guide-account__list">
					<?php foreach ( $rows as $row ) : ?>
						<li class="wpai-guide-account__item">
							<details>
								<summary>
									<strong><?php echo esc_html( wp_trim_words( (string) $row->query, 18 ) ); ?></strong>
									<span class="wpai-guide-account__date"><?php echo esc_html( mysql2date( 'd/m/Y', (string) $row->created_at ) ); ?></span>
								</summary>
								<div class="wpai-guide wpai-guide__content"><?php echo wp_kses_post( (string) $row->result_html ); ?></div>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Rimuovere questa guida dalla tua area?', 'wp-ai-publisher' ) ); ?>');">
									<input type="hidden" name="action" value="wpai_guide_member_remove">
									<input type="hidden" name="request_id" value="<?php echo esc_attr( (string) $row->id ); ?>">
									<?php wp_nonce_field( 'wpai_guide_member_remove_' . (int) $row->id ); ?>
									<button type="submit" class="wpai-guide-account__remove"><?php echo esc_html__( 'Rimuovi', 'wp-ai-publisher' ); ?></button>
								</form>
							</details>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Small box shown to already-logged-in users on the auth pages.
	 *
	 * @param array<string,mixed> $config Config.
	 * @return string
	 */
	private function logged_in_box( $config ) {
		$account = absint( $config['account_page_id'] );
		$url     = $account ? get_permalink( $account ) : home_url( '/' );
		return '<div class="wpai-guide-auth"><p>' . esc_html__( 'Hai già effettuato l’accesso.', 'wp-ai-publisher' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Vai alle tue guide', 'wp-ai-publisher' ) . '</a></p></div>';
	}

	/**
	 * Render a transient notice (set after a failed/successful auth action).
	 *
	 * @return string
	 */
	private function get_notice() {
		$code = isset( $_GET['wpai_auth'] ) ? sanitize_key( wp_unslash( $_GET['wpai_auth'] ) ) : '';
		if ( '' === $code ) {
			return '';
		}
		$messages = array(
			'exists'   => array( 'error', __( 'Esiste già un account con questa email.', 'wp-ai-publisher' ) ),
			'invalid'  => array( 'error', __( 'Controlla i dati inseriti e riprova.', 'wp-ai-publisher' ) ),
			'weak'     => array( 'error', __( 'La password è troppo corta (minimo 8 caratteri).', 'wp-ai-publisher' ) ),
			'disabled' => array( 'error', __( 'Le registrazioni non sono attive.', 'wp-ai-publisher' ) ),
		);
		if ( ! isset( $messages[ $code ] ) ) {
			return '';
		}
		return '<div class="wpai-guide-auth__notice wpai-guide-auth__notice--' . esc_attr( $messages[ $code ][0] ) . '">' . esc_html( $messages[ $code ][1] ) . '</div>';
	}

	/* ---------------------------------------------------------------------
	 * Registration handler
	 * ------------------------------------------------------------------- */

	/**
	 * Handle the front-end registration form.
	 *
	 * @return void
	 */
	public function handle_register() {
		$config = $this->assistant->get_config();
		$register_page = absint( $config['register_page_id'] );
		$redirect_base = $register_page ? get_permalink( $register_page ) : home_url( '/' );

		if ( empty( $config['enable_membership'] ) ) {
			$this->redirect_auth( $redirect_base, 'disabled' );
		}
		check_admin_referer( self::NONCE_REG );

		// Honeypot.
		if ( '' !== trim( (string) ( $_POST['website'] ?? '' ) ) ) {
			$this->redirect_auth( $redirect_base, 'invalid' );
		}

		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$name  = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
		$pass  = (string) ( $_POST['password'] ?? '' );

		if ( '' === $email || ! is_email( $email ) || '' === $name ) {
			$this->redirect_auth( $redirect_base, 'invalid' );
		}
		if ( strlen( $pass ) < 8 ) {
			$this->redirect_auth( $redirect_base, 'weak' );
		}
		if ( email_exists( $email ) ) {
			$this->redirect_auth( $redirect_base, 'exists' );
		}

		$username = $this->unique_username_from_email( $email );
		$user_id  = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $name,
				'first_name'   => $name,
				'role'         => self::ROLE,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			$this->redirect_auth( $redirect_base, 'invalid' );
		}

		// Log the new member in.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		$account = absint( $config['account_page_id'] );
		$target  = $account ? get_permalink( $account ) : $redirect_base;
		wp_safe_redirect( $target ? $target : home_url( '/' ) );
		exit;
	}

	/**
	 * Build a unique username from an email local-part.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	private function unique_username_from_email( $email ) {
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		if ( '' === $base ) {
			$base = 'membro';
		}
		$username = $base;
		$i        = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $i;
			$i++;
		}
		return $username;
	}

	/**
	 * Redirect back to an auth page with a notice code.
	 *
	 * @param string $base Base URL.
	 * @param string $code Notice code.
	 * @return void
	 */
	private function redirect_auth( $base, $code ) {
		wp_safe_redirect( add_query_arg( 'wpai_auth', $code, $base ? $base : home_url( '/' ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Saved guides (REST + storage)
	 * ------------------------------------------------------------------- */

	/**
	 * Register the save REST route.
	 *
	 * @return void
	 */
	public function register_rest_route() {
		register_rest_route(
			Guide_Assistant::REST_NAMESPACE,
			'/guide/save',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_save_guide' ),
				'permission_callback' => static function () {
					return is_user_logged_in();
				},
				'args'                => array(
					'request_id' => array( 'type' => 'integer', 'required' => true ),
				),
			)
		);
	}

	/**
	 * Save a guide to the current user's area.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_save_guide( WP_REST_Request $request ) {
		$config = $this->assistant->get_config();
		if ( empty( $config['enable_membership'] ) ) {
			return new WP_Error( 'wpai_guide_membership_off', __( 'Funzione non disponibile.', 'wp-ai-publisher' ), array( 'status' => 403 ) );
		}

		$request_id = absint( $request->get_param( 'request_id' ) );
		if ( $request_id <= 0 || ! $this->assistant->get_request( $request_id ) ) {
			return new WP_Error( 'wpai_guide_not_found', __( 'Guida non trovata.', 'wp-ai-publisher' ), array( 'status' => 404 ) );
		}

		$user_id = get_current_user_id();
		$saved   = $this->get_saved_ids( $user_id );
		if ( ! in_array( $request_id, $saved, true ) ) {
			$saved[] = $request_id;
			update_user_meta( $user_id, self::META_SAVED, array_values( array_unique( array_map( 'absint', $saved ) ) ) );
		}

		$account = absint( $config['account_page_id'] );
		return new WP_REST_Response(
			array(
				'saved'      => true,
				'accountUrl' => $account ? get_permalink( $account ) : '',
			),
			200
		);
	}

	/**
	 * Get saved request IDs for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,int>
	 */
	private function get_saved_ids( $user_id ) {
		$saved = get_user_meta( $user_id, self::META_SAVED, true );
		return is_array( $saved ) ? array_map( 'absint', $saved ) : array();
	}

	/**
	 * Get saved guide rows for a user (most recent first).
	 *
	 * @param int $user_id User ID.
	 * @return array<int,object>
	 */
	private function get_saved_guides( $user_id ) {
		$ids = $this->get_saved_ids( $user_id );
		if ( empty( $ids ) ) {
			return array();
		}
		global $wpdb;
		$table        = $this->db->get_guide_requests_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built from integer count.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, query, result_html, created_at FROM {$table} WHERE id IN ({$placeholders}) ORDER BY id DESC", $ids ) );
		return (array) $rows;
	}

	/**
	 * Remove a saved guide from the user's area.
	 *
	 * @return void
	 */
	public function handle_remove_saved() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		$request_id = absint( $_POST['request_id'] ?? 0 );
		check_admin_referer( 'wpai_guide_member_remove_' . $request_id );

		$user_id = get_current_user_id();
		$saved   = array_diff( $this->get_saved_ids( $user_id ), array( $request_id ) );
		update_user_meta( $user_id, self::META_SAVED, array_values( $saved ) );

		$config  = $this->assistant->get_config();
		$account = absint( $config['account_page_id'] );
		wp_safe_redirect( $account ? get_permalink( $account ) : home_url( '/' ) );
		exit;
	}

	/**
	 * Enqueue the shared guide stylesheet on the account page (for rendered guides).
	 *
	 * @return void
	 */
	private function enqueue_account_assets() {
		wp_enqueue_style( 'wpai-guide', WPAIP_PLUGIN_URL . 'public/css/guide.css', array(), WPAIP_VERSION );
	}
}
