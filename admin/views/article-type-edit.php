<?php
/**
 * Article type edit/create form.
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$type       = is_array( $article_type ) ? $article_type : array();
$id         = absint( $type['id'] ?? 0 );
$categories = get_categories( array( 'hide_empty' => false ) );
?>
<div class="wrap wpai-admin">
	<h1><?php echo esc_html( $id ? __( 'Modifica Tipologia articolo', 'wp-ai-publisher' ) : __( 'Aggiungi Tipologia articolo', 'wp-ai-publisher' ) ); ?></h1>
	<p class="wpai-lead"><?php echo esc_html__( 'Una Tipologia articolo definisce come l’AI deve scrivere un contenuto. Tono, struttura, lunghezza, regole e sezioni vanno descritti liberamente nel prompt principale.', 'wp-ai-publisher' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wpai_publisher_save_article_type" />
		<input type="hidden" name="id" value="<?php echo esc_attr( (string) $id ); ?>" />
		<?php wp_nonce_field( 'wpai_publisher_save_article_type_' . $id ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="wpai-at-name"><?php esc_html_e( 'Nome', 'wp-ai-publisher' ); ?></label></th>
					<td>
						<input class="regular-text" id="wpai-at-name" name="name" value="<?php echo esc_attr( (string) ( $type['name'] ?? '' ) ); ?>" required />
						<p class="description"><?php esc_html_e( 'Nome con cui riconoscere la tipologia nel workflow Idee contenuto.', 'wp-ai-publisher' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-at-prompt"><?php esc_html_e( 'Prompt principale', 'wp-ai-publisher' ); ?></label></th>
					<td>
						<textarea class="large-text code" rows="16" id="wpai-at-prompt" name="prompt"><?php echo esc_textarea( (string) ( $type['prompt'] ?? '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Istruzioni complete per l’AI: tono, stile, struttura e sezioni desiderate, lunghezza, pubblico, regole da rispettare e cose da evitare. Scrivi tutto qui, in testo libero.', 'wp-ai-publisher' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-at-image-prompt"><?php esc_html_e( 'Prompt immagini', 'wp-ai-publisher' ); ?></label></th>
					<td>
						<textarea class="large-text code" rows="6" id="wpai-at-image-prompt" name="image_prompt"><?php echo esc_textarea( (string) ( $type['image_prompt'] ?? '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Istruzioni dedicate alla generazione delle immagini per questo tipo di articolo (stile, soggetto, formato). Usato dalla generazione immagini quando disponibile.', 'wp-ai-publisher' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-at-tags"><?php esc_html_e( 'Tag preferiti', 'wp-ai-publisher' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="3" id="wpai-at-tags" name="preferred_tags"><?php echo esc_textarea( (string) ( $type['preferred_tags'] ?? '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Opzionale. Tag da applicare alla bozza, separati da virgola o uno per riga.', 'wp-ai-publisher' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Categorie consentite', 'wp-ai-publisher' ); ?></th>
					<td>
						<?php if ( empty( $categories ) ) : ?>
							<p class="description"><?php esc_html_e( 'Nessuna categoria WordPress disponibile.', 'wp-ai-publisher' ); ?></p>
						<?php else : ?>
							<?php foreach ( $categories as $cat ) : ?>
								<label style="display:block"><input type="checkbox" name="allowed_category_ids[]" value="<?php echo esc_attr( (string) $cat->term_id ); ?>" <?php checked( in_array( (int) $cat->term_id, (array) ( $type['allowed_category_ids'] ?? array() ), true ) ); ?> /> <?php echo esc_html( $cat->name ); ?></label>
							<?php endforeach; ?>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Opzionale. Se selezioni una o più categorie, la bozza userà solo queste. Nessuna selezione = nessun vincolo.', 'wp-ai-publisher' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Attiva', 'wp-ai-publisher' ); ?></th>
					<td><label><input type="checkbox" name="is_active" value="1" <?php checked( (bool) ( $type['is_active'] ?? true ) ); ?> /> <?php esc_html_e( 'Disponibile nel workflow Idee contenuto', 'wp-ai-publisher' ); ?></label></td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<?php submit_button( __( 'Salva tipologia', 'wp-ai-publisher' ), 'primary', 'submit', false ); ?>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-publisher-article-types' ) ); ?>"><?php esc_html_e( 'Annulla', 'wp-ai-publisher' ); ?></a>
		</p>
	</form>
</div>
