# TRT questionnaire filtering: production reproduction

## October 1 association response

Omar confirmed:

- Do not add `service_id` to `/shopify/callback`.
- Use source **769** for renewals rather than the initial source 768.
- Prescribery associates the latest submitted refill questionnaire with the latest renewal.
- Present the refill questions with the new renewal/lab cycle; Prescribery uses the returned results to proceed with provider scheduling.

The guarded implementation therefore registers the renewal with source 769, fetches and submits template 10622 with service 558, and records the returned `ques_map_id` against the exact WooCommerce renewal on the Myogenix side. The production automation flag remains off until a controlled fake-record submission verifies the provider-side result. No real patient questionnaire will be used for that verification.

### October 1 controlled submission result

- Retained fake subscription 5020 / renewal 5021 loaded the 12-question patient form in production and submitted test-only answers successfully.
- Prescribery returned HTTP 200 and `ques_map_id` **441118**. WooCommerce stored the mapping and operational milestone but no clinical answers; the fake order remains pending with no transaction, provider approval, charge marker, or pharmacy marker.
- The temporary QA allowlist was deleted after the test. Automated intake remains disabled for real patients.
- `GET /patients/{patientId}/questionnaires` returned HTTP 401 for the production integration credentials. Prescribery must confirm in its staff system that mapping 441118 is visible for the fake patient as the latest template-10622 refill questionnaire and associated with the latest renewal before Myogenix enables real-patient automation.

## September 30 update: service 558 filters the refill questionnaire

Neha requested service 558 on September 30. A read-only production retest at September 30, 2026, 9:24 p.m. Eastern (October 1, 01:24 UTC), using the existing client 128 integration, found:

```bash
curl --request GET \
  --url 'https://staff.prescribery.com/api/v2/questionnaires/10622?service_ids=558' \
  --header 'Authorization: Bearer <REDACTED>' \
  --header 'Accept: application/json'
```

- HTTP 200 with **12 questions**, compared with 449 in the unfiltered response. The filtered and unfiltered `data` are not identical.
- The 12 returned entries are refill/check-in questions covering treatment progress, dose changes, side effects, testosterone contraindications, new medications/interactions, treatment effectiveness, requested treatment changes, pregnancy/breastfeeding with a male N/A choice, last physical/labs, and additional provider notes.
- The filtered response contains none of the unrelated GLP-1, Vitamin B12, PT-141, hair-restoration, BPC-157, or GHK-Cu content previously flagged.
- `GET /services/558` identifies **Hormone Replacement Therapy**, client 128, initial template 5705/source 768, and refill template **10622/source 769**. Its current product list contains commercial and compounded testosterone cypionate injections plus injection kits. The service description mentions injection or oral therapy, but no oral product appeared in the returned product list.
- The service's configured initial template 5705 returned HTTP 403 both with and without the service filter. That does not block this refill-questionnaire GET, but Prescribery should clarify it before service 558 is used for new-patient intake.
- For comparison only, template 8173 with `service_ids=558` now returns 20 filtered entries instead of all 432. Template 8173 is not the initial template currently reported by service 558.

This resolves the question-selection defect for the refill GET. It does **not** by itself establish the submission workflow or prove that completed answers attach to the new WooCommerce renewal registered through `/shopify/callback`. Before replacing staff-assisted intake, Prescribery must confirm the patient-facing submission/link flow for service 558 and identify the field or mechanism that correlates the completed template 10622 response to that exact renewal/order. No answers, patient data, orders, labs, charges, or service settings were changed during this verification.

Prescribery's API documentation, updated September 28, documents `POST /api/v2/questionnaires/answers` with required `template_id`, `patient_id`, and `answers`, plus optional `ques_map_id`, `questionnaire_id`, and `service_ids`. It documents no `order_id`, `external_order_id`, or `source_id` field for that submission. The documented patient-questionnaire listing returns questionnaire mapping/submission state but no external order reference. Therefore the public API does not currently describe how to prove that a fresh response belongs to one particular quarterly WooCommerce renewal.

## September 29 update: service 560 does not resolve filtering

Virender requested service 560 on September 25. Production retest September 29 at 19:49 UTC:

```bash
curl --request GET \
  --url 'https://staff.prescribery.com/api/v2/questionnaires/10622?service_ids=560' \
  --header 'Authorization: Bearer <REDACTED>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json'
```

