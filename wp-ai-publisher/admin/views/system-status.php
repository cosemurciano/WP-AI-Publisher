<?php
/**
 * System status view.
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpai-admin">
	<h1><?php echo esc_html__( 'WP AI Publisher System Status', 'wp-ai-publisher' ); ?></h1>
	<p class="wpai-lead"><?php echo esc_html__( 'Read-only diagnostics for the phase 1 plugin foundation. No remote AI verification calls are performed.', 'wp-ai-publisher' ); ?></p>

	<table class="widefat striped wpai-status-table">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Check', 'wp-ai-publisher' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Status', 'wp-ai-publisher' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Details', 'wp-ai-publisher' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $checks as $check ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $check['label'] ); ?></th>
					<td><span class="<?php echo esc_attr( wpai_publisher_badge_class( $check['status'] ) ); ?>"><?php echo esc_html( ucwords( str_replace( '-', ' ', $check['status'] ) ) ); ?></span></td>
					<td><?php echo esc_html( $check['value'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php echo esc_html__( 'Last critical errors', 'wp-ai-publisher' ); ?></h2>
	<?php if ( empty( $critical_logs ) ) : ?>
		<p><span class="wpai-badge wpai-badge--ok"><?php echo esc_html__( 'OK', 'wp-ai-publisher' ); ?></span> <?php echo esc_html__( 'No emergency or error logs found.', 'wp-ai-publisher' ); ?></p>
	<?php else : ?>
		<table class="widefat striped wpai-status-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Date', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Level', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Source', 'wp-ai-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Message', 'wp-ai-publisher' ); ?></th>
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
