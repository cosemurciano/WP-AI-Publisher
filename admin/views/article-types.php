<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap wpai-admin">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Tipologie articolo', 'wp-ai-publisher' ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-publisher-article-types&action=new' ) ); ?>"><?php echo esc_html__( 'Aggiungi nuova', 'wp-ai-publisher' ); ?></a>
	<hr class="wp-header-end" />
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Nome', 'wp-ai-publisher' ); ?></th><th><?php esc_html_e( 'Attiva', 'wp-ai-publisher' ); ?></th><th><?php esc_html_e( 'Prompt', 'wp-ai-publisher' ); ?></th><th><?php esc_html_e( 'Immagini', 'wp-ai-publisher' ); ?></th><th><?php esc_html_e( 'Categorie consentite', 'wp-ai-publisher' ); ?></th><th><?php esc_html_e( 'Azioni', 'wp-ai-publisher' ); ?></th></tr></thead>
		<tbody>
		<?php if ( empty( $article_types ) ) : ?><tr><td colspan="6"><?php esc_html_e( 'Nessuna tipologia presente.', 'wp-ai-publisher' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $article_types as $type ) : ?>
			<tr>
				<td><strong><?php echo esc_html( $type['name'] ); ?></strong></td>
				<td><?php echo ! empty( $type['is_active'] ) ? esc_html__( 'Sì', 'wp-ai-publisher' ) : esc_html__( 'No', 'wp-ai-publisher' ); ?></td>
				<td><?php echo '' !== trim( (string) ( $type['prompt'] ?? '' ) ) ? esc_html__( 'Sì', 'wp-ai-publisher' ) : esc_html__( '—', 'wp-ai-publisher' ); ?></td>
				<td><?php echo '' !== trim( (string) ( $type['image_prompt'] ?? '' ) ) ? esc_html__( 'Sì', 'wp-ai-publisher' ) : esc_html__( '—', 'wp-ai-publisher' ); ?></td>
				<td><?php echo esc_html( empty( $type['allowed_category_ids'] ) ? __( 'Nessuna', 'wp-ai-publisher' ) : implode( ', ', array_map( 'absint', $type['allowed_category_ids'] ) ) ); ?></td>
				<td>
					<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-publisher-article-types&article_type_id=' . absint( $type['id'] ) ) ); ?>"><?php esc_html_e( 'Modifica', 'wp-ai-publisher' ); ?></a>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"><input type="hidden" name="action" value="wpai_publisher_toggle_article_type" /><input type="hidden" name="id" value="<?php echo esc_attr( (string) $type['id'] ); ?>" /><?php wp_nonce_field( 'wpai_publisher_toggle_article_type_' . absint( $type['id'] ) ); ?><?php submit_button( empty( $type['is_active'] ) ? __( 'Attiva', 'wp-ai-publisher' ) : __( 'Disattiva', 'wp-ai-publisher' ), 'secondary small', 'submit', false ); ?></form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Eliminare questa tipologia?', 'wp-ai-publisher' ) ); ?>');"><input type="hidden" name="action" value="wpai_publisher_delete_article_type" /><input type="hidden" name="id" value="<?php echo esc_attr( (string) $type['id'] ); ?>" /><?php wp_nonce_field( 'wpai_publisher_delete_article_type_' . absint( $type['id'] ) ); ?><?php submit_button( __( 'Elimina', 'wp-ai-publisher' ), 'delete small', 'submit', false ); ?></form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
