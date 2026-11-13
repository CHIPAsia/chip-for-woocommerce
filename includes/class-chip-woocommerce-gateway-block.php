<?php
/**
 * CHIP for WooCommerce Gateway - merchant-created payment block.
 *
 * One instance of this class backs one row in the payment-block registry. It
 * extends the standard CHIP gateway, so every checkout flow (classic, Blocks,
 * refunds, subscriptions) runs through the same code that already ships; the
 * subclass only supplies the identity of the block and a few behaviours that
 * make sense for a block the merchant configured themselves:
 *
 *  - credentials are inherited from the primary CHIP block unless this block
 *    overrides them, so adding a block never means re-entering a secret key;
 *  - a block holding a single e-wallet submits that wallet directly instead of
 *    showing a one-option dropdown;
 *  - the "Auto" logo is composed from the wallets the block actually accepts,
 *    so a removed wallet stops appearing on the block.
 *
 * @package CHIP for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gateway instance for a merchant-created payment block.
 */
class Chip_Woocommerce_Gateway_Block extends Chip_Woocommerce_Gateway {

	/**
	 * Display code to logo file map, used by the "Auto" logo.
	 */
	const WALLET_LOGOS = array(
		'Atome'           => 'Atome.png',
		'GrabPay'         => 'GrabPay.png',
		'MB2U_QRPay-Push' => 'MB2U_QRPay-Push.png',
		'ShopeePay'       => 'ShopeePay.png',
		'TNG-EWALLET'     => 'TNG-EWALLET.png',
	);

	/**
	 * Block id from the registry.
	 *
	 * @var string
	 */
	protected $block_id = '';

	/**
	 * Payment family of this block.
	 *
	 * @var string
	 */
	protected $block_family = '';

	/**
	 * Constructor.
	 *
	 * @param array $block Registry row: array( 'id' => ..., 'family' => ... ).
	 */
	public function __construct( $block = array() ) {
		$block = is_array( $block ) ? $block : array();

		$this->block_id     = isset( $block['id'] ) ? (string) $block['id'] : '';
		$this->block_family = isset( $block['family'] ) ? (string) $block['family'] : '';

		parent::__construct();

		$this->inherit_credentials();
	}

	/**
	 * Gateway id for this block.
	 *
	 * @return void
	 */
	protected function init_id() {
		$this->id = '' !== $this->block_id
			? Chip_Woocommerce_Payment_Blocks::gateway_id( $this->block_id )
			: 'chip_block_unregistered';
	}

	/**
	 * Family definition of this block, or an empty array.
	 *
	 * @return array
	 */
	protected function family_definition() {
		$family = Chip_Woocommerce_Payment_Blocks::family( $this->block_family );
		return is_array( $family ) ? $family : array();
	}

	/**
	 * Default title is the family title; a saved title wins later.
	 *
	 * @return void
	 */
	protected function init_title() {
		$family      = $this->family_definition();
		$this->title = isset( $family['title'] ) ? $family['title'] : __( 'CHIP payment method', 'chip-for-woocommerce' );
	}

	/**
	 * Initialize the icon.
	 *
	 * The base implementation resolves "assets/<logo>.png|svg". The "Auto"
	 * logo has no file of its own — it is composed in get_icon() — so it is
	 * anchored to the family's standard image here.
	 *
	 * @return void
	 */
	protected function init_icon() {
		$logo = $this->get_option( 'display_logo', 'logo' );
		if ( 'auto' === $logo ) {
			$family = $this->family_definition();
			$logo   = isset( $family['logo'] ) ? $family['logo'] : 'logo';
		}

		$file_extension = 'png';
		$file_path      = plugin_dir_path( CHIP_WOOCOMMERCE_FILE ) . 'assets/' . $logo . '.png';
		if ( ! file_exists( $file_path ) ) {
			$file_extension = 'svg';
		}

		$this->icon = plugins_url( "assets/{$logo}.{$file_extension}", CHIP_WOOCOMMERCE_FILE );
	}

