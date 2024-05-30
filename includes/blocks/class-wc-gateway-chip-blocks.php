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
    $script_path       = 'assets/js/frontend/blocks_'.$this->name.'.js';
    $script_asset_path = plugin_dir_path( WC_CHIP_FILE ) . 'assets/js/frontend/blocks_'.$this->name.'.asset.php';
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

    $localize_variable = array(
      'id' => $this->name,
      'fpx_b2c' => ['empty' => 'bank'],
      'fpx_b2b1' => ['empty' => 'bank']
    );

    $whitelisted_payment_method = $this->gateway->get_payment_method_whitelist();
    $bypass_chip = $this->gateway->get_bypass_chip();

    if (is_array($whitelisted_payment_method) AND count($whitelisted_payment_method) == 1  AND $bypass_chip == 'yes') {
      if ($whitelisted_payment_method[0] == 'fpx') {
        $localize_variable['fpx_b2c'] = $this->gateway->list_fpx_banks();
        unset($localize_variable['fpx_b2c']['']);
      } elseif ($whitelisted_payment_method[0] == 'fpx_b2b1') {
        $localize_variable['fpx_b2b1'] = $this->gateway->list_fpx_b2b1_banks();
        unset($localize_variable['fpx_b2b1']['']);
      }
    }

    wp_localize_script( "wc-{$this->name}-blocks", 'gateway_' . $this->name, $localize_variable );

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
      'icon'        => $this->gateway->icon,
    ];
  }
}
