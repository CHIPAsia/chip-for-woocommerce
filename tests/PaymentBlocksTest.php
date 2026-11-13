<?php
/**
 * Tests for merchant-created payment blocks.
 *
 * A payment block is one checkout row backed by its own gateway instance. The
 * tests pin the two things that decide whether the feature is safe to ship:
 *
 *  1. adding a block never changes what an existing merchant sees (the six
 *     standard gateways are registered exactly as before);
 *  2. a created block sends only the methods the merchant selected, inherits
 *     the site's credentials, and submits a single e-wallet directly.
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * Payment block registry, instance and Blocks-support tests.
 */
class PaymentBlocksTest extends GatewayTestCase {

	/**
	 * Reset the in-memory option store before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['__chip_test_options'] = array();
	}

	/**
	 * Call a method declared on the block gateway itself.
	 *
	 * GatewayTestCase::callGatewayMethod() reflects on the base class, which
	 * cannot see a subclass-only method.
	 *
	 * @param object $gateway Gateway instance.
	 * @param string $name    Method name.
	 * @return mixed
	 */
	protected function callBlockMethod( $gateway, $name ) {
		$method = new ReflectionMethod( $gateway, $name );
		$method->setAccessible( true );
		return $method->invoke( $gateway );
	}

	/**
	 * Add a block with two e-wallets.
	 *
	 * @return string Block id.
	 */
	protected function add_two_wallet_block() {
		$id = Chip_Woocommerce_Payment_Blocks::add_block(
			'ewallet',
			array(
				'methods' => array( 'razer_grabpay', 'razer_tng' ),
				'title'   => 'GrabPay & Touch n Go',
			)
		);
		$this->assertIsString( $id );

		return $id;
	}

	/**
	 * Family method lists must stay inside the gateway's label map, and the
	 * labels must be the shared strings (ShopeePay, DuitNow QR, Stablecoin).
	 *
	 * @return void
	 */
	public function test_family_definitions_use_shared_method_labels() {
		$labels = Chip_Woocommerce_Gateway::payment_method_labels();

		foreach ( Chip_Woocommerce_Payment_Blocks::payment_families() as $key => $family ) {
			$this->assertNotEmpty( $family['methods'], 'Family ' . $key . ' has no methods.' );
			foreach ( $family['methods'] as $method ) {
				$this->assertArrayHasKey( $method, $labels, 'Unknown method ' . $method . ' in family ' . $key );
			}
		}

		$this->assertSame( 'ShopeePay', $labels['shopee_pay'] );
		$this->assertSame( 'DuitNow QR', $labels['duitnow_qr'] );
		$this->assertSame( 'Stablecoin', $labels['crypto_coin'] );
		$this->assertSame( "Touch 'n Go eWallet", $labels['razer_tng'] );
	}

	/**
	 * Creating a block writes the registry row and a complete settings row, so
	 * the block works before the merchant ever opens its settings screen.
	 *
	 * @return void
	 */
	public function test_add_block_seeds_registry_and_settings() {
		$id = $this->add_two_wallet_block();

		$block = Chip_Woocommerce_Payment_Blocks::get_block( $id );
		$this->assertSame( 'ewallet', $block['family'] );
		$this->assertSame( 'chip_block_' . $id, Chip_Woocommerce_Payment_Blocks::gateway_id( $id ) );

		$settings = Chip_Woocommerce_Payment_Blocks::get_block_settings( $id );
		$this->assertSame( array( 'razer_grabpay', 'razer_tng' ), $settings['payment_method_whitelist'] );
		$this->assertSame( 'yes', $settings['enabled'] );
		$this->assertSame( 'GrabPay & Touch n Go', $settings['title'] );
		$this->assertSame( 'yes', $settings['bypass_chip'] );
		$this->assertSame( 'auto', $settings['display_logo'] );
	}

