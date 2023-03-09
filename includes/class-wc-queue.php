<?php

class Chip_Woocommerce_Queue {
  private static $_instance;

  public static function get_instance() {
    if ( static::$_instance == null ) {
      static::$_instance = new static();
    }

    return static::$_instance;
  }

  public function __construct() {
    $this->add_actions();
  }

  public function add_actions() {
    add_action( 'wc_chip_check_order_status', array( $this, 'check_order_status' ), 10, 4 );
    add_action( 'wc_chip_delete_payment_token', array( $this, 'delete_payment_token' ), 10, 2 );
  }

  public function check_order_status( $purchase_id, $order_id, $attempt, $gateway_id ) {
    $wc_gateway_chip = Chip_Woocommerce::get_chip_gateway_class( $gateway_id );

    if ( !$wc_gateway_chip ) {
      return;
    }

    $wc_gateway_chip->check_order_status( $purchase_id, $order_id, $attempt );
  }

  public function delete_payment_token( $purchase_id, $gateway_id ) {
    $wc_gateway_chip = Chip_Woocommerce::get_chip_gateway_class( $gateway_id );

    if ( !$wc_gateway_chip ) {
      return;
    }

    $wc_gateway_chip->delete_payment_token( $purchase_id );
  }
}

Chip_Woocommerce_Queue::get_instance();