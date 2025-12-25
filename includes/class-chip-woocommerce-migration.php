<?php
/**
 * CHIP for WooCommerce Migration
 *
 * Handles migration of settings from old gateway IDs to new gateway IDs.
 *
 * @package CHIP for WooCommerce
 */

/**
 * Migration class.
 */
class Chip_Woocommerce_Migration {

	/**
	 * Migration version option name.
	 */
	const MIGRATION_VERSION_OPTION = 'chip_woocommerce_migration_version';

	/**
	 * Current migration version.
	 */
	const CURRENT_VERSION = '2.0.0';

	/**
	 * Order meta migration pointer option name.
	 */
	const ORDER_META_MIGRATION_POINTER_OPTION = 'chip_woocommerce_order_meta_migration_pointer';

	/**
	 * Subscription meta migration pointer option name.
	 */
	const SUBSCRIPTION_META_MIGRATION_POINTER_OPTION = 'chip_woocommerce_subscription_meta_migration_pointer';

	/**
	 * Order meta migration total option name.
	 */
	const ORDER_META_MIGRATION_TOTAL_OPTION = 'chip_woocommerce_order_meta_migration_total';

	/**
	 * Subscription meta migration total option name.
	 */
	const SUBSCRIPTION_META_MIGRATION_TOTAL_OPTION = 'chip_woocommerce_subscription_meta_migration_total';

	/**
	 * Batch size for large database migrations.
	 */
	const BATCH_SIZE = 10000;

	/**
	 * Flag to track if batched migrations have been initialized in this request.
	 *
	 * @var bool
	 */
	private static $batched_migrations_initialized = false;

	/**
	 * Gateway ID mapping from old to new.
	 *
	 * @var array
	 */
	private static $gateway_id_map = array(
		'wc_gateway_chip'   => 'chip_woocommerce_gateway',
		'wc_gateway_chip_2' => 'chip_woocommerce_gateway_2',
		'wc_gateway_chip_3' => 'chip_woocommerce_gateway_3',
		'wc_gateway_chip_4' => 'chip_woocommerce_gateway_4',
		'wc_gateway_chip_5' => 'chip_woocommerce_gateway_5',
		'wc_gateway_chip_6' => 'chip_woocommerce_gateway_6',
	);

	/**
	 * Run migration if needed.
	 *
	 * @return void
	 */
	public static function maybe_migrate() {
		$current_version = get_option( self::MIGRATION_VERSION_OPTION, '0' );

		if ( version_compare( $current_version, self::CURRENT_VERSION, '<' ) ) {
			// Run simple migrations immediately.
			self::migrate_gateway_settings();
			self::migrate_payment_tokens();
			self::migrate_user_meta();
			self::migrate_legacy_post_meta();

			// Update version after simple migrations complete.
			// Don't wait for batched migrations - they track their own state via pointers.
			update_option( self::MIGRATION_VERSION_OPTION, self::CURRENT_VERSION );
		}

		// Always try to start/continue batched migrations if they haven't completed.
		// Migration progresses one batch per page load.
		add_action( 'admin_init', array( __CLASS__, 'init_batched_migrations' ), 20 );
		add_action( 'wp', array( __CLASS__, 'init_batched_migrations' ), 20 );
	}

	/**
	 * Initialize batched migrations.
	 * Processes one batch per page load.
	 *
	 * @return void
	 */
	public static function init_batched_migrations() {
		// Ensure we only run once per request.
		if ( self::$batched_migrations_initialized ) {
			return;
		}

		// Mark as initialized to prevent multiple runs.
		self::$batched_migrations_initialized = true;

		self::migrate_order_meta();
		self::migrate_subscription_meta();
	}

