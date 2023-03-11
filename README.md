<img src="./assets/logo.svg" alt="drawing" width="50"/>

# CHIP for WooCommerce
[![WP compatibility](https://plugintests.com/plugins/wporg/chip-for-woocommerce/wp-badge.svg)](https://plugintests.com/plugins/wporg/chip-for-woocommerce/latest)
[![PHP compatibility](https://plugintests.com/plugins/wporg/chip-for-woocommerce/php-badge.svg)](https://plugintests.com/plugins/wporg/chip-for-woocommerce/latest)

This module adds CHIP payment method option to your WordPress-based WooCommerce shop.

## Installation

* [Download zip file of WooCommerce plugin.](https://github.com/CHIPAsia/chip-for-woocommerce/archive/refs/heads/main.zip)
* Log in to your Wordpress admin panel and go: **Plugins** -> **Add New**
* Select **Upload Plugin**, choose zip file you downloaded in step 1 and press **Install Now**
* Activate plugin

## Configuration

Set the **Brand ID** and **Secret Key** in the plugins settings.

## Demo

[Test with WordPress](https://tastewp.com/new/?pre-installed-plugin-slug=chip-for-woocommerce&pre-installed-plugin-slug=woocommerce&redirect=admin.php%3Fpage%3Dwc-settings%26tab%3Dcheckout%26section%3Dchip&ni=true)

## Known Issues

WooCommerce throw warning `woocommerce_add_order_item_meta is deprecated since version 3.0.0! Use woocommerce_new_order_item instead.` when calling method: `$order->update_meta_data();`. There is no immediate patch is known yet.

## Other

Facebook: [Merchants & DEV Community](https://www.facebook.com/groups/3210496372558088)
