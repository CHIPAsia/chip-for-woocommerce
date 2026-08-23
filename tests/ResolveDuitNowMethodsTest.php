<?php
/**
 * Tests for Chip_Woocommerce_Gateway::resolve_duitnow_methods().
 *
 * Covers the 8-step behavioral table from the dnqr migration spec.
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * resolve_duitnow_methods test case.
 */
class ResolveDuitNowMethodsTest extends GatewayTestCase {

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

	public function test_short_circuits_when_whitelist_lacks_dnqr_group() {
		// Whitelist [fpx, mastercard] has no dnqr-group member -- resolver
		// returns it untouched and does NOT call the API.
		$api = $this->createMock( 'Chip_Woocommerce_API' );
		$api->expects( $this->never() )->method( 'payment_methods' );
		$gateway = $this->newGateway();
		$this->setGatewayProperty( $gateway, 'cached_api', $api );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'fpx', 'mastercard' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'fpx', 'mastercard' ), $result );
		$this->assertSame( array(), $this->getGatewayProperty( $gateway, 'resolved_dnqr_group' ) );
	}

	public function test_group_expansion_when_duitnow_qr_present() {
		// Input [duitnow_qr, dnqr] (post-load-time expansion), API returns
		// [dnqr, fpx]. The resolver picks 'dnqr' (priority) and strips
		// 'duitnow_qr' from the final whitelist. 'fpx' is not in the input
		// whitelist so it is not added -- the resolver only intersects
		// within DUITNOW_GROUP.
		$gateway = $this->gatewayWithApi( array( 'available_payment_methods' => array( 'dnqr', 'fpx' ) ) );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr', 'dnqr' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'dnqr' ), $result );
	}

	public function test_intersection_picks_only_available_methods() {
		// Input [duitnow_qr, dnqr], API returns [duitnow_qr, fpx] -> output [duitnow_qr].
		// dnqr is not available, so duitnow_qr wins. fpx is not in the input
		// whitelist so it is not added.
		$gateway = $this->gatewayWithApi( array( 'available_payment_methods' => array( 'duitnow_qr', 'fpx' ) ) );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr', 'dnqr' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'duitnow_qr' ), $result );
	}

	public function test_priority_dnqr_wins_when_both_available() {
		// Input [duitnow_qr, dnqr], API returns [duitnow_qr, dnqr] -> output [dnqr]
		// (duitnow_qr dropped due to dnqr priority).
		$gateway = $this->gatewayWithApi( array( 'available_payment_methods' => array( 'duitnow_qr', 'dnqr' ) ) );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr', 'dnqr' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'dnqr' ), $result );
	}

	public function test_priority_falls_back_to_duitnow_qr() {
		// Input [duitnow_qr], API returns [duitnow_qr] -> output [duitnow_qr].
		$gateway = $this->gatewayWithApi( array( 'available_payment_methods' => array( 'duitnow_qr' ) ) );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'duitnow_qr' ), $result );
	}

	public function test_api_failure_falls_back_to_expanded_whitelist() {
		// API returns a non-array response -- resolver returns expanded
		// whitelist unchanged and sets resolved_dnqr_group to the full group.
		$api = $this->createMock( 'Chip_Woocommerce_API' );
		$api->method( 'payment_methods' )->willReturn( array( '__all__' => array( 'message' => 'failure' ) ) );
		$gateway = $this->newGateway();
		$this->setGatewayProperty( $gateway, 'cached_api', $api );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'duitnow_qr', 'dnqr' ), $result );
		$this->assertSame(
			array( 'duitnow_qr', 'dnqr' ),
			$this->getGatewayProperty( $gateway, 'resolved_dnqr_group' )
		);
	}

	public function test_cache_hit_avoids_api_call() {
		// Pre-populate the cache; the API must not be called.
		// Cached available methods are intersected with DUITNOW_GROUP
		// (['duitnow_qr', 'dnqr']) before the resolver picks a winner,
		// so 'fpx' (a non-group method) cannot appear in the final whitelist.
		$cache_key = 'chip_pm_' . md5( 'test_brand|MYR|123' );
		$GLOBALS['__chip_test_transients'][ $cache_key ] = array( 'dnqr', 'fpx' );

		$api = $this->createMock( 'Chip_Woocommerce_API' );
		$api->expects( $this->never() )->method( 'payment_methods' );
		$gateway = $this->newGateway();
		$this->setGatewayProperty( $gateway, 'cached_api', $api );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'dnqr' ), $result );
	}

	public function test_cache_miss_calls_api_and_writes_transient() {
		// Empty cache; the API is called and the transient is populated.
		$gateway = $this->gatewayWithApi( array( 'available_payment_methods' => array( 'dnqr' ) ) );

		$this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr' ), 'MYR', 12345 )
		);

		$cache_key = 'chip_pm_' . md5( 'test_brand|MYR|123' );
		$this->assertArrayHasKey( $cache_key, $GLOBALS['__chip_test_transients'] );
		$this->assertSame( array( 'dnqr' ), $GLOBALS['__chip_test_transients'][ $cache_key ] );
	}

	public function test_sets_resolved_dnqr_group_property() {
		$gateway = $this->gatewayWithApi( array( 'available_payment_methods' => array( 'dnqr', 'fpx' ) ) );

		$this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr' ), 'MYR', 12345 )
		);

		$this->assertSame(
			array( 'dnqr' ),
			$this->getGatewayProperty( $gateway, 'resolved_dnqr_group' )
		);
	}

	public function test_amount_bucketing() {
		// Two amounts within the same 100-sen bucket share a cache entry.
		// amount 12345 -> bucket intval(12345 / 100) = 123
		// amount 12400 -> bucket intval(12400 / 100) = 124
		// Different buckets -> different cache entries -> two API calls.
		$api = $this->createMock( 'Chip_Woocommerce_API' );
		$api->expects( $this->exactly( 2 ) )
			->method( 'payment_methods' )
			->willReturn( array( 'available_payment_methods' => array( 'dnqr' ) ) );
		$gateway = $this->newGateway();
		$this->setGatewayProperty( $gateway, 'cached_api', $api );

		$this->callGatewayMethod( $gateway, 'resolve_duitnow_methods', array( array( 'duitnow_qr' ), 'MYR', 12345 ) );
		$this->callGatewayMethod( $gateway, 'resolve_duitnow_methods', array( array( 'duitnow_qr' ), 'MYR', 12400 ) );

		// Two cache entries written (different buckets).
		$bucket_123 = 'chip_pm_' . md5( 'test_brand|MYR|123' );
		$bucket_124 = 'chip_pm_' . md5( 'test_brand|MYR|124' );
		$this->assertArrayHasKey( $bucket_123, $GLOBALS['__chip_test_transients'] );
		$this->assertArrayHasKey( $bucket_124, $GLOBALS['__chip_test_transients'] );
	}

	public function test_amount_bucketing_within_same_bucket_uses_cache() {
		// Two amounts within the same 100-sen bucket share a cache entry.
		// amount 12345 -> bucket 123
		// amount 12399 -> bucket 123 (same)
		// API is called only once.
		$api = $this->createMock( 'Chip_Woocommerce_API' );
		$api->expects( $this->once() )
			->method( 'payment_methods' )
			->willReturn( array( 'available_payment_methods' => array( 'dnqr' ) ) );
		$gateway = $this->newGateway();
		$this->setGatewayProperty( $gateway, 'cached_api', $api );

		$this->callGatewayMethod( $gateway, 'resolve_duitnow_methods', array( array( 'duitnow_qr' ), 'MYR', 12345 ) );
		$this->callGatewayMethod( $gateway, 'resolve_duitnow_methods', array( array( 'duitnow_qr' ), 'MYR', 12399 ) );
	}

