<?php
/** Staff prepared orders. The existing checkout owns payment, subscriptions and patient intake. */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/wave-orders-logging.php';
require_once __DIR__ . '/wave-orders-view.php';
require_once __DIR__ . '/wave-orders-checkout.php';

add_action( 'init', function () {
	register_post_type( 'wave_order_draft', array( 'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'can_export' => false, 'supports' => array( 'title' ) ) );
} );
add_action( 'admin_menu', function () {
	$GLOBALS['wave_orders_hook'] = add_submenu_page( 'wave-trt', 'Create an order', 'Create an order', 'manage_woocommerce', 'wave-orders', 'wave_orders_render' );
}, 16 );
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( $hook !== ( $GLOBALS['wave_orders_hook'] ?? '' ) ) { return; }
	wp_enqueue_style( 'wave-trt', get_stylesheet_directory_uri() . '/assets/css/wave-trt-dashboard.css', array(), '1.1.1' );
	wp_enqueue_style( 'wave-affiliates', get_stylesheet_directory_uri() . '/assets/css/wave-affiliates.css', array( 'wave-trt' ), '1.2.0' );
	wp_enqueue_style( 'wave-orders', get_stylesheet_directory_uri() . '/assets/css/wave-orders.css', array( 'wave-affiliates' ), '1.1.0' );
	wp_enqueue_script( 'wave-orders', get_stylesheet_directory_uri() . '/assets/js/wave-orders.js', array(), '1.1.0', true );
	wp_localize_script( 'wave-orders', 'waveOrders', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'wave_orders_search' ) ) );
	$id = isset( $_GET['draft'] ) && is_scalar( $_GET['draft'] ) ? absint( $_GET['draft'] ) : 0;
	$d = $id ? wave_orders_get( $id ) : false;
	$order = $d && ! empty( $d['order_id'] ) ? wc_get_order( $d['order_id'] ) : false;
	if ( $order && class_exists( 'WC_Stripe_Admin_Order_Metaboxes' ) && $order->has_status( array( 'pending', 'failed' ) ) ) {
		WC_Stripe_Admin_Order_Metaboxes::enqueue_scripts( $order );
		wp_enqueue_style( 'wc-stripe-admin-style' );
	}
} );
add_action( 'admin_init', function () { if ( 'wave-orders' === ( $_GET['page'] ?? '' ) ) { nocache_headers(); } } );
add_action( 'admin_post_wave_orders_action', 'wave_orders_handle' );
add_action( 'wp_ajax_wave_orders_customer_search', 'wave_orders_customer_search' );

function wave_orders_authorize() {
	if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_shop_orders' ) ) { wp_die( 'You do not have permission to prepare orders.', '', array( 'response' => 403 ) ); }
}
function wave_orders_input( $key ) { return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; }
function wave_orders_url( $id = 0 ) { return add_query_arg( array( 'page' => 'wave-orders', 'draft' => $id ), admin_url( 'admin.php' ) ); }
function wave_orders_fail( $message ) { throw new RuntimeException( $message ); }
function wave_orders_get( $id ) {
	$p = get_post( $id );
	return $p && 'wave_order_draft' === $p->post_type && 'private' === $p->post_status ? get_post_meta( $id, '_wave_order', true ) : false;
}
function wave_orders_lock( $id ) {
	global $wpdb; $key = 'wave_orders_' . get_current_blog_id() . '_' . $id;
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $key ) ) ) { wave_orders_fail( 'This order is being updated. Please try again.' ); }
	return $key;
}
function wave_orders_unlock( $key ) { global $wpdb; $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) ); }
function wave_orders_token( $id, $d ) { return hash_hmac( 'sha256', $id . '|' . $d['key'] . '|' . $d['expires'], wp_salt( 'auth' ) ); }
function wave_orders_payment_url( $id, $d ) { return add_query_arg( array( 'wave_order' => $id, 'key' => wave_orders_token( $id, $d ) ), home_url( '/' ) ); }

