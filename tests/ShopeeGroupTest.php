<?php
/**
 * Tests for the SHOPEE_GROUP class constant and the Shopee Pay
 * ?preferred= resolution in build_razer_url().
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * Shopee Pay group constant + preferred test case.
 */
class ShopeeGroupTest extends GatewayTestCase {

	/**
	 * The constant must contain exactly razer_shopeepay and shopee_pay.
	 */
	public function test_constant_value() {
		$this->assertSame(
			array( 'razer_shopeepay', 'shopee_pay' ),
			Chip_Woocommerce_Gateway::SHOPEE_GROUP
		);
	}

	/**
	 * build_razer_url() with ShopeePay and a resolved_shopee_group of
	 * ['shopee_pay'] emits ?preferred=shopee_pay.
	 */
	public function test_razer_url_prefers_shopee_pay_when_resolved() {
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'razer_shopeepay', 'shopee_pay' ),
			'resolved_shopee_group'    => array( 'shopee_pay' ),
		) );

		$url = $this->callGatewayMethod( $gateway, 'build_razer_url', array( 'https://example.com/pay', 'ShopeePay' ) );

		$this->assertSame( 'https://example.com/pay?preferred=shopee_pay&razer_bank_code=ShopeePay', $url );
	}

	/**
	 * When the resolver has not populated resolved_shopee_group, build_razer_url()
	 * falls back to razer_shopeepay (the legacy single-multiselect key).
	 */
	public function test_razer_url_falls_back_to_razer_shopeepay_when_not_resolved() {
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'razer_shopeepay' ),
			'resolved_shopee_group'    => array(),
		) );

		$url = $this->callGatewayMethod( $gateway, 'build_razer_url', array( 'https://example.com/pay', 'ShopeePay' ) );

		$this->assertSame( 'https://example.com/pay?preferred=razer_shopeepay&razer_bank_code=ShopeePay', $url );
	}

	/**
	 * list_razer_ewallets() shows ShopeePay when either the legacy
	 * razer_shopeepay or modern shopee_pay is in the whitelist.
	 */
	public function test_list_razer_ewallets_shows_shopeepay_for_modern_key() {
		$gateway  = $this->newGateway( array( 'payment_method_whitelist' => array( 'shopee_pay' ) ) );
		$ewallets = $this->callGatewayMethod( $gateway, 'list_razer_ewallets' );
		$this->assertArrayHasKey( 'ShopeePay', $ewallets );
	}

	public function test_list_razer_ewallets_shows_shopeepay_for_legacy_key() {
		$gateway  = $this->newGateway( array( 'payment_method_whitelist' => array( 'razer_shopeepay' ) ) );
		$ewallets = $this->callGatewayMethod( $gateway, 'list_razer_ewallets' );
		$this->assertArrayHasKey( 'ShopeePay', $ewallets );
	}

	public function test_list_razer_ewallets_hides_shopeepay_when_not_configured() {
		$gateway  = $this->newGateway( array( 'payment_method_whitelist' => array( 'fpx' ) ) );
		$ewallets = $this->callGatewayMethod( $gateway, 'list_razer_ewallets' );
		$this->assertArrayNotHasKey( 'ShopeePay', $ewallets );
	}
}
