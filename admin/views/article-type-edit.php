<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$type = is_array( $article_type ) ? $article_type : array();
$id = absint( $type['id'] ?? 0 );
$categories = get_categories( array( 'hide_empty' => false ) );
$textareas = array(
	'description' => array( 'label' => __( 'Descrizione', 'wp-ai-publisher' ), 'rows' => 4, 'help' => __( 'Spiega a cosa serve questo tipo di articolo e quando dovrebbe essere usato.', 'wp-ai-publisher' ) ),
	'tone' => array( 'label' => __( 'Tono', 'wp-ai-publisher' ), 'rows' => 3, 'help' => __( 'Indica lo stile di scrittura che l’AI deve usare. Puoi inserire istruzioni libere, ad esempio: chiaro, autorevole, semplice, pratico, non promozionale.', 'wp-ai-publisher' ) ),
	'length' => array( 'label' => __( 'Lunghezza', 'wp-ai-publisher' ), 'rows' => 3, 'help' => __( 'Descrivi quanto deve essere esteso il contenuto. Puoi usare testo libero, ad esempio: breve, medio, lungo, oppure indicare un intervallo orientativo di parole.', 'wp-ai-publisher' ) ),
	'search_intent' => array( 'label' => __( 'Intento ricerca', 'wp-ai-publisher' ), 'rows' => 3, 'help' => __( 'Descrive il bisogno dell’utente a cui l’articolo deve rispondere, ad esempio informativo, tutoriale, comparativo, commerciale o di approfondimento.', 'wp-ai-publisher' ) ),
	'reader_level' => array( 'label' => __( 'Livello lettore', 'wp-ai-publisher' ), 'rows' => 3, 'help' => __( 'Indica il livello di competenza del lettore. Puoi specificare principiante, intermedio, avanzato o descrivere meglio il pubblico di riferimento.', 'wp-ai-publisher' ) ),
	'prompt' => array( 'label' => __( 'Prompt', 'wp-ai-publisher' ), 'rows' => 10, 'help' => __( 'Istruzioni principali che guidano la generazione AI per questo tipo di articolo. Devono spiegare come costruire il contenuto.', 'wp-ai-publisher' ) ),
	'structure' => array( 'label' => __( 'Struttura', 'wp-ai-publisher' ), 'rows' => 6, 'help' => __( 'Schema consigliato dell’articolo. Inserisci una sezione per riga o una struttura descrittiva libera.', 'wp-ai-publisher' ) ),
	'required_sections' => array( 'label' => __( 'Sezioni obbligatorie', 'wp-ai-publisher' ), 'rows' => 5, 'help' => __( 'Sezioni che devono essere presenti nella bozza finale. Inserisci una sezione per riga.', 'wp-ai-publisher' ) ),
	'forbidden_patterns' => array( 'label' => __( 'Pattern vietati', 'wp-ai-publisher' ), 'rows' => 6, 'help' => __( 'Frasi, formule, parole o stili che l’AI deve evitare. Inserisci un elemento per riga.', 'wp-ai-publisher' ) ),
	'preferred_tags' => array( 'label' => __( 'Tag preferiti', 'wp-ai-publisher' ), 'rows' => 4, 'help' => __( 'Tag editoriali suggeriti per questo tipo di contenuto. Puoi inserirli separati da virgole o uno per riga.', 'wp-ai-publisher' ) ),
	'quality_checklist' => array( 'label' => __( 'Checklist qualità', 'wp-ai-publisher' ), 'rows' => 6, 'help' => __( 'Controlli editoriali da verificare prima di considerare la bozza pronta. Inserisci un controllo per riga.', 'wp-ai-publisher' ) ),
);
?>
<div class="wrap wpai-admin">
<h1><?php echo esc_html( $id ? __( 'Modifica Tipologia articolo', 'wp-ai-publisher' ) : __( 'Aggiungi Tipologia articolo', 'wp-ai-publisher' ) ); ?></h1>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
<input type="hidden" name="action" value="wpai_publisher_save_article_type" />
<input type="hidden" name="id" value="<?php echo esc_attr( (string) $id ); ?>" />
<?php wp_nonce_field( 'wpai_publisher_save_article_type_' . $id ); ?>
<table class="form-table"><tbody>
<tr><th><label for="name"><?php esc_html_e( 'Nome', 'wp-ai-publisher' ); ?></label></th><td><input class="regular-text" id="name" name="name" value="<?php echo esc_attr( (string) ( $type['name'] ?? '' ) ); ?>" required /><p class="description"><?php esc_html_e( 'Nome interno del tipo di articolo. Serve per riconoscerlo nel pannello di amministrazione.', 'wp-ai-publisher' ); ?></p></td></tr>
<?php foreach ( $textareas as $key => $field ) : ?>
<tr><th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th><td><textarea class="large-text" rows="<?php echo esc_attr( (string) $field['rows'] ); ?>" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( (string) ( $type[ $key ] ?? '' ) ); ?></textarea><p class="description"><?php echo esc_html( $field['help'] ); ?></p></td></tr>
<?php endforeach; ?>
<tr><th><?php esc_html_e( 'Categorie consentite', 'wp-ai-publisher' ); ?></th><td><?php foreach ( $categories as $cat ) : ?><label style="display:block"><input type="checkbox" name="allowed_category_ids[]" value="<?php echo esc_attr( (string) $cat->term_id ); ?>" <?php checked( in_array( (int) $cat->term_id, (array) ( $type['allowed_category_ids'] ?? array() ), true ) ); ?> /> <?php echo esc_html( $cat->name ); ?></label><?php endforeach; ?><p class="description"><?php esc_html_e( 'Limita questo tipo di articolo a una o più categorie WordPress. Se non selezioni nulla, il tipo può essere usato senza vincolo di categoria.', 'wp-ai-publisher' ); ?></p></td></tr>
<tr><th><?php esc_html_e( 'Attivo', 'wp-ai-publisher' ); ?></th><td><label><input type="checkbox" name="is_active" value="1" <?php checked( (bool) ( $type['is_active'] ?? true ) ); ?> /> <?php esc_html_e( 'Disponibile nel workflow Idee contenuto', 'wp-ai-publisher' ); ?></label><p class="description"><?php esc_html_e( 'Se disattivato, questo tipo di articolo non potrà essere usato per creare nuove bozze.', 'wp-ai-publisher' ); ?></p></td></tr>
</tbody></table><?php submit_button( __( 'Salva tipologia', 'wp-ai-publisher' ) ); ?>
</form></div>
