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

	public function test_generation_params_defaults() {
		$params = $this->adapter->get_ai_generation_params();
		$this->assertSame( '', $params['model'] );
		$this->assertSame( 180, $params['http_timeout'] );
		$this->assertSame( 4000, $params['max_output_tokens'] );
		// Temperature is opt-in: not sent by default (avoids the reasoning-model 400).
		$this->assertNull( $params['temperature'] );
	}

	/**
	 * @dataProvider imagePayloadProvider
	 */
	public function test_extract_image_file_decodes_payloads( $payload, $expected_mime ) {
		$method = new ReflectionMethod( $this->adapter, 'extract_image_file' );
		$method->setAccessible( true );
		$image = $method->invoke( $this->adapter, $payload );
		$this->assertIsArray( $image );
		$this->assertNotEmpty( $image['bytes'] );
		$this->assertSame( $expected_mime, $image['mime'] );
	}

	public function imagePayloadProvider(): array {
		$b64 = base64_encode( "\x89PNG\r\n\x1a\nFAKE-IMAGE-BYTES-1234567890" );
		return array(
			'data-uri' => array( 'data:image/png;base64,' . $b64, 'image/png' ),
			'array'    => array( array( 'mimeType' => 'image/jpeg', 'base64Data' => $b64 ), 'image/jpeg' ),
		);
	}

	public function test_extract_image_file_reads_object_accessors() {
		$b64    = base64_encode( "\x89PNG\r\n\x1a\nFAKE-IMAGE-BYTES" );
		$result = new class( $b64 ) {
			private $b;
			public function __construct( $b ) { $this->b = $b; }
			public function getMimeType() { return 'image/webp'; }
			public function getBase64Data() { return $this->b; }
		};
		$method = new ReflectionMethod( $this->adapter, 'extract_image_file' );
		$method->setAccessible( true );
		$image = $method->invoke( $this->adapter, $result );
		$this->assertIsArray( $image );
		$this->assertSame( 'image/webp', $image['mime'] );
		$this->assertNotEmpty( $image['bytes'] );
	}
}