	/**
	 * Every id test in the plugin goes through one helper: standard blocks
	 * ("wc_gateway_chip*") and created blocks ("chip_block_*") are both CHIP.
	 *
	 * @return void
	 */
	public function test_is_chip_gateway_id_accepts_both_families() {
		$this->assertTrue( Chip_Woocommerce_Payment_Blocks::is_chip_gateway_id( 'wc_gateway_chip' ) );
		$this->assertTrue( Chip_Woocommerce_Payment_Blocks::is_chip_gateway_id( 'wc_gateway_chip_4' ) );
		$this->assertTrue( Chip_Woocommerce_Payment_Blocks::is_chip_gateway_id( 'chip_block_ab12cd34' ) );
		$this->assertFalse( Chip_Woocommerce_Payment_Blocks::is_chip_gateway_id( 'bacs' ) );
		$this->assertFalse( Chip_Woocommerce_Payment_Blocks::is_chip_gateway_id( 'chip_block' ) );
	}

	/**
	 * The credential source names the gateway the block inherits from, so the
	 * settings screen can say so instead of showing empty fields.
	 *
	 * @return void
	 */
	public function test_credential_source_names_the_primary_gateway() {
		update_option(
			'woocommerce_wc_gateway_chip_settings',
			array(
				'brand_id'   => 'brand-12345678',
				'secret_key' => 'secret',
				'title'      => 'Online Banking (FPX)',
			)
		);

		$source = Chip_Woocommerce_Payment_Blocks::credential_source();

		$this->assertTrue( $source['found'] );
		$this->assertSame( 'wc_gateway_chip', $source['gateway_id'] );
		$this->assertSame( 'Online Banking (FPX)', $source['title'] );
		$this->assertSame( 'brand-12345678', $source['brand_id'] );
	}

	/**
	 * A block that does hold credentials can also be the source — that is how the
	 * "Copy from Existing" list and the inheritance fallback stay consistent.
	 *
	 * @return void
	 */
	public function test_credential_source_falls_back_to_a_created_block() {
		$id = $this->add_two_wallet_block();

		$settings               = Chip_Woocommerce_Payment_Blocks::get_block_settings( $id );
		$settings['brand_id']   = 'block-brand';
		$settings['secret_key'] = 'block-secret';
		$settings['title']      = 'GrabPay & Touch n Go';
		update_option( Chip_Woocommerce_Payment_Blocks::settings_key( $id ), $settings );

		$source = Chip_Woocommerce_Payment_Blocks::credential_source();

		$this->assertTrue( $source['found'] );
		$this->assertSame( Chip_Woocommerce_Payment_Blocks::gateway_id( $id ), $source['gateway_id'] );
	}

	/**
	 * The block's settings screen explains where the credentials come from.
	 *
	 * @return void
	 */
	public function test_settings_screen_explains_inherited_credentials() {
		update_option(
			'woocommerce_wc_gateway_chip_settings',
			array(
				'brand_id'   => 'brand-12345678',
				'secret_key' => 'secret',
				'title'      => 'Online Banking (FPX)',
			)
		);

		$id = $this->add_two_wallet_block();

		$gateway = new Chip_Woocommerce_Gateway_Block(
			array(
				'id'     => $id,
				'family' => 'ewallet',
			)
		);

		$brand_note = $gateway->form_fields['brand_id']['description'];

		$this->assertStringContainsString( 'inherited from', strtolower( $brand_note ) );
		$this->assertStringContainsString( 'Online Banking (FPX)', $brand_note );
		$this->assertStringContainsString( 'bran…78', $brand_note );
	}

