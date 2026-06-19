# dnqr Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `dnqr` as a runtime payment-method identifier alongside `duitnow_qr`, with dnqr-prioritized selection based on the merchant's `/payment_methods/` response (30-min cache), and no breaking changes for existing merchants.

**Architecture:** Single resolver helper `resolve_duitnow_methods()` invoked at the one site where the gateway serializes `payment_method_whitelist` for the `create_payment` call. Stores its dnqr-group result on `$resolved_dnqr_group` so `bypass_chip()` can reuse it without a second API call. The Razer e-wallet switch's `case 'duitnow-qr':` and the single-method DuitNow QR branch both apply the same dnqr-priority logic. No JS, no DB migration, no settings-page migration.

**Tech Stack:** PHP 7.4+ (per `phpcs.xml` PHPCompatibilityWP testVersion), WordPress / WooCommerce, `wp_remote_request` via `Chip_Woocommerce_API`, `get_transient`/`set_transient` for the 30-min cache.

**Spec:** `docs/superpowers/specs/2026-06-18-dnqr-migration-design.md`

## Global Constraints

- WordPress Coding Standards via `phpcs.xml` (tabs, 120-char line limit, `chip_` prefix).
- Every PHP file starts with `if ( ! defined( 'ABSPATH' ) ) { exit; }` (already in place — do not duplicate).
- Text domain is `chip-for-woocommerce` (use `__( '...', 'chip-for-woocommerce' )` for any new translatable strings).
- `phpcs` should pass cleanly on every modified file before commit.
- No new dependencies, no new files, no new JS.
- Subscription/recurring code path is **not** touched (L1785, `get_payment_method_for_recurring()`, `payment_recurring_methods()`).
- The base gateway is ~3,800 lines — make minimal, surgical edits; do not refactor surrounding code.

---

## File Map

| File | Role in this plan |
|---|---|
| `includes/class-chip-woocommerce-gateway.php` | Source of all gateway logic. Receives: new const, new property, new resolver method, new helper method, edits to `get_payment_method_list()`, `list_razer_ewallets()`, `bypass_chip()`, and `process_payment()` (L1771-1772). |
| `includes/class-chip-woocommerce-gateway-6.php` | DuitNow QR-only clone gateway. Single-line edit: preset whitelist default `[duitnow_qr]` → `[duitnow_qr, dnqr]`. |
| `changelog.txt` | Append one new line under the in-development section. |
| `readme.txt` | Append one new bullet under "Payment methods" or similar. |

No new files. No tests added (the repo has no test framework — `CLAUDE.md` confirms).

---

## Task Dependency Map

```
Task 1 (foundation: const + property + dnqr list entry)
   ↓
Task 2 (resolver: resolve_duitnow_methods method)
   ↓
Task 3 (bypass_chip + get_duitnow_qr_preferred)
Task 4 (process_payment call site + list_razer_ewallets trigger)
   ↓
Task 5 (Gateway 6 preset)
   ↓
Task 6 (docs: changelog + readme)
   ↓
Task 7 (manual integration test pass against spec's testing strategy)
```

Tasks 3 and 4 both modify `includes/class-chip-woocommerce-gateway.php` but at distinct, non-overlapping sites. They can be done sequentially (3 then 4) without conflict.

---

## Task 1: Foundation — `DUITNOW_GROUP` constant, `$resolved_dnqr_group` property, `dnqr` list entry

**Files:**
- Modify: `includes/class-chip-woocommerce-gateway.php`
  - Add `const DUITNOW_GROUP = array( 'duitnow_qr', 'dnqr' );` near the top of the class (after existing `const` declarations if any, otherwise near the top of the class body).
  - Add `protected $resolved_dnqr_group = array();` with the other `protected $` properties (the existing property block is around L83-130).
  - Modify `get_payment_method_list()` at L3480-3496 to add `'dnqr' => 'DuitNow QR (new)'` as the last entry.

**Interfaces:**
- Consumes: nothing (this is the first task).
- Produces:
  - `Chip_Woocommerce_Gateway::DUITNOW_GROUP` — array constant `['duitnow_qr', 'dnqr']`. Used by Tasks 2, 3, 4.
  - `$resolved_dnqr_group` instance property — empty array by default. Populated by Task 2's resolver. Read by Task 3's `bypass_chip()`.
  - `Chip_Woocommerce_Gateway::get_payment_method_list()` — returns the existing list plus `'dnqr' => 'DuitNow QR (new)'`.

