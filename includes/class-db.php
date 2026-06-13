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
	 * Return jobs table name.
	 *
	 * @return string
	 */
	public function get_jobs_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'wpai_publisher_jobs';
	}

	/**
	 * Return content ideas table name.
	 *
	 * @return string
	 */
	public function get_content_ideas_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'wpai_publisher_content_ideas';
	}

	/**
	 * Create or update plugin tables.
	 *
	 * @return void
	 */
	public function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$logs_table_name          = $this->get_logs_table_name();
		$jobs_table_name          = $this->get_jobs_table_name();
		$content_ideas_table_name = $this->get_content_ideas_table_name();
		$charset_collate          = $wpdb->get_charset_collate();

		$logs_sql = "CREATE TABLE {$logs_table_name} (
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

		$jobs_sql = "CREATE TABLE {$jobs_table_name} (
			id BIGINT unsigned NOT NULL AUTO_INCREMENT,
			job_type VARCHAR(80) NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			priority SMALLINT unsigned NOT NULL DEFAULT 10,
			payload LONGTEXT NULL,
			attempts SMALLINT unsigned NOT NULL DEFAULT 0,
			max_attempts SMALLINT unsigned NOT NULL DEFAULT 3,
			error_message TEXT NULL,
			created_at DATETIME NOT NULL,
			started_at DATETIME NULL,
			finished_at DATETIME NULL,
			output LONGTEXT NULL,
			post_id BIGINT unsigned NULL,
			estimated_cost DECIMAL(12,6) NULL,
			PRIMARY KEY  (id),
			KEY job_type (job_type),
			KEY status (status),
			KEY priority (priority),
			KEY post_id (post_id),
			KEY created_at (created_at)
		) {$charset_collate};";


		$content_ideas_sql = "CREATE TABLE {$content_ideas_table_name} (
			id BIGINT unsigned NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'new',
			topic TEXT NOT NULL,
			keyword VARCHAR(255) NULL,
			language VARCHAR(20) NOT NULL DEFAULT 'it',
			target_audience VARCHAR(255) NULL,
			tutorial_level VARCHAR(50) NULL,
			notes LONGTEXT NULL,
			dry_run_output LONGTEXT NULL,
			validation_notes LONGTEXT NULL,
			approved_at DATETIME NULL,
			draft_created_at DATETIME NULL,
			draft_post_id BIGINT unsigned NULL,
			draft_status VARCHAR(30) NULL,
			draft_error TEXT NULL,
			related_post_id BIGINT unsigned NULL,
			job_id BIGINT unsigned NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY language (language),
			KEY related_post_id (related_post_id),
			KEY draft_post_id (draft_post_id),
			KEY draft_status (draft_status),
			KEY job_id (job_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $logs_sql );
		dbDelta( $jobs_sql );
		dbDelta( $content_ideas_sql );
		$this->ensure_content_ideas_draft_columns();
	}

	/**
	 * Ensure schema 4 draft columns/indexes exist for normal upgrades.
	 *
	 * @return void
	 */
	private function ensure_content_ideas_draft_columns() {
		global $wpdb;

		$table = $this->get_content_ideas_table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
		$column_sql = array(
			'approved_at'      => 'ADD COLUMN approved_at DATETIME NULL AFTER validation_notes',
			'draft_created_at' => 'ADD COLUMN draft_created_at DATETIME NULL AFTER approved_at',
			'draft_post_id'    => 'ADD COLUMN draft_post_id BIGINT unsigned NULL AFTER draft_created_at',
			'draft_status'     => 'ADD COLUMN draft_status VARCHAR(30) NULL AFTER draft_post_id',
			'draft_error'      => 'ADD COLUMN draft_error TEXT NULL AFTER draft_status',
		);

		foreach ( $column_sql as $column => $sql ) {
			if ( ! in_array( $column, $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$table} {$sql}" );
			}
		}

		$indexes = (array) $wpdb->get_col( "SHOW INDEX FROM {$table}", 2 );
		foreach ( array( 'draft_post_id', 'draft_status' ) as $index ) {
			if ( ! in_array( $index, $indexes, true ) ) {
				$wpdb->query( "ALTER TABLE {$table} ADD KEY {$index} ({$index})" );
			}
		}
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

		$logs_table_name          = $this->get_logs_table_name();
		$jobs_table_name          = $this->get_jobs_table_name();
		$content_ideas_table_name = $this->get_content_ideas_table_name();
		$logs_found               = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $logs_table_name ) );
		$jobs_found               = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $jobs_table_name ) );
		$content_ideas_found      = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $content_ideas_table_name ) );

		return array(
			'logs'          => $logs_found === $logs_table_name,
			'jobs'          => $jobs_found === $jobs_table_name,
			'content_ideas' => $content_ideas_found === $content_ideas_table_name,
		);
	}
}
