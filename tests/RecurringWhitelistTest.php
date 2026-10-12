<?php
/**
 * Tests for get_payment_method_for_recurring() and the recurring create-payment flows.
 *
 * Regression 1: array_intersect() preserves the keys of the first array, so a
 * group-expanded whitelist (['card','visa','mastercard','maestro']) produced a
 * non-zero-indexed result ([1=>'visa', 2=>'mastercard', 3=>'maestro']).
 * json_encode() serializes that as a JSON object ("dict") instead of a list,
 * and CHIP rejects it with "Expected a list of items but got type dict".
 *
 * Regression 2: the "Add payment method" and "Change payment method" flows
 * assigned get_payment_method_for_recurring() straight into the create-payment
 * params. A null result means the account has NO recurring-capable method, so
 * the flow must fail with a notice instead of starting a recurring payment that
 * the customer could settle with a non-recurring method (and instead of sending
 * a null whitelist).
 *
 * @package CHIP_For_WooCommerce
 */

class RecurringWhitelistTest extends GatewayTestCase {

	/**
	 * A group-expanded whitelist must yield a zero-indexed list of card
	 * networks (so json_encode produces a JSON array, not an object).
	 */
	public function test_recurring_whitelist_is_zero_indexed_list() {
		$gateway = $this->newGateway(
			array(
				'payment_method_whitelist' => array( 'card', 'visa', 'mastercard', 'maestro' ),
			)
		);

		$result = $gateway->get_payment_method_for_recurring();

		$this->assertSame( array( 'visa', 'mastercard', 'maestro' ), $result );
		// Keys must be sequential from 0 so json_encode emits a list.
		$this->assertSame( array( 0, 1, 2 ), array_keys( $result ) );
	}

	/**
	 * A whitelist with no card networks falls back to the default card list
	 * (still zero-indexed).
	 */
	public function test_recurring_whitelist_falls_back_to_default() {
		$gateway = $this->newGateway(
			array(
				'payment_method_whitelist' => array( 'fpx', 'dnqr' ),
			)
		);
		$gateway->supports = array( 'tokenization' );

		$result = $gateway->get_payment_method_for_recurring();

		$this->assertSame( array( 'visa', 'mastercard', 'maestro' ), $result );
		$this->assertSame( array( 0, 1, 2 ), array_keys( $result ) );
	}

	/**
	 * Params captured from the last create_payment() call.
	 *
	 * @var array|null
	 */
	private $captured_params = null;

	/**
	 * Build a gateway whose API records the create_payment() params.
	 *
	 * @param array $whitelist Expanded in-memory whitelist.
	 * @param array $supports  Gateway supports flags.
	 * @return Chip_Woocommerce_Gateway
	 */
	private function newCapturingGateway( array $whitelist, array $supports ) {
		$gateway = $this->newGateway(
			array(
				'id'                       => 'wc_gateway_chip',
				'payment_method_whitelist' => $whitelist,
				'disable_redirect'         => 'no',
			)
		);
		$gateway->supports     = $supports;
		$this->captured_params = null;

		$api = $this->mockApi( $gateway, array() );
		$api->method( 'create_payment' )->willReturnCallback(
			function ( $params ) {
				$this->captured_params = $params;
				return array(
					'id'                       => 'p_test_capture',
					'checkout_url'             => 'https://example.com/checkout',
					'direct_post_url'          => 'https://example.com/direct-post',
					'payment_method_whitelist' => array(),
				);
			}
		);

		return $gateway;
	}

	/**
	 * The recurring whitelist is null when the merchant has no card networks
	 * configured and the gateway does not support tokenization. This is the
	 * precondition the two omit-the-key tests below depend on.
	 */
	public function test_recurring_whitelist_is_null_without_tokenization_support() {
		$gateway = $this->newGateway(
			array(
				'payment_method_whitelist' => array( 'fpx' ),
			)
		);
		$gateway->supports = array( 'products', 'refunds' );

		$this->assertNull( $gateway->get_payment_method_for_recurring() );
	}

	/**
	 * A null recurring whitelist means the account cannot do recurring at all,
	 * so the "Add payment method" flow must fail instead of creating a payment
	 * that the customer could settle with a non-recurring method.
	 */
	public function test_add_payment_method_fails_when_recurring_is_null() {
		$gateway = $this->newCapturingGateway( array( 'fpx' ), array( 'products', 'refunds' ) );

		$result = $gateway->add_payment_method();

		$this->assertSame( 'failure', $result['result'] );
		// No purchase may be created.
		$this->assertNull( $this->captured_params );
		$this->assertSame( 1, wc_notice_count( 'error' ) );
		$this->assertStringContainsString(
			'Recurring payments are not available for this payment method',
			$GLOBALS['__chip_test_notices'][0]['message']
		);
	}

	/**
	 * Same guard for the subscription "Change payment method" flow, which is
	 * reachable through ?change_payment_method= on checkout.
	 */
	public function test_process_payment_method_change_fails_when_recurring_is_null() {
		$gateway = $this->newCapturingGateway( array( 'fpx' ), array( 'products', 'refunds' ) );

		$result = $gateway->process_payment_method_change( 55 );

		$this->assertSame( 'failure', $result['result'] );
		$this->assertNull( $this->captured_params );
		$this->assertSame( 1, wc_notice_count( 'error' ) );
		$this->assertStringContainsString(
			'Recurring payments are not available for this payment method',
			$GLOBALS['__chip_test_notices'][0]['message']
		);
	}

	/**
	 * When there IS a recurring whitelist, it is still sent as a list.
	 */
	public function test_add_payment_method_sends_whitelist_when_recurring_is_present() {
		$gateway = $this->newCapturingGateway(
			array( 'card', 'visa', 'mastercard', 'maestro' ),
			array( 'products', 'tokenization' )
		);

		$gateway->add_payment_method();

		$this->assertIsArray( $this->captured_params );
		$this->assertSame(
			array( 'visa', 'mastercard', 'maestro' ),
			$this->captured_params['payment_method_whitelist']
		);
	}

	/**
	 * Same for the change-payment-method flow.
	 */
	public function test_process_payment_method_change_sends_whitelist_when_recurring_is_present() {
		$gateway = $this->newCapturingGateway(
			array( 'card', 'visa', 'mastercard', 'maestro' ),
			array( 'products', 'tokenization' )
		);

		$gateway->process_payment_method_change( 56 );

		$this->assertIsArray( $this->captured_params );
		$this->assertSame(
			array( 'visa', 'mastercard', 'maestro' ),
			$this->captured_params['payment_method_whitelist']
		);
	}
}