**Why this is its own task:** Reviewers can validate the data-only foundation (constant, property, list entry) in isolation before any logic lands. No behavioral risk.

- [ ] **Step 1: Locate the property block**

Open `includes/class-chip-woocommerce-gateway.php` and find the block of `protected $` property declarations. In the current file this is around L83-130 (look for `protected $payment_method_whitelist;` and similar). Note the line number for the next step.

Also find the class header — search for `class Chip_Woocommerce_Gateway extends WC_Payment_Gateway` to find the class body start.

- [ ] **Step 2: Add the `DUITNOW_GROUP` constant**

Find the top of the class body (just after `class Chip_Woocommerce_Gateway extends WC_Payment_Gateway {`). Look for any existing `const` declarations near the top; if none exist, add the constant on its own line just inside the class body.

Add exactly:

```php
	/**
	 * DuitNow QR group: payment-method identifiers that are interchangeable
	 * for the merchant at runtime. dnqr is the modern identifier;
	 * duitnow_qr is the legacy identifier kept for backward compatibility.
	 *
	 * @var array
	 */
	const DUITNOW_GROUP = array( 'duitnow_qr', 'dnqr' );
```

(Two tabs of indentation since it's inside a class.)

- [ ] **Step 3: Add the `$resolved_dnqr_group` property**

Find an empty line within the `protected $` property block. Add this declaration next to the other properties (alphabetical order is fine but not required — just keep it with the other `protected $` lines):

```php

	/**
	 * Cached result of the dnqr resolver from the most recent resolve_duitnow_methods() call.
	 * Used by bypass_chip() to pick the correct ?preferred=dnqr|duitnow_qr without
	 * a second /payment_methods/ API call.
	 *
	 * @var array
	 */
	protected $resolved_dnqr_group = array();
```

- [ ] **Step 4: Add the `dnqr` entry to `get_payment_method_list()`**

Find `get_payment_method_list()` at L3480-3496. Replace the entire method body with:

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

The only change is the new line `'dnqr' => 'DuitNow QR (new)',` at the end.

- [ ] **Step 5: Run phpcs to verify no style violations**

Run from the repo root:

```bash
phpcs --standard=phpcs.xml includes/class-chip-woocommerce-gateway.php
```

Expected: no errors. If errors appear, fix them before committing (likely whitespace, indentation, or line-length issues). Note: a `Sniff comment` may be triggered if the constant or property docblock is on the wrong indentation level — fix to match the existing style of other constants/properties.

- [ ] **Step 6: Verify the constant is accessible**

Run a quick PHP syntax check on the file:

```bash
php -l includes/class-chip-woocommerce-gateway.php
```

Expected: `No syntax errors detected in includes/class-chip-woocommerce-gateway.php`.

- [ ] **Step 7: Commit**

```bash
git add includes/class-chip-woocommerce-gateway.php
git commit -m "feat(dnqr): add DUITNOW_GROUP constant, resolved_dnqr_group property, dnqr list entry

Foundation for the dnqr migration. The constant is the single
source of truth for the DuitNow QR group; the property caches the
resolver output for bypass_chip() to reuse; the new list entry
makes dnqr selectable in the multiselect UI."
```

---

## Task 2: Resolver — `resolve_duitnow_methods()` method

**Files:**
- Modify: `includes/class-chip-woocommerce-gateway.php`
  - Add new protected method `resolve_duitnow_methods()` immediately after `get_payment_method_list()` (around L3496+).

**Interfaces:**
- Consumes:
  - `Chip_Woocommerce_Gateway::DUITNOW_GROUP` — from Task 1.
  - `$this->brand_id` — existing instance property (used as part of the cache key).
  - `$this->api()` — existing method returning a configured `Chip_Woocommerce_API` instance.
  - `$this->log_info( string )` — existing logging method.
  - `$this->resolved_dnqr_group` — from Task 1 (this task populates it).
- Produces:
  - `Chip_Woocommerce_Gateway::resolve_duitnow_methods( array $whitelist, string $currency, int $amount ): array`
    - Returns the final whitelist to send to CHIP for `payment_method_whitelist`.
    - Sets `$this->resolved_dnqr_group` to the dnqr-group subset selected (or `[]` if the dnqr group was not in the configured whitelist).

**Why this is its own task:** The resolver is the core logic — group expansion, API call, intersection, priority, fallback, caching. Isolating it lets the reviewer walk through the behavioral reference table in the spec line-by-line without distractions.

- [ ] **Step 1: Locate the insertion point**

Find `get_payment_method_list()` in `includes/class-chip-woocommerce-gateway.php`. It ends at L3496. The new method goes immediately after it.

- [ ] **Step 2: Add the `resolve_duitnow_methods()` method**

Insert the following method directly after the closing `}` of `get_payment_method_list()`:

```php
	/**
	 * Resolve the configured payment_method_whitelist against the merchant's
	 * actual /payment_methods/ response, with dnqr-priority for the DuitNow QR group.
	 *
	 * Steps:
	 *   1. Group expansion: any dnqr-group member in the whitelist expands to the full group.
	 *   2. Cache key: brand + currency + amount-bucket (round to 100-sen steps).
	 *   3. Try cache. On miss, call /payment_methods/.
	 *   4. Fallback: return expanded whitelist unchanged if the API fails.
	 *   5. Intersect with available methods.
	 *   6. Priority: dnqr wins when both are present.
	 *   7. Cache the resolved group on $this->resolved_dnqr_group for bypass_chip().
	 *   8. Build the final whitelist (original non-group entries + resolved group).
	 *
	 * @param array  $whitelist Configured payment_method_whitelist.
	 * @param string $currency  Order currency code (e.g. 'MYR').
	 * @param int    $amount    Order total in sen (e.g. 12345 = RM 123.45).
	 * @return array            Final whitelist to send to CHIP.
	 */
	protected function resolve_duitnow_methods( array $whitelist, string $currency, int $amount ): array {
		// 1. Group expansion.
		$has_group_member = count( array_intersect( $whitelist, self::DUITNOW_GROUP ) ) > 0;
		$expanded         = $whitelist;
		if ( $has_group_member ) {
			$expanded = array_values( array_unique( array_merge( $whitelist, self::DUITNOW_GROUP ) ) );
		}

		// 2. Cache key: brand + currency + amount-bucket (round to 100-sen steps).
		$cache_key = 'chip_pm_' . md5( $this->brand_id . '|' . $currency . '|' . intval( $amount / 100 ) );

		// 3. Try cache. If hit, use it. If miss, call /payment_methods/.
		$available = get_transient( $cache_key );
		if ( false === $available ) {
			$chip     = $this->api();
			$response = $chip->payment_methods( $currency, '', $amount ); // no language param
			if ( ! is_array( $response ) || ! isset( $response['available_payment_methods'] ) ) {
				// 4a. Fallback: return expanded whitelist unchanged.
				$this->resolved_dnqr_group = $has_group_member ? self::DUITNOW_GROUP : array();
				$this->log_info( sprintf( 'dnqr resolver: API failed, fallback to expanded whitelist=%s', implode( ',', $expanded ) ) );
				return $expanded;
			}
			$available = $response['available_payment_methods']; // e.g. array( 'dnqr', 'fpx', ... )
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

		$this->log_info( sprintf(
			'dnqr resolver: configured=%s expanded=%s available=%s sent=%s preferred=%s',
			implode( ',', $whitelist ),
			implode( ',', $expanded ),
			implode( ',', (array) $available ),
			implode( ',', $final ),
			$resolved_group[0] ?? '(none)'
		) );

		return $final;
	}
```

- [ ] **Step 3: Verify with PHP syntax check**

```bash
php -l includes/class-chip-woocommerce-gateway.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 4: Run phpcs**

```bash
phpcs --standard=phpcs.xml includes/class-chip-woocommerce-gateway.php
```

Expected: no errors. The method is long; if line-length errors appear on the `sprintf()` call, wrap it across multiple lines using concatenation (`'... ' . $x . ' ...'`).

- [ ] **Step 5: Walk through the behavioral reference table mentally**

Open the spec at `docs/superpowers/specs/2026-06-18-dnqr-migration-design.md` lines 338-354. For each row in the table, trace through the method by hand and verify the result matches. Specifically verify:

- `[duitnow_qr]` + `[duitnow_qr, dnqr]` → sent `[dnqr]`, preferred `dnqr` ✓
- `[duitnow_qr]` + `[duitnow_qr]` → sent `[duitnow_qr]`, preferred `duitnow_qr` ✓
- `[duitnow_qr]` + API fails → fallback returns `[duitnow_qr, dnqr]`, resolved_dnqr_group = `['dnqr', 'duitnow_qr']`, first entry `dnqr` ✓
- `[fpx, mastercard]` (no dnqr group) → no expansion, no API call (well, technically the API still gets called — verify this is OK; the spec doesn't require short-circuiting when the group is absent. The behavior in that case: $available might be `[]` or contain `fpx/mastercard`, but $resolved_group is `[]` because the group isn't in $whitelist, so step 1's expansion doesn't happen — wait, re-read step 1 carefully.)

Re-check step 1: when the whitelist is `[fpx, mastercard]` (no dnqr group member), `$has_group_member` is false, so `$expanded` stays as `[fpx, mastercard]`. The method still calls the API, intersects, gets an empty `$resolved_group`, and returns the original whitelist unchanged. This is a wasted API call for non-dnqr whitelists. **Note this as a known inefficiency in the commit message — the alternative would be to short-circuit at the top, but keeping the resolver symmetrical makes it easier to maintain. Don't optimize now (YAGNI per spec).**

- [ ] **Step 6: Commit**

```bash
git add includes/class-chip-woocommerce-gateway.php
git commit -m "feat(dnqr): add resolve_duitnow_methods resolver

Expands the configured whitelist's dnqr group, calls
/payment_methods/ (cached for 30 min), intersects with available
methods, prioritizes dnqr over duitnow_qr, and falls back to the
expanded whitelist on API failure. The resolved dnqr-group subset
is cached on \$this->resolved_dnqr_group for bypass_chip() to reuse."
```

---

## Task 3: Helpers and `bypass_chip()` rewrite

**Files:**
- Modify: `includes/class-chip-woocommerce-gateway.php`
  - Add new public method `get_duitnow_qr_preferred()` immediately after `resolve_duitnow_methods()`.
  - Modify `bypass_chip()` at L2950-2989 to:
    - Update the `case 'duitnow-qr':` to apply priority (read `$this->resolved_dnqr_group`).
    - Replace the L2981 single-method branch with a call to `get_duitnow_qr_preferred()`.

**Interfaces:**
- Consumes:
  - `Chip_Woocommerce_Gateway::DUITNOW_GROUP` — from Task 1.
  - `$this->payment_method_whitelist` — existing instance property.
  - `$this->resolved_dnqr_group` — populated by Task 2's resolver.
  - `$this->bypass_chip`, `$this->id` — existing instance properties.
- Produces:
  - `Chip_Woocommerce_Gateway::get_duitnow_qr_preferred(): string`
    - Returns `dnqr` or `duitnow_qr` when the configured whitelist is purely the dnqr group.
    - Returns `''` otherwise (multiple groups, or no dnqr group).
  - `Chip_Woocommerce_Gateway::bypass_chip( string $url, array $payment ): string`
    - Razer e-wallet switch `case 'duitnow-qr':` reads `$this->resolved_dnqr_group` to pick `$preferred`.
    - Single-method DuitNow QR branch (was L2981) now uses `get_duitnow_qr_preferred()`.

**Why this is its own task:** `bypass_chip()` is the single most-modified function in the gateway (~40 lines of new logic). Isolating the helpers and the rewrite makes review tractable.

- [ ] **Step 1: Locate `bypass_chip()`**

In `includes/class-chip-woocommerce-gateway.php`, find `bypass_chip()` at L2950-2989. Note the exact line range.

- [ ] **Step 2: Add `get_duitnow_qr_preferred()` after `resolve_duitnow_methods()`**

Insert the following method directly after the closing `}` of `resolve_duitnow_methods()` (added in Task 2):

```php
	/**
	 * Get the ?preferred= value when the configured whitelist is a pure DuitNow QR group.
	 *
	 * Returns 'dnqr' (priority) or 'duitnow_qr' (fallback) when:
	 *   - the configured whitelist intersects the dnqr group, AND
	 *   - the configured whitelist has no other payment-method groups.
	 *
	 * Returns '' otherwise. Used by the single-method branch in bypass_chip().
	 *
	 * @return string 'dnqr' | 'duitnow_qr' | ''
	 */
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

