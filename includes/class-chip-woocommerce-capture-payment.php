<?php
/**
 * CHIP for WooCommerce Capture Payment
 *
 * Handles capture payment functionality for delayed capture orders.
 *
 * @package CHIP for WooCommerce
 */

/**
 * CHIP Capture Payment class.
 */
class Chip_Woocommerce_Capture_Payment {

	/**
	 * Singleton instance.
	 *
	 * @var Chip_Woocommerce_Capture_Payment
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @return Chip_Woocommerce_Capture_Payment
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
		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'add_capture_button' ) );
		add_action( 'wp_ajax_chip_capture_payment', array( $this, 'ajax_capture_payment' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_auto_capture_on_status_change' ), 10, 4 );
	}

	/**
	 * Auto-capture payment when order status changes to a paid status.
	 *
	 * @param int       $order_id   Order ID.
	 * @param string    $old_status Old status.
	 * @param string    $new_status New status.
	 * @param \WC_Order $order      Order object.
	 * @return void
	 */
	public function maybe_auto_capture_on_status_change( $order_id, $old_status, $new_status, $order ) {
		// Only process if changing to a paid status.
		if ( ! in_array( $new_status, wc_get_is_paid_statuses(), true ) ) {
			return;
		}

		// Only apply to CHIP orders.
		if ( ! $this->is_chip_order( $order ) ) {
			return;
		}

		// Only capture if order can be captured.
		if ( 'yes' !== $order->get_meta( '_chip_can_void' ) ) {
			return;
		}

		// Don't capture if hold has expired.
		if ( $this->is_hold_expired( $order ) ) {
			$order->add_order_note( __( 'Auto-capture failed: Authorization has expired (older than 30 days).', 'chip-for-woocommerce' ) );
			return;
		}

		// Get the correct gateway instance.
		$payment_method = $order->get_payment_method();
		$gateway        = Chip_Woocommerce::get_chip_gateway_class( $payment_method );

		if ( ! $gateway ) {
			$order->add_order_note( __( 'Auto-capture failed: Payment gateway not found.', 'chip-for-woocommerce' ) );
			return;
		}

		// Get purchase ID.
		$purchase    = $order->get_meta( '_' . $payment_method . '_purchase' );
		$purchase_id = isset( $purchase['id'] ) ? $purchase['id'] : $order->get_transaction_id();

		if ( ! $purchase_id ) {
			$order->add_order_note( __( 'Auto-capture failed: Purchase ID not found.', 'chip-for-woocommerce' ) );
			return;
		}

		// Get the latest order total in cents.
		$amount_in_cents = (int) round( $order->get_total() * 100 );

		// Call CHIP API to capture the payment.
		$chip   = $gateway->api();
		$result = $chip->capture_payment( $purchase_id, array( 'amount' => $amount_in_cents ) );

		if ( is_array( $result ) && isset( $result['id'] ) && 'paid' === $result['status'] ) {
			// Mark order as no longer voidable/capturable.
			$order->update_meta_data( '_chip_can_void', 'no' );
			$order->save();

			// Use gateway's payment_complete method.
			$gateway->payment_complete( $order, $result );

			/* translators: %s: Purchase ID */
			$order->add_order_note( sprintf( __( 'Payment auto-captured on status change. Purchase ID: %s.', 'chip-for-woocommerce' ), $purchase_id ) );
			$order->save();
		} else {
			$error_message = __( 'Auto-capture failed.', 'chip-for-woocommerce' );
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
	 * Authorized payments can only be captured within 30 days.
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
	 * Add Capture button for on-hold orders using CHIP gateway.
	 *
	 * @param \WC_Order $order The order object.
	 * @return void
	 */
	public function add_capture_button( $order ) {
		// Only apply to orders using a CHIP gateway.
		if ( ! $this->is_chip_order( $order ) ) {
			return;
		}

		// Only show for orders that can be captured (payment status is 'hold').
		if ( 'yes' !== $order->get_meta( '_chip_can_void' ) ) {
			return;
		}

		// Check if hold has expired (older than 30 days).
		if ( $this->is_hold_expired( $order ) ) {
			?>
			<button type="button" class="button" disabled title="<?php esc_attr_e( 'Authorization expired after 30 days', 'chip-for-woocommerce' ); ?>">
				<?php esc_html_e( 'Capture Expired', 'chip-for-woocommerce' ); ?>
			</button>
			<?php
			return;
		}

		$order_id = $order->get_id();
		?>
		<button type="button" class="button button-primary chip-capture-payment" data-order-id="<?php echo esc_attr( $order_id ); ?>">
			<?php esc_html_e( 'Capture Payment', 'chip-for-woocommerce' ); ?>
		</button>
		<script type="text/javascript">
			jQuery( function( $ ) {
				$( '.chip-capture-payment' ).on( 'click', function( e ) {
					e.preventDefault();
					var orderId = $( this ).data( 'order-id' );
					var confirmMessage = '<?php echo esc_js( __( 'Are you sure you want to capture this payment? This will charge the authorized amount to the customer\'s card. This action cannot be undone.', 'chip-for-woocommerce' ) ); ?>';
					
					if ( ! confirm( confirmMessage ) ) {
						return;
					}

					var $button = $( this );
					$button.prop( 'disabled', true ).text( '<?php echo esc_js( __( 'Processing...', 'chip-for-woocommerce' ) ); ?>' );

					$.ajax( {
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'chip_capture_payment',
							order_id: orderId,
							security: '<?php echo esc_js( wp_create_nonce( 'chip_capture_payment_' . $order_id ) ); ?>'
						},
						success: function( response ) {
							if ( response.success ) {
								alert( response.data.message );
								location.reload();
							} else {
								alert( response.data.message );
								$button.prop( 'disabled', false ).text( '<?php echo esc_js( __( 'Capture Payment', 'chip-for-woocommerce' ) ); ?>' );
							}
						},
						error: function() {
							alert( '<?php echo esc_js( __( 'An error occurred. Please try again.', 'chip-for-woocommerce' ) ); ?>' );
							$button.prop( 'disabled', false ).text( '<?php echo esc_js( __( 'Capture Payment', 'chip-for-woocommerce' ) ); ?>' );
						}
					} );
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Handle Capture payment AJAX request.
	 *
	 * @return void
	 */
	public function ajax_capture_payment() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified below.
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'chip-for-woocommerce' ) ) );
		}

