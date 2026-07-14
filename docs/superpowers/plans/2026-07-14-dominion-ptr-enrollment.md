# Dominion PTR Enrollment Form Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Dominion Peak Time Rebates enrollment form to FormFlow Lite that runs on the marketing site, validates and enrolls through Dominion's IntelliSource JSON API, and fires a first-party conversion where GTM and the click-id cookie already work.

**Architecture:** A new sibling connector (`dominion-ptr`) implements the existing `ApiConnectorInterface` and speaks Dominion's JSON API (`prospect/validate`, `portal_user_emails`, `prospect/enroll`), leaving the in-production XML connector (`intellisource`, serving Energy Wise) untouched. A new `dominion_ptr` instance on the existing `enrollment` form type selects the connector via `settings['connector']` and reuses the multi-step form engine and the existing `dataLayer` / `peanut_conversion` tracking. Built in two stages: Stage 1 (validate half, testable now against the live unauthenticated same-origin API, enroll stubbed) then Stage 2 (real enroll + portal handoff, gated on Itron).

**Tech Stack:** PHP (WordPress plugin), PHPUnit (`composer test`), `wp_remote_request` for HTTP, `json_decode` for Dominion responses.

## Global Constraints

- Target PHP 7.4+; follow the `FFFL\` namespace and existing file conventions.
- All outbound HTTP must pass `\FFFL\Api\ApiClient::is_safe_outbound_url($url)` (SSRF guard) before the call. Dominion's host `www.dominionenergyptr.com` is public and passes.
- **Never enroll the designated test account** (`210010506231` / `23116` / the test email). Validation (`prospect/validate`, `portal_user_emails`) is read-only and safe; the enroll write path must be exercised only via the mock in Stage 1.
- Do not modify the existing `intellisource` (XML) connector or its presets — it serves live Energy Wise programs.
- Dominion API base: `https://www.dominionenergyptr.com/ptr/residential/api` (same-origin JSON, GET, no `pswd`).
- Live validate response shape (verified 2026-07-14): `{"status":"found","data":{"prospect_id":<int>,"first_name":"...","last_name":"...","name":"...","email":"...","utility_no":"...","enrollable_premises":[{"id":<int>,"address":"...","zip":"..."}]}}`. A non-eligible account returns a non-`found` `status`.
- `portal_user_emails?email=` returns `{"available":<bool>,"has_login_history":<bool>}`.

---

## Stage 1 — Validate half (no Itron dependency)

### Task 1: `dominion-ptr` connector skeleton and registration

**Files:**
- Create: `connectors/dominion-ptr/loader.php`
- Create: `connectors/dominion-ptr/class-dominion-ptr-connector.php`
- Modify: `includes/class-plugin.php` (add the `require_once` + registration hook for the new connector, matching how `connectors/intellisource/loader.php` is wired)
- Test: `tests/Unit/DominionPtrConnectorRegistrationTest.php`

**Interfaces:**
- Consumes: `\FFFL\Api\ApiConnectorInterface`, `\FFFL\Api\ConnectorRegistry`, `\FFFL\Api\AccountValidationResult`, `\FFFL\Api\EnrollmentResult` (all defined in `includes/api/interface-api-connector.php`).
- Produces: a registered connector with `get_id() === 'dominion-ptr'`; `get_presets()` returns a `dominion_ptr` key; `get_supported_features() === ['enrollment']`.

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;
use FFFL\Api\ConnectorRegistry;

class DominionPtrConnectorRegistrationTest extends TestCase
{
    public function testConnectorIsRegisteredWithExpectedId(): void
    {
        $registry = ConnectorRegistry::instance();
        $registry->init_connectors();
        $connector = $registry->get('dominion-ptr');

        $this->assertNotNull($connector, 'dominion-ptr connector should be registered');
        $this->assertSame('dominion-ptr', $connector->get_id());
        $this->assertSame(['enrollment'], $connector->get_supported_features());
    }