	/**
	 * A Buy Now Pay Later block forces the Atome redirect like the standard one,
	 * even though its gateway id is chip_block_*.
	 *
	 * @return void
	 */
	public function test_bnpl_block_is_recognised_as_atome() {
		$id = Chip_Woocommerce_Payment_Blocks::add_block( 'bnpl', array( 'methods' => array( 'razer_atome' ) ) );

		$gateway = new Chip_Woocommerce_Gateway_Block(
			array(
				'id'     => $id,
				'family' => 'bnpl',
			)
		);

		$this->assertTrue( $this->callBlockMethod( $gateway, 'is_atome_gateway' ) );

		$ewallet_id = $this->add_two_wallet_block();
		$ewallet    = new Chip_Woocommerce_Gateway_Block(
			array(
				'id'     => $ewallet_id,
				'family' => 'ewallet',
			)
		);
		$this->assertFalse( $this->callBlockMethod( $ewallet, 'is_atome_gateway' ) );
	}

	/**
	 * Created blocks join the list of CHIP gateways used for credential copy.
	 *
	 * @return void
	 */
	public function test_gateway_ids_include_created_blocks() {
		$id  = $this->add_two_wallet_block();
		$ids = Chip_Woocommerce_Payment_Blocks::gateway_ids();

		$this->assertContains( 'wc_gateway_chip', $ids );
		$this->assertContains( Chip_Woocommerce_Payment_Blocks::gateway_id( $id ), $ids );
	}

	/**
	 * The admin title follows the block title, so a block is identifiable on
	 * WooCommerce → Payments instead of duplicating the family label.
	 *
	 * @return void
	 */
	public function test_admin_title_follows_block_title() {
		$id = Chip_Woocommerce_Payment_Blocks::add_block(
			'ewallet',
			array(
				'methods' => array( 'shopee_pay' ),
				'title'   => 'ShopeePay',
			)
		);

		$this->assertSame( 'CHIP ShopeePay', Chip_Woocommerce_Payment_Blocks::get_block_settings( $id )['method_title'] );
	}

	/**
	 * A block saved before the titles were linked still shows its own name in
	 * the admin list (in memory, without a write on every page load).
	 *
	 * @return void
	 */
	public function test_legacy_block_admin_title_is_rebased_on_its_title() {
		$id = Chip_Woocommerce_Payment_Blocks::add_block(
			'ewallet',
			array(
				'methods' => array( 'shopee_pay' ),
				'title'   => 'ShopeePay & ShopeePay Later',
			)
		);

		// Simulate a row written before this change: family label as admin title.
		$settings                 = Chip_Woocommerce_Payment_Blocks::get_block_settings( $id );
		$settings['method_title'] = 'CHIP E-Wallet';
		update_option( Chip_Woocommerce_Payment_Blocks::settings_key( $id ), $settings );

		$gateway = new Chip_Woocommerce_Gateway_Block(
			array(
				'id'     => $id,
				'family' => 'ewallet',
			)
		);

		$this->assertSame( 'CHIP ShopeePay & ShopeePay Later', $gateway->get_method_title() );
	}

	/**
	 * A deliberately renamed admin title is left alone.
	 *
	 * @return void
	 */
	public function test_custom_admin_title_is_preserved() {
		$id = Chip_Woocommerce_Payment_Blocks::add_block(
			'ewallet',
			array(
				'methods' => array( 'shopee_pay' ),
				'title'   => 'ShopeePay',
			)
		);

		$settings                 = Chip_Woocommerce_Payment_Blocks::get_block_settings( $id );
		$settings['method_title'] = 'CHIP Wallet — Shopee only';
		update_option( Chip_Woocommerce_Payment_Blocks::settings_key( $id ), $settings );

		$gateway = new Chip_Woocommerce_Gateway_Block(
			array(
				'id'     => $id,
				'family' => 'ewallet',
			)
		);

		$this->assertSame( 'CHIP Wallet — Shopee only', $gateway->get_method_title() );
	}

	/**
	 * A selection with nothing from the family is refused instead of saved as
	 * an empty whitelist (which CHIP reads as "allow everything").
	 *
	 * @return void
	 */
	public function test_add_block_refuses_empty_selection() {
		$result = Chip_Woocommerce_Payment_Blocks::add_block( 'ewallet', array( 'methods' => array( 'fpx' ) ) );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( array(), Chip_Woocommerce_Payment_Blocks::get_blocks() );
	}

