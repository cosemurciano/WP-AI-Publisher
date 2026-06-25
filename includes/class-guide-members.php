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
	 * Read a "save this guide after auth" intent from the URL (?wpai_save=ID).
	 *
	 * @return array{id:int,title:string}|null
	 */
	private function get_save_intent() {
		$id = isset( $_GET['wpai_save'] ) ? absint( $_GET['wpai_save'] ) : 0;
		if ( $id <= 0 ) {
			return null;
		}
		$request = $this->assistant->get_request( $id );
		if ( ! $request ) {
			return null;
		}
		return array( 'id' => $id, 'title' => wp_trim_words( (string) $request->query, 12, '…' ) );
	}

	/**
	 * Append the save-intent query arg to a URL when present.
	 *
	 * @param string                          $url URL.
	 * @param array{id:int,title:string}|null $intent Intent.
	 * @return string
	 */
	private function with_intent( $url, $intent ) {
		return $intent ? add_query_arg( 'wpai_save', (int) $intent['id'], $url ) : $url;
	}

	/**
	 * Enqueue the shared stylesheet on membership pages.
	 *
	 * @return void
	 */
	private function enqueue_auth_assets() {
		wp_enqueue_style( 'wpai-guide', WPAIP_PLUGIN_URL . 'public/css/guide.css', array(), WPAIP_VERSION );
	}

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
		$this->enqueue_auth_assets();

		$intent    = $this->get_save_intent();
		$login_url = absint( $config['login_page_id'] ) ? get_permalink( absint( $config['login_page_id'] ) ) : '';
		$title     = $intent ? __( 'Crea il tuo account e salva le tue guide', 'wp-ai-publisher' ) : __( 'Crea il tuo account', 'wp-ai-publisher' );
		$sub       = __( 'Registrati gratis per salvare le tue guide e ritrovarle quando vuoi nella tua area personale.', 'wp-ai-publisher' );

		ob_start();
		?>
		<div class="wpai-guide-auth">
			<div class="wpai-guide-auth__card">
				<span class="wpai-guide-auth__badge"><span class="wpai-guide-auth__badge-dot"></span><?php echo esc_html__( 'Area personale', 'wp-ai-publisher' ); ?></span>
				<h2 class="wpai-guide-auth__title"><?php echo esc_html( $title ); ?></h2>
				<p class="wpai-guide-auth__sub"><?php echo esc_html( $sub ); ?></p>
				<?php if ( $intent ) : ?>
					<div class="wpai-guide-auth__banner"><?php echo esc_html( sprintf( __( 'La guida “%s” verrà salvata nella tua area subito dopo la registrazione.', 'wp-ai-publisher' ), $intent['title'] ) ); ?></div>
				<?php endif; ?>
				<?php echo $this->get_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<form class="wpai-guide-auth__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wpai_guide_register">
					<?php if ( $intent ) : ?><input type="hidden" name="wpai_save" value="<?php echo esc_attr( (string) $intent['id'] ); ?>"><?php endif; ?>
					<?php wp_nonce_field( self::NONCE_REG ); ?>
					<label class="wpai-guide-auth__field">
						<span class="wpai-guide-auth__label"><?php echo esc_html__( 'Nome', 'wp-ai-publisher' ); ?></span>
						<input type="text" name="display_name" autocomplete="name" required>
					</label>
					<label class="wpai-guide-auth__field">
						<span class="wpai-guide-auth__label"><?php echo esc_html__( 'Email', 'wp-ai-publisher' ); ?></span>
						<input type="email" name="email" autocomplete="email" required>
					</label>
					<label class="wpai-guide-auth__field">
						<span class="wpai-guide-auth__label"><?php echo esc_html__( 'Password', 'wp-ai-publisher' ); ?></span>
						<input type="password" name="password" autocomplete="new-password" minlength="8" required>
						<span class="wpai-guide-auth__hint"><?php echo esc_html__( 'Almeno 8 caratteri.', 'wp-ai-publisher' ); ?></span>
					</label>
					<input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;">
					<button type="submit" class="wpai-guide-auth__submit"><?php echo esc_html__( 'Crea account', 'wp-ai-publisher' ); ?></button>
				</form>
				<?php if ( '' !== (string) $login_url ) : ?>
					<p class="wpai-guide-auth__alt"><?php echo esc_html__( 'Hai già un account?', 'wp-ai-publisher' ); ?> <a href="<?php echo esc_url( $this->with_intent( $login_url, $intent ) ); ?>"><?php echo esc_html__( 'Accedi', 'wp-ai-publisher' ); ?></a></p>
				<?php endif; ?>
			</div>
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
		$this->enqueue_auth_assets();

		$intent       = $this->get_save_intent();
		$account       = absint( $config['account_page_id'] );
		$account_url   = $account ? get_permalink( $account ) : home_url( '/' );
		$redirect      = $this->with_intent( $account_url, $intent );
		$register_url  = absint( $config['register_page_id'] ) ? get_permalink( absint( $config['register_page_id'] ) ) : '';
		$title         = $intent ? __( 'Accedi e salva la tua guida', 'wp-ai-publisher' ) : __( 'Accedi alla tua area', 'wp-ai-publisher' );

		ob_start();
		?>
		<div class="wpai-guide-auth">
			<div class="wpai-guide-auth__card">
				<span class="wpai-guide-auth__badge"><span class="wpai-guide-auth__badge-dot"></span><?php echo esc_html__( 'Area personale', 'wp-ai-publisher' ); ?></span>
				<h2 class="wpai-guide-auth__title"><?php echo esc_html( $title ); ?></h2>
				<p class="wpai-guide-auth__sub"><?php echo esc_html__( 'Bentornato! Accedi per ritrovare tutte le tue guide salvate.', 'wp-ai-publisher' ); ?></p>
				<?php if ( $intent ) : ?>
					<div class="wpai-guide-auth__banner"><?php echo esc_html( sprintf( __( 'La guida “%s” verrà salvata nella tua area dopo l’accesso.', 'wp-ai-publisher' ), $intent['title'] ) ); ?></div>
				<?php endif; ?>
				<?php echo $this->get_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<form class="wpai-guide-auth__form" method="post" action="<?php echo esc_url( wp_login_url() ); ?>">
					<label class="wpai-guide-auth__field">
						<span class="wpai-guide-auth__label"><?php echo esc_html__( 'Email o nome utente', 'wp-ai-publisher' ); ?></span>
						<input type="text" name="log" autocomplete="username" required>
					</label>
					<label class="wpai-guide-auth__field">
						<span class="wpai-guide-auth__label"><?php echo esc_html__( 'Password', 'wp-ai-publisher' ); ?></span>
						<input type="password" name="pwd" autocomplete="current-password" required>
					</label>
					<label class="wpai-guide-auth__remember"><input type="checkbox" name="rememberme" value="forever"> <?php echo esc_html__( 'Ricordami', 'wp-ai-publisher' ); ?></label>
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>">
					<button type="submit" class="wpai-guide-auth__submit"><?php echo esc_html__( 'Accedi', 'wp-ai-publisher' ); ?></button>
				</form>
				<p class="wpai-guide-auth__alt">
					<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php echo esc_html__( 'Password dimenticata?', 'wp-ai-publisher' ); ?></a>
				</p>
				<?php if ( '' !== (string) $register_url ) : ?>
					<p class="wpai-guide-auth__alt"><?php echo esc_html__( 'Non hai un account?', 'wp-ai-publisher' ); ?> <a href="<?php echo esc_url( $this->with_intent( $register_url, $intent ) ); ?>"><?php echo esc_html__( 'Crea il tuo account', 'wp-ai-publisher' ); ?></a></p>
				<?php endif; ?>
			</div>
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
		$this->enqueue_account_assets();

		if ( ! is_user_logged_in() ) {
			$login_url    = absint( $config['login_page_id'] ) ? get_permalink( absint( $config['login_page_id'] ) ) : wp_login_url();
			$register_url = absint( $config['register_page_id'] ) ? get_permalink( absint( $config['register_page_id'] ) ) : '';
			ob_start();
			?>
			<div class="wpai-guide-auth">
				<div class="wpai-guide-auth__card wpai-guide-auth__card--cta">
					<h2 class="wpai-guide-auth__title"><?php echo esc_html__( 'Le tue guide ti aspettano', 'wp-ai-publisher' ); ?></h2>
					<p class="wpai-guide-auth__sub"><?php echo esc_html__( 'Accedi o crea un account gratuito per salvare le tue guide e ritrovarle qui.', 'wp-ai-publisher' ); ?></p>
					<div class="wpai-guide-auth__cta-row">
						<?php if ( '' !== (string) $register_url ) : ?><a class="wpai-guide-auth__submit" href="<?php echo esc_url( $register_url ); ?>"><?php echo esc_html__( 'Crea il tuo account', 'wp-ai-publisher' ); ?></a><?php endif; ?>
						<a class="wpai-guide-auth__ghost" href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html__( 'Accedi', 'wp-ai-publisher' ); ?></a>
					</div>
				</div>
			</div>
			<?php
			return (string) ob_get_clean();
		}

		$user = wp_get_current_user();

		// Process a pending save intent (after registration/login).
		$saved_flash = '';
		$intent      = $this->get_save_intent();
		if ( $intent && $this->save_request_for_user( $user->ID, $intent['id'] ) ) {
			$saved_flash = sprintf( __( 'Guida “%s” salvata nella tua area.', 'wp-ai-publisher' ), $intent['title'] );
		}

		$rows         = $this->get_saved_guides( $user->ID );
		$generator_id = absint( $config['generator_page_id'] );
		$generator_url = $generator_id ? get_permalink( $generator_id ) : home_url( '/' );

		ob_start();
		?>
		<div class="wpai-guide-account">
			<div class="wpai-guide-account__head">
				<div>
					<h2 class="wpai-guide-account__greet"><?php echo esc_html( sprintf( __( 'Ciao %s', 'wp-ai-publisher' ), $user->display_name ) ); ?></h2>
					<p class="wpai-guide-account__subtitle"><?php echo esc_html__( 'Le tue guide salvate', 'wp-ai-publisher' ); ?></p>
				</div>
				<a class="wpai-guide-account__logout" href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>"><?php echo esc_html__( 'Esci', 'wp-ai-publisher' ); ?></a>
			</div>

			<?php if ( '' !== $saved_flash ) : ?>
				<div class="wpai-guide-auth__banner wpai-guide-auth__banner--ok"><?php echo esc_html( $saved_flash ); ?></div>
			<?php endif; ?>

			<?php if ( empty( $rows ) ) : ?>
				<div class="wpai-guide-account__empty">
					<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16Z"/></svg>
					<p><?php echo esc_html__( 'Non hai ancora salvato nessuna guida.', 'wp-ai-publisher' ); ?></p>
					<a class="wpai-guide-auth__submit" href="<?php echo esc_url( $generator_url ); ?>"><?php echo esc_html__( 'Crea la tua prima guida', 'wp-ai-publisher' ); ?></a>
				</div>
			<?php else : ?>
				<div class="wpai-guide-account__cards">
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$row_post_id = absint( $row->post_id ?? 0 );
						$row_url     = ( $row_post_id > 0 && 'publish' === get_post_status( $row_post_id ) ) ? get_permalink( $row_post_id ) : '';
						?>
						<article class="wpai-guide-account__card">
							<div class="wpai-guide-account__card-body">
								<span class="wpai-guide-account__date"><?php echo esc_html( mysql2date( 'd/m/Y', (string) $row->created_at ) ); ?></span>
								<h3 class="wpai-guide-account__card-title"><?php echo esc_html( wp_trim_words( (string) $row->query, 16, '…' ) ); ?></h3>
							</div>
							<div class="wpai-guide-account__card-actions">
								<?php if ( '' !== (string) $row_url ) : ?>
									<a class="wpai-guide-account__open" href="<?php echo esc_url( (string) $row_url ); ?>"><?php echo esc_html__( 'Apri', 'wp-ai-publisher' ); ?></a>
								<?php else : ?>
									<details class="wpai-guide-account__inline">
										<summary><?php echo esc_html__( 'Leggi', 'wp-ai-publisher' ); ?></summary>
										<div class="wpai-guide wpai-guide__content"><?php echo wp_kses_post( (string) $row->result_html ); ?></div>
									</details>
								<?php endif; ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Rimuovere questa guida dalla tua area?', 'wp-ai-publisher' ) ); ?>');">
									<input type="hidden" name="action" value="wpai_guide_member_remove">
									<input type="hidden" name="request_id" value="<?php echo esc_attr( (string) $row->id ); ?>">
									<?php wp_nonce_field( 'wpai_guide_member_remove_' . (int) $row->id ); ?>
									<button type="submit" class="wpai-guide-account__remove" aria-label="<?php echo esc_attr__( 'Rimuovi', 'wp-ai-publisher' ); ?>"><?php echo esc_html__( 'Rimuovi', 'wp-ai-publisher' ); ?></button>
								</form>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
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
		$this->enqueue_auth_assets();
		$account = absint( $config['account_page_id'] );
		$url     = $account ? get_permalink( $account ) : home_url( '/' );
		// Honour a pending save intent even for already-logged-in users.
		$intent = $this->get_save_intent();
		if ( $intent ) {
			$this->save_request_for_user( get_current_user_id(), $intent['id'] );
		}
		return '<div class="wpai-guide-auth"><div class="wpai-guide-auth__card"><h2 class="wpai-guide-auth__title">' . esc_html__( 'Sei già connesso', 'wp-ai-publisher' ) . '</h2><p class="wpai-guide-auth__sub">' . esc_html__( 'Trovi tutte le tue guide nella tua area personale.', 'wp-ai-publisher' ) . '</p><a class="wpai-guide-auth__submit" href="' . esc_url( $url ) . '">' . esc_html__( 'Vai alle tue guide', 'wp-ai-publisher' ) . '</a></div></div>';
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
		$target  = $target ? $target : home_url( '/' );
		// Carry a pending "save this guide" intent to the account page.
		$save_id = absint( $_POST['wpai_save'] ?? 0 );
		if ( $save_id > 0 ) {
			$target = add_query_arg( 'wpai_save', $save_id, $target );
		}
		wp_safe_redirect( $target );
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
		if ( ! $this->save_request_for_user( get_current_user_id(), $request_id ) ) {
			return new WP_Error( 'wpai_guide_not_found', __( 'Guida non trovata.', 'wp-ai-publisher' ), array( 'status' => 404 ) );
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
	 * Add a guide (by request ID) to a user's saved list.
	 *
	 * @param int $user_id User ID.
	 * @param int $request_id Guide request ID.
	 * @return bool True on success (or already saved), false if the request is invalid.
	 */
	private function save_request_for_user( $user_id, $request_id ) {
		$user_id    = absint( $user_id );
		$request_id = absint( $request_id );
		if ( $user_id <= 0 || $request_id <= 0 || ! $this->assistant->get_request( $request_id ) ) {
			return false;
		}
		$saved = $this->get_saved_ids( $user_id );
		if ( ! in_array( $request_id, $saved, true ) ) {
			$saved[] = $request_id;
			update_user_meta( $user_id, self::META_SAVED, array_values( array_unique( array_map( 'absint', $saved ) ) ) );
		}
		return true;
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
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, query, result_html, created_at, post_id FROM {$table} WHERE id IN ({$placeholders}) ORDER BY id DESC", $ids ) );
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
