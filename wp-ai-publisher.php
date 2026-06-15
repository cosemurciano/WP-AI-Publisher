<?php
/**
 * Plugin Name: WP AI Publisher
 * Plugin URI: https://github.com/cosemurciano/WP-AI-Publisher
 * GitHub Plugin URI: https://github.com/cosemurciano/WP-AI-Publisher
 * Primary Branch: main
 * Description: Base modulare per la pubblicazione assistita da AI in WordPress, con stato sistema, impostazioni, log e adapter per il sistema AI nativo di WordPress.
 * Version: 0.5.17
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: WP AI Publisher Team
 * Author URI: https://github.com/cosemurciano
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-ai-publisher
 * Domain Path: /languages
 * Update URI: https://github.com/cosemurciano/WP-AI-Publisher
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAIP_VERSION', '0.5.17' );
if ( ! defined( 'WPAIP_MIN_WP_VERSION' ) ) {
	define( 'WPAIP_MIN_WP_VERSION', '6.5' );
}
if ( ! defined( 'WPAIP_MIN_PHP_VERSION' ) ) {
	define( 'WPAIP_MIN_PHP_VERSION', '8.1' );
}
if ( ! defined( 'WPAIP_ENABLE_ARTICLE_TYPE_REPOSITORY' ) ) {
	define( 'WPAIP_ENABLE_ARTICLE_TYPE_REPOSITORY', true );
}
define( 'WPAIP_PLUGIN_FILE', __FILE__ );
define( 'WPAIP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAIP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPAIP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPAIP_DB_SCHEMA_VERSION', '7' );

require_once WPAIP_PLUGIN_DIR . 'includes/helpers.php';

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'WPAIPublisher\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( $prefix ) );
		$file_name      = 'class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';
		$file_path      = WPAIP_PLUGIN_DIR . 'includes/' . $file_name;

		if ( is_readable( $file_path ) ) {
			require_once $file_path;
		}
	}
);

register_activation_hook( __FILE__, array( 'WPAIPublisher\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPAIPublisher\\Deactivator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		WPAIPublisher\Plugin::instance();
	}
);
