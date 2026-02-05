=== CHIP for WooCommerce ===
Contributors: chipasia, wanzulnet, awisqirani, amirulazreen
Tags: chip
Requires at least: 6.3
Tested up to: 6.9
Stable tag: 2.0.2
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

1. Gateway configuration - Enter your Brand ID and Secret Key to connect with CHIP.
2. Payment gateways overview - All CHIP payment gateways available in WooCommerce Payments settings.
3. Payment method settings - Configure accepted payment methods and card options.
4. Card payment form (Legacy) - Secure card input with Visa/Mastercard brand detection.
5. FPX bank selection - Choose from available Malaysian banks with status indicators.
6. WooCommerce Blocks checkout - Modern checkout experience with card payment support.
7. Saved cards selection - Returning customers can pay with saved cards.
8. CHIP payment page - Secure hosted checkout for completing payment.
9. Order confirmation - Customer receives order confirmation after successful payment.
10. Order admin panel - View payment details including card brand and Purchase ID.
11. Capture payment - Capture pre-authorized payments when ready to fulfill.
12. Refund order - Process full or partial refunds directly from WooCommerce.
13. Site Health integration - Verify CHIP API connection status in WordPress Site Health.

== Changelog ==

= 2.0.2 2026-01-29 =
* Fixed - Subscription payment method change when customer chooses new card instead of saved card. Resolved ID mismatch between order-pay path and change_payment_method parameter used by WooCommerce Subscriptions.
* Fixed - Redirect to CHIP checkout URL blocked by wp_safe_redirect when changing subscription payment method. Added allowed_redirect_hosts filter to permit CHIP gateway domain.

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

Brand ID and Secret Key are available through our [merchant dashboard](https://gate.chip-in.asia). Navigate to Developer > Credentials after logging in.

= What currencies are supported? =

CHIP for WooCommerce supports MYR (Malaysian Ringgit) as the primary currency. Contact CHIP support for multi-currency options.

= Is this plugin compatible with WooCommerce Blocks? =

Yes! CHIP for WooCommerce fully supports the new WooCommerce Blocks checkout experience, including card payments, saved cards, and all payment methods.

= Can customers save their cards for future purchases? =

Yes. Enable "Allow Customers to Save Cards" in the gateway settings. Customers can then save their Visa, Mastercard, or Maestro cards for faster checkout.

= Why is my order showing "On Hold" status? =

Orders with "On Hold" status have pre-authorized payments awaiting capture. Go to the order page and click "Capture Payment" when ready to charge the customer.

= How do I capture a pre-authorized payment? =

Navigate to WooCommerce > Orders, open the order, and click the "Capture Payment" button in the order actions section. You can also enable auto-capture when order status changes to Processing or Completed.

= Does this work with WooCommerce Subscriptions? =

Yes! CHIP for WooCommerce supports WooCommerce Subscriptions with automatic recurring payments using saved cards.

= Does this work with WooCommerce Pre-Orders? =

Yes. Pre-Orders are supported with card tokenization. The saved card will be charged when the pre-order is released.

= Payment failed but money was deducted from my account? =

This is usually a temporary hold by your bank. If payment failed on CHIP's end, the hold will be released automatically within 1-7 business days depending on your bank.

= Why can't I see the CHIP payment option at checkout? =

Check the following:

1. Plugin is activated and gateway is enabled
2. Brand ID and Secret Key are correctly configured
3. Your store currency is supported (MYR)
4. Check Site Health (Tools > Site Health) for API connection status

= Is card data stored on my server? =

No. Card data is processed directly by CHIP's secure servers. Your WooCommerce store never handles or stores sensitive card information, ensuring PCI compliance.

= Are there any transaction fees? =

Transaction fees are determined by your CHIP merchant agreement. Contact CHIP sales for pricing details.

= Where can I find documentation? =

Visit our [API documentation](https://docs.chip-in.asia/) for technical reference.

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

**FPX Health Check API** – `CHIP_FPX_ROOT_URL` (https://api.chip-in.asia/health_check)

- `/fpx_b2c` – FPX B2C bank status
- `/fpx_b2b1` – FPX B2B1 bank status

= How to clone CHIP for WooCommerce? =

Create a new class that extends **Chip_Woocommerce_Gateway** with your own customizations.

Then, hook it with filter **woocommerce_payment_gateways** and pass your class name to it.

Refer to **includes/class-chip-woocommerce-gateway-2.php** for an example.

= How to remove additional payment methods? =

Add this constant to your wp-config.php file:

`define( 'CHIP_WOOCOMMERCE_DISABLE_GATEWAY_CLONES', true );`

== Links ==

[CHIP Website](https://www.chip-in.asia)

[Terms of Service](https://www.chip-in.asia/terms-of-service)

[Privacy Policy](https://www.chip-in.asia/privacy-policy)

[API Documentation](https://docs.chip-in.asia/)

[CHIP Merchants & DEV Community](https://www.facebook.com/groups/3210496372558088)
