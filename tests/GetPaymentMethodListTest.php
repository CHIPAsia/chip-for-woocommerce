<?php
/**
 * Tests for Chip_Woocommerce_Gateway::get_payment_method_list().
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * Payment method list test case.
 */
class GetPaymentMethodListTest extends GatewayTestCase {

	/**
	 * The list must contain all 13 expected method keys, in order.
	 */
	public function test_contains_all_expected_methods() {
		$gateway  = $this->newGateway();
		$actual   = $this->callGatewayMethod( $gateway, 'get_payment_method_list' );
		$expected = array(
			'fpx'             => 'FPX',
			'fpx_b2b1'        => 'FPX B2B1',
			'mastercard'      => 'Mastercard',
			'maestro'         => 'Maestro',
			'visa'            => 'Visa',
			'mpgs_google_pay' => 'Google Pay',
			'mpgs_apple_pay'  => 'Apple Pay',
			'razer_atome'     => 'Atome',
			'razer_grabpay'   => 'GrabPay',
			'razer_maybankqr' => 'Maybank QRPay',
			'razer_shopeepay' => 'ShopeePay',
			'razer_tng'       => "Touch 'n Go eWallet",
			'duitnow_qr'      => 'DuitNow QR',
		);
		$this->assertSame( $expected, $actual );
	}

	/**
	 * The 'dnqr' key is NOT a multiselect option -- it's injected at runtime
	 * via the constructor's group expansion. Only 'duitnow_qr' is
	 * user-selectable (it's the legitimate multiselect key for the dnqr
	 * group since the refactor in commit 67e2839).
	 */
	public function test_does_not_contain_dnqr_key() {
		$gateway = $this->newGateway();
		$actual  = $this->callGatewayMethod( $gateway, 'get_payment_method_list' );
		$this->assertArrayNotHasKey( 'dnqr', $actual );
		$this->assertArrayHasKey( 'duitnow_qr', $actual );
	}
}
