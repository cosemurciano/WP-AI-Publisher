<?php
/**
 * Classic Editor content builder.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds safe Classic Editor HTML previews from structured dry-run output.
 */
class Classic_Content_Builder {
	/**
	 * Editorial site context.
	 *
	 * @var array<string,string>
	 */
	private $site_context;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed>|null $site_context Optional site context.
	 */
	public function __construct( $site_context = null ) {
		$this->site_context = wpai_publisher_normalize_site_context( null === $site_context ? wpai_publisher_get_site_context() : $site_context );
	}

	/**
	 * Build a complete Classic Editor preview from dry-run output.
	 *
	 * @param array<string,mixed> $dry_run_output Structured dry-run output.
	 * @return array{html:string,plain_text_summary:string,validation_notes:array<int,string>}
	 */
	public function build_from_dry_run( $dry_run_output ) {
		$dry_run_output = is_array( $dry_run_output ) ? $dry_run_output : array();
		$notes          = array();

		$html_parts = array(
			$this->build_intro( $dry_run_output ),
			$this->build_outline_sections( $dry_run_output ),
			$this->outline_has_conclusion_section( $dry_run_output ) ? '' : $this->build_conclusion( $dry_run_output ),
		);

		if ( 'classic' !== $this->site_context['default_editor'] ) {
			$notes[] = __( 'Il contesto editoriale è stato forzato su Editor Classico: Gutenberg non è attivo in questa fase.', 'wp-ai-publisher' );
		}

		if ( 'local_fallback' === (string) ( $dry_run_output['source'] ?? '' ) ) {
			$html_parts[] = '<blockquote><p>' . esc_html__( 'Nota di revisione editoriale: questo contenuto è una simulazione locale utile per testare il flusso. Prima della bozza reale sarà necessaria generazione AI o revisione umana.', 'wp-ai-publisher' ) . '</p></blockquote>';
		}

		$html = $this->sanitize_classic_html( implode( "\n\n", array_filter( $html_parts ) ) );

		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			$notes[] = __( 'Anteprima Classic Editor vuota dopo la sanitizzazione.', 'wp-ai-publisher' );
		}

		$technical_checks = array(
			'<!-- wp:' => __( 'Rilevato commento Gutenberg non consentito nell’anteprima.', 'wp-ai-publisher' ),
			'wp-block' => __( 'Rilevata classe o stringa Gutenberg non consentita nell’anteprima.', 'wp-ai-publisher' ),
			'<script'  => __( 'Rilevato tag script non consentito nell’anteprima.', 'wp-ai-publisher' ),
			'<iframe'  => __( 'Rilevato tag iframe non consentito nell’anteprima.', 'wp-ai-publisher' ),
			' style='  => __( 'Rilevato attributo style inline non consentito nell’anteprima.', 'wp-ai-publisher' ),
			'descrivere in modo pratico' => __( 'L’anteprima contiene frasi placeholder e richiede revisione.', 'wp-ai-publisher' ),
			'descrivere in modo verificabile' => __( 'L’anteprima contiene frasi placeholder e richiede revisione.', 'wp-ai-publisher' ),
			'descrivere il passaggio' => __( 'L’anteprima contiene frasi placeholder e richiede revisione.', 'wp-ai-publisher' ),
			'nel contesto di' => __( 'L’anteprima contiene frasi placeholder e richiede revisione.', 'wp-ai-publisher' ),
			'evitando dettagli tecnici non confermati' => __( 'L’anteprima contiene frasi placeholder e richiede revisione.', 'wp-ai-publisher' ),
			'passaggio “' => __( 'L’anteprima contiene frasi placeholder e richiede revisione.', 'wp-ai-publisher' ),
			'passaggio "' => __( 'L’anteprima contiene frasi placeholder e richiede revisione.', 'wp-ai-publisher' ),
		);

		$lower_html = strtolower( $html );
		foreach ( $technical_checks as $needle => $message ) {
			if ( false !== strpos( $lower_html, strtolower( $needle ) ) ) {
				$notes[] = $message;
			}
		}

		if ( empty( $notes ) ) {
			$notes[] = __( 'Anteprima HTML compatibile con Editor Classico: nessun blocco Gutenberg, script, iframe o style inline rilevato.', 'wp-ai-publisher' );
		}

