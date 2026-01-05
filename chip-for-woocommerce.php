<?php
/**
 * Plugin Name: CHIP for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/chip-for-woocommerce/
 * Description: CHIP - Digital Finance Platform
 * Version: 2.0.0
 * Author: Chip In Sdn Bhd
 * Author URI: https://chip-in.asia
 * Requires PHP: 7.4
 * Requires at least: 6.3
 *
 * WC requires at least: 5.1
 * WC tested up to: 10.4
 * Requires Plugins: woocommerce
 *
 * Copyright: © 2026 CHIP
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package CHIP for WooCommerce
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
 * #7 https://developer.woocommerce.com/2022/07/07/exposing-payment-options-in-the-checkout-block/
 * #9 https://github.com/woocommerce/woocommerce-gateway-dummy/issues/12#issuecomment-1464898655
 * #10 https://developer.woocommerce.com/2022/05/20/hiding-shipping-and-payment-options-in-the-cart-and-checkout-blocks/
 * #11 https://github.com/woocommerce/woocommerce/blob/trunk/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/payment-method-integration.md
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access directly.

// Define plugin constants.
define( 'CHIP_WOOCOMMERCE_MODULE_VERSION', 'v2.0.0' );
define( 'CHIP_WOOCOMMERCE_FILE', __FILE__ );
define( 'CHIP_WOOCOMMERCE_BASENAME', plugin_basename( CHIP_WOOCOMMERCE_FILE ) );
define( 'CHIP_WOOCOMMERCE_URL', plugin_dir_url( CHIP_WOOCOMMERCE_FILE ) );

// Include main class.
require_once plugin_dir_path( CHIP_WOOCOMMERCE_FILE ) . 'includes/class-chip-woocommerce.php';

// Initialize plugin.
add_action( 'plugins_loaded', array( 'Chip_Woocommerce', 'load' ) );

/**
 * Declare plugin compatibility with WooCommerce HPOS.
 *
 * @since 1.3.2
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
