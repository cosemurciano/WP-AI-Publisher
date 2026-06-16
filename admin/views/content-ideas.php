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
$notice_step = sanitize_key( wp_unslash( $_GET['wpai_step'] ?? '' ) );
$notice_error = sanitize_text_field( wp_unslash( $_GET['wpai_error'] ?? '' ) );
$notices    = array(
	'idea_saved'               => array( 'success', __( 'Idea contenuto salvata.', 'wp-ai-publisher' ) ),
	'dry_run_completed'        => array( 'success', __( 'Dry-run completato.', 'wp-ai-publisher' ) ),
	'dry_run_failed'           => array( 'error', __( 'Dry-run fallito. Controlla le note di validazione.', 'wp-ai-publisher' ) ),
	'dry_run_approved'         => array( 'success', __( 'Dry-run approvato. Ora puoi creare la bozza.', 'wp-ai-publisher' ) ),
	'dry_run_rejected'         => array( 'success', __( 'Dry-run rifiutato.', 'wp-ai-publisher' ) ),
	'draft_created'            => array( 'success', __( 'Bozza creata correttamente.', 'wp-ai-publisher' ) ),
	'auto_draft_started'       => array( 'success', __( 'La creazione della bozza è stata messa in coda.', 'wp-ai-publisher' ) ),
	'full_article_generated'   => array( 'success', __( 'Articolo completo generato. Ora puoi approvare il contenuto e creare la bozza.', 'wp-ai-publisher' ) ),
	'full_article_failed'      => array( 'error', __( 'Non è stato possibile generare un articolo completo.', 'wp-ai-publisher' ) ),
	'missing_full_article'     => array( 'warning', __( 'Genera prima l’articolo completo, poi crea la bozza.', 'wp-ai-publisher' ) ),
	'draft_already_exists'     => array( 'warning', __( 'La bozza esiste già.', 'wp-ai-publisher' ) ),
	'draft_creation_failed'    => array( 'error', __( 'L’AI ha generato l’articolo, ma la bozza non è stata creata perché il contenuto non ha superato la validazione di pubblicabilità.', 'wp-ai-publisher' ) ),
	'draft_not_approved'       => array( 'error', __( 'Non puoi creare una bozza da un dry-run non approvato.', 'wp-ai-publisher' ) ),
	'insufficient_permissions' => array( 'error', __( 'Permessi insufficienti.', 'wp-ai-publisher' ) ),
	'idea_not_found'           => array( 'error', __( 'Idea non trovata.', 'wp-ai-publisher' ) ),
	'idea_save_failed'         => array( 'error', __( 'Impossibile salvare l’idea contenuto. Controlla i log del plugin o lo stato del database.', 'wp-ai-publisher' ) ),
	'content_ideas_table_missing' => array( 'error', __( 'La tabella delle idee contenuto non è disponibile. Apri Stato sistema o riattiva il plugin per eseguire la migrazione.', 'wp-ai-publisher' ) ),
	'linked_draft_not_found'    => array( 'warning', __( 'Bozza collegata non trovata.', 'wp-ai-publisher' ) ),
	'missing_article_type'      => array( 'warning', __( 'Assegna una Tipologia articolo prima di generare la bozza.', 'wp-ai-publisher' ) ),
	'article_type_assigned'     => array( 'success', __( 'Tipologia articolo assegnata.', 'wp-ai-publisher' ) ),
	'article_type_assign_failed' => array( 'error', __( 'Impossibile assegnare la tipologia articolo.', 'wp-ai-publisher' ) ),
	'idea_scheduled'            => array( 'success', __( 'Idea programmata: la bozza verrà generata alla data impostata.', 'wp-ai-publisher' ) ),
	'idea_deleted'              => array( 'success', __( 'Idea contenuto eliminata.', 'wp-ai-publisher' ) ),
	'idea_delete_failed'        => array( 'error', __( 'Impossibile eliminare l’idea contenuto.', 'wp-ai-publisher' ) ),
);

$language_labels = array(
	'it' => __( 'Italiano', 'wp-ai-publisher' ),
	'en' => __( 'Inglese', 'wp-ai-publisher' ),
	'fr' => __( 'Francese', 'wp-ai-publisher' ),
	'es' => __( 'Spagnolo', 'wp-ai-publisher' ),
	'de' => __( 'Tedesco', 'wp-ai-publisher' ),
);


