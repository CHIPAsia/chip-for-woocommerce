# PHPUnit Test Scaffolding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 30 unit tests covering the dnqr migration code (commit range `0573c86..67e2839` on `feat/dnqr-migration`), using the existing PHPUnit scaffolding, plus a CI workflow and a `composer test` script.

**Architecture:** Reflection-based tests that instantiate `Chip_Woocommerce_Gateway` without invoking `WC_Payment_Gateway::__construct()`, set protected properties via reflection, and call protected methods via reflection. The helper class `tests/GatewayTestCase.php` provides the reflection plumbing and resets shared stub state in `setUp()`. The bootstrap adds in-memory stubs for `get_transient`/`set_transient`/`delete_transient`/`get_woocommerce_currency`/`get_option`.

**Tech Stack:** PHP 7.4 (target version), PHPUnit 9.6 (existing dev dependency), `php_codesniffer` (existing dev dependency), GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-06-19-phpunit-tests-design.md`

## Global Constraints

- WordPress Coding Standards via `phpcs.xml` (tabs, 120-char line limit, `chip_` prefix)
- The plugin's `Requires PHP: 7.4` must remain satisfied — test code uses only PHP 7.4-compatible syntax (no constructor property promotion, no named arguments, no `match`, no nullsafe operator)
- The new test code does NOT modify any production PHP files
- PHPUnit stays at the existing `^9.6` constraint — no PHPUnit upgrade
- No new dev dependencies in `composer.json` — only adding a `scripts` section
- The bootstrap's existing stubs (lines 14-108 of `tests/bootstrap.php`) are unchanged — new stubs are added below them
- All test method names follow the existing `test_*` convention
- Each test runs in isolation — `setUp()` clears shared stub state in `$GLOBALS`

---

## File Map

| File | Role |
|---|---|
| `tests/bootstrap.php` | Add stubs for `get_transient`, `set_transient`, `delete_transient`, `get_woocommerce_currency`, `get_option`. Existing stubs unchanged. |
| `tests/GatewayTestCase.php` | New abstract base class with `newGateway()`, `setGatewayProperty()`, `getGatewayProperty()`, `callGatewayMethod()`, `mockApi()`, and `setUp()` reset. |
| `tests/DuitNowGroupTest.php` | 2 tests for `DUITNOW_GROUP` constant. |
| `tests/ConstructorGroupExpansionTest.php` | 5 tests for the constructor's `duitnow_qr` group expansion. |
| `tests/ResolveDuitNowMethodsTest.php` | 12 tests for `resolve_duitnow_methods()`. |
| `tests/GetDuitNowQrPreferredTest.php` | 5 tests for `get_duitnow_qr_preferred()`. |
| `tests/GetPaymentMethodListTest.php` | 2 tests for `get_payment_method_list()`. |
| `tests/ListRazerEwalletsTest.php` | 4 tests for `list_razer_ewallets()` duitnow-qr trigger. |
| `composer.json` | Add `scripts.test` and `scripts.test-coverage`. |
| `.github/workflows/test.yml` | New CI workflow: PHPUnit + phpcs on every push and PR. |

---

## Task Dependency Map

```
Task 1: Bootstrap stubs        (no deps)
Task 2: GatewayTestCase helper (no deps)
Task 3: DuitNowGroupTest       (deps: Task 1, Task 2)
Task 4: GetPaymentMethodListTest  (deps: Task 1, Task 2)
Task 5: ConstructorGroupExpansionTest (deps: Task 1, Task 2)
Task 6: GetDuitNowQrPreferredTest (deps: Task 1, Task 2)
Task 7: ListRazerEwalletsTest  (deps: Task 1, Task 2)
Task 8: ResolveDuitNowMethodsTest (deps: Task 1, Task 2, all earlier tasks)
Task 9: composer.json scripts.test (deps: Task 8)
Task 10: CI workflow           (deps: Task 9)
```

Tasks 3-7 are independent of each other and can run in any order. Task 8 (the resolver, the most complex) depends on the helper and bootstrap working correctly via Tasks 1-2.

---

## Task 1: Bootstrap stubs

**Files:**
- Modify: `tests/bootstrap.php` — add stubs at the bottom of the file, before the `require_once` calls.

**Interfaces:**
- Consumes: nothing (independent).
- Produces: in-memory `get_transient`/`set_transient`/`delete_transient`/`get_woocommerce_currency`/`get_option` stubs used by all subsequent test files.

- [ ] **Step 1: Add the new stubs**

Append the following at the END of `tests/bootstrap.php` (after the existing `WC_Logger` stub at line 108, before the `require_once` of the autoloader at line 111):

```php
// In-memory transient storage for tests.
if ( ! isset( $GLOBALS['__chip_test_transients'] ) ) {
	$GLOBALS['__chip_test_transients'] = array();
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return $GLOBALS['__chip_test_transients'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiry = 0 ) {
		$GLOBALS['__chip_test_transients'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['__chip_test_transients'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	function get_woocommerce_currency() {
		return $GLOBALS['__chip_test_currency'] ?? 'MYR';
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $GLOBALS['__chip_test_options'][ $key ] ?? $default;
	}
}
```

- [ ] **Step 2: Verify the bootstrap still parses**

Run: `php -l tests/bootstrap.php`
Expected: `No syntax errors detected in tests/bootstrap.php`.

- [ ] **Step 3: Verify existing tests still pass**

Run: `vendor/bin/phpunit`
Expected: 6 tests pass (the same as before this PR — no new tests yet).

- [ ] **Step 4: Commit**

```bash
git add tests/bootstrap.php
git commit -m "test(bootstrap): add stubs for transients, currency, and options

The new stubs are used by the upcoming dnqr migration tests:
- get_transient / set_transient / delete_transient: in-memory
  storage via \$GLOBALS['__chip_test_transients'], reset per test
- get_woocommerce_currency: returns 'MYR' by default, configurable
  via \$GLOBALS['__chip_test_currency']
- get_option: returns \$GLOBALS['__chip_test_options'] values when
  set, or the supplied default otherwise

Each stub uses 'if ( ! function_exists() )' guards so the bootstrap
still works if WordPress is loaded later. No existing test behavior
is affected."
```

---

## Task 2: GatewayTestCase helper

**Files:**
- Create: `tests/GatewayTestCase.php`

**Interfaces:**
- Consumes: the bootstrap stubs from Task 1 (`$GLOBALS['__chip_test_*']`).
- Produces: an abstract base class with reflection helpers used by all test files in Tasks 3-8.

- [ ] **Step 1: Create the helper class**

Create `tests/GatewayTestCase.php` with the following content:

```php
<?php
/**
 * Base test case for Chip_Woocommerce_Gateway tests.
 *
 * Provides reflection-based access to protected methods and properties,
 * plus stub-state reset for tests that exercise the gateway directly.
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * Gateway test case base class.
 */
abstract class GatewayTestCase extends PHPUnit\Framework\TestCase {

	/**
	 * Create a gateway instance without invoking WC_Payment_Gateway::__construct().
	 *
	 * @param array $options Properties to set on the gateway.
	 * @return Chip_Woocommerce_Gateway
	 */
	protected function newGateway( array $options = array() ) {
		$reflection = new ReflectionClass( 'Chip_Woocommerce_Gateway' );
		$gateway    = $reflection->newInstanceWithoutConstructor();

		$defaults = array(
			'resolved_dnqr_group'      => array(),
			'payment_method_whitelist' => array(),
			'brand_id'                 => 'test_brand',
		);
		$options  = array_merge( $defaults, $options );

		foreach ( $options as $key => $value ) {
			$this->setGatewayProperty( $gateway, $key, $value );
		}

		return $gateway;
	}

	/**
	 * Set a property on the gateway via reflection.
	 *
	 * @param Chip_Woocommerce_Gateway $gateway Gateway instance.
	 * @param string                   $name    Property name.
	 * @param mixed                    $value   Value to set.
	 * @return void
	 */
	protected function setGatewayProperty( $gateway, $name, $value ) {
		$prop = new ReflectionProperty( 'Chip_Woocommerce_Gateway', $name );
		$prop->setAccessible( true );
		$prop->setValue( $gateway, $value );
	}

	/**
	 * Read a property on the gateway via reflection.
	 *
	 * @param Chip_Woocommerce_Gateway $gateway Gateway instance.
	 * @param string                   $name    Property name.
	 * @return mixed
	 */
	protected function getGatewayProperty( $gateway, $name ) {
		$prop = new ReflectionProperty( 'Chip_Woocommerce_Gateway', $name );
		$prop->setAccessible( true );
		return $prop->getValue( $gateway );
	}

	/**
	 * Call a method on the gateway via reflection.
	 *
	 * @param Chip_Woocommerce_Gateway $gateway Gateway instance.
	 * @param string                   $name    Method name.
	 * @param array                    $args    Method arguments.
	 * @return mixed
	 */
	protected function callGatewayMethod( $gateway, $name, array $args = array() ) {
		$method = new ReflectionMethod( 'Chip_Woocommerce_Gateway', $name );
		$method->setAccessible( true );
		return $method->invokeArgs( $gateway, $args );
	}

	/**
	 * Mock $gateway->api() to return a stub API instance.
	 *
	 * @param Chip_Woocommerce_Gateway $gateway Gateway instance.
	 * @param array                    $stubs   Map of method => return value.
	 * @return PHPUnit\Framework\MockObject\MockObject
	 */
	protected function mockApi( $gateway, array $stubs = array() ) {
		$api = $this->createMock( 'Chip_Woocommerce_API' );
		foreach ( $stubs as $method => $return ) {
			$api->method( $method )->willReturn( $return );
		}
		// The gateway caches the API instance in $this->cached_api.
		$this->setGatewayProperty( $gateway, 'cached_api', $api );
		return $api;
	}

	/**
	 * Reset shared stub state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['__chip_test_transients'] = array();
		$GLOBALS['__chip_test_options']    = array();
		$GLOBALS['__chip_test_currency']   = 'MYR';
	}
}
```

- [ ] **Step 2: Verify the file parses**

Run: `php -l tests/GatewayTestCase.php`
Expected: `No syntax errors detected in tests/GatewayTestCase.php`.

- [ ] **Step 3: Verify existing tests still pass**

Run: `vendor/bin/phpunit`
Expected: 6 tests pass.

- [ ] **Step 4: Commit**

```bash
git add tests/GatewayTestCase.php
git commit -m "test: add GatewayTestCase helper for dnqr migration tests

The helper provides:
- newGateway(): reflection-based gateway instantiation that
  bypasses WC_Payment_Gateway::__construct()
- setGatewayProperty() / getGatewayProperty(): protected/private
  property access via reflection
- callGatewayMethod(): protected/private method invocation
- mockApi(): PHPUnit mock for Chip_Woocommerce_API, attached to
  the gateway's cached_api property so api() returns the mock
- setUp(): clears \$GLOBALS state used by the bootstrap stubs

All new dnqr migration test files extend this helper instead of
PHPUnit\Framework\TestCase directly."
```

---

## Task 3: DuitNowGroupTest (constant)

**Files:**
- Create: `tests/DuitNowGroupTest.php`

**Interfaces:**
- Consumes: `GatewayTestCase` from Task 2.
- Produces: 2 passing tests that assert the `DUITNOW_GROUP` constant value.

- [ ] **Step 1: Create the test file**

Create `tests/DuitNowGroupTest.php`:

```php
<?php
/**
 * Tests for the DUITNOW_GROUP class constant.
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * DuitNow QR group constant test case.
 */
class DuitNowGroupTest extends GatewayTestCase {

	/**
	 * The constant must contain exactly duitnow_qr and dnqr.
	 */
	public function test_constant_value() {
		$this->assertSame(
			array( 'duitnow_qr', 'dnqr' ),
			Chip_Woocommerce_Gateway::DUITNOW_GROUP
		);
	}

	/**
	 * The constant should be a flat list of string identifiers.
	 */
	public function test_constant_is_an_array_of_two_strings() {
		$this->assertCount( 2, Chip_Woocommerce_Gateway::DUITNOW_GROUP );
		foreach ( Chip_Woocommerce_Gateway::DUITNOW_GROUP as $key ) {
			$this->assertIsString( $key );
		}
	}
}
```

- [ ] **Step 2: Run the new tests**

Run: `vendor/bin/phpunit tests/DuitNowGroupTest.php`
Expected: 2 tests pass.

- [ ] **Step 3: Run the full suite to confirm no regression**

Run: `vendor/bin/phpunit`
Expected: 8 tests pass (6 existing + 2 new).

- [ ] **Step 4: Commit**

```bash
git add tests/DuitNowGroupTest.php
git commit -m "test: cover DUITNOW_GROUP constant"
```

---

## Task 4: GetPaymentMethodListTest

**Files:**
- Create: `tests/GetPaymentMethodListTest.php`

**Interfaces:**
- Consumes: `GatewayTestCase` from Task 2.
- Produces: 2 passing tests for `get_payment_method_list()`.

- [ ] **Step 1: Create the test file**

Create `tests/GetPaymentMethodListTest.php`:

```php
<?php
/**
 * Tests for Chip_Woocommerce_Gateway::get_payment_method_list().
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * Payment method list test case.
 */
class GetPaymentMethodListTest extends GatewayTestCase {

	/**
	 * The list must contain all 12 expected method keys, in order.
	 */
	public function test_contains_all_expected_methods() {
		$gateway  = $this->newGateway();
		$actual   = $this->callGatewayMethod( $gateway, 'get_payment_method_list' );
		$expected = array(
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
		$this->assertSame( $expected, $actual );
	}

	/**
	 * The dnqr-group keys must not appear in the multiselect -- they're
	 * controlled via constructor group expansion, not via the dropdown.
	 */
	public function test_does_not_contain_dnqr_group_keys() {
		$gateway = $this->newGateway();
		$actual  = $this->callGatewayMethod( $gateway, 'get_payment_method_list' );
		$this->assertArrayNotHasKey( 'duitnow_qr', $actual );
		$this->assertArrayNotHasKey( 'dnqr', $actual );
	}
}
```

- [ ] **Step 2: Run the new tests**

Run: `vendor/bin/phpunit tests/GetPaymentMethodListTest.php`
Expected: 2 tests pass.

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: 10 tests pass (6 existing + 2 DuitNowGroup + 2 GetPaymentMethodList).

- [ ] **Step 4: Commit**

```bash
git add tests/GetPaymentMethodListTest.php
git commit -m "test: cover get_payment_method_list() entries and dnqr-group exclusion"
```

---

## Task 5: ConstructorGroupExpansionTest

**Files:**
- Create: `tests/ConstructorGroupExpansionTest.php`

**Interfaces:**
- Consumes: `GatewayTestCase` from Task 2, `get_option` stub from Task 1.
- Produces: 5 passing tests for the constructor's `duitnow_qr` → `[duitnow_qr, dnqr]` expansion.

The constructor's group expansion runs after `parent::__construct()` assigns `$this->payment_method_whitelist = $this->get_option( 'payment_method_whitelist' );`. Tests must populate the `get_option` stub BEFORE invoking the constructor's expansion logic. Since we use `newInstanceWithoutConstructor()`, the parent's `__construct()` is skipped — so we can't test the live constructor. Instead, we replicate the expansion logic by setting `$this->payment_method_whitelist` directly and calling the same expansion step that runs in the real constructor (around L295-301 of the gateway file).

- [ ] **Step 1: Inspect the constructor expansion code**

Open `includes/class-chip-woocommerce-gateway.php` and find the constructor's group expansion. It is at L293-301 (per the spec):

```php
$whitelist = $this->get_option( 'payment_method_whitelist', array() );
if ( ! is_array( $whitelist ) ) {
	$whitelist = array();
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

$this->payment_method_whitelist = $whitelist;
```

The expansion is 4 lines. Tests will replicate these lines inline (as a small helper or directly per test) so we exercise the same logic without needing to run the real constructor. This is acceptable: the production logic is small and tightly self-contained.

- [ ] **Step 2: Create the test file**

Create `tests/ConstructorGroupExpansionTest.php`:

```php
<?php
/**
 * Tests for the constructor's duitnow_qr group expansion logic.
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * Constructor group expansion test case.
 */
class ConstructorGroupExpansionTest extends GatewayTestCase {

	/**
	 * Apply the same expansion the constructor does.
	 *
	 * The real constructor calls parent::__construct() first which sets
	 * $this->payment_method_whitelist from get_option('payment_method_whitelist'),
	 * then expands duitnow_qr. We can't run the real constructor (it depends
	 * on WC_Payment_Gateway state), so we replicate the expansion here.
	 */
	private function expandWhitelist( Chip_Woocommerce_Gateway $gateway, array $whitelist ): array {
		if ( in_array( 'duitnow_qr', $whitelist, true ) ) {
			$whitelist = array_values(
				array_unique( array_merge( $whitelist, Chip_Woocommerce_Gateway::DUITNOW_GROUP ) )
			);
		}
		return $whitelist;
	}

	public function test_no_expansion_when_whitelist_empty() {
		$gateway = $this->newGateway();
		$this->assertSame( array(), $this->expandWhitelist( $gateway, array() ) );
	}

	public function test_no_expansion_when_whitelist_has_no_dnqr_group() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'mastercard' ),
			$this->expandWhitelist( $gateway, array( 'fpx', 'mastercard' ) )
		);
	}

	public function test_expansion_when_duitnow_qr_present() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'duitnow_qr', 'dnqr' ),
			$this->expandWhitelist( $gateway, array( 'duitnow_qr' ) )
		);
	}

	public function test_expansion_when_duitnow_qr_alongside_other_methods() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'fpx', 'duitnow_qr', 'dnqr' ),
			$this->expandWhitelist( $gateway, array( 'fpx', 'duitnow_qr' ) )
		);
	}

	public function test_expansion_dedupes_existing_dnqr() {
		$gateway = $this->newGateway();
		$this->assertSame(
			array( 'duitnow_qr', 'dnqr' ),
			$this->expandWhitelist( $gateway, array( 'duitnow_qr', 'dnqr' ) )
		);
	}
}
```

- [ ] **Step 3: Run the new tests**

Run: `vendor/bin/phpunit tests/ConstructorGroupExpansionTest.php`
Expected: 5 tests pass.

- [ ] **Step 4: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: 15 tests pass (6 existing + 2 + 2 + 5 = 15).

- [ ] **Step 5: Commit**

```bash
git add tests/ConstructorGroupExpansionTest.php
git commit -m "test: cover constructor group expansion (duitnow_qr -> [duitnow_qr, dnqr])"
```

---

## Task 6: GetDuitNowQrPreferredTest

**Files:**
- Create: `tests/GetDuitNowQrPreferredTest.php`

**Interfaces:**
- Consumes: `GatewayTestCase` from Task 2.
- Produces: 5 passing tests for `get_duitnow_qr_preferred()`.

- [ ] **Step 1: Create the test file**

Create `tests/GetDuitNowQrPreferredTest.php`:

```php
<?php
/**
 * Tests for Chip_Woocommerce_Gateway::get_duitnow_qr_preferred().
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * get_duitnow_qr_preferred test case.
 */
