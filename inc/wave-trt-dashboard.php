<?php
/** Wave Consulting's read-only TRT operations view. No billing or clinical mutations. */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_menu_page( 'TRT Patients', 'Wave Consulting', 'manage_woocommerce', 'wave-trt', 'wave_trt_render', 'dashicons-chart-area', 56 );
	add_submenu_page( 'wave-trt', 'TRT Patients', 'TRT Patients', 'manage_woocommerce', 'wave-trt', 'wave_trt_render' );
} );
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'toplevel_page_wave-trt' !== $hook ) { return; }
	wp_enqueue_style( 'wave-trt', get_stylesheet_directory_uri() . '/assets/css/wave-trt-dashboard.css', array(), '1.0.0' );
	wp_enqueue_script( 'wave-trt', get_stylesheet_directory_uri() . '/assets/js/wave-trt-dashboard.js', array(), '1.0.0', true );
} );
add_action( 'admin_init', function () {
	if ( isset( $_GET['page'] ) && 'wave-trt' === $_GET['page'] ) { nocache_headers(); }
} );

function wave_trt_contains_product( $order ) {
	foreach ( $order->get_items() as $item ) {
		if ( 883 === (int) $item->get_product_id() ) { return true; }
	}
	return false;
}

function wave_trt_test_record( $order ) {
	return 'yes' === $order->get_meta( '_trt_qa_test' ) || (bool) preg_match( '/\btest\b|\bqa\b|do not ship/i', $order->get_formatted_billing_full_name() );
}

function wave_trt_patient_key( $order ) {
	if ( $order->get_customer_id() ) { return 'user-' . $order->get_customer_id(); }
	$email = strtolower( trim( $order->get_billing_email() ) );
	return $email ? 'guest-' . hash( 'sha256', $email ) : 'order-' . $order->get_id();
}

/** WC CRUD works with both HPOS and legacy storage. No patient data in persistent caches. */
function wave_trt_load_patients() {
	$patients = array();
	$limited = false;
	$excluded = 0;
	$types = array( 'shop_order' );
	if ( function_exists( 'wcs_get_subscription' ) ) { $types[] = 'shop_subscription'; }
	foreach ( $types as $type ) {
		for ( $page = 1; $page <= 20; $page++ ) {
			$statuses = 'shop_subscription' === $type ? array_keys( wcs_get_subscription_statuses() ) : array_keys( wc_get_order_statuses() );
			$result = wc_get_orders( array( 'type' => $type, 'status' => $statuses, 'limit' => 100, 'page' => $page, 'paginate' => true, 'orderby' => 'date', 'order' => 'DESC' ) );
			foreach ( $result->orders as $order ) {
				if ( $order->has_status( array( 'trash', 'auto-draft', 'checkout-draft' ) ) || ! wave_trt_contains_product( $order ) ) { continue; }
				if ( wave_trt_test_record( $order ) ) { $excluded++; continue; }
				$key = wave_trt_patient_key( $order );
				if ( ! isset( $patients[ $key ] ) ) { $patients[ $key ] = array( 'orders' => array(), 'subscriptions' => array() ); }
				$patients[ $key ][ 'shop_subscription' === $type ? 'subscriptions' : 'orders' ][] = $order;
			}
			if ( $page >= $result->max_num_pages ) { break; }
			if ( 20 === $page ) { $limited = true; }
		}
	}
	return array( $patients, $limited, $excluded );
}

/** Conservative signals: a status alone is never evidence of labs or shipment. */
function wave_trt_order_facts( $order ) {
	$events = array();
	$approved = 'yes' === $order->get_meta( '_trt_provider_approved' );
	$pharmacy = false;
	$notes = wc_get_order_notes( array( 'order_id' => $order->get_id(), 'limit' => 100, 'orderby' => 'date_created', 'order' => 'DESC', 'type' => 'internal' ) );
	foreach ( $notes as $note ) {
		$text = wp_strip_all_tags( $note->content );
		$label = '';
		if ( preg_match( '/Appointment #\d+.*→ approved\./u', $text ) ) { $approved = true; $label = 'Provider approval recorded'; }
		elseif ( false !== strpos( $text, 'Pharmacy Success (200)' ) ) { $pharmacy = true; $label = 'Pharmacy handoff acknowledged (not shipment confirmation)'; }
		elseif ( false !== strpos( $text, 'Recurring payment captured in Stripe' ) ) { $label = 'Recurring payment captured'; }
		elseif ( preg_match( '/Attempt \d+: Payment succeeded/', $text ) ) { $label = 'Medication payment succeeded'; }
		elseif ( false !== strpos( $text, 'Order refunded in Stripe' ) ) { $label = 'Refund recorded in Stripe'; }
		elseif ( false !== strpos( $text, 'Order status changed from' ) ) { $label = 'Order status updated — open order for details'; }
		if ( $label ) { $events[] = array( 'date' => $note->date_created, 'label' => $label ); }
	}
	$paid = (bool) $order->get_transaction_id() && (float) $order->get_total() > 0;
	$age = $order->get_date_created() ? max( 0, (int) floor( ( time() - $order->get_date_created()->getTimestamp() ) / DAY_IN_SECONDS ) ) : 0;
	return array( 'approved' => $approved, 'pharmacy' => $pharmacy, 'paid' => $paid, 'age' => $age, 'events' => $events,
		'lab' => (string) $order->get_meta( '_trt_lab_state' ), 'requisition' => (bool) $order->get_meta( '_prescribery_requisition_id' ),
		'refunded' => $order->has_status( 'refunded' ) || (float) $order->get_total_refunded() > 0,
		'closed' => $order->has_status( array( 'refunded', 'cancelled', 'rejected' ) ), 'status' => $order->get_status() );
}

