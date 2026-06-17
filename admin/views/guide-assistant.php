<?php
/**
 * Assistente Guide AI — settings view.
 *
 * @package WPAIPublisher
 * @var array<string,mixed> $config Current configuration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpai_guide_cats        = get_categories( array( 'hide_empty' => false ) );
$wpai_guide_post_types  = get_post_types( array( 'public' => true ), 'objects' );
$wpai_guide_selected_pt = (array) ( $config['search_post_types'] ?? array( 'post' ) );
$wpai_guide_selected_ct = array_map( 'absint', (array) ( $config['search_category_ids'] ?? array() ) );
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Assistente Guide AI', 'wp-ai-publisher' ); ?></h1>

	<?php
	if ( isset( $_GET['wpai_notice'] ) && 'guide_saved' === sanitize_key( wp_unslash( $_GET['wpai_notice'] ) ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Impostazioni salvate.', 'wp-ai-publisher' ) . '</p></div>';
	}
	?>

	<p><?php echo esc_html__( 'Genera guide personalizzate per i visitatori, ancorate ai contenuti del tuo sito. Inserisci il modulo in una pagina con lo shortcode:', 'wp-ai-publisher' ); ?>
		<code>[wpai_guide_generator]</code>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wpai_publisher_save_guide_assistant">
		<?php wp_nonce_field( 'wpai_publisher_save_guide_assistant' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Abilita assistente', 'wp-ai-publisher' ); ?></th>
					<td><label><input type="checkbox" name="wpai_guide[enabled]" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?>> <?php echo esc_html__( 'Attiva il modulo pubblico e l’endpoint di generazione.', 'wp-ai-publisher' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-guide-heading"><?php echo esc_html__( 'Titolo del modulo', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="text" id="wpai-guide-heading" name="wpai_guide[heading]" class="regular-text" value="<?php echo esc_attr( (string) $config['heading'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-guide-placeholder"><?php echo esc_html__( 'Testo segnaposto', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="text" id="wpai-guide-placeholder" name="wpai_guide[placeholder]" class="regular-text" value="<?php echo esc_attr( (string) $config['placeholder'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-guide-instructions"><?php echo esc_html__( 'Istruzioni di sistema (prompt)', 'wp-ai-publisher' ); ?></label></th>
					<td><textarea id="wpai-guide-instructions" name="wpai_guide[system_instructions]" rows="4" class="large-text"><?php echo esc_textarea( (string) $config['system_instructions'] ); ?></textarea><p class="description"><?php echo esc_html__( 'Definisce tono e comportamento dell’assistente. I link interni e i vincoli sono già gestiti dal plugin.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-guide-language"><?php echo esc_html__( 'Lingua', 'wp-ai-publisher' ); ?></label></th>
					<td>
						<select id="wpai-guide-language" name="wpai_guide[language]">
							<?php foreach ( array( 'it' => 'Italiano', 'en' => 'English', 'fr' => 'Français', 'es' => 'Español', 'de' => 'Deutsch' ) as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( (string) $config['language'], $code ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Cerca in', 'wp-ai-publisher' ); ?></th>
					<td>
						<?php foreach ( $wpai_guide_post_types as $pt ) : ?>
							<label style="margin-right:14px;"><input type="checkbox" name="wpai_guide[search_post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $wpai_guide_selected_pt, true ) ); ?>> <?php echo esc_html( $pt->labels->singular_name ); ?></label>
						<?php endforeach; ?>
						<p class="description"><?php echo esc_html__( 'Tipi di contenuto in cui cercare gli articoli da consigliare e collegare.', 'wp-ai-publisher' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Limita alle categorie', 'wp-ai-publisher' ); ?></th>
					<td>
						<?php if ( empty( $wpai_guide_cats ) ) : ?>
							<p class="description"><?php echo esc_html__( 'Nessuna categoria disponibile.', 'wp-ai-publisher' ); ?></p>
						<?php else : ?>
							<select name="wpai_guide[search_category_ids][]" multiple size="6" style="min-width:280px;">
								<?php foreach ( $wpai_guide_cats as $cat ) : ?>
									<option value="<?php echo esc_attr( (string) $cat->term_id ); ?>" <?php selected( in_array( (int) $cat->term_id, $wpai_guide_selected_ct, true ) ); ?>><?php echo esc_html( $cat->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php echo esc_html__( 'Opzionale. Se selezioni una o più categorie, la ricerca degli articoli si limita a queste.', 'wp-ai-publisher' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-guide-whitelist"><?php echo esc_html__( 'Link esterni consentiti', 'wp-ai-publisher' ); ?></label></th>
					<td><textarea id="wpai-guide-whitelist" name="wpai_guide[external_links_whitelist]" rows="4" class="large-text code" placeholder="https://esempio.com/risorsa"><?php echo esc_textarea( (string) $config['external_links_whitelist'] ); ?></textarea><p class="description"><?php echo esc_html__( 'Un URL per riga. Sono gli unici link esterni che l’AI può inserire. Tutti gli altri link non interni vengono rimossi automaticamente.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-guide-max-articles"><?php echo esc_html__( 'Articoli consigliati (max)', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="number" id="wpai-guide-max-articles" name="wpai_guide[max_articles]" min="1" max="10" value="<?php echo esc_attr( (string) $config['max_articles'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-guide-max-tokens"><?php echo esc_html__( 'Lunghezza massima (token)', 'wp-ai-publisher' ); ?></label></th>
					<td>
						<input type="number" id="wpai-guide-max-tokens" name="wpai_guide[max_tokens]" min="128" step="64" value="<?php echo esc_attr( (string) $config['max_tokens'] ); ?>">
						<p class="description">
							<?php echo esc_html__( 'Quantità libera. 1 token ≈ 1 parola. Più token = guida più lunga ma costi maggiori.', 'wp-ai-publisher' ); ?>
							<span id="wpai-guide-token-estimate" style="display:block;margin-top:4px;font-style:italic;"></span>
							<span id="wpai-guide-token-cost" style="display:block;margin-top:2px;font-style:italic;"></span>
						</p>
						<script>
						( function () {
							var field = document.getElementById( 'wpai-guide-max-tokens' );
							var out = document.getElementById( 'wpai-guide-token-estimate' );
							var cost = document.getElementById( 'wpai-guide-token-cost' );
							if ( ! field || ! out ) { return; }
							// Prezzi indicativi OpenAI (USD per 1M token), input/output.
							var MODELS = [
								{ name: 'gpt-4o-mini', input: 0.15, output: 0.60 },
								{ name: 'gpt-4o', input: 2.50, output: 10.00 }
							];
							var INPUT_TOKENS = 1200; // prompt + articoli (stima).
							function update() {
								var tokens = parseInt( field.value, 10 ) || 0;
								var minutes = Math.max( 1, Math.round( tokens / 200 ) );
								out.textContent = <?php echo wp_json_encode( __( 'Stima:', 'wp-ai-publisher' ) ); ?> + ' ≈ ' + tokens.toLocaleString() + ' ' +
									<?php echo wp_json_encode( __( 'parole', 'wp-ai-publisher' ) ); ?> + ' (' + minutes + ' ' +
									<?php echo wp_json_encode( __( 'min di lettura', 'wp-ai-publisher' ) ); ?> + ')';
								var parts = MODELS.map( function ( m ) {
									var c = ( INPUT_TOKENS / 1e6 ) * m.input + ( tokens / 1e6 ) * m.output;
									return m.name + ': ~$' + c.toFixed( 4 );
								} );
								cost.textContent = <?php echo wp_json_encode( __( 'Costo stimato per guida (OpenAI, indicativo):', 'wp-ai-publisher' ) ); ?> + ' ' + parts.join( ' · ' );
							}
							field.addEventListener( 'input', update );
							update();
						}() );
						</script>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php echo esc_html__( 'Limiti anti-abuso e cache', 'wp-ai-publisher' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="wpai-guide-ip-limit"><?php echo esc_html__( 'Limite giornaliero per IP', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="number" id="wpai-guide-ip-limit" name="wpai_guide[per_ip_daily_limit]" min="0" value="<?php echo esc_attr( (string) $config['per_ip_daily_limit'] ); ?>"><p class="description"><?php echo esc_html__( '0 = nessun limite. Consigliato 3.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-guide-global-limit"><?php echo esc_html__( 'Limite giornaliero globale', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="number" id="wpai-guide-global-limit" name="wpai_guide[global_daily_limit]" min="0" value="<?php echo esc_attr( (string) $config['global_daily_limit'] ); ?>"><p class="description"><?php echo esc_html__( 'Tetto complessivo di generazioni al giorno per tutto il sito. 0 = nessun limite.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-guide-cooldown"><?php echo esc_html__( 'Attesa tra richieste (sec)', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="number" id="wpai-guide-cooldown" name="wpai_guide[cooldown_seconds]" min="0" value="<?php echo esc_attr( (string) $config['cooldown_seconds'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-guide-cache"><?php echo esc_html__( 'Cache risposte (giorni)', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="number" id="wpai-guide-cache" name="wpai_guide[cache_ttl_days]" min="0" value="<?php echo esc_attr( (string) $config['cache_ttl_days'] ); ?>"><p class="description"><?php echo esc_html__( 'Le richieste identiche riusano il risultato salvato (risparmio token). 0 = disattiva la cache.', 'wp-ai-publisher' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-guide-footer"><?php echo esc_html__( 'Nota a piè di guida', 'wp-ai-publisher' ); ?></label></th>
					<td><textarea id="wpai-guide-footer" name="wpai_guide[result_footer]" rows="2" class="large-text"><?php echo esc_textarea( (string) $config['result_footer'] ); ?></textarea><p class="description"><?php echo esc_html__( 'Disclaimer opzionale mostrato sotto ogni guida (es. “Contenuto generato con l’AI”).', 'wp-ai-publisher' ); ?></p></td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Salva impostazioni', 'wp-ai-publisher' ) ); ?>
	</form>
</div>