- [ ] **Step 3: Update the Razer e-wallet switch's `case 'duitnow-qr':`**

In `bypass_chip()`, find the existing `case 'duitnow-qr':` block (around L2975). It currently reads:

```php
					case 'duitnow-qr':
						$preferred = 'duitnow_qr';
						break;
```

Replace it with:

```php
					case 'duitnow-qr':
						// Priority: dnqr if available, duitnow_qr fallback.
						// Reuse the resolver output from process_payment().
						$group     = ! empty( $this->resolved_dnqr_group ) ? $this->resolved_dnqr_group : self::DUITNOW_GROUP;
						$preferred = ! empty( $group ) ? $group[0] : 'duitnow_qr';
						break;
```

- [ ] **Step 4: Replace the L2981 single-method branch**

In `bypass_chip()`, find the existing single-method branch around L2981:

```php
			} elseif ( is_array( $this->payment_method_whitelist ) && 1 === count( $this->payment_method_whitelist ) && 'duitnow_qr' === $this->payment_method_whitelist[0] ) {
				$url .= '?preferred=duitnow_qr';
			}
```

Replace it with:

```php
			} else {
				// Single-method DuitNow QR branch: trigger when the configured
				// whitelist is purely the dnqr group (handles [duitnow_qr],
				// [dnqr], and [duitnow_qr, dnqr] for the dnqr-only gateway).
				$preferred = $this->get_duitnow_qr_preferred();
				if ( '' !== $preferred ) {
					$url .= '?preferred=' . $preferred;
				}
			}
```

