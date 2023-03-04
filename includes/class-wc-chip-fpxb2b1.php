<?php

class WC_Chip_Fpxb2b1 extends WC_Chip_Gateway {
  public $id           = "chip_fpxb2b1";
  public $method_title = "CHIP. Accept Payment with Online Banking (Business)";

  public function __construct()
  {
    if ($this->method_description === '') {
      $this->method_description = __('Pay with Online Banking (Business)', 'chip-for-woocommerce');
    };
    parent::__construct();


    $chip_settings = get_option( 'woocommerce_chip_settings', null );

    $this->settings['brand-id']   = $chip_settings['brand-id'];
    $this->settings['secret-key'] = $chip_settings['secret-key'];

    $this->icon  = plugins_url("../assets/fpx_b2b1.png", __FILE__);
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
      'default'     => 'Online Banking (Business)',
    );

    $this->form_fields['method_desc']['description'] = 'Payment method description';
    $this->form_fields['method_desc']['default']     = 'Online Banking (Business)';

    $disabled_inputs = array('hid', 'brand-id', 'secret-key', 'debug');

    foreach($disabled_inputs as $disabled_input) {
      unset($this->form_fields[$disabled_input]);
    }
  }
}