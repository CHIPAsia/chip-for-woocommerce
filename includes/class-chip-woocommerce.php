<?php
/**
 * CHIP for WooCommerce Main Class
 *
 * Main plugin class for CHIP for WooCommerce.
 *
 * @package CHIP for WooCommerce
 */

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
		$includes_dir = plugin_dir_path( WC_CHIP_FILE ) . 'includes/';
		include $includes_dir . 'class-chip-woocommerce-api.php';
		include $includes_dir . 'class-chip-woocommerce-api-fpx.php';
		include $includes_dir . 'class-chip-woocommerce-logger.php';
		include $includes_dir . 'class-wc-gateway-chip.php';
		include $includes_dir . 'class-chip-woocommerce-migration.php';
		include $includes_dir . 'class-chip-woocommerce-queue.php';

		if ( ! defined( 'DISABLE_CLONE_WC_GATEWAY_CHIP' ) ) {
			include $includes_dir . 'class-wc-gateway-chip-2.php';
			include $includes_dir . 'class-wc-gateway-chip-3.php';
			include $includes_dir . 'class-wc-gateway-chip-4.php';
			include $includes_dir . 'class-wc-gateway-chip-5.php';
			include $includes_dir . 'class-wc-gateway-chip-6.php';
		}

		if ( is_admin() ) {
			include $includes_dir . 'class-chip-woocommerce-bulk-action.php';
			include $includes_dir . 'class-chip-woocommerce-receipt-link.php';
			include $includes_dir . 'class-chip-woocommerce-site-health.php';
		}
	}

	/**
	 * Add filters.
	 *
	 * @return void
	 */
	public function add_filters() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		add_filter( 'plugin_action_links_' . WC_CHIP_BASENAME, array( $this, 'setting_link' ) );
	}

	/**
	 * Add actions.
	 *
	 * @return void
	 */
	public function add_actions() {
		add_action( 'woocommerce_payment_token_deleted', array( $this, 'payment_token_deleted' ), 10, 2 );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'block_support' ) );
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
	 * @return WC_Gateway_Chip|false
	 */
	public static function get_chip_gateway_class( $gateway_id ) {
		$wc_payment_gateway = WC_Payment_Gateways::instance();

		$pgs = $wc_payment_gateway->payment_gateways();

		if ( isset( $pgs[ $gateway_id ] ) && is_a( $pgs[ $gateway_id ], 'WC_Gateway_Chip' ) ) {
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
		$methods[] = WC_Gateway_Chip::class;

		if ( ! defined( 'DISABLE_CLONE_WC_GATEWAY_CHIP' ) ) {
			$methods[] = WC_Gateway_Chip_2::class;
			$methods[] = WC_Gateway_Chip_3::class;
			$methods[] = WC_Gateway_Chip_4::class;
			$methods[] = WC_Gateway_Chip_5::class;
			$methods[] = WC_Gateway_Chip_6::class;
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

		if ( defined( 'DISABLE_CLONE_WC_GATEWAY_CHIP' ) ) {
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
			include plugin_dir_path( WC_CHIP_FILE ) . 'includes/blocks/class-wc-gateway-chip-blocks-support.php';
			include plugin_dir_path( WC_CHIP_FILE ) . 'includes/blocks/class-wc-gateway-chip-2-blocks-support.php';
			include plugin_dir_path( WC_CHIP_FILE ) . 'includes/blocks/class-wc-gateway-chip-3-blocks-support.php';
			include plugin_dir_path( WC_CHIP_FILE ) . 'includes/blocks/class-wc-gateway-chip-4-blocks-support.php';
			include plugin_dir_path( WC_CHIP_FILE ) . 'includes/blocks/class-wc-gateway-chip-5-blocks-support.php';
			include plugin_dir_path( WC_CHIP_FILE ) . 'includes/blocks/class-wc-gateway-chip-6-blocks-support.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WC_Gateway_Chip_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_Chip_2_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_Chip_3_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_Chip_4_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_Chip_5_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_Chip_6_Blocks_Support() );
				}
			);
		}
	}
}
