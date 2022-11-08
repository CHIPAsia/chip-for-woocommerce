<?php

class WC_Chip_Card extends WC_Chip_Gateway {
  public $id           = "chip-card";
  public $method_title = "CHIP. Accept Payment with Card (Visa/Mastercard)";

  public function __construct()
  {
    if ($this->method_description === '') {
      $this->method_description = __('Pay with Online Banking (Business)', 'chip-for-woocommerce');
    };
    parent::__construct();

    $chip_settings = get_option( 'woocommerce_chip_settings', null );

    $this->settings['brand-id']   = $chip_settings['brand-id'];
    $this->settings['secret-key'] = $chip_settings['secret-key'];

    $this->icon  = plugins_url("../assets/card.png", __FILE__);
    $this->debug = $chip_settings['debug'];
    $this->hid   = 'yes';
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
}