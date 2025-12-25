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

// Migration options.
delete_option( 'chip_woocommerce_migration_version' );
delete_option( 'chip_woocommerce_order_meta_migration_pointer' );
delete_option( 'chip_woocommerce_subscription_meta_migration_pointer' );
delete_option( 'chip_woocommerce_migration_completion_notice' );
delete_option( 'chip_woocommerce_order_meta_migration_total' );
delete_option( 'chip_woocommerce_subscription_meta_migration_total' );

// Gateway settings (old IDs).
delete_option( 'woocommerce_wc_gateway_chip_settings' );
delete_option( 'woocommerce_wc_gateway_chip_2_settings' );
delete_option( 'woocommerce_wc_gateway_chip_3_settings' );
delete_option( 'woocommerce_wc_gateway_chip_4_settings' );
delete_option( 'woocommerce_wc_gateway_chip_5_settings' );
delete_option( 'woocommerce_wc_gateway_chip_6_settings' );

// Gateway settings (new IDs).
delete_option( 'woocommerce_chip_woocommerce_gateway_settings' );
delete_option( 'woocommerce_chip_woocommerce_gateway_2_settings' );
delete_option( 'woocommerce_chip_woocommerce_gateway_3_settings' );
delete_option( 'woocommerce_chip_woocommerce_gateway_4_settings' );
delete_option( 'woocommerce_chip_woocommerce_gateway_5_settings' );
delete_option( 'woocommerce_chip_woocommerce_gateway_6_settings' );

