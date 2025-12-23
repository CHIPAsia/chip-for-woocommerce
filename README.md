<img src="./assets/logo.svg" alt="CHIP Logo" width="50"/>

# CHIP for WooCommerce

The official CHIP payment gateway plugin for WooCommerce. Accept payments seamlessly with Malaysia's leading payment methods including FPX, credit/debit cards, DuitNow QR, and e-wallets.

## Features

- **WooCommerce Blocks Support** - Fully compatible with the new WooCommerce Blocks checkout
- **Multiple Payment Methods** - FPX, Credit/Debit Cards, DuitNow QR, E-Wallets, and more
- **Subscription Payments** - Native support for WooCommerce Subscriptions
- **Tokenization** - Allow customers to save cards for faster checkout
- **Pre-Orders Support** - Works seamlessly with WooCommerce Pre-Orders
- **Authorize & Capture** - Delay capture for card payments until order fulfillment

## Installation

> **⚠️ Important:** Always download from the [Releases page](https://github.com/CHIPAsia/chip-for-woocommerce/releases/latest), not the "Download ZIP" button on the repository. The release package includes pre-built JavaScript files required for WooCommerce Blocks. Downloading directly from the repository requires running `npm run build` first.

1. [Download the latest release](https://github.com/CHIPAsia/chip-for-woocommerce/releases/latest)
2. Log in to your WordPress admin panel and navigate to **Plugins** → **Add New**
3. Click **Upload Plugin**, select the downloaded zip file, and click **Install Now**
4. Activate the plugin

## Configuration

1. Navigate to **WooCommerce** → **Settings** → **Payments**
2. Click on **CHIP** to configure the gateway
3. Enter your **Brand ID** and **Secret Key**
4. Enable the payment method and save changes

## Demo

[Try it on WordPress](https://tastewp.com/new/?pre-installed-plugin-slug=chip-for-woocommerce&pre-installed-plugin-slug=woocommerce&redirect=admin.php%3Fpage%3Dwc-settings%26tab%3Dcheckout%26section%3Dchip&ni=true)

## Development

### Rebuild Assets

To rebuild the JavaScript assets for WooCommerce Blocks:

```bash
npm install
npm run build
```

## Known Issues

- Additional fee does not apply to pre-order fees.

## Documentation

- [API Documentation](https://docs.chip-in.asia)
- [Developer Wiki](https://github.com/CHIPAsia/chip-for-woocommerce/wiki)

## Community

Join our [Merchants & Developers Community](https://www.facebook.com/groups/3210496372558088) on Facebook for support and discussions.

## License

This plugin is licensed under the [GPLv3](http://www.gnu.org/licenses/gpl-3.0.html).
