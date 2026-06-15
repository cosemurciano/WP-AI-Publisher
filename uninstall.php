<?php
/**
 * Uninstall handler.
 *
 * By default WP AI Publisher keeps all data so accidental removal does not
 * destroy operational history. Destructive cleanup is opt-in via the
 * "Elimina i dati alla disinstallazione" setting (or the
 * `wpai_publisher_delete_data_on_uninstall` filter).
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$wpai_settings = get_option( 'wpai_publisher_settings', array() );
$wpai_delete   = is_array( $wpai_settings ) && ! empty( $wpai_settings['delete_data_on_uninstall'] );

/**
 * Filter whether WP AI Publisher should delete its data on uninstall.
 *
 * @param bool $wpai_delete Current opt-in decision from settings.
 */
$wpai_delete = (bool) apply_filters( 'wpai_publisher_delete_data_on_uninstall', $wpai_delete );

if ( ! $wpai_delete ) {
	return;
}

global $wpdb;

$wpai_tables = array(
	$wpdb->prefix . 'wpai_publisher_logs',
	$wpdb->prefix . 'wpai_publisher_jobs',
	$wpdb->prefix . 'wpai_publisher_content_ideas',
	$wpdb->prefix . 'wpai_publisher_article_types',
);

foreach ( $wpai_tables as $wpai_table ) {
	// Table names come from $wpdb->prefix, not user input; DROP TABLE cannot be prepared.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpai_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
}

$wpai_options = array(
	'wpai_publisher_settings',
	'wpai_publisher_version',
	'wpai_publisher_db_schema_version',
	'wpai_publisher_default_article_type_rows_created',
	'wpai_publisher_default_article_types_created',
);

foreach ( $wpai_options as $wpai_option ) {
	delete_option( $wpai_option );
}

// Remove the dedicated capability from every role. The default capability name
// is used directly because the plugin is not bootstrapped during uninstall.
$wpai_capability = 'manage_wp_ai_publisher';
if ( function_exists( 'wp_roles' ) ) {
	foreach ( wp_roles()->role_objects as $wpai_role ) {
		if ( is_object( $wpai_role ) && $wpai_role->has_cap( $wpai_capability ) ) {
			$wpai_role->remove_cap( $wpai_capability );
		}
	}
}