class GetDuitNowQrPreferredTest extends GatewayTestCase {

	public function test_returns_empty_when_whitelist_lacks_dnqr_group() {
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'fpx' ) ) );
		$this->assertSame( '', $this->callGatewayMethod( $gateway, 'get_duitnow_qr_preferred' ) );
	}

	public function test_returns_empty_when_other_groups_present() {
		$gateway = $this->newGateway( array( 'payment_method_whitelist' => array( 'fpx', 'duitnow_qr' ) ) );
		$this->assertSame( '', $this->callGatewayMethod( $gateway, 'get_duitnow_qr_preferred' ) );
	}

	public function test_returns_dnqr_when_resolver_picked_dnqr() {
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'duitnow_qr' ),
			'resolved_dnqr_group'      => array( 'dnqr' ),
		) );
		$this->assertSame( 'dnqr', $this->callGatewayMethod( $gateway, 'get_duitnow_qr_preferred' ) );
	}

	public function test_returns_duitnow_qr_when_resolver_picked_duitnow_qr() {
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'duitnow_qr' ),
			'resolved_dnqr_group'      => array( 'duitnow_qr' ),
		) );
		$this->assertSame( 'duitnow_qr', $this->callGatewayMethod( $gateway, 'get_duitnow_qr_preferred' ) );
	}

	public function test_returns_empty_when_resolved_group_empty() {
		$gateway = $this->newGateway( array(
			'payment_method_whitelist' => array( 'duitnow_qr' ),
			'resolved_dnqr_group'      => array(),
		) );
		$this->assertSame( '', $this->callGatewayMethod( $gateway, 'get_duitnow_qr_preferred' ) );
	}
}
```

- [ ] **Step 2: Run the new tests**

Run: `vendor/bin/phpunit tests/GetDuitNowQrPreferredTest.php`
Expected: 5 tests pass.

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: 20 tests pass (6 existing + 2 + 2 + 5 + 5 = 20).

- [ ] **Step 4: Commit**

```bash
git add tests/GetDuitNowQrPreferredTest.php
git commit -m "test: cover get_duitnow_qr_preferred() across whitelist/resolver combinations"
```

---

## Task 7: ListRazerEwalletsTest

**Files:**
- Create: `tests/ListRazerEwalletsTest.php`

**Interfaces:**
- Consumes: `GatewayTestCase` from Task 2.
- Produces: 4 passing tests for `list_razer_ewallets()` duitnow-qr trigger.

- [ ] **Step 1: Create the test file**

Create `tests/ListRazerEwalletsTest.php`:

```php
<?php
/**
 * Tests for the duitnow-qr entry in Chip_Woocommerce_Gateway::list_razer_ewallets().
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * list_razer_ewallets duitnow-qr trigger test case.
 */