- [ ] **Step 5: Verify the surrounding `elseif` chain still parses correctly**

The `elseif ( isset( $_POST['chip_razer_ewallet'] ) ... )` block ends with `} elseif (...)`. Make sure your replacement is exactly the `else` form — no trailing `elseif` after the new `else` block, and the closing `}` of the `bypass_chip` main `if ( 'yes' === $this->bypass_chip ... )` block is intact.

- [ ] **Step 6: PHP syntax check**

```bash
php -l includes/class-chip-woocommerce-gateway.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 7: Run phpcs**

```bash
phpcs --standard=phpcs.xml includes/class-chip-woocommerce-gateway.php
```

Expected: no errors.

- [ ] **Step 8: Walk through the bypass_chip flow mentally for a representative case**

For each of these, verify the `?preferred=` value:

- Whitelist `[duitnow_qr, dnqr]`, Razer dropdown pick `duitnow-qr`, resolved_dnqr_group=[dnqr]: → `?preferred=dnqr&razer_bank_code=duitnow-qr` ✓
- Whitelist `[duitnow_qr, dnqr]`, no dropdown (single-method path), resolved_dnqr_group=[dnqr]: → `?preferred=dnqr` ✓
- Whitelist `[duitnow_qr, dnqr, fpx]`, no dropdown: `get_duitnow_qr_preferred()` returns `''` because other_groups is `[fpx]`. No `?preferred=` appended. ✓ (matches spec table)
- Whitelist `[fpx]`, no dropdown: `get_duitnow_qr_preferred()` returns `''` because no dnqr group. Falls through to no `?preferred=`. ✓
- Whitelist `[razer_grabpay, dnqr]`, Razer dropdown pick `duitnow-qr`, resolved_dnqr_group=[dnqr]: → `?preferred=dnqr&razer_bank_code=duitnow-qr` ✓
- Whitelist `[razer_grabpay, dnqr]`, Razer dropdown pick `GrabPay`: → `?preferred=razer_grabpay&razer_bank_code=GrabPay` ✓ (Razer case unchanged)

- [ ] **Step 9: Commit**

```bash
git add includes/class-chip-woocommerce-gateway.php
git commit -m "feat(dnqr): apply dnqr-priority in bypass_chip

