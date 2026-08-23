<?php
/**
 * Tests for get_payment_method_for_recurring() returning a zero-indexed list.
 *
 * Regression: array_intersect() preserves the keys of the first array, so a
 * group-expanded whitelist (['card','visa','mastercard','maestro']) produced a
 * non-zero-indexed result ([1=>'visa', 2=>'mastercard', 3=>'maestro']).
 * json_encode() serializes that as a JSON object ("dict") instead of a list,
 * and CHIP rejects it with "Expected a list of items but got type dict".
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
}
