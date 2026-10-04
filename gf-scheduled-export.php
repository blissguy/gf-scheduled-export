<?php
/**
 * Plugin Name: Gravity Forms Scheduled Entry Exports
 * Description: Automatically emails you a spreadsheet of new form submissions on an hourly, weekly, or monthly schedule. Works with Gravity Forms.
 * Version: 1.3.0
 * Author: Mixbus Marketing
 * Author URI: https://mixbusmarketing.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: gravityforms
 * Text Domain: gf-scheduled-export
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GFSE_VERSION', '1.3.0' );
define( 'GFSE_PLUGIN_FILE', __FILE__ );
define( 'GFSE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

define( 'GFSE_CRON_HOOK', 'gfse_process_feeds' );
define( 'GFSE_OPTION_FEED_STATUS', 'gfse_feed_status' );

/**
 * Register the add-on with the official Gravity Forms Feed Add-On framework.
 */
function gfse_load_addon() {
	if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
		return;
	}

	GFForms::include_feed_addon_framework();

	require_once GFSE_PLUGIN_DIR . 'includes/class-exporter.php';
	require_once GFSE_PLUGIN_DIR . 'includes/class-gf-scheduled-export.php';

	GFAddOn::register( 'GF_Scheduled_Export' );
}
add_action( 'gform_loaded', 'gfse_load_addon', 5 );

function gfse_activate() {
	if ( ! wp_next_scheduled( GFSE_CRON_HOOK ) ) {
		// Align to the top of the next hour so delivery times land close to
		// the time the user picked.
		$next_hour = strtotime( gmdate( 'Y-m-d H:00:00' ) ) + HOUR_IN_SECONDS;
		wp_schedule_event( $next_hour, 'hourly', GFSE_CRON_HOOK );
	}
}
register_activation_hook( __FILE__, 'gfse_activate' );

function gfse_deactivate() {
	wp_clear_scheduled_hook( GFSE_CRON_HOOK );
}
register_deactivation_hook( __FILE__, 'gfse_deactivate' );

/**
 * Let the site owner know this plugin needs Gravity Forms.
 */
function gfse_missing_gf_notice() {
	if ( class_exists( 'GFForms' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'Gravity Forms Scheduled Entry Exports needs Gravity Forms to be installed and active before it can send your scheduled exports.', 'gf-scheduled-export' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'gfse_missing_gf_notice' );
