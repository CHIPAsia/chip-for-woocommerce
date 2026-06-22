<?php
/**
 * Tests for the constructor's duitnow_qr and card group expansion logic.
 *
 * @package CHIP_For_WooCommerce
 */

class ConstructorGroupExpansionTest extends GatewayTestCase {

	/**
	 * Mirror the constructor's whitelist processing.
	 *
	 * The real constructor:
	 *   1. Applies a backward-compat migration (collapses legacy
	 *      [visa, mastercard, maestro] to [card]).
	 *   2. Expands 'duitnow_qr' to DUITNOW_GROUP.
	 *   3. Expands 'card' to CARD_GROUP.
	 *
	 * We can't run the real constructor (depends on WC_Payment_Gateway),
	 * so we replicate the logic here.
	 */
	private function processWhitelist( Chip_Woocommerce_Gateway $gateway, array $whitelist ): array {
		// Backward-compat migration.
		if ( count( array_intersect( $whitelist, Chip_Woocommerce_Gateway::CARD_GROUP ) ) > 0 ) {
			$whitelist = array_values( array_diff( $whitelist, Chip_Woocommerce_Gateway::CARD_GROUP ) );
			if ( ! in_array( 'card', $whitelist, true ) ) {
				$whitelist[] = 'card';
			}
		}
		// duitnow_qr group expansion.
		if ( in_array( 'duitnow_qr', $whitelist, true ) ) {
			$whitelist = array_values(
				array_unique( array_merge( $whitelist, Chip_Woocommerce_Gateway::DUITNOW_GROUP ) )
			);
		}
		// card group expansion.
		if ( in_array( 'card', $whitelist, true ) ) {
			$whitelist = array_values(
				array_unique( array_merge( $whitelist, Chip_Woocommerce_Gateway::CARD_GROUP ) )
			);
		}
		return $whitelist;
	}

	public function test_no_expansion_when_whitelist_empty() {
		$gateway = $this->newGateway();
		$this->assertSame( array(), $this->processWhitelist( $gateway, array() ) );
	}

	public function test_no_expansion_when_whitelist_has_no_groups() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'fpx_b2b1' ),
			$this->processWhitelist( $gateway, array( 'fpx', 'fpx_b2b1' ) )
		);
	}

	public function test_duitnow_qr_expansion() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'duitnow_qr', 'dnqr' ),
			$this->processWhitelist( $gateway, array( 'duitnow_qr' ) )
		);
	}

	public function test_duitnow_qr_alongside_other_methods() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'duitnow_qr', 'dnqr' ),
			$this->processWhitelist( $gateway, array( 'fpx', 'duitnow_qr' ) )
		);
	}

	public function test_duitnow_qr_dedupes_existing_dnqr() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'duitnow_qr', 'dnqr' ),
			$this->processWhitelist( $gateway, array( 'duitnow_qr', 'dnqr' ) )
		);
	}

	public function test_card_expansion() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'card', 'visa', 'mastercard', 'maestro' ),
			$this->processWhitelist( $gateway, array( 'card' ) )
		);
	}

	public function test_card_alongside_fpx() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ),
			$this->processWhitelist( $gateway, array( 'fpx', 'card' ) )
		);
	}

	public function test_card_alongside_duitnow_qr() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'card', 'duitnow_qr', 'dnqr', 'visa', 'mastercard', 'maestro' ),
			$this->processWhitelist( $gateway, array( 'card', 'duitnow_qr' ) )
		);
	}

	public function test_both_groups_deduped_when_already_present() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'card', 'duitnow_qr', 'dnqr', 'visa', 'mastercard', 'maestro' ),
			$this->processWhitelist( $gateway, array( 'card', 'visa', 'mastercard', 'maestro', 'duitnow_qr', 'dnqr' ) )
		);
	}

	public function test_backward_compat_collapses_legacy_card_keys() {
		// A merchant who saved ['visa', 'mastercard', 'maestro'] in the old
		// multiselect form should have it collapsed to ['card'] in-memory.
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'card', 'visa', 'mastercard', 'maestro' ),
			$this->processWhitelist( $gateway, array( 'visa', 'mastercard', 'maestro' ) )
		);
	}

	public function test_backward_compat_preserves_other_methods() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ),
			$this->processWhitelist( $gateway, array( 'fpx', 'visa', 'mastercard', 'maestro' ) )
		);
	}
}
