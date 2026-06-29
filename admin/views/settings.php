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

// When a connection test redirects back with a notice, open the matching tab.
$wpai_active_tab = '';
if ( isset( $_GET['wpai_notice'] ) ) {
	$wpai_notice_key     = sanitize_key( wp_unslash( $_GET['wpai_notice'] ) );
	$wpai_notice_tab_map = array(
		'telegram_webhook'   => 'telegram',
		'openai_file_search' => 'ai',
		'facebook_test'      => 'social',
		'instagram_test'     => 'social',
		'linkedin_test'      => 'social',
	);
	$wpai_active_tab = isset( $wpai_notice_tab_map[ $wpai_notice_key ] ) ? $wpai_notice_tab_map[ $wpai_notice_key ] : '';
}
?>
<div class="wrap wpai-admin">
	<h1><?php echo esc_html__( 'Impostazioni WP AI Publisher', 'wp-ai-publisher' ); ?></h1>

	<?php
	if ( isset( $_GET['wpai_notice'] ) && 'telegram_webhook' === sanitize_key( wp_unslash( $_GET['wpai_notice'] ) ) ) {
		$wpai_tg_notice = get_transient( 'wpai_publisher_telegram_notice_' . get_current_user_id() );
		if ( is_array( $wpai_tg_notice ) ) {
			delete_transient( 'wpai_publisher_telegram_notice_' . get_current_user_id() );
			printf(
				'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
				! empty( $wpai_tg_notice['ok'] ) ? 'notice-success' : 'notice-error',
				esc_html( (string) ( $wpai_tg_notice['message'] ?? '' ) )
			);
		}
	}
	if ( isset( $_GET['wpai_notice'] ) && 'openai_file_search' === sanitize_key( wp_unslash( $_GET['wpai_notice'] ) ) ) {
		$wpai_fs_notice = get_transient( 'wpai_publisher_openai_fs_notice_' . get_current_user_id() );
		if ( is_array( $wpai_fs_notice ) ) {
			delete_transient( 'wpai_publisher_openai_fs_notice_' . get_current_user_id() );
			printf(
				'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
				! empty( $wpai_fs_notice['ok'] ) ? 'notice-success' : 'notice-error',
				esc_html( (string) ( $wpai_fs_notice['message'] ?? '' ) )
			);
		}
	}
	if ( isset( $_GET['wpai_notice'] ) && 'facebook_test' === sanitize_key( wp_unslash( $_GET['wpai_notice'] ) ) ) {
		$wpai_fb_notice = get_transient( 'wpai_publisher_facebook_notice_' . get_current_user_id() );
		if ( is_array( $wpai_fb_notice ) ) {
			delete_transient( 'wpai_publisher_facebook_notice_' . get_current_user_id() );
			printf(
				'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
				! empty( $wpai_fb_notice['ok'] ) ? 'notice-success' : 'notice-error',
				esc_html( (string) ( $wpai_fb_notice['message'] ?? '' ) )
			);
		}
	}
	if ( isset( $_GET['wpai_notice'] ) && 'instagram_test' === sanitize_key( wp_unslash( $_GET['wpai_notice'] ) ) ) {
		$wpai_ig_notice = get_transient( 'wpai_publisher_instagram_notice_' . get_current_user_id() );
		if ( is_array( $wpai_ig_notice ) ) {
			delete_transient( 'wpai_publisher_instagram_notice_' . get_current_user_id() );
			printf(
				'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
				! empty( $wpai_ig_notice['ok'] ) ? 'notice-success' : 'notice-error',
				esc_html( (string) ( $wpai_ig_notice['message'] ?? '' ) )
			);
		}
	}
	if ( isset( $_GET['wpai_notice'] ) && 'linkedin_test' === sanitize_key( wp_unslash( $_GET['wpai_notice'] ) ) ) {
		$wpai_in_notice = get_transient( 'wpai_publisher_linkedin_notice_' . get_current_user_id() );
		if ( is_array( $wpai_in_notice ) ) {
			delete_transient( 'wpai_publisher_linkedin_notice_' . get_current_user_id() );
			printf(
				'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
				! empty( $wpai_in_notice['ok'] ) ? 'notice-success' : 'notice-error',
				esc_html( (string) ( $wpai_in_notice['message'] ?? '' ) )
			);
		}
	}
	?>
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

		<div class="wpai-tabs" data-wpai-store="settings" data-wpai-initial="<?php echo esc_attr( $wpai_active_tab ); ?>">
			<h2 class="nav-tab-wrapper wpai-nav">
				<a href="#wpai-tab-generale" class="nav-tab nav-tab-active" data-wpai-tab="generale"><?php echo esc_html__( 'Generale', 'wp-ai-publisher' ); ?></a>
				<a href="#wpai-tab-ai" class="nav-tab" data-wpai-tab="ai"><?php echo esc_html__( 'Generazione AI', 'wp-ai-publisher' ); ?></a>
				<a href="#wpai-tab-social" class="nav-tab" data-wpai-tab="social"><?php echo esc_html__( 'Social', 'wp-ai-publisher' ); ?></a>
				<a href="#wpai-tab-telegram" class="nav-tab" data-wpai-tab="telegram"><?php echo esc_html__( 'Telegram', 'wp-ai-publisher' ); ?></a>
				<a href="#wpai-tab-avanzate" class="nav-tab" data-wpai-tab="avanzate"><?php echo esc_html__( 'Avanzate', 'wp-ai-publisher' ); ?></a>
			</h2>

			<?php /* ============================ TAB: GENERALE ============================ */ ?>
			<div class="wpai-tab-panel is-active" id="wpai-tab-generale" data-wpai-panel="generale">

				<div class="wpai-section">
					<h2><?php echo esc_html__( 'Profilo sito', 'wp-ai-publisher' ); ?></h2>
					<p class="wpai-tab-intro"><?php echo esc_html__( 'Contesto generale del sito. Le Tipologie Articolo restano la fonte principale per tono, struttura, tag e istruzioni specifiche.', 'wp-ai-publisher' ); ?></p>
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
				</div>

				<div class="wpai-section">
					<h2><?php echo esc_html__( 'Workflow editoriale', 'wp-ai-publisher' ); ?></h2>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Crea automaticamente la bozza quando salvo un’idea', 'wp-ai-publisher' ); ?></th>
								<td><label><input type="checkbox" name="wpai_publisher_settings[auto_create_draft_from_idea]" value="1" <?php checked( ! empty( $settings['auto_create_draft_from_idea'] ) ); ?>> <?php echo esc_html__( 'Quando attivo, salvando un’idea il plugin genera subito l’articolo e crea la bozza WordPress.', 'wp-ai-publisher' ); ?></label></td>
							</tr>
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
				</div>

				<div class="wpai-section">
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
				</div>

				<div class="wpai-section">
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
				</div>

			</div>

			<?php /* ============================ TAB: GENERAZIONE AI ============================ */ ?>
			<div class="wpai-tab-panel" id="wpai-tab-ai" data-wpai-panel="ai">

				<div class="wpai-section">
					<h2><?php echo esc_html__( 'Parametri AI', 'wp-ai-publisher' ); ?></h2>
					<p class="wpai-tab-intro"><?php echo esc_html__( 'Parametri usati per la generazione tramite il sistema AI di WordPress (es. AI Provider for OpenAI). Lascia vuoto un campo per usare il valore predefinito del provider.', 'wp-ai-publisher' ); ?></p>
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
				</div>

				<div class="wpai-section">
					<h2><?php echo esc_html__( 'Knowledge base OpenAI (file_search)', 'wp-ai-publisher' ); ?></h2>
					<p class="wpai-tab-intro"><?php echo esc_html__( 'Permette all’AI di ancorare gli articoli ai documenti caricati nel tuo storage OpenAI (Vector store) tramite la Responses API. Quando attivo e configurato, questo canale viene tentato per primo, con fallback automatico al canale AI di WordPress.', 'wp-ai-publisher' ); ?></p>

					<?php $wpai_openai_key_present = '' !== wpai_publisher_get_openai_api_key(); ?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Usa knowledge base', 'wp-ai-publisher' ); ?></th>
								<td><label><input type="checkbox" name="wpai_publisher_settings[use_openai_file_search]" value="1" <?php checked( ! empty( $settings['use_openai_file_search'] ) ); ?>> <?php echo esc_html__( 'Genera gli articoli usando file_search sui Vector store OpenAI configurati.', 'wp-ai-publisher' ); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><label for="wpai-openai-vs"><?php echo esc_html__( 'Vector Store ID', 'wp-ai-publisher' ); ?></label></th>
								<td><textarea id="wpai-openai-vs" name="wpai_publisher_settings[openai_vector_store_ids]" rows="2" class="large-text code" placeholder="vs_abc123..."><?php echo esc_textarea( (string) ( $settings['openai_vector_store_ids'] ?? '' ) ); ?></textarea><p class="description"><?php echo esc_html__( 'Uno o più ID di Vector store (separati da virgola, spazio o a capo). Li trovi su platform.openai.com/storage → Vector stores.', 'wp-ai-publisher' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><label for="wpai-openai-model"><?php echo esc_html__( 'Modello Responses API', 'wp-ai-publisher' ); ?></label></th>
								<td><input type="text" id="wpai-openai-model" name="wpai_publisher_settings[openai_responses_model]" class="regular-text" value="<?php echo esc_attr( (string) ( $settings['openai_responses_model'] ?? '' ) ); ?>" placeholder="gpt-4.1-mini"><p class="description"><?php echo esc_html__( 'Modello OpenAI per la Responses API. Se vuoto, usa il modello dei Parametri AI o un valore predefinito.', 'wp-ai-publisher' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><label for="wpai-fs-instruction"><?php echo esc_html__( 'Regola di riscrittura (anti-plagio)', 'wp-ai-publisher' ); ?></label></th>
								<td>
									<textarea id="wpai-fs-instruction" name="wpai_publisher_settings[file_search_instruction]" rows="4" class="large-text" placeholder="<?php echo esc_attr( wpai_publisher_default_file_search_instruction() ); ?>"><?php echo esc_textarea( (string) ( $settings['file_search_instruction'] ?? '' ) ); ?></textarea>
									<p class="description"><?php echo esc_html__( 'Istruzione data all’AI su come usare i documenti e i vector store dello storage OpenAI: usarli come riferimento autorevole ma riscriverli con parole proprie, senza copia-incolla. Lascia vuoto per usare il testo predefinito (mostrato come segnaposto).', 'wp-ai-publisher' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Chiave API OpenAI', 'wp-ai-publisher' ); ?></th>
								<td>
									<?php if ( $wpai_openai_key_present ) : ?>
										<span class="wpai-badge wpai-badge--ok"><?php echo esc_html__( 'Configurata', 'wp-ai-publisher' ); ?></span>
										<p class="description"><?php echo esc_html__( 'Rilevata dalla costante WPAIP_OPENAI_API_KEY (o dal filtro wpai_publisher_openai_api_key).', 'wp-ai-publisher' ); ?></p>
									<?php else : ?>
										<span class="wpai-badge wpai-badge--info"><?php echo esc_html__( 'Non configurata', 'wp-ai-publisher' ); ?></span>
										<p class="description"><?php echo wp_kses( __( 'Per sicurezza la chiave non si salva nel database. Aggiungi in <code>wp-config.php</code>:<br><code>define( \'WPAIP_OPENAI_API_KEY\', \'sk-...\' );</code>', 'wp-ai-publisher' ), array( 'code' => array(), 'br' => array() ) ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Verifica connessione', 'wp-ai-publisher' ); ?></th>
								<td>
									<?php $wpai_fs_test_url = wp_nonce_url( admin_url( 'admin-post.php?action=wpai_publisher_test_openai_file_search' ), 'wpai_publisher_test_openai_file_search' ); ?>
									<a href="<?php echo esc_url( $wpai_fs_test_url ); ?>" class="button button-secondary"><?php echo esc_html__( 'Testa accesso allo storage OpenAI', 'wp-ai-publisher' ); ?></a>
									<p class="description"><?php echo esc_html__( 'Verifica che la chiave raggiunga i Vector store configurati (con numero di file indicizzati) e che il modello riesca a usarli tramite file_search. Salva prima le impostazioni.', 'wp-ai-publisher' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

			</div>

			<?php /* ============================ TAB: SOCIAL ============================ */ ?>
			<div class="wpai-tab-panel" id="wpai-tab-social" data-wpai-panel="social">

				<p class="wpai-tab-intro"><?php echo esc_html__( 'Condivisione automatica degli articoli sui canali social configurati al momento della pubblicazione. Per ogni rete il token di accesso va definito in wp-config.php (mai salvato nel database) e la condivisione si attiva per singolo articolo dalle caselle “WP AI Publisher” nell’editor.', 'wp-ai-publisher' ); ?></p>

				<div class="wpai-section">
					<h2><?php echo esc_html__( 'Facebook', 'wp-ai-publisher' ); ?></h2>
					<p class="wpai-tab-intro"><?php echo esc_html__( 'Condividi automaticamente l’articolo su una Pagina Facebook quando viene pubblicato. Attiva la condivisione per singolo articolo dalla casella “WP AI Publisher — Facebook” nell’editor.', 'wp-ai-publisher' ); ?></p>

					<details class="wpai-facebook-help" style="margin:8px 0 16px; padding:12px 16px; background:#fff; border:1px solid #dcdcde; border-radius:4px;">
						<summary style="cursor:pointer; font-weight:600;"><?php echo esc_html__( 'Come recuperare l’ID Pagina e il Token di accesso (WPAIP_FACEBOOK_ACCESS_TOKEN)', 'wp-ai-publisher' ); ?></summary>
						<div style="margin-top:10px;">
							<p><strong><?php echo esc_html__( 'A. Trovare l’ID della Pagina', 'wp-ai-publisher' ); ?></strong></p>
							<ul style="margin-left:18px; list-style:disc;">
								<li><?php echo wp_kses( __( '<strong>Meta Business Suite</strong> → <em>Impostazioni</em> → <em>Informazioni sulla Pagina</em>: l’ID numerico è in fondo alla scheda. Apri <a href="https://business.facebook.com/settings/pages" target="_blank" rel="noopener">business.facebook.com/settings/pages</a>.', 'wp-ai-publisher' ), array( 'strong' => array(), 'em' => array(), 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); ?></li>
								<li><?php echo wp_kses( __( 'In alternativa, sulla Pagina: <em>Informazioni</em> → in fondo trovi “ID Pagina”.', 'wp-ai-publisher' ), array( 'em' => array() ) ); ?></li>
								<li><?php echo wp_kses( __( 'Via API: apri <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener">Graph API Explorer</a> e chiama <code>me/accounts</code>: ottieni <code>id</code> e <code>name</code> di tutte le Pagine che gestisci.', 'wp-ai-publisher' ), array( 'code' => array(), 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); ?></li>
							</ul>
							<p><strong><?php echo esc_html__( 'B. Ottenere il Page Access Token', 'wp-ai-publisher' ); ?></strong></p>
							<ol style="margin-left:18px;">
								<li><?php echo wp_kses( __( 'Crea un’app su <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">developers.facebook.com/apps</a> (tipo “Business”).', 'wp-ai-publisher' ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); ?></li>
								<li><?php echo wp_kses( __( 'Apri il <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener">Graph API Explorer</a>, seleziona l’app e aggiungi i permessi <code>pages_manage_posts</code> e <code>pages_read_engagement</code>, poi genera il token e seleziona la Pagina (così ottieni un <em>Page Access Token</em>).', 'wp-ai-publisher' ), array( 'code' => array(), 'em' => array(), 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); ?></li>
								<li><?php echo wp_kses( __( 'Consigliato per la produzione: crea un <strong>System User</strong> in <a href="https://business.facebook.com/settings/system-users" target="_blank" rel="noopener">Impostazioni Business → Utenti di sistema</a>, assegna la Pagina e genera un token <em>che non scade</em>. Verifica/allunga la durata con lo <a href="https://developers.facebook.com/tools/debug/accesstoken/" target="_blank" rel="noopener">Access Token Debugger</a>.', 'wp-ai-publisher' ), array( 'strong' => array(), 'em' => array(), 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); ?></li>
								<li><?php echo wp_kses( __( 'Aggiungi il token in <code>wp-config.php</code>:<br><code>define( \'WPAIP_FACEBOOK_ACCESS_TOKEN\', \'EAAB...\' );</code>', 'wp-ai-publisher' ), array( 'code' => array(), 'br' => array() ) ); ?></li>
								<li><?php echo esc_html__( 'Salva le impostazioni qui sotto e usa “Verifica connessione Pagina”.', 'wp-ai-publisher' ); ?></li>
							</ol>
							<p class="description"><?php echo esc_html__( 'Nota: per pubblicare su una Pagina di terzi in produzione, Meta richiede la revisione dell’app (App Review) e la verifica del Business. Per le tue Pagine in modalità sviluppo il token funziona subito.', 'wp-ai-publisher' ); ?></p>
						</div>
					</details>

					<?php
					$wpai_fb_token_present = '' !== wpai_publisher_get_facebook_access_token();
					$wpai_fb_page_id       = trim( (string) ( $settings['facebook_page_id'] ?? '' ) );
					?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Abilita Facebook', 'wp-ai-publisher' ); ?></th>
								<td><label><input type="checkbox" name="wpai_publisher_settings[facebook_enabled]" value="1" <?php checked( ! empty( $settings['facebook_enabled'] ) ); ?>> <?php echo esc_html__( 'Consenti la condivisione automatica sulla Pagina.', 'wp-ai-publisher' ); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><label for="wpai-fb-page"><?php echo esc_html__( 'ID Pagina Facebook', 'wp-ai-publisher' ); ?></label></th>
								<td>
									<input type="text" id="wpai-fb-page" name="wpai_publisher_settings[facebook_page_id]" class="regular-text" value="<?php echo esc_attr( $wpai_fb_page_id ); ?>" placeholder="1234567890">
									<p class="description">
										<?php echo esc_html__( 'L’ID numerico della Pagina (vedi la guida sopra).', 'wp-ai-publisher' ); ?>
										<?php if ( '' !== $wpai_fb_page_id ) : ?>
											<br><a href="<?php echo esc_url( 'https://www.facebook.com/' . rawurlencode( $wpai_fb_page_id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Apri la Pagina su Facebook', 'wp-ai-publisher' ); ?></a>
										<?php endif; ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Token di accesso', 'wp-ai-publisher' ); ?></th>
								<td>
									<span class="<?php echo $wpai_fb_token_present ? 'wpai-badge wpai-badge--ok' : 'wpai-badge wpai-badge--info'; ?>"><?php echo $wpai_fb_token_present ? esc_html__( 'Configurato', 'wp-ai-publisher' ) : esc_html__( 'Non configurato', 'wp-ai-publisher' ); ?></span>
									<p class="description"><?php echo wp_kses( __( 'Per sicurezza non si salva nel database. Aggiungi in <code>wp-config.php</code>:<br><code>define( \'WPAIP_FACEBOOK_ACCESS_TOKEN\', \'EAAB...\' );</code><br>Usa un <strong>Page Access Token</strong> (idealmente un token System User che non scade) con i permessi <code>pages_manage_posts</code> e <code>pages_read_engagement</code>.', 'wp-ai-publisher' ), array( 'code' => array(), 'br' => array(), 'strong' => array() ) ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wpai-fb-mode"><?php echo esc_html__( 'Tipo di post', 'wp-ai-publisher' ); ?></label></th>
								<td>
									<select id="wpai-fb-mode" name="wpai_publisher_settings[facebook_share_mode]">
										<option value="link" <?php selected( (string) ( $settings['facebook_share_mode'] ?? 'link' ), 'link' ); ?>><?php echo esc_html__( 'Link (anteprima da Open Graph)', 'wp-ai-publisher' ); ?></option>
										<option value="photo" <?php selected( (string) ( $settings['facebook_share_mode'] ?? 'link' ), 'photo' ); ?>><?php echo esc_html__( 'Foto (immagine in evidenza + testo)', 'wp-ai-publisher' ); ?></option>
									</select>
									<p class="description"><?php echo esc_html__( 'Link: condivide il permalink e Facebook genera l’anteprima. Foto: pubblica l’immagine in evidenza con il testo e il link nella didascalia.', 'wp-ai-publisher' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wpai-fb-template"><?php echo esc_html__( 'Testo del post', 'wp-ai-publisher' ); ?></label></th>
								<td><textarea id="wpai-fb-template" name="wpai_publisher_settings[facebook_message_template]" rows="4" class="large-text code"><?php echo esc_textarea( (string) ( $settings['facebook_message_template'] ?? '' ) ); ?></textarea><p class="description"><?php echo esc_html__( 'Segnaposto disponibili: {title}, {meta_title}, {meta_description}, {excerpt}, {hashtags}, {link}.', 'wp-ai-publisher' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Caption AI', 'wp-ai-publisher' ); ?></th>
								<td><label><input type="checkbox" name="wpai_publisher_settings[facebook_use_ai_caption]" value="1" <?php checked( ! empty( $settings['facebook_use_ai_caption'] ) ); ?>> <?php echo esc_html__( 'Genera il testo del post con l’AI (più ingaggiante). Se fallisce, uso il template.', 'wp-ai-publisher' ); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Pre-spunta', 'wp-ai-publisher' ); ?></th>
								<td><label><input type="checkbox" name="wpai_publisher_settings[facebook_default_share]" value="1" <?php checked( ! empty( $settings['facebook_default_share'] ) ); ?>> <?php echo esc_html__( 'Pre-attiva la condivisione per gli articoli generati dal plugin.', 'wp-ai-publisher' ); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Verifica connessione', 'wp-ai-publisher' ); ?></th>
								<td>
									<?php $wpai_fb_test_url = wp_nonce_url( admin_url( 'admin-post.php?action=wpai_publisher_facebook_test' ), 'wpai_publisher_facebook_test' ); ?>
									<a href="<?php echo esc_url( $wpai_fb_test_url ); ?>" class="button button-secondary"><?php echo esc_html__( 'Verifica connessione Pagina', 'wp-ai-publisher' ); ?></a>
									<p class="description"><?php echo esc_html__( 'Controlla che il token raggiunga la Pagina indicata. Salva prima le impostazioni.', 'wp-ai-publisher' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="wpai-section">
					<h2><?php echo esc_html__( 'Instagram', 'wp-ai-publisher' ); ?></h2>
					<p class="wpai-tab-intro"><?php echo esc_html__( 'Pubblica automaticamente l’immagine in evidenza dell’articolo su un account Instagram Business quando viene pubblicato. Attiva la condivisione per singolo articolo dalla casella “WP AI Publisher — Instagram” nell’editor.', 'wp-ai-publisher' ); ?></p>

					<details class="wpai-instagram-help" style="margin:8px 0 16px; padding:12px 16px; background:#fff; border:1px solid #dcdcde; border-radius:4px;">
						<summary style="cursor:pointer; font-weight:600;"><?php echo esc_html__( 'Prerequisiti e come recuperare l’ID account Instagram', 'wp-ai-publisher' ); ?></summary>
						<div style="margin-top:10px;">
							<p><strong><?php echo esc_html__( 'Prerequisiti (una sola volta):', 'wp-ai-publisher' ); ?></strong></p>
							<ol style="margin-left:18px;">
								<li><?php echo esc_html__( 'L’account Instagram deve essere di tipo Business o Creator.', 'wp-ai-publisher' ); ?></li>
								<li><?php echo wp_kses( __( 'Collega l’account Instagram alla tua <strong>Pagina Facebook</strong> (da Meta Business Suite → Impostazioni → Account Instagram).', 'wp-ai-publisher' ), array( 'strong' => array() ) ); ?></li>
								<li><?php echo wp_kses( __( 'Il token può essere lo stesso di Facebook: se non imposti <code>WPAIP_INSTAGRAM_ACCESS_TOKEN</code>, viene usato <code>WPAIP_FACEBOOK_ACCESS_TOKEN</code>. Servono in più i permessi <code>instagram_basic</code> e <code>instagram_content_publish</code>.', 'wp-ai-publisher' ), array( 'code' => array() ) ); ?></li>
							</ol>
							<p><strong><?php echo esc_html__( 'Trovare l’ID account Instagram (IG User ID)', 'wp-ai-publisher' ); ?></strong></p>
							<ul style="margin-left:18px; list-style:disc;">
								<li><?php echo wp_kses( __( 'Nel <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener">Graph API Explorer</a> chiama <code>{ID-Pagina}?fields=instagram_business_account</code>: il valore <code>id</code> restituito è l’ID dell’account Instagram da incollare qui sotto.', 'wp-ai-publisher' ), array( 'code' => array(), 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); ?></li>
							</ul>
							<p class="description"><?php echo esc_html__( 'Note: Instagram richiede sempre un’immagine (usa l’immagine in evidenza); i link nella caption non sono cliccabili, quindi il link viene aggiunto come testo (es. “link in bio”). Come per Facebook, in produzione Meta richiede App Review e verifica del Business.', 'wp-ai-publisher' ); ?></p>
						</div>
					</details>

					<?php
					$wpai_ig_token_present = '' !== wpai_publisher_get_instagram_access_token();
					?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Abilita Instagram', 'wp-ai-publisher' ); ?></th>
								<td><label><input type="checkbox" name="wpai_publisher_settings[instagram_enabled]" value="1" <?php checked( ! empty( $settings['instagram_enabled'] ) ); ?>> <?php echo esc_html__( 'Consenti la pubblicazione automatica su Instagram.', 'wp-ai-publisher' ); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><label for="wpai-ig-user"><?php echo esc_html__( 'ID account Instagram', 'wp-ai-publisher' ); ?></label></th>
								<td><input type="text" id="wpai-ig-user" name="wpai_publisher_settings[instagram_user_id]" class="regular-text" value="<?php echo esc_attr( (string) ( $settings['instagram_user_id'] ?? '' ) ); ?>" placeholder="17841400000000000"><p class="description"><?php echo esc_html__( 'L’ID numerico dell’account Instagram Business (vedi la guida sopra).', 'wp-ai-publisher' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Token di accesso', 'wp-ai-publisher' ); ?></th>
								<td>
									<span class="<?php echo $wpai_ig_token_present ? 'wpai-badge wpai-badge--ok' : 'wpai-badge wpai-badge--info'; ?>"><?php echo $wpai_ig_token_present ? esc_html__( 'Configurato', 'wp-ai-publisher' ) : esc_html__( 'Non configurato', 'wp-ai-publisher' ); ?></span>
									<p class="description"><?php echo wp_kses( __( 'Non si salva nel database. Usa lo stesso token di Facebook oppure definisci un token dedicato:<br><code>define( \'WPAIP_INSTAGRAM_ACCESS_TOKEN\', \'EAAB...\' );</code><br>con i permessi <code>instagram_basic</code> e <code>instagram_content_publish</code>.', 'wp-ai-publisher' ), array( 'code' => array(), 'br' => array() ) ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wpai-ig-template"><?php echo esc_html__( 'Caption', 'wp-ai-publisher' ); ?></label></th>
								<td><textarea id="wpai-ig-template" name="wpai_publisher_settings[instagram_caption_template]" rows="4" class="large-text code"><?php echo esc_textarea( (string) ( $settings['instagram_caption_template'] ?? '' ) ); ?></textarea><p class="description"><?php echo esc_html__( 'Segnaposto disponibili: {title}, {meta_title}, {meta_description}, {excerpt}, {hashtags}, {link}. Max 2200 caratteri.', 'wp-ai-publisher' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Caption AI', 'wp-ai-publisher' ); ?></th>
								<td><label><input type="checkbox" name="wpai_publisher_settings[instagram_use_ai_caption]" value="1" <?php checked( ! empty( $settings['instagram_use_ai_caption'] ) ); ?>> <?php echo esc_html__( 'Genera la caption con l’AI (più ingaggiante). Se fallisce, uso il template.', 'wp-ai-publisher' ); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Pre-spunta', 'wp-ai-publisher' ); ?></th>
								<td><label><input type="checkbox" name="wpai_publisher_settings[instagram_default_share]" value="1" <?php checked( ! empty( $settings['instagram_default_share'] ) ); ?>> <?php echo esc_html__( 'Pre-attiva la condivisione per gli articoli generati dal plugin.', 'wp-ai-publisher' ); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Verifica connessione', 'wp-ai-publisher' ); ?></th>
								<td>
									<?php $wpai_ig_test_url = wp_nonce_url( admin_url( 'admin-post.php?action=wpai_publisher_instagram_test' ), 'wpai_publisher_instagram_test' ); ?>
									<a href="<?php echo esc_url( $wpai_ig_test_url ); ?>" class="button button-secondary"><?php echo esc_html__( 'Verifica connessione Instagram', 'wp-ai-publisher' ); ?></a>
									<p class="description"><?php echo esc_html__( 'Controlla che il token raggiunga l’account Instagram indicato. Salva prima le impostazioni.', 'wp-ai-publisher' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="wpai-section">
					<h2><?php echo esc_html__( 'LinkedIn', 'wp-ai-publisher' ); ?></h2>
					<p class="wpai-tab-intro"><?php echo esc_html__( 'Condividi automaticamente l’articolo su una Pagina aziendale (organizzazione) LinkedIn quando viene pubblicato. Attiva la condivisione per singolo articolo dalla casella “WP AI Publisher — LinkedIn” nell’editor.', 'wp-ai-publisher' ); ?></p>
					<details style="margin:0 0 12px;">
						<summary style="cursor:pointer; font-weight:600;"><?php echo esc_html__( 'Prerequisiti e come recuperare l’ID organizzazione e il token', 'wp-ai-publisher' ); ?></summary>
						<div style="margin-top:8px;">
							<ul style="list-style:disc; margin-left:20px;">
								<li><?php echo wp_kses( __( 'Crea un’app LinkedIn e richiedi il prodotto <strong>Community Management API</strong> con gli scope <code>w_organization_social</code> e <code>r_organization_social</code>.', 'wp-ai-publisher' ), array( 'strong' => array(), 'code' => array() ) ); ?></li>
								<li><?php echo wp_kses( __( 'L’account che genera il token deve essere <strong>amministratore</strong> della Pagina aziendale.', 'wp-ai-publisher' ), array( 'strong' => array() ) ); ?></li>
								<li><?php echo wp_kses( __( 'L’<strong>ID organizzazione</strong> è il numero nell’URL della pagina admin (es. linkedin.com/company/<strong>12345678</strong>/admin/): inserisci solo il numero.', 'wp-ai-publisher' ), array( 'strong' => array() ) ); ?></li>
								<li><?php echo wp_kses( __( 'Il token NON si salva nel database: definiscilo in wp-config.php:<br><code>define( \'WPAIP_LINKEDIN_ACCESS_TOKEN\', \'AQX...\' );</code>', 'wp-ai-publisher' ), array( 'code' => array(), 'br' => array() ) ); ?></li>
							</ul>
						</div>
					</details>
					<?php $wpai_in_token_present = '' !== wpai_publisher_get_linkedin_access_token(); ?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Abilita LinkedIn', 'wp-ai-publisher' ); ?></th>
								<td><label><input type="checkbox" name="wpai_publisher_settings[linkedin_enabled]" value="1" <?php checked( ! empty( $settings['linkedin_enabled'] ) ); ?>> <?php echo esc_html__( 'Consenti la pubblicazione automatica su LinkedIn.', 'wp-ai-publisher' ); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><label for="wpai-in-org"><?php echo esc_html__( 'ID organizzazione', 'wp-ai-publisher' ); ?></label></th>
								<td><input type="text" id="wpai-in-org" name="wpai_publisher_settings[linkedin_org_id]" class="regular-text" value="<?php echo esc_attr( (string) ( $settings['linkedin_org_id'] ?? '' ) ); ?>" placeholder="12345678"><p class="description"><?php echo esc_html__( 'Solo il numero (vedi la guida sopra).', 'wp-ai-publisher' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Token di accesso', 'wp-ai-publisher' ); ?></th>
								<td>
									<?php if ( $wpai_in_token_present ) : ?>
										<span class="wpai-badge wpai-badge--ok"><?php echo esc_html__( 'Rilevato', 'wp-ai-publisher' ); ?></span>
									<?php else : ?>
										<span class="wpai-badge wpai-badge--not-verified"><?php echo esc_html__( 'Non rilevato', 'wp-ai-publisher' ); ?></span>
									<?php endif; ?>
									<p class="description"><?php echo wp_kses( __( 'Definito tramite la costante <code>WPAIP_LINKEDIN_ACCESS_TOKEN</code> (mai salvato nel database).', 'wp-ai-publisher' ), array( 'code' => array() ) ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wpai-in-template"><?php echo esc_html__( 'Testo del post', 'wp-ai-publisher' ); ?></label></th>
								<td><textarea id="wpai-in-template" name="wpai_publisher_settings[linkedin_message_template]" rows="4" class="large-text code"><?php echo esc_textarea( (string) ( $settings['linkedin_message_template'] ?? '' ) ); ?></textarea><p class="description"><?php echo esc_html__( 'Segnaposto: {title}, {meta_title}, {meta_description}, {excerpt}, {hashtags}, {link}.', 'wp-ai-publisher' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Caption con AI', 'wp-ai-publisher' ); ?></th>
								<td><label><input type="checkbox" name="wpai_publisher_settings[linkedin_use_ai_caption]" value="1" <?php checked( ! empty( $settings['linkedin_use_ai_caption'] ) ); ?>> <?php echo esc_html__( 'Genera il testo con l’AI. Se fallisce, uso il template.', 'wp-ai-publisher' ); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Pre-attiva condivisione', 'wp-ai-publisher' ); ?></th>
								<td><label><input type="checkbox" name="wpai_publisher_settings[linkedin_default_share]" value="1" <?php checked( ! empty( $settings['linkedin_default_share'] ) ); ?>> <?php echo esc_html__( 'Pre-attiva la condivisione per gli articoli generati dal plugin.', 'wp-ai-publisher' ); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Verifica connessione', 'wp-ai-publisher' ); ?></th>
								<td>
									<?php $wpai_in_test_url = wp_nonce_url( admin_url( 'admin-post.php?action=wpai_publisher_linkedin_test' ), 'wpai_publisher_linkedin_test' ); ?>
									<a href="<?php echo esc_url( $wpai_in_test_url ); ?>" class="button button-secondary"><?php echo esc_html__( 'Verifica connessione LinkedIn', 'wp-ai-publisher' ); ?></a>
									<p class="description"><?php echo esc_html__( 'Controlla che il token raggiunga l’organizzazione indicata. Salva prima le impostazioni.', 'wp-ai-publisher' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="wpai-section">
					<h2><?php echo esc_html__( 'Condivisione automatica', 'wp-ai-publisher' ); ?></h2>
					<p class="wpai-tab-intro"><?php echo esc_html__( 'Regola la condivisione sui social configurati per le bozze importate tramite l’importazione massiva.', 'wp-ai-publisher' ); ?></p>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Bozze importate', 'wp-ai-publisher' ); ?></th>
								<td>
									<label><input type="checkbox" name="wpai_publisher_settings[auto_share_imported]" value="1" <?php checked( ! empty( $settings['auto_share_imported'] ) ); ?>> <?php echo esc_html__( 'Condividi automaticamente sui social configurati al passaggio in pubblicazione delle bozze importate.', 'wp-ai-publisher' ); ?></label>
									<p class="description"><?php echo esc_html__( 'Vale per le bozze create dall’importazione massiva. La scelta esplicita su ogni articolo (attiva/disattiva) ha sempre la precedenza su questa impostazione.', 'wp-ai-publisher' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

			</div>

			<?php /* ============================ TAB: TELEGRAM ============================ */ ?>
			<div class="wpai-tab-panel" id="wpai-tab-telegram" data-wpai-panel="telegram">

				<div class="wpai-section">
					<h2><?php echo esc_html__( 'Telegram', 'wp-ai-publisher' ); ?></h2>
					<p class="wpai-tab-intro"><?php echo esc_html__( 'Invia un messaggio al bot Telegram per creare automaticamente un’idea contenuto e generare la bozza. La generazione avviene in background e ricevi una risposta con il link alla bozza.', 'wp-ai-publisher' ); ?></p>

					<details class="wpai-telegram-help" style="margin:8px 0 16px; padding:12px 16px; background:#fff; border:1px solid #dcdcde; border-radius:4px;">
						<summary style="cursor:pointer; font-weight:600;"><?php echo esc_html__( 'Come configurare e usare l’integrazione Telegram', 'wp-ai-publisher' ); ?></summary>
						<div style="margin-top:10px;">
							<p><strong><?php echo esc_html__( 'Configurazione (una sola volta):', 'wp-ai-publisher' ); ?></strong></p>
							<ol style="margin-left:18px;">
								<li><?php echo wp_kses( __( 'Crea un bot con <strong>@BotFather</strong> su Telegram e copia il <em>token</em>.', 'wp-ai-publisher' ), array( 'strong' => array(), 'em' => array() ) ); ?></li>
								<li><?php echo wp_kses( __( 'In <code>wp-config.php</code> aggiungi:<br><code>define( \'WPAIP_TELEGRAM_BOT_TOKEN\', \'123456:ABC...\' );</code><br><code>define( \'WPAIP_TELEGRAM_SECRET\', \'una-stringa-casuale-lunga\' );</code>', 'wp-ai-publisher' ), array( 'code' => array(), 'br' => array() ) ); ?></li>
								<li><?php echo esc_html__( 'Scopri la tua Chat ID: scrivi al bot @userinfobot (ti risponde con il tuo ID), oppure invia un messaggio al tuo bot e leggi la Chat ID nei log (Stato sistema → eventi “telegram”).', 'wp-ai-publisher' ); ?></li>
								<li><?php echo esc_html__( 'Qui sotto: spunta “Abilita Telegram”, incolla la/le Chat ID autorizzate, scegli la Tipologia articolo e la lingua, poi salva le impostazioni.', 'wp-ai-publisher' ); ?></li>
								<li><?php echo esc_html__( 'Clicca “Registra webhook”, poi “Verifica stato webhook” per conferma.', 'wp-ai-publisher' ); ?></li>
							</ol>
							<p><strong><?php echo esc_html__( 'Uso quotidiano:', 'wp-ai-publisher' ); ?></strong></p>
							<ul style="margin-left:18px; list-style:disc;">
								<li><?php echo esc_html__( 'Scrivi al bot un messaggio con l’argomento dell’articolo (es. “Guida alla scelta del nome a dominio per un blog WordPress”).', 'wp-ai-publisher' ); ?></li>
								<li><?php echo esc_html__( 'Ricevi subito la conferma “Idea ricevuta”; a generazione completata il bot risponde con titolo e link alla bozza.', 'wp-ai-publisher' ); ?></li>
								<li><?php echo esc_html__( 'Un messaggio = una bozza. I messaggi che iniziano con “/” vengono ignorati. Tipologia e lingua sono quelle impostate qui.', 'wp-ai-publisher' ); ?></li>
								<li><?php echo esc_html__( 'Il pulsante “Invia istruzioni su Telegram” recapita queste istruzioni d’uso alle Chat ID autorizzate.', 'wp-ai-publisher' ); ?></li>
							</ul>
							<p class="description"><?php echo esc_html__( 'Requisiti: il sito deve essere raggiungibile pubblicamente (per ricevere il webhook), l’hosting deve consentire HTTPS in uscita verso api.telegram.org e WP-Cron deve essere attivo (la bozza è generata in background).', 'wp-ai-publisher' ); ?></p>
						</div>
					</details>

					<?php
					$wpai_tg_token_present  = '' !== wpai_publisher_get_telegram_bot_token();
					$wpai_tg_secret_present = '' !== wpai_publisher_get_telegram_secret_token();
					$wpai_tg_article_types  = function_exists( 'wpai_publisher_get_active_article_types_safe' ) ? wpai_publisher_get_active_article_types_safe() : array();
					$wpai_tg_webhook_url    = esc_url_raw( rest_url( 'wp-ai-publisher/v1/telegram' ) );
					?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Abilita Telegram', 'wp-ai-publisher' ); ?></th>
								<td><label><input type="checkbox" name="wpai_publisher_settings[telegram_enabled]" value="1" <?php checked( ! empty( $settings['telegram_enabled'] ) ); ?>> <?php echo esc_html__( 'Accetta messaggi dal bot e crea idee/bozze.', 'wp-ai-publisher' ); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Token bot / Secret', 'wp-ai-publisher' ); ?></th>
								<td>
									<span class="<?php echo $wpai_tg_token_present ? 'wpai-badge wpai-badge--ok' : 'wpai-badge wpai-badge--info'; ?>"><?php echo $wpai_tg_token_present ? esc_html__( 'Token configurato', 'wp-ai-publisher' ) : esc_html__( 'Token mancante', 'wp-ai-publisher' ); ?></span>
									<span class="<?php echo $wpai_tg_secret_present ? 'wpai-badge wpai-badge--ok' : 'wpai-badge wpai-badge--info'; ?>"><?php echo $wpai_tg_secret_present ? esc_html__( 'Secret configurato', 'wp-ai-publisher' ) : esc_html__( 'Secret mancante', 'wp-ai-publisher' ); ?></span>
									<p class="description"><?php echo wp_kses( __( 'Per sicurezza non si salvano nel database. Aggiungi in <code>wp-config.php</code>:<br><code>define( \'WPAIP_TELEGRAM_BOT_TOKEN\', \'123456:ABC...\' );</code><br><code>define( \'WPAIP_TELEGRAM_SECRET\', \'una-stringa-casuale\' );</code>', 'wp-ai-publisher' ), array( 'code' => array(), 'br' => array() ) ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wpai-tg-chats"><?php echo esc_html__( 'Chat ID autorizzate', 'wp-ai-publisher' ); ?></label></th>
								<td><textarea id="wpai-tg-chats" name="wpai_publisher_settings[telegram_allowed_chat_ids]" rows="2" class="large-text code" placeholder="123456789, -1009876543210"><?php echo esc_textarea( (string) ( $settings['telegram_allowed_chat_ids'] ?? '' ) ); ?></textarea><p class="description"><?php echo esc_html__( 'Solo i messaggi da queste chat creano idee (separa con virgola/spazio/a capo). Lascia vuoto per accettare qualsiasi chat (sconsigliato).', 'wp-ai-publisher' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><label for="wpai-tg-type"><?php echo esc_html__( 'Tipologia articolo', 'wp-ai-publisher' ); ?></label></th>
								<td>
									<select id="wpai-tg-type" name="wpai_publisher_settings[telegram_article_type_id]">
										<option value="0"><?php echo esc_html__( '— Nessuna / predefinita —', 'wp-ai-publisher' ); ?></option>
										<?php foreach ( $wpai_tg_article_types as $wpai_tg_type ) : ?>
											<option value="<?php echo esc_attr( (string) ( $wpai_tg_type['id'] ?? 0 ) ); ?>" <?php selected( (int) ( $settings['telegram_article_type_id'] ?? 0 ), (int) ( $wpai_tg_type['id'] ?? 0 ) ); ?>><?php echo esc_html( (string) ( $wpai_tg_type['name'] ?? '' ) ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php echo esc_html__( 'Tipologia usata per le bozze create da Telegram.', 'wp-ai-publisher' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wpai-tg-lang"><?php echo esc_html__( 'Lingua', 'wp-ai-publisher' ); ?></label></th>
								<td>
									<select id="wpai-tg-lang" name="wpai_publisher_settings[telegram_language]">
										<?php foreach ( array( 'it', 'en', 'fr', 'es', 'de' ) as $wpai_tg_lang ) : ?>
											<option value="<?php echo esc_attr( $wpai_tg_lang ); ?>" <?php selected( (string) ( $settings['telegram_language'] ?? 'it' ), $wpai_tg_lang ); ?>><?php echo esc_html( strtoupper( $wpai_tg_lang ) ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Risposta su Telegram', 'wp-ai-publisher' ); ?></th>
								<td><label><input type="checkbox" name="wpai_publisher_settings[telegram_reply_enabled]" value="1" <?php checked( ! empty( $settings['telegram_reply_enabled'] ) ); ?>> <?php echo esc_html__( 'Invia un messaggio di conferma con il link alla bozza.', 'wp-ai-publisher' ); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Scelta interattiva', 'wp-ai-publisher' ); ?></th>
								<td><label><input type="checkbox" name="wpai_publisher_settings[telegram_interactive]" value="1" <?php checked( ! empty( $settings['telegram_interactive'] ) ); ?>> <?php echo esc_html__( 'Dopo il messaggio, chiedi su Telegram di scegliere Tipologia articolo e Categorie con i pulsanti, prima di generare.', 'wp-ai-publisher' ); ?></label><p class="description"><?php echo esc_html__( 'Se disattivato, la bozza viene generata subito con la Tipologia predefinita e le categorie scelte dall’AI.', 'wp-ai-publisher' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'URL webhook', 'wp-ai-publisher' ); ?></th>
								<td>
									<code><?php echo esc_html( $wpai_tg_webhook_url ); ?></code>
									<?php
									$wpai_tg_set_url  = wp_nonce_url( admin_url( 'admin-post.php?action=wpai_publisher_telegram_set_webhook' ), 'wpai_publisher_telegram_set_webhook' );
									$wpai_tg_info_url = wp_nonce_url( admin_url( 'admin-post.php?action=wpai_publisher_telegram_webhook_info' ), 'wpai_publisher_telegram_webhook_info' );
									$wpai_tg_help_url = wp_nonce_url( admin_url( 'admin-post.php?action=wpai_publisher_telegram_send_help' ), 'wpai_publisher_telegram_send_help' );
									?>
									<p style="margin-top:8px;">
										<a href="<?php echo esc_url( $wpai_tg_set_url ); ?>" class="button button-secondary"><?php echo esc_html__( 'Registra webhook', 'wp-ai-publisher' ); ?></a>
										<a href="<?php echo esc_url( $wpai_tg_info_url ); ?>" class="button button-secondary"><?php echo esc_html__( 'Verifica stato webhook', 'wp-ai-publisher' ); ?></a>
										<a href="<?php echo esc_url( $wpai_tg_help_url ); ?>" class="button button-secondary"><?php echo esc_html__( 'Invia istruzioni su Telegram', 'wp-ai-publisher' ); ?></a>
									</p>
									<p class="description"><?php echo esc_html__( 'I pulsanti usano il token e il secret configurati nelle costanti, senza terminale. Registra il webhook dopo aver salvato le impostazioni e definito le costanti.', 'wp-ai-publisher' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

			</div>

			<?php /* ============================ TAB: AVANZATE ============================ */ ?>
			<div class="wpai-tab-panel" id="wpai-tab-avanzate" data-wpai-panel="avanzate">

				<div class="wpai-section">
					<h2><?php echo esc_html__( 'Sicurezza Abilities AI', 'wp-ai-publisher' ); ?></h2>
					<p class="wpai-tab-intro"><?php echo esc_html__( 'Sezione tecnica per sviluppatori e amministratori: non fa parte del flusso editoriale principale. Per impostazione predefinita WP AI Publisher non esegue abilities arbitrarie.', 'wp-ai-publisher' ); ?></p>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="wpai-safe-ai-ability-names"><?php echo esc_html__( 'Abilities AI sicure', 'wp-ai-publisher' ); ?></label></th>
								<td><textarea id="wpai-safe-ai-ability-names" name="wpai_publisher_settings[safe_ai_ability_names]" rows="5" class="large-text code"><?php echo esc_textarea( $settings['safe_ai_ability_names'] ?? '' ); ?></textarea><p class="description"><?php echo esc_html__( 'Nomi delle abilities AI considerate sicure per il dry-run, una per riga. Lascia vuoto per usare solo il filtro o i controlli automatici conservativi.', 'wp-ai-publisher' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Consenti abilities non verificate', 'wp-ai-publisher' ); ?></th>
								<td><label><input type="checkbox" name="wpai_publisher_settings[allow_unverified_ai_abilities]" value="1" <?php checked( ! empty( $settings['allow_unverified_ai_abilities'] ) ); ?>> <?php echo esc_html__( 'Consenti l’uso di abilities AI non verificate. Sconsigliato: può eseguire callback non pensate per il dry-run.', 'wp-ai-publisher' ); ?></label></td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="wpai-section">
					<h2><?php echo esc_html__( 'Log e manutenzione', 'wp-ai-publisher' ); ?></h2>
					<table class="form-table" role="presentation">
						<tbody>
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
				</div>

			</div>

		</div>

		<?php submit_button( __( 'Salva impostazioni', 'wp-ai-publisher' ) ); ?>
	</form>
</div>