function wave_orders_validate_items( $rows ) {
	if ( ! is_array( $rows ) || count( $rows ) > 10 ) { wave_orders_fail( 'Choose up to 10 product lines.' ); }
	$items = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) || empty( $row['product'] ) ) { continue; }
		$id = absint( $row['product'] ); $qty = (string) ( $row['quantity'] ?? '' );
		if ( ! preg_match( '/^[1-9][0-9]?$/', $qty ) ) { wave_orders_fail( 'Quantity must be a whole number between 1 and 99.' ); }
		$items[ $id ] = ( $items[ $id ] ?? 0 ) + (int) $qty;
	}
	if ( ! $items ) { wave_orders_fail( 'Choose at least one product and its exact option.' ); }
	$result = array();
	foreach ( $items as $id => $qty ) {
		$p = wc_get_product( $id ); $parent = $p && $p->get_parent_id() ? wc_get_product( $p->get_parent_id() ) : $p;
		if ( ! $p || ! $parent || 'publish' !== $parent->get_status() || 'publish' !== $p->get_status() || ! $p->is_type( array( 'simple', 'variation', 'subscription', 'subscription_variation' ) ) || ! $p->is_purchasable() ) { wave_orders_fail( 'A selected product is unavailable. Choose a published product with an exact option.' ); }
		if ( $p->is_type( array( 'variation', 'subscription_variation' ) ) && in_array( '', $p->get_variation_attributes(), true ) ) { wave_orders_fail( 'This option requires an additional choice. Use the product page to select all options.' ); }
		if ( $qty > 99 || ! $p->is_in_stock() || ! $p->has_enough_stock( $qty ) || ( $p->is_sold_individually() && $qty > 1 ) ) { wave_orders_fail( $p->get_name() . ': the selected quantity is unavailable.' ); }
		$result[] = array( 'product' => $id, 'quantity' => $qty, 'name' => $p->get_name(), 'price' => $p->get_price(), 'subscription' => function_exists( 'wcs_is_subscription_product' ) ? wcs_is_subscription_product( $p ) : $p->is_type( array( 'subscription', 'subscription_variation' ) ) );
	}
	return $result;
}

