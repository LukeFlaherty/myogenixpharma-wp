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
3. Confirm how Prescribery associates the lab request with the NEW WooCommerce renewal order and sends its approval with that order ID. `save-test-order` has no documented WooCommerce order-ID field. The `reason` includes a reference for staff; it is **not a verified machine correlation contract**. The legacy callback remains suppressed during creation because its effect alongside the lab API is unverified.
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

31 checks passed on the installed WordPress/WooCommerce/WCS stack on 2026-09-15 before deployment. PHP lint and `git diff --check` also passed. This does not establish the external provider handoff.

## Failure recovery

- `_trt_pending_renewal_order` points to the one current renewal. Reuse it; do not create a second one.
- `_trt_lab_state=submitting|uncertain` blocks automatic resubmission after a timeout or interrupted request. Check Prescribery first. If the order exists, record its verified token and state `created`. Only reset to `rejected` after confirming that no lab request exists.
- Failed consent remains unresolved. Staff receives a notice containing the subscription and renewal references, without raw API responses.
- DB advisory locks serialize consent/cron/approval operations for each subscription; locks release when the DB connection closes.
- Credentials stay in `myogenix_trt_lab_api`, never in this repository, commands, or patient error pages.
- The plugin-side `prescription_cancel_subscription()` live fix was verified: it now cancels subscriptions after provider rejection. Patient Pause uses its own direct on-hold action and does not call that cancellation function.

## Deployment

Commit and push `main`, then verify the production URL and deployed file hashes. Keep `MYOGENIX_TRT_REDESIGN_LIVE=false` until the launch gates pass. Remove QA allowlisting and put every retained fake subscription on hold after testing.
