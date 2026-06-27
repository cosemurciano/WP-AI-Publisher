<?php
/**
 * System status + AI diagnostics view (combined, tabbed).
 *
 * Expects in scope:
 *  - $checks, $critical_logs (system status)
 *  - $report, $test_result, $connectivity_result (AI diagnostics)
 *  - $wpai_diag_initial (string '' | 'diagnostica') initial tab hint
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( wpai_publisher_capability() ) ) {
	wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
}

$wpai_diag_initial = isset( $wpai_diag_initial ) ? (string) $wpai_diag_initial : '';

$status_labels = array(
	'ok'      => __( 'OK', 'wp-ai-publisher' ),
	'warning' => __( 'Avviso', 'wp-ai-publisher' ),
	'error'   => __( 'Errore', 'wp-ai-publisher' ),
	'info'    => __( 'Info', 'wp-ai-publisher' ),
	'unknown' => __( 'Sconosciuto', 'wp-ai-publisher' ),
);
?>
<div class="wrap wpai-admin">
	<h1><?php echo esc_html__( 'Stato sistema e diagnostica AI', 'wp-ai-publisher' ); ?></h1>
	<p class="wpai-lead"><?php echo esc_html__( 'Diagnostica di sola lettura: controlla lo stato tecnico di WP AI Publisher e delle integrazioni AI. Non genera contenuti né modifica database o impostazioni (salvo i test manuali che avvii dalla scheda Diagnostica AI).', 'wp-ai-publisher' ); ?></p>

	<div class="wpai-tabs" data-wpai-store="system-status" data-wpai-initial="<?php echo esc_attr( $wpai_diag_initial ); ?>">
		<h2 class="nav-tab-wrapper wpai-nav">
			<a href="#wpai-stab-stato" class="nav-tab nav-tab-active" data-wpai-tab="stato"><?php echo esc_html__( 'Stato sistema', 'wp-ai-publisher' ); ?></a>
			<a href="#wpai-stab-diagnostica" class="nav-tab" data-wpai-tab="diagnostica"><?php echo esc_html__( 'Diagnostica AI', 'wp-ai-publisher' ); ?></a>
		</h2>

		<?php /* ============================ TAB: STATO SISTEMA ============================ */ ?>
		<div class="wpai-tab-panel is-active" id="wpai-stab-stato" data-wpai-panel="stato">
			<div class="wpai-section">
				<h2><?php echo esc_html__( 'Controlli di sistema', 'wp-ai-publisher' ); ?></h2>
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
			</div>

			<div class="wpai-section">
				<h2><?php echo esc_html__( 'Dettaglio log critici interni', 'wp-ai-publisher' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'Mostra gli ultimi errori interni (incluse le diagnosi di generazione AI). La colonna Dettaglio riporta canale usato, integrazioni AI rilevate ed esito per canale, utile per capire dove si interrompe la creazione bozza.', 'wp-ai-publisher' ); ?></p>
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
								<th scope="col"><?php echo esc_html__( 'Dettaglio', 'wp-ai-publisher' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $critical_logs as $log ) :
								$context = isset( $log->context ) ? json_decode( (string) $log->context, true ) : array();
								$context = is_array( $context ) ? $context : array();
								$detail_lines = array();
								$flatten = static function ( $value ) {
									if ( is_array( $value ) ) {
										$parts = array();
										foreach ( $value as $k => $v ) {
											$parts[] = ( is_string( $k ) ? $k . '=' : '' ) . ( is_scalar( $v ) ? (string) $v : wp_json_encode( $v ) );
										}
										return implode( '; ', $parts );
									}
									return is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
								};
								foreach ( array(
									'step'                => __( 'Step', 'wp-ai-publisher' ),
									'error_code'          => __( 'Codice', 'wp-ai-publisher' ),
									'channel'             => __( 'Canale', 'wp-ai-publisher' ),
									'ai_available'        => __( 'AI rilevata', 'wp-ai-publisher' ),
									'present_classes'     => __( 'Classi AI', 'wp-ai-publisher' ),
									'present_functions'   => __( 'Funzioni AI', 'wp-ai-publisher' ),
									'channel_attempts'    => __( 'Esiti canali', 'wp-ai-publisher' ),
									'abilities_detail'    => __( 'Ability esaminate', 'wp-ai-publisher' ),
								) as $key => $label ) {
									if ( array_key_exists( $key, $context ) ) {
										$rendered = $flatten( $context[ $key ] );
										if ( '' !== trim( (string) $rendered ) ) {
											$detail_lines[] = $label . ': ' . $rendered;
										}
									}
								}
								?>
								<tr>
									<td><?php echo esc_html( $log->created_at ); ?></td>
									<td><?php echo esc_html( $log->level ); ?></td>
									<td><?php echo esc_html( $log->source ); ?></td>
									<td><?php echo esc_html( $log->message ); ?></td>
									<td><?php echo empty( $detail_lines ) ? '&mdash;' : nl2br( esc_html( implode( "\n", $detail_lines ) ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<?php /* ============================ TAB: DIAGNOSTICA AI ============================ */ ?>
		<div class="wpai-tab-panel" id="wpai-stab-diagnostica" data-wpai-panel="diagnostica">
			<?php include WPAIP_PLUGIN_DIR . 'admin/views/ai-diagnostics.php'; ?>
		</div>
	</div>
</div>
