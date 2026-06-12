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
	<h1><?php echo esc_html__( 'Impostazioni WP AI Publisher', 'wp-ai-publisher' ); ?></h1>
	<p class="wpai-lead"><?php echo esc_html__( 'Configura le impostazioni operative del plugin. Le chiamate AI useranno esclusivamente il sistema AI di WordPress già configurato sul sito; non vengono gestite chiavi OpenAI personalizzate.', 'wp-ai-publisher' ); ?></p>

	<div class="notice notice-info inline">
		<p>
			<strong><?php echo esc_html__( 'Sistema AI:', 'wp-ai-publisher' ); ?></strong>
			<?php if ( ! empty( $ai_status['wordpress_ai_client_available'] ) ) : ?>
				<?php echo esc_html__( 'rilevato. Il plugin userà solo il layer AI di WordPress.', 'wp-ai-publisher' ); ?>
			<?php else : ?>
				<?php echo esc_html__( 'non rilevato dal plugin. Verifica che il nuovo sistema AI di WordPress sia attivo o esponga i modelli tramite il filtro wpai_publisher_available_ai_models.', 'wp-ai-publisher' ); ?>
			<?php endif; ?>
		</p>
	</div>

	<form method="post" action="options.php" class="wpai-settings-form">
		<?php settings_fields( 'wpai_publisher_settings_group' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Provider AI', 'wp-ai-publisher' ); ?></th>
					<td>
						<p><strong><?php echo esc_html__( 'Sistema AI di WordPress', 'wp-ai-publisher' ); ?></strong></p>
						<input type="hidden" name="wpai_publisher_settings[ai_provider_preference]" value="wordpress_ai_client_only">
						<p class="description"><?php echo esc_html__( 'WP AI Publisher non usa un client OpenAI custom e non salva chiavi API proprie. Tutte le chiamate passeranno dall’adapter interno collegato al sistema AI di WordPress.', 'wp-ai-publisher' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="wpai-default-text-model"><?php echo esc_html__( 'Versione / modello AI disponibile', 'wp-ai-publisher' ); ?></label></th>
					<td>
						<select id="wpai-default-text-model" name="wpai_publisher_settings[default_text_model]" class="regular-text" <?php disabled( empty( $ai_models ) ); ?>>
							<option value=""><?php echo esc_html__( 'Usa il modello predefinito del sistema AI di WordPress', 'wp-ai-publisher' ); ?></option>
							<?php foreach ( $ai_models as $model ) : ?>
								<option value="<?php echo esc_attr( $model['id'] ); ?>" <?php selected( $settings['default_text_model'], $model['id'] ); ?>><?php echo esc_html( $model['label'] . ' — ' . $model['id'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ( empty( $ai_models ) ) : ?>
							<p class="description"><?php echo esc_html__( 'Nessun modello è stato esposto dal sistema AI di WordPress. Il plugin può comunque usare il modello predefinito quando l’integrazione WordPress AI lo renderà disponibile.', 'wp-ai-publisher' ); ?></p>
						<?php else : ?>
							<p class="description"><?php echo esc_html__( 'I modelli elencati provengono dal sistema AI di WordPress o dal filtro di integrazione del sito.', 'wp-ai-publisher' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php echo esc_html__( 'Log attivi', 'wp-ai-publisher' ); ?></th>
					<td><label><input type="checkbox" name="wpai_publisher_settings[enable_logging]" value="1" <?php checked( ! empty( $settings['enable_logging'] ) ); ?>> <?php echo esc_html__( 'Salva i log tecnici nella tabella del plugin.', 'wp-ai-publisher' ); ?></label></td>
				</tr>

				<tr>
					<th scope="row"><label for="wpai-log-retention-days"><?php echo esc_html__( 'Conservazione log in giorni', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="number" min="1" max="365" id="wpai-log-retention-days" name="wpai_publisher_settings[log_retention_days]" value="<?php echo esc_attr( (string) $settings['log_retention_days'] ); ?>"></td>
				</tr>

				<tr>
					<th scope="row"><label for="wpai-daily-cost-limit"><?php echo esc_html__( 'Limite costo giornaliero', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="number" min="0" step="0.01" id="wpai-daily-cost-limit" name="wpai_publisher_settings[daily_cost_limit]" value="<?php echo esc_attr( $settings['daily_cost_limit'] ); ?>"> <span class="description"><?php echo esc_html__( 'Opzionale, sarà usato nelle prossime fasi.', 'wp-ai-publisher' ); ?></span></td>
				</tr>

				<tr>
					<th scope="row"><label for="wpai-monthly-cost-limit"><?php echo esc_html__( 'Limite costo mensile', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="number" min="0" step="0.01" id="wpai-monthly-cost-limit" name="wpai_publisher_settings[monthly_cost_limit]" value="<?php echo esc_attr( $settings['monthly_cost_limit'] ); ?>"> <span class="description"><?php echo esc_html__( 'Opzionale, sarà usato nelle prossime fasi.', 'wp-ai-publisher' ); ?></span></td>
				</tr>

				<tr>
					<th scope="row"><?php echo esc_html__( 'Aggiornamenti da GitHub', 'wp-ai-publisher' ); ?></th>
					<td>
						<label><input type="checkbox" value="1" disabled> <?php echo esc_html__( 'Non ancora attivo in questa fase.', 'wp-ai-publisher' ); ?></label>
						<p class="description"><?php echo esc_html__( 'L’aggiornamento one-click da GitHub sarà implementato nella fase dedicata. Nessun token GitHub viene salvato ora.', 'wp-ai-publisher' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Salva impostazioni', 'wp-ai-publisher' ) ); ?>
	</form>
</div>
