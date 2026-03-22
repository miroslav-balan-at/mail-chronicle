<?php
/**
 * Email Entity Tests
 *
 * @package MailChronicle
 */

namespace MailChronicle\Tests\Unit\Common;

use MailChronicle\Tests\TestCase;
use MailChronicle\Common\Entities\Email;

/**
 * Email Entity Test Class
 */
class EmailEntityTest extends TestCase {

	/**
	 * Test constructor sets properties
	 */
	public function test_constructor_sets_properties() {
		$data = array(
			'id'                  => 123,
			'provider_message_id' => 'msg-123',
			'provider'            => 'mailgun',
			'recipient'           => 'test@example.com',
			'subject'             => 'Test Subject',
			'message_html'        => '<p>Test</p>',
			'message_plain'       => 'Test',
			'headers'             => 'Content-Type: text/html',
			'attachments'         => '[]',
			'status'              => 'sent',
			'sent_at'             => '2026-03-19 10:00:00',
			'created_at'          => '2026-03-19 10:00:00',
			'updated_at'          => '2026-03-19 10:00:00',
		);

		$email = new Email( $data );

		$this->assertEquals( 123, $email->get_id() );
		$this->assertEquals( 'msg-123', $email->get_provider_message_id() );
		$this->assertEquals( 'mailgun', $email->get_provider() );
		$this->assertEquals( 'test@example.com', $email->get_recipient() );
		$this->assertEquals( 'Test Subject', $email->get_subject() );
		$this->assertEquals( '<p>Test</p>', $email->get_message_html() );
		$this->assertEquals( 'Test', $email->get_message_plain() );
		$this->assertEquals( 'Content-Type: text/html', $email->get_headers() );
		$this->assertEquals( '[]', $email->get_attachments() );
		$this->assertEquals( 'sent', $email->get_status() );
		$this->assertEquals( '2026-03-19 10:00:00', $email->get_sent_at() );
	}

	/**
	 * Test setters work correctly
	 */
	public function test_setters_work_correctly() {
		$email = new Email( array() );

		$email->set_id( 456 );
		$email->set_provider_message_id( 'new-msg-id' );
		$email->set_status( 'delivered' );

		$this->assertEquals( 456, $email->get_id() );
		$this->assertEquals( 'new-msg-id', $email->get_provider_message_id() );
		$this->assertEquals( 'delivered', $email->get_status() );
	}

	/**
	 * Test to_array returns all properties
	 */
	public function test_to_array_returns_all_properties() {
		$data = array(
			'id'           => 123,
			'recipient'    => 'test@example.com',
			'subject'      => 'Test',
			'status'       => 'sent',
			'provider'     => 'mailgun',
			'message_html' => '<p>Test</p>',
		);

		$email = new Email( $data );
		$array = $email->to_array();

		$this->assertIsArray( $array );
		$this->assertArrayHasKey( 'id', $array );
		$this->assertArrayHasKey( 'recipient', $array );
		$this->assertArrayHasKey( 'subject', $array );
		$this->assertArrayHasKey( 'status', $array );
		$this->assertEquals( 123, $array['id'] );
		$this->assertEquals( 'test@example.com', $array['recipient'] );
	}

	/**
	 * Test constructor handles missing properties
	 */
	public function test_constructor_handles_missing_properties() {
		$email = new Email( array() );

		$this->assertNull( $email->get_id() );
		$this->assertSame( '', $email->get_recipient() );
		$this->assertSame( '', $email->get_subject() );
	}

	/**
	 * Test constructor handles partial data
	 */
	public function test_constructor_handles_partial_data() {
		$data = array(
			'id'        => 789,
			'recipient' => 'partial@example.com',
		);

		$email = new Email( $data );

		$this->assertEquals( 789, $email->get_id() );
		$this->assertEquals( 'partial@example.com', $email->get_recipient() );
		$this->assertSame( '', $email->get_subject() );
		// Status should have a default value of 'pending'
		$this->assertEquals( 'pending', $email->get_status() );
	}
}

