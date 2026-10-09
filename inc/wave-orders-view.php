<?php
defined( 'ABSPATH' ) || exit;

function wave_orders_form( $operation, $id = 0 ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wo-form">'; wp_nonce_field( 'wave_orders_action' );
	foreach ( array( 'action' => 'wave_orders_action', 'operation' => $operation, 'draft_id' => $id ) as $key => $value ) { echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">'; }
}
function wave_orders_product_label( $p ) {
	$name = $p->get_name();
	if ( $p->get_parent_id() ) { $name .= ' — ' . wc_get_formatted_variation( $p, true, true, false ); }
	$price = html_entity_decode( wp_strip_all_tags( wc_price( $p->get_price() ) ), ENT_QUOTES, 'UTF-8' );
	$schedule = class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $p ) ? ' / every ' . WC_Subscriptions_Product::get_interval( $p ) . ' ' . WC_Subscriptions_Product::get_period( $p ) . '(s)' : ' / one-time';
	return wp_strip_all_tags( $name ) . ' · ' . $price . $schedule . ' · #' . $p->get_id();
}

function wave_orders_existing( $d ) {
	$args = array( 'type' => 'shop_order', 'limit' => 20, 'status' => array( 'pending', 'on-hold', 'processing', 'failed' ), 'orderby' => 'date', 'order' => 'DESC' );
	$args[ $d['customer_id'] ? 'customer_id' : 'billing_email' ] = $d['customer_id'] ?: $d['email'];
	$orders = wc_get_orders( $args ); $subs = array();
	if ( $d['customer_id'] && function_exists( 'wcs_get_users_subscriptions' ) ) { foreach ( wcs_get_users_subscriptions( $d['customer_id'] ) as $s ) { if ( $s->has_status( array( 'active', 'on-hold', 'pending', 'pending-cancel' ) ) ) { $subs[] = $s; } } }
	$families = array(); foreach ( $d['items'] as $i ) { $p = wc_get_product( $i['product'] ); if ( $p && ! empty( $i['subscription'] ) ) { $families[] = $p->get_parent_id() ?: $p->get_id(); } }
	$conflicts = array(); foreach ( $subs as $s ) { foreach ( $s->get_items() as $item ) { if ( in_array( $item->get_product_id(), $families, true ) ) { $conflicts[ $s->get_id() ] = $s; } } }
	return compact( 'orders', 'subs', 'conflicts' );
}

function wave_orders_render() {
	wave_orders_authorize();
	if ( ! function_exists( 'wc_get_products' ) ) { echo '<div class="wrap"><h1>Create an order</h1><p>WooCommerce must be active.</p></div>'; return; }
	$id = isset( $_GET['draft'] ) && is_scalar( $_GET['draft'] ) ? absint( $_GET['draft'] ) : 0; $d = $id ? wave_orders_get( $id ) : false;
	echo '<div class="wrap wave-trt wave-orders"><div class="wave-brand">WAVE CONSULTING <span>ORDER DESK</span></div><header class="wave-heading"><div><p class="wave-eyebrow">STAFF ASSISTED ORDERING</p><h1>Create an order</h1><p>Prepare and review the order, then enter a card securely or send the customer a payment link.</p></div><a class="button" href="' . esc_url( wave_orders_url() ) . '">Start a new order</a></header>';
	if ( $id && ! $d ) { echo '<div class="notice notice-error inline"><p>This prepared order is unavailable.</p></div>'; }
	if ( $d ) { wave_orders_detail( $id, $d ); } else { wave_orders_new_form(); }
	$drafts = get_posts( array( 'post_type' => 'wave_order_draft', 'post_status' => 'private', 'numberposts' => 50, 'orderby' => 'ID', 'order' => 'DESC' ) );
	echo '<section class="wa-panel"><h2>Recent prepared orders</h2><p>Last 50 prepared orders. Open an entry to send its link or view the resulting WooCommerce order.</p><label>Find in recent orders <input type="search" id="wo-recent-search" placeholder="Customer, email or order number"></label>';
	wave_aff_table_start( array( 'Prepared order / customer', 'Created', 'Progress', 'Action' ) );
	foreach ( $drafts as $p ) {
		$r = wave_orders_get( $p->ID ); if ( ! $r ) { continue; }
		$state = $r['order_id'] ? 'WooCommerce order #' . $r['order_id'] : ( 'cancelled' === $r['state'] ? 'Cancelled' : ( $r['expires'] <= time() ? 'Link expired' : ( 'sent' === $r['state'] ? 'Link sent — awaiting checkout' : 'Draft — not sent' ) ) );
		echo '<tr data-wo-recent><td><strong>#' . esc_html( $p->ID . ' · ' . $r['first_name'] . ' ' . $r['last_name'] ) . '</strong><small>' . esc_html( $r['email'] ) . '</small></td><td>' . esc_html( wp_date( 'M j, Y', $r['created'] ) ) . '</td><td>' . esc_html( $state ) . '</td><td><a class="button" href="' . esc_url( wave_orders_url( $p->ID ) ) . '">Open</a></td></tr>';
	}
	if ( ! $drafts ) { echo '<tr><td colspan="4">Your prepared orders will appear here.</td></tr>'; } wave_aff_table_end(); echo '</section><section class="wa-panel"><h2>Diagnostics</h2><p>Every order-desk step is recorded with a request ID, prepared-order ID and WooCommerce order ID. Card details, customer contact details and billing addresses are excluded.</p><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&source=wave-orders' ) ) . '">Open Wave order logs</a></p><p>When reporting a problem, include the prepared-order number, WooCommerce order number if one exists, the approximate time, and what Adam clicked.</p></section></div>';
}

