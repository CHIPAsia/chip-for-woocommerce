<?php
/**
 * Base test case for Chip_Woocommerce_Gateway tests.
 *
 * Provides reflection-based access to protected methods and properties,
 * plus stub-state reset for tests that exercise the gateway directly.
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * Gateway test case base class.
 */
abstract class GatewayTestCase extends PHPUnit\Framework\TestCase {

	/**
	 * Create a gateway instance without invoking WC_Payment_Gateway::__construct().
	 *
	 * @param array $options Properties to set on the gateway.
	 * @return Chip_Woocommerce_Gateway
	 */
	protected function newGateway( array $options = array() ) {
		$reflection = new ReflectionClass( 'Chip_Woocommerce_Gateway' );
		$gateway    = $reflection->newInstanceWithoutConstructor();

		$defaults = array(
			'resolved_dnqr_group'      => array(),
			'payment_method_whitelist' => array(),
			'brand_id'                 => 'test_brand',
		);
		$options  = array_merge( $defaults, $options );

		foreach ( $options as $key => $value ) {
			$this->setGatewayProperty( $gateway, $key, $value );
		}

		return $gateway;
	}

	/**
	 * Set a property on the gateway via reflection.
	 *
	 * @param Chip_Woocommerce_Gateway $gateway Gateway instance.
	 * @param string                   $name    Property name.
	 * @param mixed                    $value   Value to set.
	 * @return void
	 */
	protected function setGatewayProperty( $gateway, $name, $value ) {
		$prop = new ReflectionProperty( 'Chip_Woocommerce_Gateway', $name );
		$prop->setAccessible( true );
		$prop->setValue( $gateway, $value );
	}

	/**
	 * Read a property on the gateway via reflection.
	 *
	 * @param Chip_Woocommerce_Gateway $gateway Gateway instance.
	 * @param string                   $name    Property name.
	 * @return mixed
	 */
	protected function getGatewayProperty( $gateway, $name ) {
		$prop = new ReflectionProperty( 'Chip_Woocommerce_Gateway', $name );
		$prop->setAccessible( true );
		return $prop->getValue( $gateway );
	}

	/**
	 * Call a method on the gateway via reflection.
	 *
	 * @param Chip_Woocommerce_Gateway $gateway Gateway instance.
	 * @param string                   $name    Method name.
	 * @param array                    $args    Method arguments.
	 * @return mixed
	 */
	protected function callGatewayMethod( $gateway, $name, array $args = array() ) {
		$method = new ReflectionMethod( 'Chip_Woocommerce_Gateway', $name );
		$method->setAccessible( true );
		return $method->invokeArgs( $gateway, $args );
	}

	/**
	 * Mock $gateway->api() to return a stub API instance.
	 *
	 * @param Chip_Woocommerce_Gateway $gateway Gateway instance.
	 * @param array                    $stubs   Map of method => return value.
	 * @return PHPUnit\Framework\MockObject\MockObject
	 */
	protected function mockApi( $gateway, array $stubs = array() ) {
		$api = $this->createMock( 'Chip_Woocommerce_API' );
		foreach ( $stubs as $method => $return ) {
			$api->method( $method )->willReturn( $return );
		}
		// The gateway caches the API instance in $this->cached_api.
		$this->setGatewayProperty( $gateway, 'cached_api', $api );
		return $api;
	}

	/**
	 * Reset shared stub state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['__chip_test_transients']                = array();
		$GLOBALS['__chip_test_options']                   = array();
		$GLOBALS['__chip_test_currency']                  = 'MYR';
		$GLOBALS['__chip_test_is_checkout']               = true;
		$GLOBALS['__chip_test_is_add_payment_method_page'] = false;
		$GLOBALS['__chip_test_is_order_pay']              = false;
		$GLOBALS['__chip_test_form_fields']               = array();
		unset( $_POST['chip_payment_method'], $_POST['wc-wc_gateway_chip-payment-token'], $_POST['token'] );
	}
}
