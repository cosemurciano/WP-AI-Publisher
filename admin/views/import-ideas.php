<?php
/**
 * Bulk import of content ideas (CSV) view.
 *
 * @package WPAIPublisher
 * @var array|false $feedback Import feedback.
 * @var bool        $article_types_enabled Whether article types are enabled.
 * @var string      $ideas_url URL of the content ideas page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpai-admin">
	<h1><?php echo esc_html__( 'Importazione massiva di idee', 'wp-ai-publisher' ); ?></h1>
	<p><a href="<?php echo esc_url( $ideas_url ); ?>">&larr; <?php echo esc_html__( 'Torna alle idee', 'wp-ai-publisher' ); ?></a></p>

	<p class="wpai-lead">
		<?php echo esc_html__( 'Carica un file CSV per creare più idee in una volta sola. Tutte le bozze generate da questa funzione vengono messe obbligatoriamente in programmazione e, alla creazione, viene inviata una notifica su Telegram (se configurato).', 'wp-ai-publisher' ); ?>
	</p>

	<?php if ( is_array( $feedback ) ) : ?>
		<div class="notice notice-<?php echo ( (int) $feedback['created'] > 0 && empty( $feedback['errors'] ) ) ? 'success' : ( (int) $feedback['created'] > 0 ? 'warning' : 'error' ); ?>">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: created, 2: skipped, 3: total */
						__( 'Importazione completata: %1$d idee create, %2$d ignorate su %3$d righe.', 'wp-ai-publisher' ),
						(int) $feedback['created'],
						(int) $feedback['skipped'],
						(int) $feedback['total']
					)
				);
				?>
			</p>
			<?php if ( ! empty( $feedback['errors'] ) ) : ?>
				<ul style="list-style:disc;margin-left:20px;">
					<?php foreach ( $feedback['errors'] as $wpai_err ) : ?>
						<li><?php echo esc_html( (string) $wpai_err ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( (int) $feedback['created'] > 0 ) : ?>
				<p><a class="button" href="<?php echo esc_url( $ideas_url ); ?>"><?php echo esc_html__( 'Vedi le idee importate', 'wp-ai-publisher' ); ?></a></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<section class="wpai-card">
		<h2><?php echo esc_html__( '1. Scarica il file di esempio', 'wp-ai-publisher' ); ?></h2>
		<p><?php echo esc_html__( 'Il CSV deve contenere le colonne: Argomento principale, Lingua, Tipologia articolo, Programma creazione (data e ora), Categorie.', 'wp-ai-publisher' ); ?></p>
		<ul style="list-style:disc;margin-left:20px;">
			<li><strong><?php echo esc_html__( 'Argomento principale', 'wp-ai-publisher' ); ?></strong>: <?php echo esc_html__( 'obbligatorio.', 'wp-ai-publisher' ); ?></li>
			<li><strong><?php echo esc_html__( 'Lingua', 'wp-ai-publisher' ); ?></strong>: <?php echo esc_html__( 'codice (it, en, fr, es, de) o nome. Default: it.', 'wp-ai-publisher' ); ?></li>
			<li><strong><?php echo esc_html__( 'Tipologia articolo', 'wp-ai-publisher' ); ?></strong>: <?php echo $article_types_enabled ? esc_html__( 'nome esatto di una tipologia attiva (obbligatorio).', 'wp-ai-publisher' ) : esc_html__( 'ignorata (tipologie non attive).', 'wp-ai-publisher' ); ?></li>
			<li><strong><?php echo esc_html__( 'Programma creazione', 'wp-ai-publisher' ); ?></strong>: <?php echo esc_html__( 'obbligatorio. Formato consigliato: AAAA-MM-GG HH:MM (es. 2026-07-01 09:30), nel fuso orario del sito.', 'wp-ai-publisher' ); ?></li>
			<li><strong><?php echo esc_html__( 'Categorie', 'wp-ai-publisher' ); ?></strong>: <?php echo esc_html__( 'opzionale. Uno o più nomi di categorie già esistenti, separati da virgola. Vengono assegnate alla bozza e comunicate all’AI per orientare il contenuto. I nomi inesistenti vengono ignorati.', 'wp-ai-publisher' ); ?></li>
		</ul>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpai_publisher_download_idea_sample" />
			<?php wp_nonce_field( 'wpai_publisher_download_idea_sample' ); ?>
			<p><button type="submit" class="button"><?php echo esc_html__( 'Scarica CSV di esempio', 'wp-ai-publisher' ); ?></button></p>
		</form>
	</section>

	<section class="wpai-card">
		<h2><?php echo esc_html__( '2. Carica il tuo file CSV', 'wp-ai-publisher' ); ?></h2>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpai_publisher_import_ideas" />
			<?php wp_nonce_field( 'wpai_publisher_import_ideas' ); ?>
			<p><input type="file" name="wpai_csv" accept=".csv,text/csv" required /></p>
			<p class="submit"><button type="submit" class="button button-primary"><?php echo esc_html__( 'Importa idee', 'wp-ai-publisher' ); ?></button></p>
		</form>
	</section>
</div>
