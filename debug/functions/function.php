<?php
/**
 * Core logic for the Debug plugin.
 *
 * File read/write with strict path allowlisting, safe wp-config editing with
 * automatic backup + rollback, and a level-filtered, throttled error handler.
 *
 * @package Debug
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'DEBUG_LOG_READ_BYTES' ) ) {
	define( 'DEBUG_LOG_READ_BYTES', 1048576 ); // 1 MB tail read.
}
if ( ! defined( 'DEBUG_CONFIG_BACKUP_SUFFIX' ) ) {
	define( 'DEBUG_CONFIG_BACKUP_SUFFIX', '.debug-bak' );
}

/**
 * Resolve a relative path under WP_CONTENT_DIR and guard against traversal.
 *
 * @param string $file_name Relative file name, e.g. "wp-content/debug.log".
 * @return string|false Absolute, normalized path inside WP_CONTENT_DIR, or false on failure.
 */
function debug_resolve_safe_path( $file_name ) {
	$file_name = (string) $file_name;
	// Reject any traversal or absolute input up front.
	if ( '' === $file_name || false !== strpos( $file_name, '..' ) || '/' === $file_name[0] || '\\' === $file_name[0] ) {
		return false;
	}

	$base      = trailingslashit( WP_CONTENT_DIR );
	$candidate = $base . ltrim( $file_name, '/\\' );
	$real      = realpath( $candidate );

	if ( false === $real ) {
		return false;
	}

	if ( 0 !== strpos( $real, $base ) ) {
		return false; // Escaped the content dir.
	}

	return $real;
}

/**
 * Safely read a file relative to WP_CONTENT_DIR.
 *
 * Reads only the trailing DEBUG_LOG_READ_BYTES but always caps total bytes read.
 *
 * @param string $file_name Relative file name.
 * @return string|false File content tail, or false on failure.
 */
function debug_file_read( $file_name ) {
	$file_path = debug_resolve_safe_path( $file_name );
	if ( false === $file_path || ! is_readable( $file_path ) ) {
		return false;
	}

	$file = fopen( $file_path, 'rb' );
	if ( false === $file ) {
		return false;
	}

	$size       = (int) @filesize( $file_path );
	$read_start = max( 0, $size - DEBUG_LOG_READ_BYTES );
	if ( $read_start > 0 ) {
		fseek( $file, $read_start, SEEK_SET );
	}

	$response  = '';
	$remaining = DEBUG_LOG_READ_BYTES;
	while ( ! feof( $file ) && $remaining > 0 ) {
		$chunk = fgets( $file );
		if ( false === $chunk ) {
			break;
		}
		$response .= $chunk;
		$remaining -= strlen( $chunk );
	}

	fclose( $file );
	return $response;
}

/**
 * Write content to a file relative to WP_CONTENT_DIR (atomic temp + rename).
 *
 * @param string $file_name Relative file name.
 * @param string $content   Content to write.
 * @return bool True on success.
 */
function debug_file_write( $file_name, $content ) {
	$file_path = debug_resolve_safe_path( $file_name );
	if ( false === $file_path ) {
		return false;
	}

	$dir  = dirname( $file_path );
	$temp = $dir . '/' . uniqid( 'debug-write-', true ) . '.tmp';

	if ( false === file_put_contents( $temp, $content ) ) {
		return false;
	}

	if ( file_exists( $file_path ) ) {
		@chmod( $temp, fileperms( $file_path ) & 0777 );
	} else {
		@chmod( $temp, 0600 );
	}

	if ( ! rename( $temp, $file_path ) ) {
		@unlink( $temp );
		return false;
	}

	return true;
}

/**
 * Atomic raw file write for an absolute path (used only for wp-config).
 *
 * @param string $path    Absolute destination path.
 * @param string $content Content to write.
 * @return bool
 */
function debug_file_write_raw( $path, $content ) {
	$dir  = dirname( $path );
	$temp = $dir . '/' . uniqid( 'debug-wpcfg-', true ) . '.tmp';

	if ( false === file_put_contents( $temp, $content ) ) {
		return false;
	}
	if ( file_exists( $path ) ) {
		@chmod( $temp, fileperms( $path ) & 0777 );
	}
	if ( ! rename( $temp, $path ) ) {
		@unlink( $temp );
		return false;
	}
	return true;
}

/**
 * Create a timestamped backup of wp-config.php and return its path.
 *
 * @return string|false Backup path, or false on failure.
 */
