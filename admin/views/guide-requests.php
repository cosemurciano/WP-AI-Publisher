<?php
/**
 * Assistente Guide AI — saved requests list.
 *
 * @package WPAIPublisher
 * @var array<int,object> $requests Stored guide requests.
 * @var array<int,int>    $quick_ids IDs flagged as quick replies.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$quick_ids = isset( $quick_ids ) && is_array( $quick_ids ) ? array_map( 'absint', $quick_ids ) : array();

$wpai_guide_notices = array(
	'guide_idea_created'    => array( 'success', __( 'Idea contenuto creata dalla richiesta.', 'wp-ai-publisher' ) ),
	'guide_idea_failed'     => array( 'error', __( 'Impossibile creare l’idea dalla richiesta.', 'wp-ai-publisher' ) ),
	'guide_request_deleted' => array( 'success', __( 'Richiesta eliminata.', 'wp-ai-publisher' ) ),
	'guide_request_missing' => array( 'error', __( 'Richiesta non trovata.', 'wp-ai-publisher' ) ),
	'guide_quick_saved'     => array( 'success', __( 'Risposte veloci aggiornate.', 'wp-ai-publisher' ) ),
);
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Richieste guide', 'wp-ai-publisher' ); ?></h1>

	<?php
	if ( isset( $_GET['wpai_notice'] ) ) {
		$wpai_code = sanitize_key( wp_unslash( $_GET['wpai_notice'] ) );
		if ( isset( $wpai_guide_notices[ $wpai_code ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $wpai_guide_notices[ $wpai_code ][0] ),
				esc_html( $wpai_guide_notices[ $wpai_code ][1] )
			);
		}
	}
	?>

	<p><?php echo esc_html__( 'Tutte le richieste inviate dai visitatori all’Assistente Guide AI. Usale come fonte di idee editoriali o convertile in bozze. Spunta “Risposta veloce” per mostrare una richiesta come scorciatoia sotto il campo di ricerca.', 'wp-ai-publisher' ); ?></p>

	<?php if ( empty( $requests ) ) : ?>
		<p><?php echo esc_html__( 'Ancora nessuna richiesta.', 'wp-ai-publisher' ); ?></p>
	<?php else : ?>
		<form id="wpai-quick-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpai_publisher_save_guide_quick_replies">
			<?php wp_nonce_field( 'wpai_publisher_save_guide_quick_replies' ); ?>
			<p><button type="submit" class="button button-primary"><?php echo esc_html__( 'Salva risposte veloci', 'wp-ai-publisher' ); ?></button></p>
		</form>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Risposta veloce', 'wp-ai-publisher' ); ?></th>
					<th><?php echo esc_html__( 'Data', 'wp-ai-publisher' ); ?></th>
					<th><?php echo esc_html__( 'Richiesta', 'wp-ai-publisher' ); ?></th>
					<th><?php echo esc_html__( 'Lingua', 'wp-ai-publisher' ); ?></th>
					<th><?php echo esc_html__( 'Stato', 'wp-ai-publisher' ); ?></th>
					<th><?php echo esc_html__( 'Azioni', 'wp-ai-publisher' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $requests as $req ) : ?>
					<tr>
						<td><input type="checkbox" name="quick_replies[]" form="wpai-quick-form" value="<?php echo esc_attr( (string) $req->id ); ?>" <?php checked( in_array( (int) $req->id, $quick_ids, true ) ); ?>></td>
						<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', (string) $req->created_at ) ); ?></td>
						<td><?php echo esc_html( wp_trim_words( (string) $req->query, 24 ) ); ?></td>
						<td><?php echo esc_html( strtoupper( (string) $req->language ) ); ?></td>
						<td>
							<?php
							if ( 'idea' === $req->status && ! empty( $req->idea_id ) ) {
								echo esc_html__( 'Convertita in idea', 'wp-ai-publisher' );
							} else {
								echo esc_html__( 'Nuova', 'wp-ai-publisher' );
							}
							?>
						</td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-ai-publisher-guide-requests', 'view' => (int) $req->id ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Visualizza', 'wp-ai-publisher' ); ?></a>
							<?php
							$wpai_req_post_id = absint( $req->post_id ?? 0 );
							if ( $wpai_req_post_id > 0 && get_post_status( $wpai_req_post_id ) ) :
								?>
								<a class="button button-small" href="<?php echo esc_url( (string) get_permalink( $wpai_req_post_id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Pagina', 'wp-ai-publisher' ); ?></a>
							<?php endif; ?>
							<?php if ( 'idea' !== $req->status ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<input type="hidden" name="action" value="wpai_publisher_guide_request_to_idea">
									<input type="hidden" name="request_id" value="<?php echo esc_attr( (string) $req->id ); ?>">
									<?php wp_nonce_field( 'wpai_publisher_guide_request_to_idea_' . (int) $req->id ); ?>
									<button type="submit" class="button button-small"><?php echo esc_html__( 'Crea idea', 'wp-ai-publisher' ); ?></button>
								</form>
							<?php endif; ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Eliminare questa richiesta?', 'wp-ai-publisher' ) ); ?>');">
								<input type="hidden" name="action" value="wpai_publisher_delete_guide_request">
								<input type="hidden" name="request_id" value="<?php echo esc_attr( (string) $req->id ); ?>">
								<?php wp_nonce_field( 'wpai_publisher_delete_guide_request_' . (int) $req->id ); ?>
								<button type="submit" class="button button-small button-link-delete"><?php echo esc_html__( 'Elimina', 'wp-ai-publisher' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
