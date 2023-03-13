<?php

class WC_Gateway_Chip_2 extends WC_Gateway_Chip {}
class WC_Gateway_Chip_3 extends WC_Gateway_Chip {}
class WC_Gateway_Chip_4 extends WC_Gateway_Chip {}

add_filter( 'woocommerce_payment_gateways', 'chip_clone_wc_gateways' );

function chip_clone_wc_gateways( $methods ) {
  $methods[] = WC_Gateway_Chip_2::class;
  $methods[] = WC_Gateway_Chip_3::class;
  $methods[] = WC_Gateway_Chip_4::class;

  return $methods;
}