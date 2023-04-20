<?php

class WC_Gateway_Chip_2 extends WC_Gateway_Chip {
  const PREFERRED_TYPE = 'Corporate Online Banking';

  protected function init_title() {
    $this->title = __( 'FPX B2B1', 'chip-for-woocommerce' );
  }
}
class WC_Gateway_Chip_3 extends WC_Gateway_Chip {
  const PREFERRED_TYPE = 'Card';

  protected function init_title() {
    $this->title = __( 'Visa / Mastercard', 'chip-for-woocommerce' );
  }
}
class WC_Gateway_Chip_4 extends WC_Gateway_Chip {
  const PREFERRED_TYPE = 'E-Wallet';

  protected function init_title() {
    $this->title = __( 'Grabpay, TnG, Shopeepay, MB2QR', 'chip-for-woocommerce' );
  }
}

add_filter( 'woocommerce_payment_gateways', 'chip_clone_wc_gateways' );

function chip_clone_wc_gateways( $methods ) {
  $methods[] = WC_Gateway_Chip_2::class;
  $methods[] = WC_Gateway_Chip_3::class;
  $methods[] = WC_Gateway_Chip_4::class;

  return $methods;
}