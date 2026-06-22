# Unified Dropdown Redesign — Design

**Date:** 2026-06-22
**Status:** Draft, pending user review

## Background

The CHIP for WooCommerce plugin's checkout flow uses **three separate POST fields** to identify which payment method the customer chose:

- `$_POST['chip_fpx_bank']` — FPX B2C bank code (e.g. `MB2U0227`)
- `$_POST['chip_fpx_b2b1_bank']` — FPX B2B1 bank code (e.g. `PBB0234`)
- `$_POST['chip_razer_ewallet']` — Razer e-wallet display name (e.g. `GrabPay`, `TNG-EWALLET`, `duitnow-qr`)

Each field has its own JS list component, its own REST endpoint branch, and its own `if/elseif` chain in `bypass_chip()`. The result is fragmented: a gateway with multiple methods (e.g. FPX + GrabPay) renders the FPX dropdown separately from the Razer dropdown, and the customer must pick from the right one.

The dnqr migration (2.0.6) added DuitNow QR as a third dropdown option, and a fourth "Card" pseudo-method (visa/mastercard/maestro) sits outside the dropdown entirely. The frontend has 4+ different "lists" the customer may see, depending on the gateway's whitelist.

The goal is to **replace the three separate dropdowns with one unified dropdown** listing all eligible payment methods (FPX banks, Razer e-wallets, DuitNow QR, Card), each as a single option. The customer picks one option, the backend parses a tag-encoded value, and dispatches to the right `?preferred=...` URL — or, for Card, to the existing direct-post flow.

## Goals

- Replace the three POST fields with one tag-encoded `chip_payment_method` field.
- Replace the three JS list components (`FpxBankList`, `Fpxb2b1BankList`, `RazerEWalletList`) with one shared `UnifiedPaymentMethodList` component.
- Add a new REST endpoint type `unified` that returns the combined list.
- Update `bypass_chip()` to parse the tag and dispatch to the right URL shape.
- Support Card coexistence: when the whitelist has both card methods and dropdown methods, show the unified dropdown above the card form.
- Card is a first-class group, parallel to DuitNow QR: a single `card` key in the merchant multiselect expands to `['visa', 'mastercard', 'maestro']` at runtime.

## Non-goals

- JS modernization (hooks refactor, `wp.element` migration). The new component uses the same patterns as the existing code.
- Card brand logos in the dropdown. The card form detects the brand from the card number prefix (existing logic).
- Saved-card tokenization UI changes. Saved cards are shown alongside the unified dropdown (per maintainer decision), but the WC tokenization flow itself is unchanged.
- Removing the old `chip_fpx_bank` / `chip_fpx_b2b1_bank` / `chip_razer_ewallet` POST fields from the `bypass_chip()` fallback. The new code reads only `chip_payment_method`; the old fields are dead code. (They could be removed in a follow-up cleanup PR.)
- Touching the `wc_gateway_chip_5` (Atome) clone's JS or PHP. It stays separate with no JS UI; `bypass_chip()` short-circuits to the Atome redirect.

## Glossary

| Term | Meaning |
|---|---|
| **Unified dropdown** | The single `<select name="chip_payment_method">` rendered at checkout when the gateway's whitelist contains at least one dropdown-eligible method (FPX, Razer, DuitNow QR). |
| **Tag** | The value attribute of an `<option>` in the unified dropdown. Colon-separated: `<method_type>:<item_code>`, or just `<method_type>` for DuitNow QR and Card. |
| **Card group** | The set `{visa, mastercard, maestro}` — three methods that the merchant enables as a single "Card" option. Mirrors the dnqr group. |
| **Dnqr group** | The set `{duitnow_qr, dnqr}` — same as before, unchanged. |
| **Direct-post flow** | The card payment path: customer enters card details, frontend POSTs to `direct_post_url` (set by `process_payment()`), no `?preferred=` redirect. Unchanged. |
| **Atome separate gateway** | `wc_gateway_chip_5` clone, which has no JS UI and forces `?preferred=razer_atome&razer_bank_code=Atome` in `bypass_chip()`. Unchanged. |

## Architecture overview

