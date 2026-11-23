<?php
/**
 * Tests for the Buy Now Pay Later request-client-details behaviour.
 *
 * A BNPL purchase carries the buyer's full name and mobile number, and the
 * BNPL provider requires both. WooCommerce does not
 * require a billing phone, so an order without one used to send an empty
 * mobile number and the provider could never complete the purchase: the payer
 * reached a payment page that never moved on and the purchase expired hours
 * later, with no money moved.
 *
 * Those purchases now send `purchase.request_client_details` so the CHIP
 * payment page collects the missing details from the payer before the payment
 * runs. These tests pin which fields get requested, that the key is absent for
 * a complete order, and that a non-BNPL purchase payload is untouched.
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * Request-client-details tests.
 */
class BnplRequestClientDetailsTest extends GatewayTestCase {

	/**
	 * Reset the posted payment-method fields before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		unset(
			$_POST['chip_payment_method'],
			$_POST['chip_payment_method_wc_gateway_chip'],
			$_POST['chip_payment_method_wc_gateway_chip_5']
		);
	}

	/**
	 * Fill the stub order's billing details and return the order.
	 *
	 * @param string $first_name First name.
	 * @param string $last_name  Last name.
	 * @param string $email      Email address.
	 * @param string $phone      Billing phone.
	 * @return WC_Order
	 */
	private function newOrder( $first_name = 'Aisyah', $last_name = 'Rahman', $email = 'aisyah@example.com', $phone = '+60123456789' ) {
		$GLOBALS['__chip_test_order_billing'] = array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'email'      => $email,
			'phone'      => $phone,
		);

