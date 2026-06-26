<?php
/**
 * CHIP for WooCommerce Gateway - DuitNow QR
 *
 * WooCommerce payment gateway class for DuitNow QR payments.
 *
 * @package CHIP for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Chip_Woocommerce_Gateway_6 class for DuitNow QR payments.
 */
class Chip_Woocommerce_Gateway_6 extends Chip_Woocommerce_Gateway {

	/**
	 * Preferred payment type.
	 */
	const PREFERRED_TYPE = 'DuitNow QR';

	/**
	 * Initialize gateway ID.
	 *
	 * @return void
	 */
	protected function init_id() {
		$this->id = 'wc_gateway_chip_6';
	}

	/**
	 * Initialize the gateway title.
	 *
	 * @return void
	 */
	protected function init_title() {
		$this->title = __( 'DuitNow QR', 'chip-for-woocommerce' );
	}

	/**
	 * Initialize form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['display_logo']['default']             = 'duitnow_only';
		$this->form_fields['payment_method_whitelist']['default'] = array( 'duitnow_qr' );
		$this->form_fields['description']['default']              = __( 'Pay with DuitNow QR', 'chip-for-woocommerce' );
	}
}
