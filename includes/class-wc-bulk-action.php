<?php

class Chip_Woocommerce_Bulk_Action {
  private static $_instance;
  private $list_table_type = 'shop_order';

  public static function get_instance() {
    if ( static::$_instance == null ) {
      static::$_instance = new static();
    }

    return static::$_instance;
  }

  public function __construct() {
    $this->add_filters();
    $this->add_actions();
  }

  public function add_filters() {
    add_filter( 'bulk_actions-edit-' . $this->list_table_type, array( $this, 'define_bulk_actions' ) );
    add_filter( 'handle_bulk_actions-edit-' . $this->list_table_type, array( $this, 'handle_bulk_actions' ), 10, 3 );
  }

  public function add_actions() {
    add_action( 'admin_notices', array( $this, 'bulk_admin_notices' ) );
  }

  public function define_bulk_actions( $actions ) {
    $actions['chip_requery'] = __( 'CHIP Requery status', 'chip-for-woocommerce' );

    return $actions;
  }

  public function handle_bulk_actions( $redirect_to, $action, $ids ) {
    $ids     = apply_filters( 'wc_chip_bulk_action_ids', array_reverse( array_map( 'absint', $ids ) ), $action, 'order' );

    $changed = 0;

    if ( $action == 'chip_requery' ) {
      foreach ( $ids as $id ) {
        $order = wc_get_order( $id );
        if ( !$order->is_paid() ) {
          $gateway_id = $order->get_payment_method();
          $wc_gateway_chip = Chip_Woocommerce::get_chip_gateway_class( $gateway_id );
          
          if ( $wc_gateway_chip AND ( $purchase = $order->get_meta( '_' . $gateway_id . '_purchase', true ) ) ) {
            $order->add_order_note( __( 'Order status scheduled to requery by admin', 'chip-for-woocommerce' ) );
            
            WC()->queue()->schedule_single( time(), 'wc_chip_check_order_status', array( $purchase['id'], $id, 8, $gateway_id ), "{$gateway_id}_bulk_requery" );
            do_action( 'wc_chip_bulk_order_requery', $id );
            $changed++;
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

  public function bulk_admin_notices() {
    global $post_type, $pagenow;

		// Bail out if not on shop order list page.
		if ( 'edit.php' !== $pagenow || 'shop_order' !== $post_type || ! isset( $_REQUEST['bulk_action'] ) ) { // WPCS: input var ok, CSRF ok.
			return;
		}

    $number      = isset( $_REQUEST['changed'] ) ? absint( $_REQUEST['changed'] ) : 0; // WPCS: input var ok, CSRF ok.
		$bulk_action = wc_clean( wp_unslash( $_REQUEST['bulk_action'] ) ); // WPCS: input var ok, CSRF ok.

    if ( $bulk_action == 'chip_requery' ) {
      $message = sprintf( _n( '%s order status scheduled to requery.', '%s order statuses scheduled to requery.', $number, 'chip-for-woocommerce' ), number_format_i18n( $number ) );
      echo '<div class="updated"><p>' . esc_html( $message ) . '</p></div>';
    }
  }
}

Chip_Woocommerce_Bulk_Action::get_instance();