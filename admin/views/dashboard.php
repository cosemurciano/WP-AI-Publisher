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

		<section class="wpai-card">
			<h2><?php echo esc_html__( 'Prossima fase', 'wp-ai-publisher' ); ?></h2>
			<p><span class="wpai-badge wpai-badge--not-implemented"><?php echo esc_html__( 'Da implementare', 'wp-ai-publisher' ); ?></span></p>
			<p><?php echo esc_html__( 'Prossimo passo consigliato: coda job, migrazioni database e primo dry-run di generazione bozza.', 'wp-ai-publisher' ); ?></p>
		</section>
	</div>
</div>
