<?php
// https://woocommerce.com/document/subscriptions/develop/payment-gateway-integration/#section-12
// https://github.com/woocommerce/woocommerce/wiki/Payment-Token-API#adding-payment-token-api-support-to-your-gateway
class WC_Chip_Card extends WC_Chip_Gateway {
  public $id           = "chip_card";
  public $method_title = "CHIP. Accept Payment with Card (Visa/Mastercard)";
  public $has_fields = true;

  public function __construct()
  {
    if ($this->method_description === '') {
      $this->method_description = __('Pay with Online Banking (Business)', 'chip-for-woocommerce');
    };
    parent::__construct();

    $chip_settings = get_option( 'woocommerce_chip_settings', null );

    $this->settings['brand-id']   = $chip_settings['brand-id'];
    $this->settings['secret-key'] = $chip_settings['secret-key'];

    $supports = array( 'tokenization', 'subscriptions', 'subscription_cancellation',  'subscription_suspension',  'subscription_reactivation', 'subscription_amount_changes', 'subscription_date_changes', 'subscription_payment_method_change', 'subscription_payment_method_change_customer', 'subscription_payment_method_change_admin', 'multiple_subscriptions');
    $this->supports = array_merge($this->supports, $supports);

    $this->icon  = plugins_url("../assets/card.png", __FILE__);
    $this->debug = $chip_settings['debug'];
    $this->hid   = 'yes';

    add_action("woocommerce_api_wc_{$this->id}", array(&$this, 'handle_callback'));
    add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'auto_charge'), 10, 2);
  }

  public function init_form_fields()
  {
    parent::init_form_fields();
    $this->form_fields['label'] = array(
      'title'       => __('Change payment method title', 'chip-for-woocommerce'),
      'type'        => 'text',
      'description' => 'Payment method title.',
      'default'     => 'Cedit Card / Debit Card'
    );

    $this->form_fields['method_desc']['description'] = 'Payment method description';
    $this->form_fields['method_desc']['default']     = 'Pay with Credit Card or Debit Card';

    $disabled_inputs = array('hid', 'brand-id', 'secret-key', 'debug');

    foreach($disabled_inputs as $disabled_input) {
      unset($this->form_fields[$disabled_input]);
    }
  }

  public function add_payment_method() {
    $customer = new WC_Customer( get_current_user_id() );

    $url  = add_query_arg(
      array(
        'tokenization' => 'yes',
      ),
      WC()->api_request_url(get_class(new WC_Chip_Card))
    );

    $params = array(
      'payment_method_whitelist' => ['mastercard', 'visa'],
      'success_callback' => $url . '&status=success',
      'success_redirect' => $url . '&status=success',
      'failure_redirect' => $url . '&status=failed',
      'force_recurring' => true,
      'brand_id' => $this->settings['brand-id'],
      'skip_capture' => true,
      'client' => [
        'email' => $customer->get_email(),
        'full_name' => substr( $customer->get_first_name() . ' ' . $customer->get_last_name(), 0 , 128 )
      ],
      'purchase' => [
        'currency' => "MYR",
        'products' => [
          [
            'name' => 'Save card',
            'price' => 0
          ]
        ]
      ],
    );

    $chip = $this->chip_api();
    $get_client = $chip->get_client_by_email($customer->get_email());

    if (is_array($get_client['results']) AND !empty($get_client['results'])) {
      $client = $get_client['results'][0];
    } else {
      $client = $chip->create_client($params['client']);
    }

    unset($params['client']);
    $params['client_id'] = $client['id'];

    $payment = $chip->create_payment( $params );

    WC()->session->set('chip_preauthorize', $payment['id']);

    return array(
      'result'   => 'redirect',
      'redirect' => $payment['checkout_url'],
    );
  }

  // this handle_callback is made solely for processing save card
  public function handle_callback() {
    if ($_GET['tokenization'] != 'yes') {
      exit('Invalid route');
    }

    $status = sanitize_key($_GET['status']);

    if ($status == 'failed') {
      wc_add_notice( __( 'Unable to add payment method to your account.', 'chip-for-woocommerce' ), 'error' );
      wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
      exit;
    }

    $payment_id = WC()->session->get( 'chip_preauthorize' );

    if ( !$payment_id && isset($_SERVER['HTTP_X_SIGNATURE']) ) {
      // since it doesn't get from session, this is callback
      $content = file_get_contents('php://input');

      if (openssl_verify( $content,  base64_decode($_SERVER['HTTP_X_SIGNATURE']), $this->get_public_key(), 'sha256WithRSAEncryption' ) != 1) {
        $message = __('Success callback failed to be processed due to failure in verification.', 'chip-for-woocommerce');
        $this->log_order_info( $message, $order );
        exit( $message );
      }

      $payment    = json_decode($content, true);
      $payment_id = array_key_exists('id', $payment) ? sanitize_key($payment['id']) : '';
    } else if ( $payment_id ) {
      $payment = $this->chip_api()->get_payment($payment_id);
    } else {
      exit( __('Unexpected response', 'chip-for-woocommerce') );
    }

    if ($payment['status'] != 'preauthorized') {
      wc_add_notice( sprintf( '%1$s ' . print_r($payment['transaction_data']['attempts'][0]['error'], true) , __( 'Unable to add payment method to your account.', 'chip-for-woocommerce' )), 'error' );
      wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
      exit;
    }

    $token = new WC_Payment_Token_CC();
    $token->set_token( $payment['id'] );
    $token->set_gateway_id( $this->id );
    $token->set_card_type( $payment['transaction_data']['extra']['card_brand'] );
    $token->set_last4( substr($payment['transaction_data']['extra']['masked_pan'], -4) );
    $token->set_expiry_month( $payment['transaction_data']['extra']['expiry_month'] );
    $token->set_expiry_year( '20' . $payment['transaction_data']['extra']['expiry_year'] );
    $token->set_user_id( get_user_by( 'email', $payment['client']['email'] )->ID );
    
    if ($token->save()) {
      wc_add_notice( __( 'Payment method successfully added.', 'chip-for-woocommerce' ) );
    } else {
      wc_add_notice( __( 'Unable to add payment method to your account.', 'chip-for-woocommerce' ), 'error' );
    }

    wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
    exit;
  }

  public function payment_fields() {
    // TODO: check if merchant do have recurring payment method
    if ( $this->supports( 'tokenization' ) && is_checkout() ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->save_payment_method_checkbox();
		} else {
      parent::payment_fields();
    }
  }

  public function store_recurring_token( $payment ) {
    $token = new WC_Payment_Token_CC();
    $token->set_token( $payment['id'] );
    $token->set_gateway_id( $this->id );
    $token->set_card_type( $payment['transaction_data']['extra']['card_brand'] );
    $token->set_last4( substr($payment['transaction_data']['extra']['masked_pan'], -4) );
    $token->set_expiry_month( $payment['transaction_data']['extra']['expiry_month'] );
    $token->set_expiry_year( '20' . $payment['transaction_data']['extra']['expiry_year'] );
    $token->set_user_id( get_user_by( 'email', $payment['client']['email'] )->ID );
    $token->save();
  }

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