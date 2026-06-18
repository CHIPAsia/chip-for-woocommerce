# DuitNow QR → dnqr migration — Design

**Date:** 2026-06-18
**Status:** Draft, pending user review

## Background

CHIP is replacing the legacy `duitnow_qr` payment method with a new method called `dnqr`. Both methods coexist for the foreseeable future: legacy merchants keep `duitnow_qr`, new merchants are onboarded straight onto `dnqr`, and CHIP's `/payment_methods/` endpoint reports which method(s) each merchant actually has available.

The plugin must accept both `duitnow_qr` and `dnqr` in its payment-method whitelist, treat them as a single logical "DuitNow QR" group, and pick the correct concrete method at runtime based on what the merchant has.

## Goals

- Add `dnqr` as a recognized payment-method identifier alongside `duitnow_qr`.
- Make the gateway's `payment_method_whitelist` setting work for both old and new merchants without UI confusion.
- Decide at runtime which concrete method to send to CHIP, using `/payment_methods/` as the source of truth.
- Prefer `dnqr` when both are available; fall back to `duitnow_qr` when only that is available.
- DuitNow QR is **not** a Razer e-wallet — it must be removed from the Razer e-wallet dropdown.
- No DB migration, no settings-page migration, no breaking changes for existing merchants.

## Non-goals

- Migrating stored order meta or settings values (none change).
- Adding a new clone gateway (Gateway 6 stays the single DuitNow QR gateway; its preset is widened).
- JS-side UI for a DuitNow QR button (auto-redirect on `Place Order` is sufficient).
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
| `Chip_Woocommerce_Gateway::get_payment_method_list()` | Add `'dnqr' => 'DuitNow QR (new)'` next to `'duitnow_qr' => 'Duitnow QR'`. |
| `Chip_Woocommerce_Gateway` (new protected method) `resolve_duitnow_methods( $whitelist, $currency, $amount )` | Encapsulates group expansion → API check → intersection → dnqr-priority → fallback. Returns the final whitelist to send. |
| `Chip_Woocommerce_Gateway` (new protected property) `$resolved_dnqr_group` | Caches the resolver's dnqr-group output for `bypass_chip()` to read without re-hitting the API. |
| `Chip_Woocommerce_Gateway` (new public method) `get_duitnow_qr_preferred()` | Returns the `?preferred=` value (`dnqr` or `duitnow_qr`) when the configured whitelist is a pure DuitNow QR group, `''` otherwise. |
| `Chip_Woocommerce_Gateway::process_payment()` (L1771-1772) | Replace `$params['payment_method_whitelist'] = $this->payment_method_whitelist;` with a call to the resolver; store the resolved subset on the instance. (L1785 subscription override is untouched.) |
| `Chip_Woocommerce_Gateway::bypass_chip()` | Remove the `case 'duitnow-qr':` from the Razer e-wallet switch. Remove the L2981 single-method `duitnow_qr` branch. Add a new server-driven branch that uses `get_duitnow_qr_preferred()`. |
| `Chip_Woocommerce_Gateway::list_razer_ewallets()` (L2930) | Remove the `duitnow-qr` entry entirely. |
| `class-chip-woocommerce-gateway-6.php` | Change preset whitelist from `['duitnow_qr']` to `['duitnow_qr', 'dnqr']`. Title, description, logo, ID, and form fields stay the same. |

### What does NOT change

- Stored order meta, settings options, or DB schema (no migration).
- `Chip_Woocommerce_API::payment_methods()` — already exists at L161.
- `class-chip-woocommerce-gateway-blocks-support.php` — auto-redirect is server-side, no JS work needed.
- All JS files in `resources/js/frontend/`.
- Clones 2–5.
- `?preferred=` for card, FPX, FPX B2B1, or Razer e-wallet flows.

## Component design

### 1. Constants and properties

```php
// New class constant on Chip_Woocommerce_Gateway.
const DUITNOW_GROUP = array( 'duitnow_qr', 'dnqr' );

// New instance property.
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

Returns the `?preferred=` value when the configured whitelist is a pure DuitNow QR group, `''` otherwise.

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
    $resolved = isset( $this->resolved_dnqr_group ) ? $this->resolved_dnqr_group : self::DUITNOW_GROUP;
    return ! empty( $resolved ) ? $resolved[0] : '';
}
```

