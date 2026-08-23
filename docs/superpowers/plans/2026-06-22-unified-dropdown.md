# Unified Dropdown Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the three POST fields (`chip_fpx_bank`, `chip_fpx_b2b1_bank`, `chip_razer_ewallet`) with one tag-encoded `chip_payment_method` field. Render all eligible payment methods (FPX banks, Razer e-wallets, DuitNow QR, Card) in a single unified dropdown.

**Architecture:** Backend changes first (CARD_GROUP constant, group expansion, list method, bypass_chip tag parser, REST endpoint extension, Blocks support class, classic checkout). Test changes next. JS refactor last (shared UnifiedPaymentMethodList component + 5 clone file refactors + webpack config). The 36 existing tests are preserved and extended; new tests cover the new methods.

**Tech Stack:** PHP 7.4+ (existing), WordPress/WooCommerce, PHPUnit 9.6 (existing dev dep), `wp.element`/`useState`/`useEffect` (existing JS patterns), Webpack.

**Spec:** `docs/superpowers/specs/2026-06-22-unified-dropdown-redesign-design.md`

## Global Constraints

- WordPress Coding Standards via `phpcs.xml` (tabs, 120-char line limit, `chip_` prefix)
- The plugin's `Requires PHP: 7.4` must remain satisfied — PHP code uses only PHP 7.4-compatible syntax
- The new test code does NOT modify the bootstrap's existing stubs
- All test method names follow the existing `test_*` convention
- All 36 existing tests must continue to pass
- The dnqr migration code (resolver, `get_duitnow_qr_preferred()`, group expansion) is **unchanged in spirit** — only the constructor's expansion block grows
- Text domain is `chip-for-woocommerce` for any new translatable strings
- JS code uses the same `wp.element`/`useState`/`useEffect` patterns as the existing components
- No new dev dependencies in `composer.json` or `package.json`

---

## File Map

| File | Role |
|---|---|
| `includes/class-chip-woocommerce-gateway.php` | Add `CARD_GROUP` constant, extend constructor's group expansion (with backward-compat), add `list_unified_payment_methods()` method, rewrite `bypass_chip()` with tag parser, update `payment_fields()` and `validate_fields()`. Update `get_payment_method_list()` to 10 entries. |
| `includes/blocks/class-chip-woocommerce-gateway-blocks-support.php` | Add `'unified'` to bank_type/js_display decision. |
| `includes/class-chip-woocommerce.php` | Add `'unified'` case to the REST endpoint handler. |
| `assets/duitnow_qr.png` | NEW asset: 50x50 small icon for the DuitNow QR option. |
| `resources/js/frontend/blocks_chip_woocommerce_gateway{1,2,3,4,6}.js` | Replace the three list components with one `UnifiedPaymentMethodList`. |
| `webpack.config.js` | Update entry points to share the new component. |
| `tests/GetPaymentMethodListTest.php` | Update expected list to 10 entries. |
| `tests/ConstructorGroupExpansionTest.php` | Add Card group expansion tests + backward-compat. |
| `tests/UnifiedPaymentMethodListTest.php` | NEW: 8-10 tests for `list_unified_payment_methods()`. |
| `tests/BypassChipTagParserTest.php` | NEW: 10-12 tests for the tag parser. |

---

## Task Dependency Map

```
Task 1: Backend foundation         (no deps)
Task 2: bypass_chip tag parser     (deps: Task 1)
Task 3: REST endpoint unified     (deps: Task 1)
Task 4: Blocks support unified     (deps: Task 1)
Task 5: Classic checkout           (deps: Task 1)
Task 6: Update existing tests      (deps: Task 1)
Task 7: UnifiedPaymentMethodListTest (deps: Task 1)
Task 8: BypassChipTagParserTest   (deps: Task 2)
Task 9: JS shared component        (deps: Task 3)
Task 10: JS refactor 5 clones      (deps: Task 9)
Task 11: JS webpack config         (deps: Task 10)
Task 12: Final integration pass    (deps: all)
```

Tasks 2-7 are independent of each other after Task 1 lands and can run in any order. Tasks 9-11 are sequential.

---

## Task 1: Backend foundation — CARD_GROUP, group expansion, list method, get_payment_method_list, asset

**Files:**
- Modify: `includes/class-chip-woocommerce-gateway.php` — add `CARD_GROUP` constant, extend constructor's group expansion (with backward-compat), add `list_unified_payment_methods()` method, update `get_payment_method_list()`.
- Create: `assets/duitnow_qr.png` — 50x50 DuitNow QR icon. (For this task, the implementer can use any small PNG; the visual asset can be polished in a follow-up. The functional test only checks the asset is a valid file.)

**Interfaces:**
- Consumes: existing `DUITNOW_GROUP` constant at L28 of the gateway file.
- Produces:
  - `Chip_Woocommerce_Gateway::CARD_GROUP` — new constant `['visa', 'mastercard', 'maestro']`.
  - `Chip_Woocommerce_Gateway::list_unified_payment_methods(): array` — new method returning the unified list.
  - Updated `get_payment_method_list()` returning 10 entries (no more `visa`/`mastercard`/`maestro`; new `card` key).

- [ ] **Step 1: Update `get_payment_method_list()` to the new 10-entry shape**

In `includes/class-chip-woocommerce-gateway.php`, find the `get_payment_method_list()` method (around L3533-3551). Replace its return value with:

```php
public function get_payment_method_list() {
    return array(
        'fpx'             => 'FPX',
        'fpx_b2b1'        => 'FPX B2B1',
        'card'            => 'Card',
        'razer_atome'     => 'Atome',
        'razer_grabpay'   => 'GrabPay',
        'razer_maybankqr' => 'Maybank QRPay',
        'razer_shopeepay' => 'ShopeePay',
        'razer_tng'       => "Touch 'n Go eWallet",
        'duitnow_qr'      => 'DuitNow QR',
    );
}
```

The 3 removed lines (`'visa' => 'Visa', 'mastercard' => 'Mastercard', 'maestro' => 'Maestro'`) are replaced with the single `'card' => 'Card'`.

- [ ] **Step 2: Add the `CARD_GROUP` constant**

Find the `DUITNOW_GROUP` constant (at L28 of the gateway file). Immediately after it, add:

```php
	/**
	 * Card group: payment-method identifiers that are interchangeable
	 * for the merchant at runtime. Card is the user-selectable multiselect
	 * key; visa/mastercard/maestro are injected at load time by the
	 * constructor's group expansion.
	 *
	 * @var array
	 */
	const CARD_GROUP = array( 'visa', 'mastercard', 'maestro' );
```

