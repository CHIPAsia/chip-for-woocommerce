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
	const CURRENT_VERSION = '1.9.0';

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
			self::migrate_gateway_settings();
			self::migrate_payment_tokens();
			self::migrate_user_meta();
			self::migrate_order_meta();
			self::migrate_subscription_meta();

			update_option( self::MIGRATION_VERSION_OPTION, self::CURRENT_VERSION );
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
	 * Migrate order meta to use new gateway IDs.
	 *
	 * @return void
	 */
	private static function migrate_order_meta() {
		global $wpdb;

		// Check if HPOS is enabled.
		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
		) {
			// HPOS is enabled, update orders table directly.
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
		} else {
			// Legacy post meta storage.
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
	 * Migrate subscription meta to use new gateway IDs.
	 *
	 * @return void
	 */
	private static function migrate_subscription_meta() {
		global $wpdb;

		// Check if WooCommerce Subscriptions is active.
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			return;
		}

		// Check if HPOS is enabled.
		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
		) {
			// HPOS is enabled, update orders table for subscriptions.
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
		} else {
			// Legacy post meta storage for subscriptions.
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
	 * Get gateway ID map.
	 *
	 * @return array
	 */
	public static function get_gateway_id_map() {
		return self::$gateway_id_map;
	}
}
