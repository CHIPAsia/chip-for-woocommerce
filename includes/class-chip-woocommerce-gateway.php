<?php
/**
 * CHIP for WooCommerce Gateway
 *
 * Main payment gateway class for CHIP.
 *
 * @package CHIP for WooCommerce
 */

use Automattic\WooCommerce\Enums\OrderInternalStatus;

/**
 * WooCommerce Payment Gateway for CHIP.
 */
class Chip_Woocommerce_Gateway extends WC_Payment_Gateway {


	/**
	 * Gateway ID (wc_gateway_chip).
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Secret key for API authentication.
	 *
	 * @var string
	 */
	protected $secret_key;

	/**
	 * Brand ID for CHIP.
	 *
	 * @var string
	 */
	protected $brand_id;

	/**
	 * Due strict setting.
	 *
	 * @var string
	 */
	protected $due_strict;

	/**
	 * Due strict timing in minutes.
	 *
	 * @var int
	 */
	protected $due_strict_timing;

	/**
	 * Purchase timezone.
	 *
	 * @var string
	 */
	protected $purchase_time_zone;

	/**
	 * System URL scheme.
	 *
	 * @var string
	 */
	protected $system_url_scheme;

	/**
	 * Disable recurring support setting.
	 *
	 * @var string
	 */
	protected $disable_recurring_support;

	/**
	 * Payment method whitelist.
	 *
	 * @var array
	 */
	protected $payment_method_whitelist;

	/**
	 * Disable redirect setting.
	 *
	 * @var string
	 */
	protected $disable_redirect;

	/**
	 * Disable callback setting.
	 *
	 * @var string
	 */
	protected $disable_callback;

	/**
	 * Enable auto clear cart setting.
	 *
	 * @var string
	 */
	protected $enable_auto_clear_cart;

	/**
	 * Public key for verification.
	 *
	 * @var string
	 */
	protected $public_key;

	/**
	 * Available recurring payment method.
	 *
	 * @var string
	 */
	protected $available_recurring;

	/**
	 * Available payment methods list.
	 *
	 * @var array
	 */
	protected $available_payment_methods;

	/**
	 * Bypass CHIP payment page setting.
	 *
	 * @var string
	 */
	protected $bypass_chip;

	/**
	 * Debug mode setting.
	 *
	 * @var string
	 */
	protected $debug;

	/**
	 * Direct post URLs for current payments, keyed by gateway ID.
	 *
	 * Static property to persist across different gateway instances.
	 * Used to pass the URL from process_payment to process_payment_with_context
	 * to avoid database timing issues with order meta.
	 *
	 * @var array
	 */
	protected static $direct_post_urls = array();

	/**
	 * Enable additional charges setting.
	 *
	 * @var string
	 */
	protected $enable_additional_charges;

	/**
	 * Fixed charges amount in cents.
	 *
	 * @var int
	 */
	protected $fixed_charges;

	/**
	 * Percent charges amount.
	 *
	 * @var float
	 */
	protected $percent_charges;

	/**
	 * Cancel order flow setting.
	 *
	 * @var string
	 */
	protected $cancel_order_flow;

	/**
	 * Payment action setting (sale or authorize).
	 *
	 * @var string
	 */
	protected $payment_action;

	/**
	 * Email fallback setting.
	 *
	 * @var string
	 */
	protected $email_fallback;

	/**
	 * Cached API instance.
	 *
	 * @var Chip_Woocommerce_API
	 */
	protected $cached_api;

	/**
	 * Cached FPX API instance.
	 *
	 * @var Chip_Woocommerce_API_FPX
	 */
	protected $cached_fpx_api;

	/**
	 * Cached payment method.
	 *
	 * @var array
	 */
	protected $cached_payment_method;

	/**
	 * Unavailable FPX B2C bank codes.
	 *
	 * @var array
	 */
	protected $unavailable_fpx_banks = array();

	/**
	 * Unavailable FPX B2B1 bank codes.
	 *
	 * @var array
	 */
	protected $unavailable_fpx_b2b1_banks = array();

	/**
	 * Preferred payment type.
	 */
	const PREFERRED_TYPE = 'Online Banking';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_id();
		$this->init_icon();
		$this->init_title();
		$this->init_method_title();
		$this->init_method_description();
		$this->init_currency_check();
		$this->init_supports();
		$this->init_has_fields();

		// API credentials.
		$this->secret_key = $this->get_option( 'secret_key' );
		$this->brand_id   = $this->get_option( 'brand_id' );
		$this->public_key = $this->get_option( 'public_key' );

		// Payment behavior settings.
		$this->due_strict                = $this->get_option( 'due_strict', 'yes' );
		$this->due_strict_timing         = $this->get_option( 'due_strict_timing', 60 );
		$this->purchase_time_zone        = $this->get_option( 'purchase_time_zone', 'Asia/Kuala_Lumpur' );
		$this->system_url_scheme         = $this->get_option( 'system_url_scheme', 'https' );
		$this->disable_recurring_support = $this->get_option( 'disable_recurring_support' );
		$this->cancel_order_flow         = $this->get_option( 'cancel_order_flow' );
		$this->payment_action            = $this->get_option( 'payment_action', 'sale' );
		$this->enable_auto_clear_cart    = $this->get_option( 'enable_auto_clear_cart' );

		// Checkout experience settings.
		$this->description              = $this->get_option( 'description' );
		$this->bypass_chip              = $this->get_option( 'bypass_chip' );
		$this->payment_method_whitelist = $this->get_option( 'payment_method_whitelist' );
		$this->email_fallback           = $this->get_option( 'email_fallback' );

		// Payment method availability.
		$this->available_recurring       = $this->get_option( 'available_recurring_payment_method' );
		$this->available_payment_methods = $this->get_payment_method_list();

		// Additional charges settings.
		$this->enable_additional_charges = $this->get_option( 'enable_additional_charges' );
		$this->fixed_charges             = $this->get_option( 'fixed_charges', 100 );
		$this->percent_charges           = $this->get_option( 'percent_charges', 0 );

		// Troubleshooting settings.
		$this->disable_redirect = $this->get_option( 'disable_redirect' );
		$this->disable_callback = $this->get_option( 'disable_callback' );
		$this->debug            = $this->get_option( 'debug' );

		$this->init_form_fields();
		$this->init_settings();
		$this->init_one_time_gateway();

		if ( $this->get_option( 'title' ) ) {
			$this->title = $this->get_option( 'title' );
		}

		if ( $this->get_option( 'method_title' ) ) {
			$this->method_title = $this->get_option( 'method_title' );
		}