		return new WC_Order( 101 );
	}

	/**
	 * Create the standard BNPL gateway (id wc_gateway_chip_5).
	 *
	 * @param array $options Extra gateway properties.
	 * @return Chip_Woocommerce_Gateway
	 */
	private function newBnplGateway( array $options = array() ) {
		return $this->newGateway(
			array_merge(
				array(
					'id'                       => 'wc_gateway_chip_5',
					'payment_method_whitelist' => array( 'razer_atome' ),
					'disable_redirect'         => 'no',
				),
				$options
			)
		);
	}

	/**
	 * Run process_payment() with a create_payment() stub that records the
	 * purchase payload.
	 *
	 * The stub returns an array without an 'id', so process_payment takes its
	 * create-payment failure branch: the payload is captured and the rest of
	 * the order lifecycle (notes, meta, requery) is not exercised here.
	 *
	 * @param Chip_Woocommerce_Gateway $gateway Gateway.
	 * @return array Captured purchase params.
	 */
	private function capturePurchaseParams( $gateway ) {
		$captured = null;

		$api = $this->createMock( 'Chip_Woocommerce_API' );
		$api->method( 'create_payment' )->willReturnCallback(
			static function ( $params ) use ( &$captured ) {
				$captured = $params;
				return array();
			}
		);
		$this->setGatewayProperty( $gateway, 'cached_api', $api );

		$gateway->process_payment( 101 );

		$this->assertIsArray( $captured, 'process_payment() should reach create_payment().' );

		return $captured;
	}

	/**
	 * A BNPL order that already has every mandatory buyer detail requests
	 * nothing: the list stays empty and the key is not sent.
	 *
	 * @return void
	 */
	public function test_bnpl_with_all_buyer_details_requests_nothing() {
		$gateway = $this->newBnplGateway();

		$this->assertSame( array(), $this->callGatewayMethod( $gateway, 'request_client_details_for_bnpl', array( $this->newOrder() ) ) );

		$params = $this->capturePurchaseParams( $gateway );
		$this->assertArrayNotHasKey( 'request_client_details', $params );
		$this->assertArrayNotHasKey( 'request_client_details', $params['purchase'] );
	}

	/**
	 * An order without a billing phone asks the payment page for the phone —
	 * and the payload really is missing client.phone today, which is why the
	 * purchase could never complete.
	 *
	 * @return void
	 */
	public function test_bnpl_without_a_phone_requests_the_phone() {
		$gateway = $this->newBnplGateway();

		$this->assertSame(
			array( 'phone' ),
			$this->callGatewayMethod( $gateway, 'request_client_details_for_bnpl', array( $this->newOrder( 'Aisyah', 'Rahman', 'aisyah@example.com', '' ) ) )
		);

		$params = $this->capturePurchaseParams( $gateway );

		// A Purchase field: at the top level the API ignores it silently.
		$this->assertArrayNotHasKey( 'request_client_details', $params );
		$this->assertSame( array( 'phone' ), $params['purchase']['request_client_details'] );
		$this->assertArrayNotHasKey( 'phone', $params['client'] );
	}

	/**
	 * A whitespace-only phone counts as missing.
	 *
	 * @return void
	 */
	public function test_whitespace_phone_requests_the_phone() {
		$gateway = $this->newBnplGateway();

		$this->assertSame(
			array( 'phone' ),
			$this->callGatewayMethod( $gateway, 'request_client_details_for_bnpl', array( $this->newOrder( 'Aisyah', 'Rahman', 'aisyah@example.com', '   ' ) ) )
		);
	}

	/**
	 * A missing full name and a missing phone are both requested, in a stable
	 * order.
	 *
	 * @return void
	 */
	public function test_bnpl_without_a_name_and_phone_requests_both() {
		$gateway = $this->newBnplGateway();

		$details = $this->callGatewayMethod( $gateway, 'request_client_details_for_bnpl', array( $this->newOrder( '', '', 'aisyah@example.com', '' ) ) );

		$this->assertSame( array( 'full_name', 'phone' ), $details );
	}

	/**
	 * Full name and phone are the only fields ever requested, even when the
	 * order carries no buyer email at all: a purchase cannot be created
	 * without one, so email is never the missing field here.
	 *
	 * @return void
	 */
	public function test_bnpl_without_any_buyer_details_requests_name_and_phone() {
		$gateway = $this->newBnplGateway();

		$details = $this->callGatewayMethod( $gateway, 'request_client_details_for_bnpl', array( $this->newOrder( '', '', '', '' ) ) );

		$this->assertSame( array( 'full_name', 'phone' ), $details );
	}

	/**
	 * A missing buyer email is not requested; the configured fallback address
	 * is what the purchase sends, and email is mandatory anyway.
	 *
	 * @return void
	 */
	public function test_email_is_never_requested() {
		$gateway = $this->newBnplGateway( array( 'email_fallback' => 'store@example.com' ) );

		$this->assertSame(
			array( 'phone' ),
			$this->callGatewayMethod( $gateway, 'request_client_details_for_bnpl', array( $this->newOrder( 'Aisyah', 'Rahman', '', '' ) ) )
		);
	}

	/**
	 * A non-BNPL gateway is untouched: an order without a phone requests
	 * nothing and the purchase payload keeps its shape.
	 *
	 * @return void
	 */
	public function test_non_bnpl_gateway_requests_nothing() {
		$gateway = $this->newGateway(
			array(
				'id'                       => 'wc_gateway_chip',
				'payment_method_whitelist' => array( 'fpx' ),
				'disable_redirect'         => 'no',
			)
		);

		$this->assertSame(
			array(),
			$this->callGatewayMethod( $gateway, 'request_client_details_for_bnpl', array( $this->newOrder( 'Aisyah', 'Rahman', 'aisyah@example.com', '' ) ) )
		);

		$params = $this->capturePurchaseParams( $gateway );
		$this->assertArrayNotHasKey( 'request_client_details', $params );
		$this->assertArrayNotHasKey( 'request_client_details', $params['purchase'] );
	}

	/**
	 * An Atome-only whitelist on a standard gateway is a BNPL purchase, so it
	 * is covered too.
	 *
	 * @return void
	 */
	public function test_atome_only_whitelist_requests_the_phone() {
		$gateway = $this->newGateway(
			array(
				'id'                       => 'wc_gateway_chip',
				'payment_method_whitelist' => array( 'razer_atome' ),
				'disable_redirect'         => 'no',
			)
		);

		$this->assertSame(
			array( 'phone' ),
			$this->callGatewayMethod( $gateway, 'request_client_details_for_bnpl', array( $this->newOrder( 'Aisyah', 'Rahman', 'aisyah@example.com', '' ) ) )
		);
	}

	/**
	 * A mixed whitelist only requests details when the shopper picked the BNPL
	 * method in the unified dropdown; other selections keep today's payload.
	 *
	 * @return void
	 */
	public function test_mixed_whitelist_requests_only_for_the_atome_selection() {
		$gateway = $this->newGateway(
			array(
				'id'                       => 'wc_gateway_chip',
				'payment_method_whitelist' => array( 'fpx', 'razer_atome' ),
				'disable_redirect'         => 'no',
			)
		);
		$order = $this->newOrder( 'Aisyah', 'Rahman', 'aisyah@example.com', '' );

		$_POST['chip_payment_method'] = 'fpx:MB2U0227';
		$this->assertSame( array(), $this->callGatewayMethod( $gateway, 'request_client_details_for_bnpl', array( $order ) ) );

		$_POST['chip_payment_method'] = 'razer:Atome';
		$this->assertSame( array( 'phone' ), $this->callGatewayMethod( $gateway, 'request_client_details_for_bnpl', array( $order ) ) );
	}

	/**
	 * A merchant-created BNPL block is covered by its family, and a block from
	 * another family is not.
	 *
	 * @return void
	 */
	public function test_created_blocks_follow_their_family() {
		$bnpl_id = Chip_Woocommerce_Payment_Blocks::add_block( 'bnpl', array( 'methods' => array( 'razer_atome' ) ) );
		$bnpl    = new Chip_Woocommerce_Gateway_Block(
			array(
				'id'     => $bnpl_id,
				'family' => 'bnpl',
			)
		);

		$this->assertSame(
			array( 'phone' ),
			$this->callGatewayMethod( $bnpl, 'request_client_details_for_bnpl', array( $this->newOrder( 'Aisyah', 'Rahman', 'aisyah@example.com', '' ) ) )
		);

		$ewallet_id = Chip_Woocommerce_Payment_Blocks::add_block( 'ewallet', array( 'methods' => array( 'razer_grabpay' ) ) );
		$ewallet    = new Chip_Woocommerce_Gateway_Block(
			array(
				'id'     => $ewallet_id,
				'family' => 'ewallet',
			)
		);

		$this->assertSame(
			array(),
			$this->callGatewayMethod( $ewallet, 'request_client_details_for_bnpl', array( $this->newOrder( 'Aisyah', 'Rahman', 'aisyah@example.com', '' ) ) )
		);
	}
}
