<?php
/**
 * Clean up everything this plugin stored when it is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'gfse_feed_status' );

// Options from pre-release builds.
delete_option( 'gfse_settings' );
delete_option( 'gfse_last_run' );
delete_option( 'gfse_last_run_month' );

wp_clear_scheduled_hook( 'gfse_process_feeds' );
wp_clear_scheduled_hook( 'gfse_check_schedule' );

// Remove this add-on's feeds from the Gravity Forms feeds table, if present.
global $wpdb;
$gfse_table = $wpdb->prefix . 'gf_addon_feed';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $gfse_table ) ) === $gfse_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup on uninstall; no API exists while GF may be inactive.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE addon_slug = %s', $gfse_table, 'gf-scheduled-export' ) );
}