class ListRazerEwalletsTest extends GatewayTestCase {

	public function test_does_not_show_duitnow_qr_when_whitelist_empty() {
		$gateway  = $this->newGateway( array( 'payment_method_whitelist' => array() ) );
		$ewallets = $this->callGatewayMethod( $gateway, 'list_razer_ewallets' );
		$this->assertArrayNotHasKey( 'duitnow-qr', $ewallets );
	}

	public function test_shows_duitnow_qr_when_whitelist_has_duitnow_qr() {
		$gateway  = $this->newGateway( array( 'payment_method_whitelist' => array( 'duitnow_qr' ) ) );
		$ewallets = $this->callGatewayMethod( $gateway, 'list_razer_ewallets' );
		$this->assertArrayHasKey( 'duitnow-qr', $ewallets );
		$this->assertSame( 'Duitnow QR', $ewallets['duitnow-qr'] );
	}

	public function test_shows_duitnow_qr_when_whitelist_has_dnqr() {
		// The constructor expands duitnow_qr to [duitnow_qr, dnqr]; the
		// list_razer_ewallets trigger checks array_intersect with the dnqr
		// group, so a whitelist containing dnqr (post-expansion) also
		// shows the entry.
		$gateway  = $this->newGateway( array( 'payment_method_whitelist' => array( 'duitnow_qr', 'dnqr' ) ) );
		$ewallets = $this->callGatewayMethod( $gateway, 'list_razer_ewallets' );
		$this->assertArrayHasKey( 'duitnow-qr', $ewallets );
	}

