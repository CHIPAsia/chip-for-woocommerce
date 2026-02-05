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
	 * wp_safe_redirect() blocks external URLs by default. WooCommerce Subscriptions uses
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
				'permission_callback' => '__return_true',
				'args'                => array(
					'type'       => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return in_array( $param, array( 'fpx_b2c', 'fpx_b2b1', 'razer' ), true );
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

		$banks = array();

		switch ( $type ) {
			case 'fpx_b2c':
				$banks = $gateway_instance->list_fpx_banks();
				unset( $banks[''] );
				break;
			case 'fpx_b2b1':
				$banks = $gateway_instance->list_fpx_b2b1_banks();
				unset( $banks[''] );
				break;
			case 'razer':
				$banks = $gateway_instance->list_razer_ewallets();
				unset( $banks[''] );
				break;
		}

		return new WP_REST_Response( $banks, 200 );
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
