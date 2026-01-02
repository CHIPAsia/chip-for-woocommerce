=== CHIP for WooCommerce ===
Contributors: chipasia, wanzulnet, awisqirani, amirulazreen
Tags: chip
Requires at least: 6.3
Tested up to: 6.9
Stable tag: 2.0.0
Requires PHP: 7.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

CHIP - Digital Finance Platform. Securely accept one-time and subscription payments with CHIP for WooCommerce.

== Description ==

**CHIP for WooCommerce** is the official payment gateway plugin that connects your WooCommerce store to CHIP's powerful Digital Finance Platform. Accept payments seamlessly with Malaysia's leading payment methods.

= Why Choose CHIP for WooCommerce? =

* **WooCommerce Blocks Support** - Fully compatible with the new WooCommerce Blocks checkout experience
* **Multiple Payment Methods** - Accept FPX, Credit/Debit Cards, DuitNow QR, E-Wallets, and more
* **Subscription Payments** - Native support for WooCommerce Subscriptions
* **Tokenization** - Allow customers to save cards for faster checkout
* **Direct Post Integration** - Secure card payments without redirecting customers
* **Pre-Orders Support** - Works seamlessly with WooCommerce Pre-Orders
* **Authorize & Capture** - Delay capture for card payments until order fulfillment

= Supported Payment Methods =

* **FPX** - Malaysia's #1 online banking payment
* **FPX B2B1** - Corporate online banking
* **Credit/Debit Cards** - Visa, Mastercard, Maestro
* **DuitNow QR** - Malaysia's national QR payment
* **E-Wallets** - GrabPay, Touch 'n Go, Boost, and more via Razer

= About CHIP =

CHIP is a comprehensive Digital Finance Platform specifically designed to support and empower Micro, Small and Medium Enterprises (MSMEs). We provide a suite of solutions encompassing payment collection, expense management, risk mitigation, and treasury management.

Our aim is to help businesses streamline their financial processes, reduce operational complexity, and drive growth. With CHIP, you gain a financial partner committed to simplifying, digitizing, and enhancing your financial operations for ultimate success.

= Documentation =

Integrate your WooCommerce site with CHIP as documented in our [API Documentation](https://docs.chip-in.asia).

== Screenshots ==
* Fill up the form with Brand ID and Secret Key. Tick Enable API and Save changes to activate.
* Checkout and pay with CHIP
* CHIP payment page
* WooCommerce order received page
* WooCommerce dashboard order page
* WooCommerce refund order

== Changelog ==

= 2.0.0 2025-12-31 =
* Changed - Refactored codebase with improved class structure for WordPress coding standards compliance.
* Changed - Renamed global constants WC_CHIP_ROOT_URL and WC_CHIP_FPX_ROOT_URL to CHIP_ROOT_URL and CHIP_FPX_ROOT_URL.
* Changed - Changed hook prefixes from wc_ to chip_ with backward compatibility.
* Changed - CVC input now hidden (password type) in both legacy and blocks checkout.
* Added - Filter chip_blocks_payment_method_data for blocks payment method customization.
* Added - REST API endpoint for lazy loading FPX banks and e-wallets.
* Added - Direct POST card payments support for WooCommerce Blocks checkout.
* Added - Card form with validation for WooCommerce Blocks.
* Added - Save card option (remember_card) for both legacy and blocks checkout.
* Added - Void and Capture payment functionality for pre-authorized payments.
* Improved - WooCommerce Blocks integration with recommended patterns.
* Improved - Bank/e-wallet lists now lazy loaded via AJAX for better performance.
* Improved - Code quality with PHPCS compliance fixes.
* Fixed - Saved card payments in WooCommerce Blocks checkout.
* Removed - Metabox AJAX handler, Update client information, Disable clients API, Force tokenization, Webhook public key options, and Receipt link buttons.

[See changelog for all versions](https://raw.githubusercontent.com/CHIPAsia/chip-for-woocommerce/main/changelog.txt).

== Installation ==

= Demo =

[Test with WordPress](https://tastewp.com/new/?pre-installed-plugin-slug=chip-for-woocommerce&pre-installed-plugin-slug=woocommerce&redirect=admin.php%3Fpage%3Dwc-settings%26tab%3Dcheckout%26section%3Dchip&ni=true)

= Minimum Requirements =

* PHP 7.4 or greater is required (PHP 8.0 or greater is recommended)
* MySQL 5.6 or greater, OR MariaDB version 10.1 or greater, is required

= Automatic installation =

Automatic installation is the easiest option -- WordPress will handle the file transfer, and you won't need to leave your web browser. To do an automatic install of CHIP for WooCommerce, log in to your WordPress dashboard, navigate to the Plugins menu, and click "Add New."

In the search field type "CHIP for WooCommerce," then click "Search Plugins." Once you've found us, you can view details about it such as the point release, rating, and description. Most importantly of course, you can install it! Click "Install Now," and WordPress will take it from there.

= Manual installation =

Manual installation method requires downloading the CHIP for WooCommerce plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](https://wordpress.org/support/article/managing-plugins/#manual-plugin-installation).

= Updating =

Automatic updates should work smoothly, but we still recommend you back up your site.

== Frequently Asked Questions ==

= Where is the Brand ID and Secret Key located? =

Brand ID and Secret Key are available through our merchant dashboard.

= Do I need to set the public key for webhook? =

Optional. You may set the public key for webhook to synchronize card token availability.

= Where can I find documentation? =

You can visit our [API documentation](https://docs.chip-in.asia/) for your reference.

= What CHIP API services are used in this plugin? =

**CHIP API** – `CHIP_ROOT_URL` (https://gate.chip-in.asia)

*Payment Operations:*
- `/purchases/` – Create payment
- `/purchases/{id}/` – Get payment status
- `/purchases/{id}/refund/` – Refund payment
- `/purchases/{id}/capture/` – Capture pre-authorized payment
- `/purchases/{id}/release/` – Release pre-authorized payment

*Card Token Operations:*
- `/purchases/{id}/charge/` – Charge saved card
- `/purchases/{id}/delete_recurring_token/` – Delete saved card

*Other:*
- `/payment_methods/` – Get available payment methods
- `/clients/` – Create clients
- `/public_key/` – Webhook verification

**FPX Health Check API** – `CHIP_FPX_ROOT_URL` (https://api.chip-in.asia/health_check)
- `/fpx_b2c` – FPX B2C bank status
- `/fpx_b2b1` – FPX B2B1 bank status

**Time API** (https://timeapi.io)
- `/api/Time/current/zone?timeZone=UTC` – Server time accuracy check (cached 1 hour)

= How to clone CHIP for WooCommerce? =

Create a new class that extends **Chip_Woocommerce_Gateway** with your own customizations.

Then, hook it with filter **woocommerce_payment_gateways** and pass your class name to it.

You may refer to **includes/class-chip-woocommerce-gateway-2.php** file for an example of how to extend the base gateway class.

= How to remove additional payment methods? =

Create a PHP constant in your wp-config.php file with the following code:

`define( 'CHIP_WOOCOMMERCE_DISABLE_GATEWAY_CLONES', true );`

== Links ==

[CHIP Website](https://www.chip-in.asia)

[Terms of Service](https://www.chip-in.asia/terms-of-service)

[Privacy Policy](https://www.chip-in.asia/privacy-policy)

[API Documentation](https://docs.chip-in.asia/)

[CHIP Merchants & DEV Community](https://www.facebook.com/groups/3210496372558088)
