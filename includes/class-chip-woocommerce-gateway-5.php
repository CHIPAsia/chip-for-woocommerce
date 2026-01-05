<?php
/**
 * CHIP for WooCommerce Gateway - Atome
 *
 * WooCommerce payment gateway class for Atome (Buy Now Pay Later).
 *
 * @package CHIP for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Chip_Woocommerce_Gateway_5 class for Atome payments.
 */
class Chip_Woocommerce_Gateway_5 extends Chip_Woocommerce_Gateway {

	/**
	 * Preferred payment type.
	 */
	const PREFERRED_TYPE = 'Atome';

	/**
	 * Price divider product detail setting.
	 *
	 * @var string
	 */
	protected $price_divider_product_detail;

	/**
	 * Price divider product list setting.
	 *
	 * @var string
	 */
	protected $price_divider_product_list;

	/**
	 * Initialize gateway ID.
	 *
	 * @return void
	 */
	protected function init_id() {
		$this->id = 'wc_gateway_chip_5';
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->payment_method_whitelist = array( 'razer_atome' );

		// Load price divider settings.
		$this->price_divider_product_detail = $this->get_option( 'price_divider_product_detail' );
		$this->price_divider_product_list   = $this->get_option( 'price_divider_product_list' );

		// Enqueue price divider script on frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_price_divider_script' ) );
	}

	/**
	 * Initialize the gateway title.
	 *
	 * @return void
	 */
	protected function init_title() {
		$this->title = __( 'Buy Now Pay Later', 'chip-for-woocommerce' );
	}

	/**
	 * Initialize the gateway icon.
	 *
	 * @return void
	 */
	protected function init_icon() {
		$this->icon = plugins_url( 'assets/atome.svg', CHIP_WOOCOMMERCE_FILE );
		if ( has_filter( 'wc_' . $this->id . '_load_icon' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_load_icon', '2.0.0', 'chip_' . $this->id . '_load_icon' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$this->icon = apply_filters( 'wc_' . $this->id . '_load_icon', $this->icon );
		}
		$this->icon = apply_filters( 'chip_' . $this->id . '_load_icon', $this->icon );
	}

	/**
	 * Get payment method list.
	 *
	 * @return array
	 */
	public function get_payment_method_list() {
		return array( 'razer_atome' => 'Atome' );
	}

	/**
	 * Initialize one-time gateway supports.
	 *
	 * @return void
	 */
	protected function init_one_time_gateway() {
		$this->supports = array( 'products', 'refunds' );
	}

	/**
	 * Initialize form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['payment_method_whitelist']['default'] = array( 'razer_atome' );
		$this->form_fields['description']['default']              = __( 'Buy now pay later with Atome. <br>The bill will be split into three easy payments. <br>No hidden fees, 0% interest. <br><br><a href="https://www.atome.my">Terms & Conditions</a>', 'chip-for-woocommerce' );
		unset( $this->form_fields['display_logo'] );
		unset( $this->form_fields['disable_recurring_support'] );
		unset( $this->form_fields['payment_method_whitelist'] );
		unset( $this->form_fields['payment_action'] );
		unset( $this->form_fields['bypass_chip'] );

		// Add Price Divider settings.
		$this->form_fields['price_divider_settings'] = array(
			'title' => __( 'Price Divider Settings', 'chip-for-woocommerce' ),
			'type'  => 'title',
		);

		$this->form_fields['price_divider_product_detail'] = array(
			'title'       => __( 'Price Divider (Product Detail)', 'chip-for-woocommerce' ),
			'type'        => 'checkbox',
			'label'       => __( 'Price Divider (Product Detail)', 'chip-for-woocommerce' ),
			'description' => __( 'Show Atome price divider on product detail pages.', 'chip-for-woocommerce' ),
			'default'     => 'no',
		);

		$this->form_fields['price_divider_product_list'] = array(
			'title'       => __( 'Price Divider (Product List)', 'chip-for-woocommerce' ),
			'type'        => 'checkbox',
			'label'       => __( 'Price Divider (Product List)', 'chip-for-woocommerce' ),
			'description' => __( 'Show Atome price divider on product list pages.', 'chip-for-woocommerce' ),
			'default'     => 'no',
		);
	}

	/**
	 * Enqueue Atome price divider script.
	 *
	 * @return void
	 */
	public function enqueue_price_divider_script() {
		// Check if gateway is enabled.
		if ( 'yes' !== $this->enabled ) {
			return;
		}

		$product_detail = 'yes' === $this->price_divider_product_detail;
		$product_list   = 'yes' === $this->price_divider_product_list;

		// If neither option is checked, don't enqueue anything.
		if ( ! $product_detail && ! $product_list ) {
			return;
		}

		// Determine apply_on value.
		if ( $product_detail && $product_list ) {
			$apply_on = 'all';
		} elseif ( $product_detail ) {
			$apply_on = 'product';
		} else {
			$apply_on = 'list';
		}

		$country     = 'my';
		$language    = 'en';
		$environment = 'production';

		$options = array(
			'is_atome_enabled'         => 'yes',
			'country'                  => $country,
			'language'                 => $language,
			'api_environment'          => $environment,
			'price_divider'            => 'yes',
			'price_divider_applied_on' => $apply_on,
			'sku_permission'           => 'yes',
			'max_spend'                => '',
			'min_spend'                => '',
			'cancel_timeout'           => '720',
			'debug_mode'               => 'no',
			'version'                  => '6.7.0',
			'platform'                 => 'WOOCOMMERCE',
			'enable_send_errors'       => 'no',
			'error_report_url'         => get_site_url() . '/?wc-api=atome_error_report',
		);

		$static_file_domain = 'https://atome-paylater-fe.s3-accelerate.amazonaws.com/merchant-plugins/production/static';

		// Register and enqueue the Atome price divider script.
		wp_register_script(
			'atome-price-divider',
			$static_file_domain . '/price_divider/main.js',
			array(),
			'6.7.0',
			array(
				'strategy'  => 'defer',
				'in_footer' => false,
			)
		);

		// Add inline script with options before the main script.
		wp_add_inline_script(
			'atome-price-divider',
			'window.atomePaymentPluginPriceDividerOptions = ' . wp_json_encode( $options ) . ';',
			'before'
		);

		wp_enqueue_script( 'atome-price-divider' );
	}
}
