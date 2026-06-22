<?php
/**
 * Tests for the duitnow-qr entry in Chip_Woocommerce_Gateway::list_razer_ewallets().
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * list_razer_ewallets duitnow-qr trigger test case.
 */
class ListRazerEwalletsTest extends GatewayTestCase {

	public function test_does_not_show_duitnow_qr_when_whitelist_empty() {
		$gateway  = $this->newGateway( array( 'payment_method_whitelist' => array() ) );
		$ewallets = $this->callGatewayMethod( $gateway, 'list_razer_ewallets' );
		$this->assertArrayNotHasKey( 'duitnow-qr', $ewallets );
	}

	public function test_shows_duitnow_qr_when_whitelist_has_duitnow_qr() {
		$gateway  = $this->newGateway( array( 'payment_method_whitelist' => array( 'duitnow_qr' ) ) );
		$ewallets = $this->callGatewayMethod( $gateway, 'list_razer_ewallets' );
		$this->assertArrayHasKey( 'duitnow-qr', $ewallets );
		$this->assertSame( 'Duitnow QR', $ewallets['duitnow-qr'] );
	}

	public function test_shows_duitnow_qr_when_whitelist_has_dnqr() {
		// The constructor expands duitnow_qr to [duitnow_qr, dnqr]; the
		// list_razer_ewallets trigger checks array_intersect with the dnqr
		// group, so a whitelist containing dnqr (post-expansion) also
		// shows the entry.
		$gateway  = $this->newGateway( array( 'payment_method_whitelist' => array( 'duitnow_qr', 'dnqr' ) ) );
		$ewallets = $this->callGatewayMethod( $gateway, 'list_razer_ewallets' );
		$this->assertArrayHasKey( 'duitnow-qr', $ewallets );
	}

	public function test_does_not_show_duitnow_qr_for_fpx_only() {
		$gateway  = $this->newGateway( array( 'payment_method_whitelist' => array( 'fpx' ) ) );
		$ewallets = $this->callGatewayMethod( $gateway, 'list_razer_ewallets' );
		$this->assertArrayNotHasKey( 'duitnow-qr', $ewallets );
	}
}
