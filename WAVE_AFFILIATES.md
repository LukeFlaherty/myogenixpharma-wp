# Wave Consulting — Affiliates

Admin URL: `/wp-admin/admin.php?page=wave-affiliates`

## Workflow

1. **Performance & leaderboard:** selected-month tracked visits, converted visits, referrals, recorded paid/unpaid commission, pending commission, current linked customers and proposed outstanding payouts. Ranked by earned commissions in the store currency, then visits. Referral URLs can be copied. Tracking events are not unique visitors.
2. **Customer links:** all loaded WooCommerce customers across product lines plus AffiliateWP customers. Search by customer/email/order/affiliate; filter to an affiliate or unassigned customers. Assign, reassign or remove the canonical AffiliateWP Lifetime Commissions link. Requires evidence, confirmation and a fresh link revision. Changes retain customer identity, email aliases and a private timestamped staff audit. Existing referrals and paid commissions are unchanged.
3. **Commission review:** shows why a pending/unpaid commission is excluded from the proposal. Separately lists paid orders without referrals, including direct purchases that may not qualify. After verifying attribution, staff may enter an agreed historical commission to create a **pending** referral, dated to the payment date, for source approval. No automatic rate or historical entitlement is inferred. Self-referrals, duplicates, invalid amounts, unconfirmed payment, refunds and unsuitable order statuses are blocked.
4. **Monthly reports:** generates immutable private snapshots and two CSV downloads: per-affiliate/currency payout summary and referral detail. Exports contain affiliate identities and order references, not patient names or emails. Reports do not pay or mark referrals paid. Use AffiliateWP Payouts to reconcile before payment, then regenerate to reflect changed source balances.

## Report semantics

The report is an **as-of-generation proposal of currently unpaid/pending commissions earned through the selected month**, not a reconstructed historical month-end ledger. Prior-month carryover is included in payable totals and separately identified. Paid/rejected referrals are excluded. Pending, zero/invalid, inactive-affiliate, existing-payout, duplicate, missing-order, refunded and unconfirmed-payment items are held for review. Fractions beyond AffiliateWP's configured payout precision and other currencies are also held for reconciliation. Exact recorded amounts are preserved, using integer fixed-point sums with up to four decimal places. No currency conversion or silent rounding occurs.

The monthly interval uses the WordPress site timezone and converts both bounds to UTC, including daylight-saving transitions. Referral detail timestamps are UTC. A snapshot retains its basis, timezone, creation time, author, proposal/review rows, totals and unresolved-data counts. Later source edits never change an existing export. The UI lists the latest 24 snapshots.

## Data / access

- Requires AffiliateWP, AffiliateWP Lifetime Commissions and WooCommerce.
- Restricted to users with `manage_options`, `manage_woocommerce` and `manage_affiliates`. Both page and mutation/download endpoints enforce access. Forms/downloads require WordPress nonces.
- Customer links use the add-on's canonical lifetime table through its CRUD API. Updates use `lifetime_customer_id`, not `affwp_customer_id`.
- Direct read-only SQL is used for monthly traffic aggregates and exact customer-link lookup because the installed add-on ignores its declared customer query filter.
- Advisory locks serialize Wave mutations; revision checks reject stale link forms. Native plugin operations use their own concurrency behavior. Any multiple order referrals are held for reconciliation.
- User/account identity conflicts and duplicate customer links require source review. Orphan lifetime records are counted and never silently repaired.
- Safety caps: 1,000 affiliates; 10,000 customers, referrals and lifetime links; 2,000 WooCommerce orders. Truncation displays a warning and blocks report generation. All loaded order dates appear in the missing-referral queue.
- Customer attribution follows AffiliateWP lifetime settings; it does not guarantee commissions for excluded products, disabled integrations or custom order flows.
- Private report post type is not exposed in REST or public listings. No external analytics, scripts or patient-data service calls are added.

## Validation

Run `php tests/wave-affiliates.php`, the three existing TRT tests, PHP lint and `git diff --check`. Live predeployment validation includes read-only loader/render checks against the installed plugin versions and an isolated temporary AffiliateWP customer for assign/reassign/unlink, audit and alias-retention verification. That fixture is removed; real orders/referrals are not mutated.
