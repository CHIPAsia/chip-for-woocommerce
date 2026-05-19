<?php
/**
 * Class Test_CHIP_Woocommerce_API
 *
 * Tests for the CHIP WooCommerce API class.
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * API class test case.
 */
class Test_CHIP_Woocommerce_API extends PHPUnit\Framework\TestCase {

	/**
	 * Test that the API class can be instantiated.
	 */
	public function test_api_can_be_instantiated() {
		$logger = $this->createMock( 'Chip_Woocommerce_Logger' );
		$api    = new Chip_Woocommerce_API( 'test_secret', 'test_brand', $logger, 'yes' );

		$this->assertInstanceOf( 'Chip_Woocommerce_API', $api );
		$this->assertEquals( 'test_secret', $api->secret_key );
		$this->assertEquals( 'test_brand', $api->brand_id );
		$this->assertEquals( 'yes', $api->debug );
	}

	/**
	 * Test that API credentials can be updated.
	 */
	public function test_set_key_updates_credentials() {
		$logger = $this->createMock( 'Chip_Woocommerce_Logger' );
		$api    = new Chip_Woocommerce_API( 'old_secret', 'old_brand', $logger, 'no' );

		$api->set_key( 'new_secret', 'new_brand' );

		$this->assertEquals( 'new_secret', $api->secret_key );
		$this->assertEquals( 'new_brand', $api->brand_id );
	}
}