		// Verify nonce.
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'chip_capture_payment_' . $order_id ) ) {
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

		// Verify order can be captured (payment status is 'hold').
		if ( 'yes' !== $order->get_meta( '_chip_can_void' ) ) {
			wp_send_json_error( array( 'message' => __( 'This order cannot be captured.', 'chip-for-woocommerce' ) ) );
		}

		// Check if hold has expired (older than 30 days).
		if ( $this->is_hold_expired( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Authorization has expired. Payments can only be captured within 30 days of authorization.', 'chip-for-woocommerce' ) ) );
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

		// Get the latest order total in cents.
		$amount_in_cents = (int) round( $order->get_total() * 100 );

		// Call CHIP API to capture the payment using the correct gateway's API.
		$chip   = $gateway->api();
		$result = $chip->capture_payment( $purchase_id, array( 'amount' => $amount_in_cents ) );

		if ( is_array( $result ) && isset( $result['id'] ) && 'paid' === $result['status'] ) {
			// Mark order as no longer voidable/capturable.
			$order->update_meta_data( '_chip_can_void', 'no' );
			$order->save();

			// Use gateway's payment_complete method to ensure all logic is applied (including test mode note).
			$gateway->payment_complete( $order, $result );

			/* translators: %s: Purchase ID */
			$order->add_order_note( sprintf( __( 'Payment captured manually by admin. Purchase ID: %s.', 'chip-for-woocommerce' ), $purchase_id ) );
			$order->save();

			wp_send_json_success( array( 'message' => __( 'Payment has been captured successfully.', 'chip-for-woocommerce' ) ) );
		} else {
			$error_message = __( 'Failed to capture payment.', 'chip-for-woocommerce' );
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

Chip_Woocommerce_Capture_Payment::get_instance();
