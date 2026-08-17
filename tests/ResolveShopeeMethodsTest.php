<?php
/**
 * Tests for the Shopee Pay group resolution in
 * Chip_Woocommerce_Gateway::resolve_payment_method_groups().
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * Shopee Pay group resolution test case.
 */
class ResolveShopeeMethodsTest extends GatewayTestCase {

	/**
	 * Build a gateway with the API stubbed to return a given /payment_methods/ response.
	 */
	private function gatewayWithApi( array $payment_methods_response, array $options = array() ) {
		$gateway = $this->newGateway( $options );
		$this->mockApi( $gateway, array(
			'payment_methods' => $payment_methods_response,
		) );
		return $gateway;
	}

	public function test_short_circuits_when_whitelist_lacks_shopee_group() {
		// Whitelist [fpx] has no shopee-group member -- resolver returns it
		// untouched and does NOT call the API.
		$api = $this->createMock( 'Chip_Woocommerce_API' );
		$api->expects( $this->never() )->method( 'payment_methods' );
		$gateway = $this->newGateway();
		$this->setGatewayProperty( $gateway, 'cached_api', $api );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_payment_method_groups',
			array( array( 'fpx' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'fpx' ), $result );
		$this->assertSame( array(), $this->getGatewayProperty( $gateway, 'resolved_shopee_group' ) );
	}

	public function test_prefers_shopee_pay_when_both_available() {
		// Input [razer_shopeepay, shopee_pay] (post-load-time expansion),
		// API returns [razer_shopeepay, shopee_pay] -> output [shopee_pay]
		// (razer_shopeepay dropped due to shopee_pay priority).
		$gateway = $this->gatewayWithApi( array( 'available_payment_methods' => array( 'razer_shopeepay', 'shopee_pay' ) ) );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_payment_method_groups',
			array( array( 'razer_shopeepay', 'shopee_pay' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'shopee_pay' ), $result );
		$this->assertSame(
			array( 'shopee_pay' ),
			$this->getGatewayProperty( $gateway, 'resolved_shopee_group' )
		);
	}

	public function test_falls_back_to_razer_shopeepay_when_modern_unavailable() {
		// Input [razer_shopeepay, shopee_pay], API returns [razer_shopeepay]
		// -> output [razer_shopeepay] (shopee_pay not available).
		$gateway = $this->gatewayWithApi( array( 'available_payment_methods' => array( 'razer_shopeepay' ) ) );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_payment_method_groups',
			array( array( 'razer_shopeepay', 'shopee_pay' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'razer_shopeepay' ), $result );
		$this->assertSame(
			array( 'razer_shopeepay' ),
			$this->getGatewayProperty( $gateway, 'resolved_shopee_group' )
		);
	}

	public function test_api_failure_falls_back_to_expanded_whitelist() {
		// API returns a non-array response -- resolver returns expanded
		// whitelist unchanged and sets resolved_shopee_group to the full group.
		$api = $this->createMock( 'Chip_Woocommerce_API' );
		$api->method( 'payment_methods' )->willReturn( array( '__all__' => array( 'message' => 'failure' ) ) );
		$gateway = $this->newGateway();
		$this->setGatewayProperty( $gateway, 'cached_api', $api );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_payment_method_groups',
			array( array( 'razer_shopeepay' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'razer_shopeepay', 'shopee_pay' ), $result );
		$this->assertSame(
			array( 'razer_shopeepay', 'shopee_pay' ),
			$this->getGatewayProperty( $gateway, 'resolved_shopee_group' )
		);
	}

	public function test_resolves_shopee_alongside_dnqr_in_one_call() {
		// Whitelist contains both the dnqr and shopee groups; a single
		// /payment_methods/ call resolves both. dnqr wins (dnqr preferred)
		// and shopee_pay wins.
		$api = $this->createMock( 'Chip_Woocommerce_API' );
		$api->expects( $this->once() )
			->method( 'payment_methods' )
			->willReturn( array( 'available_payment_methods' => array( 'duitnow_qr', 'dnqr', 'razer_shopeepay', 'shopee_pay' ) ) );
		$gateway = $this->newGateway();
		$this->setGatewayProperty( $gateway, 'cached_api', $api );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_payment_method_groups',
			array( array( 'duitnow_qr', 'razer_shopeepay' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'dnqr', 'shopee_pay' ), $result );
		$this->assertSame(
			array( 'dnqr' ),
			$this->getGatewayProperty( $gateway, 'resolved_dnqr_group' )
		);
		$this->assertSame(
			array( 'shopee_pay' ),
			$this->getGatewayProperty( $gateway, 'resolved_shopee_group' )
		);
	}

	public function test_non_group_methods_preserved() {
		// Input [fpx, razer_shopeepay], API returns [fpx, shopee_pay].
		// fpx is a non-group method and is preserved; shopee_pay is resolved.
		$gateway = $this->gatewayWithApi( array( 'available_payment_methods' => array( 'fpx', 'shopee_pay' ) ) );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_payment_method_groups',
			array( array( 'fpx', 'razer_shopeepay' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'fpx', 'shopee_pay' ), $result );
	}
}
