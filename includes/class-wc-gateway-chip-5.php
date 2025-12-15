<?php
/**
 * CHIP for WooCommerce Gateway - Atome
 *
 * WooCommerce payment gateway class for Atome (Buy Now Pay Later).
 *
 * @package CHIP for WooCommerce
 */

/**
 * Chip_Woocommerce_Gateway_5 class for Atome payments.
 */
class Chip_Woocommerce_Gateway_5 extends Chip_Woocommerce_Gateway {

	/**
	 * Preferred payment type.
	 */
	const PREFERRED_TYPE = 'Atome';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->payment_met = array( 'razer_atome' );
	}

	/**
	 * Initialize the gateway title.
	 *
	 * @return void
	 */
	protected function init_title() {
		$this->title = __( 'Buy Now Pay Later', 'chip-for-woocommerce' );
	}

	/**
	 * Initialize the gateway icon.
	 *
	 * @return void
	 */
	protected function init_icon() {
		$this->icon = plugins_url( 'assets/atome.svg', WC_CHIP_FILE );
		if ( has_filter( 'wc_' . $this->id . '_load_icon' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_load_icon', '1.9.0', 'chip_' . $this->id . '_load_icon' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$this->icon = apply_filters( 'wc_' . $this->id . '_load_icon', $this->icon );
		}
		$this->icon = apply_filters( 'chip_' . $this->id . '_load_icon', $this->icon );
	}

	/**
	 * Get payment method list.
	 *
	 * @return array
	 */
	public function get_payment_method_list() {
		return array( 'razer_atome' => 'Razer Atome' );
	}

	/**
	 * Initialize one-time gateway supports.
	 *
	 * @return void
	 */
	protected function init_one_time_gateway() {
		$this->supports = array( 'products', 'refunds' );
	}

	/**
	 * Initialize form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['payment_method_whitelist']['default'] = array( 'razer_atome' );
		$this->form_fields['description']['default']              = __( 'Buy now pay later with Atome. <br>The bill will be split into three easy payments. <br>No hidden fees, 0% interest. <br><br><a href="https://www.atome.my">Terms & Conditions</a>', 'chip-for-woocommerce' );
		unset( $this->form_fields['display_logo'] );
		unset( $this->form_fields['disable_recurring_support'] );
		unset( $this->form_fields['payment_method_whitelist'] );
		unset( $this->form_fields['bypass_chip'] );
	}
}