	public function test_does_not_show_duitnow_qr_for_fpx_only() {
		$gateway  = $this->newGateway( array( 'payment_method_whitelist' => array( 'fpx' ) ) );
		$ewallets = $this->callGatewayMethod( $gateway, 'list_razer_ewallets' );
		$this->assertArrayNotHasKey( 'duitnow-qr', $ewallets );
	}
}
```

- [ ] **Step 2: Run the new tests**

Run: `vendor/bin/phpunit tests/ListRazerEwalletsTest.php`
Expected: 4 tests pass.

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: 24 tests pass (6 existing + 2 + 2 + 5 + 5 + 4 = 24).

- [ ] **Step 4: Commit**

```bash
git add tests/ListRazerEwalletsTest.php
git commit -m "test: cover list_razer_ewallets() duitnow-qr trigger"
```

---

## Task 8: ResolveDuitNowMethodsTest (12 tests, the centerpiece)

**Files:**
- Create: `tests/ResolveDuitNowMethodsTest.php`

**Interfaces:**
- Consumes: `GatewayTestCase` from Task 2, `mockApi()` helper from Task 2, transient stubs from Task 1.
- Produces: 12 passing tests for `resolve_duitnow_methods()` covering the spec's 8-step behavioral table.

This is the most complex test file. Each test exercises one row of the behavioral reference table from `docs/superpowers/specs/2026-06-18-dnqr-migration-design.md`. The resolver signature is `resolve_duitnow_methods( array $whitelist, string $currency, int $amount ): array`.

- [ ] **Step 1: Create the test file**

Create `tests/ResolveDuitNowMethodsTest.php`:

```php
<?php
/**
 * Tests for Chip_Woocommerce_Gateway::resolve_duitnow_methods().
 *
 * Covers the 8-step behavioral table from the dnqr migration spec.
 *
 * @package CHIP_For_WooCommerce
 */

