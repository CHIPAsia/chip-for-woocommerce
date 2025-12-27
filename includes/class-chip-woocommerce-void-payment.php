<?php
/**
 * CHIP for WooCommerce Void Payment
 *
 * Handles void payment functionality for delayed capture orders.
 *
 * @package CHIP for WooCommerce
 */

/**
 * CHIP Void Payment class.
 */
class Chip_Woocommerce_Void_Payment {

	/**
	 * Singleton instance.
	 *
	 * @var Chip_Woocommerce_Void_Payment
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @return Chip_Woocommerce_Void_Payment
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
		$this->add_filters();
		$this->add_actions();
	}

	/**
	 * Add filters.
	 *
	 * @return void
	 */
	public function add_filters() {
		add_filter( 'woocommerce_admin_order_should_render_refunds', array( $this, 'maybe_hide_refunds_for_on_hold' ), 10, 3 );
	}

	/**
	 * Add actions.
	 *
	 * @return void
	 */
	public function add_actions() {
		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'add_void_button' ) );
		add_action( 'wp_ajax_chip_void_payment', array( $this, 'ajax_void_payment' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_auto_void_on_status_change' ), 10, 4 );
	}

	/**
	 * Auto-void payment when order status changes to cancelled.
	 *
	 * @param int       $order_id   Order ID.
	 * @param string    $old_status Old status.
	 * @param string    $new_status New status.
	 * @param \WC_Order $order      Order object.
	 * @return void
	 */
	public function maybe_auto_void_on_status_change( $order_id, $old_status, $new_status, $order ) {
		// Only process if changing to cancelled status.
		if ( 'cancelled' !== $new_status ) {
			return;
		}

		// Only apply to CHIP orders.
		if ( ! $this->is_chip_order( $order ) ) {
			return;
		}

		// Only void if order can be voided.
		if ( 'yes' !== $order->get_meta( '_chip_can_void' ) ) {
			return;
		}

		// Don't void if hold has expired.
		if ( $this->is_hold_expired( $order ) ) {
			$order->add_order_note( __( 'Auto-void failed: Authorization has expired (older than 30 days).', 'chip-for-woocommerce' ) );
			return;
		}

		// Get the correct gateway instance.
		$payment_method = $order->get_payment_method();
		$gateway        = Chip_Woocommerce::get_chip_gateway_class( $payment_method );

		if ( ! $gateway ) {
			$order->add_order_note( __( 'Auto-void failed: Payment gateway not found.', 'chip-for-woocommerce' ) );
			return;
		}

		// Get purchase ID.
		$purchase    = $order->get_meta( '_' . $payment_method . '_purchase' );
		$purchase_id = isset( $purchase['id'] ) ? $purchase['id'] : $order->get_transaction_id();

		if ( ! $purchase_id ) {
			$order->add_order_note( __( 'Auto-void failed: Purchase ID not found.', 'chip-for-woocommerce' ) );
			return;
		}

		// Call CHIP API to release the payment.
		$chip   = $gateway->api();
		$result = $chip->release_payment( $purchase_id );

		if ( is_array( $result ) && isset( $result['id'] ) ) {
			// Mark order as no longer voidable.
			$order->update_meta_data( '_chip_can_void', 'no' );
			/* translators: %s: Purchase ID */
			$order->add_order_note( sprintf( __( 'Payment auto-voided on cancellation. Purchase ID: %s. The authorized amount has been released back to the customer.', 'chip-for-woocommerce' ), $purchase_id ) );
			$order->save();
		} else {
			$error_message = __( 'Auto-void failed.', 'chip-for-woocommerce' );
			if ( is_array( $result ) && isset( $result['__all__'] ) ) {
				$messages = array();
				foreach ( $result['__all__'] as $error ) {
					if ( isset( $error['message'] ) ) {
						$messages[] = $error['message'];
					}
				}
				if ( ! empty( $messages ) ) {
					$error_message .= ' ' . implode( ' ', $messages );
				}
			}
			$order->add_order_note( $error_message );
		}
	}

	/**
	 * Check if order uses a CHIP gateway.
	 *
	 * @param \WC_Order $order The order object.
	 * @return bool
	 */
	private function is_chip_order( $order ) {
		return 0 === strpos( $order->get_payment_method(), 'wc_gateway_chip' );
	}

	/**
	 * Check if the hold has expired (older than 30 days).
	 *
	 * Authorized payments can only be voided within 30 days.
	 *
	 * @param \WC_Order $order The order object.
	 * @return bool True if expired, false otherwise.
	 */
	private function is_hold_expired( $order ) {
		$hold_timestamp = $order->get_meta( '_chip_hold_timestamp' );

		if ( empty( $hold_timestamp ) ) {
			// If no timestamp, assume it's not expired (backward compatibility).
			return false;
		}

		$thirty_days_in_seconds = 30 * DAY_IN_SECONDS;
		$time_elapsed           = time() - (int) $hold_timestamp;

		return $time_elapsed > $thirty_days_in_seconds;
	}

	/**
	 * Hide refund UI for on-hold orders using CHIP gateway.
	 *
	 * For delayed capture (authorize only) orders, refunds are not applicable.
	 * The merchant must capture or release the payment first.
	 *
	 * @param bool      $should_render Whether to render the refund UI.
	 * @param int       $order_id      The order ID.
	 * @param \WC_Order $order         The order object.
	 * @return bool
	 */
	public function maybe_hide_refunds_for_on_hold( $should_render, $order_id, $order ) {
		// Only apply to orders using a CHIP gateway.
		if ( ! $this->is_chip_order( $order ) ) {
			return $should_render;
		}

		// Hide refunds for orders that can be voided (payment status is 'hold').
		if ( 'yes' === $order->get_meta( '_chip_can_void' ) ) {
			return false;
		}

		return $should_render;
	}

	/**
	 * Add Void button for on-hold orders using CHIP gateway.
	 *
	 * @param \WC_Order $order The order object.
	 * @return void
	 */
	public function add_void_button( $order ) {
		// Only apply to orders using a CHIP gateway.
		if ( ! $this->is_chip_order( $order ) ) {
			return;
		}

		// Only show for orders that can be voided (payment status is 'hold').
		if ( 'yes' !== $order->get_meta( '_chip_can_void' ) ) {
			return;
		}

		// Check if hold has expired (older than 30 days).
		if ( $this->is_hold_expired( $order ) ) {
			?>
			<button type="button" class="button chip-void-payment chip-void-expired" disabled title="<?php esc_attr_e( 'Authorization expired after 30 days', 'chip-for-woocommerce' ); ?>">
				<?php esc_html_e( 'Void Expired', 'chip-for-woocommerce' ); ?>
			</button>
			<?php
			return;
		}

		$order_id = $order->get_id();
		?>
		<button type="button" class="button chip-void-payment" data-order-id="<?php echo esc_attr( $order_id ); ?>">
			<?php esc_html_e( 'Void Payment', 'chip-for-woocommerce' ); ?>
		</button>
		<script type="text/javascript">
			jQuery( function( $ ) {
				$( '.chip-void-payment' ).on( 'click', function( e ) {
					e.preventDefault();
					var orderId = $( this ).data( 'order-id' );
					var confirmMessage = '<?php echo esc_js( __( 'Are you sure you want to void this payment? This will release the authorized amount back to the customer\'s card and cancel the order. This action cannot be undone.', 'chip-for-woocommerce' ) ); ?>';
					
					if ( ! confirm( confirmMessage ) ) {
						return;
					}

					var $button = $( this );
					$button.prop( 'disabled', true ).text( '<?php echo esc_js( __( 'Processing...', 'chip-for-woocommerce' ) ); ?>' );

					$.ajax( {
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'chip_void_payment',
							order_id: orderId,
							security: '<?php echo esc_js( wp_create_nonce( 'chip_void_payment_' . $order_id ) ); ?>'
						},
						success: function( response ) {
							if ( response.success ) {
								alert( response.data.message );
								location.reload();
							} else {
								alert( response.data.message );
								$button.prop( 'disabled', false ).text( '<?php echo esc_js( __( 'Void Payment', 'chip-for-woocommerce' ) ); ?>' );
							}
						},
						error: function() {
							alert( '<?php echo esc_js( __( 'An error occurred. Please try again.', 'chip-for-woocommerce' ) ); ?>' );
							$button.prop( 'disabled', false ).text( '<?php echo esc_js( __( 'Void Payment', 'chip-for-woocommerce' ) ); ?>' );
						}
					} );
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Handle Void payment AJAX request.
	 *
	 * @return void
	 */
	public function ajax_void_payment() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified below.
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'chip-for-woocommerce' ) ) );
		}

		// Verify nonce.
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'chip_void_payment_' . $order_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'chip-for-woocommerce' ) ) );
		}

		// Check user capability.
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'chip-for-woocommerce' ) ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'chip-for-woocommerce' ) ) );
		}

		// Get the payment method used for this order.
		$payment_method = $order->get_payment_method();

		// Verify this order uses a CHIP gateway.
		if ( 0 !== strpos( $payment_method, 'wc_gateway_chip' ) ) {
			wp_send_json_error( array( 'message' => __( 'This order does not use CHIP payment gateway.', 'chip-for-woocommerce' ) ) );
		}

		// Verify order can be voided (payment status is 'hold').
		if ( 'yes' !== $order->get_meta( '_chip_can_void' ) ) {
			wp_send_json_error( array( 'message' => __( 'This order cannot be voided.', 'chip-for-woocommerce' ) ) );
		}

		// Check if hold has expired (older than 30 days).
		if ( $this->is_hold_expired( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Authorization has expired. Payments can only be voided within 30 days of authorization.', 'chip-for-woocommerce' ) ) );
		}

		// Get the correct gateway instance for this order.
		$gateway = Chip_Woocommerce::get_chip_gateway_class( $payment_method );
		if ( ! $gateway ) {
			wp_send_json_error( array( 'message' => __( 'Payment gateway not found.', 'chip-for-woocommerce' ) ) );
		}

		// Get the purchase ID from order meta using the order's payment method.
		$purchase    = $order->get_meta( '_' . $payment_method . '_purchase' );
		$purchase_id = isset( $purchase['id'] ) ? $purchase['id'] : $order->get_transaction_id();

		if ( ! $purchase_id ) {
			wp_send_json_error( array( 'message' => __( 'Purchase ID not found.', 'chip-for-woocommerce' ) ) );
		}

		// Call CHIP API to release the payment using the correct gateway's API.
		$chip   = $gateway->api();
		$result = $chip->release_payment( $purchase_id );

		if ( is_array( $result ) && isset( $result['id'] ) ) {
			// Mark order as no longer voidable.
			$order->update_meta_data( '_chip_can_void', 'no' );
			// Update order status to cancelled.
			/* translators: %s: Purchase ID */
			$order->update_status( 'cancelled', sprintf( __( 'Payment voided. Purchase ID: %s. The authorized amount has been released back to the customer.', 'chip-for-woocommerce' ), $purchase_id ) );
			$order->save();

			wp_send_json_success( array( 'message' => __( 'Payment has been voided successfully. The authorized amount will be released back to the customer\'s card.', 'chip-for-woocommerce' ) ) );
		} else {
			$error_message = __( 'Failed to void payment.', 'chip-for-woocommerce' );
			if ( is_array( $result ) && isset( $result['__all__'] ) ) {
				$messages = array();
				foreach ( $result['__all__'] as $error ) {
					if ( isset( $error['message'] ) ) {
						$messages[] = $error['message'];
					}
				}
				if ( ! empty( $messages ) ) {
					$error_message .= ' ' . implode( ' ', $messages );
				}
			}
			wp_send_json_error( array( 'message' => $error_message ) );
		}
	}
}

Chip_Woocommerce_Void_Payment::get_instance();
