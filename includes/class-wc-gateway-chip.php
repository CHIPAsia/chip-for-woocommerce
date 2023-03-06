<?php

class WC_Gateway_Chip extends WC_Payment_Gateway
{
  public $id; // wc_gateway_chip
  protected $secret_key;
  protected $brand_id;
  protected $due_strict;
  protected $due_str_t;
  protected $purchase_sr;
  protected $purchase_tz;
  protected $update_clie;
  protected $system_url_;
  protected $force_token;
  protected $disable_rec;
  protected $payment_met;
  protected $disable_red;
  protected $disable_cal;
  protected $public_key;
  protected $debug;
  
  protected $cached_api;
  protected $cached_payment_method;

  public function __construct()
  {
    $this->init_id();
    $this->init_icon();
    $this->init_title();
    $this->init_method_title();
    $this->init_method_description();
    $this->init_currency_check();
    $this->init_supports();
    $this->init_has_fields();

    $this->secret_key  = $this->get_option( 'secret_key' );
    $this->brand_id    = $this->get_option( 'brand_id' );
    $this->due_strict  = $this->get_option( 'due_strict', true );
    $this->due_str_t   = $this->get_option( 'due_strict_timing', 60 );
    $this->purchase_sr = $this->get_option( 'purchase_send_receipt', true );
    $this->purchase_tz = $this->get_option( 'purchase_time_zone', 'Asia/Kuala_Lumpur' );
    $this->update_clie = $this->get_option( 'update_client_information' );
    $this->system_url_ = $this->get_option( 'system_url_scheme', 'https' );
    $this->force_token = $this->get_option( 'force_tokenization' );
    $this->disable_rec = $this->get_option( 'disable_recurring_support' );
    $this->payment_met = $this->get_option( 'payment_method_whitelist' );
    $this->disable_red = $this->get_option( 'disable_redirect' );
    $this->disable_cal = $this->get_option( 'disable_callback' );
    $this->debug       = $this->get_option( 'debug' );
    $this->public_key  = $this->get_option( 'public_key' );

    $this->init_form_fields();
    $this->init_settings();
    $this->init_one_time_gateway();
    
    if ( $this->get_option( 'title' ) ) {
      $this->title = $this->get_option( 'title' );  
    }

    if ( $this->get_option( 'method_title' ) ) {
      $this->method_title = $this->get_option( 'method_title' );
    }

    $this->add_actions();
  }

  protected function init_id() {
    $this->id = strtolower( get_class( $this ) );
  }

  protected function init_icon() {
    $logo = $this->get_option( 'display_logo', 'logo' );
    $this->icon = apply_filters( 'wc_chip_load_icon_' . $this->id , plugins_url("assets/{$logo}.png", WC_CHIP_FILE ) );
  }

  protected function init_title() {
    $this->title = __( 'Online Banking / E-Wallet / Credit Card / Debit Card (CHIP)', 'chip-for-woocommerce' );
  }

  protected function init_method_title() {
    if ( $this->id == 'wc_gateway_chip' ) {
      $this->method_title = __('CHIP', 'chip-for-woocommerce');
    } else {
      $this->method_title = sprintf( __( 'CHIP - (%1$s)', 'chip-for-woocommerce'), get_class( $this ) );
    }
  }

  protected function init_method_description() {
    if ( $this->id == 'wc_gateway_chip' ) {
      $this->method_description = __( 'CHIP - Better Payment & Business Solutions', 'chip-for-woocommerce' );
    } else {
      $this->method_description = sprintf( __( 'CHIP - Better Payment & Business Solutions (%1$s)', 'chip-for-woocommerce' ), get_class( $this ) );
    }
  }

  protected function init_currency_check() {
    $woocommerce_currency = get_woocommerce_currency();
    $supported_currencies = apply_filters( 'wc_chip_supported_currencies_' . $this->id, array( 'MYR' ), $this );
    
    if ( !in_array( $woocommerce_currency, $supported_currencies, true ) ){
      $this->enabled = 'no';
    }
  }

  protected function init_supports() {
    $supports = array( 'refunds', 'tokenization', 'subscriptions', 'subscription_cancellation',  'subscription_suspension',  'subscription_reactivation', 'subscription_amount_changes', 'subscription_date_changes', 'subscription_payment_method_change', 'subscription_payment_method_change_customer', 'subscription_payment_method_change_admin', 'multiple_subscriptions' );
    $this->supports = array_merge( $this->supports, $supports );
  }

  protected function init_has_fields() {
    $this->has_fields = true;
  }

  protected function init_one_time_gateway() {
    if ( $this->disable_rec == 'yes') {
      $this->supports = [ 'products', 'refunds' ];
    } elseif ( is_array( $this->payment_met ) AND !empty( $this->payment_met ) ) {
      $one_time_gateway = true;
      foreach( [ 'visa', 'mastercard' ] as $card_network ) {
        if ( in_array( $card_network, $this->payment_met ) ) {
          $one_time_gateway = false;
          break;
        }
      }

      if ( $one_time_gateway ) {
        $this->supports = [ 'products', 'refunds' ];
      }
    }
  }

