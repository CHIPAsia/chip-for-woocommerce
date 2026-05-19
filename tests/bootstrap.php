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
	/**
	 * Stub for plugin_basename().
	 *
	 * @param string $file Plugin file path.
	 * @return string
	 */
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	/**
	 * Stub for plugin_dir_url().
	 *
	 * @param string $file Plugin file path.
	 * @return string
	 */
	function plugin_dir_url( $file ) {
		return 'http://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	/**
	 * Stub for plugin_dir_path().
	 *
	 * @param string $file Plugin file path.
	 * @return string
	 */
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Stub for add_action().
	 *
	 * @param string   $tag      Action hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority of the action.
	 * @param int      $accepted_args Number of accepted arguments.
	 * @return true
	 */
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
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