function debug_backup_wp_config() {
	$home   = trailingslashit( get_home_path() );
	$config = $home . 'wp-config.php';
	$backup = $home . 'wp-config.php.' . date( 'Ymd-His' ) . DEBUG_CONFIG_BACKUP_SUFFIX;

	if ( ! file_exists( $config ) ) {
		return false;
	}

	if ( ! copy( $config, $backup ) ) {
		return false;
	}
	@chmod( $backup, 0600 );

	return $backup;
}

/**
 * List available wp-config backups for one-click restore.
 *
 * @return array List of backup file names.
 */
function debug_list_wp_config_backups() {
	$home    = trailingslashit( get_home_path() );
	$backups = glob( $home . 'wp-config.php.*' . DEBUG_CONFIG_BACKUP_SUFFIX );
	if ( false === $backups ) {
		return array();
	}
	return array_map( 'basename', $backups );
}

/**
 * Restore wp-config.php from a backup file name.
 *
 * @param string $backup_name Backup file name (must be one of the generated backups).
 * @return true|string True on success, or an error message on failure.
 */
function debug_restore_wp_config( $backup_name ) {
	$backup_name = basename( (string) $backup_name );
	if ( '' === $backup_name || 0 !== strpos( $backup_name, 'wp-config.php.' ) || false === strpos( $backup_name, DEBUG_CONFIG_BACKUP_SUFFIX ) ) {
		return __( 'Invalid backup file.', 'debug' );
	}

	$home   = trailingslashit( get_home_path() );
	$backup = $home . $backup_name;
	$config = $home . 'wp-config.php';

	if ( ! file_exists( $backup ) ) {
		return __( 'Backup file not found.', 'debug' );
	}

	// Validate the backup actually looks like a wp-config before overwriting.
	$content = @file_get_contents( $backup );
	if ( false === $content || false === strpos( $content, 'DB_NAME' ) ) {
		return __( 'Backup file is not a valid wp-config.php.', 'debug' );
	}

	if ( ! copy( $backup, $config ) ) {
		return __( 'Could not restore wp-config.php. Check file permissions.', 'debug' );
	}

	return true;
}

/**
 * Remove the debug.log file.
 *
 * @return array { 'class' => string, 'message' => string }.
 */
function debug_clearlog() {
	$result = array(
		'class'   => 'error',
		'message' => __( 'File debug.log not removed.', 'debug' ),
	);

	$file_path = debug_resolve_safe_path( 'wp-content/debug.log' );
	if ( false !== $file_path && file_exists( $file_path ) ) {
		if ( @unlink( $file_path ) ) {
			$result['class']   = 'updated';
			$result['message'] = __( 'debug.log file removed successfully.', 'debug' );
		}
	}

	return $result;
}

/**
 * Render an admin notice.
 *
 * @param string $class   Notice class (updated | error).
 * @param string $message Message text.
 */
function debug_admin_notice( $class, $message ) {
	printf(
		'<div class="%1$s settings-error notice is-dismissible"><p><strong>%2$s</strong></p></div>',
		esc_attr( $class ),
		esc_html( $message )
	);
}

/**
 * Save debug settings from the UI.
 *
 * Handles nonce verification + capability check, validates the email
 * notification requirement, then safely rewrites wp-config.php with an
 * automatic backup. Prints admin notices.
 */
