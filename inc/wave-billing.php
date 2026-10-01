<?php
/** Wave Consulting billing overview. WooCommerce remains the source of truth. */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	$GLOBALS['wave_billing_hook'] = add_submenu_page( 'wave-trt', 'Billing overview', 'Billing', 'manage_woocommerce', 'wave-billing', 'wave_billing_render' );
}, 15 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( $hook !== ( $GLOBALS['wave_billing_hook'] ?? '' ) ) { return; }
	wp_enqueue_style( 'wave-trt', get_stylesheet_directory_uri() . '/assets/css/wave-trt-dashboard.css', array(), '1.1.1' );
	wp_enqueue_style( 'wave-billing', get_stylesheet_directory_uri() . '/assets/css/wave-billing.css', array( 'wave-trt' ), '1.0.0' );
	wp_enqueue_script( 'wave-billing', get_stylesheet_directory_uri() . '/assets/js/wave-billing.js', array(), '1.0.0', true );
} );

add_action( 'admin_init', function () {
	if ( isset( $_GET['page'] ) && 'wave-billing' === $_GET['page'] ) { nocache_headers(); }
} );

/** Pure classification: status labels never substitute for payment evidence. */
function wave_billing_classify( array $facts ) {
	$has_transaction = ! empty( $facts['transaction'] );
	$has_paid_date = ! empty( $facts['paid_date'] );
	$positive = (float) $facts['total'] > 0;
	$collected = $positive && $has_transaction && $has_paid_date;
	$status = (string) $facts['status'];
	$state = 'unpaid';
	$label = 'Payment not recorded';
	$detail = 'No transaction and paid date are recorded.';
	$tone = 'gray';

	if ( ! $positive ) {
		$state = 'no-charge'; $label = 'No charge'; $detail = 'The order total is zero.';
	} elseif ( 'on-hold' === $status ) {
		$state = 'on-hold'; $label = $collected ? 'Collected · on hold' : 'On hold';
		$detail = $collected ? 'Payment evidence exists; the order is still on hold.' : 'Payment collection is not verified; review the order.';
		$tone = 'amber';
	} elseif ( 'failed' === $status ) {
		$state = 'failed'; $label = 'Payment failed'; $detail = 'Review the gateway response before retrying.'; $tone = 'red';
	} elseif ( $has_transaction xor $has_paid_date ) {
		$state = 'review'; $label = 'Payment evidence mismatch'; $detail = 'A transaction or paid date is missing.'; $tone = 'red';
	} elseif ( $collected ) {
		$state = 'collected'; $label = 'Collected'; $detail = 'Transaction and paid date are recorded.'; $tone = 'blue';
	} elseif ( in_array( $status, array( 'cancelled', 'refunded', 'rejected' ), true ) ) {
		$state = 'closed'; $label = 'Closed without verified collection'; $detail = 'Review the source order if payment was expected.';
	} elseif ( 'pending' === $status ) {
		$state = 'pending'; $label = 'Pending payment'; $detail = 'Payment has not been verified.'; $tone = 'amber';
	}

	if ( (float) ( $facts['refunded'] ?? 0 ) > 0 ) {
		$detail .= ' A refund is recorded on this order.';
		if ( 'collected' === $state ) { $label = 'Collected · refund recorded'; $tone = 'amber'; }
	}
	return compact( 'collected', 'state', 'label', 'detail', 'tone' );
}

function wave_billing_requested_range() {
	$timezone = wp_timezone();
	$default_from = wp_date( 'Y-m-01' );
	$default_to = wp_date( 'Y-m-d' );
	$from_value = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : $default_from;
	$to_value = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : $default_to;
	$from = DateTimeImmutable::createFromFormat( '!Y-m-d', $from_value, $timezone );
	$to = DateTimeImmutable::createFromFormat( '!Y-m-d', $to_value, $timezone );
	if ( ! $from || $from->format( 'Y-m-d' ) !== $from_value ) { $from = new DateTimeImmutable( $default_from, $timezone ); }
	if ( ! $to || $to->format( 'Y-m-d' ) !== $to_value ) { $to = new DateTimeImmutable( $default_to, $timezone ); }
	if ( $from > $to ) { $swap = $from; $from = $to; $to = $swap; }
	if ( $from->diff( $to )->days > 366 ) { $from = $to->modify( '-366 days' ); }
	return array( $from, $to, $from->getTimestamp(), $to->modify( '+1 day' )->getTimestamp() );
}

