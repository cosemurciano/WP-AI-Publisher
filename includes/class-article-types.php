<?php
/**
 * Article types CPT and metadata.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Article_Types {
	const POST_TYPE = 'wpai_article_type';
	const DEFAULTS_OPTION = 'wpai_publisher_default_article_types_created';
	const META_PREFIX = 'article_type_';

	public function register() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name' => __( 'Tipologie articolo', 'wp-ai-publisher' ),
				'singular_name' => __( 'Tipologia articolo', 'wp-ai-publisher' ),
				'add_new_item' => __( 'Aggiungi Tipologia articolo', 'wp-ai-publisher' ),
				'edit_item' => __( 'Modifica Tipologia articolo', 'wp-ai-publisher' ),
			),
			'public' => false,
			'publicly_queryable' => false,
			'show_ui' => true,
			'show_in_menu' => false,
			'show_in_rest' => false,
			'has_archive' => false,
			'supports' => array( 'title' ),
			'capability_type' => 'post',
			'map_meta_cap' => true,
		) );
	}

	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'admin_init', array( $this, 'maybe_create_defaults_safe' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
	}

	public function add_meta_boxes() {
		if ( ! function_exists( 'add_meta_box' ) ) { return; }
		add_meta_box( 'wpai_article_type_details', __( 'Configurazione Tipologia articolo', 'wp-ai-publisher' ), array( $this, 'render_meta_box' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'wpai_article_type_usage', __( 'Uso nella generazione', 'wp-ai-publisher' ), array( $this, 'render_usage_box' ), self::POST_TYPE, 'side', 'default' );
	}

	public function render_usage_box( $post ) {
		echo '<p>' . esc_html__( 'Questa tipologia guida la scrittura dell’articolo più del contesto generale del sito. Definisce struttura, tono, intento, categorie consentite e controlli qualità.', 'wp-ai-publisher' ) . '</p>';
	}

	public function render_meta_box( $post ) {
		$post_id = is_object( $post ) ? absint( $post->ID ?? 0 ) : 0;
		$meta = self::get_article_type_config( $post_id );
		$categories = function_exists( 'get_categories' ) ? get_categories( array( 'hide_empty' => false ) ) : array();
		$categories = is_array( $categories ) ? $categories : array();
		include WPAIP_PLUGIN_DIR . 'admin/views/article-type-meta-boxes.php';
	}

	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['wpai_article_type_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpai_article_type_nonce'] ) ), 'wpai_article_type_meta' ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$textarea = array( 'description','goal','prompt','structure','required_sections','forbidden_patterns','preferred_tags','internal_linking_rules','seo_rules','quality_checklist' );
		foreach ( $textarea as $field ) { update_post_meta( $post_id, self::META_PREFIX . $field, sanitize_textarea_field( wp_unslash( $_POST[ self::META_PREFIX . $field ] ?? '' ) ) ); }
		foreach ( array( 'tone','length','search_intent','reader_level' ) as $field ) { update_post_meta( $post_id, self::META_PREFIX . $field, sanitize_key( wp_unslash( $_POST[ self::META_PREFIX . $field ] ?? '' ) ) ); }
		$cats = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $_POST['article_type_allowed_category_ids'] ?? array() ) ) ) ) );
		update_post_meta( $post_id, 'article_type_allowed_category_ids', $cats );
		update_post_meta( $post_id, 'article_type_active', ! empty( $_POST['article_type_active'] ) ? '1' : '0' );
	}

	public function columns( $columns ) { $columns = is_array( $columns ) ? $columns : array(); return array( 'cb'=>$columns['cb'] ?? '', 'title'=>$columns['title'] ?? __( 'Titolo', 'wp-ai-publisher' ), 'active'=>__( 'Attiva', 'wp-ai-publisher' ), 'intent'=>__( 'Intento', 'wp-ai-publisher' ), 'length'=>__( 'Lunghezza', 'wp-ai-publisher' ), 'categories'=>__( 'Categorie', 'wp-ai-publisher' ), 'date'=>$columns['date'] ?? __( 'Data', 'wp-ai-publisher' ) ); }
	public function column_content( $column, $post_id ) {
		$config = self::get_article_type_config( $post_id );
		if ( 'active' === $column ) { echo ! empty( $config['active'] ) ? esc_html__( 'Sì', 'wp-ai-publisher' ) : esc_html__( 'No', 'wp-ai-publisher' ); }
		if ( 'intent' === $column ) { echo esc_html( $config['search_intent'] ); }
		if ( 'length' === $column ) { echo esc_html( $config['length'] ); }
		if ( 'categories' === $column ) { $names = array(); foreach ( $config['allowed_category_ids'] as $id ) { $term = get_term( $id, 'category' ); if ( $term && ! is_wp_error( $term ) ) { $names[] = $term->name; } } echo esc_html( ! empty( $names ) ? implode( ', ', $names ) : __( '—', 'wp-ai-publisher' ) ); }
	}

	public static function get_active_article_types() { return post_type_exists( self::POST_TYPE ) ? get_posts( array( 'post_type'=>self::POST_TYPE, 'post_status'=>'publish', 'numberposts'=>-1, 'orderby'=>'title', 'order'=>'ASC', 'meta_key'=>'article_type_active', 'meta_value'=>'1' ) ) : array(); }
	public static function is_active_article_type( $post_id ) { $post = get_post( absint( $post_id ) ); return $post && self::POST_TYPE === $post->post_type && '1' === (string) get_post_meta( $post->ID, 'article_type_active', true ); }
	public static function get_article_type_config( $post_id ) {
		$post_id = absint( $post_id );
		if ( 0 === $post_id || ! get_post( $post_id ) ) { return array( 'id'=>0, 'title'=> '', 'allowed_category_ids'=>array(), 'active'=>false ); }
		$data = array( 'id'=>$post_id, 'title'=> get_the_title( $post_id ), 'allowed_category_ids'=>array(), 'active'=>false );
		foreach ( array( 'description','goal','prompt','structure','required_sections','forbidden_patterns','tone','length','search_intent','reader_level','preferred_tags','internal_linking_rules','seo_rules','quality_checklist' ) as $field ) { $data[ $field ] = (string) get_post_meta( $post_id, self::META_PREFIX . $field, true ); }
		$data['allowed_category_ids'] = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $post_id, 'article_type_allowed_category_ids', true ) ) ) );
		$data['active'] = '1' === (string) get_post_meta( $post_id, 'article_type_active', true );
		return $data;
	}

	public function maybe_create_defaults_safe() {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) { return; }
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) { return; }
		if ( ! function_exists( 'post_type_exists' ) || ! post_type_exists( self::POST_TYPE ) ) { return; }
		if ( get_option( self::DEFAULTS_OPTION ) ) { return; }
		self::maybe_create_defaults();
	}

	public static function maybe_create_defaults() {
		if ( get_option( self::DEFAULTS_OPTION ) ) { return; }
		if ( ! function_exists( 'get_posts' ) || ! function_exists( 'wp_insert_post' ) || ! function_exists( 'post_type_exists' ) || ! post_type_exists( self::POST_TYPE ) ) { return; }
		$defaults = array(
			'Tutorial passo passo' => array( 'search_intent'=>'tutorial', 'reader_level'=>'principianti', 'length'=>'media', 'structure'=>"Introduzione\nPrerequisiti\nProcedura passo passo\nErrori comuni\nVerifica finale" ),
			'Guida informativa' => array( 'search_intent'=>'informazionale', 'reader_level'=>'misto', 'length'=>'media', 'structure'=>"Introduzione\nCos’è\nQuando serve\nVantaggi\nConclusione" ),
			'Confronto / comparativa' => array( 'search_intent'=>'comparativo', 'reader_level'=>'intermedi', 'length'=>'lunga', 'structure'=>"Introduzione\nCriteri di confronto\nOpzione A\nOpzione B\nTabella comparativa\nConsiglio finale" ),
			'Checklist operativa' => array( 'search_intent'=>'tutorial', 'reader_level'=>'misto', 'length'=>'breve', 'structure'=>"Introduzione\nChecklist\nControlli finali\nProssimi passi" ),
			'Articolo pillar' => array( 'search_intent'=>'informazionale', 'reader_level'=>'misto', 'length'=>'pillar', 'structure'=>"Introduzione\nPanoramica\nSezioni principali\nApprofondimenti\nFAQ\nConclusione" ),
			'Tutorial WordPress passo passo' => array( 'search_intent'=>'tutorial', 'reader_level'=>'principianti', 'length'=>'lunga', 'prompt'=>'Scrivi una guida pratica, chiara e progressiva per utenti WordPress. Spiega ogni passaggio senza dare per scontate competenze tecniche. Usa H2 descrittivi, paragrafi brevi, liste solo se utili, avvisi quando una procedura può variare in base al tema, al plugin o alla versione di WordPress. Concludi con una verifica finale.', 'structure'=>"Introduzione\nPrerequisiti\nProcedura passo passo\nErrori comuni\nVerifica finale" ),
		);
		foreach ( $defaults as $title => $cfg ) {
			$existing = get_posts( array( 'post_type'=>self::POST_TYPE, 'post_status'=>array( 'publish', 'draft', 'pending', 'private' ), 's'=>$title, 'posts_per_page'=>5, 'fields'=>'ids', 'no_found_rows'=>true ) );
			$already_exists = false;
			foreach ( (array) $existing as $existing_id ) {
				if ( function_exists( 'get_the_title' ) && $title === get_the_title( $existing_id ) ) { $already_exists = true; break; }
			}
			if ( $already_exists ) { continue; }
			$id = wp_insert_post( array( 'post_type'=>self::POST_TYPE, 'post_status'=>'publish', 'post_title'=>sanitize_text_field( $title ) ) );
			if ( $id && ! is_wp_error( $id ) ) { foreach ( $cfg as $k=>$v ) { update_post_meta( $id, self::META_PREFIX . $k, sanitize_textarea_field( $v ) ); } update_post_meta( $id, 'article_type_tone', 'chiaro_didattico_e_operativo' ); update_post_meta( $id, 'article_type_active', '1' ); }
		}
		update_option( self::DEFAULTS_OPTION, '1', false );
	}
}
