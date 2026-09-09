<?php
/**
 * Plugin Name: Debug
 * Description: Debug your WordPress site, multisite and plugins. Debug is a development/production tool that helps you remove bugs from your WordPress website.
 * Version: 1.14
 * Author: SoniNow Team
 * Author URI: https://soninow.com
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: debug
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DEBUG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DEBUG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DEBUG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'DEBUG_PLUGIN_VERSION', '1.14' );

// Load translations (must be early so admin + strings localize).
add_action( 'plugins_loaded', 'debug_load_textdomain' );
function debug_load_textdomain() {
	load_plugin_textdomain( 'debug', false, dirname( DEBUG_PLUGIN_BASENAME ) . '/languages' );
}

// Function library files.
require_once DEBUG_PLUGIN_DIR . 'functions/function.php';
require_once DEBUG_PLUGIN_DIR . 'functions/plugin.php';

/**
 * On activation: set capability / defaults and ensure ABSPATH checks are in place.
 */
function debug_activate() {
	// Create a default empty options record so get_option() always returns an array.
	if ( false === get_option( 'debug_notification', false ) ) {
		update_option( 'debug_notification', array(
			'enable' => '0',
			'email'  => '',
		), false );
	}
}
register_activation_hook( __FILE__, 'debug_activate' );
