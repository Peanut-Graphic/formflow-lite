# Changelog

All notable changes to FormFlow Lite are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.2.22] - 2026-07-14

### Fixed

- **The configured Default State now applies on the public form.** On the customer-information step, the state field fell back to the instance's Default State only when the value was strictly unset; account validation always writes a `validated_state` (empty string when the API returns no state), and that empty string shadowed the default via `??`, leaving the field on "Select State." The fallback chain now treats an empty string as absent, so the Default State is honored.
- **HTML formatting in Terms & Conditions (and other rich-text content) survives saving.** The instance save handler sanitized every content field with `sanitize_textarea_field`, which strips all tags — so the Terms Content and email-body editors (both rendered as HTML on the frontend via `wp_kses_post`) had their markup flattened to plain text on save. Rich-HTML content keys are now sanitized with `wp_kses_post`, preserving headings, lists, tables, and emphasis while other fields stay plain-text.

### Changed

- **The state field is relabeled "District" when the District of Columbia is selected.** DC is a federal district, not a state; the customer-information label now reads "District" whenever DC is the selected value (whether from the instance default or the customer's own choice) and reverts to "State" otherwise.

### Removed

- **Delmarva Power – Delaware is no longer offered.** The Delaware Energy Wise Rewards program is not run through this plugin (no DE enrollment or scheduler), so the `delmarva_de` utility preset was removed from the registry, admin presets, and email utility-name map, and **Delaware ("DE") was removed as a selectable state** from the enrollment customer-information dropdown, the admin Default State dropdown, and state validation. The plugin now serves DC and MD only (Pepco DC, Pepco MD, Delmarva MD). *Note: if a `delmarva_de` form instance still exists in the database, delete it in wp-admin — with the preset removed, that instance's utility can no longer resolve.*

## [3.2.21] - 2026-07-05

### Security

- **Escape every field in the admin submission-detail view (stored XSS).** The submission modal built its DOM from decrypted, visitor-submitted form data with HTML-escaping applied to only a couple of fields, and placed the enrollee email directly into a `mailto:` href. A crafted submission could therefore execute script in an administrator's session. Every rendered field now passes through the escaping helper, and the email is emitted as a link only when it is a valid address (URL-encoded), otherwise as plain text.
- **Neutralize CSV formula injection on the submissions and analytics exports.** Cells beginning with `=`, `+`, `-`, `@`, tab, or carriage return are now prefixed with an apostrophe so spreadsheet applications do not evaluate attacker-supplied values as formulas.
- **Resolve the client IP from a trusted proxy only.** The rate limiter keyed on forwarded headers (`X-Forwarded-For` / `CF-Connecting-IP`) that any client can set, allowing the enrollment submission throttle to be bypassed by rotating the header. The resolver now defaults to the connection address and honors forwarded headers only from an admin-configured trusted-proxy allowlist (`FFFL_TRUSTED_PROXIES`).
- **Guard outbound IntelliSource requests against SSRF** by rejecting endpoints that resolve to loopback, link-local, or private address ranges, and **fix the embed CORS policy** so a wildcard origin is never combined with credentialed responses.

## [3.2.20] - 2026-06-02

### Fixed

- **Removed dead admin code that referenced non-existent view files (latent fatals).** Six orphaned `render_*` methods (`render_logs`, `render_analytics`, `render_settings`, `render_webhooks`, `render_diagnostics`, `render_compliance`) `include`d view files that never existed (`admin/views/logs.php`, etc.). The admin menu was reorganized into consolidated tabbed pages and now routes those old slugs to `redirect_to_*`, leaving these methods unreachable — but they would have fatalled if ever invoked. Removed them. The **"Scheduled Reports" tab** on the Automation page was also removed: it `include`d `admin/views/tabs/automation-reports.php`, which never existed, so clicking it produced a fatal error in wp-admin. Caught by the fatal-references pre-ship sweep.

## [3.2.19] - 2026-05-23

### Added

- **Configurable IntelliSource device + location codes.** The desired-device codes (`dd-15`) and equipment-location code (`eqLoc-15`) are now set per instance in the form editor (**API** step → "IntelliSource Device Codes"): thermostat device code, outdoor-switch/DCU device code, and equipment location code, defaulting to `03` / `02` / `05`. These values are applied to both enrollment and scheduling. IntelliSource has changed what these codes map to over time (`03` now resolves to IntelliTemp, `05` to "roof-multi-story" on the current deployment), so this removes the need for a plugin release whenever Comverge updates a code — set the Sensi WiFi and correct location codes here once your Comverge contact confirms them.

## [3.2.18] - 2026-05-22

### Fixed

- **The scheduler no longer rejects already-enrolled accounts.** The scheduler form exists so already-enrolled customers can book their installation — but it reused the enrollment validation, which treats "already enrolled" as a blocking error. An enrolled customer trying to schedule got "this account is already enrolled." Account validation now treats already-enrolled as success when the form type is `scheduler` (and still blocks it on the enrollment flow).

## [3.2.17] - 2026-05-22

### Fixed (conversion-critical)

- **Step 1 now honors `has_ac` and `device_type` URL parameters.** Intro pages link customers to the form as `…/?has_ac=yes&device_type=thermostat`, having already asked those questions — but the form ignored the parameters and rendered Step 1 with the checkbox and device radios unchecked. Because both are `required`, clicking Continue tripped the browser's native field validation and silently refused to advance, which looked exactly like the page "refreshing" to itself. Step 1 now pre-checks the "I have central AC" box and the matching device option from the URL, so Continue proceeds to Verify on the first click. (Form navigation itself was always working — confirmed by checking the fields manually.)

## [3.2.16] - 2026-05-22

### Fixed (enrollment-critical)

- **Installation slots now load — scheduling sends the equipment counts.** With enrollment succeeding (3.2.12/3.2.14), the scheduling call was still going out with no equipment parameters because it read form fields that don't exist (`ac_units` / `heat_pumps` / `ac_heat_units`). IntelliSource needs the equipment counts to compute availability, so it returned an empty calendar. The scheduling call now uses the real fields (`device_type` + `thermostat_count`) and sends `eqCount-15`, `eqLoc-15`, and `dd-15` (Sensei WiFi `03` / DCU `02`) — matching what enrollment submits. `ApiClient::get_schedule_slots()` now also forwards the desired-device (`dd-{type}`) parameter.

### Fixed (admin)

- **Dashboard responsiveness.** The All Time stat row (locked to four columns) now steps down to two columns under 1100px and one under 480px; the wide form-instances/submissions tables scroll horizontally instead of overflowing; and the quick-action bar wraps cleanly on small screens.

## [3.2.15] - 2026-05-22

### Added (UX)

- **Enrollment failures now show the customer a clear, actionable message instead of dropping them into an empty scheduler.** When IntelliSource rejects an enrollment (no Comverge number returned), the form previously advanced silently to step 4 with no available slots and no explanation. It now stops on the info step and shows a friendly message mapped from the IntelliSource error code — e.g. an already-enrolled account, an account/ZIP that can't be verified, or a promo-code problem — so the person can adjust their answers or knows to call customer service. The raw error is still logged for support.

## [3.2.14] - 2026-05-22

### Fixed (enrollment-critical)

- **Invalid promo code no longer kills the whole enrollment (the real "no slots" cause).** IntelliSource rejects the entire enrollment with error 04 ("Invalid or missing promo code") if `pCode` isn't one of its valid codes. The promo code can arrive from a UTM `promo` parameter or stale session data (e.g. `mail`), not just the dropdown — so one bad value silently failed enrollment, which meant no Comverge number and an all-grey scheduler. The enroll path now validates `promo_code` against the live IntelliSource code list and, if it's invalid/empty, substitutes a valid fallback (the instance's `default_promo_code` setting, else the first valid code) and logs the substitution.

### Fixed (frontend)

- **Reliable column alignment via uniform label height.** Replaced the 3.2.13 flex/margin-auto row-alignment (which pushed controls to the cell bottom and offset the Primary/Alternate Phone inputs) with a uniform two-line minimum height on labels in 2- and 3-column rows. Account Number/ZIP, Lease-or-Own/thermostats, and the phone rows now line up regardless of how many lines each label wraps to.

## [3.2.13] - 2026-05-22

### Fixed (frontend)

- **Removed the success-checkmark input wrap that was breaking form layout.** Validating a field used to wrap its `<input>` in a full-width inline-flex span to hang a checkmark. That re-parenting knocked paired fields out of alignment (Email / Confirm Email), broke the grouped phone field, and raced the phone/ZIP formatters into `appendChild` errors. Success is now shown by the green border alone; any leftover icon wrap is cleaned up.
- **Paired dropdowns/inputs in 2- and 3-column rows now align even when their labels wrap to different line counts** (e.g. "Lease or Own" vs "Number of thermostats controlling AC/heat pumps"). Controls are pinned to the bottom of each grid cell so they share a baseline.

### Diagnostics

- **Enrollment failures are now logged with the IntelliSource status and full `<message>` payload.** When `enroll.xml` returns no Comverge number, the activity log now records the IS status code and message body so the rejection reason is visible (previously only the empty result was logged).

## [3.2.12] - 2026-05-22

### Fixed (enrollment-critical)

- **Scheduler now receives the Comverge number from enrollment, so installation slots load.** The step-3 `enroll.xml` response returns the Comverge/FSR identifiers inside a `<message>` wrapper as lowercase nodes (`<cano>`, `<fsrno>`, `<comvergeno>`), which the XML parser represents as `{message: {cano: {value: "..."}}}`. The extraction in `fffl_enroll_early` only looked for top-level `caNo`/`fsr` (and a non-existent `response` wrapper), so it always stored an empty Comverge number — leaving step 4 with nothing to query and an all-grey "no available slots" calendar. Now digs through the `message` wrapper and `{value}` leaves and accepts every casing IntelliSource uses.

### Fixed (frontend)

- **Phone field no longer breaks/clips when validated.** The inline-validation success state wrapped each `<input>` in a `width:100%` span to position a checkmark. For the phone field (which lives in a flex `.ff-input-group` with its type selector) the wrap broke the group's layout and clipped the number; on phone + ZIP it also raced with the formatter's blur handler and threw `appendChild: node no longer a child`. Success-icon wrapping is now skipped for grouped inputs (the green border still signals success) and guarded against detached nodes.

## [3.2.11] - 2026-05-22

### Fixed (enrollment-critical)

- **Account validation/enrollment/scheduling/booking now use GET, not POST.** IntelliSource reads all parameters from the query string; it ignores POST bodies and returns a "Validation error (Code: 01)" when the form POSTs. `ApiClient::request()` was force-converting these credentialed calls to POST (the same coercion fixed for `/promo_codes` in 3.2.7). Switched `validate.xml`, `enroll.xml`, `scheduling.xml`, and the booking `schedule` call to GET via the `$force_method` flag. This is the actual cause of the "please check your credentials / validation error" failures on real accounts — the account number itself (e.g. `PHI`-prefixed Comverge numbers) was already being sent correctly.
- **Account prefix no longer stripped in the connector path.** `IntelliSourceConnector::validate_account()` and `get_schedule_slots()` were stripping non-digits, which removed the `PHI` prefix from Comverge account numbers and caused IntelliSource to reject them. Now matches the legacy form's routing: `X`-prefixed → `caNo` (X removed); everything else (plain utility numbers and `PHI`-prefixed Comverge numbers) → `utility_no` unchanged.

## [3.2.10] - 2026-05-21

### Fixed (enrollment-critical)

- **Thermostat enrollments now register as Sensei WiFi, not IntelliTemp.** The IntelliSource desired-device code (`dd-15`) for thermostats was being sent as `05`, which IntelliSource maps to IntelliTemp. The legacy production enrollment form sent `03` (Sensei WiFi). Corrected both field mappers (`connectors/intellisource/class-intellisource-field-mapper.php` and `includes/api/class-field-mapper.php`) to send `03`. DCU/outdoor-switch enrollments (`02`) were already correct.

## [3.2.9] - 2026-05-21

### Fixed

- **All Time stats no longer wrap 3+1.** With the grid layout now actually active (3.2.8), the four All Time cards were wrapping into a 3-on-top-1-below pattern because each card had a 200px minimum. Locked to four equal columns at slightly smaller padding so all four sit on one row.
- **Today panel no longer stretches to match All Time's height.** Added `align-items: start` to the dashboard grid so the Today column hugs its content instead of inflating with vertical whitespace.

## [3.2.8] - 2026-05-21

### Fixed

- **Dashboard layout: All Time and Today now sit side-by-side as designed.** The wrapper used `fffl-dashboard-grid` (four f's) while the CSS targeted `ff-dashboard-grid` (two f's), so the grid never applied and Today was stacking full-width below All Time with oversized cards. Aligned the HTML class to the CSS.
- **Action Scheduler banner is now dismissible and correctly branded.** Said "FormFlow Pro" (this is the Lite plugin) and re-appeared on every page load. Renamed to "FormFlow Lite", added `is-dismissible`, and persistent dismissal via user_meta so it stays gone for the dismissing user once they click X.

### Changed

- **Compact API Status empty state** — the "Click 'Check Now'" prompt now sits as a single line instead of consuming ~80px of vertical real estate before any health data is loaded.

## [3.2.7] - 2026-05-21

### Fixed

- **"Test Connection" no longer always fails.** `ApiClient::get_promo_codes()` was sending the credentials probe via POST because `ApiClient::request()` force-converted GET→POST whenever the password was present (an internal "security" coercion). IntelliSource's `/promo_codes` endpoint only accepts GET with the password in the query string, so the test was structurally guaranteed to fail (HTTP 404). Added a `$force_method` flag to bypass the coercion on this specific endpoint and switched `get_promo_codes()` to GET. The "API Health" diagnostic and the instance-editor Test Connection button now work against real IntelliSource installs.

### Added

- **Show/hide eye on the API Password field.** Click the eye in the instance editor's API step to reveal what was typed before saving. Accessibility-friendly (`aria-pressed` reflects state).

## [3.2.6] - 2026-05-21

### Changed

- **Single "FormFlow" admin menu.** Removed the duplicate React SPA top-level menu entry — its deep-link route (`formflow-editor`) was never registered with WordPress's admin router, causing a "Sorry, you are not allowed to access this page" `wp_die()` on any direct load. The remaining menu (previously "FF Forms (Legacy)") is renamed to just "FormFlow". The React handler is left in `class-admin.php` for a future re-introduction once the routing is fixed.
- **Safety-net redirects.** Old `formflow-lite-app` and `formflow-editor` URLs now redirect to the dashboard instead of `wp_die`-ing.

## [3.2.5] - 2026-05-21

### Fixed (P0 — correctness, enrollment-critical)

- **Idempotency guard on early enrollment.** `fffl_enroll_early` now short-circuits when the session already has `enrollment_completed=true` and returns the cached FSR#/caNo. A retried AJAX submit (slow network, double-click, browser back+resubmit) used to fire a second live IntelliSource enrollment for the same customer; that double-enrollment path is closed.
- **Booking success classification.** `parse_booking_response` now requires an explicit positive marker (`confirmation`, `caNo`, or `fsr`) and treats `<error_cd>`, "No available slots", and HTML error pages as failures. Previously the success check was `!str_contains('error')`, which false-positived on any IS response that didn't literally contain the word "error".
- **Region-scoped schedule slot cache key.** `CacheManager::cache_schedule_slots()` / `get_schedule_slots()` now require account number + ZIP, so one customer's IS slots cannot be served from cache to another customer in a different service region. (Latent today — the cache is not wired into the hot AJAX path — but fixed before it can be.)
- **Missing IntelliSource XML parser class.** The connector and loader referenced `IntelliSourceXmlParser`, but the source file was absent from the repo (would fatal on plugin activation). Switched to `\FFFL\Api\XmlParser` and removed the dead `require_once`.

### Notes

- Verified by `tests/smoke-3.2.5.php` (run with `php tests/smoke-3.2.5.php`). 13/13 assertions green.

## [3.2.4] - 2026-05-15

### Fixed

- Fix fatal — move ABSPATH guard after namespace declaration in the namespaced files (cd6f378-style regression that 500'd sites). Durable release; prod-class hotfix already applied 2026-05-15.

## [3.2.3]

### Security

- Fix unprepared SQL queries in deactivator and diagnostics
- Add rate limiting to public embed endpoints (submit 10/min, validate 20/min, schedule 20/min)
