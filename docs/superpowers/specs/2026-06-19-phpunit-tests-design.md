# PHPUnit test scaffolding for the dnqr migration — Design

**Date:** 2026-06-19
**Status:** Draft, pending user review

## Background

The dnqr migration landed in version 2.0.6 with thirteen commits adding the `DUITNOW_GROUP` constant, the `resolve_duitnow_methods()` resolver, the constructor's group expansion, the `get_duitnow_qr_preferred()` helper, the `bypass_chip()` rewrite, the `list_razer_ewallets()` trigger widening, and Gateway 6's preset. None of this new code is currently covered by automated tests — the existing `tests/APITest.php` and `tests/PluginTest.php` only cover the API class instantiation and plugin-smoke assertions.

Before the next planned work item (the unified-dropdown redesign of `bypass_chip()`), we need a regression net so the dnqr migration's behavior is locked down.

## Goals

- Add unit tests for every new piece of behavior introduced in the 2.0.6 dnqr migration.
- Use the existing PHPUnit scaffolding (the framework, `phpunit.xml.dist`, and `tests/bootstrap.php` are already in place) — no new dev dependencies.
- Run the tests in CI on every push and PR via a new `.github/workflows/test.yml`.
- Provide a `composer test` script for local development.

## Non-goals

- Testing `bypass_chip()`'s `?preferred=` URL branches — overlaps with the planned unified-dropdown redesign; would create throwaway tests.
- Testing `process_payment()` end-to-end — best done in a staging environment, not in unit tests.
- Adding JS tests (Jest) — separate effort.
- Testing code unchanged by the dnqr migration — only the new code is in scope.
- Coverage of the 14-case manual test matrix from the dnqr spec — that matrix is for a real WordPress + WooCommerce environment.

## Glossary

| Term | Meaning |
|---|---|
| **Reflection-based test** | A test that uses PHP's `ReflectionClass` to instantiate the gateway without invoking `WC_Payment_Gateway::__construct()`, and to set or call protected/private members. |
| **Helper class** | `tests/GatewayTestCase.php` — abstract base class extending `PHPUnit\Framework\TestCase` that provides the reflection helpers and stub reset logic. |
| **Test stub** | A function defined in `tests/bootstrap.php` that mimics a WordPress function (e.g. `get_transient`) without requiring a real WordPress installation. |

## Architecture overview

The PHPUnit framework is already wired up. The work is purely additive:

1. **`tests/GatewayTestCase.php`** — abstract base test case providing reflection-based helpers and stub-state reset. Each test file extends this rather than `PHPUnit\Framework\TestCase` directly.
2. **Six new test files** under `tests/`, one per logical unit of dnqr migration code.
3. **Bootstrap additions** — new stubs for `get_transient`, `set_transient`, `delete_transient`, `get_woocommerce_currency`, `get_option`. These are added to the existing `tests/bootstrap.php` without disturbing the existing stubs.
4. **CI workflow** — a new `.github/workflows/test.yml` that runs `composer install` then `vendor/bin/phpunit` plus `vendor/bin/phpcs` on every push and PR.
5. **Composer script** — `composer test` runs the suite locally.

The reflection-based approach is the right fit because:

- It mirrors the existing test style (`APITest.php` and `PluginTest.php` both use real classes with minimal mocking).
- The dnqr methods are protected; reflection lets the tests call them without exposing them on a test-only subclass.
- The constructor (`__construct()`) calls `parent::__construct()` indirectly via WP state; bypassing it with `newInstanceWithoutConstructor()` keeps the tests self-contained.

## Component design

### 1. `tests/bootstrap.php` additions

The existing bootstrap stubs `add_action`, `add_filter`, `is_admin`, `WC_Payment_Gateway`, and `WC_Logger`. The dnqr tests need these additional stubs:

```php
// In-memory transient storage.
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

The `$GLOBALS['__chip_test_*']` arrays are reset in `GatewayTestCase::setUp()` so tests start from a clean slate.

### 2. `tests/GatewayTestCase.php`

Abstract base class providing reflection helpers and stub-state reset:

```php
<?php
/**
 * Base test case for gateway-related tests.
 */
abstract class GatewayTestCase extends PHPUnit\Framework\TestCase {

    protected function newGateway( array $options = array() ): Chip_Woocommerce_Gateway {
        $gateway = ( new ReflectionClass( Chip_Woocommerce_Gateway::class ) )
            ->newInstanceWithoutConstructor();

        $defaults = array(
            'resolved_dnqr_group'      => array(),
            'payment_method_whitelist' => array(),
            'brand_id'                 => 'test_brand',
        );
        $options = array_merge( $defaults, $options );

        foreach ( $options as $key => $value ) {
            $this->setGatewayProperty( $gateway, $key, $value );
        }

        return $gateway;
    }

    protected function setGatewayProperty( $gateway, string $name, $value ): void {
        $prop = ( new ReflectionClass( Chip_Woocommerce_Gateway::class ) )
            ->getProperty( $name );
        $prop->setAccessible( true );
        $prop->setValue( $gateway, $value );
    }

