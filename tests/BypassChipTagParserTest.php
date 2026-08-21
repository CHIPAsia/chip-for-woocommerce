<?php
/**
 * Tests for Chip_Woocommerce_Gateway::bypass_chip() with the tag parser.
 *
 * @package CHIP_For_WooCommerce
 */

class BypassChipTagParserTest extends GatewayTestCase {

	/**
	 * Invoke bypass_chip with $_POST values pre-populated.
	 *
	 * bypass_chip() is a public method so we can call it directly.
	 * We pre-populate $_POST via a helper because PHPUnit doesn't
	 * automatically restore $_POST between tests.
	 */
	private function callBypass( Chip_Woocommerce_Gateway $gateway, ?string $tag_value ): string {
		if ( null === $tag_value ) {
			unset( $_POST['chip_payment_method'] );
		} else {
			$_POST['chip_payment_method'] = $tag_value;
		}
		return $this->callGatewayMethod(
			$gateway,
			'bypass_chip',
			array( 'https://example.com/checkout', array( 'is_test' => false ) )
		);
	}

	private function newGatewayWithBypass( string $bypass = 'yes', string $id = 'wc_gateway_chip' ): Chip_Woocommerce_Gateway {
		return $this->newGateway( array(
			'bypass_chip'              => $bypass,
			'payment_method_whitelist' => array( 'fpx', 'card' ),
		) );
	}

