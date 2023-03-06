<?php

/**
 * Plugin Name: CHIP for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/chip-for-woocommerce/
 * Description: CHIP - Better Payment & Business Solutions
 * Version: 1.3.0
 * Author: Chip In Sdn Bhd
 * Author URI: https://www.chip-in.asia
 *
 * WC requires at least: 3.3
 * WC tested up to: 7.4
 *
 * Copyright: © 2023 CHIP
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Resources:
 * 
 * #1 https://woocommerce.com/document/woocommerce-payment-gateway-plugin-base/
 * #2 https://woocommerce.com/document/payment-gateway-api/
 * #3 https://woocommerce.com/document/subscriptions/develop/payment-gateway-integration/
 * #4 https://github.com/woocommerce/woocommerce/wiki/Payment-Token-API#adding-payment-token-api-support-to-your-gateway
 * #5 https://stackoverflow.com/questions/22843504/how-can-i-get-customer-details-from-an-order-in-woocommerce
 * #6 https://stackoverflow.com/questions/16813220/how-can-i-override-inline-styles-with-external-css
 */

 if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.

class Chip_Woocommerce {

  private static $_instance;

  public static function get_instance() {
    if ( self::$_instance == null ) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  public function __construct() {
    $this->define();
    $this->includes();
    $this->add_filters();
    $this->add_actions();
  }

  public function define() {
    define( 'WC_CHIP_MODULE_VERSION', 'v1.3.0' );
    define( 'WC_CHIP_FILE', __FILE__ );
    define( 'WC_CHIP_BASENAME', plugin_basename( WC_CHIP_FILE ) );
    define( 'WC_CHIP_URL', plugin_dir_url( WC_CHIP_FILE ) );
  }

  public function includes() {
    $includes_dir = plugin_dir_path( WC_CHIP_FILE ) . 'includes/';
    include $includes_dir . 'class-api.php';
    include $includes_dir . 'class-logger.php';
    include $includes_dir . 'class-wc-gateway-chip.php';

    if ( !defined( 'DISABLE_CLONE_WC_GATEWAY_CHIP' ) ){
      include $includes_dir . 'clone-wc-gateway-chip.php';
    }
  }

  public function add_filters() {
    add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
    add_filter( 'plugin_action_links_' . WC_CHIP_BASENAME, array( $this, 'setting_link' ) );
  }

  public function add_actions() {
    add_action( 'wc_chip_check_order_status', array( $this, 'check_order_status' ), 10, 4);
  }

  public function check_order_status( $purchase_id, $order_id, $gateway_class, $attempt ) {

    $gateway_class::get_lock( $order_id );
    $gateway_id = strtolower( $gateway_class );
    $order      = new WC_Order( $order_id );

    if ( $order->is_paid() ) {
      $gateway_class::release_lock( $order_id );
      return;
    }

    $gateway = get_option( "woocommerce_{$gateway_id}_settings" );

    $chip = new Chip_Woocommerce_API( $gateway['secret_key'], '', new Chip_Woocommerce_Logger(), $gateway['debug'] );

    $payment = $chip->get_payment( $purchase_id );

    if ( $payment['status'] == 'paid' ){
      $gateway_class::payment_complete( $order, $payment );
      $gateway_class::release_lock( $order_id );
      return;
    }

    if ( $attempt < 8 ) {
      $gateway_class::schedule_requery( $purchase_id, $order_id, ++$attempt );
    }
  }

  public function add_gateways( $methods ) {

    $methods[] = WC_Gateway_Chip::class;

    return $methods;
  }

  public function setting_link( $links ) {
    $url_params = array( 
      'page'    => 'wc-settings', 
      'tab'     => 'checkout',
    );

    if ( defined( 'DISABLE_CLONE_WC_GATEWAY_CHIP' ) ){
      $url_params['section'] = 'wc_gateway_chip';
    }

    $url = add_query_arg( $url_params, admin_url( 'admin.php' ) );

    $new_links = array(
      'settings' => sprintf( '<a href="%1$s">%2$s</a>', $url, esc_html__( 'Settings', 'chip-for-woocommerce' ) )
    );

    return array_merge( $new_links, $links );
  }

  public static function woocommerce_subscription_exist() {
    return class_exists( 'WC_Subscriptions' );
  }

  public static function load() {
    if ( !class_exists( 'WooCommerce' ) OR !class_exists( 'WC_Payment_Gateway' ) ) {
      return;
    }
  
    self::get_instance();
  }
}

add_action( 'plugins_loaded', array( 'Chip_Woocommerce', 'load' ) );
