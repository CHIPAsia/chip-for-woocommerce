<?php
/**
 * CHIP for WooCommerce Gateway - Corporate Online Banking
 *
 * WooCommerce payment gateway class for Corporate Online Banking (FPX B2B1).
 *
 * @package CHIP for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Chip_Woocommerce_Gateway_2 class for Corporate Online Banking.
 */
class Chip_Woocommerce_Gateway_2 extends Chip_Woocommerce_Gateway {

	/**
	 * Preferred payment type.
	 */
	const PREFERRED_TYPE = 'Corporate Online Banking';

	/**
	 * Initialize gateway ID.
	 *
	 * @return void
	 */
	protected function init_id() {
		$this->id = 'wc_gateway_chip_2';
	}

	/**
	 * Initialize the gateway title.
	 *
	 * @return void
	 */
	protected function init_title() {
		$this->title = __( 'Corporate Online Banking (FPX)', 'chip-for-woocommerce' );
	}

	/**
	 * Initialize form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['display_logo']['default']             = 'fpx_only';
		$this->form_fields['payment_method_whitelist']['default'] = array( 'fpx_b2b1' );
		$this->form_fields['description']['default']              = __( 'Pay with Corporate Online Banking (FPX)', 'chip-for-woocommerce' );
	}
}
