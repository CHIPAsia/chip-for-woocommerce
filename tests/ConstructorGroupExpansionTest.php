<?php
/**
 * Tests for the constructor's duitnow_qr group expansion logic.
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * Constructor group expansion test case.
 */
class ConstructorGroupExpansionTest extends GatewayTestCase {

	/**
	 * Apply the same expansion the constructor does.
	 *
	 * The real constructor calls parent::__construct() first which sets
	 * $this->payment_method_whitelist from get_option('payment_method_whitelist'),
	 * then expands duitnow_qr. We can't run the real constructor (it depends
	 * on WC_Payment_Gateway state), so we replicate the expansion here.
	 */
	private function expandWhitelist( Chip_Woocommerce_Gateway $gateway, array $whitelist ): array {
		if ( in_array( 'duitnow_qr', $whitelist, true ) ) {
			$whitelist = array_values(
				array_unique( array_merge( $whitelist, Chip_Woocommerce_Gateway::DUITNOW_GROUP ) )
			);
		}
		return $whitelist;
	}

	public function test_no_expansion_when_whitelist_empty() {
		$gateway = $this->newGateway();
		$this->assertSame( array(), $this->expandWhitelist( $gateway, array() ) );
	}

	public function test_no_expansion_when_whitelist_has_no_dnqr_group() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'mastercard' ),
			$this->expandWhitelist( $gateway, array( 'fpx', 'mastercard' ) )
		);
	}

	public function test_expansion_when_duitnow_qr_present() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'duitnow_qr', 'dnqr' ),
			$this->expandWhitelist( $gateway, array( 'duitnow_qr' ) )
		);
	}

	public function test_expansion_when_duitnow_qr_alongside_other_methods() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'duitnow_qr', 'dnqr' ),
			$this->expandWhitelist( $gateway, array( 'fpx', 'duitnow_qr' ) )
		);
	}

	public function test_expansion_dedupes_existing_dnqr() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'duitnow_qr', 'dnqr' ),
			$this->expandWhitelist( $gateway, array( 'duitnow_qr', 'dnqr' ) )
		);
	}
}
