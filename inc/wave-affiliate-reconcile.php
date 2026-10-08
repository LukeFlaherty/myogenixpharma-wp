<?php
/** Order-by-order attribution decisions, independent of lifetime customer links. */
defined( 'ABSPATH' ) || exit;

function wave_aff_order_revision( $order ) {
	$customer = wave_aff_resolve_order_customer( $order );
	$links = $customer ? wave_aff_current_links( $customer->customer_id ) : array();
	$refs = affiliate_wp()->referrals->get_referrals( array( 'reference' => (string) $order->get_id(), 'context' => 'woocommerce', 'number' => 100 ) );
	return hash( 'sha256', wp_json_encode( array( $order->get_id(), $order->get_customer_id(), $order->get_billing_email(), $order->get_currency(), $order->get_total(), $order->get_total_refunded(), $order->get_status(), $order->get_transaction_id(), $order->get_date_paid() ? $order->get_date_paid()->getTimestamp() : 0, wave_aff_link_revision( $links ), $refs, $order->get_meta( '_wave_aff_decision' ) ) ) );
}

function wave_aff_reconcile_order( $id, $revision, $decision, $aid, $amount, $reason, $lifetime = false ) {
	if ( ! current_user_can( 'manage_referrals' ) ) { wave_aff_fail( 'You do not have permission to reconcile commissions.' ); }
	$order = wc_get_order( $id );
	if ( ! $order || 'shop_order' !== $order->get_type() || wave_trt_test_record( $order ) ) { wave_aff_fail( 'Order unavailable.' ); }
	if ( ! hash_equals( wave_aff_order_revision( $order ), $revision ) ) { wave_aff_fail( 'The order or attribution changed. Reload before saving.' ); }
	if ( strlen( trim( $reason ) ) < 5 || strlen( $reason ) > 1500 ) { wave_aff_fail( 'Record your evidence or reason (5–1500 characters).' ); }
	if ( ! in_array( $decision, array( 'affiliate', 'direct', 'reopen' ), true ) ) { wave_aff_fail( 'Choose a reconciliation decision.' ); }
	$existing = affiliate_wp()->referrals->get_referrals( array( 'reference' => (string) $id, 'context' => 'woocommerce', 'number' => 1 ) );
	if ( $existing ) { wave_aff_fail( 'A commission already exists. Review that commission instead.' ); }
	$rid = 0;
	if ( 'affiliate' === $decision ) {
		$a = affwp_get_affiliate( $aid ); $u = $a ? get_userdata( $a->user_id ) : false;
		if ( ! $a || 'active' !== $a->status ) { wave_aff_fail( 'Choose an active affiliate.' ); }
		if ( ( $order->get_customer_id() && (int) $a->user_id === $order->get_customer_id() ) || ( $u && 0 === strcasecmp( $u->user_email, $order->get_billing_email() ) ) ) { wave_aff_fail( 'Self-referrals cannot be created.' ); }
		$units = wave_aff_units( $amount );
		if ( null === $units || $units <= 0 || $units > wave_aff_units( $order->get_total() ) ) { wave_aff_fail( 'Enter the agreed positive commission, no greater than the order total.' ); }
		$probe = (object) array( 'status' => 'unpaid', 'payout_id' => 0, 'currency' => $order->get_currency(), 'amount' => $amount, 'context' => 'woocommerce' );
		$block = wave_aff_review_reason( $probe, $a, $order, false );
		if ( $block ) { wave_aff_fail( $block . '. Resolve this before adding a commission.' ); }
		$c = wave_aff_resolve_order_customer( $order );
		if ( $lifetime ) {
			$links = $c ? wave_aff_current_links( $c->customer_id ) : array();
			if ( 1 !== count( $links ) || (int) $links[0]->affiliate_id !== $aid ) {
				wave_aff_change_link( 'o' . $id, $c ? (int) $c->customer_id : 0, wave_aff_link_revision( $links ), $aid, $reason );
				$c = wave_aff_resolve_order_customer( $order );
			}
		}
		if ( ! $c ) {
			if ( ! is_email( $order->get_billing_email() ) ) { wave_aff_fail( 'A valid customer email is required.' ); }
			$cid = affwp_add_customer( array( 'user_id' => $order->get_customer_id(), 'email' => $order->get_billing_email(), 'first_name' => $order->get_billing_first_name(), 'last_name' => $order->get_billing_last_name() ) );
			if ( ! $cid || is_wp_error( $cid ) ) { wave_aff_fail( 'Could not resolve the customer. Check their account and email aliases.' ); }
			$c = affwp_get_customer( $cid );
		}
		$rid = affiliate_wp()->referrals->add( array( 'customer_id' => $c->customer_id, 'customer' => array( 'email' => $c->email ), 'affiliate_id' => $aid, 'amount' => wave_aff_amount( $units ), 'order_total' => $order->get_total(), 'reference' => (string) $id, 'currency' => $order->get_currency(), 'context' => 'woocommerce', 'date' => gmdate( 'Y-m-d H:i:s', $order->get_date_paid()->getTimestamp() + (int) affiliate_wp()->utils->wp_offset ), 'status' => 'pending', 'description' => 'Staff attribution review — approval required' ) );
		if ( ! $rid ) { wave_aff_fail( 'Commission creation failed. Reload before retrying; any requested customer link may already be saved.' ); }
		affwp_add_referral_meta( $rid, '_wave_aff_correction', array( 'at' => gmdate( 'c' ), 'by' => get_current_user_id(), 'reason' => $reason ) );
	}
	$event = array( 'decision' => $decision, 'affiliate_id' => 'affiliate' === $decision ? $aid : 0, 'referral_id' => $rid, 'reason' => $reason, 'by' => get_current_user_id(), 'at' => gmdate( 'c' ) );
	$order->update_meta_data( '_wave_aff_decision', $event ); $order->add_meta_data( '_wave_aff_decision_history', $event ); $order->save();
	$order->add_order_note( 'Wave affiliate review: ' . $decision . ( $rid ? ' — pending commission #' . $rid : '' ) . '. ' . $reason, false, true );
	return $rid;
}

