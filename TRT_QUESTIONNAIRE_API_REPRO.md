# TRT questionnaire filtering: production reproduction

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

Please confirm the production template for **TRT quarterly refills** and correct the service filtering, or provide the exact working request if another parameter is required. We should receive the applicable clinical questions without choosing the question set ourselves.

The answer submission must also associate the completed intake with the current renewal registered through `/shopify/callback`; the lab request already sends the new WooCommerce ID as `external_order_id`. Please identify the required association if it is not automatic for the current registered renewal.

Documentation: [Questionnaire endpoint](https://staging.prescribery.com/api/docs#questionnaires-GETapi-v2-questionnaires--templateId-).
