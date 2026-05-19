<?php
/**
 * Class PluginTest
 *
 * Basic smoke tests to verify the plugin loads correctly.
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * Plugin smoke test case.
 */
class PluginTest extends PHPUnit\Framework\TestCase {

	/**
	 * Test that the main plugin constant is defined.
	 */
	public function test_plugin_constant_defined() {
		$this->assertTrue( defined( 'CHIP_WOOCOMMERCE_MODULE_VERSION' ), 'CHIP_WOOCOMMERCE_MODULE_VERSION should be defined.' );
	}

	/**
	 * Test that the API class is available.
	 */
	public function test_api_class_exists() {
		$this->assertTrue( class_exists( 'Chip_Woocommerce_API' ), 'Chip_Woocommerce_API class should be available.' );
	}

	/**
	 * Test that the gateway class is available.
	 */
	public function test_gateway_class_exists() {
		$this->assertTrue( class_exists( 'Chip_Woocommerce_Gateway' ), 'Chip_Woocommerce_Gateway class should be available.' );
	}

	/**
	 * Test that the main loader class is available.
	 */
	public function test_main_class_exists() {
		$this->assertTrue( class_exists( 'Chip_Woocommerce' ), 'Chip_Woocommerce class should be available.' );
	}
}
