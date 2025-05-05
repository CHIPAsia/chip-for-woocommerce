<?php
/**
 * CHIP FPX Corporate Online Banking Payment Gateway for WooCommerce
 *
 * @package Chip for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * CHIP FPX Corporate Online Banking Payment Gateway for WooCommerce
 *
 * @package Chip for WooCommerce
 */
class WC_Gateway_Chip_Fpx_B2b1 extends WC_Gateway_Chip {
	const GATEWAY_ID     = 'wc_gateway_chip_2';
	const PREFERRED_TYPE = 'Corporate Online Banking';

	/**
	 * Set default title.
	 */
	protected function init_title() {
		$this->title = __( 'Corporate Online Banking (FPX)', 'chip-for-woocommerce' );
	}

	/**
	 * Set default payment method whitelist.
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['payment_method_whitelist']['default'] = array( 'fpx_b2b1' );
		$this->form_fields['description']['default']              = __( 'Pay with Corporate Online Banking (FPX)', 'chip-for-woocommerce' );
	}
}
