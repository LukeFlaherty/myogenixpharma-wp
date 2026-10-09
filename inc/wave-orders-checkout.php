<?php
/** Signed prepared-order links, bound to customer identity and normal classic checkout. */
defined( 'ABSPATH' ) || exit;

function wave_orders_session_draft() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) { return false; }
	$id = absint( WC()->session->get( 'wave_order_id' ) ); $d = $id ? wave_orders_get( $id ) : false;
	return $d ? array( $id, $d ) : false;
}
function wave_orders_identity( $d, $email = '' ) {
	$u = get_user_by( 'email', $d['email'] );
	$uid = $u ? (int) $u->ID : (int) $d['customer_id'];
	if ( $uid && get_current_user_id() !== $uid ) { wave_orders_fail( 'Please sign in to the account this order was prepared for.' ); }
	if ( is_user_logged_in() && ( ! $u || get_current_user_id() !== (int) $u->ID ) ) { wave_orders_fail( 'You are signed in to a different account. Sign out and use the intended customer account.' ); }
	if ( $email && 0 !== strcasecmp( trim( $email ), $d['email'] ) ) { wave_orders_fail( 'Use the email address this order was prepared for.' ); }
}
function wave_orders_check_open( $d ) {
	if ( 'cancelled' === $d['state'] || $d['expires'] <= time() ) { wave_orders_fail( 'This order link has expired or was cancelled. Please contact Myogenix for a new link.' ); }
	if ( $d['order_id'] ) { wave_orders_fail( 'This prepared order has already been submitted. Open its original link to view the existing order.' ); }
}
function wave_orders_validate_affiliate_checkout( $d, $coupon_codes ) {
	if ( ! $d['affiliate_id'] ) { return; }
	$a = function_exists( 'affwp_get_affiliate' ) ? affwp_get_affiliate( $d['affiliate_id'] ) : false;
	if ( ! $a || 'active' !== $a->status || (int) $a->user_id === get_current_user_id() ) { wave_orders_fail( 'Myogenix needs to review the affiliate on this order before checkout.' ); }
	foreach ( $coupon_codes as $code ) {
		$c = new WC_Coupon( $code );
		$aid = (int) get_post_meta( $c->get_id(), 'affwp_discount_affiliate', true );
		if ( $aid && $aid !== (int) $d['affiliate_id'] ) { wave_orders_fail( 'This coupon conflicts with the prepared order’s affiliate. Remove the coupon or contact Myogenix.' ); }
	}
}

