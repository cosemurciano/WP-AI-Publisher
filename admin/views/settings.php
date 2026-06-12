<?php
/**
 * Settings view.
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_context = wpai_publisher_get_site_context();
$tone_options = array(
	'chiaro_didattico_e_operativo' => __( 'chiaro, didattico e operativo', 'wp-ai-publisher' ),
	'professionale_tecnico'        => __( 'professionale e tecnico', 'wp-ai-publisher' ),
	'divulgativo_semplice'         => __( 'divulgativo e semplice', 'wp-ai-publisher' ),
	'commerciale_informativo'      => __( 'commerciale ma informativo', 'wp-ai-publisher' ),
	'editoriale_narrativo'         => __( 'editoriale e narrativo', 'wp-ai-publisher' ),
	'personalizzato'               => __( 'personalizzato', 'wp-ai-publisher' ),
);
$language_options = array(
	'it' => __( 'Italiano', 'wp-ai-publisher' ),
	'en' => __( 'Inglese', 'wp-ai-publisher' ),
	'fr' => __( 'Francese', 'wp-ai-publisher' ),
	'es' => __( 'Spagnolo', 'wp-ai-publisher' ),
	'de' => __( 'Tedesco', 'wp-ai-publisher' ),
);
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


		<h2><?php echo esc_html__( 'Contesto editoriale del sito', 'wp-ai-publisher' ); ?></h2>
		<p><?php echo esc_html__( 'Queste impostazioni rendono i dry-run e il fallback locale riutilizzabili su siti e nicchie diverse. Non salvano HTML, chiavi API e non attivano pubblicazione automatica.', 'wp-ai-publisher' ); ?></p>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="wpai-site-profile-name"><?php echo esc_html__( 'Nome profilo sito', 'wp-ai-publisher' ); ?></label></th>
					<td><input id="wpai-site-profile-name" name="wpai_publisher_settings[site_context][site_profile_name]" type="text" class="regular-text" value="<?php echo esc_attr( $site_context['site_profile_name'] ); ?>"><p class="description"><?php echo esc_html__( 'Esempio: WpTutorial AI, Sothra Travel, Linea Verde Giardino.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-site-description"><?php echo esc_html__( 'Descrizione sito', 'wp-ai-publisher' ); ?></label></th>
					<td><textarea id="wpai-site-description" name="wpai_publisher_settings[site_context][site_description]" rows="4" class="large-text"><?php echo esc_textarea( $site_context['site_description'] ); ?></textarea><p class="description"><?php echo esc_html__( 'Descrive cosa fa il sito e a chi si rivolge.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-content-niche"><?php echo esc_html__( 'Nicchia contenuti', 'wp-ai-publisher' ); ?></label></th>
					<td><input id="wpai-content-niche" name="wpai_publisher_settings[site_context][content_niche]" type="text" class="regular-text" value="<?php echo esc_attr( $site_context['content_niche'] ); ?>"><p class="description"><?php echo esc_html__( 'Esempi: Tutorial WordPress, Viaggi, Giardinaggio e agricoltura, Food e ristorazione, E-commerce.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-default-audience"><?php echo esc_html__( 'Pubblico target predefinito', 'wp-ai-publisher' ); ?></label></th>
					<td><input id="wpai-default-audience" name="wpai_publisher_settings[site_context][default_audience]" type="text" class="regular-text" value="<?php echo esc_attr( $site_context['default_audience'] ); ?>"><p class="description"><?php echo esc_html__( 'Esempio: utenti WordPress principianti, viaggiatori italiani, clienti interessati al giardinaggio.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-default-tone"><?php echo esc_html__( 'Tono di voce', 'wp-ai-publisher' ); ?></label></th>
					<td><select id="wpai-default-tone" name="wpai_publisher_settings[site_context][default_tone]"><?php foreach ( $tone_options as $tone_key => $tone_label ) : ?><option value="<?php echo esc_attr( $tone_key ); ?>" <?php selected( $site_context['default_tone'], $tone_key ); ?>><?php echo esc_html( $tone_label ); ?></option><?php endforeach; ?></select><p class="description"><?php echo esc_html__( 'Se scegli personalizzato, usa le regole editoriali aggiuntive.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-default-language"><?php echo esc_html__( 'Lingua predefinita', 'wp-ai-publisher' ); ?></label></th>
					<td><select id="wpai-default-language" name="wpai_publisher_settings[site_context][default_language]"><?php foreach ( $language_options as $language_key => $language_label ) : ?><option value="<?php echo esc_attr( $language_key ); ?>" <?php selected( $site_context['default_language'], $language_key ); ?>><?php echo esc_html( $language_label ); ?></option><?php endforeach; ?></select></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-default-editor"><?php echo esc_html__( 'Editor predefinito', 'wp-ai-publisher' ); ?></label></th>
					<td><select id="wpai-default-editor" name="wpai_publisher_settings[site_context][default_editor]"><option value="classic" selected><?php echo esc_html__( 'Editor Classico', 'wp-ai-publisher' ); ?></option><option value="gutenberg_future" disabled><?php echo esc_html__( 'Gutenberg, futuro/non attivo', 'wp-ai-publisher' ); ?></option></select><p class="description"><?php echo esc_html__( 'In questa fase il plugin produce solo HTML pulito compatibile con Editor Classico.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-future-post-status"><?php echo esc_html__( 'Stato post dopo generazione', 'wp-ai-publisher' ); ?></label></th>
					<td><select id="wpai-future-post-status" name="wpai_publisher_settings[site_context][default_post_status_after_generation]"><option value="draft" <?php selected( $site_context['default_post_status_after_generation'], 'draft' ); ?>><?php echo esc_html__( 'Bozza', 'wp-ai-publisher' ); ?></option><option value="pending" <?php selected( $site_context['default_post_status_after_generation'], 'pending' ); ?>><?php echo esc_html__( 'In attesa di revisione', 'wp-ai-publisher' ); ?></option></select><p class="description"><?php echo esc_html__( 'Non esiste un’opzione Pubblica in questa fase.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-allowed-categories"><?php echo esc_html__( 'Categorie consentite', 'wp-ai-publisher' ); ?></label></th>
					<td><textarea id="wpai-allowed-categories" name="wpai_publisher_settings[site_context][allowed_categories]" rows="3" class="large-text"><?php echo esc_textarea( $site_context['allowed_categories'] ); ?></textarea><p class="description"><?php echo esc_html__( 'Una categoria per riga oppure separate da virgola.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-preferred-tags"><?php echo esc_html__( 'Tag preferiti', 'wp-ai-publisher' ); ?></label></th>
					<td><textarea id="wpai-preferred-tags" name="wpai_publisher_settings[site_context][preferred_tags]" rows="3" class="large-text"><?php echo esc_textarea( $site_context['preferred_tags'] ); ?></textarea><p class="description"><?php echo esc_html__( 'Uno o più tag separati da virgola.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-excluded-topics"><?php echo esc_html__( 'Argomenti esclusi', 'wp-ai-publisher' ); ?></label></th>
					<td><textarea id="wpai-excluded-topics" name="wpai_publisher_settings[site_context][excluded_topics]" rows="3" class="large-text"><?php echo esc_textarea( $site_context['excluded_topics'] ); ?></textarea><p class="description"><?php echo esc_html__( 'Argomenti che il plugin non dovrebbe proporre.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-internal-link-strategy"><?php echo esc_html__( 'Strategia link interni', 'wp-ai-publisher' ); ?></label></th>
					<td><select id="wpai-internal-link-strategy" name="wpai_publisher_settings[site_context][internal_link_strategy]"><option value="semantic_targets" <?php selected( $site_context['internal_link_strategy'], 'semantic_targets' ); ?>><?php echo esc_html__( 'Target semantici, non URL', 'wp-ai-publisher' ); ?></option><option value="future_existing_content" <?php selected( $site_context['internal_link_strategy'], 'future_existing_content' ); ?>><?php echo esc_html__( 'Cerca contenuti esistenti, fase futura', 'wp-ai-publisher' ); ?></option><option value="disabled" <?php selected( $site_context['internal_link_strategy'], 'disabled' ); ?>><?php echo esc_html__( 'Disabilita suggerimenti link', 'wp-ai-publisher' ); ?></option></select></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-seo-plugin-preference"><?php echo esc_html__( 'Plugin SEO preferito', 'wp-ai-publisher' ); ?></label></th>
					<td><select id="wpai-seo-plugin-preference" name="wpai_publisher_settings[site_context][seo_plugin_preference]"><option value="aioseo" <?php selected( $site_context['seo_plugin_preference'], 'aioseo' ); ?>><?php echo esc_html__( 'AIOSEO', 'wp-ai-publisher' ); ?></option><option value="none" <?php selected( $site_context['seo_plugin_preference'], 'none' ); ?>><?php echo esc_html__( 'Nessuno', 'wp-ai-publisher' ); ?></option><option value="other_future" <?php selected( $site_context['seo_plugin_preference'], 'other_future' ); ?>><?php echo esc_html__( 'Altro, futuro', 'wp-ai-publisher' ); ?></option></select><p class="description"><?php echo esc_html__( 'Preferenza informativa per dry-run e fasi future: in questa versione non vengono scritti metadati SEO.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-writing-rules"><?php echo esc_html__( 'Regole editoriali aggiuntive', 'wp-ai-publisher' ); ?></label></th>
					<td><textarea id="wpai-writing-rules" name="wpai_publisher_settings[site_context][writing_rules]" rows="4" class="large-text"><?php echo esc_textarea( $site_context['writing_rules'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-forbidden-claims"><?php echo esc_html__( 'Claim vietati o da evitare', 'wp-ai-publisher' ); ?></label></th>
					<td><textarea id="wpai-forbidden-claims" name="wpai_publisher_settings[site_context][forbidden_claims]" rows="3" class="large-text"><?php echo esc_textarea( $site_context['forbidden_claims'] ); ?></textarea><p class="description"><?php echo esc_html__( 'Esempio: Non promettere risultati garantiti, non inventare prezzi, non inventare dati tecnici.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-brand-terms"><?php echo esc_html__( 'Termini brand', 'wp-ai-publisher' ); ?></label></th>
					<td><textarea id="wpai-brand-terms" name="wpai_publisher_settings[site_context][brand_terms]" rows="3" class="large-text"><?php echo esc_textarea( $site_context['brand_terms'] ); ?></textarea><p class="description"><?php echo esc_html__( 'Nomi prodotti, nomi sito, parole da rispettare.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-content-format-preference"><?php echo esc_html__( 'Formato contenuto preferito', 'wp-ai-publisher' ); ?></label></th>
					<td><select id="wpai-content-format-preference" name="wpai_publisher_settings[site_context][content_format_preference]"><option value="tutorial_html_classic" <?php selected( $site_context['content_format_preference'], 'tutorial_html_classic' ); ?>><?php echo esc_html__( 'Tutorial HTML per Editor Classico', 'wp-ai-publisher' ); ?></option><option value="informational_article" <?php selected( $site_context['content_format_preference'], 'informational_article' ); ?>><?php echo esc_html__( 'Articolo informativo', 'wp-ai-publisher' ); ?></option><option value="product_sheet" <?php selected( $site_context['content_format_preference'], 'product_sheet' ); ?>><?php echo esc_html__( 'Scheda prodotto', 'wp-ai-publisher' ); ?></option><option value="local_guide" <?php selected( $site_context['content_format_preference'], 'local_guide' ); ?>><?php echo esc_html__( 'Guida locale', 'wp-ai-publisher' ); ?></option><option value="affiliate_content" <?php selected( $site_context['content_format_preference'], 'affiliate_content' ); ?>><?php echo esc_html__( 'Contenuto affiliato', 'wp-ai-publisher' ); ?></option><option value="other_future" <?php selected( $site_context['content_format_preference'], 'other_future' ); ?>><?php echo esc_html__( 'Altro, futuro', 'wp-ai-publisher' ); ?></option></select></td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Salva impostazioni', 'wp-ai-publisher' ) ); ?>
	</form>
</div>