		$this->add_actions();
		$this->add_filters();
	}

	/**
	 * Initialize gateway ID.
	 *
	 * @return void
	 */
	protected function init_id() {
		$this->id = 'wc_gateway_chip';
	}

	/**
	 * Initialize gateway icon.
	 *
	 * @return void
	 */
	protected function init_icon() {
		$logo = $this->get_option( 'display_logo', 'logo' );

		$file_extension = 'png';
		$file_path      = plugin_dir_path( WC_CHIP_FILE ) . 'assets/' . $logo . '.png';
		if ( ! file_exists( $file_path ) ) {
			$file_extension = 'svg';
		}

		$this->icon = plugins_url( "assets/{$logo}.{$file_extension}", WC_CHIP_FILE );
		if ( has_filter( 'wc_' . $this->id . '_load_icon' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_load_icon', '2.0.0', 'chip_' . $this->id . '_load_icon' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$this->icon = apply_filters( 'wc_' . $this->id . '_load_icon', $this->icon );
		}
		$this->icon = apply_filters( 'chip_' . $this->id . '_load_icon', $this->icon );
	}

	/**
	 * Initialize gateway title.
	 *
	 * @return void
	 */
	protected function init_title() {
		$this->title = __( 'Online Banking (FPX)', 'chip-for-woocommerce' );
	}

	/**
	 * Initialize method title.
	 *
	 * @return void
	 */
	protected function init_method_title() {
		/* translators: %1$s: Payment type name */
		$this->method_title = sprintf( __( 'CHIP %1$s', 'chip-for-woocommerce' ), static::PREFERRED_TYPE );
	}

	/**
	 * Initialize method description.
	 *
	 * @return void
	 */
	protected function init_method_description() {
		/* translators: %1$s: Gateway title */
		$this->method_description = sprintf( __( 'CHIP %1$s', 'chip-for-woocommerce' ), $this->title );
	}

	/**
	 * Initialize currency check.
	 *
	 * @return void
	 */
	protected function init_currency_check() {
		$woocommerce_currency = get_woocommerce_currency();
		$supported_currencies = array( 'MYR' );
		if ( has_filter( 'wc_' . $this->id . '_supported_currencies' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_supported_currencies', '2.0.0', 'chip_' . $this->id . '_supported_currencies' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$supported_currencies = apply_filters( 'wc_' . $this->id . '_supported_currencies', $supported_currencies, $this );
		}
		$supported_currencies = apply_filters( 'chip_' . $this->id . '_supported_currencies', $supported_currencies, $this );

		if ( ! in_array( $woocommerce_currency, $supported_currencies, true ) ) {
			$this->enabled = 'no';
		}
	}

	/**
	 * Initialize gateway supports.
	 *
	 * @return void
	 */
	protected function init_supports() {
		$supports       = array( 'refunds', 'tokenization', 'subscriptions', 'subscription_cancellation', 'subscription_suspension', 'subscription_reactivation', 'subscription_amount_changes', 'subscription_date_changes', 'subscription_payment_method_change', 'subscription_payment_method_delayed_change', 'subscription_payment_method_change_customer', 'subscription_payment_method_change_admin', 'multiple_subscriptions', 'pre-orders' );
		$this->supports = array_merge( $this->supports, $supports );
	}

	/**
	 * Initialize has fields.
	 *
	 * @return void
	 */
	protected function init_has_fields() {
		$this->has_fields = true;
	}

	/**
	 * Initialize one-time gateway settings.
	 *
	 * @return void
	 */
	protected function init_one_time_gateway() {
		$one_time_gateway = false;

		if ( is_array( $this->payment_method_whitelist ) && ! empty( $this->payment_method_whitelist ) ) {
			foreach ( array( 'visa', 'mastercard', 'maestro' ) as $card_network ) {
				if ( in_array( $card_network, $this->payment_method_whitelist, true ) ) {
					$one_time_gateway = false;
					break;
				}
				$one_time_gateway = true;
			}
		}

		if ( $one_time_gateway || 'yes' === $this->disable_recurring_support ) {
			$this->supports = array( 'products', 'refunds' );
		}
	}

	/**
	 * Add action hooks.
	 *
	 * @return void
	 */
	public function add_actions() {
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'auto_charge' ), 10, 2 );
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'handle_callback' ) );
		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array( $this, 'change_failing_payment_method' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_payments' ) );

		add_action( 'woocommerce_subscription_change_payment_method_via_pay_shortcode', array( $this, 'handle_change_payment_method_shortcode' ), 10, 1 );

		add_action( 'init', array( $this, 'register_script' ) );

		// Admin scripts for logo preview.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// WooCommerce Blocks payment processing.
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', array( $this, 'process_payment_with_context' ), 10, 2 );
	}

	/**
	 * Process payment with context for WooCommerce Blocks.
	 *
	 * This hook allows us to modify the payment result for blocks checkout,
	 * specifically to add direct_post_url for card payments.
	 *
	 * Setting a status on PaymentResult will skip legacy payment processing,
	 * preventing the default GET redirect behavior.
	 *
	 * @param \Automattic\WooCommerce\StoreApi\Payments\PaymentContext $context Payment context.
	 * @param \Automattic\WooCommerce\StoreApi\Payments\PaymentResult  $result  Payment result.
	 * @return void
	 */
	public function process_payment_with_context( $context, &$result ) {
		// Only process for this gateway.
		if ( $context->payment_method !== $this->id ) {
			return;
		}

		// Check if bypass_chip is enabled and payment methods are card-only.
		if ( 'yes' !== $this->bypass_chip ) {
			return;
		}

		// Check if payment methods include card methods that support direct post.
		$card_methods = array( 'visa', 'mastercard', 'maestro', 'mpgs_google_pay', 'mpgs_apple_pay' );
		$pm_whitelist = $this->get_payment_method_whitelist();

		if ( ! is_array( $pm_whitelist ) || empty( $pm_whitelist ) ) {
			return;
		}

		// Check if all whitelisted methods are card methods.
		$is_card_only = true;
		foreach ( $pm_whitelist as $pm ) {
			if ( ! in_array( $pm, $card_methods, true ) ) {
				$is_card_only = false;
				break;
			}
		}

		if ( ! $is_card_only ) {
			return;
		}

		// Check if a saved token is being used.
		// In Blocks checkout, payment_data contains the token selection.
		$payment_data   = $context->payment_data;
		$token_key      = 'wc-' . $this->id . '-payment-token';
		$using_new_card = true;

		if ( isset( $payment_data[ $token_key ] ) && ! empty( $payment_data[ $token_key ] ) && 'new' !== $payment_data[ $token_key ] ) {
			$using_new_card = false;
		}

		// Also check the 'token' key used by some WooCommerce versions.
		if ( isset( $payment_data['token'] ) && ! empty( $payment_data['token'] ) && 'new' !== $payment_data['token'] ) {
			$using_new_card = false;
		}

		// If using saved token, let legacy process_payment handle everything.
		// WooCommerce Blocks will redirect to the checkout_url returned.
		if ( ! $using_new_card ) {
			return;
		}

		// Call process_payment to create the CHIP payment.
		// This hook fires BEFORE legacy process_payment, so we need to call it ourselves.
		$order_id       = $context->order->get_id();
		$payment_result = $this->process_payment( $order_id );

		if ( 'success' !== $payment_result['result'] ) {
			return;
		}

		// Get the direct_post_url from the static property (set during process_payment).
		$direct_post_url = isset( self::$direct_post_urls[ $this->id ] ) ? self::$direct_post_urls[ $this->id ] : '';

		if ( ! empty( $direct_post_url ) ) {
			// IMPORTANT: Setting status will skip legacy payment processing.
			$result->set_status( 'success' );

			// New card entry - redirect to CHIP direct post URL via JS.
			$result->set_payment_details(
				array( 'chip_direct_post_url' => esc_url_raw( $direct_post_url ) )
			);

			// Clear redirect URL so WooCommerce Blocks doesn't redirect.
			// JS will handle the POST to direct_post_url.
			$result->set_redirect_url( '' );

			// Clear the property after use.
			unset( self::$direct_post_urls[ $this->id ] );
		}
		// If no direct_post_url (shouldn't happen for new card), let legacy processing handle it.
	}

	/**
	 * Add filter hooks.
	 *
	 * @return void
	 */
	public function add_filters() {
		add_filter( 'woocommerce_subscriptions_update_payment_via_pay_shortcode', array( $this, 'maybe_dont_update_payment_method' ), 10, 3 );
		add_filter( 'woocommerce_payment_gateway_get_new_payment_method_option_html', array( $this, 'maybe_hide_add_new_payment_method' ), 10, 2 );
	}

	/**
	 * Get gateway icon HTML.
	 *
	 * @return string
	 */
	public function get_icon() {
		$style = 'max-height: 25px; width: auto';

		if ( in_array( $this->get_option( 'display_logo', 'logo' ), array( 'paywithchip_all', 'paywithchip_fpx' ), true ) ) {
			$style = '';
		}

		if ( has_filter( 'wc_' . $this->id . '_get_icon_style' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_get_icon_style', '2.0.0', 'chip_' . $this->id . '_get_icon_style' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$style = apply_filters( 'wc_' . $this->id . '_get_icon_style', $style, $this );
		}
		$style = apply_filters( 'chip_' . $this->id . '_get_icon_style', $style, $this );

		$icon = '<img class="chip-for-woocommerce-' . esc_attr( $this->id ) . '" src="' . esc_url( WC_HTTPS::force_https_url( $this->icon ) ) . '" alt="' . esc_attr( $this->get_title() ) . '" style="' . esc_attr( $style ) . '" />';

		if ( has_filter( 'woocommerce_gateway_icon' ) ) {
			_deprecated_hook( 'woocommerce_gateway_icon', '2.0.0', 'chip_gateway_icon' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$icon = apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
		}
		return apply_filters( 'chip_gateway_icon', $icon, $this->id );
	}

	/**
	 * Get CHIP API instance.
	 *
	 * @return Chip_Woocommerce_API
	 */
	public function api() {
		if ( ! $this->cached_api ) {
			$this->cached_api = new Chip_Woocommerce_API(
				$this->secret_key,
				$this->brand_id,
				new Chip_Woocommerce_Logger(),
				$this->debug
			);
		}

		return $this->cached_api;
	}

	/**
	 * Get FPX API instance.
	 *
	 * @return Chip_Woocommerce_API_FPX
	 */
	public function fpx_api() {
		if ( ! $this->cached_fpx_api ) {
			$this->cached_fpx_api = new Chip_Woocommerce_API_FPX(
				new Chip_Woocommerce_Logger(),
				$this->debug
			);
		}

		return $this->cached_fpx_api;
	}

	/**
	 * Log order info message.
	 *
	 * @param string   $msg   Message to log.
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	private function log_order_info( $msg, $order ) {
		$this->api()->log_info( $msg . ': ' . $order->get_order_number() );
	}

	/**
	 * Handle callback from CHIP.
	 *
	 * @return void
	 */
	public function handle_callback() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['tokenization'] ) && 'yes' === sanitize_text_field( wp_unslash( $_GET['tokenization'] ) ) ) {
			$this->handle_callback_token();
		} elseif ( isset( $_GET['process_payment_method_change'] ) && 'yes' === sanitize_text_field( wp_unslash( $_GET['process_payment_method_change'] ) ) ) {
			$this->handle_payment_method_change();
		} else {
			$this->handle_callback_order();
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Handle tokenization callback.
	 *
	 * @return void
	 */
	public function handle_callback_token() {
		$payment_id = WC()->session->get( 'chip_preauthorize' );

		if ( ! $payment_id && isset( $_SERVER['HTTP_X_SIGNATURE'] ) ) {
			$content   = file_get_contents( 'php://input' );
			$signature = isset( $_SERVER['HTTP_X_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_SIGNATURE'] ) ) : '';

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( 1 !== openssl_verify( $content, base64_decode( $signature ), $this->get_public_key(), 'sha256WithRSAEncryption' ) ) {
				$message = __( 'Success callback failed to be processed due to failure in verification.', 'chip-for-woocommerce' );
				exit( esc_html( $message ) );
			}

			$payment    = json_decode( $content, true );
			$payment_id = array_key_exists( 'id', $payment ) ? sanitize_key( $payment['id'] ) : '';
		} elseif ( $payment_id ) {
			$payment = $this->api()->get_payment( $payment_id );
		} else {
			exit( esc_html__( 'Unexpected response', 'chip-for-woocommerce' ) );
		}

		if ( 'preauthorized' !== $payment['status'] ) {
			wc_add_notice( sprintf( '%1$s %2$s', __( 'Unable to add payment method to your account.', 'chip-for-woocommerce' ), wc_print_r( $payment['transaction_data']['attempts'][0]['error'], true ) ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
			exit;
		}

		$this->get_lock( $payment_id );

		if ( $this->store_recurring_token( $payment, $payment['reference'] ) ) {
			wc_add_notice( __( 'Payment method successfully added.', 'chip-for-woocommerce' ) );
		} else {
			wc_add_notice( __( 'Unable to add payment method to your account.', 'chip-for-woocommerce' ), 'error' );
		}

		$this->release_lock( $payment_id );

		wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
		exit;
	}

	/**
	 * Handle order callback from CHIP payment gateway.
	 *
	 * This is an external callback endpoint called by CHIP servers after payment.
	 * Nonce verification is not possible as requests originate from external service.
	 * Security is handled via X-Signature header verification using RSA public key.
	 *
	 * @return void
	 */
	public function handle_callback_order() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Callback from external payment gateway, verified via X-Signature.
		if ( ! isset( $_GET['id'] ) ) {
			exit( 'Missing order ID' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Callback from external payment gateway, verified via X-Signature.
		$order_id = intval( $_GET['id'] );

		$this->api()->log_info( 'received callback for order id: ' . $order_id );

		$this->get_lock( $order_id );

		// Clear post cache to ensure fresh data is retrieved when object cache is configured.
		// Note: This covers legacy order storage (posts table). For HPOS (High-Performance Order
		// Storage), there is no equivalent cache clearing function available at this moment.
		// HPOS may use its own caching mechanism that is not publicly accessible for clearing.
		clean_post_cache( $order_id );

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			$this->release_lock( $order_id );
			exit( 'Order not found' );
		}

		$this->log_order_info( 'received success callback', $order );

		// Use the order's payment method to get the correct meta key (handles legacy callbacks).
		$order_payment_method = $order->get_payment_method();
		$payment              = $order->get_meta( '_' . $order_payment_method . '_purchase', true );
		$payment_id           = isset( $payment['id'] ) ? $payment['id'] : '';

		if ( isset( $_SERVER['HTTP_X_SIGNATURE'] ) ) {
			$content = file_get_contents( 'php://input' );

			$signature = isset( $_SERVER['HTTP_X_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_SIGNATURE'] ) ) : '';
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required to decode RSA signature from payment gateway for verification.
			if ( openssl_verify( $content, base64_decode( $signature ), $this->get_public_key(), 'sha256WithRSAEncryption' ) !== 1 ) {
				$message = __( 'Success callback failed to be processed due to failure in verification.', 'chip-for-woocommerce' );
				$this->log_order_info( $message, $order );
				exit( esc_html( $message ) );
			}

			$payment    = json_decode( $content, true );
			$payment_id = array_key_exists( 'id', $payment ) ? sanitize_key( $payment['id'] ) : '';
		} elseif ( $payment_id ) {
			$payment = $this->api()->get_payment( $payment_id );
		} else {
			exit( esc_html__( 'Unexpected response', 'chip-for-woocommerce' ) );
		}

		if ( has_action( 'wc_' . $this->id . '_before_handle_callback_order' ) || has_action( 'chip_' . $this->id . '_before_handle_callback_order' ) ) {
			if ( has_action( 'wc_' . $this->id . '_before_handle_callback_order' ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
				_deprecated_hook( 'wc_' . $this->id . '_before_handle_callback_order', '2.0.0', 'chip_' . $this->id . '_before_handle_callback_order' );
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
				do_action( 'wc_' . $this->id . '_before_handle_callback_order', $order, $payment, $this );
			}
			do_action( 'chip_' . $this->id . '_before_handle_callback_order', $order, $payment, $this );

			$payment = $this->api()->get_payment( $payment_id );
		}

		if ( 'paid' === $payment['status'] ) {
			if ( $this->order_contains_pre_order( $order ) && $this->order_requires_payment_tokenization( $order ) ) {
				if ( $payment['is_recurring_token'] || ! empty( $payment['recurring_token'] ) ) {
					$token = $this->store_recurring_token( $payment, $order->get_user_id() );
					if ( $token ) {
						$this->add_payment_token( $order->get_id(), $token );
					}
				}

				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
			} elseif ( ! $order->is_paid() ) {
				$this->payment_complete( $order, $payment );
			}

			WC()->cart->empty_cart();

			$this->log_order_info( 'payment processed', $order );
		} elseif ( 'preauthorized' === $payment['status'] ) {
			// Handle preauthorized payments ($0 authorization for pre-orders or card verification).
			if ( $payment['is_recurring_token'] || ! empty( $payment['recurring_token'] ) ) {
				$token = $this->store_recurring_token( $payment, $order->get_user_id() );
				if ( $token ) {
					$this->add_payment_token( $order->get_id(), $token );
				}
			}

			// This is a pre-order with $0 authorization.
			if ( $this->order_contains_pre_order( $order ) && $this->order_requires_payment_tokenization( $order ) ) {
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
			}

			WC()->cart->empty_cart();

			$this->log_order_info( 'payment preauthorized ($0 authorization)', $order );
		} elseif ( 'hold' === $payment['status'] ) {
			// Handle hold payments (delayed capture with actual payment amount).
			if ( $payment['is_recurring_token'] || ! empty( $payment['recurring_token'] ) ) {
				$token = $this->store_recurring_token( $payment, $order->get_user_id() );
				if ( $token ) {
					$this->add_payment_token( $order->get_id(), $token );
				}
			}

			// Check if this is a pre-order with delayed capture.
			if ( $this->order_contains_pre_order( $order ) ) {
				$order->update_meta_data( '_' . $order_payment_method . '_purchase', $payment );
				$order->update_meta_data( '_chip_can_void', 'yes' );
				$order->update_meta_data( '_chip_hold_timestamp', time() );
				$order->set_transaction_id( $payment['id'] );
				$order->save();
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
			} elseif ( ! $order->has_status( 'on-hold' ) && ! $order->is_paid() ) {
				// Set order to On Hold for hold payments awaiting capture.
				$order->update_meta_data( '_' . $order_payment_method . '_purchase', $payment );
				$order->update_meta_data( '_chip_can_void', 'yes' );
				$order->update_meta_data( '_chip_hold_timestamp', time() );
				$order->set_transaction_id( $payment['id'] );
				/* translators: %s: Transaction ID */
				$order->add_order_note( sprintf( __( 'Payment authorized. Transaction ID: %s. Awaiting capture.', 'chip-for-woocommerce' ), $payment['id'] ) );
				$order->update_status( OrderInternalStatus::ON_HOLD, __( 'Payment authorized, awaiting capture.', 'chip-for-woocommerce' ) );
				$order->save();
			}

			WC()->cart->empty_cart();

			$this->log_order_info( 'payment on hold, awaiting capture', $order );
		} elseif ( ! $order->is_paid() ) {
			$order->update_status( 'wc-failed' );
			$this->log_order_info( 'payment not successful', $order );
		}

		$this->release_lock( $order_id );

		if ( has_action( 'wc_' . $this->id . '_after_handle_callback_order' ) || has_action( 'chip_' . $this->id . '_after_handle_callback_order' ) ) {
			if ( has_action( 'wc_' . $this->id . '_after_handle_callback_order' ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
				_deprecated_hook( 'wc_' . $this->id . '_after_handle_callback_order', '2.0.0', 'chip_' . $this->id . '_after_handle_callback_order' );
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
				do_action( 'wc_' . $this->id . '_after_handle_callback_order', $order, $payment, $this );
			}
			do_action( 'chip_' . $this->id . '_after_handle_callback_order', $order, $payment, $this );
		}

		$redirect_url = $this->get_return_url( $order );

		if ( 'yes' === $this->cancel_order_flow && ! $order->is_paid() && ! $order->has_status( array( 'pre-ordered', 'on-hold' ) ) ) {
			$redirect_url = esc_url_raw( $order->get_cancel_order_url_raw() );
		}

		if ( has_filter( 'wc_' . $this->id . '_order_redirect_url' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_order_redirect_url', '2.0.0', 'chip_' . $this->id . '_order_redirect_url' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$redirect_url = apply_filters( 'wc_' . $this->id . '_order_redirect_url', $redirect_url, $this );
		}
		$redirect_url = apply_filters( 'chip_' . $this->id . '_order_redirect_url', $redirect_url, $this );

		wp_safe_redirect( $redirect_url );

		exit;
	}

	/**
	 * Initialize form fields for gateway settings.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		// ══════════════════════════════════════════════════════════════════════
		// SECTION 1: GENERAL
		// Basic gateway setup and display settings.
		// ══════════════════════════════════════════════════════════════════════
		$this->form_fields['enabled'] = array(
			'title'   => __( 'Enable/Disable', 'chip-for-woocommerce' ),
			'label'   => sprintf( '%1$s %2$s', __( 'Enable', 'chip-for-woocommerce' ), $this->method_title ),
			'type'    => 'checkbox',
			'default' => 'no',
		);

		$this->form_fields['title'] = array(
			'title'       => __( 'Title', 'chip-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'chip-for-woocommerce' ),
			'default'     => $this->title,
		);

		$this->form_fields['method_title'] = array(
			'title'       => __( 'Method Title', 'chip-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the title in WooCommerce Admin.', 'chip-for-woocommerce' ),
			'default'     => $this->method_title,
		);

		$this->form_fields['description'] = array(
			'title'       => __( 'Description', 'chip-for-woocommerce' ),
			'type'        => 'textarea',
			'description' => __( 'This controls the description which the user sees during checkout.', 'chip-for-woocommerce' ),
			'default'     => __( 'Pay with Online Banking (FPX)', 'chip-for-woocommerce' ),
		);

		// ══════════════════════════════════════════════════════════════════════
		// SECTION 2: API CONFIGURATION
		// CHIP API credentials and public key.
		// ══════════════════════════════════════════════════════════════════════
		$this->form_fields['api_configuration'] = array(
			'title'       => __( 'API Configuration', 'chip-for-woocommerce' ),
			'type'        => 'title',
			'description' => __( 'Enter your CHIP API credentials to connect with your account.', 'chip-for-woocommerce' ),
		);

		// Get existing configurations from other gateways.
		$existing_configs = $this->get_existing_api_configurations();
		if ( ! empty( $existing_configs ) ) {
			$config_options  = array( '' => __( '— Select existing configuration —', 'chip-for-woocommerce' ) );
			$config_options += $existing_configs;

			$this->form_fields['copy_configuration'] = array(
				'title'       => __( 'Copy from Existing', 'chip-for-woocommerce' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select chip-copy-config-select',
				'description' => __( 'Select an existing gateway configuration to copy the API credentials. This will populate the Brand ID and Secret Key fields below.', 'chip-for-woocommerce' ),
				'options'     => $config_options,
				'default'     => '',
			);
		}

		// Check if Brand ID is valid (stored on save).
		$brand_id_valid       = $this->get_option( 'brand_id_valid' );
		$current_brand_id     = $this->get_option( 'brand_id' );
		$brand_id_description = __( 'Brand ID can be obtained from CHIP Collect Dashboard >> Developers >> Brands.', 'chip-for-woocommerce' );

		if ( 'yes' === $brand_id_valid && ! empty( $current_brand_id ) ) {
			$brand_id_description .= '<br><span style="color: #46b450; font-weight: bold;">&#10004; ' . esc_html__( 'Brand ID verified', 'chip-for-woocommerce' ) . '</span>';
		} elseif ( 'no' === $brand_id_valid && ! empty( $current_brand_id ) ) {
			$brand_id_description .= '<br><span style="color: #dc3232; font-weight: bold;">&#10006; ' . esc_html__( 'Brand ID invalid - please check your Brand ID', 'chip-for-woocommerce' ) . '</span>';
		}

		$this->form_fields['brand_id'] = array(
			'title'             => __( 'Brand ID', 'chip-for-woocommerce' ),
			'type'              => 'text',
			'description'       => $brand_id_description,
			'sanitize_callback' => function ( $value ) {
				$value = trim( $value );
				$value = str_replace( ' ', '', $value );
				return $value;
			},
		);

		// Check if API credentials are valid.
		$current_public_key = $this->get_option( 'public_key' );
		$is_api_valid       = $this->is_valid_public_key( $current_public_key );

		$secret_key_description = __( 'Secret Key can be obtained from CHIP Collect Dashboard >> Developers >> Keys.', 'chip-for-woocommerce' );
		if ( $is_api_valid ) {
			$secret_key_description .= '<br><span style="color: #46b450; font-weight: bold;">&#10004; ' . esc_html__( 'API credentials verified', 'chip-for-woocommerce' ) . '</span>';
		} elseif ( ! empty( $this->get_option( 'secret_key' ) ) && ! empty( $this->get_option( 'brand_id' ) ) ) {
			$secret_key_description .= '<br><span style="color: #dc3232; font-weight: bold;">&#10006; ' . esc_html__( 'API credentials invalid - please check your Secret Key', 'chip-for-woocommerce' ) . '</span>';
		}

		$this->form_fields['secret_key'] = array(
			'title'             => __( 'Secret Key', 'chip-for-woocommerce' ),
			'type'              => 'text',
			'description'       => $secret_key_description,
			'sanitize_callback' => function ( $value ) {
				$value = trim( $value );
				$value = str_replace( ' ', '', $value );
				return $value;
			},
		);

		$this->form_fields['public_key'] = array(
			'title'       => __( 'Public Key', 'chip-for-woocommerce' ),
			'type'        => 'textarea',
			'description' => __( 'Public key for validating callback will be auto-filled upon successful configuration.', 'chip-for-woocommerce' ),
			'disabled'    => true,
		);

		// ══════════════════════════════════════════════════════════════════════
		// SECTION 3: CHECKOUT EXPERIENCE
		// Settings that affect the customer checkout experience.
		// ══════════════════════════════════════════════════════════════════════
		$this->form_fields['checkout_experience'] = array(
			'title'       => __( 'Checkout Experience', 'chip-for-woocommerce' ),
			'type'        => 'title',
			'description' => __( 'Configure how the payment gateway appears and behaves during checkout.', 'chip-for-woocommerce' ),
		);

		$this->form_fields['display_logo'] = array(
			'title'       => __( 'Display Logo', 'chip-for-woocommerce' ),
			'type'        => 'select',
			'class'       => 'wc-enhanced-select chip-display-logo-select',
			'description' => __( 'Select which logo appears on the checkout page.', 'chip-for-woocommerce' )
				. '<div id="chip-logo-preview-' . esc_attr( $this->id ) . '" style="margin-top: 10px; padding: 15px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 4px; text-align: center; min-height: 60px;">'
				. '<img id="chip-logo-preview-img-' . esc_attr( $this->id ) . '" src="" alt="' . esc_attr__( 'Logo Preview', 'chip-for-woocommerce' ) . '" style="max-height: 50px; max-width: 100%;" />'
				. '</div>',
			'default'     => 'fpx_only',
			'options'     => array(
				// Combined Logos (Shows CHIP branding with payment method).
				__( 'Combined Logos', 'chip-for-woocommerce' ) => array(
					'logo'               => __( 'CHIP Logo — CHIP branding only', 'chip-for-woocommerce' ),
					'fpx'                => __( 'FPX B2C — FPX logo with CHIP branding', 'chip-for-woocommerce' ),
					'fpx_b2b1'           => __( 'FPX B2B1 — Corporate FPX with CHIP branding', 'chip-for-woocommerce' ),
					'ewallet'            => __( 'E-Wallet — E-Wallet logos with CHIP branding', 'chip-for-woocommerce' ),
					'card'               => __( 'Card — Card logos with CHIP branding', 'chip-for-woocommerce' ),
					'card_international' => __( 'Card with Maestro — Includes Maestro with CHIP branding', 'chip-for-woocommerce' ),
					'duitnow'            => __( 'DuitNow QR — DuitNow logo with CHIP branding', 'chip-for-woocommerce' ),
					'paywithchip_all'    => __( 'Pay with CHIP (All) — All methods with CHIP branding', 'chip-for-woocommerce' ),
					'paywithchip_fpx'    => __( 'Pay with CHIP (FPX) — FPX with CHIP branding', 'chip-for-woocommerce' ),
				),
				// Standalone Logos (Payment method logo only, no CHIP branding).
				__( 'Standalone Logos (No CHIP Branding)', 'chip-for-woocommerce' ) => array(
					'fpx_only'                => __( 'FPX Only — FPX logo without CHIP branding', 'chip-for-woocommerce' ),
					'ewallet_only'            => __( 'E-Wallet Only — E-Wallet logo without CHIP branding', 'chip-for-woocommerce' ),
					'card_only'               => __( 'Card Only — Card logo without CHIP branding', 'chip-for-woocommerce' ),
					'card_international_only' => __( 'Card with Maestro Only — Includes Maestro, no CHIP branding', 'chip-for-woocommerce' ),
					'duitnow_only'            => __( 'DuitNow QR Only — DuitNow logo without CHIP branding', 'chip-for-woocommerce' ),
				),
			),
		);

		$this->form_fields['bypass_chip'] = array(
			'title'       => __( 'Bypass CHIP Payment Page', 'chip-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Enable to skip the CHIP payment page and show payment options directly on checkout.', 'chip-for-woocommerce' ),
			'default'     => 'yes',
		);

		$this->form_fields['payment_method_whitelist'] = array(
			'title'       => __( 'Payment Method Whitelist', 'chip-for-woocommerce' ),
			'type'        => 'multiselect',
			'class'       => 'wc-enhanced-select',
			'description' => __( 'Select which payment methods to allow. Leave empty to allow all available methods.', 'chip-for-woocommerce' ),
			'default'     => array( 'fpx' ),
			'options'     => $this->available_payment_methods,
			'disabled'    => empty( $this->available_payment_methods ),
		);

		$this->form_fields['email_fallback'] = array(
			'title'       => __( 'Email Fallback', 'chip-for-woocommerce' ),
			'type'        => 'email',
			'description' => __( 'Fallback email address used when customer email is not available. <strong>Not required</strong> if default WooCommerce checkout behavior is preserved (email field is mandatory).', 'chip-for-woocommerce' ),
			'placeholder' => 'merchant@gmail.com',
		);

		// ══════════════════════════════════════════════════════════════════════
		// SECTION 4: PAYMENT BEHAVIOR
		// Settings that control payment processing behavior.
		// ══════════════════════════════════════════════════════════════════════
		$this->form_fields['payment_behavior'] = array(
			'title'       => __( 'Payment Behavior', 'chip-for-woocommerce' ),
			'type'        => 'title',
			'description' => __( 'Configure payment timing, redirects, and order handling.', 'chip-for-woocommerce' ),
		);

		$this->form_fields['due_strict'] = array(
			'title'       => __( 'Due Strict', 'chip-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Block payments after the due time has passed.', 'chip-for-woocommerce' ),
			'default'     => 'yes',
		);

		$this->form_fields['due_strict_timing'] = array(
			'title'       => __( 'Due Strict Timing (minutes)', 'chip-for-woocommerce' ),
			'type'        => 'number',
			/* translators: %1$s: Default hold stock minutes value */
			'description' => sprintf( __( 'Payment expiry time in minutes. Defaults to WooCommerce hold stock setting: <code>%1$s</code>. Only applies when Due Strict is enabled.', 'chip-for-woocommerce' ), get_option( 'woocommerce_hold_stock_minutes', '60' ) ),
			'default'     => get_option( 'woocommerce_hold_stock_minutes', '60' ),
		);

		$this->form_fields['purchase_time_zone'] = array(
			'title'       => __( 'Purchase Time Zone', 'chip-for-woocommerce' ),
			'type'        => 'select',
			'class'       => 'wc-enhanced-select',
			'description' => __( 'Time zone displayed on the receipt page.', 'chip-for-woocommerce' ),
			'default'     => 'Asia/Kuala_Lumpur',
			'options'     => $this->get_timezone_list(),
		);

		$this->form_fields['system_url_scheme'] = array(
			'title'       => __( 'System URL Scheme', 'chip-for-woocommerce' ),
			'type'        => 'select',
			'class'       => 'wc-enhanced-select',
			'description' => __( 'Choose HTTPS if you experience payment status update issues due to HTTP to HTTPS redirection.', 'chip-for-woocommerce' ),
			'default'     => 'https',
			'options'     => array(
				'https'   => __( 'HTTPS', 'chip-for-woocommerce' ),
				'default' => __( 'System Default', 'chip-for-woocommerce' ),
			),
		);

		$this->form_fields['cancel_order_flow'] = array(
			'title'       => __( 'Cancel Order Flow', 'chip-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Redirect customers to the cancel order page for failed or cancelled payments.', 'chip-for-woocommerce' ),
			'default'     => 'no',
		);

		$this->form_fields['disable_recurring_support'] = array(
			'title'       => __( 'Disable Card Recurring Support', 'chip-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Disable saved card functionality. Applies to <code>Visa</code>, <code>Mastercard</code>, and <code>Maestro</code>.', 'chip-for-woocommerce' ),
			'default'     => 'no',
		);

		// Payment action field - only available for card-only whitelists.
		$card_only_whitelist    = $this->is_card_only_whitelist();
		$payment_action_options = array(
			'sale'      => __( 'Sale (Immediate Capture)', 'chip-for-woocommerce' ),
			'authorize' => __( 'Authorize (Delayed Capture)', 'chip-for-woocommerce' ),
		);

		$this->form_fields['payment_action'] = array(
			'title'       => __( 'Payment Action', 'chip-for-woocommerce' ),
			'type'        => 'select',
			'class'       => 'wc-enhanced-select',
			'description' => $card_only_whitelist
				? __( 'Choose whether to capture payment immediately (Sale) or authorize first and capture later (Authorize). Authorize allows you to capture or void the payment from the order page.', 'chip-for-woocommerce' )
					. '<br><br><strong style="color: #d63638;">' . esc_html__( 'Important Notes for Authorize:', 'chip-for-woocommerce' ) . '</strong>'
					. '<ul style="margin-top: 5px; margin-bottom: 0;">'
					. '<li>' . esc_html__( 'You will NOT receive settlement until you complete the order (update status to Completed) or manually capture the payment from the order page.', 'chip-for-woocommerce' ) . '</li>'
					. '<li>' . esc_html__( 'Authorized payments must be captured within 30 days. After 30 days, the authorization expires and cannot be captured.', 'chip-for-woocommerce' ) . '</li>'
					. '</ul>'
				: __( 'Payment Action is only supported when Payment Method Whitelist is set to card methods only. Supported cards: <code>Visa</code>, <code>Mastercard</code>, <code>Maestro</code>.', 'chip-for-woocommerce' ),
			'default'     => 'sale',
			'options'     => $payment_action_options,
			'disabled'    => ! $card_only_whitelist,
		);

		$this->form_fields['enable_auto_clear_cart'] = array(
			'title'   => __( 'Auto Clear Cart', 'chip-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Clear cart when customer proceeds to checkout', 'chip-for-woocommerce' ),
			'default' => 'no',
		);

		// ══════════════════════════════════════════════════════════════════════
		// SECTION 5: ADDITIONAL CHARGES
		// Configure additional fees applied at checkout.
		// ══════════════════════════════════════════════════════════════════════
		$this->form_fields['additional_charges'] = array(
			'title'       => __( 'Additional Charges', 'chip-for-woocommerce' ),
			'type'        => 'title',
			'description' => __( 'Add extra fees to orders at checkout. Does not apply to WooCommerce Pre-order fees.', 'chip-for-woocommerce' ),
		);

		$this->form_fields['enable_additional_charges'] = array(
			'title'       => __( 'Enable Additional Charges', 'chip-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Enable to apply additional charges to orders.', 'chip-for-woocommerce' ),
		);

		$this->form_fields['fixed_charges'] = array(
			'title'       => __( 'Fixed Charges (cents)', 'chip-for-woocommerce' ),
			'type'        => 'number',
			'description' => __( 'Fixed amount in cents to add to each order. Default: <code>100</code> (RM 1.00).', 'chip-for-woocommerce' ),
			'default'     => '100',
		);

		$this->form_fields['percent_charges'] = array(
			'title'       => __( 'Percentage Charges (%)', 'chip-for-woocommerce' ),
			'type'        => 'number',
			'description' => __( 'Percentage of order total to add as a fee. Enter <code>100</code> for 1%. Default: <code>0</code>.', 'chip-for-woocommerce' ),
			'default'     => '0',
		);

		// ══════════════════════════════════════════════════════════════════════
		// SECTION 6: TROUBLESHOOTING
		// Debug and diagnostic options.
		// ══════════════════════════════════════════════════════════════════════
		$this->form_fields['troubleshooting'] = array(
			'title'       => __( 'Troubleshooting', 'chip-for-woocommerce' ),
			'type'        => 'title',
			'description' => __( 'Diagnostic options for debugging payment issues.', 'chip-for-woocommerce' ),
		);

		$this->form_fields['disable_redirect'] = array(
			'title'       => __( 'Disable Redirect', 'chip-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Disable automatic redirect after payment. Use for testing only.', 'chip-for-woocommerce' ),
		);

		$this->form_fields['disable_callback'] = array(
			'title'       => __( 'Disable Callback', 'chip-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Disable payment callback processing. Use for testing only.', 'chip-for-woocommerce' ),
		);

		$this->form_fields['debug'] = array(
			'title'       => __( 'Debug Log', 'chip-for-woocommerce' ),
			'type'        => 'checkbox',
			'label'       => __( 'Enable logging', 'chip-for-woocommerce' ),
			'default'     => 'no',
			/* translators: %s: Log file URL */
			'description' => sprintf( __( 'Log payment events to <code>%s</code>.', 'chip-for-woocommerce' ), esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&source=chip-for-woocommerce' ) ) ),
		);
	}

	/**
	 * Get list of timezones.
	 *
	 * @return array
	 */
	private function get_timezone_list() {
		$list_time_zones = DateTimeZone::listIdentifiers( DateTimeZone::ALL );

		$formatted_time_zones = array();
		foreach ( $list_time_zones as $mtz ) {
			$formatted_time_zones[ $mtz ] = str_replace( '_', ' ', $mtz );
		}

		return $formatted_time_zones;
	}

	/**
	 * Check if payment method whitelist contains only card methods.
	 *
	 * Payment action (authorize/sale) is only available when the whitelist
	 * contains only Visa, Mastercard, and/or Maestro.
	 *
	 * @return bool True if whitelist contains only card methods.
	 */
	private function is_card_only_whitelist() {
		$allowed_card_methods = array( 'visa', 'mastercard', 'maestro' );
		$whitelist            = $this->payment_method_whitelist;

		// If whitelist is empty or not an array, return false.
		if ( empty( $whitelist ) || ! is_array( $whitelist ) ) {
			return false;
		}

		// Check if all items in whitelist are allowed card methods.
		foreach ( $whitelist as $method ) {
			if ( ! in_array( $method, $allowed_card_methods, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get logo URLs for display logo preview.
	 *
	 * @return array Associative array of logo key => URL.
	 */
	private function get_logo_urls() {
		$base_url = WC_CHIP_URL . 'assets/';

		return array(
			'logo'                    => $base_url . 'logo.png',
			'fpx'                     => $base_url . 'fpx.png',
			'fpx_b2b1'                => $base_url . 'fpx_b2b1.png',
			'ewallet'                 => $base_url . 'ewallet.png',
			'card'                    => $base_url . 'card.png',
			'fpx_only'                => $base_url . 'fpx_only.png',
			'ewallet_only'            => $base_url . 'ewallet_only.png',
			'card_only'               => $base_url . 'card_only.png',
			'card_international'      => $base_url . 'card_international.png',
			'card_international_only' => $base_url . 'card_international_only.png',
			'paywithchip_all'         => $base_url . 'paywithchip_all.png',
			'paywithchip_fpx'         => $base_url . 'paywithchip_fpx.png',
			'duitnow'                 => $base_url . 'duitnow.svg',
			'duitnow_only'            => $base_url . 'duitnow_only.svg',
		);
	}

	/**
	 * Output payment fields on checkout.
	 *
	 * @return void
	 */
	public function payment_fields() {
		if ( has_action( 'wc_' . $this->id . '_payment_fields' ) || has_action( 'chip_' . $this->id . '_payment_fields' ) ) {
			if ( has_action( 'wc_' . $this->id . '_payment_fields' ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
				_deprecated_hook( 'wc_' . $this->id . '_payment_fields', '2.0.0', 'chip_' . $this->id . '_payment_fields' );
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
				do_action( 'wc_' . $this->id . '_payment_fields', $this );
			}
			do_action( 'chip_' . $this->id . '_payment_fields', $this );
		} elseif ( $this->supports( 'tokenization' ) && is_checkout() ) {
			$description = $this->get_description();
			if ( ! empty( $description ) ) {
				echo wp_kses_post( wpautop( wptexturize( $description ) ) );
			}
			$this->tokenization_script();
			$this->saved_payment_methods();

		} else {
			parent::payment_fields();

			// Check for razer.
			$pattern  = '/^razer_/';
			$is_razer = false;

			// Check if payment_met empty.
			if ( is_array( $this->payment_method_whitelist ) ) {
				$output = preg_grep( $pattern, $this->payment_method_whitelist );

				if ( count( $output ) > 0 ) {
					$is_razer = true;
				}
			}

			$select_field_id = '';

			if ( is_array( $this->payment_method_whitelist ) && 1 === count( $this->payment_method_whitelist ) && 'fpx' === $this->payment_method_whitelist[0] && 'yes' === $this->bypass_chip ) {
				$select_field_id = 'chip_fpx_bank';
				woocommerce_form_field(
					$select_field_id,
					array(
						'type'     => 'select',
						'required' => true,
						'label'    => __( 'Internet Banking', 'chip-for-woocommerce' ),
						'options'  => $this->list_fpx_banks(),
						'class'    => array( 'form-row-wide' ),
					)
				);
			} elseif ( is_array( $this->payment_method_whitelist ) && 1 === count( $this->payment_method_whitelist ) && 'fpx_b2b1' === $this->payment_method_whitelist[0] && 'yes' === $this->bypass_chip ) {
				$select_field_id = 'chip_fpx_b2b1_bank';
				woocommerce_form_field(
					$select_field_id,
					array(
						'type'     => 'select',
						'required' => true,
						'label'    => __( 'Corporate Internet Banking', 'chip-for-woocommerce' ),
						'options'  => $this->list_fpx_b2b1_banks(),
						'class'    => array( 'form-row-wide' ),
					)
				);
			} elseif ( is_array( $this->payment_method_whitelist ) && $is_razer && 'yes' === $this->bypass_chip ) {
				$select_field_id = 'chip_razer_ewallet';
				woocommerce_form_field(
					$select_field_id,
					array(
						'type'     => 'select',
						'required' => true,
						'label'    => __( 'E-Wallet', 'chip-for-woocommerce' ),
						'options'  => $this->list_razer_ewallets(),
						'class'    => array( 'form-row-wide' ),
					)
				);
			}

			// Initialize Select2 (selectWoo) on the dropdown for better UX.
			if ( '' !== $select_field_id ) {
				$placeholder       = '';
				$unavailable_banks = array();
				$show_bank_logos   = false;
				$bank_logo_base    = '';

				if ( 'chip_fpx_bank' === $select_field_id ) {
					$placeholder       = __( 'Select a bank…', 'chip-for-woocommerce' );
					$unavailable_banks = $this->get_unavailable_fpx_banks();
					$show_bank_logos   = true;
					$bank_logo_base    = WC_CHIP_URL . 'assets/fpx_bank/';
				} elseif ( 'chip_fpx_b2b1_bank' === $select_field_id ) {
					$placeholder       = __( 'Select a bank…', 'chip-for-woocommerce' );
					$unavailable_banks = $this->get_unavailable_fpx_b2b1_banks();
					$show_bank_logos   = true;
					$bank_logo_base    = WC_CHIP_URL . 'assets/fpx_bank/';
				} elseif ( 'chip_razer_ewallet' === $select_field_id ) {
					$placeholder     = __( 'Select an e-wallet…', 'chip-for-woocommerce' );
					$show_bank_logos = true;
					$bank_logo_base  = WC_CHIP_URL . 'assets/razer_ewallet/';
				}
				?>
				<script type="text/javascript">
					jQuery( function( $ ) {
						var $select = $( '#<?php echo esc_js( $select_field_id ); ?>' );
						var unavailableBanks = <?php echo wp_json_encode( $unavailable_banks ); ?>;
						var showBankLogos = <?php echo $show_bank_logos ? 'true' : 'false'; ?>;
						var bankLogoBase = '<?php echo esc_js( $bank_logo_base ); ?>';

						// Disable unavailable bank options.
						if ( unavailableBanks && unavailableBanks.length > 0 ) {
							unavailableBanks.forEach( function( bankCode ) {
								$select.find( 'option[value="' + bankCode + '"]' ).prop( 'disabled', true );
							});
						}

						// Custom template for bank options with logos (dropdown).
						function formatBankResult( option ) {
							if ( ! option.id || ! showBankLogos ) {
								return option.text;
							}

							var logoUrl = bankLogoBase + option.id + '.png';
							var $option = $(
								'<span class="chip-bank-option">' +
									'<img src="' + logoUrl + '" class="chip-bank-logo" onerror="this.style.display=\'none\'" />' +
									'<span class="chip-bank-name">' + option.text + '</span>' +
								'</span>'
							);

							return $option;
						}

						// Custom template for selected bank (input display).
						// Note: Return text only for selection to avoid SelectWoo rendering issues.
						function formatBankSelection( option ) {
							return option.text || '';
						}

						$select.selectWoo({
							placeholder: '<?php echo esc_js( $placeholder ); ?>',
							allowClear: false,
							width: 'resolve',
							templateResult: formatBankResult,
							templateSelection: formatBankSelection
						});
					});
				</script>
				<style>
					.chip-bank-option {
						display: flex;
						align-items: center;
						gap: 10px;
					}
					.chip-bank-logo {
						width: 24px;
						height: 24px;
						object-fit: contain;
						flex-shrink: 0;
					}
					.chip-bank-name {
						flex: 1;
					}
					.select2-results__option .chip-bank-option,
					.select2-selection__rendered .chip-bank-option {
						display: flex;
						align-items: center;
					}
				</style>
				<?php
			}
			// Note: wc_gateway_chip_5 requires no additional fields.
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verification handled by WooCommerce checkout.
		if ( ! is_wc_endpoint_url( 'order-pay' ) && ! is_add_payment_method_page() && is_array( $this->payment_method_whitelist ) && count( $this->payment_method_whitelist ) >= 2 && 'yes' === $this->bypass_chip && ! isset( $_GET['change_payment_method'] ) ) {
			foreach ( $this->payment_method_whitelist as $pm ) {
				if ( in_array( $pm, array( 'visa', 'mastercard', 'maestro' ), true ) ) {
					wp_enqueue_script( "wc-{$this->id}-direct-post" );
					$this->form();
					break;
				}
			}
		}
	}

	/**
	 * Get current language code.
	 *
	 * Currently, CHIP only supports English ('en'). When CHIP adds support for
	 * more languages, uncomment the code below to enable automatic language
	 * detection based on WordPress locale or WPML.
	 *
	 * @return string ISO 639-1 language code.
	 */
	public function get_language() {
		// TODO: Uncomment the code below when CHIP supports more languages.
		return 'en';

		/*
		 * Multi-language support (disabled - CHIP only supports 'en' currently).
		 *
		 * if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
		 *     $language = ICL_LANGUAGE_CODE;
		 * } else {
		 *     // Get WordPress locale (e.g., 'en_US', 'ms_MY') and extract ISO 639-1 two-letter code.
		 *     $locale   = get_locale();
		 *     $language = substr( $locale, 0, 2 );
		 * }
		 *
		 * // Filters the language code used for the CHIP payment page.
		 * // The language code should be a valid ISO 639-1 two-letter code.
		 * // @see https://en.wikipedia.org/wiki/List_of_ISO_639_language_codes
		 * return apply_filters( 'chip_payment_page_language', $language, $this );
		 */
	}

	/**
	 * Validate payment fields.
	 *
	 * @return bool
	 * @throws Exception When required field is missing.
	 */
	public function validate_fields() {
		// Check and throw error if payment method not selected.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification handled by WooCommerce checkout.
		$fpx_bank      = isset( $_POST['chip_fpx_bank'] ) ? sanitize_text_field( wp_unslash( $_POST['chip_fpx_bank'] ) ) : '';
		$fpx_b2b1_bank = isset( $_POST['chip_fpx_b2b1_bank'] ) ? sanitize_text_field( wp_unslash( $_POST['chip_fpx_b2b1_bank'] ) ) : '';

		if ( is_array( $this->payment_method_whitelist ) && 1 === count( $this->payment_method_whitelist ) && 'yes' === $this->bypass_chip ) {
			if ( 'fpx' === $this->payment_method_whitelist[0] && 0 === strlen( $fpx_bank ) ) {
				throw new Exception( esc_html__( 'Internet Banking is a required field.', 'chip-for-woocommerce' ) );
			} elseif ( 'fpx_b2b1' === $this->payment_method_whitelist[0] && 0 === strlen( $fpx_b2b1_bank ) ) {
				throw new Exception( esc_html__( 'Corporate Internet Banking is a required field.', 'chip-for-woocommerce' ) );
			}
		}

		// Check for razer.
		$pattern  = '/^razer_/';
		$is_razer = false;

		// Check if payment_met empty.
		if ( is_array( $this->payment_method_whitelist ) ) {
			$output = preg_grep( $pattern, $this->payment_method_whitelist );

			if ( count( $output ) > 0 ) {
				$is_razer = true;
			}
		}

		$razer_ewallet = isset( $_POST['chip_razer_ewallet'] ) ? sanitize_text_field( wp_unslash( $_POST['chip_razer_ewallet'] ) ) : '';
		if ( is_array( $this->payment_method_whitelist ) && 'yes' === $this->bypass_chip && $is_razer && 0 === strlen( $razer_ewallet ) ) {
			throw new Exception( esc_html__( 'E-Wallet is a required field.', 'chip-for-woocommerce' ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return true;
	}

	/**
	 * Process the payment for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( has_action( 'wc_' . $this->id . '_before_process_payment' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_before_process_payment', '2.0.0', 'chip_' . $this->id . '_before_process_payment' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			do_action( 'wc_' . $this->id . '_before_process_payment', $order_id, $this );
		}
		do_action( 'chip_' . $this->id . '_before_process_payment', $order_id, $this );

		// Start of logic for subscription_payment_method_change_customer supports.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['change_payment_method'] ) && absint( $_GET['change_payment_method'] ) === $order_id ) {
			return $this->process_payment_method_change( $order_id );
		}
		// End of logic for subscription_payment_method_change_customer supports.

		$order   = new WC_Order( $order_id );
		$user_id = $order->get_user_id();

		$token_id = '';

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification handled by WooCommerce checkout.
		$payment_token_key   = "wc-{$this->id}-payment-token";
		$payment_token_value = isset( $_POST[ $payment_token_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $payment_token_key ] ) ) : '';

		if ( ! empty( $payment_token_value ) && 'new' !== $payment_token_value ) {
			$token_id = wc_clean( $payment_token_value );
			$token    = WC_Payment_Tokens::get( $token_id );
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			if ( $token ) {
				if ( $token->get_user_id() !== $user_id ) {
					return array( 'result' => 'failure' );
				}

				$this->add_payment_token( $order->get_id(), $token );
			}
		}

		if ( 'yes' === $this->enable_additional_charges ) {
			$this->add_item_order_fee( $order );
		}

		$callback_url = add_query_arg( array( 'id' => $order_id ), WC()->api_request_url( $this->id ) );
		if ( defined( 'WC_CHIP_OLD_URL_SCHEME' ) && WC_CHIP_OLD_URL_SCHEME === true ) {
			$callback_url = home_url( '/?wc-api=' . get_class( $this ) . '&id=' . $order_id );
		}

		$params = array(
			'success_callback' => $callback_url,
			'success_redirect' => $callback_url,
			'failure_redirect' => $callback_url,
			'cancel_redirect'  => $callback_url,
			'force_recurring'  => false,
			'send_receipt'     => false,
			'creator_agent'    => 'WooCommerce: ' . WC_CHIP_MODULE_VERSION,
			'reference'        => $order->get_id(),
			'platform'         => 'woocommerce',
			'due'              => $this->get_due_timestamp(),
			'purchase'         => array(
				'total_override' => round( $order->get_total() * 100 ),
				'due_strict'     => 'yes' === $this->due_strict,
				'timezone'       => $this->purchase_time_zone,
				'currency'       => $order->get_currency(),
				'language'       => $this->get_language(),
				'products'       => array(),
			),
			'brand_id'         => $this->brand_id,
			'client'           => array(
				'email'                   => $order->get_billing_email(),
				'phone'                   => substr( $order->get_billing_phone(), 0, 32 ),
				'full_name'               => $this->filter_customer_full_name( trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ),
				'street_address'          => substr( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(), 0, 128 ),
				'country'                 => substr( $order->get_billing_country(), 0, 2 ),
				'city'                    => substr( $order->get_billing_city(), 0, 128 ),
				'zip_code'                => substr( $order->get_billing_postcode(), 0, 32 ),
				'state'                   => substr( $order->get_billing_state(), 0, 128 ),
				'shipping_street_address' => substr( $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(), 0, 128 ),
				'shipping_country'        => substr( $order->get_shipping_country(), 0, 2 ),
				'shipping_city'           => substr( $order->get_shipping_city(), 0, 128 ),
				'shipping_zip_code'       => substr( $order->get_shipping_postcode(), 0, 32 ),
				'shipping_state'          => substr( $order->get_shipping_state(), 0, 128 ),
			),
		);

		$items = $order->get_items();

		if ( is_countable( $items ) && count( $items ) > 100 ) {
			$params['purchase']['products'] = array(
				array(
					'name'  => 'Order #' . $order->get_id(),
					'price' => round( $order->get_total() * 100 ),
				),
			);
		} else {
			foreach ( $items as $item ) {
				$price = round( $item->get_total() * 100 );
				$qty   = $item->get_quantity();

				if ( $price < 0 ) {
					$price = 0;
				}

				$params['purchase']['products'][] = array(
					'name'     => substr( $item->get_name(), 0, 256 ),
					'price'    => round( $price / $qty ),
					'quantity' => $qty,
				);
			}
		}

		/**
		 * Ensure product is not empty as some WooCommerce installation doesn't have any product
		 */

		if ( empty( $params['purchase']['products'] ) ) {
			$params['purchase']['products'] = array(
				array(
					'name'  => 'Product',
					'price' => round( $order->get_total() * 100 ),
				),
			);
		}

		foreach ( $params['client'] as $key => $value ) {
			if ( empty( $value ) ) {
				unset( $params['client'][ $key ] );
			}
		}

		$chip = $this->api();

		if ( is_array( $this->payment_method_whitelist ) && ! empty( $this->payment_method_whitelist ) ) {
			$params['payment_method_whitelist'] = $this->payment_method_whitelist;
		}

		// Set skip_capture for authorize (delayed capture) payment action.
		if ( 'authorize' === $this->payment_action && $this->is_card_only_whitelist() ) {
			$params['skip_capture'] = true;
		}

		// Note: When customer ticks "save card" checkbox, remember_card parameter is
		// passed via direct-post.js instead of setting force_recurring here.

		if ( function_exists( 'wcs_order_contains_subscription' ) ) {
			if ( $this->supports( 'tokenization' ) && wcs_order_contains_subscription( $order ) ) {
				$params['payment_method_whitelist'] = $this->get_payment_method_for_recurring();
				$params['force_recurring']          = true;

				if ( 0 === $params['purchase']['total_override'] ) {
					$params['skip_capture'] = true;
				}
			}
		}

		if ( 'https' === $this->system_url_scheme ) {
			$params['success_callback'] = preg_replace( '/^http:/i', 'https:', $params['success_callback'] );
		}

		if ( 'yes' === $this->disable_callback ) {
			unset( $params['success_callback'] );
		}

		if ( 'yes' === $this->disable_redirect ) {
			unset( $params['success_redirect'] );
		}

		if ( ! empty( $order->get_customer_note() ) ) {
			$params['purchase']['notes'] = substr( $order->get_customer_note(), 0, 10000 );
		}

		if ( ( ! isset( $params['client']['email'] ) || empty( $params['client']['email'] ) ) ) {
			$params['client']['email'] = $this->email_fallback;
		}

		// Start of logic for WooCommerce Pre-orders.
		if ( $this->order_contains_pre_order( $order ) && $this->order_requires_payment_tokenization( $order ) ) {

			// WooCommerce Pre-orders only accept 1 single item in cart.
			foreach ( $order->get_items() as $item_id => $item ) {
				$product = $item->get_product();
			}

			$params['purchase']['total_override'] = round( absint( WC_Pre_Orders_Product::get_pre_order_fee( $product ) ) * 100 );

			if ( ! empty( $token_id ) && 0 === $params['purchase']['total_override'] ) {

				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			}

			$params['force_recurring'] = true;

			if ( $params['purchase']['total_override'] > 0 && 'authorize' !== $this->payment_action ) {
				$params['skip_capture'] = false;
			} else {
				$params['skip_capture'] = true;
			}
		}
		// End of logic for WooCommerce Pre-orders.

		if ( has_filter( 'wc_' . $this->id . '_purchase_params' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_purchase_params', '2.0.0', 'chip_' . $this->id . '_purchase_params' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$params = apply_filters( 'wc_' . $this->id . '_purchase_params', $params, $this );
		}
		$params = apply_filters( 'chip_' . $this->id . '_purchase_params', $params, $this );

		$payment = $chip->create_payment( $params );

		if ( ! is_array( $payment ) || ! array_key_exists( 'id', $payment ) ) {
			if ( is_array( $payment ) && array_key_exists( '__all__', $payment ) ) {
				foreach ( $payment['__all__'] as $all_error ) {
					wc_add_notice( $all_error['message'], 'error' );
					wc_add_notice( 'Brand ID: ' . $params['brand_id'], 'error' );
					wc_add_notice( 'Payment Method: ' . implode( ', ', $params['payment_method_whitelist'] ), 'error' );
					wc_add_notice( 'Amount: ' . $params['purchase']['currency'] . ' ' . number_format( $params['purchase']['total_override'] / 100, 2 ), 'error' );
				}
			} else {
				wc_add_notice( wc_print_r( $payment, true ), 'error' );
			}
			$this->log_order_info( 'create payment failed. message: ' . wc_print_r( $payment, true ), $order );
			return array(
				'result' => 'failure',
			);
		}

		if ( 'yes' === $this->enable_auto_clear_cart ) {
			WC()->cart->empty_cart();
		}

		$this->log_order_info( 'got checkout url, redirecting', $order );

		$payment_requery_status = 'due';

		if ( ! empty( $token_id ) ) {

			$charge_payment = $chip->charge_payment( $payment['id'], array( 'recurring_token' => $token->get_token() ) );
			/* translators: %1$s: Payment token ID */
			$order->add_order_note( sprintf( __( 'Token ID: %1$s', 'chip-for-woocommerce' ), $token->get_token() ) );
			$this->maybe_delete_payment_token( $charge_payment, $token_id );

			$get_payment            = $chip->get_payment( $payment['id'] );
			$payment_requery_status = $get_payment['status'];
		}

		$order->add_order_note(
			/* translators: %1$s: CHIP Purchase ID */
			sprintf( __( 'Payment attempt with CHIP. Purchase ID: %1$s', 'chip-for-woocommerce' ), $payment['id'] )
		);

		$order->update_meta_data( '_' . $this->id . '_purchase', $payment );
		$order->save();

		if ( has_action( 'wc_' . $this->id . '_chip_purchase' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_chip_purchase', '2.0.0', 'chip_' . $this->id . '_chip_purchase' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			do_action( 'wc_' . $this->id . '_chip_purchase', $payment, $order->get_id() );
		}
		do_action( 'chip_' . $this->id . '_chip_purchase', $payment, $order->get_id() );

		if ( 'paid' !== $payment_requery_status ) {
			$this->schedule_requery( $payment['id'], $order_id );
		}

		$redirect_url    = $payment['checkout_url'];
		$direct_post_url = '';

		if ( is_array( $payment['payment_method_whitelist'] ) && ! empty( $payment['payment_method_whitelist'] ) ) {
			foreach ( $payment['payment_method_whitelist'] as $pm ) {
				if ( ! in_array( $pm, array( 'visa', 'mastercard', 'maestro', 'mpgs_google_pay', 'mpgs_apple_pay' ), true ) ) {
					$redirect_url = $payment['checkout_url'];
					break;
				}

				$redirect_url    = $payment['direct_post_url'];
				$direct_post_url = $payment['direct_post_url'];
			}
		}

		// Store direct_post_url for blocks checkout (only for new card, not saved tokens).
		// Use static property to persist across different gateway instances.
		// When using a saved token, payment is charged directly - no redirect needed.
		if ( ! empty( $direct_post_url ) && 'yes' === $this->bypass_chip && empty( $token_id ) ) {
			self::$direct_post_urls[ $this->id ] = $direct_post_url;
		}

		if ( has_action( 'wc_' . $this->id . '_after_process_payment' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_after_process_payment', '2.0.0', 'chip_' . $this->id . '_after_process_payment' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			do_action( 'wc_' . $this->id . '_after_process_payment', $order_id, $this );
		}
		do_action( 'chip_' . $this->id . '_after_process_payment', $order_id, $this );

		return array(
			'result'   => 'success',
			'redirect' => esc_url_raw( $this->bypass_chip( $redirect_url, $payment ) ),
			'messages' => '<div class="woocommerce-info"><a href="' . esc_url_raw( $this->bypass_chip( $redirect_url, $payment ) ) . '">' . __( 'Click here to pay', 'chip-for-woocommerce' ) . '</a></div>',
		);
	}

	/**
	 * Get due timestamp for payment.
	 *
	 * @return int
	 */
	public function get_due_timestamp() {
		$due_strict_timing = $this->due_strict_timing;
		if ( empty( $this->due_strict_timing ) ) {
			$due_strict_timing = 60;
		}
		return time() + ( absint( $due_strict_timing ) * 60 );
	}

	/**
	 * Check if order can be refunded.
	 *
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		$has_api_creds    = $this->enabled && $this->secret_key && $this->brand_id;
		$can_refund_order = $order && $order->get_transaction_id() && $has_api_creds;

		if ( has_filter( 'wc_' . $this->id . '_can_refund_order' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_can_refund_order', '2.0.0', 'chip_' . $this->id . '_can_refund_order' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$can_refund_order = apply_filters( 'wc_' . $this->id . '_can_refund_order', $can_refund_order, $order, $this );
		}
		return apply_filters( 'chip_' . $this->id . '_can_refund_order', $can_refund_order, $order, $this );
	}

	/**
	 * Process a refund.
	 *
	 * @param int    $order_id Order ID.
	 * @param float  $amount   Refund amount.
	 * @param string $reason   Refund reason.
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $this->can_refund_order( $order ) ) {
			$this->log_order_info( 'Cannot refund order', $order );
			return new WP_Error( 'error', __( 'Refund failed.', 'chip-for-woocommerce' ) );
		}

		$chip   = $this->api();
		$params = array( 'amount' => round( $amount * 100 ) );

		$result = $chip->refund_payment( $order->get_transaction_id(), $params );

		if ( is_wp_error( $result ) || isset( $result['__all__'] ) ) {
			$error_messages = array_map(
				function ( $error ) {
					return isset( $error['message'] ) ? $error['message'] : '';
				},
				$result['__all__']
			);
			$error_details  = implode( '; ', array_filter( $error_messages ) );
			$chip->log_error( $error_details . ': ' . $order->get_order_number() );
			return new WP_Error( 'error', $error_details );
		}

		$this->log_order_info( 'Refund Result: ' . wc_print_r( $result, true ), $order );
		switch ( strtolower( $result['status'] ?? 'failed' ) ) {
			case 'success':
				$refund_amount = round( $result['payment']['amount'] / 100, 2 ) . $result['payment']['currency'];
				$order->add_order_note(
					/* translators: %1$s: Refund amount with currency, %2$s: Refund ID */
					sprintf( __( 'Refunded %1$s - Refund ID: %2$s', 'chip-for-woocommerce' ), $refund_amount, $result['id'] )
				);
				return true;
		}

		return true;
	}

	/**
	 * Get the public key from CHIP API.
	 *
	 * @return string
	 */
	public function get_public_key() {
		if ( empty( $this->public_key ) ) {
			$this->public_key = str_replace( '\n', "\n", $this->api()->public_key() );
			$this->update_option( 'public_key', $this->public_key );
		}

		return $this->public_key;
	}

	/**
	 * Process admin options and validate settings.
	 *
	 * @return bool
	 */
	public function process_admin_options() {
		parent::process_admin_options();
		$post = $this->get_post_data();

		$brand_id   = wc_clean( $post[ "woocommerce_{$this->id}_brand_id" ] );
		$secret_key = wc_clean( $post[ "woocommerce_{$this->id}_secret_key" ] );

		$chip = $this->api();
		$chip->set_key( $secret_key, $brand_id );
		$public_key = $chip->public_key();

		if ( is_array( $public_key ) ) {
			/* translators: %1$s: Error message */
			$this->add_error( sprintf( __( 'Configuration error: %1$s', 'chip-for-woocommerce' ), current( $public_key['__all__'] )['message'] ) );
			$this->update_option( 'public_key', '' );
			$this->update_option( 'available_recurring_payment_method', array() );
			$this->update_option( 'brand_id_valid', 'no' );
			return false;
		}

		$public_key = str_replace( '\n', "\n", $public_key );

		$woocommerce_currency = get_woocommerce_currency();
		if ( has_filter( 'wc_' . $this->id . '_purchase_currency' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_purchase_currency', '2.0.0', 'chip_' . $this->id . '_purchase_currency' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$woocommerce_currency = apply_filters( 'wc_' . $this->id . '_purchase_currency', $woocommerce_currency, $this );
		}
		$woocommerce_currency = apply_filters( 'chip_' . $this->id . '_purchase_currency', $woocommerce_currency, $this );

		$available_recurring_payment_method = array();

		$get_available_recurring_payment_method = $chip->payment_recurring_methods( $woocommerce_currency, $this->get_language(), 200 );

		if ( isset( $get_available_recurring_payment_method['available_payment_methods'] ) ) {
			foreach ( $get_available_recurring_payment_method['available_payment_methods'] as $apm ) {
				$available_recurring_payment_method[ $apm ] = ucwords( str_replace( '_', ' ', $apm ) );
			}
		}

		$this->update_option( 'public_key', $public_key );
		$this->update_option( 'available_recurring_payment_method', $available_recurring_payment_method );

		// Validate Brand ID based on payment_recurring_methods response.
		$brand_id_valid = isset( $get_available_recurring_payment_method['available_payment_methods'] );
		$this->update_option( 'brand_id_valid', $brand_id_valid ? 'yes' : 'no' );

		return true;
	}

	/**
	 * Auto charge for subscription renewals.
	 *
	 * @param float    $total_amount  Total amount to charge.
	 * @param WC_Order $renewal_order Renewal order object.
	 * @return void
	 */
	public function auto_charge( $total_amount, $renewal_order ) {

		if ( 'yes' === $this->enable_additional_charges ) {
			$this->add_item_order_fee( $renewal_order );
		}

		$renewal_order_id = $renewal_order->get_id();
		$tokens           = WC_Payment_Tokens::get_order_tokens( $renewal_order_id );
		if ( empty( $tokens ) ) {
			$renewal_order->update_status( 'failed' );
			$renewal_order->add_order_note( __( 'No card token available to charge.', 'chip-for-woocommerce' ) );
			return;
		}

		$callback_url = add_query_arg( array( 'id' => $renewal_order_id ), WC()->api_request_url( $this->id ) );
		if ( defined( 'WC_CHIP_OLD_URL_SCHEME' ) && WC_CHIP_OLD_URL_SCHEME === true ) {
			$callback_url = home_url( '/?wc-api=' . get_class( $this ) . '&id=' . $renewal_order_id );
		}

		$params = array(
			'success_callback' => $callback_url,
			'send_receipt'     => false,
			'creator_agent'    => 'WooCommerce: ' . WC_CHIP_MODULE_VERSION,
			'reference'        => $renewal_order_id,
			'platform'         => 'woocommerce_subscriptions',
			'due'              => $this->get_due_timestamp(),
			'brand_id'         => $this->brand_id,
			'client'           => array(
				'email'     => $renewal_order->get_billing_email(),
				'full_name' => $this->filter_customer_full_name( trim( $renewal_order->get_billing_first_name() . ' ' . $renewal_order->get_billing_last_name() ) ),
			),
			'purchase'         => array(
				'timezone'       => $this->purchase_time_zone,
				'currency'       => $renewal_order->get_currency(),
				'language'       => $this->get_language(),
				'due_strict'     => 'yes' === $this->due_strict,
				'total_override' => round( $total_amount * 100 ),
				'products'       => array(),
			),
		);

		$items = $renewal_order->get_items();

		foreach ( $items as $item ) {
			$price = round( $item->get_total() * 100 );
			$qty   = $item->get_quantity();

			if ( $price < 0 ) {
				$price = 0;
			}

			$params['purchase']['products'][] = array(
				'name'     => substr( $item->get_name(), 0, 256 ),
				'price'    => round( $price / $qty ),
				'quantity' => $qty,
			);
		}

		if ( has_filter( 'wc_' . $this->id . '_purchase_params' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_purchase_params', '2.0.0', 'chip_' . $this->id . '_purchase_params' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$params = apply_filters( 'wc_' . $this->id . '_purchase_params', $params, $this );
		}
		$params = apply_filters( 'chip_' . $this->id . '_purchase_params', $params, $this );

		$chip    = $this->api();
		$payment = $chip->create_payment( $params );

		$renewal_order->update_meta_data( '_' . $this->id . '_purchase', $payment );
		$renewal_order->save();

		if ( has_action( 'wc_' . $this->id . '_chip_purchase' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_chip_purchase', '2.0.0', 'chip_' . $this->id . '_chip_purchase' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			do_action( 'wc_' . $this->id . '_chip_purchase', $payment, $renewal_order_id );
		}
		do_action( 'chip_' . $this->id . '_chip_purchase', $payment, $renewal_order_id );

		$token = new WC_Payment_Token_CC();
		foreach ( $tokens as $key => $t ) {
			if ( $t->get_gateway_id() === $this->id ) {
				$token = $t;
				break;
			}
		}

		$this->get_lock( $renewal_order_id );

		$charge_payment = $chip->charge_payment( $payment['id'], array( 'recurring_token' => $token->get_token() ) );

		$this->maybe_delete_payment_token( $charge_payment, $token->get_id() );

		if ( is_array( $charge_payment ) && array_key_exists( '__all__', $charge_payment ) ) {
			$renewal_order->update_status( 'failed' );
			$error_messages = array_map(
				function ( $error ) {
					return isset( $error['message'] ) ? $error['message'] : '';
				},
				$charge_payment['__all__']
			);
			$error_details  = implode( '; ', array_filter( $error_messages ) );
			/* translators: %1$s: Error message details */
			$renewal_order->add_order_note( sprintf( __( 'Automatic charge attempt failed. Details: %1$s', 'chip-for-woocommerce' ), $error_details ) );
		} elseif ( is_array( $charge_payment ) && 'paid' === $charge_payment['status'] ) {
			$this->payment_complete( $renewal_order, $charge_payment );
			/* translators: %s: Transaction ID */
			$renewal_order->add_order_note( sprintf( __( 'Payment Successful by tokenization. Transaction ID: %s', 'chip-for-woocommerce' ), $payment['id'] ) );
		} elseif ( is_array( $charge_payment ) && 'pending_charge' === $charge_payment['status'] ) {
			$renewal_order->update_status( OrderInternalStatus::ON_HOLD );
		} else {
			$renewal_order->update_status( 'failed' );
			$renewal_order->add_order_note( __( 'Automatic charge attempt failed.', 'chip-for-woocommerce' ) );
		}

		/* translators: %1$s: Payment token ID */
		$renewal_order->add_order_note( sprintf( __( 'Token ID: %1$s', 'chip-for-woocommerce' ), $token->get_token() ) );

		$this->release_lock( $renewal_order_id );
	}

	/**
	 * Get database lock for order processing.
	 *
	 * Supports both MySQL (GET_LOCK) and PostgreSQL (pg_advisory_lock).
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function get_lock( $order_id ) {
		global $wpdb;

		$order_id = absint( $order_id );

		if ( $this->is_postgresql() ) {
			// PostgreSQL advisory lock using order_id as key.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'SELECT pg_advisory_lock(%d)', $order_id ) );
		} else {
			// MySQL named lock with 15 second timeout.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'SELECT GET_LOCK(%s, 15)', 'chip_payment_' . $order_id ) );
		}
	}

	/**
	 * Release database lock for order processing.
	 *
	 * Supports both MySQL (RELEASE_LOCK) and PostgreSQL (pg_advisory_unlock).
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function release_lock( $order_id ) {
		global $wpdb;

		$order_id = absint( $order_id );

		if ( $this->is_postgresql() ) {
			// PostgreSQL advisory unlock.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'SELECT pg_advisory_unlock(%d)', $order_id ) );
		} else {
			// MySQL release named lock.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'chip_payment_' . $order_id ) );
		}
	}

	/**
	 * Check if the database is PostgreSQL.
	 *
	 * @return bool True if PostgreSQL, false otherwise.
	 */
	private function is_postgresql() {
		global $wpdb;

		// Check if using PostgreSQL by examining the db_version or connection.
		if ( isset( $wpdb->is_mysql ) && false === $wpdb->is_mysql ) {
			return true;
		}

		// Alternative detection via SQL query.
		static $is_pgsql = null;

		if ( null === $is_pgsql ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$result   = $wpdb->get_var( 'SELECT VERSION()' );
			$is_pgsql = ( false !== stripos( $result, 'PostgreSQL' ) );
		}

		return $is_pgsql;
	}

	/**
	 * Store recurring token for user.
	 *
	 * @param array $payment Payment data from CHIP.
	 * @param int   $user_id User ID.
	 * @return WC_Payment_Token_CC|false
	 */
	public function store_recurring_token( $payment, $user_id ) {
		if ( ! get_user_by( 'id', $user_id ) ) {
			return false;
		}

		$chip_token_ids = get_user_meta( $user_id, '_' . $this->id . '_client_token_ids', true );

		if ( is_string( $chip_token_ids ) ) {
			$chip_token_ids = array();
		}

		$chip_tokenized_purchase_id = $payment['id'];

		if ( ! $payment['is_recurring_token'] ) {
			$chip_tokenized_purchase_id = $payment['recurring_token'];
		}

		foreach ( $chip_token_ids as $purchase_id => $token_id ) {
			if ( $purchase_id === $chip_tokenized_purchase_id ) {
				$wc_payment_token = WC_Payment_Tokens::get( $token_id );
				if ( $wc_payment_token ) {
					return $wc_payment_token;
				}
			}
		}

		$token = new WC_Payment_Token_CC();
		$token->set_token( $chip_tokenized_purchase_id );
		$token->set_gateway_id( $this->id );
		$token->set_card_type( $payment['transaction_data']['extra']['card_brand'] );
		$token->set_last4( substr( $payment['transaction_data']['extra']['masked_pan'], -4 ) );
		$token->set_expiry_month( $payment['transaction_data']['extra']['expiry_month'] );
		$token->set_expiry_year( '20' . $payment['transaction_data']['extra']['expiry_year'] );
		$token->set_user_id( $user_id );

		/**
		 * Store optional card data for later use-case
		 */
		$token->add_meta_data( 'cardholder_name', $payment['transaction_data']['extra']['cardholder_name'] );
		$token->add_meta_data( 'card_issuer_country', $payment['transaction_data']['extra']['card_issuer_country'] );
		$token->add_meta_data( 'masked_pan', $payment['transaction_data']['extra']['masked_pan'] );
		$token->add_meta_data( 'card_type', $payment['transaction_data']['extra']['card_type'] );
		if ( $token->save() ) {
			$chip_token_ids[ $chip_tokenized_purchase_id ] = $token->get_id();
			update_user_meta( $user_id, '_' . $this->id . '_client_token_ids', $chip_token_ids );
			return $token;
		}
		return false;
	}

	/**
	 * Add a new payment method for the customer.
	 *
	 * @return array
	 */
	public function add_payment_method() {
		$customer = new WC_Customer( get_current_user_id() );

		$url = add_query_arg(
			array(
				'tokenization' => 'yes',
			),
			WC()->api_request_url( $this->id )
		);

		$params = array(
			'payment_method_whitelist' => $this->get_payment_method_for_recurring(),
			'creator_agent'            => 'WooCommerce: ' . WC_CHIP_MODULE_VERSION,
			'platform'                 => 'woocommerce_subscriptions',
			'success_redirect'         => $url,
			'failure_redirect'         => $url,
			'force_recurring'          => true,
			'reference'                => get_current_user_id(),
			'brand_id'                 => $this->brand_id,
			'skip_capture'             => true,
			'client'                   => array(
				'email'     => wp_get_current_user()->user_email,
				'full_name' => $this->filter_customer_full_name( $customer->get_first_name() . ' ' . $customer->get_last_name() ),
			),
			'purchase'                 => array(
				'currency' => 'MYR',
				'products' => array(
					array(
						'name'  => 'Add payment method',
						'price' => 0,
					),
				),
			),
		);

		$chip = $this->api();

		if ( 'yes' === $this->disable_redirect ) {
			unset( $params['success_redirect'] );
		}

		$payment = $chip->create_payment( $params );

		WC()->session->set( 'chip_preauthorize', $payment['id'] );

		return array(
			'result'   => 'redirect',
			'redirect' => $payment['checkout_url'],
		);
	}

	/**
	 * Add payment token to order.
	 *
	 * @param int                 $order_id Order ID.
	 * @param WC_Payment_Token_CC $token    Payment token.
	 * @return void
	 */
	public function add_payment_token( $order_id, $token ) {
		$data_store = WC_Data_Store::load( 'order' );

		$order = new WC_Order( $order_id );
		$data_store->update_payment_token_ids( $order, array() );
		$order->add_payment_token( $token );

		if ( class_exists( 'WC_Subscriptions' ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );

			if ( empty( $subscriptions ) ) {
				return;
			}

			foreach ( $subscriptions as $subscription ) {
				$data_store->update_payment_token_ids( $subscription, array() );

				$subscription->add_payment_token( $token );
			}
		}
	}

	/**
	 * Change failing payment method for subscription.
	 *
	 * @param WC_Subscription $subscription  Subscription object.
	 * @param WC_Order        $renewal_order Renewal order object.
	 * @return void
	 */
	public function change_failing_payment_method( $subscription, $renewal_order ) {
		$token_ids = $renewal_order->get_payment_tokens();

		if ( empty( $token_ids ) ) {
			return;
		}

		$token = WC_Payment_Tokens::get( current( $token_ids ) );

		if ( empty( $token ) ) {
			return;
		}

		$data_store = WC_Data_Store::load( 'order' );
		$data_store->update_payment_token_ids( $subscription, array() );
		$subscription->add_payment_token( $token );
	}

	/**
	 * Complete payment and store token if applicable.
	 *
	 * @param WC_Order $order   Order object.
	 * @param array    $payment Payment data from CHIP.
	 * @return void
	 */
	public function payment_complete( $order, $payment ) {
		if ( $payment['is_recurring_token'] || ! empty( $payment['recurring_token'] ) ) {
			$token = $this->store_recurring_token( $payment, $order->get_user_id() );
			if ( $token ) {
				$this->add_payment_token( $order->get_id(), $token );
			}
		}

		// Use the order's payment method to get the correct meta key (handles legacy orders).
		$order_payment_method = $order->get_payment_method();

		/* translators: %s: Transaction ID */
		$order->add_order_note( sprintf( __( 'Payment Successful. Transaction ID: %s', 'chip-for-woocommerce' ), $payment['id'] ) );
		$order->update_meta_data( '_' . $order_payment_method . '_purchase', $payment );
		$order->payment_complete( $payment['id'] );
		$order->save();

		if ( true === $payment['is_test'] ) {
			/* translators: %s: Payment ID */
			$order->add_order_note( sprintf( __( 'The payment (%s) made in test mode where it does not involve real payment.', 'chip-for-woocommerce' ), $payment['id'] ) );
		}
	}

	/**
	 * Schedule a requery for order status.
	 *
	 * @param string $purchase_id Purchase ID.
	 * @param int    $order_id    Order ID.
	 * @param int    $attempt     Attempt number.
	 * @return void
	 */
	public function schedule_requery( $purchase_id, $order_id, $attempt = 1 ) {
		WC()->queue()->schedule_single( time() + $attempt * HOUR_IN_SECONDS, 'wc_chip_check_order_status', array( $purchase_id, $order_id, $attempt, $this->id ), "{$this->id}_single_requery" );
	}

	/**
	 * Handle payment token deletion.
	 *
	 * @param int              $token_id Token ID.
	 * @param WC_Payment_Token $token    Payment token object.
	 * @return void
	 */
	public function payment_token_deleted( $token_id, $token ) {
		$user_id    = $token->get_user_id();
		$token_id   = $token->get_id();
		$payment_id = $token->get_token();

		$chip_token_ids = get_user_meta( $user_id, '_' . $this->id . '_client_token_ids', true );

		if ( is_string( $chip_token_ids ) ) {
			$chip_token_ids = array();
		}

		foreach ( $chip_token_ids as $purchase_id => $c_token_id ) {
			if ( $token_id === $c_token_id ) {
				unset( $chip_token_ids[ $payment_id ] );
				update_user_meta( $user_id, '_' . $this->id . '_client_token_ids', $chip_token_ids );
				break;
			}
		}

		WC()->queue()->schedule_single( time(), 'wc_chip_delete_payment_token', array( $token->get_token(), $this->id ), "{$this->id}_delete_token" );
	}

	/**
	 * Delete payment token from CHIP.
	 *
	 * @param string $purchase_id Purchase ID.
	 * @return void
	 */
	public function delete_payment_token( $purchase_id ) {
		$this->api()->delete_token( $purchase_id );
	}

	/**
	 * Check order status from CHIP.
	 *
	 * @param string $purchase_id Purchase ID.
	 * @param int    $order_id    Order ID.
	 * @param int    $attempt     Attempt number.
	 * @return void
	 */
	public function check_order_status( $purchase_id, $order_id, $attempt ) {
		$this->get_lock( $order_id );

		// Clear post cache to ensure fresh data is retrieved when object cache is configured.
		// Note: This covers legacy order storage (posts table). For HPOS (High-Performance Order
		// Storage), there is no equivalent cache clearing function available at this moment.
		// HPOS may use its own caching mechanism that is not publicly accessible for clearing.
		clean_post_cache( $order_id );

		try {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				$this->release_lock( $order_id );
				return;
			}
		} catch ( Exception $e ) {
			$this->release_lock( $order_id );
			return;
		}

		if ( $order->is_paid() ) {
			$this->release_lock( $order_id );
			return;
		}

		$chip = $this->api();

		$payment = $chip->get_payment( $purchase_id );

		if ( array_key_exists( '__all__', $payment ) ) {
			$order->add_order_note( __( 'Order status check failed and no further reattempt will be made.', 'chip-for-woocommerce' ) );
			$this->release_lock( $order_id );
			return;
		}

		if ( 'paid' === $payment['status'] ) {
			$this->payment_complete( $order, $payment );
			$this->release_lock( $order_id );
			return;
		}

		if ( 'preauthorized' === $payment['status'] ) {
			// Handle preauthorized payments ($0 authorization for pre-orders or card verification).
			if ( $this->order_contains_pre_order( $order ) && $this->order_requires_payment_tokenization( $order ) ) {
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
			}
			$this->release_lock( $order_id );
			return;
		}

		if ( 'hold' === $payment['status'] ) {
			// Handle hold payments (delayed capture with actual payment amount).
			// Use the order's payment method to get the correct meta key (handles legacy orders).
			$order_payment_method = $order->get_payment_method();

			// Check if this is a pre-order with delayed capture.
			if ( $this->order_contains_pre_order( $order ) ) {
				$order->update_meta_data( '_' . $order_payment_method . '_purchase', $payment );
				$order->update_meta_data( '_chip_can_void', 'yes' );
				$order->update_meta_data( '_chip_hold_timestamp', time() );
				$order->set_transaction_id( $payment['id'] );
				$order->save();
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
			} elseif ( ! $order->has_status( 'on-hold' ) ) {
				$order->update_meta_data( '_' . $order_payment_method . '_purchase', $payment );
				$order->update_meta_data( '_chip_can_void', 'yes' );
				$order->update_meta_data( '_chip_hold_timestamp', time() );
				$order->set_transaction_id( $payment['id'] );
				/* translators: %s: Transaction ID */
				$order->add_order_note( sprintf( __( 'Payment authorized. Transaction ID: %s. Awaiting capture.', 'chip-for-woocommerce' ), $payment['id'] ) );
				$order->update_status( OrderInternalStatus::ON_HOLD, __( 'Payment authorized, awaiting capture.', 'chip-for-woocommerce' ) );
				$order->save();
			}
			$this->release_lock( $order_id );
			return;
		}

		/* translators: %1$s: Payment status from CHIP API. */
		$order->add_order_note( sprintf( __( 'Order status checked and the status is %1$s.', 'chip-for-woocommerce' ), $payment['status'] ) );

		$this->release_lock( $order_id );

		if ( 'expired' === $payment['status'] ) {
			return;
		}

		if ( $attempt < 8 ) {
			$this->schedule_requery( $purchase_id, $order_id, ++$attempt );
		}
	}

	/**
	 * Display admin notices for errors.
	 *
	 * @return void
	 */
	public function admin_notices() {
		foreach ( $this->errors as $error ) {
			?>
			<div class="notice notice-error">
				<p><?php echo esc_html( $error ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Get list of FPX banks.
	 *
	 * @return array
	 */
	public function list_fpx_banks() {
		$default_fpx = array(
			''         => __( 'Choose your bank', 'chip-for-woocommerce' ),
			'ABB0233'  => __( 'Affin Bank', 'chip-for-woocommerce' ),
			'ABMB0212' => __( 'Alliance Bank (Personal)', 'chip-for-woocommerce' ),
			'AGRO01'   => __( 'AGRONet', 'chip-for-woocommerce' ),
			'AMBB0209' => __( 'AmBank', 'chip-for-woocommerce' ),
			'BIMB0340' => __( 'Bank Islam', 'chip-for-woocommerce' ),
			'BMMB0341' => __( 'Bank Muamalat', 'chip-for-woocommerce' ),
			'BKRM0602' => __( 'Bank Rakyat', 'chip-for-woocommerce' ),
			'BOCM01'   => __( 'Bank Of China', 'chip-for-woocommerce' ),
			'BSN0601'  => __( 'BSN', 'chip-for-woocommerce' ),
			'BCBB0235' => __( 'CIMB Bank', 'chip-for-woocommerce' ),
			'HLB0224'  => __( 'Hong Leong Bank', 'chip-for-woocommerce' ),
			'HSBC0223' => __( 'HSBC Bank', 'chip-for-woocommerce' ),
			'KFH0346'  => __( 'KFH', 'chip-for-woocommerce' ),
			'MBB0228'  => __( 'Maybank2E', 'chip-for-woocommerce' ),
			'MB2U0227' => __( 'Maybank2u', 'chip-for-woocommerce' ),
			'OCBC0229' => __( 'OCBC Bank', 'chip-for-woocommerce' ),
			'PBB0233'  => __( 'Public Bank', 'chip-for-woocommerce' ),
			'RHB0218'  => __( 'RHB Bank', 'chip-for-woocommerce' ),
			'SCB0216'  => __( 'Standard Chartered', 'chip-for-woocommerce' ),
			'UOB0226'  => __( 'UOB Bank', 'chip-for-woocommerce' ),
		);

		$data_chip_fpx_b2c_banks = $this->get_fpx_banks_data( 'chip_fpx_b2c_banks' );

		$fpx = $data_chip_fpx_b2c_banks['fpx'];

		$this->filter_non_available_fpx( $default_fpx, $fpx );

		if ( has_filter( 'wc_' . $this->id . '_list_fpx_banks' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_list_fpx_banks', '2.0.0', 'chip_' . $this->id . '_list_fpx_banks' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$default_fpx = apply_filters( 'wc_' . $this->id . '_list_fpx_banks', $default_fpx );
		}
		return apply_filters( 'chip_' . $this->id . '_list_fpx_banks', $default_fpx );
	}

	/**
	 * Get FPX banks data from transient or API.
	 *
	 * @param string $transient_key Transient key.
	 * @return array
	 */
	protected function get_fpx_banks_data( $transient_key ) {
		$expiration = 60 * 3; // 3 minutes

		$data = get_transient( $transient_key );

		if ( false === $data || $this->is_fpx_data_expired( $data ) ) {
			$data = array(
				'timestamp' => time(),
			);

			if ( 'chip_fpx_b2c_banks' === $transient_key ) {
				$data['fpx'] = $this->fpx_api()->get_fpx();
			} else {
				$data['fpx'] = $this->fpx_api()->get_fpx_b2b1();
			}

			set_transient( $transient_key, $data, $expiration );
		}

		return $data;
	}

	/**
	 * Check if FPX data is expired.
	 *
	 * @param array $data FPX data.
	 * @return bool
	 */
	protected function is_fpx_data_expired( $data ) {
		if ( ! is_array( $data ) || ! isset( $data['timestamp'] ) ) {
			return true;
		}

		$expiration = 60 * 3; // 3 minutes.
		return ( time() - $data['timestamp'] ) > $expiration;
	}

	/**
	 * Get list of FPX B2B1 banks.
	 *
	 * @return array
	 */
	public function list_fpx_b2b1_banks() {
		$default_fpx = array(
			''         => __( 'Choose your bank', 'chip-for-woocommerce' ),
			'ABB0235'  => __( 'AFFINMAX', 'chip-for-woocommerce' ),
			'ABMB0213' => __( 'Alliance Bank (Business)', 'chip-for-woocommerce' ),
			'AGRO02'   => __( 'AGRONetBIZ', 'chip-for-woocommerce' ),
			'AMBB0208' => __( 'AmBank', 'chip-for-woocommerce' ),
			'BIMB0340' => __( 'Bank Islam', 'chip-for-woocommerce' ),
			'BMMB0342' => __( 'Bank Muamalat', 'chip-for-woocommerce' ),
			'BNP003'   => __( 'BNP Paribas', 'chip-for-woocommerce' ),
			'BCBB0235' => __( 'CIMB Bank', 'chip-for-woocommerce' ),
			'CIT0218'  => __( 'Citibank Corporate Banking', 'chip-for-woocommerce' ),
			'DBB0199'  => __( 'Deutsche Bank', 'chip-for-woocommerce' ),
			'HLB0224'  => __( 'Hong Leong Bank', 'chip-for-woocommerce' ),
			'HSBC0223' => __( 'HSBC Bank', 'chip-for-woocommerce' ),
			'BKRM0602' => __( 'Bank Rakyat', 'chip-for-woocommerce' ),
			'KFH0346'  => __( 'KFH', 'chip-for-woocommerce' ),
			'MBB0228'  => __( 'Maybank2E', 'chip-for-woocommerce' ),
			'OCBC0229' => __( 'OCBC Bank', 'chip-for-woocommerce' ),
			'PBB0233'  => __( 'Public Bank', 'chip-for-woocommerce' ),
			'PBB0234'  => __( 'Public Bank PB enterprise', 'chip-for-woocommerce' ),
			'RHB0218'  => __( 'RHB Bank', 'chip-for-woocommerce' ),
			'SCB0215'  => __( 'Standard Chartered', 'chip-for-woocommerce' ),
			'UOB0228'  => __( 'UOB Regional', 'chip-for-woocommerce' ),
		);

		$data_chip_fpx_b2b1_banks = $this->get_fpx_banks_data( 'chip_fpx_b2b1_banks' );

		$fpx = $data_chip_fpx_b2b1_banks['fpx'];

		$this->filter_non_available_fpx( $default_fpx, $fpx, 'b2b1' );

		if ( has_filter( 'wc_' . $this->id . '_list_fpx_b2b1_banks' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_list_fpx_b2b1_banks', '2.0.0', 'chip_' . $this->id . '_list_fpx_b2b1_banks' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$default_fpx = apply_filters( 'wc_' . $this->id . '_list_fpx_b2b1_banks', $default_fpx );
		}
		return apply_filters( 'chip_' . $this->id . '_list_fpx_b2b1_banks', $default_fpx );
	}

	/**
	 * Filter out non-available FPX banks.
	 *
	 * @param array  $default_fpx Default FPX banks array.
	 * @param array  $fpx         FPX banks data from API.
	 * @param string $type        Bank type: 'b2c' or 'b2b1'.
	 * @return void
	 */
	public function filter_non_available_fpx( &$default_fpx, $fpx, $type = 'b2c' ) {
		$unavailable = array();

		if ( is_array( $fpx ) ) {
			foreach ( $default_fpx as $key => $value ) {
				if ( '' === $key ) {
					continue;
				}
				if ( isset( $fpx[ $key ] ) && 'A' !== $fpx[ $key ] ) {
					// Mark bank as offline instead of removing it.
					/* translators: %s: Bank name. */
					$default_fpx[ $key ] = sprintf( __( '%s (Offline)', 'chip-for-woocommerce' ), $value );
					$unavailable[]       = $key;
				}
			}
		}

		if ( 'b2b1' === $type ) {
			$this->unavailable_fpx_b2b1_banks = $unavailable;
		} else {
			$this->unavailable_fpx_banks = $unavailable;
		}
	}

	/**
	 * Get unavailable FPX B2C bank codes.
	 *
	 * @return array
	 */
	public function get_unavailable_fpx_banks() {
		return $this->unavailable_fpx_banks;
	}

	/**
	 * Get unavailable FPX B2B1 bank codes.
	 *
	 * @return array
	 */
	public function get_unavailable_fpx_b2b1_banks() {
		return $this->unavailable_fpx_b2b1_banks;
	}

	/**
	 * Get list of available Razer e-wallets.
	 *
	 * @return array
	 */
	public function list_razer_ewallets() {
		$ewallet_list = array(
			'' => __( 'Choose your e-wallet', 'chip-for-woocommerce' ),
		);

		if ( in_array( 'razer_atome', $this->payment_method_whitelist, true ) ) {
			$ewallet_list['Atome'] = __( 'Atome', 'chip-for-woocommerce' );
		}

		if ( in_array( 'razer_grabpay', $this->payment_method_whitelist, true ) ) {
			$ewallet_list['GrabPay'] = __( 'GrabPay', 'chip-for-woocommerce' );
		}
		if ( in_array( 'razer_maybankqr', $this->payment_method_whitelist, true ) ) {
			$ewallet_list['MB2U_QRPay-Push'] = __( 'Maybank QRPay', 'chip-for-woocommerce' );
		}

		if ( in_array( 'razer_shopeepay', $this->payment_method_whitelist, true ) ) {
			$ewallet_list['ShopeePay'] = __( 'ShopeePay', 'chip-for-woocommerce' );
		}

		if ( in_array( 'razer_tng', $this->payment_method_whitelist, true ) ) {
			$ewallet_list['TNG-EWALLET'] = __( 'Touch \'n Go eWallet', 'chip-for-woocommerce' );
		}

		if ( in_array( 'duitnow_qr', $this->payment_method_whitelist, true ) ) {
			$ewallet_list['duitnow-qr'] = __( 'Duitnow QR', 'chip-for-woocommerce' );
		}

		if ( has_filter( 'wc_' . $this->id . '_list_razer_ewallets' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_list_razer_ewallets', '2.0.0', 'chip_' . $this->id . '_list_razer_ewallets' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$ewallet_list = apply_filters( 'wc_' . $this->id . '_list_razer_ewallets', $ewallet_list );
		}
		return apply_filters( 'chip_' . $this->id . '_list_razer_ewallets', $ewallet_list );
	}

	/**
	 * Bypass CHIP payment page if configured.
	 *
	 * @param string $url     Checkout URL.
	 * @param array  $payment Payment data from CHIP.
	 * @return string
	 */
	public function bypass_chip( $url, $payment ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification handled by WooCommerce checkout.
		if ( 'yes' === $this->bypass_chip && ! $payment['is_test'] ) {
			if ( isset( $_POST['chip_fpx_bank'] ) && ! empty( $_POST['chip_fpx_bank'] ) ) {
				$url .= '?preferred=fpx&fpx_bank_code=' . sanitize_text_field( wp_unslash( $_POST['chip_fpx_bank'] ) );
			} elseif ( isset( $_POST['chip_fpx_b2b1_bank'] ) && ! empty( $_POST['chip_fpx_b2b1_bank'] ) ) {
				$url .= '?preferred=fpx_b2b1&fpx_bank_code=' . sanitize_text_field( wp_unslash( $_POST['chip_fpx_b2b1_bank'] ) );
			} elseif ( isset( $_POST['chip_razer_ewallet'] ) && ! empty( $_POST['chip_razer_ewallet'] ) ) {
				$razer_ewallet = sanitize_text_field( wp_unslash( $_POST['chip_razer_ewallet'] ) );
				switch ( $razer_ewallet ) {
					case 'Atome':
						$preferred = 'razer_atome';
						break;
					case 'GrabPay':
						$preferred = 'razer_grabpay';
						break;
					case 'TNG-EWALLET':
						$preferred = 'razer_tng';
						break;
					case 'ShopeePay':
						$preferred = 'razer_shopeepay';
						break;
					case 'MB2U_QRPay-Push':
						$preferred = 'razer_maybankqr';
						break;
					case 'duitnow-qr':
						$preferred = 'duitnow_qr';
						break;
				}

				$url .= '?preferred=' . $preferred . '&razer_bank_code=' . $razer_ewallet;
			} elseif ( is_array( $this->payment_method_whitelist ) && 1 === count( $this->payment_method_whitelist ) && 'duitnow_qr' === $this->payment_method_whitelist[0] ) {
				$url .= '?preferred=duitnow_qr';
			}
		} elseif ( 'wc_gateway_chip_5' === $this->id ) {
			$url .= '?preferred=razer_atome&razer_bank_code=Atome';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return $url;
	}

	/**
	 * Process payment method change for subscriptions.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment_method_change( $order_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification handled by WooCommerce Subscriptions.
		$payment_token = isset( $_POST[ "wc-{$this->id}-payment-token" ] ) ? sanitize_text_field( wp_unslash( $_POST[ "wc-{$this->id}-payment-token" ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( '' !== $payment_token && 'new' !== $payment_token ) {
			return array(
				'result'   => 'success',
				'redirect' => wc_get_page_permalink( 'myaccount' ),
			);
		}

		$customer = new WC_Customer( get_current_user_id() );

		$url = add_query_arg(
			array(
				'id'                            => $order_id,
				'process_payment_method_change' => 'yes',
			),
			WC()->api_request_url( $this->id )
		);
		if ( defined( 'WC_CHIP_OLD_URL_SCHEME' ) && WC_CHIP_OLD_URL_SCHEME === true ) {
			$url = home_url( '/?wc-api=' . get_class( $this ) . '&id=' . $order_id );
		}

		$params = array(
			'payment_method_whitelist' => $this->get_payment_method_for_recurring(),
			'creator_agent'            => 'WooCommerce: ' . WC_CHIP_MODULE_VERSION,
			'platform'                 => 'woocommerce_subscriptions',
			'success_redirect'         => $url,
			'failure_redirect'         => $url,
			'force_recurring'          => true,
			'reference'                => $order_id,
			'brand_id'                 => $this->brand_id,
			'skip_capture'             => true,
			'client'                   => array(
				'email'     => wp_get_current_user()->user_email,
				'full_name' => $this->filter_customer_full_name( $customer->get_first_name() . ' ' . $customer->get_last_name() ),
			),
			'purchase'                 => array(
				'currency' => 'MYR',
				'products' => array(
					array(
						'name'  => 'Add payment method',
						'price' => 0,
					),
				),
			),
		);

		$chip = $this->api();

		if ( 'yes' === $this->disable_redirect ) {
			unset( $params['success_redirect'] );
		}

		$payment = $chip->create_payment( $params );

		WC()->session->set( 'chip_payment_method_change_' . $order_id, $payment['id'] );

		$redirect_url = $payment['checkout_url'];

		if ( is_array( $payment['payment_method_whitelist'] ) && ! empty( $payment['payment_method_whitelist'] ) ) {
			foreach ( $payment['payment_method_whitelist'] as $pm ) {
				if ( ! in_array( $pm, array( 'visa', 'mastercard', 'maestro', 'mpgs_google_pay', 'mpgs_apple_pay' ), true ) ) {
					$redirect_url = $payment['checkout_url'];
					break;
				}

				$redirect_url = $payment['direct_post_url'];
			}
		}

		return array(
			'result'   => 'success',
			'redirect' => $redirect_url,
		);
	}

	/**
	 * Maybe don't update payment method for subscription.
	 *
	 * @param bool            $update             Whether to update.
	 * @param string          $new_payment_method New payment method.
	 * @param WC_Subscription $subscription       Subscription object.
	 * @return bool
	 */
	public function maybe_dont_update_payment_method( $update, $new_payment_method, $subscription ) {
		if ( $this->id !== $new_payment_method ) {
			return $update;
		}

		$nonce = isset( $_POST['_wcsnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wcsnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wcs_change_payment_method' ) ) {
			return $update;
		}

		$payment_token_key   = "wc-{$this->id}-payment-token";
		$payment_token_value = isset( $_POST[ $payment_token_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $payment_token_key ] ) ) : '';

		/**
		 * If customer chooses to create new card token:
		 *   - Do not immediately call ::update_payment_method
		 *   - Do flag _delayed_update_payment_method_all if any
		 *
		 * If customer chooses existing card token, the default $update value is used.
		 */
		if ( empty( $payment_token_value ) || 'new' === $payment_token_value ) {
			$update = false;
		}

		return $update;
	}

	/**
	 * Handle payment method change callback.
	 *
	 * @return void
	 */
	public function handle_payment_method_change() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Callback from external payment gateway, verified via X-Signature.
		if ( ! isset( $_GET['id'] ) ) {
			exit( 'Missing subscription ID' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Callback from external payment gateway, verified via X-Signature.
		$subscription_id = intval( $_GET['id'] );
		$payment_id      = WC()->session->get( 'chip_payment_method_change_' . $subscription_id );

		if ( ! wcs_is_subscription( $subscription_id ) ) {
			exit( esc_html__( 'Order is not subscription', 'chip-for-woocommerce' ) );
		}

		$subscription = new WC_Subscription( $subscription_id );

		if ( ! $payment_id && isset( $_SERVER['HTTP_X_SIGNATURE'] ) ) {
			$content   = file_get_contents( 'php://input' );
			$signature = isset( $_SERVER['HTTP_X_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_SIGNATURE'] ) ) : '';

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required to decode RSA signature from payment gateway for verification.
			if ( openssl_verify( $content, base64_decode( $signature ), $this->get_public_key(), 'sha256WithRSAEncryption' ) !== 1 ) {
				$message = __( 'Success callback failed to be processed due to failure in verification.', 'chip-for-woocommerce' );
				$this->log_order_info( $message, $subscription );
				exit( esc_html( $message ) );
			}

			$payment    = json_decode( $content, true );
			$payment_id = array_key_exists( 'id', $payment ) ? sanitize_key( $payment['id'] ) : '';
		} elseif ( $payment_id ) {
			$payment = $this->api()->get_payment( $payment_id );
		} else {
			exit( esc_html__( 'Unexpected response', 'chip-for-woocommerce' ) );
		}

		if ( 'preauthorized' !== $payment['status'] ) {
			wc_clear_notices();
			wc_add_notice( sprintf( '%1$s %2$s', __( 'Unable to change payment method.', 'chip-for-woocommerce' ), wc_print_r( $payment['transaction_data']['attempts'][0]['error'], true ) ), 'error' );
			wp_safe_redirect( $subscription->get_view_order_url() );
			exit;
		}

		$this->get_lock( $payment_id );

		$token = $this->store_recurring_token( $payment, $subscription->get_user_id() );
		if ( $token ) {
			$this->add_payment_token( $subscription_id, $token );

			WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $subscription, $this->id );

			if ( WC_Subscriptions_Change_Payment_Gateway::will_subscription_update_all_payment_methods( $subscription ) ) {
				WC_Subscriptions_Change_Payment_Gateway::update_all_payment_methods_from_subscription( $subscription, $this->id );

				$subscription_ids = WCS_Customer_Store::instance()->get_users_subscription_ids( $subscription->get_customer_id() );
				foreach ( $subscription_ids as $subscription_id ) {
					// Skip the subscription providing the new payment meta.
					if ( $subscription->get_id() === $subscription_id ) {
						continue;
					}

					$user_subscription = wcs_get_subscription( $subscription_id );
					// Skip if subscription's current payment method is not supported.
					if ( ! $user_subscription->payment_method_supports( 'subscription_cancellation' ) ) {
						continue;
					}

					// Skip if there are no remaining payments || the subscription is not current.
					if ( $user_subscription->get_time( 'next_payment' ) <= 0 || ! $user_subscription->has_status( array( 'active', 'on-hold' ) ) ) {
							continue;
					}

					$this->add_payment_token( $user_subscription->get_id(), $token );
				}
			}
		}

		$this->release_lock( $payment_id );

		wp_safe_redirect( $subscription->get_view_order_url() );
		exit;
	}

	/**
	 * Handle change payment method from shortcode.
	 *
	 * @param WC_Subscription $subscription Subscription object.
	 * @return void
	 */
	public function handle_change_payment_method_shortcode( $subscription ) {
		$nonce = isset( $_POST['_wcsnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wcsnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wcs_change_payment_method' ) ) {
			return;
		}

		$payment_token_key   = "wc-{$this->id}-payment-token";
		$payment_token_value = isset( $_POST[ $payment_token_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $payment_token_key ] ) ) : '';

		if ( ! empty( $payment_token_value ) && 'new' !== $payment_token_value ) {
			$token_id = wc_clean( $payment_token_value );

			$this->add_payment_token( $subscription->get_id(), WC_Payment_Tokens::get( $token_id ) );

			$update_all = isset( $_POST['update_all_subscriptions_payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['update_all_subscriptions_payment_method'] ) ) : '';
			if ( $update_all ) {
				$subscription_ids = WCS_Customer_Store::instance()->get_users_subscription_ids( $subscription->get_customer_id() );
				foreach ( $subscription_ids as $subscription_id ) {
					// Skip the subscription providing the new payment meta.
					if ( $subscription->get_id() === $subscription_id ) {
						continue;
					}

					$user_subscription = wcs_get_subscription( $subscription_id );
					// Skip if subscription's current payment method is not supported.
					if ( ! $user_subscription->payment_method_supports( 'subscription_cancellation' ) ) {
						continue;
					}

					// Skip if there are no remaining payments || the subscription is not current.
					if ( $user_subscription->get_time( 'next_payment' ) <= 0 || ! $user_subscription->has_status( array( 'active', 'on-hold' ) ) ) {
						continue;
					}

					$this->add_payment_token( $user_subscription->get_id(), WC_Payment_Tokens::get( $token_id ) );
				}
			}
		}
	}

	/**
	 * Maybe hide add new payment method option.
	 *
	 * @param string             $html    HTML content.
	 * @param WC_Payment_Gateway $gateway Payment gateway.
	 * @return string
	 */
	public function maybe_hide_add_new_payment_method( $html, $gateway ) {
		if ( count( $gateway->get_tokens() ) === 0 ) {
			return '';
		}

		return $html;
	}

	/**
	 * Filter customer full name to valid characters.
	 *
	 * @param string $name Customer name.
	 * @return string
	 */
	public function filter_customer_full_name( $name ) {
		$name = str_replace( "\u{2019}", "'", $name );

		$name = preg_replace( '/[^A-Za-z0-9\@\/\\\(\)\.\-\_\,\&\']\ /', '', $name );

		return substr( $name, 0, 128 );
	}

	/**
	 * Register frontend scripts.
	 *
	 * @return void
	 */
	public function register_script() {
		wp_register_script(
			"wc-{$this->id}-direct-post",
			trailingslashit( WC_CHIP_URL ) . 'includes/js/direct-post.js',
			array( 'jquery' ),
			WC_CHIP_MODULE_VERSION,
			true
		);

		wp_localize_script( "wc-{$this->id}-direct-post", 'gateway_option', array( 'id' => $this->id ) );
	}

	/**
	 * Enqueue admin scripts for gateway settings page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load on WooCommerce settings pages.
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking page context.
		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

		// Only load for this gateway's settings page.
		if ( $section !== $this->id ) {
			return;
		}

		wp_enqueue_script(
			'chip-admin-logo-preview',
			trailingslashit( WC_CHIP_URL ) . 'includes/js/admin-logo-preview.js',
			array( 'jquery' ),
			WC_CHIP_MODULE_VERSION,
			true
		);

		// Pass logo URLs to JavaScript.
		wp_localize_script(
			'chip-admin-logo-preview',
			'chipLogoUrls_' . $this->id,
			$this->get_logo_urls()
		);

		// Add copy configuration script if there are existing configurations.
		$config_data = $this->get_api_configuration_data();
		if ( ! empty( $config_data ) ) {
			$script = '
				jQuery(function($) {
					var configData = ' . wp_json_encode( $config_data ) . ';
					$("#woocommerce_' . esc_js( $this->id ) . '_copy_configuration").on("change", function() {
						var selected = $(this).val();
						if (selected && configData[selected]) {
							$("#woocommerce_' . esc_js( $this->id ) . '_brand_id").val(configData[selected].brand_id).trigger("change");
							$("#woocommerce_' . esc_js( $this->id ) . '_secret_key").val(configData[selected].secret_key).trigger("change");
							$(this).val("").trigger("change.select2");
						}
					});
				});
			';
			wp_add_inline_script( 'chip-admin-logo-preview', $script );
		}
	}

	/**
	 * Output the payment form fields.
	 *
	 * @return void
	 */
	public function form() {
		wp_enqueue_script( 'wc-credit-card-form' );

		$fields = array();

		$cvc_placeholder = esc_attr__( 'CVC', 'chip-for-woocommerce' );
		$cvc_field       = '<p class="form-row form-row-last validate-required" id="' . esc_attr( $this->id ) . '-card-cvc_field">
			<label for="' . esc_attr( $this->id ) . '-card-cvc" class="required_field">' . esc_html__( 'CVC', 'chip-for-woocommerce' ) . '&nbsp;<span class="required" aria-hidden="true">*</span></label>
			<span class="woocommerce-input-wrapper">
				<input type="password" class="input-text" name="' . esc_attr( $this->id ) . '-card-cvc" id="' . esc_attr( $this->id ) . '-card-cvc" placeholder="' . $cvc_placeholder . '" aria-required="true" autocomplete="off" inputmode="numeric" maxlength="4" data-placeholder="' . $cvc_placeholder . '" />
			</span>
		</p>';

		$name_placeholder   = esc_attr__( 'Name', 'chip-for-woocommerce' );
		$number_placeholder = esc_attr__( '1234 1234 1234 1234', 'chip-for-woocommerce' );
		$expiry_placeholder = esc_attr__( 'MM / YY', 'chip-for-woocommerce' );

		$default_fields = array(
			'card-name-field'   => '<p class="form-row form-row-wide validate-required" id="' . esc_attr( $this->id ) . '-card-name_field">
				<label for="' . esc_attr( $this->id ) . '-card-name" class="required_field">' . esc_html__( 'Cardholder Name', 'chip-for-woocommerce' ) . '&nbsp;<span class="required" aria-hidden="true">*</span></label>
				<span class="woocommerce-input-wrapper">
					<input type="text" class="input-text" name="' . esc_attr( $this->id ) . '-card-name" id="' . esc_attr( $this->id ) . '-card-name" placeholder="' . $name_placeholder . '" aria-required="true" autocomplete="cc-name" inputmode="text" maxlength="30" data-placeholder="' . $name_placeholder . '" />
				</span>
			</p>',
			'card-number-field' => '<p class="form-row form-row-wide validate-required" id="' . esc_attr( $this->id ) . '-card-number_field">
				<label for="' . esc_attr( $this->id ) . '-card-number" class="required_field">' . esc_html__( 'Card number', 'chip-for-woocommerce' ) . '&nbsp;<span class="required" aria-hidden="true">*</span></label>
				<span class="woocommerce-input-wrapper">
					<input type="tel" class="input-text" name="' . esc_attr( $this->id ) . '-card-number" id="' . esc_attr( $this->id ) . '-card-number" placeholder="' . $number_placeholder . '" aria-required="true" autocomplete="cc-number" inputmode="numeric" data-placeholder="' . $number_placeholder . '" />
				</span>
			</p>',
			'card-expiry-field' => '<p class="form-row form-row-first validate-required" id="' . esc_attr( $this->id ) . '-card-expiry_field">
				<label for="' . esc_attr( $this->id ) . '-card-expiry" class="required_field">' . esc_html__( 'Expiry (MM/YY)', 'chip-for-woocommerce' ) . '&nbsp;<span class="required" aria-hidden="true">*</span></label>
				<span class="woocommerce-input-wrapper">
					<input type="tel" class="input-text" name="' . esc_attr( $this->id ) . '-card-expiry" id="' . esc_attr( $this->id ) . '-card-expiry" placeholder="' . $expiry_placeholder . '" aria-required="true" autocomplete="cc-exp" inputmode="numeric" maxlength="7" data-placeholder="' . $expiry_placeholder . '" />
				</span>
			</p>',
		);

		if ( ! $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
			$default_fields['card-cvc-field'] = $cvc_field;
		}

		$filtered_fields = $default_fields;
		if ( has_filter( 'woocommerce_credit_card_form_fields' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$filtered_fields = apply_filters( 'woocommerce_credit_card_form_fields', $filtered_fields, $this->id );
		}
		$filtered_fields = apply_filters( 'chip_credit_card_form_fields', $filtered_fields, $this->id );
		$fields          = wp_parse_args( $fields, $filtered_fields );
		?>

		<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
		<?php
		if ( has_action( 'woocommerce_credit_card_form_start' ) ) {
			_deprecated_hook( 'woocommerce_credit_card_form_start', '2.0.0', 'chip_credit_card_form_start' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			do_action( 'woocommerce_credit_card_form_start', $this->id );
		}
		do_action( 'chip_credit_card_form_start', $this->id );
		?>
		<?php
		$allowed_html = array(
			'p'     => array(
				'class' => array(),
				'id'    => array(),
			),
			'label' => array(
				'for'   => array(),
				'class' => array(),
			),
			'span'  => array(
				'class'       => array(),
				'aria-hidden' => array(),
			),
			'input' => array(
				'id'               => array(),
				'class'            => array(),
				'type'             => array(),
				'inputmode'        => array(),
				'autocomplete'     => array(),
				'maxlength'        => array(),
				'placeholder'      => array(),
				'style'            => array(),
				'name'             => array(),
				'value'            => array(),
				'aria-required'    => array(),
				'data-placeholder' => array(),
			),
		);
		foreach ( $fields as $field ) {
			echo wp_kses( $field, $allowed_html );
		}
		?>
		<?php
		if ( has_action( 'woocommerce_credit_card_form_end' ) ) {
			_deprecated_hook( 'woocommerce_credit_card_form_end', '2.0.0', 'chip_credit_card_form_end' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			do_action( 'woocommerce_credit_card_form_end', $this->id );
		}
		do_action( 'chip_credit_card_form_end', $this->id );
		?>
			<div class="clear"></div>
		</fieldset>
		<?php

		$this->save_payment_method_checkbox();

		if ( $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
			echo '<fieldset>' . wp_kses( $cvc_field, $allowed_html ) . '</fieldset>';
		}
	}

	/**
	 * Get list of available payment methods.
	 *
	 * @return array
	 */
	public function get_payment_method_list() {
		return array(
			'fpx'             => 'FPX',
			'fpx_b2b1'        => 'FPX B2B1',
			'mastercard'      => 'Mastercard',
			'maestro'         => 'Maestro',
			'visa'            => 'Visa',
			'mpgs_google_pay' => 'Google Pay',
			'mpgs_apple_pay'  => 'Apple Pay',
			'razer_atome'     => 'Razer Atome',
			'razer_grabpay'   => 'Razer Grabpay',
			'razer_maybankqr' => 'Razer Maybankqr',
			'razer_shopeepay' => 'Razer Shopeepay',
			'razer_tng'       => 'Razer Tng',
			'duitnow_qr'      => 'Duitnow QR',
		);
	}

	/**
	 * Add processing fee items to order.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function add_item_order_fee( &$order ) {
		foreach ( $order->get_items( 'fee' ) as $item_id => $item_value ) {
			if ( in_array( $item_value->get_name( 'chip_view' ), array( 'Fixed Processing Fee', 'Variable Processing Fee' ), true ) ) {
				$order->remove_item( $item_id );
			}
		}

		if ( has_action( 'wc_' . $this->id . '_before_add_item_order_fee' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_before_add_item_order_fee', '2.0.0', 'chip_' . $this->id . '_before_add_item_order_fee' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			do_action( 'wc_' . $this->id . '_before_add_item_order_fee', $order, $this );
		}
		do_action( 'chip_' . $this->id . '_before_add_item_order_fee', $order, $this );
		if ( $this->fixed_charges > 0 ) {
			$item_fee = new WC_Order_Item_Fee();

			$item_fee->set_name( 'Fixed Processing Fee' );
			$item_fee->set_amount( $this->fixed_charges / 100 );
			$item_fee->set_total( $this->fixed_charges / 100 );
			$item_fee->set_order_id( $order->get_id() );
			$item_fee->save();
			$order->add_item( $item_fee );

			$order->update_meta_data( '_chip_fixed_processing_fee', $item_fee->get_id() );
		}

		if ( $this->percent_charges > 0 ) {
			$item_fee = new WC_Order_Item_Fee();

			$item_fee->set_name( 'Variable Processing Fee' );
			$item_fee->set_amount( $order->get_total() * ( $this->percent_charges / 100 ) / 100 );
			$item_fee->set_total( $order->get_total() * ( $this->percent_charges / 100 ) / 100 );
			$item_fee->set_order_id( $order->get_id() );
			$item_fee->save();
			$order->add_item( $item_fee );

			$order->update_meta_data( '_chip_variable_processing_fee', $item_fee->get_id() );
		}

		$order->calculate_totals();
		$order->save();

		if ( has_action( 'wc_' . $this->id . '_after_add_item_order_fee' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_after_add_item_order_fee', '2.0.0', 'chip_' . $this->id . '_after_add_item_order_fee' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			do_action( 'wc_' . $this->id . '_after_add_item_order_fee', $order, $this );
		}
		do_action( 'chip_' . $this->id . '_after_add_item_order_fee', $order, $this );
	}

	/**
	 * Get the payment method whitelist.
	 *
	 * @return array
	 */
	public function get_payment_method_whitelist() {
		return $this->payment_method_whitelist;
	}

	/**
	 * Get bypass chip setting.
	 *
	 * @return string
	 */
	public function get_bypass_chip() {
		return $this->bypass_chip;
	}

	/**
	 * Get payment method for recurring payments.
	 *
	 * @return array|null
	 */
	public function get_payment_method_for_recurring() {

		$pmw = $this->get_payment_method_whitelist();
		if ( is_countable( $pmw ) && count( $pmw ) >= 1 ) {
			return $pmw;
		} elseif ( $this->supports( 'tokenization' ) ) {
			return array( 'visa', 'mastercard' ); // Return the most generic card payment method.
		}

		return null;
	}

	/**
	 * Maybe delete payment token if invalid.
	 *
	 * @param array $charge_payment Charge payment response.
	 * @param int   $token_id       Token ID.
	 * @return void
	 */
	public function maybe_delete_payment_token( $charge_payment, $token_id ) {
		if ( is_array( $charge_payment ) && array_key_exists( '__all__', $charge_payment ) ) {
			if ( is_array( $charge_payment['__all__'] ) ) {
				foreach ( $charge_payment['__all__'] as $errors ) {
					if ( isset( $errors['code'] ) && 'invalid_recurring_token' === $errors['code'] ) {
						WC_Payment_Tokens::delete( $token_id );
					}
				}
			}
		}
	}

	/**
	 * Check if order contains pre-order.
	 *
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	public function order_contains_pre_order( $order ) {
		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			if ( WC_Pre_Orders_Order::order_contains_pre_order( $order ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if order requires payment tokenization.
	 *
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	public function order_requires_payment_tokenization( $order ) {
		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			if ( WC_Pre_Orders_Order::order_requires_payment_tokenization( $order ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Process pre-order payments.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function process_pre_order_payments( $order ) {
		$tokens = WC_Payment_Tokens::get_order_tokens( $order->get_id() );
		if ( empty( $tokens ) ) {
			$order->update_status( 'failed' );
			$order->add_order_note( __( 'No card token available to charge.', 'chip-for-woocommerce' ) );
			return;
		}

		$callback_url = add_query_arg( array( 'id' => $order->get_id() ), WC()->api_request_url( $this->id ) );
		if ( defined( 'WC_CHIP_OLD_URL_SCHEME' ) && WC_CHIP_OLD_URL_SCHEME ) {
			$callback_url = home_url( '/?wc-api=' . get_class( $this ) . '&id=' . $order->get_id() );
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
		}
		$total_pre_order_fee = WC_Pre_Orders_Product::get_pre_order_fee( $product );

		// TODO: Check if still require to minus total_pre_order_fee.
		$total = absint( $order->get_total() ) - absint( $total_pre_order_fee );

		$params = array(
			'success_callback' => $callback_url,
			'send_receipt'     => false,
			'creator_agent'    => 'WooCommerce: ' . WC_CHIP_MODULE_VERSION,
			'reference'        => $order->get_id(),
			'platform'         => 'woocommerce',
			'due'              => $this->get_due_timestamp(),
			'brand_id'         => $this->brand_id,
			'client'           => array(
				'email'     => $order->get_billing_email(),
				'full_name' => $this->filter_customer_full_name( trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ),
			),
			'purchase'         => array(
				'timezone'       => $this->purchase_time_zone,
				'currency'       => $order->get_currency(),
				'language'       => $this->get_language(),
				'due_strict'     => 'yes' === $this->due_strict,
				'total_override' => round( $total * 100 ),
				'products'       => array(),
			),
		);

		$items = $order->get_items();

		foreach ( $items as $item ) {
			$price = round( $item->get_total() * 100 );
			$qty   = $item->get_quantity();

			if ( $price < 0 ) {
				$price = 0;
			}

			$params['purchase']['products'][] = array(
				'name'     => substr( $item->get_name(), 0, 256 ),
				'price'    => round( $price / $qty ),
				'quantity' => $qty,
			);
		}

		if ( has_filter( 'wc_' . $this->id . '_purchase_params' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_purchase_params', '2.0.0', 'chip_' . $this->id . '_purchase_params' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$params = apply_filters( 'wc_' . $this->id . '_purchase_params', $params, $this );
		}
		$params = apply_filters( 'chip_' . $this->id . '_purchase_params', $params, $this );

		$chip    = $this->api();
		$payment = $chip->create_payment( $params );

		$order->add_order_note(
			/* translators: %1$s: CHIP Purchase ID */
			sprintf( __( 'Payment attempt with CHIP. Purchase ID: %1$s', 'chip-for-woocommerce' ), $payment['id'] )
		);

		$order->update_meta_data( '_' . $this->id . '_purchase', $payment );
		$order->save();

		if ( has_action( 'wc_' . $this->id . '_chip_purchase' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $this->id is not output.
			_deprecated_hook( 'wc_' . $this->id . '_chip_purchase', '2.0.0', 'chip_' . $this->id . '_chip_purchase' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			do_action( 'wc_' . $this->id . '_chip_purchase', $payment, $order->get_id() );
		}
		do_action( 'chip_' . $this->id . '_chip_purchase', $payment, $order->get_id() );

		$token = new WC_Payment_Token_CC();
		foreach ( $tokens as $key => $t ) {
			if ( $t->get_gateway_id() === $this->id ) {
				$token = $t;
				break;
			}
		}

		$this->get_lock( $order->get_id() );

		$charge_payment = $chip->charge_payment( $payment['id'], array( 'recurring_token' => $token->get_token() ) );

		$this->maybe_delete_payment_token( $charge_payment, $token->get_id() );

		if ( is_array( $charge_payment ) && array_key_exists( '__all__', $charge_payment ) ) {
			$order->update_status( 'failed' );
			$error_messages = array_map(
				function ( $error ) {
					return isset( $error['message'] ) ? $error['message'] : '';
				},
				$charge_payment['__all__']
			);
			$error_details  = implode( '; ', array_filter( $error_messages ) );
			/* translators: %1$s: Error message details */
			$order->add_order_note( sprintf( __( 'Automatic charge attempt failed. Details: %1$s', 'chip-for-woocommerce' ), $error_details ) );
		} elseif ( is_array( $charge_payment ) && 'paid' === $charge_payment['status'] ) {
			$this->payment_complete( $order, $charge_payment );
			/* translators: %s: Transaction ID */
			$order->add_order_note( sprintf( __( 'Payment Successful by tokenization. Transaction ID: %s', 'chip-for-woocommerce' ), $payment['id'] ) );
		} else {
			$order->update_status( 'failed' );
			$order->add_order_note( __( 'Automatic charge attempt failed.', 'chip-for-woocommerce' ) );
		}

		/* translators: %1$s: Token ID */
		$order->add_order_note( sprintf( __( 'Token ID: %1$s', 'chip-for-woocommerce' ), $token->get_token() ) );

		$this->release_lock( $order->get_id() );
	}

	/**
	 * Check if a public key is valid by attempting to parse it.
	 *
	 * @param string $public_key The public key to validate.
	 * @return bool True if the public key is valid, false otherwise.
	 */
	protected function is_valid_public_key( $public_key ) {
		if ( empty( $public_key ) ) {
			return false;
		}

		// Attempt to parse the public key.
		$key = openssl_pkey_get_public( $public_key );

		if ( false === $key ) {
			return false;
		}

		return true;
	}

	/**
	 * Get existing API configurations from other gateway instances.
	 *
	 * Returns an array of gateway IDs and their method titles for gateways
	 * that have both brand_id and secret_key configured.
	 *
	 * @return array Array of gateway_id => method_title pairs.
	 */
	protected function get_existing_api_configurations() {
		$gateway_ids = array(
			'wc_gateway_chip',
			'wc_gateway_chip_2',
			'wc_gateway_chip_3',
			'wc_gateway_chip_4',
			'wc_gateway_chip_5',
			'wc_gateway_chip_6',
		);

		$configurations = array();

		foreach ( $gateway_ids as $gateway_id ) {
			// Skip current gateway.
			if ( $gateway_id === $this->id ) {
				continue;
			}

			$settings = get_option( 'woocommerce_' . $gateway_id . '_settings', array() );

			// Check if this gateway has valid API credentials (public_key must be parseable).
			$has_credentials = ! empty( $settings['brand_id'] ) && ! empty( $settings['secret_key'] );
			$has_valid_key   = isset( $settings['public_key'] ) && $this->is_valid_public_key( $settings['public_key'] );

			if ( $has_credentials && $has_valid_key ) {
				$title                         = ! empty( $settings['title'] ) ? $settings['title'] : $gateway_id;
				$configurations[ $gateway_id ] = $title;
			}
		}

		return $configurations;
	}

	/**
	 * Get API configuration data (brand_id and secret_key) for all configured gateways.
	 *
	 * @return array Array of gateway_id => array('brand_id' => '', 'secret_key' => '') pairs.
	 */
	protected function get_api_configuration_data() {
		$gateway_ids = array(
			'wc_gateway_chip',
			'wc_gateway_chip_2',
			'wc_gateway_chip_3',
			'wc_gateway_chip_4',
			'wc_gateway_chip_5',
			'wc_gateway_chip_6',
		);

		$data = array();

		foreach ( $gateway_ids as $gateway_id ) {
			// Skip current gateway.
			if ( $gateway_id === $this->id ) {
				continue;
			}

			$settings = get_option( 'woocommerce_' . $gateway_id . '_settings', array() );

			// Check if this gateway has valid API credentials (public_key must be parseable).
			$has_credentials = ! empty( $settings['brand_id'] ) && ! empty( $settings['secret_key'] );
			$has_valid_key   = isset( $settings['public_key'] ) && $this->is_valid_public_key( $settings['public_key'] );

			if ( $has_credentials && $has_valid_key ) {
				$data[ $gateway_id ] = array(
					'brand_id'   => $settings['brand_id'],
					'secret_key' => $settings['secret_key'],
				);
			}
		}

		return $data;
	}
}