- HTTP 200, **449 questions**, including mandatory and nonconditional Vitamin B12, PT-141, and GLP-1 questions.
- Initial template 8173 with `service_ids=560` returns HTTP 200 and **432 questions**.
- Explicit grouped/ungrouped formats, array query syntax, and a trailing-comma service list also return all 449 refill questions. The normalized refill data hash matches the unfiltered response and the September 23 response below.
- `/services/560` reports **TRT Cream ORAL** and its `product_and_price` contains testosterone cream and rapid-dissolve tablets. `renewal_template_id=10622`, `renewal_source_id=769`.
- `/services/559` now reports **TRT Injectable CA Commercial**, with populated `product_and_price` entries for testosterone cypionate and an injection kit. It also specifies renewal template 10622/source 769. No service, medication, source, or price settings were changed by this investigation.

The service 560 treatment listing does not match the injectable product in this renewal project. Ask Prescribery to confirm the applicable injectable service and demonstrate a working filtered request with our production client credentials. The returned clinical question set must be selected by the provider configuration, not by guessing which of the 449 questions apply.

Full response retained locally at `output/trt-api-20260929/refill-template-10622-service-560.json` for sharing with the engineers. It contains questionnaire definitions only, with no token or patient answers.

## September 23 evidence

Verified September 23, 2026, 16:32–16:34 UTC. Read-only GET requests. No patient answers, credentials, orders, or personal details are included here.

## Request context

- Production base: `https://staff.prescribery.com/api/v2`
- Client: 128; configured source: 768.
- A valid production bearer token was obtained using the existing access-token integration. Substitute it for the placeholder below; do not send the token by email.
- `GET /clients/128/services` returns service **559**, title **TRT Injectable**, template **8173**, source **768**.
- `GET /questionnaires?per_page=100` also exposes template **10622**, title **MYOGENIX REFILL Questionnaire**, source **769**.

## Full requests (authorization redacted)

```bash
curl --request GET \
  --url 'https://staff.prescribery.com/api/v2/questionnaires/8173?service_ids=559' \
  --header 'Authorization: Bearer <REDACTED>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json'

curl --request GET \
  --url 'https://staff.prescribery.com/api/v2/questionnaires/10622?service_ids=559' \
  --header 'Authorization: Bearer <REDACTED>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json'
```

Neither request has a body.

## Actual results

Both return HTTP 200 with top-level keys `version` and `data`.

| Template | Items in `data` | Unexpected treatment content |
| --- | ---: | --- |
| 8173 | 432 | GLP-1 and microdose GLP-1 goals appear among the first questions |
| 10622 | 449 | Vitamin B12, PT-141, GLP-1, hair restoration, BPC-157, and GHK-Cu questions appear among the first questions |

For example, template 10622 entry `7569` is a mandatory GLP-1 conditions question with `conditions: []`, `isConditional: false`, and blank `group_questionnaire` and `flow` fields. The response does not identify it as an optional branch for a different service.

Additional read-only checks:

- Explicit `group_questionnaire=0` returns the same respective counts.
- `group_questionnaire=1` returns one group containing all 432 or 449 entries.
- Template 10622 with and without `service_ids=559` returns identical `data` after JSON normalization. SHA-256: `6167b71f97758ebefc8ec705ed0ac4e690dd11042e89ea37f1694d771ee06970`.
- On September 18 the filtered requests returned empty lists; September 23 results supersede that observation.

## Needed from Prescribery

Please confirm that service 558 and template 10622 are the provider-approved production configuration for **injectable TRT quarterly refills**. The September 30 GET now returns a focused 12-entry question set, so the filtering issue appears resolved.

The remaining blocker is the patient-facing completion and association workflow. Please provide the supported submission/link flow and confirm how completed template 10622 answers for service 558 associate with the current renewal registered through `/shopify/callback`. In particular, please confirm whether the renewal callback must send service 558 and renewal source 769 rather than the currently configured source 768. The documented questionnaire-answer endpoint has no order-reference field. The lab request already sends the new WooCommerce ID as `external_order_id`; please identify the equivalent questionnaire association if it is not automatic for the registered renewal.

Documentation: [Questionnaire endpoint](https://staging.prescribery.com/api/docs#questionnaires-GETapi-v2-questionnaires--templateId-).
