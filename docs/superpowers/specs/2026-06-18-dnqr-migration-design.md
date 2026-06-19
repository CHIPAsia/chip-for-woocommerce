# DuitNow QR → dnqr migration — Design

**Date:** 2026-06-18
**Status:** Draft, pending user review

## Background

CHIP is replacing the legacy `duitnow_qr` payment method with a new method called `dnqr`. Both methods coexist for the foreseeable future: legacy merchants keep `duitnow_qr`, new merchants are onboarded straight onto `dnqr`, and CHIP's `/payment_methods/` endpoint reports which method(s) each merchant actually has available.

The plugin must accept both `duitnow_qr` and `dnqr` in its payment-method whitelist, treat them as a single logical "DuitNow QR" group, and pick the correct concrete method at runtime based on what the merchant has.

## Goals

- Add `dnqr` as a recognized payment-method identifier alongside `duitnow_qr`.
- Make the gateway's payment-method selection UI work for both old and new merchants with **no UI confusion**: a single "DuitNow QR" checkbox (separate from the existing multiselect) controls the dnqr group. The multiselect no longer exposes `duitnow_qr` or `dnqr` directly.
- Decide at runtime which concrete method to send to CHIP, using `/payment_methods/` as the source of truth.
- Prefer `dnqr` when both are available; fall back to `duitnow_qr` when only that is available.
- DuitNow QR **is** a Razer e-wallet option in the customer-facing dropdown. The `duitnow-qr` entry stays in `list_razer_ewallets()` and the Razer e-wallet switch in `bypass_chip()`. The migration adds dnqr-priority logic to both.
- No DB migration, no settings-page migration, no breaking changes for existing merchants.

## Non-goals

- Migrating stored order meta or settings values (none change).
- Adding a new clone gateway (Gateway 6 stays the single DuitNow QR gateway; its preset is widened).
- JS-side UI for a DuitNow QR button (auto-redirect on `Place Order` is sufficient for the single-method DuitNow QR branch; the Razer e-wallet dropdown handles the e-wallet case).
- Subscription / recurring-method behavior changes (the existing `payment_recurring_methods()` call site is untouched).

## Glossary

| Term | Meaning |
|---|---|
| **dnqr group** | The set `{duitnow_qr, dnqr}` — treated as one logical "DuitNow QR" payment option. |
| **Card group** | The set `{visa, mastercard, maestro}` — treated as one logical "card" option for `?preferred=` grouping only. |
| **Group count** | Number of distinct payment-method groups represented by a whitelist. Used to decide whether `?preferred=` should be added to the redirect URL. |
| **Resolved dnqr group** | The subset of the dnqr group that the merchant actually has available, intersected with priority order (`dnqr` first, `duitnow_qr` fallback). |

## Architecture overview

Introduce a single resolver helper that runs at the one site where the gateway serializes `payment_method_whitelist` for the `create_payment` call. All gateway variants (base + 6 clones) already share that code path, so the migration is implemented once and inherited everywhere.

### The unit-level changes

| Unit | Change |
|---|---|
| `Chip_Woocommerce_Gateway::get_payment_method_list()` | Remove `'duitnow_qr'` and `'dnqr'` from the list. The dnqr group is now controlled by a separate form field `enable_dnqr_group`. |
| `Chip_Woocommerce_Gateway` (new protected method) `resolve_duitnow_methods( $whitelist, $currency, $amount )` | Encapsulates group expansion → API check → intersection → dnqr-priority → fallback. Returns the final whitelist to send. |
| `Chip_Woocommerce_Gateway` (new protected property) `$resolved_dnqr_group` | Caches the resolver's dnqr-group output for `bypass_chip()` to read without re-hitting the API. |
| `Chip_Woocommerce_Gateway` (new public method) `get_duitnow_qr_preferred()` | Returns the `?preferred=` value (`dnqr` or `duitnow_qr`) when the configured whitelist is a pure DuitNow QR group, `''` otherwise. |
| `Chip_Woocommerce_Gateway` (new form field `enable_dnqr_group`) | Single boolean checkbox controlling whether the dnqr group is in the runtime whitelist. Replaces the multiselect entry for `duitnow_qr`. |
| `Chip_Woocommerce_Gateway::process_payment()` (L1771-1772) | Replace `$params['payment_method_whitelist'] = $this->payment_method_whitelist;` with a call to the resolver; store the resolved subset on the instance. (L1785 subscription override is untouched.) |
| `Chip_Woocommerce_Gateway::bypass_chip()` | Keep the `case 'duitnow-qr':` in the Razer e-wallet switch — apply priority: pick `dnqr` first, `duitnow_qr` fallback, based on `$this->resolved_dnqr_group`. Keep the L2981 single-method branch but trigger on "whitelist intersects the dnqr group AND has no other groups" and apply the same priority. |
| `Chip_Woocommerce_Gateway::list_razer_ewallets()` (L2930) | Keep the `duitnow-qr` entry. Trigger condition: `array_intersect( $whitelist, DUITNOW_GROUP )` is non-empty (so the option shows when merchant configured either `duitnow_qr` or `dnqr` or both). |
| `class-chip-woocommerce-gateway-6.php` | Change preset: set `enable_dnqr_group` default to `'yes'`. Title, description, logo, ID, and form fields stay the same. |

