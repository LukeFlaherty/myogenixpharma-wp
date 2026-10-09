# Wave Consulting — Create an order

Admin URL: `/wp-admin/admin.php?page=wave-orders`

## Staff workflow

1. Search for an existing customer by name, email or billing phone, or enter a new customer's exact contact and billing details.
2. Choose each exact purchasable product or variation, quantity and any existing coupon codes.
3. Optionally choose an affiliate for this order. Leaving it blank preserves normal lifetime/customer/referral rules.
4. Review open orders and subscriptions. A matching active or unresolved subscription blocks sending a new link.
5. Choose one payment path after review:
   - Create a pending WooCommerce order and use Stripe's admin card window. Existing customers can use a saved card by brand/last four digits; a new card can also be entered. Capture charges now; Authorize creates a hold for later capture.
   - Send the secure, seven-day payment link. The accepted mail event is recorded, but mail acceptance does not prove delivery.
6. Recurring subscription products always use the customer payment link so WooCommerce can create the subscription schedule and capture the customer's consent.
7. With the link, the customer signs in when the email belongs to an existing account, or confirms the exact recipient email as a guest. Continuing intentionally replaces that browser's cart with the prepared items.
8. The normal Stripe, WooCommerce, prescription-review and subscription systems remain authoritative.

## Card and account handling

- Staff can enter a card into Stripe's hosted admin card field. The raw card number is tokenized by Stripe and is not stored in WordPress or the prepared-order record.
- Existing customers' saved payment methods are shown only by their safe display label (such as brand and last four digits). A full stored card number is never revealed.
- Guest cards cannot be saved for future purchases; saving requires an existing customer account.
- The link is bound to the exact recipient. Existing accounts must sign in; guests must confirm the exact email address.
- Opening a link does not mutate a cart. The customer must submit the signed landing form before the prepared basket is created.

## Safety and recovery

- Exact items, quantities, identity and any explicitly selected affiliate are revalidated immediately before either payment path creates an order, including WooCommerce Checkout Block requests.
- Admin-created orders reproduce the installed Prescribery consultation-only pricing and expected-after-approval metadata before Stripe is opened.
- Creation and checkout are serialized and idempotent so repeated submissions cannot intentionally create multiple prepared records or orders.
- Coupon-to-affiliate conflicts and affiliate self-referrals are blocked. Customer lifetime attribution is not changed.
- A prepared order with a different email or items cannot be edited in place. Cancel it and create a replacement so the original audit trail remains intact.
- Prepared records and audit metadata are private and not exposed through REST or public archives.