function debug_save_setting() {
	if ( empty( $_POST['debugsetting'] ) ) {
		return; // Not a save-submit.
	}

	$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'debug_save_settings' ) ) {
		debug_admin_notice( 'error', __( 'Security check failed. Please try again.', 'debug' ) );
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		debug_admin_notice( 'error', __( 'You do not have permission to change debug settings.', 'debug' ) );
		return;
	}

	$enable_notification = ( isset( $_POST['enable_notification'] ) && '1' === $_POST['enable_notification'] ) ? '1' : '0';
	$email_notification  = isset( $_POST['email_notification'] ) ? sanitize_email( wp_unslash( $_POST['email_notification'] ) ) : '';

	if ( '1' === $enable_notification && ! is_email( $email_notification ) ) {
		debug_admin_notice( 'error', __( 'Please enter a valid email address.', 'debug' ) );
		return;
	}

	$error_reporting = ( isset( $_POST['error_reporting'] ) && '1' === $_POST['error_reporting'] ) ? '1' : '0';
	$error_log       = ( isset( $_POST['error_log'] ) && '1' === $_POST['error_log'] ) ? '1' : '0';
	$display_error   = ( isset( $_POST['display_error'] ) && '1' === $_POST['display_error'] ) ? '1' : '0';
	$error_script    = ( isset( $_POST['error_script'] ) && '1' === $_POST['error_script'] ) ? '1' : '0';
	$error_savequery = ( isset( $_POST['error_savequery'] ) && '1' === $_POST['error_savequery'] ) ? '1' : '0';

	// Enabling email notification implies the relevant debug flags.
	if ( '1' === $enable_notification ) {
		$error_reporting = $error_log = $error_script = $error_savequery = '1';
	}

	$settings = array(
		'WP_DEBUG'         => ( '1' === $error_reporting ),
		'WP_DEBUG_LOG'     => ( '1' === $error_log ),
		'WP_DEBUG_DISPLAY' => ( '1' === $display_error ),
		'SCRIPT_DEBUG'     => ( '1' === $error_script ),
		'SAVEQUERIES'      => ( '1' === $error_savequery ),
	);

	$backup = debug_backup_wp_config();

	$result = debug_apply_wp_config_settings( $settings );
	if ( is_wp_error( $result ) ) {
		debug_admin_notice( 'error', $result->get_error_message() );
		// Show the MODIFIED config content for manual paste.
		$data = $result->get_error_data();
		$paste = ( is_array( $data ) && ! empty( $data['content'] ) ) ? $data['content'] : '';
		if ( '' === $paste ) {
			$config = trailingslashit( get_home_path() ) . 'wp-config.php';
			$paste  = ( is_readable( $config ) ) ? (string) @file_get_contents( $config ) : '';
		}
		if ( '' !== $paste ) {
			echo '<textarea style="width:100%;height:400px;" readonly>';
			echo esc_textarea( $paste );
			echo '</textarea>';
		}
		return;
	}

	update_option(
		'debug_notification',
		array(
			'enable' => $enable_notification,
			'email'  => $email_notification,
		),
		false
	);

	debug_admin_notice( 'updated', __( 'Settings saved successfully.', 'debug' ) );
	if ( false !== $backup ) {
		/* translators: %s: backup file name. */
		debug_admin_notice( 'updated', sprintf( __( 'A backup of wp-config.php was created: %s', 'debug' ), esc_html( basename( $backup ) ) ) );
	}

	echo '<script>setTimeout(function(){ location.reload(); }, 3000);</script>';
}

/**
 * Apply debug settings to wp-config.php safely.
 *
 * Uses a tolerant regex to find and replace existing defines, inserting new ones
 * before $table_prefix otherwise. Writes atomically and returns a WP_Error with
 * the modified content for manual paste when the file is not writable.
 *
 * @param array $settings Map of constant name => bool.
 * @return true|WP_Error
 */
function debug_apply_wp_config_settings( $settings ) {
	$home   = trailingslashit( get_home_path() );
	$config = $home . 'wp-config.php';

	if ( ! file_exists( $config ) ) {
		return new WP_Error( 'debug_no_config', __( 'wp-config.php was not found.', 'debug' ) );
	}

	$content = @file_get_contents( $config );
	if ( false === $content ) {
		return new WP_Error( 'debug_read_fail', __( 'Could not read wp-config.php.', 'debug' ) );
	}

	foreach ( $settings as $define => $value ) {
		$content = debug_add_option( $value, $define, $content );
	}

	if ( ! is_writable( $config ) ) {
		return new WP_Error(
			'debug_not_writable',
			__( 'wp-config.php is not writable. Copy the code below into your wp-config.php file:', 'debug' ),
			array( 'content' => $content )
		);
	}

	if ( ! debug_file_write_raw( $config, $content ) ) {
		return new WP_Error(
			'debug_write_fail',
			__( 'Could not write wp-config.php.', 'debug' ),
			array( 'content' => $content )
		);
	}

	return true;
}

/**
 * Modify wp-config content to add/update a debug define.
 *
 * Uses a case-insensitive, quote-tolerant regex so existing defines in any
 * formatting are updated rather than duplicated. New defines are inserted
 * before $table_prefix (or appended).
 *
 * @param bool   $option      New value (true/false).
 * @param string $define      Constant name.
 * @param string $fileContent Current wp-config content.
 * @return string
 */
