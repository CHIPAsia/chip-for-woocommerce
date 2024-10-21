=== CHIP for WooCommerce ===
Contributors: chipasia, wanzulnet
Tags: chip
Requires at least: 4.7
Tested up to: 6.6
Stable tag: 1.6.5
Requires PHP: 7.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

CHIP - Digital Finance Platform. Securely accept one-time and subscription payment with CHIP for WooCommerce.

== Description ==

This is an official CHIP plugin for WooCommerce.

CHIP is a comprehensive Digital Finance Platform specifically designed to support and empower Micro, Small and Medium Enterprises (MSMEs). We provide a suite of solutions encompassing payment collection, expense management, risk mitigation, and treasury management.

Our aim is to help businesses streamline their financial processes, reduce operational complexity, and drive growth.

With CHIP, you gain a financial partner committed to simplifying, digitizing, and enhancing your financial operations for ultimate success.

This plugin will enable your WooCommerce site to be integrated with CHIP as per documented in [API Documentation](https://docs.chip-in.asia).

The plugins do includes support for WooCommerce Subscription products.

== Screenshots ==
* Fill up the form with Brand ID and Secret Key. Tick Enable API and Save changes to activate.
* Checkout and pay with CHIP
* CHIP payment page
* WooCommerce order received page
* WooCommerce dashboard order page
* WooCommerce refund order

== Changelog ==

= 1.6.5 2024-10-02 =
* Fixed - Fix Razer Checkout Block 

[See changelog for all versions](https://raw.githubusercontent.com/CHIPAsia/chip-for-woocommerce/main/changelog.txt).

== Installation ==

= Demo =

[Test with WordPress](https://tastewp.com/new/?pre-installed-plugin-slug=chip-for-woocommerce&pre-installed-plugin-slug=woocommerce&redirect=admin.php%3Fpage%3Dwc-settings%26tab%3Dcheckout%26section%3Dchip&ni=true)

= Minimum Requirements =

* PHP 7.4 or greater is required (PHP 8.0 or greater is recommended)
* MySQL 5.6 or greater, OR MariaDB version 10.1 or greater, is required

= Automatic installation =

Automatic installation is the easiest option -- WordPress will handle the file transfer, and you won’t need to leave your web browser. To do an automatic install of WooCommerce, log in to your WordPress dashboard, navigate to the Plugins menu, and click “Add New.”

In the search field type “CHIP for WooCommerce,” then click “Search Plugins.” Once you’ve found us,  you can view details about it such as the point release, rating, and description. Most importantly of course, you can install it by! Click “Install Now,” and WordPress will take it from there.

= Manual installation =

Manual installation method requires downloading the CHIP for WooCommerce plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](https://wordpress.org/support/article/managing-plugins/#manual-plugin-installation).

= Updating =

Automatic updates should work smoothly, but we still recommend you back up your site.

== Frequently Asked Questions ==

= Where is the Brand ID and Secret Key located? =

Brand ID and Secret Key available through our merchant dashboard.

= Do I need to set public key for webhook? =

Optional. You may set the public key for webhook to synchronize the card token availability.

= Where can I find documentation? =

You can visit our [API documentation](https://docs.chip-in.asia/) for your reference.

= What CHIP API services used in this plugin? =

This plugin rely on CHIP API ([WC_CHIP_ROOT_URL](https://gate.chip-in.asia)) as follows:

  - **/payment_methods/**
    - This is for getting available payment method specific to your account
  - **/purchases/**
    - This is for accepting payment
  - **/purchases/<id\>/**
    - This is for getting payment status from CHIP
  - **/purchases/<id\>/refund/**
    - This is for refunding payment
  - **/purchases/<id\>/charge/**
    - This is for charging payment with token
  - **/purchases/<id\>/delete_recurring_token/**
    - This is for deleting card token
  - **/clients/**
    - This is for creating clients in CHIP
  - **/clients/?q=<email\>**
    - This is for getting client in CHIP with email
  - **/clients/<id\>/**
    - This to get client and patch client information

Additionally, for FPX Bank status, this plugin rely on CHIP API ([WC_CHIP_FPX_ROOT_URL](https://api.chip-in.asia/health_check)) as follows:

  - **/fpx_b2c**
    - This is for getting FPX B2C status
  - **/fpx_b2b1**
    - This is for getting FPX B2B1 status

= How to clone CHIP for WooCommerce? =

Create new class and extend **WC_Gateway_Chip** or **WC_Gateway_Chip_Subscription** with own class.

Then, hook it with filter **woocommerce_payment_gateways** and pass the method own class name to it.

You may refer to **includes/clone-wc-gateway-chip.php** file for example.

= How to remove the additional payment method? =

Create a PHP constant in your wp-config.php file with the following code:

`define( 'DISABLE_CLONE_WC_GATEWAY_CHIP' , true );`

== Links ==

[CHIP Website](https://www.chip-in.asia)

[Terms of Service](https://www.chip-in.asia/terms-of-service)

[Privacy Policy](https://www.chip-in.asia/privacy-policy)

[API Documentation](https://docs.chip-in.asia/)

[CHIP Merchants & DEV Community](https://www.facebook.com/groups/3210496372558088)