### What does NOT change

- Stored order meta, settings options, or DB schema (no migration).
- `Chip_Woocommerce_API::payment_methods()` — already exists at L161.
- `class-chip-woocommerce-gateway-blocks-support.php` — auto-redirect is server-side, no JS work needed.
- All JS files in `resources/js/frontend/`.
- Clones 2–5.
- `?preferred=` for card, FPX, FPX B2B1, and non-dnqr Razer e-wallet flows.

## Component design

### 1. Constants and properties

```php
// New class constant on Chip_Woocommerce_Gateway.
const DUITNOW_GROUP = array( 'duitnow_qr', 'dnqr' );

// New instance properties.
protected $enable_dnqr_group   = 'no';
protected $resolved_dnqr_group = array();
```

The constant is the single source of truth for "what counts as the DuitNow QR group." If CHIP ever introduces a third identifier (e.g. `dnqr2`), adding it to the array is a one-line change.

### 2. Resolver — `resolve_duitnow_methods()`

The heart of the migration. Pseudocode:

```php
protected function resolve_duitnow_methods( array $whitelist, string $currency, int $amount ): array {
    // 1. Group expansion: any dnqr-group member in the whitelist expands to the full group.
    $has_group_member = count( array_intersect( $whitelist, self::DUITNOW_GROUP ) ) > 0;
    $expanded          = $whitelist;
    if ( $has_group_member ) {
        $expanded = array_values( array_unique( array_merge( $whitelist, self::DUITNOW_GROUP ) ) );
    }

    // 2. Cache key: brand + currency + amount-bucket (round to 100-sen steps).
    $cache_key = 'chip_pm_' . md5( $this->brand_id . '|' . $currency . '|' . intval( $amount / 100 ) );

    // 3. Try cache. If hit, use it. If miss, call /payment_methods/.
    $available = get_transient( $cache_key );
    if ( false === $available ) {
        $chip      = $this->api();
        $response  = $chip->payment_methods( $currency, '', $amount ); // no language param
        if ( ! is_array( $response ) || ! isset( $response['available_payment_methods'] ) ) {
            // 4a. Fallback: return expanded whitelist unchanged.
            $this->resolved_dnqr_group = $has_group_member ? self::DUITNOW_GROUP : array();
            $this->log_info( sprintf( 'dnqr resolver: API failed, fallback to expanded whitelist=%s', implode( ',', $expanded ) ) );
            return $expanded;
        }
        $available = $response['available_payment_methods']; // e.g. ['dnqr', 'fpx', ...]
        set_transient( $cache_key, $available, 30 * MINUTE_IN_SECONDS );
    }

    // 5. Intersect: keep only group members the merchant actually has.
    $resolved_group = array_values( array_intersect( self::DUITNOW_GROUP, $available ) );

    // 6. Priority: dnqr wins when both are present.
    if ( in_array( 'dnqr', $resolved_group, true ) ) {
        $resolved_group = array_values( array_diff( $resolved_group, array( 'duitnow_qr' ) ) );
    }

    // 7. Cache for bypass_chip() to read.
    $this->resolved_dnqr_group = $resolved_group;

    // 8. Build final whitelist: original entries (with group members stripped) + resolved group.
    $final = array_values( array_diff( $expanded, self::DUITNOW_GROUP ) );
    $final = array_merge( $final, $resolved_group );

    $this->log_info( sprintf( 'dnqr resolver: configured=%s expanded=%s available=%s sent=%s preferred=%s',
        implode( ',', $whitelist ),
        implode( ',', $expanded ),
        implode( ',', (array) $available ),
        implode( ',', $final ),
        $resolved_group[0] ?? '(none)'
    ) );

    return $final;
}
```

