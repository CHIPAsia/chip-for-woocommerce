<?php
/**
 * CHIP for WooCommerce Blocks support - merchant-created payment block.
 *
 * The standard gateways each ship their own support class with a hardcoded
 * name. A created block cannot have a hardcoded class, so this one takes its
 * name at construction time and reuses the family's built Blocks asset: the
 * asset reads its settings from the `gateway_<name>` global that the base
 * support class localizes, so one asset serves every block of that family.
 *
 * @package CHIP for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Blocks support for a created payment block.
 */
class Chip_Woocommerce_Gateway_Block_Blocks_Support extends Chip_Woocommerce_Gateway_Blocks_Support {

	/**
	 * Constructor.
	 *
	 * @param string $name        Gateway id of the block (e.g. 'chip_block_ab12cd34').
	 * @param string $script_name Blocks asset suffix of the block's family.
	 */
	public function __construct( $name, $script_name ) {
		$this->name        = (string) $name;
		$this->script_name = (string) $script_name;
	}
}
