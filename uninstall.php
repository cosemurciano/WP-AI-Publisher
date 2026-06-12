<?php
/**
 * Uninstall handler.
 *
 * Phase 1 keeps all data by default. This file intentionally avoids deleting
 * settings or logs so accidental removal does not destroy operational history.
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
