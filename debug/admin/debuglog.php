<?php
/**
 * Debug log viewer UI.
 *
 * @package Debug
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle clear-log action (same-page POST with nonce).
if ( isset( $_POST['clearlog'] ) ) {
	$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
	if ( wp_verify_nonce( $nonce, 'debug_clear_log' ) && current_user_can( 'manage_options' ) ) {
		$response = debug_clearlog();
		debug_admin_notice( $response['class'], $response['message'] );
	} else {
		debug_admin_notice( 'error', __( 'Security check failed.', 'debug' ) );
	}
}

$content = debug_file_read( 'wp-content/debug.log' );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Debug Log', 'debug' ); ?></h1>

	<?php if ( false !== $content ) : ?>
		<form method="post" action="">
			<?php wp_nonce_field( 'debug_clear_log' ); ?>
			<p class="debug-log-actions">
				<input type="submit" name="clearlog" id="clearlog" class="button button-primary"
					value="<?php esc_attr_e( 'Clear Log', 'debug' ); ?>">
			</p>
		</form>

		<p class="debug-log-actions">
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=debug_download_log' ), 'debug_download_log' ) ); ?>">
				<?php esc_html_e( 'Download Log', 'debug' ); ?>
			</a>
		</p>

		<pre id="debug-log"><?php echo esc_html( $content ); ?></pre>
	<?php else : ?>
		<div class="notice settings-error">
			<p><strong><?php esc_html_e( 'No Log File Found', 'debug' ); ?></strong></p>
		</div>
	<?php endif; ?>
</div>
