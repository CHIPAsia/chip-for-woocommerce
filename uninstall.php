<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
  die;
}

delete_option( 'chip_woocommerce_payment_method' );
delete_option( 'woocommerce_chip-fpxb2b1_settings' );
delete_option( 'woocommerce_chip-card_settings' );
delete_option( 'wc_chip_migrations' );