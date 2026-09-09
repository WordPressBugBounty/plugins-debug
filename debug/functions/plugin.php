<?php
/**
 * Menu + hooks for the Debug plugin.
 *
 * @package Debug
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register admin menus.
 */
function debug_admin_menu() {
	add_menu_page(
		__( 'Debug', 'debug' ),
		__( 'Debug', 'debug' ),
		'manage_options',
		'debug_settings',
		'debug_admin_page',
		'dashicons-migrate'
	);

	add_submenu_page(
		'debug_settings',
		__( 'Settings', 'debug' ),
		__( 'Settings', 'debug' ),
		'manage_options',
		'debug_settings',
		'debug_admin_page'
	);

	add_submenu_page(
		'debug_settings',
		__( 'Error Log', 'debug' ),
		__( 'Error Log', 'debug' ),
		'manage_options',
		'debug_log',
		'debug_log_file_page'
	);
}
add_action( 'admin_menu', 'debug_admin_menu' );

/**
 * Admin settings page callback.
 */
function debug_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'debug' ) );
	}
	require_once DEBUG_PLUGIN_DIR . 'admin/setting.php';
}

/**
 * Admin log page callback.
 */
function debug_log_file_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'debug' ) );
	}
	require_once DEBUG_PLUGIN_DIR . 'admin/debuglog.php';
}

/**
 * Add a settings link on the plugin list page. (Fixed broken href.)
 *
 * @param array $links Existing action links.
 * @return array
 */
function debug_settings_link( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=debug_settings' ) ) . '">' . esc_html__( 'Settings', 'debug' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . DEBUG_PLUGIN_BASENAME, 'debug_settings_link' );

/**
 * Register the error handler only when email notifications are enabled.
 *
 * Hooked on plugins_loaded for the earliest possible coverage (catches errors
 * during theme/plugin loading that 'init' would miss), and re-registered on
 * init as a belt-and-braces measure.
 */
function debug_register_error_handler() {
	$debug_setting = debug_get_options();
	if ( isset( $debug_setting['enable'] ) && '1' === $debug_setting['enable'] ) {
		set_error_handler( 'debug_error_handler' );
	}
}
add_action( 'plugins_loaded', 'debug_register_error_handler', 1 );

/**
 * Flush queued error notifications on shutdown (non-blocking).
 */
function debug_shutdown_flush() {
	debug_flush_notifications();
}
add_action( 'shutdown', 'debug_shutdown_flush', 20 );

/**
 * Register the admin-post download endpoint (clean binary output).
 */
function debug_register_admin_post() {
	add_action( 'admin_post_debug_download_log', 'debug_handle_file_download' );
}
add_action( 'admin_init', 'debug_register_admin_post' );

/**
 * Enqueue a small stylesheet for the debug pages (CSP-friendly, no inline CSS).
 */
function debug_enqueue_assets( $hook ) {
	if ( false === strpos( $hook, 'debug_settings' ) && false === strpos( $hook, 'debug_log' ) ) {
		return;
	}
	wp_enqueue_style(
		'debug-admin',
		DEBUG_PLUGIN_URL . 'assets/debug.css',
		array(),
		DEBUG_PLUGIN_VERSION
	);
	wp_enqueue_script(
		'debug-admin',
		DEBUG_PLUGIN_URL . 'assets/debug.js',
		array(),
		DEBUG_PLUGIN_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'debug_enqueue_assets' );