    protected function getGatewayProperty( $gateway, string $name ) {
        $prop = ( new ReflectionClass( Chip_Woocommerce_Gateway::class ) )
            ->getProperty( $name );
        $prop->setAccessible( true );
        return $prop->getValue( $gateway );
    }

    protected function callGatewayMethod( $gateway, string $name, array $args = array() ) {
        $method = ( new ReflectionClass( Chip_Woocommerce_Gateway::class ) )
            ->getMethod( $name );
        $method->setAccessible( true );
        return $method->invokeArgs( $gateway, $args );
    }

    protected function mockApi( $gateway, array $stubs = array() ) {
        $api = $this->createMock( 'Chip_Woocommerce_API' );
        foreach ( $stubs as $method => $return ) {
            $api->method( $method )->willReturn( $return );
        }
        // Stub $gateway->api() to return this mock by setting the cached property
        // if one exists. Implementation will verify the exact property name; if the
        // gateway does not cache the API instance, fall back to overriding api()
        // via reflection.
        $this->setGatewayProperty( $gateway, 'cached_api', $api );
        return $api;
    }

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['__chip_test_transients'] = array();
        $GLOBALS['__chip_test_options']    = array();
        $GLOBALS['__chip_test_currency']   = 'MYR';
    }
}
```

The `mockApi` helper is the one place where the implementation may need adjustment if `Chip_Woocommerce_Gateway` does not cache the API instance. The implementation phase will inspect `Chip_Woocommerce_Gateway::api()` and adapt the helper accordingly.

### 3. Test files

**`tests/DuitNowGroupTest.php`** (2 tests):

```php
class DuitNowGroupTest extends GatewayTestCase {

    public function test_constant_value() {
        $this->assertSame(
            array( 'duitnow_qr', 'dnqr' ),
            Chip_Woocommerce_Gateway::DUITNOW_GROUP
        );
    }