- New get_duitnow_qr_preferred() helper returns dnqr | duitnow_qr
  | '' based on configured whitelist and resolver output.
- bypass_chip() Razer e-wallet case 'duitnow-qr' now reads
  \$this->resolved_dnqr_group to pick the priority method.
- bypass_chip() single-method branch (was L2981) now triggers on
  'whitelist is purely the dnqr group' and uses the helper."
```

---

## Task 4: `process_payment()` call site + `list_razer_ewallets()` trigger

**Files:**
- Modify: `includes/class-chip-woocommerce-gateway.php`
  - Modify the L1771-1772 block in `process_payment()` to call the resolver.
  - Modify the L2930 `if ( in_array( 'duitnow_qr', ... ) )` block in `list_razer_ewallets()` to widen the trigger.

**Interfaces:**
- Consumes:
  - `Chip_Woocommerce_Gateway::resolve_duitnow_methods()` — from Task 2.
  - `Chip_Woocommerce_Gateway::DUITNOW_GROUP` — from Task 1.
  - `get_woocommerce_currency()` — WP function (already used elsewhere).
  - `$order->get_total()` — WC_Order method (already used in same file).
- Produces:
  - `$params['payment_method_whitelist']` — populated with the resolved whitelist.
  - `$this->resolved_dnqr_group` — populated as a side effect of the resolver call.
  - `list_razer_ewallets()` — returns the existing 5 Razer e-wallets + DuitNow QR when the whitelist intersects the dnqr group.

**Why this is its own task:** These two sites are the consumer side of the resolver. Wiring them up is independent of the bypass_chip rewrite (Task 3) — the resolver caches its result on the instance, and both sites can read it.

- [ ] **Step 1: Locate the L1771-1772 block in `process_payment()`**

In `includes/class-chip-woocommerce-gateway.php`, find:

```php
		if ( is_array( $this->payment_method_whitelist ) && ! empty( $this->payment_method_whitelist ) ) {
			$params['payment_method_whitelist'] = $this->payment_method_whitelist;
		}
