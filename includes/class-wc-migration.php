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
      'enabled'     => $gateway['enabled'],
      'title'       => $gateway['label'],
      'description' => $gateway['method_desc'],
      'brand_id'    => $gateway['brand-id'],
      'secret_key'  => $gateway['secret-key'],
      'debug'       => $gateway['debug'],
      'public_key'  => $gateway['public-key'],
    );

    $new_options['display_logo'] = 'logo';
    $new_options['disable_recurring_support'] = 'yes';

    $new_options_2 = $new_options;
    $new_options_3 = $new_options;

    if ( $gateway['hid'] == 'yes' ) {
      $new_options['payment_method_whitelist'] = array('fpx');

      /**
       * CHIP Card
       */
      if ( ( $gateway_chip_card = get_option( 'woocommerce_chip-card_settings' ) ) ) {
        if ( !( get_option( 'woocommerce_wc_gateway_chip_2_settings' ) ) ) {
          $new_options_2['display_logo'] = 'card';
          $new_options_2['enabled']      = $gateway_chip_card['enabled'];
          $new_options_2['title']        = $gateway_chip_card['label'];
          $new_options_2['description']  = $gateway_chip_card['method_desc'];

          $new_options_2['payment_method_whitelist'] = array('visa', 'mastercard');

          update_option( 'woocommerce_wc_gateway_chip_2_settings', $new_options_2 );
        }
      }

      /**
       * FPX B2B1
       */
      if ( ( $gateway_chip_b2b1 = get_option( 'woocommerce_chip-fpxb2b1_settings' ) ) ) {
        if ( !( get_option( 'woocommerce_wc_gateway_chip_3_settings' ) ) ) {
          $new_options_3['display_logo'] = 'fpx_b2b1';
          $new_options_3['enabled']      = $gateway_chip_b2b1['enabled'];
          $new_options_3['title']        = $gateway_chip_b2b1['label'];
          $new_options_3['description']  = $gateway_chip_b2b1['method_desc'];

          $new_options_3['payment_method_whitelist'] = array('fpx_b2b1');

          update_option( 'woocommerce_wc_gateway_chip_3_settings', $new_options_3 );
        }
      }
    }

    update_option( 'woocommerce_wc_gateway_chip_settings', $new_options );

    delete_option( 'chip_woocommerce_payment_method' );
    delete_option( 'woocommerce_chip_settings' );
    delete_option( 'woocommerce_chip-fpxb2b1_settings' );
    delete_option( 'woocommerce_chip-card_settings' );

    $this->update_migrations( __FUNCTION__, 'done' );
  }
}

Chip_Woocommerce_Migration::get_instance();