<?php
/**
 * Structured output validator.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates and normalizes structured dry-run output.
 */
class Structured_Output_Validator {
	/**
	 * Validate a content dry-run output.
	 *
	 * @param mixed $output Raw output array or JSON string.
	 * @return array{output:array<string,mixed>,is_valid:bool,validation_notes:array<int,string>}
	 */
	public function validate_content_dry_run( $output ) {
		$normalized = $this->normalize_content_dry_run( $output );
		$notes      = $normalized['validation_notes'];
		$is_valid   = true;

		foreach ( $this->get_required_fields() as $field ) {
			if ( ! array_key_exists( $field, $normalized ) || '' === $normalized[ $field ] || array() === $normalized[ $field ] ) {
				$notes[]  = sprintf( __( 'Campo critico mancante o vuoto: %s.', 'wp-ai-publisher' ), $field );
				$is_valid = false;
			}
		}

		if ( empty( $normalized['content_outline'] ) || ! is_array( $normalized['content_outline'] ) ) {
			$notes[]  = __( 'content_outline deve contenere almeno una sezione valida.', 'wp-ai-publisher' );
			$is_valid = false;
		}

		foreach ( $normalized['content_outline'] as $section ) {
			if ( empty( $section['heading'] ) || empty( $section['summary'] ) || empty( $section['level'] ) ) {
				$notes[]  = __( 'Ogni sezione di content_outline deve avere heading, level e summary.', 'wp-ai-publisher' );
				$is_valid = false;
				break;
			}
		}

		$classic_notes = $this->validate_classic_editor_preview( $normalized['classic_editor_preview'] ?? array() );
		if ( ! empty( $classic_notes ) ) {
			$is_valid = false;
			$notes    = array_merge( $notes, $classic_notes );
		}

		$normalized['validation_notes'] = array_values( array_unique( array_filter( array_map( 'strval', $notes ) ) ) );

		return array(
			'output'           => $normalized,
			'is_valid'         => $is_valid,
			'validation_notes' => $normalized['validation_notes'],
		);
	}

	/**
	 * Normalize a content dry-run output.
	 *
	 * @param mixed $output Raw output array or JSON string.
	 * @return array<string,mixed>
	 */
	public function normalize_content_dry_run( $output ) {
		$notes = array();

		if ( is_string( $output ) ) {
			$decoded = json_decode( $output, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$output  = $decoded;
				$notes[] = __( 'Output JSON decodificato e normalizzato.', 'wp-ai-publisher' );
			} else {
				$output  = array();
				$notes[] = __( 'Output non decodificabile come JSON valido.', 'wp-ai-publisher' );
			}
		}

		if ( is_object( $output ) ) {
			$encoded = wp_json_encode( $output );
			$output  = false !== $encoded ? json_decode( $encoded, true ) : array();
			$notes[] = __( 'Output oggetto convertito in array.', 'wp-ai-publisher' );
		}

		if ( ! is_array( $output ) ) {
			$output  = array();
			$notes[] = __( 'Output non valido: è stato creato un contenitore vuoto.', 'wp-ai-publisher' );
		}

		$defaults = array(
			'title'                  => '',
			'slug'                   => '',
			'excerpt'                => '',
			'content_outline'        => array(),
			'category_ids'            => array(),
			'categories'             => array(),
			'tags'                   => array(),
			'meta_title'             => '',
			'meta_description'       => '',
			'open_graph_title'       => '',
			'open_graph_description' => '',
			'twitter_title'          => '',
			'twitter_description'    => '',
			'featured_image_prompt'  => '',
			'internal_image_prompts' => array(),
			'image_alt_texts'        => array(),
			'image_captions'         => array(),
			'internal_link_targets'  => array(),
			'knowledge_summary'      => '',
			'entities'               => array(),
			'search_intent'          => '',
			'tutorial_level'         => '',
			'cluster_topic'          => '',
			'subtopic'               => '',
			'validation_notes'       => array(),
			'language'               => 'it',
			'source'                 => 'unknown',
			'classic_editor_preview' => array(
				'html'               => '',
				'plain_text_summary' => '',
				'validation_notes'   => array(),
			),
		);

		foreach ( $defaults as $field => $default ) {
			if ( ! array_key_exists( $field, $output ) ) {
				$output[ $field ] = $default;
				if ( ! in_array( $field, $this->get_required_fields(), true ) && 'classic_editor_preview' !== $field ) {
					$notes[] = sprintf( __( 'Campo non critico aggiunto con valore predefinito: %s.', 'wp-ai-publisher' ), $field );
				}
			}
		}

		foreach ( array( 'category_ids', 'categories', 'tags', 'internal_image_prompts', 'image_alt_texts', 'image_captions', 'internal_link_targets', 'entities', 'validation_notes' ) as $array_field ) {
			if ( ! is_array( $output[ $array_field ] ) ) {
				$output[ $array_field ] = '' === (string) $output[ $array_field ] ? array() : array( (string) $output[ $array_field ] );
				$notes[] = sprintf( __( 'Campo convertito in array: %s.', 'wp-ai-publisher' ), $array_field );
			}
			if ( 'category_ids' === $array_field ) { $output[ $array_field ] = array_values( array_filter( array_map( 'absint', $output[ $array_field ] ) ) ); } else { $output[ $array_field ] = array_values( array_filter( array_map( 'strval', $output[ $array_field ] ) ) ); }
		}

		$output['content_outline'] = $this->normalize_outline( $output['content_outline'], $notes );
		$output['tutorial_level']  = $this->normalize_tutorial_level( (string) $output['tutorial_level'] );
		$output['language']        = '' !== sanitize_key( (string) $output['language'] ) ? sanitize_key( (string) $output['language'] ) : 'it';
		$output['source']          = in_array( $output['source'], array( 'wordpress_ai', 'local_fallback' ), true ) ? $output['source'] : 'unknown';
		$output['slug']            = '' !== (string) $output['slug'] ? sanitize_title( (string) $output['slug'] ) : '';
		$output['validation_notes'] = array_values( array_unique( array_filter( array_merge( $output['validation_notes'], $notes ) ) ) );

		return $output;
	}

