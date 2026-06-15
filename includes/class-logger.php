<?php
/**
 * Logging service.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes plugin logs to the database with a safe fallback.
 */
class Logger {
	/**
	 * Database service.
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param DB $db Database service.
	 */
	public function __construct( DB $db ) {
		$this->db = $db;
	}

	/**
	 * Log emergency message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public function emergency( $message, $context = array() ) {
		$this->log( 'emergency', 'plugin', $message, $context );
	}

	/**
	 * Log error message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public function error( $message, $context = array() ) {
		$this->log( 'error', 'plugin', $message, $context );
	}

	/**
	 * Log warning message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public function warning( $message, $context = array() ) {
		$this->log( 'warning', 'plugin', $message, $context );
	}

	/**
	 * Log informational message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public function info( $message, $context = array() ) {
		$this->log( 'info', 'plugin', $message, $context );
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public function debug( $message, $context = array() ) {
		$this->log( 'debug', 'plugin', $message, $context );
	}

	/**
	 * Store a log line.
	 *
	 * @param string $level Log level.
	 * @param string $source Source component.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public function log( $level, $source, $message, $context = array() ) {
		global $wpdb;

		$settings = wpai_publisher_get_settings();
		if ( empty( $settings['enable_logging'] ) ) {
			return;
		}

		$level   = sanitize_key( $level );
		$source  = sanitize_text_field( (string) $source );
		$message = sanitize_textarea_field( (string) $message );
		$context = is_array( $context ) ? $context : array( 'value' => $context );

		$encoded_context = wp_json_encode( $context );
		if ( false === $encoded_context ) {
			$encoded_context = wp_json_encode( array( 'serialization_error' => true ) );
		}

		$tables = $this->db->check_tables();
		if ( empty( $tables['logs'] ) ) {
			$this->fallback_error_log( $level, $source, $message, $encoded_context );
			return;
		}

		$inserted = $wpdb->insert(
			$this->db->get_logs_table_name(),
			array(
				'created_at' => current_time( 'mysql' ),
				'level'      => $level,
				'source'     => $source,
				'message'    => $message,
				'context'    => $encoded_context,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->fallback_error_log( $level, $source, $message, $encoded_context );
		}
	}

	/**
	 * Get latest critical logs.
	 *
	 * @param int $limit Number of rows.
	 * @return array<int,object>
	 */
	public function get_last_critical_errors( $limit = 5 ) {
		global $wpdb;

		$tables = $this->db->check_tables();
		if ( empty( $tables['logs'] ) ) {
			return array();
		}

		$limit = max( 1, min( 50, absint( $limit ) ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT created_at, level, source, message, context FROM {$this->db->get_logs_table_name()} WHERE level IN ('emergency', 'error') ORDER BY created_at DESC, id DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Write to PHP error log only as fallback.
	 *
	 * @param string $level Level.
	 * @param string $source Source.
	 * @param string $message Message.
	 * @param string $context JSON context.
	 * @return void
	 */
	private function fallback_error_log( $level, $source, $message, $context ) {
		$error_message = sprintf( '[WP AI Publisher] %s.%s: %s %s', $source, $level, $message, $context );
		error_log( $error_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