function wave_orders_new_form() {
	$products = wc_get_products( array( 'status' => 'publish', 'limit' => 500, 'orderby' => 'name', 'order' => 'ASC' ) ); $options = array();
	foreach ( $products as $p ) {
		$choices = $p->is_type( array( 'variable', 'variable-subscription' ) ) ? array_map( 'wc_get_product', $p->get_children() ) : array( $p );
		foreach ( $choices as $choice ) { if ( $choice && $choice->is_purchasable() && $choice->is_in_stock() ) { $options[ $choice->get_id() ] = wave_orders_product_label( $choice ); } }
	}
	echo '<div class="wave-notice"><strong>Choose how payment will be completed after review.</strong><p>Adam can enter a new card in Stripe’s secure card window, use an existing customer’s saved card when available, or send the customer a payment link.</p></div><ol class="wa-steps"><li><strong>1. Customer</strong><span>Find an existing account or enter a new customer.</span></li><li><strong>2. Products &amp; options</strong><span>Select the exact dose, plan and quantity.</span></li><li><strong>3. Review &amp; payment</strong><span>Enter a card securely or send the payment link.</span></li></ol>';
	wave_orders_form( 'create' );
	echo '<input type="hidden" name="request_id" value="' . esc_attr( wp_generate_uuid4() ) . '"><section class="wa-panel"><h2>1. Who is the order for?</h2><label for="wo-customer-search">Search an existing customer</label><input type="search" id="wo-customer-search" placeholder="Name, email or phone — at least 3 characters" autocomplete="off"><div id="wo-customer-results" aria-live="polite"></div><p id="wo-customer-selected">New customer or guest. An exact email match will reuse an existing account.</p><button type="button" class="button" id="wo-new-customer">Use a new customer</button><input type="hidden" name="customer_id" value="0"><div class="wo-grid"><label>First name<input name="first_name" required maxlength="100" autocomplete="off"></label><label>Last name<input name="last_name" required maxlength="100" autocomplete="off"></label><label>Email<input name="email" type="email" required maxlength="200" autocomplete="off"></label><label>Phone<input name="phone" type="tel" maxlength="50" autocomplete="off"></label><label class="wo-wide">Billing street address<input name="address_1" required maxlength="200" autocomplete="off"></label><label class="wo-wide">Apartment, suite, etc. (optional)<input name="address_2" maxlength="200" autocomplete="off"></label><label>City<input name="city" required maxlength="100" autocomplete="off"></label><label>State / region<input name="state" required maxlength="100" autocomplete="off"></label><label>Postal code<input name="postcode" required maxlength="30" autocomplete="off"></label><label>Country<select name="country" required>';
	foreach ( WC()->countries->get_countries() as $code => $country ) { echo '<option value="' . esc_attr( $code ) . '"' . selected( 'US', $code, false ) . '>' . esc_html( $country ) . '</option>'; }
	echo '</select></label></div><p>The billing address is used for the WooCommerce order and card verification. Existing customer search fills saved billing details when available.</p></section><section class="wa-panel"><h2>2. Items</h2><p>Catalog prices are shown for reference. The review step calculates discounts and the amount due now. Prescription medication remains subject to patient intake and provider approval.</p><label>Filter product choices <input type="search" id="wo-product-search" placeholder="Product, dose, plan or ID"></label><div id="wo-lines">';
	for ( $i = 0; $i < 5; $i++ ) {
		echo '<div class="wo-line"><label>Product / exact option ' . ( $i + 1 ) . '<select name="items[' . $i . '][product]"' . ( 0 === $i ? ' required' : '' ) . '><option value="">' . ( 0 === $i ? 'Choose a product…' : 'Optional additional item…' ) . '</option>';
		foreach ( $options as $pid => $label ) { echo '<option value="' . esc_attr( $pid ) . '">' . esc_html( $label ) . '</option>'; }
		echo '</select></label><label>Quantity<input name="items[' . $i . '][quantity]" type="number" min="1" max="99" step="1" value="1" required></label></div>';
	}
	echo '</div><label>Coupon codes (optional, comma separated)<input name="coupons" maxlength="250" placeholder="Existing coupon codes"></label><p>Coupons are checked again at checkout. For an existing subscription renewal, use its existing renewal workflow; creating a new order can start a second subscription.</p></section><section class="wa-panel"><h2>3. Referral</h2><label>Affiliate for this order<select name="affiliate_id"><option value="0">Use existing customer / referral rules</option>';
	if ( function_exists( 'wave_aff_ready' ) && wave_aff_ready() ) { foreach ( affiliate_wp()->affiliates->get_affiliates( array( 'status' => 'active', 'number' => 1000 ) ) as $a ) { echo '<option value="' . esc_attr( $a->affiliate_id ) . '">' . esc_html( wave_aff_affiliate_name( $a ) . ' (#' . $a->affiliate_id . ')' ) . '</option>'; } }
	echo '</select></label><p>A selected affiliate applies to this order. Existing commission rules determine the amount. Customer lifetime links are unchanged.</p><button class="button button-primary button-large">Prepare order &amp; review</button><p>Next you will review the customer, amount, existing orders and payment method.</p></section></form>';
}

