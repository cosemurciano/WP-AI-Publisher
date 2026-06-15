<?php
/**
 * Unit tests for settings normalization (clamping/fallbacks).
 *
 * @package WPAIPublisher
 */

use PHPUnit\Framework\TestCase;

final class SettingsSanitizeTest extends TestCase {

	public function test_timeout_is_clamped() {
		$this->assertSame( 600, wpai_publisher_normalize_settings( array( 'ai_http_timeout' => 99999 ) )['ai_http_timeout'] );
		$this->assertSame( 15, wpai_publisher_normalize_settings( array( 'ai_http_timeout' => 1 ) )['ai_http_timeout'] );
		$this->assertSame( 180, wpai_publisher_normalize_settings( array() )['ai_http_timeout'] );
	}

	public function test_max_output_tokens_is_clamped() {
		$this->assertSame( 32000, wpai_publisher_normalize_settings( array( 'ai_max_output_tokens' => 999999 ) )['ai_max_output_tokens'] );
		$this->assertSame( 0, wpai_publisher_normalize_settings( array( 'ai_max_output_tokens' => -5 ) )['ai_max_output_tokens'] );
	}

	public function test_temperature_empty_stays_empty_and_out_of_range_clamps() {
		$this->assertSame( '', wpai_publisher_normalize_settings( array( 'ai_temperature' => '' ) )['ai_temperature'] );
		$this->assertSame( '2', wpai_publisher_normalize_settings( array( 'ai_temperature' => '5' ) )['ai_temperature'] );
	}

	public function test_booleans_and_legacy_keys() {
		$normalized = wpai_publisher_normalize_settings( array( 'generate_featured_image' => '1', 'monthly_cost_limit' => '10' ) );
		$this->assertTrue( $normalized['generate_featured_image'] );
		// Legacy keys are dropped.
		$this->assertArrayNotHasKey( 'monthly_cost_limit', $normalized );
		$this->assertArrayNotHasKey( 'ai_provider_preference', $normalized );
	}
}
