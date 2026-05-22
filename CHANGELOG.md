# Changelog

All notable changes to FormFlow Lite are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
