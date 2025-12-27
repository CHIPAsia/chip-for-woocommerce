<?php
/**
 * CHIP for WooCommerce Gateway - E-Wallet
 *
 * WooCommerce payment gateway class for E-Wallet payments.
 *
 * @package CHIP for WooCommerce
 */

/**
 * Chip_Woocommerce_Gateway_4 class for E-Wallet payments.
 */
class Chip_Woocommerce_Gateway_4 extends Chip_Woocommerce_Gateway {

	/**
	 * Preferred payment type.
	 */
	const PREFERRED_TYPE = 'E-Wallet';

	/**
	 * Initialize gateway ID.
	 *
	 * @return void
	 */
	protected function init_id() {
		$this->id = 'wc_gateway_chip_4';
	}

	/**
	 * Initialize the gateway title.
	 *
	 * @return void
	 */
	protected function init_title() {
		$this->title = __( "GrabPay, Touch 'n Go eWallet, ShopeePay, Maybank QRPay", 'chip-for-woocommerce' );
	}

	/**
	 * Initialize form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['display_logo']['default']             = 'ewallet_only';
		$this->form_fields['payment_method_whitelist']['default'] = array( 'razer_grabpay', 'razer_maybankqr', 'razer_shopeepay', 'razer_tng' );
		$this->form_fields['description']['default']              = __( 'Pay with E-Wallet', 'chip-for-woocommerce' );
	}
}
