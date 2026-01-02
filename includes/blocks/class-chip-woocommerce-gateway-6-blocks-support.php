<?php
/**
 * CHIP for WooCommerce Blocks Support - Gateway 6
 *
 * WooCommerce Blocks support class for CHIP payment gateway 6.
 *
 * @package CHIP for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Blocks support class for CHIP gateway 6.
 */
class Chip_Woocommerce_Gateway_6_Blocks_Support extends Chip_Woocommerce_Gateway_Blocks_Support {

	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'wc_gateway_chip_6';

	/**
	 * Script name for assets.
	 *
	 * @var string
	 */
	protected $script_name = 'chip_woocommerce_gateway_6';
}
