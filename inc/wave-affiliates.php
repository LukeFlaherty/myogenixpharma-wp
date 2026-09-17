<?php
/** Affiliate operations: AffiliateWP is the financial and attribution source of truth. */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/wave-affiliate-actions.php';
require_once __DIR__ . '/wave-affiliate-view.php';
require_once __DIR__ . '/wave-affiliate-workflow.php';
add_action( 'admin_menu', function () {
	$GLOBALS['wave_aff_hook'] = add_submenu_page( 'wave-trt', 'Affiliates', 'Affiliates', 'manage_options', 'wave-affiliates', 'wave_aff_render' );
}, 20 );
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( $hook !== ( $GLOBALS['wave_aff_hook'] ?? '' ) ) { return; }
	wp_enqueue_style( 'wave-trt', get_stylesheet_directory_uri() . '/assets/css/wave-trt-dashboard.css', array(), '1.1.0' );
	wp_enqueue_style( 'wave-affiliates', get_stylesheet_directory_uri() . '/assets/css/wave-affiliates.css', array( 'wave-trt' ), '1.1.0' );
	wp_enqueue_script( 'wave-affiliates', get_stylesheet_directory_uri() . '/assets/js/wave-affiliates.js', array(), '1.1.0', true );
} );
add_action( 'admin_init', function () { if ( isset( $_GET['page'] ) && 'wave-affiliates' === $_GET['page'] ) { nocache_headers(); } } );
add_action( 'init', function () { register_post_type( 'wave_aff_report', array( 'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'can_export' => false, 'supports' => array( 'title' ) ) ); } );
function wave_aff_allowed() { return current_user_can( 'manage_options' ) && current_user_can( 'manage_woocommerce' ) && current_user_can( 'manage_affiliates' ); }
function wave_aff_ready() { return function_exists( 'affiliate_wp' ) && function_exists( 'affiliate_wp_lifetime_commissions' ) && function_exists( 'wc_get_orders' ); }
function wave_aff_month( $value ) {
	if ( ! is_string( $value ) || ! preg_match( '/^20\d{2}-(0[1-9]|1[0-2])$/', $value ) ) { return wp_date( 'Y-m' ); }
	return $value;
}
function wave_aff_bounds( $month ) {
	$start = new DateTimeImmutable( $month . '-01 00:00:00', wp_timezone() );
	return array( $start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ), $start->modify( '+1 month' )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) );
}
/** Fixed-point arithmetic; never sum monetary floats. */
function wave_aff_units( $amount ) {
	if ( ! preg_match( '/^(-?)(\d+)(?:\.(\d{1,4}))?$/', (string) $amount, $m ) ) { return null; }
	if ( strlen( $m[2] ) > 10 ) { return null; }
	return ( '-' === $m[1] ? -1 : 1 ) * ( (int) $m[2] * 10000 + (int) str_pad( $m[3] ?? '', 4, '0' ) );
}
function wave_aff_amount( $units ) {
	$negative = $units < 0; $units = abs( $units );
	$decimal = rtrim( str_pad( (string) ( $units % 10000 ), 4, '0', STR_PAD_LEFT ), '0' );
	return ( $negative ? '-' : '' ) . intdiv( $units, 10000 ) . '.' . str_pad( $decimal, 2, '0' );
}
function wave_aff_money( $totals ) {
	$labels = array(); foreach ( $totals as $currency => $units ) { $labels[] = $currency . ' ' . wave_aff_amount( $units ); }
	return $labels ? implode( ' / ', $labels ) : '—';
}
function wave_aff_sum( &$totals, $currency, $units ) { $totals[ $currency ] = ( $totals[ $currency ] ?? 0 ) + $units; }
function wave_aff_url( $args = array() ) { return add_query_arg( array_merge( array( 'page' => 'wave-affiliates' ), $args ), admin_url( 'admin.php' ) ); }
function wave_aff_native( $page, $args = array() ) { return add_query_arg( array_merge( array( 'page' => 'affiliate-wp-' . $page ), $args ), admin_url( 'admin.php' ) ); }
function wave_aff_customer_orders( $customer, $orders ) {
	return array_values( array_filter( $orders, function ( $o ) use ( $customer ) { if ( $customer->user_id && $o->get_customer_id() ) { return (int) $customer->user_id === $o->get_customer_id(); } return $customer->email && strtolower( $customer->email ) === strtolower( $o->get_billing_email() ); } ) );
}
function wave_aff_link_revision( $links ) {
	$values = array(); foreach ( $links as $l ) { $values[] = $l->lifetime_customer_id . ':' . $l->affiliate_id; } sort( $values ); return hash( 'sha256', implode( '|', $values ) );
}
function wave_aff_links_for( $id, $links ) { return array_values( array_filter( $links, function ( $l ) use ( $id ) { return (int) $l->affwp_customer_id === (int) $id; } ) ); }
function wave_aff_affiliate_name( $affiliate ) {
	$user = get_userdata( $affiliate->user_id ); return $user ? $user->display_name : 'Affiliate #' . $affiliate->affiliate_id;
}
/** Explain eligibility; source referral statuses and amounts remain unchanged. */
function wave_aff_review_reason( $ref, $affiliate, $order, $duplicate ) {
	if ( 'unpaid' !== $ref->status ) { return 'Source status: ' . $ref->status; }
	if ( ! $affiliate || 'active' !== $affiliate->status ) { return 'Affiliate not active'; }
	if ( ! empty( $ref->payout_id ) ) { return 'Already assigned to a payout'; }
	if ( null === wave_aff_units( $ref->amount ) || wave_aff_units( $ref->amount ) <= 0 ) { return 'Zero or invalid commission — review calculation'; }
	if ( ! preg_match( '/^[A-Z]{3}$/', $ref->currency ) ) { return 'Currency needs review'; }
	if ( $ref->currency === affwp_get_currency() ) {
		$precision = max( 0, min( 4, (int) affwp_get_decimal_count() ) );
		if ( 0 !== wave_aff_units( $ref->amount ) % ( 10 ** ( 4 - $precision ) ) ) { return 'Commission exceeds payout currency precision — reconcile rounding in AffiliateWP'; }
	} else { return 'Currency differs from AffiliateWP payout currency — reconcile separately'; }
	if ( 'woocommerce' !== $ref->context ) { return 'Non-WooCommerce referral — review source'; }
	if ( ! $order || 'shop_order' !== $order->get_type() ) { return 'Order missing or unavailable'; }
	if ( $duplicate ) { return 'Multiple referrals on this order — review split / duplicate'; }
	if ( $order->has_status( array( 'refunded', 'cancelled', 'failed', 'rejected', 'trash' ) ) ) { return 'Order ' . $order->get_status(); }
	if ( (float) $order->get_total_refunded() > 0 ) { return 'Order has a refund — reconcile commission'; }
	if ( ! $order->get_transaction_id() || ! $order->get_date_paid() || (float) $order->get_total() <= 0 ) { return 'Order payment not confirmed'; }
	if ( ! $order->has_status( array( 'processing', 'completed', 'approved' ) ) ) { return 'Order status needs review'; }
	return '';
}
function wave_aff_load( $month ) {
	global $wpdb;
	$app = affiliate_wp(); $lc = affiliate_wp_lifetime_commissions()->lifetime_customers;
	$affiliates = $app->affiliates->get_affiliates( array( 'number' => 1001 ) );
	$customers = $app->customers->get_customers( array( 'number' => 10001 ) );
	$referrals = $app->referrals->get_referrals( array( 'number' => 10001 ) );
	$links = $lc->get_lifetime_customers( array( 'number' => 10001 ) );
	$limited = count( $affiliates ) > 1000 || count( $customers ) > 10000 || count( $referrals ) > 10000 || count( $links ) > 10000;
	$affmap = array(); $board = array(); $custmap = array(); $order_refs = array(); $orders = array(); $people = array();
	foreach ( $affiliates as $a ) { $id = (int) $a->affiliate_id; $affmap[ $id ] = $a; $board[ $id ] = array( 'name' => wave_aff_affiliate_name( $a ), 'status' => $a->status, 'visits' => 0, 'converted' => 0, 'linked' => 0, 'referrals' => 0, 'earned' => array(), 'pending' => array(), 'payable' => array() ); }
	foreach ( $referrals as $r ) { if ( 'woocommerce' === $r->context ) { $order_refs[ (int) $r->reference ][] = $r; } }
	for ( $page = 1; $page <= 20; $page++ ) {
		$result = wc_get_orders( array( 'type' => 'shop_order', 'status' => array_keys( wc_get_order_statuses() ), 'limit' => 100, 'page' => $page, 'paginate' => true, 'orderby' => 'date', 'order' => 'DESC' ) );
		foreach ( $result->orders as $o ) { if ( ! wave_trt_test_record( $o ) ) { $orders[ $o->get_id() ] = $o; } }
		if ( $page >= $result->max_num_pages ) { break; } if ( 20 === $page ) { $limited = true; }
	}
	foreach ( $customers as $c ) {
		$custmap[ $c->customer_id ] = $c;
		$related = wave_aff_customer_orders( $c, $orders ); $cl = wave_aff_links_for( $c->customer_id, $links );
		$name = trim( $c->first_name . ' ' . $c->last_name ) ?: ( $related ? $related[0]->get_formatted_billing_full_name() : $c->email );
		$people[ 'c' . $c->customer_id ] = array( 'name' => $name, 'email' => $c->email, 'customer' => $c, 'orders' => $related, 'links' => $cl );
		foreach ( array_unique( array_map( function( $l ){ return (int) $l->affiliate_id; }, $cl ) ) as $id ) { if ( isset( $board[ $id ] ) ) { $board[ $id ]['linked']++; } }
	}
	$matched = array(); foreach ( $people as $p ) { foreach ( $p['orders'] as $o ) { $matched[ $o->get_id() ] = true; } }
	$unregistered = array();
	foreach ( $orders as $o ) {
		if ( isset( $matched[ $o->get_id() ] ) ) { continue; }
		$key = wave_trt_patient_key( $o );
		if ( isset( $unregistered[ $key ] ) ) { $people[ $unregistered[ $key ] ]['orders'][] = $o; continue; }
		$target = 'o' . $o->get_id(); $unregistered[ $key ] = $target;
		$people[ $target ] = array( 'name' => $o->get_formatted_billing_full_name(), 'email' => $o->get_billing_email(), 'customer' => null, 'orders' => array( $o ), 'links' => array() );
	}
	$orphan = 0; foreach ( $links as $l ) { if ( ! isset( $custmap[ $l->affwp_customer_id ] ) ) { $orphan++; } }
	list( $start, $end ) = wave_aff_bounds( $month );
	// Aggregate traffic only: no visitor IPs, URLs or patient browsing history fetched.
	$traffic = $wpdb->get_results( $wpdb->prepare( "SELECT affiliate_id, COUNT(*) visits, SUM(referral_id > 0) converted FROM {$app->visits->table_name} WHERE date >= %s AND date < %s GROUP BY affiliate_id", $start, $end ) );
	foreach ( $traffic as $t ) { if ( isset( $board[ $t->affiliate_id ] ) ) { $board[ $t->affiliate_id ]['visits'] = (int) $t->visits; $board[ $t->affiliate_id ]['converted'] = (int) $t->converted; } }
	$report = array(); $totals = array( 'payable' => array(), 'held' => array(), 'carryover' => array() );
	foreach ( $referrals as $r ) {
		$units = wave_aff_units( $r->amount ); $currency = $r->currency ?: 'UNKNOWN'; $id = (int) $r->affiliate_id;
		$in_month = $r->date >= $start && $r->date < $end;
		if ( $in_month && isset( $board[ $id ] ) ) {
			$board[ $id ]['referrals']++;
			if ( null !== $units && in_array( $r->status, array( 'unpaid', 'paid' ), true ) ) { wave_aff_sum( $board[ $id ]['earned'], $currency, $units ); }
			if ( null !== $units && 'pending' === $r->status ) { wave_aff_sum( $board[ $id ]['pending'], $currency, $units ); }
		}
		// An as-of-now report of unpaid balances earned through the selected month, not a historical balance reconstruction.
		if ( $r->date >= $end || ! in_array( $r->status, array( 'unpaid', 'pending' ), true ) ) { continue; }
		$order = 'woocommerce' === $r->context ? ( $orders[ (int) $r->reference ] ?? wc_get_order( absint( $r->reference ) ) ) : false;
		$probe = clone $r; if ( 'pending' === $probe->status ) { $probe->status = 'unpaid'; }
		$reason = wave_aff_review_reason( $probe, $affmap[ $id ] ?? null, $order, count( $order_refs[ (int) $r->reference ] ?? array() ) > 1 );
		if ( ! $reason && 'pending' === $r->status ) { $reason = 'Source status: pending'; }
		if ( $order && wave_trt_test_record( $order ) ) { $reason = 'Test order excluded'; }
		$bucket = $reason ? 'held' : 'payable';
		if ( null !== $units ) { wave_aff_sum( $totals[ $bucket ], $currency, $units ); }
		if ( ! $reason && isset( $board[ $id ] ) ) { wave_aff_sum( $board[ $id ]['payable'], $currency, $units ); if ( ! $in_month ) { wave_aff_sum( $totals['carryover'], $currency, $units ); } }
		$report[] = array( 'referral_id' => (int) $r->referral_id, 'affiliate_id' => $id, 'affiliate' => $board[ $id ]['name'] ?? 'Unknown affiliate', 'order' => $r->reference, 'date' => $r->date, 'currency' => $currency, 'amount' => null === $units ? (string) $r->amount : wave_aff_amount( $units ), 'source_status' => $r->status, 'decision' => $reason ? 'Review' : 'Proposed payable', 'reason' => $reason ?: 'Unpaid commission with confirmed order payment; verify before payout', 'period' => $in_month ? 'Selected month' : 'Prior-month carryover' );
	}
	$missing = array();
	foreach ( $people as $target => $p ) { foreach ( $p['orders'] as $o ) { if ( ! isset( $order_refs[ $o->get_id() ] ) && $o->get_transaction_id() && (float) $o->get_total() > 0 && ! $o->has_status( array( 'cancelled', 'refunded', 'failed', 'rejected' ) ) ) { $missing[] = array( 'target' => $target, 'person' => $p, 'order' => $o ); } } }
	// Rank by recorded earned commission in USD (current store currency); other currencies remain separate in every total.
	$rank_currency = get_woocommerce_currency();
	uasort( $board, function( $a, $b ) use ( $rank_currency ) { return ( ( $b['earned'][ $rank_currency ] ?? 0 ) <=> ( $a['earned'][ $rank_currency ] ?? 0 ) ) ?: ( $b['visits'] <=> $a['visits'] ); } );
	return compact( 'affmap', 'board', 'customers', 'links', 'people', 'orders', 'order_refs', 'report', 'totals', 'limited', 'orphan', 'missing', 'month', 'rank_currency' );
}
