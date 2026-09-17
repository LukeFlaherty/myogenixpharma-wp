<?php
/** Wave Consulting affiliate workspace. All dynamic values escaped at output. */
defined( 'ABSPATH' ) || exit;
function wave_aff_get( $key ) { return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; }
function wave_aff_form_start( $operation, $month ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wa-form">'; wp_nonce_field( 'wave_aff_action' );
	foreach ( array( 'action' => 'wave_aff_action', 'operation' => $operation, 'month' => $month ) as $key => $value ) { wave_aff_hidden( $key, $value ); }
}
function wave_aff_hidden( $key, $value ) { echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">'; }
function wave_aff_select( $data, $selected = 0, $allow_remove = true ) {
	echo '<label>Affiliate<select name="affiliate_id" required>'; if ( $allow_remove ) { echo '<option value="0">No affiliate / remove current link</option>'; }
	foreach ( $data['affmap'] as $id => $a ) { if ( 'active' !== $a->status && $id !== $selected ) { continue; } echo '<option value="' . esc_attr( $id ) . '" ' . selected( $id, $selected, false ) . '>' . esc_html( $data['board'][ $id ]['name'] . ' (#' . $id . ') — ' . $a->status ) . '</option>'; }
	echo '</select></label>';
}
function wave_aff_link_labels( $p, $data ) {
	$labels = array(); foreach ( $p['links'] as $l ) { $labels[] = ( $data['board'][ $l->affiliate_id ]['name'] ?? 'Unknown affiliate' ) . ' (#' . $l->affiliate_id . ')'; }
	return $labels ? implode( ', ', $labels ) : 'Unassigned';
}
function wave_aff_search( $placeholder ) { echo '<label class="wa-search">Search this table <input type="search" data-wa-search placeholder="' . esc_attr( $placeholder ) . '"></label><p class="wa-search-count" aria-live="polite"></p>'; }
function wave_aff_table_start( $headers ) {
	echo '<div class="wa-table-wrap"><table class="widefat striped wa-table"><thead><tr>'; foreach ( $headers as $h ) { echo '<th scope="col">' . esc_html( $h ) . '</th>'; } echo '</tr></thead><tbody>';
}
function wave_aff_table_end() { echo '</tbody></table></div>'; }
function wave_aff_render() {
	wave_aff_authorize();
	$month = wave_aff_month( wave_aff_get( 'month' ) ); $view = wave_aff_get( 'view' ) ?: 'overview';
	$views = array( 'overview' => 'Overview', 'customers' => 'Customer links', 'review' => 'Needs attention', 'reports' => 'Monthly payout report' );
	if ( ! isset( $views[ $view ] ) ) { $view = 'overview'; }
	$data = wave_aff_load( $month );
	echo '<div class="wrap wave-trt wave-aff"><div class="wave-brand">WAVE CONSULTING <span>OPERATIONS WORKSPACE</span></div><div class="wave-heading"><div><p class="wave-eyebrow">AFFILIATE OPERATIONS</p><h1>Affiliates</h1><p>See who is driving business, fix attribution, and prepare affiliate payouts.</p></div><a class="button" href="' . esc_url( wave_aff_native( 'affiliates' ) ) . '">Manage affiliate accounts ↗</a></div>';
	echo '<nav class="wa-nav" aria-label="Affiliate workspace">'; foreach ( $views as $key => $label ) { echo '<a class="' . ( $view === $key ? 'is-current' : '' ) . '" ' . ( $view === $key ? 'aria-current="page"' : '' ) . ' href="' . esc_url( wave_aff_url( array( 'view' => $key, 'month' => $month ) ) ) . '">' . esc_html( $label ) . '</a>'; } echo '</nav>';
	echo '<form method="get" class="wa-month">'; wave_aff_hidden( 'page', 'wave-affiliates' ); wave_aff_hidden( 'view', $view ); echo '<label>Reporting month <input type="month" name="month" value="' . esc_attr( $month ) . '" required></label> <button class="button">Apply month</button><span>Site time: ' . esc_html( wp_timezone_string() ) . '. Loaded ' . esc_html( wp_date( 'M j, Y g:i a' ) ) . '.</span></form>';
	if ( '1' === wave_aff_get( 'saved' ) ) { echo '<div class="notice notice-success inline"><p>Saved successfully. Review the updated record below.</p></div>'; }
	$report_id = absint( wave_aff_get( 'report' ) );
	if ( $report_id && 'wave_aff_report' === get_post_type( $report_id ) ) { echo '<div class="wa-review-banner wa-download-ready"><div><strong>Saved report #' . esc_html( $report_id ) . ' is ready</strong><span>Download the payout proposal or its supporting detail.</span></div><div><a class="button button-primary" href="' . esc_url( wave_aff_download_url( $report_id, 'summary' ) ) . '">Who to pay · CSV</a> <a class="button" href="' . esc_url( wave_aff_download_url( $report_id, 'detail' ) ) . '">Commission detail · CSV</a></div></div>'; }
	if ( $data['limited'] ) { echo '<div class="notice notice-error inline"><p><strong>Partial data:</strong> a safety limit was reached. Counts are incomplete and report generation is disabled.</p></div>'; }

	call_user_func( 'wave_aff_view_' . $view, $data );
	echo '<p class="wave-footer">Restricted to administrators with WooCommerce and AffiliateWP management access. AffiliateWP remains the source for visits, attribution and commissions. Customer links are current; historical referrals retain their original affiliate. Reports are proposals and do not send payments or mark commissions paid.</p></div>';
}
function wave_aff_attention_links( $d ) {
	$count = count( array_filter( $d['report'], function( $r ) { return 'Review' === $r['decision']; } ) );
	echo '<div class="wa-shortcuts"><a href="' . esc_url( wave_aff_url( array( 'view' => 'review', 'month' => $d['month'] ) ) ) . '"><strong>' . esc_html( $count ) . '</strong> commissions need attention →</a><a href="' . esc_url( wave_aff_url( array( 'view' => 'review', 'month' => $d['month'], 'issue' => 'missing' ) ) ) . '"><strong>' . esc_html( count( $d['missing'] ) ) . '</strong> orders to check for a missing commission →</a><a href="' . esc_url( wave_aff_url( array( 'view' => 'reports', 'month' => $d['month'] ) ) ) . '">Prepare monthly payout report →</a></div>';
}
function wave_aff_view_overview( $d ) {
	$visits = 0; $converted = 0; $earned = array();
	foreach ( $d['board'] as $a ) { $visits += $a['visits']; $converted += $a['converted']; foreach ( $a['earned'] as $currency => $units ) { wave_aff_sum( $earned, $currency, $units ); } }
	$attached = count( array_filter( $d['people'], function( $p ){ return ! empty( $p['links'] ); } ) );
	$stats = array( 'Visits this month' => $visits, 'Visits with a referral' => $converted, 'Customers linked to affiliates' => $attached, 'Commissions earned this month' => wave_aff_display_money( $earned ) );
	echo '<div class="wa-stats">'; foreach ( $stats as $label => $value ) { echo '<div class="wa-stat"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>'; } echo '</div>';
	wave_aff_attention_links( $d );
	$all = '1' === wave_aff_get( 'all' );
	echo '<section class="wa-panel" data-wa-limit="10" data-wa-all="' . ( $all ? '1' : '0' ) . '"><div class="wa-section-head"><div><h2>Top affiliates</h2><p>Top 10 by this month’s earned commission. Search includes every affiliate.</p></div><label>Rank by <select data-wa-sort><option value="earned">Commission earned</option><option value="visits">Traffic</option><option value="linked">Customers attached</option><option value="converted">Converted visits</option></select></label></div>';
	wave_aff_search( 'Search all affiliates by name or ID' ); wave_aff_table_start( array( 'Rank / affiliate', 'Visits', 'Converted visits', 'Customers', 'Month earned', 'Actions' ) );
	$rank = 0;
	foreach ( $d['board'] as $id => $a ) {
		$rank++;
		echo '<tr data-wa-row data-earned="' . esc_attr( $a['earned'][ $d['rank_currency'] ] ?? 0 ) . '" data-visits="' . esc_attr( $a['visits'] ) . '" data-linked="' . esc_attr( $a['linked'] ) . '" data-converted="' . esc_attr( $a['converted'] ) . '"' . ( $rank > 10 && ! $all ? ' hidden' : '' ) . '><td><strong><span data-wa-rank>' . esc_html( $rank ) . '</span>. ' . esc_html( $a['name'] ) . '</strong><small>#' . esc_html( $id . ' · ' . $a['status'] ) . '</small></td><td>' . esc_html( $a['visits'] ) . '</td><td>' . esc_html( $a['converted'] ) . '</td><td><a href="' . esc_url( wave_aff_url( array( 'view' => 'customers', 'affiliate' => $id, 'month' => $d['month'] ) ) ) . '">' . esc_html( $a['linked'] ) . ' customers</a></td><td>' . esc_html( wave_aff_display_money( $a['earned'] ) ) . '</td><td><a href="' . esc_url( wave_aff_native( 'affiliates', array( 'action' => 'edit_affiliate', 'affiliate_id' => $id ) ) ) . '">Profile ↗</a><details><summary>Referral link</summary><input aria-label="Referral URL for ' . esc_attr( $a['name'] ) . '" readonly value="' . esc_attr( affwp_get_affiliate_referral_url( array( 'affiliate_id' => $id ) ) ) . '"><button type="button" class="button wa-copy">Copy URL</button></details></td></tr>';
	}
	wave_aff_table_end();
	echo '<a class="button" data-wa-expand href="' . esc_url( wave_aff_url( array( 'month' => $d['month'], 'all' => $all ? '0' : '1' ) ) ) . '">' . ( $all ? 'Show top 10' : 'Show all ' . count( $d['board'] ) . ' affiliates' ) . '</a><p class="wave-muted">Earned = paid + unpaid commissions in ' . esc_html( $d['month'] . ' / ' . $d['rank_currency'] ) . '. Traffic counts visits, not unique people. A converted visit may still have a pending commission.</p></section>';
}
function wave_aff_view_customers( $d ) {
	$target = wave_aff_get( 'target' ); $filter = wave_aff_get( 'affiliate' );
	if ( $target && isset( $d['people'][ $target ] ) ) {
		$p = $d['people'][ $target ]; $cid = $p['customer'] ? (int) $p['customer']->customer_id : 0;
		echo '<section class="wa-panel wa-editor"><h2>Edit attribution: ' . esc_html( $p['name'] ) . '</h2><p>' . esc_html( $p['email'] ) . '</p><p><strong>Current:</strong> ' . esc_html( wave_aff_link_labels( $p, $d ) ) . '</p><p>This changes the lifetime affiliate used for future qualifying purchases under your AffiliateWP settings. Existing order commissions and payouts retain their recorded attribution. Choose “No affiliate” to unlink, keeping the customer and history.</p>';
		wave_aff_form_start( 'link', $d['month'] ); wave_aff_hidden( 'target', $target ); wave_aff_hidden( 'customer_id', $cid ); wave_aff_hidden( 'revision', wave_aff_link_revision( $p['links'] ) );
		wave_aff_select( $d, $p['links'] ? (int) $p['links'][0]->affiliate_id : 0 );
		echo '<label>Reason / attribution evidence<textarea name="reason" required minlength="5" maxlength="1500" rows="3" placeholder="Example: Customer confirmed referral from affiliate; support ticket reference."></textarea></label><label class="wa-confirm"><input type="checkbox" name="confirmed" value="yes" required> I verified the customer and affiliate, and understand this does not change historical commissions.</label><button class="button button-primary">Save customer attribution</button></form>';
		if ( $cid ) {
			$history = affwp_get_customer_meta( $cid, '_wave_aff_link_audit', false );
			if ( $history ) { echo '<details><summary>Attribution change history (' . esc_html( count( $history ) ) . ')</summary>'; foreach ( array_reverse( $history ) as $event ) { if ( ! is_array( $event ) ) { continue; } $user = get_userdata( $event['by'] ); echo '<p><strong>' . esc_html( $event['at'] . ' · ' . ( $user ? $user->display_name : 'Staff #' . $event['by'] ) ) . '</strong><br>' . esc_html( 'Affiliate #' . $event['before'] . ' → #' . $event['after'] . ' (0 = unassigned). ' . $event['reason'] ) . '</p>'; } echo '</details>'; }
		}
		echo '</section>';
	}
	echo '<section class="wa-panel"><h2>Customer & patient attribution</h2><p>All product lines, including TRT. Current lifetime links are shown alongside recorded order history. A customer without a link may be a direct sale; verify attribution before assigning an affiliate.</p><div class="wa-filters"><a href="' . esc_url( wave_aff_url( array( 'view' => 'customers', 'month' => $d['month'] ) ) ) . '">All customers</a><a href="' . esc_url( wave_aff_url( array( 'view' => 'customers', 'month' => $d['month'], 'affiliate' => 'unassigned' ) ) ) . '">Unassigned only</a>';
	if ( ctype_digit( $filter ) && isset( $d['board'][ (int) $filter ] ) ) { echo '<strong>Filtered: ' . esc_html( $d['board'][ (int) $filter ]['name'] ) . '</strong>'; } echo '</div>';
	wave_aff_search( 'Customer, email, order or affiliate' ); wave_aff_table_start( array( 'Customer / patient', 'Current affiliate', 'Order history', 'Actions' ) ); $count = 0;
	foreach ( $d['people'] as $key => $p ) {
		$ids = array_map( function( $l ){ return (int) $l->affiliate_id; }, $p['links'] );
		if ( ( 'unassigned' === $filter && $ids ) || ( ctype_digit( $filter ) && ! in_array( (int) $filter, $ids, true ) ) ) { continue; } $count++;
		echo '<tr data-wa-row><td><strong>' . esc_html( $p['name'] ?: 'Unnamed customer' ) . '</strong><small>' . esc_html( $p['email'] ) . '</small><small>' . esc_html( $p['customer'] ? 'AffiliateWP customer #' . $p['customer']->customer_id : 'WooCommerce customer — no AffiliateWP profile' ) . '</small></td><td><span class="wave-badge ' . ( $ids ? 'blue' : 'amber' ) . '">' . esc_html( wave_aff_link_labels( $p, $d ) ) . '</span>' . ( count( $ids ) > 1 ? '<small>Duplicate links — source review required</small>' : '' ) . '</td><td>';
		if ( ! $p['orders'] ) { echo 'No matching orders'; }
		foreach ( $p['orders'] as $i => $o ) { if ( 3 === $i ) { echo '<details><summary>' . esc_html( count( $p['orders'] ) - 3 ) . ' more orders</summary>'; } $ref_names = array(); foreach ( $d['order_refs'][ $o->get_id() ] ?? array() as $ref ) { $ref_names[] = '#' . $ref->affiliate_id . ' · ' . $ref->status; }
			echo '<div class="wa-order"><a href="' . esc_url( $o->get_edit_order_url() ) . '">Order #' . esc_html( $o->get_order_number() ) . '</a> · ' . esc_html( wc_get_order_status_name( $o->get_status() ) ) . '<small>' . esc_html( $ref_names ? 'Recorded commissions: ' . implode( ', ', $ref_names ) : 'No referral recorded' ) . '</small></div>';
		} if ( count( $p['orders'] ) > 3 ) { echo '</details>'; }
		echo '</td><td><a class="button" href="' . esc_url( wave_aff_url( array( 'view' => 'customers', 'target' => $key, 'month' => $d['month'] ) ) ) . '">' . ( $ids ? 'Edit / unlink' : 'Assign affiliate' ) . '</a>';
		if ( $p['customer'] && $p['customer']->user_id ) { echo '<a class="wa-secondary" href="' . esc_url( get_edit_user_link( $p['customer']->user_id ) ) . '">Customer account ↗</a>'; } echo '</td></tr>';
	}
	if ( ! $count ) { echo '<tr><td colspan="4">No customers match this filter.</td></tr>'; } wave_aff_table_end(); echo '</section>';
}
function wave_aff_render_referrals( $rows, $month = null ) {
	$month = $month ?: wave_aff_month( wave_aff_get( 'month' ) );
	wave_aff_table_start( array( 'Affiliate / referral', 'Commission', 'What needs to happen', 'Action' ) );
	foreach ( $rows as $r ) {
		list( $key, $label, $help ) = wave_aff_issue( $r );
		echo '<tr data-wa-row><td><strong>' . esc_html( $r['affiliate'] ) . '</strong><small>Referral #' . esc_html( $r['referral_id'] ) . ' · Order ' . esc_html( $r['order'] ) . '</small><small>' . esc_html( substr( $r['date'], 0, 10 ) . ' · ' . ( 'Selected month' === $r['period'] ? 'This month' : 'Older balance' ) ) . '</small></td><td><strong>' . esc_html( $r['currency'] . ' ' . $r['amount'] ) . '</strong><small>' . esc_html( $r['source_status'] ) . '</small></td><td><span class="wave-badge ' . ( 'ready' === $key ? 'blue' : 'amber' ) . '">' . esc_html( $label ) . '</span><small>' . esc_html( $help ) . '</small></td><td><a class="button" href="' . esc_url( wave_aff_url( array( 'view' => 'review', 'month' => $month, 'referral' => $r['referral_id'] ) ) ) . '">Review &amp; resolve</a><a class="wa-secondary" href="' . esc_url( wave_aff_native( 'referrals', array( 'action' => 'edit_referral', 'referral_id' => $r['referral_id'] ) ) ) . '">Source referral ↗</a></td></tr>';
	}
	if ( ! $rows ) { echo '<tr><td colspan="4">No commissions match this queue.</td></tr>'; } wave_aff_table_end();
}
function wave_aff_issue_cards( $d, $affiliate = 0 ) {
	$rows = $affiliate ? array_values( array_filter( $d['report'], function( $r ) use ( $affiliate ) { return $r['affiliate_id'] === $affiliate; } ) ) : $d['report'];
	echo '<div class="wa-issue-grid">';
	foreach ( wave_aff_issue_groups( $rows ) as $key => $group ) {
		echo '<a class="wa-issue" href="' . esc_url( wave_aff_url( array( 'view' => 'review', 'month' => $d['month'], 'issue' => $key, 'affiliate' => $affiliate ?: '' ) ) ) . '"><strong>' . esc_html( $group['count'] ) . '</strong><span>' . esc_html( $group['label'] ) . '</span><small>' . esc_html( wave_aff_display_money( $group['totals'] ) ) . ' · Review →</small></a>';
	}
	echo '</div>';
}
function wave_aff_resolution_editor( $d, $rid ) {
	$ref = affwp_get_referral( $rid );
	if ( ! $ref ) { echo '<div class="notice notice-warning inline"><p>This referral is no longer available.</p></div>'; return; }
	$order = 'woocommerce' === $ref->context ? wc_get_order( absint( $ref->reference ) ) : false;
	$probe = clone $ref; $probe->status = 'unpaid';
	$other = affiliate_wp()->referrals->get_referrals( array( 'reference' => $ref->reference, 'context' => $ref->context, 'number' => 2 ) );
	$block = wave_aff_review_reason( $probe, affwp_get_affiliate( $ref->affiliate_id ), $order, count( $other ) > 1 );
	if ( ! $block && 'pending' === $ref->status ) { $block = 'Source status: pending'; }
	$row = array( 'reason' => $block, 'decision' => $block ? 'Review' : 'Proposed payable' ); list( $key, $label, $help ) = wave_aff_issue( $row );
	echo '<section class="wa-panel wa-editor"><h2>Resolve referral #' . esc_html( $rid ) . '</h2><p><strong>' . esc_html( $d['board'][ $ref->affiliate_id ]['name'] ?? 'Affiliate #' . $ref->affiliate_id ) . '</strong> · ' . esc_html( $ref->currency . ' ' . $ref->amount . ' · ' . $ref->status ) . '</p><div class="wave-notice"><strong>' . esc_html( $label ) . '</strong><p>' . esc_html( $help ) . '</p></div>';
	if ( $order ) { echo '<p><a class="button" target="_blank" rel="noopener" href="' . esc_url( $order->get_edit_order_url() ) . '">Open order #' . esc_html( $order->get_order_number() ) . ' ↗</a> ' . esc_html( 'Status: ' . $order->get_status() . ' · Total: ' . $order->get_currency() . ' ' . $order->get_total() . ' · Refunded: ' . $order->get_total_refunded() ) . '</p>'; }
	echo '<p><a target="_blank" rel="noopener" href="' . esc_url( wave_aff_native( 'referrals', array( 'action' => 'edit_referral', 'referral_id' => $rid ) ) ) . '">Open source referral ↗</a> · <a target="_blank" rel="noopener" href="' . esc_url( wave_aff_native( 'affiliates', array( 'action' => 'edit_affiliate', 'affiliate_id' => $ref->affiliate_id ) ) ) . '">Open affiliate account ↗</a></p>';
	if ( in_array( $ref->status, array( 'pending', 'unpaid' ), true ) && empty( $ref->payout_id ) && current_user_can( 'manage_referrals' ) ) {
		$can_correct = in_array( $key, array( 'ready', 'approval', 'rounding', 'amount' ), true );
		if ( ! $can_correct ) { echo '<p><strong>Fix the source issue before correcting or approving this commission.</strong> You can reject it here if you have verified that nothing is owed.</p>'; }
		wave_aff_form_start( 'resolve_referral', $d['month'] ); wave_aff_hidden( 'referral_id', $rid ); wave_aff_hidden( 'revision', wave_aff_referral_revision( $ref, $order ) );
		echo '<label>Decision<select name="decision" required><option value="">Choose an action…</option>' . ( $can_correct ? '<option value="correct">Correct amount — keep current status</option>' : '' ) . ( $can_correct && 'pending' === $ref->status ? '<option value="approve">Approve as unpaid — include when eligible</option>' : '' ) . '<option value="reject">Reject — no commission owed</option></select></label><label>Confirmed commission (' . esc_html( $ref->currency ) . ')<input type="number" name="amount" min="0" step="0.0001" value="' . esc_attr( $ref->amount ) . '"><small>Enter the agreed amount. Rejection keeps the original amount for history.</small></label><label>Reason / supporting evidence<textarea name="reason" required minlength="5" maxlength="1500" rows="3" placeholder="State the agreed calculation, payment evidence or reason this commission is not owed."></textarea></label><label class="wa-confirm"><input type="checkbox" name="confirmed" value="yes" required> I checked the order and affiliate agreement and confirm this decision.</label><button class="button button-primary">Save review decision</button><p class="wave-muted">Approval records an unpaid commission. Payment happens separately. Saved reports retain their original values; generate a new report after changes.</p></form>';
	} else { echo '<p>This record is protected. Review its current payout/status in AffiliateWP.</p>'; }
	$events = affwp_get_referral_meta( $rid, '_wave_aff_resolution', false );
	if ( $events ) { echo '<details><summary>Review history</summary>'; foreach ( array_reverse( $events ) as $e ) { if ( ! is_array( $e ) ) { continue; } echo '<p>' . esc_html( $e['at'] . ' · Staff #' . $e['by'] . ' · ' . $e['decision'] . ': ' . $e['reason'] ) . '</p>'; } echo '</details>'; }
	echo '</section>';
}
function wave_aff_view_review( $d ) {
	$issue = wave_aff_get( 'issue' ); $affiliate = absint( wave_aff_get( 'affiliate' ) );
	if ( absint( wave_aff_get( 'referral' ) ) ) { wave_aff_resolution_editor( $d, absint( wave_aff_get( 'referral' ) ) ); }
	$oid = absint( wave_aff_get( 'order' ) );
	if ( $oid && isset( $d['orders'][ $oid ] ) ) {
		$o = $d['orders'][ $oid ]; $person = null;
		foreach ( $d['missing'] as $m ) { if ( $m['order']->get_id() === $oid ) { $person = $m['person']; break; } }
		if ( $person && 1 === count( $person['links'] ) ) {
			echo '<section class="wa-panel wa-editor"><h2>Review missing commission: order #' . esc_html( $o->get_order_number() ) . '</h2><p>Customer: ' . esc_html( $person['name'] ) . '. Current affiliate: ' . esc_html( wave_aff_link_labels( $person, $d ) ) . '.</p><p>Enter the commission agreed for this historical order. This creates a <strong>pending</strong> AffiliateWP referral for approval in the source system, dated to the order’s payment date. It does not approve or pay the commission.</p>';
			wave_aff_form_start( 'referral', $d['month'] ); wave_aff_hidden( 'order_id', $oid ); wave_aff_hidden( 'affiliate_id', $person['links'][0]->affiliate_id );
			echo '<label>Commission (' . esc_html( $o->get_currency() ) . ')<input type="number" name="amount" min="0.0001" step="0.0001" max="' . esc_attr( $o->get_total() ) . '" required></label><label>Agreed rate / calculation and attribution evidence<textarea name="reason" required minlength="5" maxlength="1500" rows="3"></textarea></label><label class="wa-confirm"><input type="checkbox" name="confirmed" value="yes" required> I verified the historical attribution and commission calculation.</label><button class="button button-primary">Create pending referral</button></form></section>';
		}
	}
	$held = array_values( array_filter( $d['report'], function( $r ) use ( $affiliate, $issue ) { return 'Review' === $r['decision'] && ( ! $affiliate || $r['affiliate_id'] === $affiliate ) && ( ! $issue || wave_aff_issue( $r )[0] === $issue ); } ) );
	echo '<section class="wa-panel"><h2>What needs attention?</h2><p>Pick an issue to see the affected commissions and the next action. Fix the source issue, then refresh this page.</p>';
	wave_aff_issue_cards( $d, $affiliate );
	echo '<div class="wa-filters"><a href="' . esc_url( wave_aff_url( array( 'view' => 'review', 'month' => $d['month'] ) ) ) . '">All commission issues</a><a href="' . esc_url( wave_aff_url( array( 'view' => 'review', 'month' => $d['month'], 'issue' => 'missing' ) ) ) . '">Missing commissions (' . esc_html( count( $d['missing'] ) ) . ')</a><a href="' . esc_url( wave_aff_url( array( 'view' => 'review', 'month' => $d['month'], 'issue' => 'data' ) ) ) . '">Customer data issues (' . esc_html( $d['orphan'] ) . ')</a></div>';
	if ( $affiliate && isset( $d['board'][ $affiliate ] ) ) { echo '<p>Filtered to <strong>' . esc_html( $d['board'][ $affiliate ]['name'] ) . '</strong>.</p>'; }
	if ( ! in_array( $issue, array( 'missing', 'data' ), true ) ) {
		echo '<h3>' . esc_html( $issue && $held ? wave_aff_issue( $held[0] )[1] : 'Commission issues' ) . ' (' . esc_html( count( $held ) ) . ')</h3>';
		wave_aff_search( 'Affiliate, order, referral or issue' ); wave_aff_render_referrals( $held, $d['month'] );
	}
	echo '</section>';
	if ( 'data' === $issue ) { wave_aff_data_issues( $d ); return; }
	if ( 'missing' !== $issue && ! $oid ) { return; }

	echo '<section class="wa-panel"><h2>Paid orders without a recorded referral (' . esc_html( count( $d['missing'] ) ) . ')</h2><p>All loaded order dates. These include direct purchases and are <strong>not automatically commissions owed</strong>. Verify the customer link and historical agreement first. Existing referrals are never duplicated.</p>'; wave_aff_search( 'Customer, order or affiliate' ); wave_aff_table_start( array( 'Order / customer', 'Current affiliate', 'Payment / status', 'Next action' ) );
	foreach ( $d['missing'] as $m ) {
		$o = $m['order']; $p = $m['person'];
		echo '<tr data-wa-row><td><a href="' . esc_url( $o->get_edit_order_url() ) . '">Order #' . esc_html( $o->get_order_number() ) . '</a><small>' . esc_html( $p['name'] ) . '</small></td><td>' . esc_html( wave_aff_link_labels( $p, $d ) ) . '</td><td>' . esc_html( $o->get_currency() . ' ' . $o->get_total() . ' · ' . $o->get_status() ) . '<small>' . esc_html( $o->get_date_paid() ? $o->get_date_paid()->date_i18n( 'M j, Y' ) : 'Payment date missing — review' ) . '</small></td><td><a href="' . esc_url( wave_aff_url( array( 'view' => 'customers', 'target' => $m['target'], 'month' => $d['month'] ) ) ) . '">Verify / correct link</a>';
		if ( 1 === count( $p['links'] ) ) { echo '<a class="wa-secondary" href="' . esc_url( wave_aff_url( array( 'view' => 'review', 'order' => $o->get_id(), 'month' => $d['month'] ) ) ) . '">Review missing commission</a>'; } echo '</td></tr>';
	}
	if ( ! $d['missing'] ) { echo '<tr><td colspan="4">No paid orders without referrals found.</td></tr>'; } wave_aff_table_end(); echo '</section>';
}
function wave_aff_data_issues( $d ) {
	$known = array(); foreach ( $d['customers'] as $customer ) { $known[ $customer->customer_id ] = true; }
	echo '<section class="wa-panel"><h2>Customer links with no matching customer</h2><p>These records do not identify a valid customer. Use the affiliate’s records to establish who the customer was, then assign the verified customer in Customer links. Do not guess from an empty customer ID.</p>';
	wave_aff_table_start( array( 'Affiliate', 'Link record', 'Next action' ) );
	foreach ( $d['links'] as $link ) { if ( isset( $known[ $link->affwp_customer_id ] ) ) { continue; } echo '<tr><td>' . esc_html( $d['board'][ $link->affiliate_id ]['name'] ?? 'Unknown affiliate' ) . '</td><td>#' . esc_html( $link->lifetime_customer_id ) . '<small>Customer ID: ' . esc_html( $link->affwp_customer_id ) . '</small></td><td><a target="_blank" rel="noopener" href="' . esc_url( wave_aff_native( 'affiliates', array( 'action' => 'edit_affiliate', 'affiliate_id' => $link->affiliate_id ) ) ) . '">Review affiliate records ↗</a> · <a href="' . esc_url( wave_aff_url( array( 'view' => 'customers', 'month' => $d['month'] ) ) ) . '">Find / link customer</a></td></tr>'; }
	wave_aff_table_end(); echo '</section>';
}
function wave_aff_summary_table( $summary, $rows, $month ) {
	usort( $summary, function( $a, $b ) { return strcmp( $a['currency'], $b['currency'] ) ?: ( $b['payable'] <=> $a['payable'] ); } );
	wave_aff_table_start( array( 'Affiliate', 'From this month', 'Older unpaid', 'Ready for payout review', 'Needs attention', 'Action' ) );
	foreach ( $summary as $r ) {
		$count = count( array_filter( $rows, function( $row ) use ( $r ) { return $row['affiliate_id'] === $r['affiliate_id'] && $row['currency'] === $r['currency'] && 'Review' === $row['decision']; } ) );
		echo '<tr data-wa-row><td><strong>' . esc_html( $r['affiliate'] ) . '</strong><small>#' . esc_html( $r['affiliate_id'] ) . '</small></td><td>' . esc_html( wave_aff_display_money( array( $r['currency'] => $r['payable'] - $r['carryover'] ) ) ) . '</td><td>' . esc_html( wave_aff_display_money( array( $r['currency'] => $r['carryover'] ) ) ) . '</td><td class="wa-ready-cell"><strong>' . esc_html( wave_aff_display_money( array( $r['currency'] => $r['payable'] ) ) ) . '</strong></td><td>' . esc_html( $count . ' issues' ) . '<small>' . esc_html( wave_aff_display_money( array( $r['currency'] => $r['held'] ) ) ) . '</small></td><td>';
		if ( $count ) { echo '<a class="button" href="' . esc_url( wave_aff_url( array( 'view' => 'review', 'month' => $month, 'affiliate' => $r['affiliate_id'] ) ) ) . '">Resolve ' . esc_html( $count ) . ' issues</a>'; } else { echo '<span class="wave-badge blue">No blocking issues</span>'; }
		echo '</td></tr>';
	}
	if ( ! $summary ) { echo '<tr><td colspan="6">No outstanding commissions through this month.</td></tr>'; } wave_aff_table_end();
}
function wave_aff_download_url( $id, $format ) { return wp_nonce_url( add_query_arg( array( 'action' => 'wave_aff_export', 'report' => $id, 'format' => $format ), admin_url( 'admin-post.php' ) ), 'wave_aff_export_' . $id ); }
function wave_aff_view_reports( $d ) {
	$held = count( array_filter( $d['report'], function( $r ) { return 'Review' === $r['decision']; } ) );
	echo '<section class="wa-panel"><h2>Prepare payouts · ' . esc_html( $d['month'] ) . '</h2><p>Current unpaid commissions earned through this month. Older unpaid balances are included and shown separately.</p><ol class="wa-steps"><li><strong>1. Review amounts</strong><span>See this month + older unpaid below.</span></li><li><strong>2. Resolve issues</strong><span>Held commissions stay out of the payout total.</span></li><li><strong>3. Save &amp; export</strong><span>Check past payouts before sending payment.</span></li></ol>';
	echo '<div class="wa-payout-equation"><div><span>From this month</span><strong>' . esc_html( wave_aff_display_money( wave_aff_month_payable( $d['totals'] ) ) ) . '</strong></div><b>+</b><div><span>Older unpaid</span><strong>' . esc_html( wave_aff_display_money( $d['totals']['carryover'] ) ) . '</strong></div><b>=</b><div class="wa-ready"><span>Ready for payout review</span><strong>' . esc_html( wave_aff_display_money( $d['totals']['payable'] ) ) . '</strong></div></div>';
	echo '<div class="wa-review-banner"><div><strong>' . esc_html( $held ) . ' commissions need attention</strong><span>' . esc_html( wave_aff_display_money( $d['totals']['held'] ) ) . ' held outside the total above.</span></div><a class="button" href="' . esc_url( wave_aff_url( array( 'view' => 'review', 'month' => $d['month'] ) ) ) . '">Resolve issues →</a></div>';
	wave_aff_search( 'Find an affiliate in this report' ); wave_aff_summary_table( wave_aff_report_summary( $d['report'] ), $d['report'], $d['month'] );
	wave_aff_form_start( 'report', $d['month'] ); echo '<button class="button button-primary" ' . disabled( $d['limited'], true, false ) . '>Save report &amp; prepare downloads</button></form><p class="wave-muted">Saving a report does not send money or mark commissions paid. After corrections, save a new version. ≈ means the summary is rounded for display; detail and CSVs preserve the exact recorded amount.</p><details><summary>How these amounts are calculated</summary><p>Evaluated using today’s statuses. Includes currently unpaid and pending referrals earned before the selected month ends; paid and rejected referrals are excluded. Eligible unpaid commissions go into the payout-review total. Missing commissions are not estimated. This is not a reconstruction of the historical month-end balance.</p><a href="' . esc_url( wave_aff_native( 'payouts' ) ) . '">Check existing payouts ↗</a></details><details><summary>All referral details (' . esc_html( count( $d['report'] ) ) . ')</summary>'; wave_aff_render_referrals( $d['report'], $d['month'] ); echo '</details></section>';
	$reports = get_posts( array( 'post_type' => 'wave_aff_report', 'post_status' => 'private', 'posts_per_page' => 24, 'orderby' => 'ID', 'order' => 'DESC' ) );
	echo '<section class="wa-panel"><h2>Download saved reports</h2><p>Saved versions keep the amounts recorded at that time. After a correction or payout, generate a fresh version.</p>';
	wave_aff_table_start( array( 'Month / version', 'Saved', 'Payout-review total', 'Downloads' ) );
	foreach ( $reports as $post ) { $s = get_post_meta( $post->ID, '_wave_aff_snapshot', true ); if ( ! is_array( $s ) ) { continue; } $user = get_userdata( $s['generated_by'] ); echo '<tr><td><strong>' . esc_html( $s['month'] . ' · #' . $post->ID ) . '</strong></td><td>' . esc_html( wp_date( 'M j, Y g:i a', strtotime( $s['generated_at'] ) ) ) . '<small>' . esc_html( $user ? $user->display_name : 'Staff' ) . '</small></td><td>' . esc_html( wave_aff_display_money( $s['totals']['payable'] ) ) . '</td><td><a class="button button-primary" href="' . esc_url( wave_aff_download_url( $post->ID, 'summary' ) ) . '">Who to pay · CSV</a> <a class="button" href="' . esc_url( wave_aff_download_url( $post->ID, 'detail' ) ) . '">Commission detail · CSV</a></td></tr>'; }
	if ( ! $reports ) { echo '<tr><td colspan="4">Save your first report above to prepare downloads.</td></tr>'; } wave_aff_table_end(); echo '</section>';
}
