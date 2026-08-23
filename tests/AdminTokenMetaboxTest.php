<?php
/**
 * Tests for the admin saved-card metabox (Chip_Woocommerce_Admin_Token).
 *
 * @package CHIP_For_WooCommerce
 */

require_once dirname( __DIR__ ) . '/includes/class-chip-woocommerce-admin-token.php';

/**
 * A minimal token stub for the metabox tests.
 */
class Chip_Test_Token {
	/**
	 * Token ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Gateway ID.
	 *
	 * @var string
	 */
	private $gateway_id;

	/**
	 * User ID.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Display name.
	 *
	 * @var string
	 */
	private $display_name;

	/**
	 * Constructor.
	 *
	 * @param int    $id           Token ID.
	 * @param string $gateway_id   Gateway ID.
	 * @param int    $user_id      User ID.
	 * @param string $display_name Display name.
	 */
	public function __construct( $id, $gateway_id, $user_id, $display_name ) {
		$this->id           = $id;
		$this->gateway_id   = $gateway_id;
		$this->user_id      = $user_id;
		$this->display_name = $display_name;
	}

	/**
	 * Get token ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get gateway ID.
	 *
	 * @return string
	 */
	public function get_gateway_id() {
		return $this->gateway_id;
	}

	/**
	 * Get user ID.
	 *
	 * @return int
	 */
	public function get_user_id() {
		return $this->user_id;
	}

	/**
	 * Get display name.
	 *
	 * @return string
	 */
	public function get_display_name() {
		return $this->display_name;
	}
}

/**
 * A minimal subscription stub for the metabox tests.
 */
class Chip_Test_Subscription {
	/**
	 * Customer ID.
	 *
	 * @var int
	 */
	private $customer_id;

	/**
	 * Payment token IDs.
	 *
	 * @var array
	 */
	private $payment_tokens = array();

	/**
	 * Added tokens (recorded for assertions).
	 *
	 * @var array
	 */
	public $added_tokens = array();

	/**
	 * Order notes (recorded for assertions).
	 *
	 * @var array
	 */
	public $notes = array();

	/**
	 * Constructor.
	 *
	 * @param int   $customer_id    Customer ID.
	 * @param array $payment_tokens Initial payment token IDs.
	 */
	public function __construct( $customer_id, $payment_tokens = array() ) {
		$this->customer_id    = $customer_id;
		$this->payment_tokens = $payment_tokens;
	}

	/**
	 * Get customer ID.
	 *
	 * @return int
	 */
	public function get_customer_id() {
		return $this->customer_id;
	}

	/**
	 * Get payment token IDs.
	 *
	 * @return array
	 */
	public function get_payment_tokens() {
		return $this->payment_tokens;
	}

	/**
	 * Add a payment token.
	 *
	 * @param object $token Token.
	 * @return void
	 */
	public function add_payment_token( $token ) {
		$this->added_tokens[] = $token;
		$this->payment_tokens = array( $token->get_id() );
	}

	/**
	 * Add an order note.
	 *
	 * @param string $note Note text.
	 * @return void
	 */
	public function add_order_note( $note ) {
		$this->notes[] = $note;
	}
}

/**
 * Admin token metabox test case.
 */
class AdminTokenMetaboxTest extends PHPUnit\Framework\TestCase {

	/**
	 * Reset shared stub state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['__chip_test_meta_boxes']        = array();
		$GLOBALS['__chip_test_screen_id']         = null;
		$GLOBALS['__chip_test_customer_tokens']   = array();
		$GLOBALS['__chip_test_subscription']      = null;
		$GLOBALS['__chip_test_nonce_valid']       = false;
		$GLOBALS['__chip_test_can_edit_orders']   = false;
		unset( $_POST['chip_admin_token_nonce'], $_POST['chip_admin_token_id'] );
	}

	/**
	 * Build a fresh metabox instance.
	 *
	 * @return Chip_Woocommerce_Admin_Token
	 */
	private function newMetabox() {
		return new Chip_Woocommerce_Admin_Token();
	}

	/**
	 * The metabox is registered on the subscription screen (legacy id).
	 */
	public function test_metabox_registered_on_subscription_screen() {
		$GLOBALS['__chip_test_screen_id'] = 'shop_subscription';

		$this->newMetabox()->add_token_metabox();

		$this->assertArrayHasKey( 'chip-subscription-token', $GLOBALS['__chip_test_meta_boxes'] );
		$box = $GLOBALS['__chip_test_meta_boxes']['chip-subscription-token'];
		$this->assertSame( 'shop_subscription', $box['screen'] );
		$this->assertSame( 'side', $box['context'] );
	}

