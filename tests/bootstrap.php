<?php
/**
 * Lightweight PHPUnit bootstrap.
 *
 * These are pure-unit tests that exercise plugin helpers without a full
 * WordPress test installation. The few WordPress functions used by the helpers
 * under test are stubbed below.
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Minimal sanitize_key() stub matching WordPress behaviour for ASCII keys.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );

		return (string) preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

require_once dirname( __DIR__ ) . '/includes/helpers.php';