/** Pure rules kept separate so payment, refund and missing-evidence cases can be tested. */
function wave_trt_assess( array $f, array $s ) {
	$flags = array();
	$queue = 'review';
	$tone = 'gray';
	$stage = 'Journey unconfirmed';
	$action = 'Reconcile the latest cycle with the provider.';
	if ( $f['pharmacy'] ) { $stage = 'Pharmacy handoff recorded'; $action = 'Confirm shipment and tracking with the pharmacy.'; $tone = 'blue'; $queue = 'fulfillment'; }
	elseif ( $f['approved'] ) { $stage = 'Provider approval recorded'; $action = 'Confirm medication payment and pharmacy handoff.'; $tone = 'blue'; }
	elseif ( 'created' === $f['lab'] && $f['requisition'] ) { $stage = 'Lab request created'; $action = 'Confirm requisition access and lab completion.'; $tone = 'blue'; $queue = 'labs'; }
	if ( $f['paid'] && ! $f['closed'] && ! $f['pharmacy'] ) {
		$flags[] = 'Payment recorded; pharmacy handoff unconfirmed'; $queue = 'fulfillment';
		if ( $f['age'] >= 7 ) { $tone = 'amber'; }
	}
	if ( 'completed' === $f['status'] ) { $stage = 'Order marked completed'; $action = 'Verify delivery evidence; review the next renewal.'; }
	if ( $f['closed'] ) { $stage = 'Order ' . $f['status']; $action = 'Review closure reason and subscription plan.'; $queue = 'closed'; $tone = 'gray'; }
	if ( $s['due_soon'] && ! $f['closed'] ) { $flags[] = 'Renewal due within 21 days'; if ( 'review' === $queue ) { $queue = 'renewal'; } }
	if ( $s['overdue'] ) { $flags[] = 'Active subscription has a past payment date'; $tone = 'amber'; }
	if ( $s['fees'] ) { $flags[] = 'Recurring subscription includes a lab / consultation fee — verify intent'; $tone = 'amber'; }
	if ( $s['zero'] ) { $flags[] = 'Active subscription total is zero — verify future billing'; $tone = 'amber'; }
	if ( $s['multiple'] ) { $flags[] = 'Multiple active TRT subscriptions'; $tone = 'amber'; }
	if ( ! empty( $s['partial_refund_active'] ) ) { $flags[] = 'Partial refund on an active subscription — verify context'; $tone = 'amber'; }
	if ( in_array( $f['lab'], array( 'submitting', 'uncertain', 'rejected' ), true ) ) {
		$flags[] = 'Lab request needs verification; do not resubmit blindly'; $queue = 'attention'; $tone = 'red'; $action = 'Verify the existing lab request with Prescribery.';
	}
	if ( 'failed' === $f['status'] ) { $flags[] = 'Order marked failed'; $queue = 'attention'; $tone = 'red'; $action = 'Review payment and provider records before retrying.'; }
	if ( $s['refund_active'] ) {
		$flags[] = 'Refunded order linked to an active subscription'; $queue = 'attention'; $tone = 'red'; $action = 'Reconcile the refund and future billing with the client.';
	}
	return compact( 'flags', 'queue', 'tone', 'stage', 'action' );
}

function wave_trt_date( $date ) { return $date ? wp_date( 'M j, Y', $date->getTimestamp() ) : 'Not recorded'; }
function wave_trt_money( $order, $amount ) { return wc_price( $amount, array( 'currency' => $order->get_currency() ) ); }

