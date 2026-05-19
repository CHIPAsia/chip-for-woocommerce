<?php
/**
 * PHPUnit bootstrap file for CHIP for WooCommerce tests.
 *
 * @package CHIP_For_WooCommerce
 */

// Define WordPress constants if not already defined.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// Stub WordPress functions used by the plugin at load time.
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}

// Load Composer autoloader if available.
$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
}

/**
 * Load the main plugin file so classes are available for testing.
 */
require_once dirname( __DIR__ ) . '/chip-for-woocommerce.php';