$site_context              = wpai_publisher_get_site_context();
$site_context_configured   = wpai_publisher_is_site_context_configured( $site_context );
$settings_context_url      = admin_url( 'admin.php?page=wp-ai-publisher-settings#wpai-site-profile-name' );
$default_audience          = sanitize_text_field( (string) ( $site_context['default_audience'] ?? '' ) );
$settings                  = wpai_publisher_get_settings();
$article_types_enabled     = isset( $article_types_enabled ) ? (bool) $article_types_enabled : wpai_publisher_article_types_enabled();


$source_labels = array(
	'wordpress_ai'   => __( 'AI WordPress', 'wp-ai-publisher' ),
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
	<p class="wpai-lead"><?php echo esc_html__( 'Inserisci un’idea, scegli la Tipologia articolo e crea la bozza: l’AI genera l’articolo e il plugin lo salva come bozza WordPress (mai pubblicato automaticamente).', 'wp-ai-publisher' ); ?></p>

	<?php if ( isset( $notices[ $notice_key ] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notices[ $notice_key ][0] ); ?> is-dismissible">
			<p><?php echo esc_html( $notices[ $notice_key ][1] ); ?><?php if ( 'draft_creation_failed' === $notice_key && ( '' !== $notice_step || '' !== $notice_error ) ) : ?> <?php echo esc_html( sprintf( __( 'Step: %1$s. Dettaglio: %2$s', 'wp-ai-publisher' ), '' !== $notice_step ? $notice_step : __( 'non disponibile', 'wp-ai-publisher' ), '' !== $notice_error ? $notice_error : __( 'non disponibile', 'wp-ai-publisher' ) ) ); ?><?php endif; ?></p>
		</div>
		<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $_GET['wpai_debug'] ) ) : ?>
			<div class="notice notice-warning is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['wpai_debug'] ) ) ); ?></p></div>
		<?php endif; ?>
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
									<option value="<?php echo esc_attr( $language_key ); ?>" <?php selected( $site_context['default_language'], $language_key ); ?>><?php echo esc_html( $language_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<?php if ( $article_types_enabled ) : ?>
						<tr>
							<th scope="row"><label for="wpai-content-article-type"><?php echo esc_html__( 'Tipologia articolo', 'wp-ai-publisher' ); ?></label></th>
							<td>
								<select id="wpai-content-article-type" name="article_type_id" required>
									<option value=""><?php echo esc_html__( 'Seleziona una tipologia', 'wp-ai-publisher' ); ?></option>
									<?php foreach ( $active_article_types as $article_type ) : ?>
										<option value="<?php echo esc_attr( (string) $article_type['id'] ); ?>"><?php echo esc_html( $article_type['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><label for="wpai-content-scheduled-at"><?php echo esc_html__( 'Programma creazione (opzionale)', 'wp-ai-publisher' ); ?></label></th>
						<td>
							<input type="datetime-local" id="wpai-content-scheduled-at" name="wpai_scheduled_at" value="" />
							<p class="description"><?php echo esc_html__( 'Imposta data e ora e usa il pulsante “Programma” per generare la bozza automaticamente in seguito.', 'wp-ai-publisher' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php if ( $article_types_enabled && empty( $active_article_types ) ) : ?><div class="notice notice-warning inline"><p><?php echo esc_html__( 'Crea almeno una Tipologia articolo prima di generare bozze.', 'wp-ai-publisher' ); ?> <a href="<?php echo esc_url( $article_types_url ); ?>"><?php echo esc_html__( 'Crea Tipologia articolo', 'wp-ai-publisher' ); ?></a></p></div><?php endif; ?>
			<p class="submit">
				<button type="submit" name="wpai_creation_mode" value="create_draft" class="button button-primary" <?php disabled( $article_types_enabled && empty( $active_article_types ) ); ?>><?php echo esc_html__( 'Crea bozza', 'wp-ai-publisher' ); ?></button>
				<button type="submit" name="wpai_creation_mode" value="schedule" class="button" <?php disabled( $article_types_enabled && empty( $active_article_types ) ); ?>><?php echo esc_html__( 'Programma', 'wp-ai-publisher' ); ?></button>
				<button type="submit" name="wpai_creation_mode" value="save_only" class="button"><?php echo esc_html__( 'Salva solo idea', 'wp-ai-publisher' ); ?></button>
			</p>
		</form>
	</section>

	<h2><?php echo esc_html__( 'Ultime idee', 'wp-ai-publisher' ); ?></h2>
	<?php if ( empty( $ideas ) ) : ?>
		<div class="notice notice-info inline">
			<p><?php echo esc_html__( 'Nessuna idea contenuto presente. Inserisci un argomento per crearne una.', 'wp-ai-publisher' ); ?></p>
		</div>
	<?php else : ?>
		<?php
		$ideas_total      = isset( $ideas_total ) ? (int) $ideas_total : count( $ideas );
		$ideas_page       = isset( $ideas_page ) ? (int) $ideas_page : 1;
		$ideas_pages      = isset( $ideas_pages ) ? (int) $ideas_pages : 1;
		$ideas_page_base  = remove_query_arg( array( 'view_idea', '_wpnonce', 'wpai_notice', 'wpai_step', 'wpai_error', 'wpai_debug' ) );
		$render_ideas_nav = static function () use ( $ideas_total, $ideas_page, $ideas_pages, $ideas_page_base ) {
			if ( $ideas_pages < 2 ) { return; }
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo '<span class="displaying-num">' . esc_html( sprintf( _n( '%s idea', '%s idee', $ideas_total, 'wp-ai-publisher' ), number_format_i18n( $ideas_total ) ) ) . '</span> ';
			echo wp_kses_post( paginate_links( array(
				'base'      => add_query_arg( 'paged', '%#%', $ideas_page_base ),
				'format'    => '',
				'current'   => $ideas_page,
				'total'     => $ideas_pages,
				'prev_text' => '‹',
				'next_text' => '›',
			) ) );
			echo '</div></div>';
		};
		$render_ideas_nav();
		?>
		<table class="widefat striped wpai-status-table wpai-ideas-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'ID', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Stato', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Argomento', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Keyword', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Lingua', 'wp-ai-publisher' ); ?></th>
					<?php if ( $article_types_enabled ) : ?><th scope="col"><?php echo esc_html__( 'Tipologia articolo', 'wp-ai-publisher' ); ?></th><?php endif; ?>
					<th scope="col"><?php echo esc_html__( 'Data creazione', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Bozza', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Stato bozza', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Azioni', 'wp-ai-publisher' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $ideas as $idea ) : ?>
					<?php
					$idea_id       = absint( $idea->id );
					$view_url      = wp_nonce_url( admin_url( 'admin.php?page=wp-ai-publisher-content-ideas&view_idea=' . $idea_id ), 'wpai_publisher_view_content_idea_' . $idea_id );
					$draft_post_id = absint( $idea->draft_post_id ?? 0 );
					$draft_exists  = $draft_post_id > 0 && get_post( $draft_post_id );
					$draft_edit_url = $draft_exists ? get_edit_post_link( $draft_post_id, '' ) : '';
					$job = ! empty( $idea->job_id ) ? $job_queue->get_job( absint( $idea->job_id ) ) : null;
					$job_status = $job ? sanitize_key( (string) $job->status ) : '';
					$idea_status_display = sanitize_key( (string) $idea->status );
					$idea_status_label = $content_ideas->get_status_label( $idea->status );
					if ( 'processing' === $idea_status_display && 'pending' === $job_status ) {
						$idea_status_label = __( 'In coda', 'wp-ai-publisher' );
					} elseif ( 'processing' === $idea_status_display && 'timeout' === $job_status ) {
						$idea_status_display = 'timeout';
						$idea_status_label = __( 'Scaduto', 'wp-ai-publisher' );
					} elseif ( 'draft_failed' === $idea_status_display ) {
						$idea_status_label = __( 'Errore', 'wp-ai-publisher' );
					}
					?>
					<tr>
						<td><?php echo esc_html( (string) $idea->id ); ?></td>
						<td><span class="<?php echo esc_attr( wpai_publisher_badge_class( $idea_status_display ) ); ?>"><?php echo esc_html( $idea_status_label ); ?></span>
							<?php if ( 'scheduled' === $idea_status_display && ! empty( $idea->scheduled_at ) ) : ?>
								<p class="description"><?php echo esc_html( sprintf( __( 'Per: %s', 'wp-ai-publisher' ), (string) $idea->scheduled_at ) ); ?></p>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( wp_trim_words( (string) $idea->topic, 18, '…' ) ); ?></td>
						<td><?php echo '' !== (string) $idea->keyword ? esc_html( (string) $idea->keyword ) : esc_html__( '—', 'wp-ai-publisher' ); ?></td>
						<td><?php echo esc_html( $language_labels[ $idea->language ] ?? (string) $idea->language ); ?></td>
						<?php if ( $article_types_enabled ) : ?>
						<td>
							<?php $atype_id = absint( $idea->article_type_id ?? 0 ); ?>
							<?php $atype_valid = wpai_publisher_is_active_article_type_safe( $atype_id ); ?>
							<?php if ( $atype_valid ) : ?>
								<?php $atype = wpai_publisher_get_article_type_config_safe( $atype_id ); echo esc_html( $atype['name'] ); ?>
							<?php else : ?>
								<p class="description"><?php echo esc_html( $atype_id > 0 ? __( 'Tipologia non valida/inattiva', 'wp-ai-publisher' ) : __( 'Non assegnata', 'wp-ai-publisher' ) ); ?></p>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="wpai_publisher_assign_article_type_to_idea" />
									<input type="hidden" name="idea_id" value="<?php echo esc_attr( (string) $idea_id ); ?>" />
									<?php wp_nonce_field( 'wpai_publisher_assign_article_type_to_idea_' . $idea_id ); ?>
									<select name="article_type_id" required>
										<option value=""><?php echo esc_html__( 'Tipologia articolo', 'wp-ai-publisher' ); ?></option>
										<?php foreach ( $active_article_types as $article_type ) : ?>
											<option value="<?php echo esc_attr( (string) $article_type['id'] ); ?>"><?php echo esc_html( $article_type['name'] ); ?></option>
										<?php endforeach; ?>
									</select>
									<?php submit_button( __( 'Assegna', 'wp-ai-publisher' ), 'secondary small', 'submit', false, array( 'disabled' => empty( $active_article_types ) ) ); ?>
								</form>
							<?php endif; ?>
						</td>
						<?php endif; ?>
						<td><?php echo esc_html( (string) $idea->created_at ); ?></td>
						<td>
							<?php if ( $draft_exists && $draft_edit_url ) : ?>
								<?php echo esc_html( sprintf( __( 'Bozza #%d', 'wp-ai-publisher' ), $draft_post_id ) ); ?> <a href="<?php echo esc_url( $draft_edit_url ); ?>"><?php echo esc_html__( 'Modifica', 'wp-ai-publisher' ); ?></a>
							<?php else : ?>
								<?php echo esc_html__( 'Non creata', 'wp-ai-publisher' ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( 'draft_failed' === (string) $idea->status ) : ?>
								<?php echo esc_html__( 'errore', 'wp-ai-publisher' ); ?>
							<?php elseif ( ! empty( $idea->draft_status ) ) : ?>
								<code><?php echo esc_html( (string) $idea->draft_status ); ?></code>
							<?php else : ?>
								<?php echo esc_html__( '—', 'wp-ai-publisher' ); ?>
							<?php endif; ?>
							<?php if ( $job ) : ?>
								<p class="description"><?php echo esc_html( sprintf( __( 'Ultimo aggiornamento: %s', 'wp-ai-publisher' ), (string) ( $job->finished_at ?: $job->started_at ?: $idea->updated_at ?: $idea->created_at ) ) ); ?></p>
								<p class="description"><?php echo esc_html( sprintf( __( 'Step/job: %1$s (%2$s)', 'wp-ai-publisher' ), $job_queue->get_job_type_label( $job->job_type ), $job_queue->get_status_label( $job->status ) ) ); ?></p>
								<?php if ( ! empty( $job->error_message ) ) : ?><p class="description"><?php echo esc_html( sprintf( __( 'Ultimo errore: %s', 'wp-ai-publisher' ), (string) $job->error_message ) ); ?></p><?php endif; ?>
							<?php elseif ( ! empty( $idea->draft_error ) ) : ?>
								<p class="description"><?php echo esc_html( sprintf( __( 'Ultimo errore: %s', 'wp-ai-publisher' ), (string) $idea->draft_error ) ); ?></p>
							<?php endif; ?>
						</td>
						<td>
							<?php
							$idea_output = ! empty( $idea->dry_run_output ) ? json_decode( (string) $idea->dry_run_output, true ) : array();
							$has_full_article = is_array( $idea_output ) && ! empty( $idea_output['full_article']['html'] );
							$status = sanitize_key( (string) $idea->status );
							?>
							<?php if ( $draft_exists && $draft_edit_url ) : ?>
								<a class="button button-primary button-small" href="<?php echo esc_url( $draft_edit_url ); ?>"><?php echo esc_html__( 'Modifica bozza', 'wp-ai-publisher' ); ?></a>
							<?php elseif ( in_array( $status, array( 'new', 'scheduled', 'dry_run_failed', 'draft_failed', 'timeout' ), true ) && ( ! $article_types_enabled || wpai_publisher_is_active_article_type_safe( absint( $idea->article_type_id ?? 0 ) ) ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<input type="hidden" name="action" value="wpai_publisher_create_draft_from_idea" />
									<input type="hidden" name="idea_id" value="<?php echo esc_attr( (string) $idea_id ); ?>" />
									<?php wp_nonce_field( 'wpai_publisher_create_draft_from_idea_' . $idea_id ); ?>
									<?php submit_button( in_array( $status, array( 'draft_failed', 'timeout' ), true ) ? __( 'Riprova', 'wp-ai-publisher' ) : __( 'Genera bozza', 'wp-ai-publisher' ), 'primary small', 'submit', false ); ?>
								</form>
							<?php elseif ( $article_types_enabled && in_array( $status, array( 'new', 'scheduled', 'dry_run_failed', 'draft_failed', 'timeout' ), true ) ) : ?><span class="description"><?php echo esc_html__( 'Assegna prima una Tipologia articolo.', 'wp-ai-publisher' ); ?></span>
							<?php elseif ( $has_full_article ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<input type="hidden" name="action" value="wpai_publisher_create_draft_from_idea" />
									<input type="hidden" name="idea_id" value="<?php echo esc_attr( (string) $idea_id ); ?>" />
									<?php wp_nonce_field( 'wpai_publisher_create_draft_from_idea_' . $idea_id ); ?>
									<?php submit_button( __( 'Crea bozza', 'wp-ai-publisher' ), 'primary small', 'submit', false ); ?>
								</form>
							<?php elseif ( 'processing' === $status ) : ?>
								<span class="description"><?php echo esc_html( $idea_status_label ); ?></span>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<input type="hidden" name="action" value="wpai_publisher_process_idea_job_now" />
									<input type="hidden" name="idea_id" value="<?php echo esc_attr( (string) $idea_id ); ?>" />
									<?php wp_nonce_field( 'wpai_publisher_process_idea_job_now_' . $idea_id ); ?>
									<?php submit_button( __( 'Processa job ora', 'wp-ai-publisher' ), 'secondary small', 'submit', false ); ?>
								</form>
							<?php endif; ?>
							<div class="row-actions wpai-secondary-actions">
								<?php if ( ! empty( $idea->dry_run_output ) ) : ?><a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html__( 'Visualizza risultato', 'wp-ai-publisher' ); ?></a><?php endif; ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Eliminare questa idea? La bozza eventualmente collegata non verrà eliminata.', 'wp-ai-publisher' ) ); ?>');">
									<input type="hidden" name="action" value="wpai_publisher_delete_content_idea" />
									<input type="hidden" name="idea_id" value="<?php echo esc_attr( (string) $idea_id ); ?>" />
									<?php wp_nonce_field( 'wpai_publisher_delete_content_idea_' . $idea_id ); ?>
									<button class="button-link delete" type="submit"><?php echo esc_html__( 'Elimina', 'wp-ai-publisher' ); ?></button>
								</form>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php $render_ideas_nav(); ?>
	<?php endif; ?>

	<?php if ( $selected_idea && ! empty( $dry_run_data ) ) : ?>
		<?php
		$generation_source = isset( $dry_run_data['source'] ) ? sanitize_key( (string) $dry_run_data['source'] ) : 'unknown';
		if ( ! isset( $source_labels[ $generation_source ] ) ) {
			$generation_source = 'unknown';
		}
		$source_badge_class = 'wordpress_ai' === $generation_source ? 'wpai-badge wpai-badge--ok' : ( 'local_fallback' === $generation_source ? 'wpai-badge wpai-badge--warning' : 'wpai-badge wpai-badge--not-verified' );
		$classic_preview = isset( $dry_run_data['classic_editor_preview'] ) && is_array( $dry_run_data['classic_editor_preview'] ) ? $dry_run_data['classic_editor_preview'] : array();
		$classic_html    = isset( $classic_preview['html'] ) ? (string) $classic_preview['html'] : '';
		$classic_notes   = isset( $classic_preview['validation_notes'] ) && is_array( $classic_preview['validation_notes'] ) ? $classic_preview['validation_notes'] : array();
		$full_article   = isset( $dry_run_data['full_article'] ) && is_array( $dry_run_data['full_article'] ) ? $dry_run_data['full_article'] : array();
		$full_html      = isset( $full_article['html'] ) ? (string) $full_article['html'] : '';
		$full_source    = sanitize_key( (string) ( $full_article['source'] ?? 'unknown' ) );
		$full_notes     = isset( $full_article['validation_notes'] ) && is_array( $full_article['validation_notes'] ) ? $full_article['validation_notes'] : array();
		$candidate_builder = new \WPAIPublisher\Classic_Content_Builder( null, isset( $dry_run_data['article_type'] ) && is_array( $dry_run_data['article_type'] ) ? $dry_run_data['article_type'] : array() );
		$normalized_full_html = '' !== trim( $full_html ) ? $candidate_builder->normalize_full_article_html( $full_html, $dry_run_data ) : '';
		$debug_candidate_html = '' !== trim( $normalized_full_html ) ? $normalized_full_html : $classic_html;
		$full_validation = '' !== trim( $normalized_full_html ) ? $candidate_builder->validate_publishable_article_html( $normalized_full_html ) : array( 'valid' => false, 'notes' => array() );
		$full_diagnostics = $candidate_builder->get_full_article_validation_diagnostics( $full_html, $normalized_full_html, $dry_run_data, $full_validation );
		$full_valid     = ! empty( $full_validation['valid'] );
		$full_status_message = $full_valid ? __( 'Articolo completo pronto per la bozza.', 'wp-ai-publisher' ) : ( '' !== trim( $full_html ) ? __( 'Articolo completo presente ma da normalizzare.', 'wp-ai-publisher' ) : __( 'Articolo completo non ancora generato.', 'wp-ai-publisher' ) );
		$all_notes       = array_values( array_unique( array_filter( array_merge( (array) $notes_data, $classic_notes ) ) ) );
		$review_notes    = array();
		$blocking_notes  = array();
		foreach ( $all_notes as $note ) {
			$note_text = strtolower( remove_accents( (string) $note ) );
			if ( false !== strpos( $note_text, 'nota grave' ) || false !== strpos( $note_text, 'campo critico' ) || false !== strpos( $note_text, 'gravemente' ) || false !== strpos( $note_text, 'gutenberg' ) || false !== strpos( $note_text, 'script non consentiti' ) || false !== strpos( $note_text, 'iframe non consentiti' ) || false !== strpos( $note_text, 'vuoto' ) || false !== strpos( $note_text, 'mancante' ) ) {
				$blocking_notes[] = (string) $note;
			} elseif ( false !== strpos( $note_text, 'nota lieve' ) || false !== strpos( $note_text, 'da revisionare' ) ) {
				$review_notes[] = (string) $note;
			}
		}
		$review_notes   = array_values( array_unique( $review_notes ) );
		$blocking_notes = array_values( array_unique( $blocking_notes ) );
		$is_ready       = empty( $blocking_notes );
		$quality_label  = __( 'Da revisionare', 'wp-ai-publisher' );
		$quality_class  = 'wpai-badge wpai-badge--warning';

		if ( 'local_fallback' === $generation_source ) {
			$quality_label = __( 'Simulazione locale', 'wp-ai-publisher' );
			$quality_class = 'wpai-badge wpai-badge--warning';
		} elseif ( 'wordpress_ai' === $generation_source && empty( $review_notes ) ) {
			$quality_label = __( 'Output AI reale', 'wp-ai-publisher' );
			$quality_class = 'wpai-badge wpai-badge--ok';
		}
		?>
		<section class="wpai-card" style="margin-top:20px;">
			<h2><?php echo esc_html__( 'Risultato generazione', 'wp-ai-publisher' ); ?> #<?php echo esc_html( (string) $selected_idea->id ); ?></h2>
			<?php $selected_draft_id = absint( $selected_idea->draft_post_id ?? 0 ); ?>
			<?php if ( $selected_draft_id > 0 && get_post( $selected_draft_id ) ) : ?>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( get_edit_post_link( $selected_draft_id, '' ) ); ?>"><?php echo esc_html__( 'Modifica bozza', 'wp-ai-publisher' ); ?></a>
					<?php if ( function_exists( 'get_preview_post_link' ) ) : ?>
						<a class="button" href="<?php echo esc_url( get_preview_post_link( $selected_draft_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Visualizza anteprima', 'wp-ai-publisher' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<details open>
				<summary><?php echo esc_html__( 'Visualizza struttura articolo proposta', 'wp-ai-publisher' ); ?></summary>

				<h3><?php echo esc_html__( 'Stato dry-run', 'wp-ai-publisher' ); ?></h3>
				<ul>
					<li><strong><?php echo esc_html__( 'Origine:', 'wp-ai-publisher' ); ?></strong> <span class="<?php echo esc_attr( $source_badge_class ); ?>"><?php echo esc_html( $source_badges[ $generation_source ] ); ?></span> <?php echo esc_html( $source_labels[ $generation_source ] ); ?></li>
					<li><strong><?php echo esc_html__( 'Compatibilità:', 'wp-ai-publisher' ); ?></strong> <span class="wpai-badge wpai-badge--ok"><?php echo esc_html__( 'Editor Classico', 'wp-ai-publisher' ); ?></span></li>
					<li><strong><?php echo esc_html__( 'Qualità anteprima:', 'wp-ai-publisher' ); ?></strong> <span class="<?php echo esc_attr( $quality_class ); ?>"><?php echo esc_html( $quality_label ); ?></span></li>
					<li><strong><?php echo esc_html__( 'Stato:', 'wp-ai-publisher' ); ?></strong> <span class="<?php echo esc_attr( $is_ready ? 'wpai-badge wpai-badge--ok' : 'wpai-badge wpai-badge--error' ); ?>"><?php echo esc_html( $is_ready ? __( 'PRONTO', 'wp-ai-publisher' ) : __( 'BLOCCATO', 'wp-ai-publisher' ) ); ?></span></li>
				</ul>
				<?php if ( 'local_fallback' === $generation_source ) : ?>
					<div class="notice notice-warning inline">
						<p><?php echo esc_html__( 'Il fallback locale serve solo a testare il flusso. Prima di creare una bozza reale è consigliata una revisione editoriale o una generazione AI effettiva.', 'wp-ai-publisher' ); ?></p>
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

				<h3><?php echo esc_html__( 'Anteprima contenuto per Editor Classico', 'wp-ai-publisher' ); ?></h3>
				<p>
					<span class="wpai-badge wpai-badge--ok"><?php echo esc_html__( 'Compatibile con Editor Classico', 'wp-ai-publisher' ); ?></span>
				</p>
				<p><?php echo esc_html__( 'Questa anteprima usa HTML pulito compatibile con l’Editor Classico di WordPress. Non sono presenti blocchi Gutenberg.', 'wp-ai-publisher' ); ?></p>
				<?php if ( 'local_fallback' === $generation_source ) : ?>
					<div class="notice notice-warning inline">
						<p><?php echo esc_html__( 'Il fallback locale serve solo a testare il flusso. Prima di creare una bozza reale è consigliata una revisione editoriale o una generazione AI effettiva.', 'wp-ai-publisher' ); ?></p>
					</div>
				<?php endif; ?>
				<div class="wpai-classic-preview">
					<?php echo wp_kses_post( $classic_html ); ?>
				</div>
				<h4><?php echo esc_html__( 'HTML generato per debug o copia manuale', 'wp-ai-publisher' ); ?></h4>
				<textarea class="large-text code" rows="12" readonly><?php echo esc_textarea( $debug_candidate_html ); ?></textarea>
				<?php if ( ! empty( $classic_notes ) ) : ?>
					<h4><?php echo esc_html__( 'Diagnostica compatibilità Classic Editor', 'wp-ai-publisher' ); ?></h4>
					<?php $render_list( $classic_notes ); ?>
				<?php endif; ?>


				<h3><?php echo esc_html__( 'Articolo completo per bozza', 'wp-ai-publisher' ); ?></h3>
				<ul>
					<li><strong><?php echo esc_html__( 'Stato:', 'wp-ai-publisher' ); ?></strong> <span class="<?php echo esc_attr( $full_valid ? 'wpai-badge wpai-badge--ok' : ( '' !== $full_html ? 'wpai-badge wpai-badge--warning' : 'wpai-badge wpai-badge--not-verified' ) ); ?>"><?php echo esc_html( $full_valid ? __( 'generato', 'wp-ai-publisher' ) : ( '' !== $full_html ? __( 'da revisionare', 'wp-ai-publisher' ) : __( 'non generato', 'wp-ai-publisher' ) ) ); ?></span></li>
					<li><strong><?php echo esc_html__( 'Fonte:', 'wp-ai-publisher' ); ?></strong> <?php echo esc_html( $source_labels[ $full_source ] ?? __( 'Non disponibile', 'wp-ai-publisher' ) ); ?></li>
					<li><strong><?php echo esc_html__( 'Qualità:', 'wp-ai-publisher' ); ?></strong> <?php echo esc_html( $full_status_message ); ?></li>
				</ul>
				<?php if ( '' !== $full_html ) : ?>
					<div class="wpai-classic-preview"><?php echo wp_kses_post( $full_html ); ?></div>
					<h4><?php echo esc_html__( 'HTML articolo completo', 'wp-ai-publisher' ); ?></h4>
					<textarea class="large-text code" rows="12" readonly><?php echo esc_textarea( $full_html ); ?></textarea>
					<?php if ( ! empty( $full_notes ) ) : ?>
						<div class="notice notice-warning inline"><?php $render_list( $full_notes ); ?></div>
					<?php endif; ?>
				<?php else : ?>
					<div class="notice notice-info inline"><p><?php echo esc_html__( 'Genera l’articolo completo prima di approvare e creare la bozza. La bozza userà full_article.html, non l’anteprima strutturale.', 'wp-ai-publisher' ); ?></p></div>
				<?php endif; ?>

				<h3><?php echo esc_html__( 'Note di revisione', 'wp-ai-publisher' ); ?></h3>
				<?php if ( empty( $review_notes ) ) : ?>
					<div class="notice notice-success inline"><p><?php echo esc_html__( 'Nessuna nota lieve di revisione rilevata.', 'wp-ai-publisher' ); ?></p></div>
				<?php else : ?>
					<div class="notice notice-warning inline"><?php $render_list( $review_notes ); ?></div>
				<?php endif; ?>

				<h3><?php echo esc_html__( 'Problemi bloccanti', 'wp-ai-publisher' ); ?></h3>
				<?php if ( empty( $blocking_notes ) ) : ?>
					<div class="notice notice-success inline"><p><?php echo esc_html__( 'Nessun problema bloccante: dry-run PRONTO.', 'wp-ai-publisher' ); ?></p></div>
				<?php else : ?>
					<div class="notice notice-error inline"><?php $render_list( $blocking_notes ); ?></div>
				<?php endif; ?>

			</details>
		</section>
	<?php endif; ?>
</div>