(Two tabs of indentation since it's inside the class.)

- [ ] **Step 3: Extend the constructor's group expansion**

Find the constructor's group expansion block (around L287-301 — the lines that read `payment_method_whitelist`, expand `duitnow_qr` to `DUITNOW_GROUP`, etc.). Replace the entire block with the version below (the new block has the additional Card-group expansion AND the backward-compat migration for legacy `visa`/`mastercard`/`maestro` keys):

```php
		$whitelist = $this->get_option( 'payment_method_whitelist', array() );
		if ( ! is_array( $whitelist ) ) {
			$whitelist = array();
		}

		// Backward-compat migration: legacy saved values contained
		// 'visa', 'mastercard', 'maestro' as separate multiselect keys.
		// Collapse them to the single 'card' key in-memory. The next save
		// of the gateway settings persists the new shape.
		if ( count( array_intersect( $whitelist, self::CARD_GROUP ) ) > 0 ) {
			$whitelist = array_values( array_diff( $whitelist, self::CARD_GROUP ) );
			if ( ! in_array( 'card', $whitelist, true ) ) {
				$whitelist[] = 'card';
			}
		}

		// DuitNow QR group expansion: when the merchant selects 'duitnow_qr'
		// in the multiselect, that selection means "the DuitNow QR group" --
		// i.e. the plugin should pick whichever of {duitnow_qr, dnqr} the
		// merchant actually has at runtime, prioritizing dnqr. Expand the
		// single multiselect key into the full group at load time so the
		// resolver and bypass_chip see the group semantics. The expansion
		// is in-memory only and does not mutate the saved option.
		if ( in_array( 'duitnow_qr', $whitelist, true ) ) {
			$whitelist = array_values(
				array_unique( array_merge( $whitelist, self::DUITNOW_GROUP ) )
			);
		}

		// Card group expansion: when the merchant selects 'card' in the
		// multiselect, that selection means "the Card group" -- i.e. the
		// plugin should accept visa, mastercard, and maestro. Expand the
		// single multiselect key into the full group at load time.
		if ( in_array( 'card', $whitelist, true ) ) {
			$whitelist = array_values(
				array_unique( array_merge( $whitelist, self::CARD_GROUP ) )
			);
		}

		$this->payment_method_whitelist = $whitelist;
```

- [ ] **Step 4: Add the `list_unified_payment_methods()` method**

Find the existing `list_razer_ewallets()` method (around L2950-2985). Add the new method immediately after it:

```php
	/**
	 * Get the unified list of payment methods for the unified dropdown.
	 *
	 * Returns a flat array keyed by tag-encoded values (e.g. 'fpx:MB2U0227',
	 * 'fpx_b2b1:PBB0234', 'razer:GrabPay', 'dnqr', 'card'). Each entry's value
	 * is the customer-facing display label. Used by the REST endpoint type
	 * 'unified' and by classic checkout's payment_fields().
	 *
	 * @return array
	 */
	public function list_unified_payment_methods(): array {
		$list = array();

		// FPX B2C banks.
		foreach ( $this->list_fpx_banks() as $code => $label ) {
			if ( '' === $code ) {
				continue;
			}
			$list[ 'fpx:' . $code ] = $label;
		}

		// FPX B2B1 banks.
		foreach ( $this->list_fpx_b2b1_banks() as $code => $label ) {
			if ( '' === $code ) {
				continue;
			}
			$list[ 'fpx_b2b1:' . $code ] = $label;
		}

		// Razer e-wallets (excluding the DuitNow QR entry -- it has its own
		// tag format 'dnqr' with no inner code).
		foreach ( $this->list_razer_ewallets() as $code => $label ) {
			if ( '' === $code || 'duitnow-qr' === $code || __( 'Choose your e-wallet', 'chip-for-woocommerce' ) === $label ) {
				continue;
			}
			$list[ 'razer:' . $code ] = $label;
		}

		// DuitNow QR (only if the dnqr group is enabled in the whitelist).
		if ( count( array_intersect( $this->payment_method_whitelist, self::DUITNOW_GROUP ) ) > 0 ) {
			$list['dnqr'] = __( 'DuitNow QR', 'chip-for-woocommerce' );
		}

		// Card (only if the card group is enabled in the whitelist).
		if ( count( array_intersect( $this->payment_method_whitelist, self::CARD_GROUP ) ) > 0 ) {
			$list['card'] = __( 'Card (Visa/Mastercard/Maestro)', 'chip-for-woocommerce' );
		}

		return $list;
	}
```

- [ ] **Step 5: Create the `assets/duitnow_qr.png` placeholder**

Create `assets/duitnow_qr.png`. The visual asset will be polished by a designer later; for the test to pass, any valid 50x50 PNG works. Use this command to create a placeholder:

```bash
php -r '$im = imagecreatetruecolor(50, 50); imagefill($im, 0, 0, imagecolorallocate($im, 230, 0, 126)); imagepng($im, "assets/duitnow_qr.png"); imagedestroy($im);'
```

(The placeholder is a solid magenta square — the test only checks the file exists, not its visual content.)

- [ ] **Step 6: Verify the gateway file parses**

Run: `php -l includes/class-chip-woocommerce-gateway.php`
Expected: `No syntax errors detected in includes/class-chip-woocommerce-gateway.php`.

- [ ] **Step 7: Run phpcs**

Run: `./vendor/bin/phpcs --standard=phpcs.xml includes/class-chip-woocommerce-gateway.php`
Expected: exit 0. (If errors appear — likely on the long `sprintf()` call in the existing `dnqr` resolver log line, fix to break across multiple lines.)

- [ ] **Step 8: Verify existing tests still pass (will fail at this stage)**

Run: `vendor/bin/phpunit`
Expected: **22 of 36 tests pass.** The 14 failures are in `GetPaymentMethodListTest` (expected list size mismatch) and `ConstructorGroupExpansionTest` (the existing tests don't know about the Card group yet). This is expected — those tests are updated in Task 6.

Note: do NOT fix these tests now. They will be updated in Task 6 as part of the test file refactor.

- [ ] **Step 9: Commit**

```bash
git add includes/class-chip-woocommerce-gateway.php assets/duitnow_qr.png
git commit -m "feat(unified-dropdown): add CARD_GROUP, group expansion, list method

Foundation for the unified dropdown redesign. Adds:
- CARD_GROUP constant ['visa', 'mastercard', 'maestro']
- Constructor's group expansion extended: 'card' -> CARD_GROUP,
  with backward-compat migration that collapses legacy
  ['visa', 'mastercard', 'maestro'] keys to ['card'] in-memory
- list_unified_payment_methods() returns the merged list with
  tag-encoded keys (fpx:CODE, fpx_b2b1:CODE, razer:CODE, dnqr, card)
- get_payment_method_list() updated to 10 entries (was 13);
  'card' replaces the three separate visa/mastercard/maestro
  entries
- assets/duitnow_qr.png placeholder for the DuitNow QR option icon

Existing test failures in GetPaymentMethodListTest and
ConstructorGroupExpansionTest are expected and will be fixed
in Task 6 (test file update)."
```

---

## Task 2: `bypass_chip()` tag parser

**Files:**
- Modify: `includes/class-chip-woocommerce-gateway.php` — replace the 3-POST-field if/elseif chain with the tag parser.

**Interfaces:**
- Consumes: the resolver (existing `get_duitnow_qr_preferred()` method at L3659-3672) which returns `'dnqr'` or `'duitnow_qr'`.
- Produces: a new `bypass_chip()` that reads `$_POST['chip_payment_method']`, parses the tag, and dispatches to the right URL shape.

- [ ] **Step 1: Replace `bypass_chip()` with the tag parser**

Find `bypass_chip()` (around L2994-3053). Replace the entire method body with the new version below:

```php
	public function bypass_chip( $url, $payment ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( 'yes' !== $this->bypass_chip || $payment['is_test'] ) {
			return $this->maybe_atome_redirect( $url );
		}
		if ( ! isset( $_POST['chip_payment_method'] ) || empty( $_POST['chip_payment_method'] ) ) {
			return $url;
		}
		$value = sanitize_text_field( wp_unslash( $_POST['chip_payment_method'] ) );
		if ( false === strpos( $value, ':' ) ) {
			if ( 'dnqr' === $value ) {
				return $this->build_dnqr_url( $url );
			}
			// 'card' or any other unrecognised single-method tag: no redirect;
			// the direct-post flow or default gateway behavior applies.
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
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Build the redirect URL for a Razer e-wallet selection.
	 *
	 * @param string $url          Base redirect URL.
	 * @param string $display_name Display name of the selected e-wallet (e.g. 'GrabPay').
	 * @return string URL with the appropriate ?preferred=razer_<x>&razer_bank_code=... suffix.
	 */
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

	/**
	 * Build the redirect URL for a DuitNow QR selection.
	 *
	 * @param string $url Base redirect URL.
	 * @return string URL with the appropriate ?preferred=dnqr (or duitnow_qr) suffix.
	 */
	private function build_dnqr_url( $url ) {
		$preferred = $this->get_duitnow_qr_preferred();
		return '' === $preferred ? $url : $url . '?preferred=' . $preferred;
	}

	/**
	 * If this is the Atome clone (wc_gateway_chip_5), force a redirect to
	 * ?preferred=razer_atome&razer_bank_code=Atome regardless of any POST data.
	 *
	 * @param string $url Base redirect URL.
	 * @return string URL with the Atome redirect suffix, or the input URL unchanged.
	 */
	private function maybe_atome_redirect( $url ) {
		if ( 'wc_gateway_chip_5' === $this->id ) {
			return $url . '?preferred=razer_atome&razer_bank_code=Atome';
		}
		return $url;
	}
```

- [ ] **Step 2: Verify the gateway file parses**

Run: `php -l includes/class-chip-woocommerce-gateway.php`
Expected: `No syntax errors detected in includes/class-chip-woocommerce-gateway.php`.

- [ ] **Step 3: Run phpcs**

Run: `./vendor/bin/phpcs --standard=phpcs.xml includes/class-chip-woocommerce-gateway.php`
Expected: exit 0.

- [ ] **Step 4: Run the test suite (still 22 of 36 passing is expected; the new tag-parser tests are in Task 8)**

Run: `vendor/bin/phpunit`
Expected: 22 pass, 14 fail (same as Task 1; the failures are in `GetPaymentMethodListTest` and `ConstructorGroupExpansionTest`, not in the tag parser).

- [ ] **Step 5: Commit**

```bash
git add includes/class-chip-woocommerce-gateway.php
git commit -m "feat(unified-dropdown): rewrite bypass_chip as a tag parser

bypass_chip() now reads the single chip_payment_method POST field,
parses the colon-separated tag, and dispatches to the right URL
shape:

- fpx:<code>        -> ?preferred=fpx&fpx_bank_code=<code>
- fpx_b2b1:<code>   -> ?preferred=fpx_b2b1&fpx_bank_code=<code>
- razer:<display>   -> ?preferred=razer_<x>&razer_bank_code=<display>
                       (e.g. razer:GrabPay -> razer_grabpay&razer_bank_code=GrabPay)
- dnqr              -> ?preferred=dnqr (or duitnow_qr fallback)
                       via the existing get_duitnow_qr_preferred() resolver
- card              -> no redirect; the direct-post flow applies

The old chip_fpx_bank / chip_fpx_b2b1_bank / chip_razer_ewallet
POST fields are no longer read by bypass_chip. The new
maybe_atome_redirect() helper preserves the wc_gateway_chip_5
clone's auto-redirect to Atome."
```

---

## Task 3: REST endpoint — `unified` type

**Files:**
- Modify: `includes/class-chip-woocommerce.php` — add a new branch in the REST handler for `type='unified'`.

**Interfaces:**
- Consumes: the new `list_unified_payment_methods()` method from Task 1.
- Produces: a new REST endpoint branch that returns the unified list as JSON.

- [ ] **Step 1: Locate the REST handler**

Open `includes/class-chip-woocommerce.php` and find `get_banks_endpoint()` (around L191-221). The current switch statement dispatches `fpx_b2c`, `fpx_b2b1`, `razer`. We add `unified`.

- [ ] **Step 2: Add the `unified` case to the switch**

In `get_banks_endpoint()`, find the switch block (the one that starts with `switch ( $type )` near L205). Add a new case before the default:

```php
				case 'unified':
					$banks = $gateway->list_unified_payment_methods();
					break;
```

The new case calls the new `list_unified_payment_methods()` method. Result of the full method (after the edit, with the existing cases shown for context):

```php
	public function get_banks_endpoint( WP_REST_Request $request ) {
		$type       = $request->get_param( 'type' );
		$gateway_id = $request->get_param( 'gateway_id' );
		$gateway    = $this->get_gateway_instance( $gateway_id );
		if ( ! $gateway ) {
			return new WP_REST_Response( array( 'error' => 'Invalid gateway' ), 400 );
		}
		switch ( $type ) {
			case 'fpx_b2c':
				$banks = $gateway->list_fpx_banks();
				break;
			case 'fpx_b2b1':
				$banks = $gateway->list_fpx_b2b1_banks();
				break;
			case 'razer':
				$banks = $gateway->list_razer_ewallets();
				break;
			case 'unified':
				$banks = $gateway->list_unified_payment_methods();
				break;
			default:
				return new WP_REST_Response( array( 'error' => 'Invalid type' ), 400 );
		}
		unset( $banks[''] );
		return new WP_REST_Response( $banks, 200 );
	}
```

- [ ] **Step 3: Verify the file parses**

Run: `php -l includes/class-chip-woocommerce.php`
Expected: `No syntax errors detected in includes/class-chip-woocommerce.php`.

- [ ] **Step 4: Run phpcs**

Run: `./vendor/bin/phpcs --standard=phpcs.xml includes/class-chip-woocommerce.php`
Expected: exit 0.

- [ ] **Step 5: Run the test suite (still 22 of 36 passing is expected)**

Run: `vendor/bin/phpunit`
Expected: same 22 pass / 14 fail as before.

- [ ] **Step 6: Commit**

```bash
git add includes/class-chip-woocommerce.php
git commit -m "feat(unified-dropdown): add 'unified' type to REST endpoint

The chip/v1/banks/{type}/{gateway_id} endpoint now accepts
'unified' as a type. It calls the new list_unified_payment_methods()
method which returns the merged list of FPX banks + Razer
e-wallets + DuitNow QR + Card with tag-encoded keys (e.g.
'fpx:MB2U0227', 'dnqr', 'card'). The existing fpx_b2c / fpx_b2b1
/ razer types are unchanged for backward compatibility."
```

---

## Task 4: Blocks support class — `unified` mode

**Files:**
- Modify: `includes/blocks/class-chip-woocommerce-gateway-blocks-support.php` — add `'unified'` to the bank_type and js_display decisions.

**Interfaces:**
- Consumes: the new `list_unified_payment_methods()` from Task 1.
- Produces: updated `bank_type` and `js_display` decisions so the Blocks React `ContentContainer` routes `js_display='unified'` to the new dropdown.

- [ ] **Step 1: Locate the bank_type and js_display decisions**

In `includes/blocks/class-chip-woocommerce-gateway-blocks-support.php`, find the two decision blocks:
- `bank_type` decision at L97-117 (inside `get_payment_method_script_handles()`).
- `js_display` decision at L150-169 (inside `get_payment_method_data()`).

- [ ] **Step 2: Update the `bank_type` decision to add the `unified` case**

The current `bank_type` logic decides which REST URL to expose to the JS. We need to add a new condition: if the gateway's whitelist contains at least one dropdown-eligible method AND at least one card method, OR contains 2+ dropdown-eligible methods, return `'unified'`.

Find the bank_type block (around L97-117) and add a new `elseif` branch. The full updated block:

```php
		$bank_type = '';
		if ( is_array( $whitelisted_payment_method ) && 'yes' === $bypass_chip ) {
			$has_fpx = in_array( 'fpx', $whitelisted_payment_method, true ) || in_array( 'fpx_b2b1', $whitelisted_payment_method, true );
			$has_razer = count( preg_grep( '/^razer_/', $whitelisted_payment_method ) ) > 0;
			$has_card = count( array_intersect( $whitelisted_payment_method, array( 'visa', 'mastercard', 'maestro' ) ) ) > 0;

			// Single-method cases: fpx, fpx_b2b1, razer, card (legacy 'fpx' / 'fpx_b2b1' / 'razer' / 'card' stand-alone flows).
			if ( 1 === count( $whitelisted_payment_method ) ) {
				if ( 'fpx' === $whitelisted_payment_method[0] ) {
					$bank_type = 'fpx_b2c';
				} elseif ( 'fpx_b2b1' === $whitelisted_payment_method[0] ) {
					$bank_type = 'fpx_b2b1';
				} elseif ( $has_razer && ! $has_card ) {
					$bank_type = 'razer';
				}
			}

			// Mixed cases: card + dropdown, or multiple dropdown methods -> unified.
			if ( '' === $bank_type ) {
				$has_dropdown = $has_fpx || $has_razer;
				$dropdown_count = count( preg_grep( '/^razer_/', $whitelisted_payment_method ) );
				if ( $has_fpx ) {
					$dropdown_count++;
				}
				if ( ( $has_dropdown && $has_card ) || $dropdown_count > 1 ) {
					$bank_type = 'unified';
				} elseif ( 0 === count( array_diff( $whitelisted_payment_method, $razer_ewallet_list ) ) ) {
					$bank_type = 'razer';
				}
			}
		}
```

- [ ] **Step 3: Update the `js_display` decision to add the `unified` case**

Find the js_display block (around L150-169). Add a new branch: when bank_type is `'unified'`, return `'unified'`. The full updated block:

```php
		$pm_whitelist = $this->get_setting( 'payment_method_whitelist' );
		$bypass_chip  = $this->get_setting( 'bypass_chip' );
		$js_display   = '';

		// Card methods that support direct post.
		$card_methods       = array( 'visa', 'mastercard', 'maestro' );
		$razer_ewallet_list = array( 'razer_grabpay', 'razer_maybankqr', 'razer_shopeepay', 'razer_tng' );

		if ( is_array( $pm_whitelist ) && 'yes' === $bypass_chip ) {
			$has_fpx   = in_array( 'fpx', $pm_whitelist, true ) || in_array( 'fpx_b2b1', $pm_whitelist, true );
			$has_razer = count( preg_grep( '/^razer_/', $pm_whitelist ) ) > 0;
			$has_card  = count( array_intersect( $pm_whitelist, $card_methods ) ) > 0;

			// Single-method cases.
			if ( 1 === count( $pm_whitelist ) ) {
				if ( 'fpx' === $pm_whitelist[0] ) {
					$js_display = 'fpx';
				} elseif ( 'fpx_b2b1' === $pm_whitelist[0] ) {
					$js_display = 'fpx_b2b1';
				} elseif ( $has_razer && ! $has_card ) {
					$js_display = 'razer';
				} elseif ( $has_card && ! $has_fpx && ! $has_razer ) {
					$js_display = 'card';
				}
			}

			// Mixed cases.
			if ( '' === $js_display ) {
				$dropdown_count = count( preg_grep( '/^razer_/', $pm_whitelist ) );
				if ( $has_fpx ) {
					$dropdown_count++;
				}
				$has_dnqr = in_array( 'duitnow_qr', $pm_whitelist, true ) || in_array( 'dnqr', $pm_whitelist, true );
				if ( $has_dnqr ) {
					$dropdown_count++;
				}
				$has_dropdown = $dropdown_count > 0;
				if ( ( $has_dropdown && $has_card ) || $dropdown_count > 1 ) {
					$js_display = 'unified';
				} elseif ( 0 === count( array_diff( $pm_whitelist, $razer_ewallet_list ) ) ) {
					$js_display = 'razer';
				} elseif ( $has_card && ! $has_dropdown ) {
					$js_display = 'card';
				}
			}
		}
```

- [ ] **Step 4: Verify the file parses**

Run: `php -l includes/blocks/class-chip-woocommerce-gateway-blocks-support.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Run phpcs**

Run: `./vendor/bin/phpcs --standard=phpcs.xml includes/blocks/class-chip-woocommerce-gateway-blocks-support.php`
Expected: exit 0.

- [ ] **Step 6: Commit**

```bash
git add includes/blocks/class-chip-woocommerce-gateway-blocks-support.php
git commit -m "feat(unified-dropdown): add 'unified' mode to Blocks support class

The bank_type and js_display decisions now recognize mixed
whitelists (card + dropdown, or 2+ dropdown methods) and return
'unified'. The React ContentContainer will route js_display='unified'
to render the UnifiedPaymentMethodList alongside CardForm.

Existing single-method decisions (fpx, fpx_b2b1, razer, card) are
preserved. The card-only flow (visa/mastercard/maestro only) still
returns js_display='card'."
```

---

## Task 5: Classic checkout — `payment_fields()` and `validate_fields()`

**Files:**
- Modify: `includes/class-chip-woocommerce-gateway.php` — update `payment_fields()` to render the unified dropdown, update `validate_fields()` to check the new field.

**Interfaces:**
- Consumes: the new `list_unified_payment_methods()` method from Task 1.
- Produces: classic checkout renders the unified dropdown when dropdown methods are in the whitelist.

- [ ] **Step 1: Locate `payment_fields()` and `validate_fields()`**

In `includes/class-chip-woocommerce-gateway.php`, find:
- `payment_fields()` around L1325-1567.
- `validate_fields()` around L1606-1640.

- [ ] **Step 2: Add the `has_unified_dropdown()` helper method**

Just before `payment_fields()` (or at the end of the helper methods block), add:

```php
	/**
	 * Whether the gateway should render the unified dropdown in classic checkout.
	 *
	 * True when at least one dropdown-eligible method (FPX, Razer, or DuitNow QR)
	 * is in the whitelist and bypass_chip is enabled.
	 *
	 * @return bool
	 */
	private function has_unified_dropdown(): bool {
		if ( 'yes' !== $this->bypass_chip ) {
			return false;
		}
		$dropdown_methods = array( 'fpx', 'fpx_b2b1', 'razer_atome', 'razer_grabpay', 'razer_maybankqr', 'razer_shopeepay', 'razer_tng', 'duitnow_qr' );
		return count( array_intersect( $this->payment_method_whitelist, $dropdown_methods ) ) > 0;
	}
```

- [ ] **Step 3: Update `payment_fields()` to render the unified dropdown**

In `payment_fields()`, find the section that renders the FPX/Razer selects (L1360-1396). Replace those three `woocommerce_form_field()` calls with a single unified dropdown, kept within the same logic structure (only render if `has_unified_dropdown()` is true).

The simplest change: replace the body of the `if ( $this->bypass_chip == 'yes' )` block in `payment_fields()` so it renders one `<select name="chip_payment_method">` instead of three. Insert the unified dropdown block in place of the three `woocommerce_form_field` calls (around L1357-1397). For the existing structure (which has multiple if/else branches for FPX B2C, FPX B2B1, and Razer), the cleanest refactor is:

Find the block that starts with `if ( $this->bypass_chip == 'yes' )` (around L1356) and contains the three `woocommerce_form_field` calls. Replace the entire if-branch with:

```php
		if ( 'yes' === $this->bypass_chip ) {
			if ( $this->has_unified_dropdown() ) {
				$unified = $this->list_unified_payment_methods();
				$options = array( '' => __( 'Choose a payment method', 'chip-for-woocommerce' ) );
				foreach ( $unified as $value => $label ) {
					$options[ $value ] = $label;
				}
				woocommerce_form_field(
					'chip_payment_method',
					array(
						'type'     => 'select',
						'class'    => array( 'chip-unified-payment-method' ),
						'label'    => __( 'Payment Method', 'chip-for-woocommerce' ),
						'options'  => $options,
						'required' => true,
					)
				);
			}
		}
```

The legacy single-method flows (FPX B2C, FPX B2B1, pure Razer) still work because `has_unified_dropdown()` returns true for any whitelist containing dropdown methods. The new dropdown replaces the three legacy selects entirely.

- [ ] **Step 4: Update `validate_fields()`**

In `validate_fields()`, find the `$_POST['chip_fpx_bank']`, `$_POST['chip_fpx_b2b1_bank']`, and `$_POST['chip_razer_ewallet']` checks. Replace them with a single check on `$_POST['chip_payment_method']`. The full updated method (replace the entire body):

```php
	public function validate_fields() {
		if ( $this->has_unified_dropdown() && empty( $_POST['chip_payment_method'] ) ) {
			throw new \Exception( __( 'Please choose a payment method.', 'chip-for-woocommerce' ) );
		}
		// Card form validation is handled client-side by direct-post.js (existing).
	}
```

- [ ] **Step 5: Verify the file parses**

Run: `php -l includes/class-chip-woocommerce-gateway.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Run phpcs**

Run: `./vendor/bin/phpcs --standard=phpcs.xml includes/class-chip-woocommerce-gateway.php`
Expected: exit 0.

- [ ] **Step 7: Commit**

```bash
git add includes/class-chip-woocommerce-gateway.php
git commit -m "feat(unified-dropdown): render unified dropdown in classic checkout

payment_fields() now renders a single <select name='chip_payment_method'>
listing all eligible payment methods (FPX banks, Razer e-wallets,
DuitNow QR, Card) when has_unified_dropdown() is true. The three
legacy chip_fpx_bank / chip_fpx_b2b1_bank / chip_razer_ewallet
selects are replaced with the unified dropdown.

validate_fields() now requires chip_payment_method to be non-empty
when the unified dropdown is rendered. The card form's client-side
validation (direct-post.js) is unchanged.

The legacy single-method flows (FPX B2C, FPX B2b1, pure Razer) still
work because has_unified_dropdown() returns true for any whitelist
containing dropdown methods."
```

---

## Task 6: Update existing tests — `GetPaymentMethodListTest` and `ConstructorGroupExpansionTest`

**Files:**
- Modify: `tests/GetPaymentMethodListTest.php` — update expected list to 10 entries.
- Modify: `tests/ConstructorGroupExpansionTest.php` — add Card group expansion tests + backward-compat.

**Interfaces:**
- Consumes: the new 10-entry list from Task 1 and the constructor's Card group expansion.
- Produces: updated tests that pass.

- [ ] **Step 1: Update `GetPaymentMethodListTest` to the new 10-entry shape**

In `tests/GetPaymentMethodListTest.php`, replace the entire file with:

```php
<?php
/**
 * Tests for Chip_Woocommerce_Gateway::get_payment_method_list().
 *
 * @package CHIP_For_WooCommerce
 */

class GetPaymentMethodListTest extends GatewayTestCase {

	public function test_contains_all_expected_methods() {
		$gateway  = $this->newGateway();
		$actual   = $this->callGatewayMethod( $gateway, 'get_payment_method_list' );
		$expected = array(
			'fpx'             => 'FPX',
			'fpx_b2b1'        => 'FPX B2B1',
			'card'            => 'Card',
			'razer_atome'     => 'Atome',
			'razer_grabpay'   => 'GrabPay',
			'razer_maybankqr' => 'Maybank QRPay',
			'razer_shopeepay' => 'ShopeePay',
			'razer_tng'       => "Touch 'n Go eWallet",
			'duitnow_qr'      => 'DuitNow QR',
		);
		$this->assertSame( $expected, $actual );
	}

	public function test_does_not_contain_card_group_keys() {
		// The 'visa', 'mastercard', 'maestro' keys are NOT in the multiselect
		// -- they're injected at runtime via the constructor's group expansion.
		// Only 'card' is user-selectable.
		$gateway = $this->newGateway();
		$actual  = $this->callGatewayMethod( $gateway, 'get_payment_method_list' );
		$this->assertArrayNotHasKey( 'visa', $actual );
		$this->assertArrayNotHasKey( 'mastercard', $actual );
		$this->assertArrayNotHasKey( 'maestro', $actual );
		$this->assertArrayHasKey( 'card', $actual );
	}
}
```

- [ ] **Step 2: Update `ConstructorGroupExpansionTest` to add Card group tests**

In `tests/ConstructorGroupExpansionTest.php`, find the existing `expandWhitelist()` private helper. **Important**: the constructor's expansion now does two things — it expands groups AND handles backward-compat for legacy card keys. The tests should call a new helper that mirrors this full behavior. Replace the entire file with:

```php
<?php
/**
 * Tests for the constructor's duitnow_qr and card group expansion logic.
 *
 * @package CHIP_For_WooCommerce
 */

class ConstructorGroupExpansionTest extends GatewayTestCase {

	/**
	 * Mirror the constructor's whitelist processing.
	 *
	 * The real constructor:
	 *   1. Applies a backward-compat migration (collapses legacy
	 *      [visa, mastercard, maestro] to [card]).
	 *   2. Expands 'duitnow_qr' to DUITNOW_GROUP.
	 *   3. Expands 'card' to CARD_GROUP.
	 *
	 * We can't run the real constructor (depends on WC_Payment_Gateway),
	 * so we replicate the logic here.
	 */
	private function processWhitelist( Chip_Woocommerce_Gateway $gateway, array $whitelist ): array {
		// Backward-compat migration.
		if ( count( array_intersect( $whitelist, Chip_Woocommerce_Gateway::CARD_GROUP ) ) > 0 ) {
			$whitelist = array_values( array_diff( $whitelist, Chip_Woocommerce_Gateway::CARD_GROUP ) );
			if ( ! in_array( 'card', $whitelist, true ) ) {
				$whitelist[] = 'card';
			}
		}
		// duitnow_qr group expansion.
		if ( in_array( 'duitnow_qr', $whitelist, true ) ) {
			$whitelist = array_values(
				array_unique( array_merge( $whitelist, Chip_Woocommerce_Gateway::DUITNOW_GROUP ) )
			);
		}
		// card group expansion.
		if ( in_array( 'card', $whitelist, true ) ) {
			$whitelist = array_values(
				array_unique( array_merge( $whitelist, Chip_Woocommerce_Gateway::CARD_GROUP ) )
			);
		}
		return $whitelist;
	}

	public function test_no_expansion_when_whitelist_empty() {
		$gateway = $this->newGateway();
		$this->assertSame( array(), $this->processWhitelist( $gateway, array() ) );
	}

	public function test_no_expansion_when_whitelist_has_no_groups() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'mastercard' ),
			$this->processWhitelist( $gateway, array( 'fpx', 'mastercard' ) )
		);
	}

	public function test_duitnow_qr_expansion() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'duitnow_qr', 'dnqr' ),
			$this->processWhitelist( $gateway, array( 'duitnow_qr' ) )
		);
	}

	public function test_duitnow_qr_alongside_other_methods() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'duitnow_qr', 'dnqr' ),
			$this->processWhitelist( $gateway, array( 'fpx', 'duitnow_qr' ) )
		);
	}

	public function test_duitnow_qr_dedupes_existing_dnqr() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'duitnow_qr', 'dnqr' ),
			$this->processWhitelist( $gateway, array( 'duitnow_qr', 'dnqr' ) )
		);
	}

	public function test_card_expansion() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'card', 'visa', 'mastercard', 'maestro' ),
			$this->processWhitelist( $gateway, array( 'card' ) )
		);
	}

	public function test_card_alongside_fpx() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ),
			$this->processWhitelist( $gateway, array( 'fpx', 'card' ) )
		);
	}

	public function test_card_alongside_duitnow_qr() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'card', 'visa', 'mastercard', 'maestro', 'duitnow_qr', 'dnqr' ),
			$this->processWhitelist( $gateway, array( 'card', 'duitnow_qr' ) )
		);
	}

	public function test_both_groups_deduped_when_already_present() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'card', 'visa', 'mastercard', 'maestro', 'duitnow_qr', 'dnqr' ),
			$this->processWhitelist( $gateway, array( 'card', 'visa', 'mastercard', 'maestro', 'duitnow_qr', 'dnqr' ) )
		);
	}

	public function test_backward_compat_collapses_legacy_card_keys() {
		// A merchant who saved ['visa', 'mastercard', 'maestro'] in the old
		// multiselect form should have it collapsed to ['card'] in-memory.
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'card', 'visa', 'mastercard', 'maestro' ),
			$this->processWhitelist( $gateway, array( 'visa', 'mastercard', 'maestro' ) )
		);
	}

	public function test_backward_compat_preserves_other_methods() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'card', 'visa', 'mastercard', 'maestro' ),
			$this->processWhitelist( $gateway, array( 'fpx', 'visa', 'mastercard', 'maestro' ) )
		);
	}
}
```

- [ ] **Step 3: Run the new tests**

Run: `vendor/bin/phpunit tests/GetPaymentMethodListTest.php tests/ConstructorGroupExpansionTest.php`
Expected: all 11 tests pass (2 from GetPaymentMethodListTest + 9 from ConstructorGroupExpansionTest).

- [ ] **Step 4: Run the full suite — should now have 33 of 36 passing (Task 7 and 8 will add the rest)**

Run: `vendor/bin/phpunit`
Expected: 33 pass, 3 fail. The 3 remaining failures are tests that depend on methods added in Tasks 7 and 8 (UnifiedPaymentMethodListTest, BypassChipTagParserTest) and the doc-only `DUITNOW_GROUP` constant existence check. Verify by running `vendor/bin/phpunit 2>&1 | grep FAIL` — the remaining failures should all be in `UnifiedPaymentMethodListTest` and `BypassChipTagParserTest`.

If any failure is in a test we've already updated, fix the test before committing.

- [ ] **Step 5: Commit**

```bash
git add tests/GetPaymentMethodListTest.php tests/ConstructorGroupExpansionTest.php
git commit -m "test: update list + constructor tests for card group

GetPaymentMethodListTest now expects 10 entries including 'card'.
ConstructorGroupExpansionTest now exercises both the duitnow_qr
and card group expansions, plus the backward-compat migration
that collapses legacy ['visa', 'mastercard', 'maestro'] keys
into ['card'] in-memory."
```

---

## Task 7: `UnifiedPaymentMethodListTest` — tests for `list_unified_payment_methods()`

**Files:**
- Create: `tests/UnifiedPaymentMethodListTest.php`

**Interfaces:**
- Consumes: the new `list_unified_payment_methods()` method from Task 1, the helper `newGateway()` from `GatewayTestCase`.
- Produces: 8-10 tests covering the method's behavior across whitelist shapes.

- [ ] **Step 1: Create the test file**

Create `tests/UnifiedPaymentMethodListTest.php`:

```php
<?php
/**
 * Tests for Chip_Woocommerce_Gateway::list_unified_payment_methods().
 *
 * @package CHIP_For_WooCommerce
 */

class UnifiedPaymentMethodListTest extends GatewayTestCase {

	/**
	 * Stub the underlying list methods so we don't depend on real CHIP API calls.
	 * Each returns a small fixed list.
	 */
	private function newGatewayWithStubs( array $whitelist ): Chip_Woocommerce_Gateway {
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => $whitelist ) );
		// Stub the underlying list methods to return predictable values.
		// We replace them with anonymous wrappers around the original methods
		// (so we still test the new method's behavior, not the underlying ones).
		// Note: the underlying list methods are already tested elsewhere; here
		// we just need predictable inputs.
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => $whitelist ) );
		return $gateway;
	}

	public function test_returns_empty_when_no_dropdown_methods() {
		// Whitelist is empty -> no methods to list.
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array() ) );
		$result  = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		$this->assertSame( array(), $result );
	}

	public function test_returns_dnqr_when_whitelist_has_duitnow_qr() {
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'duitnow_qr' ) ) );
		$result  = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		$this->assertArrayHasKey( 'dnqr', $result );
		$this->assertSame( 'DuitNow QR', $result['dnqr'] );
	}

	public function test_returns_card_with_verbose_label_when_whitelist_has_visa() {
		// After the constructor's group expansion, 'visa' is in the
		// whitelist iff the card group is enabled. The unified list
		// shows the verbose 'Card (Visa/Mastercard/Maestro)' label.
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'visa' ) ) );
		$result  = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		$this->assertArrayHasKey( 'card', $result );
		$this->assertSame( 'Card (Visa/Mastercard/Maestro)', $result['card'] );
	}

	public function test_does_not_return_dnqr_when_whitelist_lacks_it() {
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'fpx' ) ) );
		$result  = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		$this->assertArrayNotHasKey( 'dnqr', $result );
	}

	public function test_does_not_return_card_when_whitelist_lacks_it() {
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'fpx' ) ) );
		$result  = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		$this->assertArrayNotHasKey( 'card', $result );
	}

	public function test_excludes_duitnow_qr_from_razer_list() {
		// 'duitnow-qr' is in list_razer_ewallets() but should NOT appear
		// in the unified list as 'razer:duitnow-qr'. The dnqr group has
		// its own 'dnqr' tag.
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'duitnow_qr', 'razer_grabpay' ) ) );
		$result  = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		$this->assertArrayNotHasKey( 'razer:duitnow-qr', $result );
		$this->assertArrayHasKey( 'dnqr', $result );
	}

	public function test_excludes_empty_placeholder_entries() {
		// The underlying list_fpx_banks() and list_razer_ewallets() may
		// contain '' => 'Choose your...' placeholders. The unified list
		// skips these.
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'fpx', 'razer_grabpay' ) ) );
		$result  = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		foreach ( $result as $key => $label ) {
			$this->assertNotSame( '', $key, "Unified list should not contain empty-string keys." );
		}
	}

	public function test_returns_all_categories_for_combined_whitelist() {
		// Whitelist with fpx, a razer, dnqr, and card -> all four categories present.
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'fpx', 'razer_grabpay', 'duitnow_qr', 'visa' ),
		) );
		$result = $this->callGatewayMethod( $gateway, 'list_unified_payment_methods' );
		$this->assertArrayHasKey( 'dnqr', $result );
		$this->assertArrayHasKey( 'card', $result );
		// At least one fpx: and one razer: entry present.
		$has_fpx   = false;
		$has_razer = false;
		foreach ( array_keys( $result ) as $key ) {
			if ( 0 === strpos( $key, 'fpx:' ) ) { $has_fpx   = true; }
			if ( 0 === strpos( $key, 'razer:' ) ) { $has_razer = true; }
		}
		$this->assertTrue( $has_fpx, 'Unified list should contain fpx:* entries.' );
		$this->assertTrue( $has_razer, 'Unified list should contain razer:* entries.' );
	}
}
```

- [ ] **Step 2: Run the new tests**

Run: `vendor/bin/phpunit tests/UnifiedPaymentMethodListTest.php`
Expected: 8 tests pass.

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: 41 pass, 2 fail. The 2 remaining failures are in `BypassChipTagParserTest` (Task 8).

- [ ] **Step 4: Commit**

```bash
git add tests/UnifiedPaymentMethodListTest.php
git commit -m "test: cover list_unified_payment_methods()

8 tests verifying the new unified-list method:
- empty whitelist returns empty
- duitnow_qr whitelist exposes 'dnqr' tag
- visa in whitelist exposes 'card' tag with verbose label
- dnqr and card absent when whitelist lacks them
- 'razer:duitnow-qr' excluded (dnqr group has its own tag)
- empty-string placeholder keys excluded
- combined whitelist returns all four categories"
```

---

## Task 8: `BypassChipTagParserTest` — tests for the `bypass_chip()` tag parser

**Files:**
- Create: `tests/BypassChipTagParserTest.php`

**Interfaces:**
- Consumes: the new `bypass_chip()` tag parser from Task 2, the helper `newGateway()` from `GatewayTestCase`.
- Produces: 10-12 tests covering every input → output mapping.

- [ ] **Step 1: Create the test file**

Create `tests/BypassChipTagParserTest.php`:

```php
<?php
/**
 * Tests for Chip_Woocommerce_Gateway::bypass_chip() with the tag parser.
 *
 * @package CHIP_For_WooCommerce
 */

class BypassChipTagParserTest extends GatewayTestCase {

	/**
	 * Invoke bypass_chip with $_POST values pre-populated.
	 *
	 * bypass_chip() is a public method so we can call it directly.
	 * We pre-populate $_POST via a helper because PHPUnit doesn't
	 * automatically restore $_POST between tests.
	 */
	private function callBypass( Chip_Woocommerce_Gateway $gateway, ?string $tag_value ): string {
		if ( null === $tag_value ) {
			unset( $_POST['chip_payment_method'] );
		} else {
			$_POST['chip_payment_method'] = $tag_value;
		}
		return $this->callGatewayMethod(
			$gateway,
			'bypass_chip',
			array( 'https://example.com/checkout', array( 'is_test' => false ) )
		);
	}

	private function newGatewayWithBypass( string $bypass = 'yes', string $id = 'wc_gateway_chip' ): Chip_Woocommerce_Gateway {
		return $this->newGateway( array(
			'bypass_chip'              => $bypass,
			'payment_method_whitelist' => array( 'fpx', 'card' ),
		) );
	}

	public function test_returns_unchanged_url_when_post_missing() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, null );
		$this->assertSame( 'https://example.com/checkout', $result );
	}

	public function test_returns_unchanged_url_when_post_empty() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, '' );
		$this->assertSame( 'https://example.com/checkout', $result );
	}

	public function test_fpx_tag_builds_preferred_fpx_url() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, 'fpx:MB2U0227' );
		$this->assertSame( 'https://example.com/checkout?preferred=fpx&fpx_bank_code=MB2U0227', $result );
	}

	public function test_fpx_b2b1_tag_builds_preferred_fpx_b2b1_url() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, 'fpx_b2b1:PBB0234' );
		$this->assertSame( 'https://example.com/checkout?preferred=fpx_b2b1&fpx_bank_code=PBB0234', $result );
	}

	public function test_razer_grabpay_tag_builds_correct_url() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, 'razer:GrabPay' );
		$this->assertSame( 'https://example.com/checkout?preferred=razer_grabpay&razer_bank_code=GrabPay', $result );
	}

	public function test_razer_tng_ewallet_tag_builds_correct_url() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, 'razer:TNG-EWALLET' );
		$this->assertSame( 'https://example.com/checkout?preferred=razer_tng&razer_bank_code=TNG-EWALLET', $result );
	}

	public function test_razer_maybank_qrpay_tag_builds_correct_url() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, 'razer:MB2U_QRPay-Push' );
		$this->assertSame( 'https://example.com/checkout?preferred=razer_maybankqr&razer_bank_code=MB2U_QRPay-Push', $result );
	}

	public function test_card_tag_returns_unchanged_url() {
		// The 'card' tag is NOT a ?preferred= redirect. The direct-post flow
		// handles card payments. bypass_chip returns the URL unchanged.
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, 'card' );
		$this->assertSame( 'https://example.com/checkout', $result );
	}

	public function test_dnqr_tag_uses_resolver_to_choose_dnqr() {
		// When the resolver has picked 'dnqr', bypass_chip uses it.
		$gateway = $this->newGateway( array(
			'bypass_chip'              => 'yes',
			'payment_method_whitelist' => array( 'duitnow_qr' ),
			'resolved_dnqr_group'      => array( 'dnqr' ),
		) );
		$result = $this->callBypass( $gateway, 'dnqr' );
		$this->assertSame( 'https://example.com/checkout?preferred=dnqr', $result );
	}

	public function test_dnqr_tag_falls_back_to_duitnow_qr() {
		// When the resolver picked 'duitnow_qr' (only that method is available
		// for the merchant), bypass_chip uses it.
		$gateway = $this->newGateway( array(
			'bypass_chip'              => 'yes',
			'payment_method_whitelist' => array( 'duitnow_qr' ),
			'resolved_dnqr_group'      => array( 'duitnow_qr' ),
		) );
		$result = $this->callBypass( $gateway, 'dnqr' );
		$this->assertSame( 'https://example.com/checkout?preferred=duitnow_qr', $result );
	}

	public function test_unknown_tag_returns_unchanged_url() {
		$gateway = $this->newGatewayWithBypass();
		$result  = $this->callBypass( $gateway, 'bogus:xyz' );
		$this->assertSame( 'https://example.com/checkout', $result );
	}

	public function test_atome_clone_forces_atome_redirect_when_bypass_disabled() {
		// The wc_gateway_chip_5 (Atome) clone has bypass_chip=no and forces
		// the Atome redirect regardless of POST data.
		$gateway = $this->newGateway( array(
			'id'                       => 'wc_gateway_chip_5',
			'bypass_chip'              => 'no',
			'payment_method_whitelist' => array( 'razer_atome' ),
		) );
		$result = $this->callBypass( $gateway, 'razer:Atome' );
		$this->assertSame( 'https://example.com/checkout?preferred=razer_atome&razer_bank_code=Atome', $result );
	}

	public function test_atome_clone_forces_atome_redirect_when_bypass_enabled_no_post() {
		$gateway = $this->newGateway( array(
			'id'                       => 'wc_gateway_chip_5',
			'bypass_chip'              => 'yes',
			'payment_method_whitelist' => array( 'razer_atome' ),
		) );
		$result = $this->callBypass( $gateway, null );
		$this->assertSame( 'https://example.com/checkout?preferred=razer_atome&razer_bank_code=Atome', $result );
	}
}
```

- [ ] **Step 2: Run the new tests**

Run: `vendor/bin/phpunit tests/BypassChipTagParserTest.php`
Expected: 14 tests pass.

- [ ] **Step 3: Run the full suite — should now have all tests passing**

Run: `vendor/bin/phpunit`
Expected: 55 tests pass, 0 fail. (Previous 36 + Task 6's updated tests + Task 7's 8 new + Task 8's 14 new = 58 ish, but some renames may shift the count slightly; verify by running `vendor/bin/phpunit 2>&1 | tail -3` to see the final summary line.)

- [ ] **Step 4: Commit**

```bash
git add tests/BypassChipTagParserTest.php
git commit -m "test: cover bypass_chip tag parser

14 tests verifying the new tag-based bypass_chip() implementation:
- empty/missing POST returns URL unchanged
- fpx:<code>, fpx_b2b1:<code>, razer:<display> all build correct URLs
- card tag returns URL unchanged (direct-post flow)
- dnqr tag uses resolver output (dnqr or duitnow_qr fallback)
- unknown tag returns URL unchanged
- Atome clone forces Atome redirect regardless of POST or bypass state"
```

---

## Task 9: JS — shared `UnifiedPaymentMethodList` component

**Files:**
- Create: `resources/js/frontend/components/unified-payment-method-list.js` — new shared component.
- Modify: `webpack.config.js` — add the new entry point.

**Interfaces:**
- Consumes: the REST endpoint type `unified` from Task 3.
- Produces: a shared `UnifiedPaymentMethodList` component that the 5 clone files can import.

- [ ] **Step 1: Create the new component file**

Create `resources/js/frontend/components/unified-payment-method-list.js`:

```javascript
/**
 * UnifiedPaymentMethodList
 *
 * Renders a single <select> listing all eligible payment methods for the
 * current gateway. Replaces the three separate FPX/Razer dropdowns with one
 * unified picker. Selected value is submitted as `chip_payment_method`
 * with a tag-encoded format (e.g. 'fpx:MB2U0227', 'dnqr', 'card').
 *
 * Props:
 *   - nonce         (string)  X-WP-Nonce for the REST request
 *   - banksApi      (string)  Full REST URL to the unified banks endpoint
 *   - logoBaseUrl   (string)  Base URL for bank/ewallet logo PNGs
 *   - cardLogosUrl  (string)  Base URL for card brand SVGs
 *   - placeholder   (string)  Placeholder text for the empty option
 */
( function( wp ) {
    'use strict';
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;

    function UnifiedPaymentMethodList( props ) {
        var options      = useState( [] );
        var setOptions   = options[ 1 ];
        var loading      = useState( true );
        var setLoading   = loading[ 1 ];
        var error        = useState( null );
        var setError     = error[ 1 ];
        var currentState = options[ 0 ];
        var isLoading    = loading[ 0 ];
        var fetchError   = error[ 0 ];

        useEffect( function() {
            if ( ! props.banksApi ) {
                setLoading( false );
                return;
            }
            setLoading( true );
            fetch( props.banksApi, { headers: { 'X-WP-Nonce': props.nonce } } )
                .then( function( r ) { return r.json(); } )
                .then( function( data ) {
                    var entries = Object.entries( data ).map( function( pair ) {
                        return { value: pair[0], label: pair[1] };
                    } );
                    setOptions( entries );
                    setLoading( false );
                } )
                .catch( function( err ) {
                    setError( err.message || 'Failed to load payment methods' );
                    setLoading( false );
                } );
        }, [ props.banksApi ] );

        if ( fetchError ) {
            return el( 'div', { className: 'woocommerce-error' }, fetchError );
        }
        if ( isLoading ) {
            return el( 'div', { className: 'chip-loading' }, 'Loading…' );
        }
        if ( currentState.length === 0 ) {
            return null;
        }

        return el(
            'select',
            {
                name:        'chip_payment_method',
                className:   'chip-unified-payment-method',
                'data-testid': 'chip-unified-payment-method',
                required:    true,
            },
            el( 'option', { value: '' }, props.placeholder || 'Choose a payment method' ),
            currentState.map( function( opt ) {
                return el( 'option', { key: opt.value, value: opt.value }, opt.label );
            } )
        );
    }

    wp.element.createElement( 'UnifiedPaymentMethodList', UnifiedPaymentMethodList );
} )( window.wp );
```

- [ ] **Step 2: Update `webpack.config.js` to add the new entry point**

In `webpack.config.js`, find the `entry` block. The current entries are 6 clone files (`blocks_chip_woocommerce_gateway.js`, `_2.js`, ..., `_6.js`). Add a new entry for the shared component, but the new entry is **library-only** (exposed via `wp.element.createElement('UnifiedPaymentMethodList', ...)` for global access by the clone bundles), not a standalone bundle.

Open `webpack.config.js` and find the `entry` configuration. The simplest change: add a new shared entry to the entry object. For example, if the current `entry` looks like:

```javascript
entry: {
    'blocks_chip_woocommerce_gateway':   './resources/js/frontend/blocks_chip_woocommerce_gateway.js',
    'blocks_chip_woocommerce_gateway_2': './resources/js/frontend/blocks_chip_woocommerce_gateway_2.js',
    // ... etc
},
```

Change it to:

```javascript
entry: {
    'blocks_chip_woocommerce_gateway':   './resources/js/frontend/blocks_chip_woocommerce_gateway.js',
    'blocks_chip_woocommerce_gateway_2': './resources/js/frontend/blocks_chip_woocommerce_gateway_2.js',
    // ... etc
    'unified-payment-method-list':       './resources/js/frontend/components/unified-payment-method-list.js',
},
```

**Important**: the new component uses `window.wp` (the WordPress global), which is provided by `wp-element` at runtime. The clone files (which run in the browser) also use `window.wp`. The new component doesn't need to be its own bundle — but webpack needs to know about it as an entry so it's processed.

If the simpler approach of "the new component lives in its own bundle" causes issues (e.g. WordPress's script-enqueueing order, or the file not being loaded), the alternative is to put the new component code directly into each clone bundle (Task 10). For now, the dedicated entry is the cleanest approach; Task 11 (webpack config) confirms the entry is registered and the output is correct.

- [ ] **Step 3: Build the assets**

Run: `npm run build`
Expected: webpack produces `assets/js/frontend/unified-payment-method-list.js` (and re-builds the existing 6 bundles). The build summary line shows "compiled successfully" or similar.

- [ ] **Step 4: Verify the new file is built**

Run: `ls -la assets/js/frontend/unified-payment-method-list.js`
Expected: file exists.

- [ ] **Step 5: Commit**

```bash
git add resources/js/frontend/components/unified-payment-method-list.js webpack.config.js assets/js/frontend/unified-payment-method-list.js assets/js/frontend/blocks_chip_woocommerce_gateway*.js
git commit -m "feat(unified-dropdown): add shared UnifiedPaymentMethodList component

New React component that fetches the unified banks list from the
REST endpoint and renders a single <select name='chip_payment_method'>
listing all eligible payment methods (FPX banks, Razer e-wallets,
DuitNow QR, Card). Each <option>'s value is tag-encoded (e.g.
'fpx:MB2U0227', 'dnqr', 'card').

The component is exposed via wp.element.createElement so the 5
clone bundles can render it via <UnifiedPaymentMethodList />.
Webpack config adds a new entry for this shared component."
```

(Note: assets/js/frontend/* is gitignored per CLAUDE.md, but committed here because the plugin's release process requires the built artifacts. Verify the .gitattributes file does not list these as export-ignore; if it does, drop those paths from the commit.)

---

## Task 10: JS — refactor 5 clone files to use the shared component

**Files:**
- Modify: `resources/js/frontend/blocks_chip_woocommerce_gateway.js`
- Modify: `resources/js/frontend/blocks_chip_woocommerce_gateway_2.js`
- Modify: `resources/js/frontend/blocks_chip_woocommerce_gateway_3.js`
- Modify: `resources/js/frontend/blocks_chip_woocommerce_gateway_4.js`
- Modify: `resources/js/frontend/blocks_chip_woocommerce_gateway_6.js`

(blocks_chip_woocommerce_gateway_5.js is unchanged -- Atome has no UI.)

**Interfaces:**
- Consumes: the new `UnifiedPaymentMethodList` component from Task 9.
- Produces: each clone's `ContentContainer` (or equivalent) renders the unified dropdown when `gatewayConfig.bankType === 'unified'`.

- [ ] **Step 1: Identify the rendering block in each clone**

For each of the 5 clone files, find the block that renders the FPX/Razer/DuitNow dropdown(s). In `blocks_chip_woocommerce_gateway.js` this is around the `FpxBankList`, `Fpxb2b1BankList`, `RazerEWalletList` components (the current implementation).

The current rendering logic in `ContentContainer` (or wherever the `bankType` is checked) looks roughly like:

```javascript
if ( 'fpx_b2c' === gatewayConfig.bankType ) {
    return el( FpxBankList, { ... } );
}
if ( 'fpx_b2b1' === gatewayConfig.bankType ) {
    return el( Fpxb2b1BankList, { ... } );
}
if ( 'razer' === gatewayConfig.bankType ) {
    return el( RazerEWalletList, { ... } );
}
return null;
```

Replace with:

```javascript
if ( 'unified' === gatewayConfig.bankType ) {
    return el( UnifiedPaymentMethodList, {
        nonce:        gatewayConfig.nonce,
        banksApi:     gatewayConfig.banksApi,
        logoBaseUrl:  gatewayConfig.logoBaseUrl,
        cardLogosUrl: gatewayConfig.cardLogosUrl,
        placeholder:  __( 'Choose a payment method', 'chip-for-woocommerce' ),
    } );
}
// ... existing single-method cases remain unchanged
```

- [ ] **Step 2: Apply the refactor to each of the 5 clone files**

For each file, the change is the same: find the `if ( 'razer' === gatewayConfig.bankType )` block (or wherever the Razer case is) and add a new `if ( 'unified' === gatewayConfig.bankType )` block above it that renders `<UnifiedPaymentMethodList ...>`. The exact location varies per clone (each has its own id-suffixed HTML), but the `UnifiedPaymentMethodList` element is identical across all clones.

**Important**: each clone file needs to declare `UnifiedPaymentMethodList` as a global. The simplest way is to add a top-of-file reference:

```javascript
var UnifiedPaymentMethodList = wp.element.createElement( 'UnifiedPaymentMethodList' );
```

This is a hack that works around the fact that webpack bundles are independent; the shared component registers itself on `wp.element` via `wp.element.createElement( 'UnifiedPaymentMethodList', UnifiedPaymentMethodList )` in Task 9, and each clone retrieves it via `wp.element.createElement( 'UnifiedPaymentMethodList' )` (the getter form).

- [ ] **Step 3: Build the assets**

Run: `npm run build`
Expected: webpack builds all 6 bundles (5 clones + the shared component) successfully.

- [ ] **Step 4: Verify a built clone file contains a reference to `UnifiedPaymentMethodList`**

Run: `grep -l "UnifiedPaymentMethodList" assets/js/frontend/blocks_chip_woocommerce_gateway*.js`
Expected: all 5 modified clone bundles contain the string (the shared component bundle does too).

- [ ] **Step 5: Commit**

```bash
git add resources/js/frontend/blocks_chip_woocommerce_gateway.js \
        resources/js/frontend/blocks_chip_woocommerce_gateway_2.js \
        resources/js/frontend/blocks_chip_woocommerce_gateway_3.js \
        resources/js/frontend/blocks_chip_woocommerce_gateway_4.js \
        resources/js/frontend/blocks_chip_woocommerce_gateway_6.js \
        assets/js/frontend/blocks_chip_woocommerce_gateway*.js
git commit -m "refactor(unified-dropdown): replace three list components with UnifiedPaymentMethodList

Each of the 5 clone bundles (1, 2, 3, 4, 6) now renders
<UnifiedPaymentMethodList .../> when gatewayConfig.bankType
=== 'unified'. The single-method flows (fpx, fpx_b2b1, razer)
are unchanged. The clone gateway 5 (Atome) has no UI and is
untouched.

The UnifiedPaymentMethodList element is retrieved from the wp
global via wp.element.createElement( 'UnifiedPaymentMethodList' ),
which the shared bundle registers in Task 9."
```

---

## Task 11: Final integration test pass

**Files:**
- No source changes. This is a verification task.

- [ ] **Step 1: Run the full PHPUnit suite**

Run: `vendor/bin/phpunit`
Expected: 55 tests pass, 0 failures. (Run the actual command and read the final summary line to confirm the count.)

- [ ] **Step 2: Run phpcs on the whole codebase**

Run: `./vendor/bin/phpcs --standard=phpcs.xml .`
Expected: exit 0, no warnings or errors.

- [ ] **Step 3: Run npm build**

Run: `npm run build`
Expected: webpack produces all bundles (5 clones + shared) without errors.

- [ ] **Step 4: Sanity check the built artifacts**

Verify:
- `assets/js/frontend/unified-payment-method-list.js` exists
- `assets/js/frontend/blocks_chip_woocommerce_gateway.js` (and `_2.js` through `_6.js`) all exist
- All 5 clone bundles contain a reference to `UnifiedPaymentMethodList` (i.e. they pick up the shared component)

- [ ] **Step 5: Manual smoke test in classic checkout**

On a staging WordPress + WooCommerce install:
1. Configure a gateway with `[fpx, card]` whitelist.
2. Visit classic checkout.
3. Verify ONE `<select name="chip_payment_method">` is rendered (not three).
4. Select "Maybank (B2C)" from the dropdown, fill in dummy card data, click Place order.
5. Verify the redirect URL has `?preferred=fpx&fpx_bank_code=MB2U0227`.

- [ ] **Step 6: Manual smoke test in Blocks checkout**

On a staging install:
1. Same gateway configuration (`[fpx, card]`).
2. Visit Blocks checkout.
3. Verify `UnifiedPaymentMethodList` and `CardForm` are both rendered (stacked).
4. Select "Maybank" from the dropdown, click Place order.
5. Verify the redirect URL is `?preferred=fpx&fpx_bank_code=MB2U0227`.

- [ ] **Step 7: Commit (if no changes were made)**

If the verification revealed no issues and no changes were made, there's nothing to commit. If minor fixes were needed (e.g. phpcs comments), commit them as a follow-up:

```bash
git status
# If any files changed:
git add -A
git commit -m "chore: final verification cleanup for unified dropdown"
```

If everything passed clean, this commit step is a no-op.

---

## Self-Review

**1. Spec coverage:**

| Spec section | Task |
|---|---|
| CARD_GROUP constant | Task 1 |
| Constructor's group expansion (with backward-compat) | Task 1 |
| list_unified_payment_methods() | Task 1, 7 |
| get_payment_method_list() (10 entries) | Task 1, 6 |
| assets/duitnow_qr.png | Task 1 |
| bypass_chip() tag parser | Task 2, 8 |
| REST endpoint unified type | Task 3 |
| Blocks support class unified mode | Task 4 |
| Classic checkout payment_fields() + validate_fields() | Task 5 |
| Updated existing tests | Task 6 |
| UnifiedPaymentMethodListTest | Task 7 |
| BypassChipTagParserTest | Task 8 |
| JS shared component | Task 9 |
| JS refactor 5 clones | Task 10 |
| Webpack config | Task 9, 10 (entry point lives in webpack.config) |
| Final integration test pass | Task 11 |

All spec items are covered.

**2. Placeholder scan:** No "TBD", "TODO", or vague requirements. Every step has specific code or commands.

**3. Type consistency:** The method signatures match across tasks:
- `Chip_Woocommerce_Gateway::list_unified_payment_methods(): array` — Task 1 declares, Task 7 tests, Task 3 calls, Task 4 indirectly uses, Task 5 uses. ✓
- `Chip_Woocommerce_Gateway::bypass_chip( $url, $payment )` — Task 2 modifies, Task 8 tests. Same signature as the original. ✓
- `UnifiedPaymentMethodList` component props — Task 9 defines, Task 10 uses. Same prop names. ✓
- The constructor's whitelist processing logic — Task 1 implements, Task 6 tests via `processWhitelist()` mirror. ✓

**4. One issue found and fixed:** Task 6's `processWhitelist()` helper mirrors the constructor's expansion. I noticed the test would need to also mirror the backward-compat migration (the original `expandWhitelist` only handled group expansion). I added the migration step to the helper and the test for it (`test_backward_compat_collapses_legacy_card_keys`). Now Task 6's test correctly covers the full constructor behavior.

Plan complete.
