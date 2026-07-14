# Dominion PTR enrollment — Stage 1 manual demo

Stage 1 delivers live account validation + a stubbed enroll, so the whole flow
is demoable without a live enrollment. **The test account is validation-only —
never complete a live enrollment with it; enrolling it corrupts the system.**
In Stage 1 the enroll call is stubbed via `test_mode` (the seeded instance sets
`test_mode = 1`), so the demo never writes.

## Steps

1. On a WordPress site with the plugin active, seed the instance:
   `wp eval '\FFFL\Connectors\DominionPtr\Seeder::create_instance();'`
   (or call `Seeder::create_instance()` from an activation hook). This creates a
   `dominion-ptr` enrollment instance bound to the `dominion-ptr` connector, with
   `test_mode` on.
2. Render the form via its shortcode on a marketing page that loads the GTM
   container `GTM-KG937MGX` (see `ptr-gtm-trigger.md`; the marketing site must be
   loading GTM again).
3. Enter the test account: account `210010506231`, zip `23116`, a test email.
   Click through to validation.
4. Expected: `prospect/validate` (live, read-only) returns the service address;
   the form shows the address-confirmation step; continuing advances toward the
   terms step. The step set is `validate → address_confirm → terms → enroll`
   (no device/scheduling steps).
5. With `test_mode` on, the enroll step returns a `PTR-DEMO-…` confirmation
   without any live write.
6. Confirm in the browser console / Tag Assistant that a `conversion` event is
   pushed to `dataLayer` on completion.

## What is NOT in Stage 1

- Live `prospect/enroll` (real enrollment) and its authentication — Stage 2,
  gated on IntelliSource credentials.
- The customer-portal set-password hand-off — Stage 2.
- The GTM trigger + marketing-site GTM restore — owner action, see
  `ptr-gtm-trigger.md`.
- The settings-to-frontend-JS analytics config wiring is reconciled at
  integration time, not asserted by the pure suite.
