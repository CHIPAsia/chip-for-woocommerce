<?php
/**
 * Tests for Chip_Woocommerce_Gateway::migrate_legacy_payment_method_whitelist().
 *
 * Covers the backward-compat migration that collapses legacy card-network
 * keys ('visa', 'mastercard', 'maestro') to the single 'card' group key and
 * renames a lone legacy 'razer_shopeepay' to 'shopee_pay', so the admin
 * multiselect renders the correct selection without requiring a re-save.
 *
 * @package CHIP_For_WooCommerce
 */

class MigrateLegacyWhitelistTest extends GatewayTestCase {

	public function test_unchanged_when_no_legacy_keys() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'card' ),
			$gateway->migrate_legacy_payment_method_whitelist( array( 'fpx', 'card' ) )
		);
	}

	public function test_unchanged_when_empty() {
		$gateway = $this->newGateway();
		$this->assertSame( array(), $gateway->migrate_legacy_payment_method_whitelist( array() ) );
	}

	public function test_collapses_full_legacy_card_group() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'card' ),
			$gateway->migrate_legacy_payment_method_whitelist( array( 'visa', 'mastercard', 'maestro' ) )
		);
	}

	public function test_collapses_single_legacy_card_key() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'card' ),
			$gateway->migrate_legacy_payment_method_whitelist( array( 'visa' ) )
		);
	}

	public function test_collapses_legacy_card_keys_alongside_other_methods() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'card' ),
			$gateway->migrate_legacy_payment_method_whitelist( array( 'fpx', 'visa', 'mastercard', 'maestro' ) )
		);
	}

	public function test_keeps_existing_card_when_collapsing() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'card' ),
			$gateway->migrate_legacy_payment_method_whitelist( array( 'card', 'visa' ) )
		);
	}

	public function test_migrates_legacy_razer_shopeepay_to_shopee_pay() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'shopee_pay' ),
			$gateway->migrate_legacy_payment_method_whitelist( array( 'razer_shopeepay' ) )
		);
	}

	public function test_keeps_razer_shopeepay_when_shopee_pay_present() {
		// When 'shopee_pay' is already saved, the legacy key is not renamed
		// (idempotent). Runtime group expansion handles the rest.
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'razer_shopeepay', 'shopee_pay' ),
			$gateway->migrate_legacy_payment_method_whitelist( array( 'razer_shopeepay', 'shopee_pay' ) )
		);
	}

	public function test_migrates_legacy_keys_alongside_other_methods() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'shopee_pay', 'card' ),
			$gateway->migrate_legacy_payment_method_whitelist( array( 'fpx', 'visa', 'mastercard', 'razer_shopeepay' ) )
		);
	}
}