function wave_orders_detail( $id, $d ) {
	$existing = wave_orders_existing( $d ); $order = $d['order_id'] ? wc_get_order( $d['order_id'] ) : false;
	$address = (array) ( $d['address'] ?? array() );
	echo '<section class="wa-panel"><h2>Prepared order #' . esc_html( $id ) . '</h2><p><strong>' . esc_html( $d['first_name'] . ' ' . $d['last_name'] ) . '</strong> · ' . esc_html( $d['email'] ) . ( ! empty( $address['phone'] ) ? ' · ' . esc_html( $address['phone'] ) : '' ) . '</p><p>' . esc_html( $d['customer_id'] ? 'Existing customer account #' . $d['customer_id'] . ' — saved Stripe cards are offered when available.' : 'New customer / guest — a new card can be entered or a payment link can be sent.' ) . '</p>';
	if ( ! empty( $address['address_1'] ) ) { echo '<p><strong>Billing / shipping:</strong> ' . esc_html( implode( ', ', array_filter( array( $address['address_1'], $address['address_2'] ?? '', $address['city'] ?? '', $address['state'] ?? '', $address['postcode'] ?? '', $address['country'] ?? '' ) ) ) ) . '</p>'; }
	wave_aff_table_start( array( 'Item / option', 'Quantity', 'Catalog unit price' ) );
	foreach ( $d['items'] as $i ) { $p = wc_get_product( $i['product'] ); echo '<tr><td>' . esc_html( $p ? wave_orders_product_label( $p ) : $i['name'] . ' — unavailable' ) . '</td><td>' . esc_html( $i['quantity'] ) . '</td><td>' . wp_kses_post( wc_price( $i['price'] ) ) . '</td></tr>'; }
	wave_aff_table_end();
	echo '<p><strong>Coupons:</strong> ' . esc_html( $d['coupons'] ? implode( ', ', $d['coupons'] ) : 'None' ) . '</p><p><strong>Affiliate:</strong> ' . esc_html( $d['affiliate_id'] && function_exists( 'affwp_get_affiliate' ) && affwp_get_affiliate( $d['affiliate_id'] ) ? wave_aff_affiliate_name( affwp_get_affiliate( $d['affiliate_id'] ) ) : 'Existing customer / referral rules' ) . '</p><div class="wave-notice"><strong>Review the payment path before continuing.</strong><p>Card entry creates a pending WooCommerce order first and then opens Stripe. The payment-link path lets the customer confirm details and complete any required patient information or recurring-plan consent.</p></div></section>';
	if ( $existing['orders'] || $existing['subs'] ) {
		echo '<section class="wa-panel"><h2>Check existing orders before sending</h2><p>Confirm this is a new purchase. Continue an existing unpaid order or subscription renewal when appropriate.</p>';
		foreach ( array_merge( $existing['subs'], $existing['orders'] ) as $o ) { echo '<p><a href="' . esc_url( $o->get_edit_order_url() ) . '" target="_blank" rel="noopener">' . esc_html( ( 'shop_subscription' === $o->get_type() ? 'Subscription #' : 'Order #' ) . $o->get_id() ) . ' ↗</a> · ' . esc_html( $o->get_status() ) . ' · ' . wp_kses_post( $o->get_formatted_order_total() ) . '</p>'; } echo '</section>';
	}
	echo '<section class="wa-panel"><h2>Payment &amp; progress</h2>';
	if ( $order ) {
		echo '<p><a class="button" href="' . esc_url( $order->get_edit_order_url() ) . '">Open WooCommerce order #' . esc_html( $order->get_id() ) . '</a> · ' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . ' · <strong>' . wp_kses_post( $order->get_formatted_order_total() ) . '</strong></p>';
		if ( $order->get_transaction_id() && $order->get_date_paid() ) { echo '<div class="notice notice-success inline"><p>Payment is recorded on this order.</p></div>'; }
		elseif ( $order->has_status( array( 'pending', 'failed' ) ) && class_exists( 'WC_Stripe_Admin_Order_Metaboxes' ) ) {
			echo '<div class="wo-card-payment"><h3>Enter or choose a credit card</h3><p>Stripe securely handles the card entry. Existing customers can use a saved card shown by brand and last four digits; otherwise choose New Card.</p><input type="hidden" id="post_ID" value="' . esc_attr( $order->get_id() ) . '"><input type="hidden" id="customer_user" value="' . esc_attr( $order->get_customer_id() ) . '">';
			WC_Stripe_Admin_Order_Metaboxes::pay_order_section( $order );
			echo '<p class="description">Choose <strong>Capture</strong> to charge now. Choose <strong>Authorize</strong> only when the amount should be held and captured later.</p></div>';
		} else { echo '<p>Payment is not confirmed. Open the WooCommerce order to review its status and available actions.</p>'; }
	} elseif ( 'cancelled' === $d['state'] || $d['expires'] <= time() ) { echo '<p>This link is ' . ( 'cancelled' === $d['state'] ? 'cancelled' : 'expired' ) . '. Prepare a new order if the customer still wants to proceed.</p>'; }
	elseif ( $existing['conflicts'] ) { echo '<div class="notice notice-warning inline"><p><strong>A subscription for a selected product already exists.</strong> Use its existing renewal workflow above. This prepared link cannot be sent or used while that subscription is active or awaiting resolution.</p></div>'; }
	else {
		if ( $d['sent_at'] ) { echo '<p>Last email attempt: ' . esc_html( wp_date( 'M j, Y g:i a', $d['sent_at'] ) ) . '. ' . esc_html( 'accepted' === ( $d['mail_state'] ?? '' ) ? 'Accepted by the mail service; delivery is not confirmed.' : 'Delivery not confirmed — inspect the email log before retrying.' ) . '</p>'; }
		echo '<div class="wo-payment-choices"><div><h3>Adam enters the card</h3>';
		if ( wave_orders_has_subscription( $d ) ) { echo '<p>Recurring products use the customer payment link so WooCommerce can create the subscription schedule and capture the customer’s consent.</p>'; }
		else { wave_orders_form( 'create_wc_order', $id ); echo '<label class="wo-confirm"><input type="checkbox" name="reviewed" value="yes" required> I checked the customer, billing address, products, coupons, affiliate and existing orders.</label><button class="button button-primary">Create order for card payment</button></form><p>This creates the pending order and then shows Stripe’s secure card window.</p>'; }
		echo '</div><div><h3>Customer pays from the link</h3>'; wave_orders_form( 'send', $id ); echo '<label class="wo-confirm"><input type="checkbox" name="reviewed" value="yes" required> I checked the recipient, products and existing orders. This is a new purchase.</label><button class="button">' . ( $d['sent_at'] ? 'Resend payment link' : 'Send payment link' ) . '</button></form><p>Link recipient: <strong>' . esc_html( $d['email'] ) . '</strong>. Link expires ' . esc_html( wp_date( 'M j, Y g:i a', $d['expires'] ) ) . '.</p></div></div><details><summary>Copy the customer payment link</summary><p>Treat this as a private customer link.</p><input id="wo-link" readonly aria-label="Secure payment link" value="' . esc_attr( wave_orders_payment_url( $id, $d ) ) . '"><button type="button" class="button" id="wo-copy">Copy link</button></details>';
	}
	if ( ! $d['order_id'] && 'cancelled' !== $d['state'] ) { echo '<hr><p>Need different items or a corrected email? Cancel this link and prepare a new order.</p>'; wave_orders_form( 'cancel', $id ); echo '<button class="button">Cancel prepared link</button></form>'; }
	echo '</section>';
}
