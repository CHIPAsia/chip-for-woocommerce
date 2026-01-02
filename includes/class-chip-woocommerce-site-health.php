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
		$tests['direct']['CHIP_plugin_api_status']  = array(
			'test'  => array( $this, 'chip_plugin_check_api_health' ),
			'label' => __( 'CHIP Plugin Connection Status', 'chip-for-woocommerce' ),
		);
		$tests['direct']['CHIP_plugin_server_time'] = array(
			'test'  => array( $this, 'chip_plugin_check_server_time' ),
			'label' => __( 'Server Time Accuracy', 'chip-for-woocommerce' ),
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
			'label'       => __( 'CHIP Payment Gateway is connected', 'chip-for-woocommerce' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'CHIP Plugin', 'chip-for-woocommerce' ),
				'color' => 'green',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Your site can successfully communicate with the CHIP Payment Gateway.', 'chip-for-woocommerce' )
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
			'label'       => __( 'CHIP Payment Gateway connection failed', 'chip-for-woocommerce' ),
			'status'      => 'critical',
			'badge'       => array(
				'label' => __( 'CHIP Plugin', 'chip-for-woocommerce' ),
				'color' => 'red',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Your site cannot connect to the CHIP Payment Gateway. Please check your server\'s internet connectivity.', 'chip-for-woocommerce' )
			),
			'actions'     => array(),
			'test'        => 'CHIP_plugin_api_status',
		);
	}

	/**
	 * Check server time accuracy against external time API.
	 *
	 * @return array
	 */
	public function chip_plugin_check_server_time() {
		$response = wp_remote_get(
			'https://timeapi.io/api/Time/current/zone?timeZone=UTC',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->response_time_check_failed();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['dateTime'] ) ) {
			return $this->response_time_check_failed();
		}

		$api_time    = strtotime( $data['dateTime'] );
		$server_time = time();

		if ( false === $api_time ) {
			return $this->response_time_check_failed();
		}

		$offset = $server_time - $api_time;

		// If server is more than 30 seconds behind, show warning.
		if ( $offset < -30 ) {
			return $this->response_time_behind( abs( $offset ) );
		}

		// If server is more than 30 seconds ahead, show warning.
		if ( $offset > 30 ) {
			return $this->response_time_ahead( $offset );
		}

		return $this->response_time_good( $offset );
	}

	/**
	 * Return response when time check failed.
	 *
	 * @return array
	 */
	public function response_time_check_failed() {
		return array(
			'label'       => __( 'Server time check could not be completed', 'chip-for-woocommerce' ),
			'status'      => 'recommended',
			'badge'       => array(
				'label' => __( 'CHIP Plugin', 'chip-for-woocommerce' ),
				'color' => 'orange',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Could not verify server time against external time service. This may be due to network restrictions. If you experience payment expiry issues, contact your hosting provider to verify server time accuracy.', 'chip-for-woocommerce' )
			),
			'actions'     => '',
			'test'        => 'CHIP_plugin_server_time',
		);
	}

	/**
	 * Return response when server time is behind.
	 *
	 * @param int $seconds Number of seconds behind.
	 * @return array
	 */
	public function response_time_behind( $seconds ) {
		return array(
			'label'       => __( 'Server time is behind actual time', 'chip-for-woocommerce' ),
			'status'      => 'critical',
			'badge'       => array(
				'label' => __( 'CHIP Plugin', 'chip-for-woocommerce' ),
				'color' => 'red',
			),
			'description' => sprintf(
				'<p>%s</p><p>%s</p>',
				sprintf(
					/* translators: %d: Number of seconds */
					__( 'Your server time is approximately %d seconds behind the actual time. This can cause payment expiry issues with CHIP payments.', 'chip-for-woocommerce' ),
					$seconds
				),
				__( 'Please contact your hosting provider to synchronize your server clock with an NTP time server, or leave the "Due Strict Timing" field blank in the CHIP gateway settings to disable payment expiry.', 'chip-for-woocommerce' )
			),
			'actions'     => sprintf(
				'<a href="%s">%s</a>',
				admin_url( 'admin.php?page=wc-settings&tab=checkout&section=chip' ),
				__( 'Go to CHIP Settings', 'chip-for-woocommerce' )
			),
			'test'        => 'CHIP_plugin_server_time',
		);
	}

	/**
	 * Return response when server time is ahead.
	 *
	 * @param int $seconds Number of seconds ahead.
	 * @return array
	 */
	public function response_time_ahead( $seconds ) {
		return array(
			'label'       => __( 'Server time is ahead of actual time', 'chip-for-woocommerce' ),
			'status'      => 'recommended',
			'badge'       => array(
				'label' => __( 'CHIP Plugin', 'chip-for-woocommerce' ),
				'color' => 'orange',
			),
			'description' => sprintf(
				'<p>%s</p><p>%s</p>',
				sprintf(
					/* translators: %d: Number of seconds */
					__( 'Your server time is approximately %d seconds ahead of the actual time. While this is less critical than being behind, it may cause minor issues.', 'chip-for-woocommerce' ),
					$seconds
				),
				__( 'Consider contacting your hosting provider to synchronize your server clock with an NTP time server for optimal accuracy.', 'chip-for-woocommerce' )
			),
			'actions'     => '',
			'test'        => 'CHIP_plugin_server_time',
		);
	}

	/**
	 * Return response when server time is accurate.
	 *
	 * @param int $offset Time offset in seconds.
	 * @return array
	 */
	public function response_time_good( $offset ) {
		$offset_text = 0 === $offset
			? __( 'exactly synchronized', 'chip-for-woocommerce' )
			: sprintf(
				/* translators: %d: Number of seconds */
				__( 'within %d seconds', 'chip-for-woocommerce' ),
				abs( $offset )
			);

		return array(
			'label'       => __( 'Server time is accurate', 'chip-for-woocommerce' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'CHIP Plugin', 'chip-for-woocommerce' ),
				'color' => 'green',
			),
			'description' => sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: Offset text */
					__( 'Your server time is %s with the actual time. Payment expiry will work correctly.', 'chip-for-woocommerce' ),
					$offset_text
				)
			),
			'actions'     => '',
			'test'        => 'CHIP_plugin_server_time',
		);
	}
}

add_action(
	'init',
	function () {
		new Chip_Woocommerce_Site_Health();
	}
);
