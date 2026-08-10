<?php
/**
 * Plugin Name:       PNG to WebP Converter
 * Description:       Convert PNG and JPG images to WebP directly from your WordPress dashboard. Fast, simple, and privacy-friendly.
 * Version:           1.0.1
 * Author:            Your Name
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       png-to-webp-converter
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      7.4
 *
 * @package PNG_To_WebP_Converter
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'PTW_VERSION', '1.0.1' );
define( 'PTW_PLUGIN_FILE', __FILE__ );
define( 'PTW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PTW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PTW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PTW_TEXT_DOMAIN', 'png-to-webp-converter' );
define( 'PTW_OPTION_NAME', 'ptw_settings' );

/**
 * Load required class files.
 */
require_once PTW_PLUGIN_DIR . 'includes/class-ptw-converter.php';
require_once PTW_PLUGIN_DIR . 'includes/class-ptw-settings.php';
require_once PTW_PLUGIN_DIR . 'includes/class-ptw-media.php';
require_once PTW_PLUGIN_DIR . 'includes/class-ptw-admin.php';
require_once PTW_PLUGIN_DIR . 'includes/class-ptw-ajax.php';
require_once PTW_PLUGIN_DIR . 'includes/class-ptw-plugin.php';

/**
 * Returns the single running instance of the plugin.
 *
 * @return PTW_Plugin
 */
function ptw_run_plugin() {
	return PTW_Plugin::instance();
}
ptw_run_plugin();

/**
 * Activation hook. Sets sane default options; performs no destructive actions.
 */
function ptw_activate_plugin() {
	if ( false === get_option( PTW_OPTION_NAME ) ) {
		$defaults = PTW_Settings::get_default_settings();
		add_option( PTW_OPTION_NAME, $defaults );
	}
}
register_activation_hook( __FILE__, 'ptw_activate_plugin' );

/**
 * Deactivation hook. Intentionally does not delete any data or media files.
 */
function ptw_deactivate_plugin() {
	// Intentionally left blank. No settings or media are removed on deactivation.
}
register_deactivation_hook( __FILE__, 'ptw_deactivate_plugin' );