```

Note: this is inside a larger function — find it by searching for `$params['payment_method_whitelist'] = $this->payment_method_whitelist;` (unique in the file).

- [ ] **Step 2: Replace the L1771-1772 block**

Replace the entire `if` block from Step 1 with:

```php
		if ( is_array( $this->payment_method_whitelist ) && ! empty( $this->payment_method_whitelist ) ) {
			$woocommerce_currency                    = get_woocommerce_currency();
			$order_total                             = $order->get_total();
			$amount                                  = (int) round( $order_total * 100 ); // sen
			$params['payment_method_whitelist']      = $this->resolve_duitnow_methods(
				$this->payment_method_whitelist,
				$woocommerce_currency,
				$amount
			);
		}
```

Do NOT touch the L1785 subscription override (`$params['payment_method_whitelist'] = $this->get_payment_method_for_recurring();`). It's intentional and explained in the spec.

- [ ] **Step 3: Verify the resolver is reachable**

Search the file for `resolve_duitnow_methods` to confirm the method exists at this point. It was added in Task 2 and should be in the same file. If you get an undefined-method error after Step 2, Task 2 wasn't completed correctly — go back and verify.

- [ ] **Step 4: Locate the L2930 block in `list_razer_ewallets()`**

In `includes/class-chip-woocommerce-gateway.php`, find:

```php
		if ( in_array( 'duitnow_qr', $this->payment_method_whitelist, true ) ) {
			$ewallet_list['duitnow-qr'] = __( 'Duitnow QR', 'chip-for-woocommerce' );
		}
```

- [ ] **Step 5: Widen the trigger to the dnqr group**

Replace that `if` block with:

```php
		if ( count( array_intersect( $this->payment_method_whitelist, self::DUITNOW_GROUP ) ) > 0 ) {
			$ewallet_list['duitnow-qr'] = __( 'Duitnow QR', 'chip-for-woocommerce' );
		}
```

The frontend key stays `duitnow-qr` and the label stays `'Duitnow QR'`. Customers see one dropdown option; the plugin picks the right API key at the bypass_chip site.

- [ ] **Step 6: PHP syntax check**

```bash
php -l includes/class-chip-woocommerce-gateway.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 7: Run phpcs**

```bash
phpcs --standard=phpcs.xml includes/class-chip-woocommerce-gateway.php
```

Expected: no errors. If line-length errors appear on the resolver call, reformat with intermediate variables (or split the call across lines).

- [ ] **Step 8: Trace a representative flow mentally**

