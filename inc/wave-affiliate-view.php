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
	$views = array( 'overview' => 'Performance & leaderboard', 'customers' => 'Customer links', 'review' => 'Commission review', 'reports' => 'Monthly reports' );
	if ( ! isset( $views[ $view ] ) ) { $view = 'overview'; }
	$data = wave_aff_load( $month );
	echo '<div class="wrap wave-trt wave-aff"><div class="wave-brand">WAVE CONSULTING <span>OPERATIONS WORKSPACE</span></div><div class="wave-heading"><div><p class="wave-eyebrow">AFFILIATE OPERATIONS</p><h1>Affiliates</h1><p>Traffic, customer attribution and a clear path from commission review to monthly payout proposals.</p></div><a class="button" href="' . esc_url( wave_aff_native( 'affiliates' ) ) . '">Manage affiliate accounts ↗</a></div>';
	echo '<nav class="wa-nav" aria-label="Affiliate workspace">'; foreach ( $views as $key => $label ) { echo '<a class="' . ( $view === $key ? 'is-current' : '' ) . '" ' . ( $view === $key ? 'aria-current="page"' : '' ) . ' href="' . esc_url( wave_aff_url( array( 'view' => $key, 'month' => $month ) ) ) . '">' . esc_html( $label ) . '</a>'; } echo '</nav>';
	echo '<form method="get" class="wa-month">'; wave_aff_hidden( 'page', 'wave-affiliates' ); wave_aff_hidden( 'view', $view ); echo '<label>Reporting month <input type="month" name="month" value="' . esc_attr( $month ) . '" required></label> <button class="button">Apply month</button><span>Calendar boundaries: ' . esc_html( wp_timezone_string() ) . '. Loaded ' . esc_html( wp_date( 'M j, Y g:i a' ) ) . '.</span></form>';
	if ( '1' === wave_aff_get( 'saved' ) ) { echo '<div class="notice notice-success inline"><p>Saved successfully. Review the updated record below.</p></div>'; }
	if ( $data['limited'] ) { echo '<div class="notice notice-error inline"><p><strong>Partial data:</strong> a safety limit was reached. Counts are incomplete and report generation is disabled.</p></div>'; }
	if ( $data['orphan'] ) { echo '<div class="wave-notice wa-warning"><strong>Attribution data needs attention</strong><p>' . esc_html( $data['orphan'] ) . ' existing lifetime-link records do not resolve to an AffiliateWP customer. These are excluded from attached-customer counts. Validated customer corrections are available below; orphan records require source review.</p><a href="' . esc_url( wave_aff_native( 'affiliates' ) ) . '">Review affiliate accounts ↗</a></div>'; }
	call_user_func( 'wave_aff_view_' . $view, $data );
	echo '<p class="wave-footer">Restricted to administrators with WooCommerce and AffiliateWP management access. AffiliateWP remains the source for visits, attribution and commissions. Customer links are current; historical referrals retain their original affiliate. Reports are proposals and do not send payments or mark commissions paid.</p></div>';
}
function wave_aff_view_overview( $d ) {
	$visits = 0; $converted = 0; $earned = array();
	foreach ( $d['board'] as $a ) { $visits += $a['visits']; $converted += $a['converted']; foreach ( $a['earned'] as $currency => $units ) { wave_aff_sum( $earned, $currency, $units ); } }
	$attached = count( array_filter( $d['people'], function( $p ){ return ! empty( $p['links'] ); } ) );
	$stats = array( 'Tracked visits this month' => $visits, 'Visits with a referral' => $converted, 'Customers currently linked' => $attached, 'Recorded earned this month' => wave_aff_money( $earned ), 'Proposed payable through month' => wave_aff_money( $d['totals']['payable'] ) );
	echo '<div class="wa-stats">'; foreach ( $stats as $label => $value ) { echo '<div class="wa-stat"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>'; } echo '</div>';
	echo '<section class="wa-panel"><h2>Affiliate leaderboard</h2><p>Ranked by recorded paid + unpaid commission earned in ' . esc_html( $d['month'] . ' / ' . $d['rank_currency'] ) . ', then tracked visits. Pending and rejected referrals do not count as earned. Visits are tracking events, not unique people; converted visits can include referrals awaiting approval.</p>';
	wave_aff_search( 'Affiliate name, ID or status' ); wave_aff_table_start( array( 'Rank / affiliate', 'Status', 'Month visits', 'Converted visits', 'Current customers', 'Month referrals', 'Month earned', 'Month pending', 'Proposed payable', 'Links' ) );
	$rank = 0;
	foreach ( $d['board'] as $id => $a ) {
		echo '<tr data-wa-row><td><strong>' . esc_html( ++$rank . '. ' . $a['name'] ) . '</strong><small>Affiliate #' . esc_html( $id ) . '</small></td><td><span class="wave-badge ' . ( 'active' === $a['status'] ? 'blue' : 'gray' ) . '">' . esc_html( $a['status'] ) . '</span></td><td>' . esc_html( $a['visits'] ) . '</td><td>' . esc_html( $a['converted'] ) . '<small>' . esc_html( $a['visits'] ? round( 100 * $a['converted'] / $a['visits'], 1 ) . '%' : '—' ) . '</small></td><td><a href="' . esc_url( wave_aff_url( array( 'view' => 'customers', 'affiliate' => $id, 'month' => $d['month'] ) ) ) . '">' . esc_html( $a['linked'] ) . ' customers</a></td><td>' . esc_html( $a['referrals'] ) . '</td><td>' . esc_html( wave_aff_money( $a['earned'] ) ) . '</td><td>' . esc_html( wave_aff_money( $a['pending'] ) ) . '</td><td>' . esc_html( wave_aff_money( $a['payable'] ) ) . '</td><td><a href="' . esc_url( wave_aff_native( 'affiliates', array( 'action' => 'edit_affiliate', 'affiliate_id' => $id ) ) ) . '">Profile ↗</a><details><summary>Referral URL</summary><input aria-label="Referral URL for ' . esc_attr( $a['name'] ) . '" readonly value="' . esc_attr( affwp_get_affiliate_referral_url( array( 'affiliate_id' => $id ) ) ) . '"><button type="button" class="button wa-copy">Copy URL</button></details></td></tr>';
	}
	wave_aff_table_end(); echo '</section>';
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
function wave_aff_render_referrals( $rows ) {
	wave_aff_table_start( array( 'Referral / order', 'Affiliate', 'Earned (UTC)', 'Commission', 'Decision / reason', 'Period' ) );
	foreach ( $rows as $r ) { echo '<tr data-wa-row><td><a href="' . esc_url( wave_aff_native( 'referrals', array( 'action' => 'edit_referral', 'referral_id' => $r['referral_id'] ) ) ) . '">Referral #' . esc_html( $r['referral_id'] ) . ' ↗</a><small>Order ref: ' . esc_html( $r['order'] ) . '</small></td><td>' . esc_html( $r['affiliate'] . ' (#' . $r['affiliate_id'] . ')' ) . '</td><td>' . esc_html( $r['date'] ) . '</td><td>' . esc_html( $r['currency'] . ' ' . $r['amount'] ) . '<small>Source: ' . esc_html( $r['source_status'] ) . '</small></td><td><span class="wave-badge ' . ( 'Review' === $r['decision'] ? 'amber' : 'blue' ) . '">' . esc_html( $r['decision'] ) . '</span><small>' . esc_html( $r['reason'] ) . '</small></td><td>' . esc_html( $r['period'] ) . '</td></tr>'; }
	if ( ! $rows ) { echo '<tr><td colspan="6">No referrals in this queue.</td></tr>'; } wave_aff_table_end();
}
function wave_aff_view_review( $d ) {
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
	$held = array_values( array_filter( $d['report'], function( $r ){ return 'Review' === $r['decision']; } ) );
	echo '<section class="wa-panel"><h2>Commissions held for review (' . esc_html( count( $held ) ) . ')</h2><p>Currently pending or unpaid referrals earned through ' . esc_html( $d['month'] ) . '. Resolve the source issue, then regenerate the monthly proposal. Paid and rejected referrals remain in <a href="' . esc_url( wave_aff_native( 'referrals' ) ) . '">AffiliateWP ↗</a>.</p>'; wave_aff_search( 'Affiliate, referral, order or review reason' ); wave_aff_render_referrals( $held ); echo '</section>';
	echo '<section class="wa-panel"><h2>Paid orders without a recorded referral (' . esc_html( count( $d['missing'] ) ) . ')</h2><p>All loaded order dates. These include direct purchases and are <strong>not automatically commissions owed</strong>. Verify the customer link and historical agreement first. Existing referrals are never duplicated.</p>'; wave_aff_search( 'Customer, order or affiliate' ); wave_aff_table_start( array( 'Order / customer', 'Current affiliate', 'Payment / status', 'Next action' ) );
	foreach ( $d['missing'] as $m ) {
		$o = $m['order']; $p = $m['person'];
		echo '<tr data-wa-row><td><a href="' . esc_url( $o->get_edit_order_url() ) . '">Order #' . esc_html( $o->get_order_number() ) . '</a><small>' . esc_html( $p['name'] ) . '</small></td><td>' . esc_html( wave_aff_link_labels( $p, $d ) ) . '</td><td>' . esc_html( $o->get_currency() . ' ' . $o->get_total() . ' · ' . $o->get_status() ) . '<small>' . esc_html( $o->get_date_paid() ? $o->get_date_paid()->date_i18n( 'M j, Y' ) : 'Payment date missing — review' ) . '</small></td><td><a href="' . esc_url( wave_aff_url( array( 'view' => 'customers', 'target' => $m['target'], 'month' => $d['month'] ) ) ) . '">Verify / correct link</a>';
		if ( 1 === count( $p['links'] ) ) { echo '<a class="wa-secondary" href="' . esc_url( wave_aff_url( array( 'view' => 'review', 'order' => $o->get_id(), 'month' => $d['month'] ) ) ) . '">Review missing commission</a>'; } echo '</td></tr>';
	}
	if ( ! $d['missing'] ) { echo '<tr><td colspan="4">No paid orders without referrals found.</td></tr>'; } wave_aff_table_end(); echo '</section>';
}
function wave_aff_summary_table( $summary ) {
	wave_aff_table_start( array( 'Affiliate', 'Currency', 'Proposed payable', 'Includes prior-month carryover', 'Held for review', 'Referrals' ) );
	foreach ( $summary as $r ) { echo '<tr data-wa-row><td>' . esc_html( $r['affiliate'] . ' (#' . $r['affiliate_id'] . ')' ) . '</td><td>' . esc_html( $r['currency'] ) . '</td><td><strong>' . esc_html( wave_aff_amount( $r['payable'] ) ) . '</strong></td><td>' . esc_html( wave_aff_amount( $r['carryover'] ) ) . '</td><td>' . esc_html( wave_aff_amount( $r['held'] ) ) . '</td><td>' . esc_html( $r['count'] ) . '</td></tr>'; }
	if ( ! $summary ) { echo '<tr><td colspan="6">No outstanding commissions through this month.</td></tr>'; } wave_aff_table_end();
}
function wave_aff_download_url( $id, $format ) { return wp_nonce_url( add_query_arg( array( 'action' => 'wave_aff_export', 'report' => $id, 'format' => $format ), admin_url( 'admin-post.php' ) ), 'wave_aff_export_' . $id ); }
function wave_aff_view_reports( $d ) {
	echo '<section class="wa-panel"><h2>Monthly payout proposal · ' . esc_html( $d['month'] ) . '</h2><p>Based on AffiliateWP’s recorded amounts. Includes <strong>currently unpaid and pending</strong> commissions earned before the end of this month, evaluated now. Prior-month carryover is included in proposed payable and shown separately. This is not a reconstruction of the historical month-end balance.</p><div class="wa-stats">';
	foreach ( array( 'payable' => 'Proposed payable', 'carryover' => 'Included carryover', 'held' => 'Held for review' ) as $key => $label ) { echo '<div class="wa-stat"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( wave_aff_money( $d['totals'][ $key ] ) ) . '</strong></div>'; } echo '</div>';
	wave_aff_summary_table( wave_aff_report_summary( $d['report'] ) );
	echo '<p>A saved snapshot preserves these amounts and decisions. CSV exports provide an affiliate payout summary and referral-level detail. Missing commissions are not estimated; resolve them in Commission review and generate a fresh snapshot. Any payout must be reconciled against payments made since the snapshot.</p>';
	wave_aff_form_start( 'report', $d['month'] ); echo '<button class="button button-primary" ' . disabled( $d['limited'], true, false ) . '>Generate & save monthly report</button></form><p><a href="' . esc_url( wave_aff_native( 'payouts' ) ) . '">Open AffiliateWP payouts for reconciliation ↗</a></p><details><summary>Inspect proposal referral detail (' . esc_html( count( $d['report'] ) ) . ')</summary>'; wave_aff_render_referrals( $d['report'] ); echo '</details></section>';
	$reports = get_posts( array( 'post_type' => 'wave_aff_report', 'post_status' => 'private', 'posts_per_page' => 24, 'orderby' => 'ID', 'order' => 'DESC' ) );
	echo '<section class="wa-panel"><h2>Saved reports</h2><p>Latest 24 immutable snapshots. Generate a new version after corrections; old exports remain a record of what was reviewed.</p>';
	wave_aff_table_start( array( 'Report', 'Generated / author', 'Proposed payable', 'Downloads' ) );
	foreach ( $reports as $post ) { $s = get_post_meta( $post->ID, '_wave_aff_snapshot', true ); if ( ! is_array( $s ) ) { continue; } $user = get_userdata( $s['generated_by'] ); echo '<tr><td><strong>' . esc_html( $s['month'] . ' · #' . $post->ID ) . '</strong></td><td>' . esc_html( $s['generated_at'] ) . '<small>' . esc_html( $user ? $user->display_name : 'Staff' ) . '</small></td><td>' . esc_html( wave_aff_money( $s['totals']['payable'] ) ) . '</td><td><a class="button" href="' . esc_url( wave_aff_download_url( $post->ID, 'summary' ) ) . '">Payout summary CSV</a> <a class="button" href="' . esc_url( wave_aff_download_url( $post->ID, 'detail' ) ) . '">Referral detail CSV</a></td></tr>'; }
	if ( ! $reports ) { echo '<tr><td colspan="4">No reports saved yet. Generate the first monthly snapshot above.</td></tr>'; } wave_aff_table_end(); echo '</section>';
}
