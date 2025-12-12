<?php
/**
 * CHIP for WooCommerce Gateway - Card
 *
 * WooCommerce payment gateway class for Visa / Mastercard.
 *
 * @package CHIP for WooCommerce
 */

/**
 * WC_Gateway_Chip_3 class for Card payments.
 */
class WC_Gateway_Chip_3 extends WC_Gateway_Chip {

	/**
	 * Preferred payment type.
	 */
	const PREFERRED_TYPE = 'Card';

	/**
	 * Initialize the gateway title.
	 *
	 * @return void
	 */
	protected function init_title() {
		$this->title = __( 'Visa / Mastercard', 'chip-for-woocommerce' );
	}

	/**
	 * Initialize form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['payment_method_whitelist']['default'] = array( 'maestro', 'visa', 'mastercard', 'mpgs_google_pay', 'mpgs_apple_pay' );
		$this->form_fields['description']['default']              = __( 'Pay with Visa / Mastercard', 'chip-for-woocommerce' );
	}
}
