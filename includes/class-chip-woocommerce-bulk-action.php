<?php
/**
 * CHIP for WooCommerce Bulk Action
 *
 * Handles bulk order actions for CHIP payment gateway.
 *
 * @package CHIP for WooCommerce
 */

/**
 * CHIP Bulk Action class.
 */
class Chip_Woocommerce_Bulk_Action {

	/**
	 * Singleton instance.
	 *
	 * @var Chip_Woocommerce_Bulk_Action
	 */
	private static $instance;

	/**
	 * List table type.
	 *
	 * @var string
	 */
	private $list_table_type = 'shop_order';

	/**
	 * Get singleton instance.
	 *
	 * @return Chip_Woocommerce_Bulk_Action
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
		add_filter( 'bulk_actions-edit-' . $this->list_table_type, array( $this, 'define_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . $this->list_table_type, array( $this, 'handle_bulk_actions' ), 10, 3 );

		// HPOS compatibility.
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'define_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_bulk_actions_hpos' ), 10, 3 );
	}

	/**
	 * Add actions.
	 *
	 * @return void
	 */
	public function add_actions() {
		add_action( 'admin_notices', array( $this, 'bulk_admin_notices' ) );
	}

	/**
	 * Define bulk actions.
	 *
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public function define_bulk_actions( $actions ) {
		$actions['chip_requery'] = __( 'CHIP Requery status', 'chip-for-woocommerce' );

		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Action name.
	 * @param array  $ids         Order IDs.
	 * @return string
	 */
	public function handle_bulk_actions( $redirect_to, $action, $ids ) {
		$ids = array_reverse( array_map( 'absint', $ids ) );
		if ( has_filter( 'wc_chip_bulk_action_ids' ) ) {
			_deprecated_hook( 'wc_chip_bulk_action_ids', '2.0.0', 'chip_bulk_action_ids' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
			$ids = apply_filters( 'wc_chip_bulk_action_ids', $ids, $action, 'order' );
		}
		$ids = apply_filters( 'chip_bulk_action_ids', $ids, $action, 'order' );

		$changed = 0;

		if ( 'chip_requery' === $action ) {
			foreach ( $ids as $id ) {
				$order = wc_get_order( $id );
				if ( ! $order->is_paid() ) {
					$gateway_id      = $order->get_payment_method();
					$wc_gateway_chip = Chip_Woocommerce::get_chip_gateway_class( $gateway_id );
					$purchase        = $order->get_meta( '_' . $gateway_id . '_purchase', true );

					if ( $wc_gateway_chip && $purchase ) {
						$order->add_order_note( __( 'Order status scheduled to requery by admin', 'chip-for-woocommerce' ) );

						WC()->queue()->schedule_single( time(), 'wc_chip_check_order_status', array( $purchase['id'], $id, 8, $gateway_id ), "{$gateway_id}_bulk_requery" );
						if ( has_action( 'wc_chip_bulk_order_requery' ) ) {
							_deprecated_hook( 'wc_chip_bulk_order_requery', '2.0.0', 'chip_bulk_order_requery' );
							// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deprecated hook for backward compatibility.
							do_action( 'wc_chip_bulk_order_requery', $id );
						}
						do_action( 'chip_bulk_order_requery', $id );
						++$changed;
					}
				}
			}
		}

		if ( $changed ) {
			$redirect_to = add_query_arg(
				array(
					'post_type'   => $this->list_table_type,
					'bulk_action' => $action,
					'changed'     => $changed,
					'ids'         => join( ',', $ids ),
				),
				$redirect_to
			);
		}

		return esc_url_raw( $redirect_to );
	}

	/**
	 * Handle bulk actions for HPOS.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Action name.
	 * @param array  $ids         Order IDs.
	 * @return string
	 */
	public function handle_bulk_actions_hpos( $redirect_to, $action, $ids ) {
		$bulk_action = $this->handle_bulk_actions( $redirect_to, $action, $ids );

		// HPOS doesn't require post_type.
		return remove_query_arg( 'post_type', $bulk_action );
	}

	/**
	 * Display bulk admin notices.
	 *
	 * @return void
	 */
	public function bulk_admin_notices() {
		global $post_type, $pagenow;

		// Bail out if not on shop order list page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not required for admin notice display.
		if ( 'edit.php' !== $pagenow || 'shop_order' !== $post_type || ! isset( $_REQUEST['bulk_action'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not required for admin notice display.
		$number = isset( $_REQUEST['changed'] ) ? absint( $_REQUEST['changed'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean.
		$bulk_action = isset( $_REQUEST['bulk_action'] ) ? wc_clean( wp_unslash( $_REQUEST['bulk_action'] ) ) : '';

		if ( 'chip_requery' === $bulk_action ) {
			/* translators: %s: number of orders */
			$message = sprintf( _n( '%s order status scheduled to requery.', '%s order statuses scheduled to requery.', $number, 'chip-for-woocommerce' ), number_format_i18n( $number ) );
			echo '<div class="updated"><p>' . esc_html( $message ) . '</p></div>';
		}
	}
}

Chip_Woocommerce_Bulk_Action::get_instance();
