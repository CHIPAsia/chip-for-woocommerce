<?php
/**
 * CHIP for WooCommerce Site Health
 *
 * Adds CHIP API health check to WordPress Site Health.
 *
 * @package CHIP for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CHIP Site Health class.
 */
class Chip_Woocommerce_Site_Health {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'site_status_tests', array( $this, 'chip_plugin_register_site_health_tests' ) );
	}

	/**
	 * Register CHIP site health tests.
	 *
	 * @param array $tests Site health tests.
	 * @return array
	 */
	public function chip_plugin_register_site_health_tests( $tests ) {
		$tests['direct']['CHIP_plugin_api_status'] = array(
			'test'  => array( $this, 'chip_plugin_check_api_health' ),
			'label' => __( 'CHIP Plugin Connection Status', 'chip-for-woocommerce' ),
		);
		return $tests;
	}

	/**
	 * Check CHIP API health.
	 *
	 * @return array
	 */
	public function chip_plugin_check_api_health() {
		$purchase_api = CHIP_ROOT_URL . '/v1/purchases/';
		$response     = wp_remote_get( $purchase_api );

		$status_code = wp_remote_retrieve_response_code( $response );

		switch ( $status_code ) {
			case 401:
				return $this->response_success();
			default:
				return $this->response_fail();
		}
	}

	/**
	 * Return success response.
	 *
	 * @return array
	 */
	public function response_success() {
		return array(
			'label'       => __( 'CHIP Collect API is connected', 'chip-for-woocommerce' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'CHIP Plugin', 'chip-for-woocommerce' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Connection successful. Your store can accept payments via CHIP. Ensure your payment gateway is enabled in WooCommerce settings.', 'chip-for-woocommerce' )
			),
			'actions'     => sprintf(
				'<a href="%s" class="button">%s</a>',
				admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
				__( 'Go to Payment Settings', 'chip-for-woocommerce' )
			),
			'test'        => 'CHIP_plugin_api_status',
		);
	}

	/**
	 * Return fail response.
	 *
	 * @return array
	 */
	public function response_fail() {
		return array(
			'label'       => __( 'CHIP Collect API connection failed', 'chip-for-woocommerce' ),
			'status'      => 'critical',
			'badge'       => array(
				'label' => __( 'CHIP Plugin', 'chip-for-woocommerce' ),
				'color' => 'red',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Your store cannot accept payments via CHIP. Please check your server\'s internet connectivity or contact your hosting provider.', 'chip-for-woocommerce' )
			),
			'actions'     => '',
			'test'        => 'CHIP_plugin_api_status',
		);
	}
}

add_action(
	'init',
	function () {
		new Chip_Woocommerce_Site_Health();
	}
);
