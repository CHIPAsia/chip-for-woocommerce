<?php
/**
 * Tests for Chip_Woocommerce_Gateway::get_payment_method_list().
 *
 * @package CHIP_For_WooCommerce
 */

class GetPaymentMethodListTest extends GatewayTestCase {

	public function test_contains_all_expected_methods() {
		$gateway  = $this->newGateway();
		$actual   = $this->callGatewayMethod( $gateway, 'get_payment_method_list' );
		$expected = array(
			'fpx'             => 'FPX',
			'fpx_b2b1'        => 'FPX B2B1',
			'card'            => 'Card',
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

	public function test_does_not_contain_card_group_keys() {
		// The 'visa', 'mastercard', 'maestro' keys are NOT in the multiselect
		// -- they're injected at runtime via the constructor's group expansion.
		// Only 'card' is user-selectable.
		$gateway = $this->newGateway();
		$actual  = $this->callGatewayMethod( $gateway, 'get_payment_method_list' );
		$this->assertArrayNotHasKey( 'visa', $actual );
		$this->assertArrayNotHasKey( 'mastercard', $actual );
		$this->assertArrayNotHasKey( 'maestro', $actual );
		$this->assertArrayHasKey( 'card', $actual );
	}
}
