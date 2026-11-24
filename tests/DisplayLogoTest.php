<?php
/**
 * Tests for the Display Logo options: the ShopeePay-only logo and the
 * merchant-uploaded custom logo.
 *
 * The point of these tests is the two things a merchant can get wrong:
 *
 *  1. a logo key that has no file behind it renders a broken icon on checkout,
 *     so every offered key — including the new "custom" one before anything is
 *     uploaded — has to resolve to an image that exists;
 *  2. the custom image is a media-library attachment, so it must win over the
 *     bundled map as soon as one is chosen, on the standard gateways and on
 *     created blocks alike — and a value that is not a usable image (an old URL,
 *     a PDF, a deleted attachment) must fall back rather than break checkout.
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * Display Logo option and resolution tests.
 */
class DisplayLogoTest extends GatewayTestCase {

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
	 * Flatten a select's options, which may be grouped into optgroups.
	 *
	 * @param object $gateway Gateway instance.
	 * @return array Option key => label.
	 */
	protected function display_logo_options( $gateway ) {
		$options = isset( $gateway->form_fields['display_logo']['options'] ) ? $gateway->form_fields['display_logo']['options'] : array();
		$flat    = array();

		foreach ( $options as $key => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $child_key => $child_label ) {
					$flat[ $child_key ] = $child_label;
				}
				continue;
			}
			$flat[ $key ] = $value;
		}

		return $flat;
	}

	/**
	 * Build a block gateway backed by a real registry row.
	 *
	 * @param array $settings Settings to store on the block's own row.
	 * @return Chip_Woocommerce_Gateway_Block
	 */
	protected function new_block_gateway( array $settings = array() ) {
		$id = Chip_Woocommerce_Payment_Blocks::add_block(
			'ewallet',
			array(
				'methods' => array( 'shopee_pay' ),
				'title'   => 'ShopeePay',
			)
		);
		$this->assertIsString( $id );

		if ( ! empty( $settings ) ) {
			update_option( 'woocommerce_' . Chip_Woocommerce_Payment_Blocks::gateway_id( $id ) . '_settings', $settings );
		}

		return new Chip_Woocommerce_Gateway_Block(
			array(
				'id'     => $id,
				'family' => 'ewallet',
			)
		);
	}

	/**
	 * The Display Logo select offers a ShopeePay-only logo and a custom option,
	 * with the shared label spelling.
	 *
	 * @return void
	 */
	public function test_display_logo_offers_shopee_pay_and_custom() {
		$gateway = $this->new_block_gateway();
		$options = $this->display_logo_options( $gateway );

		$this->assertArrayHasKey( 'shopee_pay_only', $options );
		$this->assertStringContainsString( 'ShopeePay Only', $options['shopee_pay_only'] );

		$this->assertArrayHasKey( 'custom', $options );
		$this->assertStringContainsString( 'Custom', $options['custom'] );
	}

	/**
	 * Every offered logo key resolves to a bundled file that exists, so no
	 * selection can render a broken image.
	 *
	 * @return void
	 */
	public function test_every_offered_logo_key_has_a_file_behind_it() {
		$gateway = $this->newGateway( array( 'id' => 'wc_gateway_chip' ) );
		$gateway->init_form_fields();

		$options  = $this->display_logo_options( $gateway );
		$base_dir = dirname( __DIR__ ) . '/assets/';

		$this->assertNotEmpty( $options );

		foreach ( array_keys( $options ) as $key ) {
			if ( 'custom' === $key || 'auto' === $key ) {
				continue;
			}

			$png = $base_dir . $key . '.png';
			$svg = $base_dir . $key . '.svg';

			$this->assertTrue(
				file_exists( $png ) || file_exists( $svg ),
				'Display Logo option "' . $key . '" has no asset file.'
			);
		}
	}

	/**
	 * The ShopeePay-only option points at the bundled ShopeePay asset.
	 *
	 * @return void
	 */
	public function test_shopee_pay_only_resolves_to_the_bundled_asset() {
		$gateway = $this->newGateway( array( 'id' => 'wc_gateway_chip' ) );

		$url = $this->callGatewayMethod( $gateway, 'resolve_display_logo_url', array( 'shopee_pay_only' ) );

		$this->assertStringEndsWith( 'assets/shopee_pay_only.png', $url );
		$this->assertFileExists( dirname( __DIR__ ) . '/assets/shopee_pay_only.png' );

		$size = getimagesize( dirname( __DIR__ ) . '/assets/shopee_pay_only.png' );
		$this->assertIsArray( $size );
		$this->assertGreaterThan( 0, $size[0] );
		$this->assertGreaterThan( 0, $size[1] );
	}

	/**
	 * Register a media-library image for the attachment stubs.
	 *
	 * @param int    $id        Attachment id.
	 * @param string $url       Image URL.
	 * @param bool   $is_image  Whether the attachment is an image.
	 * @return void
	 */
	protected function register_attachment( $id, $url, $is_image = true ) {
		$GLOBALS['__chip_test_attachments'][ $id ] = array(
			'url'   => $url,
			'image' => $is_image,
		);
	}

	/**
	 * A standard gateway renders the media-library image once one is chosen.
	 *
	 * @return void
	 */
	public function test_custom_logo_uses_the_uploaded_image() {
		$this->register_attachment( 42, 'https://shop.example.com/wp-content/uploads/2026/11/logo.png' );

		update_option(
			'woocommerce_wc_gateway_chip_settings',
			array(
				'display_logo' => 'custom',
				'custom_logo'  => 42,
			)
		);

		$gateway = $this->newGateway( array( 'id' => 'wc_gateway_chip' ) );
		$this->callGatewayMethod( $gateway, 'init_icon' );

		$this->assertSame( 'https://shop.example.com/wp-content/uploads/2026/11/logo.png', $gateway->icon );
	}

	/**
	 * The logo is restricted to the media library: a stored URL is not an
	 * attachment id, so it resolves to nothing and the CHIP logo is used.
	 *
	 * @return void
	 */
	public function test_custom_logo_ignores_a_stored_url() {
		update_option(
			'woocommerce_wc_gateway_chip_settings',
			array(
				'display_logo' => 'custom',
				'custom_logo'  => 'https://cdn.example.com/logo.png',
			)
		);

		$gateway = $this->newGateway( array( 'id' => 'wc_gateway_chip' ) );
		$this->callGatewayMethod( $gateway, 'init_icon' );

		$this->assertStringEndsWith( 'assets/logo.png', $gateway->icon );
	}

	/**
	 * An attachment that is not an image (a PDF invoice, say) must not be used
	 * as the checkout logo.
	 *
	 * @return void
	 */
	public function test_custom_logo_ignores_a_non_image_attachment() {
		$this->register_attachment( 43, 'https://shop.example.com/wp-content/uploads/2026/11/invoice.pdf', false );

		update_option(
			'woocommerce_wc_gateway_chip_settings',
			array(
				'display_logo' => 'custom',
				'custom_logo'  => 43,
			)
		);

		$gateway = $this->newGateway( array( 'id' => 'wc_gateway_chip' ) );
		$this->callGatewayMethod( $gateway, 'init_icon' );

		$this->assertStringEndsWith( 'assets/logo.png', $gateway->icon );
	}

	/**
	 * Selecting Custom with nothing uploaded must not point at a missing file:
	 * it falls back to the CHIP logo.
	 *
	 * @return void
	 */
	public function test_custom_logo_without_an_upload_falls_back_to_the_chip_logo() {
		update_option(
			'woocommerce_wc_gateway_chip_settings',
			array( 'display_logo' => 'custom' )
		);

		$gateway = $this->newGateway( array( 'id' => 'wc_gateway_chip' ) );
		$this->callGatewayMethod( $gateway, 'init_icon' );

		$this->assertStringEndsWith( 'assets/logo.png', $gateway->icon );
	}

	/**
	 * A created block resolves the custom image and the ShopeePay-only logo the
	 * same way the standard gateways do.
	 *
	 * @return void
	 */
	public function test_created_block_honours_custom_and_shopee_pay_logos() {
		$this->register_attachment( 44, 'https://shop.example.com/wp-content/uploads/2026/11/block-logo.png' );

		$custom = $this->new_block_gateway(
			array(
				'display_logo' => 'custom',
				'custom_logo'  => 44,
			)
		);
		$this->assertSame( 'https://shop.example.com/wp-content/uploads/2026/11/block-logo.png', $custom->icon );

		$shopee = $this->new_block_gateway( array( 'display_logo' => 'shopee_pay_only' ) );
		$this->assertStringEndsWith( 'assets/shopee_pay_only.png', $shopee->icon );
	}

	/**
	 * The field saves an attachment id, not a URL, and its markup carries the
	 * picker instead of a free-text field.
	 *
	 * @return void
	 */
	public function test_custom_logo_field_stores_an_attachment_id() {
		$this->register_attachment( 42, 'https://shop.example.com/wp-content/uploads/2026/11/logo.png' );

		update_option(
			'woocommerce_wc_gateway_chip_settings',
			array( 'custom_logo' => 42 )
		);

		$gateway = $this->newGateway( array( 'id' => 'wc_gateway_chip' ) );
		$gateway->init_form_fields();

		$this->assertArrayHasKey( 'custom_logo', $gateway->form_fields );
		$this->assertSame( 'absint', $gateway->form_fields['custom_logo']['sanitize_callback'] );

		$html = $gateway->generate_chip_custom_logo_html( 'custom_logo', $gateway->form_fields['custom_logo'] );

		// One submitted value, under the key WooCommerce saves, carrying the id.
		$this->assertSame( 1, substr_count( $html, 'name="woocommerce_wc_gateway_chip_custom_logo"' ) );
		$this->assertSame( 1, substr_count( $html, 'chip-custom-logo__value' ) );
		$this->assertStringContainsString( 'value="42"', $html );

		// No free-text URL input: the image can only come from the media library.
		$this->assertStringNotContainsString( 'type="text"', $html );

		// The picker and the current image.
		$this->assertStringContainsString( 'chip-custom-logo__upload', $html );
		$this->assertStringContainsString( 'chip-custom-logo__remove', $html );
		$this->assertStringContainsString( 'src="https://shop.example.com/wp-content/uploads/2026/11/logo.png"', $html );
	}

	/**
	 * An empty custom field renders the empty state instead of a broken image.
	 *
	 * @return void
	 */
	public function test_custom_logo_field_renders_an_empty_state() {
		$gateway = $this->newGateway( array( 'id' => 'wc_gateway_chip' ) );
		$gateway->init_form_fields();

		$html = $gateway->generate_chip_custom_logo_html( 'custom_logo', $gateway->form_fields['custom_logo'] );

		// The empty state hides the <img> (a src="" would render as a broken
		// image) and shows the placeholder instead.
		$this->assertStringContainsString( 'chip-custom-logo__empty', $html );
		$this->assertMatchesRegularExpression( '/chip-custom-logo__img[^>]*hidden/', $html );
		$this->assertStringNotContainsString( 'src="https://', $html );
	}

	/**
	 * The Atome gateway shows a fixed logo, so neither logo field belongs on its
	 * settings screen.
	 *
	 * @return void
	 */
	public function test_atome_gateway_has_no_logo_fields() {
		$gateway = new Chip_Woocommerce_Gateway_5();

		$this->assertArrayNotHasKey( 'display_logo', $gateway->form_fields );
		$this->assertArrayNotHasKey( 'custom_logo', $gateway->form_fields );
	}
}
