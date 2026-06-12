<?php
/**
 * Content ideas view.
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice_key = sanitize_key( wp_unslash( $_GET['wpai_notice'] ?? '' ) );
$notices    = array(
	'idea_saved'               => array( 'success', __( 'Idea contenuto salvata.', 'wp-ai-publisher' ) ),
	'dry_run_completed'        => array( 'success', __( 'Dry-run completato.', 'wp-ai-publisher' ) ),
	'dry_run_failed'           => array( 'error', __( 'Dry-run fallito. Controlla le note di validazione.', 'wp-ai-publisher' ) ),
	'insufficient_permissions' => array( 'error', __( 'Permessi insufficienti.', 'wp-ai-publisher' ) ),
	'idea_not_found'           => array( 'error', __( 'Idea non trovata.', 'wp-ai-publisher' ) ),
	'idea_save_failed'         => array( 'error', __( 'Impossibile salvare l’idea contenuto.', 'wp-ai-publisher' ) ),
);

$language_labels = array(
	'it' => __( 'Italiano', 'wp-ai-publisher' ),
	'en' => __( 'Inglese', 'wp-ai-publisher' ),
	'fr' => __( 'Francese', 'wp-ai-publisher' ),
	'es' => __( 'Spagnolo', 'wp-ai-publisher' ),
	'de' => __( 'Tedesco', 'wp-ai-publisher' ),
);

$level_labels = array(
	'base'       => __( 'Base', 'wp-ai-publisher' ),
	'intermedio' => __( 'Intermedio', 'wp-ai-publisher' ),
	'avanzato'   => __( 'Avanzato', 'wp-ai-publisher' ),
);


$source_labels = array(
	'wordpress_ai'   => __( 'WordPress AI', 'wp-ai-publisher' ),
	'local_fallback' => __( 'Fallback locale', 'wp-ai-publisher' ),
	'unknown'        => __( 'Non disponibile', 'wp-ai-publisher' ),
);

$source_badges = array(
	'wordpress_ai'   => __( 'AI WordPress', 'wp-ai-publisher' ),
	'local_fallback' => __( 'Fallback locale', 'wp-ai-publisher' ),
	'unknown'        => __( 'Non disponibile', 'wp-ai-publisher' ),
);

$render_list = static function ( $items ) {
	if ( empty( $items ) || ! is_array( $items ) ) {
		echo esc_html__( '—', 'wp-ai-publisher' );
		return;
	}

	echo '<ul>';
	foreach ( $items as $item ) {
		echo '<li>' . esc_html( is_scalar( $item ) ? (string) $item : wp_json_encode( $item ) ) . '</li>';
	}
	echo '</ul>';
};
?>
<div class="wrap wpai-admin">
	<h1><?php echo esc_html__( 'Idee contenuto', 'wp-ai-publisher' ); ?></h1>
	<p class="wpai-lead"><?php echo esc_html__( 'Crea e testa idee editoriali prima di trasformarle in bozze WordPress. In questa fase il dry-run non pubblica nulla e non modifica contenuti esistenti.', 'wp-ai-publisher' ); ?></p>

	<?php if ( isset( $notices[ $notice_key ] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notices[ $notice_key ][0] ); ?> is-dismissible">
			<p><?php echo esc_html( $notices[ $notice_key ][1] ); ?></p>
		</div>
	<?php endif; ?>

	<section class="wpai-card">
		<h2><?php echo esc_html__( 'Nuova idea contenuto', 'wp-ai-publisher' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpai_publisher_create_content_idea" />
			<?php wp_nonce_field( 'wpai_publisher_create_content_idea' ); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="wpai-content-topic"><?php echo esc_html__( 'Argomento principale', 'wp-ai-publisher' ); ?></label></th>
						<td>
							<textarea id="wpai-content-topic" name="topic" rows="4" class="large-text" required></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpai-content-keyword"><?php echo esc_html__( 'Keyword principale', 'wp-ai-publisher' ); ?></label></th>
						<td><input id="wpai-content-keyword" name="keyword" type="text" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpai-content-language"><?php echo esc_html__( 'Lingua', 'wp-ai-publisher' ); ?></label></th>
						<td>
							<select id="wpai-content-language" name="language">
								<?php foreach ( $language_labels as $language_key => $language_label ) : ?>
									<option value="<?php echo esc_attr( $language_key ); ?>" <?php selected( 'it', $language_key ); ?>><?php echo esc_html( $language_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpai-content-target"><?php echo esc_html__( 'Pubblico target', 'wp-ai-publisher' ); ?></label></th>
						<td><input id="wpai-content-target" name="target_audience" type="text" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpai-content-level"><?php echo esc_html__( 'Livello tutorial', 'wp-ai-publisher' ); ?></label></th>
						<td>
							<select id="wpai-content-level" name="tutorial_level">
								<option value="base"><?php echo esc_html__( 'Base', 'wp-ai-publisher' ); ?></option>
								<option value="intermedio"><?php echo esc_html__( 'Intermedio', 'wp-ai-publisher' ); ?></option>
								<option value="avanzato"><?php echo esc_html__( 'Avanzato', 'wp-ai-publisher' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpai-content-notes"><?php echo esc_html__( 'Note editoriali', 'wp-ai-publisher' ); ?></label></th>
						<td><textarea id="wpai-content-notes" name="notes" rows="4" class="large-text"></textarea></td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'Salva idea', 'wp-ai-publisher' ) ); ?>
		</form>
	</section>

	<h2><?php echo esc_html__( 'Ultime idee', 'wp-ai-publisher' ); ?></h2>
	<?php if ( empty( $ideas ) ) : ?>
		<div class="notice notice-info inline">
			<p><?php echo esc_html__( 'Nessuna idea contenuto presente. Inserisci un argomento per iniziare un dry-run sicuro.', 'wp-ai-publisher' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped wpai-status-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'ID', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Stato', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Argomento', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Keyword', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Lingua', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Livello', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Data creazione', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Azioni', 'wp-ai-publisher' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $ideas as $idea ) : ?>
					<?php $view_url = wp_nonce_url( admin_url( 'admin.php?page=wp-ai-publisher-content-ideas&view_idea=' . absint( $idea->id ) ), 'wpai_publisher_view_content_idea_' . absint( $idea->id ) ); ?>
					<tr>
						<td><?php echo esc_html( (string) $idea->id ); ?></td>
						<td><span class="<?php echo esc_attr( wpai_publisher_badge_class( $idea->status ) ); ?>"><?php echo esc_html( $content_ideas->get_status_label( $idea->status ) ); ?></span></td>
						<td><?php echo esc_html( wp_trim_words( (string) $idea->topic, 18, '…' ) ); ?></td>
						<td><?php echo '' !== (string) $idea->keyword ? esc_html( (string) $idea->keyword ) : esc_html__( '—', 'wp-ai-publisher' ); ?></td>
						<td><?php echo esc_html( $language_labels[ $idea->language ] ?? (string) $idea->language ); ?></td>
						<td><?php echo esc_html( $level_labels[ $idea->tutorial_level ] ?? __( '—', 'wp-ai-publisher' ) ); ?></td>
						<td><?php echo esc_html( (string) $idea->created_at ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
								<input type="hidden" name="action" value="wpai_publisher_run_content_idea_dry_run" />
								<input type="hidden" name="idea_id" value="<?php echo esc_attr( (string) absint( $idea->id ) ); ?>" />
								<?php wp_nonce_field( 'wpai_publisher_run_content_idea_dry_run' ); ?>
								<?php submit_button( __( 'Esegui dry-run', 'wp-ai-publisher' ), 'secondary small', 'submit', false ); ?>
							</form>
							<?php if ( ! empty( $idea->dry_run_output ) ) : ?>
								<a class="button button-small" href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html__( 'Visualizza risultato', 'wp-ai-publisher' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( $selected_idea && ! empty( $dry_run_data ) ) : ?>
		<?php
		$generation_source = isset( $dry_run_data['source'] ) ? sanitize_key( (string) $dry_run_data['source'] ) : 'unknown';
		if ( ! isset( $source_labels[ $generation_source ] ) ) {
			$generation_source = 'unknown';
		}
		$source_badge_class = 'wordpress_ai' === $generation_source ? 'wpai-badge wpai-badge--ok' : ( 'local_fallback' === $generation_source ? 'wpai-badge wpai-badge--warning' : 'wpai-badge wpai-badge--not-verified' );
		?>
		<section class="wpai-card" style="margin-top:20px;">
			<h2><?php echo esc_html__( 'Risultato dry-run', 'wp-ai-publisher' ); ?> #<?php echo esc_html( (string) $selected_idea->id ); ?></h2>
			<details open>
				<summary><?php echo esc_html__( 'Visualizza struttura articolo proposta', 'wp-ai-publisher' ); ?></summary>

				<h3><?php echo esc_html__( 'Origine generazione', 'wp-ai-publisher' ); ?></h3>
				<p>
					<span class="<?php echo esc_attr( $source_badge_class ); ?>"><?php echo esc_html( $source_badges[ $generation_source ] ); ?></span>
					<?php echo esc_html( $source_labels[ $generation_source ] ); ?>
				</p>
				<?php if ( 'local_fallback' === $generation_source ) : ?>
					<div class="notice notice-warning inline">
						<p><?php echo esc_html__( 'Questo risultato è utile per testare il flusso, ma non è ancora stato prodotto dal sistema AI reale.', 'wp-ai-publisher' ); ?></p>
					</div>
				<?php elseif ( 'wordpress_ai' === $generation_source ) : ?>
					<div class="notice notice-success inline">
						<p><?php echo esc_html__( 'Risultato prodotto tramite sistema AI di WordPress.', 'wp-ai-publisher' ); ?></p>
					</div>
				<?php else : ?>
					<div class="notice notice-error inline">
						<p><?php echo esc_html__( 'Origine del dry-run non disponibile.', 'wp-ai-publisher' ); ?></p>
					</div>
				<?php endif; ?>
				<h3><?php echo esc_html__( 'Titolo proposto', 'wp-ai-publisher' ); ?></h3>
				<p><?php echo esc_html( (string) ( $dry_run_data['title'] ?? '—' ) ); ?></p>

				<h3><?php echo esc_html__( 'Slug', 'wp-ai-publisher' ); ?></h3>
				<p><code><?php echo esc_html( (string) ( $dry_run_data['slug'] ?? '—' ) ); ?></code></p>

				<h3><?php echo esc_html__( 'Estratto', 'wp-ai-publisher' ); ?></h3>
				<p><?php echo esc_html( (string) ( $dry_run_data['excerpt'] ?? '—' ) ); ?></p>

				<h3><?php echo esc_html__( 'Struttura articolo', 'wp-ai-publisher' ); ?></h3>
				<?php if ( ! empty( $dry_run_data['content_outline'] ) && is_array( $dry_run_data['content_outline'] ) ) : ?>
					<ol>
						<?php foreach ( $dry_run_data['content_outline'] as $section ) : ?>
							<li>
								<strong><?php echo esc_html( (string) ( $section['heading'] ?? __( 'Sezione', 'wp-ai-publisher' ) ) ); ?></strong>
								<?php echo isset( $section['level'] ) ? esc_html( 'H' . (string) absint( $section['level'] ) ) : ''; ?>
								<p><?php echo esc_html( (string) ( $section['summary'] ?? '' ) ); ?></p>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php else : ?>
					<p><?php echo esc_html__( '—', 'wp-ai-publisher' ); ?></p>
				<?php endif; ?>

				<h3><?php echo esc_html__( 'Categorie', 'wp-ai-publisher' ); ?></h3>
				<?php $render_list( $dry_run_data['categories'] ?? array() ); ?>

				<h3><?php echo esc_html__( 'Tag', 'wp-ai-publisher' ); ?></h3>
				<?php $render_list( $dry_run_data['tags'] ?? array() ); ?>

				<h3><?php echo esc_html__( 'Meta title', 'wp-ai-publisher' ); ?></h3>
				<p><?php echo esc_html( (string) ( $dry_run_data['meta_title'] ?? '—' ) ); ?></p>

				<h3><?php echo esc_html__( 'Meta description', 'wp-ai-publisher' ); ?></h3>
				<p><?php echo esc_html( (string) ( $dry_run_data['meta_description'] ?? '—' ) ); ?></p>

				<h3><?php echo esc_html__( 'Prompt immagine in evidenza', 'wp-ai-publisher' ); ?></h3>
				<p><?php echo esc_html( (string) ( $dry_run_data['featured_image_prompt'] ?? '—' ) ); ?></p>

				<h3><?php echo esc_html__( 'Link interni previsti', 'wp-ai-publisher' ); ?></h3>
				<?php $render_list( $dry_run_data['internal_link_targets'] ?? array() ); ?>

				<h3><?php echo esc_html__( 'Note di validazione', 'wp-ai-publisher' ); ?></h3>
				<?php $render_list( $notes_data ); ?>

				<h3><?php echo esc_html__( 'JSON grezzo per debug', 'wp-ai-publisher' ); ?></h3>
				<textarea class="large-text code" rows="12" readonly><?php echo esc_textarea( wp_json_encode( $dry_run_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
			</details>
		</section>
	<?php endif; ?>
</div>
