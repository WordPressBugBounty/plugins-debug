<?php
/**
 * Uninstall handler for the Debug plugin.
 *
 * Cleans up plugin options and queued notifications. Deliberately does NOT
 * modify wp-config.php (that is the user's own file — leaving their debug flags
 * intact is the safe default).
 *
 * @package Debug
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'debug_notification' );
delete_option( 'debug_pending_notifications' );
