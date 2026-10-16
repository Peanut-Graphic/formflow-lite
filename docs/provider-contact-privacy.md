# Provider contact privacy boundary

Account number and ZIP validation does not establish ownership of the provider's contact record. The masked validation summary remains available, but full provider name, email and street must not appear in rendered steps or resume JSON.

`PublicFormData` stores provider results privately and signs an allowlisted copy of caller input, bound to the server-issued session and instance. Public templates and resume responses receive only this projection. A posted private envelope cannot grant provenance. Ambiguous legacy contact fields stay private until the caller enters them again; this intentionally removes old automatic contact prefills. Validation again clears old contact, consent and appointment state. Submitted enrollments cannot change their account.

Enrollment mapping uses caller-entered contact and server-owned validated account identifiers. Scheduler-only confirmation mail may use a separate private provider payload, addressed directly to the provider mailbox. That payload is never persisted as caller input or returned to the browser. Save-and-email resume links still go to the requested mailbox and expose only caller input.

Residual disclosure: the existing masked validation summary and medical acknowledgement flag remain. This change is not proof of utility-account ownership and does not add an identity verification mechanism.

## Verification

Synthetic demo accounts only; no live utility account lookup or real mail delivery. Unit flow coverage exercises all enrollment/scheduler templates, autosave, resume to an unrelated mailbox, explicit caller input, enrollment mapping, scheduler confirmation mail and appointment dates, legacy/tampered provenance, malformed stored JSON, and account revalidation guards. Existing session-binding and completed-session tests remain in force.

The default PHPUnit suite and separate property, reliability, contract and regression configurations must pass. Real WordPress contract and Docker activation checks are required before production release. Release and deployment require a fresh passing hosted CI run for the final main candidate.
