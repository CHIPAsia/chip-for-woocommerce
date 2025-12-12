<?php
/**
 * CHIP for WooCommerce Receipt Link
 *
 * Adds CHIP invoice, receipt, and feed buttons to order page.
 *
 * @package CHIP for WooCommerce
 */

/**
 * CHIP Receipt Link class.
 */
class Chip_Woocommerce_Receipt_Link {

	/**
	 * Singleton instance.
	 *
	 * @var Chip_Woocommerce_Receipt_Link
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @return Chip_Woocommerce_Receipt_Link
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
		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'invoice_button' ) );
		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'receipt_button' ) );
		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'view_button' ) );
	}

	/**
	 * Display invoice button.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function invoice_button( $order ) {
		$gateway_id      = $order->get_payment_method();
		$wc_gateway_chip = Chip_Woocommerce::get_chip_gateway_class( $gateway_id );
		$purchase        = $order->get_meta( '_' . $gateway_id . '_purchase', true );

		if ( $wc_gateway_chip && $purchase ) {
			?>
			<a href="https://gate.chip-in.asia/p/<?php echo esc_attr( $purchase['id'] ); ?>/invoice/" target="_blank">
				<button type="button" class="button"><?php esc_html_e( 'CHIP Invoice', 'chip-for-woocommerce' ); ?></button>
			</a>
			<?php
		}
	}

	/**
	 * Display receipt button.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function receipt_button( $order ) {
		if ( ! $order->is_paid() ) {
			return;
		}

		$gateway_id      = $order->get_payment_method();
		$wc_gateway_chip = Chip_Woocommerce::get_chip_gateway_class( $gateway_id );
		$purchase        = $order->get_meta( '_' . $gateway_id . '_purchase', true );

		if ( $wc_gateway_chip && $purchase ) {
			if ( ! in_array( $purchase['status'], array( 'paid', 'cleared', 'settled', 'chargeback', 'pending_refund', 'refunded' ), true ) ) {
				return;
			}
			?>
			<a href="https://gate.chip-in.asia/p/<?php echo esc_attr( $purchase['id'] ); ?>/receipt/" target="_blank">
				<button type="button" class="button"><?php esc_html_e( 'CHIP Receipt', 'chip-for-woocommerce' ); ?></button>
			</a>
			<?php
		}
	}

	/**
	 * Display feed view button.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function view_button( $order ) {
		$gateway_id      = $order->get_payment_method();
		$wc_gateway_chip = Chip_Woocommerce::get_chip_gateway_class( $gateway_id );
		$purchase        = $order->get_meta( '_' . $gateway_id . '_purchase', true );

		if ( $wc_gateway_chip && $purchase ) {
			?>
			<a href="https://gate.chip-in.asia/t/<?php echo esc_attr( $purchase['company_id'] ); ?>/feed/purchase/<?php echo esc_attr( $purchase['id'] ); ?>/"
				target="_blank">
				<button type="button" class="button"><?php esc_html_e( 'CHIP Feed', 'chip-for-woocommerce' ); ?></button>
			</a>
			<?php
		}
	}
}

Chip_Woocommerce_Receipt_Link::get_instance();
