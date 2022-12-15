=== CHIP for WooCommerce ===
Contributors: chipasia, wanzulnet
Tags: chip, cash, card, coin
Requires at least: 4.7
Tested up to: 6.2
Stable tag: 1.2.3
Requires PHP: 7.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

CHIP - Cash, Card and Coin Handling Integrated Platform. Securely accept payment with CHIP for WooCommerce.

== Description ==

This is an official CHIP plugin for WooCommerce.

CHIP is an abbreviation of Cash, Card and Coin Handling Integrated Platform. CHIP allows you to securely sell your products and get paid.

This plugin will enable your WooCommerce site to be integrated with CHIP as per documented in [API Documentation](https://developer.chip-in.asia/api#online_purchases_custom_payment_flow_direct_post).

== Screenshots ==
* Fill up the form with Brand ID and Secret Key. Tick Enable API and Save changes to activate.
* Checkout and pay with CHIP
* CHIP payment page
* WooCommerce order received page
* WooCommerce dashboard order page
* WooCommerce refund order

== Changelog ==

= 1.2.3 - 2022-12-15 =
* Added   - Add maestro as card group
* Added   - Constant WC_CHIP_OLD_URL_SCHEME for switch to old URL scheme
* Changed - Redirect URL using new URL scheme

= 1.2.2 - 2022-11-25 =
* Tweak - Enhance locking to lock per order id
* Fix   - Issue with error when payment with visa and mastercard

= 1.2.1 - 2022-11-12 =
* Add - Support for success_callback verification using public key

= 1.2.0 - 2022-11-8 =
* Add    - Add filters wc_chip_supports to allow refund to be disabled
* Add    - Timezone support for due strict
* Tweak  - Revamp how preferred payment option being presented
* Tweak  - Hide custom field for chip_transaction_id as it is not meant to be edited
* Update - New logo

= 1.1.4 - 2022-11-7 =
* Add    - Check payment id to avoid spoofing
* Add    - Add hooks to allow checkout page to be customized
* Add    - CHIP Icon on checkout page
* Tweak  - Prevent unsupported currencies to be paid
* Update - Standardize terminology for secret key
* Update - Change FPX B2C to FPX

= 1.1.3 - 2022-10-12 =
* New   - Intial Repack Release.
* Tweak - Added due strict to enforce payment timeout based on Hold Stock (Minutes)
* Tweak - Removed country selection on checkout page.

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

If on the off-chance you do encounter issues with the shop/category pages after an update you simply need to flush the permalinks by going to WordPress > Settings > Permalinks and hitting 'save'. That should return things to normal.


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

== Links ==

[CHIP Website](https://www.chip-in.asia)

[Terms of Service](https://www.chip-in.asia/terms-of-service)

[Privacy Policy](https://www.chip-in.asia/privacy-policy)

[API Documentation](https://developer.chip-in.asia/)

[CHIP Merchants & DEV Community](https://www.facebook.com/groups/3210496372558088)
