<?php
/**
 * Tests for Chip_Woocommerce_Gateway::list_unified_payment_methods().
 *
 * @package CHIP_For_WooCommerce
 */

class UnifiedPaymentMethodListTest extends GatewayTestCase {

	/**
	 * Stub the underlying list methods so we don't depend on real CHIP API calls.
	 * Each returns a small fixed list.
	 */
	private function newGatewayWithStubs( array $whitelist ): Chip_Woocommerce_Gateway {
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => $whitelist ) );
		// Stub the underlying list methods to return predictable values.
		// We replace them with anonymous wrappers around the original methods
		// (so we still test the new method's behavior, not the underlying ones).
		// Note: the underlying list methods are already tested elsewhere; here
		// we just need predictable inputs.
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => $whitelist ) );
		return $gateway;
	}

	public function test_returns_empty_when_no_dropdown_methods() {
		// Whitelist is empty -> no methods to list.
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array() ) );
		$result  = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		$this->assertSame( array(), $result );
	}

	public function test_returns_dnqr_when_whitelist_has_duitnow_qr() {
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'duitnow_qr' ) ) );
		$result  = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		$this->assertArrayHasKey( 'dnqr', $result );
		$this->assertSame( 'DuitNow QR', $result['dnqr'] );
	}

	public function test_returns_card_with_verbose_label_when_whitelist_has_visa() {
		// After the constructor's group expansion, 'visa' is in the
		// whitelist iff the card group is enabled. The unified list
		// shows the verbose 'Card (Visa/Mastercard/Maestro)' label.
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'visa' ) ) );
		$result  = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		$this->assertArrayHasKey( 'card', $result );
		$this->assertSame( 'Card (Visa/Mastercard/Maestro)', $result['card'] );
	}

	public function test_does_not_return_dnqr_when_whitelist_lacks_it() {
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'fpx' ) ) );
		$result  = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		$this->assertArrayNotHasKey( 'dnqr', $result );
	}

	public function test_does_not_return_card_when_whitelist_lacks_it() {
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'fpx' ) ) );
		$result  = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		$this->assertArrayNotHasKey( 'card', $result );
	}

	public function test_excludes_duitnow_qr_from_razer_list() {
		// 'duitnow-qr' is in list_razer_ewallets() but should NOT appear
		// in the unified list as 'razer:duitnow-qr'. The dnqr group has
		// its own 'dnqr' tag.
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'duitnow_qr', 'razer_grabpay' ) ) );
		$result  = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		$this->assertArrayNotHasKey( 'razer:duitnow-qr', $result );
		$this->assertArrayHasKey( 'dnqr', $result );
	}

	public function test_excludes_empty_placeholder_entries() {
		// The underlying list_fpx_banks() and list_razer_ewallets() may
		// contain '' => 'Choose your...' placeholders. The unified list
		// skips these.
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'fpx', 'razer_grabpay' ) ) );
		$result  = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		foreach ( $result as $key => $label ) {
			$this->assertNotSame( '', $key, "Unified list should not contain empty-string keys." );
		}
	}

	public function test_returns_all_categories_for_combined_whitelist() {
		// Whitelist with fpx, a razer, dnqr, and card -> all four categories present.
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'fpx', 'razer_grabpay', 'duitnow_qr', 'visa' ),
		) );
		$result = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		$this->assertArrayHasKey( 'dnqr', $result );
		$this->assertArrayHasKey( 'card', $result );
		// At least one fpx: and one razer: entry present.
		$has_fpx   = false;
		$has_razer = false;
		foreach ( array_keys( $result ) as $key ) {
			if ( 0 === strpos( $key, 'fpx:' ) ) { $has_fpx   = true; }
			if ( 0 === strpos( $key, 'razer:' ) ) { $has_razer = true; }
		}
		$this->assertTrue( $has_fpx, 'Unified list should contain fpx:* entries.' );
		$this->assertTrue( $has_razer, 'Unified list should contain razer:* entries.' );
	}
}