  public function add_actions() {
    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'auto_charge' ), 10, 2);
    add_action( 'woocommerce_payment_token_deleted', array( $this, 'payment_token_deleted' ), 10, 2 );
    add_action( 'woocommerce_api_' . $this->id, array( $this, 'handle_callback' ) );
    add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array( $this, 'change_failing_payment_method' ), 10, 2 );

    // TODO: Delete in future release
    if ( $this->id == 'wc_gateway_chip' ) {
      add_action( 'woocommerce_api_wc_chip_gateway', array( $this, 'handle_callback' ) );
    }
  }

  public function get_icon() {
    $style = apply_filters( 'wc_chip_get_icon_style', 'max-height: 25px; width: auto', $this );
    $icon = '<img class="chip-for-woocommerce-" ' . $this->id . ' src="' . WC_HTTPS::force_https_url( $this->icon ) . '" alt="' . esc_attr( $this->get_title() ) . '" style="' . esc_attr( $style ) . '" />';
    return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
  }

  protected function api() {
    if ( !$this->cached_api ) {
      $this->cached_api = new Chip_Woocommerce_API(
        $this->secret_key,
        $this->brand_id,
        new Chip_Woocommerce_Logger(),
        $this->debug
      );
    }

    return $this->cached_api;
  }

  private function log_order_info( $msg, $order ) {
    $this->api()->log_info( $msg . ': ' . $order->get_order_number() );
  }

  public function handle_callback() {
    if ( isset( $_GET['tokenization'] ) AND $_GET['tokenization'] == 'yes' ) {
      $this->handle_callback_token();
    } else {
      $this->handle_callback_order();
    }
  }

  public function handle_callback_token() {
    $status = sanitize_key( $_GET['action'] );

    if ( $status == 'failed' ) {
      wc_add_notice( __( 'Unable to add payment method to your account.', 'chip-for-woocommerce' ), 'error' );
      wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
      exit;
    }

    $payment_id = WC()->session->get( 'chip_preauthorize' );

    if ( !$payment_id && isset($_SERVER['HTTP_X_SIGNATURE']) ) {
      $content = file_get_contents( 'php://input' );

      if ( openssl_verify( $content,  base64_decode( $_SERVER['HTTP_X_SIGNATURE'] ), $this->get_public_key(), 'sha256WithRSAEncryption' ) != 1) {
        $message = __( 'Success callback failed to be processed due to failure in verification.', 'chip-for-woocommerce' );
        $this->log_order_info( $message, $order );
        exit( $message );
      }

      $payment    = json_decode( $content, true );
      $payment_id = array_key_exists( 'id', $payment ) ? sanitize_key( $payment['id'] ) : '';
    } else if ( $payment_id ) {
      $payment = $this->api()->get_payment( $payment_id );
    } else {
      exit( __( 'Unexpected response', 'chip-for-woocommerce' ) );
    }

    if ( $payment['status'] != 'preauthorized' ) {
      wc_add_notice( sprintf( '%1$s %2$s' , __( 'Unable to add payment method to your account.', 'chip-for-woocommerce' ), print_r( $payment['transaction_data']['attempts'][0]['error'], true ) ), 'error' );
      wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
      exit;
    }

    if ( $this->store_recurring_token( $payment ) ) {
      wc_add_notice( __( 'Payment method successfully added.', 'chip-for-woocommerce' ) );
    } else {
      wc_add_notice( __( 'Unable to add payment method to your account.', 'chip-for-woocommerce' ), 'error' );
    }

    wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
    exit;
  }

  public function handle_callback_order() {
    $order_id = intval( $_GET['id'] );

    $this->api()->log_info( 'received callback for order id: ' . $order_id );

    $GLOBALS['wpdb']->get_results( "SELECT GET_LOCK('chip_payment_$order_id', 15);" );

    $order = new WC_Order( $order_id );

    $this->log_order_info( 'received success callback', $order );

    $payment_id = WC()->session->get( 'chip_payment_id_' . $order_id );
    if ( !$payment_id AND isset( $_SERVER['HTTP_X_SIGNATURE'] ) ) {
      $content = file_get_contents( 'php://input' );

      if ( openssl_verify( $content,  base64_decode( $_SERVER['HTTP_X_SIGNATURE'] ), $this->get_public_key(), 'sha256WithRSAEncryption' ) != 1 ) {
        $message = __( 'Success callback failed to be processed due to failure in verification.', 'chip-for-woocommerce' );
        $this->log_order_info( $message, $order );
        exit( $message );
      }

      $payment    = json_decode( $content, true );
      $payment_id = array_key_exists( 'id', $payment ) ? sanitize_key( $payment['id'] ) : '';
    } else if ( $payment_id ) {
      $payment = $this->api()->get_payment( $payment_id );
    } else {
      exit( __( 'Unexpected response', 'chip-for-woocommerce' ) );
    }

    if ( $payment['status'] == 'paid' ) {
      if ( !$order->is_paid() ) {
        if ( $payment['is_recurring_token'] ) {
          $token = $this->store_recurring_token( $payment, $order->get_user_id() );

          $this->add_payment_token( $order->get_id(), $token );
        }

        $order->payment_complete( $payment_id );
        $order->add_order_note(
          sprintf( __( 'Payment Successful. Transaction ID: %s', 'chip-for-woocommerce' ), $payment_id )
        );

        if ( $payment['is_test'] == true ) {
          $order->add_order_note(
            sprintf( __( 'The payment (%s) made in test mode where it does not involve real payment.', 'chip-for-woocommerce' ), $payment_id )
          );
        }
      }
      WC()->cart->empty_cart();

      $this->log_order_info( 'payment processed', $order );
    } else {
      if ( !$order->is_paid() ) {
        if ( !empty( $payment['transaction_data']['attempts'] ) AND !empty( $payment_extra = $payment['transaction_data']['attempts'][0]['extra'] ) ) {
          if ( isset($payment_extra['payload']) AND isset($payment_extra['payload']['fpx_debitAuthCode']) ) {
            $debit_auth_code = $payment_extra['payload']['fpx_debitAuthCode'][0];
            $fpx_txn_id = $payment_extra['payload']['fpx_fpxTxnId'][0];
            $fpx_seller_order_no = $payment_extra['payload']['fpx_sellerOrderNo'][0];

            $order->add_order_note(
              sprintf( __( 'FPX Debit Auth Code: %1$s. FPX Transaction ID: %2$s. FPX Seller Order Number: %3$s.','chip-for-woocommerce' ), $debit_auth_code, $fpx_txn_id, $fpx_seller_order_no )
            );
          }
        }

        $order->update_status(
          'wc-failed'
        );
        $this->log_order_info( 'payment not successful', $order );
      }
    }

    $GLOBALS['wpdb']->get_results( "SELECT RELEASE_LOCK('chip_payment_$order_id');" );

    wp_safe_redirect( $this->get_return_url( $order ) );
    exit;
  }

  public function init_form_fields() {
    $should_call_chip = true;
    $available_payment_method = array();
    $available_recurring_payment_method = array();

    foreach ( array( 'page' => 'wc-settings', 'tab' => 'checkout', 'section' => $this->id ) as $key => $value ) {
      if ( !isset( $_GET[$key] ) ) {
        $should_call_chip = false;
        break;
      }


      if ( $_GET[$key] != $value ) {
        $should_call_chip = false;
        break;
      }
    }

    if ( $should_call_chip ) {
      $available_payment_method = $this->get_available_payment_method();
      $available_recurring_payment_method = $this->get_available_recurring_payment_method();
    }

    $this->form_fields['enabled'] = array(
      'title'   => __( 'Enable/Disable', 'chip-for-woocommerce' ),
      'label'   => sprintf( '%1$s %2$s', __( 'Enable', 'chip-for-woocommerce' ), $this->method_title ),
      'type'    => 'checkbox',
      'default' => 'no',
    );

    $this->form_fields['title'] = array(
      'title'       => __( 'Title', 'chip-for-woocommerce' ),
      'type'        => 'text',
      'description' => __( 'This controls the title which the user sees during checkout.', 'chip-for-woocommerce' ),
      'default'     => __( 'Online Banking / E-Wallet / Credit Card / Debit Card (CHIP)', 'chip-for-woocommerce' ),
    );

    $this->form_fields['method_title'] = array(
      'title'       => __( 'Method Title', 'chip-for-woocommerce' ),
      'type'        => 'text',
      'description' => __( 'This controls the title in WooCommerce Admin.', 'chip-for-woocommerce' ),
      'default'     => $this->method_title,
    );

    $this->form_fields['description'] = array(
      'title'       => __( 'Description', 'chip-for-woocommerce' ),
      'type'        => 'text',
      'description' => __( 'This controls the description which the user sees during checkout.', 'chip-for-woocommerce' ),
      'default'     => __( 'Pay with Online Banking / E-Wallet / Credit Card / Debit Card. You will choose your payment option on the next page', 'chip-for-woocommerce' ),
    );

    $this->form_fields['credentials'] = array(
      'title'       => __( 'Credentials', 'chip-for-woocommerce' ),
      'type'        => 'title',
      'description' => __( 'Options to set Brand ID and Secret Key.', 'chip-for-woocommerce' ),
    );

    $this->form_fields['brand_id'] = array(
      'title'       => __( 'Brand ID', 'chip-for-woocommerce' ),
      'type'        => 'text',
      'description' => __( 'Brand ID can be obtained from CHIP Collect Dashboard >> Developers >> Brands', 'chip-for-woocommerce' ),
    );

    $this->form_fields['secret_key'] = array(
      'title'       => __( 'Secret key', 'chip-for-woocommerce' ),
      'type'        => 'text',
      'description' => __( 'Secret key can be obtained from CHIP Collect Dashboard >> Developers >> Keys', 'chip-for-woocommerce' ),
    );

    $this->form_fields['miscellaneous'] = array(
      'title'       => __( 'Miscellaneous', 'chip-for-woocommerce' ),
      'type'        => 'title',
      'description' => __( 'Options to set display logo, due strict, send receipt, time zone, tokenization and payment method whitelist.', 'chip-for-woocommerce' ),
    );

    $this->form_fields['display_logo'] = array(
      'title'       => __( 'Display Logo', 'chip-for-woocommerce' ),
      'type'        => 'select',
      'class'       => 'wc-enhanced-select',
      'description' => sprintf(__('This controls which logo appeared on checkout page. <a target="_blank" href="%s">Logo</a>. <a target="_blank" href="%s">FPX B2C</a>. <a target="_blank" href="%s">FPX B2B1</a>. <a target="_blank" href="%s">Card</a>.', 'bfw' ), WC_CHIP_URL.'assets/logo.png', WC_CHIP_URL.'assets/fpx.png', WC_CHIP_URL.'assets/fpx_b2b1.png', WC_CHIP_URL.'assets/card.png'),
      'default'     => 'logo',
      'options'     => array(
        'logo'     => 'Logo',
        'fpx'      => 'FPX B2C',
        'fpx_b2b1' => 'FPX B2B1',
        'card'     => 'Card',
      ),
    );

    $this->form_fields['due_strict'] = array(
      'title'       => __( 'Due Strict', 'chip-for-woocommerce' ),
      'type'        => 'checkbox',
      'description' => __( 'Enforce due strict payment timeframe to block payment after due strict timing is passed.', 'chip-for-woocommerce' ),
      'default'     => 'yes',
    );

    $this->form_fields['due_strict_timing'] = array(
      'title'       => __( 'Due Strict Timing (minutes)', 'chip-for-woocommerce' ),
      'type'        => 'number',
      'description' => sprintf( __( 'Due strict timing in minutes. Default to hold stock minutes: <code>%1$s</code>. This will only be enforced if Due Strict option is activated.', 'chip-for-woocommerce' ), get_option( 'woocommerce_hold_stock_minutes', '60' ) ),
      'default'     => get_option( 'woocommerce_hold_stock_minutes', '60' ),
    );

    $this->form_fields['purchase_send_receipt'] = array(
      'title'       => __( 'Purchase Send Receipt', 'chip-for-woocommerce' ),
      'type'        => 'checkbox',
      'description' => __( 'Tick to ask CHIP to send receipt upon successful payment. If activated, CHIP will send purchase receipt upon payment completion.', 'chip-for-woocommerce' ),
      'default'     => 'yes',
    );

    $this->form_fields['purchase_time_zone'] = array(
      'title'       => __( 'Purchase Time Zone', 'chip-for-woocommerce' ),
      'type'        => 'select',
      'class'       => 'wc-enhanced-select',
      'description' => __( 'Time zone setting for receipt page.', 'chip-for-woocommerce' ),
      'default'     => 'Asia/Kuala_Lumpur',
      'options'     => $this->get_timezone_list()
    );

    $this->form_fields['update_client_information'] = array(
      'title'       => __( 'Update client information', 'chip-for-woocommerce' ),
      'type'        => 'checkbox',
      'description' => __( 'Tick to update client information on purchase creation.', 'chip-for-woocommerce' ),
      'default'     => 'no',
    );

    $this->form_fields['system_url_scheme'] = array(
      'title'       => __( 'System URL Scheme', 'chip-for-woocommerce' ),
      'type'        => 'select',
      'class'       => 'wc-enhanced-select',
      'description' => __( 'Choose https if you are facing issue with payment status update due to http to https redirection', 'chip-for-woocommerce' ),
      'default'     => 'https',
      'options'     => array(
        'default' => __( 'System Default', 'chip-for-woocommerce' ),
        'https'   => __( 'HTTPS', 'chip-for-woocommerce' ),
      )
    );

    $this->form_fields['disable_recurring_support'] = array(
      'title'       => __( 'Disable card recurring support', 'chip-for-woocommerce' ),
      'type'        => 'checkbox',
      'description' =>__( 'Tick to disable card recurring support.', 'chip-for-woocommerce' ),
      'default'     => 'no',
    );

    $this->form_fields['force_tokenization'] = array(
      'title'       => __( 'Force Tokenization', 'chip-for-woocommerce' ),
      'type'        => 'checkbox',
      'description' =>__( 'Tick to force tokenization if possible.', 'chip-for-woocommerce' ),
      'default'     => 'no',
      'disabled'     => empty( $available_recurring_payment_method )
    );

    $this->form_fields['payment_method_whitelist'] = array(
      'title'       => __( 'Payment Method Whitelist', 'chip-for-woocommerce' ),
      'type'        => 'multiselect',
      'class'       => 'wc-enhanced-select',
      'description' => __( 'Choose payment method to enforce payment method whitelisting if possible.', 'chip-for-woocommerce' ),
      'options'     => $available_payment_method,
      'disabled'    => empty( $available_payment_method )
    );

    $this->form_fields['public_key'] = array(
      'title'       => __( 'Public Key', 'chip-for-woocommerce' ),
      'type'        => 'textarea',
      'description' => __( 'Public key for validating callback will be auto-filled upon successful configuration.', 'chip-for-woocommerce' ),
      'disabled'    => true,
    );

    $this->form_fields['troubleshooting'] = array(
      'title'       => __( 'Troubleshooting', 'chip-for-woocommerce' ),
      'type'        => 'title',
      'description' => __( 'Options to disable redirect, disable callback and turn on debugging.', 'chip-for-woocommerce' ),
    );

    $this->form_fields['disable_redirect'] = array(
      'title'       => __( 'Disable Redirect', 'chip-for-woocommerce' ),
      'type'        => 'checkbox',
      'description' => __( 'Disable redirect for troubleshooting purpose.', 'chip-for-woocommerce' ),
    );

    $this->form_fields['disable_callback'] = array(
      'title'       => __( 'Disable Callback', 'chip-for-woocommerce' ),
      'type'        => 'checkbox',
      'description' => __( 'Disable callback for troubleshooting purpose.', 'chip-for-woocommerce' ),
    );

    $this->form_fields['debug'] = array(
      'title'       => __( 'Debug Log', 'chip-for-woocommerce' ),
      'type'        => 'checkbox',
      'label'       => __( 'Enable logging', 'chip-for-woocommerce' ),
      'default'     => 'no',
      'description' =>
        sprintf( __( 'Log events to <code>%s</code>', 'chip-for-woocommerce' ), wc_get_log_file_path( $this->id ) ),
    );
  }

  private function get_timezone_list() {
    $list_time_zones = DateTimeZone::listIdentifiers( DateTimeZone::ALL );

    $formatted_time_zones = array();
    foreach ( $list_time_zones as $mtz ) {
      $formatted_time_zones[$mtz] = str_replace( "_"," ",$mtz );;
    }
    
    return $formatted_time_zones;
  }

  private function get_available_recurring_payment_method() {
    $available_payment_method = array();

    $chip = $this->api();
    $result = $chip->payment_recurring_methods( get_woocommerce_currency(), $this->get_language(), 200 );

    if ( array_key_exists( 'available_payment_methods', $result ) AND !empty( $result['available_payment_methods'] ) ) {
      foreach( $result['available_payment_methods'] as $apm ) {
        $available_payment_method[$apm] = ucfirst( $apm );
      }
    }

    return $available_payment_method;
  }

  private function get_available_payment_method() {

    if ( !$this->cached_payment_method ) {
      $available_payment_method = array();

      $chip = $this->api();
      $result = $chip->payment_methods( get_woocommerce_currency(), $this->get_language(), 200 );

      if ( array_key_exists( 'available_payment_methods', $result ) AND !empty( $result['available_payment_methods'] ) ) {
        foreach( $result['available_payment_methods'] as $apm ) {
          $available_payment_method[$apm] = ucwords( str_replace( '_', ' ', $apm ) );
        }
      }

      $this->cached_payment_method = $available_payment_method;
    }

    return $this->cached_payment_method;
  }

  public function payment_fields() {
    if ( has_action( 'wc_chip_payment_fields' ) ) {
      do_action( 'wc_chip_payment_fields', $this );
    } elseif ( $this->supports( 'tokenization' ) && is_checkout() ) {
      $this->tokenization_script();
      $this->saved_payment_methods();
      $this->save_payment_method_checkbox();
    } else {
      parent::payment_fields();
    }
  }

  public function get_language() {
    if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
      $ln = ICL_LANGUAGE_CODE;
    } else {
      $ln = get_locale();
    }
    switch ( $ln ) {
    case 'et_EE':
      $ln = 'et';
      break;
    case 'ru_RU':
      $ln = 'ru';
      break;
    case 'lt_LT':
      $ln = 'lt';
      break;
    case 'lv_LV':
      $ln = 'lv';
      break;
    case 'et':
    case 'lt':
    case 'lv':
    case 'ru':
      break;
    default:
      $ln = 'en';
    }
    return $ln;
  }

  public function process_payment( $order_id ) {
    $order = new WC_Order( $order_id );
    
    $callback_url  = add_query_arg( [ 'id' => $order_id ], WC()->api_request_url( $this->id ) );
    if ( defined( 'WC_CHIP_OLD_URL_SCHEME' ) AND WC_CHIP_OLD_URL_SCHEME ) {
      $callback_url = home_url( '/?wc-api=' . get_class( $this ). '&id=' . $order_id );
    }

    $params = [
      'success_callback' => $callback_url,
      'success_redirect' => $callback_url,
      'failure_redirect' => $callback_url,
      'cancel_redirect'  => $callback_url,
      'force_recurring'  => $this->force_token == 'yes',
      'send_receipt'     => $this->purchase_sr == 'yes',
      'creator_agent'    => 'WooCommerce: ' . WC_CHIP_MODULE_VERSION,
      'reference'        => $order->get_id(),
      'platform'         => 'woocommerce',
      'due'              => $this->get_due_timestamp(),
      'purchase' => [
        'timezone'   => $this->purchase_tz,
        'currency'   => $order->get_currency(),
        'language'   => $this->get_language(),
        'due_strict' => $this->due_strict == 'yes',
        'total_override' => round( $order->get_total() * 100 ),
        'products'   => [],
      ],
      'brand_id' => $this->brand_id,
      'client' => [
        'email'                   => $order->get_billing_email(),
        'phone'                   => substr( $order->get_billing_phone(), 0, 32 ),
        'full_name'               => substr( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(), 0 , 128 ),
        'street_address'          => substr( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(), 0, 128 ) ,
        'country'                 => substr( $order->get_billing_country(), 0, 2 ),
        'city'                    => substr( $order->get_billing_city(), 0, 128 ) ,
        'zip_code'                => substr( $order->get_shipping_postcode(), 0, 32 ),
        'shipping_street_address' => substr( $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(), 0, 128 ) ,
        'shipping_country'        => substr( $order->get_shipping_country(), 0, 2 ),
        'shipping_city'           => substr( $order->get_shipping_city(), 0, 128 ),
        'shipping_zip_code'       => substr( $order->get_shipping_postcode(), 0, 32 ),
      ],
    ];

    $items = $order->get_items();

    foreach ( $items as $item ) {
      $params['purchase']['products'][] = array(
        'name'     => substr( $item->get_name(), 0, 256 ),
        'price'    => round( $item->get_total() * 100 ),
      );
    }

    $chip = $this->api();

    if ( is_user_logged_in() ) {
      $client_with_params = $params['client'];
      unset( $params['client'] );

      $params['client_id'] = get_user_meta( $order->get_user_id(), '_chip_client_id', true );

      if ( empty( $params['client_id'] ) ) {
        $get_client = $chip->get_client_by_email( $order->get_user()->get_email() );

        if ( array_key_exists( '__all__', $get_client ) ) {
          return array(
            'result' => 'failure',
          );
        }

        if ( is_array($get_client['results']) AND !empty( $get_client['results'] ) ) {
          $client = $get_client['results'][0];

          if ($this->update_clie == 'yes') {
            $chip->patch_client( $client['id'], $client_with_params );
          }
        } else {
          $client = $chip->create_client( $client_with_params );
        }

        update_user_meta( $order->get_user_id(), '_chip_client_id', $client['id'] );

        $params['client_id'] = $client['id'];
      }
    }

    if ( is_array( $this->payment_met ) AND !empty( $this->payment_met ) ) {
      $params['payment_method_whitelist'] = $this->payment_met;
    }

    if ( isset( $_POST["wc-{$this->id}-new-payment-method"] ) AND $_POST["wc-{$this->id}-new-payment-method"] == 'true' ) {
      $params['payment_method_whitelist'] = ['visa', 'mastercard'];
      $params['force_recurring'] = true;
    }

    if ( $this->system_url_ == 'https' ) {
      $params['success_callback'] = preg_replace( "/^http:/i", "https:", $params['success_callback'] );
    }

    if ( $this->disable_cal == 'yes' ) {
      unset( $params['success_callback'] );
    }

    if ( $this->disable_red == 'yes' ) {
      unset( $params['success_redirect'] );
    }
    
    $params = apply_filters( 'wc_chip_purchase_params', $params, $this );

    $payment = $chip->create_payment( $params );

    if ( !array_key_exists( 'id', $payment ) ) {
      $this->log_order_info('create payment failed. message: ' . print_r( $payment, true ), $order );
      return array(
        'result' => 'failure',
      );
    }
    
    WC()->session->set( 'chip_payment_id_' . $order_id, $payment['id'] );
    
    $this->log_order_info('got checkout url, redirecting', $order);

    if ( isset( $_POST["wc-{$this->id}-payment-token"] ) AND 'new' !== $_POST["wc-{$this->id}-payment-token"] ) {
      $token_id = wc_clean( $_POST["wc-{$this->id}-payment-token"] );
      $token    = WC_Payment_Tokens::get( $token_id );

      if ( $token->get_user_id() !== get_current_user_id() ) {
        return array( 'result' => 'failure' );
      }

      $this->add_payment_token( $order->get_id(), $token );

      $chip->charge_payment( $payment['id'], array( 'recurring_token' => $token->get_token() ) );
    }
    
    return array(
      'result' => 'success',
      'redirect' => esc_url( $payment['checkout_url'] ),
    );
  }

  public function get_due_timestamp() {
    $due_strict_timing = $this->due_str_t;
    if ( empty( $this->due_str_t ) ) {
      $due_strict_timing = 60;
    }
    return time() + ( absint ( $due_strict_timing ) * 60 );
  }

  public function can_refund_order( $order ) {
    $has_api_creds    = $this->enabled AND $this->secret_key AND $this->brand_id;
    $can_refund_order = $order AND $order->get_transaction_id() AND $has_api_creds;
    
    return apply_filters( 'wc_chip_can_refund_order', $can_refund_order, $order, $this );
  }

  public function process_refund( $order_id, $amount = null, $reason = '' ) {
    $order = wc_get_order( $order_id );

    if ( ! $this->can_refund_order( $order ) ) {
      $this->log_order_info( 'Cannot refund order', $order );
      return new WP_Error( 'error', __( 'Refund failed.', 'chip-for-woocommerce' ) );
    }

    $chip = $this->api();
    $params = [ 'amount' => round( $amount * 100 ) ];

    $result = $chip->refund_payment( $order->get_transaction_id(), $params );
    
    if ( is_wp_error( $result ) || isset( $result['__all__'] ) ) {
      $this->api()->log_error( var_export( $result['__all__'], true ) . ': ' . $order->get_order_number() );
      return new WP_Error( 'error', var_export( $result['__all__'], true ) );
    }

    $this->log_order_info( 'Refund Result: ' . wc_print_r( $result, true ), $order );
    switch ( strtolower( $result['status'] ?? 'failed' ) ) {
      case 'success':
        $refund_amount = round($result['payment']['amount'] / 100, 2) . $result['payment']['currency'];
        $order->add_order_note(
            sprintf( __( 'Refunded %1$s - Refund ID: %2$s', 'chip-for-woocommerce' ), $refund_amount, $result['id'] )
        );
        return true;
    }
    
    return true;
  }

  public function get_public_key() {
    if ( empty( $this->public_key ) ){
      $this->public_key = str_replace( '\n', "\n", $this->api()->public_key() );
      $this->update_option( 'public_key', $this->public_key );
    }

    return $this->public_key;
  }

  public function process_admin_options() {
    parent::process_admin_options();
    $post  = $this->get_post_data();
    
    $brand_id   = wc_clean( $post["woocommerce_{$this->id}_brand_id"] );
    $secret_key = wc_clean( $post["woocommerce_{$this->id}_secret_key"] );

    $chip = $this->api();
    $chip->set_key( $secret_key, $brand_id );
    $public_key = $chip->public_key();

    if ( is_array( $public_key ) ) {
      $this->add_error( sprintf( __( 'Configuration error: %1$s', 'chip-for-woocommerce' ), print_r( $public_key, true ) ) );
      $this->update_option( 'public_key', '' );
      return false;
    }

    $public_key = str_replace( '\n', "\n", $public_key );

    $result = $chip->payment_methods( get_woocommerce_currency(), $this->get_language(), 200 );

    if ( array_key_exists( 'available_payment_methods', $result ) AND empty( $result['available_payment_methods'] ) ) {
      $this->add_error( sprintf( __( 'Configuration error: No payment method available for the brand id: %1$s', 'chip-for-woocommerce' ), $brand_id ) );
      $this->update_option( 'public_key', '' );
      return false;
    }

    $this->update_option( 'public_key', $public_key );

    return true;
  }

  public function auto_charge( $total_amount, $renewal_order ) {
    $renewal_order_id = $renewal_order->get_id();
    if ( empty( $tokens = WC_Payment_Tokens::get_order_tokens( $renewal_order_id ) ) ) {
      $renewal_order->update_status( 'failed' );
      $renewal_order->add_order_note( __( 'No card token available to charge.', 'chip-for-woocommerce' ) );
      return;
    }

    $callback_url  = add_query_arg( [ 'id' => $renewal_order_id ], WC()->api_request_url( $this->id ) );
    if ( defined( 'WC_CHIP_OLD_URL_SCHEME' ) AND WC_CHIP_OLD_URL_SCHEME ) {
      $callback_url = home_url( '/?wc-api=' . get_class( $this ). '&id=' . $renewal_order_id );
    }

    $params = [
      'success_callback' => $callback_url,
      'send_receipt'     => $this->purchase_sr == 'yes',
      'creator_agent'    => 'WooCommerce: ' . WC_CHIP_MODULE_VERSION,
      'reference'        => $renewal_order_id,
      'platform'         => 'woocommerce',
      'due'              => $this->get_due_timestamp(),
      'brand_id'         => $this->brand_id,
      'client_id'        => get_user_meta( $renewal_order->get_user_id(), '_chip_client_id', true ),
      'purchase' => [
        'timezone'   => $this->purchase_tz,
        'currency'   => $renewal_order->get_currency(),
        'language'   => $this->get_language(),
        'due_strict' => $this->due_strict == 'yes',
        'total_override' => round( $total_amount * 100 ),
        'products'   => [],
      ],
    ];

    $items = $renewal_order->get_items();

    foreach ( $items as $item ) {
      $params['purchase']['products'][] = array(
        'name'     => substr( $item->get_name(), 0, 256 ),
        'price'    => round( $item->get_total() * 100 ),
      );
    }

    $chip    = $this->api();
    $payment = $chip->create_payment( $params );

    $token = new WC_Payment_Token_CC;
    foreach ( $tokens as $key => $t ) {
      if ( $t->get_gateway_id() == $this->id ) {
        $token = $t;
        break;
      }
    }

    $GLOBALS['wpdb']->get_results( "SELECT GET_LOCK('chip_payment_$renewal_order_id', 15);" );

    $charge_payment = $chip->charge_payment( $payment['id'], array( 'recurring_token' => $token->get_token() ) );

    if ( array_key_exists( '__all__', $charge_payment ) ){
      $renewal_order->update_status( 'failed' );
      $renewal_order->add_order_note( sprintf( __( 'Automatic charge attempt failed. Details: %1$s', 'chip-for-woocommerce' ), var_export( $charge_payment, true ) ) );
    } elseif ( $charge_payment['status'] == 'paid' ) {
      $renewal_order->payment_complete( $payment['id'] );
      $renewal_order->add_order_note( sprintf( __( 'Payment Successful by tokenization. Transaction ID: %s', 'chip-for-woocommerce' ), $payment['id'] ) );
    } elseif ( $charge_payment['status'] == 'pending_charge' ) {
      $renewal_order->update_status( 'on-hold' );
    } else {
      $renewal_order->update_status( 'failed' );
      $renewal_order->add_order_note( __( 'Automatic charge attempt failed.', 'chip-for-woocommerce' ) );
    }

    $GLOBALS['wpdb']->get_results( "SELECT RELEASE_LOCK('chip_payment_$renewal_order_id');" );
  }

  public function store_recurring_token( $payment = array(), $user_id = '' ) {
    if ( empty ( $user_id ) ) {
      $user_id = get_user_by( 'email', $payment['client']['email'] )->ID;
    }

    $token = new WC_Payment_Token_CC();
    $token->set_token( $payment['id'] );
    $token->set_gateway_id( $this->id );
    $token->set_card_type( $payment['transaction_data']['extra']['card_brand'] );
    $token->set_last4( substr( $payment['transaction_data']['extra']['masked_pan'], -4 ) );
    $token->set_expiry_month( $payment['transaction_data']['extra']['expiry_month'] );
    $token->set_expiry_year( '20' . $payment['transaction_data']['extra']['expiry_year'] );
    $token->set_user_id( $user_id );
    if ( $token->save() ) {
      return $token;
    }
    return false;
  }

  public function add_payment_method() {
    $customer = new WC_Customer( get_current_user_id() );

    $url  = add_query_arg(
      array(
        'tokenization' => 'yes',
      ),
      WC()->api_request_url( $this->id )
    );

    $params = array(
      'payment_method_whitelist' => ['mastercard', 'visa'],
      'success_callback' => $url . '&action=success',
      'success_redirect' => $url . '&action=success',
      'failure_redirect' => $url . '&action=failed',
      'force_recurring' => true,
      'brand_id' => $this->brand_id,
      'skip_capture' => true,
      'client' => [
        'email' => $customer->get_email(),
        'full_name' => substr( $customer->get_first_name() . ' ' . $customer->get_last_name(), 0 , 128 )
      ],
      'purchase' => [
        'currency' => 'MYR',
        'products' => [
          [
            'name' => 'Add payment method',
            'price' => 0
          ]
        ]
      ],
    );

    $chip = $this->api();

    $params['client_id'] = get_user_meta( get_current_user_id(), '_chip_client_id', true );

    if ( empty( $params['client_id'] ) ) {
      $get_client = $chip->get_client_by_email( $customer->get_email() );

      if ( array_key_exists( '__all__', $get_client ) ) {
        return array(
          'result' => 'failure',
        );
      }

      if ( is_array($get_client['results']) AND !empty( $get_client['results'] ) ) {
        $client = $get_client['results'][0];

        if ($this->update_clie == 'yes') {
          $chip->patch_client( $client['id'], $params['client'] );
        }
      } else {
        $client = $chip->create_client( $params['client'] );
      }

      update_user_meta( $order->get_user_id(), '_chip_client_id', $client['id'] );

      $params['client_id'] = $client['id'];
    }

    unset( $params['client'] );

    if ( $this->system_url_ == 'https' ) {
      $params['success_callback'] = preg_replace( "/^http:/i", "https:", $params['success_callback'] );
    }

    if ( $this->disable_cal == 'yes' ) {
      unset( $params['success_callback'] );
    }

    if ( $this->disable_red == 'yes' ) {
      unset( $params['success_redirect'] );
    }

    $payment = $chip->create_payment( $params );

    WC()->session->set( 'chip_preauthorize', $payment['id'] );

    return array(
      'result'   => 'redirect',
      'redirect' => $payment['checkout_url'],
    );
  }

  public function payment_token_deleted( $token_id, $token ) {
    if ( $token->get_gateway_id() != $this->id ) {
      return;
    }

    $chip = $this->api();
    $chip->delete_token( $token->get_token() );
  }

  public function add_payment_token( $order_id, $token ) {
    $data_store = WC_Data_Store::load( 'order' );

    $order = new WC_Order( $order_id );
    $data_store->update_payment_token_ids( $order, array() );
    $order->add_payment_token( $token );

    if ( class_exists( 'WC_Subscriptions' ) ) {
      $subscriptions = wcs_get_subscriptions_for_order( $order_id );

      foreach ( $subscriptions as $subscription ) {
        $data_store->update_payment_token_ids( $subscription, array() );

        $subscription->add_payment_token( $token );
      }
    }
  }

  public function change_failing_payment_method( $subscription, $renewal_order ) {
    $token_ids = $renewal_order->get_payment_tokens();

    if ( empty( $token_ids ) ) {
      return;
    }

    $token = WC_Payment_Tokens::get( current( $token_ids ) );

    if ( empty( $token ) ) {
      return;
    }

    $data_store = WC_Data_Store::load( 'order' );
    $data_store->update_payment_token_ids( $subscription, array() );
    $subscription->add_payment_token( $token );
  }
}
