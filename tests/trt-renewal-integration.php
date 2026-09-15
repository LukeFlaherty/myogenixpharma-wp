<?php
/**
 * Controlled WP-CLI integration suite. Creates only marked fake records, mocks
 * outbound HTTP/mail, and puts all fixtures on hold before trashing them.
 * Run with --skip-themes and TRT_TEST_SOURCE pointing to the candidate directory.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! getenv( 'TRT_TEST_SOURCE' ) ) { exit( 'WP-CLI test runner only.' ); }
require rtrim( getenv( 'TRT_TEST_SOURCE' ), '/' ) . '/trt-renewal-redesign.php';
myogenix_trt_install_renewal_guards();
$test_ids = array(); $test_checks = 0; $test_http = array(); $test_mail = array(); $test_lab_mode = 'success';
$old_qa = get_option( 'myogenix_trt_qa', null );
$assert = function ( $condition, $message ) use ( &$test_checks ) { if ( ! $condition ) { throw new RuntimeException( $message ); } ++$test_checks; echo "PASS: $message\n"; };
add_filter( 'pre_wp_mail', function ( $result, $mail ) use ( &$test_mail ) { $test_mail[] = $mail; return true; }, 999, 2 );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$test_http, &$test_lab_mode ) {
	$test_http[] = $url;
	if ( str_contains( $url, '/access-token' ) ) { $body = array( 'data' => array( 'access_token' => 'qa-only-not-a-real-token', 'expires_in' => 1 ) ); }
	elseif ( str_contains( $url, '/save-test-order' ) ) {
		if ( 'timeout' === $test_lab_mode ) { return new WP_Error( 'http_request_failed', 'QA simulated timeout' ); }
		if ( 'reject' === $test_lab_mode ) { return array( 'headers' => array(), 'body' => '{"message":"QA invalid test"}', 'response' => array( 'code' => 422, 'message' => 'Unprocessable' ), 'cookies' => array() ); }
		$body = array( 'token' => array( 'qa-requisition-token' ) );
	} else { $body = array( 'qa_intercepted' => true ); }
	return array( 'headers' => array(), 'body' => wp_json_encode( $body ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
}, 999, 3 );
// Prevent the auth mock from ever entering the real site's token cache.
add_filter( 'pre_option_myogenix_trt_lab_api', function () { return array( 'active_env' => 'sandbox', 'sandbox' => array( 'api_base_url' => 'https://staging.prescribery.com/api/v2', 'api_key' => 'integration-fixture', 'client_id' => 16, 'source_id' => 1198, 'lab_id' => 1057, 'test_ids' => array( 59, 5 ) ) ); } );
$qa_user = get_user_by( 'login', 'trt_renewal_qa_20260915' );
$qa_user_id = $qa_user ? $qa_user->ID : wp_insert_user( array( 'user_login' => 'trt_renewal_qa_20260915', 'user_pass' => wp_generate_password( 40, true, true ), 'user_email' => 'luke+trt-qa-20260915@waveconsulting.biz', 'display_name' => 'TRT TEST ONLY', 'role' => 'customer' ) );
if ( is_wp_error( $qa_user_id ) ) { throw new RuntimeException( 'Unable to create test customer' ); }
$fixture = function ( $days = 65, $price = 0, $trt = true ) use ( &$test_ids, $qa_user_id ) {
	$parent = wc_create_order( array( 'status' => 'pending', 'customer_id' => $qa_user_id, 'created_via' => 'trt-qa' ) );
	$parent->set_billing_first_name( 'TRT TEST' ); $parent->set_billing_last_name( 'Renewal QA' ); $parent->set_billing_email( 'luke@waveconsulting.biz' );
	$parent->update_meta_data( '_trt_qa_test', 'yes' ); $parent->save(); $test_ids[] = $parent->get_id();
	$sub = wcs_create_subscription( array( 'order_id' => $parent->get_id(), 'customer_id' => $qa_user_id, 'status' => 'pending', 'billing_period' => 'month', 'billing_interval' => 3, 'start_date' => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
	if ( is_wp_error( $sub ) ) { throw new RuntimeException( $sub->get_error_message() ); }
	$test_ids[] = $sub->get_id();
	$sub->set_billing_first_name( 'Luke' ); $sub->set_billing_last_name( 'TEST ONLY' ); $sub->set_billing_email( 'luke@waveconsulting.biz' );
	$item = new WC_Order_Item_Product(); $item->set_name( $trt ? 'TEST Testosterone renewal' : 'TEST unrelated subscription' ); $item->set_product_id( $trt ? 883 : 1051 ); $item->set_variation_id( $trt ? 2133 : ( wc_get_product( 1051 )->get_children()[0] ?? 0 ) ); $item->set_quantity( 1 ); $item->set_subtotal( $price ); $item->set_total( $price ); $item->update_meta_data( '_hidden_approval_price', 567 ); $sub->add_item( $item );
	$sub->update_meta_data( '_trt_qa_test', 'yes' ); $sub->update_meta_data( '_trt_cycle_start', time() - $days * DAY_IN_SECONDS ); $sub->update_meta_data( '_prescribery_patient_id', 1 );
	$sub->set_requires_manual_renewal( true ); $sub->calculate_totals(); $sub->save();
	$sub->update_dates( array( 'next_payment' => gmdate( 'Y-m-d H:i:s', time() + 25 * DAY_IN_SECONDS ) ) ); $sub->set_status( 'active', 'TEST ONLY: controlled integration fixture.' ); $sub->save();
	$qa = get_option( 'myogenix_trt_qa', array() ); $qa['subscription_ids'][] = $sub->get_id(); $qa['email'] = 'luke@waveconsulting.biz'; $qa['expires_at'] = time() + HOUR_IN_SECONDS; update_option( 'myogenix_trt_qa', $qa, false );
	return $sub;
};
$params_for = function ( $sub, $action = 'continue' ) { $cycle = myogenix_trt_cycle_start_ts( $sub ); return array( 'subscription_id' => $sub->get_id(), 'cycle_start' => $cycle, 'action' => $action, 'token' => myogenix_trt_consent_token( $sub->get_id(), $cycle ) ); };
try {
	$sub = $fixture(); $id = $sub->get_id(); $cycle = myogenix_trt_cycle_start_ts( $sub );
	$assert( myogenix_trt_lock( $id ), 'Database lock is available' ); myogenix_trt_unlock( $id );
	$sub->update_meta_data( '_trt_consent_sent_for', $cycle ); $sub->save();
	myogenix_trt_check_subscription( $id ); $sub = wcs_get_subscription( $id );
	$assert( (int) $sub->get_meta( '_trt_patient_email_for' ) === $cycle, 'Old admin-only detection does not suppress patient email' );
	$count = count( $test_mail ); myogenix_trt_check_subscription( $id ); $assert( count( $test_mail ) === $count, 'Daily rerun does not resend email' );
	$p = $params_for( $sub );
	$assert( ! is_wp_error( myogenix_trt_validate_consent_request( $p ) ), 'Valid signed link accepted without mutation' );
	$assert( ! $sub->get_meta( '_trt_pending_renewal_order' ), 'Opening link does not create an order' );
	$bad = $p; $bad['token'] = 'invalid'; $assert( is_wp_error( myogenix_trt_validate_consent_request( $bad ) ), 'Invalid signature rejected' );
	$bad = $p; $bad['token'] = array(); $assert( is_wp_error( myogenix_trt_validate_consent_request( $bad ) ), 'Malformed array input rejected' );
	$bad = $p; $bad['cycle_start'] -= DAY_IN_SECONDS; $bad['token'] = myogenix_trt_consent_token( $id, $bad['cycle_start'] ); $assert( is_wp_error( myogenix_trt_validate_consent_request( $bad ) ), 'Signed stale-cycle link rejected' );
	$result = myogenix_trt_process_consent( $p ); $assert( ! is_wp_error( $result ), 'Continue creates a renewal and requisition' );
	$order = wc_get_order( $result['order_id'] ); $test_ids[] = $order->get_id();
	$assert( $order->has_status( 'pending' ) && ! $order->get_transaction_id(), 'Renewal stays unpaid' );
	$assert( $order->get_meta( '_prescribery_requisition_id' ) === 'qa-requisition-token', 'Lab token is mapped to renewal' );
	$assert( ! $order->needs_payment(), 'Patient cannot bypass provider approval through pay link' );
	$assert( (float) $order->get_total() === 567.0, 'Zero-price copied line receives established approval price' );
	$assert( myogenix_trt_cycle_start_ts( wcs_get_subscription( $id ) ) === $cycle, 'Unpaid renewal does not advance cycle' );
	$http_count = count( $test_http ); $assert( is_wp_error( myogenix_trt_process_consent( $p ) ), 'Repeated consent rejected' ); $assert( count( $test_http ) === $http_count, 'Repeated click sends no second lab request' );
	$other = $fixture( 65, 10, false );
	do_action( 'woocommerce_scheduled_subscription_payment', $id );
	$assert( count( wcs_get_subscription( $id )->get_related_orders( 'ids', 'renewal' ) ) === 1, 'Native TRT scheduler creates no extra order' );
	do_action( 'woocommerce_scheduled_subscription_payment', $other->get_id() );
	$renewals = wcs_get_subscription( $other->get_id() )->get_related_orders( 'ids', 'renewal' ); $test_ids = array_merge( $test_ids, $renewals );
	$assert( count( $renewals ) === 1 && wcs_get_subscription( $other->get_id() )->has_status( 'on-hold' ), 'Non-TRT renewal still runs in the same request' );
	$decline = $fixture(); myogenix_trt_check_subscription( $decline->get_id() );
	$assert( ! is_wp_error( myogenix_trt_process_consent( $params_for( $decline, 'decline' ) ) ), 'Decline accepted' );
	$assert( wcs_get_subscription( $decline->get_id() )->has_status( 'on-hold' ), 'Decline puts subscription on hold' );
	$late = $fixture( 80 ); myogenix_trt_check_subscription( $late->get_id() );
	$assert( (bool) wcs_get_subscription( $late->get_id() )->get_meta( '_trt_patient_email_for' ), 'Missed week-9 window catches up before deadline' );
	$expired = $fixture( 86 ); myogenix_trt_check_subscription( $expired->get_id() );
	$assert( wcs_get_subscription( $expired->get_id() )->has_status( 'on-hold' ), 'Expired consent pauses without charging' );
	$assert( is_wp_error( myogenix_trt_validate_consent_request( $params_for( $expired ) ) ), 'Expired signed token rejected' );
	$failure = $fixture(); myogenix_trt_check_subscription( $failure->get_id() ); $test_lab_mode = 'timeout';
	$failed = myogenix_trt_process_consent( $params_for( $failure ) );
	$assert( is_wp_error( $failed ), 'API timeout returns a recoverable staff-review error' );
	$failure = wcs_get_subscription( $failure->get_id() ); $test_ids[] = $failure->get_meta( '_trt_pending_renewal_order' );
	$assert( ! $failure->get_meta( '_trt_consent_resolved_for' ), 'Failed submission is not marked resolved' );
	$before = count( $test_http ); myogenix_trt_process_consent( $params_for( $failure ) );
	$assert( count( $test_http ) === $before, 'Uncertain API outcome cannot issue a duplicate lab request' );
	$discounted = $fixture( 65, 283.50 ); myogenix_trt_check_subscription( $discounted->get_id() ); $test_lab_mode = 'success';
	$discount_result = myogenix_trt_process_consent( $params_for( $discounted ) ); $assert( ! is_wp_error( $discount_result ), 'Discounted renewal created' ); $test_ids[] = $discount_result['order_id'];
	$assert( (float) wc_get_order( $discount_result['order_id'] )->get_total() === 283.50, 'Established half-price plan is preserved' );
	$due_before = wcs_get_subscription( $id = $sub->get_id() )->get_time( 'next_payment' );
	$order->update_meta_data( '_trt_provider_approved', 'yes' ); $order->update_meta_data( '_prescription_stripe_charging', 1 ); $order->save();
	$order->payment_complete( 'qa_simulated_payment_no_real_charge' );
	$updated_sub = wcs_get_subscription( $id );
	$assert( $updated_sub->get_time( 'next_payment' ) === wcs_add_time( 3, 'month', $due_before ), 'Early payment advances schedule without losing prepaid days' );
	$assert( myogenix_trt_cycle_start_ts( $updated_sub ) === $due_before, 'Next consent cycle starts at the original renewal date' );
	$next_due = $updated_sub->get_time( 'next_payment' ); do_action( 'woocommerce_payment_complete', $order->get_id() );
	$assert( wcs_get_subscription( $id )->get_time( 'next_payment' ) === $next_due, 'Duplicate payment callback does not advance twice' );
	echo "SUCCESS: $test_checks integration checks passed.\n";
} finally {
	foreach ( array_unique( array_filter( $test_ids ) ) as $id ) {
		$o = wc_get_order( $id ); if ( ! $o || $o->get_billing_email() !== 'luke@waveconsulting.biz' ) { continue; }
		if ( $o instanceof WC_Subscription && $o->has_status( 'active' ) ) { $o->update_status( 'on-hold', 'TEST COMPLETE' ); }
		$o->delete( false );
	}
	if ( null === $old_qa ) { delete_option( 'myogenix_trt_qa' ); } else { update_option( 'myogenix_trt_qa', $old_qa, false ); }
}