public function test_resolved_dnqr_group_is_emptied_on_no_group_member() {
		// When the whitelist has no dnqr-group member, resolved_dnqr_group
		// should be set to array() (per the resolver's short-circuit).
		$gateway = $this->newGateway( array( 'resolved_dnqr_group' => array( 'stale' ) ) );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'fpx' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'fpx' ), $result );
		$this->assertSame( array(), $this->getGatewayProperty( $gateway, 'resolved_dnqr_group' ) );
	}

	public function test_card_aggregator_is_stripped_from_whitelist() {
		// The 'card' aggregator key is a runtime-only marker produced by
		// the constructor's group expansion. CHIP's API does not
		// recognise it, so resolve_duitnow_methods() must strip it
		// before the whitelist is sent upstream.
		$gateway = $this->newGateway();

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'card', 'fpx' ), 'MYR', 12345 )
		);

		$this->assertNotContains( 'card', $result, "The 'card' aggregator must be stripped from the whitelist." );
		$this->assertContains( 'fpx', $result );
	}

	public function test_card_aggregator_is_stripped_even_on_short_circuit() {
		// The 'card' strip happens at the top of resolve_duitnow_methods()
		// so it applies to the short-circuit path too (no dnqr group
		// member in the whitelist).
		$api = $this->createMock( 'Chip_Woocommerce_API' );
		$api->expects( $this->never() )->method( 'payment_methods' );
		$gateway = $this->newGateway();
		$this->setGatewayProperty( $gateway, 'cached_api', $api );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'card', 'visa', 'mastercard', 'maestro' ), 'MYR', 12345 )
		);

		$this->assertNotContains( 'card', $result );
		// The card-network identifiers remain.
		$this->assertSame(
			array( 'visa', 'mastercard', 'maestro' ),
			$result
		);
	}
}
