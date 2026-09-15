# Wave Consulting — TRT Patients

Admin URL: `/wp-admin/admin.php?page=wave-trt`

Read-only dashboard restricted to `manage_woocommerce`. No mutation routes, patient emails, payment calls, background jobs, or persistent patient caches. Assets load only on this admin page. Data is queried through WooCommerce CRUD for HPOS/legacy compatibility.

## Scope

- Product 883, including its variations, on orders and subscriptions.
- Customer ID groups registered patients. Guest billing email groups guests separately. Missing guest email stays per-order; no speculative identity merging.
- Latest order supplies the journey summary; every loaded TRT order and subscription is expandable below it.
- Recognized test names and `_trt_qa_test=yes` are excluded. Trash/drafts excluded.
- At most 2,000 records per type are scanned, newest first. A visible warning appears if the limit is reached. Counts then describe only loaded records.
- Selected milestone notes are read from the latest 100 internal order notes. Historical note signals are evidence of recorded events, not independently verified current provider state.
- Lab creation requires both created state and requisition marker. Intake, lab results, delivery are unconfirmed. Order completion never means confirmed delivery.
- Transaction reference and positive order total are labeled transaction recorded, not a reconciled medication payment amount. Order totals/refunds can include mixed products or fees; use source order to reconcile.
- Seven days from creation triggers follow-up for payment without pharmacy evidence; not a clinical turnaround SLA. Upcoming renewals are within 21 days. Counts may overlap.
- Refund/active flag matches refunded parent or renewal orders to their own active subscription. Historical resolved refunds can still flag until reviewed in source records.
- No automatic status changes or external provider polling. Refresh for current WooCommerce records.

## Validation

`php tests/wave-trt-dashboard.php` tests conservative classification using fictional facts only.
Lint PHP and run `git diff --check` before deployment. On production verify menu visibility, records/subscriptions, known refund flags, search, card filters, expanders, source links and mobile overflow. No patient data or screenshots should be committed.