**Why this shape:**
- Step 1 keeps the API call relevant only when the dnqr group is in play. A `[fpx, mastercard]` whitelist is returned untouched (no API call).
- Step 2's amount-bucketing (round to nearest 100 sen) dramatically improves cache hit rate — a 1-sen cart difference won't bust the cache.
- Step 4a's fallback means an API hiccup never blocks a purchase.
- Step 5-6 implements the "send only what's available, prioritize dnqr" rule.
- Step 8 builds the final whitelist that goes into the `create_payment` payload.

### 3. Helper — `get_duitnow_qr_preferred()`

Returns the `?preferred=` value when the configured whitelist is a pure DuitNow QR group, `''` otherwise. Used by the single-method branch in `bypass_chip()`. The Razer e-wallet switch reads `$this->resolved_dnqr_group` directly to avoid the group-count check (Razer e-wallet selection is always single-method at the customer level).

```php
public function get_duitnow_qr_preferred(): string {
    $whitelist = is_array( $this->payment_method_whitelist ) ? $this->payment_method_whitelist : array();
    $has_dnqr  = count( array_intersect( $whitelist, self::DUITNOW_GROUP ) ) > 0;
    if ( ! $has_dnqr ) {
        return '';
    }
    // Group-count rule: only DuitNow QR, no other groups.
    $other_groups = array_diff( $whitelist, self::DUITNOW_GROUP );
    if ( ! empty( $other_groups ) ) {
        return '';
    }
    $resolved = ! empty( $this->resolved_dnqr_group ) ? $this->resolved_dnqr_group : self::DUITNOW_GROUP;
    return ! empty( $resolved ) ? $resolved[0] : '';
}
```

### 4. `bypass_chip()` rewrite

The DuitNow QR Razer e-wallet switch case now reads `$this->resolved_dnqr_group` (set by the resolver during `process_payment()`) to pick the right `?preferred=` value. The single-method DuitNow QR branch (originally L2981) is extended to handle the dnqr group and applies the same priority.

```php
public function bypass_chip( $url, $payment ) {
    if ( 'yes' === $this->bypass_chip && ! $payment['is_test'] ) {
        if ( isset( $_POST['chip_fpx_bank'] ) && ! empty( $_POST['chip_fpx_bank'] ) ) {
            $url .= '?preferred=fpx&fpx_bank_code=' . sanitize_text_field( wp_unslash( $_POST['chip_fpx_bank'] ) );
        } elseif ( isset( $_POST['chip_fpx_b2b1_bank'] ) && ! empty( $_POST['chip_fpx_b2b1_bank'] ) ) {
            $url .= '?preferred=fpx_b2b1&fpx_bank_code=' . sanitize_text_field( wp_unslash( $_POST['chip_fpx_b2b1_bank'] ) );
        } elseif ( isset( $_POST['chip_razer_ewallet'] ) && ! empty( $_POST['chip_razer_ewallet'] ) ) {
            $razer_ewallet = sanitize_text_field( wp_unslash( $_POST['chip_razer_ewallet'] ) );
            $preferred     = '';
            switch ( $razer_ewallet ) {
                case 'Atome':           $preferred = 'razer_atome';      break;
                case 'GrabPay':         $preferred = 'razer_grabpay';    break;
                case 'TNG-EWALLET':     $preferred = 'razer_tng';        break;
                case 'ShopeePay':       $preferred = 'razer_shopeepay';  break;
                case 'MB2U_QRPay-Push': $preferred = 'razer_maybankqr';  break;
                case 'duitnow-qr':
                    // Priority: dnqr if available, duitnow_qr fallback.
                    // Reuse the resolver output from process_payment().
                    $group     = ! empty( $this->resolved_dnqr_group ) ? $this->resolved_dnqr_group : self::DUITNOW_GROUP;
                    $preferred = ! empty( $group ) ? $group[0] : 'duitnow_qr';
                    break;
            }
            if ( '' !== $preferred ) {
                $url .= '?preferred=' . $preferred . '&razer_bank_code=' . $razer_ewallet;
            }
        } else {
            // Single-method DuitNow QR branch (was L2981): trigger when the
            // configured whitelist is purely the dnqr group.
            $preferred = $this->get_duitnow_qr_preferred();
            if ( '' !== $preferred ) {
                $url .= '?preferred=' . $preferred;
            }
        }
    } elseif ( 'wc_gateway_chip_5' === $this->id ) {
        $url .= '?preferred=razer_atome&razer_bank_code=Atome';
    }
    return $url;
}
```