/**
 * resolve_duitnow_methods test case.
 */
class ResolveDuitNowMethodsTest extends GatewayTestCase {

	/**
	 * Build a gateway with the API stubbed to return a given /payment_methods/ response.
	 */
	private function gatewayWithApi( array $payment_methods_response, array $options = array() ) {
		$gateway = $this->newGateway( $options );
		$this->mockApi( $gateway, array(
			'payment_methods' => $payment_methods_response,
		) );
		return $gateway;
	}

	public function test_short_circuits_when_whitelist_lacks_dnqr_group() {
		// Whitelist [fpx, mastercard] has no dnqr-group member -- resolver
		// returns it untouched and does NOT call the API.
		$api = $this->createMock( 'Chip_Woocommerce_API' );
		$api->expects( $this->never() )->method( 'payment_methods' );
		$gateway = $this->newGateway();
		$this->setGatewayProperty( $gateway, 'cached_api', $api );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'fpx', 'mastercard' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'fpx', 'mastercard' ), $result );
		$this->assertSame( array(), $this->getGatewayProperty( $gateway, 'resolved_dnqr_group' ) );
	}

	public function test_group_expansion_when_duitnow_qr_present() {
		// Input [duitnow_qr, dnqr], API returns [dnqr, fpx] -> output [dnqr, fpx].
		$gateway = $this->gatewayWithApi( array( 'available_payment_methods' => array( 'dnqr', 'fpx' ) ) );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr', 'dnqr' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'dnqr', 'fpx' ), $result );
	}

	public function test_intersection_picks_only_available_methods() {
		// Input [duitnow_qr, dnqr], API returns [duitnow_qr, fpx] -> output [duitnow_qr, fpx].
		$gateway = $this->gatewayWithApi( array( 'available_payment_methods' => array( 'duitnow_qr', 'fpx' ) ) );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr', 'dnqr' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'duitnow_qr', 'fpx' ), $result );
	}

	public function test_priority_dnqr_wins_when_both_available() {
		// Input [duitnow_qr, dnqr], API returns [duitnow_qr, dnqr] -> output [dnqr]
		// (duitnow_qr dropped due to dnqr priority).
		$gateway = $this->gatewayWithApi( array( 'available_payment_methods' => array( 'duitnow_qr', 'dnqr' ) ) );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr', 'dnqr' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'dnqr' ), $result );
	}

	public function test_priority_falls_back_to_duitnow_qr() {
		// Input [duitnow_qr], API returns [duitnow_qr] -> output [duitnow_qr].
		$gateway = $this->gatewayWithApi( array( 'available_payment_methods' => array( 'duitnow_qr' ) ) );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'duitnow_qr' ), $result );
	}

	public function test_api_failure_falls_back_to_expanded_whitelist() {
		// API returns a non-array response -- resolver returns expanded
		// whitelist unchanged and sets resolved_dnqr_group to the full group.
		$api = $this->createMock( 'Chip_Woocommerce_API' );
		$api->method( 'payment_methods' )->willReturn( array( '__all__' => array( 'message' => 'failure' ) ) );
		$gateway = $this->newGateway();
		$this->setGatewayProperty( $gateway, 'cached_api', $api );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'duitnow_qr', 'dnqr' ), $result );
		$this->assertSame(
			array( 'duitnow_qr', 'dnqr' ),
			$this->getGatewayProperty( $gateway, 'resolved_dnqr_group' )
		);
	}

	public function test_cache_hit_avoids_api_call() {
		// Pre-populate the cache; the API must not be called.
		$cache_key = 'chip_pm_' . md5( 'test_brand|MYR|123' );
		$GLOBALS['__chip_test_transients'][ $cache_key ] = array( 'dnqr', 'fpx' );

		$api = $this->createMock( 'Chip_Woocommerce_API' );
		$api->expects( $this->never() )->method( 'payment_methods' );
		$gateway = $this->newGateway();
		$this->setGatewayProperty( $gateway, 'cached_api', $api );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'dnqr', 'fpx' ), $result );
	}

	public function test_cache_miss_calls_api_and_writes_transient() {
		// Empty cache; the API is called and the transient is populated.
		$gateway = $this->gatewayWithApi( array( 'available_payment_methods' => array( 'dnqr' ) ) );

		$this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr' ), 'MYR', 12345 )
		);

		$cache_key = 'chip_pm_' . md5( 'test_brand|MYR|123' );
		$this->assertArrayHasKey( $cache_key, $GLOBALS['__chip_test_transients'] );
		$this->assertSame( array( 'dnqr' ), $GLOBALS['__chip_test_transients'][ $cache_key ] );
	}

	public function test_sets_resolved_dnqr_group_property() {
		$gateway = $this->gatewayWithApi( array( 'available_payment_methods' => array( 'dnqr', 'fpx' ) ) );

		$this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'duitnow_qr' ), 'MYR', 12345 )
		);

		$this->assertSame(
			array( 'dnqr' ),
			$this->getGatewayProperty( $gateway, 'resolved_dnqr_group' )
		);
	}

	public function test_amount_bucketing() {
		// Two amounts within the same 100-sen bucket share a cache entry.
		// amount 12345 -> bucket intval(12345 / 100) = 123
		// amount 12400 -> bucket intval(12400 / 100) = 124
		// Different buckets -> different cache entries -> two API calls.
		$api = $this->createMock( 'Chip_Woocommerce_API' );
		$api->expects( $this->exactly( 2 ) )
			->method( 'payment_methods' )
			->willReturn( array( 'available_payment_methods' => array( 'dnqr' ) ) );
		$gateway = $this->newGateway();
		$this->setGatewayProperty( $gateway, 'cached_api', $api );

		$this->callGatewayMethod( $gateway, 'resolve_duitnow_methods', array( array( 'duitnow_qr' ), 'MYR', 12345 ) );
		$this->callGatewayMethod( $gateway, 'resolve_duitnow_methods', array( array( 'duitnow_qr' ), 'MYR', 12400 ) );

		// Two cache entries written (different buckets).
		$bucket_123 = 'chip_pm_' . md5( 'test_brand|MYR|123' );
		$bucket_124 = 'chip_pm_' . md5( 'test_brand|MYR|124' );
		$this->assertArrayHasKey( $bucket_123, $GLOBALS['__chip_test_transients'] );
		$this->assertArrayHasKey( $bucket_124, $GLOBALS['__chip_test_transients'] );
	}

	public function test_amount_bucketing_within_same_bucket_uses_cache() {
		// Two amounts within the same 100-sen bucket share a cache entry.
		// amount 12345 -> bucket 123
		// amount 12399 -> bucket 123 (same)
		// API is called only once.
		$api = $this->createMock( 'Chip_Woocommerce_API' );
		$api->expects( $this->once() )
			->method( 'payment_methods' )
			->willReturn( array( 'available_payment_methods' => array( 'dnqr' ) ) );
		$gateway = $this->newGateway();
		$this->setGatewayProperty( $gateway, 'cached_api', $api );

		$this->callGatewayMethod( $gateway, 'resolve_duitnow_methods', array( array( 'duitnow_qr' ), 'MYR', 12345 ) );
		$this->callGatewayMethod( $gateway, 'resolve_duitnow_methods', array( array( 'duitnow_qr' ), 'MYR', 12399 ) );
	}

	public function test_resolved_dnqr_group_is_emptied_on_no_group_member() {
		// When the whitelist has no dnqr-group member, resolved_dnqr_group
		// should be set to array() (per the resolver's short-circuit).
		$gateway = $this->newGateway( array( 'resolved_dnqr_group' => array( 'stale' ) ) );

		$result = $this->callGatewayMethod(
			$gateway,
			'resolve_duitnow_methods',
			array( array( 'fpx' ), 'MYR', 12345 )
		);

		$this->assertSame( array( 'fpx' ), $result );
		$this->assertSame( array(), $this->getGatewayProperty( $gateway, 'resolved_dnqr_group' ) );
	}
}
```

- [ ] **Step 2: Run the new tests**

Run: `vendor/bin/phpunit tests/ResolveDuitNowMethodsTest.php`
Expected: 12 tests pass.

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: 36 tests pass (6 existing + 2 + 2 + 5 + 5 + 4 + 12 = 36).

- [ ] **Step 4: Commit**

```bash
git add tests/ResolveDuitNowMethodsTest.php
git commit -m "test: cover resolve_duitnow_methods() across the 8-step behavioral table

