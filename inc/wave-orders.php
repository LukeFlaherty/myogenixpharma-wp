<?php
/** Staff prepared orders. The existing checkout owns payment, subscriptions and patient intake. */
defined( 'ABSPATH' ) || exit;
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
	wp_enqueue_style( 'wave-orders', get_stylesheet_directory_uri() . '/assets/css/wave-orders.css', array( 'wave-affiliates' ), '1.0.0' );
	wp_enqueue_script( 'wave-orders', get_stylesheet_directory_uri() . '/assets/js/wave-orders.js', array(), '1.0.0', true );
	wp_localize_script( 'wave-orders', 'waveOrders', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'wave_orders_search' ) ) );
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
	wp_send_json_success( array_map( function ( $u ) {
		$c = new WC_Customer( $u->ID );
		return array( 'id' => $u->ID, 'label' => $u->display_name . ' · ' . $u->user_email . ' (#' . $u->ID . ')', 'email' => $u->user_email, 'first_name' => $c->get_billing_first_name() ?: $c->get_first_name(), 'last_name' => $c->get_billing_last_name() ?: $c->get_last_name() );
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
	return array( 'customer_id' => $uid, 'email' => $email, 'first_name' => $first, 'last_name' => $last, 'items' => $items, 'affiliate_id' => $affiliate, 'coupons' => $coupons, 'state' => 'draft', 'created_by' => get_current_user_id(), 'created' => time(), 'expires' => time() + 7 * DAY_IN_SECONDS, 'key' => wp_generate_password( 40, false, false ), 'order_id' => 0, 'sent_at' => 0 );
}

function wave_orders_send( $id, $d ) {
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
	if ( ! $accepted ) { wave_orders_fail( 'The email service did not accept this message. The prepared order is saved; retry from its detail screen.' ); }
}

function wave_orders_handle() {
	wave_orders_authorize();
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'POST required.', '', array( 'response' => 405 ) ); }
	check_admin_referer( 'wave_orders_action' ); $id = absint( wave_orders_input( 'draft_id' ) ); $lock = null;
	try {
		$op = wave_orders_input( 'operation' );
		if ( 'create' === $op ) {
			$request = wave_orders_input( 'request_id' );
			if ( ! preg_match( '/^[a-f0-9-]{36}$/', $request ) ) { wave_orders_fail( 'Reload the form and try again.' ); }
			$lock = wave_orders_lock( 'create_' . get_current_user_id() );
			$prior = get_posts( array( 'post_type' => 'wave_order_draft', 'post_status' => 'private', 'meta_key' => '_wave_order_request', 'meta_value' => get_current_user_id() . ':' . $request, 'numberposts' => 1 ) );
			if ( $prior ) { $id = $prior[0]->ID; }
			else {
				$input = array(); foreach ( array( 'email', 'customer_id', 'first_name', 'last_name', 'affiliate_id', 'coupons' ) as $key ) { $input[ $key ] = wave_orders_input( $key ); }
				$input['items'] = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
				$d = wave_orders_prepare( $input );
				$id = wp_insert_post( array( 'post_type' => 'wave_order_draft', 'post_status' => 'private', 'post_title' => 'Prepared order — ' . wp_date( 'Y-m-d H:i' ), 'post_author' => get_current_user_id(), 'meta_input' => array( '_wave_order' => $d, '_wave_order_request' => get_current_user_id() . ':' . $request ) ), true );
				if ( is_wp_error( $id ) || ! $id ) { wave_orders_fail( 'The order could not be saved. Please retry.' ); }
			}
		} else {
			$lock = wave_orders_lock( $id ); $d = wave_orders_get( $id );
			if ( ! $d ) { wave_orders_fail( 'Prepared order not found.' ); }
			if ( 'send' === $op ) {
				if ( 'yes' !== wave_orders_input( 'reviewed' ) ) { wave_orders_fail( 'Confirm that you reviewed the recipient, products and existing orders.' ); }
				wave_orders_send( $id, $d );
			}
			elseif ( 'cancel' === $op ) {
				if ( $d['order_id'] ) { wave_orders_fail( 'Checkout already created an order. Review that order before cancelling anything.' ); }
				$d['state'] = 'cancelled'; update_post_meta( $id, '_wave_order', $d );
				add_post_meta( $id, '_wave_order_audit', array( 'at' => gmdate( 'c' ), 'by' => get_current_user_id(), 'action' => 'cancelled' ) );
			} else { wave_orders_fail( 'Unknown action.' ); }
		}
	} catch ( RuntimeException $e ) {
		if ( $lock ) { wave_orders_unlock( $lock ); }
		wp_die( esc_html( $e->getMessage() ) . ( $id ? ' <a href="' . esc_url( wave_orders_url( $id ) ) . '">Return to prepared order</a>' : '' ), 'Order needs review', array( 'back_link' => true, 'response' => 400 ) );
	}
	if ( $lock ) { wave_orders_unlock( $lock ); }
	wp_safe_redirect( wave_orders_url( $id ) ); exit;
}
