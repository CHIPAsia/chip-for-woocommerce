<?php
/**
 * CHIP for WooCommerce Gateway - Card
 *
 * WooCommerce payment gateway class for Visa / Mastercard.
 *
 * @package CHIP for WooCommerce
 */

/**
 * Chip_Woocommerce_Gateway_3 class for Card payments.
 */
class Chip_Woocommerce_Gateway_3 extends Chip_Woocommerce_Gateway {

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
		$this->form_fields['display_logo']['default']             = 'card_only';
		$this->form_fields['payment_method_whitelist']['default'] = array( 'maestro', 'visa', 'mastercard', 'mpgs_google_pay', 'mpgs_apple_pay' );
		$this->form_fields['description']['default']              = __( 'Pay with Visa / Mastercard', 'chip-for-woocommerce' );
	}
}
