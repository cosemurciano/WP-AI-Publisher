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
		if ( ! current_user_can( 'manage_options' ) ) { return false; }
		$data = $this->sanitize_article_type_data( $data );
		if ( '' === $data['name'] ) { return false; }
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = null;
		$ok = $wpdb->insert( $this->get_table_name(), $data, $this->formats() );
		return false === $ok ? false : absint( $wpdb->insert_id );
	}

	public function update_article_type( $id, $data ) {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) ) { return false; }
		$id = absint( $id );
		if ( 0 === $id ) { return false; }
		$data = $this->sanitize_article_type_data( $data );
		if ( '' === $data['name'] ) { return false; }
		$data['updated_at'] = current_time( 'mysql' );
		unset( $data['created_at'] );
		return false !== $wpdb->update( $this->get_table_name(), $data, array( 'id' => $id ), $this->formats( false ), array( '%d' ) );
	}

	public function delete_article_type( $id ) {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) ) { return false; }
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
		$list_fields = array( 'allowed_category_ids', 'preferred_tags', 'required_sections', 'forbidden_patterns', 'quality_checklist' );
		foreach ( $list_fields as $field ) {
			$value = $row[ $field ] ?? '';
			if ( 'allowed_category_ids' === $field ) {
				$decoded = json_decode( (string) $value, true );
				$row[ $field ] = array_values( array_filter( array_map( 'absint', is_array( $decoded ) ? $decoded : preg_split( '/[\r\n,]+/', (string) $value ) ) ) );
			} else {
				$row[ $field ] = wpai_publisher_split_context_list( (string) $value );
			}
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
			'structure' => sanitize_textarea_field( (string) ( $data['structure'] ?? '' ) ),
			'required_sections' => sanitize_textarea_field( (string) ( $data['required_sections'] ?? '' ) ),
			'forbidden_patterns' => sanitize_textarea_field( (string) ( $data['forbidden_patterns'] ?? '' ) ),
			'tone' => sanitize_key( (string) ( $data['tone'] ?? '' ) ),
			'length' => sanitize_key( (string) ( $data['length'] ?? '' ) ),
			'search_intent' => sanitize_key( (string) ( $data['search_intent'] ?? '' ) ),
			'reader_level' => sanitize_key( (string) ( $data['reader_level'] ?? '' ) ),
			'allowed_category_ids' => wp_json_encode( $allowed_ids ),
			'preferred_tags' => sanitize_textarea_field( (string) ( $data['preferred_tags'] ?? '' ) ),
			'quality_checklist' => sanitize_textarea_field( (string) ( $data['quality_checklist'] ?? '' ) ),
		);
	}

	public function maybe_create_default_article_types() {
		if ( get_option( self::DEFAULTS_OPTION ) || ! current_user_can( 'manage_options' ) ) { return; }
		global $wpdb;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->get_table_name() ) ) !== $this->get_table_name() ) { return; }
		$defaults = array(
			'Tutorial WordPress passo passo' => array( 'search_intent' => 'tutorial', 'length' => 'lunga', 'reader_level' => 'principianti', 'tone' => 'chiaro_didattico_e_operativo', 'required_sections' => "Introduzione\nPrerequisiti\nProcedura passo passo\nVerifica finale" ),
			'Guida informativa' => array( 'search_intent' => 'informazionale', 'length' => 'media', 'reader_level' => 'misto', 'tone' => 'divulgativo_semplice' ),
			'Checklist operativa' => array( 'search_intent' => 'tutorial', 'length' => 'breve', 'reader_level' => 'intermedi', 'tone' => 'professionale_tecnico' ),
			'Confronto / comparativa' => array( 'search_intent' => 'comparativo', 'length' => 'media', 'reader_level' => 'misto', 'tone' => 'commerciale_informativo' ),
			'Articolo pillar' => array( 'search_intent' => 'informazionale', 'length' => 'pillar', 'reader_level' => 'misto', 'tone' => 'chiaro_didattico_e_operativo' ),
		);
		foreach ( $defaults as $name => $data ) { $this->create_article_type( array_merge( array( 'name' => $name, 'is_active' => 1 ), $data ) ); }
		update_option( self::DEFAULTS_OPTION, current_time( 'mysql' ), false );
	}

	private function formats( $include_created = true ) {
		$formats = array( '%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' );
		return $include_created ? array_merge( array( '%s','%s' ), $formats ) : array_merge( $formats, array( '%s' ) );
	}
}
