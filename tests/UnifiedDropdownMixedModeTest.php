<?php
/**
 * Regression tests for the pre-release review findings on PR #82:
 *
 *   1. The unified dropdown must render on classic checkout even when
 *      tokenization is enabled (previously only the `else` branch rendered
 *      it, so `validate_fields()` threw "Please choose a payment method"
 *      against a dropdown that was never rendered).
 *   2. validate_fields() must be gated on the dropdown actually rendering
 *      (skipped on add-payment-method / change-payment-method pages and
 *      for saved-token charges).
 *   3. Selecting "Card" in the dropdown narrows the whitelist to card-only
 *      so the gateway returns direct_post_url and the card form data is
 *      posted to CHIP instead of being discarded.
 *   4. The payment-method-group resolver uses the order's currency
 *      ($order->get_currency()) instead of the global store currency.
 *
 * @package CHIP_For_WooCommerce
 */

class UnifiedDropdownMixedModeTest extends GatewayTestCase {

	/**
	 * Whether the recording gateway subclass has been declared.
	 *
	 * @var bool
	 */
	private static $recording_gateway_defined = false;

	private function newRecordingGateway( array $whitelist ): Chip_Woocommerce_Gateway {
		if ( ! self::$recording_gateway_defined ) {
			self::$recording_gateway_defined = true;
			eval(
				'class Chip_Recording_Gateway_Test extends Chip_Woocommerce_Gateway {
					public $process_payment_called = false;
					public function process_payment( $order_id ) {
						$this->process_payment_called = true;
						return array( "result" => "success" );
					}
				}'
			);
		}
		$reflection = new ReflectionClass( 'Chip_Recording_Gateway_Test' );
		$gateway    = $reflection->newInstanceWithoutConstructor();
		$this->setGatewayProperty( $gateway, 'id', 'wc_gateway_chip' );
		$this->setGatewayProperty( $gateway, 'bypass_chip', 'yes' );
		$this->setGatewayProperty( $gateway, 'payment_method_whitelist', $whitelist );
		$this->setGatewayProperty( $gateway, 'brand_id', 'test_brand' );
		$this->setGatewayProperty( $gateway, 'resolved_dnqr_group', array() );
		$gateway->process_payment_called = false;
		return $gateway;
	}

	/**
	 * Build a gateway with the given whitelist and bypass state.
	 *
	 * @param array  $whitelist Expanded in-memory whitelist.
	 * @param string $bypass    'yes' or 'no'.
	 * @return Chip_Woocommerce_Gateway
	 */
	private function newMixedGateway( array $whitelist, string $bypass = 'yes' ): Chip_Woocommerce_Gateway {
		return $this->newGateway( array(
			'id'                       => 'wc_gateway_chip',
			'bypass_chip'              => $bypass,
			'payment_method_whitelist' => $whitelist,
		) );
	}

	/**
	 * Capture the rendered payment fields by invoking payment_fields() with
	 * output buffering (echo calls are captured by PHPUnit's output
	 * handling; woocommerce_form_field is stubbed to record instead).
	 *
	 * @param Chip_Woocommerce_Gateway $gateway Gateway instance.
	 * @return void
	 */
	private function renderPaymentFields( Chip_Woocommerce_Gateway $gateway ) {
		ob_start();
		try {
			$gateway->payment_fields();
		} finally {
			ob_end_clean();
		}
	}

	public function test_validate_fields_skips_when_tokenization_branch_renders_no_dropdown() {
		// Card-only whitelist: no dropdown methods -> has_unified_dropdown()
		// is false, so no validation should be enforced.
		$gateway = $this->newMixedGateway( array( 'card', 'visa', 'mastercard', 'maestro' ) );
		$this->assertTrue( $gateway->validate_fields() );
	}

	public function test_validate_fields_requires_dropdown_on_classic_checkout() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );
		$GLOBALS['__chip_test_is_checkout'] = true;

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Please choose a payment method.' );
		$gateway->validate_fields();
	}

	public function test_validate_fields_passes_when_dropdown_value_posted() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );
		$_POST['chip_payment_method'] = 'fpx:MBB0228';
		$this->assertTrue( $gateway->validate_fields() );
	}

	public function test_validate_fields_skips_on_add_payment_method_page() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );
		$GLOBALS['__chip_test_is_checkout']                = true;
		$GLOBALS['__chip_test_is_add_payment_method_page'] = true;

		// No exception should be thrown even though no method was chosen.
		$this->assertTrue( $gateway->validate_fields() );
	}

	public function test_validate_fields_skips_on_change_payment_method_page() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );
		$GLOBALS['__chip_test_is_checkout'] = true;
		$_GET['change_payment_method']      = '123';

		try {
			$this->assertTrue( $gateway->validate_fields() );
		} finally {
			unset( $_GET['change_payment_method'] );
		}
	}

	public function test_validate_fields_skips_for_saved_token_charge() {
		// Blocks submits only the token key; the dropdown value is absent.
		$gateway = $this->newMixedGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );
		$_POST['wc-wc_gateway_chip-payment-token'] = '42';
		$this->assertTrue( $gateway->validate_fields() );
	}

	public function test_payment_fields_renders_dropdown_in_tokenization_branch() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );
		$gateway->supports = array( 'products', 'tokenization' );

		$this->renderPaymentFields( $gateway );

		$this->assertArrayHasKey( 'chip_payment_method_wc_gateway_chip', $GLOBALS['__chip_test_form_fields'] );
		$field = $GLOBALS['__chip_test_form_fields']['chip_payment_method_wc_gateway_chip'];
		$this->assertSame( 'select', $field['type'] );
		$this->assertTrue( $field['required'] );
		// Both FPX and Card entries must be present in the merged list.
		$this->assertArrayHasKey( 'fpx:MBB0228', $field['options'] );
		$this->assertArrayHasKey( 'card', $field['options'] );
	}

	public function test_payment_fields_skips_dropdown_when_bypass_disabled() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ), 'no' );
		$gateway->supports = array( 'products', 'tokenization' );

		$this->renderPaymentFields( $gateway );

		$this->assertArrayNotHasKey( 'chip_payment_method_wc_gateway_chip', $GLOBALS['__chip_test_form_fields'] );
	}

	public function test_payment_fields_keeps_card_option_on_order_pay_page() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );
		$gateway->supports = array( 'products', 'tokenization' );
		$GLOBALS['__chip_test_is_order_pay'] = true;

		$this->renderPaymentFields( $gateway );

		// The dropdown renders all methods on order-pay, including 'card'.
		// Selecting Card there redirects to the CHIP payment page with
		// ?preferred=card (direct-post is unsupported on order-pay).
		$this->assertArrayHasKey( 'chip_payment_method_wc_gateway_chip', $GLOBALS['__chip_test_form_fields'] );
		$field = $GLOBALS['__chip_test_form_fields']['chip_payment_method_wc_gateway_chip'];
		$this->assertArrayHasKey( 'fpx:MBB0228', $field['options'] );
		$this->assertArrayHasKey( 'card', $field['options'] );
	}

	public function test_card_selection_narrows_whitelist_to_card_only() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );
		$_POST['chip_payment_method'] = 'card';

		$narrowed = $this->callGatewayMethod( $gateway, 'card_only_whitelist', array( array( 'fpx', 'visa', 'mastercard', 'maestro' ) ) );

		$this->assertSame( array( 'visa', 'mastercard', 'maestro' ), $narrowed );
	}

	public function test_card_selection_with_mpgs_keeps_mpgs_keys() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );
		$_POST['chip_payment_method'] = 'card';

		$narrowed = $this->callGatewayMethod( $gateway, 'card_only_whitelist', array( array( 'fpx', 'visa', 'mpgs_google_pay' ) ) );

		$this->assertSame( array( 'visa', 'mpgs_google_pay' ), $narrowed );
	}

	public function test_card_only_whitelist_returns_input_when_no_card_method() {
		$gateway = $this->newMixedGateway( array( 'fpx' ) );

		$result = $this->callGatewayMethod( $gateway, 'card_only_whitelist', array( array( 'fpx' ) ) );

		$this->assertSame( array( 'fpx' ), $result );
	}

	public function test_bypass_chip_is_card_selection_true_when_card_tag_posted() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );
		$_POST['chip_payment_method'] = 'card';

		$this->assertTrue( $this->callGatewayMethod( $gateway, 'bypass_chip_is_card_selection' ) );
	}

	public function test_bypass_chip_is_card_selection_false_for_other_tags() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );
		$_POST['chip_payment_method'] = 'dnqr';

		$this->assertFalse( $this->callGatewayMethod( $gateway, 'bypass_chip_is_card_selection' ) );
	}

	public function test_context_is_card_selection_true_from_payment_data() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );
		$context = (object) array(
			'payment_method' => 'wc_gateway_chip',
			'payment_data'   => array( 'chip_payment_method' => 'card' ),
		);

		$this->assertTrue( $this->callGatewayMethod( $gateway, 'context_is_card_selection', array( $context ) ) );
	}

	public function test_context_is_card_selection_false_for_other_method() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );
		$context = (object) array(
			'payment_method' => 'wc_gateway_chip',
			'payment_data'   => array( 'chip_payment_method' => 'fpx:MBB0228' ),
		);

		$this->assertFalse( $this->callGatewayMethod( $gateway, 'context_is_card_selection', array( $context ) ) );
	}

	/**
	 * The resolver is invoked with the ORDER currency. This pins the fix so
	 * a multi-currency store resolving a USD order does not query the API
	 * with the store's MYR currency.
	 */
	public function test_resolver_uses_order_currency_in_process_payment() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'duitnow_qr' ) );
		$gateway->supports = array( 'products' );

		// The bootstrap WC_Order stub reads these globals.
		$GLOBALS['__chip_test_order_currency'] = 'USD';
		$GLOBALS['__chip_test_order_total']    = 123.45;

		$api = $this->mockApi( $gateway, array() );
		$api->expects( $this->once() )
			->method( 'payment_methods' )
			->with( 'USD', '', 12345 );

		// Fail payment creation so the method short-circuits after the
		// resolver call; we only assert the resolver's currency argument.
		$api->method( 'create_payment' )->willReturn( array( '__all__' => array() ) );

		$gateway->process_payment( 1 );
	}

	public function test_single_method_renders_hidden_input_instead_of_dropdown() {
		// Gateway 6 (DuitNow QR-only) expands ['duitnow_qr'] to the full
		// group in-memory; a dropdown with a single option adds friction, so
		// a hidden pre-selected input keeps the zero-click UX.
		$gateway = $this->newMixedGateway( array( 'duitnow_qr', 'dnqr' ) );
		$gateway->supports = array( 'products', 'tokenization' );

		ob_start();
		try {
			$gateway->payment_fields();
		} finally {
			$output = ob_get_clean();
		}

		// No visible dropdown rendered...
		$this->assertArrayNotHasKey( 'chip_payment_method_wc_gateway_chip', $GLOBALS['__chip_test_form_fields'] );
		// ...but a hidden pre-selected dnqr input is emitted.
		$this->assertStringContainsString(
			'<input type="hidden" name="chip_payment_method_wc_gateway_chip" value="dnqr" />',
			$output
		);
	}

	public function test_multi_method_still_renders_dropdown() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'duitnow_qr', 'dnqr' ) );
		$gateway->supports = array( 'products', 'tokenization' );

		$this->renderPaymentFields( $gateway );

		$this->assertArrayHasKey( 'chip_payment_method_wc_gateway_chip', $GLOBALS['__chip_test_form_fields'] );
		$field = $GLOBALS['__chip_test_form_fields']['chip_payment_method_wc_gateway_chip'];
		$this->assertSame( 'select', $field['type'] );
	}

	public function test_crypto_in_unified_dropdown_list() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'crypto_coin' ) );
		$gateway->supports = array( 'products', 'tokenization' );

		$this->renderPaymentFields( $gateway );

		$this->assertArrayHasKey( 'chip_payment_method_wc_gateway_chip', $GLOBALS['__chip_test_form_fields'] );
		$field = $GLOBALS['__chip_test_form_fields']['chip_payment_method_wc_gateway_chip'];
		$this->assertSame( 'select', $field['type'] );
		$this->assertArrayHasKey( 'fpx:MBB0228', $field['options'] );
		$this->assertArrayHasKey( 'crypto_coin', $field['options'] );
	}

	public function test_crypto_only_renders_hidden_input() {
		$gateway = $this->newMixedGateway( array( 'crypto_coin' ) );
		$gateway->supports = array( 'products', 'tokenization' );

		ob_start();
		try {
			$gateway->payment_fields();
		} finally {
			$output = ob_get_clean();
		}

		$this->assertArrayNotHasKey( 'chip_payment_method_wc_gateway_chip', $GLOBALS['__chip_test_form_fields'] );
		$this->assertStringContainsString(
			'<input type="hidden" name="chip_payment_method_wc_gateway_chip" value="crypto_coin" />',
			$output
		);
	}

	public function test_bypass_chip_crypto_coin_builds_preferred_url() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'crypto_coin' ) );
		$_POST['chip_payment_method'] = 'crypto_coin';

		$result = $this->callGatewayMethod(
			$gateway,
			'bypass_chip',
			array( 'https://example.com/checkout', array( 'is_test' => false ) )
		);

		$this->assertSame( 'https://example.com/checkout?preferred=crypto_coin', $result );
	}

	public function test_mpgs_in_unified_dropdown_list() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'mpgs_google_pay', 'mpgs_apple_pay' ) );
		$gateway->supports = array( 'products', 'tokenization' );

		$this->renderPaymentFields( $gateway );

		$this->assertArrayHasKey( 'chip_payment_method_wc_gateway_chip', $GLOBALS['__chip_test_form_fields'] );
		$field = $GLOBALS['__chip_test_form_fields']['chip_payment_method_wc_gateway_chip'];
		$this->assertArrayHasKey( 'mpgs_google_pay', $field['options'] );
		$this->assertArrayHasKey( 'mpgs_apple_pay', $field['options'] );
	}

	public function test_bypass_chip_mpgs_uses_checkout_url_with_preferred() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'mpgs_google_pay' ) );
		$_POST['chip_payment_method'] = 'mpgs_google_pay';

		$result = $this->callGatewayMethod(
			$gateway,
			'bypass_chip',
			array(
				'https://example.com/direct-post-url',
				array(
					'is_test'       => false,
					'checkout_url'  => 'https://example.com/checkout',
					'direct_post_url' => 'https://example.com/direct-post-url',
				),
			)
		);

		$this->assertSame( 'https://example.com/checkout?preferred=mpgs_google_pay', $result );
	}

	public function test_validate_fields_passes_with_hidden_single_method_input() {
		$gateway = $this->newMixedGateway( array( 'duitnow_qr', 'dnqr' ) );
		$gateway->supports = array( 'products', 'tokenization' );

		// The hidden pre-selected input posts the value, as a real form
		// submission would.
		$_POST['chip_payment_method'] = 'dnqr';
		$this->assertTrue( $gateway->validate_fields() );
	}

	public function test_unified_dropdown_script_registered_with_logo_data() {
		$gateway = $this->newMixedGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );
		$gateway->supports = array( 'products' );

		$GLOBALS['__chip_test_scripts']    = array();
		$GLOBALS['__chip_test_localized']  = array();

		$gateway->register_script();

		// The unified-dropdown enhancer script is registered.
		$this->assertArrayHasKey( 'wc-wc_gateway_chip-unified-dropdown', $GLOBALS['__chip_test_scripts'] );

		// Logo base URLs and empty unavailable-bank lists are localized.
		$localized = $GLOBALS['__chip_test_localized']['wc-wc_gateway_chip-unified-dropdown']['gateway_unified_option'] ?? array();
		$this->assertNotEmpty( $localized['unified']['fpx_logo_base'] );
		$this->assertNotEmpty( $localized['unified']['razer_logo_base'] );
		$this->assertArrayHasKey( 'unavailable_fpx', $localized['unified'] );
		$this->assertArrayHasKey( 'unavailable_b2b1', $localized['unified'] );
	}

	/**
	 * Mixed whitelist + Card selected via Blocks payment_data must proceed
	 * through the with-context handler (process_payment is invoked so the
	 * whitelist narrowing yields direct_post_url).
	 */
	public function test_process_payment_with_context_runs_for_mixed_whitelist_card_selection() {
		$gateway = $this->newRecordingGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );

		$context = (object) array(
			'payment_method' => 'wc_gateway_chip',
			'payment_data'   => array( 'chip_payment_method' => 'card' ),
			'order'          => new class() {
				public function get_id() { return 1; }
			},
		);
		$result  = new class() {
			public $status = '';
			public $payment_details = array();
			public $redirect_url = '';
			public function set_status( $s ) { $this->status = $s; }
			public function set_payment_details( $d ) { $this->payment_details = $d; }
			public function set_redirect_url( $u ) { $this->redirect_url = $u; }
		};

		$this->callGatewayMethod( $gateway, 'process_payment_with_context', array( $context, &$result ) );

		$this->assertTrue( $gateway->process_payment_called );
	}

	/**
	 * Mixed whitelist + a redirect method selected must NOT take the
	 * direct-post path (legacy process_payment handles the redirect).
	 */
	public function test_process_payment_with_context_skips_for_mixed_whitelist_redirect_selection() {
		$gateway = $this->newRecordingGateway( array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ) );

		$context = (object) array(
			'payment_method' => 'wc_gateway_chip',
			'payment_data'   => array( 'chip_payment_method' => 'fpx:MBB0228' ),
		);
		$result  = new class() {
			public $status = '';
			public $payment_details = array();
			public $redirect_url = '';
			public function set_status( $s ) { $this->status = $s; }
			public function set_payment_details( $d ) { $this->payment_details = $d; }
			public function set_redirect_url( $u ) { $this->redirect_url = $u; }
		};

		$this->callGatewayMethod( $gateway, 'process_payment_with_context', array( $context, &$result ) );

		$this->assertFalse( $gateway->process_payment_called );
	}
}