function wave_billing_in_range( $timestamp, $start, $end ) {
	return $timestamp && $timestamp >= $start && $timestamp < $end;
}

function wave_billing_add_amount( &$totals, $currency, $amount ) {
	$currency = $currency ?: get_woocommerce_currency();
	$totals[ $currency ] = ( $totals[ $currency ] ?? 0 ) + (float) $amount;
}

function wave_billing_money( $totals ) {
	if ( ! $totals ) { return wc_price( 0 ); }
	ksort( $totals );
	$labels = array();
	foreach ( $totals as $currency => $amount ) { $labels[] = wc_price( $amount, array( 'currency' => $currency ) ); }
	return implode( ' / ', $labels );
}

function wave_billing_hold_reason( $order, $classification ) {
	if ( 'on-hold' !== $classification['state'] ) { return $classification['detail']; }
	if ( function_exists( 'myogenix_trt_is_renewal_order' ) && myogenix_trt_is_renewal_order( $order ) ) {
		if ( $order->get_meta( '_trt_waiting_approval' ) ) { return 'Provider approval is recorded; payment is blocked until quarterly intake is verified.'; }
		if ( function_exists( 'myogenix_trt_intake_verified' ) && ! myogenix_trt_intake_verified( $order ) ) { return 'Quarterly intake must be verified before payment can proceed.'; }
	}
	return $classification['detail'];
}

/** Scan through WooCommerce CRUD for HPOS and legacy compatibility. */
function wave_billing_load( $start, $end ) {
	$orders = array(); $limited = false; $excluded = 0;
	$statuses = array_keys( wc_get_order_statuses() );
	for ( $page = 1; $page <= 20; $page++ ) {
		$result = wc_get_orders( array( 'type' => 'shop_order', 'status' => $statuses, 'limit' => 100, 'page' => $page, 'paginate' => true, 'orderby' => 'date', 'order' => 'DESC' ) );
		foreach ( $result->orders as $order ) {
			if ( $order->has_status( array( 'trash', 'auto-draft', 'checkout-draft' ) ) ) { continue; }
			if ( function_exists( 'wave_trt_test_record' ) && wave_trt_test_record( $order ) ) { $excluded++; continue; }
			$created = $order->get_date_created(); $paid = $order->get_date_paid();
			$created_at = $created ? $created->getTimestamp() : 0; $paid_at = $paid ? $paid->getTimestamp() : 0;
			$period_refunds = 0.0; $latest_refund_at = 0;
			foreach ( $order->get_refunds() as $refund ) {
				$refund_date = $refund->get_date_created(); $refund_at = $refund_date ? $refund_date->getTimestamp() : 0;
				if ( wave_billing_in_range( $refund_at, $start, $end ) ) { $period_refunds += abs( (float) $refund->get_amount() ); }
				$latest_refund_at = max( $latest_refund_at, $refund_at );
			}
			$facts = array( 'status' => $order->get_status(), 'transaction' => $order->get_transaction_id(), 'paid_date' => $paid_at, 'total' => $order->get_total(), 'refunded' => $order->get_total_refunded() );
			$class = wave_billing_classify( $facts );
			$open = in_array( $order->get_status(), array( 'pending', 'processing', 'on-hold', 'failed' ), true );
			$period_activity = wave_billing_in_range( $created_at, $start, $end ) || wave_billing_in_range( $paid_at, $start, $end ) || $period_refunds > 0;
			if ( ! $open && ! $period_activity ) { continue; }
			$items = array(); foreach ( $order->get_items() as $item ) { $items[] = $item->get_name(); }
			$orders[] = array(
				'order' => $order, 'class' => $class, 'created_at' => $created_at, 'paid_at' => $paid_at,
				'period_collected' => $class['collected'] && wave_billing_in_range( $paid_at, $start, $end ) ? (float) $order->get_total() : 0.0,
				'period_refunds' => $period_refunds, 'latest_refund_at' => $latest_refund_at, 'open' => $open,
				'items' => implode( ', ', array_slice( $items, 0, 3 ) ) . ( count( $items ) > 3 ? ' +' . ( count( $items ) - 3 ) . ' more' : '' ),
			);
		}
		if ( $page >= $result->max_num_pages ) { break; }
		if ( 20 === $page ) { $limited = true; }
	}
	$subscriptions = array();
	if ( function_exists( 'wcs_get_subscription_statuses' ) ) {
		$result = wc_get_orders( array( 'type' => 'shop_subscription', 'status' => array( 'wc-active' ), 'limit' => 2000, 'paginate' => true, 'orderby' => 'date', 'order' => 'DESC' ) );
		foreach ( $result->orders as $subscription ) {
			if ( function_exists( 'wave_trt_test_record' ) && wave_trt_test_record( $subscription ) ) { continue; }
			$next = $subscription->get_time( 'next_payment' );
			if ( $next && $next >= time() && $next <= time() + 30 * DAY_IN_SECONDS ) { $subscriptions[] = $subscription; }
		}
		if ( $result->max_num_pages > 1 ) { $limited = true; }
	}
	return compact( 'orders', 'subscriptions', 'limited', 'excluded' );
}

