<?php
/**
 * CHIP Duitnow QR Payment Gateway for WooCommerce
 *
 * @package Chip for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * CHIP Duitnow QR Payment Gateway for WooCommerce
 *
 * @package Chip for WooCommerce
 */
class WC_Gateway_Chip_Duitnow_Qr extends WC_Gateway_Chip {
	const GATEWAY_ID     = 'wc_gateway_chip_6';
	const PREFERRED_TYPE = 'Duitnow QR';

	/**
	 * Set default title.
	 */
	protected function init_title() {
		$this->title = __( 'Duitnow QR', 'chip-for-woocommerce' );
	}

	/**
	 * Set default payment method whitelist.
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['payment_method_whitelist']['default'] = array( 'duitnow_qr' );
		$this->form_fields['description']['default']              = __( 'Pay with Duitnow QR', 'chip-for-woocommerce' );

		// Set the default icon to ewallet payment method.
		$this->form_fields['display_logo']['default'] = 'duitnow_only';
	}
}
