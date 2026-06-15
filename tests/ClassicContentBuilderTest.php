<?php
/**
 * Unit tests for Classic_Content_Builder normalization/validation.
 *
 * @package WPAIPublisher
 */

use PHPUnit\Framework\TestCase;
use WPAIPublisher\Classic_Content_Builder;

final class ClassicContentBuilderTest extends TestCase {

	private function builder() {
		return new Classic_Content_Builder( array(), array() );
	}

	public function test_validate_accepts_substantial_article() {
		$para    = str_repeat( 'parola ', 120 );
		$html    = "<h2>Uno</h2><p>$para</p><h2>Due</h2><p>$para</p><h2>Tre</h2><p>$para</p>";
		$result  = $this->builder()->validate_publishable_article_html( $html );
		$this->assertTrue( $result['valid'] );
		$this->assertGreaterThanOrEqual( 3, $result['h2_count'] );
		$this->assertGreaterThanOrEqual( 300, $result['word_count'] );
	}

	public function test_validate_flags_short_article_with_few_headings() {
		$result = $this->builder()->validate_publishable_article_html( '<h2>Breve</h2><p>Poche parole.</p>' );
		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['notes'] );
	}

	public function test_validate_flags_empty_article() {
		$result = $this->builder()->validate_publishable_article_html( '' );
		$this->assertFalse( $result['valid'] );
	}

	public function test_normalize_strips_gutenberg_script_and_style() {
		$raw  = "<!-- wp:paragraph --><p style=\"color:red\">Ciao</p><!-- /wp:paragraph --><script>alert(1)</script>";
		$html = $this->builder()->normalize_full_article_html( $raw, array() );
		$this->assertStringNotContainsString( '<!-- wp:', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'style=', $html );
		$this->assertStringContainsString( 'Ciao', $html );
	}

	public function test_contains_placeholder_text_detects_known_phrases() {
		$this->assertTrue( $this->builder()->contains_placeholder_text( 'Testo con lorem ipsum dolor.' ) );
		$this->assertFalse( $this->builder()->contains_placeholder_text( 'Un paragrafo editoriale concreto e utile.' ) );
	}
}
