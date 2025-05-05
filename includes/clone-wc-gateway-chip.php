<?php
/**
 * Clone WooCommerce Chip Payment Gateway.
 *
 * This file is responsible for loading the Chip payment gateway classes
 * and adding them to WooCommerce.
 *
 * @package WC_Gateway_Chip
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Autoload Chip payment gateway classes.
// This function will automatically include the necessary class files.

spl_autoload_register(
	function ( $class_name ) {
		$prefix   = 'WC_Gateway_Chip_';
		$base_dir = plugin_dir_path( WC_CHIP_FILE ) . '/includes/gateways/';

		// Only handle your gateway classes.
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}

		// Convert class to filename (e.g., WC_Gateway_Chip_FPX → class-wc-gateway-chip-fpx.php).
		$file = $base_dir . 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

add_filter( 'woocommerce_payment_gateways', 'chip_clone_wc_gateways' );

/**
 * Add Chip payment gateways to WooCommerce.
 *
 * @param array $methods Existing payment gateways.
 * @return array Updated payment gateways.
 */
function chip_clone_wc_gateways( $methods ) {
	$methods[] = WC_Gateway_Chip_Fpx_B2b1::class; // wc_gateway_chip_2 .
	$methods[] = WC_Gateway_Chip_Card::class; // wc_gateway_chip_3 .
	$methods[] = WC_Gateway_Chip_Ewallet::class; // wc_gateway_chip_4 .
	$methods[] = WC_Gateway_Chip_Atome::class; // wc_gateway_chip_5 .
	$methods[] = WC_Gateway_Chip_Duitnow_Qr::class; // wc_gateway_chip_6 .

	return $methods;
}
