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
	 * Migration completion notice option name.
	 */
	const MIGRATION_COMPLETION_NOTICE_OPTION = 'chip_woocommerce_migration_completion_notice';

	/**
	 * Batch size for large database migrations.
	 */
	const BATCH_SIZE = 1000;

	/**
	 * Threshold for using batched migration (records).
	 */
	const BATCH_THRESHOLD = 50000;

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
			// Register cron hooks for batched migrations.
			add_action( 'chip_woocommerce_migrate_order_meta_batch', array( __CLASS__, 'handle_order_meta_batch_cron' ) );
			add_action( 'chip_woocommerce_migrate_subscription_meta_batch', array( __CLASS__, 'handle_subscription_meta_batch_cron' ) );

			// Register admin notices hook.
			add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );

			self::migrate_gateway_settings();
			self::migrate_payment_tokens();
			self::migrate_user_meta();
			self::migrate_order_meta();
			self::migrate_subscription_meta();

			update_option( self::MIGRATION_VERSION_OPTION, self::CURRENT_VERSION );
		} else {
			// Always register admin notices to show migration status.
			add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		}
	}

	/**
	 * Display admin notices for migration status.
	 *
	 * @return void
	 */
	public static function admin_notices() {
		// Only show on admin pages.
		if ( ! is_admin() ) {
			return;
		}

		// Check if batched migration is in progress.
		$order_pointer        = get_option( self::ORDER_META_MIGRATION_POINTER_OPTION, false );
		$subscription_pointer = get_option( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, false );

		// Migration in progress.
		if ( false !== $order_pointer || false !== $subscription_pointer ) {
			?>
			<div class="notice notice-info is-dismissible">
				<p>
					<strong><?php esc_html_e( 'CHIP for WooCommerce:', 'chip-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'Database migration is currently in progress. This process runs in the background and may take a while depending on the number of orders and subscriptions. Please be patient.', 'chip-for-woocommerce' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		// Check if migration completion notice should be shown.
		$completion_notice = get_option( self::MIGRATION_COMPLETION_NOTICE_OPTION, false );
		if ( 'shown' !== $completion_notice && 'batched' === $completion_notice ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<strong><?php esc_html_e( 'CHIP for WooCommerce:', 'chip-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'Database migration completed successfully. All orders and subscriptions have been updated.', 'chip-for-woocommerce' ); ?>
				</p>
			</div>
			<?php
			// Mark notice as shown.
			update_option( self::MIGRATION_COMPLETION_NOTICE_OPTION, 'shown', false );
			wp_cache_delete( self::MIGRATION_COMPLETION_NOTICE_OPTION, 'options' );
		}
	}

	/**
	 * Handle order meta batch migration cron.
	 *
	 * @return void
	 */
	public static function handle_order_meta_batch_cron() {
		self::migrate_order_meta_batched();
	}

	/**
	 * Handle subscription meta batch migration cron.
	 *
	 * @return void
	 */
	public static function handle_subscription_meta_batch_cron() {
		self::migrate_subscription_meta_batched();
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
	 * Get record counts for orders and subscriptions.
	 *
	 * @return array Array with 'orders' and 'subscriptions' counts.
	 */
	private static function get_migration_record_counts() {
		global $wpdb;

		$hpos_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		$sync_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'is_custom_order_tables_in_sync' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::is_custom_order_tables_in_sync();

		$total_order_records        = 0;
		$total_subscription_records = 0;

		foreach ( self::$gateway_id_map as $old_id => $new_id ) {
			// Count orders.
			if ( $hpos_enabled ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$count                = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE payment_method = %s AND type != 'shop_subscription'",
						$old_id
					)
				);
				$total_order_records += (int) $count;
			}

			if ( ! $hpos_enabled || $sync_enabled ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$count                = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
						INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
						WHERE pm.meta_key = '_payment_method'
						AND pm.meta_value = %s
						AND p.post_type IN ('shop_order', 'shop_order_refund')",
						$old_id
					)
				);
				$total_order_records += (int) $count;
			}

			// Count subscriptions if WooCommerce Subscriptions is active.
			if ( class_exists( 'WC_Subscriptions' ) ) {
				if ( $hpos_enabled ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$count                = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders 
							WHERE payment_method = %s 
							AND type = 'shop_subscription'",
							$old_id
						)
					);
					$total_subscription_records += (int) $count;
				}

				if ( ! $hpos_enabled || $sync_enabled ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$count                = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
							INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
							WHERE pm.meta_key = '_payment_method'
							AND pm.meta_value = %s
							AND p.post_type = 'shop_subscription'",
							$old_id
						)
					);
					$total_subscription_records += (int) $count;
				}
			}
		}

		return array(
			'orders'        => $total_order_records,
			'subscriptions' => $total_subscription_records,
		);
	}

	/**
	 * Migrate order meta to use new gateway IDs.
	 *
	 * @return void
	 */
	private static function migrate_order_meta() {
		$counts                    = self::get_migration_record_counts();
		$total_order_records       = $counts['orders'];
		$total_subscription_records = $counts['subscriptions'];
		$total_combined            = $total_order_records + $total_subscription_records;

		// Use simple migration if below threshold (100k per type OR 200k combined).
		if ( ( $total_order_records < self::BATCH_THRESHOLD && $total_subscription_records < self::BATCH_THRESHOLD ) || $total_combined < ( self::BATCH_THRESHOLD * 2 ) ) {
			self::migrate_order_meta_simple();
			return;
		}

		// Mark that batched migration is being used.
		update_option( self::MIGRATION_COMPLETION_NOTICE_OPTION, 'batched', false );
		wp_cache_delete( self::MIGRATION_COMPLETION_NOTICE_OPTION, 'options' );

		// Use batched migration for large databases.
		self::migrate_order_meta_batched();
	}

	/**
	 * Simple order meta migration (for small databases).
	 *
	 * @return void
	 */
	private static function migrate_order_meta_simple() {
		global $wpdb;

		$hpos_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		$sync_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'is_custom_order_tables_in_sync' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::is_custom_order_tables_in_sync();

		// Update HPOS table if enabled.
		if ( $hpos_enabled ) {
			foreach ( self::$gateway_id_map as $old_id => $new_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->prefix . 'wc_orders',
					array( 'payment_method' => $new_id ),
					array( 'payment_method' => $old_id ),
					array( '%s' ),
					array( '%s' )
				);
			}
		}

		// Update legacy post meta if HPOS is disabled OR sync mode is enabled (compatibility mode).
		if ( ! $hpos_enabled || $sync_enabled ) {
			foreach ( self::$gateway_id_map as $old_id => $new_id ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Migration requires direct meta queries.
				$wpdb->update(
					$wpdb->postmeta,
					array( 'meta_value' => $new_id ),
					array(
						'meta_key'   => '_payment_method',
						'meta_value' => $old_id,
					),
					array( '%s' ),
					array( '%s', '%s' )
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			}
		}
	}

	/**
	 * Batched order meta migration (for large databases).
	 *
	 * @return void
	 */
	private static function migrate_order_meta_batched() {
		global $wpdb;

		$hpos_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		$sync_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'is_custom_order_tables_in_sync' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::is_custom_order_tables_in_sync();

		// Get migration pointer (starts from max order ID).
		$pointer = get_option( self::ORDER_META_MIGRATION_POINTER_OPTION, false );

		// Initialize pointer if not set.
		if ( false === $pointer ) {
			$max_id = 0;

			if ( $hpos_enabled ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$max_id = max( $max_id, (int) $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}wc_orders" ) );
			}

			if ( ! $hpos_enabled || $sync_enabled ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$max_id = max( $max_id, (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type IN ('shop_order', 'shop_order_refund')" ) );
			}

			$pointer = $max_id;
			update_option( self::ORDER_META_MIGRATION_POINTER_OPTION, $pointer, false );
			wp_cache_delete( self::ORDER_META_MIGRATION_POINTER_OPTION, 'options' );
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
		if ( $hpos_enabled ) {
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
		}

		// Migrate legacy post meta.
		if ( ! $hpos_enabled || $sync_enabled ) {
			foreach ( self::$gateway_id_map as $old_id => $new_id ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Migration requires direct meta queries.
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->postmeta} pm
						INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
						SET pm.meta_value = %s
						WHERE pm.meta_key = '_payment_method'
						AND pm.meta_value = %s
						AND p.post_type IN ('shop_order', 'shop_order_refund')
						AND p.ID <= %d
						AND p.ID > %d",
						$new_id,
						$old_id,
						$pointer,
						$end_pointer
					)
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			}
		}

		// Update pointer.
		update_option( self::ORDER_META_MIGRATION_POINTER_OPTION, $end_pointer, false );
		wp_cache_delete( self::ORDER_META_MIGRATION_POINTER_OPTION, 'options' );

		// Schedule next batch if not complete.
		if ( $end_pointer > 0 ) {
			wp_schedule_single_event( time() + 1, 'chip_woocommerce_migrate_order_meta_batch' );
		} else {
			delete_option( self::ORDER_META_MIGRATION_POINTER_OPTION );
			wp_cache_delete( self::ORDER_META_MIGRATION_POINTER_OPTION, 'options' );

			// Check if subscription migration is also complete.
			$subscription_pointer = get_option( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, false );
			if ( false === $subscription_pointer ) {
				// Both migrations complete, ensure completion notice is set.
				$completion_notice = get_option( self::MIGRATION_COMPLETION_NOTICE_OPTION, false );
				if ( 'batched' === $completion_notice ) {
					// Keep it as 'batched' so notice will show.
					update_option( self::MIGRATION_COMPLETION_NOTICE_OPTION, 'batched', false );
					wp_cache_delete( self::MIGRATION_COMPLETION_NOTICE_OPTION, 'options' );
				}
			}
		}
	}

	/**
	 * Migrate subscription meta to use new gateway IDs.
	 *
	 * @return void
	 */
	private static function migrate_subscription_meta() {
		// Check if WooCommerce Subscriptions is active.
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			return;
		}

		$counts                    = self::get_migration_record_counts();
		$total_order_records       = $counts['orders'];
		$total_subscription_records = $counts['subscriptions'];
		$total_combined            = $total_order_records + $total_subscription_records;

		// Use simple migration if below threshold (100k per type OR 200k combined).
		if ( ( $total_order_records < self::BATCH_THRESHOLD && $total_subscription_records < self::BATCH_THRESHOLD ) || $total_combined < ( self::BATCH_THRESHOLD * 2 ) ) {
			self::migrate_subscription_meta_simple();
			return;
		}

		// Mark that batched migration is being used.
		update_option( self::MIGRATION_COMPLETION_NOTICE_OPTION, 'batched', false );
		wp_cache_delete( self::MIGRATION_COMPLETION_NOTICE_OPTION, 'options' );

		// Use batched migration for large databases.
		self::migrate_subscription_meta_batched();
	}

	/**
	 * Simple subscription meta migration (for small databases).
	 *
	 * @return void
	 */
	private static function migrate_subscription_meta_simple() {
		global $wpdb;

		$hpos_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		$sync_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'is_custom_order_tables_in_sync' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::is_custom_order_tables_in_sync();

		// Update HPOS table if enabled.
		if ( $hpos_enabled ) {
			foreach ( self::$gateway_id_map as $old_id => $new_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->prefix . 'wc_orders',
					array( 'payment_method' => $new_id ),
					array(
						'payment_method' => $old_id,
						'type'           => 'shop_subscription',
					),
					array( '%s' ),
					array( '%s', '%s' )
				);
			}
		}

		// Update legacy post meta if HPOS is disabled OR sync mode is enabled (compatibility mode).
		if ( ! $hpos_enabled || $sync_enabled ) {
			foreach ( self::$gateway_id_map as $old_id => $new_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->postmeta} pm
						INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
						SET pm.meta_value = %s
						WHERE pm.meta_key = '_payment_method'
						AND pm.meta_value = %s
						AND p.post_type = 'shop_subscription'",
						$new_id,
						$old_id
					)
				);
			}
		}
	}

	/**
	 * Batched subscription meta migration (for large databases).
	 *
	 * @return void
	 */
	private static function migrate_subscription_meta_batched() {
		global $wpdb;

		$hpos_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		$sync_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'is_custom_order_tables_in_sync' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::is_custom_order_tables_in_sync();

		// Get migration pointer (starts from max subscription ID).
		$pointer = get_option( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, false );

		// Initialize pointer if not set.
		if ( false === $pointer ) {
			$max_id = 0;

			if ( $hpos_enabled ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$max_id = max( $max_id, (int) $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_subscription'" ) );
			}

			if ( ! $hpos_enabled || $sync_enabled ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$max_id = max( $max_id, (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type = 'shop_subscription'" ) );
			}

			$pointer = $max_id;
			update_option( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, $pointer, false );
			wp_cache_delete( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, 'options' );
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
		if ( $hpos_enabled ) {
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
		}

		// Migrate legacy post meta.
		if ( ! $hpos_enabled || $sync_enabled ) {
			foreach ( self::$gateway_id_map as $old_id => $new_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->postmeta} pm
						INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
						SET pm.meta_value = %s
						WHERE pm.meta_key = '_payment_method'
						AND pm.meta_value = %s
						AND p.post_type = 'shop_subscription'
						AND p.ID <= %d
						AND p.ID > %d",
						$new_id,
						$old_id,
						$pointer,
						$end_pointer
					)
				);
			}
		}

		// Update pointer.
		update_option( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, $end_pointer, false );
		wp_cache_delete( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, 'options' );

		// Schedule next batch if not complete.
		if ( $end_pointer > 0 ) {
			wp_schedule_single_event( time() + 1, 'chip_woocommerce_migrate_subscription_meta_batch' );
		} else {
			delete_option( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION );
			wp_cache_delete( self::SUBSCRIPTION_META_MIGRATION_POINTER_OPTION, 'options' );

			// Check if order migration is also complete.
			$order_pointer = get_option( self::ORDER_META_MIGRATION_POINTER_OPTION, false );
			if ( false === $order_pointer ) {
				// Both migrations complete, ensure completion notice is set.
				$completion_notice = get_option( self::MIGRATION_COMPLETION_NOTICE_OPTION, false );
				if ( 'batched' === $completion_notice ) {
					// Keep it as 'batched' so notice will show.
					update_option( self::MIGRATION_COMPLETION_NOTICE_OPTION, 'batched', false );
					wp_cache_delete( self::MIGRATION_COMPLETION_NOTICE_OPTION, 'options' );
				}
			}
		}
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
