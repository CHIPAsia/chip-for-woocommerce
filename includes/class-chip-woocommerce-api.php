<?php
/**
 * CHIP for WooCommerce API
 *
 * Handles API calls to CHIP payment gateway.
 *
 * @package CHIP for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// This is CHIP API URL Endpoint as per documented in: https://docs.chip-in.asia.
if ( ! defined( 'CHIP_ROOT_URL' ) ) {
	define( 'CHIP_ROOT_URL', 'https://gate.chip-in.asia/api' );
}

/**
 * CHIP API class.
 */
class Chip_Woocommerce_API {

	/**
	 * Secret key for API authentication.
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Brand ID for API requests.
	 *
	 * @var string
	 */
	public $brand_id;

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
	 * @param string $secret_key Secret key.
	 * @param string $brand_id   Brand ID.
	 * @param object $logger     Logger instance.
	 * @param string $debug      Debug mode.
	 */
	public function __construct( $secret_key, $brand_id, $logger, $debug ) {
		$this->secret_key = $secret_key;
		$this->brand_id   = $brand_id;
		$this->logger     = $logger;
		$this->debug      = $debug;
	}

	/**
	 * Set API credentials.
	 *
	 * @param string $secret_key Secret key.
	 * @param string $brand_id   Brand ID.
	 * @return void
	 */
	public function set_key( $secret_key, $brand_id ) {
		$this->secret_key = $secret_key;
		$this->brand_id   = $brand_id;
	}

	/**
	 * Create a payment.
	 *
	 * @param array $params Payment parameters.
	 * @return array|null
	 */
	public function create_payment( $params ) {
		$this->log_info( 'creating purchase' );

		return $this->call( 'POST', '/purchases/?time=' . time(), $params );
	}

	/**
	 * Create a client.
	 *
	 * @param array $params Client parameters.
	 * @return array|null
	 */
	public function create_client( $params ) {
		$this->log_info( 'creating client' );

		return $this->call( 'POST', '/clients/', $params );
	}

	/**
	 * Delete token.
	 *
	 * @param string $purchase_id Purchase ID.
	 * @return array|null
	 */
	public function delete_token( $purchase_id ) {
		$this->log_info( "delete token: {$purchase_id}" );

		return $this->call( 'POST', "/purchases/$purchase_id/delete_recurring_token/" );
	}

	/**
	 * Capture payment.
	 *
	 * @param string $payment_id Payment ID.
	 * @param array  $params     Capture parameters.
	 * @return array|null
	 */
	public function capture_payment( $payment_id, $params = array() ) {
		$this->log_info( "capture payment: {$payment_id}" );

		return $this->call( 'POST', "/purchases/{$payment_id}/capture/", $params );
	}

	/**
	 * Release payment.
	 *
	 * @param string $payment_id Payment ID.
	 * @return array|null
	 */
	public function release_payment( $payment_id ) {
		$this->log_info( "release payment: {$payment_id}" );

		return $this->call( 'POST', "/purchases/{$payment_id}/release/" );
	}

	/**
	 * Charge payment.
	 *
	 * @param string $payment_id Payment ID.
	 * @param array  $params     Charge parameters.
	 * @return array|null
	 */
	public function charge_payment( $payment_id, $params ) {
		$this->log_info( "charge payment: {$payment_id}" );

		return $this->call( 'POST', "/purchases/{$payment_id}/charge/", $params );
	}

	/**
	 * Get payment methods.
	 *
	 * @param string $currency Currency code.
	 * @param string $language Language code.
	 * @param int    $amount   Amount.
	 * @return array|null
	 */
	public function payment_methods( $currency, $language, $amount ) {
		$this->log_info( 'fetching payment methods' );

		return $this->call(
			'GET',
			"/payment_methods/?brand_id={$this->brand_id}&currency={$currency}&language={$language}&amount={$amount}"
		);
	}

	/**
	 * Get payment recurring methods.
	 *
	 * @param string $currency Currency code.
	 * @param string $language Language code.
	 * @param int    $amount   Amount.
	 * @return array|null
	 */
	public function payment_recurring_methods( $currency, $language, $amount ) {
		$this->log_info( 'fetching payment methods' );

		return $this->call(
			'GET',
			"/payment_methods/?brand_id={$this->brand_id}&currency={$currency}&language={$language}&amount={$amount}&recurring=true"
		);
	}

	/**
	 * Get payment.
	 *
	 * @param string $payment_id Payment ID.
	 * @return array|null
	 */
	public function get_payment( $payment_id ) {
		$this->log_info( sprintf( 'get payment: %s', $payment_id ) );

		// time() is to force fresh instead cache.
		$result = $this->call( 'GET', "/purchases/{$payment_id}/?time=" . time() );
		$this->log_info( sprintf( 'success check result: %s', wc_print_r( $result, true ) ) );

		return $result;
	}

	/**
	 * Refund payment.
	 *
	 * @param string $payment_id Payment ID.
	 * @param array  $params     Refund parameters.
	 * @return array|null
	 */
	public function refund_payment( $payment_id, $params ) {
		$this->log_info( sprintf( 'refunding payment: %s', $payment_id ) );

		$result = $this->call( 'POST', "/purchases/{$payment_id}/refund/", $params );

		$this->log_info( sprintf( 'payment refund result: %s', wc_print_r( $result, true ) ) );

		return $result;
	}

	/**
	 * Get public key.
	 *
	 * @return array|null
	 */
	public function public_key() {
		$this->log_info( 'getting public key' );

		$result = $this->call( 'GET', '/public_key/' );

		$this->log_info( sprintf( 'public key: %s', wc_print_r( $result, true ) ) );

		return $result;
	}

	/**
	 * Get company turnover.
	 *
	 * @return array|null
	 */
	public function turnover() {
		$this->log_info( 'getting company turnover' );

		$result = $this->call( 'GET', '/account/json/turnover/?currency=MYR' );

		return $result;
	}

	/**
	 * Get company balance.
	 *
	 * @return array|null
	 */
	public function balance() {
		$this->log_info( 'getting company balance' );

		$result = $this->call( 'GET', '/account/json/balance/?currency=MYR' );

		return $result;
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
		$secret_key = $this->secret_key;
		if ( ! empty( $params ) ) {
			$params = wp_json_encode( $params );
		}

		$response = $this->request(
			$method,
			sprintf( '%s/v1%s', CHIP_ROOT_URL, $route ),
			$params,
			array(
				'Content-type'  => 'application/json',
				'Authorization' => "Bearer {$secret_key}",
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
				wc_print_r( $params, true ),
				wc_print_r( $headers, true )
			)
		);

		$wp_request = wp_remote_request(
			$url,
			array(
				'method'    => $method,
				'sslverify' => ! defined( 'CHIP_WOOCOMMERCE_SSLVERIFY_FALSE' ),
				'headers'   => $headers,
				'body'      => $params,
				'timeout'   => 10, // Charge card require longer timeout.
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
			$error_text .= ' ERROR DATA: ' . wc_print_r( $error_data, true ) . ';';
		}

		$this->logger->log( $error_text );
	}
}