function wave_trt_patient_model( $patient ) {
	$order = $patient['orders'][0] ?? null;
	$record = $order ?: $patient['subscriptions'][0];
	$s = array( 'due_soon' => false, 'overdue' => false, 'fees' => false, 'zero' => false, 'multiple' => false, 'refund_active' => false, 'partial_refund_active' => false );
	$active = 0;
	foreach ( $patient['subscriptions'] as $sub ) {
		if ( ! $sub->has_status( 'active' ) ) { continue; }
		$active++;
		$next = $sub->get_time( 'next_payment' );
		$s['due_soon'] = $s['due_soon'] || ( $next && $next >= time() && $next <= time() + 21 * DAY_IN_SECONDS );
		$s['overdue'] = $s['overdue'] || ( $next && $next < time() );
		$s['zero'] = $s['zero'] || (float) $sub->get_total() <= 0;
		foreach ( $sub->get_fees() as $fee ) {
			if ( (float) $fee->get_total() > 0 && preg_match( '/consultation|blood\s*work|lab fee/i', $fee->get_name() ) ) { $s['fees'] = true; }
		}
		// Only flag a refund on this subscription's own related orders, not another plan.
		$related = $sub->get_related_orders( 'ids', array( 'parent', 'renewal' ) );
		foreach ( $patient['orders'] as $past ) {
			if ( ! in_array( $past->get_id(), array_map( 'intval', $related ), true ) ) { continue; }
			if ( $past->has_status( 'refunded' ) || ( (float) $past->get_total_refunded() > 0 && (float) $past->get_total_refunded() >= (float) $past->get_total() ) ) { $s['refund_active'] = true; }
			elseif ( (float) $past->get_total_refunded() > 0 ) { $s['partial_refund_active'] = true; }
		}
	}
	$s['multiple'] = $active > 1;
	$f = $order ? wave_trt_order_facts( $order ) : array( 'approved' => false, 'pharmacy' => false, 'paid' => false, 'age' => 0, 'events' => array(), 'lab' => '', 'requisition' => false, 'refunded' => false, 'closed' => false, 'status' => '' );
	$a = wave_trt_assess( $f, $s );
	return array_merge( $patient, compact( 'record', 'order', 'f', 'a', 's' ) );
}

