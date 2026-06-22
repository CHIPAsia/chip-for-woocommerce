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

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * Stub for is_admin().
	 *
	 * @return bool
	 */
	function is_admin() {
		return false;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Stub for add_filter().
	 *
	 * @param string   $tag      Filter hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority of the filter.
	 * @param int      $accepted_args Number of accepted arguments.
	 * @return true
	 */
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	/**
	 * Minimal stub for WC_Payment_Gateway so gateway class can be loaded.
	 */
	class WC_Payment_Gateway {}
}

if ( ! class_exists( 'WC_Logger' ) ) {
	/**
	 * Minimal stub for WC_Logger so logger class can be loaded.
	 */
	class WC_Logger {
		public function notice( $message, $context = array() ) {}
		public function info( $message, $context = array() ) {}
		public function error( $message, $context = array() ) {}
		public function debug( $message, $context = array() ) {}
	}
}

// In-memory transient storage for tests.
if ( ! isset( $GLOBALS['__chip_test_transients'] ) ) {
	$GLOBALS['__chip_test_transients'] = array();
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return $GLOBALS['__chip_test_transients'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiry = 0 ) {
		$GLOBALS['__chip_test_transients'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['__chip_test_transients'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	function get_woocommerce_currency() {
		return $GLOBALS['__chip_test_currency'] ?? 'MYR';
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $GLOBALS['__chip_test_options'][ $key ] ?? $default;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Stub for the WordPress __() translation function.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = '' ) {
		return $text;
	}
}

if ( ! function_exists( 'has_filter' ) ) {
	/**
	 * Stub for has_filter(). No filters are registered in tests.
	 *
	 * @param string   $tag     Filter hook name.
	 * @param callable $function_to_check Optional callback to check.
	 * @return bool
	 */
	function has_filter( $tag, $function_to_check = false ) {
		return false;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Stub for apply_filters(). Returns the value unchanged.
	 *
	 * @param string $tag    Filter hook name.
	 * @param mixed  $value  Value to filter.
	 * @param mixed  ...$args Additional arguments.
	 * @return mixed
	 */
	function apply_filters( $tag, $value, ...$args ) {
		return $value;
	}
}

// Load Composer autoloader if available.
$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
}

// Load the GatewayTestCase helper so child test classes can resolve the parent.
require_once __DIR__ . '/GatewayTestCase.php';

/**
 * Load the main plugin file so classes are available for testing.
 */
require_once dirname( __DIR__ ) . '/chip-for-woocommerce.php';

// Trigger includes so all plugin classes are loaded.
Chip_Woocommerce::get_instance();
