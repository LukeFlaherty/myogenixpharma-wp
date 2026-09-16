# TRT consent renewal operations

Updated 2026-09-16. Patient rollout remains **off** until the provider-side handoff is verified.

## Behavior

- Product 883 only. WCS remains the subscription record.
- Daily checks send consent starting on day 63, catching up through day 84.
- Detection-only admin notices and patient delivery have separate markers.
- A GET link renders a confirmation page. Only a signed POST records the choice.
- Continue creates one pending renewal, then requests labs and stores the returned token.
- No patient payment link works before explicit provider approval.
- Pause and expiry put the subscription on hold for staff follow-up, without a charge.
- Approved renewals use the existing Stripe charge/retry/receipt/pharmacy function, with a TRT-specific preparation adapter. The copied unpaid total is never treated as a checkout payment.
- An established nonzero medicine line price is preserved, including discounted plans. Zero-price legacy lines use the stored approval price. Copied upfront consultation/blood-work fees are removed. Missing prices require staff review.
- Early payment advances the next billing date from the existing paid-through date using WCS date helpers; repeat callbacks do not advance it twice.

## Launch gates

1. **Complete:** Omar confirmed panel 627, Men's Testosterone FollowUp, with tests 2, 13, 15, 9, 10, 8, 11 in his September 15, 2026, 7:27 PM reply supplied by Luke. The production configuration was checked September 16 and matches exactly.
2. **Partially complete:** Luke received the lab-work email for the fake patient. Verify that its requisition download opens the correct usable form; inbox delivery alone does not establish this.
3. **Waiting on Prescribery:** Omar says an optional correlation field is being added, with production deployment planned September 16. Obtain its exact name, type, example, deployment confirmation, and approval callback contract before implementation. Confirm how Prescribery associates the lab request with the NEW WooCommerce renewal order and sends its approval with that order ID. `save-test-order` has no documented WooCommerce order-ID field. The `reason` includes a reference for staff; it is **not a verified machine correlation contract**. The legacy callback remains suppressed for this exact renewal during creation and later status transitions because its effect alongside the lab API is unverified.
4. Complete an actual provider-to-site callback test. A direct test of our approval handler proves local behavior only.
5. Resolve the lab-cost discrepancy described below. Prescribery charges are not automatic authorization to change patient pricing or add a charge before consent/provider approval.
6. Review overdue active subscriptions and missing patient IDs before changing the live constant.

Do not equate receiving a lab token with completion of these gates.

## QA

`myogenix_trt_qa` is a private WP option with `subscription_ids`, `email`, and `expires_at`. A fake subscription must also have `_trt_qa_test=yes` and its billing email must match. This permits testing specific subscriptions while the global flag is false. External provider callbacks for fake orders are rejected. Test payment checks run only in a controlled CLI request using Stripe test mode, with pharmacy release intercepted.

Candidate integration suite:

```sh
TRT_TEST_SOURCE=/tmp wp --skip-plugins=affiliate-wp --skip-themes eval-file /tmp/trt-renewal-integration.php
```

Copy the three `inc/trt-renewal-*.php` files and `tests/trt-renewal-integration.php` to the chosen private candidate directory first. The suite mocks all HTTP and email, creates labeled fixtures, and trashes its fake orders afterward. It never operates on real subscriptions. It leaves a dedicated test customer account for subsequent preview work.

37 checks passed on the installed WordPress/WooCommerce/WCS stack on 2026-09-15 before deployment. PHP lint and `git diff --check` also passed. This does not establish the external provider handoff.

## Failure recovery

- `_trt_pending_renewal_order` points to the one current renewal. Reuse it; do not create a second one.
- `_trt_lab_state=submitting|uncertain` blocks automatic resubmission after a timeout or interrupted request. Check Prescribery first. If the order exists, record its verified token and state `created`. Only reset to `rejected` after confirming that no lab request exists.
- Failed consent remains unresolved. Staff receives a notice containing the subscription and renewal references, without raw API responses.
- DB advisory locks serialize consent/cron/approval operations for each subscription; locks release when the DB connection closes.
- Credentials stay in `myogenix_trt_lab_api`, never in this repository, commands, or patient error pages.
- The plugin-side `prescription_cancel_subscription()` live fix was verified: it now cancels subscriptions after provider rejection. Patient Pause uses its own direct on-hold action and does not call that cancellation function.

## Deployment

Commit and push `main`, then verify the production URL and deployed file hashes. Keep `MYOGENIX_TRT_REDESIGN_LIVE=false` until the launch gates pass. Remove QA allowlisting and put every retained fake subscription on hold after testing.

## Production QA evidence — 2026-09-15