function wave_trt_render() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to view this page.' ), '', array( 'response' => 403 ) ); }
	if ( ! function_exists( 'wc_get_orders' ) ) { echo '<div class="wrap"><h1>TRT Patients</h1><p>WooCommerce must be active to load patient records.</p></div>'; return; }
	list( $patients, $limited, $excluded ) = wave_trt_load_patients();
	$rows = array_map( 'wave_trt_patient_model', array_values( $patients ) );
	$rank = array( 'red' => 0, 'amber' => 1, 'gray' => 2, 'blue' => 3, 'green' => 4 );
	usort( $rows, function ( $a, $b ) use ( $rank ) { return ( $rank[ $a['a']['tone'] ] <=> $rank[ $b['a']['tone'] ] ) ?: ( $b['f']['age'] <=> $a['f']['age'] ); } );
	$counts = array( 'all' => count( $rows ), 'attention' => 0, 'fulfillment' => 0, 'labs' => 0, 'renewal' => 0, 'closed' => 0 );
	foreach ( $rows as $row ) {
		if ( in_array( $row['a']['tone'], array( 'red', 'amber' ), true ) ) { $counts['attention']++; }
		if ( isset( $counts[ $row['a']['queue'] ] ) && ! in_array( $row['a']['queue'], array( 'attention', 'renewal', 'closed' ), true ) ) { $counts[ $row['a']['queue'] ]++; }
		if ( $row['f']['closed'] ) { $counts['closed']++; }
		if ( $row['s']['due_soon'] ) { $counts['renewal']++; }
	}
	?>
	<div class="wrap wave-trt" id="wave-trt">
		<div class="wave-brand">WAVE CONSULTING <span>Patient operations</span></div>
		<header class="wave-heading"><div><p class="wave-eyebrow">MYOGENIX PHARMA</p><h1>TRT patient dashboard</h1><p>See the evidence. Find the next step. Keep every patient moving.</p></div><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wave-trt' ) ); ?>">Refresh records</a></header>
		<p class="wave-fresh">Live WordPress snapshot · <?php echo esc_html( wp_date( 'M j, Y · g:i a T' ) ); ?> · Read-only · Testosterone product #883 · <?php echo esc_html( $excluded ); ?> test records excluded</p>
		<?php if ( $limited ) : ?><div class="notice notice-warning inline"><p>Partial results: the scan reached 2,000 records of an order type. Older patients may be missing. Counts below cover loaded records only.</p></div><?php endif; ?>
		<div class="wave-notice"><strong>What these records can tell you</strong><p>Payments, order notes and subscriptions come from WooCommerce. Lab completion, intake and shipping are unconfirmed unless supported by verified data. “Completed” is an order status, not proof of delivery. Follow-up is flagged after 7 days from order creation; this is a review threshold, not a promised turnaround.</p><?php if ( defined( 'MYOGENIX_TRT_REDESIGN_LIVE' ) && ! MYOGENIX_TRT_REDESIGN_LIVE ) : ?><p><strong>Consent renewal rollout is off.</strong> Existing subscriptions may still use legacy billing. Confirm the provider handoff before enabling the new flow.</p><?php endif; ?></div>
		<div class="wave-stats" aria-label="Patient filters">
		<?php foreach ( array( 'all' => 'All patients', 'attention' => 'Needs attention', 'fulfillment' => 'Fulfillment review', 'labs' => 'Lab request created', 'renewal' => 'Renewal upcoming', 'closed' => 'Latest order closed' ) as $key => $label ) : ?>
			<button type="button" class="wave-stat <?php echo 'all' === $key ? 'is-active' : ''; ?>" data-filter="<?php echo esc_attr( $key ); ?>" aria-pressed="<?php echo 'all' === $key ? 'true' : 'false'; ?>"><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( $counts[ $key ] ); ?></strong></button>
		<?php endforeach; ?>
		</div>
		<div class="wave-tools"><label for="wave-search">Find a patient or order<input type="search" id="wave-search" placeholder="Name, email, order or subscription number" autocomplete="off"></label><span id="wave-result-count" role="status" aria-live="polite"><?php echo esc_html( count( $rows ) ); ?> patients</span></div>
		<div class="wave-legend"><span class="wave-badge red">Action needed</span><span class="wave-badge amber">Follow-up due</span><span class="wave-badge blue">Recorded progress</span><span class="wave-badge gray">Unconfirmed / closed</span><span>Counts can overlap. Each row summarizes the latest TRT order.</span></div>
		<div class="wave-patients">
		<?php foreach ( $rows as $row ) :
			$r = $row['record']; $o = $row['order']; $f = $row['f']; $a = $row['a'];
			$name = trim( $r->get_formatted_billing_full_name() ) ?: 'Customer #' . $r->get_customer_id();
			$ids = array_map( function ( $record ) { return (string) $record->get_id(); }, array_merge( $row['orders'], $row['subscriptions'] ) );
			$filters = array( 'all', $a['queue'] ); if ( in_array( $a['tone'], array( 'red', 'amber' ), true ) ) { $filters[] = 'attention'; }
			if ( $row['s']['due_soon'] ) { $filters[] = 'renewal'; }
			if ( $f['closed'] ) { $filters[] = 'closed'; }
			?>
			<article class="wave-patient" data-filters="<?php echo esc_attr( implode( ' ', array_unique( $filters ) ) ); ?>" data-search="<?php echo esc_attr( strtolower( $name . ' ' . $r->get_billing_email() . ' ' . implode( ' ', $ids ) ) ); ?>">
				<div class="wave-row">
					<div class="wave-person"><h2><?php echo esc_html( $name ); ?></h2><p><?php echo esc_html( $r->get_billing_email() ); ?></p><a href="<?php echo esc_url( $r->get_edit_order_url() ); ?>"><?php echo $o ? 'Order #' : 'Subscription #'; echo esc_html( $r->get_id() ); ?></a><span> · <?php echo esc_html( wave_trt_date( $r->get_date_created() ) ); ?></span></div>
					<div><span class="wave-label">Latest cycle evidence</span><span class="wave-badge <?php echo esc_attr( $a['tone'] ); ?>"><?php echo esc_html( $a['stage'] ); ?></span><p><?php echo $o ? esc_html( $f['age'] . ' days since order · ' . wc_get_order_status_name( $o->get_status() ) ) : 'No linked TRT order loaded'; ?></p></div>
					<div><span class="wave-label">Order payment</span><?php if ( $o ) : ?><strong><?php echo wp_kses_post( wave_trt_money( $o, $o->get_total() ) ); ?> order total</strong><p><?php echo $f['paid'] ? 'Transaction recorded' : 'Payment not verified'; ?></p><?php if ( $f['refunded'] ) : ?><p class="wave-refund"><?php echo wp_kses_post( wave_trt_money( $o, $o->get_total_refunded() ) ); ?> refunded</p><?php endif; ?><?php else : ?>Unconfirmed<?php endif; ?></div>
					<div class="wave-next"><span class="wave-label">Suggested next action</span><strong><?php echo esc_html( $a['action'] ); ?></strong></div>
				</div>
				<?php if ( $a['flags'] ) : ?><ul class="wave-flags"><?php foreach ( $a['flags'] as $flag ) : ?><li><?php echo esc_html( $flag ); ?></li><?php endforeach; ?></ul><?php endif; ?>
				<details><summary>View journey, subscriptions &amp; order history</summary><div class="wave-detail-body">
					<div class="wave-journey" aria-label="Confirmed milestones for the latest order">
					<?php foreach ( array( 'Order' => $o ? 'Recorded' : 'Unconfirmed', 'Intake' => 'Unconfirmed', 'Labs' => 'created' === $f['lab'] && $f['requisition'] ? 'Request created; results unconfirmed' : 'Unconfirmed', 'Provider' => $f['approved'] ? 'Approval recorded' : 'Unconfirmed', 'Payment' => $f['refunded'] ? 'Refund recorded' : ( $f['paid'] ? 'Transaction recorded; see order' : 'Unconfirmed' ), 'Pharmacy' => $f['pharmacy'] ? 'Handoff acknowledged' : 'Unconfirmed', 'Delivery' => 'Unconfirmed' ) as $step => $value ) : ?><div class="wave-step <?php echo 'Unconfirmed' === $value ? '' : 'recorded'; ?>"><strong><?php echo esc_html( $step ); ?></strong><span><?php echo esc_html( $value ); ?></span></div><?php endforeach; ?>
					</div>
					<div class="wave-detail-grid"><section><h3>Subscription &amp; next billing</h3>
					<?php if ( ! $row['subscriptions'] ) : ?><p>No TRT subscription matched to this customer. Check the order for legacy or guest records.</p><?php endif; ?>
					<?php foreach ( $row['subscriptions'] as $sub ) : ?><div class="wave-sub"><a href="<?php echo esc_url( $sub->get_edit_order_url() ); ?>">Subscription #<?php echo esc_html( $sub->get_id() ); ?></a> <strong><?php echo esc_html( wc_get_order_status_name( $sub->get_status() ) ); ?></strong><p><?php echo wp_kses_post( wave_trt_money( $sub, $sub->get_total() ) ); ?> every <?php echo esc_html( $sub->get_billing_interval() . ' ' . $sub->get_billing_period() . '(s)' ); ?></p><p>Next payment: <?php echo esc_html( $sub->get_time( 'next_payment' ) ? wp_date( 'M j, Y', $sub->get_time( 'next_payment' ) ) : 'Not scheduled' ); ?></p><p>Renewal consent: <?php echo esc_html( $sub->get_meta( '_trt_consent_resolved_action' ) ?: 'Not recorded' ); ?> <small>(stored response; verify cycle)</small></p></div><?php endforeach; ?>
					</section><section><h3>Latest order activity</h3><p class="wave-muted">Selected system notes, newest first. Clinical details and raw provider responses stay in the source record.</p><ol class="wave-events"><?php foreach ( $f['events'] as $event ) : ?><li><time><?php echo esc_html( wave_trt_date( $event['date'] ) ); ?></time> <?php echo esc_html( $event['label'] ); ?></li><?php endforeach; ?></ol><?php if ( ! $f['events'] ) : ?><p>No recognized milestone notes found. Open the order to verify.</p><?php endif; ?></section></div>
					<h3>TRT order history</h3><div class="wave-history"><table><thead><tr><th>Order</th><th>Created</th><th>Status</th><th>Total</th><th>Refunded</th></tr></thead><tbody><?php foreach ( $row['orders'] as $past ) : ?><tr><td><a href="<?php echo esc_url( $past->get_edit_order_url() ); ?>">#<?php echo esc_html( $past->get_id() ); ?></a></td><td><?php echo esc_html( wave_trt_date( $past->get_date_created() ) ); ?></td><td><?php echo esc_html( wc_get_order_status_name( $past->get_status() ) ); ?></td><td><?php echo wp_kses_post( wave_trt_money( $past, $past->get_total() ) ); ?></td><td><?php echo wp_kses_post( wave_trt_money( $past, $past->get_total_refunded() ) ); ?></td></tr><?php endforeach; ?></tbody></table></div>
				</div></details>
			</article>
		<?php endforeach; ?>
		</div><p id="wave-empty" <?php echo $rows ? 'hidden' : ''; ?>>No patients match this view.</p>
		<footer class="wave-footer">Wave Consulting · Operational visibility from existing records. Refresh to load new updates. Guest records are grouped by billing email; registered customers by customer ID. No charges, emails or subscription changes are triggered here.</footer>
	</div>
	<?php
}
