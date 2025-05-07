<?php
/**
 * CHIP E-Wallet Payment Gateway for WooCommerce
 *
 * @package Chip for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * CHIP E-Wallet Payment Gateway for WooCommerce
 *
 * @package Chip for WooCommerce
 */
class WC_Gateway_Chip_Ewallet extends WC_Gateway_Chip {
	const GATEWAY_ID     = 'wc_gateway_chip_4';
	const PREFERRED_TYPE = 'E-Wallet';

	/**
	 * Set default title.
	 */
	protected function init_title() {
		$this->title = __( 'Grabpay, TnG, Shopeepay, MB2QR', 'chip-for-woocommerce' );
	}

	/**
	 * Set default payment method whitelist.
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['payment_method_whitelist']['default'] = array( 'razer_grabpay', 'razer_maybankqr', 'razer_shopeepay', 'razer_tng' );
		$this->form_fields['description']['default']              = __( 'Pay with E-Wallet', 'chip-for-woocommerce' );

		// Set the default icon to ewallet payment method.
		$this->form_fields['display_logo']['default'] = 'ewallet_only';
	}
}