add_action( 'template_redirect', 'wave_orders_landing', -20 );
function wave_orders_landing() {
	if ( ! isset( $_GET['wave_order'] ) ) { return; }
	nocache_headers(); header( 'Referrer-Policy: no-referrer' ); header( 'X-Robots-Tag: noindex, nofollow' );
	$id = is_scalar( $_GET['wave_order'] ) ? absint( $_GET['wave_order'] ) : 0;
	$key = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
	$d = wave_orders_get( $id );
	if ( ! $d || ! $key || ! hash_equals( wave_orders_token( $id, $d ), $key ) ) { wp_die( 'This order link is unavailable.', 'Order link unavailable', array( 'response' => 404 ) ); }
	$error = ''; $lock = null; $existing_order = false;
	try {
		if ( 'cancelled' === $d['state'] || $d['expires'] <= time() ) { wave_orders_fail( 'This order link has expired or was cancelled. Please contact Myogenix for a new link.' ); }
		$account = get_user_by( 'email', $d['email'] );
		if ( $account && ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( wave_orders_payment_url( $id, $d ) ) ); exit;
		}
		wave_orders_identity( $d );
		if ( $d['order_id'] ) {
			$order = wc_get_order( $d['order_id'] );
			if ( $order && $order->get_customer_id() && $order->get_customer_id() === get_current_user_id() ) {
				wp_safe_redirect( $order->needs_payment() ? $order->get_checkout_payment_url() : $order->get_view_order_url() ); exit;
			}
			if ( ! $order || $order->get_customer_id() ) { wave_orders_fail( 'This order was already submitted. Check your confirmation email or contact Myogenix to review it.' ); }
			$existing_order = $order;
			if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
				check_admin_referer( 'wave_order_start_' . $id );
				wave_orders_identity( $d, wave_orders_input( 'email' ) );
				if ( ! is_email( wave_orders_input( 'email' ) ) ) { wave_orders_fail( 'Enter the email address that received this link.' ); }
				wp_safe_redirect( $order->needs_payment() ? $order->get_checkout_payment_url() : $order->get_checkout_order_received_url() ); exit;
			}
		}
		if ( ! $existing_order && 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			check_admin_referer( 'wave_order_start_' . $id );
			wave_orders_identity( $d, wave_orders_input( 'email' ) );
			if ( ! is_email( wave_orders_input( 'email' ) ) ) { wave_orders_fail( 'Enter the email address that received this link.' ); }
			$lock = wave_orders_lock( $id ); $d = wave_orders_get( $id ); wave_orders_check_open( $d );
			$items = wave_orders_validate_items( $d['items'] );
			if ( ! WC()->cart || ! WC()->session ) { wave_orders_fail( 'Checkout is temporarily unavailable. Please try again.' ); }
			// Explicit POST consent replaces this browser's basket, never on email-scanner GETs.
			WC()->cart->empty_cart();
			foreach ( $items as $row ) {
				$p = wc_get_product( $row['product'] ); $variation = $p->get_parent_id() ? $p->get_id() : 0;
				if ( ! WC()->cart->add_to_cart( $variation ? $p->get_parent_id() : $p->get_id(), $row['quantity'], $variation, $variation ? $p->get_variation_attributes() : array() ) ) { wave_orders_fail( 'A product could not be added. Please contact Myogenix to review your prepared order.' ); }
			}
			WC()->session->set( 'wave_order_id', $id ); WC()->session->set_customer_session_cookie( true );
			if ( WC()->customer ) {
				WC()->customer->set_billing_email( $d['email'] ); WC()->customer->set_billing_first_name( $d['first_name'] ); WC()->customer->set_billing_last_name( $d['last_name'] );
				WC()->customer->set_shipping_first_name( $d['first_name'] ); WC()->customer->set_shipping_last_name( $d['last_name'] ); WC()->customer->save();
			}
			foreach ( $d['coupons'] as $coupon ) { if ( ! WC()->cart->apply_coupon( $coupon ) ) { wc_add_notice( 'A prepared coupon could not be applied. Review your total before paying.', 'notice' ); } }
			WC()->cart->calculate_totals();
			wave_orders_unlock( $lock ); $lock = null;
			wp_safe_redirect( wc_get_checkout_url() ); exit;
		}
	} catch ( RuntimeException $e ) { $error = $e->getMessage(); }
	finally { if ( $lock ) { wave_orders_unlock( $lock ); } }
	// Render a minimal no-tracking page; no patient or payment information is disclosed.
	$body = '<h1>Your prepared Myogenix order</h1>';
	if ( $error ) { $body .= '<p role="alert">' . esc_html( $error ) . '</p><p><a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">My account</a></p>'; }
	elseif ( $existing_order ) {
		$body .= '<p>Your checkout already created order #' . esc_html( $existing_order->get_order_number() ) . '. Confirm the recipient email to continue the same order without creating another one.</p><form method="post">' . wp_nonce_field( 'wave_order_start_' . $id, '_wpnonce', true, false ) . '<p><label>Email address that received this link<br><input type="email" name="email" autocomplete="email" required></label></p><p><button type="submit">' . ( $existing_order->needs_payment() ? 'Continue payment' : 'View confirmation' ) . '</button></p></form>';
	} else {
		$body .= '<p>Continue to review your items, confirm your details and see the final amount before payment. Required intake and provider approval still apply.</p><p>Continuing replaces the current shopping basket in this browser with the items Myogenix prepared for you.</p><form method="post">' . wp_nonce_field( 'wave_order_start_' . $id, '_wpnonce', true, false ) . '<p><label>Email address that received this link<br><input type="email" name="email" autocomplete="email" required></label></p><p><button type="submit">Continue to secure checkout</button></p></form>';
	}
	wp_die( $body, 'Your Myogenix order', array( 'response' => $error ? 400 : 200 ) );
}

add_action( 'woocommerce_cart_emptied', function () { if ( WC()->session ) { WC()->session->__unset( 'wave_order_id' ); } } );
add_filter( 'woocommerce_checkout_get_value', function ( $value, $key ) {
	$pair = wave_orders_session_draft(); if ( ! $pair || $value ) { return $value; }
	$d = $pair[1];
	try { wave_orders_identity( $d ); } catch ( RuntimeException $e ) { return $value; }
	$fields = array( 'billing_email' => 'email', 'billing_first_name' => 'first_name', 'billing_last_name' => 'last_name' );
	return isset( $fields[ $key ] ) ? $d[ $fields[ $key ] ] : $value;
}, 20, 2 );

/** Validate before provider calls or payment; serialize the entire checkout attempt. */
add_action( 'woocommerce_before_checkout_process', 'wave_orders_checkout_guard', -100 );
function wave_orders_checkout_guard() {
	$pair = wave_orders_session_draft(); if ( ! $pair ) { return; }
	list( $id, $d ) = $pair;
	$lock = wave_orders_lock( $id );
	$GLOBALS['wave_order_checkout_lock'] = $lock;
	register_shutdown_function( function () use ( $lock ) { wave_orders_unlock( $lock ); } );
	$d = wave_orders_get( $id ); wave_orders_check_open( $d );
	wave_orders_identity( $d, wave_orders_input( 'billing_email' ) );
	$expected = array(); foreach ( $d['items'] as $row ) { $expected[ $row['product'] ] = (int) $row['quantity']; }
	$actual = array(); foreach ( WC()->cart->get_cart() as $row ) { $pid = $row['variation_id'] ?: $row['product_id']; $actual[ $pid ] = ( $actual[ $pid ] ?? 0 ) + (int) $row['quantity']; }
	ksort( $expected ); ksort( $actual );
	if ( $expected !== $actual ) { wave_orders_fail( 'Your basket changed. Open your prepared-order link again to restore the selected items, or contact Myogenix to change this order.' ); }
	wave_orders_validate_affiliate_checkout( $d, WC()->cart->get_applied_coupons() );
}

