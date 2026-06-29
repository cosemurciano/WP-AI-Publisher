<?php
/**
 * Edit an existing content idea before it becomes a draft.
 *
 * @package WPAIPublisher
 * @var object $edit_idea Idea row.
 * @var bool   $article_types_enabled Whether article types are enabled.
 * @var array  $active_article_types Active article types.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpai_langs = array(
	'it' => __( 'Italiano', 'wp-ai-publisher' ),
	'en' => __( 'Inglese', 'wp-ai-publisher' ),
	'fr' => __( 'Francese', 'wp-ai-publisher' ),
	'es' => __( 'Spagnolo', 'wp-ai-publisher' ),
	'de' => __( 'Tedesco', 'wp-ai-publisher' ),
);

$wpai_back_url     = admin_url( 'admin.php?page=wp-ai-publisher-content-ideas' );
$wpai_scheduled_in = '';
if ( ! empty( $edit_idea->scheduled_at ) ) {
	// Stored as UTC; show in the site timezone for the datetime-local field.
	$wpai_scheduled_in = get_date_from_gmt( (string) $edit_idea->scheduled_at, 'Y-m-d\TH:i' );
}
$wpai_error = isset( $_GET['wpai_error'] ) ? sanitize_text_field( wp_unslash( $_GET['wpai_error'] ) ) : '';
?>
<div class="wrap wpai-admin">
	<h1><?php echo esc_html( sprintf( __( 'Modifica idea #%d', 'wp-ai-publisher' ), absint( $edit_idea->id ) ) ); ?></h1>
	<p><a href="<?php echo esc_url( $wpai_back_url ); ?>">&larr; <?php echo esc_html__( 'Torna alle idee', 'wp-ai-publisher' ); ?></a></p>

	<?php if ( '' !== $wpai_error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $wpai_error ); ?></p></div>
	<?php endif; ?>

	<section class="wpai-card">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpai_publisher_update_content_idea" />
			<input type="hidden" name="idea_id" value="<?php echo esc_attr( (string) $edit_idea->id ); ?>" />
			<?php wp_nonce_field( 'wpai_publisher_update_content_idea_' . absint( $edit_idea->id ) ); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="wpai-edit-topic"><?php echo esc_html__( 'Argomento principale', 'wp-ai-publisher' ); ?></label></th>
						<td><textarea id="wpai-edit-topic" name="topic" rows="4" class="large-text" required><?php echo esc_textarea( (string) $edit_idea->topic ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpai-edit-language"><?php echo esc_html__( 'Lingua', 'wp-ai-publisher' ); ?></label></th>
						<td>
							<select id="wpai-edit-language" name="language">
								<?php foreach ( $wpai_langs as $wpai_code => $wpai_label ) : ?>
									<option value="<?php echo esc_attr( $wpai_code ); ?>" <?php selected( (string) $edit_idea->language, $wpai_code ); ?>><?php echo esc_html( $wpai_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<?php if ( $article_types_enabled ) : ?>
						<tr>
							<th scope="row"><label for="wpai-edit-article-type"><?php echo esc_html__( 'Tipologia articolo', 'wp-ai-publisher' ); ?></label></th>
							<td>
								<select id="wpai-edit-article-type" name="article_type_id" required>
									<option value=""><?php echo esc_html__( 'Seleziona una tipologia', 'wp-ai-publisher' ); ?></option>
									<?php foreach ( (array) $active_article_types as $wpai_type ) : ?>
										<option value="<?php echo esc_attr( (string) $wpai_type['id'] ); ?>" <?php selected( absint( $edit_idea->article_type_id ?? 0 ), (int) $wpai_type['id'] ); ?>><?php echo esc_html( $wpai_type['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endif; ?>
						<tr>
							<th scope="row"><label><?php echo esc_html__( 'Categorie', 'wp-ai-publisher' ); ?></label></th>
							<td>
								<?php wpai_publisher_render_category_checklist( $content_ideas->get_idea_category_ids( $edit_idea ) ); ?>
								<p class="description"><?php echo esc_html__( 'Le categorie selezionate vengono assegnate alla bozza e comunicate all’AI. Lascia vuoto per far scegliere l’AI.', 'wp-ai-publisher' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wpai-edit-scheduled-at"><?php echo esc_html__( 'Programma creazione', 'wp-ai-publisher' ); ?></label></th>
						<td>
							<input type="datetime-local" id="wpai-edit-scheduled-at" name="wpai_scheduled_at" value="<?php echo esc_attr( $wpai_scheduled_in ); ?>" />
							<p class="description"><?php echo esc_html__( 'Lascia vuoto per riportare l’idea allo stato “nuova”. Con data e ora, l’idea resta programmata.', 'wp-ai-publisher' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpai-edit-image-prompt"><?php echo esc_html__( 'Prompt immagine (copertina)', 'wp-ai-publisher' ); ?></label></th>
						<td>
							<textarea id="wpai-edit-image-prompt" name="image_prompt" rows="3" class="large-text"><?php echo esc_textarea( (string) ( $edit_idea->image_prompt ?? '' ) ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'Descrizione per generare la sola immagine in evidenza. Le eventuali immagini nel corpo restano gestite dalla Tipologia articolo.', 'wp-ai-publisher' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpai-edit-fb"><?php echo esc_html__( 'Prompt Social Facebook', 'wp-ai-publisher' ); ?></label></th>
						<td><textarea id="wpai-edit-fb" name="social_facebook" rows="2" class="large-text"><?php echo esc_textarea( (string) ( $edit_idea->social_facebook ?? '' ) ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpai-edit-ig"><?php echo esc_html__( 'Prompt Social Instagram', 'wp-ai-publisher' ); ?></label></th>
						<td><textarea id="wpai-edit-ig" name="social_instagram" rows="2" class="large-text"><?php echo esc_textarea( (string) ( $edit_idea->social_instagram ?? '' ) ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpai-edit-in"><?php echo esc_html__( 'Prompt Social LinkedIn', 'wp-ai-publisher' ); ?></label></th>
						<td><textarea id="wpai-edit-in" name="social_linkedin" rows="2" class="large-text"><?php echo esc_textarea( (string) ( $edit_idea->social_linkedin ?? '' ) ); ?></textarea><p class="description"><?php echo esc_html__( 'LinkedIn: l’integrazione di pubblicazione sarà aggiunta in seguito; il prompt viene salvato fin da ora.', 'wp-ai-publisher' ); ?></p></td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php echo esc_html__( 'Salva modifiche', 'wp-ai-publisher' ); ?></button>
				<a href="<?php echo esc_url( $wpai_back_url ); ?>" class="button"><?php echo esc_html__( 'Annulla', 'wp-ai-publisher' ); ?></a>
			</p>
		</form>
	</section>
</div>
