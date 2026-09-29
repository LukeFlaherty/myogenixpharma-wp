# TRT consent renewal operations

Updated 2026-09-29. Patient rollout remains **off**. Stripe remains **live** for ordinary production orders.

## Latest verification: September 29

- Virender's proposed `service_ids=560` still returns HTTP 200 with all 449 refill questions (template 10622) and 432 initial questions (8173). Grouping and alternate parameter encodings do not fix it. The refill response is identical to the unfiltered response. [Updated reproduction details](TRT_QUESTIONNAIRE_API_REPRO.md) include the full request and current service definitions.
- Service 560 is now labeled **TRT Cream ORAL**, with cream and tablet products. Service 559 is **TRT Injectable CA Commercial** and now has populated injectable products. Both identify 10622 as their renewal template. Do not silently configure injectable renewals to an oral/cream service. No clinical answers, orders, labs, or charges were submitted in this check.
- Current audit: **23 active real subscriptions** plus excluded internal test 4843. Newly missing patient mapping **5166** was repaired to patient **353175** after a unique exact email, full-name, and phone match; metadata only, no status/date/price changes. **5168** had no unique match on all three identifiers and needs staff verification. Do not create a duplicate provider patient to bypass this.
- **2899** still lacks a medication renewal price. **3452** has reached day 85, with next payment October 6 and no new-flow invitation recorded. Its paid renewal is 4742 (paid July 5; cycle creation July 6). Before activation, staff must resolve its current renewal or arrange a transition that does not treat a never-sent invitation as unanswered. No real subscription was paused during the audit.
- The intake interface and submission verification remain unfinished. September 23's cohort counts and eligibility findings below are historical; this section supersedes them. Production Stripe remains live and the rollout flag remains false.

## Current behavior

- Product 883 only. WCS remains the subscription record.
- Daily checks invite the patient starting on day 63 (week 9), catching up through day 84. Day 75 prompts staff follow-up; no response by day 85 pauses the subscription without a charge.
- GET renders a confirmation page; a signed POST records the choice. Email scanners cannot create an order by opening a link.
- Continue creates one pending renewal, registers it with `/shopify/callback`, then requests labs through `/api/v2/lab/128/save-test-order` with `external_order_id` equal to the **new renewal ID as a string**.
- The explicit intake callback runs once after the pending order and its prices are saved. Legacy creation/status callbacks remain suppressed for that exact renewal. Other orders retain existing behavior.
- Patients choose Continue/Pause in the email and confirm on our site. Renewal registration and lab ordering run through the APIs. **Omar confirmed on September 18 that patients must submit intake every three months.** The patient-facing renewal intake step is not implemented. The callback acknowledgment records technical registration, not completion of clinical intake. Do not enable rollout until required intake is connected and verified.
- No renewal payment link works before provider approval. Pause and expiry put the subscription on hold for staff follow-up.
- Provider approval must identify the correct patient, appointment, and pending renewal; consent, accepted renewal registration, and confirmed lab creation are prerequisites.
- Approved renewals use the existing Stripe charge/retry/receipt/pharmacy function. Established medicine prices, including discounted plans, are preserved. Zero-price legacy lines use the stored approval price; copied upfront lab/consultation fees are removed. Missing prices require review.
- Payment can follow early approval, but advancing the subscription preserves the existing paid-through period. Repeated callbacks do not charge or advance twice.

## Completed provider checks

Omar confirmed production panel 627 and tests 2, 13, 15, 9, 10, 8, 11. His September 16 reply confirms that the intake callback is required and `external_order_id` has been deployed. Fresh official documentation includes it as an optional string.

A controlled production test used fake patient **351289**, subscription **5020**, and renewal **5021**, addressed only to Luke's requested test email:

- `/shopify/callback` returned HTTP 200 and `{"ok":true}`. The patient lab list was unchanged immediately after the callback and again before the separate lab request.
- The lab request included `external_order_id: "5021"`, returned HTTP 200 and a token, and created exactly one new lab order **2439**. Existing test lab **2423** was not replaced or recreated.
- Renewal 5021 stayed pending and unpaid; no live charge or pharmacy release occurred.
- Gmail confirmed receipt of the renewal confirmation and the new “Next Step: Complete Your Lab Work” email. Both the September 15 and new requisition links returned readable one-page Quest PDFs with the fake patient's details, lab reference/barcodes, and all seven tests.
- The questionnaire token loaded Prescribery's refill flow and recognized the fake patient's email/DOB. No medical questionnaire or clinical consent was submitted.
- The provider's lab-list API does not expose `external_order_id`. On September 18, Omar triggered an approval and our authenticated handler recorded **order 5021, patient 351289, appointment 448501, status approved**. The fake-order guard returned its expected 403 before payment/pharmacy processing. Renewal 5021 remains pending with no transaction or pharmacy release; the fake parent was untouched. Production Stripe remains live and rollout remains off. This verifies provider-to-renewal correlation, not completion of patient intake or an actual charge.

Prescribery's screenshot totals **$90.60 + $28 = $118.60**, versus Omar's approximate $90.50 + $28. This is vendor cost information, not a change to patient pricing. Preserve customer prices; the ten-cent estimate difference is not itself a launch blocker. The catalog's free/zero-price label does not establish Myogenix's wholesale invoice amount.

## Remaining launch gates

1. **Required quarterly intake:** Connect the correct provider-approved TRT refill questions and verify fresh patient answers reach the correct renewal. On September 23, Omar's requested `service_ids=559` calls returned HTTP 200 with **432 questions for template 8173 and 449 for template 10622**, including unrelated treatments. This supersedes September 18's empty-list result. Template 10622 returns identical normalized `data` with and without the service filter; grouped format puts all questions into one group. Do not choose clinical questions by guesswork. [Full redacted requests and observed responses](TRT_QUESTIONNAIRE_API_REPRO.md) are ready for Omar/Virender. They must confirm the refill template and working service filtering, plus how submitted answers associate with the current registered renewal. No answers or clinical attestations were submitted. The patient intake interface and submission verification remain unfinished.
2. **Existing subscription review:** September 23 audit found **21 active real TRT subscriptions**, all with patient mappings and none past the consent deadline. Only **2899** still needs a price decision: its medication line and September 22 renewal 5125 are $0, with no saved approval price. Do not infer the established patient price from the current catalog. Confirm the intended renewal amount or explicitly exclude the subscription from rollout pending staff review. The other previously flagged records are resolved as launch-review items: 4679 is now cancelled and mapped; 2880 renewed September 19 with a $567 total and transaction reference; 4843 carries `_trt_internal_test=yes` and a test name and is excluded from the patient rollout. These status/payment changes were already present when audited, not performed by this task.
3. Only after those checks, set `MYOGENIX_TRT_REDESIGN_LIVE=true` and verify the first eligible cohort.

On September 23, the missing patient mapping on subscription 2899 was repaired to patient **321236** after a unique exact email, full-name, and phone match from the authenticated client-scoped provider API. Only subscription metadata changed; no billing dates, prices, order statuses, payments, labs, or patient answers were changed. Its next payment remains December 22.

The earlier plugin-side `prescription_cancel_subscription()` fix was verified live. Patient Pause independently sets on-hold and does not call the provider-rejection cancellation helper.

## Test safety and evidence

`myogenix_trt_qa` is a private option with `subscription_ids`, `email`, and `expires_at`. A fixture also requires `_trt_qa_test=yes` and matching billing email. Never use a site-wide Stripe test-mode switch.

All marked fake orders, including fake original parents, are blocked from external approval charges. After the existing REST authentication succeeds, `_trt_qa_callback_seen` records only IDs, status, top-level field names, and time; the endpoint returns 403 for these fake orders. Tell Prescribery this rejection is expected during the mapping test. No raw clinical payload or credentials are retained there.

Legacy `_trt_internal_test=yes` subscriptions never enter the new renewal flow, including staff detection emails and expiry handling. `_trt_qa_test=yes` subscriptions remain restricted to the temporary QA allowlist even after the production flag is enabled. These exclusions do not alter the separate legacy billing process.

