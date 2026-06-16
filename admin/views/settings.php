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
$global_categories = get_categories( array( 'hide_empty' => false ) );
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
					<th colspan="2"><h2><?php echo esc_html__( 'Workflow editoriale', 'wp-ai-publisher' ); ?></h2></th>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Crea automaticamente la bozza quando salvo un’idea', 'wp-ai-publisher' ); ?></th>
					<td><label><input type="checkbox" name="wpai_publisher_settings[auto_create_draft_from_idea]" value="1" <?php checked( ! empty( $settings['auto_create_draft_from_idea'] ) ); ?>> <?php echo esc_html__( 'Quando attivo, salvando un’idea il plugin genera subito l’articolo e crea la bozza WordPress.', 'wp-ai-publisher' ); ?></label></td>
				</tr>

				<tr>
					<th colspan="2"><h2><?php echo esc_html__( 'Avanzate: Sicurezza Abilities AI', 'wp-ai-publisher' ); ?></h2><p class="description"><?php echo esc_html__( 'Sezione tecnica per sviluppatori e amministratori: non fa parte del flusso editoriale principale. Per impostazione predefinita WP AI Publisher non esegue abilities arbitrarie.', 'wp-ai-publisher' ); ?></p></th>
				</tr>

				<tr>
					<th scope="row"><label for="wpai-safe-ai-ability-names"><?php echo esc_html__( 'Abilities AI sicure', 'wp-ai-publisher' ); ?></label></th>
					<td><textarea id="wpai-safe-ai-ability-names" name="wpai_publisher_settings[safe_ai_ability_names]" rows="5" class="large-text code"><?php echo esc_textarea( $settings['safe_ai_ability_names'] ?? '' ); ?></textarea><p class="description"><?php echo esc_html__( 'Nomi delle abilities AI considerate sicure per il dry-run, una per riga. Lascia vuoto per usare solo il filtro o i controlli automatici conservativi.', 'wp-ai-publisher' ); ?></p></td>
				</tr>

				<tr>
					<th scope="row"><?php echo esc_html__( 'Consenti abilities non verificate', 'wp-ai-publisher' ); ?></th>
					<td><label><input type="checkbox" name="wpai_publisher_settings[allow_unverified_ai_abilities]" value="1" <?php checked( ! empty( $settings['allow_unverified_ai_abilities'] ) ); ?>> <?php echo esc_html__( 'Consenti l’uso di abilities AI non verificate. Sconsigliato: può eseguire callback non pensate per il dry-run.', 'wp-ai-publisher' ); ?></label></td>
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
					<th scope="row"><?php echo esc_html__( 'Elimina i dati alla disinstallazione', 'wp-ai-publisher' ); ?></th>
					<td><label><input type="checkbox" name="wpai_publisher_settings[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?>> <?php echo esc_html__( 'Quando attivo, la disinstallazione del plugin elimina tabelle, impostazioni e capability dedicata. Lascia disattivo per conservare lo storico operativo.', 'wp-ai-publisher' ); ?></label></td>
				</tr>

			</tbody>
		</table>


		<h2><?php echo esc_html__( 'Parametri AI', 'wp-ai-publisher' ); ?></h2>
		<p><?php echo esc_html__( 'Parametri usati per la generazione tramite il sistema AI di WordPress (es. AI Provider for OpenAI). Lascia vuoto un campo per usare il valore predefinito del provider.', 'wp-ai-publisher' ); ?></p>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="wpai-ai-model"><?php echo esc_html__( 'Modello AI', 'wp-ai-publisher' ); ?></label></th>
					<td>
						<input type="text" id="wpai-ai-model" name="wpai_publisher_settings[ai_model]" class="regular-text" list="wpai-ai-model-options" value="<?php echo esc_attr( (string) ( $settings['ai_model'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr__( 'es. gpt-4o-mini (vuoto = modello predefinito del provider)', 'wp-ai-publisher' ); ?>">
						<?php if ( ! empty( $ai_models ) ) : ?>
							<datalist id="wpai-ai-model-options">
								<?php foreach ( $ai_models as $model ) : ?>
									<option value="<?php echo esc_attr( $model['id'] ); ?>"><?php echo esc_html( $model['label'] ?? $model['id'] ); ?></option>
								<?php endforeach; ?>
							</datalist>
						<?php endif; ?>
						<p class="description"><?php echo esc_html__( 'ID del modello da richiedere. Consigliato un modello veloce (es. gpt-4o-mini) per restare entro il timeout. Se vuoto, viene usato il modello configurato nel provider AI.', 'wp-ai-publisher' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-ai-http-timeout"><?php echo esc_html__( 'Timeout richiesta AI (secondi)', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="number" min="15" max="600" id="wpai-ai-http-timeout" name="wpai_publisher_settings[ai_http_timeout]" value="<?php echo esc_attr( (string) ( $settings['ai_http_timeout'] ?? 180 ) ); ?>"><p class="description"><?php echo esc_html__( 'Tempo massimo di attesa della risposta AI. Aumentalo se la generazione è lenta (max 600).', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-ai-max-tokens"><?php echo esc_html__( 'Lunghezza massima output (token)', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="number" min="0" max="32000" id="wpai-ai-max-tokens" name="wpai_publisher_settings[ai_max_output_tokens]" value="<?php echo esc_attr( (string) ( $settings['ai_max_output_tokens'] ?? 4000 ) ); ?>"><p class="description"><?php echo esc_html__( 'Limite di token in uscita per contenere i tempi di generazione. 0 = nessun limite (usa il default del modello).', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-ai-temperature"><?php echo esc_html__( 'Temperatura', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="text" id="wpai-ai-temperature" name="wpai_publisher_settings[ai_temperature]" class="small-text" value="<?php echo esc_attr( (string) ( $settings['ai_temperature'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr__( 'vuoto', 'wp-ai-publisher' ); ?>"><p class="description"><?php echo esc_html__( 'Valore 0–2. Lascia VUOTO se usi un modello “reasoning” (o1/o3/gpt-5): questi modelli rifiutano il parametro temperature.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Immagine in evidenza AI', 'wp-ai-publisher' ); ?></th>
					<td><label><input type="checkbox" name="wpai_publisher_settings[generate_featured_image]" value="1" <?php checked( ! empty( $settings['generate_featured_image'] ) ); ?>> <?php echo esc_html__( 'Genera automaticamente un’immagine in evidenza quando creo la bozza.', 'wp-ai-publisher' ); ?></label><p class="description"><?php echo esc_html__( 'Usa il “Prompt immagini” della Tipologia articolo (se vuoto, ne crea uno dal titolo). Richiede un provider AI con generazione immagini. Se fallisce, la bozza viene comunque creata senza immagine.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Immagini nel corpo AI', 'wp-ai-publisher' ); ?></th>
					<td><label><input type="checkbox" name="wpai_publisher_settings[generate_inline_images]" value="1" <?php checked( ! empty( $settings['generate_inline_images'] ) ); ?>> <?php echo esc_html__( 'Genera e inserisci automaticamente immagini reali nel corpo dell’articolo, nei punti scelti dall’AI.', 'wp-ai-publisher' ); ?></label><p class="description"><?php echo esc_html__( 'L’AI indica i punti adatti; il plugin genera l’immagine, la carica nella Libreria media e sostituisce il segnaposto con un’immagine reale (nessun placeholder). Usa lo stile del “Prompt immagini” della Tipologia articolo. Richiede un provider AI con generazione immagini.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-max-inline-images"><?php echo esc_html__( 'Numero massimo immagini nel corpo', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="number" id="wpai-max-inline-images" name="wpai_publisher_settings[max_inline_images]" class="small-text" min="0" max="10" step="1" value="<?php echo esc_attr( (string) ( $settings['max_inline_images'] ?? 3 ) ); ?>"><p class="description"><?php echo esc_html__( 'Limite di immagini generate per articolo (0–10). Oltre questo numero i segnaposto in eccesso vengono rimossi.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
			</tbody>
		</table>

		<h2><?php echo esc_html__( 'Profilo sito', 'wp-ai-publisher' ); ?></h2>
		<p><?php echo esc_html__( 'Contesto generale del sito. Le Tipologie Articolo restano la fonte principale per tono, struttura, tag e istruzioni specifiche.', 'wp-ai-publisher' ); ?></p>

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
					<td><textarea id="wpai-content-niche" name="wpai_publisher_settings[site_context][content_niche]" rows="2" class="large-text"><?php echo esc_textarea( $site_context['content_niche'] ); ?></textarea><p class="description"><?php echo esc_html__( 'Ambito editoriale ampio del sito, non istruzioni per un singolo articolo.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-default-audience"><?php echo esc_html__( 'Pubblico target predefinito', 'wp-ai-publisher' ); ?></label></th>
					<td><textarea id="wpai-default-audience" name="wpai_publisher_settings[site_context][default_audience]" rows="2" class="large-text"><?php echo esc_textarea( $site_context['default_audience'] ); ?></textarea><p class="description"><?php echo esc_html__( 'Profilo pubblico generale. Il livello lettore della Tipologia Articolo ha priorità.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-default-language"><?php echo esc_html__( 'Lingua predefinita', 'wp-ai-publisher' ); ?></label></th>
					<td><select id="wpai-default-language" name="wpai_publisher_settings[site_context][default_language]"><?php foreach ( $language_options as $language_key => $language_label ) : ?><option value="<?php echo esc_attr( $language_key ); ?>" <?php selected( $site_context['default_language'], $language_key ); ?>><?php echo esc_html( $language_label ); ?></option><?php endforeach; ?></select></td>
				</tr>
			</tbody>
		</table>

		<h2><?php echo esc_html__( 'Workflow contenuti', 'wp-ai-publisher' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="wpai-default-editor"><?php echo esc_html__( 'Editor predefinito', 'wp-ai-publisher' ); ?></label></th>
					<td><select id="wpai-default-editor" name="wpai_publisher_settings[site_context][default_editor]"><option value="classic" selected><?php echo esc_html__( 'Editor Classico', 'wp-ai-publisher' ); ?></option><option value="gutenberg_future" disabled><?php echo esc_html__( 'Gutenberg, futuro/non attivo', 'wp-ai-publisher' ); ?></option></select><p class="description"><?php echo esc_html__( 'In questa fase il plugin produce solo HTML pulito compatibile con Editor Classico.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-future-post-status"><?php echo esc_html__( 'Stato post dopo generazione', 'wp-ai-publisher' ); ?></label></th>
					<td><select id="wpai-future-post-status" name="wpai_publisher_settings[site_context][default_post_status_after_generation]"><option value="draft" <?php selected( $site_context['default_post_status_after_generation'], 'draft' ); ?>><?php echo esc_html__( 'Bozza', 'wp-ai-publisher' ); ?></option><option value="pending" <?php selected( $site_context['default_post_status_after_generation'], 'pending' ); ?>><?php echo esc_html__( 'In attesa di revisione', 'wp-ai-publisher' ); ?></option><option value="publish" <?php selected( $site_context['default_post_status_after_generation'], 'publish' ); ?>><?php echo esc_html__( 'Pubblicato', 'wp-ai-publisher' ); ?></option></select><p class="description"><?php echo esc_html__( 'In 0.5.0 Pubblicato resta un’intenzione futura: la creazione bozza convertirà publish in draft salvo costante di sviluppo esplicita.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
			</tbody>
		</table>

		<h2><?php echo esc_html__( 'Vincoli globali', 'wp-ai-publisher' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Categorie consentite globali', 'wp-ai-publisher' ); ?></th>
					<td>
						<input type="hidden" name="wpai_publisher_settings[site_context][__allowed_category_ids_present]" value="1">
						<?php if ( empty( $global_categories ) ) : ?>
							<p class="description"><?php echo esc_html__( 'Nessuna categoria WordPress disponibile.', 'wp-ai-publisher' ); ?></p>
						<?php else : ?>
							<?php foreach ( $global_categories as $cat ) : ?>
								<label style="display:block"><input type="checkbox" name="wpai_publisher_settings[site_context][allowed_category_ids][]" value="<?php echo esc_attr( (string) $cat->term_id ); ?>" <?php checked( in_array( (int) $cat->term_id, (array) $site_context['allowed_category_ids'], true ) ); ?>> <?php echo esc_html( $cat->name ); ?></label>
							<?php endforeach; ?>
						<?php endif; ?>
						<p class="description"><?php echo esc_html__( 'Limite globale opzionale. Se selezioni una o più categorie, il plugin potrà usare solo queste categorie. Le Tipologie Articolo potranno restringere ulteriormente la selezione, ma non ampliarla.', 'wp-ai-publisher' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-internal-link-strategy"><?php echo esc_html__( 'Strategia link interni', 'wp-ai-publisher' ); ?></label></th>
					<td><select id="wpai-internal-link-strategy" name="wpai_publisher_settings[site_context][internal_link_strategy]"><option value="semantic_targets" <?php selected( $site_context['internal_link_strategy'], 'semantic_targets' ); ?>><?php echo esc_html__( 'Target semantici, non URL', 'wp-ai-publisher' ); ?></option><option value="future_existing_content" <?php selected( $site_context['internal_link_strategy'], 'future_existing_content' ); ?>><?php echo esc_html__( 'Cerca contenuti esistenti, fase futura', 'wp-ai-publisher' ); ?></option><option value="disabled" <?php selected( $site_context['internal_link_strategy'], 'disabled' ); ?>><?php echo esc_html__( 'Disabilita suggerimenti link', 'wp-ai-publisher' ); ?></option></select></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-seo-plugin-preference"><?php echo esc_html__( 'Plugin SEO preferito', 'wp-ai-publisher' ); ?></label></th>
					<td><select id="wpai-seo-plugin-preference" name="wpai_publisher_settings[site_context][seo_plugin_preference]"><option value="aioseo" <?php selected( $site_context['seo_plugin_preference'], 'aioseo' ); ?>><?php echo esc_html__( 'AIOSEO', 'wp-ai-publisher' ); ?></option><option value="none" <?php selected( $site_context['seo_plugin_preference'], 'none' ); ?>><?php echo esc_html__( 'Nessuno', 'wp-ai-publisher' ); ?></option><option value="other_future" <?php selected( $site_context['seo_plugin_preference'], 'other_future' ); ?>><?php echo esc_html__( 'Altro, futuro', 'wp-ai-publisher' ); ?></option></select><p class="description"><?php echo esc_html__( 'Preferenza informativa per dry-run e fasi future: in questa versione non vengono scritti metadati SEO.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
			</tbody>
		</table>

		<h2><?php echo esc_html__( 'Regole globali AI', 'wp-ai-publisher' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="wpai-writing-rules"><?php echo esc_html__( 'Regole editoriali globali', 'wp-ai-publisher' ); ?></label></th>
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
