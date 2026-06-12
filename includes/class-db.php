<?php
/**
 * Database management.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database tables and schema metadata.
 */
class DB {
	/**
	 * Return log table name.
	 *
	 * @return string
	 */
	public function get_logs_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'wpai_publisher_logs';
	}

	/**
	 * Create or update plugin tables.
	 *
	 * @return void
	 */
	public function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $this->get_logs_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT unsigned NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			level VARCHAR(20) NOT NULL,
			source VARCHAR(100) NOT NULL,
			message TEXT NOT NULL,
			context LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY level (level),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Get stored schema version.
	 *
	 * @return string
	 */
	public function get_schema_version() {
		return (string) get_option( 'wpai_publisher_db_schema_version', '0' );
	}

	/**
	 * Store schema version.
	 *
	 * @param string $version Schema version.
	 * @return void
	 */
	public function set_schema_version( $version ) {
		update_option( 'wpai_publisher_db_schema_version', sanitize_text_field( (string) $version ), false );
	}

	/**
	 * Check plugin tables.
	 *
	 * @return array<string,bool>
	 */
	public function check_tables() {
		global $wpdb;

		$table_name = $this->get_logs_table_name();
		$found      = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		return array(
			'logs' => $found === $table_name,
		);
	}
}