	/**
	 * Gateway icon, composing the wallet logos when "Auto" is selected.
	 *
	 * @return string
	 */
	public function get_icon() {
		if ( 'auto' !== $this->get_option( 'display_logo', 'logo' ) ) {
			return parent::get_icon();
		}

		$urls = $this->auto_icon_urls();
		if ( empty( $urls ) ) {
			return parent::get_icon();
		}

		$html = '';
		foreach ( $urls as $url ) {
			$html .= '<img class="chip-for-woocommerce-' . esc_attr( $this->id ) . '" src="' . esc_url( WC_HTTPS::force_https_url( $url ) ) . '"'
				. ' alt="' . esc_attr( $this->get_title() ) . '" style="max-height: 25px; width: auto; margin-left: 4px;" />';
		}

		return apply_filters( 'chip_gateway_icon', $html, $this->id );
	}

	/**
	 * Logo URLs for the wallets this block accepts.
	 *
	 * @return array
	 */
	protected function auto_icon_urls() {
		$urls = array();

		foreach ( $this->list_razer_ewallets() as $code => $label ) {
			if ( '' === $code || ! isset( self::WALLET_LOGOS[ $code ] ) ) {
				continue;
			}
			$urls[] = CHIP_WOOCOMMERCE_URL . 'assets/razer_ewallet/' . self::WALLET_LOGOS[ $code ];
		}

		return $urls;
	}

	/**
	 * Form fields: inherit everything, then narrow the defaults to this family
	 * and offer the "Auto" logo.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		$family = $this->family_definition();
		if ( empty( $family ) ) {
			return;
		}

		$this->form_fields['payment_method_whitelist']['default'] = $family['methods'];
		$this->form_fields['description']['default']              = $family['description'];
		$this->form_fields['display_logo']['default']             = $family['logo'];

		$options                                      = isset( $this->form_fields['display_logo']['options'] ) ? $this->form_fields['display_logo']['options'] : array();
		$options                                      = array( __( 'Dynamic Logos', 'chip-for-woocommerce' ) => array( 'auto' => __( 'Auto — built from the selected payment methods', 'chip-for-woocommerce' ) ) ) + $options;
		$this->form_fields['display_logo']['options'] = $options;
	}

	/**
	 * Inherit CHIP credentials from the primary block when this block has none.
	 *
	 * @return void
	 */
	protected function inherit_credentials() {
		if ( ! empty( $this->brand_id ) && ! empty( $this->secret_key ) ) {
			return;
		}

		$source = Chip_Woocommerce_Payment_Blocks::credential_source_settings();

		if ( empty( $this->brand_id ) && ! empty( $source['brand_id'] ) ) {
			$this->brand_id = $source['brand_id'];
		}
		if ( empty( $this->secret_key ) && ! empty( $source['secret_key'] ) ) {
			$this->secret_key = $source['secret_key'];
		}
		if ( empty( $this->public_key ) && ! empty( $source['public_key'] ) ) {
			$this->public_key = $source['public_key'];
		}
	}

	/**
	 * Whether this block is backed by a registered family.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( empty( $this->block_family ) || null === Chip_Woocommerce_Payment_Blocks::family( $this->block_family ) ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Display code of the single e-wallet this block offers, if that is the case.
	 *
	 * @return string Display code (e.g. 'ShopeePay'), or '' when the block
	 *                offers a choice (or is not an e-wallet block).
	 */
	protected function solo_razer_code() {
		if ( ! in_array( $this->block_family, array( 'ewallet', 'bnpl' ), true ) ) {
			return '';
		}

		$list = $this->list_razer_ewallets();
		unset( $list[''] );

		if ( 1 !== count( $list ) ) {
			return '';
		}

		$code = (string) key( $list );

		// DuitNow QR is handled by the base gateway's own single-method path.
		if ( 'duitnow-qr' === $code ) {
			return '';
		}

		return $code;
	}

	/**
	 * Field name the checkout posts the chosen method under.
	 *
	 * @return string
	 */
	protected function block_method_field() {
		return 'chip_payment_method_' . $this->id;
	}

	/**
	 * Payment fields on the classic checkout.
	 *
	 * A block that offers exactly one e-wallet submits it directly instead of
	 * rendering a dropdown with a single option.
	 *
	 * @return void
	 */
	public function payment_fields() {
		$solo = $this->solo_razer_code();

		if ( '' === $solo ) {
			parent::payment_fields();
			return;
		}

		$description = $this->get_description();
		if ( ! empty( $description ) ) {
			echo '<p>' . esc_html( wp_strip_all_tags( $description ) ) . '</p>';
		}

		printf(
			'<input type="hidden" name="%1$s" value="%2$s" />',
			esc_attr( $this->block_method_field() ),
			esc_attr( 'razer:' . $solo )
		);
	}
}
