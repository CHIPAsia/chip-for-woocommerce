<?php

define('WC_CHIP_FPX_ROOT_URL', 'https://api.chip-in.asia/health_check');

class Chip_Woocommerce_API_FPX
{
  public $logger;
  public $debug;

  public function __construct( $logger, $debug ) {
    $this->logger     = $logger;
    $this->debug      = $debug;
  }

  public function get_fpx( ) {
    $this->log_info( 'fetch fpx b2c status' );

    return $this->call( 'GET', '/fpx_b2c?time=' . time() );
  }

  public function get_fpx_b2b1( ) {
    $this->log_info( 'fetch fpx b2b1 status' );

    return $this->call( 'GET', '/fpx_b2b1?time=' . time() );
  }

  private function call( $method, $route, $params = [] ) {
    if ( !empty( $params ) ) {
      $params = json_encode( $params );
    }

    $response = $this->request(
      $method,
      sprintf( '%s%s', WC_CHIP_FPX_ROOT_URL, $route ),
      $params,
      [
        'Content-type' => 'application/json'
      ]
    );
    
    $this->log_info( sprintf( 'received response: %s', $response ) );
    
    $result = json_decode( $response, true );
    
    if ( !$result ) {
      $this->log_error( 'JSON parsing error/NULL API response' );
      return null;
    }

    if ( !empty( $result['errors'] ) ) {
      $this->log_error( 'API error', $result['errors'] );
      return null;
    }

    return $result;
  }

  private function request( $method, $url, $params = [], $headers = [] ) {
    $this->log_info( sprintf(
      '%s `%s`\n%s\n%s',
      $method,
      $url,
      var_export( $params, true ),
      var_export( $headers, true )
    ));

    $wp_request = wp_remote_request( $url, array(
      'method'    => $method,
      'sslverify' => !defined( 'WC_CHIP_SSLVERIFY_FALSE' ),
      'headers'   => $headers,
      'body'      => $params,
      'timeout'   => 3
    ));

    $response = wp_remote_retrieve_body( $wp_request );

    switch ( $code = wp_remote_retrieve_response_code( $wp_request ) ) {
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

  public function log_info( $text ) {
    if ( $this->debug ) {
      $this->logger->log( "INFO: {$text};" );
    }
  }

  public function log_error( $error_text, $error_data = null ) {
    $error_text = "ERROR: {$error_text};";

    if ( $error_data ) {
      $error_text .= ' ERROR DATA: ' . var_export($error_data, true) . ';';
    }

    $this->logger->log( $error_text );
  }
}
