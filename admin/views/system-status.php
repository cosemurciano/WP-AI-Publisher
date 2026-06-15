<?php
/**
 * System status view.
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
}

$status_labels = array(
	'ok'      => __( 'OK', 'wp-ai-publisher' ),
	'warning' => __( 'Avviso', 'wp-ai-publisher' ),
	'error'   => __( 'Errore', 'wp-ai-publisher' ),
	'info'    => __( 'Info', 'wp-ai-publisher' ),
	'unknown' => __( 'Sconosciuto', 'wp-ai-publisher' ),
);
?>
<div class="wrap wpai-admin">
	<h1><?php echo esc_html__( 'Stato sistema WP AI Publisher', 'wp-ai-publisher' ); ?></h1>
	<p class="wpai-lead"><?php echo esc_html__( 'This page checks the technical status of WP AI Publisher and related integrations.', 'wp-ai-publisher' ); ?></p>
	<p><?php echo esc_html__( 'Diagnostica di sola lettura: non genera contenuti, non chiama OpenAI e non modifica database o impostazioni.', 'wp-ai-publisher' ); ?></p>

	<table class="widefat striped wpai-status-table">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Controllo', 'wp-ai-publisher' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Stato', 'wp-ai-publisher' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Valore', 'wp-ai-publisher' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Suggerimento', 'wp-ai-publisher' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $checks as $check ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $check['label'] ); ?></th>
					<td><span class="<?php echo esc_attr( wpai_publisher_badge_class( $check['status'] ) ); ?>"><?php echo esc_html( $status_labels[ $check['status'] ] ?? $check['status'] ); ?></span></td>
					<td><?php echo esc_html( $check['value'] ); ?></td>
					<td><?php echo '' !== (string) ( $check['suggestion'] ?? '' ) ? esc_html( $check['suggestion'] ) : '&mdash;'; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php echo esc_html__( 'Dettaglio log critici interni', 'wp-ai-publisher' ); ?></h2>
	<?php if ( empty( $critical_logs ) ) : ?>
		<p><span class="wpai-badge wpai-badge--ok"><?php echo esc_html__( 'OK', 'wp-ai-publisher' ); ?></span> <?php echo esc_html__( 'Nessun errore critico recente nel log interno del plugin.', 'wp-ai-publisher' ); ?></p>
	<?php else : ?>
		<table class="widefat striped wpai-status-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Data', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Livello', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Origine', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Messaggio', 'wp-ai-publisher' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $critical_logs as $log ) : ?>
					<tr>
						<td><?php echo esc_html( $log->created_at ); ?></td>
						<td><?php echo esc_html( $log->level ); ?></td>
						<td><?php echo esc_html( $log->source ); ?></td>
						<td><?php echo esc_html( $log->message ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
