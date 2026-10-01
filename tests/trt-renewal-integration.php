<?php
/**
 * Controlled WP-CLI integration suite. Creates only marked fake records, mocks
 * outbound HTTP/mail, and puts all fixtures on hold before trashing them.
 * Run with --skip-themes and TRT_TEST_SOURCE pointing to the candidate directory.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! getenv( 'TRT_TEST_SOURCE' ) ) { exit( 'WP-CLI test runner only.' ); }
require rtrim( getenv( 'TRT_TEST_SOURCE' ), '/' ) . '/trt-renewal-redesign.php';
myogenix_trt_install_renewal_guards();
$test_ids = array(); $test_checks = 0; $test_http = array(); $test_mail = array(); $test_lab_mode = 'success'; $test_intake_mode = 'success'; $test_questionnaire_mode = 'success'; $test_requests = array();
$old_qa = get_option( 'myogenix_trt_qa', null );
$assert = function ( $condition, $message ) use ( &$test_checks ) { if ( ! $condition ) { throw new RuntimeException( $message ); } ++$test_checks; echo "PASS: $message\n"; };
add_filter( 'pre_wp_mail', function ( $result, $mail ) use ( &$test_mail ) { $test_mail[] = $mail; return true; }, 999, 2 );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$test_http, &$test_lab_mode, &$test_intake_mode, &$test_questionnaire_mode, &$test_requests ) {
	if ( false !== $pre ) { return $pre; }
	$test_http[] = $url;
	$test_requests[] = array( 'url' => $url, 'body' => json_decode( $args['body'] ?? '{}', true ) );
	if ( str_contains( $url, '/access-token' ) ) { $body = array( 'data' => array( 'access_token' => 'qa-only-not-a-real-token', 'expires_in' => 1 ) ); }
	elseif ( str_contains( $url, '/shopify/callback' ) ) {
		if ( 'timeout' === $test_intake_mode ) { return new WP_Error( 'http_request_failed', 'QA intake timeout' ); }
		$body = array( 'ok' => 'success' === $test_intake_mode );
	}
	elseif ( str_contains( $url, '/save-test-order' ) ) {
		if ( 'timeout' === $test_lab_mode ) { return new WP_Error( 'http_request_failed', 'QA simulated timeout' ); }
		if ( 'reject' === $test_lab_mode ) { return array( 'headers' => array(), 'body' => '{"message":"QA invalid test"}', 'response' => array( 'code' => 422, 'message' => 'Unprocessable' ), 'cookies' => array() ); }
		$body = array( 'token' => array( 'qa-requisition-token' ) );
	} elseif ( str_contains( $url, '/questionnaires/answers' ) ) {
		if ( 'timeout' === $test_questionnaire_mode ) { return new WP_Error( 'http_request_failed', 'QA questionnaire timeout' ); }
		if ( 'reject' === $test_questionnaire_mode ) { return array( 'headers' => array(), 'body' => '{"message":"QA invalid answers"}', 'response' => array( 'code' => 422, 'message' => 'Unprocessable' ), 'cookies' => array() ); }
		$body = array( 'data' => array( 'ques_map_id' => 98765 ) );
	} elseif ( str_contains( $url, '/questionnaires/' . MYOGENIX_TRT_REFILL_TEMPLATE_ID ) ) {
		$body = array( 'data' => array(
			array( 'entry_id' => 'qa-radio', 'template_id' => MYOGENIX_TRT_REFILL_TEMPLATE_ID, 'type' => 'radio-group', 'notes' => 'Required radio', 'is_mandatory' => 1, 'value' => array( 'Yes', 'No' ), 'options' => array( 'Yes', 'No' ) ),
			array( 'entry_id' => 'qa-check', 'template_id' => MYOGENIX_TRT_REFILL_TEMPLATE_ID, 'type' => 'checkbox-group', 'notes' => 'Required checkbox', 'is_mandatory' => 1, 'value' => array( 'One', 'Two' ), 'options' => array( 'One', 'Two' ) ),
			array( 'entry_id' => 'qa-text', 'template_id' => MYOGENIX_TRT_REFILL_TEMPLATE_ID, 'type' => 'text', 'notes' => 'Optional text', 'is_mandatory' => '', 'value' => '', 'options' => null ),
		) );
	} else { $body = array( 'qa_intercepted' => true ); }
	return array( 'headers' => array(), 'body' => wp_json_encode( $body ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
}, 999, 3 );
// Prevent the auth mock from ever entering the real site's token cache.
add_filter( 'pre_option_myogenix_trt_lab_api', function () { return array( 'active_env' => 'sandbox', 'sandbox' => array( 'api_base_url' => 'https://staging.prescribery.com/api/v2', 'api_key' => 'integration-fixture', 'client_id' => 16, 'source_id' => 1198, 'renewal_source_id' => 1200, 'lab_id' => 1057, 'test_ids' => array( 59, 5 ) ) ); } );
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
	$internal = $fixture(); $internal->update_meta_data( '_trt_internal_test', 'yes' ); $internal->save();
	$before_mail = count( $test_mail ); $before_http = count( $test_http );
	myogenix_trt_check_subscription( $internal->get_id() );
	$assert( ! myogenix_trt_enabled( $internal ) && count( $test_mail ) === $before_mail && count( $test_http ) === $before_http, 'Legacy internal test is excluded from invitations and provider requests even if QA allowlisted' );
	$internal->update_meta_data( '_trt_cycle_start', time() - 90 * DAY_IN_SECONDS ); $internal->save();
	myogenix_trt_check_subscription( $internal->get_id() );
	$assert( wcs_get_subscription( $internal->get_id() )->has_status( 'active' ), 'Overdue internal test does not enter patient expiry processing' );
	$sub = $fixture(); $id = $sub->get_id(); $cycle = myogenix_trt_cycle_start_ts( $sub );
	$exclude_fixture = function () use ( $id ) { return array( $id => 'TEST: individual review' ); };
	add_filter( 'pre_option_myogenix_trt_launch_exceptions', $exclude_fixture );
	$assert( ! myogenix_trt_enabled( $sub ), 'Individual-review exception stays outside the new flow even when QA allowlisted' );
	remove_filter( 'pre_option_myogenix_trt_launch_exceptions', $exclude_fixture );
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
	$sub->update_meta_data( '_wave_trt_workflow', array( 'milestones' => array( 'intake' => array( 'at' => time(), 'by' => 'Prior cycle', 'note' => 'Old intake' ) ) ) ); $sub->save();
	$result = myogenix_trt_process_consent( $p ); $assert( ! is_wp_error( $result ), 'Continue creates a renewal and requisition' );
	$order = wc_get_order( $result['order_id'] ); $test_ids[] = $order->get_id();
	$assert( $order->has_status( 'pending' ) && ! $order->get_transaction_id(), 'Renewal stays unpaid' );
	$assert( $order->get_meta( '_prescribery_requisition_id' ) === 'qa-requisition-token', 'Lab token is mapped to renewal' );
	$intakes = array_values( array_filter( $test_requests, function ( $r ) { return str_contains( $r['url'], '/shopify/callback' ); } ) );
	$labs = array_values( array_filter( $test_requests, function ( $r ) { return str_contains( $r['url'], '/save-test-order' ); } ) );
	$assert( count( $intakes ) === 1 && 'sent' === $order->get_meta( '_trt_intake_state' ), 'Exactly one explicit intake callback is accepted' );
	$assert( $intakes[0]['url'] === 'https://staging.prescribery.com/shopify/callback' && $intakes[0]['body']['client_id'] === 16 && $intakes[0]['body']['source_id'] === 1200 && $intakes[0]['body']['patient_id'] === 1 && ! isset( $intakes[0]['body']['service_id'] ), 'Renewal registration uses the configured renewal source without a service ID' );
	$assert( $intakes[0]['body']['orderId'] === $order->get_id() && base64_decode( $intakes[0]['body']['uuid'] ) === 'wc-16-' . $order->get_id(), 'Intake identifies the new WooCommerce renewal' );
	$assert( $labs[0]['body']['external_order_id'] === (string) $order->get_id() && $order->get_id() !== $sub->get_parent_id(), 'Lab external_order_id is the new renewal ID as a string' );
	$assert( array_search( $intakes[0], $test_requests, true ) < array_search( $labs[0], $test_requests, true ), 'Intake registration precedes the lab request' );
	$assert( is_wp_error( myogenix_trt_place_lab_requisition( $sub ) ), 'Lab request without a linked renewal is blocked' );
	foreach ( array( $order->get_id(), $sub->get_parent_id() ) as $qa_order_id ) {
		$request = new WP_REST_Request( 'POST', '/prescription/v1/approve' ); $request->set_body_params( array( 'order_id' => $qa_order_id, 'status' => 'approved', 'patient_id' => 1, 'appointment_id' => 123 ) );
		$assert( myogenix_trt_approval_callback( $request )->get_status() === 403 && ! wc_get_order( $qa_order_id )->get_transaction_id(), 'External QA approval cannot charge fake order ' . $qa_order_id );
	}
	$assert( ! myogenix_trt_intake_verified( $order ), 'Old subscription intake evidence is not copied to the renewal' );
	$work = $order->get_meta( '_wave_trt_workflow' );
	$assert( ! empty( $work['due'] ) && 'patient' === $work['status'], 'Continue creates a dated staff task for quarterly intake' );
	$primary_order_id = $order->get_id();
	$auto_sub = $fixture(); myogenix_trt_check_subscription( $auto_sub->get_id() );
	$auto_result = myogenix_trt_process_consent( $params_for( wcs_get_subscription( $auto_sub->get_id() ) ) );
	$assert( ! is_wp_error( $auto_result ), 'Automated-intake fixture creates its renewal safely' );
	$auto_order = wc_get_order( $auto_result['order_id'] ); $test_ids[] = $auto_order->get_id();
	$questionnaire_url = myogenix_trt_questionnaire_url( $auto_order );
	parse_str( wp_parse_url( $questionnaire_url, PHP_URL_QUERY ), $questionnaire_params );
	$validated_questionnaire = myogenix_trt_validate_questionnaire_request( $questionnaire_params );
	$assert( ! is_wp_error( $validated_questionnaire ) && false === $validated_questionnaire[2], 'Signed current-order questionnaire link is accepted' );
	$form_html = myogenix_trt_render_questionnaire_form( $auto_order );
	$assert( str_contains( $form_html, 'Required radio' ) && str_contains( $form_html, 'Submit medical check-in' ) && ! str_contains( $form_html, 'TEST ONLY answer' ), 'Provider schema renders as a secure patient form without stored answers' );
	$bad_questionnaire = $questionnaire_params; $bad_questionnaire['token'] = 'invalid';
	$assert( is_wp_error( myogenix_trt_validate_questionnaire_request( $bad_questionnaire ) ), 'Invalid questionnaire signature is rejected' );
	$missing_answers = $questionnaire_params + array( 'answers' => array( 'qa-radio' => 'Yes' ) );
	$before = count( array_filter( $test_http, function ( $url ) { return str_contains( $url, '/questionnaires/answers' ); } ) );
	$assert( is_wp_error( myogenix_trt_submit_questionnaire( $missing_answers ) ), 'Missing required questionnaire answer is rejected' );
	$assert( $before === count( array_filter( $test_http, function ( $url ) { return str_contains( $url, '/questionnaires/answers' ); } ) ), 'Invalid questionnaire is not submitted to Prescribery' );
	$invalid_answers = $questionnaire_params + array( 'answers' => array( 'qa-radio' => 'Maybe', 'qa-check' => array( 'One' ) ) );
	$assert( is_wp_error( myogenix_trt_submit_questionnaire( $invalid_answers ) ), 'Answer outside the provider schema is rejected' );
	$malformed_answers = $questionnaire_params + array( 'answers' => array( 'qa-radio' => array( 'Yes' ), 'qa-check' => array( array( 'One' ) ) ) );
	$assert( is_wp_error( myogenix_trt_submit_questionnaire( $malformed_answers ) ), 'Nested or wrong-shape questionnaire input is rejected' );
	$valid_answers = $questionnaire_params + array( 'answers' => array( 'qa-radio' => 'Yes', 'qa-check' => array( 'One', 'Two' ), 'qa-text' => 'TEST ONLY answer' ) );
	$submitted_questionnaire = myogenix_trt_submit_questionnaire( $valid_answers );
	$assert( ! is_wp_error( $submitted_questionnaire ), 'Valid quarterly questionnaire is accepted' );
	$auto_order = wc_get_order( $auto_order->get_id() );
	$questionnaire_requests = array_values( array_filter( $test_requests, function ( $r ) { return str_contains( $r['url'], '/questionnaires/answers' ); } ) );
	$questionnaire_body = end( $questionnaire_requests )['body'];
	$assert( 10622 === $questionnaire_body['template_id'] && 1 === $questionnaire_body['patient_id'] && '558' === $questionnaire_body['service_ids'], 'Questionnaire submission identifies the refill template, patient and service' );
	$assert( array( 'One', 'Two' ) === array_column( $questionnaire_body['answers'][1]['multiple_answers'], 'answer' ), 'Multiple-choice answers use the documented answer structure' );
	$assert( 'submitted' === $auto_order->get_meta( '_trt_questionnaire_state' ) && 98765 === (int) $auto_order->get_meta( '_trt_questionnaire_map_id' ) && myogenix_trt_intake_verified( $auto_order ), 'Successful submission stores only correlation evidence and verifies current intake' );
	$assert( false === strpos( wp_json_encode( $auto_order->get_meta_data() ), 'TEST ONLY answer' ), 'WooCommerce does not retain clinical questionnaire answers' );
	$before = count( $questionnaire_requests ); $repeat_questionnaire = myogenix_trt_submit_questionnaire( $valid_answers );
	$after = count( array_filter( $test_http, function ( $url ) { return str_contains( $url, '/questionnaires/answers' ); } ) );
	$assert( ! is_wp_error( $repeat_questionnaire ) && ! empty( $repeat_questionnaire['already_submitted'] ) && $before === $after, 'Repeated questionnaire submission is idempotent' );
	$timeout_sub = $fixture(); myogenix_trt_check_subscription( $timeout_sub->get_id() );
	$timeout_result = myogenix_trt_process_consent( $params_for( wcs_get_subscription( $timeout_sub->get_id() ) ) );
	$timeout_order = wc_get_order( $timeout_result['order_id'] ); $test_ids[] = $timeout_order->get_id();
	parse_str( wp_parse_url( myogenix_trt_questionnaire_url( $timeout_order ), PHP_URL_QUERY ), $timeout_params );
	$timeout_params['answers'] = $valid_answers['answers']; $test_questionnaire_mode = 'timeout';
	$before = count( array_filter( $test_http, function ( $url ) { return str_contains( $url, '/questionnaires/answers' ); } ) );
	$assert( is_wp_error( myogenix_trt_submit_questionnaire( $timeout_params ) ) && 'uncertain' === wc_get_order( $timeout_order->get_id() )->get_meta( '_trt_questionnaire_state' ), 'Questionnaire timeout is held for staff review' );
	myogenix_trt_submit_questionnaire( $timeout_params );
	$after = count( array_filter( $test_http, function ( $url ) { return str_contains( $url, '/questionnaires/answers' ); } ) );
	$assert( $before + 1 === $after, 'Uncertain questionnaire submission cannot be resent automatically' );
	$test_questionnaire_mode = 'success';
	$order = wc_get_order( $primary_order_id );
	$work = $order->get_meta( '_wave_trt_workflow' );
	add_filter( 'myogenix_trt_allow_qa_approval', '__return_true' );
	$early = new WP_REST_Request( 'POST', '/prescription/v1/approve' );
	$early->set_body_params( array( 'order_id' => $order->get_id(), 'status' => 'approved', 'patient_id' => 999, 'appointment_id' => 123 ) );
	$assert( myogenix_trt_approval_callback( $early )->get_status() === 422 && ! wc_get_order( $order->get_id() )->get_meta( '_trt_waiting_approval' ), 'Mismatched provider approval cannot enter the waiting queue' );
	$early->set_body_params( array( 'order_id' => $order->get_id(), 'status' => 'approved', 'patient_id' => 1, 'appointment_id' => 123 ) );
	$before_http = count( $test_http );
	$assert( myogenix_trt_approval_callback( $early )->get_status() === 202, 'Early provider approval is retained while quarterly intake is incomplete' );
	$order = wc_get_order( $order->get_id() );
	$assert( ! $order->get_meta( '_trt_provider_approved' ) && ! $order->get_transaction_id() && count( $test_http ) === $before_http && ! $order->needs_payment(), 'Unverified intake blocks both automatic payment and patient pay links' );
	$before_mail = count( $test_mail );
	myogenix_trt_approval_callback( $early );
	$assert( count( $test_mail ) === $before_mail, 'Repeated early approval does not repeat staff alerts' );
	$work['milestones']['intake'] = array( 'at' => $order->get_date_created()->getTimestamp() - 1, 'by' => 'Fixture staff', 'note' => 'Prior-cycle evidence' );
	$order->update_meta_data( '_wave_trt_workflow', $work ); $order->save();
	$assert( ! myogenix_trt_intake_verified( $order ), 'Evidence predating the new renewal cannot verify this quarter’s intake' );
	$work['milestones']['intake'] = array( 'at' => time(), 'by' => 'Fixture staff', 'note' => 'TEST ONLY: verified current-cycle fixture' );
	$order->update_meta_data( '_wave_trt_workflow', $work ); $order->save();
	$assert( myogenix_trt_intake_verified( $order ), 'Current-order staff evidence verifies quarterly intake' );
	do_action( 'wave_trt_milestone_updated', $order, 'intake', 'verify' );
	$assert( (bool) wp_next_scheduled( 'myogenix_trt_resume_approval', array( $order->get_id() ) ), 'Recording verified intake schedules the retained approval for processing' );
	do_action( 'wave_trt_milestone_updated', $order, 'intake', 'clear_milestone' );
	$assert( ! wp_next_scheduled( 'myogenix_trt_resume_approval', array( $order->get_id() ) ), 'Removing verification cancels pending approval processing' );
	myogenix_trt_resume_approval( $order->get_id() );
	$assert( count( $test_http ) === $before_http, 'Internal resume refuses fake QA orders even with intake verified' );
	remove_filter( 'myogenix_trt_allow_qa_approval', '__return_true' );
	$events = myogenix_trt_calendar_events( wcs_get_subscription( $sub->get_id() ) );
	$assert( count( $events ) === 2 && ! in_array( 'scheduled', array_column( $events, 'state' ), true ), 'Resolved consent calendar removes future no-response deadlines' );
	$assert( ! $order->needs_payment(), 'Verified intake alone cannot bypass provider approval through pay link' );
	$assert( '' === do_shortcode( '[pre_woo_questionnaire_link order_id="' . $order->get_id() . '"]' ), 'Renewal receipts omit the legacy questionnaire shortcode' );
	$assert( false === apply_filters( 'pre_do_shortcode_tag', false, 'pre_woo_questionnaire_link', array( 'order_id' => $sub->get_parent_id() ), array() ), 'Initial-order questionnaire rendering remains available' );
	$blocked = myogenix_trt_maybe_block_shopify_callback( false, array( 'body' => wp_json_encode( array( 'orderId' => $order->get_id() ) ) ), 'https://staff.prescribery.com/shopify/callback' );
	$assert( is_array( $blocked ), 'Legacy callback is suppressed after creation as well as during creation' );
	$allowed = myogenix_trt_maybe_block_shopify_callback( false, array( 'body' => wp_json_encode( array( 'orderId' => $sub->get_parent_id() ) ) ), 'https://staff.prescribery.com/shopify/callback' );
	$assert( false === $allowed, 'Unrelated order callback is preserved' );
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
	$calendar = myogenix_trt_calendar_events( $fixture( 30 ) );
	$assert( count( $calendar ) === 3 && array_unique( array_column( $calendar, 'state' ) ) === array( 'scheduled' ), 'Calendar schedules actual day 63, 75 and 85 renewal events' );
	$late = $fixture( 80 ); myogenix_trt_check_subscription( $late->get_id() );
	$assert( (bool) wcs_get_subscription( $late->get_id() )->get_meta( '_trt_patient_email_for' ), 'Missed week-9 window catches up before deadline' );
	$uninvited = $fixture( 86 ); myogenix_trt_check_subscription( $uninvited->get_id() );
	$assert( wcs_get_subscription( $uninvited->get_id() )->has_status( 'active' ) && wcs_get_subscription( $uninvited->get_id() )->get_meta( '_trt_missed_invitation_for' ), 'An uninvited patient is sent to staff review instead of being marked non-responsive' );
	$expired = $fixture( 86 ); $expired->update_meta_data( '_trt_patient_email_for', myogenix_trt_cycle_start_ts( $expired ) ); $expired->save(); myogenix_trt_check_subscription( $expired->get_id() );
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
	$retry = $fixture(); myogenix_trt_check_subscription( $retry->get_id() ); $test_lab_mode = 'reject';
	$assert( is_wp_error( myogenix_trt_process_consent( $params_for( $retry ) ) ), 'Lab rejection leaves consent unresolved' );
	$retry_order = wcs_get_subscription( $retry->get_id() )->get_meta( '_trt_pending_renewal_order' ); $test_ids[] = $retry_order;
	$before = count( array_filter( $test_http, function ( $url ) { return str_contains( $url, '/shopify/callback' ); } ) );
	$test_lab_mode = 'success'; $retried = myogenix_trt_process_consent( $params_for( $retry ) );
	$assert( ! is_wp_error( $retried ) && $retried['order_id'] === (int) $retry_order, 'Known lab rejection retries the same renewal' );
	$assert( $before === count( array_filter( $test_http, function ( $url ) { return str_contains( $url, '/shopify/callback' ); } ) ), 'Lab retry does not repeat the accepted intake callback' );
	foreach ( array( 'timeout', 'error' ) as $mode ) {
		$intake_failure = $fixture(); myogenix_trt_check_subscription( $intake_failure->get_id() ); $test_intake_mode = $mode;
		$before = count( array_filter( $test_http, function ( $url ) { return str_contains( $url, '/save-test-order' ); } ) );
		$assert( is_wp_error( myogenix_trt_process_consent( $params_for( $intake_failure ) ) ), 'Intake ' . $mode . ' stops processing' );
		$test_ids[] = wcs_get_subscription( $intake_failure->get_id() )->get_meta( '_trt_pending_renewal_order' );
		$assert( $before === count( array_filter( $test_http, function ( $url ) { return str_contains( $url, '/save-test-order' ); } ) ), 'Intake ' . $mode . ' cannot create a lab request' );
		$before = count( $test_http ); myogenix_trt_process_consent( $params_for( $intake_failure ) );
		$assert( count( $test_http ) === $before, 'Uncertain intake ' . $mode . ' cannot be resent automatically' );
	}
	$test_intake_mode = 'success';
	$mail_failure = $fixture();
	$fail_mail = function ( $result, $mail ) { return str_contains( $mail['subject'], 'ready to review' ) ? false : $result; };
	add_filter( 'pre_wp_mail', $fail_mail, 1000, 2 ); myogenix_trt_check_subscription( $mail_failure->get_id() );
	$assert( ! wcs_get_subscription( $mail_failure->get_id() )->get_meta( '_trt_patient_email_for' ), 'Failed email is not marked delivered' );
	remove_filter( 'pre_wp_mail', $fail_mail, 1000 ); myogenix_trt_check_subscription( $mail_failure->get_id() );
	$assert( (bool) wcs_get_subscription( $mail_failure->get_id() )->get_meta( '_trt_patient_email_for' ), 'Failed invitation retries on the next check' );
	$missing = $fixture(); $missing->delete_meta_data( '_prescribery_patient_id' ); $missing->save(); myogenix_trt_check_subscription( $missing->get_id() );
	$assert( is_wp_error( myogenix_trt_process_consent( $params_for( $missing ) ) ) && ! wcs_get_subscription( $missing->get_id() )->get_meta( '_trt_pending_renewal_order' ), 'Missing patient mapping stops before lab or order creation' );
	$disabled = $fixture(); myogenix_trt_check_subscription( $disabled->get_id() );
	$qa = get_option( 'myogenix_trt_qa' ); $qa['subscription_ids'] = array_values( array_diff( $qa['subscription_ids'], array( $disabled->get_id() ) ) ); update_option( 'myogenix_trt_qa', $qa, false );
	$assert( is_wp_error( myogenix_trt_process_consent( $params_for( $disabled ) ) ), 'Global off switch blocks a valid token outside the QA allowlist' );
	$due_before = wcs_get_subscription( $id = $sub->get_id() )->get_time( 'next_payment' );
	// Hold the existing payment lock to exercise the approval-to-payment handoff
	// without attempting a Stripe charge, even if fixture payment data changes.
	wp_cache_set( 'prescription_charge_lock_' . $order->get_id(), 1, 'prescription_locks', 90 );
	add_filter( 'myogenix_trt_allow_qa_approval', '__return_true' );
	$approved = myogenix_trt_approval_callback( $early );
	remove_filter( 'myogenix_trt_allow_qa_approval', '__return_true' );
	wp_cache_delete( 'prescription_charge_lock_' . $order->get_id(), 'prescription_locks' );
	$order = wc_get_order( $order->get_id() );
	$assert( 200 === $approved->get_status() && 'pending_retry' === ( $approved->get_data()['payment']['status'] ?? '' ), 'Verified intake and valid provider approval reach the existing payment pipeline' );
	$assert( 'yes' === $order->get_meta( '_trt_provider_approved' ) && ! $order->get_meta( '_trt_waiting_approval' ) && ! $order->get_transaction_id(), 'Validated approval is released once; fixture remains uncharged' );
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
