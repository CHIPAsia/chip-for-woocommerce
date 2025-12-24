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
	 * Migration progress option name.
	 */
	const MIGRATION_PROGRESS_OPTION = 'chip_woocommerce_migration_progress';

	/**
	 * Current migration version.
	 */
	const CURRENT_VERSION = '2.0.0';

	/**
	 * Batch size for processing records.
	 */
	const BATCH_SIZE = 100;

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
			$progress = get_option( self::MIGRATION_PROGRESS_OPTION, array() );

			// Migrate gateway settings first (always safe, no batching needed).
			if ( ! isset( $progress['gateway_settings'] ) ) {
				self::migrate_gateway_settings();
				$progress['gateway_settings'] = true;
				update_option( self::MIGRATION_PROGRESS_OPTION, $progress );
			}

			// Process other migrations in batches, one batch per page load.
			if ( ! isset( $progress['payment_tokens'] ) || true !== $progress['payment_tokens'] ) {
				$gateway_index        = ( isset( $progress['payment_tokens'] ) && is_int( $progress['payment_tokens'] ) ) ? $progress['payment_tokens'] : 0;
				$progress['payment_tokens'] = self::migrate_payment_tokens_batched( $gateway_index );
				update_option( self::MIGRATION_PROGRESS_OPTION, $progress );
				if ( true !== $progress['payment_tokens'] ) {
					return; // Still processing, continue on next page load.
				}
			}

			if ( ! isset( $progress['user_meta'] ) || true !== $progress['user_meta'] ) {
				$progress['user_meta'] = self::migrate_user_meta_batched( $progress['user_meta'] ?? array() );
				update_option( self::MIGRATION_PROGRESS_OPTION, $progress );
				if ( true !== $progress['user_meta'] ) {
					return; // Still processing, continue on next page load.
				}
			}

			if ( ! isset( $progress['order_meta'] ) || true !== $progress['order_meta'] ) {
				$progress['order_meta'] = self::migrate_order_meta_batched( $progress['order_meta'] ?? array() );
				update_option( self::MIGRATION_PROGRESS_OPTION, $progress );
				if ( true !== $progress['order_meta'] ) {
					return; // Still processing, continue on next page load.
				}
			}

			if ( ! isset( $progress['subscription_meta'] ) || true !== $progress['subscription_meta'] ) {
				$progress['subscription_meta'] = self::migrate_subscription_meta_batched( $progress['subscription_meta'] ?? array() );
				update_option( self::MIGRATION_PROGRESS_OPTION, $progress );
				if ( true !== $progress['subscription_meta'] ) {
					return; // Still processing, continue on next page load.
				}
			}

			// All migrations complete.
			update_option( self::MIGRATION_VERSION_OPTION, self::CURRENT_VERSION );
			delete_option( self::MIGRATION_PROGRESS_OPTION );
		}
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
	 * Migrate payment tokens to use new gateway IDs (batched).
	 *
	 * @param int $gateway_index Current gateway index being processed.
	 * @return bool|int True if complete, false if still processing, or gateway index if continuing.
	 */
	private static function migrate_payment_tokens_batched( $gateway_index = 0 ) {
		global $wpdb;

		$gateway_ids = array_keys( self::$gateway_id_map );

		if ( $gateway_index >= count( $gateway_ids ) ) {
			return true; // All gateways processed.
		}

		$old_id = $gateway_ids[ $gateway_index ];
		$new_id = self::$gateway_id_map[ $old_id ];

		// Get count of remaining tokens to migrate.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE gateway_id = %s",
				$old_id
			)
		);

		if ( 0 === (int) $total ) {
			// No tokens for this gateway, move to next.
			return self::migrate_payment_tokens_batched( $gateway_index + 1 );
		}

		// Process batch.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}woocommerce_payment_tokens 
				SET gateway_id = %s 
				WHERE gateway_id = %s 
				LIMIT %d",
				$new_id,
				$old_id,
				self::BATCH_SIZE
			)
		);

		// Check if more tokens remain for this gateway.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$remaining = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE gateway_id = %s",
				$old_id
			)
		);

		if ( 0 === (int) $remaining ) {
			// This gateway complete, move to next.
			return self::migrate_payment_tokens_batched( $gateway_index + 1 );
		}

		// Still processing this gateway.
		return $gateway_index;
	}

	/**
	 * Migrate user meta keys from old gateway IDs to new gateway IDs (batched).
	 *
	 * @param array $progress Progress array with gateway_index and last_umeta_id.
	 * @return bool|array True if complete, or progress array if still processing.
	 */
	private static function migrate_user_meta_batched( $progress = array() ) {
		global $wpdb;

		$gateway_index = $progress['gateway_index'] ?? 0;
		$last_umeta_id = $progress['last_umeta_id'] ?? 0;

		$gateway_ids = array_keys( self::$gateway_id_map );

		if ( $gateway_index >= count( $gateway_ids ) ) {
			return true; // All gateways processed.
		}

		$old_id = $gateway_ids[ $gateway_index ];
		$new_id = self::$gateway_id_map[ $old_id ];

		// Process batch.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->usermeta} 
				SET meta_key = REPLACE(meta_key, %s, %s) 
				WHERE meta_key LIKE %s 
				AND umeta_id > %d 
				ORDER BY umeta_id ASC 
				LIMIT %d",
				'_' . $old_id . '_',
				'_' . $new_id . '_',
				'%\_' . $wpdb->esc_like( $old_id ) . '\_%',
				$last_umeta_id,
				self::BATCH_SIZE
			)
		);

		// Get the last processed umeta_id.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$last_processed = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(umeta_id) FROM {$wpdb->usermeta} 
				WHERE meta_key LIKE %s 
				AND umeta_id > %d",
				'%\_' . $wpdb->esc_like( $old_id ) . '\_%',
				$last_umeta_id
			)
		);

		if ( null === $last_processed || $last_processed <= $last_umeta_id ) {
			// This gateway complete, move to next.
			return self::migrate_user_meta_batched(
				array(
					'gateway_index' => $gateway_index + 1,
					'last_umeta_id' => 0,
				)
			);
		}

		// Still processing this gateway.
		return array(
			'gateway_index' => $gateway_index,
			'last_umeta_id' => (int) $last_processed,
		);
	}

	/**
	 * Migrate order meta to use new gateway IDs (batched).
	 *
	 * @param array $progress Progress array with stage, gateway_index, and last_id.
	 * @return bool|array True if complete, or progress array if still processing.
	 */
	private static function migrate_order_meta_batched( $progress = array() ) {
		global $wpdb;

		$hpos_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		$sync_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'is_custom_order_tables_in_sync' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::is_custom_order_tables_in_sync();

		// Default stage based on HPOS status if not set in progress.
		$default_stage = $hpos_enabled ? 'hpos' : 'postmeta';
		$stage         = $progress['stage'] ?? $default_stage; // 'hpos' or 'postmeta'.
		$gateway_index = $progress['gateway_index'] ?? 0;
		$last_id       = $progress['last_id'] ?? 0;

		$gateway_ids = array_keys( self::$gateway_id_map );

		// Process HPOS table if enabled.
		if ( 'hpos' === $stage && $hpos_enabled ) {
			if ( $gateway_index >= count( $gateway_ids ) ) {
				// All HPOS gateways processed, move to postmeta stage.
				if ( ! $hpos_enabled || $sync_enabled ) {
					return self::migrate_order_meta_batched(
						array(
							'stage'         => 'postmeta',
							'gateway_index' => 0,
							'last_id'       => 0,
						)
					);
				}
				return true; // Complete.
			}

			$old_id = $gateway_ids[ $gateway_index ];
			$new_id = self::$gateway_id_map[ $old_id ];

			// Process batch.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}wc_orders 
					SET payment_method = %s 
					WHERE payment_method = %s 
					AND id > %d 
					ORDER BY id ASC 
					LIMIT %d",
					$new_id,
					$old_id,
					$last_id,
					self::BATCH_SIZE
				)
			);

			// Check if more orders remain for this gateway.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$last_processed = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(id) FROM {$wpdb->prefix}wc_orders 
					WHERE payment_method = %s 
					AND id > %d",
					$old_id,
					$last_id
				)
			);

			if ( null === $last_processed || $last_processed <= $last_id ) {
				// This gateway complete, move to next.
				return self::migrate_order_meta_batched(
					array(
						'stage'         => 'hpos',
						'gateway_index' => $gateway_index + 1,
						'last_id'       => 0,
					)
				);
			}

			// Still processing this gateway.
			return array(
				'stage'         => 'hpos',
				'gateway_index' => $gateway_index,
				'last_id'       => (int) $last_processed,
			);
		}

		// Process legacy post meta if HPOS is disabled OR sync mode is enabled.
		if ( 'postmeta' === $stage && ( ! $hpos_enabled || $sync_enabled ) ) {
			if ( $gateway_index >= count( $gateway_ids ) ) {
				return true; // Complete.
			}

			$old_id = $gateway_ids[ $gateway_index ];
			$new_id = self::$gateway_id_map[ $old_id ];

			// Process batch.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Migration requires direct meta queries.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					SET pm.meta_value = %s
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value = %s
					AND p.post_type IN ('shop_order', 'shop_order_refund')
					AND pm.meta_id > %d
					ORDER BY pm.meta_id ASC
					LIMIT %d",
					$new_id,
					$old_id,
					$last_id,
					self::BATCH_SIZE
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value

			// Check if more postmeta remain for this gateway.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$last_processed = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(pm.meta_id) FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value = %s
					AND p.post_type IN ('shop_order', 'shop_order_refund')
					AND pm.meta_id > %d",
					$old_id,
					$last_id
				)
			);

			if ( null === $last_processed || $last_processed <= $last_id ) {
				// This gateway complete, move to next.
				return self::migrate_order_meta_batched(
					array(
						'stage'         => 'postmeta',
						'gateway_index' => $gateway_index + 1,
						'last_id'       => 0,
					)
				);
			}

			// Still processing this gateway.
			return array(
				'stage'         => 'postmeta',
				'gateway_index' => $gateway_index,
				'last_id'       => (int) $last_processed,
			);
		}

		return true; // Complete.
	}

	/**
	 * Migrate subscription meta to use new gateway IDs (batched).
	 *
	 * @param array $progress Progress array with stage, gateway_index, and last_id.
	 * @return bool|array True if complete, or progress array if still processing.
	 */
	private static function migrate_subscription_meta_batched( $progress = array() ) {
		global $wpdb;

		// Check if WooCommerce Subscriptions is active.
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			return true; // Skip if not active.
		}

		$hpos_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		$sync_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'is_custom_order_tables_in_sync' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::is_custom_order_tables_in_sync();

		// Default stage based on HPOS status if not set in progress.
		$default_stage = $hpos_enabled ? 'hpos' : 'postmeta';
		$stage         = $progress['stage'] ?? $default_stage; // 'hpos' or 'postmeta'.
		$gateway_index = $progress['gateway_index'] ?? 0;
		$last_id       = $progress['last_id'] ?? 0;

		$gateway_ids = array_keys( self::$gateway_id_map );

		// Process HPOS table if enabled.
		if ( 'hpos' === $stage && $hpos_enabled ) {
			if ( $gateway_index >= count( $gateway_ids ) ) {
				// All HPOS gateways processed, move to postmeta stage.
				if ( ! $hpos_enabled || $sync_enabled ) {
					return self::migrate_subscription_meta_batched(
						array(
							'stage'         => 'postmeta',
							'gateway_index' => 0,
							'last_id'       => 0,
						)
					);
				}
				return true; // Complete.
			}

			$old_id = $gateway_ids[ $gateway_index ];
			$new_id = self::$gateway_id_map[ $old_id ];

			// Process batch.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}wc_orders 
					SET payment_method = %s 
					WHERE payment_method = %s 
					AND type = 'shop_subscription'
					AND id > %d 
					ORDER BY id ASC 
					LIMIT %d",
					$new_id,
					$old_id,
					$last_id,
					self::BATCH_SIZE
				)
			);

			// Check if more subscriptions remain for this gateway.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$last_processed = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(id) FROM {$wpdb->prefix}wc_orders 
					WHERE payment_method = %s 
					AND type = 'shop_subscription'
					AND id > %d",
					$old_id,
					$last_id
				)
			);

			if ( null === $last_processed || $last_processed <= $last_id ) {
				// This gateway complete, move to next.
				return self::migrate_subscription_meta_batched(
					array(
						'stage'         => 'hpos',
						'gateway_index' => $gateway_index + 1,
						'last_id'       => 0,
					)
				);
			}

			// Still processing this gateway.
			return array(
				'stage'         => 'hpos',
				'gateway_index' => $gateway_index,
				'last_id'       => (int) $last_processed,
			);
		}

		// Process legacy post meta if HPOS is disabled OR sync mode is enabled.
		if ( 'postmeta' === $stage && ( ! $hpos_enabled || $sync_enabled ) ) {
			if ( $gateway_index >= count( $gateway_ids ) ) {
				return true; // Complete.
			}

			$old_id = $gateway_ids[ $gateway_index ];
			$new_id = self::$gateway_id_map[ $old_id ];

			// Process batch.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					SET pm.meta_value = %s
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value = %s
					AND p.post_type = 'shop_subscription'
					AND pm.meta_id > %d
					ORDER BY pm.meta_id ASC
					LIMIT %d",
					$new_id,
					$old_id,
					$last_id,
					self::BATCH_SIZE
				)
			);

			// Check if more postmeta remain for this gateway.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$last_processed = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(pm.meta_id) FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value = %s
					AND p.post_type = 'shop_subscription'
					AND pm.meta_id > %d",
					$old_id,
					$last_id
				)
			);

			if ( null === $last_processed || $last_processed <= $last_id ) {
				// This gateway complete, move to next.
				return self::migrate_subscription_meta_batched(
					array(
						'stage'         => 'postmeta',
						'gateway_index' => $gateway_index + 1,
						'last_id'       => 0,
					)
				);
			}

			// Still processing this gateway.
			return array(
				'stage'         => 'postmeta',
				'gateway_index' => $gateway_index,
				'last_id'       => (int) $last_processed,
			);
		}

		return true; // Complete.
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