function wave_aff_missing_editor( $d, $o ) {
	try { $revision = wave_aff_order_revision( $o ); $c = wave_aff_resolve_order_customer( $o ); }
	catch ( RuntimeException $e ) { echo '<div class="notice notice-error inline"><p>' . esc_html( $e->getMessage() ) . '</p></div>'; return; }
	$links = $c ? wave_aff_current_links( $c->customer_id ) : array();
	$previous = $o->get_meta( '_wave_aff_decision' );
	echo '<section class="wa-panel wa-editor"><h2>Reconcile order #' . esc_html( $o->get_order_number() ) . '</h2><p><strong>' . esc_html( $o->get_formatted_billing_full_name() ) . '</strong> · ' . esc_html( $o->get_billing_email() ) . '</p><p>' . esc_html( $o->get_currency() . ' ' . $o->get_total() . ' · ' . wc_get_order_status_name( $o->get_status() ) . ' · Refunds: ' . $o->get_total_refunded() ) . '</p><p>Payment: ' . esc_html( $o->get_transaction_id() && $o->get_date_paid() ? 'Recorded on ' . $o->get_date_paid()->date_i18n( 'M j, Y' ) : 'Not confirmed — commissions must wait for payment' ) . '</p><ul>';
	foreach ( $o->get_items() as $item ) { echo '<li>' . esc_html( $item->get_name() . ' × ' . $item->get_quantity() ) . '</li>'; } echo '</ul>';
	if ( $previous ) { echo '<p><strong>Last decision:</strong> ' . esc_html( $previous['decision'] . ' · ' . $previous['reason'] ) . '</p>'; }
	echo '<p>Choose who referred <strong>this order</strong>, or confirm it was a direct purchase. A current customer link is a clue; it does not prove historical attribution. Enter the agreed commission after checking the agreement.</p>';
	wave_aff_form_start( 'reconcile_order', $d['month'] ); wave_aff_hidden( 'order_id', $o->get_id() ); wave_aff_hidden( 'revision', $revision );
	echo '<label>Decision<select name="reconcile_decision" required><option value="">Choose…</option><option value="affiliate">Assign affiliate and add commission for review</option><option value="direct">Direct purchase — no affiliate commission owed</option>' . ( $previous ? '<option value="reopen">Reopen this order for review</option>' : '' ) . '</select></label><div data-wa-attribution>';
	wave_aff_select( $d, 1 === count( $links ) ? (int) $links[0]->affiliate_id : 0, false );
	echo '<label>Agreed commission (' . esc_html( $o->get_currency() ) . ')<input name="amount" type="number" min="0.01" step="0.01" max="' . esc_attr( $o->get_total() ) . '"></label><label><input type="checkbox" name="lifetime" value="yes"> Also use this affiliate for future qualifying customer purchases</label><p>Leave unchecked to keep any existing customer link unchanged.</p></div><label>Evidence / reason<textarea name="reason" minlength="5" maxlength="1500" required rows="3" placeholder="Customer confirmation, referral code, agreement and commission calculation"></textarea></label><label class="wa-confirm"><input type="checkbox" name="confirmed" value="yes" required> I verified this order, the attribution and the amount.</label><button class="button button-primary">Save reconciliation</button></form><p><a href="' . esc_url( $o->get_edit_order_url() ) . '">View full order ↗</a></p></section>';
}

