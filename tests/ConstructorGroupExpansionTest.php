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
	 *      [visa, mastercard, maestro] to [card] and migrates legacy
	 *      'razer_shopeepay' to 'shopee_pay').
	 *   2. Expands 'duitnow_qr' to DUITNOW_GROUP.
	 *   3. Expands 'shopee_pay' to SHOPEE_GROUP.
	 *   4. Expands 'card' to CARD_GROUP.
	 *
	 * We can't run the real constructor (depends on WC_Payment_Gateway),
	 * so we replicate the logic here.
	 */
	private function processWhitelist( Chip_Woocommerce_Gateway $gateway, array $whitelist ): array {
		// Backward-compat migration.
		$whitelist = $gateway->migrate_legacy_payment_method_whitelist( $whitelist );
		// duitnow_qr group expansion.
		if ( in_array( 'duitnow_qr', $whitelist, true ) ) {
			$whitelist = array_values(
				array_unique( array_merge( $whitelist, Chip_Woocommerce_Gateway::DUITNOW_GROUP ) )
			);
		}
		// shopee_pay group expansion.
		if ( count( array_intersect( $whitelist, Chip_Woocommerce_Gateway::SHOPEE_GROUP ) ) > 0 ) {
			$whitelist = array_values(
				array_unique( array_merge( $whitelist, Chip_Woocommerce_Gateway::SHOPEE_GROUP ) )
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

	public function test_shopee_pay_expansion() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'shopee_pay', 'razer_shopeepay' ),
			$this->processWhitelist( $gateway, array( 'shopee_pay' ) )
		);
	}

	public function test_backward_compat_migrates_legacy_razer_shopeepay_to_shopee_pay() {
		// A merchant who saved the legacy 'razer_shopeepay' key is migrated
		// in-memory to 'shopee_pay', then expanded to the full group. The
		// dashboard now stores 'shopee_pay'.
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'shopee_pay', 'razer_shopeepay' ),
			$this->processWhitelist( $gateway, array( 'razer_shopeepay' ) )
		);
	}

	public function test_backward_compat_migrates_legacy_key_alongside_other_methods() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'shopee_pay', 'razer_shopeepay' ),
			$this->processWhitelist( $gateway, array( 'fpx', 'razer_shopeepay' ) )
		);
	}

	public function test_backward_compat_is_idempotent_when_shopee_pay_present() {
		// When 'shopee_pay' is already in the whitelist, the migration is a
		// no-op and the group expansion still works. SHOPEE_GROUP order
		// ({razer_shopeepay, shopee_pay}) is preserved by the merge.
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'razer_shopeepay', 'shopee_pay' ),
			$this->processWhitelist( $gateway, array( 'razer_shopeepay', 'shopee_pay' ) )
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

	public function test_backward_compat_collapses_single_legacy_card_key() {
		// A merchant who saved just ['visa'] in the old multiselect form
		// (only Visa enabled) should also be collapsed to ['card'] so the
		// Card group is the single source of truth.
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'card', 'visa', 'mastercard', 'maestro' ),
			$this->processWhitelist( $gateway, array( 'visa' ) )
		);
	}

	public function test_backward_compat_collapses_two_legacy_card_keys() {
		// A merchant who saved ['visa', 'mastercard'] (no maestro)
		// should also be collapsed to ['card'] -- the merchant
		// implicitly wants the Card group.
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'card', 'visa', 'mastercard', 'maestro' ),
			$this->processWhitelist( $gateway, array( 'visa', 'mastercard' ) )
		);
	}

	public function test_backward_compat_collapses_visa_and_maestro() {
		// A merchant who saved ['visa', 'maestro'] (skipped mastercard)
		// should also be collapsed to ['card'].
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'card', 'visa', 'mastercard', 'maestro' ),
			$this->processWhitelist( $gateway, array( 'visa', 'maestro' ) )
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
