<?php
/**
 * CHIP for WooCommerce Queue
 *
 * Handles scheduled queue actions for CHIP payment gateway.
 *
 * @package CHIP for WooCommerce
 */

/**
 * CHIP Queue class.
 */
class Chip_Woocommerce_Queue {

	/**
	 * Singleton instance.
	 *
	 * @var Chip_Woocommerce_Queue
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @return Chip_Woocommerce_Queue
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
		$this->add_actions();
	}

	/**
	 * Add actions.
	 *
	 * @return void
	 */
	public function add_actions() {
		add_action( 'wc_chip_check_order_status', array( $this, 'check_order_status' ), 10, 4 );
		add_action( 'wc_chip_delete_payment_token', array( $this, 'delete_payment_token' ), 10, 2 );
	}

	/**
	 * Check order status.
	 *
	 * @param string $purchase_id Purchase ID.
	 * @param int    $order_id    Order ID.
	 * @param int    $attempt     Attempt number.
	 * @param string $gateway_id  Gateway ID.
	 * @return void
	 */
	public function check_order_status( $purchase_id, $order_id, $attempt, $gateway_id ) {
		$wc_gateway_chip = Chip_Woocommerce::get_chip_gateway_class( $gateway_id );

		if ( ! $wc_gateway_chip ) {
			return;
		}

		$wc_gateway_chip->check_order_status( $purchase_id, $order_id, $attempt );
	}

	/**
	 * Delete payment token.
	 *
	 * @param string $purchase_id Purchase ID.
	 * @param string $gateway_id  Gateway ID.
	 * @return void
	 */
	public function delete_payment_token( $purchase_id, $gateway_id ) {
		$wc_gateway_chip = Chip_Woocommerce::get_chip_gateway_class( $gateway_id );

		if ( ! $wc_gateway_chip ) {
			return;
		}

		$wc_gateway_chip->delete_payment_token( $purchase_id );
	}
}

Chip_Woocommerce_Queue::get_instance();
