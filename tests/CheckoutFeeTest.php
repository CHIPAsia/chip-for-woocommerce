<?php
/**
 * Tests for the additional-charges checkout fee (add_checkout_fee).
 *
 * @package CHIP_For_WooCommerce
 */

require_once dirname( __DIR__ ) . '/includes/class-chip-woocommerce.php';

/**
 * A minimal gateway stub exposing get_option().
 */
class Chip_Test_Fee_Gateway {
	/**
	 * Options.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @param array $options Options.
	 */
	public function __construct( $options ) {
		$this->options = $options;
	}

	/**
	 * Get an option.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_option( $key, $default = null ) {
		return isset( $this->options[ $key ] ) ? $this->options[ $key ] : $default;
	}
}

/**
 * Tests for add_checkout_fee().
 */
class CheckoutFeeTest extends \PHPUnit\Framework\TestCase {

	/**
	 * Reset globals before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		unset( $GLOBALS['__chip_test_chosen_payment_method'] );
		unset( $GLOBALS['__chip_test_payment_gateways'] );
	}

	/**
	 * No fee is added when no CHIP gateway is chosen.
	 *
	 * @return void
	 */
	public function test_no_fee_when_non_chip_gateway_chosen() {
		$GLOBALS['__chip_test_chosen_payment_method'] = 'bacs';
		$cart = new Chip_Test_Cart();
		$cart->total = 45.00;

		Chip_Woocommerce::get_instance()->add_checkout_fee( $cart );

		$this->assertCount( 0, $cart->fees );
	}

	/**
	 * No fee is added when the chosen CHIP gateway has charges disabled.
	 *
	 * @return void
	 */
	public function test_no_fee_when_charges_disabled() {
		$GLOBALS['__chip_test_chosen_payment_method'] = 'wc_gateway_chip';
		$GLOBALS['__chip_test_payment_gateways']      = array(
			'wc_gateway_chip' => new Chip_Test_Fee_Gateway(
				array(
					'enable_additional_charges' => 'no',
					'fixed_charges'             => 100,
					'percent_charges'           => 0,
				)
			),
		);
		$cart = new Chip_Test_Cart();
		$cart->total = 45.00;

		Chip_Woocommerce::get_instance()->add_checkout_fee( $cart );

		$this->assertCount( 0, $cart->fees );
	}

	/**
	 * A fixed fee is added when the chosen CHIP gateway has fixed charges.
	 *
	 * @return void
	 */
	public function test_fixed_fee_added() {
		$GLOBALS['__chip_test_chosen_payment_method'] = 'wc_gateway_chip';
		$GLOBALS['__chip_test_payment_gateways']      = array(
			'wc_gateway_chip' => new Chip_Test_Fee_Gateway(
				array(
					'enable_additional_charges' => 'yes',
					'fixed_charges'             => 100,
					'percent_charges'           => 0,
				)
			),
		);
		$cart = new Chip_Test_Cart();
		$cart->total = 45.00;

		Chip_Woocommerce::get_instance()->add_checkout_fee( $cart );

		$this->assertCount( 1, $cart->fees );
		$this->assertSame( 'Fixed Processing Fee', $cart->fees[0]['name'] );
		$this->assertEquals( 1.0, $cart->fees[0]['amount'] );
	}

	/**
	 * A percentage fee is added when the chosen CHIP gateway has percent charges.
	 *
	 * @return void
	 */
	public function test_percent_fee_added() {
		$GLOBALS['__chip_test_chosen_payment_method'] = 'wc_gateway_chip';
		$GLOBALS['__chip_test_payment_gateways']      = array(
			'wc_gateway_chip' => new Chip_Test_Fee_Gateway(
				array(
					'enable_additional_charges' => 'yes',
					'fixed_charges'             => 0,
					'percent_charges'           => 100,
				)
			),
		);
		$cart = new Chip_Test_Cart();
		$cart->cart_contents_total = 45.00;

		Chip_Woocommerce::get_instance()->add_checkout_fee( $cart );

		$this->assertCount( 1, $cart->fees );
		$this->assertSame( 'Variable Processing Fee', $cart->fees[0]['name'] );
		// 45.00 * (100/100) / 100 = 0.45
		$this->assertSame( 0.45, $cart->fees[0]['amount'] );
	}

	/**
	 * Both fixed and percentage fees are added when both are configured.
	 *
	 * @return void
	 */
	public function test_both_fees_added() {
		$GLOBALS['__chip_test_chosen_payment_method'] = 'wc_gateway_chip';
		$GLOBALS['__chip_test_payment_gateways']      = array(
			'wc_gateway_chip' => new Chip_Test_Fee_Gateway(
				array(
					'enable_additional_charges' => 'yes',
					'fixed_charges'             => 100,
					'percent_charges'           => 100,
				)
			),
		);
		$cart = new Chip_Test_Cart();
		$cart->total = 45.00;

		Chip_Woocommerce::get_instance()->add_checkout_fee( $cart );

		$this->assertCount( 2, $cart->fees );
	}

	/**
	 * A created block ("chip_block_*") charges its own fee.
	 *
	 * The checkout fee used to be gated on the "wc_gateway_chip" id prefix, so a
	 * block created from the Payment Methods screen charged nothing at checkout
	 * while its order-side fee was still applied at payment time — the customer
	 * saw one total and the order carried another.
	 *
	 * @return void
	 */
	public function test_fee_applied_for_created_block() {
		$GLOBALS['__chip_test_chosen_payment_method'] = 'chip_block_ab12cd34';
		$GLOBALS['__chip_test_payment_gateways']      = array(
			'chip_block_ab12cd34' => new Chip_Test_Fee_Gateway(
				array(
					'enable_additional_charges' => 'yes',
					'fixed_charges'             => 150,
					'percent_charges'           => 0,
				)
			),
		);
		$cart        = new Chip_Test_Cart();
		$cart->total = 45.00;

		Chip_Woocommerce::get_instance()->add_checkout_fee( $cart );

		$this->assertCount( 1, $cart->fees );
		$this->assertSame( 'Fixed Processing Fee', $cart->fees[0]['name'] );
		$this->assertEquals( 1.5, $cart->fees[0]['amount'] );
	}

	/**
	 * Two blocks can charge different fees: the fee follows the chosen block,
	 * not a global setting.
	 *
	 * @return void
	 */
	public function test_each_block_charges_its_own_fee() {
		$GLOBALS['__chip_test_payment_gateways'] = array(
			'chip_block_grab' => new Chip_Test_Fee_Gateway(
				array(
					'enable_additional_charges' => 'yes',
					'fixed_charges'             => 100,
					'percent_charges'           => 0,
				)
			),
			'chip_block_shopee' => new Chip_Test_Fee_Gateway(
				array(
					'enable_additional_charges' => 'yes',
					'fixed_charges'             => 250,
					'percent_charges'           => 0,
				)
			),
		);

		$GLOBALS['__chip_test_chosen_payment_method'] = 'chip_block_grab';
		$cart                                          = new Chip_Test_Cart();
		$cart->total                                   = 45.00;
		Chip_Woocommerce::get_instance()->add_checkout_fee( $cart );
		$this->assertEquals( 1.0, $cart->fees[0]['amount'] );

		$GLOBALS['__chip_test_chosen_payment_method'] = 'chip_block_shopee';
		$cart                                          = new Chip_Test_Cart();
		$cart->total                                   = 45.00;
		Chip_Woocommerce::get_instance()->add_checkout_fee( $cart );
		$this->assertEquals( 2.5, $cart->fees[0]['amount'] );
	}
}