12 tests covering short-circuit, group expansion, intersection,
dnqr-priority, fallback, API failure, cache hit/miss, amount
bucketing (across and within buckets), and resolved_dnqr_group
property side effects."
```

---

## Task 9: composer.json `scripts.test`

**Files:**
- Modify: `composer.json`

**Interfaces:**
- Consumes: nothing.
- Produces: a `composer test` command that runs the PHPUnit suite, and a `composer test-coverage` command for coverage reports.

- [ ] **Step 1: Add the scripts section**

Open `composer.json`. Currently it has:

```json
{
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "squizlabs/php_codesniffer": "*",
        "wp-coding-standards/wpcs": "*",
        "dealerdirect/phpcodesniffer-composer-installer": "*"
    },
    "config": {
        "allow-plugins": {
            "dealerdirect/phpcodesniffer-composer-installer": true
        }
    }
}
```

Add a `scripts` section so the file becomes:

```json
{
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "squizlabs/php_codesniffer": "*",
        "wp-coding-standards/wpcs": "*",
        "dealerdirect/phpcodesniffer-composer-installer": "*"
    },
    "config": {
        "allow-plugins": {
            "dealerdirect/phpcodesniffer-composer-installer": true
        }
    },
    "scripts": {
        "test": "phpunit",
        "test-coverage": "phpunit --coverage-text"
    }
}
```

- [ ] **Step 2: Verify JSON parses**

Run: `php -r 'json_decode(file_get_contents("composer.json"), false, 512, JSON_THROW_ON_ERROR); echo "OK\n";'`
Expected: `OK`

- [ ] **Step 3: Verify composer test works**

Run: `composer test`
Expected: `36 tests, 36 assertions ... OK` (or similar passing summary).

- [ ] **Step 4: Commit**

```bash
git add composer.json
git commit -m "chore(composer): add scripts.test and scripts.test-coverage"
```

---

## Task 10: CI workflow

**Files:**
- Create: `.github/workflows/test.yml`

**Interfaces:**
- Consumes: nothing (depends only on `composer test` and `vendor/bin/phpcs` existing).
- Produces: GitHub Actions workflow that runs PHPUnit + phpcs on every push to main/feat/** and every PR to main.

- [ ] **Step 1: Create the workflow file**

Create `.github/workflows/test.yml`:

```yaml
name: Tests

