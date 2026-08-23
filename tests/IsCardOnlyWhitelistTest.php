<?php
/**
 * Tests for Chip_Woocommerce_Gateway::is_card_only_whitelist().
 *
 * The constructor's group expansion turns saved ['card'] into
 * ['card', 'visa', 'mastercard', 'maestro'] in memory. The card-only
 * check must accept that expanded list -- otherwise card-only merchants
 * lose their payment_action='authorize' behavior and Blocks checkout's
 * card POST path is broken (see final review finding C4).
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * is_card_only_whitelist test case.
 */
class IsCardOnlyWhitelistTest extends GatewayTestCase {

	public function test_true_for_constructor_expanded_card_list() {
		// Saved ['card'] is expanded by the constructor to
		// ['card', 'visa', 'mastercard', 'maestro']. The card-only check
		// must treat this as card-only.
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'card', 'visa', 'mastercard', 'maestro' ),
		) );
		$this->assertTrue( $this->callGatewayMethod( $gateway, 'is_card_only_whitelist' ) );
	}

	public function test_true_for_resolved_card_network_ids_only() {
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'visa', 'mastercard', 'maestro' ),
		) );
		$this->assertTrue( $this->callGatewayMethod( $gateway, 'is_card_only_whitelist' ) );
	}

	public function test_false_for_empty_whitelist() {
		// Empty whitelist is not card-only -- the gateway has no methods.
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array(),
		) );
		$this->assertFalse( $this->callGatewayMethod( $gateway, 'is_card_only_whitelist' ) );
	}

	public function test_false_for_non_array_whitelist() {
		// Defensive: non-array whitelist returns false (matches the
		// early-return guard in the implementation).
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => null,
		) );
		$this->assertFalse( $this->callGatewayMethod( $gateway, 'is_card_only_whitelist' ) );
	}

	public function test_false_when_fpx_added() {
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'card', 'fpx' ),
		) );
		$this->assertFalse( $this->callGatewayMethod( $gateway, 'is_card_only_whitelist' ) );
	}

	public function test_false_when_only_fpx() {
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'fpx' ),
		) );
		$this->assertFalse( $this->callGatewayMethod( $gateway, 'is_card_only_whitelist' ) );
	}

	public function test_false_when_razer_ewallet_added() {
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'card', 'razer_grabpay' ),
		) );
		$this->assertFalse( $this->callGatewayMethod( $gateway, 'is_card_only_whitelist' ) );
	}

	public function test_false_when_duitnow_qr_added() {
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'card', 'duitnow_qr' ),
		) );
		$this->assertFalse( $this->callGatewayMethod( $gateway, 'is_card_only_whitelist' ) );
	}
}