    public function test_constant_is_an_array_of_two_strings() {
        $this->assertCount( 2, Chip_Woocommerce_Gateway::DUITNOW_GROUP );
        foreach ( Chip_Woocommerce_Gateway::DUITNOW_GROUP as $key ) {
            $this->assertIsString( $key );
        }
    }
}
```

**`tests/ConstructorGroupExpansionTest.php`** (5 tests): the constructor's group expansion is tested by re-invoking the constructor on a fresh gateway via reflection and asserting on the resulting `payment_method_whitelist`. Since the constructor calls `parent::__construct()` indirectly (via `WC_Payment_Gateway::__construct()`), the test invokes the post-property assignments directly, not the full constructor. The behavioral coverage:

- Empty whitelist stays empty.
- `[fpx, mastercard]` stays unchanged (no dnqr group expansion).
- `[duitnow_qr]` becomes `[duitnow_qr, dnqr]`.
- `[fpx, duitnow_qr]` becomes `[fpx, duitnow_qr, dnqr]` (dnqr appended).
- `[duitnow_qr, dnqr]` stays `[duitnow_qr, dnqr]` (no duplicates).

**`tests/ResolveDuitNowMethodsTest.php`** (12 tests): the resolver's 8-step behavioral table from the dnqr spec, including:

- Short-circuit when whitelist lacks dnqr group.
- Group expansion when `duitnow_qr` is present.
- Intersection picks only available methods.
- Priority: dnqr wins when both available.
- Priority: fall back to duitnow_qr when only it is available.
- API failure falls back to expanded whitelist and sets `$resolved_dnqr_group` to full group.
- Cache hit avoids API call.
- Cache miss calls API and writes transient.
- Sets `$resolved_dnqr_group` property correctly.
- Amount-bucketing: two calls within the same 100-sen bucket share a cache entry.
- Resolver returns the whitelist with the resolved group substituted.

**`tests/GetDuitNowQrPreferredTest.php`** (5 tests):

- Returns `''` when whitelist lacks dnqr group.
- Returns `''` when other groups are present in the whitelist.
- Returns `'dnqr'` when resolver picked dnqr.
- Returns `'duitnow_qr'` when resolver picked duitnow_qr.
- Returns `'duitnow_qr'` (the first element of `DUITNOW_GROUP`) when resolved group is empty -- a defensive default that mirrors the production code's `! empty( $resolved ) ? $resolved[0] : ''` logic at `includes/class-chip-woocommerce-gateway.php:3668-3671`. The helper is unreachable in practice because the resolver always populates `$resolved_dnqr_group` when the dnqr group is enabled, but the fallback keeps `bypass_chip()` safe if called outside the normal `process_payment()` flow.

**`tests/GetPaymentMethodListTest.php`** (2 tests):

- Contains all 13 expected method keys (the 12 listed below plus `duitnow_qr` as the user-selectable multiselect key for the dnqr group). The dnqr-group selection is represented by the `duitnow_qr` key only -- `dnqr` is never a multiselect option; it is injected at runtime via the constructor's group expansion.
- Does NOT contain `'duitnow_qr'` or `'dnqr'` (the dnqr group is controlled via constructor expansion, not via the multiselect).

**`tests/ListRazerEwalletsTest.php`** (4 tests):

- Does not show `'duitnow-qr'` entry when whitelist is empty.
- Shows `'duitnow-qr'` entry when whitelist contains `'duitnow_qr'`.
- Shows `'duitnow-qr'` entry when whitelist contains `'dnqr'` (test sets the post-expansion whitelist directly).
- Does not show `'duitnow-qr'` entry when whitelist is `[fpx]` only.

### 4. `.github/workflows/test.yml`

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

PHP 8.1 is used because the dev dependencies (`phpunit/phpunit ^9.6`) require a recent PHP version. The plugin's runtime minimum stays at 7.4.

### 5. `composer.json` addition

Add a `scripts` section:

```json
"scripts": {
    "test": "phpunit",
    "test-coverage": "phpunit --coverage-text"
}
```

## Data flow

A typical test runs through this flow:

1. `setUp()` clears `$GLOBALS['__chip_test_transients']`, `$GLOBALS['__chip_test_options']`, and sets `$GLOBALS['__chip_test_currency']` to `'MYR'`.
2. The test calls `$this->newGateway( $options )` to instantiate the gateway via reflection with the desired initial state.
3. If the test exercises the resolver, it calls `$this->mockApi( $gateway, $stubs )` to stub `$gateway->api()` with the desired return values for `payment_methods()`.
4. The test invokes `resolve_duitnow_methods()` (or another method) via `$this->callGatewayMethod()`.
5. Assertions verify the return value and the side effects (e.g. `$this->getGatewayProperty( $gateway, 'resolved_dnqr_group' )`).
6. `tearDown()` (inherited from PHPUnit) runs; no shared state to clean beyond the `setUp()` reset.

## Error handling

- **Reflection failures**: if a protected method or property is renamed in a future PR, the test fails with a clear `ReflectionException` message naming the missing member. The helper's error message is already informative.
- **Stub conflicts**: if a WordPress function gets a real implementation (e.g. loaded via a different bootstrap), the bootstrap's `if ( ! function_exists() )` guards skip our stubs. No double-definition error.
- **Cache pollution between tests**: the `setUp()` reset clears `$GLOBALS['__chip_test_transients']`. Tests that need to pre-populate the cache set it explicitly after `setUp()` runs.

## File map

### Added

| File | Purpose |
|---|---|
| `tests/GatewayTestCase.php` | Base test case with reflection helpers and stub reset |
| `tests/DuitNowGroupTest.php` | Tests `DUITNOW_GROUP` constant |
| `tests/ConstructorGroupExpansionTest.php` | Tests the constructor's `duitnow_qr` group expansion |
| `tests/ResolveDuitNowMethodsTest.php` | Tests `resolve_duitnow_methods()` (12 tests) |
| `tests/GetDuitNowQrPreferredTest.php` | Tests `get_duitnow_qr_preferred()` (5 tests) |
| `tests/GetPaymentMethodListTest.php` | Tests `get_payment_method_list()` (2 tests) |
| `tests/ListRazerEwalletsTest.php` | Tests `list_razer_ewallets()` duitnow-qr trigger (4 tests) |
| `.github/workflows/test.yml` | CI workflow: PHPUnit + phpcs |

### Modified

| File | Change |
|---|---|
| `tests/bootstrap.php` | Add stubs for `get_transient`, `set_transient`, `delete_transient`, `get_woocommerce_currency`, `get_option` |
| `composer.json` | Add `scripts.test` |

### Test count

| File | Tests |
|---|---|
| `DuitNowGroupTest` | 2 |
| `ConstructorGroupExpansionTest` | 5 |
| `ResolveDuitNowMethodsTest` | 12 |
| `GetDuitNowQrPreferredTest` | 5 |
| `GetPaymentMethodListTest` | 2 |
| `ListRazerEwalletsTest` | 4 |
| **Total new tests** | **30** |
| Existing tests (unchanged) | 6 |
| **Total after this PR** | **36** |

## Risks and open questions

- **Reflection cost**: `ReflectionClass::getProperty()` per test is cheap (microseconds). If we add hundreds more tests, we may want to cache reflection handles in the helper. Defer until measured.
- **`api()` mockability**: the `mockApi` helper assumes a cached property. The implementation phase will inspect `Chip_Woocommerce_Gateway::api()` and adapt. If the gateway recreates the API instance on every call, the mock will not stick and we will need a different approach.
- **`get_option` stub**: the existing tests do not call `get_option` heavily. The stub is cheap insurance; if it turns out to be unused, we can remove it.
- **PHP 8.0+ syntax in test code**: tests must use only PHP 7.4-compatible syntax. No constructor property promotion, no named arguments, no match expressions. The implementation will verify this.
- **PHPDoc / class-level coverage**: out of scope. Only the new code from the dnqr migration is covered.

## Testing strategy

The PHPUnit tests are themselves the test artifact. To verify the design:

1. `composer install` succeeds.
2. `vendor/bin/phpunit` runs all 36 tests, all green.
3. `vendor/bin/phpcs --standard=phpcs.xml .` is clean.
4. Manual check: the CI workflow runs on a test PR and reports green.
5. Manual check: a deliberate breaking change to `resolve_duitnow_methods()` (e.g. swapping the priority order) makes one of the 12 resolver tests fail.