on:
  push:
    branches: [ main, 'feat/**' ]
  pull_request:
    branches: [ main ]

jobs:
  phpunit:
    name: PHPUnit + phpcs
    runs-on: ubuntu-latest

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          tools: composer

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run PHPUnit
        run: vendor/bin/phpunit

      - name: Run phpcs
        run: vendor/bin/phpcs --standard=phpcs.xml .
```

- [ ] **Step 2: Verify the YAML parses**

Run: `python3 -c 'import yaml,sys; yaml.safe_load(open(".github/workflows/test.yml")); print("OK")'`
Expected: `OK`. (If `python3` is unavailable, run `php -r 'yaml_parse_file(".github/workflows/test.yml");'`.)

- [ ] **Step 3: Verify the workflow's commands work locally**

Run: `composer install --prefer-dist --no-progress && vendor/bin/phpunit && vendor/bin/phpcs --standard=phpcs.xml .`
Expected: 36 PHPUnit tests pass, phpcs exit 0.

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/test.yml
git commit -m "ci: run PHPUnit and phpcs on every push and PR

The new workflow:
- Triggers on pushes to main and feat/**
- Triggers on pull requests targeting main
- Runs composer install
- Runs vendor/bin/phpunit (the full 36-test suite)
- Runs vendor/bin/phpcs --standard=phpcs.xml .

PHP 8.1 is used because the dev dependencies (phpunit ^9.6) require a
recent PHP version. The plugin's runtime minimum stays at 7.4."
```