Fake renewal 5021 and its parent are retained as evidence of the completed provider check. Browser verification is complete, subscription 5020 is on hold, and the temporary QA allowlist has been removed. These retained fake records cannot be charged by the external approval handler. Other test-suite fixtures are trashed after each run.

Candidate integration suite:

```sh
TRT_TEST_SOURCE=/tmp/myogenix-trt-20260923 wp --skip-plugins=affiliate-wp --skip-themes eval-file /tmp/myogenix-trt-20260923/trt-renewal-integration.php
```

Copy all four `inc/trt-renewal-*.php` files and `tests/trt-renewal-integration.php` into the private candidate directory first. All HTTP and email are mocked. **58 checks passed on September 23**, covering internal-test exclusion from invitations/provider requests and expiry handling, consent, unpaid orders, registration ordering/correlation, duplicate suppression, lab retries, ambiguous callback/lab outcomes, QA approval protection, pricing, scheduling, and omission of the legacy questionnaire from renewal receipts. Fixtures were cleaned up and the original QA option restored.

Historical verification before the questionnaire correction: production browser verification passed on desktop and at 390px: the confirmation displayed its working questionnaire button, and the revised confirmation reached Luke’s inbox. The already-created test renewal was reused for this presentation check; the final provider lab list remained exactly 2439 and 2423. Deployed PHP hashes matched the committed candidate; Stripe stayed live, rollout stayed off, and the fake renewal had neither a transaction nor pharmacy-release marker. The public renewal guide loaded with its updated questionnaire instructions.

Earlier September 15 payment verification used the existing Stripe pipeline with request-scoped test credentials (`livemode=false`), a 56700-cent test charge, and intercepted pharmacy release. Repeat approval did not charge again; the billing date advanced from October 10 to January 10. Production Stripe mode remained live throughout. That local test is separate from the pending real provider callback test.

## Failure recovery

- `_trt_pending_renewal_order` identifies the existing renewal. Reuse it; do not create a replacement to work around a failed request.
- `_trt_intake_state=submitting|uncertain` blocks resending after a timeout, interrupted request, or unrecognized response. Check Prescribery before resetting it. Accepted response is HTTP 200/201 with JSON `ok: true`; state `sent` prevents another callback on lab retry or later order transitions.
- `_trt_lab_state=submitting|uncertain` blocks lab resubmission. If Prescribery already has the request, record its verified token and state `created`. Reset to `rejected` only after confirming no request exists. Known validation/authentication failures can retry the same renewal.
- Failed consent stays unresolved and sends a staff notice with subscription/order references, without raw provider responses.
- DB advisory locks serialize consent, cron, and approval for each subscription.
- Credentials stay in private WP options. Environment is selected by `myogenix_trt_lab_api.active_env`; do not switch an in-progress renewal between environments. The callback does not require a patient-facing questionnaire URL.

## Patient experience and deployment

Consent emails prioritize the two Continue/Pause buttons and use the homepage's black texture, red accents, and logo. Confirmation/error pages reuse site navigation/footer. The guide at `/trt-renewal/` covers initial ordering, renewal, labs, receipts, and staff follow-up. Consent pages remain no-cache/no-referrer/noindex and omit unrelated tracking hooks.

Run PHP lint and `git diff --check`, commit only TRT files on `main`, push, then verify deployed file hashes and production pages with Playwright. Do not include unrelated dashboard/affiliate work or private QA artifacts in the commit.

Reference: [Prescribery lab API documentation](https://staging.prescribery.com/api/docs#labs-POSTapi-v2-lab--clientId--save-test-order).

## Historical correction on September 17: keep renewal patient actions on Myogenix

The questionnaire buttons and instructions were added before the requirement was established and were removed on September 17. The legacy plugin's questionnaire shortcode is also suppressed for tagged consent renewals, including payment receipts. Neither registration nor lab creation required a completed questionnaire in the production test. **That API behavior did not establish whether intake was clinically required. Omar's September 18 confirmation supersedes the earlier assumption:** intake every three months is required. Patient copy now reflects that requirement; the actual intake interface remains a launch gate. Initial-purchase intake is unchanged. Prior test emails already delivered cannot be recalled.