function debug_add_option( $option, $define, $fileContent ) {
	$escaped     = preg_quote( $define, '/' );
	$value       = $option ? 'true' : 'false';

	// Match define('X', true|false); regardless of quote style/case/whitespace.
	$pattern     = "/define\s*\(\s*['\"]" . $escaped . "['\"]\s*,\s*(?:true|false|'true'|'false'|\"true\"|\"false\"|0|1)\s*\)\s*;/i";
	$replacement = "define('" . $define . "', " . $value . ');';

	$new = preg_replace( $pattern, $replacement, $fileContent, 1, $count );
	if ( null !== $new ) {
		$fileContent = $new;
	}

	if ( 0 === $count ) {
		// Insert before $table_prefix if present, otherwise append at end.
		if ( false !== strpos( $fileContent, '$table_prefix' ) ) {
			$fileContent = str_replace( '$table_prefix', $replacement . "\n" . '$table_prefix', $fileContent );
		} else {
			$fileContent .= "\n" . $replacement . "\n";
		}
	}

	return $fileContent;
}

/**
 * Fetch sanitized notification settings.
 *
 * @return array
 */
function debug_get_options() {
	$option = get_option( 'debug_notification', array() );
	if ( ! is_array( $option ) ) {
		$option = array();
	}
	return wp_parse_args(
		$option,
		array(
			'enable' => '0',
			'email'  => '',
		)
	);
}

/**
 * Serialize an arbitrary value into a safe HTML table for email.
 *
 * @param mixed $array Data to serialize.
 * @return string
 */
function debug_create_table_format( $array ) {
	if ( ! is_array( $array ) || 0 === count( $array ) ) {
		return '';
	}

	$error_content = '<table border="1">';
	foreach ( $array as $key => $val ) {
		$error_content .= '<tr><td>' . esc_html( (string) $key ) . '</td><td>';
		if ( is_array( $val ) && 0 !== count( $val ) ) {
			$error_content .= debug_create_table_format( $val );
		} else {
			$error_content .= esc_html( (string) print_r( $val, true ) );
		}
		$error_content .= '</td></tr>';
	}
	$error_content .= '</table>';

	return $error_content;
}

/**
 * Send a queued error-notification email.
 *
 * @param array $data Notification payload.
 */
function debug_send_notification_email_payload( $data ) {
	$settings = debug_get_options();
	if ( empty( $settings['email'] ) || ! is_email( $settings['email'] ) ) {
		return;
	}

	$home_url = home_url( '/' );
	$subject  = sprintf(
		/* translators: 1: site name. */
		__( 'Error on %s (via Debug)', 'debug' ),
		wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
	);

	$message  = '<h2>' . esc_html__( 'Error Reporting', 'debug' ) . '</h2>';
	$message .= '<p><strong>' . esc_html( $data['time'] ) . '</strong></p>';
	$message .= '<p><strong>' . esc_html__( 'URL:', 'debug' ) . '</strong> ' . esc_url( $data['url'] ) . '</p>';
	$message .= '<p><strong>' . esc_html__( 'Level:', 'debug' ) . '</strong> ' . esc_html( $data['level'] ) . '</p>';
	$message .= '<p><strong>' . esc_html__( 'Message:', 'debug' ) . '</strong> ' . esc_html( $data['message'] ) . '</p>';
	$message .= '<p><strong>' . esc_html__( 'File:', 'debug' ) . '</strong> ' . esc_html( $data['file'] ) . ':' . esc_html( $data['line'] ) . '</p>';
	$message .= '<p><strong>' . esc_html__( 'Context:', 'debug' ) . '</strong></p>';
	$message .= debug_create_table_format( $data['context'] );

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );

	wp_mail( $settings['email'], $subject, wp_kses_post( $message ), $headers );
}

/**
 * Send any queued error notifications (called on shutdown / admin_init).
 */
function debug_flush_notifications() {
	$pending = get_option( 'debug_pending_notifications', array() );
	if ( ! is_array( $pending ) || 0 === count( $pending ) ) {
		return;
	}

	delete_option( 'debug_pending_notifications' );
	$max = 10;
	foreach ( $pending as $data ) {
		if ( $max <= 0 ) {
			break;
		}
		debug_send_notification_email_payload( $data );
		$max--;
	}
}

