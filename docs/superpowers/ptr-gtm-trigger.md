# PTR GTM trigger (owner: Nat)

The FormFlow Dominion PTR enrollment form pushes a `conversion` event to
`dataLayer` on completion (`public/assets/js/analytics-integration.js`,
~line 169). The PTR instance now ships with `settings.analytics.gtmEnabled =
true` and `settings.analytics.gtmContainerId = 'GTM-KG937MGX'`, so the form
will push that event once the JS is configured with these values at
integration time.

## Container change required

In the marketing-site container **GTM-KG937MGX**:

1. Add a **Custom Event** trigger firing on event name `conversion`.
2. Point the existing HUB conversion tag (the one already corrected for
   `btn-validate`) at that new trigger, so the conversion fires with the
   `_pnut_cid` click-id present when the form completes.

## Prerequisite

The marketing site must be loading GTM-KG937MGX again — it stopped loading
around July 6. Confirm the container is live before wiring the trigger,
otherwise the tag will never fire.

## Notes

- GA4 conversion tracking is additionally gated on `ga4MeasurementId` being
  configured in the analytics settings; that is not part of this change.
- The exact mapping from the instance's `settings.analytics` block to the
  front-end analytics-integration.js config (camelCase `gtmEnabled` /
  `gtmContainerId`) is reconciled at integration time (Stage 2 / a
  ContractWp or manual pass) — it is not asserted by this pure PHP suite.
