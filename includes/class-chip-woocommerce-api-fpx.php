<?php
/**
 * CHIP for WooCommerce FPX API
 *
 * Handles FPX bank status API calls for CHIP payment gateway.
 *
 * @package CHIP for WooCommerce
 */

define( 'WC_CHIP_FPX_ROOT_URL', 'https://api.chip-in.asia/health_check' );

/**
 * CHIP FPX API class.
 */
class Chip_Woocommerce_API_FPX {

	/**
	 * Logger instance.
	 *
	 * @var object
	 */
	public $logger;

	/**
	 * Debug mode flag.
	 *
	 * @var string
	 */
	public $debug;

	/**
	 * Constructor.
	 *
	 * @param object $logger Logger instance.
	 * @param string $debug  Debug mode.
	 */
	public function __construct( $logger, $debug ) {
		$this->logger = $logger;
		$this->debug  = $debug;
	}

	/**
	 * Get FPX B2C bank status.
	 *
	 * @return array|null
	 */
	public function get_fpx() {
		$this->log_info( 'fetch fpx b2c status' );

		return $this->call( 'GET', '/fpx_b2c?time=' . time() );
	}

	/**
	 * Get FPX B2B1 bank status.
	 *
	 * @return array|null
	 */
	public function get_fpx_b2b1() {
		$this->log_info( 'fetch fpx b2b1 status' );

		return $this->call( 'GET', '/fpx_b2b1?time=' . time() );
	}

	/**
	 * Make API call.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  API route.
	 * @param array  $params Request parameters.
	 * @return array|null
	 */
	private function call( $method, $route, $params = array() ) {
		if ( ! empty( $params ) ) {
			$params = wp_json_encode( $params );
		}

		$response = $this->request(
			$method,
			sprintf( '%s%s', WC_CHIP_FPX_ROOT_URL, $route ),
			$params,
			array(
				'Content-type' => 'application/json',
			)
		);

		$this->log_info( sprintf( 'received response: %s', $response ) );

		$result = json_decode( $response, true );

		if ( ! $result ) {
			$this->log_error( 'JSON parsing error/NULL API response' );
			return null;
		}

		if ( ! empty( $result['errors'] ) ) {
			$this->log_error( 'API error', $result['errors'] );
			return null;
		}

		return $result;
	}

	/**
	 * Make HTTP request.
	 *
	 * @param string $method  HTTP method.
	 * @param string $url     Request URL.
	 * @param array  $params  Request parameters.
	 * @param array  $headers Request headers.
	 * @return string
	 */
	private function request( $method, $url, $params = array(), $headers = array() ) {
		$this->log_info(
			sprintf(
				'%s `%s`\n%s\n%s',
				$method,
				$url,
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				print_r( $params, true ),
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				print_r( $headers, true )
			)
		);

		$wp_request = wp_remote_request(
			$url,
			array(
				'method'    => $method,
				'sslverify' => ! defined( 'WC_CHIP_SSLVERIFY_FALSE' ),
				'headers'   => $headers,
				'body'      => $params,
				'timeout'   => 3,
			)
		);

		$response = wp_remote_retrieve_body( $wp_request );

		$code = wp_remote_retrieve_response_code( $wp_request );
		switch ( $code ) {
			case 200:
			case 201:
				break;
			default:
				$this->log_error(
					sprintf( '%s %s: %d', $method, $url, $code ),
					$response
				);
		}

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'wp_remote_request', $response->get_error_message() );
		}

		return $response;
	}

	/**
	 * Log info message.
	 *
	 * @param string $text Log text.
	 * @return void
	 */
	public function log_info( $text ) {
		if ( 'yes' === $this->debug ) {
			$this->logger->log( "INFO: {$text};" );
		}
	}

	/**
	 * Log error message.
	 *
	 * @param string $error_text Error text.
	 * @param mixed  $error_data Error data.
	 * @return void
	 */
	public function log_error( $error_text, $error_data = null ) {
		if ( 'yes' !== $this->debug ) {
			return;
		}

		$error_text = "ERROR: {$error_text};";

		if ( $error_data ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$error_text .= ' ERROR DATA: ' . print_r( $error_data, true ) . ';';
		}

		$this->logger->log( $error_text );
	}
}
