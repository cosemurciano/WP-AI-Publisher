<?php
/**
 * Role / login based content access control.
 *
 * Restricts viewing of posts, pages, CPTs (incl. wpai_guide), terms
 * (categories/tags) and nav menu items by access mode: everyone (default),
 * logged-in only, or specific roles. Administrators always see everything.
 *
 * Performance: a precomputed index of the *restricted* objects only is kept in
 * an autoloaded option (and object cache), rebuilt incrementally on save. The
 * per-request enforcement reads that index — no meta_query per request.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Access control manager.
 */
class Access_Control {

	const META   = '_wpai_access';
	const OPTION = 'wpai_publisher_access';
	const INDEX  = 'wpai_publisher_access_index';

	const MODE_EVERYONE  = 'everyone';
	const MODE_LOGGED_IN = 'logged_in';
	const MODE_ROLES     = 'roles';

	/**
	 * In-request memo of the restricted post IDs the current user cannot access.
	 *
	 * @var array<int,int>|null
	 */
	private $blocked_post_ids = null;

	/* ---------------------------------------------------------------------
	 * Bootstrap
	 * ------------------------------------------------------------------- */

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		// --- Admin UI (always available so rules can be set) ---
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_post_rule' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'register_term_fields' ) );
		add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'render_menu_item_field' ), 10, 2 );
		add_action( 'wp_update_nav_menu_item', array( $this, 'save_menu_item_rule' ), 10, 2 );

		// --- Index maintenance ---
		add_action( 'save_post', array( $this, 'reindex_post' ), 99, 1 );
		add_action( 'deleted_post', array( $this, 'remove_post_from_index' ) );
		add_action( 'created_term', array( $this, 'reindex_terms' ) );
		add_action( 'edited_term', array( $this, 'on_term_edited' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'on_term_deleted' ), 10, 5 );
		add_action( 'wp_update_nav_menu', array( $this, 'reindex_menu_items' ) );

		add_action( 'admin_post_wpai_publisher_save_access', array( $this, 'handle_save_settings' ) );

		// --- Enforcement (front-end only, and only when enabled) ---
		if ( ! $this->is_enabled() ) {
			return;
		}
		add_action( 'template_redirect', array( $this, 'enforce_single_view' ), 1 );
		add_action( 'pre_get_posts', array( $this, 'filter_listing_queries' ) );
		add_filter( 'wp_nav_menu_objects', array( $this, 'filter_menu_objects' ), 10, 2 );
		add_filter( 'get_terms', array( $this, 'filter_terms' ), 10, 2 );
		add_filter( 'rest_pre_dispatch', array( $this, 'filter_rest' ), 10, 3 );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'filter_sitemap_posts' ), 10, 1 );
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------- */

	/**
	 * Get the access-control settings.
	 *
	 * @return array{enabled:bool,denied_page_id:int}
	 */
	public function get_settings() {
		$opt = get_option( self::OPTION, array() );
		return array(
			'enabled'        => ! empty( $opt['enabled'] ),
			'denied_page_id' => absint( $opt['denied_page_id'] ?? 0 ),
		);
	}

	/**
	 * Whether enforcement is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		$s = $this->get_settings();
		return $s['enabled'];
	}

	/**
	 * Resolve the login page URL (the membership login page, with fallbacks).
	 *
	 * @return string
	 */
	private function login_url() {
		$guide = get_option( 'wpai_publisher_guide_assistant', array() );
		$login = absint( $guide['login_page_id'] ?? 0 ) ?: absint( $guide['register_page_id'] ?? 0 );
		$url   = $login ? get_permalink( $login ) : '';
		return $url ? (string) $url : wp_login_url();
	}

	/* ---------------------------------------------------------------------
	 * Rule helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Available access modes with labels.
	 *
	 * @return array<string,string>
	 */
	public function get_modes() {
		return array(
			self::MODE_EVERYONE  => __( 'Tutti (pubblico)', 'wp-ai-publisher' ),
			self::MODE_LOGGED_IN => __( 'Solo utenti registrati', 'wp-ai-publisher' ),
			self::MODE_ROLES     => __( 'Solo ruoli specifici', 'wp-ai-publisher' ),
		);
	}

	/**
	 * Normalize a stored meta value to a rule array, or null when unrestricted.
	 *
	 * @param mixed $value Stored meta.
	 * @return array{mode:string,roles:array<int,string>}|null
	 */
	private function normalize_rule( $value ) {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$mode = isset( $value['mode'] ) ? sanitize_key( (string) $value['mode'] ) : self::MODE_EVERYONE;
		if ( self::MODE_LOGGED_IN === $mode ) {
			return array( 'mode' => self::MODE_LOGGED_IN, 'roles' => array() );
		}
		if ( self::MODE_ROLES === $mode ) {
			$roles = array_values( array_filter( array_map( 'sanitize_key', (array) ( $value['roles'] ?? array() ) ) ) );
			if ( empty( $roles ) ) {
				// "Roles" with no role selected behaves like logged-in only.
				return array( 'mode' => self::MODE_LOGGED_IN, 'roles' => array() );
			}
			return array( 'mode' => self::MODE_ROLES, 'roles' => $roles );
		}
		return null; // everyone.
	}

	/**
	 * Combine several rules into the most restrictive one.
	 *
	 * everyone < logged_in < roles. For multiple role rules the allowed roles
	 * are unioned (a user with any allowed role can view).
	 *
	 * @param array<int,array{mode:string,roles:array<int,string>}> $rules Rules.
	 * @return array{mode:string,roles:array<int,string>}|null
	 */
	private function combine_rules( $rules ) {
		$rank   = array( self::MODE_EVERYONE => 0, self::MODE_LOGGED_IN => 1, self::MODE_ROLES => 2 );
		$best   = self::MODE_EVERYONE;
		$roles  = array();
		foreach ( $rules as $rule ) {
			if ( ! $rule ) {
				continue;
			}
			if ( $rank[ $rule['mode'] ] > $rank[ $best ] ) {
				$best = $rule['mode'];
			}
			if ( self::MODE_ROLES === $rule['mode'] ) {
				$roles = array_merge( $roles, $rule['roles'] );
			}
		}
		if ( self::MODE_EVERYONE === $best ) {
			return null;
		}
		return array( 'mode' => $best, 'roles' => array_values( array_unique( $roles ) ) );
	}

	/**
	 * Whether a user satisfies a rule.
	 *
	 * @param array{mode:string,roles:array<int,string>}|null $rule Rule.
	 * @param \WP_User|null                                   $user User (defaults to current).
	 * @return bool
	 */
	public function user_can_access( $rule, $user = null ) {
		if ( empty( $rule ) || self::MODE_EVERYONE === ( $rule['mode'] ?? self::MODE_EVERYONE ) ) {
			return true;
		}
		$user = $user instanceof \WP_User ? $user : wp_get_current_user();
		if ( $user && $user->ID && ( user_can( $user, 'manage_options' ) || is_super_admin( $user->ID ) ) ) {
			return true; // admins see everything.
		}
		if ( ! $user || ! $user->ID ) {
			return false; // logged-out cannot access any restricted content.
		}
		if ( self::MODE_LOGGED_IN === $rule['mode'] ) {
			return true;
		}
		if ( self::MODE_ROLES === $rule['mode'] ) {
			return ! empty( array_intersect( (array) $user->roles, (array) $rule['roles'] ) );
		}
		return false;
	}

	/* ---------------------------------------------------------------------
	 * Index
	 * ------------------------------------------------------------------- */

	/**
	 * Read the access index.
	 *
	 * @return array{posts:array<int,array>,terms:array<int,array>,menu:array<int,array>}
	 */
	private function get_index() {
		$index = get_option( self::INDEX, array() );
		return array(
			'posts' => is_array( $index['posts'] ?? null ) ? $index['posts'] : array(),
			'terms' => is_array( $index['terms'] ?? null ) ? $index['terms'] : array(),
			'menu'  => is_array( $index['menu'] ?? null ) ? $index['menu'] : array(),
		);
	}

	/**
	 * Persist the access index.
	 *
	 * @param array $index Index.
	 * @return void
	 */
	private function save_index( $index ) {
		update_option( self::INDEX, $index, true );
		$this->blocked_post_ids = null;
	}

	/**
	 * Compute the effective rule for a post (own rule + inherited term rules).
	 *
	 * @param int $post_id Post ID.
	 * @return array{mode:string,roles:array<int,string>}|null
	 */
	private function compute_post_rule( $post_id ) {
		$rules = array();
		$own   = $this->normalize_rule( get_post_meta( $post_id, self::META, true ) );
		if ( $own ) {
			$rules[] = $own;
		}
		foreach ( (array) get_object_taxonomies( get_post_type( $post_id ) ) as $tax ) {
			$terms = get_the_terms( $post_id, $tax );
			if ( ! $terms || is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$rule = $this->normalize_rule( get_term_meta( $term->term_id, self::META, true ) );
				if ( $rule ) {
					$rules[] = $rule;
				}
			}
		}
		return $this->combine_rules( $rules );
	}

	/**
	 * Reindex a single post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function reindex_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$type = get_post_type( $post_id );
		if ( 'nav_menu_item' === $type ) {
			$this->reindex_menu_items();
			return;
		}
		$index = $this->get_index();
		$rule  = $this->compute_post_rule( $post_id );
		if ( $rule ) {
			$index['posts'][ $post_id ] = $rule;
		} else {
			unset( $index['posts'][ $post_id ] );
		}
		$this->save_index( $index );
	}

	/**
	 * Remove a deleted post from the index.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function remove_post_from_index( $post_id ) {
		$index = $this->get_index();
		if ( isset( $index['posts'][ absint( $post_id ) ] ) ) {
			unset( $index['posts'][ absint( $post_id ) ] );
			$this->save_index( $index );
		}
	}

	/**
	 * Rebuild the terms index (terms are few; a full scan is cheap).
	 *
	 * @return void
	 */
	public function reindex_terms() {
		global $wpdb;
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = %s", self::META ) );
		$terms = array();
		foreach ( (array) $rows as $row ) {
			$rule = $this->normalize_rule( maybe_unserialize( $row->meta_value ) );
			if ( $rule ) {
				$terms[ (int) $row->term_id ] = $rule;
			}
		}
		$index          = $this->get_index();
		$index['terms'] = $terms;
		$this->save_index( $index );
	}

	/**
	 * When a term's access changes, reindex the term set and propagate the new
	 * rule immediately to the posts that belong to that term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public function on_term_edited( $term_id, $tt_id = 0, $taxonomy = '' ) {
		$this->reindex_terms();
		$object_ids = get_objects_in_term( (int) $term_id, $taxonomy ? $taxonomy : 'category' );
		if ( ! is_wp_error( $object_ids ) && ! empty( $object_ids ) ) {
			$this->reindex_posts_batch( array_map( 'absint', (array) $object_ids ) );
		}
	}

	/**
	 * When a term is deleted, reindex and recompute the posts that had it.
	 *
	 * @param int      $term         Term ID.
	 * @param int      $tt_id        Term taxonomy ID.
	 * @param string   $taxonomy     Taxonomy.
	 * @param \WP_Term $deleted_term Deleted term.
	 * @param array    $object_ids   Objects that had the term.
	 * @return void
	 */
	public function on_term_deleted( $term, $tt_id = 0, $taxonomy = '', $deleted_term = null, $object_ids = array() ) {
		$this->reindex_terms();
		if ( ! empty( $object_ids ) ) {
			$this->reindex_posts_batch( array_map( 'absint', (array) $object_ids ) );
		}
	}

	/**
	 * Recompute the effective rule for a set of posts in a single index write.
	 *
	 * @param array<int,int> $ids Post IDs.
	 * @return void
	 */
	private function reindex_posts_batch( $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( empty( $ids ) ) {
			return;
		}
		// Cap to keep a single term edit from triggering an unbounded recompute;
		// beyond this a full rebuild (settings save) covers the rest.
		$ids   = array_slice( $ids, 0, 5000 );
		$index = $this->get_index();
		foreach ( $ids as $pid ) {
			if ( 'nav_menu_item' === get_post_type( $pid ) ) {
				continue;
			}
			$rule = $this->compute_post_rule( $pid );
			if ( $rule ) {
				$index['posts'][ $pid ] = $rule;
			} else {
				unset( $index['posts'][ $pid ] );
			}
		}
		$this->save_index( $index );
	}

	/**
	 * Rebuild the menu-items index (menus are small).
	 *
	 * @return void
	 */
	public function reindex_menu_items() {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, pm.meta_value FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				 WHERE p.post_type = 'nav_menu_item'",
				self::META
			)
		);
		$menu = array();
		foreach ( (array) $rows as $row ) {
			$rule = $this->normalize_rule( maybe_unserialize( $row->meta_value ) );
			if ( $rule ) {
				$menu[ (int) $row->ID ] = $rule;
			}
		}
		$index         = $this->get_index();
		$index['menu'] = $menu;
		$this->save_index( $index );
	}

	/**
	 * Full rebuild of the whole index (used when settings are saved).
	 *
	 * @return void
	 */
	public function rebuild_index() {
		global $wpdb;
		$posts = array();
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::META
			)
		);
		// Posts directly restricted, plus posts inheriting a restricted term.
		$term_post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} tr
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				 INNER JOIN {$wpdb->termmeta} tm ON tm.term_id = tt.term_id AND tm.meta_key = %s",
				self::META
			)
		);
		$candidates = array_unique( array_map( 'absint', array_merge( (array) $ids, (array) $term_post_ids ) ) );
		foreach ( $candidates as $pid ) {
			if ( 'nav_menu_item' === get_post_type( $pid ) ) {
				continue;
			}
			$rule = $this->compute_post_rule( $pid );
			if ( $rule ) {
				$posts[ $pid ] = $rule;
			}
		}
		$index = array( 'posts' => $posts, 'terms' => array(), 'menu' => array() );
		$this->save_index( $index );
		$this->reindex_terms();
		$this->reindex_menu_items();
	}

	/* ---------------------------------------------------------------------
	 * Enforcement
	 * ------------------------------------------------------------------- */

	/**
	 * The set of restricted post IDs the current user cannot access.
	 *
	 * @return array<int,int>
	 */
	private function blocked_post_ids() {
		if ( null !== $this->blocked_post_ids ) {
			return $this->blocked_post_ids;
		}
		$blocked = array();
		foreach ( $this->get_index()['posts'] as $pid => $rule ) {
			if ( ! $this->user_can_access( $rule ) ) {
				$blocked[] = (int) $pid;
			}
		}
		$this->blocked_post_ids = $blocked;
		return $blocked;
	}

	/**
	 * Block the current single view (post or term archive) when restricted.
	 *
	 * @return void
	 */
	public function enforce_single_view() {
		if ( is_admin() ) {
			return;
		}
		$rule = null;

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			$index   = $this->get_index();
			$rule    = $index['posts'][ $post_id ] ?? null;
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term  = get_queried_object();
			$index = $this->get_index();
			$rule  = ( $term && isset( $term->term_id ) ) ? ( $index['terms'][ (int) $term->term_id ] ?? null ) : null;
		}

		if ( $rule && ! $this->user_can_access( $rule ) ) {
			$this->deny_current_request();
		}
	}

	/**
	 * Redirect/deny the current request.
	 *
	 * @return void
	 */
	private function deny_current_request() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // keep page caches from storing restricted output.
		}
		nocache_headers();

		$current = home_url( add_query_arg( null, null ) );

		if ( ! is_user_logged_in() ) {
			// Logged-out → membership login with a return URL.
			$login = add_query_arg( 'redirect_to', rawurlencode( $current ), $this->login_url() );
			wp_safe_redirect( $login );
			exit;
		}

		// Logged-in but unauthorized role → dedicated page.
		$denied = absint( $this->get_settings()['denied_page_id'] );
		if ( $denied && get_post_status( $denied ) ) {
			$url = get_permalink( $denied );
			if ( $url && untrailingslashit( $url ) !== untrailingslashit( $current ) ) {
				wp_safe_redirect( $url );
				exit;
			}
		}

		status_header( 403 );
		wp_die(
			esc_html__( 'Non hai i permessi per visualizzare questo contenuto.', 'wp-ai-publisher' ),
			esc_html__( 'Accesso non consentito', 'wp-ai-publisher' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Exclude restricted posts from listings, feeds and REST collection queries.
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function filter_listing_queries( $query ) {
		if ( is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( $query->is_singular() ) {
			return;
		}
		$blocked = $this->blocked_post_ids();
		if ( empty( $blocked ) ) {
			return;
		}
		$existing = (array) $query->get( 'post__not_in' );
		$query->set( 'post__not_in', array_values( array_unique( array_merge( $existing, $blocked ) ) ) );
	}

	/**
	 * Remove restricted items (and their descendants) from rendered menus.
	 *
	 * @param array<int,object> $items Menu items.
	 * @param object            $args  Menu args.
	 * @return array<int,object>
	 */
	public function filter_menu_objects( $items, $args ) {
		$index   = $this->get_index();
		$removed = array();
		$out     = array();
		foreach ( (array) $items as $item ) {
			$blocked = in_array( (int) $item->menu_item_parent, $removed, true );
			if ( ! $blocked ) {
				$rule = $index['menu'][ (int) $item->ID ] ?? null;
				if ( ! $rule ) {
					// Inherit from the linked object.
					if ( 'post_type' === $item->type ) {
						$rule = $index['posts'][ (int) $item->object_id ] ?? null;
					} elseif ( 'taxonomy' === $item->type ) {
						$rule = $index['terms'][ (int) $item->object_id ] ?? null;
					}
				}
				$blocked = $rule && ! $this->user_can_access( $rule );
			}
			if ( $blocked ) {
				$removed[] = (int) $item->ID;
				continue;
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * Hide restricted terms from term listings (front-end, non-admin).
	 *
	 * @param array<int,mixed> $terms Terms (objects or IDs).
	 * @param array<int,string>|string $taxonomies Taxonomies.
	 * @return array<int,mixed>
	 */
	public function filter_terms( $terms, $taxonomies ) {
		// get_terms may return an int (count) or non-array fields; only filter lists.
		if ( is_admin() || empty( $terms ) || ! is_array( $terms ) ) {
			return $terms;
		}
		$index = $this->get_index()['terms'];
		if ( empty( $index ) ) {
			return $terms;
		}
		$out = array();
		foreach ( $terms as $term ) {
			$tid = is_object( $term ) ? (int) ( $term->term_id ?? 0 ) : ( is_numeric( $term ) ? (int) $term : 0 );
			if ( $tid > 0 && isset( $index[ $tid ] ) && ! $this->user_can_access( $index[ $tid ] ) ) {
				continue;
			}
			$out[] = $term;
		}
		return $out;
	}

	/**
	 * Block REST access to a restricted single post.
	 *
	 * @param mixed            $result  Pre-dispatch result.
	 * @param \WP_REST_Server  $server  Server.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function filter_rest( $result, $server, $request ) {
		if ( null !== $result ) {
			return $result;
		}
		$route = (string) $request->get_route();
		if ( ! preg_match( '#^/wp/v2/[^/]+/(\d+)$#', $route, $m ) ) {
			return $result;
		}
		$post_id = (int) $m[1];
		$index   = $this->get_index();
		$rule    = $index['posts'][ $post_id ] ?? null;
		if ( $rule && ! $this->user_can_access( $rule ) ) {
			return new \WP_Error( 'wpai_access_forbidden', __( 'Contenuto riservato.', 'wp-ai-publisher' ), array( 'status' => is_user_logged_in() ? 403 : 401 ) );
		}
		return $result;
	}

	/**
	 * Exclude restricted posts from XML sitemaps.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<string,mixed>
	 */
	public function filter_sitemap_posts( $args ) {
		$blocked = $this->blocked_post_ids();
		if ( ! empty( $blocked ) ) {
			$args['post__not_in'] = array_values( array_unique( array_merge( (array) ( $args['post__not_in'] ?? array() ), $blocked ) ) );
		}
		return $args;
	}

	/* ---------------------------------------------------------------------
	 * Admin: post meta box
	 * ------------------------------------------------------------------- */

	/**
	 * Register the access meta box on viewable post types.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, array( 'attachment', 'nav_menu_item' ), true ) ) {
				continue;
			}
			add_meta_box(
				'wpai-access',
				__( 'Accesso (WP AI Publisher)', 'wp-ai-publisher' ),
				array( $this, 'render_meta_box' ),
				$type->name,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the post access meta box.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		$rule = $this->normalize_rule( get_post_meta( $post->ID, self::META, true ) );
		$mode = $rule['mode'] ?? self::MODE_EVERYONE;
		$roles = $rule['roles'] ?? array();
		wp_nonce_field( 'wpai_access_meta', 'wpai_access_nonce' );
		$this->render_control( $mode, $roles );
	}

	/**
	 * Public helper: render the access control for a given post (used by custom
	 * editors such as the guide edit screen). Outputs the nonce + control.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function render_post_control( $post_id ) {
		$rule = $this->normalize_rule( get_post_meta( absint( $post_id ), self::META, true ) );
		wp_nonce_field( 'wpai_access_meta', 'wpai_access_nonce' );
		$this->render_control( $rule['mode'] ?? self::MODE_EVERYONE, $rule['roles'] ?? array() );
	}

	/**
	 * Public helper: save the access rule for a post from the current request.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_post_control( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$this->store_rule( 'post', $post_id, $this->read_posted_rule() );
		$this->reindex_post( $post_id );
	}

	/**
	 * Shared access control markup (radios + role checkboxes).
	 *
	 * @param string            $mode Current mode.
	 * @param array<int,string> $roles Current roles.
	 * @return void
	 */
	private function render_control( $mode, $roles ) {
		$id = 'wpai-access-' . wp_rand( 1000, 9999 );
		?>
		<div class="wpai-access-control">
			<?php foreach ( $this->get_modes() as $key => $label ) : ?>
				<p style="margin:.3em 0;">
					<label>
						<input type="radio" name="wpai_access_mode" value="<?php echo esc_attr( $key ); ?>" <?php checked( $mode, $key ); ?> class="wpai-access-mode">
						<?php echo esc_html( $label ); ?>
					</label>
				</p>
			<?php endforeach; ?>
			<div class="wpai-access-roles" style="margin:.4em 0 0 22px;<?php echo self::MODE_ROLES === $mode ? '' : 'display:none;'; ?>">
				<?php foreach ( wp_roles()->get_names() as $role_key => $role_name ) : ?>
					<label style="display:block;">
						<input type="checkbox" name="wpai_access_roles[]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $roles, true ) ); ?>>
						<?php echo esc_html( translate_user_role( $role_name ) ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<script>
		( function () {
			var box = document.currentScript.previousElementSibling;
			if ( ! box ) { return; }
			box.addEventListener( 'change', function ( e ) {
				if ( ! e.target.classList.contains( 'wpai-access-mode' ) ) { return; }
				var roles = box.querySelector( '.wpai-access-roles' );
				if ( roles ) { roles.style.display = ( e.target.value === 'roles' && e.target.checked ) ? '' : 'none'; }
			} );
		}() );
		</script>
		<?php
		unset( $id );
	}

	/**
	 * Read the posted rule from $_POST (shared by post/term/menu handlers).
	 *
	 * @param string $mode_key Field name for the mode.
	 * @param string $roles_key Field name for the roles.
	 * @return array{mode:string,roles:array<int,string>}
	 */
	private function read_posted_rule( $mode_key = 'wpai_access_mode', $roles_key = 'wpai_access_roles' ) {
		$mode  = isset( $_POST[ $mode_key ] ) ? sanitize_key( wp_unslash( $_POST[ $mode_key ] ) ) : self::MODE_EVERYONE;
		$roles = isset( $_POST[ $roles_key ] ) && is_array( $_POST[ $roles_key ] )
			? array_values( array_filter( array_map( 'sanitize_key', wp_unslash( $_POST[ $roles_key ] ) ) ) )
			: array();
		return array( 'mode' => $mode, 'roles' => $roles );
	}

	/**
	 * Save the post access rule.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function save_post_rule( $post_id, $post ) {
		if ( ! isset( $_POST['wpai_access_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpai_access_nonce'] ) ), 'wpai_access_meta' ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$this->store_rule( 'post', $post_id, $this->read_posted_rule() );
	}

	/* ---------------------------------------------------------------------
	 * Admin: term fields
	 * ------------------------------------------------------------------- */

	/**
	 * Hook the access fields onto every public taxonomy edit screen.
	 *
	 * @return void
	 */
	public function register_term_fields() {
		foreach ( get_taxonomies( array( 'show_ui' => true ), 'names' ) as $tax ) {
			add_action( "{$tax}_add_form_fields", array( $this, 'render_term_add_field' ) );
			add_action( "{$tax}_edit_form_fields", array( $this, 'render_term_edit_field' ) );
			add_action( "created_{$tax}", array( $this, 'save_term_rule' ) );
			add_action( "edited_{$tax}", array( $this, 'save_term_rule' ) );
		}
	}

	/**
	 * Render the access field on the "add term" form.
	 *
	 * @return void
	 */
	public function render_term_add_field() {
		wp_nonce_field( 'wpai_access_term', 'wpai_access_nonce' );
		echo '<div class="form-field"><label>' . esc_html__( 'Accesso (WP AI Publisher)', 'wp-ai-publisher' ) . '</label>';
		$this->render_control( self::MODE_EVERYONE, array() );
		echo '</div>';
	}

	/**
	 * Render the access field on the "edit term" form.
	 *
	 * @param \WP_Term $term Term.
	 * @return void
	 */
	public function render_term_edit_field( $term ) {
		$rule = $this->normalize_rule( get_term_meta( $term->term_id, self::META, true ) );
		wp_nonce_field( 'wpai_access_term', 'wpai_access_nonce' );
		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'Accesso (WP AI Publisher)', 'wp-ai-publisher' ) . '</th><td>';
		$this->render_control( $rule['mode'] ?? self::MODE_EVERYONE, $rule['roles'] ?? array() );
		echo '</td></tr>';
	}

	/**
	 * Save a term access rule.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_term_rule( $term_id ) {
		if ( ! isset( $_POST['wpai_access_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpai_access_nonce'] ) ), 'wpai_access_term' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$this->store_rule( 'term', $term_id, $this->read_posted_rule() );
		$this->reindex_terms();
	}

	/* ---------------------------------------------------------------------
	 * Admin: nav menu item fields
	 * ------------------------------------------------------------------- */

	/**
	 * Render the access field inside a menu item in the menu editor.
	 *
	 * @param int $item_id Menu item ID.
	 * @return void
	 */
	public function render_menu_item_field( $item_id ) {
		$rule  = $this->normalize_rule( get_post_meta( $item_id, self::META, true ) );
		$mode  = $rule['mode'] ?? self::MODE_EVERYONE;
		$roles = $rule['roles'] ?? array();
		?>
		<p class="field-wpai-access description description-wide">
			<label><?php echo esc_html__( 'Accesso (WP AI Publisher)', 'wp-ai-publisher' ); ?><br>
				<select name="wpai_access_menu_mode[<?php echo esc_attr( (string) $item_id ); ?>]" class="widefat">
					<?php foreach ( $this->get_modes() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $mode, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<span style="display:block;margin-top:6px;">
				<?php foreach ( wp_roles()->get_names() as $role_key => $role_name ) : ?>
					<label style="display:inline-block;margin-right:10px;">
						<input type="checkbox" name="wpai_access_menu_roles[<?php echo esc_attr( (string) $item_id ); ?>][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $roles, true ) ); ?>>
						<?php echo esc_html( translate_user_role( $role_name ) ); ?>
					</label>
				<?php endforeach; ?>
			</span>
		</p>
		<?php
	}

	/**
	 * Save a menu item access rule (called per item on menu save).
	 *
	 * @param int $menu_id Menu ID.
	 * @param int $item_id Menu item ID.
	 * @return void
	 */
	public function save_menu_item_rule( $menu_id, $item_id ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		$mode  = isset( $_POST['wpai_access_menu_mode'][ $item_id ] ) ? sanitize_key( wp_unslash( $_POST['wpai_access_menu_mode'][ $item_id ] ) ) : self::MODE_EVERYONE;
		$roles = isset( $_POST['wpai_access_menu_roles'][ $item_id ] ) && is_array( $_POST['wpai_access_menu_roles'][ $item_id ] )
			? array_values( array_filter( array_map( 'sanitize_key', wp_unslash( $_POST['wpai_access_menu_roles'][ $item_id ] ) ) ) )
			: array();
		$this->store_rule( 'post', $item_id, array( 'mode' => $mode, 'roles' => $roles ) );
	}

	/* ---------------------------------------------------------------------
	 * Storage
	 * ------------------------------------------------------------------- */

	/**
	 * Store (or clear) a rule on a post or term.
	 *
	 * @param string                                   $type 'post' or 'term'.
	 * @param int                                       $id Object ID.
	 * @param array{mode:string,roles:array<int,string>} $raw Raw posted rule.
	 * @return void
	 */
	private function store_rule( $type, $id, $raw ) {
		$rule = $this->normalize_rule( $raw );
		if ( 'term' === $type ) {
			if ( $rule ) {
				update_term_meta( $id, self::META, $rule );
			} else {
				delete_term_meta( $id, self::META );
			}
			return;
		}
		if ( $rule ) {
			update_post_meta( $id, self::META, $rule );
		} else {
			delete_post_meta( $id, self::META );
		}
	}

	/* ---------------------------------------------------------------------
	 * Admin: settings page
	 * ------------------------------------------------------------------- */

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		$settings = $this->get_settings();
		require WPAIP_PLUGIN_DIR . 'admin/views/access-control.php';
	}

	/**
	 * Save the settings and rebuild the index.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		check_admin_referer( 'wpai_publisher_save_access' );

		update_option(
			self::OPTION,
			array(
				'enabled'        => ! empty( $_POST['wpai_access']['enabled'] ),
				'denied_page_id' => absint( $_POST['wpai_access']['denied_page_id'] ?? 0 ),
			),
			true
		);
		$this->rebuild_index();

		wp_safe_redirect( add_query_arg( 'wpai_notice', 'access_saved', admin_url( 'admin.php?page=wp-ai-publisher-access' ) ) );
		exit;
	}
}