### 4. `bypass_chip()` rewrite

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
                // No 'duitnow-qr' case — DuitNow QR is server-driven now.
            }
            if ( '' !== $preferred ) {
                $url .= '?preferred=' . $preferred . '&razer_bank_code=' . $razer_ewallet;
            }
        } else {
            // DuitNow QR branch: server-driven, no customer input.
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

The L2981 single-method `duitnow_qr` branch is gone. It's replaced by `get_duitnow_qr_preferred()` which handles the wider dnqr group correctly.

### 5. `list_razer_ewallets()` change

Remove the `duitnow-qr` entry entirely. Razer e-wallet dropdown is now strictly Atome, GrabPay, MB2U_QRPay-Push, ShopeePay, TNG-EWALLET.

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
        'duitnow_qr'      => 'Duitnow QR',
        'dnqr'            => 'DuitNow QR (new)',
    );
}
```

The `dnqr` label is disambiguated so merchants in the multiselect UI can see they are two different identifiers even though both belong to the same logical group.

### 8. Gateway 6 preset change

```php
// class-chip-woocommerce-gateway-6.php L50
$this->form_fields['payment_method_whitelist']['default'] = array( 'duitnow_qr', 'dnqr' );
```

All other Gateway 6 properties (title `'Duitnow QR'`, description `'Pay with Duitnow QR'`, logo default `'duitnow_only'`, id `'wc_gateway_chip_6'`, `PREFERRED_TYPE = 'Duitnow QR'`) stay the same. The merchant-facing UI is identical.

## Data flow

### Classic checkout, DuitNow QR-only gateway

1. Customer places order on the WooCommerce checkout page.
2. `process_payment()` runs. Currency and total are read from the order.
3. Resolver expands `[duitnow_qr, dnqr]` → `[duitnow_qr, dnqr]` (already expanded).
4. Resolver checks `chip_pm_${brand}_${currency}_${bucket}` transient. On miss, calls `/payment_methods/?brand_id=...&currency=...&amount=...`.
5. Resolver intersects with API response. If API returned `[duitnow_qr, dnqr, fpx]`, intersected group is `[duitnow_qr, dnqr]`. Priority removes `duitnow_qr` → `[dnqr]`.
6. Final whitelist `[dnqr]` goes into `$params['payment_method_whitelist']` for the `create_payment` API call.
7. `$this->resolved_dnqr_group = [dnqr]` is stored on the instance.
8. CHIP returns a `checkout_url`. `bypass_chip()` appends `?preferred=dnqr` (via `get_duitnow_qr_preferred()`).
9. Customer is redirected to CHIP's hosted page.
10. Customer completes payment via QR scan.

### Blocks checkout

Identical to classic. No JS-side change needed — `process_payment_with_context()` returns the redirect URL, Blocks follows it.

### Subscription / recurring

The recurring flow (`payment_recurring_methods()` at L2108) is untouched. Recurring-methods metadata is computed via a separate `/payment_methods/?recurring=true` call during settings validation, not at purchase time.

## Error handling

| Failure | Behavior |
|---|---|
| `/payment_methods/` HTTP timeout (>10s) | Resolver logs the failure via `$chip->log_info()`, returns the expanded whitelist unchanged. `?preferred=` falls back to `dnqr` (since priority rule picks it). |
| `/payment_methods/` returns error JSON | Same as timeout. |
| `/payment_methods/` returns success but missing `available_payment_methods` | Same. |
| Transient write fails (DB issue) | Treated as cache miss; re-tries the API on next purchase. No user-facing error. |
| Resolver called without a configured dnqr group | Group expansion is a no-op. Resolver returns the whitelist untouched. `$resolved_dnqr_group` is set to `[]`. |
| Resolver called for a gateway with no `bypass_chip` | `$params['payment_method_whitelist']` is still set correctly; bypass_chip() never runs, so `$this->resolved_dnqr_group` is unused. |
| Customer on Razer + DuitNow QR mixed gateway | Customer picks a Razer e-wallet; the DuitNow QR group is sent alongside but only the Razer e-wallet `?preferred=` is added. No DuitNow QR option in the Razer dropdown. |

## Behavioral reference table

| Configured | API returns | Final whitelist sent | `?preferred=` |
|---|---|---|---|
| `[duitnow_qr]` | `[duitnow_qr, dnqr]` | `[dnqr]` | `dnqr` |
| `[duitnow_qr]` | `[duitnow_qr]` | `[duitnow_qr]` | `duitnow_qr` |
| `[duitnow_qr]` | API fails | `[duitnow_qr, dnqr]` (fallback) | `dnqr` |
| `[dnqr]` | `[dnqr]` | `[dnqr]` | `dnqr` |
| `[dnqr]` | `[duitnow_qr]` | `[duitnow_qr]` | `duitnow_qr` |
| `[duitnow_qr, dnqr]` | `[dnqr]` | `[dnqr]` | `dnqr` |
| `[duitnow_qr, dnqr]` | `[duitnow_qr, dnqr]` | `[dnqr]` | `dnqr` |
| `[duitnow_qr, fpx]` | `[duitnow_qr, dnqr, fpx]` | `[dnqr, fpx]` | (none — 2 groups) |
| `[duitnow_qr, dnqr, fpx]` | `[dnqr, fpx]` | `[dnqr, fpx]` | (none — 2 groups) |
| `[duitnow_qr, visa]` | `[duitnow_qr, dnqr, visa]` | `[dnqr, visa]` | (none — 2 groups) |
| `[visa, mastercard]` | `[visa, mastercard]` | `[visa, mastercard]` | (none — card uses `direct_post_url`) |
| `[fpx]` | `[fpx]` | `[fpx]` | `fpx` (existing FPX branch) |
| `[fpx, mastercard]` | `[fpx, mastercard]` | `[fpx, mastercard]` | (none — 2 groups) |
| `[Atome]` (Razer e-wallet) | `[Atome]` | `[razer_atome]` | `razer_atome` (Razer e-wallet branch) |
| `[Atome, dnqr]` | `[Atome, dnqr]` | `[razer_atome, dnqr]` | (none — 2 groups) |

## Files changed

| File | Change |
|---|---|
| `includes/class-chip-woocommerce-gateway.php` | Add `DUITNOW_GROUP` const + `$resolved_dnqr_group` property. Add `resolve_duitnow_methods()`, `get_duitnow_qr_preferred()`. Modify `get_payment_method_list()` to include `dnqr`. Modify `process_payment()` to call resolver before assigning `$params['payment_method_whitelist']`. Modify `bypass_chip()`: remove `duitnow-qr` case from Razer switch, remove L2981 single-method branch, add new server-driven dnqr branch using `get_duitnow_qr_preferred()`. Remove the `duitnow-qr` entry from `list_razer_ewallets()`. |
| `includes/class-chip-woocommerce-gateway-6.php` | Change preset whitelist from `['duitnow_qr']` to `['duitnow_qr', 'dnqr']`. Title, description, logo default, ID, form fields — all unchanged. |
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

1. **Gateway 6 default settings, merchant has both `duitnow_qr` and `dnqr`** — Customer is redirected with `?preferred=dnqr`. Payment on CHIP dashboard shows `dnqr`.
2. **Gateway 6 default settings, merchant has only `duitnow_qr`** — Customer is redirected with `?preferred=duitnow_qr`. Payment shows `duitnow_qr`.
3. **Gateway 6 default settings, `/payment_methods/` API times out** — Customer is redirected with `?preferred=dnqr` (fallback). Payment may fail on CHIP if dnqr not actually available — accepted per fallback policy.
4. **Base gateway, whitelist `[duitnow_qr, fpx]`** — No `?preferred=` (2 groups). API receives `[dnqr, fpx]` after expansion + intersection + priority.
5. **Base gateway, whitelist `[duitnow_qr]`, merchant has both** — API receives `[dnqr]`. No Razer e-wallet dropdown involved. Customer redirected with `?preferred=dnqr`.
6. **Base gateway, whitelist `[dnqr]`, merchant has only `duitnow_qr`** — API receives `[duitnow_qr]`. Customer redirected with `?preferred=duitnow_qr`.
7. **Base gateway, whitelist `[visa, mastercard]`** — Resolver is a no-op. Card flow unchanged.
8. **Subscription renewal** — Verify recurring `payment_recurring_methods()` call site at L2108 is not affected.
9. **Cache hit/miss** — First purchase after settings save calls API; second purchase within 30 min uses cache. Verify via logs.
10. **Razer dropdown no longer contains Duitnow QR** — For any gateway, the e-wallet dropdown only shows Atome, GrabPay, MB2U_QRPay-Push, ShopeePay, TNG-EWALLET.
11. **Razer + DuitNow QR mixed gateway** — Customer picks a Razer e-wallet; the API receives both groups; no `?preferred=` is added (2 groups).
12. **Admin UI** — Gateway 6 settings page shows the `[duitnow_qr, dnqr]` preset selected. The base gateway's multiselect shows the new "DuitNow QR (new)" option labeled `dnqr`.

## Risks and open questions

- **Cache key invalidation:** If the merchant changes their CHIP account (e.g. gains dnqr availability), the cache holds the stale list for up to 30 minutes. Acceptable per agreed behavior, but worth noting.
- **Fallback accuracy:** When `/payment_methods/` fails, the resolver sends both `duitnow_qr` and `dnqr`. CHIP will pick whichever it accepts; if neither is actually available, the purchase fails. This is the agreed fallback policy.
- **Third identifier future:** If CHIP introduces `dnqr2` or similar, the constant `DUITNOW_GROUP` is the one place to extend.