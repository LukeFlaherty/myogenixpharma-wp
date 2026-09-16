<?php
/** Authenticated affiliate corrections and immutable payout proposals. */
defined( 'ABSPATH' ) || exit;
add_action( 'admin_post_wave_aff_action', 'wave_aff_handle_action' );
add_action( 'admin_post_wave_aff_export', 'wave_aff_export' );
function wave_aff_input( $key ) { return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; }
function wave_aff_fail( $message ) { throw new RuntimeException( $message ); }
function wave_aff_authorize() {
	if ( ! wave_aff_allowed() ) { wp_die( 'You do not have permission to manage affiliate operations.', '', array( 'response' => 403 ) ); }
	if ( ! wave_aff_ready() ) { wp_die( 'AffiliateWP, Lifetime Commissions and WooCommerce must be active.' ); }
}
/** Read canonical links directly: the installed add-on ignores its customer query argument. */
function wave_aff_current_links( $cid ) {
	global $wpdb; $db = affiliate_wp_lifetime_commissions()->lifetime_customers;
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$db->table_name} WHERE affwp_customer_id = %d ORDER BY lifetime_customer_id", $cid ) );
}
function wave_aff_resolve_order_customer( $order ) {
	$by_user = $order->get_customer_id() ? affwp_get_customer_by( 'user_id', $order->get_customer_id() ) : false;
	$by_email = affwp_get_customer_by( 'email', $order->get_billing_email() );
	$by_user = is_wp_error( $by_user ) ? false : $by_user; $by_email = is_wp_error( $by_email ) ? false : $by_email;
	if ( $by_user && $by_email && (int) $by_user->customer_id !== (int) $by_email->customer_id ) { wave_aff_fail( 'Customer account and email match different AffiliateWP customers. Resolve the identity conflict in AffiliateWP first.' ); }
	if ( $by_email && $by_email->user_id && $order->get_customer_id() && (int) $by_email->user_id !== $order->get_customer_id() ) { wave_aff_fail( 'This email belongs to a different customer account. Review the identity before linking.' ); }
	return $by_user ?: $by_email;
}
function wave_aff_validate_affiliate( $aid, $uid, $email ) {
	$a = affwp_get_affiliate( $aid );
	if ( ! $a || 'active' !== $a->status ) { wave_aff_fail( 'Choose an active affiliate.' ); }
	$user = get_userdata( $a->user_id );
	if ( ( $uid && (int) $a->user_id === (int) $uid ) || ( $user && strtolower( $user->user_email ) === strtolower( $email ) ) ) { wave_aff_fail( 'Self-referrals cannot be assigned here.' ); }
	if ( ! affiliate_wp()->settings->get( 'lifetime_commissions' ) && ! affwp_get_affiliate_meta( $aid, 'affwp_lc_enabled', true ) ) { wave_aff_fail( 'Lifetime attribution is not enabled for this affiliate. Enable it in AffiliateWP first.' ); }
	return $a;
}
function wave_aff_change_link( $target, $expected_cid, $revision, $aid, $reason ) {
	if ( strlen( trim( $reason ) ) < 5 || strlen( $reason ) > 1500 ) { wave_aff_fail( 'Record a reason or evidence for this attribution change (at least 5 characters).' ); }
	$customer = false; $order = false;
	if ( preg_match( '/^c([1-9]\d*)$/', $target, $m ) ) { $customer = affwp_get_customer( (int) $m[1] ); }
	elseif ( preg_match( '/^o([1-9]\d*)$/', $target, $m ) ) {
		$order = wc_get_order( (int) $m[1] );
		if ( ! $order || 'shop_order' !== $order->get_type() || wave_trt_test_record( $order ) ) { wave_aff_fail( 'Order is unavailable.' ); }
		$customer = wave_aff_resolve_order_customer( $order );
	} else { wave_aff_fail( 'Invalid customer selection.' ); }
	if ( is_wp_error( $customer ) ) { $customer = false; }
	if ( ! $customer && ! $order ) { wave_aff_fail( 'Customer no longer exists.' ); }
	$cid = $customer ? (int) $customer->customer_id : 0;
	if ( $cid !== $expected_cid ) { wave_aff_fail( 'Customer attribution changed. Reload the page before saving.' ); }
	$links = $cid ? wave_aff_current_links( $cid ) : array();
	if ( ! hash_equals( wave_aff_link_revision( $links ), $revision ) ) { wave_aff_fail( 'Another change was made. Reload and review the current affiliate.' ); }
	if ( count( $links ) > 1 ) { wave_aff_fail( 'Multiple lifetime links exist for this customer. Resolve the duplicate records in AffiliateWP first.' ); }
	$email = $customer ? $customer->email : $order->get_billing_email(); $uid = $customer ? $customer->user_id : $order->get_customer_id();
	if ( $aid ) { wave_aff_validate_affiliate( $aid, $uid, $email ); }
	$before = $links ? (int) $links[0]->affiliate_id : 0;
	if ( $before === $aid ) { wave_aff_fail( 'The selected attribution is already in place.' ); }
	if ( ! $cid ) {
		if ( ! is_email( $email ) ) { wave_aff_fail( 'A valid customer billing email is required.' ); }
		$cid = affwp_add_customer( array( 'user_id' => $uid, 'email' => $email, 'first_name' => $order->get_billing_first_name(), 'last_name' => $order->get_billing_last_name() ) );
		if ( ! $cid || is_wp_error( $cid ) ) { wave_aff_fail( 'Customer could not be created. Check whether this email is already an AffiliateWP customer alias.' ); }
	}
	$db = affiliate_wp_lifetime_commissions()->lifetime_customers;
	if ( ! $aid ) {
		// The add-on removes email aliases when unlinking. Preserve identity aliases for future relinking.
		$aliases = affwp_get_customer_meta( $cid, 'affwp_lc_customer_email', false );
		$ok = $db->delete( $links[0]->lifetime_customer_id );
		if ( $ok ) { foreach ( $aliases as $alias ) { affwp_add_customer_meta( $cid, 'affwp_lc_customer_email', $alias ); } }
	} elseif ( $links ) { $ok = $db->update( $links[0]->lifetime_customer_id, array( 'affiliate_id' => $aid ), '', 'lifetime_customer' ); }
	else { $ok = $db->add( array( 'affwp_customer_id' => $cid, 'affiliate_id' => $aid ) ); }
	if ( ! $ok ) { wave_aff_fail( 'Attribution could not be saved. Reload and inspect the current link before retrying.' ); }
	wp_cache_set( 'last_changed', microtime(), $db->cache_group );
	$event = array( 'at' => gmdate( 'c' ), 'by' => get_current_user_id(), 'before' => $before, 'after' => $aid, 'reason' => $reason );
	affwp_add_customer_meta( $cid, '_wave_aff_link_audit', $event );
	return 'c' . $cid;
}
function wave_aff_create_referral( $oid, $aid, $amount, $reason ) {
	$order = wc_get_order( $oid ); $units = wave_aff_units( $amount );
	if ( ! $order || 'shop_order' !== $order->get_type() || wave_trt_test_record( $order ) ) { wave_aff_fail( 'Order is unavailable.' ); }
	if ( null === $units || $units <= 0 || $units > wave_aff_units( $order->get_total() ) ) { wave_aff_fail( 'Enter a positive commission no greater than the order total, with at most four decimal places.' ); }
	if ( strlen( trim( $reason ) ) < 5 || strlen( $reason ) > 1500 ) { wave_aff_fail( 'Record the agreed rate/calculation and evidence for this commission.' ); }
	$affiliate = wave_aff_validate_affiliate( $aid, $order->get_customer_id(), $order->get_billing_email() );
	$customer = wave_aff_resolve_order_customer( $order );
	$links = $customer ? wave_aff_current_links( $customer->customer_id ) : array();
	if ( count( $links ) !== 1 || (int) $links[0]->affiliate_id !== $aid ) { wave_aff_fail( 'Assign and verify the customer’s affiliate before creating a commission.' ); }
	$existing = affiliate_wp()->referrals->get_referrals( array( 'reference' => (string) $oid, 'context' => 'woocommerce', 'number' => 1 ) );
	if ( $existing ) { wave_aff_fail( 'This order already has a referral. Review it in AffiliateWP instead of creating a duplicate.' ); }
	$test = (object) array( 'status' => 'unpaid', 'payout_id' => 0, 'amount' => $amount, 'currency' => $order->get_currency(), 'context' => 'woocommerce' );
	$blocked = wave_aff_review_reason( $test, $affiliate, $order, false );
	if ( $blocked ) { wave_aff_fail( $blocked ); }
	// The installed API requires both customer and customer_id, and converts input dates using its current offset.
	$rid = affiliate_wp()->referrals->add( array( 'customer_id' => $customer->customer_id, 'customer' => array( 'email' => $customer->email ), 'affiliate_id' => $aid, 'amount' => wave_aff_amount( $units ), 'order_total' => $order->get_total(), 'reference' => (string) $oid, 'currency' => $order->get_currency(), 'context' => 'woocommerce', 'date' => gmdate( 'Y-m-d H:i:s', $order->get_date_paid()->getTimestamp() + (int) affiliate_wp()->utils->wp_offset ), 'status' => 'pending', 'description' => 'Wave Consulting attribution correction — review required' ) );
	if ( ! $rid ) { wave_aff_fail( 'Referral could not be created. Check AffiliateWP before retrying.' ); }
	affwp_add_referral_meta( $rid, '_wave_aff_correction', array( 'at' => gmdate( 'c' ), 'by' => get_current_user_id(), 'reason' => $reason ) );
	$order->add_order_note( 'Wave Consulting: pending AffiliateWP referral #' . $rid . ' created for affiliate #' . $aid . '. Reason: ' . $reason, false, true );
	return $rid;
}
function wave_aff_report_summary( $rows ) {
	$result = array();
	foreach ( $rows as $r ) {
		$key = $r['affiliate_id'] . ':' . $r['currency'];
		if ( ! isset( $result[ $key ] ) ) { $result[ $key ] = array( 'affiliate_id' => $r['affiliate_id'], 'affiliate' => $r['affiliate'], 'currency' => $r['currency'], 'payable' => 0, 'held' => 0, 'carryover' => 0, 'count' => 0 ); }
		$result[ $key ]['count']++; $units = wave_aff_units( $r['amount'] );
		if ( null === $units ) { continue; }
		$bucket = 'Proposed payable' === $r['decision'] ? 'payable' : 'held';
		$result[ $key ][ $bucket ] += $units;
		if ( 'payable' === $bucket && 'Prior-month carryover' === $r['period'] ) { $result[ $key ]['carryover'] += $units; }
	}
	return array_values( $result );
}
function wave_aff_save_report( $month ) {
	$data = wave_aff_load( $month );
	if ( $data['limited'] ) { wave_aff_fail( 'The data safety limit was reached. Expand the loader before generating a complete payout report.' ); }
	$id = wp_insert_post( array( 'post_type' => 'wave_aff_report', 'post_status' => 'private', 'post_title' => 'Affiliate proposal ' . $month . ' — ' . wp_date( 'Y-m-d H:i:s' ), 'post_author' => get_current_user_id() ), true );
	if ( is_wp_error( $id ) || ! $id ) { wave_aff_fail( 'Report could not be saved.' ); }
	$snapshot = array( 'version' => 1, 'month' => $month, 'generated_at' => gmdate( 'c' ), 'generated_by' => get_current_user_id(), 'timezone' => wp_timezone_string(), 'basis' => 'Current unpaid and pending referrals earned through selected month; includes prior-month carryover. Proposal only, evaluated at generation time. Paid and rejected referrals excluded. Amounts come from AffiliateWP; missing commissions are not estimated.', 'rows' => $data['report'], 'summary' => wave_aff_report_summary( $data['report'] ), 'totals' => $data['totals'], 'unresolved_lifetime_links' => $data['orphan'], 'orders_without_referrals' => count( $data['missing'] ) );
	if ( ! add_post_meta( $id, '_wave_aff_snapshot', $snapshot, true ) ) { wp_delete_post( $id, true ); wave_aff_fail( 'Snapshot could not be saved. Please retry.' ); }
	return $id;
}
function wave_aff_handle_action() {
	wave_aff_authorize();
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'POST required.', '', array( 'response' => 405 ) ); }
	check_admin_referer( 'wave_aff_action' );
	global $wpdb; $lock = 'wave_aff_operations_' . get_current_blog_id();
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) { wp_die( 'Another affiliate operation is running. Please try again.' ); }
	$month = wave_aff_month( wave_aff_input( 'month' ) ); $args = array( 'month' => $month, 'view' => 'customers' ); $error = '';
	try {
		$op = wave_aff_input( 'operation' );
		if ( 'report' === $op ) { $args['report'] = wave_aff_save_report( $month ); $args['view'] = 'reports'; }
		elseif ( 'link' === $op ) {
			if ( 'yes' !== wave_aff_input( 'confirmed' ) ) { wave_aff_fail( 'Confirm that you verified this attribution change.' ); }
			$args['target'] = wave_aff_change_link( wave_aff_input( 'target' ), absint( wave_aff_input( 'customer_id' ) ), wave_aff_input( 'revision' ), absint( wave_aff_input( 'affiliate_id' ) ), wave_aff_input( 'reason' ) );
		} elseif ( 'referral' === $op ) {
			if ( 'yes' !== wave_aff_input( 'confirmed' ) ) { wave_aff_fail( 'Confirm the commission amount and attribution evidence.' ); }
			wave_aff_create_referral( absint( wave_aff_input( 'order_id' ) ), absint( wave_aff_input( 'affiliate_id' ) ), wave_aff_input( 'amount' ), wave_aff_input( 'reason' ) ); $args['view'] = 'review';
		} else { wave_aff_fail( 'Unknown operation.' ); }
	} catch ( RuntimeException $e ) { $error = $e->getMessage(); }
	finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
	if ( $error ) { wp_die( esc_html( $error ), 'Affiliate operation needs review', array( 'back_link' => true, 'response' => 400 ) ); }
	$args['saved'] = '1'; wp_safe_redirect( wave_aff_url( $args ) ); exit;
}
function wave_aff_csv_cell( $value ) {
	$value = (string) $value;
	return preg_match( '/^[\s\x00-\x1f]*[=+@-]|^[\t\r\n]/u', $value ) ? "'" . $value : $value;
}
function wave_aff_export() {
	wave_aff_authorize(); $id = isset( $_GET['report'] ) && is_scalar( $_GET['report'] ) ? absint( $_GET['report'] ) : 0;
	check_admin_referer( 'wave_aff_export_' . $id );
	$post = get_post( $id ); $data = get_post_meta( $id, '_wave_aff_snapshot', true );
	if ( ! $post || 'wave_aff_report' !== $post->post_type || 'private' !== $post->post_status || ! is_array( $data ) ) { wp_die( 'Report unavailable.', '', array( 'response' => 404 ) ); }
	$summary = isset( $_GET['format'] ) && 'summary' === $_GET['format'];
	nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Disposition: attachment; filename="wave-affiliates-' . $data['month'] . '-' . $id . ( $summary ? '-summary' : '-detail' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	$write = function( $row ) use ( $out ) { fputcsv( $out, array_map( 'wave_aff_csv_cell', $row ), ',', '"', '' ); };
	$write( array( 'Report', $id, 'Month', $data['month'], 'Generated UTC', $data['generated_at'], 'Timezone', $data['timezone'] ) );
	$write( array( 'Basis', $data['basis'] ) );
	$write( array( 'Unresolved lifetime links', $data['unresolved_lifetime_links'], 'Paid orders without referrals (not automatically owed)', $data['orders_without_referrals'] ) );
	if ( $summary ) {
		$write( array( 'Affiliate ID', 'Affiliate', 'Currency', 'Proposed payable', 'Of which prior-month carryover', 'Held for review', 'Referral count' ) );
		foreach ( $data['summary'] as $r ) { $write( array( $r['affiliate_id'], $r['affiliate'], $r['currency'], wave_aff_amount( $r['payable'] ), wave_aff_amount( $r['carryover'] ), wave_aff_amount( $r['held'] ), $r['count'] ) ); }
	} else {
		$write( array( 'Referral ID', 'Affiliate ID', 'Affiliate', 'Order reference', 'Earned UTC', 'Currency', 'Amount', 'Source status', 'Decision', 'Reason', 'Period' ) );
		foreach ( $data['rows'] as $r ) { $write( array_values( $r ) ); }
	}
	fclose( $out ); exit;
}
