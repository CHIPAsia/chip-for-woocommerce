<?php
class WC_Chip_Card extends WC_Chip_Gateway {
  public function auto_charge($total_amount, $order) {
    /*
     Make sure to put locking mechanism here
    */
    $chip = $this->chip_api();

    $params = [
      // 'success_callback' => $url . "&action=paid",
      'send_receipt'     => true,
      'creator_agent'    => 'Chip Woocommerce module: ' . WC_CHIP_MODULE_VERSION,
      'reference'        => (string)$order->get_order_number(),
      'platform'         => 'woocommerce',
      'due'              => apply_filters( 'wc_chip_due_timestamp', $this->get_due_timestamp() ),
      'purchase' => [
        'timezone'   => apply_filters( 'wc_chip_purchase_timezone', $this->get_timezone() ),
        "currency"   => apply_filters( 'wc_chip_purchase_currency', $order->get_currency()),
        "language"   => $this->get_language(),
        "due_strict" => apply_filters( 'wc_chip_purchase_due_strict', true ),
        "products"   => [
          [
            'name'     => 'Order #' . $order->get_id() . ' '. home_url(),
            'price'    => apply_filters( 'wc_chip_purchase_products_price', round( $total_amount * 100 ), $order->get_currency()),
            'quantity' => 1,
          ],
        ],
      ],
      'brand_id' => $this->settings['brand-id'],
      'client' => [
        'email'                   => $order->get_billing_email(),
        'phone'                   => $order->get_billing_phone(),
        'full_name'               => substr( $order->get_billing_first_name() . ' '
            . $order->get_billing_last_name(), 0 , 128 ),
        'street_address'          => substr( $order->get_billing_address_1() . ' '
            . $order->get_billing_address_2(), 0, 128 ) ,
        'country'                 => $order->get_billing_country(),
        'city'                    => substr( $order->get_billing_city(), 0, 128 ) ,
        'zip_code'                => $order->get_shipping_postcode(),
        'shipping_street_address' => substr( $order->get_shipping_address_1()
            . ' ' . $order->get_shipping_address_2(), 0, 128 ) ,
        'shipping_country'        => $order->get_shipping_country(),
        'shipping_city'           => substr( $order->get_shipping_city(), 0, 128 ) ,
        'shipping_zip_code'       => $order->get_shipping_postcode(),
      ],
    ];

    $client_with_params = $params['client'];

    unset($params['client']);

    //https://stackoverflow.com/questions/22843504/how-can-i-get-customer-details-from-an-order-in-woocommerce
    $get_client = $chip->get_client_by_email($order->get_user()->get_email());

    // add validation here to return failure if $get_client failed

    $client = $get_client['results'][0];

    $params['client_id'] = $client['id'];

    $payment = $chip->create_payment( $params );

    // this need to be rethink as merchant might have more than 1 gateway that support token
    $token = WC_Payment_Tokens::get_customer_default_token( $order->get_customer_id() );

    $chip->charge_payment($payment['id'], array('recurring_token' => $token->get_token()));
    
    $order->payment_complete($payment['id']);
    $order->add_order_note(
      sprintf( __( 'Payment Successful by tokenization. Transaction ID: %s', 'chip-for-woocommerce' ), $payment['id'] )
    );
  }
}