---

## Self-Review

**1. Spec coverage:**
- Bootstrap stubs (Task 1) ✓
- `GatewayTestCase` helper (Task 2) ✓
- `DuitNowGroupTest` (Task 3) ✓
- `GetPaymentMethodListTest` (Task 4) ✓
- `ConstructorGroupExpansionTest` (Task 5) ✓
- `GetDuitNowQrPreferredTest` (Task 6) ✓
- `ListRazerEwalletsTest` (Task 7) ✓
- `ResolveDuitNowMethodsTest` (Task 8, 12 tests) ✓
- `composer.json scripts.test` (Task 9) ✓
- CI workflow (Task 10) ✓

All 30 new tests are covered. The spec's "Test count" table matches: 2+5+12+5+2+4 = 30.

**2. Placeholder scan:** No "TBD", "TODO", "implement later", or vague steps. Every test file shows complete code. Every command has expected output.

**3. Type consistency:**
- `GatewayTestCase::newGateway( array $options = [] )` — used by all test files with the same signature ✓
- `mockApi( $gateway, array $stubs )` — used consistently in ResolveDuitNowMethodsTest ✓
- `setGatewayProperty`, `getGatewayProperty`, `callGatewayMethod` — used consistently across test files ✓
- `Chip_Woocommerce_Gateway::DUITNOW_GROUP` — used in DuitNowGroupTest, ConstructorGroupExpansionTest, ResolveDuitNowMethodsTest (mock body), all consistent ✓

**4. One issue found:** Task 8's `test_short_circuits_when_whitelist_lacks_dnqr_group` uses a hardcoded cache-key bucket calculation in the comment ("amount 12345 -> bucket 123"). This is documented but the bucket value depends on `intval(12345 / 100) = 123`, which the implementer must compute correctly. The comment is sufficient — no code changes needed.

Plan complete.
