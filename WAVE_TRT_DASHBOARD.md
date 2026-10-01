# Wave Consulting — TRT Patients

Admin URL: `/wp-admin/admin.php?page=wave-trt`

Dashboard restricted to `manage_woocommerce`. Staff workflow actions use authenticated POST requests with record-specific nonces, revision checks and per-record database locks. No patient emails, payment calls, background jobs, or persistent patient caches. Assets load only on this admin page. Data is queried through WooCommerce CRUD for HPOS/legacy compatibility.

## Billing overview

Admin URL: `/wp-admin/admin.php?page=wave-billing`, under Wave Consulting → Billing. Restricted to `manage_woocommerce`; this is a read-only operational view and never charges, refunds or changes an order.

- Date-range totals use the WooCommerce paid date for collections and refund creation date for refunds. Open pending, processing, on-hold and failed orders remain visible even when they began before the selected period.
- “Collected” requires both a positive order total, transaction reference and paid date. A single missing signal is flagged for review rather than counted as collected.
- “Paid & processing” means collection evidence is recorded while the WooCommerce order remains in processing. It does not describe processor settlement timing.
- “On hold” preserves the source WooCommerce status and clearly distinguishes paid from unverified collection. Recognized TRT renewal holds explain when intake verification is blocking payment.
- Period refunds, current processing value, holds, failed/pending/mismatched orders, customer/order detail and the next 30 days of active subscription value are visible and filterable.
- Monetary totals remain separated by currency. They are WooCommerce records, not bank deposits, processor fees, disputes, tax reporting or reconciled payouts.
- Recognized test records are excluded. At most 2,000 recent orders and 2,000 active subscriptions are scanned, with a visible warning when the limit makes totals incomplete.

`php tests/wave-billing.php` validates conservative payment classification without WordPress or live order access.

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
- Refund/active flag matches refunded parent or renewal orders to their own active subscription. Full refunds are red; partial refunds are amber and ask for context. Historical resolved refunds can still flag until reviewed in source records.
- No automatic status changes or external provider polling. Refresh for current WooCommerce records.

## Validation

`php tests/wave-trt-dashboard.php` tests conservative classification using fictional facts only.
Lint PHP and run `git diff --check` before deployment. On production verify menu visibility, records/subscriptions, known refund flags, search, card filters, expanders, source links and mobile overflow. No patient data or screenshots should be committed.

## Staff actions

- Assign follow-up to self or clear assignment; save status, next action and due date.
- Log private contact notes; close/reopen staff follow-up with a resolution note.
- Record or remove verified intake, lab completion, pharmacy, shipment and delivery milestones with supporting notes. Manual milestones are labeled staff verified and never write clinical/financial integration fields.
- Source alerts remain visible after closing a staff follow-up. Billing and subscription changes use clearly labeled links to native WooCommerce records.
- Workflow belongs to the displayed order (or subscription when no order exists). New treatment orders begin a separate workflow; previous notes remain on the original order.
- Saves retain the latest 50 staff events in metadata plus private WooCommerce audit notes. Duplicate/stale submissions are rejected.
- `php tests/wave-trt-actions.php`: isolated request-handler checks covering authorized writes, invalid actions, stale revisions, invalid dates, missing evidence, permissions, nonce failure and non-TRT records. No live patient changes during QA.

## Annual patient calendar

Admin URL: `/wp-admin/admin.php?page=wave-trt-calendar`, under Wave Consulting → TRT Patient Calendar. Same `manage_woocommerce` restriction and no-cache headers as the patient dashboard. All 12 months render together; the desktop layout uses four columns. Patient search, patient selection and event-type filters update month totals, day markers, the agenda, and scheduling gaps. Click a day for all matching events with patient-action and source-record links.

Sources and date meaning:

- Initial and renewal order creation dates, with current order status labeled separately.
- Refund record creation dates.
- Recognized provider approval, pharmacy handoff and medication-payment note dates.
- Staff follow-up due dates, with closed tasks labeled closed.
- Contact-note logging dates from retained staff history (latest 50 events per record).
- Current staff milestone verification dates, explicitly not assumed to be actual occurrence dates; removed verifications disappear.
- Next payment dates on active subscriptions only. Later cycles are not invented. On-hold/cancelled subscriptions do not appear as upcoming renewals.
- Optional suggested check-ins exactly 21 local calendar days before the next stored renewal. Off by default; not appointments, automated reminders, or messages.

All timestamps are converted using the WordPress timezone. Dates without timestamps (staff follow-ups) retain their stored local day. Scheduling gaps show patients with no open dated follow-up or no active scheduled renewal, independently of the viewed year. Past-due denotes an open scheduled date in the past, not proof of missed care. The existing record scan limit and test exclusions apply.

`php tests/wave-trt-calendar.php` validates year inputs, timezone boundaries, leap years, prior-year suggestions, renewal identification, closed follow-ups, paused subscriptions and removal of staff verification.
