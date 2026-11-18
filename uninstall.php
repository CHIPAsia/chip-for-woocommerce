<?php
/**
 * CHIP for WooCommerce Uninstall
 *
 * Removes all plugin options when the plugin is uninstalled.
 *
 * @package CHIP for WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Legacy options.
delete_option( 'chip_woocommerce_payment_method' );
delete_option( 'woocommerce_chip-fpxb2b1_settings' );
delete_option( 'woocommerce_chip-card_settings' );
delete_option( 'wc_chip_migrations' );

// Merchant-created payment blocks: registry plus one settings row per block.
$chip_blocks = get_option( 'chip_woocommerce_payment_blocks', array() );
if ( is_array( $chip_blocks ) ) {
	foreach ( $chip_blocks as $chip_block ) {
		if ( is_array( $chip_block ) && ! empty( $chip_block['id'] ) ) {
			delete_option( 'woocommerce_chip_block_' . $chip_block['id'] . '_settings' );
		}
	}
}
delete_option( 'chip_woocommerce_payment_blocks' );

// Gateway settings.
delete_option( 'woocommerce_wc_gateway_chip_settings' );
delete_option( 'woocommerce_wc_gateway_chip_2_settings' );
delete_option( 'woocommerce_wc_gateway_chip_3_settings' );
delete_option( 'woocommerce_wc_gateway_chip_4_settings' );
delete_option( 'woocommerce_wc_gateway_chip_5_settings' );
delete_option( 'woocommerce_wc_gateway_chip_6_settings' );
