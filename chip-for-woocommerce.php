<?php

/**
 * Plugin Name: CHIP for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/chip-for-woocommerce/
 * Description: Cash, Card and Coin Handling Integrated Platform
 * Version: 1.2.0
 * Author: Chip In Sdn Bhd
 * Author URI: https://www.chip-in.asia

 * WC requires at least: 3.3.4
 * WC tested up to: 7.0.0
 *
 * Copyright: © 2022 CHIP
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

// based on
// http://docs.woothemes.com/document/woocommerce-payment-gateway-plugin-base/
// docs http://docs.woothemes.com/document/payment-gateway-api/

define('WC_CHIP_MODULE_VERSION', 'v1.2.0');

require_once dirname(__FILE__) . '/api.php';

add_action('plugins_loaded', 'wc_chip_payment_gateway_init', 100);
function wc_chip_payment_gateway_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once dirname( __FILE__ ) . '/includes/class-wc-chip-default.php';
    require_once dirname( __FILE__ ) . '/includes/class-wc-chip-fpxb2b1.php';
    require_once dirname( __FILE__ ) . '/includes/class-wc-chip-card.php';
    class ChipWCLogger
    {
        public function __construct()
        {
            $this->logger = new WC_Logger();
        }

        public function log($message)
        {
            $this->logger->add('chip', $message);
        }
    }

    // Add the Gateway to WooCommerce
    function wc_chip_add_gateway($methods)
    {
        $methods[] = 'WC_Chip_Gateway';

        $chip_settings = get_option( 'woocommerce_chip_settings', null );
        $chip_payments = get_option( 'chip_woocommerce_payment_method', null );

        $class_name = array(
          'fpx_b2b1' => WC_Chip_Fpxb2b1::class,
          'card'     => WC_Chip_Card::class,
        );

        if ( $chip_settings && $chip_settings['hid'] == 'yes' && $chip_payments ) {
          
          foreach($chip_payments as $cp) {
            if ($cp == 'fpx') {
              continue;
            }
            $methods[] = $class_name[$cp];
          }
        }
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'wc_chip_add_gateway');

    function wc_chip_setting_link($links)
    {
        $new_links = array(
            'settings' => sprintf(
              '<a href="%1$s">%2$s</a>', admin_url('admin.php?page=wc-settings&tab=checkout&section=chip'), esc_html__('Settings', 'chip-for-woocommerce')
            )
        );
        return array_merge($new_links, $links);
    }

    add_filter(
        'plugin_action_links_' . plugin_basename(__FILE__),
        'wc_chip_setting_link'
    );
}