	/**
	 * Unknown families are refused.
	 *
	 * @return void
	 */
	public function test_add_block_refuses_unknown_family() {
		$result = Chip_Woocommerce_Payment_Blocks::add_block( 'nope', array( 'methods' => array( 'fpx' ) ) );

		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Editing methods and title keeps every other stored setting, including
	 * credentials the merchant may have overridden on this block.
	 *
	 * @return void
	 */
	public function test_update_block_keeps_unrelated_settings() {
		$id = $this->add_two_wallet_block();

		$settings              = Chip_Woocommerce_Payment_Blocks::get_block_settings( $id );
		$settings['brand_id']  = 'own-brand';
		$settings['secret_key'] = 'own-secret';
		update_option( Chip_Woocommerce_Payment_Blocks::settings_key( $id ), $settings );

		$result = Chip_Woocommerce_Payment_Blocks::update_block(
			$id,
			array(
				'methods' => array( 'shopee_pay' ),
				'title'   => 'ShopeePay',
			)
		);

		$this->assertTrue( $result );

		$updated = Chip_Woocommerce_Payment_Blocks::get_block_settings( $id );
		$this->assertSame( array( 'shopee_pay' ), $updated['payment_method_whitelist'] );
		$this->assertSame( 'ShopeePay', $updated['title'] );
		$this->assertSame( 'own-brand', $updated['brand_id'] );
		$this->assertSame( 'own-secret', $updated['secret_key'] );
	}

	/**
	 * Updating to an empty selection is refused and the stored selection stays.
	 *
	 * @return void
	 */
	public function test_update_block_refuses_empty_selection() {
		$id = $this->add_two_wallet_block();

		$result = Chip_Woocommerce_Payment_Blocks::update_block( $id, array( 'methods' => array( 'razer_atome' ) ) );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame(
			array( 'razer_grabpay', 'razer_tng' ),
			Chip_Woocommerce_Payment_Blocks::get_block_settings( $id )['payment_method_whitelist']
		);
	}

	/**
	 * Deleting a block removes both the registry row and its settings.
	 *
	 * @return void
	 */
	public function test_delete_block_removes_row_and_settings() {
		$id = $this->add_two_wallet_block();

		$this->assertTrue( Chip_Woocommerce_Payment_Blocks::delete_block( $id ) );
		$this->assertNull( Chip_Woocommerce_Payment_Blocks::get_block( $id ) );
		$this->assertArrayNotHasKey( Chip_Woocommerce_Payment_Blocks::settings_key( $id ), $GLOBALS['__chip_test_options'] );
	}

	/**
	 * The registry ignores rows that lost their family, so a corrupted option
	 * cannot register a gateway with no behaviour behind it.
	 *
	 * @return void
	 */
	public function test_registry_ignores_unknown_family_rows() {
		update_option(
			Chip_Woocommerce_Payment_Blocks::OPTION,
			array(
				array(
					'id'     => 'abcd1234',
					'family' => 'removed-family',
				),
			)
		);

		$this->assertSame( array(), Chip_Woocommerce_Payment_Blocks::get_blocks() );
	}

	/**
	 * The gateway instance takes its id from the block and its defaults from the
	 * family, so it is a normal CHIP gateway to every other code path.
	 *
	 * @return void
	 */
	public function test_gateway_instance_uses_block_identity() {
		$id = $this->add_two_wallet_block();

		$gateway = new Chip_Woocommerce_Gateway_Block(
			array(
				'id'     => $id,
				'family' => 'ewallet',
			)
		);

		$this->assertSame( 'chip_block_' . $id, $gateway->id );
		$this->assertSame( 'GrabPay & Touch n Go', $gateway->get_title() );
		$this->assertSame(
			array( 'razer_grabpay', 'razer_tng' ),
			$this->getGatewayProperty( $gateway, 'payment_method_whitelist' )
		);
	}

	/**
	 * A block with no credentials of its own uses the primary block's, so the
	 * merchant enters the secret key once per site.
	 *
	 * @return void
	 */
	public function test_gateway_inherits_credentials() {
		update_option(
			'woocommerce_wc_gateway_chip_settings',
			array(
				'brand_id'   => 'primary-brand',
				'secret_key' => 'primary-secret',
				'public_key' => 'primary-public',
			)
		);

		$id      = $this->add_two_wallet_block();
		$gateway = new Chip_Woocommerce_Gateway_Block(
			array(
				'id'     => $id,
				'family' => 'ewallet',
			)
		);

		$this->assertSame( 'primary-brand', $this->getGatewayProperty( $gateway, 'brand_id' ) );
		$this->assertSame( 'primary-secret', $this->getGatewayProperty( $gateway, 'secret_key' ) );
		$this->assertSame( 'primary-public', $this->getGatewayProperty( $gateway, 'public_key' ) );
	}

	/**
	 * An overridden credential on the block itself wins over the primary.
	 *
	 * @return void
	 */
	public function test_gateway_prefers_own_credentials() {
		update_option(
			'woocommerce_wc_gateway_chip_settings',
			array(
				'brand_id'   => 'primary-brand',
				'secret_key' => 'primary-secret',
			)
		);

		$id = $this->add_two_wallet_block();

		$settings               = Chip_Woocommerce_Payment_Blocks::get_block_settings( $id );
		$settings['brand_id']   = 'own-brand';
		$settings['secret_key'] = 'own-secret';
		update_option( Chip_Woocommerce_Payment_Blocks::settings_key( $id ), $settings );

		$gateway = new Chip_Woocommerce_Gateway_Block(
			array(
				'id'     => $id,
				'family' => 'ewallet',
			)
		);

		$this->assertSame( 'own-brand', $this->getGatewayProperty( $gateway, 'brand_id' ) );
		$this->assertSame( 'own-secret', $this->getGatewayProperty( $gateway, 'secret_key' ) );
	}

	/**
	 * A block holding one e-wallet submits it directly — no one-option dropdown.
	 *
	 * @return void
	 */
	public function test_single_wallet_block_submits_directly() {
		$id = Chip_Woocommerce_Payment_Blocks::add_block(
			'ewallet',
			array(
				'methods'     => array( 'shopee_pay' ),
				'title'       => 'ShopeePay',
				'description' => 'Pay with ShopeePay',
			)
		);

		$gateway = new Chip_Woocommerce_Gateway_Block(
			array(
				'id'     => $id,
				'family' => 'ewallet',
			)
		);

		ob_start();
		$gateway->payment_fields();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="chip_payment_method_chip_block_' . $id . '"', $html );
		$this->assertStringContainsString( 'value="razer:ShopeePay"', $html );
		$this->assertStringNotContainsString( '<select', $html );
	}

	/**
	 * Two wallets keep the normal dropdown, so the customer still chooses.
	 *
	 * Asserted on the decision itself rather than the rendered markup: above
	 * one wallet the block intentionally falls through to the standard
	 * gateway's dropdown, which is covered by the existing checkout tests.
	 *
	 * @return void
	 */
	public function test_two_wallet_block_keeps_the_dropdown() {
		$id = $this->add_two_wallet_block();

		$gateway = new Chip_Woocommerce_Gateway_Block(
			array(
				'id'     => $id,
				'family' => 'ewallet',
			)
		);

		$this->assertSame( '', $this->callBlockMethod( $gateway, 'solo_razer_code' ) );
	}

	/**
	 * The solo path resolves the wallet the block actually offers.
	 *
	 * @return void
	 */
	public function test_solo_code_resolves_the_configured_wallet() {
		$id = Chip_Woocommerce_Payment_Blocks::add_block(
			'ewallet',
			array( 'methods' => array( 'razer_tng' ) )
		);

		$gateway = new Chip_Woocommerce_Gateway_Block(
			array(
				'id'     => $id,
				'family' => 'ewallet',
			)
		);

		$this->assertSame( 'TNG-EWALLET', $this->callBlockMethod( $gateway, 'solo_razer_code' ) );
	}

	/**
	 * The Blocks support type takes the block's name and reuses its family asset.
	 *
	 * @return void
	 */
	public function test_blocks_support_uses_block_name_and_family_asset() {
		// The Blocks support classes are only included on the
		// woocommerce_blocks_loaded hook, which the test suite never fires.
		require_once dirname( __DIR__ ) . '/includes/blocks/class-chip-woocommerce-gateway-blocks-support.php';
		require_once dirname( __DIR__ ) . '/includes/blocks/class-chip-woocommerce-gateway-block-blocks-support.php';

		$support = new Chip_Woocommerce_Gateway_Block_Blocks_Support( 'chip_block_abcd1234', 'chip_woocommerce_gateway_4' );

		$this->assertSame( 'chip_block_abcd1234', $support->get_name() );

		$property = new ReflectionProperty( $support, 'script_name' );
		$property->setAccessible( true );
		$this->assertSame( 'chip_woocommerce_gateway_4', $property->getValue( $support ) );
	}

	/**
	 * The family bundle is shared, so each block's script tag must be told which
	 * gateway name to register — otherwise a created block registers under the
	 * standard gateway's name and never appears on the Blocks checkout.
	 *
	 * @return void
	 */
	public function test_blocks_support_injects_its_own_gateway_name() {
		require_once dirname( __DIR__ ) . '/includes/blocks/class-chip-woocommerce-gateway-blocks-support.php';
		require_once dirname( __DIR__ ) . '/includes/blocks/class-chip-woocommerce-gateway-block-blocks-support.php';

		$GLOBALS['__chip_test_inline_scripts'] = array();

		$id      = $this->add_two_wallet_block();
		$name    = Chip_Woocommerce_Payment_Blocks::gateway_id( $id );
		$support = new Chip_Woocommerce_Gateway_Block_Blocks_Support( $name, 'chip_woocommerce_gateway_4' );

		// get_payment_method_script_handles() needs a live gateway instance.
		$gateway = new Chip_Woocommerce_Gateway_Block(
			array(
				'id'     => $id,
				'family' => 'ewallet',
			)
		);
		// initialize() resolves the gateway through WooCommerce's registry, which
		// the test suite does not build; inject the instance directly.
		$gateway_property = new ReflectionProperty( $support, 'gateway' );
		$gateway_property->setAccessible( true );
		$gateway_property->setValue( $support, $gateway );

		$support->get_payment_method_script_handles();

		$inline = $GLOBALS['__chip_test_inline_scripts'][ 'wc-' . $name . '-blocks' ]['before'][0] ?? '';
		$this->assertStringContainsString( 'window.chipBlocksPaymentMethodName', $inline );
		$this->assertStringContainsString( $name, $inline );
	}

	/**
	 * Registering gateways is unchanged for a site with no created blocks, and
	 * grows by exactly one instance when a block exists.
	 *
	 * @return void
	 */
	public function test_gateway_registration_is_additive() {
		$chip     = Chip_Woocommerce::get_instance();
		$existing = $chip->add_gateways( array() );

		$this->assertCount( 6, $existing );
		foreach ( $existing as $gateway ) {
			$this->assertIsString( $gateway );
		}

		$id       = $this->add_two_wallet_block();
		$extended = $chip->add_gateways( array() );

		$this->assertCount( 7, $extended );
		$this->assertSame( $existing, array_slice( $extended, 0, 6 ) );
		$this->assertInstanceOf( 'Chip_Woocommerce_Gateway_Block', $extended[6] );
		$this->assertSame( 'chip_block_' . $id, $extended[6]->id );
	}
}
