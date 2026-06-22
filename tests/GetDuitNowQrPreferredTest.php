<?php
/**
 * Tests for Chip_Woocommerce_Gateway::get_duitnow_qr_preferred().
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * get_duitnow_qr_preferred test case.
 */
class GetDuitNowQrPreferredTest extends GatewayTestCase {

	public function test_returns_empty_when_whitelist_lacks_dnqr_group() {
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'fpx' ) ) );
		$this->assertSame( '', $this->callGatewayMethod( $gateway, 'get_duitnow_qr_preferred' ) );
	}

	public function test_returns_empty_when_other_groups_present() {
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'fpx', 'duitnow_qr' ) ) );
		$this->assertSame( '', $this->callGatewayMethod( $gateway, 'get_duitnow_qr_preferred' ) );
	}

	public function test_returns_dnqr_when_resolver_picked_dnqr() {
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'duitnow_qr' ),
			'resolved_dnqr_group'      => array( 'dnqr' ),
		) );
		$this->assertSame( 'dnqr', $this->callGatewayMethod( $gateway, 'get_duitnow_qr_preferred' ) );
	}

	public function test_returns_duitnow_qr_when_resolver_picked_duitnow_qr() {
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'duitnow_qr' ),
			'resolved_dnqr_group'      => array( 'duitnow_qr' ),
		) );
		$this->assertSame( 'duitnow_qr', $this->callGatewayMethod( $gateway, 'get_duitnow_qr_preferred' ) );
	}

	/**
	 * When the resolver has not populated $resolved_dnqr_group, the helper
	 * falls back to self::DUITNOW_GROUP, returning the first element
	 * ('duitnow_qr') as the defensive default.
	 */
	public function test_falls_back_to_duitnow_qr_when_resolved_group_empty() {
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'duitnow_qr' ),
			'resolved_dnqr_group'      => array(),
		) );
		$this->assertSame( 'duitnow_qr', $this->callGatewayMethod( $gateway, 'get_duitnow_qr_preferred' ) );
	}
}
