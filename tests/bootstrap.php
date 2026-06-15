<?php
/**
 * Lightweight PHPUnit bootstrap.
 *
 * Pure-unit tests that exercise plugin logic without a full WordPress install.
 * The WordPress functions used by the units under test are stubbed below, then
 * the relevant plugin files are loaded so every test can use them.
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) { return $text; }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = null ) { return esc_html( $text ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) { return esc_html( $text ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) { return (string) $url; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) { return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return trim( (string) preg_replace( '/\s+/', ' ', (string) $s ) ); }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $s ) { return (string) preg_replace( '/[^a-z0-9\-]+/', '-', strtolower( trim( (string) $s ) ) ); }
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) { return abs( (int) $v ); }
}
if ( ! function_exists( 'remove_accents' ) ) {
	function remove_accents( $s ) { return (string) $s; }
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, (int) $flags ); }
}
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); }
}
if ( ! function_exists( 'wp_trim_words' ) ) {
	function wp_trim_words( $text, $num = 55, $more = '…' ) {
		$words = preg_split( '/\s+/', trim( wp_strip_all_tags( $text ) ) );
		return count( $words ) <= $num ? implode( ' ', $words ) : implode( ' ', array_slice( $words, 0, $num ) ) . $more;
	}
}
if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( $html, $allowed ) {
		$tags = '';
		foreach ( array_keys( (array) $allowed ) as $tag ) { $tags .= '<' . $tag . '>'; }
		return strip_tags( (string) $html, $tags );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) { return $default; }
}
if ( ! function_exists( 'add_filter' ) ) {
	$GLOBALS['__wpai_filters'] = array();
	function add_filter( $tag, $cb, $priority = 10, $args = 1 ) { $GLOBALS['__wpai_filters'][ $tag ][] = $cb; return true; }
}
if ( ! function_exists( 'has_filter' ) ) {
	function has_filter( $tag, $cb = false ) { return ! empty( $GLOBALS['__wpai_filters'][ $tag ] ); }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		$args = array_slice( func_get_args(), 1 );
		if ( ! empty( $GLOBALS['__wpai_filters'][ $tag ] ) ) {
			foreach ( $GLOBALS['__wpai_filters'][ $tag ] as $cb ) { $args[0] = call_user_func_array( $cb, $args ); }
		}
		return $args[0];
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code; private $message; private $data;
		public function __construct( $code = '', $message = '', $data = '' ) { $this->code = $code; $this->message = $message; $this->data = $data; }
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
		public function get_error_data() { return $this->data; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
}

require_once dirname( __DIR__ ) . '/includes/helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-classic-content-builder.php';
require_once dirname( __DIR__ ) . '/includes/class-ai-provider-adapter.php';
