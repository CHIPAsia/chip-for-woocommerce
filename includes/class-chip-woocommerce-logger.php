<?php
/**
 * CHIP for WooCommerce Logger
 *
 * Handles logging for CHIP payment gateway.
 *
 * @package CHIP for WooCommerce
 */

/**
 * CHIP Logger class.
 */
class Chip_Woocommerce_Logger {

	/**
	 * WooCommerce logger instance.
	 *
	 * @var WC_Logger
	 */
	private $logger;

	/**
	 * Logger context.
	 *
	 * @var array
	 */
	private $context;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->context = array( 'source' => 'chip-for-woocommerce' );
		$this->logger  = new WC_Logger();
	}

	/**
	 * Log a notice message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	public function log( $message ) {
		try {
			$this->logger->notice( $message, $this->context );
		} catch ( Exception $e ) {
			// Fallback to WordPress error log if WC_Logger fails.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Chip WooCommerce Logger Error: ' . $e->getMessage() . ' - Message: ' . $message );
		}
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	public function info( $message ) {
		try {
			$this->logger->info( $message, $this->context );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Chip WooCommerce Logger Info: ' . $message );
		}
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	public function error( $message ) {
		try {
			$this->logger->error( $message, $this->context );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Chip WooCommerce Logger Error: ' . $message );
		}
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	public function debug( $message ) {
		try {
			$this->logger->debug( $message, $this->context );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Chip WooCommerce Logger Debug: ' . $message );
		}
	}
}
