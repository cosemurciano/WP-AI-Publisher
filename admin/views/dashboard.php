<?php
/**
 * Dashboard view.
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpai-admin">
	<h1><?php echo esc_html__( 'WP AI Publisher', 'wp-ai-publisher' ); ?></h1>
	<p class="wpai-lead"><?php echo esc_html__( 'Base modulare per la futura pubblicazione assistita da AI dentro WordPress.', 'wp-ai-publisher' ); ?></p>

	<div class="notice notice-info inline">
		<p><?php echo esc_html__( 'Questa fase prepara l’infrastruttura. La generazione articoli, le immagini, la SEO, la coda job e la pubblicazione assistita saranno implementate nelle fasi successive.', 'wp-ai-publisher' ); ?></p>
	</div>

	<div class="wpai-card-grid">
		<section class="wpai-card">
			<h2><?php echo esc_html__( 'Sistema pronto', 'wp-ai-publisher' ); ?></h2>
			<p><span class="wpai-badge wpai-badge--ok"><?php echo esc_html__( 'OK', 'wp-ai-publisher' ); ?></span></p>
			<p><?php echo esc_html__( 'I servizi principali del plugin sono caricati e pronti per la prossima fase di sviluppo.', 'wp-ai-publisher' ); ?></p>
			<ul>
				<li><?php echo esc_html__( 'Versione plugin:', 'wp-ai-publisher' ); ?> <strong><?php echo esc_html( WPAIP_VERSION ); ?></strong></li>
				<li><?php echo esc_html__( 'PHP minimo:', 'wp-ai-publisher' ); ?> <strong><?php echo esc_html( '8.1' ); ?></strong></li>
				<li><?php echo esc_html__( 'WordPress minimo:', 'wp-ai-publisher' ); ?> <strong><?php echo esc_html( '7.0' ); ?></strong></li>
			</ul>
		</section>

		<section class="wpai-card">
			<h2><?php echo esc_html__( 'Connessione AI WordPress', 'wp-ai-publisher' ); ?></h2>
			<?php if ( ! empty( $ai_status['wordpress_ai_client_available'] ) ) : ?>
				<p><span class="wpai-badge wpai-badge--ok"><?php echo esc_html__( 'Rilevata', 'wp-ai-publisher' ); ?></span></p>
			<?php else : ?>
				<p><span class="wpai-badge wpai-badge--not-verified"><?php echo esc_html__( 'Non rilevata', 'wp-ai-publisher' ); ?></span></p>
			<?php endif; ?>
			<p><?php echo esc_html__( 'Il plugin userà esclusivamente il sistema AI di WordPress già configurato sul sito.', 'wp-ai-publisher' ); ?></p>
			<p><?php echo esc_html__( 'Modelli rilevati:', 'wp-ai-publisher' ); ?> <strong><?php echo esc_html( (string) $ai_status['available_text_models_count'] ); ?></strong></p>
			<p><?php echo esc_html__( 'Modello selezionato:', 'wp-ai-publisher' ); ?> <strong><?php echo esc_html( $ai_status['selected_text_model'] ? $ai_status['selected_text_model'] : __( 'Predefinito WordPress AI', 'wp-ai-publisher' ) ); ?></strong></p>
		</section>

		<section class="wpai-card">
			<h2><?php echo esc_html__( 'Database', 'wp-ai-publisher' ); ?></h2>
			<?php if ( ! empty( $db_status['logs'] ) ) : ?>
				<p><span class="wpai-badge wpai-badge--ok"><?php echo esc_html__( 'OK', 'wp-ai-publisher' ); ?></span></p>
				<p><?php echo esc_html__( 'La tabella log di base è disponibile.', 'wp-ai-publisher' ); ?></p>
			<?php else : ?>
				<p><span class="wpai-badge wpai-badge--error"><?php echo esc_html__( 'Errore', 'wp-ai-publisher' ); ?></span></p>
				<p><?php echo esc_html__( 'La tabella log di base non è presente. Riattiva il plugin o verifica i permessi del database.', 'wp-ai-publisher' ); ?></p>
			<?php endif; ?>
		</section>


		<section class="wpai-card wpai-card--wide">
			<h2><?php echo esc_html__( 'Plugin terzi e integrazioni', 'wp-ai-publisher' ); ?></h2>
			<p><?php echo esc_html__( 'WP AI Publisher controlla questi plugin in modo difensivo. Se una integrazione manca, il plugin resta funzionante ma alcune funzioni future potrebbero non essere disponibili.', 'wp-ai-publisher' ); ?></p>
			<p><?php echo esc_html__( 'La chiave REST di Git Remote Updater deve essere gestita nel pannello Git Updater / Git Remote Updater. WP AI Publisher non salva né replica questa chiave.', 'wp-ai-publisher' ); ?></p>
			<?php if ( empty( $third_party_plugins ) ) : ?>
				<p><span class="wpai-badge wpai-badge--not-verified"><?php echo esc_html__( 'Non verificato', 'wp-ai-publisher' ); ?></span> <?php echo esc_html__( 'La diagnostica delle integrazioni non è disponibile in questo momento.', 'wp-ai-publisher' ); ?></p>
			<?php else : ?>
				<?php
				$third_party_status_labels = array(
					'active'       => __( 'Attivo', 'wp-ai-publisher' ),
					'inactive'     => __( 'Non attivo', 'wp-ai-publisher' ),
					'missing'      => __( 'Non installato', 'wp-ai-publisher' ),
					'detected'     => __( 'Rilevato', 'wp-ai-publisher' ),
					'not_detected' => __( 'Non rilevato', 'wp-ai-publisher' ),
				);
				$third_party_badge_status = array(
					'active'       => 'ok',
					'inactive'     => 'warning',
					'missing'      => 'not-configured',
					'detected'     => 'ok',
					'not_detected' => 'not-verified',
				);
				?>
				<table class="widefat striped wpai-status-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Nome plugin / integrazione', 'wp-ai-publisher' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Stato', 'wp-ai-publisher' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Tipo', 'wp-ai-publisher' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Utilità', 'wp-ai-publisher' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Azione', 'wp-ai-publisher' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $third_party_plugins as $plugin ) : ?>
							<?php
							$status       = isset( $plugin['status'] ) ? sanitize_key( (string) $plugin['status'] ) : 'not_detected';
							$badge_status = $third_party_badge_status[ $status ] ?? 'not-verified';
							$type_label   = ! empty( $plugin['required'] ) ? __( 'Obbligatorio', 'wp-ai-publisher' ) : ( ! empty( $plugin['recommended'] ) ? __( 'Consigliato', 'wp-ai-publisher' ) : __( 'Opzionale', 'wp-ai-publisher' ) );
							$admin_url    = ! empty( $plugin['admin_url'] ) ? (string) $plugin['admin_url'] : '';
							$download_url = ! empty( $plugin['download_url'] ) ? (string) $plugin['download_url'] : '';
							?>
							<tr>
								<th scope="row">
									<?php echo esc_html( (string) $plugin['name'] ); ?>
									<?php if ( ! empty( $plugin['notes'] ) ) : ?>
										<br><small><?php echo esc_html( (string) $plugin['notes'] ); ?></small>
									<?php endif; ?>
								</th>
								<td><span class="<?php echo esc_attr( wpai_publisher_badge_class( $badge_status ) ); ?>"><?php echo esc_html( $third_party_status_labels[ $status ] ?? $status ); ?></span></td>
								<td><?php echo esc_html( $type_label ); ?></td>
								<td><?php echo esc_html( (string) $plugin['purpose'] ); ?></td>
								<td>
									<?php if ( 'active' === $status || 'detected' === $status ) : ?>
										<?php echo esc_html__( 'OK', 'wp-ai-publisher' ); ?>
										<?php if ( '' !== $admin_url ) : ?>
											<br><a href="<?php echo esc_url( $admin_url ); ?>"><?php echo esc_html__( 'Apri impostazioni', 'wp-ai-publisher' ); ?></a>
										<?php endif; ?>
									<?php elseif ( '' !== $download_url && ( ! empty( $plugin['required'] ) || ! empty( $plugin['recommended'] ) ) ) : ?>
										<a href="<?php echo esc_url( $download_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( sprintf( __( 'Scarica o apri la documentazione per %s', 'wp-ai-publisher' ), (string) $plugin['name'] ) ); ?>"><?php echo esc_html__( 'Scarica / documentazione', 'wp-ai-publisher' ); ?></a>
									<?php elseif ( '' !== $admin_url ) : ?>
										<a href="<?php echo esc_url( $admin_url ); ?>"><?php echo esc_html__( 'Apri impostazioni', 'wp-ai-publisher' ); ?></a>
									<?php else : ?>
										<?php echo esc_html__( 'Nessuna azione', 'wp-ai-publisher' ); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

		<section class="wpai-card">
			<h2><?php echo esc_html__( 'Prossima fase', 'wp-ai-publisher' ); ?></h2>
			<p><span class="wpai-badge wpai-badge--not-implemented"><?php echo esc_html__( 'Da implementare', 'wp-ai-publisher' ); ?></span></p>
			<p><?php echo esc_html__( 'Prossimo passo consigliato: coda job, migrazioni database e primo dry-run di generazione bozza.', 'wp-ai-publisher' ); ?></p>
		</section>
	</div>
</div>