The L2981 single-method `duitnow_qr` branch is extended (not removed): its trigger is changed from "whitelist is exactly `[duitnow_qr]`" to "whitelist is purely the dnqr group" (via `get_duitnow_qr_preferred()`). It now handles `[duitnow_qr]`, `[dnqr]`, and `[duitnow_qr, dnqr]` (when the dnqr group is the only group configured).

### 5. `list_razer_ewallets()` change

The `duitnow-qr` entry is kept. The trigger condition is widened to show the option when either dnqr-group member is in the configured whitelist:

```php
if ( count( array_intersect( $this->payment_method_whitelist, self::DUITNOW_GROUP ) ) > 0 ) {
    $ewallet_list['duitnow-qr'] = __( 'Duitnow QR', 'chip-for-woocommerce' );
}
```

The frontend key stays `duitnow-qr` and the display label stays `Duitnow QR`. Customers see one dropdown option; the plugin picks the right API key at the bypass_chip site.

### 6. `process_payment()` change

The single site to modify is L1771-1772:

```php
if ( is_array( $this->payment_method_whitelist ) && ! empty( $this->payment_method_whitelist ) ) {
    $params['payment_method_whitelist'] = $this->payment_method_whitelist;
}
```

becomes:

```php
if ( is_array( $this->payment_method_whitelist ) && ! empty( $this->payment_method_whitelist ) ) {
    $woocommerce_currency = get_woocommerce_currency();
    $order_total          = $order->get_total();
    $amount               = (int) round( $order_total * 100 ); // sen
    $params['payment_method_whitelist'] = $this->resolve_duitnow_methods(
        $this->payment_method_whitelist,
        $woocommerce_currency,
        $amount
    );
}
```

The currency/amount resolution follows the same shape as the existing `payment_recurring_methods()` call at L2097-2108.

**Subscription override at L1785 is intentionally NOT modified.** That line sets `$params['payment_method_whitelist'] = $this->get_payment_method_for_recurring();` for subscription orders, and `get_payment_method_for_recurring()` (L3579) restricts the list to `visa`, `mastercard`, `maestro` only — DuitNow QR is never part of a recurring whitelist. The resolver does not need to run on the recurring path because the dnqr group cannot be selected by `get_payment_method_for_recurring()`.

### 7. `get_payment_method_list()` change

`duitnow_qr` and `dnqr` are **removed** from this list. The dnqr group is controlled by a separate `enable_dnqr_group` form field (Section 8).

```php
public function get_payment_method_list() {
    return array(
        'fpx'             => 'FPX',
        'fpx_b2b1'        => 'FPX B2B1',
        'mastercard'      => 'Mastercard',
        'maestro'         => 'Maestro',
        'visa'            => 'Visa',
        'mpgs_google_pay' => 'Google Pay',
        'mpgs_apple_pay'  => 'Apple Pay',
        'razer_atome'     => 'Atome',
        'razer_grabpay'   => 'GrabPay',
        'razer_maybankqr' => 'Maybank QRPay',
        'razer_shopeepay' => 'ShopeePay',
        'razer_tng'       => "Touch 'n Go eWallet",
    );
}
```

### 8. New form field `enable_dnqr_group`

A separate single-checkbox form field added to `init_form_fields()`, placed near the `payment_method_whitelist` multiselect for visual grouping.

