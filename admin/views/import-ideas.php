<?php
/**
 * Bulk import of content ideas (CSV) view.
 *
 * Two-step flow: upload → preview report → confirm import.
 *
 * @package WPAIPublisher
 * @var array|false $feedback Import feedback (after a confirmed import).
 * @var array|null  $preview  Pending preview report (after an upload, before confirm).
 * @var bool        $article_types_enabled Whether article types are enabled.
 * @var string      $ideas_url URL of the content ideas page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpai_fmt_dt = static function ( $value ) {
	$value = (string) $value;
	if ( '' === $value ) {
		return '—';
	}
	$dt = \DateTime::createFromFormat( 'Y-m-d H:i:s', $value );
	return $dt ? $dt->format( 'd/m/Y H:i' ) : esc_html( $value );
};
?>
<div class="wrap wpai-admin">
	<h1><?php echo esc_html__( 'Importazione massiva di idee', 'wp-ai-publisher' ); ?></h1>
	<p><a href="<?php echo esc_url( $ideas_url ); ?>">&larr; <?php echo esc_html__( 'Torna alle idee', 'wp-ai-publisher' ); ?></a></p>

	<?php if ( is_array( $feedback ) ) : ?>
		<div class="notice notice-<?php echo ( (int) $feedback['created'] > 0 && empty( $feedback['errors'] ) ) ? 'success' : ( (int) $feedback['created'] > 0 ? 'warning' : 'error' ); ?>">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: created, 2: skipped, 3: processed rows */
						__( 'Importazione completata: %1$d idee create, %2$d ignorate su %3$d righe elaborate.', 'wp-ai-publisher' ),
						(int) $feedback['created'],
						(int) $feedback['skipped'],
						(int) $feedback['total']
					)
				);
				if ( ! empty( $feedback['file_total'] ) && (int) $feedback['file_total'] > (int) $feedback['total'] ) {
					echo ' ' . esc_html(
						sprintf(
							/* translators: 1: imported records, 2: file total */
							__( '(hai importato i primi %1$d record di %2$d presenti nel file)', 'wp-ai-publisher' ),
							(int) $feedback['total'],
							(int) $feedback['file_total']
						)
					);
				}
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

	<?php if ( is_array( $preview ) ) : /* ===================== STEP 2: PREVIEW / CONFIRM ===================== */ ?>

		<p class="wpai-lead">
			<?php echo esc_html__( 'Controlla il riepilogo qui sotto prima di importare. Nessuna idea o categoria è ancora stata creata.', 'wp-ai-publisher' ); ?>
			<?php if ( '' !== (string) ( $preview['filename'] ?? '' ) ) : ?>
				<br><strong><?php echo esc_html__( 'File:', 'wp-ai-publisher' ); ?></strong> <code><?php echo esc_html( (string) $preview['filename'] ); ?></code>
			<?php endif; ?>
		</p>

		<?php if ( ! empty( $preview['truncated'] ) ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: max rows */
						__( 'Il file supera il limite per batch: vengono elaborate solo le prime %d righe. Suddividi il file per importare le restanti.', 'wp-ai-publisher' ),
						(int) \WPAIPublisher\Bulk_Import::MAX_ROWS
					)
				);
				?>
			</p></div>
		<?php endif; ?>

		<div class="wpai-stats" style="margin:16px 0;">
			<div class="wpai-stat wpai-stat--primary">
				<span class="wpai-stat__num"><?php echo esc_html( (string) (int) $preview['total'] ); ?></span>
				<span class="wpai-stat__label"><?php echo esc_html__( 'Idee totali nel file', 'wp-ai-publisher' ); ?></span>
			</div>
			<div class="wpai-stat">
				<span class="wpai-stat__num"><?php echo esc_html( (string) (int) $preview['valid'] ); ?></span>
				<span class="wpai-stat__label"><?php echo esc_html__( 'Valide (importabili)', 'wp-ai-publisher' ); ?></span>
			</div>
			<div class="wpai-stat">
				<span class="wpai-stat__num"><?php echo esc_html( (string) (int) $preview['invalid'] ); ?></span>
				<span class="wpai-stat__label"><?php echo esc_html__( 'Da correggere (saltate)', 'wp-ai-publisher' ); ?></span>
			</div>
			<div class="wpai-stat">
				<span class="wpai-stat__num"><?php echo esc_html( (string) count( (array) $preview['categories_create'] ) ); ?></span>
				<span class="wpai-stat__label"><?php echo esc_html__( 'Categorie da creare', 'wp-ai-publisher' ); ?></span>
			</div>
		</div>

		<section class="wpai-card">
			<h2><?php echo esc_html__( 'Periodo di creazione bozze', 'wp-ai-publisher' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: from date, 2: to date */
					esc_html__( 'Dal %1$s al %2$s (fuso orario del sito).', 'wp-ai-publisher' ),
					'<strong>' . esc_html( $wpai_fmt_dt( $preview['schedule_min'] ?? '' ) ) . '</strong>',
					'<strong>' . esc_html( $wpai_fmt_dt( $preview['schedule_max'] ?? '' ) ) . '</strong>'
				);
				?>
			</p>
			<p class="description"><?php echo esc_html__( 'È la finestra in cui verranno programmate le bozze delle idee valide.', 'wp-ai-publisher' ); ?></p>
		</section>

		<section class="wpai-card">
			<h2><?php echo esc_html__( 'Conferma importazione', 'wp-ai-publisher' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wpai-import-confirm">
				<input type="hidden" name="action" value="wpai_publisher_import_ideas" />
				<?php wp_nonce_field( 'wpai_publisher_import_ideas' ); ?>
				<p>
					<label>
						<input type="checkbox" name="wpai_import_all" id="wpai-import-all" value="1" checked />
						<?php echo esc_html__( 'Importa tutte le idee del file', 'wp-ai-publisher' ); ?>
					</label>
				</p>
				<p>
					<label for="wpai-import-limit"><?php echo esc_html__( 'Oppure importa solo le prime', 'wp-ai-publisher' ); ?></label>
					<input type="number" name="wpai_limit" id="wpai-import-limit" min="1" max="<?php echo esc_attr( (string) (int) $preview['total'] ); ?>" step="1" style="width:90px;" disabled />
					<?php echo esc_html__( 'idee, seguendo l’ordine dei record nel file.', 'wp-ai-publisher' ); ?>
				</p>
				<p class="description"><?php echo esc_html__( 'Il conteggio segue l’ordine del file dall’alto; le righe non valide comprese nel conteggio vengono saltate e segnalate.', 'wp-ai-publisher' ); ?></p>
				<p class="description">
					<?php
					echo ! empty( $preview['create_terms'] )
						? esc_html__( 'Le categorie mancanti verranno create automaticamente.', 'wp-ai-publisher' )
						: esc_html__( 'Le categorie mancanti NON verranno create (le idee si importano senza quelle categorie).', 'wp-ai-publisher' );
					?>
				</p>
				<p class="submit" style="display:flex;gap:8px;align-items:center;">
					<button type="submit" class="button button-primary"><?php echo esc_html__( 'Conferma e importa', 'wp-ai-publisher' ); ?></button>
				</p>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:-8px;">
				<input type="hidden" name="action" value="wpai_publisher_cancel_import" />
				<?php wp_nonce_field( 'wpai_publisher_cancel_import' ); ?>
				<button type="submit" class="button button-link-delete"><?php echo esc_html__( 'Annulla e carica un altro file', 'wp-ai-publisher' ); ?></button>
			</form>
			<script>
			( function () {
				var all = document.getElementById( 'wpai-import-all' );
				var num = document.getElementById( 'wpai-import-limit' );
				if ( ! all || ! num ) { return; }
				function sync() { num.disabled = all.checked; }
				all.addEventListener( 'change', sync );
				num.addEventListener( 'focus', function () { all.checked = false; sync(); } );
				sync();
			}() );
			</script>
		</section>

		<?php if ( ! empty( $preview['errors'] ) ) : ?>
			<section class="wpai-card">
				<h2><span class="wpai-badge wpai-badge--error"><?php echo esc_html( (string) count( (array) $preview['errors'] ) ); ?></span> <?php echo esc_html__( 'Righe non valide (verranno saltate)', 'wp-ai-publisher' ); ?></h2>
				<ul style="list-style:disc;margin-left:20px;">
					<?php foreach ( $preview['errors'] as $wpai_e ) : ?>
						<li><?php echo esc_html( (string) $wpai_e ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $preview['warnings'] ) ) : ?>
			<section class="wpai-card">
				<h2><span class="wpai-badge wpai-badge--warning"><?php echo esc_html( (string) count( (array) $preview['warnings'] ) ); ?></span> <?php echo esc_html__( 'Avvisi', 'wp-ai-publisher' ); ?></h2>
				<ul style="list-style:disc;margin-left:20px;">
					<?php foreach ( $preview['warnings'] as $wpai_w ) : ?>
						<li><?php echo esc_html( (string) $wpai_w ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php
		$wpai_has_breakdown = ! empty( $preview['by_type'] ) || ! empty( $preview['by_language'] ) || ! empty( $preview['categories_create'] ) || ! empty( $preview['categories_missing'] ) || ! empty( $preview['duplicates'] );
		if ( $wpai_has_breakdown ) :
			?>
			<section class="wpai-card">
				<h2><?php echo esc_html__( 'Dettaglio', 'wp-ai-publisher' ); ?></h2>
				<div class="wpai-card-grid">
					<?php if ( ! empty( $preview['by_type'] ) ) : ?>
						<div>
							<h3><?php echo esc_html__( 'Per tipologia (idee valide)', 'wp-ai-publisher' ); ?></h3>
							<ul style="list-style:disc;margin-left:20px;">
								<?php foreach ( $preview['by_type'] as $wpai_t => $wpai_c ) : ?>
									<li><?php echo esc_html( (string) $wpai_t ); ?>: <strong><?php echo esc_html( (string) (int) $wpai_c ); ?></strong></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $preview['by_language'] ) ) : ?>
						<div>
							<h3><?php echo esc_html__( 'Per lingua (idee valide)', 'wp-ai-publisher' ); ?></h3>
							<ul style="list-style:disc;margin-left:20px;">
								<?php foreach ( $preview['by_language'] as $wpai_l => $wpai_c ) : ?>
									<li><?php echo esc_html( strtoupper( (string) $wpai_l ) ); ?>: <strong><?php echo esc_html( (string) (int) $wpai_c ); ?></strong></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $preview['categories_create'] ) ) : ?>
						<div>
							<h3><?php echo esc_html__( 'Categorie che verranno create', 'wp-ai-publisher' ); ?></h3>
							<p><?php echo esc_html( implode( ', ', array_map( 'strval', (array) $preview['categories_create'] ) ) ); ?></p>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $preview['categories_missing'] ) ) : ?>
						<div>
							<h3><?php echo esc_html__( 'Categorie non trovate (saltate)', 'wp-ai-publisher' ); ?></h3>
							<p><?php echo esc_html( implode( ', ', array_map( 'strval', (array) $preview['categories_missing'] ) ) ); ?></p>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $preview['duplicates'] ) ) : ?>
						<div>
							<h3><?php echo esc_html__( 'Argomenti duplicati nel file', 'wp-ai-publisher' ); ?></h3>
							<ul style="list-style:disc;margin-left:20px;">
								<?php foreach ( $preview['duplicates'] as $wpai_dup => $wpai_n ) : ?>
									<li><?php echo esc_html( (string) $wpai_dup ); ?> (×<?php echo esc_html( (string) (int) $wpai_n ); ?>)</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $preview['rows'] ) ) : ?>
			<details class="wpai-diag-group" style="margin-top:14px;">
				<summary><?php echo esc_html__( 'Anteprima righe', 'wp-ai-publisher' ); ?> (<?php echo esc_html( (string) count( (array) $preview['rows'] ) ); ?><?php echo (int) $preview['total'] > count( (array) $preview['rows'] ) ? ' / ' . esc_html( (string) (int) $preview['total'] ) : ''; ?>)</summary>
				<table class="widefat striped wpai-status-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Riga', 'wp-ai-publisher' ); ?></th>
							<th><?php echo esc_html__( 'Stato', 'wp-ai-publisher' ); ?></th>
							<th><?php echo esc_html__( 'Argomento', 'wp-ai-publisher' ); ?></th>
							<th><?php echo esc_html__( 'Lingua', 'wp-ai-publisher' ); ?></th>
							<th><?php echo esc_html__( 'Tipologia', 'wp-ai-publisher' ); ?></th>
							<th><?php echo esc_html__( 'Programmazione', 'wp-ai-publisher' ); ?></th>
							<th><?php echo esc_html__( 'Note', 'wp-ai-publisher' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $preview['rows'] as $wpai_r ) : ?>
							<tr>
								<td><?php echo esc_html( (string) (int) $wpai_r['line'] ); ?></td>
								<td>
									<?php if ( ! empty( $wpai_r['valid'] ) ) : ?>
										<span class="wpai-badge wpai-badge--ok"><?php echo esc_html__( 'OK', 'wp-ai-publisher' ); ?></span>
									<?php else : ?>
										<span class="wpai-badge wpai-badge--error"><?php echo esc_html__( 'Salta', 'wp-ai-publisher' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( (string) $wpai_r['topic'] ); ?></td>
								<td><?php echo esc_html( strtoupper( (string) $wpai_r['language'] ) ); ?></td>
								<td><?php echo esc_html( (string) $wpai_r['type'] ); ?></td>
								<td><?php echo esc_html( $wpai_fmt_dt( $wpai_r['schedule'] ?? '' ) ); ?></td>
								<td><?php echo empty( $wpai_r['errors'] ) ? '&mdash;' : esc_html( implode( '; ', array_map( 'strval', (array) $wpai_r['errors'] ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</details>
		<?php endif; ?>

	<?php else : /* ===================== STEP 1: UPLOAD ===================== */ ?>

		<p class="wpai-lead">
			<?php echo esc_html__( 'Carica un file CSV per creare più idee in una volta sola. Tutte le bozze generate da questa funzione vengono messe obbligatoriamente in programmazione e, alla creazione, viene inviata una notifica su Telegram (se configurato). Dopo il caricamento vedrai un’anteprima di controllo prima di importare.', 'wp-ai-publisher' ); ?>
		</p>

		<section class="wpai-card">
			<h2><?php echo esc_html__( '1. Scarica il file di esempio', 'wp-ai-publisher' ); ?></h2>
			<p><?php echo esc_html__( 'Il CSV deve contenere le colonne: Argomento principale, Lingua, Tipologia articolo, Programma creazione (data e ora), Categorie.', 'wp-ai-publisher' ); ?></p>
			<ul style="list-style:disc;margin-left:20px;">
				<li><strong><?php echo esc_html__( 'Argomento principale', 'wp-ai-publisher' ); ?></strong>: <?php echo esc_html__( 'obbligatorio.', 'wp-ai-publisher' ); ?></li>
				<li><strong><?php echo esc_html__( 'Lingua', 'wp-ai-publisher' ); ?></strong>: <?php echo esc_html__( 'codice (it, en, fr, es, de) o nome. Default: it.', 'wp-ai-publisher' ); ?></li>
				<li><strong><?php echo esc_html__( 'Tipologia articolo', 'wp-ai-publisher' ); ?></strong>: <?php echo $article_types_enabled ? esc_html__( 'nome esatto di una tipologia attiva (obbligatorio).', 'wp-ai-publisher' ) : esc_html__( 'ignorata (tipologie non attive).', 'wp-ai-publisher' ); ?></li>
				<li><strong><?php echo esc_html__( 'Programma creazione', 'wp-ai-publisher' ); ?></strong>: <?php echo esc_html__( 'obbligatorio. Formato consigliato: AAAA-MM-GG HH:MM (es. 2026-07-01 09:30), nel fuso orario del sito.', 'wp-ai-publisher' ); ?></li>
				<li><strong><?php echo esc_html__( 'Categorie | Sottocategorie', 'wp-ai-publisher' ); ?></strong>: <?php echo esc_html__( 'opzionale. Categoria principale, poi il simbolo | e le sottocategorie separate da punto e virgola. Esempio: "GUIDE | Bici da città; Mobilità urbana". Con l’opzione qui sotto, categorie e gerarchie mancanti vengono create automaticamente. È supportato anche il vecchio formato (nomi separati da virgola).', 'wp-ai-publisher' ); ?></li>
				<li><strong><?php echo esc_html__( 'Prompt dell’immagine da inserire', 'wp-ai-publisher' ); ?></strong>: <?php echo esc_html__( 'opzionale. Descrizione per generare l’immagine in evidenza (una sola immagine di copertina).', 'wp-ai-publisher' ); ?></li>
				<li><strong><?php echo esc_html__( 'Prompt Social Facebook / Instagram / LinkedIn', 'wp-ai-publisher' ); ?></strong>: <?php echo esc_html__( 'opzionale. Istruzioni salvate sulla bozza e usate dalle integrazioni social alla pubblicazione.', 'wp-ai-publisher' ); ?></li>
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
				<input type="hidden" name="action" value="wpai_publisher_preview_ideas" />
				<?php wp_nonce_field( 'wpai_publisher_preview_ideas' ); ?>
				<p><input type="file" name="wpai_csv" accept=".csv,text/csv" required /></p>
				<p>
					<label>
						<input type="checkbox" name="wpai_create_terms" value="1" checked />
						<?php echo esc_html__( 'Crea automaticamente le categorie mancanti (incluse le sottocategorie/gerarchie).', 'wp-ai-publisher' ); ?>
					</label>
				</p>
				<p class="submit"><button type="submit" class="button button-primary"><?php echo esc_html__( 'Analizza file e mostra anteprima', 'wp-ai-publisher' ); ?></button></p>
			</form>
		</section>

	<?php endif; ?>
</div>
