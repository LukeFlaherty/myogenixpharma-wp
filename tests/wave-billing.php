<?php
/** Standalone billing classification checks. No WordPress or order access. */
define( 'ABSPATH', __DIR__ );
function add_action( ...$args ) {}
require dirname( __DIR__ ) . '/inc/wave-billing.php';
$checks = 0;
function billing_check( $condition, $label ) {
	global $checks;
	if ( ! $condition ) { fwrite( STDERR, "FAIL: $label\n" ); exit( 1 ); }
	$checks++;
}
$base = array( 'status' => 'processing', 'transaction' => '', 'paid_date' => 0, 'total' => '100.00', 'refunded' => '0' );
$result = wave_billing_classify( array_merge( $base, array( 'transaction' => 'txn_1', 'paid_date' => 123 ) ) );
billing_check( $result['collected'] && 'collected' === $result['state'], 'Transaction plus paid date is collected' );
$result = wave_billing_classify( array_merge( $base, array( 'transaction' => 'txn_1' ) ) );
billing_check( ! $result['collected'] && 'review' === $result['state'], 'Transaction without paid date requires review' );
$result = wave_billing_classify( array_merge( $base, array( 'paid_date' => 123 ) ) );
billing_check( ! $result['collected'] && 'review' === $result['state'], 'Paid date without transaction requires review' );
$result = wave_billing_classify( array_merge( $base, array( 'status' => 'on-hold' ) ) );
billing_check( 'on-hold' === $result['state'] && ! $result['collected'], 'Unpaid hold stays distinct from collection' );
$result = wave_billing_classify( array_merge( $base, array( 'status' => 'on-hold', 'transaction' => 'txn_1', 'paid_date' => 123 ) ) );
billing_check( 'on-hold' === $result['state'] && $result['collected'], 'Paid hold keeps both facts' );
$result = wave_billing_classify( array_merge( $base, array( 'status' => 'failed' ) ) );
billing_check( 'failed' === $result['state'] && 'red' === $result['tone'], 'Failed payment is urgent' );
$result = wave_billing_classify( array_merge( $base, array( 'total' => '0' ) ) );
billing_check( 'no-charge' === $result['state'] && ! $result['collected'], 'Zero total is not collection' );
$result = wave_billing_classify( array_merge( $base, array( 'transaction' => 'txn_1', 'paid_date' => 123, 'refunded' => '25.00' ) ) );
billing_check( str_contains( $result['label'], 'refund' ) && 'amber' === $result['tone'], 'Collected order with refund is visibly qualified' );
echo "$checks billing rules passed.\n";
