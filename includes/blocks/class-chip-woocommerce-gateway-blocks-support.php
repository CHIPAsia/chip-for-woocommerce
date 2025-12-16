<?php
/**
 * CHIP for WooCommerce Blocks Support
 *
 * Adds WooCommerce Blocks support for CHIP payment gateway.
 *
 * @package CHIP for WooCommerce
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * WooCommerce Blocks support class for CHIP gateway.
 */
class Chip_Woocommerce_Gateway_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Gateway instance.
	 *
	 * @var Chip_Woocommerce_Gateway
	 */
	protected $gateway;

	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'chip_woocommerce_gateway';

	/**
	 * Initialize the payment method.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->gateway = Chip_Woocommerce::get_chip_gateway_class( $this->name );
		if ( $this->gateway ) {
			$this->settings = $this->gateway->settings;
		}
	}

	/**
	 * Check if payment method is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->gateway && $this->gateway->is_available();
	}

	/**
	 * Get payment method script handles.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		if ( ! $this->gateway ) {
			return array();
		}

		$script_path       = 'assets/js/frontend/blocks_' . $this->name . '.js';
		$script_asset_path = plugin_dir_path( WC_CHIP_FILE ) . 'assets/js/frontend/blocks_' . $this->name . '.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => WC_CHIP_MODULE_VERSION,
			);
		$script_url        = WC_CHIP_URL . $script_path;

		wp_register_script(
			"wc-{$this->name}-blocks",
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		$localize_variable = array(
			'id'       => $this->name,
			'fpx_b2c'  => array( 'empty' => 'bank' ),
			'fpx_b2b1' => array( 'empty' => 'bank' ),
			'razer'    => array( 'empty' => 'ewallet' ),
		);

		$whitelisted_payment_method = $this->gateway->get_payment_method_whitelist();
		$bypass_chip                = $this->gateway->get_bypass_chip();

		// Exclude razer_atome.
		$razer_ewallet_list = array( 'razer_grabpay', 'razer_maybankqr', 'razer_shopeepay', 'razer_tng' );

		if ( is_array( $whitelisted_payment_method ) && 'yes' === $bypass_chip ) {
			if ( 1 === count( $whitelisted_payment_method ) ) {
				if ( 'fpx' === $whitelisted_payment_method[0] ) {
					$localize_variable['fpx_b2c'] = $this->gateway->list_fpx_banks();
					unset( $localize_variable['fpx_b2c'][''] );
				} elseif ( 'fpx_b2b1' === $whitelisted_payment_method[0] ) {
					$localize_variable['fpx_b2b1'] = $this->gateway->list_fpx_b2b1_banks();
					unset( $localize_variable['fpx_b2b1'][''] );
				} elseif ( count( preg_grep( '/^razer_/', $whitelisted_payment_method ) ) > 0 ) {
					// Checker when whitelist one e-wallet only (razer).
					$localize_variable['razer'] = $this->gateway->list_razer_ewallets();
					unset( $localize_variable['razer'][''] );
				}
			} elseif ( 0 === count( array_diff( $whitelisted_payment_method, $razer_ewallet_list ) ) ) {
				$localize_variable['razer'] = $this->gateway->list_razer_ewallets();
				unset( $localize_variable['razer'][''] );
			}
		}

		wp_localize_script( "wc-{$this->name}-blocks", 'gateway_' . $this->name, $localize_variable );

		return array( "wc-{$this->name}-blocks" );
	}

	/**
	 * Get payment method data.
	 *
	 * Data returned here is passed to the JavaScript client side via getSetting().
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		if ( ! $this->gateway ) {
			return array();
		}

		$pm_whitelist = $this->get_setting( 'payment_method_whitelist' );
		$bypass_chip  = $this->get_setting( 'bypass_chip' );
		$js_display   = '';

		// Card payment methods that support direct post.
		$card_methods       = array( 'visa', 'mastercard', 'maestro' );
		$razer_ewallet_list = array( 'razer_grabpay', 'razer_maybankqr', 'razer_shopeepay', 'razer_tng' );

		if ( is_array( $pm_whitelist ) && 'yes' === $bypass_chip ) {
			if ( 1 === count( $pm_whitelist ) && 'fpx' === $pm_whitelist[0] ) {
				$js_display = 'fpx';
			} elseif ( 1 === count( $pm_whitelist ) && 'fpx_b2b1' === $pm_whitelist[0] ) {
				$js_display = 'fpx_b2b1';
			} elseif ( 0 === count( array_diff( $pm_whitelist, $razer_ewallet_list ) ) ) {
				// All whitelisted methods are razer e-wallets.
				$js_display = 'razer';
			} elseif ( count( $pm_whitelist ) >= 2 && count( array_intersect( $pm_whitelist, $card_methods ) ) > 0 ) {
				// Multiple payment methods with at least one card method - show card form.
				$js_display = 'card';
			}
		}

		$payment_method_data = array(
			'title'                => $this->get_setting( 'title' ),
			'description'          => $this->get_setting( 'description' ),
			'supports'             => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
			'method_name'          => $this->name,
			'saved_option'         => $this->gateway->supports( 'tokenization' ),
			'save_option'          => false,
			'js_display'           => $js_display,
			'icon'                 => $this->gateway->icon,
			'supported_currencies' => array( 'MYR' ),
		);

		/**
		 * Filter the payment method data passed to WooCommerce Blocks.
		 *
		 * This filter allows plugins to modify the payment method data
		 * that is passed to the JavaScript client side via getSetting().
		 *
		 * @since 1.9.0
		 *
		 * @param array                      $payment_method_data The payment method data array.
		 * @param string                     $name                The payment method name/ID.
		 * @param Chip_Woocommerce_Gateway   $gateway             The gateway instance.
		 */
		return apply_filters( 'chip_blocks_payment_method_data', $payment_method_data, $this->name, $this->gateway );
	}
}