- Whitelist `[duitnow_qr, dnqr]`, merchant has both → resolver returns `[dnqr]`, sets `$this->resolved_dnqr_group = ['dnqr']`. `list_razer_ewallets()` shows "Duitnow QR" entry. `bypass_chip()` either adds `?preferred=dnqr` (Razer branch) or `?preferred=dnqr` (single-method branch) depending on whether the customer picked from the dropdown. ✓
- Whitelist `[razer_grabpay, dnqr]`, merchant has dnqr only → resolver returns `[razer_grabpay, dnqr]`, `$this->resolved_dnqr_group = ['dnqr']`. Dropdown shows Razer e-wallets + "Duitnow QR". If customer picks "Duitnow QR", `?preferred=dnqr&razer_bank_code=duitnow-qr`. If customer picks "GrabPay", `?preferred=razer_grabpay&razer_bank_code=GrabPay`. ✓
- Whitelist `[fpx, mastercard]` → resolver returns `[fpx, mastercard]` (no group expansion), `$this->resolved_dnqr_group = []`. Dropdown doesn't show "Duitnow QR" (intersect is empty). Single-method branch fires only if whitelist is purely the dnqr group — here it isn't, so `get_duitnow_qr_preferred()` returns `''`. No `?preferred=` for these methods (the FPX branch fires earlier based on `$_POST['chip_fpx_bank']`). ✓

- [ ] **Step 9: Commit**

```bash
git add includes/class-chip-woocommerce-gateway.php
git commit -m "feat(dnqr): wire resolver into process_payment and widen e-wallet dropdown

- process_payment() now calls resolve_duitnow_methods() at L1771
  to populate \$params['payment_method_whitelist']. Subscription
  override at L1785 is intentionally untouched.
- list_razer_ewallets() trigger widened from in_array('duitnow_qr')
  to array_intersect(DUITNOW_GROUP), so the dropdown shows
  'Duitnow QR' when either dnqr-group member is configured."
```

---

## Task 5: Gateway 6 preset

**Files:**
- Modify: `includes/class-chip-woocommerce-gateway-6.php` — single-line change at L50.

**Interfaces:**
- Consumes: nothing new.
- Produces: Gateway 6's `payment_method_whitelist` form-field default widens to include `dnqr`.

**Why this is its own task:** Single-line change. Isolating it makes the diff trivial to review.

- [ ] **Step 1: Open Gateway 6**

Open `includes/class-chip-woocommerce-gateway-6.php`. Find L50:

```php
		$this->form_fields['payment_method_whitelist']['default'] = array( 'duitnow_qr' );
```

- [ ] **Step 2: Replace the preset**

Replace the line with:

```php
		$this->form_fields['payment_method_whitelist']['default'] = array( 'duitnow_qr', 'dnqr' );
```

Title (`'Duitnow QR'`), description (`'Pay with Duitnow QR'`), logo default (`'duitnow_only'`), id (`'wc_gateway_chip_6'`), and `PREFERRED_TYPE` constant all stay the same.

- [ ] **Step 3: PHP syntax check**

```bash
php -l includes/class-chip-woocommerce-gateway-6.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 4: Run phpcs**

```bash
phpcs --standard=phpcs.xml includes/class-chip-woocommerce-gateway-6.php
```

Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add includes/class-chip-woocommerce-gateway-6.php
git commit -m "feat(dnqr): widen Gateway 6 preset to include dnqr

Gateway 6 (DuitNow QR-only clone) now defaults to the dnqr group
[duitnow_qr, dnqr] instead of [duitnow_qr]. The runtime resolver
will pick the right concrete method based on the merchant's
/payment_methods/ response. Merchant-facing UI is unchanged."
```

---

## Task 6: Documentation — `changelog.txt` and `readme.txt`

**Files:**
- Modify: `changelog.txt` — append a new line under the in-development section.
- Modify: `readme.txt` — append a new bullet under "Payment methods".

**Why this is its own task:** Documentation changes are independent and can be reviewed against the spec's wording without touching code.

- [ ] **Step 1: Inspect the in-development section in `changelog.txt`**

Open `changelog.txt`. The format used elsewhere is:

```
= X.Y.Z =

* Added - description.
* Tweak - description.
```

Find the topmost `=` version line — that's the in-development section. Note the version number for the next step.

- [ ] **Step 2: Add a new line to the in-development section**

Under the topmost version heading, append a new line:

```
* Added - dnqr payment method support alongside duitnow_qr. Both identifiers are treated as a single "DuitNow QR" group; the plugin picks the right one at runtime based on the merchant's /payment_methods/ response, preferring dnqr.
```

If you don't know the version number, run `./scripts/bump-version.sh X.Y.Z` per `CLAUDE.md` — but only as a separate commit after this one. Don't bundle the bump into the dnqr commit.

- [ ] **Step 3: Inspect `readme.txt`**

