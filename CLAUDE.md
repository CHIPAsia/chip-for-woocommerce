# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Build JavaScript assets (required before release)
```bash
npm install
npm run build       # Production build → assets/js/frontend/*.js
npm run start       # Development watch mode
```

Built JS files are **gitignored** but required for the plugin to function. They must be built before tagging a release.

### Lint
```bash
# WordPress Coding Standards (tabs, 120 char line limit, `chip_` prefix)
phpcs --standard=phpcs.xml .

# PHP compatibility check
phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4 --extensions=php --ignore=vendor,node_modules,assets/js/frontend .
```

### Version bump
```bash
./scripts/bump-version.sh X.Y.Z
```
Updates version strings across `chip-for-woocommerce.php`, `readme.txt`, `package.json`, and prepends a changelog entry. It also runs `npm run build` and stages all changes.

## Architecture

### Plugin bootstrap
`chip-for-woocommerce.php` defines constants and loads `Chip_Woocommerce` (singleton in `includes/class-chip-woocommerce.php`). The main class is a loader: it includes files, adds hooks, and registers WooCommerce Blocks support. It does not itself extend any WooCommerce class.

### Payment gateway hierarchy
`Chip_Woocommerce_Gateway` (in `includes/class-chip-woocommerce-gateway.php`) extends `WC_Payment_Gateway`. It is the base class containing all payment logic. There are five **clone gateways** (`Gateway_2` through `Gateway_6`) that extend it and only override `init_id()`, `init_title()`, and `init_form_fields()` to provide preset configurations (e.g., FPX-only, card-only). All clones share the same callback handler and API client.

Clones are loaded unless the `CHIP_WOOCOMMERCE_DISABLE_GATEWAY_CLONES` constant is defined.

### External callbacks (CHIP → WordPress)
CHIP sends payment results to `WC()->api_request_url( $this->id )`, which produces URLs like `https://store.com/wc-api/wc_gateway_chip/?id=123`. WooCommerce fires `do_action( 'woocommerce_api_wc_gateway_chip' )`, routed to `Chip_Woocommerce_Gateway::handle_callback()`. This is the legacy REST API stub kept in WooCommerce core specifically for payment gateway callbacks.

The callback handler dispatches to three sub-handlers based on query params:
- `?tokenization=yes` → `handle_callback_token()` (save card via X-Signature RSA verification)
- `?process_payment_method_change=yes` → `handle_payment_method_change()` (subscription payment change)
- default → `handle_callback_order()` (normal order payment completion)

### WooCommerce Blocks checkout
Blocks support is registered in `Chip_Woocommerce::block_support()` via `woocommerce_blocks_loaded`. It instantiates `Chip_Woocommerce_Gateway_Blocks_Support` (and clones), which extends `Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType`.

The JS source lives in `resources/js/frontend/blocks_chip_woocommerce_gateway.js` (and clones) and is built by Webpack into `assets/js/frontend/`. The blocks class registers the script via `wp_register_script` and localizes bank/e-wallet API endpoints for lazy loading.

For **card payments with bypass_chip enabled**, the gateway creates a direct POST URL. In Blocks checkout, `process_payment_with_context()` intercepts the Store API `PaymentResult`, injects the `direct_post_url` into `paymentDetails`, and clears the redirect URL so the frontend JS can POST card data directly to CHIP instead of redirecting.

### API client
`Chip_Woocommerce_API` (in `includes/class-chip-woocommerce-api.php`) wraps the CHIP REST API (`https://gate.chip-in.asia/api/v1`). It uses `wp_remote_request` with a 10-second timeout and Bearer token auth. FPX bank status is fetched via a separate `Chip_Woocommerce_API_FPX` class (`https://api.chip-in.asia/health_check`).

### Database locking
The gateway implements advisory locking for callback idempotency. It detects the database type (MySQL vs PostgreSQL) via `$wpdb->is_mysql` and uses `GET_LOCK`/`RELEASE_LOCK` or `pg_advisory_lock`/`pg_advisory_unlock` accordingly.

### Admin-only features
Several classes are loaded only inside `is_admin()`:
- `Chip_Woocommerce_Bulk_Action` — bulk requery orders
- `Chip_Woocommerce_Site_Health` — adds a CHIP API connectivity test
- `Chip_Woocommerce_Void_Payment` / `Chip_Woocommerce_Capture_Payment` — order action buttons with AJAX handlers
- `Chip_Woocommerce_Payment_Details` — displays CHIP metadata on order pages

### Key files not to miss
| File | Purpose |
|---|---|
| `chip-for-woocommerce.php` | Constants, version, HPOS compatibility declaration |
| `includes/class-chip-woocommerce.php` | Main loader, singleton, Blocks registration |
| `includes/class-chip-woocommerce-gateway.php` | Core payment logic (~3,800 lines) |
| `includes/class-chip-woocommerce-api.php` | CHIP REST API wrapper |
| `includes/blocks/class-chip-woocommerce-gateway-blocks-support.php` | Blocks payment method registration |
| `resources/js/frontend/blocks_chip_woocommerce_gateway.js` | Blocks checkout React components |
| `scripts/bump-version.sh` | Automated version bump across all files |
| `phpcs.xml` | Coding standards config (WordPress rules, text domain `chip-for-woocommerce`) |

## Important context

- **No unit tests exist** in the `tests/` directory. It only contains `.DS_Store`.
- **Release artifacts**: `git archive` is used by workflows to create clean exports; `.gitattributes` marks dev files (`vendor/`, `node_modules/`, `resources/`, `.github/`, etc.) as `export-ignore` so they are never shipped.
- **WordPress.org assets**: The `.wordpress-org/` directory tracks banners, icons, and screenshots that are synced to SVN `assets/` by the deploy workflow.
- **Security guards**: Every PHP file begins with `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- **Deprecated hooks**: Version 2.0.0 renamed hooks from `wc_` to `chip_` prefixes. The old hooks still exist via `_deprecated_hook()` calls for backward compatibility.
