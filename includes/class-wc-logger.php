<?php

class Chip_Woocommerce_Logger {
  private $logger;

  public function __construct() {
    $this->logger = new WC_Logger();
  }

  public function log( $message ) {
    $this->logger->add( 'chip', $message ); 
  }  
}