    public function testExposesDominionPtrPreset(): void
    {
        $connector = ConnectorRegistry::instance()->get('dominion-ptr');
        $presets = $connector->get_presets();

        $this->assertArrayHasKey('dominion_ptr', $presets);
        $this->assertSame(
            'https://www.dominionenergyptr.com/ptr/residential/api',
            $presets['dominion_ptr']['api_endpoint']
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter DominionPtrConnectorRegistrationTest`
Expected: FAIL (connector not registered / class not found).

- [ ] **Step 3: Create the connector class**

Create `connectors/dominion-ptr/class-dominion-ptr-connector.php`:

```php
<?php
namespace FFFL\Connectors\DominionPtr;

use FFFL\Api\ApiConnectorInterface;
use FFFL\Api\AccountValidationResult;
use FFFL\Api\EnrollmentResult;
use FFFL\Api\SchedulingResult;
use FFFL\Api\BookingResult;
use FFFL\Api\ApiClient;

if (!defined('ABSPATH')) { exit; }

/**
 * Dominion Peak Time Rebates connector.
 *
 * Speaks Dominion's IntelliSource JSON API (prospect/validate,
 * portal_user_emails, prospect/enroll) under /ptr/residential/api. Distinct
 * from the XML `intellisource` connector, which serves the Energy Wise
 * programs and must not be touched.
 */
class DominionPtrConnector implements ApiConnectorInterface {

    public function get_id(): string { return 'dominion-ptr'; }
    public function get_name(): string { return __('Dominion Peak Time Rebates', 'formflow-lite'); }
    public function get_description(): string {
        return __('Dominion PTR enrollment via the IntelliSource JSON API.', 'formflow-lite');
    }
    public function get_version(): string { return '1.0.0'; }

    public function get_config_fields(): array {
        return [
            'api_endpoint' => [
                'label' => __('API Endpoint', 'formflow-lite'),
                'type' => 'url',
                'required' => true,
                'description' => __('Base URL for the Dominion PTR JSON API (…/ptr/residential/api)', 'formflow-lite'),
                'default' => 'https://www.dominionenergyptr.com/ptr/residential/api',
            ],
            'test_mode' => [
                'label' => __('Test Mode', 'formflow-lite'),
                'type' => 'checkbox',
                'required' => false,
                'description' => __('Stub enrollment (validation stays live).', 'formflow-lite'),
                'default' => false,
            ],
        ];
    }

    public function validate_config(array $config): array {
        $errors = [];
        if (empty($config['api_endpoint']) || !filter_var($config['api_endpoint'], FILTER_VALIDATE_URL)) {
            $errors[] = __('A valid API Endpoint is required', 'formflow-lite');
        }
        return $errors;
    }

    public function test_connection(array $config): array {
        // cep_configurations is a lightweight unauthenticated GET.
        try {
            $this->http_get_json(rtrim($config['api_endpoint'], '/') . '/cep_configurations');
            return ['success' => true, 'message' => __('Connection successful', 'formflow-lite')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function validate_account(array $data, array $config): AccountValidationResult {
        // Implemented in Task 2.
        return new AccountValidationResult(['is_valid' => false, 'error_code' => 'not_implemented']);
    }

    public function submit_enrollment(array $form_data, array $config): EnrollmentResult {
        // Implemented in Task 5 (mock) / Task 7 (live).
        return new EnrollmentResult(['success' => false, 'error_code' => 'not_implemented']);
    }

    public function get_schedule_slots(array $data, array $config): SchedulingResult {
        return new SchedulingResult(['success' => false, 'error_code' => 'unsupported']);
    }

    public function book_appointment(array $data, array $config): BookingResult {
        return new BookingResult(['success' => false, 'error_code' => 'unsupported']);
    }

    public function map_fields(array $form_data, string $type = 'enrollment'): array {
        return [
            'utility_no' => trim($form_data['account_number'] ?? $form_data['utility_no'] ?? ''),
            'zip' => preg_replace('/\D/', '', $form_data['zip'] ?? ''),
            'email' => trim($form_data['email'] ?? ''),
        ];
    }

    public function get_supported_features(): array { return ['enrollment']; }
    public function supports(string $feature): bool { return in_array($feature, $this->get_supported_features(), true); }

    public function get_presets(): array {
        return [
            'dominion_ptr' => [
                'name' => __('Dominion Energy — Peak Time Rebates', 'formflow-lite'),
                'short_name' => 'Dominion PTR',
                'state' => 'VA',
                'api_endpoint' => 'https://www.dominionenergyptr.com/ptr/residential/api',
                'program_name' => 'Peak Time Rebates',
                'program_url' => 'https://www.dominionenergyptr.com',
                'connector' => 'dominion-ptr',
                'disable_device' => true,
                'disable_scheduling' => true,
                'branding' => ['primary_color' => '#0072ce', 'logo_url' => ''],
            ],
        ];
    }

    /**
     * GET a JSON endpoint. Protected so tests can override with a fixture.
     *
     * @return array Decoded JSON as an associative array.
     * @throws \Exception on transport error, non-2xx, or invalid JSON.
     */
    protected function http_get_json(string $url, array $query = []): array {
        if ($query) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }
        if (!ApiClient::is_safe_outbound_url($url)) {
            throw new \Exception(__('Blocked request to a non-public or unsafe URL', 'formflow-lite'));
        }
        $response = wp_remote_request($url, ['method' => 'GET', 'timeout' => 30, 'sslverify' => true, 'reject_unsafe_urls' => true]);
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($status >= 400) {
            throw new \Exception("HTTP {$status}");
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \Exception(__('Invalid JSON response', 'formflow-lite'));
        }
        return $decoded;
    }
}
```

- [ ] **Step 4: Create the loader**

Create `connectors/dominion-ptr/loader.php`:

```php
<?php
namespace FFFL\Connectors\DominionPtr;

if (!defined('ABSPATH')) { exit; }

define('FFFL_DOMINION_PTR_PATH', __DIR__);

function load_connector(): void {
    require_once FFFL_DOMINION_PTR_PATH . '/class-dominion-ptr-connector.php';
}

function register_connector($registry): void {
    load_connector();
    $registry->register(new DominionPtrConnector());
}

add_action('fffl_register_connectors', __NAMESPACE__ . '\\register_connector');
```

- [ ] **Step 5: Wire the loader into the plugin**

In `includes/class-plugin.php`, find the line that requires `connectors/intellisource/loader.php` and add directly after it:

```php
require_once FORMFLOW_LITE_PATH . 'connectors/dominion-ptr/loader.php';
```

(Match the exact constant/path style used for the intellisource `require_once` in that file.)

- [ ] **Step 6: Run test to verify it passes**

Run: `composer test -- --filter DominionPtrConnectorRegistrationTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add connectors/dominion-ptr/ includes/class-plugin.php tests/Unit/DominionPtrConnectorRegistrationTest.php
git commit -m "feat(ptr): register dominion-ptr JSON connector skeleton"
```

---

### Task 2: `validate_account()` against the live JSON API

**Files:**
- Modify: `connectors/dominion-ptr/class-dominion-ptr-connector.php` (implement `validate_account`, add a private `parse_validate` helper)
- Test: `tests/Unit/DominionPtrValidateTest.php`

**Interfaces:**
- Consumes: `http_get_json()` from Task 1; `AccountValidationResult`.
- Produces: `validate_account(array $data, array $config): AccountValidationResult` where on eligible account `is_valid === true` and `customer_data` contains keys `prospect_id`, `first_name`, `last_name`, `name`, `email`, `utility_no`, `enrollable_premises` (array of `['id','address','zip']`), `portal_available` (bool), `has_login_history` (bool).

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;
use FFFL\Connectors\DominionPtr\DominionPtrConnector;

class DominionPtrValidateTest extends TestCase
{
    private function connectorReturning(array $byPath): DominionPtrConnector
    {
        // Anonymous subclass injects fixtures keyed by the last path segment.
        return new class($byPath) extends DominionPtrConnector {
            private array $byPath;
            public function __construct(array $byPath) { $this->byPath = $byPath; }
            protected function http_get_json(string $url, array $query = []): array {
                foreach ($this->byPath as $needle => $resp) {
                    if (strpos($url, $needle) !== false) { return $resp; }
                }
                throw new \Exception("no fixture for {$url}");
            }
        };
    }

    public function testEligibleAccountReturnsValidWithPremises(): void
    {
        $c = $this->connectorReturning([
            'prospect/validate' => ['status' => 'found', 'data' => [
                'prospect_id' => 728, 'first_name' => 'ASHOK', 'last_name' => 'RAMASUBBU',
                'name' => 'ASHOK RAMASUBBU', 'email' => 'X@GMAIL.COM', 'utility_no' => '210010506231',
                'enrollable_premises' => [['id' => 728, 'address' => '9593 SYCAMORE GROVE WAY, MECHANICSVILLE, VA 23116', 'zip' => '23116']],
            ]],
            'portal_user_emails' => ['available' => false, 'has_login_history' => false],
        ]);

        $r = $c->validate_account(
            ['account_number' => '210010506231', 'zip' => '23116', 'email' => 'x@gmail.com'],
            ['api_endpoint' => 'https://www.dominionenergyptr.com/ptr/residential/api']
        );

        $this->assertTrue($r->is_valid());
        $this->assertSame(728, $r->get_customer_data()['prospect_id']);
        $this->assertCount(1, $r->get_customer_data()['enrollable_premises']);
        $this->assertFalse($r->get_customer_data()['portal_available']);
    }

    public function testIneligibleAccountReturnsInvalid(): void
    {
        $c = $this->connectorReturning([
            'prospect/validate' => ['status' => 'not_found', 'data' => null],
            'portal_user_emails' => ['available' => true, 'has_login_history' => false],
        ]);

        $r = $c->validate_account(
            ['account_number' => '000000000000', 'zip' => '00000', 'email' => 'x@gmail.com'],
            ['api_endpoint' => 'https://www.dominionenergyptr.com/ptr/residential/api']
        );

        $this->assertFalse($r->is_valid());
        $this->assertSame('not_found', $r->get_error_code());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter DominionPtrValidateTest`
Expected: FAIL (`validate_account` returns `not_implemented`).

- [ ] **Step 3: Implement `validate_account`**

Replace the stub `validate_account` in the connector with:

```php
    public function validate_account(array $data, array $config): AccountValidationResult {
        $base = rtrim($config['api_endpoint'], '/');
        $mapped = $this->map_fields($data);

        try {
            $validate = $this->http_get_json($base . '/prospect/validate', [
                'email' => $mapped['email'],
                'zip' => $mapped['zip'],
                'utility_no' => $mapped['utility_no'],
            ]);
        } catch (\Exception $e) {
            return new AccountValidationResult(['is_valid' => false, 'error_code' => 'connection_error', 'error_message' => $e->getMessage()]);
        }

        $status = $validate['status'] ?? 'error';
        if ($status !== 'found' || empty($validate['data'])) {
            return new AccountValidationResult(['is_valid' => false, 'error_code' => (string) $status, 'raw_response' => $validate]);
        }

        // Existing-account check (non-fatal if it fails).
        $portal = ['available' => null, 'has_login_history' => null];
        try {
            $portal = $this->http_get_json($base . '/portal_user_emails', ['email' => $mapped['email']]);
        } catch (\Exception $e) { /* leave nulls */ }

        $d = $validate['data'];
        return new AccountValidationResult([
            'is_valid' => true,
            'customer_data' => [
                'prospect_id' => $d['prospect_id'] ?? null,
                'first_name' => $d['first_name'] ?? '',
                'last_name' => $d['last_name'] ?? '',
                'name' => $d['name'] ?? '',
                'email' => $d['email'] ?? $mapped['email'],
                'utility_no' => $d['utility_no'] ?? $mapped['utility_no'],
                'enrollable_premises' => $d['enrollable_premises'] ?? [],
                'portal_available' => $portal['available'] ?? null,
                'has_login_history' => $portal['has_login_history'] ?? null,
            ],
            'raw_response' => $validate,
        ]);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter DominionPtrValidateTest`
Expected: PASS.

- [ ] **Step 5: Add a guarded live integration test**

Append to `tests/Unit/DominionPtrValidateTest.php`:

```php
    public function testLiveValidateReadOnly(): void
    {
        if (getenv('FFFL_LIVE_TESTS') !== '1') {
            $this->markTestSkipped('Set FFFL_LIVE_TESTS=1 to hit the live read-only validate endpoint.');
        }
        $c = new DominionPtrConnector();
        $r = $c->validate_account(
            ['account_number' => '210010506231', 'zip' => '23116', 'email' => 'Rspectesting123@gmail.com'],
            ['api_endpoint' => 'https://www.dominionenergyptr.com/ptr/residential/api']
        );
        // Read-only. NEVER call submit_enrollment with this account.
        $this->assertTrue($r->is_valid());
        $this->assertNotEmpty($r->get_customer_data()['enrollable_premises']);
    }
```

- [ ] **Step 6: Commit**

```bash
git add connectors/dominion-ptr/class-dominion-ptr-connector.php tests/Unit/DominionPtrValidateTest.php
git commit -m "feat(ptr): validate_account against Dominion JSON API (+guarded live test)"
```

---

### Task 3: Seed the `dominion_ptr` instance and resolve the connector

**Files:**
- Create: `connectors/dominion-ptr/class-dominion-ptr-seeder.php` (a `create_instance()` helper that inserts the instance row from the preset)
- Modify: `connectors/dominion-ptr/loader.php` (require the seeder)
- Test: `tests/Unit/DominionPtrInstanceTest.php`

**Interfaces:**
- Consumes: the instances table (via the plugin's `Database`/instance repository — mirror how `class-activator.php` / the admin creates an instance), `DominionPtrConnector::get_presets()`.
- Produces: `\FFFL\Connectors\DominionPtr\Seeder::create_instance(): int` returning the new instance id; the created row has `settings['connector'] === 'dominion-ptr'`, `form_type === 'enrollment'`, and the preset's `api_endpoint`.

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;
use FFFL\Connectors\DominionPtr\Seeder;

class DominionPtrInstanceTest extends TestCase
{
    public function testCreatesEnrollmentInstanceBoundToConnector(): void
    {
        $id = Seeder::create_instance();
        $this->assertIsInt($id);

        $instance = $this->getInstance($id); // TestCase helper reading the instances table
        $this->assertSame('enrollment', $instance['form_type']);
        $settings = json_decode($instance['settings'], true);
        $this->assertSame('dominion-ptr', $settings['connector']);
        $this->assertTrue($settings['disable_device']);
        $this->assertStringContainsString('/ptr/residential/api', $instance['api_endpoint']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter DominionPtrInstanceTest`
Expected: FAIL (`Seeder` class not found; add `getInstance()` helper to `tests/TestCase.php` if not present, reading the instances table the same way other tests read fixtures).

- [ ] **Step 3: Implement the seeder**

Create `connectors/dominion-ptr/class-dominion-ptr-seeder.php`:

```php
<?php
namespace FFFL\Connectors\DominionPtr;

use FFFL\Database\Database;

if (!defined('ABSPATH')) { exit; }

class Seeder {
    public static function create_instance(): int {
        $preset = (new DominionPtrConnector())->get_presets()['dominion_ptr'];
        $db = Database::instance();

        $settings = [
            'connector' => 'dominion-ptr',
            'disable_device' => true,
            'disable_scheduling' => true,
            'branding' => $preset['branding'],
            'program' => ['name' => $preset['program_name'], 'url' => $preset['program_url']],
        ];

        // Mirror the insert the admin instance editor performs. Use the same
        // Database method the admin uses to create an instance; do not write
        // raw SQL if a repository method exists.
        return $db->create_instance([
            'name' => $preset['name'],
            'slug' => 'dominion-ptr',
            'utility' => 'dominion',
            'form_type' => 'enrollment',
            'api_endpoint' => $preset['api_endpoint'],
            'settings' => wp_json_encode($settings),
            'is_active' => 1,
            'test_mode' => 1, // Stage 1: enroll stubbed.
        ]);
    }
}
```

(During implementation, confirm the exact `Database` create method name by reading `includes/class-database.php` / the admin instance-create path, and match its signature. If instances are created only via the admin UI, add a thin `create_instance(array $row): int` to the Database class and use it here and there.)

- [ ] **Step 4: Require the seeder in the loader**

In `connectors/dominion-ptr/loader.php`, inside `load_connector()`, add:

```php
    require_once FFFL_DOMINION_PTR_PATH . '/class-dominion-ptr-seeder.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter DominionPtrInstanceTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add connectors/dominion-ptr/ tests/Unit/DominionPtrInstanceTest.php tests/TestCase.php
git commit -m "feat(ptr): seed dominion_ptr enrollment instance bound to the connector"
```

---

### Task 4: Render the PTR multi-step flow (Approach A / B decision point)

**Files:**
- Modify: `includes/class-embed-handler.php` and/or the form engine that builds the enrollment step set (identify the exact file during implementation — start from `get_connector_for_instance` / the embed config route at `includes/class-embed-handler.php:262` `'endpoints'`)
- Test: `tests/Unit/DominionPtrFlowStepsTest.php`

**Interfaces:**
- Consumes: the `dominion_ptr` instance (Task 3), its `settings['disable_device']` / `settings['disable_scheduling']`.
- Produces: the enrollment flow for this instance renders the steps `fields → validate → address confirmation → terms` and omits device-selection and scheduling steps.

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;
use FFFL\Connectors\DominionPtr\Seeder;

class DominionPtrFlowStepsTest extends TestCase
{
    public function testFlowOmitsDeviceAndSchedulingSteps(): void
    {
        $id = Seeder::create_instance();
        $steps = $this->getEnrollmentSteps($id); // helper: returns the step keys the engine will render

        $this->assertContains('validate', $steps);
        $this->assertContains('address_confirm', $steps);
        $this->assertContains('terms', $steps);
        $this->assertNotContains('device', $steps);
        $this->assertNotContains('scheduling', $steps);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter DominionPtrFlowStepsTest`
Expected: FAIL.

- [ ] **Step 3: Make the step set honor the disable flags**

Read the enrollment step-definition source (the code that produces the ordered step list for an `enrollment` instance). Add gating so `settings['disable_device']` removes the device step and `settings['disable_scheduling']` removes the scheduling/booking steps, and ensure an `address_confirm` step is present for the JSON `enrollable_premises` payload. Implement the minimal change that makes the test pass.

**Approach A/B gate:** if the step set is hard-coded to Energy Wise's device flow and cannot be reduced with settings without significant change, STOP and escalate: this is the trigger to switch to Approach B (a purpose-built PTR form type). Record the finding in `known-gaps.md` and raise it before proceeding.

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter DominionPtrFlowStepsTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/ tests/Unit/DominionPtrFlowStepsTest.php
git commit -m "feat(ptr): render reduced PTR enrollment flow (no device/scheduling)"
```

---

### Task 5: Stub `submit_enrollment()` via test mode for an end-to-end demo

**Files:**
- Modify: `connectors/dominion-ptr/class-dominion-ptr-connector.php` (implement `submit_enrollment` test-mode branch)
- Test: `tests/Unit/DominionPtrEnrollStubTest.php`

**Interfaces:**
- Consumes: `$config['test_mode']`, `map_fields()`.
- Produces: in test mode, `submit_enrollment()` returns `EnrollmentResult` with `success === true`, a `confirmation_number` prefixed `PTR-DEMO-`, and `data` containing `account_id` and `set_password_token` (so Task 8's handoff has something to consume).

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;
use FFFL\Connectors\DominionPtr\DominionPtrConnector;

class DominionPtrEnrollStubTest extends TestCase
{
    public function testTestModeEnrollReturnsStubbedSuccess(): void
    {
        $c = new DominionPtrConnector();
        $r = $c->submit_enrollment(
            ['account_number' => '210010506231', 'zip' => '23116', 'email' => 'x@gmail.com'],
            ['api_endpoint' => 'https://www.dominionenergyptr.com/ptr/residential/api', 'test_mode' => true]
        );

        $this->assertTrue($r->is_successful());
        $this->assertStringStartsWith('PTR-DEMO-', $r->get_confirmation_number());
        $this->assertArrayHasKey('set_password_token', $r->toArray()['data']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter DominionPtrEnrollStubTest`
Expected: FAIL (returns `not_implemented`).

- [ ] **Step 3: Implement the test-mode branch**

Replace the stub `submit_enrollment` with:

```php
    public function submit_enrollment(array $form_data, array $config): EnrollmentResult {
        if (!empty($config['test_mode'])) {
            $mapped = $this->map_fields($form_data);
            return new EnrollmentResult([
                'success' => true,
                'confirmation_number' => 'PTR-DEMO-' . substr(md5($mapped['utility_no'] . $mapped['email']), 0, 8),
                'data' => [
                    'account_id' => 0,
                    'set_password_token' => 'demo-token',
                ],
            ]);
        }
        // Live path implemented in Task 7 (gated on Itron).
        return new EnrollmentResult(['success' => false, 'error_code' => 'not_implemented']);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter DominionPtrEnrollStubTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add connectors/dominion-ptr/class-dominion-ptr-connector.php tests/Unit/DominionPtrEnrollStubTest.php
git commit -m "feat(ptr): stub submit_enrollment in test mode for end-to-end demo"
```

---

### Task 6: Confirm the dataLayer conversion event for the PTR instance

**Files:**
- Modify: instance `settings` (analytics block) so the form's analytics integration enables GTM/dataLayer for this instance — read `public/assets/js/analytics-integration.js` (`gtmEnabled`, `gtmContainerId`, the `pushEvent('conversion', …)` call) and `public/assets/js/enrollment.js` (`pushToDataLayer`) to find the exact settings keys.
- Modify: `connectors/dominion-ptr/class-dominion-ptr-seeder.php` (add the analytics settings)
- Test: `tests/Unit/DominionPtrAnalyticsSettingsTest.php`
- Docs: `docs/superpowers/ptr-gtm-trigger.md` (the GTM container change Nat must make)

**Interfaces:**
- Consumes: the seeded instance `settings`.
- Produces: the instance `settings['analytics']` enables the dataLayer conversion push with `event` name `conversion` and the marketing-site container id.

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;
use FFFL\Connectors\DominionPtr\Seeder;

class DominionPtrAnalyticsSettingsTest extends TestCase
{
    public function testInstanceEnablesDataLayerConversion(): void
    {
        $id = Seeder::create_instance();
        $instance = $this->getInstance($id);
        $settings = json_decode($instance['settings'], true);

        $this->assertTrue($settings['analytics']['gtm_enabled']);
        $this->assertSame('conversion', $settings['analytics']['conversion_event']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter DominionPtrAnalyticsSettingsTest`
Expected: FAIL.

- [ ] **Step 3: Add analytics settings to the seeder**

In `Seeder::create_instance()`, extend `$settings` with (use the exact keys the analytics JS reads — confirm against `analytics-integration.js`):

```php
        $settings['analytics'] = [
            'gtm_enabled' => true,
            'gtm_container_id' => 'GTM-KG937MGX',
            'conversion_event' => 'conversion',
        ];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter DominionPtrAnalyticsSettingsTest`
Expected: PASS.

- [ ] **Step 5: Document the GTM container change**

Create `docs/superpowers/ptr-gtm-trigger.md`:

```markdown
# PTR GTM trigger (owner: Nat)

The FormFlow enrollment form pushes a `conversion` event to `dataLayer` on
completion (see enrollment.js `pushToDataLayer`). In the marketing-site
container GTM-KG937MGX, add a Custom Event trigger on event name `conversion`
and point the existing HUB conversion tag (the one corrected for btn-validate)
at it, so the conversion fires with the `_pnut_cid` click-id on form success.

Prerequisite: the marketing site must load GTM-KG937MGX again (it stopped ~Jul 6).
```

- [ ] **Step 6: Commit**

```bash
git add connectors/dominion-ptr/class-dominion-ptr-seeder.php tests/Unit/DominionPtrAnalyticsSettingsTest.php docs/superpowers/ptr-gtm-trigger.md
git commit -m "feat(ptr): enable dataLayer conversion for the PTR instance + document GTM trigger"
```

---

### Task 7 (Stage 1 close-out): Full suite green + manual demo checklist

**Files:**
- Modify: `CHANGELOG.md` (note the Stage 1 addition)

- [ ] **Step 1: Run the full unit suite**

Run: `composer test`
Expected: PASS (no regressions in existing connector/form/security suites).

- [ ] **Step 2: Run the guarded live validate test**

Run: `FFFL_LIVE_TESTS=1 composer test -- --filter testLiveValidateReadOnly`
Expected: PASS (read-only; confirms the live Dominion validate endpoint still matches the parser).

- [ ] **Step 3: Record the Stage 1 manual demo steps**

Add to `CHANGELOG.md` under an "Unreleased" heading a short Stage 1 note and the manual demo path: render the `dominion-ptr` instance form, enter the test account, confirm validation returns the address and the flow advances to terms, confirm the stubbed enroll shows success, and confirm the `conversion` dataLayer event is present in the browser console. **Do not submit a live enroll.**

- [ ] **Step 4: Commit**

```bash
git add CHANGELOG.md
git commit -m "docs(ptr): stage 1 complete — live validation + stubbed enroll demo"
```

---

## Stage 2 — Live enroll + portal handoff (GATED on Itron)

> Do not start Stage 2 until Itron provides: the enroll endpoint path, its authentication, the IP-allowlist status for `LAS-P-ITS-DOMPTR-MKT-01`, and the enroll response shape (account id + set-password token). The tasks below are structured; the exact request params and response parsing are finalized against Itron's answer and the real (Itron-provided) test enrollment account. **The `Rspectesting123` account is validation-only and must never be enrolled.**

### Task 8 (gated): Live `submit_enrollment()` against `prospect/enroll`

**Files:**
- Modify: `connectors/dominion-ptr/class-dominion-ptr-connector.php` (live branch of `submit_enrollment`, add `parse_enrollment`)
- Test: `tests/Unit/DominionPtrEnrollLiveTest.php` (fixture built from Itron's documented enroll response)

Structure: mirror `validate_account` — build the request from `map_fields()` plus the confirmed premise id, POST/GET to the Itron-provided enroll path, decode JSON, map to `EnrollmentResult` (`success`, `confirmation_number`, `data['account_id']`, `data['set_password_token']`). Write the unit test first from Itron's documented response body, then implement. Add auth per Itron (header or param); keep it out of logs.

### Task 9 (gated): Portal handoff redirect

**Files:**
- Modify: the enrollment success handler (the code that runs after a successful `submit_enrollment` — identify from the embed submit route at `includes/class-embed-handler.php`)
- Test: `tests/Unit/DominionPtrHandoffTest.php`

Structure: on live enroll success, build the IntelliSource portal initial-password URL from `data['account_id']` and `data['set_password_token']` (format confirmed with Itron; the SPA used `…/ptr/residential/#initial-password/{id}/{token}`) and return it as the post-submit redirect target. Test asserts the URL is built correctly from a stubbed `EnrollmentResult`.

### Task 10 (gated): Move conversion to true enroll success + end-to-end verify

**Files:**
- Modify: instance `settings` / the completion handler so the `conversion` dataLayer event fires on live enroll success rather than the Stage 1 milestone.

Structure: flip `test_mode` off on the instance; verify end to end with the Itron test account — validation, live enroll, portal handoff, and a HUB conversion carrying a `click_id` that stitches the journey (requires Nat's GTM trigger from Task 6 to be live and the marketing site loading GTM-KG937MGX). Record the verified run in `CHANGELOG.md`.

---

## Self-review notes

- **Spec coverage:** scope/boundary (Tasks 3-4, 9), connector JSON dialect (Tasks 1-2), multi-step flow (Task 4), conversion/attribution (Task 6, 10), portal handoff (Task 9), staged delivery (Stage 1 vs Stage 2 split), testing (guarded live test Task 2/7), risks (Task 4 A/B gate; Stage 2 Itron gate). All covered.
- **Spec refinement recorded:** the spec described "adapt the connector's parse layer"; implementation is a *new sibling connector* speaking JSON (the classic `/prospects/validate.xml` returns 404 on Dominion), which is lower-risk than modifying the live XML connector. Same Approach A at the form/instance/tracking level.
- **Known unknowns (external, not placeholders):** the enroll endpoint path, auth, and response shape are Itron-provided and explicitly gate Stage 2; Stage 1 is fully concrete and independently shippable (live validation + stubbed enroll + wired conversion).
