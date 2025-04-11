<?php

class WC_Gateway_Chip_2 extends WC_Gateway_Chip {
	const PREFERRED_TYPE = 'Corporate Online Banking';

	protected function init_title() {
		$this->title = __( 'Corporate Online Banking (FPX)', 'chip-for-woocommerce' );
	}

	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['payment_method_whitelist']['default'] = array( 'fpx_b2b1' );
		$this->form_fields['description']['default']              = __( 'Pay with Corporate Online Banking (FPX)', 'chip-for-woocommerce' );
	}
}
class WC_Gateway_Chip_3 extends WC_Gateway_Chip {
	const PREFERRED_TYPE = 'Card';

	protected function init_title() {
		$this->title = __( 'Visa / Mastercard', 'chip-for-woocommerce' );
	}

	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['payment_method_whitelist']['default'] = array( 'maestro', 'visa', 'mastercard' );
		$this->form_fields['description']['default']              = __( 'Pay with Visa / Mastercard', 'chip-for-woocommerce' );
	}
}
class WC_Gateway_Chip_4 extends WC_Gateway_Chip {
	const PREFERRED_TYPE = 'E-Wallet';

	protected function init_title() {
		$this->title = __( 'Grabpay, TnG, Shopeepay, MB2QR', 'chip-for-woocommerce' );
	}

	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['payment_method_whitelist']['default'] = array( 'razer_grabpay', 'razer_maybankqr', 'razer_shopeepay', 'razer_tng' );
		$this->form_fields['description']['default']              = __( 'Pay with E-Wallet', 'chip-for-woocommerce' );
	}
}

class WC_Gateway_Chip_5 extends WC_Gateway_Chip {
	const PREFERRED_TYPE = 'Atome';

	public function __construct() {
		parent::__construct();
		$this->payment_met = array( 'razer_atome' );
	}

	protected function init_title() {
		$this->title = __( 'Buy Now Pay Later', 'chip-for-woocommerce' );
	}

	protected function init_icon() {
		$this->icon = apply_filters( 'wc_' . $this->id . '_load_icon', plugins_url( 'assets/atome.svg', WC_CHIP_FILE ) );
	}

	public function get_payment_method_list() {
		return array( 'razer_atome' => 'Razer Atome' );
	}

	protected function init_one_time_gateway() {
		$this->supports = array( 'products', 'refunds' );
	}

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

class WC_Gateway_Chip_6 extends WC_Gateway_Chip {
	const PREFERRED_TYPE = 'Duitnow QR';

	protected function init_title() {
		$this->title = __( 'Duitnow QR', 'chip-for-woocommerce' );
	}

	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['payment_method_whitelist']['default'] = array( 'duitnow_qr' );
		$this->form_fields['description']['default']              = __( 'Pay with Duitnow QR', 'chip-for-woocommerce' );
	}
}

add_filter( 'woocommerce_payment_gateways', 'chip_clone_wc_gateways' );

function chip_clone_wc_gateways( $methods ) {
	$methods[] = WC_Gateway_Chip_2::class;
	$methods[] = WC_Gateway_Chip_3::class;
	$methods[] = WC_Gateway_Chip_4::class;
	$methods[] = WC_Gateway_Chip_5::class;
	$methods[] = WC_Gateway_Chip_6::class;

	return $methods;
}