The redesign replaces 3 POST fields with 1 tag-encoded field across the JS, PHP, REST, and classic-checkout layers. The dnqr group logic (resolver, `get_duitnow_qr_preferred()`, group expansion) is unchanged. The Card group is added as a parallel concept.

### The unit-level changes

| Unit | Change |
|---|---|
| `Chip_Woocommerce_Gateway::DUITNOW_GROUP` (existing) | Unchanged. Still `['duitnow_qr', 'dnqr']`. |
| `Chip_Woocommerce_Gateway::CARD_GROUP` (new) | New constant. `['visa', 'mastercard', 'maestro']`. |
| `Chip_Woocommerce_Gateway::get_payment_method_list()` | Add back `'card' => 'Card'` as a single multiselect entry. The three old keys `'visa'`, `'mastercard'`, `'maestro'` are NOT in the list (they're injected at runtime via group expansion). The list now has 10 entries. |
| `Chip_Woocommerce_Gateway::__construct()` | Extend the group-expansion block. After the existing `duitnow_qr` expansion, add: when `'card'` is in the saved multiselect, expand to `CARD_GROUP` in-memory. Same pattern as the dnqr group expansion. |
| `Chip_Woocommerce_Gateway::list_unified_payment_methods()` (new) | Returns a flat JSON object: `{ 'fpx:MB2U0227': 'Maybank (B2C)', ..., 'card': 'Card (Visa/Mastercard/Maestro)', 'dnqr': 'DuitNow QR' }`. Skips empty/placeholder entries. |
| `Chip_Woocommerce_Gateway::bypass_chip()` | Replaces the 3-POST-field if/elseif chain with one tag parser. Reads `$_POST['chip_payment_method']`, splits on `:`, dispatches to the right URL shape. The dnqr path uses `$this->resolved_dnqr_group` (existing dnqr-priority logic, unchanged). The Atome separate-gateway fallback is preserved. |
| `Chip_Woocommerce_Gateway::payment_fields()` (classic) | When the unified dropdown is in scope, render ONE `<select name="chip_payment_method">` instead of the three current selects. The card form (`<id>-card-name`, etc.) is rendered alongside when card methods are in the whitelist. |
| `Chip_Woocommerce_Gateway::validate_fields()` | Replaces the three required-field checks with one: `chip_payment_method` must be non-empty. |
| `Chip_Woocommerce::get_banks_endpoint()` (REST) | Add a new branch for `type='unified'` that calls `list_unified_payment_methods()`. Existing `fpx_b2c`/`fpx_b2b1`/`razer` types stay (backward compat). |
| `Chip_Woocommerce_Gateway_Blocks_Support` | Add `bank_type='unified'` and `js_display='unified'` to the decision matrix. When `js_display='unified'`, the React `ContentContainer` renders `UnifiedPaymentMethodList` AND `CardForm` (stacked). |
| `resources/js/frontend/blocks_chip_woocommerce_gateway{1,2,3,4,6}.js` | Replace `FpxBankList` + `Fpxb2b1BankList` + `RazerEWalletList` with one shared `UnifiedPaymentMethodList` component. Submits `chip_payment_method` with the tag-encoded value. |
| `webpack.config.js` | Update entry points to share the new component across the 5 affected clones. |
| `assets/duitnow_qr.png` | NEW asset. 50x50 small icon for the DuitNow QR option in the unified dropdown. |
| `tests/*` | Update existing tests for the new `get_payment_method_list()` (10 entries) and constructor's Card group expansion. Add new tests for `list_unified_payment_methods()` and the `bypass_chip()` tag parser. |

### What does NOT change

- The dnqr resolver (`resolve_duitnow_methods()`) — unchanged.
- `get_duitnow_qr_preferred()` — unchanged.
- The 6 clone gateway PHP classes — unchanged (Gateway 6's `duitnow_qr` preset still works).
- The Razer e-wallet switch's `case 'duitnow-qr':` logic — moved to the tag parser, same behavior.
- The CI workflow (`.github/workflows/test.yml`) — unchanged.
- `composer.json` — no new dev dependencies.
- `package.json` — no new JS dependencies.
- The Atome clone (`wc_gateway_chip_5`) — unchanged.

## Component design

### 1. The tag format

Each `<option>` in the unified dropdown has a value attribute that's a colon-separated tag:

```
<method_type>:<item_code>
```

For DuitNow QR and Card (no inner code), the tag is just the method type:

```
dnqr
card
```

| Method type | Tag format | Example | Maps to `?preferred=` |
|---|---|---|---|
| FPX B2C | `fpx:<bank_code>` | `fpx:MB2U0227` | `fpx&fpx_bank_code=MB2U0227` |
| FPX B2B1 | `fpx_b2b1:<bank_code>` | `fpx_b2b1:PBB0234` | `fpx_b2b1&fpx_bank_code=PBB0234` |
| Razer | `razer:<display_name>` | `razer:GrabPay` | `razer_grabpay&razer_bank_code=GrabPay` (or `razer_atome` for Atome, etc.) |
| DuitNow QR | `dnqr` | `dnqr` | `dnqr` (or `duitnow_qr` fallback) — no `&razer_bank_code=` |
| Card | `card` | `card` | (no `?preferred=` redirect; direct-post flow) |

### 2. `bypass_chip()` tag parser

```php
public function bypass_chip( $url, $payment ) {
    if ( 'yes' !== $this->bypass_chip || $payment['is_test'] ) {
        return $this->maybe_atome_redirect( $url );
    }
    if ( ! isset( $_POST['chip_payment_method'] ) || empty( $_POST['chip_payment_method'] ) ) {
        return $url;
    }
    $value = sanitize_text_field( wp_unslash( $_POST['chip_payment_method'] ) );
    if ( false === strpos( $value, ':' ) ) {
        // No colon: single-method tag ('dnqr' or 'card').
        if ( 'dnqr' === $value ) {
            return $this->build_dnqr_url( $url );
        }
        if ( 'card' === $value ) {
            // Card: no redirect; direct-post flow handles it.
            return $url;
        }
        return $url;
    }
    [ $type, $code ] = explode( ':', $value, 2 );
    switch ( $type ) {
        case 'fpx':
            return $url . '?preferred=fpx&fpx_bank_code=' . $code;
        case 'fpx_b2b1':
            return $url . '?preferred=fpx_b2b1&fpx_bank_code=' . $code;
        case 'razer':
            return $this->build_razer_url( $url, $code );
    }
    return $url;
}

private function build_razer_url( $url, $display_name ) {
    $map = array(
        'Atome'           => 'razer_atome',
        'GrabPay'         => 'razer_grabpay',
        'ShopeePay'       => 'razer_shopeepay',
        'TNG-EWALLET'     => 'razer_tng',
        'MB2U_QRPay-Push' => 'razer_maybankqr',
    );
    if ( ! isset( $map[ $display_name ] ) ) {
        return $url;
    }
    return $url . '?preferred=' . $map[ $display_name ] . '&razer_bank_code=' . $display_name;
}

private function build_dnqr_url( $url ) {
    $preferred = $this->get_duitnow_qr_preferred();
    return '' === $preferred ? $url : $url . '?preferred=' . $preferred;
}

private function maybe_atome_redirect( $url ) {
    if ( 'wc_gateway_chip_5' === $this->id ) {
        return $url . '?preferred=razer_atome&razer_bank_code=Atome';
    }
    return $url;
}
```

### 3. `list_unified_payment_methods()` method

```php
public function list_unified_payment_methods(): array {
    $list = array();

    // FPX B2C banks.
    foreach ( $this->list_fpx_banks() as $code => $label ) {
        if ( '' === $code ) { continue; }
        $list[ 'fpx:' . $code ] = $label;
    }

    // FPX B2B1 banks.
    foreach ( $this->list_fpx_b2b1_banks() as $code => $label ) {
        if ( '' === $code ) { continue; }
        $list[ 'fpx_b2b1:' . $code ] = $label;
    }

    // Razer e-wallets (excluding DuitNow QR -- added separately).
    foreach ( $this->list_razer_ewallets() as $code => $label ) {
        if ( '' === $code || 'duitnow-qr' === $code || 'Choose your e-wallet' === $label ) {
            continue;
        }
        $list[ 'razer:' . $code ] = $label;
    }

    // DuitNow QR (only if dnqr group is in the whitelist).
    if ( count( array_intersect( $this->payment_method_whitelist, self::DUITNOW_GROUP ) ) > 0 ) {
        $list['dnqr'] = __( 'DuitNow QR', 'chip-for-woocommerce' );
    }

    // Card (only if card group is in the whitelist).
    if ( count( array_intersect( $this->payment_method_whitelist, self::CARD_GROUP ) ) > 0 ) {
        $list['card'] = __( 'Card (Visa/Mastercard/Maestro)', 'chip-for-woocommerce' );
    }

    return $list;
}
```

### 4. Constructor's group expansion (extended)

```php
public function __construct() {
    parent::__construct();
    $whitelist = $this->get_option( 'payment_method_whitelist', array() );
    if ( ! is_array( $whitelist ) ) {
        $whitelist = array();
    }

    // Backward-compat migration: if the saved whitelist contains any of the
    // expanded card-group keys (visa, mastercard, maestro), collapse them
    // into the single 'card' key in-memory. This handles merchants who
    // had card methods in their multiselect before the unified-dropdown
    // redesign, before they next save the gateway settings.
    if ( count( array_intersect( $whitelist, self::CARD_GROUP ) ) > 0 ) {
        $whitelist = array_values(
            array_diff( $whitelist, self::CARD_GROUP )
        );
        if ( ! in_array( 'card', $whitelist, true ) ) {
            $whitelist[] = 'card';
        }
    }

    // DuitNow QR group expansion: 'duitnow_qr' -> ['duitnow_qr', 'dnqr'].
    if ( in_array( 'duitnow_qr', $whitelist, true ) ) {
        $whitelist = array_values(
            array_unique( array_merge( $whitelist, self::DUITNOW_GROUP ) )
        );
    }

    // Card group expansion: 'card' -> ['visa', 'mastercard', 'maestro'].
    if ( in_array( 'card', $whitelist, true ) ) {
        $whitelist = array_values(
            array_unique( array_merge( $whitelist, self::CARD_GROUP ) )
        );
    }

    $this->payment_method_whitelist = $whitelist;
    // ... rest of constructor unchanged
}
```

The backward-compat migration at the top of the constructor handles merchants who have the old `['visa', 'mastercard', 'maestro']` form in their DB. It collapses them to `'card'` in-memory, so the rest of the code sees the new shape.

### 5. REST endpoint

`Chip_Woocommerce::get_banks_endpoint()` adds a new branch:

```php
public function get_banks_endpoint( WP_REST_Request $request ) {
    $type       = $request->get_param( 'type' );
    $gateway_id = $request->get_param( 'gateway_id' );
    $gateway    = $this->get_gateway_instance( $gateway_id );
    if ( ! $gateway ) {
        return new WP_REST_Response( array( 'error' => 'Invalid gateway' ), 400 );
    }
    switch ( $type ) {
        case 'fpx_b2c':  $banks = $gateway->list_fpx_banks(); break;
        case 'fpx_b2b1': $banks = $gateway->list_fpx_b2b1_banks(); break;
        case 'razer':    $banks = $gateway->list_razer_ewallets(); break;
        case 'unified':  $banks = $gateway->list_unified_payment_methods(); break;
        default:
            return new WP_REST_Response( array( 'error' => 'Invalid type' ), 400 );
    }
    unset( $banks[''] );
    return new WP_REST_Response( $banks, 200 );
}
```

The `chip/v1` route's regex (currently `[a-z0-9_]+`) already accepts `unified`. No route change needed.

### 6. Blocks support class

`bank_type` and `js_display` decision (updated):

| Whitelist shape (post-expansion) | `bank_type` | `js_display` |
|---|---|---|
| `['fpx']` | `fpx_b2c` | `fpx` (existing) |
| `['fpx_b2b1']` | `fpx_b2b1` | `fpx_b2b1` (existing) |
| All `razer_*` (no card, no FPX, no dnqr) | `razer` | `razer` (existing) |
| All card_methods (no FPX, no Razer) | `''` | `card` (existing) |
| Mixed: at least one card + at least one dropdown method | `unified` | `unified` (NEW) |
| Mixed: ≥2 dropdown methods (no card) | `unified` | `unified` (NEW) |
| Otherwise | `''` | `''` |

When `js_display='unified'`, the `ContentContainer` renders `UnifiedPaymentMethodList` (the new dropdown) and `CardForm` (when card methods are in the whitelist). They are stacked: dropdown first, then card form. The customer picks one.

### 7. JS component: `UnifiedPaymentMethodList`

Replaces the three current list components. Renders one `<select name="chip_payment_method">` populated from the REST endpoint.

```javascript
// Pseudocode for the new component.
function UnifiedPaymentMethodList( { gatewayId, nonce, logoBaseUrl, cardLogosUrl } ) {
    const [ options, setOptions ] = useState( [] );
    useEffect( () => {
        fetch( `/wp-json/chip/v1/banks/unified/${gatewayId}`, {
            headers: { 'X-WP-Nonce': nonce },
        } )
        .then( ( r ) => r.json() )
        .then( ( data ) => {
            // data is { 'fpx:MB2U0227': 'Maybank (B2C)', ..., 'card': 'Card (Visa/Mastercard/Maestro)', 'dnqr': 'DuitNow QR' }
            setOptions( Object.entries( data ).map( ( [ value, label ] ) => ( { value, label } ) ) );
        } );
    }, [ gatewayId ] );

    return (
        <select name="chip_payment_method" className="chip-unified-payment-method">
            <option value="">{ __( 'Choose a payment method', 'chip-for-woocommerce' ) }</option>
            { options.map( ( { value, label } ) => (
                <option key={ value } value={ value }>{ label }</option>
            ) ) }
        </select>
    );
}
```

The `onPaymentSetup` handler in the existing `ContentContainer` is updated to forward the selected value as `chip_payment_method` in the payment data.

### 8. `payment_fields()` classic checkout

When the unified dropdown is in scope (whitelist contains dropdown-eligible methods):

```php
public function payment_fields() {
    // ... existing tokenization check ...
    if ( $this->has_unified_dropdown() ) {
        echo '<select name="chip_payment_method" class="chip-unified-payment-method">';
        echo '<option value="">' . esc_html__( 'Choose a payment method', 'chip-for-woocommerce' ) . '</option>';
        foreach ( $this->list_unified_payment_methods() as $value => $label ) {
            printf(
                '<option value="%s">%s</option>',
                esc_attr( $value ),
                esc_html( $label )
            );
        }
        echo '</select>';
    }
    // ... card form (existing) when card methods are in the whitelist ...
}

private function has_unified_dropdown(): bool {
    $dropdown_methods = array( 'fpx', 'fpx_b2b1', 'razer_atome', 'razer_grabpay', 'razer_maybankqr', 'razer_shopeepay', 'razer_tng' );
    return count( array_intersect( $this->payment_method_whitelist, $dropdown_methods ) ) > 0;
}
```

The existing Select2/selectWoo initialization JS (L1421-1482) and CSS (L1483-1551) are updated to target the new `<select>` class.

### 9. `validate_fields()` classic checkout

```php
public function validate_fields() {
    if ( $this->has_unified_dropdown() && empty( $_POST['chip_payment_method'] ) ) {
        throw new \Exception( __( 'Please choose a payment method.', 'chip-for-woocommerce' ) );
    }
    // Card form validation is handled client-side by direct-post.js (existing).
}
```

### 10. `assets/duitnow_qr.png`

NEW asset, 50x50 small icon. Used in the unified dropdown's DuitNow QR option. Existing FPX and Razer e-wallet logos are reused.

## Data flow

### Customer picks FPX B2C bank from the unified dropdown

1. Customer visits checkout with a gateway configured for `[fpx, card]`.
2. The Blocks `ContentContainer` renders: `UnifiedPaymentMethodList` (the new dropdown) AND `CardForm` (stacked below).
3. Customer selects "Maybank (B2C)" from the dropdown. The form's hidden field is set to `fpx:MB2U0227`.
4. Customer clicks "Place order". The `onPaymentSetup` handler submits the form data including `chip_payment_method=fpx:MB2U0227`.
5. `process_payment()` is called. It calls `bypass_chip()` which reads `$_POST['chip_payment_method']`, splits on `:`, sees `type='fpx'`, builds `?preferred=fpx&fpx_bank_code=MB2U0227`.
6. Customer is redirected to CHIP's hosted page with the FPX redirect URL.

### Customer picks "Card" from the unified dropdown

1. Customer visits checkout with a gateway configured for `[fpx, card]`.
2. The Blocks `ContentContainer` renders: `UnifiedPaymentMethodList` AND `CardForm`.
3. Customer selects "Card (Visa/Mastercard/Maestro)" from the dropdown. The form's hidden field is set to `card`.
4. Customer enters card details in `CardForm` (card name, number, expiry, CVC).
5. Customer clicks "Place order". The `onPaymentSetup` handler submits:
   - `chip_payment_method=card`
   - Card form fields (`chip-cardholder-name`, `chip-card-number`, etc.)
6. `process_payment()` is called. It calls `bypass_chip()` which reads `chip_payment_method=card` and returns the URL unchanged (no `?preferred=` redirect).
7. `process_payment()` then uses `direct_post_url` (existing card flow). The JS's `onCheckoutSuccess` reads `paymentDetails.chip_direct_post_url` and POSTs the card data directly to CHIP.

### Customer picks "DuitNow QR" from the unified dropdown

1. Customer visits checkout with a gateway configured for `[duitnow_qr]`.
2. The `UnifiedPaymentMethodList` shows one option: "DuitNow QR" (value `dnqr`).
3. Customer selects it. The form's hidden field is set to `dnqr`.
4. `bypass_chip()` reads `chip_payment_method=dnqr`. It calls `build_dnqr_url()` which uses `get_duitnow_qr_preferred()` (existing logic — returns `dnqr` if merchant has it, else `duitnow_qr`).
5. Customer is redirected to `?preferred=dnqr` (or `?preferred=duitnow_qr`).

## Error handling

| Failure | Behavior |
|---|---|
| `chip_payment_method` POST missing or empty | `bypass_chip()` returns the URL unchanged. The customer sees whatever the gateway default behavior is. |
| Unknown tag type (e.g. `bogus:xyz`) | `bypass_chip()` returns the URL unchanged. |
| Known tag type with invalid item code (e.g. `fpx:NOTAREALBANK`) | The URL is built and the redirect goes to CHIP. CHIP rejects the bank code with an error. |
| REST endpoint failure (`unified`) | The frontend shows no options. The customer can't place an order. The existing error-handling pattern in the current `FpxBankList` etc. applies. |
| Constructor's backward-compat migration finds old `visa`/`mastercard`/`maestro` keys | It collapses them to `'card'` in-memory. The next save of the gateway settings persists the new shape. |
| Atome clone (`wc_gateway_chip_5`) — `bypass_chip()` short-circuits to `?preferred=razer_atome&razer_bank_code=Atome` regardless of the new POST field. (Atome has no JS UI; the customer clicks "Place order" and the PHP auto-redirects.) |

## File map

### Added

| File | Purpose |
|---|---|
| `assets/duitnow_qr.png` | DuitNow QR small icon (50x50) for the unified dropdown |
| `tests/UnifiedPaymentMethodListTest.php` | Tests for `list_unified_payment_methods()` (8-10 tests) |
| `tests/BypassChipTagParserTest.php` | Tests for the tag parser in `bypass_chip()` (10-12 tests) |

### Modified

| File | Change |
|---|---|
| `includes/class-chip-woocommerce-gateway.php` | Add `CARD_GROUP` constant, extend constructor's group expansion, add `list_unified_payment_methods()` method, rewrite `bypass_chip()` with tag parser, update `payment_fields()` and `validate_fields()` |
| `includes/blocks/class-chip-woocommerce-gateway-blocks-support.php` | Add `'unified'` to bank_type and js_display decision |
| `includes/class-chip-woocommerce.php` | Add `'unified'` to REST endpoint accepted types |
| `resources/js/frontend/blocks_chip_woocommerce_gateway.js` | Replace three list components with one `UnifiedPaymentMethodList` |
| `resources/js/frontend/blocks_chip_woocommerce_gateway_2.js` | Same refactor |
| `resources/js/frontend/blocks_chip_woocommerce_gateway_3.js` | Same refactor |
| `resources/js/frontend/blocks_chip_woocommerce_gateway_4.js` | Same refactor |
| `resources/js/frontend/blocks_chip_woocommerce_gateway_6.js` | Same refactor |
| `webpack.config.js` | Update entry points to share the new component |
| `tests/GetPaymentMethodListTest.php` | Update expected list to 10 entries (was 13) |
| `tests/ConstructorGroupExpansionTest.php` | Add Card group expansion tests |

### Files NOT changed

- The 6 clone gateway PHP classes
- The CI workflow
- `composer.json`, `package.json`
- `phpcs.xml`
- The Atome clone (`wc_gateway_chip_5`)
- The dnqr resolver and `get_duitnow_qr_preferred()`

## Risks

1. **JS refactor is the biggest change.** 5 clone files need to be refactored. The cleanest approach is a shared component imported by each clone's entry point. The webpack config needs to support shared code.
2. **Classic checkout + Blocks checkout must stay in sync.** Both need the same dropdown behavior, same tag values, same logo rendering.
3. **bypass_chip() tag parser must handle all method types correctly** including the Atome separate-gateway fallback. The spec lists every expected input → output mapping.
4. **Card coexistence UX** — the dropdown sits above the card form. The customer picks one. Form validation only requires one (not both).
5. **Backward compat for the old 3-POST-field design.** The old `chip_fpx_bank` / `chip_fpx_b2b1_bank` / `chip_razer_ewallet` POST fields are no longer used by the new code. Any external consumer (e.g. a custom checkout) using them would break. Since these are plugin-internal, this is acceptable.
6. **Saved card tokenization** -- the Blocks UI shows saved cards (existing WC tokenization flow at L1334-1340) alongside the unified dropdown. Saved cards render as radio-button choices ABOVE the unified dropdown. The customer picks either a saved card (skips the dropdown entirely, reuses the saved card) or a new method from the dropdown below. The existing tokenization flow is unchanged; we don't need to alter the Blocks `CardForm` component or the WC tokenization hooks.
7. **Classic checkout's Select2/selectWoo integration** has ~60 lines of JS and ~70 lines of CSS. The new unified dropdown needs the same treatment.

## Testing strategy

The PHPUnit test suite is the test artifact. New tests:
- `tests/UnifiedPaymentMethodListTest.php` — 8-10 tests for `list_unified_payment_methods()` covering: empty whitelist, FPX-only, Razer-only, mixed, card-only, card+FPX, all-groups, the DuitNow QR (no inner code) and Card (verbose label) entries.
- `tests/BypassChipTagParserTest.php` — 10-12 tests for the tag parser covering: each method type's URL shape, the `card` no-redirect case, the `dnqr` resolver-driven URL, the unknown-tag fallback, the Atome separate-gateway path, the empty-POST fallback, the Bypass-chip-disabled case.
- Updated `tests/GetPaymentMethodListTest.php` — assert 10 entries, `card` is present, `visa`/`mastercard`/`maestro` are absent.
- Updated `tests/ConstructorGroupExpansionTest.php` — add `card`-group expansion tests, plus a backward-compat test (legacy `['visa', 'mastercard', 'maestro']` collapses to `['card']`).

Total test count after this PR: ~58 tests (was 36), all passing.

## Migration path for existing merchants

- Merchants who previously saved `payment_method_whitelist = ['fpx']` keep working unchanged.
- Merchants who saved `payment_method_whitelist = ['visa', 'mastercard', 'maestro']` (the old card-in-multiselect form) — the constructor's backward-compat migration collapses these to `'card'` in-memory. Behavior is identical to the new design. The next save of the gateway settings persists the new shape.
- Merchants who saved `payment_method_whitelist = ['duitnow_qr']` keep working unchanged (existing dnqr migration logic).
- The `chip_fpx_bank` / `chip_fpx_b2b1_bank` / `chip_razer_ewallet` POST fields become dead code. They are no longer read by `bypass_chip()`. A follow-up cleanup PR can remove them entirely.

## Out of scope (deferred)

- **JS modernization** — the new component uses the same `wp.element`/`useEffect` patterns as the existing code. A future PR can do a full React hooks refactor.
- **Card brand logos in the dropdown** — the card form detects the brand from the card number prefix (existing logic).
- **Removing the old POST fields from `bypass_chip()`** — they're dead code but cleanup is a follow-up.
