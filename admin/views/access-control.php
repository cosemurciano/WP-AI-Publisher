<?php
/**
 * Access control settings.
 *
 * @package WPAIPublisher
 * @var array{enabled:bool,denied_page_id:int} $settings Current settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpai-admin">
	<h1><?php echo esc_html__( 'Controllo accessi', 'wp-ai-publisher' ); ?></h1>

	<?php if ( isset( $_GET['wpai_notice'] ) && 'access_saved' === sanitize_key( wp_unslash( $_GET['wpai_notice'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Impostazioni salvate e indice ricostruito.', 'wp-ai-publisher' ); ?></p></div>
	<?php endif; ?>

	<p class="wpai-lead">
		<?php echo esc_html__( 'Limita la visualizzazione di contenuti (articoli, pagine, tipi di contenuto, categorie/tag e voci di menu) in base al login e ai ruoli utente. Imposta l’accesso direttamente nell’editor di ciascun contenuto, termine o voce di menu. Gli amministratori vedono sempre tutto.', 'wp-ai-publisher' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wpai_publisher_save_access">
		<?php wp_nonce_field( 'wpai_publisher_save_access' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Attiva controllo accessi', 'wp-ai-publisher' ); ?></th>
					<td>
						<label><input type="checkbox" name="wpai_access[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php echo esc_html__( 'Applica le regole di accesso sul front-end.', 'wp-ai-publisher' ); ?></label>
						<p class="description"><?php echo esc_html__( 'Se disattivo, le regole restano salvate ma non vengono applicate.', 'wp-ai-publisher' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpai-access-denied-page"><?php echo esc_html__( 'Pagina “accesso negato”', 'wp-ai-publisher' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'wpai_access[denied_page_id]',
								'id'                => 'wpai-access-denied-page',
								'selected'          => (int) $settings['denied_page_id'],
								'show_option_none'  => esc_html__( '— Nessuna (mostra messaggio 403) —', 'wp-ai-publisher' ),
								'option_none_value' => 0,
							)
						);
						?>
						<p class="description"><?php echo esc_html__( 'Pagina mostrata a un utente loggato che non ha il ruolo richiesto. Gli utenti non loggati vengono invece reindirizzati alla pagina di login dell’area membri.', 'wp-ai-publisher' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php echo esc_html__( 'Comportamento', 'wp-ai-publisher' ); ?></h2>
		<ul style="list-style:disc;margin-left:20px;max-width:760px;">
			<li><?php echo esc_html__( 'Default: se non specificato, un contenuto è visibile a tutti.', 'wp-ai-publisher' ); ?></li>
			<li><?php echo esc_html__( 'Un contenuto “solo registrati” o “ruoli specifici” viene nascosto da elenchi, menu, feed, sitemap e REST.', 'wp-ai-publisher' ); ?></li>
			<li><?php echo esc_html__( 'Utente non loggato su contenuto riservato → reindirizzato al login. Ruolo non autorizzato → pagina “accesso negato”.', 'wp-ai-publisher' ); ?></li>
			<li><?php echo esc_html__( 'Una categoria/tag riservato rende riservati anche i contenuti che vi appartengono (salvo regola diversa sul singolo contenuto).', 'wp-ai-publisher' ); ?></li>
			<li><?php echo esc_html__( 'Le pagine riservate non vengono memorizzate dalla cache full-page (header no-cache + DONOTCACHEPAGE).', 'wp-ai-publisher' ); ?></li>
		</ul>

		<?php submit_button( __( 'Salva impostazioni', 'wp-ai-publisher' ) ); ?>
	</form>
</div>
