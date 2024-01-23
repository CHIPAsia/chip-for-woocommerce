<?php

class WC_Gateway_Chip_2 extends WC_Gateway_Chip {
  const PREFERRED_TYPE = 'Corporate Online Banking';

  protected function init_title() {
    $this->title = __( 'Corporate Online Banking (FPX)', 'chip-for-woocommerce' );
  }

  public function init_form_fields() {
    parent::init_form_fields();
    $this->form_fields['payment_method_whitelist']['default'] = ['fpx_b2b1'];
    $this->form_fields['description']['default'] = __( 'Pay with Corporate Online Banking (FPX)', 'chip-for-woocommerce' );
  }
}
class WC_Gateway_Chip_3 extends WC_Gateway_Chip {
  const PREFERRED_TYPE = 'Card';

  protected function init_title() {
    $this->title = __( 'Visa / Mastercard', 'chip-for-woocommerce' );
  }

  public function init_form_fields() {
    parent::init_form_fields();
    $this->form_fields['payment_method_whitelist']['default'] = ['maestro', 'visa', 'mastercard'];
    $this->form_fields['description']['default'] = __( 'Pay with Visa / Mastercard', 'chip-for-woocommerce' );
  }
}
class WC_Gateway_Chip_4 extends WC_Gateway_Chip {
  const PREFERRED_TYPE = 'E-Wallet';

  protected function init_title() {
    $this->title = __( 'Grabpay, TnG, Shopeepay, MB2QR', 'chip-for-woocommerce' );
  }

  public function init_form_fields() {
    parent::init_form_fields();
    $this->form_fields['payment_method_whitelist']['default'] = ['razer', 'razer_atome', 'razer_grabpay','razer_maybankqr','razer_shopeepay','razer_tng'];
    $this->form_fields['description']['default'] = __( 'Pay with E-Wallet', 'chip-for-woocommerce' );
  }
}

add_filter( 'woocommerce_payment_gateways', 'chip_clone_wc_gateways' );

function chip_clone_wc_gateways( $methods ) {
  $methods[] = WC_Gateway_Chip_2::class;
  $methods[] = WC_Gateway_Chip_3::class;
  $methods[] = WC_Gateway_Chip_4::class;

  return $methods;
}