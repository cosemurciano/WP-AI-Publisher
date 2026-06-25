<?php
/**
 * Edit a generated guide (title + content).
 *
 * @package WPAIPublisher
 * @var \WP_Post $guide Guide post.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpai_back_url = add_query_arg( array( 'page' => 'wp-ai-publisher-guide-requests' ), admin_url( 'admin.php' ) );
$wpai_view_url = get_permalink( $guide );
?>
<div class="wrap wpai-admin">
	<h1><?php echo esc_html__( 'Modifica guida', 'wp-ai-publisher' ); ?></h1>
	<p>
		<a href="<?php echo esc_url( $wpai_back_url ); ?>">&larr; <?php echo esc_html__( 'Torna alle richieste', 'wp-ai-publisher' ); ?></a>
		<?php if ( $wpai_view_url ) : ?> &nbsp;·&nbsp; <a href="<?php echo esc_url( (string) $wpai_view_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Vedi pagina pubblica', 'wp-ai-publisher' ); ?></a><?php endif; ?>
	</p>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Guida aggiornata.', 'wp-ai-publisher' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wpai_publisher_save_guide_edit">
		<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $guide->ID ); ?>">
		<?php wp_nonce_field( 'wpai_publisher_save_guide_edit_' . (int) $guide->ID ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="wpai-guide-title"><?php echo esc_html__( 'Titolo', 'wp-ai-publisher' ); ?></label></th>
					<td><input type="text" id="wpai-guide-title" name="guide_title" class="large-text" value="<?php echo esc_attr( $guide->post_title ); ?>"></td>
				</tr>
			</tbody>
		</table>

		<h2 style="margin-top:8px;"><?php echo esc_html__( 'Contenuto', 'wp-ai-publisher' ); ?></h2>
		<?php
		wp_editor(
			$guide->post_content,
			'guide_content',
			array(
				'textarea_name' => 'guide_content',
				'textarea_rows' => 20,
				'media_buttons' => false,
				'teeny'         => false,
			)
		);
		?>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php echo esc_html__( 'Salva modifiche', 'wp-ai-publisher' ); ?></button>
			<a href="<?php echo esc_url( $wpai_back_url ); ?>" class="button"><?php echo esc_html__( 'Annulla', 'wp-ai-publisher' ); ?></a>
		</p>
	</form>
</div>
