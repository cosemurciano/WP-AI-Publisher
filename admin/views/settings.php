<?php
/**
 * Settings view.
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpai-admin">
	<h1><?php echo esc_html__( 'WP AI Publisher Settings', 'wp-ai-publisher' ); ?></h1>
	<p class="wpai-lead"><?php echo esc_html__( 'Configure safe defaults for the future AI publishing pipeline. API keys and GitHub tokens are intentionally not stored in phase 1.', 'wp-ai-publisher' ); ?></p>

	<form method="post" action="options.php" class="wpai-settings-form">
		<?php settings_fields( 'wpai_publisher_settings_group' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="wpai-ai-provider-preference"><?php echo esc_html__( 'AI provider preference', 'wp-ai-publisher' ); ?></label></th>
					<td>
						<select id="wpai-ai-provider-preference" name="wpai_publisher_settings[ai_provider_preference]">
							<option value="wordpress_ai_client_first" <?php selected( $settings['ai_provider_preference'], 'wordpress_ai_client_first' ); ?>><?php echo esc_html__( 'WordPress AI Client first', 'wp-ai-publisher' ); ?></option>
							<option value="openai_direct_only" <?php selected( $settings['ai_provider_preference'], 'openai_direct_only' ); ?>><?php echo esc_html__( 'OpenAI direct only', 'wp-ai-publisher' ); ?></option>
							<option value="disabled" <?php selected( $settings['ai_provider_preference'], 'disabled' ); ?>><?php echo esc_html__( 'Disabled', 'wp-ai-publisher' ); ?></option>
						</select>
						<p class="description"><?php echo esc_html__( 'All future AI calls must pass through the central adapter.', 'wp-ai-publisher' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php echo esc_html__( 'Fallback to OpenAI direct', 'wp-ai-publisher' ); ?></th>
					<td>
						<label><input type="checkbox" name="wpai_publisher_settings[fallback_to_openai_direct]" value="1" <?php checked( ! empty( $settings['fallback_to_openai_direct'] ) ); ?>> <?php echo esc_html__( 'Allow fallback in future phases when explicitly configured.', 'wp-ai-publisher' ); ?></label>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="wpai-default-text-model"><?php echo esc_html__( 'Default text model', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="text" id="wpai-default-text-model" class="regular-text" name="wpai_publisher_settings[default_text_model]" value="<?php echo esc_attr( $settings['default_text_model'] ); ?>"></td>
				</tr>

				<tr>
					<th scope="row"><label for="wpai-default-image-model"><?php echo esc_html__( 'Default image model', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="text" id="wpai-default-image-model" class="regular-text" name="wpai_publisher_settings[default_image_model]" value="<?php echo esc_attr( $settings['default_image_model'] ); ?>"></td>
				</tr>

				<tr>
					<th scope="row"><?php echo esc_html__( 'Enable logging', 'wp-ai-publisher' ); ?></th>
					<td><label><input type="checkbox" name="wpai_publisher_settings[enable_logging]" value="1" <?php checked( ! empty( $settings['enable_logging'] ) ); ?>> <?php echo esc_html__( 'Store plugin logs in the WP AI Publisher log table.', 'wp-ai-publisher' ); ?></label></td>
				</tr>

				<tr>
					<th scope="row"><label for="wpai-log-retention-days"><?php echo esc_html__( 'Log retention days', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="number" min="1" max="365" id="wpai-log-retention-days" name="wpai_publisher_settings[log_retention_days]" value="<?php echo esc_attr( (string) $settings['log_retention_days'] ); ?>"></td>
				</tr>

				<tr>
					<th scope="row"><label for="wpai-daily-cost-limit"><?php echo esc_html__( 'Daily cost limit', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="number" min="0" step="0.01" id="wpai-daily-cost-limit" name="wpai_publisher_settings[daily_cost_limit]" value="<?php echo esc_attr( $settings['daily_cost_limit'] ); ?>"></td>
				</tr>

				<tr>
					<th scope="row"><label for="wpai-monthly-cost-limit"><?php echo esc_html__( 'Monthly cost limit', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="number" min="0" step="0.01" id="wpai-monthly-cost-limit" name="wpai_publisher_settings[monthly_cost_limit]" value="<?php echo esc_attr( $settings['monthly_cost_limit'] ); ?>"></td>
				</tr>

				<tr>
					<th scope="row"><?php echo esc_html__( 'GitHub updater enabled', 'wp-ai-publisher' ); ?></th>
					<td>
						<label><input type="checkbox" value="1" disabled> <?php echo esc_html__( 'Disabled in phase 1.', 'wp-ai-publisher' ); ?></label>
						<p class="description"><?php echo esc_html__( 'The GitHub updater will be implemented in the dedicated GitHub updater phase. No token is stored now.', 'wp-ai-publisher' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Save settings', 'wp-ai-publisher' ) ); ?>
	</form>
</div>
