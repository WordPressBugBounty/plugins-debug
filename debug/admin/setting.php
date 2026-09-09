<?php
/**
 * Admin settings UI for the Debug plugin.
 *
 * @package Debug
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php
// Process restore before any output so notices render above the list.
debug_handle_restore();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Debug Settings', 'debug' ); ?></h1>
	<?php debug_save_setting(); ?>
	<?php
	$debug_settings = debug_get_options();
	$current_user   = wp_get_current_user();
	$backups        = debug_list_wp_config_backups();
	?>
	<form method="post" action="">
		<?php wp_nonce_field( 'debug_save_settings' ); ?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Debug settings', 'debug' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">
								<span><?php esc_html_e( 'Error Reporting', 'debug' ); ?></span>
							</legend>

							<label for="enable_notification">
								<input name="enable_notification" type="checkbox" id="enable_notification" value="1" <?php checked( isset( $debug_settings['enable'] ) ? $debug_settings['enable'] : '0', '1' ); ?>>
								<?php esc_html_e( 'Enable Email Notification', 'debug' ); ?>
							</label>
							<br>

							<div class="debug-email-field">
								<label for="email_notification">
									<input type="email" name="email_notification" id="email_notification" class="regular-text"
										value="<?php echo esc_attr( ! empty( $debug_settings['email'] ) ? $debug_settings['email'] : $current_user->user_email ); ?>"
										placeholder="<?php esc_attr_e( 'Email Address', 'debug' ); ?>">
								</label>
								<br>
							</div>

							<div class="debug-no-email-field">
								<label for="error_reporting">
									<input name="error_reporting" type="checkbox" id="error_reporting" value="1" <?php checked( ( defined( 'WP_DEBUG' ) && WP_DEBUG ), true ); ?>>
									<?php esc_html_e( 'Enable Error Reporting', 'debug' ); ?>
								</label>
							</div>

							<div class="debug-no-email-field">
								<label for="error_log">
									<input name="error_log" type="checkbox" id="error_log" value="1" <?php checked( ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ), true ); ?>>
									<?php esc_html_e( 'Create Error Log in File', 'debug' ); ?> /wp-content/debug.log
								</label>
							</div>

							<div>
								<label for="display_error">
									<input name="display_error" type="checkbox" id="display_error" value="1" <?php checked( ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ), true ); ?>>
									<?php esc_html_e( 'Display Errors on the website', 'debug' ); ?>
								</label>
							</div>

							<div class="debug-no-email-field">
								<label for="error_script">
									<input name="error_script" type="checkbox" id="error_script" value="1" <?php checked( ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ), true ); ?>>
									<?php esc_html_e( 'Enable Script Debug', 'debug' ); ?>
								</label>
							</div>

							<div class="debug-no-email-field">
								<label for="error_savequery">
									<input name="error_savequery" type="checkbox" id="error_savequery" value="1" <?php checked( ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ), true ); ?>>
									<?php esc_html_e( 'Enable Save Queries', 'debug' ); ?>
								</label>
							</div>

							<p class="description">
								<?php esc_html_e( 'These settings update your wp-config.php file. A timestamped backup is created automatically before each save; you can restore it below.', 'debug' ); ?>
							</p>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<input type="hidden" name="debugsetting" value="1">
			<input type="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'debug' ); ?>">
		</p>
	</form>

	<?php if ( ! empty( $backups ) ) : ?>
		<hr>
		<h2><?php esc_html_e( 'wp-config.php Backups & Restore', 'debug' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'The most recent backups are listed newest-first. Restoring returns your wp-config.php to that saved state.', 'debug' ); ?>
		</p>
		<form method="post" action="">
			<?php wp_nonce_field( 'debug_restore_config' ); ?>
			<table class="widefat striped" style="max-width:640px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Backup file', 'debug' ); ?></th>
						<th><?php esc_html_e( 'Restore', 'debug' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$sorted = array_reverse( $backups );
					foreach ( $sorted as $backup ) :
						?>
						<tr>
							<td><code><?php echo esc_html( $backup ); ?></code></td>
							<td>
								<button type="submit" name="debug_restore_backup" value="<?php echo esc_attr( $backup ); ?>" class="button debug-restore-btn">
									<?php esc_html_e( 'Restore', 'debug' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</form>
	<?php endif; ?>

</div>