function wave_orders_customer_search() {
	wave_orders_authorize(); check_ajax_referer( 'wave_orders_search', 'nonce' );
	$q = isset( $_GET['q'] ) && is_string( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	if ( strlen( $q ) < 3 ) { wp_send_json_success( array() ); }
	$users = get_users( array( 'search' => '*' . $q . '*', 'search_columns' => array( 'user_email', 'display_name', 'user_login' ), 'number' => 15, 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );
	$by_meta = get_users( array( 'number' => 15, 'fields' => array( 'ID', 'display_name', 'user_email' ), 'meta_query' => array( 'relation' => 'OR', array( 'key' => 'billing_first_name', 'value' => $q, 'compare' => 'LIKE' ), array( 'key' => 'billing_last_name', 'value' => $q, 'compare' => 'LIKE' ), array( 'key' => 'billing_phone', 'value' => $q, 'compare' => 'LIKE' ) ) ) );
	$indexed = array(); foreach ( array_merge( $users, $by_meta ) as $u ) { $indexed[ $u->ID ] = $u; } $users = array_slice( array_values( $indexed ), 0, 15 );
	wave_orders_log( 'debug', 'customer_search_complete', array( 'query_length' => strlen( $q ), 'result_count' => count( $users ) ) );
	wp_send_json_success( array_map( function ( $u ) {
		$c = new WC_Customer( $u->ID );
		return array( 'id' => $u->ID, 'label' => $u->display_name . ' · ' . $u->user_email . ' (#' . $u->ID . ')', 'email' => $u->user_email, 'first_name' => $c->get_billing_first_name() ?: $c->get_first_name(), 'last_name' => $c->get_billing_last_name() ?: $c->get_last_name(), 'phone' => $c->get_billing_phone(), 'address_1' => $c->get_billing_address_1(), 'address_2' => $c->get_billing_address_2(), 'city' => $c->get_billing_city(), 'state' => $c->get_billing_state(), 'postcode' => $c->get_billing_postcode(), 'country' => $c->get_billing_country() ?: 'US' );
	}, $users ) );
}

function wave_orders_prepare( $input ) {
	$email = sanitize_email( $input['email'] ?? '' );
	if ( ! is_email( $email ) ) { wave_orders_fail( 'Enter the customer’s valid email address.' ); }
	$uid = absint( $input['customer_id'] ?? 0 ); $user = get_user_by( 'email', $email );
	if ( $uid && ( ! $user || (int) $user->ID !== $uid ) ) { wave_orders_fail( 'The selected customer and email do not match. Search again or choose a new customer.' ); }
	$uid = $user ? (int) $user->ID : 0;
	$first = sanitize_text_field( $input['first_name'] ?? '' ); $last = sanitize_text_field( $input['last_name'] ?? '' );
	if ( ! $first || ! $last ) { wave_orders_fail( 'Enter the customer’s first and last name.' ); }
	$address = array();
	foreach ( array( 'phone', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ) as $key ) { $address[ $key ] = sanitize_text_field( $input[ $key ] ?? '' ); }
	$address['country'] = strtoupper( $address['country'] ?: 'US' );
	if ( ! isset( WC()->countries->get_countries()[ $address['country'] ] ) ) { wave_orders_fail( 'Choose a valid billing country.' ); }
	$items = wave_orders_validate_items( $input['items'] ?? array() );
	$affiliate = absint( $input['affiliate_id'] ?? 0 );
	if ( $affiliate ) {
		if ( ! function_exists( 'wave_aff_ready' ) || ! wave_aff_ready() ) { wave_orders_fail( 'Affiliate tools are unavailable.' ); }
		$a = affwp_get_affiliate( $affiliate ); $u = $a ? get_userdata( $a->user_id ) : false;
		if ( ! $a || 'active' !== $a->status || ( $uid && (int) $a->user_id === $uid ) || ( $u && 0 === strcasecmp( $u->user_email, $email ) ) ) { wave_orders_fail( 'Choose an active affiliate who is not the customer.' ); }
	}
	$coupons = array_values( array_unique( array_filter( array_map( 'wc_format_coupon_code', explode( ',', $input['coupons'] ?? '' ) ) ) ) );
	if ( count( $coupons ) > 5 ) { wave_orders_fail( 'Use at most five coupon codes.' ); }
	foreach ( $coupons as $code ) {
		$coupon = new WC_Coupon( $code );
		if ( ! $coupon->get_id() ) { wave_orders_fail( 'Coupon not found: ' . $code ); }
		$aid = (int) get_post_meta( $coupon->get_id(), 'affwp_discount_affiliate', true );
		if ( $affiliate && $aid && $aid !== $affiliate ) { wave_orders_fail( 'This coupon belongs to a different affiliate. Resolve attribution before preparing the order.' ); }
	}
	return array( 'customer_id' => $uid, 'email' => $email, 'first_name' => $first, 'last_name' => $last, 'address' => $address, 'items' => $items, 'affiliate_id' => $affiliate, 'coupons' => $coupons, 'state' => 'draft', 'created_by' => get_current_user_id(), 'created' => time(), 'expires' => time() + 7 * DAY_IN_SECONDS, 'key' => wp_generate_password( 40, false, false ), 'order_id' => 0, 'sent_at' => 0 );
}

function wave_orders_has_subscription( $d ) {
	foreach ( $d['items'] as $item ) { if ( ! empty( $item['subscription'] ) ) { return true; } }
	return false;
}

/** Match the installed Prescribery checkout's consultation-only amount for an admin-created order. */
function wave_orders_prescribery_totals( $d ) {
	$result = array( 'fee' => 0.0, 'expected' => 0.0, 'expected_discount' => 0.0, 'synced' => array() );
	if ( ! get_option( 'prewoo_charge_consultation_only', 0 ) ) { return $result; }
	$fees = get_option( 'prewoo_consultation_fees', array() ); $fee_amounts = array();
	foreach ( $d['items'] as $item ) {
		$p = wc_get_product( $item['product'] ); if ( ! $p ) { continue; }
		$pid = $p->get_parent_id() ?: $p->get_id();
		if ( 'yes' !== strtolower( trim( (string) get_post_meta( $pid, '_pre_woo_sync', true ) ) ) ) { continue; }
		$result['synced'][ $p->get_id() ] = true;
		$result['expected'] += (float) $p->get_price() * (int) $item['quantity'];
		$fid = get_post_meta( $pid, '_prewoo_fee_id', true );
		foreach ( $fees as $fee ) { if ( (string) ( $fee['id'] ?? '' ) === (string) $fid ) { $fee_amounts[] = (float) ( $fee['amount'] ?? 0 ); } }
	}
	foreach ( $d['coupons'] as $code ) {
		$coupon = new WC_Coupon( $code );
		if ( '1' === (string) get_post_meta( $coupon->get_id(), '_prewoo_waive_consultation_fee', true ) ) { $fee_amounts = array(); }
		$type = $coupon->get_discount_type(); $amount = (float) $coupon->get_amount();
		if ( 'percent' === $type ) { $result['expected_discount'] += $result['expected'] * $amount / 100; }
		elseif ( 'fixed_cart' === $type ) { $result['expected_discount'] += min( $amount, $result['expected'] ); }
		elseif ( 'fixed_product' === $type ) { foreach ( $d['items'] as $item ) { if ( isset( $result['synced'][ $item['product'] ] ) ) { $result['expected_discount'] += $amount * (int) $item['quantity']; } } }
	}
	$result['expected_discount'] = min( $result['expected'], max( 0, $result['expected_discount'] ) );
	$result['fee'] = $fee_amounts ? max( $fee_amounts ) : 0;
	return $result;
}

function wave_orders_create_wc_order( $id, $d ) {
	wave_orders_log( 'info', 'admin_order_create_started', array( 'draft_id' => $id, 'customer_id' => (int) $d['customer_id'], 'affiliate_id' => (int) $d['affiliate_id'], 'items' => array_map( function ( $item ) { return array( 'product_id' => (int) $item['product'], 'quantity' => (int) $item['quantity'] ); }, $d['items'] ), 'coupons' => $d['coupons'] ) );
	if ( ! empty( $d['order_id'] ) ) { wave_orders_fail( 'This prepared order already has a WooCommerce order.' ); }
	if ( 'cancelled' === $d['state'] ) { wave_orders_fail( 'This prepared order is cancelled.' ); }
	if ( wave_orders_has_subscription( $d ) ) { wave_orders_fail( 'Recurring subscription products must use the customer payment link so WooCommerce can create the subscription schedule and consent record.' ); }
	wave_orders_validate_items( $d['items'] );
	$address = (array) ( $d['address'] ?? array() );
	foreach ( array( 'address_1' => 'street address', 'city' => 'city', 'state' => 'state / region', 'postcode' => 'postal code', 'country' => 'country' ) as $key => $label ) { if ( empty( $address[ $key ] ) ) { wave_orders_fail( 'Add the customer’s billing ' . $label . ' before creating an order for card payment.' ); } }
	$order = wc_create_order( array( 'customer_id' => (int) $d['customer_id'], 'status' => 'pending', 'created_via' => 'wave-order-desk' ) );
	if ( is_wp_error( $order ) ) { wave_orders_fail( $order->get_error_message() ); }
	wave_orders_log( 'info', 'woocommerce_order_record_created', array( 'draft_id' => $id, 'order_id' => $order->get_id(), 'customer_id' => (int) $d['customer_id'] ) );
	try {
		$order->set_address( array_merge( $address, array( 'first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'email' => $d['email'] ) ), 'billing' );
		$order->set_address( array( 'first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'address_1' => $address['address_1'], 'address_2' => $address['address_2'] ?? '', 'city' => $address['city'], 'state' => $address['state'], 'postcode' => $address['postcode'], 'country' => $address['country'] ), 'shipping' );
		$prewoo = wave_orders_prescribery_totals( $d );
		wave_orders_log( 'info', 'pricing_rules_calculated', array( 'draft_id' => $id, 'order_id' => $order->get_id(), 'consultation_fee' => $prewoo['fee'], 'expected_after_approval' => $prewoo['expected'], 'expected_discount' => $prewoo['expected_discount'], 'synced_product_ids' => array_keys( $prewoo['synced'] ) ) );
		foreach ( $d['items'] as $item ) {
			$product = wc_get_product( $item['product'] ); $args = array();
			if ( isset( $prewoo['synced'][ $product->get_id() ] ) ) { $args = array( 'subtotal' => 0, 'total' => 0 ); }
			$order->add_product( $product, (int) $item['quantity'], $args );
		}
		if ( $prewoo['fee'] > 0 ) { $fee = new WC_Order_Item_Fee(); $fee->set_name( 'Consultation Fee' ); $fee->set_amount( $prewoo['fee'] ); $fee->set_total( $prewoo['fee'] ); $order->add_item( $fee ); }
		foreach ( $d['coupons'] as $code ) { $result = $order->apply_coupon( $code ); if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Coupon could not be applied: ' . $code . '. ' . $result->get_error_message() ); } wave_orders_log( 'info', 'coupon_applied', array( 'draft_id' => $id, 'order_id' => $order->get_id(), 'coupon' => $code ) ); }
		if ( $prewoo['expected'] > 0 ) { $order->update_meta_data( '_prewoo_expected_product_original', $prewoo['expected'] ); $order->update_meta_data( '_prewoo_expected_discount', $prewoo['expected_discount'] ); $order->update_meta_data( '_prewoo_expected_product_charge', max( 0, $prewoo['expected'] - $prewoo['expected_discount'] ) ); }
		$order->update_meta_data( '_wave_order_draft', $id ); $order->update_meta_data( '_wave_order_affiliate', $d['affiliate_id'] ); $order->update_meta_data( '_wave_order_prepared_by', $d['created_by'] );
		$order->calculate_totals(); $order->save();
		wave_orders_log( 'info', 'order_totals_ready', wave_orders_log_order_context( $order, array( 'line_count' => count( $order->get_items() ), 'coupon_count' => count( $order->get_coupon_codes() ), 'fee_total' => $order->get_total_fees(), 'discount_total' => $order->get_discount_total(), 'tax_total' => $order->get_total_tax(), 'shipping_total' => $order->get_shipping_total() ) ) );
		if ( (float) $order->get_total() <= 0 ) { throw new RuntimeException( 'The amount due now is zero. Review the product and consultation-fee setup before attempting a card payment.' ); }
		if ( function_exists( 'affiliate_wp' ) ) {
			$integration = affiliate_wp()->integrations->get( 'woocommerce' );
			if ( ! is_wp_error( $integration ) && is_callable( array( $integration, 'add_pending_referral' ) ) ) {
				$GLOBALS['wave_order_admin_affiliate'] = (int) $d['affiliate_id'];
				$integration->add_pending_referral( $order->get_id() );
				unset( $GLOBALS['wave_order_admin_affiliate'] );
				$referral = function_exists( 'affwp_get_referral_by' ) ? affwp_get_referral_by( 'reference', $order->get_id(), 'woocommerce' ) : false;
				$has_referral = is_object( $referral ) && ! is_wp_error( $referral ) && isset( $referral->referral_id );
				wave_orders_log( $d['affiliate_id'] && ! $has_referral ? 'warning' : 'info', 'affiliate_referral_evaluated', array( 'draft_id' => $id, 'order_id' => $order->get_id(), 'affiliate_id' => (int) $d['affiliate_id'], 'referral_id' => $has_referral ? (int) $referral->referral_id : 0, 'referral_status' => $has_referral ? $referral->status : 'none' ) );
			}
		} elseif ( $d['affiliate_id'] ) { wave_orders_log( 'warning', 'affiliate_integration_unavailable', array( 'draft_id' => $id, 'order_id' => $order->get_id(), 'affiliate_id' => (int) $d['affiliate_id'] ) ); }
		$order->add_order_note( 'Created in Wave Consulting prepared order #' . $id . ' for secure Stripe card entry by staff.' );
		$d['order_id'] = $order->get_id(); $d['state'] = 'submitted'; update_post_meta( $id, '_wave_order', $d );
		add_post_meta( $id, '_wave_order_audit', array( 'at' => gmdate( 'c' ), 'by' => get_current_user_id(), 'action' => 'admin_order_created', 'order_id' => $order->get_id() ) );
		wave_orders_log( 'info', 'admin_order_ready_for_stripe', wave_orders_log_order_context( $order ) );
		return $order;
	} catch ( Throwable $e ) {
		wave_orders_log( 'error', 'admin_order_create_failed', array( 'draft_id' => $id, 'order_id' => $order->get_id(), 'exception' => get_class( $e ), 'message' => $e->getMessage(), 'file' => wp_basename( $e->getFile() ), 'line' => $e->getLine() ) );
		$order->update_status( 'cancelled', 'Wave order creation stopped before payment: ' . $e->getMessage() );
		throw new RuntimeException( $e->getMessage() );
	}
}

function wave_orders_send( $id, $d ) {
	wave_orders_log( 'info', 'payment_link_send_started', array( 'draft_id' => $id, 'customer_id' => (int) $d['customer_id'], 'previous_attempt' => (bool) $d['sent_at'] ) );
	if ( $d['order_id'] || 'cancelled' === $d['state'] || $d['expires'] <= time() ) { wave_orders_fail( 'This link is no longer available. Review the order status below.' ); }
	if ( time() - (int) $d['sent_at'] < 60 ) { wave_orders_fail( 'A send was just attempted. Wait one minute before resending.' ); }
	wave_orders_validate_items( $d['items'] );
	$d['sent_at'] = time(); $d['mail_state'] = 'sending'; update_post_meta( $id, '_wave_order', $d );
	$url = wave_orders_payment_url( $id, $d );
	$message = '<p>Hello ' . esc_html( $d['first_name'] ) . ',</p><p>Myogenix has prepared your order. Use the secure link below to review your items, confirm your details and complete checkout.</p><p><a href="' . esc_url( $url ) . '">Review order and complete checkout</a></p><p>Your final total, any consultation fees and recurring charges will be shown before you submit. Prescription medication remains subject to the required intake and provider approval.</p><p>This link expires ' . esc_html( wp_date( 'F j, Y', $d['expires'] ) ) . '. If you already have an account, sign in using this email address. Please do not reply with card information.</p>';
	$accepted = wp_mail( $d['email'], 'Your secure Myogenix order link', $message, array( 'Content-Type: text/html; charset=UTF-8' ) );
	$d['mail_state'] = $accepted ? 'accepted' : 'failed'; $d['state'] = $accepted ? 'sent' : 'draft';
	update_post_meta( $id, '_wave_order', $d );
	add_post_meta( $id, '_wave_order_audit', array( 'at' => gmdate( 'c' ), 'by' => get_current_user_id(), 'action' => $accepted ? 'mail_accepted' : 'mail_failed' ) );
	wave_orders_log( $accepted ? 'info' : 'error', 'payment_link_send_finished', array( 'draft_id' => $id, 'mail_accepted' => $accepted ) );
	if ( ! $accepted ) { wave_orders_fail( 'The email service did not accept this message. The prepared order is saved; retry from its detail screen.' ); }
}

function wave_orders_handle() {
	wave_orders_authorize();
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'POST required.', '', array( 'response' => 405 ) ); }
	check_admin_referer( 'wave_orders_action' ); $id = absint( wave_orders_input( 'draft_id' ) ); $lock = null;
	try {
		$op = wave_orders_input( 'operation' );
		wave_orders_log( 'info', 'admin_action_started', array( 'operation' => $op, 'draft_id' => $id ) );
		if ( 'create' === $op ) {
			$request = wave_orders_input( 'request_id' );
			if ( ! preg_match( '/^[a-f0-9-]{36}$/', $request ) ) { wave_orders_fail( 'Reload the form and try again.' ); }
			$lock = wave_orders_lock( 'create_' . get_current_user_id() );
			$prior = get_posts( array( 'post_type' => 'wave_order_draft', 'post_status' => 'private', 'meta_key' => '_wave_order_request', 'meta_value' => get_current_user_id() . ':' . $request, 'numberposts' => 1 ) );
			if ( $prior ) { $id = $prior[0]->ID; }
			else {
				$input = array(); foreach ( array( 'email', 'customer_id', 'first_name', 'last_name', 'phone', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'affiliate_id', 'coupons' ) as $key ) { $input[ $key ] = wave_orders_input( $key ); }
				$input['items'] = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
				$d = wave_orders_prepare( $input );
				$id = wp_insert_post( array( 'post_type' => 'wave_order_draft', 'post_status' => 'private', 'post_title' => 'Prepared order — ' . wp_date( 'Y-m-d H:i' ), 'post_author' => get_current_user_id(), 'meta_input' => array( '_wave_order' => $d, '_wave_order_request' => get_current_user_id() . ':' . $request ) ), true );
				if ( is_wp_error( $id ) || ! $id ) { wave_orders_fail( 'The order could not be saved. Please retry.' ); }
				wave_orders_log( 'info', 'prepared_order_created', array( 'draft_id' => $id, 'customer_id' => (int) $d['customer_id'], 'affiliate_id' => (int) $d['affiliate_id'], 'product_ids' => array_map( function ( $item ) { return (int) $item['product']; }, $d['items'] ), 'coupon_count' => count( $d['coupons'] ) ) );
			}
		} else {
			$lock = wave_orders_lock( $id ); $d = wave_orders_get( $id );
			if ( ! $d ) { wave_orders_fail( 'Prepared order not found.' ); }
			if ( 'send' === $op ) {
				if ( 'yes' !== wave_orders_input( 'reviewed' ) ) { wave_orders_fail( 'Confirm that you reviewed the recipient, products and existing orders.' ); }
				wave_orders_send( $id, $d );
			}
			elseif ( 'create_wc_order' === $op ) {
				if ( 'yes' !== wave_orders_input( 'reviewed' ) ) { wave_orders_fail( 'Confirm that you reviewed the customer, address, products, amount and existing orders.' ); }
				wave_orders_create_wc_order( $id, $d );
			}
			elseif ( 'cancel' === $op ) {
				if ( $d['order_id'] ) { wave_orders_fail( 'Checkout already created an order. Review that order before cancelling anything.' ); }
				$d['state'] = 'cancelled'; update_post_meta( $id, '_wave_order', $d );
				add_post_meta( $id, '_wave_order_audit', array( 'at' => gmdate( 'c' ), 'by' => get_current_user_id(), 'action' => 'cancelled' ) );
				wave_orders_log( 'notice', 'prepared_order_cancelled', array( 'draft_id' => $id ) );
			} else { wave_orders_fail( 'Unknown action.' ); }
		}
		wave_orders_log( 'info', 'admin_action_finished', array( 'operation' => $op, 'draft_id' => $id ) );
	} catch ( Throwable $e ) {
		wave_orders_log( 'error', 'admin_action_failed', array( 'operation' => $op ?? 'unknown', 'draft_id' => $id, 'exception' => get_class( $e ), 'message' => $e->getMessage(), 'file' => wp_basename( $e->getFile() ), 'line' => $e->getLine() ) );
		if ( $lock ) { wave_orders_unlock( $lock ); }
		wp_die( esc_html( $e->getMessage() ) . ( $id ? ' <a href="' . esc_url( wave_orders_url( $id ) ) . '">Return to prepared order</a>' : '' ), 'Order needs review', array( 'back_link' => true, 'response' => 400 ) );
	}
	if ( $lock ) { wave_orders_unlock( $lock ); }
	wp_safe_redirect( wave_orders_url( $id ) ); exit;
}
