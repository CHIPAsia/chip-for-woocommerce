<?php
/**
 * CHIP for WooCommerce Payment Blocks registry.
 *
 * A "payment block" is one payment method row at checkout. The plugin ships
 * six standard blocks (gateways) that are registered by class. This registry
 * adds merchant-created blocks on top: each row here becomes one extra
 * gateway instance of a payment family, so a merchant can offer the same
 * family twice with different methods (for example GrabPay + Touch 'n Go in
 * one block and ShopeePay in another).
 *
 * The registry stores ONLY identity (id + family). Everything a block does at
 * checkout — title, description, method whitelist, logo, enabled flag — lives
 * in that instance's own WooCommerce settings option, which is also what the
 * standard WooCommerce gateway settings screen reads and writes. One source of
 * truth per field, and no second copy to drift.
 *
 * @package CHIP for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payment block registry and family definitions.
 */
class Chip_Woocommerce_Payment_Blocks {

	/**
	 * Option holding the list of merchant-created blocks.
	 */
	const OPTION = 'chip_woocommerce_payment_blocks';

	/**
	 * Prefix for the WooCommerce gateway id of a created block.
	 */
	const GATEWAY_PREFIX = 'chip_block_';

	/**
	 * Gateway id of the standard Online Banking (FPX) block, used as the
	 * default credential source.
	 */
	const PRIMARY_GATEWAY = 'wc_gateway_chip';

	/**
	 * Payment families a block can be created from.
	 *
	 * `methods` lists the whitelist keys offered by the family, `script`
	 * is the WooCommerce Blocks asset suffix shared with the standard
	 * gateway of that family, and `logo` is the default display logo key.
	 *
	 * @return array
	 */
	public static function families() {
		return array(
			'fpx'      => array(
				'label'       => __( 'Online Banking (FPX)', 'chip-for-woocommerce' ),
				'methods'     => array( 'fpx' ),
				'script'      => 'chip_woocommerce_gateway',
				'logo'        => 'fpx',
				'title'       => __( 'Online Banking (FPX)', 'chip-for-woocommerce' ),
				'description' => __( 'Pay with Online Banking (FPX)', 'chip-for-woocommerce' ),
			),
			'fpx_b2b1' => array(
				'label'       => __( 'Corporate Online Banking (FPX)', 'chip-for-woocommerce' ),
				'methods'     => array( 'fpx_b2b1' ),
				'script'      => 'chip_woocommerce_gateway_2',
				'logo'        => 'fpx_b2b1',
				'title'       => __( 'Corporate Online Banking (FPX)', 'chip-for-woocommerce' ),
				'description' => __( 'Pay with Corporate Online Banking (FPX)', 'chip-for-woocommerce' ),
			),
			'card'     => array(
				'label'       => __( 'Card', 'chip-for-woocommerce' ),
				'methods'     => array( 'card', 'mpgs_google_pay', 'mpgs_apple_pay' ),
				'script'      => 'chip_woocommerce_gateway_3',
				'logo'        => 'card',
				'title'       => __( 'Visa / Mastercard', 'chip-for-woocommerce' ),
				'description' => __( 'Pay with Visa / Mastercard', 'chip-for-woocommerce' ),
			),
			'ewallet'  => array(
				'label'       => __( 'E-Wallet', 'chip-for-woocommerce' ),
				'methods'     => array( 'razer_grabpay', 'razer_maybankqr', 'shopee_pay', 'razer_tng' ),
				'script'      => 'chip_woocommerce_gateway_4',
				'logo'        => 'ewallet_only',
				'title'       => __( "GrabPay, Touch 'n Go eWallet, ShopeePay, Maybank QRPay", 'chip-for-woocommerce' ),
				'description' => __( 'Pay with E-Wallet', 'chip-for-woocommerce' ),
			),
			'bnpl'     => array(
				'label'       => __( 'Buy Now Pay Later', 'chip-for-woocommerce' ),
				'methods'     => array( 'razer_atome' ),
				'script'      => 'chip_woocommerce_gateway_5',
				'logo'        => 'paywithchip_all',
				'title'       => __( 'Buy Now Pay Later', 'chip-for-woocommerce' ),
				'description' => __( 'Buy now pay later with Atome.', 'chip-for-woocommerce' ),
			),
			'dnqr'     => array(
				'label'       => __( 'DuitNow QR', 'chip-for-woocommerce' ),
				'methods'     => array( 'duitnow_qr' ),
				'script'      => 'chip_woocommerce_gateway_6',
				'logo'        => 'duitnow_only',
				'title'       => __( 'DuitNow QR', 'chip-for-woocommerce' ),
				'description' => __( 'Pay with DuitNow QR', 'chip-for-woocommerce' ),
			),
		);
	}

