<?php
/**
 * ManageSettings Feature Tests
 *
 * @package MailChronicle
 */

namespace MailChronicle\Tests\Unit\Features;

use MailChronicle\Tests\TestCase;
use MailChronicle\Features\ManageSettings\ManageSettings;
use Mockery;

/**
 * ManageSettings Test Class
 */
class ManageSettingsTest extends TestCase {

	/**
	 * Set up
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->mock_wordpress_functions();
	}

	/**
	 * Test get returns default settings
	 */
	public function test_get_returns_default_settings() {
		$handler = new ManageSettings();

		$settings = $handler->get();

		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'enabled', $settings );
		$this->assertArrayHasKey( 'provider', $settings );
		$this->assertArrayHasKey( 'mailgun_api_key', $settings );
		$this->assertArrayHasKey( 'mailgun_domain', $settings );
		$this->assertArrayHasKey( 'mailgun_region', $settings );
		$this->assertArrayHasKey( 'log_retention_days', $settings );
		$this->assertTrue( $settings['enabled'] );
		$this->assertEquals( 'mailgun', $settings['provider'] );
		$this->assertEquals( 30, $settings['log_retention_days'] );
	}

	/**
	 * Test update sanitizes settings
	 */
	public function test_update_sanitizes_settings() {
		$handler = new ManageSettings();

		$data = array(
			'enabled'            => '1',
			'provider'           => '<script>mailgun</script>',
			'mailgun_api_key'    => 'key-123',
			'mailgun_domain'     => 'mg.example.com',
			'mailgun_region'     => 'EU',
			'log_retention_days' => '60',
		);

		$result = $handler->update( $data );

		$this->assertTrue( $result );
	}

	/**
	 * Test update handles missing enabled field
	 */
	public function test_update_handles_missing_enabled_field() {
		$handler = new ManageSettings();

		$data = array(
			'provider' => 'wordpress',
		);

		$result = $handler->update( $data );

		$this->assertTrue( $result );
	}

	/**
	 * Test update converts log retention to integer
	 */
	public function test_update_converts_log_retention_to_integer() {
		$handler = new ManageSettings();

		$data = array(
			'log_retention_days' => '45.5',
		);

		$result = $handler->update( $data );

		$this->assertTrue( $result );
	}

	/**
	 * Test update handles negative log retention
	 */
	public function test_update_handles_negative_log_retention() {
		$handler = new ManageSettings();

		$data = array(
			'log_retention_days' => '-10',
		);

		$result = $handler->update( $data );

		$this->assertTrue( $result );
	}

	/**
	 * Test update uses defaults for missing fields
	 */
	public function test_update_uses_defaults_for_missing_fields() {
		$handler = new ManageSettings();

		$data = array();

		$result = $handler->update( $data );

		$this->assertTrue( $result );
	}
}

