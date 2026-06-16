<?php
/**
 * Custom-table Article Type repository.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Article_Type_Repository {
	const DEFAULTS_OPTION = 'wpai_publisher_default_article_type_rows_created';

	public function get_table_name() { global $wpdb; return $wpdb->prefix . 'wpai_publisher_article_types'; }

	public function create_article_type( $data ) {
		global $wpdb;
		if ( ! current_user_can( wpai_publisher_capability() ) ) { return false; }
		$data = $this->sanitize_article_type_data( $data );
		if ( '' === $data['name'] ) { return false; }
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = null;
		$ok = $wpdb->insert( $this->get_table_name(), $data, $this->formats_for_data( $data ) );
		return false === $ok ? false : absint( $wpdb->insert_id );
	}

	public function update_article_type( $id, $data ) {
		global $wpdb;
		if ( ! current_user_can( wpai_publisher_capability() ) ) { return false; }
		$id = absint( $id );
		if ( 0 === $id ) { return false; }
		$data = $this->sanitize_article_type_data( $data );
		if ( '' === $data['name'] ) { return false; }
		$data['updated_at'] = current_time( 'mysql' );
		unset( $data['created_at'] );
		return false !== $wpdb->update( $this->get_table_name(), $data, array( 'id' => $id ), $this->formats_for_data( $data ), array( '%d' ) );
	}

	public function delete_article_type( $id ) {
		global $wpdb;
		if ( ! current_user_can( wpai_publisher_capability() ) ) { return false; }
		$id = absint( $id );
		return $id > 0 && false !== $wpdb->delete( $this->get_table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public function get_article_type( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( 0 === $id ) { return null; }
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->get_table_name()} WHERE id = %d", $id ) );
		return $row ? $this->normalize_article_type( $row ) : null;
	}

	public function get_active_article_types() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$this->get_table_name()} WHERE is_active = 1 ORDER BY name ASC, id ASC" );
		return array_values( array_filter( array_map( array( $this, 'normalize_article_type' ), is_array( $rows ) ? $rows : array() ) ) );
	}

	public function get_all_article_types() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$this->get_table_name()} ORDER BY name ASC, id ASC" );
		return array_values( array_filter( array_map( array( $this, 'normalize_article_type' ), is_array( $rows ) ? $rows : array() ) ) );
	}

	public function is_active_article_type( $id ) { $row = $this->get_article_type( $id ); return is_array( $row ) && ! empty( $row['is_active'] ); }

	public function normalize_article_type( $row ) {
		$row = (array) $row;
		$decoded = json_decode( (string) ( $row['allowed_category_ids'] ?? '' ), true );
		$row['allowed_category_ids'] = array_values( array_filter( array_map( 'absint', is_array( $decoded ) ? $decoded : preg_split( '/[\r\n,]+/', (string) ( $row['allowed_category_ids'] ?? '' ) ) ) ) );
		foreach ( array( 'description', 'tone', 'length', 'search_intent', 'reader_level', 'prompt', 'image_prompt', 'structure', 'required_sections', 'forbidden_patterns', 'preferred_tags', 'quality_checklist' ) as $field ) {
			$row[ $field ] = (string) ( $row[ $field ] ?? '' );
		}
		$row['id'] = absint( $row['id'] ?? 0 );
		$row['ID'] = $row['id'];
		$row['name'] = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
		$row['title'] = $row['name'];
		$row['is_active'] = ! empty( $row['is_active'] );
		$row['active'] = $row['is_active'];
		return $row;
	}

	public function sanitize_article_type_data( $data ) {
		$data = is_array( $data ) ? $data : array();
		$allowed_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $data['allowed_category_ids'] ?? array() ) ) ) ) );
		return array(
			'is_active' => empty( $data['is_active'] ) ? 0 : 1,
			'name' => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
			'description' => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
			'prompt' => sanitize_textarea_field( (string) ( $data['prompt'] ?? '' ) ),
			'image_prompt' => sanitize_textarea_field( (string) ( $data['image_prompt'] ?? '' ) ),
			'structure' => sanitize_textarea_field( (string) ( $data['structure'] ?? '' ) ),
			'required_sections' => sanitize_textarea_field( (string) ( $data['required_sections'] ?? '' ) ),
			'forbidden_patterns' => sanitize_textarea_field( (string) ( $data['forbidden_patterns'] ?? '' ) ),
			'tone' => sanitize_textarea_field( (string) ( $data['tone'] ?? '' ) ),
			'length' => sanitize_textarea_field( (string) ( $data['length'] ?? '' ) ),
			'search_intent' => sanitize_textarea_field( (string) ( $data['search_intent'] ?? '' ) ),
			'reader_level' => sanitize_textarea_field( (string) ( $data['reader_level'] ?? '' ) ),
			'allowed_category_ids' => wp_json_encode( $allowed_ids ),
			'preferred_tags' => sanitize_textarea_field( (string) ( $data['preferred_tags'] ?? '' ) ),
			'quality_checklist' => sanitize_textarea_field( (string) ( $data['quality_checklist'] ?? '' ) ),
		);
	}

	public function maybe_create_default_article_types() {
		if ( get_option( self::DEFAULTS_OPTION ) || ! current_user_can( wpai_publisher_capability() ) ) { return; }
		global $wpdb;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->get_table_name() ) ) !== $this->get_table_name() ) { return; }
		$defaults = array(
			'Tutorial WordPress passo passo' => array( 'prompt' => "Scrivi un tutorial WordPress lungo e dettagliato, con tono chiaro, didattico e operativo, per lettori principianti. Struttura consigliata: Introduzione, Prerequisiti, Procedura passo passo (con passaggi numerati e chiari), Verifica finale. Evita gergo non spiegato e promesse non verificabili." ),
			'Guida informativa' => array( 'prompt' => "Scrivi una guida informativa di lunghezza media, con tono divulgativo e semplice, per un pubblico misto. Spiega l'argomento in modo chiaro con sezioni H2 logiche, esempi pratici e una conclusione utile. Intento di ricerca informazionale." ),
			'Checklist operativa' => array( 'prompt' => "Scrivi un articolo breve e operativo con tono professionale e tecnico, per lettori intermedi. Organizza il contenuto come checklist azionabile (liste puntate) con sezioni H2 e una breve introduzione e chiusura." ),
			'Confronto / comparativa' => array( 'prompt' => "Scrivi un articolo comparativo di lunghezza media, con tono commerciale ma informativo, per un pubblico misto. Confronta le opzioni in modo equilibrato con sezioni H2 per criteri, pro e contro, e una conclusione con raccomandazione motivata. Non inventare dati o prezzi." ),
			'Articolo pillar' => array( 'prompt' => "Scrivi un articolo pillar ampio e autorevole, con tono chiaro, didattico e operativo, per un pubblico misto. Copri l'argomento in modo esaustivo con molte sezioni H2/H3, collegamenti concettuali tra i temi e una conclusione che riepiloga i punti chiave. Intento di ricerca informazionale." ),
		);
		foreach ( $defaults as $name => $data ) { $this->create_article_type( array_merge( array( 'name' => $name, 'is_active' => 1 ), $data ) ); }
		update_option( self::DEFAULTS_OPTION, current_time( 'mysql' ), false );
	}

	private function formats_for_data( $data ) {
		$format_map = array(
			'id' => '%d',
			'is_active' => '%d',
			'name' => '%s',
			'description' => '%s',
			'tone' => '%s',
			'length' => '%s',
			'search_intent' => '%s',
			'reader_level' => '%s',
			'prompt' => '%s',
			'image_prompt' => '%s',
			'structure' => '%s',
			'required_sections' => '%s',
			'forbidden_patterns' => '%s',
			'preferred_tags' => '%s',
			'quality_checklist' => '%s',
			'allowed_category_ids' => '%s',
			'created_at' => '%s',
			'updated_at' => '%s',
		);

		$formats = array();
		foreach ( array_keys( $data ) as $key ) {
			$formats[] = $format_map[ $key ] ?? '%s';
		}
		return $formats;
	}
}
