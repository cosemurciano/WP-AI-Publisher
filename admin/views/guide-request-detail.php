<?php
/**
 * Assistente Guide AI — single request detail (full result).
 *
 * @package WPAIPublisher
 * @var object|null                    $request  The request row.
 * @var array<int,array<string,mixed>> $articles Recommended articles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpai_back_url = add_query_arg( array( 'page' => 'wp-ai-publisher-guide-requests' ), admin_url( 'admin.php' ) );
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Dettaglio richiesta guida', 'wp-ai-publisher' ); ?></h1>
	<p><a href="<?php echo esc_url( $wpai_back_url ); ?>">&larr; <?php echo esc_html__( 'Torna all’elenco', 'wp-ai-publisher' ); ?></a></p>

	<?php if ( ! $request ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html__( 'Richiesta non trovata.', 'wp-ai-publisher' ); ?></p></div>
	<?php else : ?>
		<table class="widefat" style="max-width:760px;margin-bottom:20px;">
			<tbody>
				<tr><th style="width:160px;"><?php echo esc_html__( 'Data', 'wp-ai-publisher' ); ?></th><td><?php echo esc_html( mysql2date( 'd/m/Y H:i', (string) $request->created_at ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Richiesta', 'wp-ai-publisher' ); ?></th><td><strong><?php echo esc_html( (string) $request->query ); ?></strong></td></tr>
				<tr><th><?php echo esc_html__( 'Lingua', 'wp-ai-publisher' ); ?></th><td><?php echo esc_html( strtoupper( (string) $request->language ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Stato', 'wp-ai-publisher' ); ?></th><td><?php echo 'idea' === $request->status ? esc_html__( 'Convertita in idea', 'wp-ai-publisher' ) : esc_html__( 'Nuova', 'wp-ai-publisher' ); ?></td></tr>
			</tbody>
		</table>

		<h2><?php echo esc_html__( 'Guida generata', 'wp-ai-publisher' ); ?></h2>
		<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:24px;max-width:820px;line-height:1.7;">
			<?php
			// Stored HTML was already sanitized with wp_kses at generation time.
			echo wp_kses_post( (string) $request->result_html );
			?>
		</div>

		<?php if ( ! empty( $articles ) ) : ?>
			<h2 style="margin-top:24px;"><?php echo esc_html__( 'Articoli per la tua guida', 'wp-ai-publisher' ); ?></h2>
			<ul>
				<?php foreach ( $articles as $wpai_article ) : ?>
					<li><a href="<?php echo esc_url( (string) $wpai_article['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $wpai_article['title'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( 'idea' !== $request->status ) : ?>
			<p style="margin-top:20px;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
					<input type="hidden" name="action" value="wpai_publisher_guide_request_to_idea">
					<input type="hidden" name="request_id" value="<?php echo esc_attr( (string) $request->id ); ?>">
					<?php wp_nonce_field( 'wpai_publisher_guide_request_to_idea_' . (int) $request->id ); ?>
					<button type="submit" class="button button-primary"><?php echo esc_html__( 'Crea idea contenuto', 'wp-ai-publisher' ); ?></button>
				</form>
			</p>
		<?php endif; ?>
	<?php endif; ?>
</div>
