# Dominion PTR Enrollment Form — Design

- **Date:** 2026-07-14
- **Status:** Approved design, ready for implementation planning
- **Plugin:** FormFlow Lite (`Peanut-Graphic/formflow-lite`, v3.2.22)
- **Branch:** `feature/dominion-ptr-enrollment`

## Problem

Dominion Energy Peak Time Rebates (PTR) enrollment happens inside the
IntelliSource-hosted SPA at `https://www.dominionenergyptr.com/ptr/residential/`.
That SPA loads our Google Tag Manager container through the `gtag.js` bootstrap,
which runs Google-owned tags but silently skips all custom tags. The enrollment
conversion tag and our first-party tracker are custom tags, so real enrollments
produce no conversion signal, and we cannot fix the SPA (it is not served by any
server we control; the path is routed at the F5 to the IntelliSource pool).

Every attribution step up to enrollment already works: tracked short links plant
a `click_id`, the marketing site records the visit, and the "Enroll Now" button
carries the `click_id` into the portal URL. The only missing piece is capturing
the enrollment itself.

## Approach

Replace only the **enrollment entry** with a FormFlow `enrollment` instance
embedded on the dominionenergyptr.com marketing site (the same pattern already in
production for the Energy Wise Rewards programs on Pepco and Delmarva). The form
collects the fields the portal asks for, calls the IntelliSource enrollment API on
the back end, and fires the conversion on our own page where GTM and the tracker
already work. After a successful enroll, the customer is handed to the
IntelliSource portal to set a login password. **The customer portal is unchanged**
and remains the home for password setup and account management. Only the
enrollment entry moves.

Architecture decision: **Approach A** — add PTR as a new instance on FormFlow's
existing `enrollment` form type, reusing the multi-step form engine, the
IntelliSource connector, and the existing conversion pathway. Fall back to
**Approach B** (a purpose-built PTR form type) only if the enrollment UI proves
too welded to Energy Wise's device/scheduling steps to render PTR's simpler flow
cleanly.

## Observed enrollment flow (validated live, read-only)

Walked with test data (account `210010506231`, zip `23116`, a test email),
stopping before any write:

1. **Field entry:** account number (no dashes), service zip, email. Three fields.
2. **Validate:** `GET /ptr/residential/api/prospect/validate?email=&zip=&utility_no=`
   — a GET API, unauthenticated, same shape the FormFlow IntelliSource connector
   already speaks. The SPA also calls
   `GET /ptr/residential/api/portal_user_emails?email=` (existing-account check)
   and `GET /ptr/residential/api/cep_configurations` (config).