	/**
	 * Migrate gateway settings from old IDs to new IDs.
	 *
	 * @return void
	 */
	private static function migrate_gateway_settings() {
		foreach ( self::$gateway_id_map as $old_id => $new_id ) {
			$old_option = 'woocommerce_' . $old_id . '_settings';
			$new_option = 'woocommerce_' . $new_id . '_settings';

			$old_settings = get_option( $old_option );
			$new_settings = get_option( $new_option );

			// Only migrate if old settings exist and new settings don't.
			if ( false !== $old_settings && false === $new_settings ) {
				update_option( $new_option, $old_settings );
			}

			// Delete old settings after migration.
			if ( false !== $old_settings ) {
				delete_option( $old_option );
			}
		}
	}

	/**
	 * Migrate payment tokens to use new gateway IDs.
	 *
	 * @return void
	 */
	private static function migrate_payment_tokens() {
		global $wpdb;

		foreach ( self::$gateway_id_map as $old_id => $new_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'woocommerce_payment_tokens',
				array( 'gateway_id' => $new_id ),
				array( 'gateway_id' => $old_id ),
				array( '%s' ),
				array( '%s' )
			);
		}
	}

	/**
	 * Migrate user meta keys from old gateway IDs to new gateway IDs.
	 *
	 * @return void
	 */
	private static function migrate_user_meta() {
		global $wpdb;

		foreach ( self::$gateway_id_map as $old_id => $new_id ) {
			// Migrate client_id meta.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->usermeta} SET meta_key = REPLACE(meta_key, %s, %s) WHERE meta_key LIKE %s",
					'_' . $old_id . '_',
					'_' . $new_id . '_',
					'%\_' . $wpdb->esc_like( $old_id ) . '\_%'
				)
			);
		}
	}

	/**
	 * Migrate legacy post meta to use new gateway IDs.
	 * Simple UPDATE runs once (no batching needed).
	 * The _payment_method meta key is WooCommerce-specific, safe to update all.
	 * Runs regardless of HPOS or sync status.
	 *
	 * @return void
	 */
	private static function migrate_legacy_post_meta() {
		global $wpdb;

		foreach ( self::$gateway_id_map as $old_id => $new_id ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Migration requires direct meta queries.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta}
					SET meta_value = %s
					WHERE meta_key = '_payment_method'
					AND meta_value = %s",
					$new_id,
					$old_id
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}
	}

	/**
	 * Migrate order meta to use new gateway IDs.
	 * Processes one batch per page load.
	 *
	 * @return void
	 */
	private static function migrate_order_meta() {
		// Check if migration is already complete (no pointer and total exists = completed).
		$pointer = get_option( self::ORDER_META_MIGRATION_POINTER_OPTION, false );
		$total   = get_option( self::ORDER_META_MIGRATION_TOTAL_OPTION, false );

		// If total exists but pointer doesn't, migration was already completed.
		if ( false === $pointer && false !== $total ) {
			return;
		}

		// Process one batch.
		self::migrate_order_meta_batched();
	}

	/**
	 * Batched order meta migration.
	 * HPOS: Processes one batch per page load using pointer.
	 *
	 * @return void
	 */
	private static function migrate_order_meta_batched() {
		global $wpdb;

		$hpos_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		// If HPOS is not enabled, we're done (legacy migration handled separately).
		if ( ! $hpos_enabled ) {
			return;
		}

		// HPOS migration uses batched pointer approach.
		$pointer = get_option( self::ORDER_META_MIGRATION_POINTER_OPTION, false );

		// Initialize pointer if not set.
		if ( false === $pointer ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}wc_orders" );

			$pointer = $max_id;
			update_option( self::ORDER_META_MIGRATION_POINTER_OPTION, $pointer, false );
			wp_cache_delete( self::ORDER_META_MIGRATION_POINTER_OPTION, 'options' );

			// Store total for progress tracking (only once at the start).
			update_option( self::ORDER_META_MIGRATION_TOTAL_OPTION, $max_id, false );
			wp_cache_delete( self::ORDER_META_MIGRATION_TOTAL_OPTION, 'options' );
		}

		// If pointer is 0 or less, migration is complete.
		if ( $pointer <= 0 ) {
			delete_option( self::ORDER_META_MIGRATION_POINTER_OPTION );
			wp_cache_delete( self::ORDER_META_MIGRATION_POINTER_OPTION, 'options' );
			return;
		}

		// Calculate end pointer (decrement by batch size).
		$end_pointer = max( 0, $pointer - self::BATCH_SIZE );

		// Migrate HPOS orders.
		foreach ( self::$gateway_id_map as $old_id => $new_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}wc_orders 
					SET payment_method = %s 
					WHERE payment_method = %s 
					AND id <= %d 
					AND id > %d",
					$new_id,
					$old_id,
					$pointer,
					$end_pointer
				)
			);
		}

		// Update pointer.
		update_option( self::ORDER_META_MIGRATION_POINTER_OPTION, $end_pointer, false );
		wp_cache_delete( self::ORDER_META_MIGRATION_POINTER_OPTION, 'options' );

		// Check if this batch completed the migration.
		if ( $end_pointer <= 0 ) {
			delete_option( self::ORDER_META_MIGRATION_POINTER_OPTION );
			wp_cache_delete( self::ORDER_META_MIGRATION_POINTER_OPTION, 'options' );
		}
		// Next batch will be processed on the next page load.
	}

	/**
	 * Migrate subscription meta to use new gateway IDs.
	 * Processes one batch per page load.
	 *
	 * @return void
	 */
	private static function migrate_subscription_meta() {
		// Check if WooCommerce Subscriptions is active.
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			return;
		}

		// Check if migration is already complete (no pointer and total exists = completed).
		$pointer = get_option( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, false );
		$total   = get_option( self::SUBSCRIPTION_META_MIGRATION_TOTAL_OPTION, false );

		// If total exists but pointer doesn't, migration was already completed.
		if ( false === $pointer && false !== $total ) {
			return;
		}

		// Process one batch.
		self::migrate_subscription_meta_batched();
	}

	/**
	 * Batched subscription meta migration.
	 * HPOS: Processes one batch per page load using pointer.
	 *
	 * @return void
	 */
	private static function migrate_subscription_meta_batched() {
		global $wpdb;

		$hpos_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		// If HPOS is not enabled, we're done (legacy migration handled separately).
		if ( ! $hpos_enabled ) {
			return;
		}

		// HPOS migration uses batched pointer approach.
		$pointer = get_option( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, false );

		// Initialize pointer if not set.
		if ( false === $pointer ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_subscription'" );

			$pointer = $max_id;
			update_option( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, $pointer, false );
			wp_cache_delete( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, 'options' );

			// Store total for progress tracking (only once at the start).
			update_option( self::SUBSCRIPTION_META_MIGRATION_TOTAL_OPTION, $max_id, false );
			wp_cache_delete( self::SUBSCRIPTION_META_MIGRATION_TOTAL_OPTION, 'options' );
		}

		// If pointer is 0 or less, migration is complete.
		if ( $pointer <= 0 ) {
			delete_option( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION );
			wp_cache_delete( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, 'options' );
			return;
		}

		// Calculate end pointer (decrement by batch size).
		$end_pointer = max( 0, $pointer - self::BATCH_SIZE );

		// Migrate HPOS subscriptions.
		foreach ( self::$gateway_id_map as $old_id => $new_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}wc_orders 
					SET payment_method = %s 
					WHERE payment_method = %s 
					AND type = 'shop_subscription'
					AND id <= %d 
					AND id > %d",
					$new_id,
					$old_id,
					$pointer,
					$end_pointer
				)
			);
		}

		// Update pointer.
		update_option( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, $end_pointer, false );
		wp_cache_delete( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, 'options' );

		// Check if this batch completed the migration.
		if ( $end_pointer <= 0 ) {
			delete_option( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION );
			wp_cache_delete( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, 'options' );
		}
		// Next batch will be processed on the next page load.
	}

	/**
	 * Get gateway ID map.
	 *
	 * @return array
	 */
	public static function get_gateway_id_map() {
		return self::$gateway_id_map;
	}
}
