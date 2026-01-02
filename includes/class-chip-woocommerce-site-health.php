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
				'color' => 'green',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Your site can successfully communicate with the CHIP Collect API.', 'chip-for-woocommerce' )
			),
			'actions'     => array(),
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
				__( 'Your site cannot connect to the CHIP Collect API. Please check your server\'s internet connectivity.', 'chip-for-woocommerce' )
			),
			'actions'     => array(),
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