- Test patient 351289 (TRT Test / Renewal QA; Luke’s requested email).
- Browser Continue: fake subscription 4910 created renewal 4913 at $567, pending and unpaid, with a real production lab token. Prescribery lists lab order 2423, panel 627, results pending. The free panel reports `Paid` in Prescribery even though the request specifies pending and no live payment was made; do not infer patient billing from that label.
- Browser Pause: fake subscription 4912 moved to on-hold. Patient and staff-preview messages were routed to Luke.
- Desktop and 390px mobile checks: no horizontal overflow; signed GET is read-only; buttons submit a POST; invalid links show a styled error.
- Authenticated in-process REST approval test: existing Stripe pipeline succeeded for 56700 cents using `livemode=false`. No live card was charged. Pharmacy release was intercepted.
- Repeated approval: returned already paid, with no additional charge.
- Subscription schedule advanced from 2026-10-10 to 2027-01-10 and preserved the prepaid period.
- Approval adapter sets the existing invoice-origin marker so the normal approval receipt is sent.
- Test emails: consent invitations, renewal confirmation, pause confirmation, staff preview, and test payment receipt were accepted by WordPress mail. Inbox delivery is for Luke to confirm.
- Fake orders were trashed (recoverable), fake subscriptions disabled, and QA allowlisting removed after checks. Provider test patient/lab records remain clearly labeled for provider verification. Do not create another lab request for the same test to compensate for an unknown delivery state.
- Global Stripe mode remains live; the test-mode override existed only within the controlled CLI payment request.
- Actual Prescribery-to-site approval correlation and patient requisition delivery are still unverified. Production activation remains blocked on those points and confirmation of the clinical panel. The code is not evidence that Prescribery implements that missing contract.

## Patient experience update — 2026-09-15 afternoon

- Consent and response emails now use the homepage’s black texture, red accents, and a raster export of its existing logo. The invitation leads with a headline and two large choices; next-step detail lives at `/trt-renewal/`.
- Consent, success, and error pages reuse the actual site navigation/footer and grunge styles. They deliberately omit WordPress head/footer tracking hooks, retain no-cache/no-referrer/noindex protections, and allow only the required styles, fonts, images, and site navigation script.
- `/trt-renewal/` explains initial ordering, consent renewal, labs, payment, each email’s purpose, and staff follow-up. A launch notice appears while the global renewal switch is off.
- Luke supplied an inbox screenshot of “Next Step: Complete Your Lab Work” for the fake patient. Email delivery is now evidenced; the linked requisition contents and external approval-to-renewal correlation still need verification. The email is absent from the WordPress mail log and the local integration templates, suggesting provider-side delivery; confirm ownership with Prescribery before changing that template.
- Global Stripe mode was rechecked as `live`. Scoped test mode must never be replaced with a site-wide switch for QA.
- The 37 guarded integration checks passed again after the presentation update; all HTTP and mail were mocked for that suite.


## Prescribery follow-up — 2026-09-16

### Confirmed / read-only checks

- Omar confirmed the configured production panel and all seven test IDs. No lab configuration change was necessary.
- Production Stripe remains `live`; `MYOGENIX_TRT_REDESIGN_LIVE` remains `false`.
- Authenticated production `GET /api/v2/lab/list/128/1/1` returned HTTP 200, panel 627 with `type: free`, and `price: 0.00` for each selected test. The response does not establish Prescribery's wholesale invoice amount or whether processing fees are included.
- Omar's statement that Prescribery charges each test price plus 28 in lab processing fees requires clarification against those zero prices. Ask for the authoritative amount, whether the processing fee is per requisition or per test, whether Myogenix is invoiced separately, and which payment fields are appropriate when labs are included for the patient. Preserve existing customer pricing until Luke approves any change.
- Read-only checks of fake patient 351289 returned no patient documents; lab order 2423 remains present with amount `0`, payment `Paid`, and results `Pending`. These API views did not expose the emailed requisition download, so that link still needs direct verification.
- The publicly accessible staging API documentation still does not list the new WooCommerce correlation field at the time of review. The production documentation could not be retrieved with the web reader; neither observation proves the new field is or is not deployed. Do not guess a field name or treat the `reason` reference as sufficient.

### Exact callback behavior to explain to Omar

Checked against a fresh copy of the installed `prescribery-wc-integration` plugin, unchanged from the September 15 audit:

- `woocommerce_order_status_processing` and `woocommerce_order_status_on-hold`, priority 20: `Pre_Woo_Integration::on_order_processed_send_callback()` invokes the callback for orders containing synced products. The sender returns early when `is_admin()` is true. These are status-transition hooks, not an explicit once-only checkout hook; later transitions can attempt another callback.
- `wcs_renewal_order_created`, priority 10: `Pre_Woo_Integration::on_renewal_order_created_callback()` invokes the callback as soon as a native WCS renewal order exists. The plugin calculates a synced-product flag here but does not use it to guard the send.
- Endpoint: `https://staff.prescribery.com/shopify/callback`. Both payloads include `uuid`, `orderId`, `platform: woocommerce`, `source_id`, and `client_id`; the status-transition sender also includes `patient_id`.
- New consent renewals: Continue creates a pending WooCommerce renewal, then calls the lab API. The theme suppresses the legacy callback for that specifically tagged renewal, including later order-status transitions, while its required role and duplicate effects are unconfirmed. Existing initial orders and ordinary renewals retain their current behavior while the new workflow is off.
- Ask whether the new correlation field alone registers the renewal for review/approval, or whether the legacy callback must also run. If it must run, request exact sequencing and how Prescribery prevents a second lab order. Obtain an example approval payload returning the new WooCommerce order ID and appointment ID, then coordinate the controlled external callback test.

Reference: [Prescribery lab API documentation](https://staging.prescribery.com/api/docs#labs-POSTapi-v2-lab--clientId--save-test-order). No new production lab request, live payment, or pharmacy release was initiated during this follow-up.
