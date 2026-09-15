# TRT consent renewal operations

Updated 2026-09-15. Patient rollout remains **off** until the provider-side handoff is verified.

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

1. Confirm the production panel with Prescribery. Configuration currently uses panel 627, Men's Testosterone FollowUp, with tests 2, 13, 15, 9, 10, 8, 11. Previous notes claimed confirmation but their detailed reference says the ID was inferred by name.
2. Confirm how a pending lab API order delivers a usable requisition to the patient.
3. Confirm how Prescribery associates the lab request with the NEW WooCommerce renewal order and sends its approval with that order ID. `save-test-order` has no documented WooCommerce order-ID field. The `reason` includes a reference for staff; it is **not a verified machine correlation contract**. The legacy callback remains suppressed for this exact renewal during creation and later status transitions because its effect alongside the lab API is unverified.
4. Complete an actual provider-to-site callback test. A direct test of our approval handler proves local behavior only.
5. Review overdue active subscriptions and missing patient IDs before changing the live constant.

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
