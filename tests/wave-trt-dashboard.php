<?php
/** Standalone regression checks. No WordPress connection or patient records. */
define( 'ABSPATH', __DIR__ );
function add_action( ...$args ) {}
require dirname( __DIR__ ) . '/inc/wave-trt-dashboard.php';
$facts = array( 'approved' => false, 'pharmacy' => false, 'paid' => false, 'age' => 0, 'lab' => '', 'requisition' => false, 'closed' => false, 'status' => 'processing' );
$subscription = array_fill_keys( array( 'due_soon', 'overdue', 'fees', 'zero', 'multiple', 'refund_active' ), false );
$checks = 0;
function check_rule( $condition, $label ) {
	global $checks;
	if ( ! $condition ) { fwrite( STDERR, "FAIL: $label\n" ); exit( 1 ); }
	$checks++;
}
$a = wave_trt_assess( $facts, $subscription );
check_rule( 'Journey unconfirmed' === $a['stage'], 'Processing alone proves no clinical milestone' );
$a = wave_trt_assess( array_merge( $facts, array( 'paid' => true, 'age' => 40 ) ), $subscription );
check_rule( 'amber' === $a['tone'] && 'fulfillment' === $a['queue'], 'Old payment without handoff requires follow-up' );
check_rule( 'Journey unconfirmed' === $a['stage'], 'Payment never proves provider approval' );
$a = wave_trt_assess( array_merge( $facts, array( 'paid' => true, 'age' => 3 ) ), $subscription );
check_rule( 'amber' !== $a['tone'], 'Young order does not trigger seven-day follow-up' );
$a = wave_trt_assess( array_merge( $facts, array( 'pharmacy' => true, 'approved' => true ) ), $subscription );
check_rule( 'Pharmacy handoff recorded' === $a['stage'] && str_contains( $a['action'], 'Confirm shipment' ), 'Pharmacy handoff does not prove shipment' );
$a = wave_trt_assess( array_merge( $facts, array( 'closed' => true, 'status' => 'refunded' ) ), array_merge( $subscription, array( 'refund_active' => true ) ) );
check_rule( 'red' === $a['tone'] && 'attention' === $a['queue'], 'Refund with active subscription is urgent' );
$a = wave_trt_assess( $facts, array_merge( $subscription, array( 'fees' => true ) ) );
check_rule( 'amber' === $a['tone'], 'Recurring consultation fee needs review' );
$a = wave_trt_assess( $facts, array_merge( $subscription, array( 'zero' => true ) ) );
check_rule( 'amber' === $a['tone'], 'Zero total active subscription needs review' );
$a = wave_trt_assess( array_merge( $facts, array( 'lab' => 'created', 'requisition' => false ) ), $subscription );
check_rule( 'Journey unconfirmed' === $a['stage'], 'Created label without requisition is not confirmed' );
$a = wave_trt_assess( array_merge( $facts, array( 'lab' => 'created', 'requisition' => true ) ), $subscription );
check_rule( 'Lab request created' === $a['stage'], 'Lab request never means labs completed' );
$a = wave_trt_assess( array_merge( $facts, array( 'lab' => 'uncertain' ) ), $subscription );
check_rule( 'red' === $a['tone'] && str_contains( $a['action'], 'existing lab request' ), 'Uncertain lab request requires verification' );
$a = wave_trt_assess( array_merge( $facts, array( 'status' => 'completed' ) ), $subscription );
check_rule( str_contains( $a['action'], 'Verify delivery' ), 'Completed does not prove delivery' );
$a = wave_trt_assess( $facts, array_merge( $subscription, array( 'due_soon' => true ) ) );
check_rule( 'renewal' === $a['queue'], 'Upcoming renewal is surfaced' );
$a = wave_trt_assess( array_merge( $facts, array( 'status' => 'failed' ) ), $subscription );
check_rule( 'red' === $a['tone'], 'Failed order is urgent' );
echo "$checks dashboard rules passed.\n";
