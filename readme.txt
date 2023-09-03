=== CHIP for WooCommerce ===
Contributors: chipasia, wanzulnet
Tags: chip
Requires at least: 4.7
Tested up to: 6.3
Stable tag: 1.4.1
Requires PHP: 7.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

CHIP - Digital Finance Platform. Securely accept one-time and subscription payment with CHIP for WooCommerce.

== Description ==

This is an official CHIP plugin for WooCommerce.

CHIP is a comprehensive Digital Finance Platform specifically designed to support and empower Micro, Small and Medium Enterprises (MSMEs). We provide a suite of solutions encompassing payment collection, expense management, risk mitigation, and treasury management.

Our aim is to help businesses streamline their financial processes, reduce
operational complexity, and drive growth.

With CHIP, you gain a financial partner committed to simplifying, digitizing, and enhancing your financial operations for ultimate success.

This plugin will enable your WooCommerce site to be integrated with CHIP as per documented in [API Documentation](https://developer.chip-in.asia).

The plugins do includes support for WooCommerce Subscription products.

== Screenshots ==
* Fill up the form with Brand ID and Secret Key. Tick Enable API and Save changes to activate.
* Checkout and pay with CHIP
* CHIP payment page
* WooCommerce order received page
* WooCommerce dashboard order page
* WooCommerce refund order

== Changelog ==

= 1.4.1 = 2023-09-01 =
* Fixed - Performance improvement for FPX bypass page status check.
* Fixed - Ensure bypass chip payment page works without maestro option.
* Fixed - Fix checkout issue with CheckoutWC

= 1.4.0 = 2023-08-18 =
* Added - Support for bypass payment page for WooCommerce Blocks checkout for FPX B2C and FPX B2B1
* Added - Support for bypass payment page for legacy WooCommerce checkout for cards.
* Added - Default payment method whitelist for easier configuration.
* Added - Item charge for adding additional fee.
* Added - New icon.
* Added - Now bypass payment page for FPX will be based on banks availability.

= 1.3.9 = 2023-07-21 =
* Added   - Support for $0 initial checkout.
* Changed - Set redirect parameter to direct_post_url for Visa/Mastercard payment method
* Fixed   - Error after payment for admin after making payment
* Fixed   - Missing client if the order created through dashboard

= 1.3.8 = 2023-05-27 =
* Added - State information for address in billing and shipping
* Fixed - Zip code billing should taken from billing

= 1.3.7 = 2023-05-24 =
* Added - Quantity in CHIP Purchase invoice.

= 1.3.6 = 2023-05-23 =
* Changed - Put meaningful description in option page to reduce confusion
* Added   - More hooks for better data manipulation
* Fixed   - Issue when product price is less than zero

= 1.3.5 = 2023-05-08 =
* Changed - Put meaningful description to reduce confusion
* Changed - Automatic force tokenization for card when subscription product exists
* Removed - Removed save to account checkbox due to confusion
* Fixed   - Prevent Fatal Error on WooCommerce Scheduler in the event of invalid secret key

== Installation ==

= Demo =

[Test with WordPress](https://tastewp.com/new/?pre-installed-plugin-slug=chip-for-woocommerce&pre-installed-plugin-slug=woocommerce&redirect=admin.php%3Fpage%3Dwc-settings%26tab%3Dcheckout%26section%3Dchip&ni=true)

= Minimum Requirements =

* WordPress 4.7 or greater

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type "CHIP for WooCommerce" and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your webserver via your favorite FTP application. The
WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Frequently Asked Questions ==

= Where is the Brand ID and Secret Key located? =

Brand ID and Secret Key available through our merchant dashboard.

= Do I need to set public key for webhook? =

Optional. You may set the public key for webhook to synchronize the card token availability.

= Where can I find documentation? =

You can visit our [API documentation](https://developer.chip-in.asia/) for your reference.

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

[API Documentation](https://developer.chip-in.asia/)

[CHIP Merchants & DEV Community](https://www.facebook.com/groups/3210496372558088)