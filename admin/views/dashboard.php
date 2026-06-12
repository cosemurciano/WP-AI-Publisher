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
	<p class="wpai-lead"><?php echo esc_html__( 'A secure, modular foundation for future AI-assisted WordPress publishing workflows.', 'wp-ai-publisher' ); ?></p>

	<div class="notice notice-info inline">
		<p><?php echo esc_html__( 'Phase 1 is infrastructure only. Article generation, images, SEO writing, queues, and publishing automation will be implemented in later phases.', 'wp-ai-publisher' ); ?></p>
	</div>

	<div class="wpai-card-grid">
		<section class="wpai-card">
			<h2><?php echo esc_html__( 'System ready', 'wp-ai-publisher' ); ?></h2>
			<p><span class="wpai-badge wpai-badge--ok"><?php echo esc_html__( 'OK', 'wp-ai-publisher' ); ?></span></p>
			<p><?php echo esc_html__( 'Core plugin services are loaded and ready for the next implementation phase.', 'wp-ai-publisher' ); ?></p>
			<ul>
				<li><?php echo esc_html__( 'Plugin version:', 'wp-ai-publisher' ); ?> <strong><?php echo esc_html( WPAIP_VERSION ); ?></strong></li>
				<li><?php echo esc_html__( 'PHP minimum:', 'wp-ai-publisher' ); ?> <strong><?php echo esc_html( '8.1' ); ?></strong></li>
				<li><?php echo esc_html__( 'WordPress minimum:', 'wp-ai-publisher' ); ?> <strong><?php echo esc_html( '7.0' ); ?></strong></li>
			</ul>
		</section>

		<section class="wpai-card">
			<h2><?php echo esc_html__( 'AI connection', 'wp-ai-publisher' ); ?></h2>
			<?php if ( ! empty( $ai_status['wordpress_ai_client_available'] ) || ! empty( $ai_status['openai_direct_available'] ) ) : ?>
				<p><span class="wpai-badge wpai-badge--warning"><?php echo esc_html__( 'Detected / not verified', 'wp-ai-publisher' ); ?></span></p>
			<?php else : ?>
				<p><span class="wpai-badge wpai-badge--not-verified"><?php echo esc_html__( 'Not verified', 'wp-ai-publisher' ); ?></span></p>
			<?php endif; ?>
			<p><?php echo esc_html__( 'The AI adapter is installed. No OpenAI or AI generation calls are performed in this phase.', 'wp-ai-publisher' ); ?></p>
			<p><?php echo esc_html__( 'Provider preference:', 'wp-ai-publisher' ); ?> <strong><?php echo esc_html( $ai_status['provider_preference'] ); ?></strong></p>
		</section>

		<section class="wpai-card">
			<h2><?php echo esc_html__( 'Database', 'wp-ai-publisher' ); ?></h2>
			<?php if ( ! empty( $db_status['logs'] ) ) : ?>
				<p><span class="wpai-badge wpai-badge--ok"><?php echo esc_html__( 'OK', 'wp-ai-publisher' ); ?></span></p>
				<p><?php echo esc_html__( 'The base log table is available.', 'wp-ai-publisher' ); ?></p>
			<?php else : ?>
				<p><span class="wpai-badge wpai-badge--error"><?php echo esc_html__( 'Error', 'wp-ai-publisher' ); ?></span></p>
				<p><?php echo esc_html__( 'The base log table is missing. Reactivate the plugin or review database permissions.', 'wp-ai-publisher' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="wpai-card">
			<h2><?php echo esc_html__( 'Next implementation phase', 'wp-ai-publisher' ); ?></h2>
			<p><span class="wpai-badge wpai-badge--not-implemented"><?php echo esc_html__( 'Not implemented', 'wp-ai-publisher' ); ?></span></p>
			<p><?php echo esc_html__( 'Recommended next step: implement secure provider credential management and a no-publish article draft dry-run workflow.', 'wp-ai-publisher' ); ?></p>
		</section>
	</div>
</div>
