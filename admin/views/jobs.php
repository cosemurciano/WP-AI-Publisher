<?php
/**
 * Jobs queue view.
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpai-admin">
	<h1><?php echo esc_html__( 'Coda job', 'wp-ai-publisher' ); ?></h1>
	<p class="wpai-lead"><?php echo esc_html__( 'Fondazione della coda operativa di WP AI Publisher. In questa fase i job possono essere registrati e diagnosticati; il tipo generate_draft_from_idea prepara il workflow idea → dry-run → articolo completo → bozza.', 'wp-ai-publisher' ); ?></p>

	<div class="wpai-card-grid">
		<section class="wpai-card">
			<h2><?php echo esc_html__( 'In attesa', 'wp-ai-publisher' ); ?></h2>
			<p class="wpai-metric"><?php echo esc_html( number_format_i18n( (int) ( $status_counts['pending'] ?? 0 ) ) ); ?></p>
		</section>
		<section class="wpai-card">
			<h2><?php echo esc_html__( 'In corso', 'wp-ai-publisher' ); ?></h2>
			<p class="wpai-metric"><?php echo esc_html( number_format_i18n( (int) ( $status_counts['running'] ?? 0 ) ) ); ?></p>
		</section>
		<section class="wpai-card">
			<h2><?php echo esc_html__( 'Completati', 'wp-ai-publisher' ); ?></h2>
			<p class="wpai-metric"><?php echo esc_html( number_format_i18n( (int) ( $status_counts['completed'] ?? 0 ) ) ); ?></p>
		</section>
		<section class="wpai-card">
			<h2><?php echo esc_html__( 'Falliti', 'wp-ai-publisher' ); ?></h2>
			<p class="wpai-metric"><?php echo esc_html( number_format_i18n( (int) ( $status_counts['failed'] ?? 0 ) ) ); ?></p>
		</section>
	</div>

	<h2><?php echo esc_html__( 'Ultimi 20 job', 'wp-ai-publisher' ); ?></h2>
	<?php if ( empty( $jobs ) ) : ?>
		<div class="notice notice-info inline">
			<p><?php echo esc_html__( 'Nessun job presente. La coda sarà popolata nelle prossime fasi quando verranno abilitate generazione contenuti, SEO, immagini e indicizzazione.', 'wp-ai-publisher' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped wpai-status-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'ID', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Tipo', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Stato', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Priorità', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Tentativi', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Post collegato', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Costo stimato', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Data creazione', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Payload', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Errore', 'wp-ai-publisher' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $jobs as $job ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $job->id ); ?></td>
						<td><?php echo esc_html( $job_queue->get_job_type_label( $job->job_type ) ); ?></td>
						<td><span class="<?php echo esc_attr( wpai_publisher_badge_class( $job->status ) ); ?>"><?php echo esc_html( $job_queue->get_status_label( $job->status ) ); ?></span></td>
						<td><?php echo esc_html( (string) $job->priority ); ?></td>
						<td><?php echo esc_html( sprintf( '%d/%d', (int) $job->attempts, (int) $job->max_attempts ) ); ?></td>
						<td>
							<?php if ( ! empty( $job->post_id ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( (int) $job->post_id ) ); ?>"><?php echo esc_html( (string) $job->post_id ); ?></a>
							<?php else : ?>
								<?php echo esc_html__( '—', 'wp-ai-publisher' ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php echo null !== $job->estimated_cost ? esc_html( number_format_i18n( (float) $job->estimated_cost, 6 ) ) : esc_html__( '—', 'wp-ai-publisher' ); ?>
						</td>
						<td><?php echo esc_html( $job->created_at ); ?></td>
						<td><code><?php echo esc_html( $job->payload ? wp_trim_words( (string) $job->payload, 12, '…' ) : __( '—', 'wp-ai-publisher' ) ); ?></code></td>
						<td><?php echo esc_html( $job->error_message ? $job->error_message : __( '—', 'wp-ai-publisher' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
