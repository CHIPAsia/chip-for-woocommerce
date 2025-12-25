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

// Clear any scheduled cron events for migration (WP-Cron).
wp_clear_scheduled_hook( 'chip_woocommerce_migrate_order_meta_batch' );
wp_clear_scheduled_hook( 'chip_woocommerce_migrate_subscription_meta_batch' );

// Clear Action Scheduler tasks for migration if WooCommerce is available.
if ( function_exists( 'WC' ) && is_callable( array( WC(), 'queue' ) ) ) {
	WC()->queue()->cancel_all( 'chip_woocommerce_migrate_order_meta_batch', array(), 'chip_migration' );
	WC()->queue()->cancel_all( 'chip_woocommerce_migrate_subscription_meta_batch', array(), 'chip_migration' );
}