Open `readme.txt`. Find the section that lists payment methods (around L20 in the current file: `* **Multiple Payment Methods** - Accept FPX, Credit/Debit Cards, DuitNow QR, E-Wallets, and more`).

- [ ] **Step 4: Update the payment-methods description**

Replace the existing "Multiple Payment Methods" line with:

```
* **Multiple Payment Methods** - Accept FPX, Credit/Debit Cards, DuitNow QR (with automatic dnqr preference for merchants that have it), E-Wallets, and more
```

- [ ] **Step 5: Verify both files have no syntax issues**

`readme.txt` and `changelog.txt` are plain text; no lint required. Read through the additions to confirm they read correctly.

- [ ] **Step 6: Commit**

```bash
git add changelog.txt readme.txt
git commit -m "docs(dnqr): note dnqr support in changelog and readme"
```

---

## Task 7: Manual integration test pass

**Files:**
- No source changes. This is a verification task.

**Why this is its own task:** The repo has no unit tests. The spec's testing strategy is a manual + integration matrix. Walking through it on a real install is the only way to catch behavioral bugs that compile-time checks miss.

- [ ] **Step 1: Build JS assets if they were touched**

`CLAUDE.md` says: "Built JS files are **gitignored** but required for the plugin to function." Since no JS was touched in this plan, this step is informational only. Skip if no JS changes.

- [ ] **Step 2: Set up a staging environment**

Install the plugin on a WordPress + WooCommerce staging site. Configure the CHIP API credentials for a test merchant. Create at least one test merchant account that has both `duitnow_qr` and `dnqr` available, and one that has only `duitnow_qr`.

- [ ] **Step 3: Walk through spec test cases**

Open the spec at `docs/superpowers/specs/2026-06-18-dnqr-migration-design.md` lines 376-390. For each numbered test case:

1. **Gateway 6 default settings, merchant has both** — Click Place Order. Verify redirect URL has `?preferred=dnqr`. Verify CHIP dashboard shows `dnqr` as the payment method.
2. **Gateway 6 default settings, merchant has only `duitnow_qr`** — Verify `?preferred=duitnow_qr` and CHIP shows `duitnow_qr`.
3. **Gateway 6 default settings, `/payment_methods/` API times out** — Simulate by temporarily blocking the API endpoint. Verify `?preferred=dnqr` (fallback).
4. **Gateway with Razer e-wallets including DuitNow QR, merchant has both** — Pick "Duitnow QR" from the Razer dropdown. Verify `?preferred=dnqr&razer_bank_code=duitnow-qr`.
5. **Gateway with Razer e-wallets including DuitNow QR, merchant has only `duitnow_qr`** — Pick "Duitnow QR". Verify `?preferred=duitnow_qr&razer_bank_code=duitnow-qr`.
6. **Gateway with Razer e-wallets, NO DuitNow QR configured** — Verify dropdown does NOT include "Duitnow QR".
7. **Base gateway, whitelist `[duitnow_qr, fpx]`** — Verify no `?preferred=` and API receives `[dnqr, fpx]`.
8. **Base gateway, whitelist `[dnqr]`, merchant has only `duitnow_qr`** — Verify `?preferred=duitnow_qr`.
9. **Base gateway, whitelist `[visa, mastercard]`** — Verify card flow unchanged.
10. **Subscription renewal** — Verify recurring methods unaffected.
11. **Cache hit/miss** — Verify first purchase after settings save calls API; second purchase within 30 min uses cache (check logs for `dnqr resolver:` lines).
12. **Razer dropdown shows "Duitnow QR" when configured** — Verify on any gateway where the whitelist intersects the dnqr group.
13. **Admin UI** — Gateway 6 settings page shows `[duitnow_qr, dnqr]` selected. Base gateway's multiselect shows the new "DuitNow QR (new)" option.

For each case, document the actual result next to the expected result. If any case fails, file a follow-up — do not patch in this task.

- [ ] **Step 4: Run the full phpcs suite**

```bash
phpcs --standard=phpcs.xml .
```

Expected: no errors across the codebase.

- [ ] **Step 5: Final commit if any doc tweaks were made during testing**

If you adjusted the changelog or readme during testing, commit those. Otherwise, the work is done.

```bash
git status
```

Expected: working tree clean.

- [ ] **Step 6: Report completion**

Summarize which test cases passed, which (if any) failed, and what follow-up is needed. The migration is complete when all 13 cases pass.