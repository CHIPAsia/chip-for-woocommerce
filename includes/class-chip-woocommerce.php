<?php
/**
 * CHIP for WooCommerce Main Class
 *
 * Main plugin class for CHIP for WooCommerce.
 *
 * @package CHIP for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main CHIP for WooCommerce class.
 */
class Chip_Woocommerce {

	/**
	 * Singleton instance.
	 *
	 * @var Chip_Woocommerce
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @return Chip_Woocommerce
	 */
	public static function get_instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->includes();
		$this->add_filters();
		$this->add_actions();
	}

	/**
	 * Include required files.
	 *
	 * @return void
	 */
	public function includes() {
		$includes_dir = plugin_dir_path( CHIP_WOOCOMMERCE_FILE ) . 'includes/';
		include $includes_dir . 'class-chip-woocommerce-api.php';
		include $includes_dir . 'class-chip-woocommerce-api-fpx.php';
		include $includes_dir . 'class-chip-woocommerce-logger.php';
		include $includes_dir . 'class-chip-woocommerce-gateway.php';
		include $includes_dir . 'class-chip-woocommerce-queue.php';

		if ( ! defined( 'CHIP_WOOCOMMERCE_DISABLE_GATEWAY_CLONES' ) ) {
			include $includes_dir . 'class-chip-woocommerce-gateway-2.php';
			include $includes_dir . 'class-chip-woocommerce-gateway-3.php';
			include $includes_dir . 'class-chip-woocommerce-gateway-4.php';
			include $includes_dir . 'class-chip-woocommerce-gateway-5.php';
			include $includes_dir . 'class-chip-woocommerce-gateway-6.php';
		}

		if ( is_admin() ) {
			include $includes_dir . 'class-chip-woocommerce-bulk-action.php';
			include $includes_dir . 'class-chip-woocommerce-site-health.php';
			include $includes_dir . 'class-chip-woocommerce-void-payment.php';
			include $includes_dir . 'class-chip-woocommerce-capture-payment.php';
			include $includes_dir . 'class-chip-woocommerce-payment-details.php';
			include $includes_dir . 'class-chip-woocommerce-admin-token.php';
		}
	}

	/**
	 * Add filters.
	 *
	 * @return void
	 */
	public function add_filters() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		add_filter( 'plugin_action_links_' . CHIP_WOOCOMMERCE_BASENAME, array( $this, 'setting_link' ) );
		add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_redirect_hosts' ), 10, 2 );
	}

	/**
	 * Allow redirects to CHIP gateway domain for payment method change and checkout flows.
	 *
	 * The wp_safe_redirect() function blocks external URLs by default. WooCommerce Subscriptions uses
	 * wp_safe_redirect() when redirecting to the gateway after process_payment(), which
	 * would reject the CHIP checkout URL and fall back to wp-admin.
	 *
	 * @param array  $hosts Allowed redirect hosts.
	 * @param string $host  The host of the redirect destination.
	 * @return array
	 */
	public function allowed_redirect_hosts( $hosts, $host ) {
		$chip_suffix = '.chip-in.asia';
		if ( ! empty( $host ) && ( 'chip-in.asia' === $host || substr( $host, -strlen( $chip_suffix ) ) === $chip_suffix ) ) {
			if ( ! in_array( $host, $hosts, true ) ) {
				$hosts[] = $host;
			}
		}
		return $hosts;
	}

	/**
	 * Add actions.
	 *
	 * @return void
	 */
	public function add_actions() {
		add_action( 'woocommerce_payment_token_deleted', array( $this, 'payment_token_deleted' ), 10, 2 );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'block_support' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'admin_notices', array( $this, 'missing_assets_notice' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_checkout_fee' ) );
		add_action( 'wp_footer', array( $this, 'checkout_fee_refresh_script' ) );
	}

	/**
	 * Output a small script that refreshes the legacy checkout order review
	 * when the payment method changes.
	 *
	 * The additional-charges fee is applied via woocommerce_cart_calculate_fees,
	 * which only runs when the cart totals are recalculated. In the legacy
	 * (shortcode) checkout, changing the payment method does NOT trigger an
	 * order-review refresh (only address/shipping changes do), so a fee added
	 * for one gateway would linger after switching to a gateway without fees.
	 * This script triggers update_checkout on payment-method change so the fee
	 * is recalculated immediately. The Blocks checkout already recalculates on
	 * every change and is unaffected.
	 *
	 * @return void
	 */
	public function checkout_fee_refresh_script() {
		if ( ! is_checkout() ) {
			return;
		}
		?>
		<script type="text/javascript">
		( function( $ ) {
			$( document.body ).on( 'change', 'input[name="payment_method"]', function() {
				$( document.body ).trigger( 'update_checkout' );
			} );
		} )( jQuery );
		</script>
		<?php
	}

	/**
	 * Add the additional-charges fee to the cart so it is visible on the
	 * checkout page before the customer pays.
	 *
	 * The fee is only applied when the chosen payment method is a CHIP
	 * gateway and that gateway has additional charges enabled. This mirrors
	 * the fee that add_item_order_fee() applies to the order at
	 * process_payment() time, so the customer sees the exact amount they will
	 * be charged.
	 *
	 * @param WC_Cart $cart Cart object.
	 * @return void
	 */
	public function add_checkout_fee( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$chosen = WC()->session ? WC()->session->get( 'chosen_payment_method' ) : '';
		if ( empty( $chosen ) || 0 !== strpos( $chosen, 'wc_gateway_chip' ) ) {
			return;
		}

		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		if ( empty( $gateways[ $chosen ] ) ) {
			return;
		}

		$gateway = $gateways[ $chosen ];

		if ( 'yes' !== $gateway->get_option( 'enable_additional_charges' ) ) {
			return;
		}

		$fixed_charges   = (int) $gateway->get_option( 'fixed_charges', 100 );
		$percent_charges = (int) $gateway->get_option( 'percent_charges', 0 );

		if ( $fixed_charges > 0 ) {
			$cart->add_fee( __( 'Fixed Processing Fee', 'chip-for-woocommerce' ), $fixed_charges / 100 );
		}

		if ( $percent_charges > 0 ) {
			$cart->add_fee( __( 'Variable Processing Fee', 'chip-for-woocommerce' ), (float) $cart->get_total() * ( $percent_charges / 100 ) / 100 );
		}
	}

	/**
	 * Display admin notice if built assets are missing.
	 *
	 * This warns users who downloaded from GitHub repository directly
	 * instead of from the Releases page.
	 *
	 * @return void
	 */
	public function missing_assets_notice() {
		// Check if the built JS file exists.
		$built_js = plugin_dir_path( CHIP_WOOCOMMERCE_FILE ) . 'assets/js/frontend/blocks_chip_woocommerce_gateway.js';

		if ( file_exists( $built_js ) ) {
			return;
		}

		?>
		<div class="notice notice-error chip-admin-notice chip-missing-assets-notice">
			<p>
				<strong><?php esc_html_e( 'CHIP for WooCommerce:', 'chip-for-woocommerce' ); ?></strong>
				<?php
				printf(
					/* translators: %1$s: Opening link tag, %2$s: Closing link tag */
					esc_html__( 'Required JavaScript files are missing. Please download the plugin from the %1$sReleases page%2$s instead of using "Download ZIP" from the repository. If you are a developer, run %3$s to build the assets.', 'chip-for-woocommerce' ),
					'<a href="https://github.com/CHIPAsia/chip-for-woocommerce/releases/latest" target="_blank">',
					'</a>',
					'<code>npm install && npm run build</code>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'chip/v1',
			'/banks/(?P<type>[a-z0-9_]+)/(?P<gateway_id>[a-z0-9_]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_banks_endpoint' ),
				// Verify the wp_rest nonce (sent as X-WP-Nonce by the Blocks
				// checkout). This keeps the endpoint public for logged-out
				// customers (the wp_rest nonce is session-based and works
				// without auth) while rejecting requests that lack a valid
				// nonce, so an unauthenticated caller can no longer trigger
				// the outbound health-check curl at will.
				'permission_callback' => array( $this, 'banks_endpoint_permission' ),
				'args'                => array(
					'type'       => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return in_array( $param, array( 'fpx_b2c', 'fpx_b2b1', 'razer', 'unified' ), true );
						},
					),
					'gateway_id' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Permission callback for the banks REST endpoint.
	 *
	 * Verifies the wp_rest nonce from the X-WP-Nonce header. The nonce is
	 * session-based, so it works for logged-out checkout customers (the
	 * Blocks checkout localizes it via wp_create_nonce('wp_rest')) while
	 * rejecting requests without a valid nonce.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function banks_endpoint_permission( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( empty( $nonce ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Missing nonce.', 'chip-for-woocommerce' ), array( 'status' => 403 ) );
		}

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Invalid nonce.', 'chip-for-woocommerce' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * REST API endpoint to get bank/ewallet list.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_banks_endpoint( $request ) {
		$type       = $request->get_param( 'type' );
		$gateway_id = $request->get_param( 'gateway_id' );

		// Get the gateway instance.
		$gateways         = WC()->payment_gateways()->payment_gateways();
		$gateway_instance = isset( $gateways[ $gateway_id ] ) ? $gateways[ $gateway_id ] : null;

		if ( ! $gateway_instance || ! ( $gateway_instance instanceof Chip_Woocommerce_Gateway ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid gateway' ), 400 );
		}

		$banks       = array();
		$unavailable = array();

		switch ( $type ) {
			case 'fpx_b2c':
				$banks = $gateway_instance->list_fpx_banks();
				unset( $banks[''] );
				$banks = $this->prefix_bank_tags( $banks, 'fpx' );
				foreach ( $gateway_instance->get_unavailable_fpx_banks() as $code ) {
					$unavailable[] = 'fpx:' . $code;
				}
				break;
			case 'fpx_b2b1':
				$banks = $gateway_instance->list_fpx_b2b1_banks();
				unset( $banks[''] );
				$banks = $this->prefix_bank_tags( $banks, 'fpx_b2b1' );
				foreach ( $gateway_instance->get_unavailable_fpx_b2b1_banks() as $code ) {
					$unavailable[] = 'fpx_b2b1:' . $code;
				}
				break;
			case 'razer':
				$banks = $gateway_instance->list_razer_ewallets();
				unset( $banks[''] );
				$banks = $this->prefix_bank_tags( $banks, 'razer' );
				break;
			case 'unified':
				$banks = $gateway_instance->list_unified_payment_methods();
				unset( $banks[''] );
				foreach ( $gateway_instance->get_unavailable_fpx_banks() as $code ) {
					$unavailable[] = 'fpx:' . $code;
				}
				foreach ( $gateway_instance->get_unavailable_fpx_b2b1_banks() as $code ) {
					$unavailable[] = 'fpx_b2b1:' . $code;
				}
				break;
		}

		return new WP_REST_Response(
			array(
				'banks'       => $banks,
				'unavailable' => $unavailable,
			),
			200
		);
	}

	/**
	 * Prefix bank/e-wallet codes with their method tag so the Blocks dropdown
	 * submits the same tag-encoded value (e.g. 'fpx_b2b1:PBB0234') that
	 * bypass_chip() expects. The single-method REST endpoints previously
	 * returned bare codes ('PBB0234'), which bypass_chip() could not parse
	 * and so never appended the ?preferred= redirect parameter.
	 *
	 * @param array  $banks Bank list keyed by code.
	 * @param string $tag   Method tag to prefix (fpx, fpx_b2b1, razer).
	 * @return array
	 */
	private function prefix_bank_tags( $banks, $tag ) {
		$prefixed = array();
		foreach ( $banks as $code => $label ) {
			$prefixed[ $tag . ':' . $code ] = $label;
		}
		return $prefixed;
	}

	/**
	 * Handle payment token deletion.
	 *
	 * @param int              $token_id Token ID.
	 * @param WC_Payment_Token $token    Token object.
	 * @return void
	 */
	public function payment_token_deleted( $token_id, $token ) {
		$wc_gateway_chip = static::get_chip_gateway_class( $token->get_gateway_id() );

		if ( ! $wc_gateway_chip ) {
			return;
		}

		$wc_gateway_chip->payment_token_deleted( $token_id, $token );
	}

	/**
	 * Get CHIP gateway class by gateway ID.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @return Chip_Woocommerce_Gateway|false
	 */
	public static function get_chip_gateway_class( $gateway_id ) {
		$wc_payment_gateway = WC_Payment_Gateways::instance();

		$pgs = $wc_payment_gateway->payment_gateways();

		if ( isset( $pgs[ $gateway_id ] ) && is_a( $pgs[ $gateway_id ], 'Chip_Woocommerce_Gateway' ) ) {
			return $pgs[ $gateway_id ];
		}

		return false;
	}

	/**
	 * Add CHIP gateway to WooCommerce.
	 *
	 * @param array $methods Payment methods.
	 * @return array
	 */
	public function add_gateways( $methods ) {
		$methods[] = Chip_Woocommerce_Gateway::class;

		if ( ! defined( 'CHIP_WOOCOMMERCE_DISABLE_GATEWAY_CLONES' ) ) {
			$methods[] = Chip_Woocommerce_Gateway_2::class;
			$methods[] = Chip_Woocommerce_Gateway_3::class;
			$methods[] = Chip_Woocommerce_Gateway_4::class;
			$methods[] = Chip_Woocommerce_Gateway_5::class;
			$methods[] = Chip_Woocommerce_Gateway_6::class;
		}

		return $methods;
	}

	/**
	 * Add settings link to plugin actions.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function setting_link( $links ) {
		$url_params = array(
			'page' => 'wc-settings',
			'tab'  => 'checkout',
		);

		if ( defined( 'CHIP_WOOCOMMERCE_DISABLE_GATEWAY_CLONES' ) ) {
			$url_params['section'] = 'wc_gateway_chip';
		}

		$url = add_query_arg( $url_params, admin_url( 'admin.php' ) );

		$new_links = array(
			'settings' => sprintf( '<a href="%1$s">%2$s</a>', $url, esc_html__( 'Settings', 'chip-for-woocommerce' ) ),
		);

		return array_merge( $new_links, $links );
	}

	/**
	 * Load the plugin.
	 *
	 * @return void
	 */
	public static function load() {
		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		static::get_instance();
	}

	/**
	 * Add WooCommerce Blocks support.
	 *
	 * @return void
	 */
	public function block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			include plugin_dir_path( CHIP_WOOCOMMERCE_FILE ) . 'includes/blocks/class-chip-woocommerce-gateway-blocks-support.php';
			include plugin_dir_path( CHIP_WOOCOMMERCE_FILE ) . 'includes/blocks/class-chip-woocommerce-gateway-2-blocks-support.php';
			include plugin_dir_path( CHIP_WOOCOMMERCE_FILE ) . 'includes/blocks/class-chip-woocommerce-gateway-3-blocks-support.php';
			include plugin_dir_path( CHIP_WOOCOMMERCE_FILE ) . 'includes/blocks/class-chip-woocommerce-gateway-4-blocks-support.php';
			include plugin_dir_path( CHIP_WOOCOMMERCE_FILE ) . 'includes/blocks/class-chip-woocommerce-gateway-5-blocks-support.php';
			include plugin_dir_path( CHIP_WOOCOMMERCE_FILE ) . 'includes/blocks/class-chip-woocommerce-gateway-6-blocks-support.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new Chip_Woocommerce_Gateway_Blocks_Support() );
					$payment_method_registry->register( new Chip_Woocommerce_Gateway_2_Blocks_Support() );
					$payment_method_registry->register( new Chip_Woocommerce_Gateway_3_Blocks_Support() );
					$payment_method_registry->register( new Chip_Woocommerce_Gateway_4_Blocks_Support() );
					$payment_method_registry->register( new Chip_Woocommerce_Gateway_5_Blocks_Support() );
					$payment_method_registry->register( new Chip_Woocommerce_Gateway_6_Blocks_Support() );
				}
			);
		}
	}
}