	/**
	 * The metabox is NOT registered on a non-subscription screen.
	 */
	public function test_metabox_not_registered_on_other_screen() {
		$GLOBALS['__chip_test_screen_id'] = 'shop_order';

		$this->newMetabox()->add_token_metabox();

		$this->assertArrayNotHasKey( 'chip-subscription-token', $GLOBALS['__chip_test_meta_boxes'] );
	}

	/**
	 * Saving with a valid nonce + capability switches the token and records
	 * a consent note.
	 */
	public function test_save_switches_token_and_records_consent() {
		$subscription = new Chip_Test_Subscription( 7, array( 1 ) );
		$GLOBALS['__chip_test_subscription'] = $subscription;

		$tokens = array(
			new Chip_Test_Token( 1, 'wc_gateway_chip', 7, 'Visa ending in 1111 (expires 10/26)' ),
			new Chip_Test_Token( 2, 'wc_gateway_chip', 7, 'Visa ending in 2222 (expires 11/26)' ),
		);
		$GLOBALS['__chip_test_customer_tokens'] = $tokens;

		$GLOBALS['__chip_test_nonce_valid']     = true;
		$GLOBALS['__chip_test_can_edit_orders'] = true;
		$_POST['chip_admin_token_nonce']        = 'valid';
		$_POST['chip_admin_token_id']           = '2';

		$this->newMetabox()->save_token_metabox( 155 );

		// Token switched to id 2.
		$this->assertCount( 1, $subscription->added_tokens );
		$this->assertSame( 2, $subscription->added_tokens[0]->get_id() );

		// Consent note recorded.
		$this->assertCount( 1, $subscription->notes );
		$this->assertStringContainsString( 'consent', $subscription->notes[0] );
	}

	/**
	 * Saving with an invalid nonce does nothing.
	 */
	public function test_save_rejected_without_valid_nonce() {
		$subscription = new Chip_Test_Subscription( 7, array( 1 ) );
		$GLOBALS['__chip_test_subscription'] = $subscription;

		$GLOBALS['__chip_test_customer_tokens'] = array(
			new Chip_Test_Token( 2, 'wc_gateway_chip', 7, 'Visa ending in 2222' ),
		);

		$GLOBALS['__chip_test_nonce_valid']     = false;
		$GLOBALS['__chip_test_can_edit_orders'] = true;
		$_POST['chip_admin_token_nonce']        = 'invalid';
		$_POST['chip_admin_token_id']           = '2';

		$this->newMetabox()->save_token_metabox( 155 );

		$this->assertCount( 0, $subscription->added_tokens );
		$this->assertCount( 0, $subscription->notes );
	}

	/**
	 * Saving a token that belongs to a different customer is rejected.
	 */
	public function test_save_rejected_for_foreign_token() {
		$subscription = new Chip_Test_Subscription( 7, array( 1 ) );
		$GLOBALS['__chip_test_subscription'] = $subscription;

		// Token belongs to user 99, not 7.
		$GLOBALS['__chip_test_customer_tokens'] = array(
			new Chip_Test_Token( 2, 'wc_gateway_chip', 99, 'Visa ending in 2222' ),
		);

		$GLOBALS['__chip_test_nonce_valid']     = true;
		$GLOBALS['__chip_test_can_edit_orders'] = true;
		$_POST['chip_admin_token_nonce']        = 'valid';
		$_POST['chip_admin_token_id']           = '2';

		$this->newMetabox()->save_token_metabox( 155 );

		$this->assertCount( 0, $subscription->added_tokens );
		$this->assertCount( 0, $subscription->notes );
	}

	/**
	 * Saving a non-CHIP token is rejected.
	 */
	public function test_save_rejected_for_non_chip_token() {
		$subscription = new Chip_Test_Subscription( 7, array( 1 ) );
		$GLOBALS['__chip_test_subscription'] = $subscription;

		$GLOBALS['__chip_test_customer_tokens'] = array(
			new Chip_Test_Token( 2, 'stripe', 7, 'Visa ending in 2222' ),
		);

		$GLOBALS['__chip_test_nonce_valid']     = true;
		$GLOBALS['__chip_test_can_edit_orders'] = true;
		$_POST['chip_admin_token_nonce']        = 'valid';
		$_POST['chip_admin_token_id']           = '2';

		$this->newMetabox()->save_token_metabox( 155 );

		$this->assertCount( 0, $subscription->added_tokens );
		$this->assertCount( 0, $subscription->notes );
	}
}
