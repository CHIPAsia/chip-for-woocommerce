<?php
/**
 * CHIP Card Payment Gateway for WooCommerce
 *
 * @package Chip for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * CHIP Card Payment Gateway for WooCommerce
 *
 * @package Chip for WooCommerce
 */
class WC_Gateway_Chip_Card extends WC_Gateway_Chip {
	const GATEWAY_ID     = 'wc_gateway_chip_3';
	const PREFERRED_TYPE = 'Card';

	/**
	 * Set default title.
	 */
	protected function init_title() {
		$this->title = __( 'Visa / Mastercard', 'chip-for-woocommerce' );
	}

	/**
	 * Set default payment method whitelist.
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['payment_method_whitelist']['default'] = array( 'maestro', 'visa', 'mastercard' );
		$this->form_fields['description']['default']              = __( 'Pay with Visa / Mastercard', 'chip-for-woocommerce' );

		// Set the default icon to card payment method.
		$this->form_fields['display_logo']['default'] = 'card_only';
	}
}