	/**
	 * Get one family definition.
	 *
	 * @param string $key Family key.
	 * @return array|null
	 */
	public static function family( $key ) {
		$families = self::families();
		return isset( $families[ $key ] ) ? $families[ $key ] : null;
	}

	/**
	 * Registered blocks.
	 *
	 * @return array
	 */
	public static function get_blocks() {
		$blocks = get_option( self::OPTION, array() );
		if ( ! is_array( $blocks ) ) {
			return array();
		}

		$valid = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || empty( $block['id'] ) || empty( $block['family'] ) ) {
				continue;
			}
			if ( null === self::family( $block['family'] ) ) {
				continue;
			}
			$valid[] = array(
				'id'     => (string) $block['id'],
				'family' => (string) $block['family'],
			);
		}
		return $valid;
	}

	/**
	 * Persist the block list.
	 *
	 * @param array $blocks Blocks.
	 * @return void
	 */
	public static function save_blocks( array $blocks ) {
		update_option( self::OPTION, array_values( $blocks ) );
	}

	/**
	 * Get one registered block.
	 *
	 * @param string $id Block id.
	 * @return array|null
	 */
	public static function get_block( $id ) {
		foreach ( self::get_blocks() as $block ) {
			if ( $block['id'] === $id ) {
				return $block;
			}
		}
		return null;
	}

	/**
	 * WooCommerce gateway id for a block.
	 *
	 * @param string $id Block id.
	 * @return string
	 */
	public static function gateway_id( $id ) {
		return self::GATEWAY_PREFIX . $id;
	}

	/**
	 * Settings option key for a block's gateway instance.
	 *
	 * @param string $id Block id.
	 * @return string
	 */
	public static function settings_key( $id ) {
		return 'woocommerce_' . self::gateway_id( $id ) . '_settings';
	}

	/**
	 * Create a block id that is not already taken.
	 *
	 * @param array $existing Existing block ids.
	 * @return string
	 */
	public static function new_block_id( array $existing = array() ) {
		$id = '';

		for ( $attempt = 0; $attempt < 20; $attempt++ ) {
			$id     = substr( str_replace( array( '.', ' ' ), '', uniqid( '', true ) ), -8 );
			$id     = strtolower( preg_replace( '/[^a-z0-9]/i', '', $id ) );
			$length = strlen( $id );
			if ( $length >= 4 && ! in_array( $id, $existing, true ) ) {
				return $id;
			}
		}

		return $id;
	}

	/**
	 * Default settings for a new block of a family.
	 *
	 * Written at creation time so the block works before the merchant ever
	 * opens its settings screen — `WC_Payment_Gateway::get_option()` only
	 * falls back to a form-field default once that screen saved, and a
	 * block with an empty whitelist would offer every CHIP method instead
	 * of the merchant's selection.
	 *
	 * @param string $family  Family key.
	 * @param array  $args    Title/description/methods/logo/enabled overrides.
	 * @return array
	 */
	public static function default_settings( $family, array $args = array() ) {
		$family_def = self::family( $family );
		if ( null === $family_def ) {
			return array();
		}

		$methods = isset( $args['methods'] ) ? (array) $args['methods'] : $family_def['methods'];
		$methods = array_values( array_intersect( $methods, $family_def['methods'] ) );

		// Wallet families default to the composed icon: a merchant who removes
		// a wallet from the block should not keep advertising it in the logo.
		$default_logo = in_array( $family, array( 'ewallet', 'bnpl' ), true ) ? 'auto' : $family_def['logo'];

		return array(
			'enabled'                  => isset( $args['enabled'] ) && 'no' === $args['enabled'] ? 'no' : 'yes',
			'title'                    => isset( $args['title'] ) && '' !== trim( (string) $args['title'] ) ? (string) $args['title'] : $family_def['title'],
			'method_title'             => sprintf( 'CHIP %s', $family_def['label'] ),
			'description'              => isset( $args['description'] ) ? (string) $args['description'] : $family_def['description'],
			'payment_method_whitelist' => $methods,
			'display_logo'             => isset( $args['logo'] ) && '' !== $args['logo'] ? (string) $args['logo'] : $default_logo,
			// Behavior defaults: the created block behaves like a standard
			// block out of the box (dropdown on checkout, no card form).
			'bypass_chip'              => 'yes',
			'due_strict'               => 'yes',
			'purchase_time_zone'       => 'Asia/Kuala_Lumpur',
			'system_url_scheme'        => 'https',
			'payment_action'           => 'sale',
			'cancel_order_flow'        => 'no',
			'debug'                    => 'no',
			// Credentials are inherited from the primary CHIP block unless
			// the merchant overrides them on this block's settings screen.
			'brand_id'                 => '',
			'secret_key'               => '',
			'public_key'               => '',
		);
	}

	/**
	 * Add a block.
	 *
	 * @param string $family Family key.
	 * @param array  $args   Settings overrides.
	 * @return string|WP_Error Block id on success.
	 */
	public static function add_block( $family, array $args = array() ) {
		$family_def = self::family( $family );
		if ( null === $family_def ) {
			return new WP_Error( 'chip_block_family', __( 'Unknown payment family.', 'chip-for-woocommerce' ) );
		}

		$blocks = self::get_blocks();
		$ids    = array_column( $blocks, 'id' );
		$id     = self::new_block_id( $ids );

		$settings = self::default_settings( $family, $args );
		if ( empty( $settings['payment_method_whitelist'] ) ) {
			return new WP_Error( 'chip_block_methods', __( 'Select at least one payment method.', 'chip-for-woocommerce' ) );
		}

		$blocks[] = array(
			'id'     => $id,
			'family' => $family,
		);
		self::save_blocks( $blocks );
		update_option( self::settings_key( $id ), $settings );

		return $id;
	}

	/**
	 * Update a block's settings.
	 *
	 * @param string $id   Block id.
	 * @param array  $args Settings overrides.
	 * @return true|WP_Error
	 */
	public static function update_block( $id, array $args = array() ) {
		$block = self::get_block( $id );
		if ( null === $block ) {
			return new WP_Error( 'chip_block_missing', __( 'That payment block no longer exists.', 'chip-for-woocommerce' ) );
		}

		$family_def = self::family( $block['family'] );
		$current    = self::get_block_settings( $id );
		$settings   = array_merge( self::default_settings( $block['family'], $args ), $current );

		// Fields the merchant just edited win over the stored values.
		foreach ( array( 'enabled', 'title', 'description', 'display_logo' ) as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$settings[ $key ] = (string) $args[ $key ];
			}
		}
		if ( isset( $args['methods'] ) ) {
			$methods = array_values( array_intersect( (array) $args['methods'], $family_def['methods'] ) );
			if ( empty( $methods ) ) {
				return new WP_Error( 'chip_block_methods', __( 'Select at least one payment method.', 'chip-for-woocommerce' ) );
			}
			$settings['payment_method_whitelist'] = $methods;
		}
		if ( '' === trim( (string) $settings['title'] ) ) {
			return new WP_Error( 'chip_block_title', __( 'The checkout title cannot be empty.', 'chip-for-woocommerce' ) );
		}

		update_option( self::settings_key( $id ), $settings );

		return true;
	}

	/**
	 * Delete a block and its settings.
	 *
	 * @param string $id Block id.
	 * @return bool
	 */
	public static function delete_block( $id ) {
		$blocks = self::get_blocks();
		$kept   = array();
		$found  = false;

		foreach ( $blocks as $block ) {
			if ( $block['id'] === $id ) {
				$found = true;
				continue;
			}
			$kept[] = $block;
		}

		if ( ! $found ) {
			return false;
		}

		self::save_blocks( $kept );
		delete_option( self::settings_key( $id ) );

		return true;
	}

	/**
	 * Stored settings for a block.
	 *
	 * @param string $id Block id.
	 * @return array
	 */
	public static function get_block_settings( $id ) {
		$settings = get_option( self::settings_key( $id ), array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Settings of the block that supplies the CHIP credentials.
	 *
	 * Defaults to the standard Online Banking (FPX) block. If that block has
	 * no credentials (the merchant may have configured only one other
	 * block), the first CHIP gateway that does have them is used, so a
	 * created block is never silently credential-less.
	 *
	 * @return array
	 */
	public static function credential_source_settings() {
		$primary = get_option( 'woocommerce_' . self::PRIMARY_GATEWAY . '_settings', array() );
		if ( is_array( $primary ) && ! empty( $primary['brand_id'] ) && ! empty( $primary['secret_key'] ) ) {
			return $primary;
		}

		$gateway_ids = array(
			self::PRIMARY_GATEWAY,
			'wc_gateway_chip_2',
			'wc_gateway_chip_3',
			'wc_gateway_chip_4',
			'wc_gateway_chip_5',
			'wc_gateway_chip_6',
		);
		foreach ( $gateway_ids as $gateway_id ) {
			$settings = get_option( 'woocommerce_' . $gateway_id . '_settings', array() );
			if ( is_array( $settings ) && ! empty( $settings['brand_id'] ) && ! empty( $settings['secret_key'] ) ) {
				return $settings;
			}
		}

		return is_array( $primary ) ? $primary : array();
	}
}
