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
class WC_Gateway_Chip_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Gateway instance.
	 *
	 * @var WC_Gateway_Chip
	 */
	protected $gateway;

	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'wc_gateway_chip';

	/**
	 * Initialize the payment method.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->gateway  = Chip_Woocommerce::get_chip_gateway_class( $this->name );
		$this->settings = $this->gateway->settings;
	}

	/**
	 * Check if payment method is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Get payment method script handles.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
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
	 * @return array
	 */
	public function get_payment_method_data() {
		$pm_whitelist = $this->get_setting( 'payment_method_whitelist' );
		$bypass_chip  = $this->get_setting( 'bypass_chip' );
		$js_display   = '';

		if ( is_array( $pm_whitelist ) && 1 === count( $pm_whitelist ) && 'fpx' === $pm_whitelist[0] && 'yes' === $bypass_chip ) {
			$js_display = 'fpx';
		} elseif ( is_array( $pm_whitelist ) && 1 === count( $pm_whitelist ) && 'fpx_b2b1' === $pm_whitelist[0] && 'yes' === $bypass_chip ) {
			$js_display = 'fpx_b2b1';
		} elseif ( is_array( $pm_whitelist ) && 'yes' === $bypass_chip ) {
			$razer_ewallet_list = array( 'razer_grabpay', 'razer_maybankqr', 'razer_shopeepay', 'razer_tng' );
			if ( 0 === count( array_diff( $pm_whitelist, $razer_ewallet_list ) ) ) {
				$js_display = 'razer';
			}
		}

		return array(
			'title'        => $this->get_setting( 'title' ),
			'description'  => $this->get_setting( 'description' ),
			'supports'     => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
			'method_name'  => $this->name,
			'saved_option' => $this->gateway->supports( 'tokenization' ),
			'save_option'  => false,
			'js_display'   => $js_display,
			'icon'         => $this->gateway->icon,
		);
	}
}

