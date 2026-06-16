<?php
/**
 * Dashboard widget view.
 *
 * @package WPAIPublisher
 *
 * @var array<string,int>   $counts  Idea counts by status.
 * @var int                 $total   Total ideas.
 * @var array<int,object>   $recent  Recent idea rows.
 * @var string              $ideas_url Content ideas admin URL.
 * @var array<string,string> $summary Status key => label for the summary grid.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpai-dashboard-widget">
	<p class="wpai-dw-total">
		<strong><?php echo esc_html( number_format_i18n( (int) $total ) ); ?></strong>
		<?php echo esc_html__( 'idee contenuto totali', 'wp-ai-publisher' ); ?>
	</p>

	<ul class="wpai-dw-counts">
		<?php foreach ( $summary as $status_key => $label ) : ?>
			<li>
				<span class="<?php echo esc_attr( wpai_publisher_badge_class( $status_key ) ); ?>"><?php echo esc_html( (string) ( $counts[ $status_key ] ?? 0 ) ); ?></span>
				<?php echo esc_html( $label ); ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php if ( ! empty( $recent ) ) : ?>
		<h3 class="wpai-dw-heading"><?php echo esc_html__( 'Ultime idee', 'wp-ai-publisher' ); ?></h3>
		<ul class="wpai-dw-recent">
			<?php foreach ( $recent as $idea ) : ?>
				<?php
				$view_url = wp_nonce_url(
					add_query_arg( 'view_idea', absint( $idea->id ), $ideas_url ),
					'wpai_publisher_view_content_idea_' . absint( $idea->id )
				);
				?>
				<li>
					<a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( wp_trim_words( (string) $idea->topic, 10, '…' ) ); ?></a>
					<span class="<?php echo esc_attr( wpai_publisher_badge_class( sanitize_key( (string) $idea->status ) ) ); ?>"><?php echo esc_html( $this->content_ideas->get_status_label( $idea->status ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<p class="wpai-dw-actions">
		<a class="button button-primary" href="<?php echo esc_url( $ideas_url ); ?>"><?php echo esc_html__( 'Apri Idee contenuto', 'wp-ai-publisher' ); ?></a>
	</p>
</div>
<?php
// phpcs:ignore Squiz.PHP.EmbeddedPhp -- inline widget styling kept local.
?>
<style>
.wpai-dashboard-widget .wpai-dw-counts { display:flex; flex-wrap:wrap; gap:6px 16px; margin:8px 0; padding:0; list-style:none; }
.wpai-dashboard-widget .wpai-dw-counts li { display:flex; align-items:center; gap:6px; }
.wpai-dashboard-widget .wpai-dw-recent { margin:4px 0 12px; padding:0; list-style:none; }
.wpai-dashboard-widget .wpai-dw-recent li { display:flex; justify-content:space-between; gap:8px; padding:3px 0; border-bottom:1px solid #f0f0f1; }
.wpai-dashboard-widget .wpai-dw-heading { margin:10px 0 4px; }
</style>
