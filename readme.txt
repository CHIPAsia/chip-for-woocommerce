=== CHIP for WooCommerce ===
Contributors: chipasia, wanzulnet
Tags: chip
Requires at least: 4.7
Tested up to: 6.2
Stable tag: 1.3.0
Requires PHP: 7.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

CHIP - Better Payment & Business Solutions. Securely accept one-time and subscription payment with CHIP for WooCommerce.

== Description ==

This is an official CHIP plugin for WooCommerce.

CHIP is a payment and business solutions platform that allow you to securely sell your products and get paid via multiple local and international payment methods.

This plugin will enable your WooCommerce site to be integrated with CHIP as per documented in [API Documentation](https://developer.chip-in.asia).

== Screenshots ==
* Fill up the form with Brand ID and Secret Key. Tick Enable API and Save changes to activate.
* Checkout and pay with CHIP
* CHIP payment page
* WooCommerce order received page
* WooCommerce dashboard order page
* WooCommerce refund order

== Changelog ==

= 1.3.0 - 2023-3-10 =
* Added   - Add support for WooCommerce Subscription
* Added   - Hook token deletion with CHIP
* Added   - WC_Gateway_Chip can be extended for cloning
* Added   - Now CHIP payment have 4 payment method by default
* Added   - Support for whitelisting payment method
* Added   - Purchase due strict is now configurable
* Added   - Due strict timing can be configured independently
* Added   - Registered users are now linked with CHIP clients
* Added   - Option to update client information on checkout
* Added   - Option to disable success_callback or success_redirect for troubleshooting
* Added   - Option to force https:// to prevent redirection on success_callback
* Added   - Option to disable tokenization
* Added   - Option to disable payment method cloning via PHP constant
* Added   - Button to reqeuery payment status
* Added   - Automatic requery payment status
* Changed - Timezone is now configurable within plugin option
* Removed - Enable Payment method selection is now removed

= 1.2.6 - 2023-1-26 =
* Added - Add FPX extra information on failure
* Fixed - Enable payment method selection requires total amount

= 1.2.5 - 2023-1-1 =
* Fixed - Amount deducted twice when using coupon

= 1.2.4 - 2022-12-28 =
* Added - Add error logging on create purchase error

= 1.2.3 - 2022-12-15 =
* Added   - Add maestro as card group
* Added   - Constant WC_CHIP_OLD_URL_SCHEME for switch to old URL scheme
* Changed - Redirect URL using new URL scheme

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

No.

= Where can I find documentation? =

You can visit our [API documentation](https://developer.chip-in.asia/) for your reference.

= What CHIP API services used in this plugin? =

This plugin rely on CHIP API ([WC_CHIP_ROOT_URL](https://gate.chip-in.asia)) as follows:

  - **/payment_methods/**
    - This is for getting available payment method specific to your account
  - **/purchases/**
    - This is for accepting payment
  - **/purchases/<id\>**
    - This is for getting payment status from CHIP
  - **/purchases/<id\>/refund**
    - This is for refunding payment

= How to clone CHIP for WooCommerce? =

Create new class and extend WC_Gateway_Chip or WC_Gateway_Chip_Subscription with own class.

Then, hook it with filter woocommerce_payment_gateways and pass the method own class name to it.

== Links ==

[CHIP Website](https://www.chip-in.asia)

[Terms of Service](https://www.chip-in.asia/terms-of-service)

[Privacy Policy](https://www.chip-in.asia/privacy-policy)

[API Documentation](https://developer.chip-in.asia/)

[CHIP Merchants & DEV Community](https://www.facebook.com/groups/3210496372558088)
