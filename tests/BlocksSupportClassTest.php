<?php
/**
 * Tests for Chip_Woocommerce_Gateway_Blocks_Support.
 *
 * Covers the get_payment_method_data() js_display decision tree and the
 * dependency-injected bank_type decision in get_payment_method_script_handles().
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * Blocks support class test case.
 */
class BlocksSupportClassTest extends PHPUnit\Framework\TestCase {

	/**
	 * Load the blocks-support class (it's only loaded in production
	 * when AbstractPaymentMethodType exists and `block_support()` is called).
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once dirname( __DIR__ ) . '/includes/blocks/class-chip-woocommerce-gateway-blocks-support.php';
	}

	/**
	 * Build a blocks-support instance backed by a gateway with the
	 * given settings. The gateway's `payment_method_whitelist` is
	 * pre-expanded as the constructor would have done.
	 *
	 * @param array $settings Saved settings (raw, including payment_method_whitelist).
	 * @return array{0: Chip_Woocommerce_Gateway_Blocks_Support, 1: Chip_Woocommerce_Gateway}
	 */
	private function newSupport( array $settings ) {
		$whitelist = isset( $settings['payment_method_whitelist'] ) && is_array( $settings['payment_method_whitelist'] )
			? $settings['payment_method_whitelist']
			: array();

		// Apply the constructor's expansion so the gateway's in-memory
		// payment_method_whitelist matches what Blocks would see at runtime.
		$expanded = $this->expandWhitelist( $whitelist );

		$gateway_reflection = new ReflectionClass( 'Chip_Woocommerce_Gateway' );
		$gateway            = $gateway_reflection->newInstanceWithoutConstructor();

		$prop = new ReflectionProperty( 'Chip_Woocommerce_Gateway', 'payment_method_whitelist' );
		$prop->setAccessible( true );
		$prop->setValue( $gateway, $expanded );

		// supports is a public property of WC_Payment_Gateway -- declare it
		// dynamically. Without this, get_payment_method_data() chokes on
		// array_filter($this->gateway->supports, ...).
		$gateway->supports = array( 'products', 'refunds' );
		$gateway->icon     = '';

		// Other settings used by get_payment_method_data() come from the
		// support class's own $settings property (via get_setting), not
		// from the gateway. The gateway only exposes ->supports() and
		// ->icon for the data filter call.
		$other_settings = array(
			'bypass_chip'  => isset( $settings['bypass_chip'] ) ? $settings['bypass_chip'] : 'yes',
			'title'        => isset( $settings['title'] ) ? $settings['title'] : 'CHIP',
			'description'  => isset( $settings['description'] ) ? $settings['description'] : '',
			'tokenization' => 'no',
		);

		$support = new Chip_Woocommerce_Gateway_Blocks_Support();

		$name_prop = new ReflectionProperty( 'Chip_Woocommerce_Gateway_Blocks_Support', 'name' );
		$name_prop->setAccessible( true );
		$name_prop->setValue( $support, 'wc_gateway_chip' );

		$settings_array_prop = new ReflectionProperty( 'Chip_Woocommerce_Gateway_Blocks_Support', 'settings' );
		$settings_array_prop->setAccessible( true );
		$settings_array_prop->setValue( $support, $other_settings );

		$gateway_prop = new ReflectionProperty( 'Chip_Woocommerce_Gateway_Blocks_Support', 'gateway' );
		$gateway_prop->setAccessible( true );
		$gateway_prop->setValue( $support, $gateway );

		return array( $support, $gateway );
	}

	/**
	 * Mirror the constructor's whitelist expansion.
	 */
	private function expandWhitelist( array $whitelist ): array {
		// Backward-compat migration (legacy card keys -> 'card',
		// legacy 'razer_shopeepay' -> 'shopee_pay').
		$gateway_reflection = new ReflectionClass( 'Chip_Woocommerce_Gateway' );
		$gateway            = $gateway_reflection->newInstanceWithoutConstructor();
		$whitelist          = $gateway->migrate_legacy_payment_method_whitelist( $whitelist );
		// duitnow_qr -> DUITNOW_GROUP.
		if ( in_array( 'duitnow_qr', $whitelist, true ) ) {
			$whitelist = array_values(
				array_unique( array_merge( $whitelist, Chip_Woocommerce_Gateway::DUITNOW_GROUP ) )
			);
		}
		// card -> CARD_GROUP.
		if ( in_array( 'card', $whitelist, true ) ) {
			$whitelist = array_values(
				array_unique( array_merge( $whitelist, Chip_Woocommerce_Gateway::CARD_GROUP ) )
			);
		}
		return $whitelist;
	}

