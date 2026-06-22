<?php
/**
 * Tests for the DUITNOW_GROUP class constant.
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * DuitNow QR group constant test case.
 */
class DuitNowGroupTest extends GatewayTestCase {

	/**
	 * The constant must contain exactly duitnow_qr and dnqr.
	 */
	public function test_constant_value() {
		$this->assertSame(
			array( 'duitnow_qr', 'dnqr' ),
			Chip_Woocommerce_Gateway::DUITNOW_GROUP
		);
	}

	/**
	 * The constant should be a flat list of string identifiers.
	 */
	public function test_constant_is_an_array_of_two_strings() {
		$this->assertCount( 2, Chip_Woocommerce_Gateway::DUITNOW_GROUP );
		foreach ( Chip_Woocommerce_Gateway::DUITNOW_GROUP as $key ) {
			$this->assertIsString( $key );
		}
	}
}