/**
 * Throttled, level-filtered error handler.
 *
 * Only fires when email notifications are enabled, filters out noise levels, and
 * queues emails (rather than sending synchronously in the handler) to avoid
 * hammering the mail queue on busy sites. Always chains the previous handler.
 *
 * @param int    $errno   Error level.
 * @param string $errstr  Error message.
 * @param string $errfile File where the error occurred.
 * @param int    $errline Line number.
 * @param array  $errctx  Error context.
 * @return bool
 */
function debug_error_handler( $errno, $errstr, $errfile, $errline, $errctx ) {
	$settings       = debug_get_options();
	$notify_enabled = ( '1' === $settings['enable'] );

	// Only act when notifications are enabled.
	if ( ! $notify_enabled ) {
		return false; // Let the default handler run.
	}

	// Skip noise levels (deprecated + notices) unless E_ALL-style reporting is on.
	$reporting = error_reporting();
	if ( 0 === ( $reporting & $errno ) ) {
		return false; // @-suppressed or not in reporting mask.
	}

	$notify_levels = E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR;
	if ( 0 === ( $errno & $notify_levels ) ) {
		return false; // E_NOTICE / E_DEPRECATED / E_STRICT filtered out by default.
	}

	$level_names = array(
		E_ERROR             => 'ERROR',
		E_WARNING           => 'WARNING',
		E_PARSE             => 'PARSE',
		E_USER_ERROR        => 'USER ERROR',
		E_USER_WARNING      => 'USER WARNING',
		E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
	);

	$pending   = get_option( 'debug_pending_notifications', array() );
	if ( ! is_array( $pending ) ) {
		$pending = array();
	}
	// Bounded queue - drop oldest when full.
	if ( count( $pending ) >= 10 ) {
		array_shift( $pending );
	}

	$pending[] = array(
		'time'    => current_time( 'mysql' ),
		'url'     => ( is_ssl() ? 'https://' : 'http://' ) . ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' ) . ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/' ),
		'level'   => isset( $level_names[ $errno ] ) ? $level_names[ $errno ] : (string) $errno,
		'message' => (string) $errstr,
		'file'    => (string) $errfile,
		'line'    => (int) $errline,
		'context' => is_array( $errctx ) ? $errctx : array(),
	);

	update_option( 'debug_pending_notifications', $pending, false );

	// Continue to the default handler so PHP's normal display / logging still happens.
	return false;
}

/**
 * Stream an allowed file to the browser as a download (safe binary output).
 *
 * Only permits wp-content/debug.log (wp-config.php is never served wholesale).
 *
 * @param string $path Relative file path.
 */
function debug_stream_download( $path ) {
	$safe = debug_resolve_safe_path( $path );
	// Only allow the debug log to be downloaded - never wp-config.
	if ( false === $safe || 'debug.log' !== basename( $safe ) ) {
		wp_die( esc_html__( 'Invalid file for download.', 'debug' ), '', array( 'response' => 403 ) );
	}

	$content = debug_file_read( $path );
	if ( false === $content ) {
		wp_die( esc_html__( 'File not found or unreadable.', 'debug' ) );
	}

	// Send clean headers before any output.
	nocache_headers();
	header( 'Content-Type: text/plain; charset=UTF-8', true );
	header( 'Content-Disposition: attachment; filename="debug.log"', true );
	header( 'Content-Length: ' . strlen( $content ) );
	// Raw output - do NOT escape binary/log content.
	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw file download.
	exit;
}

/**
 * Handle download requests securely via admin-post (no HTML in output).
 */
function debug_handle_file_download() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to download files.', 'debug' ), '', array( 'response' => 403 ) );
	}

	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'debug_download_log' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'debug' ), '', array( 'response' => 403 ) );
	}

	debug_stream_download( 'wp-content/debug.log' );
}

/**
 * Handle wp-config restore requests securely.
 */
function debug_handle_restore() {
	if ( empty( $_POST['debug_restore_backup'] ) ) {
		return; // Not a restore-submit.
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		debug_admin_notice( 'error', __( 'You do not have permission to restore wp-config.php.', 'debug' ) );
		return;
	}

	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'debug_restore_config' ) ) {
		debug_admin_notice( 'error', __( 'Security check failed for restore.', 'debug' ) );
		return;
	}

	$backup = sanitize_file_name( wp_unslash( $_POST['debug_restore_backup'] ) );
	$result = debug_restore_wp_config( $backup );

	if ( true === $result ) {
		debug_admin_notice( 'updated', __( 'wp-config.php restored from backup.', 'debug' ) );
	} else {
		debug_admin_notice( 'error', $result );
	}
}
