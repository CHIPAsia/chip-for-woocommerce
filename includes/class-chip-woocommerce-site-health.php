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
			'label' => 'CHIP Plugin Connection Status',
		);
		return $tests;
	}

	/**
	 * Check CHIP API health.
	 *
	 * @return array
	 */
	public function chip_plugin_check_api_health() {
		$purchase_api = WC_CHIP_ROOT_URL . '/v1/purchases/';
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
			'label'       => 'CHIP API is working',
			'status'      => 'good',
			'badge'       => array(
				'label' => 'CHIP Plugin',
				'color' => 'green',
			),
			'description' => sprintf(
				'<p>%s</p>',
				'CHIP API is responding correctly.'
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
			'label'       => 'API Issue Detected',
			'status'      => 'critical',
			'badge'       => array(
				'label' => 'CHIP Plugin',
				'color' => 'red',
			),
			'description' => sprintf(
				'<p>%s</p>',
				'Unable to connect to CHIP API.'
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
