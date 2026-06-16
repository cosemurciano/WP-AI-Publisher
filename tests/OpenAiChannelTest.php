<?php
/**
 * Unit tests for the OpenAI Responses (file_search) channel helpers.
 *
 * @package WPAIPublisher
 */

use PHPUnit\Framework\TestCase;

final class OpenAiChannelTest extends TestCase {

	public function test_vector_store_ids_parsing() {
		$this->assertSame(
			array( 'vs_one', 'vs_two', 'vs_three' ),
			wpai_publisher_get_openai_vector_store_ids( "vs_one, vs_two\nvs_three" )
		);
	}

	public function test_vector_store_ids_dedupe_and_reject_invalid() {
		$this->assertSame(
			array( 'vs_a', 'vs_b' ),
			wpai_publisher_get_openai_vector_store_ids( 'vs_a vs_a vs_b bad!token vs_a' )
		);
	}

	public function test_vector_store_ids_empty() {
		$this->assertSame( array(), wpai_publisher_get_openai_vector_store_ids( '   ' ) );
	}

	public function test_api_key_empty_without_constant_or_filter() {
		$this->assertSame( '', wpai_publisher_get_openai_api_key() );
	}

	public function test_api_key_from_filter() {
		add_filter( 'wpai_publisher_openai_api_key', static function () {
			return '  sk-test-123  ';
		} );
		$this->assertSame( 'sk-test-123', wpai_publisher_get_openai_api_key() );
	}

	public function test_telegram_chat_ids_parsing() {
		$this->assertSame(
			array( '123456789', '-1009876543210' ),
			wpai_publisher_get_telegram_allowed_chat_ids( "123456789, -1009876543210\nnot-an-id 123456789" )
		);
		$this->assertSame( array(), wpai_publisher_get_telegram_allowed_chat_ids( '   ' ) );
	}

	public function test_telegram_secret_from_filter() {
		add_filter( 'wpai_publisher_telegram_secret_token', static function () {
			return '  s3cr3t  ';
		} );
		$this->assertSame( 's3cr3t', wpai_publisher_get_telegram_secret_token() );
	}
}