3. **Address confirmation:** validation returns the service address for the
   customer to confirm ("We found 9593 Sycamore Grove Way, Mechanicsville, VA
   23116 — correct?", Continue / Cancel).
4. **Qualification / terms:** not traversed (to avoid enrolling the test account).
   PTR carries a sweepstakes terms-and-conditions step.
5. **Enroll:** the write call (expected `prospect/enroll` or similar), not
   traversed.
6. **Portal handoff:** the SPA's `#initial-password/{id}/{token}` step implies the
   enroll response provisions a portal account and issues a set-password token.

Key facts this establishes:

- **The API is same-origin** under `https://www.dominionenergyptr.com/ptr/residential/api/`
  (served by the F5 to IntelliSource, but the same host to the browser).
- **The validate half is buildable and testable now** — unauthenticated,
  same-origin, and we have a designated test account. Only the `enroll` endpoint
  and its authentication still require Itron.
- **The flow is multi-step**, mapping onto FormFlow's existing multi-step
  enrollment engine rather than a single-field form.

## Components

### 1. `dominion_ptr` instance and preset

A new instance on `form_type = enrollment`:

- `api_endpoint` = `https://www.dominionenergyptr.com/ptr/residential/api`
- `api_password` = (Stage 2, from Itron; validate needs none)
- Fields: `utility_no`, `zip`, `email`
- Dominion branding (logo, colors)
- `settings JSON` flags that disable Energy Wise's device-selection and
  install-scheduling steps

Add a matching entry to the connector's `get_presets()` alongside the existing
`delmarva_*` / `pepco_*` presets.

### 2. IntelliSource connector — Dominion adaptation

The connector (`connectors/intellisource/class-intellisource-connector.php`)
already exposes `validate_account()`, `submit_enrollment()`, and GET-based
`make_request()`. The work is in the parse layer, adapting to Dominion's response
shape:

- `parse_validation_response()` — return the confirmable service address from
  `prospect/validate`.
- Existing-account handling — consult `portal_user_emails` so already-enrolled
  accounts are routed appropriately rather than double-enrolled.
- `parse_enrollment_response()` — extract the new account id and set-password
  token from the `prospect/enroll` response for the portal handoff (Stage 2).

Field mapping lives in
`connectors/intellisource/class-intellisource-field-mapper.php`.

### 3. Multi-step form flow

Reuse the existing enrollment engine to render:

`fields → validate → address confirmation → terms/qualification → enroll → success + portal handoff`

Per-instance `settings` suppress the device/scheduling steps. If the engine
cannot render this reduced flow without Energy-Wise-specific assumptions leaking
through, that is the Approach A → B fallback trigger.

## Conversion and attribution

The form is embedded on the marketing site, where the GTM container
(`GTM-KG937MGX`) and the `_pnut_cid` click-id cookie already exist. FormFlow's
`public/assets/js/enrollment.js` already pushes a `conversion` event to
`dataLayer` on success (and `class-peanut-integration.php` fires
`do_action('peanut_conversion', ...)` server-side).

Flow on enroll success:

1. `enrollment.js` pushes the `conversion` dataLayer event.
2. The marketing-site GTM container fires the conversion tag (the same tag already
   corrected for the portal's `btn-validate`), which reads `_pnut_cid` from the
   cookie and sends the conversion to HUB **carrying the `click_id`**.
3. HUB stitches the conversion to the originating journey; GA4 and Google Ads
   conversions fire from the same container.

The server-side `peanut_conversion` hook fires as a backstop. Note the current
`peanut_conversion` payload carries `visitor_id` and UTM but not `click_id`, so
the dataLayer → GTM path (which reads the cookie client-side) is the primary route
for `click_id` journey stitching.

**Required container change (owner: Peanut/Nat):** add a GTM trigger that fires
the HUB conversion tag on the FormFlow `conversion` dataLayer event, the same way
the `btn-validate` trigger was added.

## Portal handoff

On a successful `prospect/enroll`, redirect the customer to the IntelliSource
portal's initial-password URL using the account id and token from the enroll
response (shape confirmed by Itron question 5). This preserves the existing portal
for password setup and account management.

## Staged delivery

The build is sequenced to match the Itron unblock so we deliver testable progress
before Itron responds.

### Stage 1 — no Itron dependency

- `dominion_ptr` instance + preset
- Full multi-step form UI (fields → validate → address confirm → terms)
- Live `prospect/validate` integration (unauthenticated, same-origin), tested with
  the designated test account, read-only
- `enroll` stubbed via the existing `class-mock-api-client.php`
- dataLayer `conversion` event wired (fires on the Stage 1 completion milestone)

Demo-able end to end except the final write.

### Stage 2 — after Itron provides the enroll endpoint, auth, allowlist

- Real `prospect/enroll` + authentication
- Move the conversion to fire on true enroll success
- Portal handoff redirect
- End-to-end verification (real test enrollment → HUB journey + conversion +
  Google Ads conversion → portal handoff)

## Testing

- `class-mock-api-client.php` for `enroll` in Stage 1.
- Live read-only `validate` tests with the designated test account. **The test
  account must never be enrolled** — it is validation-only; enrolling it corrupts
  the system.
- Assert the `conversion` dataLayer event fires on completion.
- Once the GTM trigger is added, confirm HUB receives the conversion carrying a
  `click_id` and the journey stitches.
- Existing FormFlow diagnostics harness for connector-level checks, mirroring the
  Delmarva validation approach.

## Risks and dependencies

- **Approach A weld risk:** the enrollment UI may embed device/scheduling
  assumptions. Mitigation: fall back to Approach B (purpose-built PTR form type).
- **Itron (blocking Stage 2):** enroll endpoint, authentication, IP allowlist
  status, and account-provisioning shape for the handoff. The same-origin,
  unauthenticated validate call suggests auth may be light, but this is unconfirmed.
- **Peanut/Nat:** the GTM dataLayer conversion trigger; a rebuild of the marketing
  site's GTM load (it stopped loading around July 6 and must be restored for the
  conversion path to work).
- **Dominion (client):** sign-off on moving the enrollment entry point from the
  hosted portal to a form on the marketing site.

## Out of scope

- Changes to the IntelliSource customer portal (login, dashboard, password reset).
- The F5 iRule and vendor `gtm.js` fixes (Solution 2), tracked separately as the
  parallel path to fix the hosted portal loader.
- The scheduler/booking half of the FormFlow enrollment flow (PTR is a bill-credit
  program with no device install).
