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
			$this->build_conclusion( $dry_run_output ),
		);

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
				__( 'In questa guida vedremo %s con un percorso ordinato, pratico e pensato per l’Editor Classico di WordPress.', 'wp-ai-publisher' ),
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
	 * Build conclusion paragraph.
	 *
	 * @param array<string,mixed> $dry_run_output Structured dry-run output.
	 * @return string
	 */
	public function build_conclusion( $dry_run_output ) {
		$title = sanitize_text_field( (string) ( $dry_run_output['title'] ?? __( 'il contenuto', 'wp-ai-publisher' ) ) );

		return '<h2>' . esc_html__( 'Conclusione', 'wp-ai-publisher' ) . '</h2>' . "\n" . '<p>' . esc_html( sprintf( __( 'Prima di trasformare “%s” in una bozza reale, conviene rileggere la struttura, verificare i passaggi tecnici e completare eventuali esempi specifici del sito.', 'wp-ai-publisher' ), $title ) ) . '</p>';
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
			return __( 'Spiegare il ruolo dei widget nelle aree predisposte dal tema e indicare cosa controllare prima di salvarli sul sito pubblico.', 'wp-ai-publisher' );
		}

		if ( false !== strpos( $normalized, 'menu' ) || false !== strpos( $normalized, 'navigazione' ) ) {
			return __( 'Collegare la sezione alla navigazione del sito, chiarendo come ordinare le voci e verificare che ogni link sia utile per l’utente.', 'wp-ai-publisher' );
		}

		if ( false !== strpos( $normalized, 'wpml' ) || false !== strpos( $normalized, 'traduz' ) || false !== strpos( $normalized, 'lingu' ) ) {
			return __( 'Inquadrare la gestione multilingua, ricordando di verificare traduzioni, URL, menu e contenuti collegati in ogni lingua attiva.', 'wp-ai-publisher' );
		}

		return sprintf( __( 'Presentare “%s” con indicazioni operative, controlli nel pannello WordPress e una verifica finale dal front-end.', 'wp-ai-publisher' ), sanitize_text_field( (string) $heading ) );
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
			__( 'Salva una modifica alla volta e annota cosa è stato cambiato.', 'wp-ai-publisher' ),
			__( 'Verifica il risultato dal pannello di amministrazione e dal front-end.', 'wp-ai-publisher' ),
		);
	}
}
