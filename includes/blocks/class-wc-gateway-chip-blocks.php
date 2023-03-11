<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Gateway_Chip_Blocks_Support extends AbstractPaymentMethodType {

  protected $gateway;
  protected $name = 'wc_gateway_chip';

  public function initialize() {
    $this->gateway  = Chip_Woocommerce::get_chip_gateway_class( $this->name );
    $this->settings = $this->gateway->settings;
  }

  public function is_active() {
    return $this->gateway->is_available();
  }

  public function get_payment_method_script_handles() {
    $script_path       = 'assets/js/frontend/'. $this->name.'.js';
    $script_asset_path = plugin_dir_path( WC_CHIP_FILE ) . 'assets/js/frontend/'.$this->name.'.asset.php';
    $script_asset      = file_exists( $script_asset_path )
      ? require( $script_asset_path )
      : array(
        'dependencies' => array(),
        'version'      => WC_CHIP_MODULE_VERSION
      );
    $script_url        = WC_CHIP_URL . $script_path;

    wp_register_script(
      'wc-' . $this->name . '-blocks',
      $script_url,
      $script_asset[ 'dependencies' ],
      $script_asset[ 'version' ],
      true
    );

    return [ 'wc-' . $this->name . '-blocks' ];
  }

  public function get_payment_method_data() {
    return [
      'title'       => $this->get_setting( 'title' ),
      'description' => $this->get_setting( 'description' ),
      'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
      'method_name' => $this->name,
      'save_option' => $this->gateway->supports( 'tokenization' ),
    ];
  }
}