function wave_aff_view_missing( $d ) {
	$oid = absint( wave_aff_get( 'order' ) );
	if ( $oid && isset( $d['orders'][ $oid ] ) && empty( $d['order_refs'][ $oid ] ) ) { wave_aff_missing_editor( $d, $d['orders'][ $oid ] ); }
	$queue = wave_aff_get( 'queue' ) ?: 'unresolved';
	echo '<section class="wa-panel"><h2>Orders missing an affiliate or commission</h2><p>Review paid and open orders across all dates. Confirm direct sales to clear them from this queue, or assign an affiliate and enter the agreed commission once payment is confirmed.</p><div class="wa-filters">';
	foreach ( array( 'unresolved' => 'Needs review', 'direct' => 'Confirmed direct sales', 'all' => 'All without commissions' ) as $key => $label ) { echo '<a href="' . esc_url( wave_aff_url( array( 'view' => 'missing', 'month' => $d['month'], 'queue' => $key ) ) ) . '"' . ( $queue === $key ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>'; } echo '</div>';
	wave_aff_search( 'Customer, email, order number or affiliate' ); wave_aff_table_start( array( 'Order / customer', 'Customer’s current affiliate', 'Payment / items', 'Next step' ) ); $count = 0;
	foreach ( $d['people'] as $p ) { foreach ( $p['orders'] as $o ) {
		if ( isset( $d['order_refs'][ $o->get_id() ] ) || $o->has_status( array( 'trash', 'auto-draft', 'checkout-draft', 'cancelled', 'refunded', 'failed', 'rejected' ) ) ) { continue; }
		$decision = $o->get_meta( '_wave_aff_decision' ); $direct = 'direct' === ( $decision['decision'] ?? '' );
		if ( ( 'unresolved' === $queue && $direct ) || ( 'direct' === $queue && ! $direct ) ) { continue; } $count++;
		$items = array(); foreach ( $o->get_items() as $item ) { $items[] = $item->get_name(); }
		echo '<tr data-wa-row><td><strong>#' . esc_html( $o->get_order_number() . ' · ' . $p['name'] ) . '</strong><small>' . esc_html( $p['email'] ) . '</small><small>' . esc_html( $o->get_date_created() ? $o->get_date_created()->date_i18n( 'M j, Y' ) : '' ) . '</small></td><td>' . esc_html( wave_aff_link_labels( $p, $d ) ) . '</td><td>' . esc_html( $o->get_currency() . ' ' . $o->get_total() . ' · ' . $o->get_status() ) . '<small>' . esc_html( $o->get_date_paid() && $o->get_transaction_id() ? 'Payment recorded' : 'Awaiting verified payment' ) . '</small><small>' . esc_html( implode( ', ', $items ) ) . '</small></td><td><a class="button" href="' . esc_url( wave_aff_url( array( 'view' => 'missing', 'month' => $d['month'], 'order' => $o->get_id() ) ) ) . '">' . ( $direct ? 'Review decision' : 'Reconcile order' ) . '</a>' . ( $direct ? '<small>Confirmed direct sale</small>' : '' ) . '</td></tr>';
	} }
	if ( ! $count ) { echo '<tr><td colspan="4">No orders in this queue.</td></tr>'; } wave_aff_table_end(); echo '</section>';
}