```php
$this->form_fields['enable_dnqr_group'] = array(
    'title'       => __( 'DuitNow QR', 'chip-for-woocommerce' ),
    'label'       => __( 'Accept DuitNow QR payments', 'chip-for-woocommerce' ),
    'type'        => 'checkbox',
    'description' => __( 'Uses dnqr when available for this merchant, falls back to duitnow_qr. Recommended.', 'chip-for-woocommerce' ),
    'default'     => 'no',
);
```

For Gateway 6, `default` is overridden to `'yes'` (Section 9).

### 9. Effective whitelist — constructor change

The constructor (around L265) currently sets `$this->payment_method_whitelist = $this->get_option( 'payment_method_whitelist' );`. After the change, it builds the **effective whitelist** by:

1. Reading the saved `payment_method_whitelist` option (multiselect — no dnqr-group keys).
2. Reading the saved `enable_dnqr_group` option.
3. **Backward compatibility migration:** if `enable_dnqr_group` is unset AND `duitnow_qr` is in the saved multiselect, treat `enable_dnqr_group` as `'yes'` and strip `duitnow_qr` from the multiselect value (one-time auto-migration at load time; doesn't write back to the DB).
4. If `enable_dnqr_group === 'yes'`, inject `['duitnow_qr', 'dnqr']` into the whitelist.

```php
public function init_settings() {
    parent::init_settings();
    $whitelist = $this->get_option( 'payment_method_whitelist', array() );
    if ( ! is_array( $whitelist ) ) {
        $whitelist = array();
    }
    $enable_dnqr_group = $this->get_option( 'enable_dnqr_group', null );

    // Backward-compat migration: legacy saved values contained 'duitnow_qr'
    // in the multiselect. Treat that as enable_dnqr_group='yes' for one
    // migration cycle, but do not mutate the saved option.
    if ( null === $enable_dnqr_group && in_array( 'duitnow_qr', $whitelist, true ) ) {
        $enable_dnqr_group = 'yes';
        $whitelist         = array_values( array_diff( $whitelist, array( 'duitnow_qr' ) ) );
    }
    if ( null === $enable_dnqr_group ) {
        $enable_dnqr_group = 'no';
    }

    $this->enable_dnqr_group       = $enable_dnqr_group;
    $this->payment_method_whitelist = $whitelist;
    if ( 'yes' === $enable_dnqr_group ) {
        $this->payment_method_whitelist = array_values(
            array_unique( array_merge( $this->payment_method_whitelist, self::DUITNOW_GROUP ) )
        );
    }
}
```

The `parent::init_settings()` call (L262 area) is preserved. The new property `enable_dnqr_group` is added to the property block alongside `$resolved_dnqr_group` (Task 1).

**Backward-compat note:** Existing merchants who saved `payment_method_whitelist = ['duitnow_qr']` continue to work without intervention. The legacy value is migrated at runtime on every request. The first time they save the gateway settings explicitly (any change to any field), the migration persists because the new `enable_dnqr_group = 'yes'` is written to the option.

### 10. Gateway 6 preset change

```php
// class-chip-woocommerce-gateway-6.php
// Replace the existing preset whitelist default with:
$this->form_fields['enable_dnqr_group']['default'] = 'yes';
// (payment_method_whitelist default stays as array() — the dnqr group is
//  injected at runtime via enable_dnqr_group.)
```

All other Gateway 6 properties (title `'Duitnow QR'`, description `'Pay with Duitnow QR'`, logo default `'duitnow_only'`, id `'wc_gateway_chip_6'`, `PREFERRED_TYPE = 'Duitnow QR'`) stay the same. The merchant-facing UI is now: a checkbox "DuitNow QR — Accept DuitNow QR payments" pre-checked.

## Data flow

### Classic checkout, DuitNow QR-only gateway (single-method path)

1. Customer places order on the WooCommerce checkout page. The gateway is Gateway 6, with `enable_dnqr_group='yes'` and an empty multiselect.
2. At load time, `init_settings()` injects the dnqr group into `$this->payment_method_whitelist`, so the effective whitelist is `[duitnow_qr, dnqr]`.
3. `process_payment()` runs. Currency and total are read from the order.
4. Resolver expands `[duitnow_qr, dnqr]` → `[duitnow_qr, dnqr]` (already expanded).
5. Resolver checks `chip_pm_${brand}_${currency}_${bucket}` transient. On miss, calls `/payment_methods/?brand_id=...&currency=...&amount=...`.
6. Resolver intersects with API response. If API returned `[duitnow_qr, dnqr, fpx]`, intersected group is `[duitnow_qr, dnqr]`. Priority removes `duitnow_qr` → `[dnqr]`.
7. Final whitelist `[dnqr]` goes into `$params['payment_method_whitelist']` for the `create_payment` API call.
8. `$this->resolved_dnqr_group = [dnqr]` is stored on the instance.
9. CHIP returns a `checkout_url`. `bypass_chip()` is invoked, sees the customer's POST has no `chip_razer_ewallet` value, falls into the single-method DuitNow QR branch. `get_duitnow_qr_preferred()` reads `$this->resolved_dnqr_group = [dnqr]` and returns `dnqr`. URL becomes `?preferred=dnqr`.
10. Customer is redirected to CHIP's hosted page.
11. Customer completes payment via QR scan.

### Classic checkout, gateway with Razer e-wallets including DuitNow QR

1. Customer places order. The configured settings: `enable_dnqr_group='yes'`, multiselect includes `razer_grabpay`, `razer_tng`.
2. At load time, `init_settings()` injects the dnqr group. Effective whitelist: `[razer_grabpay, razer_tng, duitnow_qr, dnqr]`.
3. `process_payment()` runs. Resolver expands the dnqr group. After intersection + priority, the resolved group is e.g. `[dnqr]`. The final whitelist sent to CHIP is `[razer_grabpay, razer_tng, dnqr]`.
4. Customer sees the Razer e-wallet dropdown with options including "Duitnow QR" (one entry — both `duitnow_qr` and `dnqr` collapse to the same dropdown option).
5. Customer picks "Duitnow QR" from the dropdown. `$_POST['chip_razer_ewallet'] = 'duitnow-qr'`.
6. `bypass_chip()` enters the Razer e-wallet switch. The `duitnow-qr` case reads `$this->resolved_dnqr_group = [dnqr]`, picks `dnqr` as `$preferred`. URL becomes `?preferred=dnqr&razer_bank_code=duitnow-qr`.
7. Customer is redirected to CHIP's hosted page.

### Legacy merchant migration path

A merchant who saved the gateway before this migration has `payment_method_whitelist = ['duitnow_qr']` in the saved option. No `enable_dnqr_group` value saved.

1. At load time, `init_settings()` sees `enable_dnqr_group` is unset. It checks the saved multiselect, finds `duitnow_qr`, sets `enable_dnqr_group = 'yes'` (in-memory only — does not write to DB), and strips `duitnow_qr` from the in-memory whitelist.
2. Effective whitelist becomes `[duitnow_qr, dnqr]` (after re-injection by the migration helper).
3. The legacy merchant's checkout works exactly like the new Gateway 6 flow above.
4. The next time the merchant saves the gateway settings (any field), `enable_dnqr_group = 'yes'` is persisted to the DB and `duitnow_qr` is permanently removed from the saved multiselect.

### Blocks checkout

Identical to classic. No JS-side change needed — `process_payment_with_context()` returns the redirect URL, Blocks follows it. The Razer e-wallet dropdown JS already handles `chip_razer_ewallet` POSTs, including the `duitnow-qr` value.

### Subscription / recurring

The recurring flow (`payment_recurring_methods()` at L2108) is untouched. Recurring-methods metadata is computed via a separate `/payment_methods/?recurring=true` call during settings validation, not at purchase time. `get_payment_method_for_recurring()` (L3579) restricts the whitelist to `visa`, `mastercard`, `maestro` only — DuitNow QR is never part of a recurring whitelist.

## Error handling

| Failure | Behavior |
|---|---|
| `/payment_methods/` HTTP timeout (>10s) | Resolver logs the failure via `$chip->log_info()`, returns the expanded whitelist unchanged. `?preferred=` falls back to `dnqr` (since priority rule picks it). |
| `/payment_methods/` returns error JSON | Same as timeout. |
| `/payment_methods/` returns success but missing `available_payment_methods` | Same. |
| Transient write fails (DB issue) | Treated as cache miss; re-tries the API on next purchase. No user-facing error. |
| Resolver called without a configured dnqr group | Group expansion is a no-op. Resolver returns the whitelist untouched. `$resolved_dnqr_group` is set to `[]`. |
| Resolver called for a gateway with no `bypass_chip` | `$params['payment_method_whitelist']` is still set correctly; bypass_chip() never runs, so `$this->resolved_dnqr_group` is unused. |
| Customer on Razer + DuitNow QR mixed gateway | Customer picks a Razer e-wallet from the dropdown; the DuitNow QR group is sent alongside but only the Razer e-wallet `?preferred=` is added. The dropdown's "Duitnow QR" entry is one option among the Razer e-wallets — if picked, it routes through the dnqr-priority case. |

## Behavioral reference table

The "Configured" column reflects what the merchant sees: `enable_dnqr_group` (boolean) plus the multiselect (which no longer contains `duitnow_qr` or `dnqr`).

| enable_dnqr_group | Multiselect | API returns | Effective whitelist | Final whitelist sent | `?preferred=` |
|---|---|---|---|---|---|
| `yes` | (anything) | `[duitnow_qr, dnqr]` | `[..., dnqr]` | `[..., dnqr]` | `dnqr` |
| `yes` | (anything) | `[duitnow_qr]` | `[..., duitnow_qr]` | `[..., duitnow_qr]` | `duitnow_qr` |
| `yes` | (anything) | API fails | `[..., dnqr, duitnow_qr]` (fallback) | `[..., dnqr, duitnow_qr]` | `dnqr` |
| `yes` | (anything) | `[dnqr]` | `[..., dnqr]` | `[..., dnqr]` | `dnqr` |
| `no` | `[fpx]` | `[fpx]` | `[fpx]` | `[fpx]` | `fpx` (existing FPX branch) |
| `no` | `[fpx, mastercard]` | `[fpx, mastercard]` | `[fpx, mastercard]` | `[fpx, mastercard]` | (none — card uses `direct_post_url`) |
| `no` | `[razer_grabpay]` | `[razer_grabpay]` | `[razer_grabpay]` | `[razer_grabpay]` | `razer_grabpay` (Razer branch) |
| `no` | `[razer_grabpay, fpx]` | `[razer_grabpay, fpx]` | `[razer_grabpay, fpx]` | `[razer_grabpay, fpx]` | (none — 2 groups) |

**Legacy merchants** who saved the gateway before this migration may have `payment_method_whitelist` containing `duitnow_qr`. The runtime migration in `init_settings()` (Section 9) treats that as `enable_dnqr_group='yes'` and strips `duitnow_qr` from the multiselect value at load time, so the behavior matches the new model without a DB migration.

## Files changed

| File | Change |
|---|---|
| `includes/class-chip-woocommerce-gateway.php` | Add `DUITNOW_GROUP` const, `$resolved_dnqr_group` and `$enable_dnqr_group` properties. Add `resolve_duitnow_methods()`, `get_duitnow_qr_preferred()`. Modify `get_payment_method_list()` to **remove** `duitnow_qr` and `dnqr`. Add new form field `enable_dnqr_group`. Modify `init_settings()` to build the effective whitelist (with backward-compat migration for legacy `payment_method_whitelist` containing `duitnow_qr`). Modify `process_payment()` to call resolver. Modify `bypass_chip()`: extend the `duitnow-qr` Razer e-wallet case to apply priority using `$this->resolved_dnqr_group`; extend the L2981 single-method branch trigger to handle the dnqr group. Modify `list_razer_ewallets()`: widen the trigger to `array_intersect( ..., DUITNOW_GROUP )`. |
| `includes/class-chip-woocommerce-gateway-6.php` | Change preset: set `enable_dnqr_group` default to `'yes'`. Title, description, logo, ID, and form fields stay the same. |
| `readme.txt` | Add a "Tweak" or "Add" line describing the migration. |
| `changelog.txt` | Add a new line describing the dnqr group support. |
| `README.md` | Optional — same content as readme.txt. |

## Files NOT changed

- `includes/class-chip-woocommerce-api.php` — `payment_methods()` already exists at L161.
- `includes/blocks/class-chip-woocommerce-gateway-blocks-support.php` — auto-redirect is server-side.
- All JS files in `resources/js/frontend/`.
- `class-chip-woocommerce-gateway-2.php` through `_5.php` — unaffected.
- DB / settings / order meta — no migration.

## Testing strategy

The repo has no unit tests (`CLAUDE.md` confirms). Testing is manual + integration:

1. **Gateway 6 default settings, merchant has both `duitnow_qr` and `dnqr`** — Customer clicks Place Order. Single-method DuitNow QR branch fires. Customer is redirected with `?preferred=dnqr`. Payment on CHIP dashboard shows `dnqr`.
2. **Gateway 6 default settings, merchant has only `duitnow_qr`** — Customer is redirected with `?preferred=duitnow_qr`. Payment shows `duitnow_qr`.
3. **Gateway 6 default settings, `/payment_methods/` API times out** — Customer is redirected with `?preferred=dnqr` (fallback, since `DUITNOW_GROUP[0] = 'dnqr'`). Payment may fail on CHIP if dnqr not actually available — accepted per fallback policy.
4. **Gateway with Razer e-wallets including DuitNow QR, merchant has both** — Customer picks "Duitnow QR" from the Razer dropdown. Redirect URL has `?preferred=dnqr&razer_bank_code=duitnow-qr`. Payment on CHIP shows `dnqr`.
5. **Gateway with Razer e-wallets including DuitNow QR, merchant has only `duitnow_qr`** — Customer picks "Duitnow QR" from the Razer dropdown. Redirect URL has `?preferred=duitnow_qr&razer_bank_code=duitnow-qr`. Payment shows `duitnow_qr`.
6. **Gateway with Razer e-wallets, NO DuitNow QR configured** — Razer e-wallet dropdown does NOT include "Duitnow QR" entry. No regressions.
7. **Base gateway, whitelist `[duitnow_qr, fpx]`** — No `?preferred=` (2 groups). API receives `[dnqr, fpx]` after expansion + intersection + priority.
8. **Base gateway, whitelist `[dnqr]`, merchant has only `duitnow_qr`** — API receives `[duitnow_qr]`. No Razer e-wallet dropdown involved (no `razer_*` in whitelist). Single-method DuitNow QR branch fires. Customer redirected with `?preferred=duitnow_qr`.
9. **Base gateway, whitelist `[visa, mastercard]`** — Resolver is a no-op. Card flow unchanged.
10. **Subscription renewal** — Verify recurring `payment_recurring_methods()` call site at L2108 is not affected. `get_payment_method_for_recurring()` still restricts to cards.
11. **Cache hit/miss** — First purchase after settings save calls API; second purchase within 30 min uses cache. Verify via logs.
12. **Razer dropdown shows "Duitnow QR" when configured** — For any gateway where the whitelist intersects the dnqr group, the e-wallet dropdown includes the "Duitnow QR" option alongside Atome, GrabPay, MB2U_QRPay-Push, ShopeePay, TNG-EWALLET.
13. **Admin UI** — Gateway 6 settings page shows the `enable_dnqr_group` checkbox pre-checked. The base gateway's settings page shows the `enable_dnqr_group` checkbox unchecked by default. The `payment_method_whitelist` multiselect does NOT contain `duitnow_qr` or `dnqr` as options.
14. **Legacy merchant migration** — A gateway saved before this plugin version (with `payment_method_whitelist = ['duitnow_qr']` in the DB) continues to accept DuitNow QR payments without intervention. The runtime migration in `init_settings()` treats the legacy value as `enable_dnqr_group='yes'`. After the merchant saves any gateway settings, the migration persists.

## Risks and open questions

- **Cache key invalidation:** If the merchant changes their CHIP account (e.g. gains dnqr availability), the cache holds the stale list for up to 30 minutes. Acceptable per agreed behavior, but worth noting.
- **Fallback accuracy:** When `/payment_methods/` fails, the resolver sends both `duitnow_qr` and `dnqr`. CHIP will pick whichever it accepts; if neither is actually available, the purchase fails. This is the agreed fallback policy.
- **Third identifier future:** If CHIP introduces `dnqr2` or similar, the constant `DUITNOW_GROUP` is the one place to extend.