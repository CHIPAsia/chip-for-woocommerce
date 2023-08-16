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
    $script_path       = 'assets/js/frontend/blocks.js';
    $script_asset_path = plugin_dir_path( WC_CHIP_FILE ) . 'assets/js/frontend/blocks.asset.php';
    $script_asset      = file_exists( $script_asset_path )
      ? require( $script_asset_path )
      : array(
        'dependencies' => array(),
        'version'      => WC_CHIP_MODULE_VERSION
      );
    $script_url        = WC_CHIP_URL . $script_path;

    wp_register_script(
      "wc-{$this->name}-blocks",
      $script_url,
      $script_asset[ 'dependencies' ],
      $script_asset[ 'version' ],
      true
    );

    wp_localize_script( "wc-{$this->name}-blocks", 'GATEWAY', ['id' => $this->name ] );

    return [ "wc-{$this->name}-blocks" ];
  }

  public function get_payment_method_data() {
    $pm_whitelist = $this->get_setting( 'payment_method_whitelist' );
    $bypass_chip  = $this->get_setting( 'bypass_chip' );
    $js_display = '';

    if ( is_array( $pm_whitelist ) AND count( $pm_whitelist ) == 1 AND $pm_whitelist[0] == 'fpx' AND $bypass_chip == 'yes' ) {
      $js_display = 'fpx';
    }

    if ( is_array( $pm_whitelist ) AND count( $pm_whitelist ) == 1 AND $pm_whitelist[0] == 'fpx_b2b1' AND $bypass_chip == 'yes' ) {
      $js_display = 'fpx_b2b1';
    }

    return [
      'title'       => $this->get_setting( 'title' ),
      'description' => $this->get_setting( 'description' ),
      'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
      'method_name' => $this->name,
      'saved_option' => $this->gateway->supports( 'tokenization' ),
      'save_option' => false,
      'js_display'  => $js_display,
    ];
  }
}