		return array(
			'html'               => $html,
			'plain_text_summary' => wp_trim_words( wp_strip_all_tags( $html ), 45, '…' ),
			'validation_notes'   => array_values( array_unique( array_filter( $notes ) ) ),
		);
	}


	/**
	 * Build a complete publishable Classic Editor article from a dry-run.
	 *
	 * @param array<string,mixed> $dry_run_output Structured dry-run output.
	 * @param array<string,mixed> $site_context Optional internal editorial context.
	 * @return array{html:string,plain_text_summary:string,validation_notes:array<int,string>,quality_notes:array<int,string>}
	 */
	public function build_full_article_from_dry_run( $dry_run_output, $site_context = array() ) {
		$dry_run_output = is_array( $dry_run_output ) ? $dry_run_output : array();
		if ( ! empty( $site_context ) ) {
			$this->site_context = wpai_publisher_normalize_site_context( $site_context );
		}

		$title   = sanitize_text_field( (string) ( $dry_run_output['title'] ?? $dry_run_output['subtopic'] ?? $dry_run_output['cluster_topic'] ?? '' ) );
		$excerpt = $this->clean_reader_excerpt( (string) ( $dry_run_output['excerpt'] ?? '' ), $title, $dry_run_output );
		$outline = isset( $dry_run_output['content_outline'] ) && is_array( $dry_run_output['content_outline'] ) ? $dry_run_output['content_outline'] : array();
		if ( count( $outline ) < 3 ) {
			$outline[] = array( 'heading' => __( 'Panoramica', 'wp-ai-publisher' ), 'level' => 2, 'summary' => $excerpt );
			$outline[] = array( 'heading' => __( 'Passaggi principali', 'wp-ai-publisher' ), 'level' => 2, 'summary' => __( 'Una sequenza pratica aiuta a procedere senza perdere controlli importanti.', 'wp-ai-publisher' ) );
			$outline[] = array( 'heading' => __( 'Controlli finali', 'wp-ai-publisher' ), 'level' => 2, 'summary' => __( 'La verifica finale permette di confermare che il risultato sia chiaro e utilizzabile.', 'wp-ai-publisher' ) );
		}

		$html = '<p>' . esc_html( $excerpt ) . '</p>' . "\n\n";
		$html .= '<h2>' . esc_html__( 'Indice dei contenuti', 'wp-ai-publisher' ) . '</h2>' . "\n<ul>\n";
		foreach ( $outline as $section ) {
			$heading = sanitize_text_field( (string) ( is_array( $section ) ? ( $section['heading'] ?? '' ) : $section ) );
			if ( '' !== $heading ) {
				$html .= '<li>' . esc_html( $heading ) . '</li>' . "\n";
			}
		}
		$html .= '</ul>' . "\n\n";

		foreach ( $outline as $section ) {
			$heading = sanitize_text_field( (string) ( is_array( $section ) ? ( $section['heading'] ?? '' ) : $section ) );
			if ( '' === $heading ) {
				continue;
			}
			$summary = is_array( $section ) ? (string) ( $section['summary'] ?? '' ) : '';
			$paragraphs = $this->expand_summary_to_reader_paragraphs( $heading, $summary, $dry_run_output );
			$html .= '<h2>' . esc_html( $heading ) . '</h2>' . "\n";
			foreach ( $paragraphs as $paragraph ) {
				$html .= '<p>' . esc_html( $paragraph ) . '</p>' . "\n";
			}
		}

		if ( ! $this->outline_has_conclusion_section( $dry_run_output ) ) {
			$html .= '<h2>' . esc_html__( 'Conclusione e prossimi passi', 'wp-ai-publisher' ) . '</h2>' . "\n";
			$html .= '<p>' . esc_html( sprintf( __( 'A questo punto hai una visione completa di %s e puoi applicare i passaggi descritti con maggiore sicurezza. Rileggi le sezioni principali, verifica il risultato nel sito reale e aggiorna il contenuto quando cambiano strumenti, tema o impostazioni.', 'wp-ai-publisher' ), '' !== $title ? $title : __( 'questo argomento', 'wp-ai-publisher' ) ) ) . '</p>';
		}

		$html = $this->sanitize_classic_html( $html );
		$validation = $this->validate_publishable_article_html( $html );

		return array(
			'html'               => $html,
			'plain_text_summary' => wp_trim_words( wp_strip_all_tags( $html ), 55, '…' ),
			'validation_notes'   => $validation['notes'],
			'quality_notes'      => empty( $validation['valid'] ) ? array( __( 'Articolo da revisionare prima della bozza.', 'wp-ai-publisher' ) ) : array( __( 'Articolo pronto per una bozza Classic Editor.', 'wp-ai-publisher' ) ),
		);
	}

	/**
	 * Validate final Classic Editor article HTML before draft creation.
	 *
	 * @param string $html Article HTML.
	 * @return array{valid:bool,notes:array<int,string>,word_count:int,h2_count:int}
	 */
	public function validate_publishable_article_html( $html ) {
		$html = (string) $html;
		$notes = array();
		$plain = trim( wp_strip_all_tags( $html ) );
		$lower = strtolower( remove_accents( $html ) );
		$min_words = absint( apply_filters( 'wpai_publisher_min_full_article_words', 300 ) );
		$word_count = str_word_count( strtolower( remove_accents( $plain ) ) );
		$h2_count = preg_match_all( '/<h2\b[^>]*>/i', $html );

		if ( '' === $plain ) { $notes[] = __( 'Articolo completo vuoto.', 'wp-ai-publisher' ); }
		foreach ( array( '<!-- wp:', 'wp-block', '<script', '<iframe', ' style=', '<style', '[caption', '[gallery' ) as $needle ) { if ( false !== strpos( $lower, $needle ) ) { $notes[] = __( 'Markup non consentito nel contenuto finale.', 'wp-ai-publisher' ); break; } }
		foreach ( array( 'pubblico:', 'tono:', 'formato preferito:', 'target tecnico:', 'nota editoriale:', 'regole editoriali:', 'claim vietati:', 'termini brand:', 'contesto editoriale:', 'writing rules:', 'forbidden_claims', 'site_context', 'validation_notes', 'knowledge_summary' ) as $needle ) { if ( false !== strpos( $lower, $needle ) ) { $notes[] = __( 'Il contenuto finale contiene note interne visibili.', 'wp-ai-publisher' ); break; } }
		foreach ( array( 'spiegare', 'indicare', 'descrivere', 'mostrare', 'illustrare', 'suggerire', 'riassumere', 'chiudere', 'elencare', 'presentare', 'approfondire' ) as $needle ) { if ( 1 === preg_match( '/<(p|li)>\s*' . preg_quote( $needle, '/' ) . '\b/i', $lower ) ) { $notes[] = __( 'Il contenuto finale contiene placeholder editoriali.', 'wp-ai-publisher' ); break; } }
		foreach ( array( 'inserire qui', 'da completare', 'todo', 'lorem ipsum', 'procedi per passaggi ordinati e annota le verifiche necessarie', 'verifica il risultato finale prima di usare il contenuto in una bozza', 'traccia editoriale', 'dry-run editoriale', 'struttura preliminare', 'articolo da completare' ) as $needle ) { if ( false !== strpos( $lower, $needle ) ) { $notes[] = __( 'Il contenuto finale contiene placeholder editoriali.', 'wp-ai-publisher' ); break; } }
		if ( $h2_count < 3 ) { $notes[] = __( 'Il contenuto finale deve contenere almeno 3 sezioni H2.', 'wp-ai-publisher' ); }
		if ( $word_count < $min_words ) { $notes[] = sprintf( __( 'Il contenuto finale contiene %1$d parole: minimo richiesto %2$d.', 'wp-ai-publisher' ), $word_count, $min_words ); }
		if ( 1 === preg_match( '/^\s*[\{\[]|"(title|content_outline|validation_notes)"\s*:/i', $plain ) ) { $notes[] = __( 'Il contenuto finale sembra contenere JSON grezzo.', 'wp-ai-publisher' ); }
		if ( false !== strpos( $lower, 'prompt immagine' ) || false !== strpos( $lower, 'featured_image_prompt' ) || false !== strpos( $lower, 'internal_image_prompts' ) ) { $notes[] = __( 'Il contenuto finale contiene prompt immagine visibili.', 'wp-ai-publisher' ); }

		return array( 'valid' => empty( $notes ), 'notes' => array_values( array_unique( $notes ) ), 'word_count' => $word_count, 'h2_count' => (int) $h2_count );
	}

	/**
	 * Clean or rewrite editorial excerpts before using them in reader-facing HTML.
	 *
	 * @param string              $excerpt Dry-run excerpt.
	 * @param string              $title Article title.
	 * @param array<string,mixed> $dry_run_output Dry-run data.
	 * @return string
	 */
	public function clean_reader_excerpt( $excerpt, $title, $dry_run_output = array() ) {
		$excerpt = $this->finalize_reader_sentence( $excerpt, $title );
		$lower   = strtolower( remove_accents( $excerpt ) );
		$editorial_patterns = array( 'traccia editoriale', 'dry-run editoriale', 'per spiegare', 'struttura preliminare', 'senza creare bozze', 'senza pubblicare contenuti', 'utile per validare flusso', 'articolo da completare' );
		foreach ( $editorial_patterns as $pattern ) {
			if ( false !== strpos( $lower, $pattern ) ) {
				$topic = sanitize_text_field( (string) ( $dry_run_output['subtopic'] ?? $dry_run_output['cluster_topic'] ?? $title ) );
				$intent = sanitize_text_field( (string) ( $dry_run_output['search_intent'] ?? '' ) );
				$first_heading = '';
				if ( ! empty( $dry_run_output['content_outline'][0]['heading'] ) ) {
					$first_heading = sanitize_text_field( (string) $dry_run_output['content_outline'][0]['heading'] );
				}
				$focus = '' !== $first_heading ? $first_heading : ( '' !== $topic ? $topic : $title );
				$excerpt = sprintf(
					__( 'In questa guida vedremo %1$s con un percorso pratico, organizzato e facile da seguire. L’obiettivo è aiutarti a capire i passaggi essenziali, applicarli nel sito e controllare il risultato finale senza inserire contenuti non verificati.', 'wp-ai-publisher' ),
					'' !== $focus ? $focus : __( 'l’argomento proposto', 'wp-ai-publisher' )
				);
				if ( '' !== $intent ) {
					$excerpt .= ' ' . sprintf( __( 'Il percorso risponde a un intento di ricerca orientato a %s.', 'wp-ai-publisher' ), $intent );
				}
				break;
			}
		}

		return $this->finalize_reader_sentence( $excerpt, $title );
	}

	private function finalize_reader_sentence( $text, $fallback_topic ) {
		$text = trim( wp_strip_all_tags( (string) $text ) );
		if ( '' === $text || $this->looks_like_placeholder_summary( $text ) ) {
			$text = sprintf( __( 'Questa guida accompagna il lettore nella gestione di %s con spiegazioni pratiche, esempi chiari e controlli finali utili per evitare errori comuni.', 'wp-ai-publisher' ), '' !== $fallback_topic ? $fallback_topic : __( 'questo argomento', 'wp-ai-publisher' ) );
		}
		return $text;
	}

	private function expand_summary_to_reader_paragraphs( $heading, $summary, $dry_run_output ) {
		$summary = $this->finalize_reader_sentence( $summary, $heading );
		$topic = sanitize_text_field( (string) ( $dry_run_output['title'] ?? $dry_run_output['cluster_topic'] ?? $heading ) );
		return array(
			$summary,
			sprintf( __( 'In pratica, questa parte aiuta a collegare “%1$s” al risultato che il lettore vuole ottenere. Conviene procedere con calma, controllare ogni impostazione rilevante e confrontare il risultato con le esigenze reali del sito.', 'wp-ai-publisher' ), $heading ),
			sprintf( __( 'Per %1$s è utile mantenere un approccio semplice: parti dagli elementi essenziali, evita modifiche non necessarie e verifica sempre che il contenuto rimanga chiaro, accessibile e coerente con l’obiettivo dell’articolo.', 'wp-ai-publisher' ), '' !== $topic ? $topic : $heading ),
		);
	}

	/**
	 * Build introductory paragraph.
	 *
	 * @param array<string,mixed> $dry_run_output Structured dry-run output.
	 * @return string
	 */
	public function build_intro( $dry_run_output ) {
		$title   = sanitize_text_field( (string) ( $dry_run_output['title'] ?? '' ) );
		$excerpt = sanitize_textarea_field( (string) ( $dry_run_output['excerpt'] ?? '' ) );
		$topic   = sanitize_textarea_field( (string) ( $dry_run_output['subtopic'] ?? $dry_run_output['cluster_topic'] ?? $title ) );

		if ( '' === $excerpt ) {
			$excerpt = sprintf(
				__( 'In questo contenuto vedremo %s con un percorso ordinato, pratico e pensato per HTML pulito in Editor Classico.', 'wp-ai-publisher' ),
				'' !== $topic ? $topic : __( 'l’argomento proposto', 'wp-ai-publisher' )
			);
		}

		return '<p>' . esc_html( $excerpt ) . '</p>';
	}

	/**
	 * Build outline sections as H2/H3 Classic Editor HTML.
	 *
	 * @param array<string,mixed> $dry_run_output Structured dry-run output.
	 * @return string
	 */
	public function build_outline_sections( $dry_run_output ) {
		$outline = isset( $dry_run_output['content_outline'] ) && is_array( $dry_run_output['content_outline'] ) ? $dry_run_output['content_outline'] : array();
		if ( empty( $outline ) ) {
			return '';
		}

		$html = '';
		if ( count( $outline ) >= 4 ) {
			$html .= '<h2>' . esc_html__( 'Indice dei contenuti', 'wp-ai-publisher' ) . '</h2>' . "\n";
			$html .= '<ul>' . "\n";
			foreach ( $outline as $section ) {
				$heading = sanitize_text_field( (string) ( is_array( $section ) ? ( $section['heading'] ?? '' ) : $section ) );
				if ( '' !== $heading ) {
					$html .= '<li>' . esc_html( $heading ) . '</li>' . "\n";
				}
			}
			$html .= '</ul>' . "\n\n";
		}

		foreach ( $outline as $section ) {
			if ( ! is_array( $section ) ) {
				$section = array(
					'heading' => (string) $section,
					'level'   => 2,
					'summary' => '',
				);
			}

			$heading = sanitize_text_field( (string) ( $section['heading'] ?? '' ) );
			$summary = sanitize_textarea_field( (string) ( $section['summary'] ?? '' ) );
			$level   = absint( $section['level'] ?? 2 );
			$tag     = $level >= 3 ? 'h3' : 'h2';

			if ( '' === $heading ) {
				continue;
			}

			if ( '' === $summary || $this->looks_like_placeholder_summary( $summary ) ) {
				$summary = $this->build_editorial_summary_from_heading( $heading );
			}

			$html .= sprintf( '<%1$s>%2$s</%1$s>', $tag, esc_html( $heading ) ) . "\n";
			$html .= '<p>' . esc_html( $summary ) . '</p>' . "\n";

			if ( $this->section_should_have_checklist( $heading ) ) {
				$html .= '<ul>' . "\n";
				foreach ( $this->build_section_bullets( $heading ) as $bullet ) {
					$html .= '<li>' . esc_html( $bullet ) . '</li>' . "\n";
				}
				$html .= '</ul>' . "\n";
			}
		}

		return $html;
	}

	/**
	 * Detect whether the outline already contains a final conclusion section.
	 *
	 * @param array<string,mixed> $dry_run_output Structured dry-run output.
	 * @return bool
	 */
	private function outline_has_conclusion_section( $dry_run_output ) {
		$outline = isset( $dry_run_output['content_outline'] ) && is_array( $dry_run_output['content_outline'] ) ? $dry_run_output['content_outline'] : array();
		foreach ( $outline as $section ) {
			$heading = is_array( $section ) ? (string) ( $section['heading'] ?? '' ) : (string) $section;
			$heading = strtolower( remove_accents( sanitize_text_field( $heading ) ) );
			foreach ( array( 'conclusione', 'conclusioni', 'prossimi passi', 'riepilogo finale' ) as $needle ) {
				if ( false !== strpos( $heading, $needle ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Build conclusion paragraph.
	 *
	 * @param array<string,mixed> $dry_run_output Structured dry-run output.
	 * @return string
	 */
	public function build_conclusion( $dry_run_output ) {
		$title = sanitize_text_field( (string) ( $dry_run_output['title'] ?? __( 'il contenuto', 'wp-ai-publisher' ) ) );

		return '<h2>' . esc_html__( 'Conclusione', 'wp-ai-publisher' ) . '</h2>' . "\n" . '<p>' . esc_html( sprintf( __( 'Con “%s” hai un percorso ordinato da seguire e controllare. Rileggi i passaggi principali, verifica il risultato nel sito reale e aggiorna il contenuto quando cambiano strumenti o impostazioni.', 'wp-ai-publisher' ), $title ) ) . '</p>';
	}

	/**
	 * Sanitize generated HTML with a Classic Editor allowlist.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	public function sanitize_classic_html( $html ) {
		return wp_kses( (string) $html, $this->get_allowed_html() );
	}

	/**
	 * Return allowed HTML tags and safe attributes for Classic Editor previews.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_allowed_html() {
		return array(
			'p'          => array(),
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'strong'     => array(),
			'em'         => array(),
			'a'          => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
			),
			'img'        => array(
				'src'    => true,
				'alt'    => true,
				'title'  => true,
				'width'  => true,
				'height' => true,
			),
			'blockquote' => array(),
			'code'       => array(),
			'pre'        => array(),
			'br'         => array(),
		);
	}

	/**
	 * Decide whether a section benefits from a small checklist.
	 *
	 * @param string $heading Section heading.
	 * @return bool
	 */
	private function section_should_have_checklist( $heading ) {
		$heading = strtolower( remove_accents( $heading ) );
		foreach ( array( 'prima', 'requisiti', 'verifica', 'errori', 'controlli', 'aggiungere', 'ordinare', 'configurazione', 'installazione' ) as $keyword ) {
			if ( false !== strpos( $heading, $keyword ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect generic placeholder sentences in section summaries.
	 *
	 * @param string $summary Summary text.
	 * @return bool
	 */
	private function looks_like_placeholder_summary( $summary ) {
		$summary = strtolower( remove_accents( (string) $summary ) );
		foreach ( array( 'descrivere in modo pratico', 'descrivere in modo verificabile', 'descrivere il passaggio', 'nel contesto di', 'evitando dettagli tecnici non confermati', 'passaggio “', 'passaggio "' ) as $needle ) {
			if ( false !== strpos( $summary, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a concrete editorial paragraph when the source summary is missing.
	 *
	 * @param string $heading Section heading.
	 * @return string
	 */
	private function build_editorial_summary_from_heading( $heading ) {
		$normalized = strtolower( remove_accents( (string) $heading ) );

		if ( false !== strpos( $normalized, 'widget' ) ) {
			return __( 'I widget permettono di aggiungere contenuti e funzioni nelle aree predisposte dal tema. Controlla posizione, visibilità e coerenza prima del salvataggio definitivo, così il sito resta ordinato e comprensibile per chi lo visita.', 'wp-ai-publisher' );
		}

		if ( false !== strpos( $normalized, 'menu' ) || false !== strpos( $normalized, 'navigazione' ) ) {
			return __( 'La navigazione del sito deve aiutare il visitatore a trovare rapidamente le pagine principali. Ordina le voci in modo logico, usa etichette chiare e verifica che ogni collegamento sia davvero utile.', 'wp-ai-publisher' );
		}

		if ( false !== strpos( $normalized, 'wpml' ) || false !== strpos( $normalized, 'traduz' ) || false !== strpos( $normalized, 'lingu' ) ) {
			return __( 'La gestione multilingua richiede attenzione a traduzioni, URL, menu e contenuti collegati. Ogni lingua attiva dovrebbe offrire un percorso coerente e completo per il visitatore.', 'wp-ai-publisher' );
		}

		return sprintf( __( 'La sezione “%s” introduce gli aspetti pratici più importanti, indica cosa controllare e aiuta a mantenere il risultato coerente con l’obiettivo del contenuto.', 'wp-ai-publisher' ), sanitize_text_field( (string) $heading ) );
	}

	/**
	 * Build contextual bullets for sections that benefit from a checklist.
	 *
	 * @param string $heading Section heading.
	 * @return array<int,string>
	 */
	private function build_section_bullets( $heading ) {
		$normalized = strtolower( remove_accents( (string) $heading ) );

		if ( false !== strpos( $normalized, 'errori' ) ) {
			return array(
				__( 'Evita modifiche non documentate o difficili da annullare.', 'wp-ai-publisher' ),
				__( 'Controlla desktop e mobile prima di considerare conclusa la modifica.', 'wp-ai-publisher' ),
			);
		}

		if ( false !== strpos( $normalized, 'verifica' ) ) {
			return array(
				__( 'Apri il sito pubblico in una nuova finestra e controlla il risultato reale.', 'wp-ai-publisher' ),
				__( 'Ripeti il controllo da mobile o con una larghezza schermo ridotta.', 'wp-ai-publisher' ),
			);
		}

		return array(
			__( 'Lavora un passaggio alla volta e conserva solo le modifiche davvero utili.', 'wp-ai-publisher' ),
			__( 'Controlla il risultato finale nel sito prima di considerare concluso il lavoro.', 'wp-ai-publisher' ),
		);
	}
}
