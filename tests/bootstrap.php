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
	class WC_Payment_Gateway {
		/**
		 * Stub for WC_Payment_Gateway::supports().
		 *
		 * @param string $feature Feature name.
		 * @return bool
		 */
		public function supports( $feature ) {
			return in_array( $feature, $this->supports ?? array(), true );
		}
	}
}

// Minimal stub for the WooCommerce Blocks payment method type so the
// blocks-support class can be loaded by the test suite.
if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
	eval( <<<'PHP'
	namespace Automattic\WooCommerce\Blocks\Payments\Integrations {
		abstract class AbstractPaymentMethodType {
			protected $name     = '';
			protected $settings = array();
			protected function get_setting( $name, $default = '' ) {
				return isset( $this->settings[ $name ] ) ? $this->settings[ $name ] : $default;
			}
			public function get_name() { return $this->name; }
			public function is_active() { return true; }
			public function get_payment_method_script_handles() { return array(); }
			public function get_payment_method_script_handles_for_admin() { return $this->get_payment_method_script_handles(); }
			public function get_supported_features() { return array( 'products' ); }
			public function get_payment_method_data() { return array(); }
			public function get_script_handles() { return $this->get_payment_method_script_handles(); }
			public function get_editor_script_handles() { return $this->get_payment_method_script_handles_for_admin(); }
			public function get_script_data() { return $this->get_payment_method_data(); }
		}
	}
PHP
	);
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

// WordPress time constants used by the gateway at test time.
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
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

if ( ! function_exists( 'wc_print_r' ) ) {
	/**
	 * Stub for wc_print_r(). Returns a string representation of the value.
	 *
	 * @param mixed $value Value to print.
	 * @param bool  $return Whether to return the string.
	 * @return string
	 */
	function wc_print_r( $value, $return = false ) {
		return print_r( $value, true );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Stub for sanitize_text_field(). Returns the input unchanged.
	 *
	 * @param string $str String to sanitize.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return is_string( $str ) ? trim( $str ) : '';
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Stub for wp_unslash(). Returns the input unchanged.
	 *
	 * @param mixed $value Value to unslash.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	/**
	 * Stub for wp_remote_request(). Returns a WP_Error so callers see a
	 * failed request without actually hitting the network.
	 *
	 * @param string $url  URL.
	 * @param array  $args Args.
	 * @return WP_Error
	 */
	function wp_remote_request( $url, $args = array() ) {
		return new WP_Error( 'http_request_failed', 'Stub: no network in tests.' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * Stub for wp_remote_retrieve_body(). Returns empty string.
	 *
	 * @param mixed $response Response.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) {
		return '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Stub for wp_remote_retrieve_response_code(). Returns 0.
	 *
	 * @param mixed $response Response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) {
		return 0;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub for tests.
	 */
	class WP_Error {
		public $errors = array();
		public $error_data = array();
		public function __construct( $code = '', $message = '', $data = null ) {
			$this->errors[ $code ][] = $message;
			if ( null !== $data ) {
				$this->error_data[ $code ] = $data;
			}
		}
		public function get_error_message() {
			foreach ( $this->errors as $code => $messages ) {
				return $messages[0];
			}
			return '';
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Stub for is_wp_error(). Returns true for WP_Error instances.
	 *
	 * @param mixed $thing Thing to check.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	/**
	 * Stub for wp_register_script() in tests. Records the registration
	 * but does not enqueue anything.
	 *
	 * @return void
	 */
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $args = false ) {
		if ( ! isset( $GLOBALS['__chip_test_scripts'] ) ) {
			$GLOBALS['__chip_test_scripts'] = array();
		}
		$GLOBALS['__chip_test_scripts'][ $handle ] = array(
			'src'  => $src,
			'deps' => $deps,
			'ver'  => $ver,
		);
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	/**
	 * Stub for wp_localize_script() in tests.
	 *
	 * @return void
	 */
	function wp_localize_script( $handle, $object_name, $data ) {
		if ( ! isset( $GLOBALS['__chip_test_localized'] ) ) {
			$GLOBALS['__chip_test_localized'] = array();
		}
		$GLOBALS['__chip_test_localized'][ $handle ][ $object_name ] = $data;
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	/**
	 * Stub for rest_url() in tests.
	 *
	 * @param string $path Optional REST path.
	 * @return string
	 */
	function rest_url( $path = '' ) {
		return 'http://example.com/wp-json/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * Stub for wp_create_nonce() in tests. Returns a fixed string.
	 *
	 * @return string
	 */
	function wp_create_nonce( $action = '' ) {
		return 'test_nonce';
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	/**
	 * Stub for is_user_logged_in() in tests. Returns false by default.
	 *
	 * @return bool
	 */
	function is_user_logged_in() {
		return false;
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