	public function test_returns_unchanged_url_when_post_missing() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, null );
		$this->assertSame( 'https://example.com/checkout', $result );
	}

	public function test_returns_unchanged_url_when_post_empty() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, '' );
		$this->assertSame( 'https://example.com/checkout', $result );
	}

	public function test_fpx_tag_builds_preferred_fpx_url() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, 'fpx:MB2U0227' );
		$this->assertSame( 'https://example.com/checkout?preferred=fpx&fpx_bank_code=MB2U0227', $result );
	}

	public function test_fpx_b2b1_tag_builds_preferred_fpx_b2b1_url() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, 'fpx_b2b1:PBB0234' );
		$this->assertSame( 'https://example.com/checkout?preferred=fpx_b2b1&fpx_bank_code=PBB0234', $result );
	}

	public function test_razer_grabpay_tag_builds_correct_url() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, 'razer:GrabPay' );
		$this->assertSame( 'https://example.com/checkout?preferred=razer_grabpay&razer_bank_code=GrabPay', $result );
	}

	public function test_razer_tng_ewallet_tag_builds_correct_url() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, 'razer:TNG-EWALLET' );
		$this->assertSame( 'https://example.com/checkout?preferred=razer_tng&razer_bank_code=TNG-EWALLET', $result );
	}

	public function test_razer_maybank_qrpay_tag_builds_correct_url() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, 'razer:MB2U_QRPay-Push' );
		$this->assertSame( 'https://example.com/checkout?preferred=razer_maybankqr&razer_bank_code=MB2U_QRPay-Push', $result );
	}

	public function test_card_tag_returns_unchanged_url() {
		// The 'card' tag is NOT a ?preferred= redirect. The direct-post flow
		// handles card payments. bypass_chip returns the URL unchanged.
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, 'card' );
		$this->assertSame( 'https://example.com/checkout', $result );
	}

	public function test_crypto_coin_tag_builds_preferred_url() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, 'crypto_coin' );
		$this->assertSame( 'https://example.com/checkout?preferred=crypto_coin', $result );
	}

	/**
	 * When the merchant enables "skip payment page" (bypass_chip), the
	 * ?preferred= parameter is sent regardless of $payment['is_test']. A
	 * test-mode purchase must not silently disable the auto-redirect.
	 */
	public function test_preferred_url_sent_even_when_payment_is_test_mode() {
		$gateway = $this->newGatewayWithBypass();
		$_POST['chip_payment_method'] = 'crypto_coin';
		$result = $this->callGatewayMethod(
			$gateway,
			'bypass_chip',
			array( 'https://example.com/checkout', array( 'is_test' => true ) )
		);
		unset( $_POST['chip_payment_method'] );
		$this->assertSame( 'https://example.com/checkout?preferred=crypto_coin', $result );
	}

	public function test_dnqr_tag_uses_resolver_to_choose_dnqr() {
		// When the resolver has picked 'dnqr', bypass_chip uses it.
		$gateway = $this->newGateway( array(
			'bypass_chip'              => 'yes',
			'payment_method_whitelist' => array( 'duitnow_qr' ),
			'resolved_dnqr_group'      => array( 'dnqr' ),
		) );
		$result = $this->callBypass( $gateway, 'dnqr' );
		$this->assertSame( 'https://example.com/checkout?preferred=dnqr', $result );
	}

	public function test_dnqr_tag_falls_back_to_duitnow_qr() {
		// When the resolver picked 'duitnow_qr' (only that method is available
		// for the merchant), bypass_chip uses it.
		$gateway = $this->newGateway( array(
			'bypass_chip'              => 'yes',
			'payment_method_whitelist' => array( 'duitnow_qr' ),
			'resolved_dnqr_group'      => array( 'duitnow_qr' ),
		) );
		$result = $this->callBypass( $gateway, 'dnqr' );
		$this->assertSame( 'https://example.com/checkout?preferred=duitnow_qr', $result );
	}

	/**
	 * When the customer explicitly picks DuitNow QR from the unified dropdown
	 * in a MIXED whitelist (other methods configured), the ?preferred=dnqr
	 * redirect must still happen. get_duitnow_qr_preferred() returns '' for
	 * mixed whitelists (its group-count rule is for the zero-click single
	 * method flow), so bypass_chip must use the explicit resolver instead.
	 */
	public function test_dnqr_tag_redirects_in_mixed_whitelist() {
		$gateway = $this->newGateway( array(
			'bypass_chip'              => 'yes',
			'payment_method_whitelist' => array( 'fpx', 'duitnow_qr', 'crypto_coin' ),
			'resolved_dnqr_group'      => array( 'dnqr' ),
		) );
		$result = $this->callBypass( $gateway, 'dnqr' );
		$this->assertSame( 'https://example.com/checkout?preferred=dnqr', $result );
	}

	public function test_dnqr_tag_redirects_in_mixed_whitelist_without_resolved_group() {
		// No resolved_dnqr_group set: fall back to 'dnqr'.
		$gateway = $this->newGateway( array(
			'bypass_chip'              => 'yes',
			'payment_method_whitelist' => array( 'fpx', 'duitnow_qr', 'razer_grabpay' ),
		) );
		$result = $this->callBypass( $gateway, 'dnqr' );
		$this->assertSame( 'https://example.com/checkout?preferred=dnqr', $result );
	}

	/**
	 * A merchant who upgraded from a legacy version may still have
	 * 'duitnow_qr' in the saved whitelist. When they explicitly pick
	 * DuitNow QR in a mixed whitelist and the resolver only has
	 * 'duitnow_qr' available, the redirect must use ?preferred=duitnow_qr
	 * (the group-count rule in get_duitnow_qr_preferred() returns '' for
	 * mixed whitelists, so the explicit path must still resolve it).
	 */
	public function test_dnqr_tag_redirects_to_duitnow_qr_in_mixed_legacy_whitelist() {
		$gateway = $this->newGateway( array(
			'bypass_chip'              => 'yes',
			'payment_method_whitelist' => array( 'fpx', 'duitnow_qr', 'crypto_coin' ),
			'resolved_dnqr_group'      => array( 'duitnow_qr' ),
		) );
		$result = $this->callBypass( $gateway, 'dnqr' );
		$this->assertSame( 'https://example.com/checkout?preferred=duitnow_qr', $result );
	}

	public function test_unknown_tag_returns_unchanged_url() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, 'bogus:xyz' );
		$this->assertSame( 'https://example.com/checkout', $result );
	}

	public function test_atome_clone_forces_atome_redirect_when_bypass_disabled() {
		// The wc_gateway_chip_5 (Atome) clone has bypass_chip=no and forces
		// the Atome redirect regardless of POST data.
		$gateway = $this->newGateway( array(
			'id'                       => 'wc_gateway_chip_5',
			'bypass_chip'              => 'no',
			'payment_method_whitelist' => array( 'razer_atome' ),
		) );
		$result = $this->callBypass( $gateway, 'razer:Atome' );
		$this->assertSame( 'https://example.com/checkout?preferred=razer_atome&razer_bank_code=Atome', $result );
	}

	public function test_atome_clone_forces_atome_redirect_when_bypass_enabled_no_post() {
		$gateway = $this->newGateway( array(
			'id'                       => 'wc_gateway_chip_5',
			'bypass_chip'              => 'yes',
			'payment_method_whitelist' => array( 'razer_atome' ),
		) );
		$result = $this->callBypass( $gateway, null );
		$this->assertSame( 'https://example.com/checkout?preferred=razer_atome&razer_bank_code=Atome', $result );
	}

	/**
	 * Legacy POST field names (chip_fpx_bank, chip_fpx_b2b1_bank,
	 * chip_razer_ewallet) are not read by bypass_chip(); only the unified
	 * chip_payment_method tag is consulted. These tests pin that behaviour
	 * so the legacy fields cannot accidentally be re-introduced as a hidden
	 * source of truth.
	 */
	public function test_legacy_fpx_post_field_is_ignored() {
		$_POST['chip_fpx_bank']       = 'MB2U0227';
		$_POST['chip_payment_method'] = 'fpx:MBB0228';
		try {
			$gateway = $this->newGatewayWithBypass();
			$result  = $this->callBypass( $gateway, 'fpx:MBB0228' );
			$this->assertStringContainsString( 'fpx_bank_code=MBB0228', $result );
			$this->assertStringNotContainsString( 'MB2U0227', $result );
		} finally {
			unset( $_POST['chip_fpx_bank'], $_POST['chip_payment_method'] );
		}
	}

	public function test_legacy_fpx_b2b1_post_field_is_ignored() {
		$_POST['chip_fpx_b2b1_bank'] = 'MB2U0227';
		$_POST['chip_payment_method'] = 'fpx_b2b1:PBB0234';
		try {
			$gateway = $this->newGatewayWithBypass();
			$result  = $this->callBypass( $gateway, 'fpx_b2b1:PBB0234' );
			$this->assertStringContainsString( 'fpx_bank_code=PBB0234', $result );
			$this->assertStringNotContainsString( 'MB2U0227', $result );
		} finally {
			unset( $_POST['chip_fpx_b2b1_bank'], $_POST['chip_payment_method'] );
		}
	}

	public function test_legacy_razer_ewallet_post_field_is_ignored() {
		$_POST['chip_razer_ewallet'] = 'MB2U_QRPay-Push';
		$_POST['chip_payment_method'] = 'razer:GrabPay';
		try {
			$gateway = $this->newGatewayWithBypass();
			$result  = $this->callBypass( $gateway, 'razer:GrabPay' );
			$this->assertStringContainsString( 'razer_bank_code=GrabPay', $result );
			$this->assertStringNotContainsString( 'MB2U_QRPay-Push', $result );
		} finally {
			unset( $_POST['chip_razer_ewallet'], $_POST['chip_payment_method'] );
		}
	}
}
