<?php

class Chip_Woocommerce_Logger {
	private $logger;
	private $context;

	public function __construct() {
		$this->context = array( 'source' => 'chip-for-woocommerce' );
		$this->logger = new WC_Logger();
	}

	public function log( $message ) {
		try {
			$this->logger->notice( $message, $this->context );
		} catch ( Exception $e ) {
			// Fallback to WordPress error log if WC_Logger fails
			error_log( 'Chip WooCommerce Logger Error: ' . $e->getMessage() . ' - Message: ' . $message );
		}
	}

	public function info( $message ) {
		try {
			$this->logger->info( $message, $this->context );
		} catch ( Exception $e ) {
			error_log( 'Chip WooCommerce Logger Info: ' . $message );
		}
	}

	public function error( $message ) {
		try {
			$this->logger->error( $message, $this->context );
		} catch ( Exception $e ) {
			error_log( 'Chip WooCommerce Logger Error: ' . $message );
		}
	}

	public function debug( $message ) {
		try {
			$this->logger->debug( $message, $this->context );
		} catch ( Exception $e ) {
			error_log( 'Chip WooCommerce Logger Debug: ' . $message );
		}
	}
}