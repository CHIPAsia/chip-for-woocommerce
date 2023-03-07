<?php

class Chip_Woocommerce_Migration {

  const OPTION_NAME = 'wc_chip_migrations';
  private static $_instance;

  private $migrations;

  public static function get_instance() {
    if ( static::$_instance == null ) {
      static::$_instance = new static();
    }

    return static::$_instance;
  }

  public function __construct() {
    $this->migrations = get_option( self::OPTION_NAME, array() );

    $this->v_127_to_130();
  }

  private function update_migrations( $key, $value ) {
    $this->migrations[$key] = $value;
    $this->update();
  }

  private function update() {
    update_option( self::OPTION_NAME, $this->migrations );
  }

  private function v_127_to_130() {
    if ( isset( $this->migrations[__FUNCTION__] ) ) {
      return;
    }

    if ( !( $gateway = get_option( 'woocommerce_chip_settings' ) ) ) {
      $this->update_migrations( __FUNCTION__, 'done' );
      return;
    }

    if ( get_option( 'woocommerce_wc_gateway_chip_settings' ) ) {
      $this->update_migrations( __FUNCTION__, 'done' );
      return;
    }

    $new_options = array(
      'enabled'    => $gateway['enabled'],
      'title'      => $gateway['label'],
      'brand_id'   => $gateway['brand-id'],
      'secret_key' => $gateway['secret-key'],
      'debug'      => $gateway['debug'],
      'public_key' => $gateway['public-key'],
    );

    update_option( 'woocommerce_wc_gateway_chip_settings', $new_options );

    delete_option( 'chip_woocommerce_payment_method' );
    delete_option( 'woocommerce_chip_settings' );

    $this->update_migrations( __FUNCTION__, 'done' );
  }
}

Chip_Woocommerce_Migration::get_instance();