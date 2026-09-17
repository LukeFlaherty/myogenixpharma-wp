<?php
/** Plain-language issue routing and individual commission resolution. */
defined( 'ABSPATH' ) || exit;
function wave_aff_issue( $row ) {
	$r = $row['reason'];
	if ( 'Proposed payable' === $row['decision'] ) { return array( 'ready', 'Ready for payout review', 'Check the payout history before including this commission in a payment.' ); }
	if ( false !== strpos( $r, 'precision' ) ) { return array( 'rounding', 'Amount needs rounding', 'Confirm the agreed commission, enter the final amount to the currency’s precision, then save the correction.' ); }
	if ( false !== strpos( $r, 'Zero or invalid' ) ) { return array( 'amount', 'Commission amount missing', 'Check the affiliate agreement and enter the correct commission, or reject it if no commission is owed.' ); }
	if ( false !== strpos( $r, 'refund' ) || preg_match( '/Order (cancelled|failed|rejected|trash)/', $r ) ) { return array( 'refund', 'Refund or cancelled order', 'Open the order and reconcile the refund. Reject a commission that is no longer owed; partial refunds need an agreed adjustment.' ); }
	if ( false !== strpos( $r, 'payment' ) || false !== strpos( $r, 'Order status' ) ) { return array( 'payment', 'Verify customer payment', 'Open the order to confirm payment and order status. Fix the source order first; do not approve solely because a commission exists.' ); }
	if ( false !== strpos( $r, 'pending' ) ) { return array( 'approval', 'Waiting for approval', 'Verify the affiliate and commission amount, then approve it as unpaid or reject it with a reason.' ); }
	if ( false !== strpos( $r, 'Affiliate not active' ) ) { return array( 'affiliate', 'Affiliate account needs review', 'Open the affiliate profile and confirm whether the account should be active. Then refresh this queue.' ); }
	if ( false !== strpos( $r, 'Multiple referrals' ) ) { return array( 'duplicate', 'Check duplicate commissions', 'Compare all referrals on this order in AffiliateWP. Resolve duplicate or split records before payout.' ); }
	if ( false !== strpos( $r, 'payout' ) ) { return array( 'payout', 'Already in a payout', 'Check the existing payout before doing anything else. This record is protected from edits here.' ); }
	return array( 'other', 'Source record needs review', 'Open the source referral and order, correct the missing or conflicting information, then refresh this queue.' );
}
function wave_aff_issue_groups( $rows ) {
	$groups = array();
	foreach ( $rows as $row ) {
		if ( 'Review' !== $row['decision'] ) { continue; }
		list( $key, $label, $help ) = wave_aff_issue( $row );
		if ( ! isset( $groups[ $key ] ) ) { $groups[ $key ] = array( 'label' => $label, 'help' => $help, 'count' => 0, 'totals' => array() ); }
		$groups[ $key ]['count']++; $units = wave_aff_units( $row['amount'] );
		if ( null !== $units ) { wave_aff_sum( $groups[ $key ]['totals'], $row['currency'], $units ); }
	}
	uasort( $groups, function( $a, $b ) { return $b['count'] <=> $a['count']; } );
	return $groups;
}
/** Summary display only. Source amounts and saved exports retain full precision. */
function wave_aff_display_money( $totals ) {
	if ( ! $totals ) { $totals = array( affwp_get_currency() => 0 ); }
	$labels = array();
	foreach ( $totals as $currency => $units ) {
		$dp = $currency === affwp_get_currency() ? max( 0, min( 4, (int) affwp_get_decimal_count() ) ) : 2;
		$factor = 10 ** ( 4 - $dp ); $rounded = intdiv( abs( $units ) + intdiv( $factor, 2 ), $factor ) * $factor * ( $units < 0 ? -1 : 1 );
		$labels[] = ( $rounded !== $units ? '≈ ' : '' ) . $currency . ' ' . number_format( $rounded / 10000, $dp, '.', ',' );
	}
	return implode( ' / ', $labels );
}
function wave_aff_month_payable( $totals ) {
	$result = $totals['payable']; foreach ( $totals['carryover'] as $currency => $units ) { $result[ $currency ] = ( $result[ $currency ] ?? 0 ) - $units; } return $result;
}
function wave_aff_referral_revision( $ref, $order ) {
	$values = array(); foreach ( array( 'referral_id', 'affiliate_id', 'amount', 'currency', 'status', 'payout_id', 'reference', 'context' ) as $key ) { $values[] = (string) ( $ref->$key ?? '' ); }
	if ( $order ) { $values = array_merge( $values, array( $order->get_status(), $order->get_total(), $order->get_total_refunded(), $order->get_transaction_id(), $order->get_date_paid() ? $order->get_date_paid()->getTimestamp() : 0 ) ); }
	return hash( 'sha256', implode( '|', $values ) );
}
function wave_aff_resolve_referral( $rid, $revision, $decision, $amount, $reason ) {
	if ( ! current_user_can( 'manage_referrals' ) ) { wave_aff_fail( 'You do not have permission to edit referrals.' ); }
	$ref = affwp_get_referral( $rid );
	if ( ! $ref || ! in_array( $ref->status, array( 'pending', 'unpaid' ), true ) || ! empty( $ref->payout_id ) ) { wave_aff_fail( 'Only pending or unpaid commissions outside a payout can be edited here.' ); }
	$order = 'woocommerce' === $ref->context ? wc_get_order( absint( $ref->reference ) ) : false;
	if ( ! hash_equals( wave_aff_referral_revision( $ref, $order ), $revision ) ) { wave_aff_fail( 'The referral or order changed. Reload and review it before saving.' ); }
	if ( strlen( trim( $reason ) ) < 5 || strlen( $reason ) > 1500 ) { wave_aff_fail( 'Enter a reason or evidence (5–1500 characters).' ); }
	if ( ! in_array( $decision, array( 'correct', 'approve', 'reject' ), true ) ) { wave_aff_fail( 'Choose a supported review decision.' ); }
	$before = array( 'amount' => $ref->amount, 'status' => $ref->status );
	if ( 'reject' === $decision ) {
		// Reject without changing amount; the plugin reverses the original unpaid balance correctly.
		$ok = affwp_set_referral_status( $rid, 'rejected' );
	} else {
		$units = wave_aff_units( $amount );
		if ( ! $order || 'shop_order' !== $order->get_type() || wave_trt_test_record( $order ) ) { wave_aff_fail( 'Review this source order in AffiliateWP before changing a commission.' ); }
		if ( null === $units || $units <= 0 || $units > wave_aff_units( $order->get_total() ) ) { wave_aff_fail( 'Enter a positive commission no greater than the order total.' ); }
		if ( $order->get_currency() !== $ref->currency ) { wave_aff_fail( 'Order and referral currencies differ. Reconcile them in AffiliateWP first.' ); }
		$affiliate = affwp_get_affiliate( $ref->affiliate_id );
		if ( $affiliate ) {
			$user = get_userdata( $affiliate->user_id );
			if ( ( $order->get_customer_id() && (int) $affiliate->user_id === $order->get_customer_id() ) || ( $user && strtolower( $user->user_email ) === strtolower( $order->get_billing_email() ) ) ) { wave_aff_fail( 'Self-referrals require source review.' ); }
		}
		$others = affiliate_wp()->referrals->get_referrals( array( 'reference' => $ref->reference, 'context' => 'woocommerce', 'number' => 2 ) );
		$probe = clone $ref; $probe->status = 'unpaid'; $probe->amount = wave_aff_amount( $units );
		$block = wave_aff_review_reason( $probe, $affiliate, $order, count( $others ) > 1 );
		if ( $block ) { wave_aff_fail( $block . '. Resolve the source issue before approving or correcting this commission.' ); }
		if ( 'correct' === $decision && wave_aff_units( $ref->amount ) === $units ) { wave_aff_fail( 'The amount is unchanged. Choose approve or reject if that is your intended decision.' ); }
		// First correct amount at the SAME status so AffiliateWP adjusts the unpaid balance correctly.
		$ok = true;
		if ( wave_aff_units( $ref->amount ) !== $units ) { $ok = affiliate_wp()->referrals->update_referral( $rid, array( 'amount' => wave_aff_amount( $units ) ) ); }
		if ( $ok && 'approve' === $decision && 'pending' === $ref->status ) { $ok = affwp_set_referral_status( $rid, 'unpaid' ); }
	}
	$after = affwp_get_referral( $rid );
	if ( ! $after ) { wave_aff_fail( 'The source referral is no longer available. Check AffiliateWP before retrying.' ); }
	affwp_add_referral_meta( $rid, '_wave_aff_resolution', array( 'at' => gmdate( 'c' ), 'by' => get_current_user_id(), 'decision' => $decision, 'before' => $before, 'after' => array( 'amount' => $after->amount, 'status' => $after->status ), 'reason' => $reason, 'success' => (bool) $ok ) );
	if ( ! $ok ) { wave_aff_fail( 'The update did not fully complete. Reload the referral and check its recorded amount/status before retrying.' ); }
	if ( $order ) { $order->add_order_note( 'Wave Consulting: referral #' . $rid . ' reviewed (' . $decision . '). ' . $reason, false, true ); }
}