/** Blocks checkout: validate the final Store API request after addresses and payment data are present. */
add_action( 'woocommerce_store_api_checkout_update_order_from_request', function ( $order, $request ) {
	$pair = wave_orders_session_draft(); if ( ! $pair ) { return; }
	list( $id, $d ) = $pair;
	$lock = wave_orders_lock( $id ); $GLOBALS['wave_order_checkout_lock'] = $lock;
	register_shutdown_function( function () use ( $lock ) { wave_orders_unlock( $lock ); } );
	$d = wave_orders_get( $id ); wave_orders_check_open( $d ); wave_orders_identity( $d, $order->get_billing_email() );
	$expected = array(); foreach ( $d['items'] as $row ) { $expected[ $row['product'] ] = (int) $row['quantity']; }
	$actual = array(); foreach ( $order->get_items() as $item ) { $pid = $item->get_variation_id() ?: $item->get_product_id(); $actual[ $pid ] = ( $actual[ $pid ] ?? 0 ) + (int) $item->get_quantity(); }
	ksort( $expected ); ksort( $actual );
	if ( $expected !== $actual ) { wave_orders_fail( 'Your basket changed. Open your prepared-order link again or contact Myogenix to change this order.' ); }
	wave_orders_validate_affiliate_checkout( $d, $order->get_coupon_codes() );
	$order->update_meta_data( '_wave_order_draft', $id ); $order->update_meta_data( '_wave_order_affiliate', $d['affiliate_id'] ); $order->update_meta_data( '_wave_order_prepared_by', $d['created_by'] );
}, -100, 2 );

add_action( 'woocommerce_checkout_create_order', function ( $order, $data ) {
	$pair = wave_orders_session_draft(); if ( ! $pair ) { return; }
	list( $id, $d ) = $pair;
	if ( empty( $GLOBALS['wave_order_checkout_lock'] ) ) { wave_orders_fail( 'Please reload checkout before submitting this prepared order.' ); }
	wave_orders_identity( $d, $order->get_billing_email() );
	$order->update_meta_data( '_wave_order_draft', $id );
	$order->update_meta_data( '_wave_order_affiliate', $d['affiliate_id'] );
	$order->update_meta_data( '_wave_order_prepared_by', $d['created_by'] );
}, 99, 2 );

// Record the single resulting order before payment/provider status callbacks run.
add_action( 'woocommerce_checkout_update_order_meta', function ( $order_id ) {
	$order = wc_get_order( $order_id ); $id = $order ? absint( $order->get_meta( '_wave_order_draft' ) ) : 0;
	$d = $id ? wave_orders_get( $id ) : false; if ( ! $d ) { return; }
	if ( $d['order_id'] && (int) $d['order_id'] !== (int) $order_id ) { wave_orders_fail( 'This prepared order already has an order. Contact Myogenix before retrying payment.' ); }
	$d['order_id'] = $order_id; $d['state'] = 'submitted'; update_post_meta( $id, '_wave_order', $d );
	$order->add_order_note( 'Prepared in Wave Consulting (#' . $id . ') and submitted by the customer through checkout.' );
}, -100 );

add_action( 'woocommerce_store_api_checkout_order_processed', function ( $order ) {
	$id = absint( $order->get_meta( '_wave_order_draft' ) ); $d = $id ? wave_orders_get( $id ) : false;
	if ( ! $d ) { return; }
	if ( empty( $GLOBALS['wave_order_checkout_lock'] ) ) { wave_orders_fail( 'Please reload checkout before submitting this prepared order.' ); }
	if ( $d['order_id'] && (int) $d['order_id'] !== (int) $order->get_id() ) { wave_orders_fail( 'This prepared order already created another order. Contact Myogenix before retrying payment.' ); }
	$d['order_id'] = $order->get_id(); $d['state'] = 'submitted'; update_post_meta( $id, '_wave_order', $d );
	$order->add_order_note( 'Prepared in Wave Consulting (#' . $id . ') and submitted by the customer through checkout.' );
}, -100 );

// Use the native affiliate calculation; selected attribution only applies to this prepared order.
add_filter( 'affwp_get_referring_affiliate_id', function ( $aid, $reference, $context ) {
	if ( 'woocommerce' !== $context || ! $reference ) { return $aid; }
	$o = wc_get_order( $reference ); $selected = $o ? (int) $o->get_meta( '_wave_order_affiliate' ) : 0;
	return $selected ?: $aid;
}, PHP_INT_MAX, 3 );
add_filter( 'affwp_was_referred', function ( $referred ) {
	if ( ! empty( $GLOBALS['wave_order_admin_affiliate'] ) ) { return true; }
	$pair = wave_orders_session_draft();
	return $pair && ! empty( $GLOBALS['wave_order_checkout_lock'] ) && $pair[1]['affiliate_id'] ? true : $referred;
}, 100 );
