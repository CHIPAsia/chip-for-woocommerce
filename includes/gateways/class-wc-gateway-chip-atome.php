<?php
/**
 * CHIP Atome Payment Gateway for WooCommerce
 *
 * @package Chip for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * CHIP Atome Payment Gateway for WooCommerce
 *
 * @package Chip for WooCommerce
 */
class WC_Gateway_Chip_Atome extends WC_Gateway_Chip {
	const GATEWAY_ID     = 'wc_gateway_chip_5';
	const PREFERRED_TYPE = 'Atome';

	/**
	 * Override payment_method_whitelist value to Razer Atome.
	 * This is a workaround to avoid the need to set the payment method whitelist in the settings.
	 * The payment method whitelist is set to Razer Atome by default.
	 */
	public function __construct() {
		parent::__construct();
		$this->payment_met = array( 'razer_atome' );
	}

	/**
	 * Override title.
	 */
	protected function init_title() {
		$this->title = __( 'Buy Now Pay Later', 'chip-for-woocommerce' );
	}

	/**
	 * Override icon settings.
	 */
	protected function init_icon() {
		$this->icon = apply_filters( 'wc_' . $this->id . '_load_icon', plugins_url( 'assets/atome.svg', WC_CHIP_FILE ) );
	}

	/**
	 * Set payment method whitelist to Razer Atome.
	 */
	public function get_payment_method_list() {
		return array( 'razer_atome' => 'Razer Atome' );
	}

	/**
	 * Limit support to products and refunds.
	 * This to define the supported features of the payment gateway.
	 */
	protected function init_one_time_gateway() {
		$this->supports = array( 'products', 'refunds' );
	}

	/**
	 * Set default payment method whitelist.
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['payment_method_whitelist']['default'] = array( 'razer_atome' );
		$this->form_fields['description']['default']              = __( 'Buy now pay later with Atome. <br>The bill will be split into three easy payments. <br>No hidden fees, 0% interest. <br><br><a href="https://www.atome.my">Terms & Conditions</a>', 'chip-for-woocommerce' );
		unset( $this->form_fields['display_logo'] );
		unset( $this->form_fields['disable_recurring_support'] );
		unset( $this->form_fields['force_tokenization'] );
		unset( $this->form_fields['payment_method_whitelist'] );
		unset( $this->form_fields['bypass_chip'] );
		unset( $this->form_fields['webhooks'] );
		unset( $this->form_fields['webhook_public_key'] );
	}
}
