<?php
/**
 * CHIP for WooCommerce Blocks Support
 *
 * Adds WooCommerce Blocks support for CHIP payment gateway.
 *
 * @package CHIP for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * WooCommerce Blocks support class for CHIP gateway.
 */
class Chip_Woocommerce_Gateway_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Gateway instance.
	 *
	 * @var Chip_Woocommerce_Gateway
	 */
	protected $gateway;

	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'wc_gateway_chip';

	/**
	 * Script name for assets.
	 *
	 * @var string
	 */
	protected $script_name = 'chip_woocommerce_gateway';

	/**
	 * Initialize the payment method.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->gateway = Chip_Woocommerce::get_chip_gateway_class( $this->name );
		if ( $this->gateway ) {
			$this->settings = $this->gateway->settings;
		}
	}

	/**
	 * Check if payment method is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->gateway && $this->gateway->is_available();
	}

	/**
	 * Get payment method script handles.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		if ( ! $this->gateway ) {
			return array();
		}

		$script_path       = 'assets/js/frontend/blocks_' . $this->script_name . '.js';
		$script_asset_path = plugin_dir_path( CHIP_WOOCOMMERCE_FILE ) . 'assets/js/frontend/blocks_' . $this->script_name . '.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => CHIP_WOOCOMMERCE_MODULE_VERSION,
			);
		$script_url        = CHIP_WOOCOMMERCE_URL . $script_path;

		// The clone bundles `import UnifiedPaymentMethodList from
		// 'chip/unified-payment-method-list'`, which webpack resolves to
		// `window.chip.UnifiedPaymentMethodList`. The shared bundle exposes
		// its default export at that global (see webpack.config.js). We list
		// the shared bundle's handle here so WordPress loads it BEFORE the
		// clone, otherwise `window.chip.UnifiedPaymentMethodList` would be
		// undefined when the clone runs.
		$shared_script_path       = 'assets/js/frontend/unified-payment-method-list.js';
		$shared_script_asset_path = plugin_dir_path( CHIP_WOOCOMMERCE_FILE ) . 'assets/js/frontend/unified-payment-method-list.asset.php';
		$shared_script_asset      = file_exists( $shared_script_asset_path )
			? require $shared_script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => CHIP_WOOCOMMERCE_MODULE_VERSION,
			);
		$shared_script_url        = CHIP_WOOCOMMERCE_URL . $shared_script_path;

		wp_register_script(
			'chip-unified-payment-method-list',
			$shared_script_url,
			$shared_script_asset['dependencies'],
			$shared_script_asset['version'],
			true
		);

		$clone_dependencies = array_merge(
			$script_asset['dependencies'],
			array( 'chip-unified-payment-method-list' )
		);

		wp_register_script(
			"wc-{$this->name}-blocks",
			$script_url,
			$clone_dependencies,
			$script_asset['version'],
			true
		);

		$whitelisted_payment_method = $this->gateway->get_payment_method_whitelist();
		$bypass_chip                = $this->gateway->get_bypass_chip();

		// Exclude razer_atome.
		$razer_ewallet_list = array( 'razer_grabpay', 'razer_maybankqr', 'razer_shopeepay', 'shopee_pay', 'razer_tng' );

		// Determine which bank type is needed for lazy loading.
		$bank_type = '';
		if ( is_array( $whitelisted_payment_method ) && 'yes' === $bypass_chip ) {
			$has_fpx   = in_array( 'fpx', $whitelisted_payment_method, true ) || in_array( 'fpx_b2b1', $whitelisted_payment_method, true );
			$has_razer = count( preg_grep( '/^(razer_|shopee_pay)/', $whitelisted_payment_method ) ) > 0;
			$has_card  = count( array_intersect( $whitelisted_payment_method, array( 'visa', 'mastercard', 'maestro' ) ) ) > 0;

			// Single-method cases: fpx, fpx_b2b1, razer, card (legacy 'fpx' / 'fpx_b2b1' / 'razer' / 'card' stand-alone flows).
			if ( 1 === count( $whitelisted_payment_method ) ) {
				if ( 'fpx' === $whitelisted_payment_method[0] ) {
					$bank_type = 'fpx_b2c';
				} elseif ( 'fpx_b2b1' === $whitelisted_payment_method[0] ) {
					$bank_type = 'fpx_b2b1';
				} elseif ( $has_razer && ! $has_card ) {
					$bank_type = 'razer';
				}
			}

			// Mixed cases: card + dropdown, or multiple dropdown methods -> unified.
			if ( '' === $bank_type ) {
				$has_dnqr       = in_array( 'duitnow_qr', $whitelisted_payment_method, true )
					|| in_array( 'dnqr', $whitelisted_payment_method, true );
				$has_crypto     = in_array( 'crypto_coin', $whitelisted_payment_method, true );
				$has_mpgs       = count( array_intersect( $whitelisted_payment_method, array( 'mpgs_google_pay', 'mpgs_apple_pay' ) ) ) > 0;
				$has_dropdown   = $has_fpx || $has_razer || $has_dnqr || $has_crypto || $has_mpgs;
				$dropdown_count = count( preg_grep( '/^(razer_|shopee_pay)/', $whitelisted_payment_method ) );
				if ( $has_fpx ) {
					++$dropdown_count;
				}
				if ( $has_dnqr ) {
					++$dropdown_count;
				}
				if ( $has_crypto ) {
					++$dropdown_count;
				}
				if ( $has_mpgs ) {
					++$dropdown_count;
				}
				if ( ( $has_dropdown && $has_card ) || $dropdown_count > 1 ) {
					$bank_type = 'unified';
				} elseif ( 0 === count( array_diff( $whitelisted_payment_method, $razer_ewallet_list ) ) ) {
					$bank_type = 'razer';
				} elseif ( $has_dnqr && 0 === count( array_diff( $whitelisted_payment_method, array( 'duitnow_qr', 'dnqr' ) ) ) ) {
					// DuitNow QR-only whitelist (e.g. Gateway 6): the method
					// is auto-selected; no bank list is needed.
					$bank_type = 'dnqr';
				} elseif ( $has_crypto && 1 === count( $whitelisted_payment_method ) ) {
					// Crypto-only whitelist: the method is auto-selected.
					$bank_type = 'crypto';
				} elseif ( $has_mpgs && 1 === count( $whitelisted_payment_method ) ) {
					// Google Pay / Apple Pay-only whitelist: auto-selected.
					$bank_type = 'mpgs';
				}
			}
		}

		// Determine logo base URL based on bank type.
		$logo_base_url = CHIP_WOOCOMMERCE_URL . 'assets/fpx_bank/';
		if ( 'razer' === $bank_type ) {
			$logo_base_url = CHIP_WOOCOMMERCE_URL . 'assets/razer_ewallet/';
		}

		// Provide API URL for lazy loading banks instead of embedding data.
		$localize_variable = array(
			'id'                => $this->name,
			'bank_type'         => $bank_type,
			'banks_api'         => ! empty( $bank_type ) ? rest_url( "chip/v1/banks/{$bank_type}/{$this->name}" ) : '',
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'logo_base_url'     => $logo_base_url,
			'fpx_logo_base_url' => CHIP_WOOCOMMERCE_URL . 'assets/fpx_bank/',
			'razer_logo_base_url' => CHIP_WOOCOMMERCE_URL . 'assets/razer_ewallet/',
			'card_logos_url'    => CHIP_WOOCOMMERCE_URL . 'assets/',
		);

		wp_localize_script( "wc-{$this->name}-blocks", 'gateway_' . $this->name, $localize_variable );

		return array( "wc-{$this->name}-blocks" );
	}

	/**
	 * Get payment method data.
	 *
	 * Data returned here is passed to the JavaScript client side via getSetting().
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		if ( ! $this->gateway ) {
			return array();
		}

		$pm_whitelist = $this->gateway->get_payment_method_whitelist();
		$bypass_chip  = $this->get_setting( 'bypass_chip' );
		$js_display   = '';

		// Card payment methods that support direct post.
		$card_methods       = array( 'visa', 'mastercard', 'maestro' );
		$razer_ewallet_list = array( 'razer_grabpay', 'razer_maybankqr', 'razer_shopeepay', 'shopee_pay', 'razer_tng' );

		if ( is_array( $pm_whitelist ) && 'yes' === $bypass_chip ) {
			$has_fpx   = in_array( 'fpx', $pm_whitelist, true ) || in_array( 'fpx_b2b1', $pm_whitelist, true );
			$has_razer = count( preg_grep( '/^(razer_|shopee_pay)/', $pm_whitelist ) ) > 0;
			$has_card  = count( array_intersect( $pm_whitelist, $card_methods ) ) > 0;

			// Single-method cases.
			if ( 1 === count( $pm_whitelist ) ) {
				if ( 'fpx' === $pm_whitelist[0] ) {
					$js_display = 'fpx';
				} elseif ( 'fpx_b2b1' === $pm_whitelist[0] ) {
					$js_display = 'fpx_b2b1';
				} elseif ( $has_razer && ! $has_card ) {
					$js_display = 'razer';
				} elseif ( $has_card && ! $has_fpx && ! $has_razer ) {
					$js_display = 'card';
				}
			}

			// Mixed cases.
			if ( '' === $js_display ) {
				$dropdown_count = count( preg_grep( '/^(razer_|shopee_pay)/', $pm_whitelist ) );
				if ( $has_fpx ) {
					++$dropdown_count;
				}
				$has_dnqr = in_array( 'duitnow_qr', $pm_whitelist, true ) || in_array( 'dnqr', $pm_whitelist, true );
				if ( $has_dnqr ) {
					++$dropdown_count;
				}
				$has_crypto = in_array( 'crypto_coin', $pm_whitelist, true );
				if ( $has_crypto ) {
					++$dropdown_count;
				}
				$has_mpgs = count( array_intersect( $pm_whitelist, array( 'mpgs_google_pay', 'mpgs_apple_pay' ) ) ) > 0;
				if ( $has_mpgs ) {
					++$dropdown_count;
				}
				$has_dropdown = $dropdown_count > 0;
				if ( ( $has_dropdown && $has_card ) || $dropdown_count > 1 ) {
					$js_display = 'unified';
				} elseif ( 0 === count( array_diff( $pm_whitelist, $razer_ewallet_list ) ) ) {
					$js_display = 'razer';
				} elseif ( $has_card && ! $has_dropdown ) {
					$js_display = 'card';
				} elseif ( $has_dnqr && 0 === count( array_diff( $pm_whitelist, array( 'duitnow_qr', 'dnqr' ) ) ) ) {
					// DuitNow QR-only whitelist (e.g. Gateway 6): no picker
					// needed — the method is auto-selected and submitted.
					$js_display = 'dnqr';
				} elseif ( $has_crypto && 1 === count( $pm_whitelist ) ) {
					// Crypto-only whitelist: auto-submit the method.
					$js_display = 'crypto';
				} elseif ( $has_mpgs && 1 === count( $pm_whitelist ) ) {
					// Google Pay / Apple Pay-only whitelist: auto-submit.
					$js_display = 'mpgs';
				}
			}
		}

		// Show save card checkbox if tokenization is supported and user is logged in.
		// WooCommerce Blocks handles the display of the built-in save card checkbox.
		$show_save_option = $this->gateway->supports( 'tokenization' ) && is_user_logged_in();

		$payment_method_data = array(
			'title'                => $this->get_setting( 'title' ),
			'description'          => $this->get_setting( 'description' ),
			'supports'             => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
			'method_name'          => $this->name,
			'saved_option'         => $this->gateway->supports( 'tokenization' ),
			'save_option'          => $show_save_option,
			'js_display'           => $js_display,
			'icon'                 => $this->gateway->icon,
			'supported_currencies' => array( 'MYR' ),
		);

		/**
		 * Filter the payment method data passed to WooCommerce Blocks.
		 *
		 * This filter allows plugins to modify the payment method data
		 * that is passed to the JavaScript client side via getSetting().
		 *
		 * @since 2.0.0
		 *
		 * @param array                      $payment_method_data The payment method data array.
		 * @param string                     $name                The payment method name/ID.
		 * @param Chip_Woocommerce_Gateway   $gateway             The gateway instance.
		 */
		return apply_filters( 'chip_blocks_payment_method_data', $payment_method_data, $this->name, $this->gateway );
	}
}