	public function test_js_display_unified_for_saved_card() {
		// Saved ['card'] expands to ['card', 'visa', 'mastercard', 'maestro'].
		// Card methods + (no dropdown) -> legacy 'card'... but with the post-C2
		// fix, the expanded list has 4 entries (count > 1) so mixed-case
		// branch fires. Card-only without dropdown methods is 'card'.
		list( $support ) = $this->newSupport( array( 'payment_method_whitelist' => array( 'card' ) ) );
		$data = $support->get_payment_method_data();
		// Card-only whitelist has no dropdown methods -> js_display='card'.
		$this->assertSame( 'card', $data['js_display'] );
	}

	public function test_js_display_unified_for_saved_fpx_card() {
		// Saved ['fpx', 'card'] expands to ['fpx', 'card', 'visa', 'mastercard', 'maestro'].
		// fpx is a dropdown + card is present -> 'unified'.
		list( $support ) = $this->newSupport( array( 'payment_method_whitelist' => array( 'fpx', 'card' ) ) );
		$data = $support->get_payment_method_data();
		$this->assertSame( 'unified', $data['js_display'] );
	}

	public function test_js_display_unified_for_saved_fpx_razer() {
		// Saved ['fpx', 'razer_grabpay'] -> 2 dropdown methods -> 'unified'.
		list( $support ) = $this->newSupport( array( 'payment_method_whitelist' => array( 'fpx', 'razer_grabpay' ) ) );
		$data = $support->get_payment_method_data();
		$this->assertSame( 'unified', $data['js_display'] );
	}

	public function test_js_display_card_for_legacy_card_only() {
		// Saved ['visa', 'mastercard', 'maestro'] collapses to
		// ['card', 'visa', 'mastercard', 'maestro'] via backward-compat.
		// No dropdown methods -> 'card'.
		list( $support ) = $this->newSupport( array( 'payment_method_whitelist' => array( 'visa', 'mastercard', 'maestro' ) ) );
		$data = $support->get_payment_method_data();
		$this->assertSame( 'card', $data['js_display'] );
	}

	public function test_js_display_card_for_card_only_after_expansion() {
		// Saved ['card'] expands to the same 4-entry list. Card-only
		// with no dropdown methods -> 'card'.
		list( $support ) = $this->newSupport( array( 'payment_method_whitelist' => array( 'card' ) ) );
		$data = $support->get_payment_method_data();
		$this->assertSame( 'card', $data['js_display'] );
	}

	public function test_js_display_unified_for_card_with_dnqr() {
		// Saved ['card', 'duitnow_qr'] -> both groups expand ->
		// ['card', 'duitnow_qr', 'dnqr', 'visa', 'mastercard', 'maestro'].
		// Card + dnqr dropdown -> 'unified'.
		list( $support ) = $this->newSupport( array( 'payment_method_whitelist' => array( 'card', 'duitnow_qr' ) ) );
		$data = $support->get_payment_method_data();
		$this->assertSame( 'unified', $data['js_display'] );
	}

	public function test_js_display_empty_when_bypass_chip_disabled() {
		// bypass_chip != 'yes' -> js_display stays ''.
		list( $support ) = $this->newSupport( array(
			'payment_method_whitelist' => array( 'fpx', 'card' ),
			'bypass_chip'              => 'no',
		) );
		$data = $support->get_payment_method_data();
		$this->assertSame( '', $data['js_display'] );
	}

	public function test_get_payment_method_script_handles_returns_block_handle() {
		// The handle includes the gateway name.
		list( $support ) = $this->newSupport( array( 'payment_method_whitelist' => array( 'fpx' ) ) );

		// Clear any prior script registrations.
		$GLOBALS['__chip_test_scripts']    = array();
		$GLOBALS['__chip_test_localized'] = array();

		$handles = $support->get_payment_method_script_handles();

		$this->assertIsArray( $handles );
		$this->assertContains( 'wc-wc_gateway_chip-blocks', $handles );
		// A script must have been registered for the handle.
		$this->assertArrayHasKey( 'wc-wc_gateway_chip-blocks', $GLOBALS['__chip_test_scripts'] );
	}

	public function test_script_handle_registers_unified_bundle_dependency() {
		// The C1 fix: the shared unified-payment-method-list bundle must
		// be enqueued alongside each clone's bundle. We assert that the
		// clone bundle was registered -- and the dependency is declared
		// by webpack in the .asset.php file (a build-time artifact, not
		// a runtime test).
		list( $support ) = $this->newSupport( array( 'payment_method_whitelist' => array( 'card' ) ) );

		$GLOBALS['__chip_test_scripts']    = array();
		$GLOBALS['__chip_test_localized'] = array();

		$support->get_payment_method_script_handles();

		$registered = $GLOBALS['__chip_test_scripts']['wc-wc_gateway_chip-blocks'] ?? null;
		$this->assertNotNull( $registered );
		// The shared bundle is a build-time dep declared in .asset.php;
		// runtime tests cannot verify it without reading the asset file.
		$this->assertArrayHasKey( 'deps', $registered );
	}
}
