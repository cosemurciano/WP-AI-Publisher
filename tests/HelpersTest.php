<?php
/**
 * Unit tests for pure plugin helpers.
 *
 * @package WPAIPublisher
 */

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase {

	public function test_split_context_list_trims_dedupes_and_reindexes() {
		$result = wpai_publisher_split_context_list( "alpha, beta\nalpha,  gamma " );

		$this->assertSame( array( 'alpha', 'beta', 'gamma' ), $result );
	}

	public function test_split_context_list_handles_empty_input() {
		$this->assertSame( array(), wpai_publisher_split_context_list( '' ) );
	}

	public function test_normalize_default_tone_maps_legacy_values() {
		$this->assertSame(
			'chiaro_didattico_e_operativo',
			wpai_publisher_normalize_default_tone( 'chiarodidatticoeoperativo' )
		);
		$this->assertSame(
			'chiaro_didattico_e_operativo',
			wpai_publisher_normalize_default_tone( 'chiaro_didattico_operativo' )
		);
	}

	public function test_normalize_default_tone_passes_through_known_values() {
		$this->assertSame(
			'professionale_tecnico',
			wpai_publisher_normalize_default_tone( 'professionale_tecnico' )
		);
	}
}