function wave_billing_render() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to view this page.' ), '', array( 'response' => 403 ) ); }
	if ( ! function_exists( 'wc_get_orders' ) ) { echo '<div class="wrap"><h1>Billing overview</h1><p>WooCommerce must be active.</p></div>'; return; }
	list( $from, $to, $start, $end ) = wave_billing_requested_range();
	$data = wave_billing_load( $start, $end );
	$totals = array( 'collected' => array(), 'refunded' => array(), 'processing' => array(), 'hold' => array(), 'upcoming' => array() );
	$counts = array( 'all' => count( $data['orders'] ), 'collected' => 0, 'processing' => 0, 'on-hold' => 0, 'refunded' => 0, 'review' => 0 );
	foreach ( $data['orders'] as &$row ) {
		$order = $row['order']; $currency = $order->get_currency(); $filters = array();
		if ( $row['period_collected'] > 0 ) { wave_billing_add_amount( $totals['collected'], $currency, $row['period_collected'] ); $filters[] = 'collected'; $counts['collected']++; }
		if ( $row['period_refunds'] > 0 ) { wave_billing_add_amount( $totals['refunded'], $currency, $row['period_refunds'] ); $filters[] = 'refunded'; $counts['refunded']++; }
		if ( 'processing' === $order->get_status() && $row['class']['collected'] ) { wave_billing_add_amount( $totals['processing'], $currency, max( 0, (float) $order->get_total() - (float) $order->get_total_refunded() ) ); $filters[] = 'processing'; $counts['processing']++; }
		if ( 'on-hold' === $order->get_status() ) { wave_billing_add_amount( $totals['hold'], $currency, max( 0, (float) $order->get_total() - (float) $order->get_total_refunded() ) ); $filters[] = 'on-hold'; $counts['on-hold']++; }
		if ( in_array( $row['class']['state'], array( 'failed', 'review', 'pending', 'on-hold' ), true ) ) { $filters[] = 'review'; $counts['review']++; }
		$row['filters'] = array_unique( $filters );
	}
	unset( $row );
	foreach ( $data['subscriptions'] as $subscription ) { wave_billing_add_amount( $totals['upcoming'], $subscription->get_currency(), $subscription->get_total() ); }
	usort( $data['orders'], function ( $a, $b ) {
		$rank = array( 'failed' => 0, 'review' => 1, 'on-hold' => 2, 'pending' => 3, 'collected' => 4, 'closed' => 5, 'no-charge' => 6, 'unpaid' => 7 );
		return ( ( $rank[ $a['class']['state'] ] ?? 9 ) <=> ( $rank[ $b['class']['state'] ] ?? 9 ) ) ?: ( $b['created_at'] <=> $a['created_at'] );
	} );
	$net = $totals['collected']; foreach ( $totals['refunded'] as $currency => $amount ) { $net[ $currency ] = ( $net[ $currency ] ?? 0 ) - $amount; }
	?>
	<div class="wrap wave-trt wave-billing" id="wave-billing">
		<div class="wave-brand">WAVE CONSULTING <span>MYOGENIX PHARMA</span></div>
		<header class="wave-heading"><div><p class="wave-eyebrow">BILLING CONTROL CENTER</p><h1>Know what moved—and what needs attention</h1><p>WooCommerce collections, refunds, open orders, holds and scheduled renewals in one review queue.</p></div><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders' ) ); ?>">All WooCommerce orders ↗</a></header>
		<form class="wave-billing-range" method="get"><input type="hidden" name="page" value="wave-billing"><label>From<input type="date" name="from" value="<?php echo esc_attr( $from->format( 'Y-m-d' ) ); ?>"></label><label>Through<input type="date" name="to" value="<?php echo esc_attr( $to->format( 'Y-m-d' ) ); ?>"></label><button class="button button-primary">Update period</button><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wave-billing' ) ); ?>">This month</a><p>Period totals use payment and refund dates. Open queues remain visible even when the order began earlier.</p></form>
		<div class="wave-notice"><strong>How to read this view</strong><p><strong>Collected</strong> requires both a transaction reference and paid date. <strong>Paid &amp; processing</strong> means money is recorded while fulfillment remains open. <strong>On hold</strong> is WooCommerce’s current status and may be paid or unpaid—open the order for the reason. Totals are WooCommerce records, not processor settlements, bank deposits, fees, disputes or tax accounting.</p></div>
		<section class="wave-billing-stats" aria-label="Billing summary">
			<button type="button" class="wave-billing-stat is-active" data-billing-filter="all"><span>Net recorded movement</span><strong><?php echo wp_kses_post( wave_billing_money( $net ) ); ?></strong><small><?php echo esc_html( $counts['all'] ); ?> relevant orders</small></button>
			<button type="button" class="wave-billing-stat" data-billing-filter="collected"><span>Collected in period</span><strong><?php echo wp_kses_post( wave_billing_money( $totals['collected'] ) ); ?></strong><small><?php echo esc_html( $counts['collected'] ); ?> orders with payment evidence</small></button>
			<button type="button" class="wave-billing-stat" data-billing-filter="refunded"><span>Refunded in period</span><strong><?php echo wp_kses_post( wave_billing_money( $totals['refunded'] ) ); ?></strong><small><?php echo esc_html( $counts['refunded'] ); ?> orders</small></button>
			<button type="button" class="wave-billing-stat" data-billing-filter="processing"><span>Paid &amp; processing now</span><strong><?php echo wp_kses_post( wave_billing_money( $totals['processing'] ) ); ?></strong><small><?php echo esc_html( $counts['processing'] ); ?> awaiting completion</small></button>
			<button type="button" class="wave-billing-stat" data-billing-filter="on-hold"><span>On hold now</span><strong><?php echo esc_html( $counts['on-hold'] ); ?> orders</strong><small><?php echo wp_kses_post( wave_billing_money( $totals['hold'] ) ); ?> listed value</small></button>
			<button type="button" class="wave-billing-stat" data-billing-filter="review"><span>Needs billing review</span><strong><?php echo esc_html( $counts['review'] ); ?> orders</strong><small>Holds, failures, pending or mismatched evidence</small></button>
		</section>
		<section class="wave-billing-forward"><div><span>Next 30 days</span><strong><?php echo wp_kses_post( wave_billing_money( $totals['upcoming'] ) ); ?></strong><p><?php echo esc_html( count( $data['subscriptions'] ) ); ?> active subscriptions have a stored next-payment date. This is scheduled value, not guaranteed revenue.</p></div><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders--shop_subscription' ) ); ?>">Review subscriptions ↗</a></section>
		<div class="wave-tools"><label>Find a customer or order<input id="wave-billing-search" type="search" placeholder="Name, email, order, product or transaction" autocomplete="off"></label><span id="wave-billing-result-count" aria-live="polite"></span></div>
		<div class="wave-billing-table"><table><thead><tr><th>Customer / order</th><th>Payment evidence</th><th>Amount</th><th>Current workflow</th><th>Next action</th></tr></thead><tbody>
		<?php foreach ( $data['orders'] as $row ) : $order = $row['order']; $class = $row['class']; $name = trim( $order->get_formatted_billing_full_name() ) ?: 'Guest customer'; $transaction = $order->get_transaction_id(); ?>
			<tr data-billing-row data-filters="<?php echo esc_attr( implode( ' ', $row['filters'] ) ); ?>" data-search="<?php echo esc_attr( strtolower( implode( ' ', array( $name, $order->get_billing_email(), $order->get_order_number(), $row['items'], $transaction, $order->get_payment_method_title() ) ) ) ); ?>">
				<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><strong><?php echo esc_html( $name ); ?></strong></a><small><?php echo esc_html( $order->get_billing_email() ?: 'No billing email' ); ?></small><small>Order #<?php echo esc_html( $order->get_order_number() ); ?> · <?php echo esc_html( $row['created_at'] ? wp_date( 'M j, Y', $row['created_at'] ) : 'date missing' ); ?></small><small><?php echo esc_html( $row['items'] ?: 'No line items' ); ?></small></td>
				<td><span class="wave-badge <?php echo esc_attr( $class['tone'] ); ?>"><?php echo esc_html( $class['label'] ); ?></span><small><?php echo esc_html( $order->get_payment_method_title() ?: 'Payment method not recorded' ); ?></small><small><?php echo $row['paid_at'] ? 'Paid ' . esc_html( wp_date( 'M j, Y', $row['paid_at'] ) ) : 'Paid date not recorded'; ?></small><?php if ( $transaction ) : ?><code title="<?php echo esc_attr( $transaction ); ?>"><?php echo esc_html( strlen( $transaction ) > 22 ? substr( $transaction, 0, 10 ) . '…' . substr( $transaction, -8 ) : $transaction ); ?></code><?php endif; ?></td>
				<td><strong><?php echo wp_kses_post( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ); ?></strong><small>Order total</small><?php if ( (float) $order->get_total_refunded() > 0 ) : ?><small class="wave-refund"><?php echo wp_kses_post( wc_price( $order->get_total_refunded(), array( 'currency' => $order->get_currency() ) ) ); ?> refunded total</small><?php endif; ?><?php if ( $row['period_refunds'] > 0 ) : ?><small><?php echo wp_kses_post( wc_price( $row['period_refunds'], array( 'currency' => $order->get_currency() ) ) ); ?> refunded in period</small><?php endif; ?></td>
				<td><strong><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></strong><small><?php echo esc_html( wave_billing_hold_reason( $order, $class ) ); ?></small></td>
				<td><a class="button" href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">Open source order</a><?php if ( 'processing' === $order->get_status() && $class['collected'] ) : ?><small>Confirm fulfillment; do not collect again.</small><?php elseif ( 'on-hold' === $order->get_status() ) : ?><small>Check notes and gateway response before changing status.</small><?php elseif ( in_array( $class['state'], array( 'failed', 'review', 'pending' ), true ) ) : ?><small>Reconcile payment evidence before retrying or fulfilling.</small><?php else : ?><small>Source record has the audit trail and refund controls.</small><?php endif; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table><p id="wave-billing-empty" hidden>No orders match this filter and search.</p></div>
		<p class="wave-footer">Loaded up to 2,000 recent orders and 2,000 active subscriptions through WooCommerce. <?php if ( $data['limited'] ) : ?><strong>The scan limit was reached; totals may be incomplete.</strong> <?php endif; ?><?php echo esc_html( $data['excluded'] ); ?> recognized test records excluded. Refresh to read current records; nothing on this page charges, refunds or changes an order.</p>
	</div>
	<?php
}
