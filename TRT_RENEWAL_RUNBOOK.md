# TRT consent renewal operations

Updated 2026-09-17. Patient rollout remains **off**. Stripe remains **live** for ordinary production orders.

## Current behavior

- Product 883 only. WCS remains the subscription record.
- Daily checks invite the patient starting on day 63 (week 9), catching up through day 84. Day 75 prompts staff follow-up; no response by day 85 pauses the subscription without a charge.
- GET renders a confirmation page; a signed POST records the choice. Email scanners cannot create an order by opening a link.
- Continue creates one pending renewal, registers it with `/shopify/callback`, then requests labs through `/api/v2/lab/128/save-test-order` with `external_order_id` equal to the **new renewal ID as a string**.
- The explicit intake callback runs once after the pending order and its prices are saved. Legacy creation/status callbacks remain suppressed for that exact renewal. Other orders retain existing behavior.
- The questionnaire URL follows the installed integration's configured funnel URL and encoded WooCommerce order token. The confirmation email/page provide a questionnaire button. Intake must be completed before scheduling the provider visit.
- No renewal payment link works before provider approval. Pause and expiry put the subscription on hold for staff follow-up.
- Provider approval must identify the correct patient, appointment, and pending renewal; consent, accepted intake, and confirmed lab creation are prerequisites.
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
- The provider's lab-list API does not expose `external_order_id`; API acceptance alone does not prove the eventual approval callback will return the right WooCommerce ID.

Prescribery's screenshot totals **$90.60 + $28 = $118.60**, versus Omar's approximate $90.50 + $28. This is vendor cost information, not a change to patient pricing. Preserve customer prices; the ten-cent estimate difference is not itself a launch blocker. The catalog's free/zero-price label does not establish Myogenix's wholesale invoice amount.

## Remaining launch gates

1. **Provider approval round trip:** Have Prescribery trigger a test approval for renewal 5021 / patient 351289 / lab 2439. Confirm the incoming new order ID, patient ID, and appointment ID. An invented approval request tests our handler but cannot establish Prescribery's mapping.
2. **Provider-owned intake wording:** The TRT refill page currently requires agreement to “Compounded / Non-FDA-Approved Peptide Treatment.” Prescribery must confirm/correct the TRT consent wording. Screenshot: `output/playwright/trt-refill-consent-20260917.png` (local QA artifact, not committed).
3. **Existing subscription review:** Read-only audit of 23 active non-QA TRT subscriptions found patient mappings missing on 4679 and 2899; cycle age past 85 days on 2880 and 2899. Subscription 4843 has an upcoming September 22 payment but an August 27 last-order date and no recorded paid orders; its cycle anchor also needs review. All four use a three-month billing interval. Do not blindly enable a rule that immediately pauses overdue records or suppresses legacy billing before their cycle is established. No real subscription was modified by this audit.
4. Only after those checks, set `MYOGENIX_TRT_REDESIGN_LIVE=true` and verify the first eligible cohort.

The earlier plugin-side `prescription_cancel_subscription()` fix was verified live. Patient Pause independently sets on-hold and does not call the provider-rejection cancellation helper.

## Test safety and evidence

`myogenix_trt_qa` is a private option with `subscription_ids`, `email`, and `expires_at`. A fixture also requires `_trt_qa_test=yes` and matching billing email. Never use a site-wide Stripe test-mode switch.

All marked fake orders, including fake original parents, are blocked from external approval charges. After the existing REST authentication succeeds, `_trt_qa_callback_seen` records only IDs, status, top-level field names, and time; the endpoint returns 403 for these fake orders. Tell Prescribery this rejection is expected during the mapping test. No raw clinical payload or credentials are retained there.

Keep fake renewal 5021 and its parent for that provider check; keep subscription 5020 on hold and remove the temporary QA allowlist after browser verification. These retained fake records cannot be charged by the external approval handler. Other test-suite fixtures are trashed after each run.

Candidate integration suite:

```sh
TRT_TEST_SOURCE=/tmp/myogenix-trt-20260917 wp --skip-plugins=affiliate-wp --skip-themes eval-file /tmp/myogenix-trt-20260917/trt-renewal-integration.php
```

Copy all four `inc/trt-renewal-*.php` files and `tests/trt-renewal-integration.php` into the private candidate directory first. All HTTP and email are mocked. **55 checks passed** on the installed WooCommerce/WCS stack September 17, covering consent, unpaid orders, intake ordering/correlation, duplicate suppression, lab retries, ambiguous intake/lab outcomes, QA approval protection, pricing, and subscription scheduling.

Earlier September 15 payment verification used the existing Stripe pipeline with request-scoped test credentials (`livemode=false`), a 56700-cent test charge, and intercepted pharmacy release. Repeat approval did not charge again; the billing date advanced from October 10 to January 10. Production Stripe mode remained live throughout. That local test is separate from the pending real provider callback test.

## Failure recovery

- `_trt_pending_renewal_order` identifies the existing renewal. Reuse it; do not create a replacement to work around a failed request.
- `_trt_intake_state=submitting|uncertain` blocks resending after a timeout, interrupted request, or unrecognized response. Check Prescribery before resetting it. Accepted response is HTTP 200/201 with JSON `ok: true`; state `sent` prevents another callback on lab retry or later order transitions.
- `_trt_lab_state=submitting|uncertain` blocks lab resubmission. If Prescribery already has the request, record its verified token and state `created`. Reset to `rejected` only after confirming no request exists. Known validation/authentication failures can retry the same renewal.
- Failed consent stays unresolved and sends a staff notice with subscription/order references, without raw provider responses.
- DB advisory locks serialize consent, cron, and approval for each subscription.
- Credentials stay in private WP options. Environment is selected by `myogenix_trt_lab_api.active_env`; do not switch an in-progress renewal between environments. Sandbox testing needs its matching `intake_base_url` if the installed integration's funnel settings are production-only.

## Patient experience and deployment

Consent emails prioritize the two Continue/Pause buttons and use the homepage's black texture, red accents, and logo. Confirmation/error pages reuse site navigation/footer. The guide at `/trt-renewal/` covers ordering, renewal, questionnaires, labs, receipts, and staff follow-up. Consent pages remain no-cache/no-referrer/noindex and omit unrelated tracking hooks.

Run PHP lint and `git diff --check`, commit only TRT files on `main`, push, then verify deployed file hashes and production pages with Playwright. Do not include unrelated dashboard/affiliate work or private QA artifacts in the commit.

Reference: [Prescribery lab API documentation](https://staging.prescribery.com/api/docs#labs-POSTapi-v2-lab--clientId--save-test-order).
