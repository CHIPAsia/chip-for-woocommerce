<?php
/**
 * Checkout notices must stay readable when the gateway rejects a purchase.
 *
 * The API answers validation problems with a field => list of
 * array( 'message' => ... ) entries; dumping that array at the shopper shows
 * "Array ( [success_callback] => ... )" on the checkout page.
 *
 * @package CHIP_For_WooCommerce
 */

require_once __DIR__ . '/GatewayTestCase.php';

/**
 * Class PaymentErrorNoticeTest
 */
class PaymentErrorNoticeTest extends GatewayTestCase {

	/**
	 * Validation errors are flattened to readable sentences.
	 *
	 * @return void
	 */
	public function test_flattens_api_validation_payload() {
		$gateway = $this->newGateway();

		$payload = array(
			'success_callback' => array(
				array( 'message' => "You can't use custom ports in callback (only 80/443)." ),
			),
			'brand_id'         => array(
				array( 'message' => 'Brand does not exist.' ),
			),
		);

		$message = $this->callGatewayMethod( $gateway, 'describe_payment_error', array( $payload ) );

		$this->assertStringNotContainsString( 'Array', $message );
		$this->assertStringContainsString( "You can't use custom ports in callback", $message );
		$this->assertStringContainsString( 'success_callback:', $message );
		$this->assertStringContainsString( 'Brand does not exist.', $message );
	}

	/**
	 * '__all__' entries read as a sentence, without a field name prefix.
	 *
	 * @return void
	 */
	public function test_all_key_is_not_printed_as_field_name() {
		$gateway = $this->newGateway();

		$message = $this->callGatewayMethod(
			$gateway,
			'describe_payment_error',
			array( array( '__all__' => array( array( 'message' => 'Amount is too low.' ) ) ) )
		);

		$this->assertStringContainsString( 'Amount is too low.', $message );
		$this->assertStringNotContainsString( '__all__', $message );
	}

	/**
	 * Unknown shapes still produce a sentence instead of an empty notice.
	 *
	 * @return void
	 */
	public function test_falls_back_to_generic_message() {
		$gateway = $this->newGateway();

		foreach ( array( null, 'timeout', array(), array( 'weird' => array() ) ) as $payload ) {
			$message = $this->callGatewayMethod( $gateway, 'describe_payment_error', array( $payload ) );

			$this->assertIsString( $message );
			$this->assertNotSame( '', trim( $message ) );
			$this->assertStringContainsString( 'could not be created', $message );
		}
	}

	/**
	 * Repeated messages are only shown once.
	 *
	 * @return void
	 */
	public function test_deduplicates_messages() {
		$gateway = $this->newGateway();

		$payload = array(
			'amount' => array(
				array( 'message' => 'Too low.' ),
				array( 'message' => 'Too low.' ),
			),
		);

		$message = $this->callGatewayMethod( $gateway, 'describe_payment_error', array( $payload ) );

		$this->assertSame( 1, substr_count( $message, 'Too low.' ) );
	}
}
