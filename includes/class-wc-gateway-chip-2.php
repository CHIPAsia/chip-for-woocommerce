<?php
/**
 * CHIP for WooCommerce Gateway - Corporate Online Banking
 *
 * WooCommerce payment gateway class for Corporate Online Banking (FPX B2B1).
 *
 * @package CHIP for WooCommerce
 */

/**
 * WC_Gateway_Chip_2 class for Corporate Online Banking.
 */
class WC_Gateway_Chip_2 extends WC_Gateway_Chip {

	/**
	 * Preferred payment type.
	 */
	const PREFERRED_TYPE = 'Corporate Online Banking';

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
		$this->form_fields['payment_method_whitelist']['default'] = array( 'fpx_b2b1' );
		$this->form_fields['description']['default']              = __( 'Pay with Corporate Online Banking (FPX)', 'chip-for-woocommerce' );
	}
}

