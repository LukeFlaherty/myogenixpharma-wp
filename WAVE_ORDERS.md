# Wave Consulting — Create an order

Admin URL: `/wp-admin/admin.php?page=wave-orders`

## Staff workflow

1. Search for an existing customer by name, email or billing phone, or enter a new customer's exact email and name.
2. Choose each exact purchasable product or variation, quantity and any existing coupon codes.
3. Optionally choose an affiliate for this order. Leaving it blank preserves normal lifetime/customer/referral rules.
4. Review open orders and subscriptions. A matching active or unresolved subscription blocks sending a new link.
5. Confirm the review and send the secure, seven-day payment link. The accepted mail event is recorded, but mail acceptance does not prove delivery.
6. The customer signs in when the email belongs to an existing account, or confirms the exact recipient email as a guest. Continuing intentionally replaces that browser's cart with the prepared items.
7. WooCommerce checkout calculates current prices, fees, discounts, shipping and tax; collects required patient data and consent; and creates the order. The normal payment, prescription-review and subscription systems remain authoritative.

## Card and account handling

- Staff never enter, receive or view full card details in this screen. Do not request card details by text or email.
- Existing customers use the normal signed-in checkout and any saved payment methods it offers. A prepared link cannot reveal a stored card number.
- The link is bound to the exact recipient. Existing accounts must sign in; guests must confirm the exact email address.
- Opening a link does not mutate a cart. The customer must submit the signed landing form before the prepared basket is created.

## Safety and recovery

- Exact items, quantities, identity and any explicitly selected affiliate are revalidated immediately before checkout creates an order, including WooCommerce Checkout Block requests.
- Creation and checkout are serialized and idempotent so repeated submissions cannot intentionally create multiple prepared records or orders.
- Coupon-to-affiliate conflicts and affiliate self-referrals are blocked. Customer lifetime attribution is not changed.
- A prepared order with a different email or items cannot be edited in place. Cancel it and create a replacement so the original audit trail remains intact.
- Prepared records and audit metadata are private and not exposed through REST or public archives.
