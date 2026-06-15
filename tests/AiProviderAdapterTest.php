<?php
/**
 * Unit tests for the AI provider adapter detection surface.
 *
 * These tests pin the speculative probing lists so accidental edits that empty
 * or break them are caught early. They do not require a WordPress installation.
 *
 * @package WPAIPublisher
 */

use PHPUnit\Framework\TestCase;
use WPAIPublisher\AI_Provider_Adapter;

final class AiProviderAdapterTest extends TestCase {

	/**
	 * @var AI_Provider_Adapter
	 */
	private $adapter;

	protected function setUp(): void {
		require_once dirname( __DIR__ ) . '/includes/class-ai-provider-adapter.php';
		$this->adapter = new AI_Provider_Adapter();
	}

	/**
	 * @dataProvider indicatorListProvider
	 */
	public function test_indicator_lists_are_non_empty_string_arrays( $method ) {
		$list = $this->adapter->{$method}();

		$this->assertIsArray( $list );
		$this->assertNotEmpty( $list );
		foreach ( $list as $value ) {
			$this->assertIsString( $value );
			$this->assertNotSame( '', $value );
		}
	}

	public function test_indicator_lists_have_no_duplicates() {
		foreach ( $this->indicatorListProvider() as $case ) {
			$list = $this->adapter->{$case[0]}();
			$this->assertSame( array_values( array_unique( $list ) ), array_values( $list ), $case[0] . ' contains duplicates' );
		}
	}

	public function indicatorListProvider(): array {
		return array(
			array( 'get_ai_indicator_classes' ),
			array( 'get_ai_indicator_functions' ),
			array( 'get_model_discovery_functions' ),
			array( 'get_model_client_factories' ),
			array( 'get_ability_discovery_functions' ),
		);
	}
}