	/**
	 * Return minimum required fields for a valid dry-run.
	 *
	 * @return array<int,string>
	 */
	public function get_required_fields() {
		return array( 'title', 'slug', 'excerpt', 'content_outline', 'categories', 'tags', 'meta_title', 'meta_description' );
	}

	/**
	 * Determine whether a value is an associative array.
	 *
	 * @param mixed $value Value to inspect.
	 * @return bool
	 */
	public function is_assoc_array( $value ) {
		return is_array( $value ) && array() !== $value && array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}

	/**
	 * Normalize outline sections.
	 *
	 * @param mixed             $outline Raw outline.
	 * @param array<int,string> $notes Validation notes passed by reference.
	 * @return array<int,array{heading:string,level:int,summary:string}>
	 */
	private function normalize_outline( $outline, &$notes ) {
		if ( ! is_array( $outline ) ) {
			$notes[] = __( 'content_outline convertito in array vuoto perché non era un array.', 'wp-ai-publisher' );
			return array();
		}

		if ( $this->is_assoc_array( $outline ) ) {
			$outline = array( $outline );
			$notes[] = __( 'content_outline associativo convertito in lista di sezioni.', 'wp-ai-publisher' );
		}

		$normalized = array();
		foreach ( $outline as $section ) {
			if ( is_string( $section ) ) {
				$section = array(
					'heading' => $section,
					'level'   => 2,
					'summary' => '',
				);
				$notes[] = __( 'Sezione testuale convertita in oggetto outline.', 'wp-ai-publisher' );
			}

			if ( ! is_array( $section ) ) {
				$notes[] = __( 'Sezione content_outline ignorata perché non valida.', 'wp-ai-publisher' );
				continue;
			}

			$level = $section['level'] ?? 2;
			if ( is_string( $level ) && is_numeric( $level ) ) {
				$level   = (int) $level;
				$notes[] = __( 'Level numerico convertito da stringa a intero.', 'wp-ai-publisher' );
			}

			$level = absint( $level );
			if ( $level < 2 || $level > 6 ) {
				$level   = 2;
				$notes[] = __( 'Level outline fuori intervallo normalizzato a 2.', 'wp-ai-publisher' );
			}

			$normalized[] = array(
				'heading' => sanitize_text_field( (string) ( $section['heading'] ?? $section['title'] ?? '' ) ),
				'level'   => $level,
				'summary' => sanitize_textarea_field( (string) ( $section['summary'] ?? $section['description'] ?? '' ) ),
			);
		}

		return $normalized;
	}

	/**
	 * Validate Classic Editor preview safety constraints when present.
	 *
	 * @param mixed $preview Preview data.
	 * @return array<int,string>
	 */
	private function validate_classic_editor_preview( $preview ) {
		if ( empty( $preview ) || ! is_array( $preview ) ) {
			return array();
		}

		$notes = array();
		$html  = (string) ( $preview['html'] ?? '' );

		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			$notes[] = __( 'classic_editor_preview.html è vuoto.', 'wp-ai-publisher' );
		}

		$checks = array(
			'<!-- wp:' => __( 'Nota grave: classic_editor_preview contiene markup Gutenberg.', 'wp-ai-publisher' ),
			'wp-block' => __( 'Nota grave: classic_editor_preview contiene classi o stringhe Gutenberg.', 'wp-ai-publisher' ),
			'<script'  => __( 'Nota grave: classic_editor_preview contiene script non consentiti.', 'wp-ai-publisher' ),
			'<iframe'  => __( 'Nota grave: classic_editor_preview contiene iframe non consentiti.', 'wp-ai-publisher' ),
			' style='  => __( 'Nota grave: classic_editor_preview contiene style inline non consentiti.', 'wp-ai-publisher' ),
			'la sezione “' => __( 'Nota grave: classic_editor_preview contiene testo placeholder.', 'wp-ai-publisher' ),
			'la sezione "' => __( 'Nota grave: classic_editor_preview contiene testo placeholder.', 'wp-ai-publisher' ),
			'introduce gli aspetti pratici più importanti' => __( 'Nota grave: classic_editor_preview contiene testo placeholder.', 'wp-ai-publisher' ),
			'indica cosa controllare' => __( 'Nota grave: classic_editor_preview contiene testo placeholder.', 'wp-ai-publisher' ),
			'aiuta a mantenere il risultato coerente' => __( 'Nota grave: classic_editor_preview contiene testo placeholder.', 'wp-ai-publisher' ),
		);

		$lower_html = strtolower( $html );
		foreach ( $checks as $needle => $message ) {
			if ( false !== strpos( $lower_html, strtolower( $needle ) ) ) {
				$notes[] = $message;
			}
		}

		return array_values( array_unique( array_filter( $notes ) ) );
	}

	/**
	 * Normalize tutorial level.
	 *
	 * @param string $level Raw level.
	 * @return string
	 */
	private function normalize_tutorial_level( $level ) {
		$level = sanitize_key( $level );
		return in_array( $level, array( 'base', 'intermedio', 'avanzato' ), true ) ? $level : 'base';
	}
}
