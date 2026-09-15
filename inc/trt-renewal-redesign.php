<?php
/**
 * TRT renewals: explicit patient consent, labs, then provider-approved payment.
 * Configuration and credentials live in WP options, never in this public repo.
 * See TRT_RENEWAL_RUNBOOK.md for rollout and recovery.
 */
defined( 'ABSPATH' ) || exit;

const MYOGENIX_TRT_PRODUCT_ID = 883;
const MYOGENIX_TRT_WEEK_TARGET = 9;
const MYOGENIX_TRT_CONSENT_TTL = 85 * DAY_IN_SECONDS;
const MYOGENIX_TRT_NORESPONSE_DAYS = 75;
const MYOGENIX_TRT_ADMIN_EMAILS = array( 'adam@myogenixpharma.com', 'adam@myogenix.com' );
const MYOGENIX_TRT_REDESIGN_LIVE = false;

require_once __DIR__ . '/trt-renewal-presentation.php';
require_once __DIR__ . '/trt-renewal-approval.php';

function myogenix_trt_subscription_has_product( WC_Subscription $subscription, $product_id ) {
	foreach ( $subscription->get_items() as $item ) {
		if ( (int) $item->get_product_id() === (int) $product_id ) {
			return true;
		}
	}
	return false;
}

/** Only explicitly allowlisted QA subscriptions can run before launch. */
function myogenix_trt_is_qa( $subscription ) {
	$qa = get_option( 'myogenix_trt_qa', array() );
	return $subscription instanceof WC_Subscription
		&& (int) ( $qa['expires_at'] ?? 0 ) > time()
		&& in_array( $subscription->get_id(), array_map( 'intval', $qa['subscription_ids'] ?? array() ), true )
		&& ! empty( $qa['email'] )
		&& strtolower( $subscription->get_billing_email() ) === strtolower( $qa['email'] )
		&& 'yes' === $subscription->get_meta( '_trt_qa_test' );
}

function myogenix_trt_enabled( $subscription ) {
	return $subscription instanceof WC_Subscription
		&& myogenix_trt_subscription_has_product( $subscription, MYOGENIX_TRT_PRODUCT_ID )
		&& ( MYOGENIX_TRT_REDESIGN_LIVE || myogenix_trt_is_qa( $subscription ) );
}

function myogenix_trt_cycle_start_ts( WC_Subscription $subscription ) {
	// Freeze the cycle before creating an unpaid order: WCS's last-order date
	// changes immediately on creation, which must not create another consent window.
	$saved = (int) $subscription->get_meta( '_trt_cycle_start' );
	if ( $saved ) {
		return $saved;
	}
	$date = $subscription->get_date( 'last_order_date_created' ) ?: $subscription->get_date( 'start' );
	return $date ? strtotime( $date . ' UTC' ) : false;
}

function myogenix_trt_consent_token( $subscription_id, $cycle_start_ts ) {
	return wp_hash( "trt_renewal_consent|{$subscription_id}|{$cycle_start_ts}" );
}

function myogenix_trt_consent_url( array $args ) {
	return add_query_arg( $args, home_url( '/trt-renewal-consent/' ) );
}

/** DB advisory locks serialize all actions for a subscription across PHP workers. */
function myogenix_trt_lock( $id ) {
	global $wpdb;
	return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', 'myogenix_trt_' . absint( $id ) ) );
}

function myogenix_trt_unlock( $id ) {
	global $wpdb;
	$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'myogenix_trt_' . absint( $id ) ) );
}

/**
 * Wrap the exact registered handlers once. Never remove them during a dispatch:
 * Action Scheduler may process TRT and non-TRT subscriptions in the same request.
 */
add_action( 'wp_loaded', 'myogenix_trt_install_renewal_guards', 100 );
function myogenix_trt_install_renewal_guards() {
	$hook = 'woocommerce_scheduled_subscription_payment';
	$methods = array( 'maybe_process_failed_renewal_for_repair', 'prepare_renewal', 'gateway_scheduled_subscription_payment' );
	foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks ?? array() as $priority => $callbacks ) {
		foreach ( $callbacks as $entry ) {
			$fn = $entry['function'];
			$name = is_array( $fn ) ? ( is_object( $fn[0] ) ? get_class( $fn[0] ) : $fn[0] ) . '::' . $fn[1] : ( is_string( $fn ) ? $fn : '' );
			$parts = explode( '::', $name );
			if ( ! in_array( $parts[0], array( 'WC_Subscriptions_Manager', 'WC_Subscriptions_Payment_Gateways' ), true ) || ! in_array( $parts[1] ?? '', $methods, true ) ) {
				continue;
			}
			remove_action( $hook, $fn, $priority );
			add_action( $hook, function ( $id ) use ( $fn ) {
				$sub = $id instanceof WC_Subscription ? $id : wcs_get_subscription( $id );
				if ( ! myogenix_trt_enabled( $sub ) ) {
					return call_user_func( $fn, $id );
				}
			}, $priority, $entry['accepted_args'] );
		}
	}
}

// Suppress legacy callbacks for the exact consent renewal, including later
// status transitions. Provider correlation must use the verified launch contract.
$GLOBALS['myogenix_trt_creating_subscription'] = 0;
add_filter( 'pre_http_request', 'myogenix_trt_maybe_block_shopify_callback', 10, 3 );
function myogenix_trt_maybe_block_shopify_callback( $preempt, $args, $url ) {
	if ( '/shopify/callback' !== substr( wp_parse_url( $url, PHP_URL_PATH ) ?? '', -17 ) ) {
		return $preempt;
	}
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! in_array( $host, array( 'staff.prescribery.com', 'staging.prescribery.com' ), true ) ) {
		return $preempt;
	}
	$payload = is_string( $args['body'] ?? null ) ? json_decode( $args['body'], true ) : ( $args['body'] ?? array() );
	$order = wc_get_order( absint( $payload['orderId'] ?? 0 ) );
	if ( ! myogenix_trt_is_renewal_order( $order ) ) { return $preempt; }
	return array( 'headers' => array(), 'body' => '{"suppressed":"trt-consent-renewal"}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
}

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'myogenix_trt_week9_cron' ) ) {
		wp_schedule_event( time(), 'daily', 'myogenix_trt_week9_cron' );
	}
	add_rewrite_rule( '^trt-renewal-consent/?$', 'index.php?myogenix_trt_consent=1', 'top' );
} );
add_filter( 'query_vars', function ( $vars ) { $vars[] = 'myogenix_trt_consent'; return $vars; } );
add_action( 'myogenix_trt_week9_cron', 'myogenix_trt_run_week9_check' );

function myogenix_trt_run_week9_check() {
	if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
		return;
	}
	foreach ( wcs_get_subscriptions( array( 'subscription_status' => 'active', 'subscriptions_per_page' => -1, 'product_id' => MYOGENIX_TRT_PRODUCT_ID ) ) as $sub ) {
		myogenix_trt_check_subscription( $sub->get_id() );
	}
}

function myogenix_trt_check_subscription( $id ) {
	if ( ! myogenix_trt_lock( $id ) ) {
		return;
	}
	try {
		$sub = wcs_get_subscription( $id );
		if ( ! $sub || ! $sub->has_status( 'active' ) || ! myogenix_trt_subscription_has_product( $sub, MYOGENIX_TRT_PRODUCT_ID ) ) { return; }
		$cycle = myogenix_trt_cycle_start_ts( $sub );
		if ( ! $cycle ) { return; }
		$days = (int) floor( ( time() - $cycle ) / DAY_IN_SECONDS );
		if ( $days < 63 ) { return; }
		if ( $days < 70 && (string) ( $sub->get_meta( '_trt_admin_week9_for' ) ?: $sub->get_meta( '_trt_consent_sent_for' ) ) !== (string) $cycle ) {
			if ( myogenix_trt_staff_notice( $sub, 'Week 9 renewal review', myogenix_trt_enabled( $sub ) ? 'The patient consent window is open.' : 'Detection only. Patient emails and consent renewals are not live.' ) ) {
				$sub->update_meta_data( '_trt_admin_week9_for', $cycle );
				$sub->save();
			}
		}
		if ( ! myogenix_trt_enabled( $sub ) || (string) $sub->get_meta( '_trt_consent_resolved_for' ) === (string) $cycle ) { return; }
		if ( $days >= 85 ) {
			// Consent is required; native charging never acts as a fallback.
			$sub->update_meta_data( '_trt_consent_resolved_for', $cycle );
			$sub->update_meta_data( '_trt_consent_resolved_action', 'expired' );
			$sub->update_status( 'on-hold', 'TRT renewal paused: consent deadline passed. Staff review required; no renewal charge.' );
			$sub->save();
			myogenix_trt_staff_notice( $sub, 'Consent expired — follow-up needed', 'Renewal paused without a charge. Contact the patient to discuss next steps.' );
			return;
		}
		// New key deliberately ignores the old detection-only _trt_consent_sent_for.
		if ( (string) $sub->get_meta( '_trt_patient_email_for' ) !== (string) $cycle ) {
			$sub->update_meta_data( '_trt_cycle_start', $cycle );
			$sub->save();
			if ( myogenix_trt_send_consent_email( $sub, $cycle ) ) {
				$sub->update_meta_data( '_trt_patient_email_for', $cycle );
				$sub->update_meta_data( '_trt_patient_email_sent_at', time() );
				$sub->save();
			} else {
				myogenix_trt_staff_notice( $sub, 'Consent email failed', 'Patient email was not accepted for delivery. The next daily check will retry.' );
			}
		}
		$sent_at = (int) $sub->get_meta( '_trt_patient_email_sent_at' );
		if ( $days >= MYOGENIX_TRT_NORESPONSE_DAYS && $sent_at && time() - $sent_at >= DAY_IN_SECONDS && (string) $sub->get_meta( '_trt_admin_noresponse_sent_for' ) !== (string) $cycle ) {
			if ( myogenix_trt_staff_notice( $sub, 'No response — follow-up needed', 'The patient has not responded. The renewal will pause at day 85; there is no automatic charge.' ) ) {
				$sub->update_meta_data( '_trt_admin_noresponse_sent_for', $cycle );
				$sub->save();
			}
		}
	} finally {
		myogenix_trt_unlock( $id );
	}
}

function myogenix_trt_validate_consent_request( array $params ) {
	foreach ( array( 'subscription_id', 'cycle_start', 'token', 'action' ) as $key ) {
		if ( ! isset( $params[ $key ] ) || ! is_scalar( $params[ $key ] ) ) { return new WP_Error( 'invalid', 'This renewal link is invalid.', array( 'status' => 400 ) ); }
	}
	$id = absint( $params['subscription_id'] );
	$cycle = absint( $params['cycle_start'] );
	$action = (string) $params['action'];
	$sub = wcs_get_subscription( $id );
	if ( ! $sub || ! in_array( $action, array( 'continue', 'decline' ), true ) || ! hash_equals( myogenix_trt_consent_token( $id, $cycle ), (string) $params['token'] ) ) {
		return new WP_Error( 'invalid', 'This renewal link is invalid.', array( 'status' => 403 ) );
	}
	if ( ! myogenix_trt_enabled( $sub ) ) { return new WP_Error( 'unavailable', 'Online renewal is not available for this subscription. Please contact our team.', array( 'status' => 403 ) ); }
	if ( ! $cycle || $cycle !== (int) myogenix_trt_cycle_start_ts( $sub ) || time() < $cycle || time() >= $cycle + MYOGENIX_TRT_CONSENT_TTL ) {
		return new WP_Error( 'expired', 'This renewal link has expired. Our team can help you with the next steps.', array( 'status' => 410 ) );
	}
	if ( (string) $sub->get_meta( '_trt_consent_resolved_for' ) === (string) $cycle ) {
		return new WP_Error( 'resolved', 'Your response has already been recorded. No additional order or charge has been made.', array( 'status' => 409 ) );
	}
	if ( ! $sub->has_status( 'active' ) || (string) $sub->get_meta( '_trt_patient_email_for' ) !== (string) $cycle ) {
		return new WP_Error( 'unavailable', 'This renewal is not awaiting a response. Please contact our team.', array( 'status' => 409 ) );
	}
	return array( $sub, $cycle, $action );
}

/** Mutation service is separate from rendering, allowing integration testing. */
function myogenix_trt_process_consent( array $params ) {
	$id = is_scalar( $params['subscription_id'] ?? null ) ? absint( $params['subscription_id'] ) : 0;
	if ( ! myogenix_trt_lock( $id ) ) { return new WP_Error( 'busy', 'Your renewal is being processed. Please wait a moment before checking again.', array( 'status' => 409 ) ); }
	try {
		$result = myogenix_trt_validate_consent_request( $params );
		if ( is_wp_error( $result ) ) { return $result; }
		list( $sub, $cycle, $action ) = $result;
		$outcome = 'continue' === $action ? myogenix_trt_handle_continue( $sub ) : myogenix_trt_handle_decline( $sub );
		if ( is_wp_error( $outcome ) ) {
			$sub->add_order_note( 'TRT renewal needs review: ' . $outcome->get_error_code() );
			myogenix_trt_staff_notice( $sub, 'Renewal needs attention', 'Processing stopped: ' . $outcome->get_error_code() . '. Review the linked renewal before retrying any external lab request.' );
			return $outcome;
		}
		$sub->update_meta_data( '_trt_consent_resolved_for', $cycle );
		$sub->update_meta_data( '_trt_consent_resolved_action', $action );
		$sub->save();
		myogenix_trt_send_response_email( $sub, $action );
		return array( 'action' => $action, 'order_id' => $outcome instanceof WC_Order ? $outcome->get_id() : 0 );
	} catch ( Throwable $error ) {
		wc_get_logger()->error( 'TRT consent exception for subscription ' . $id . ': ' . get_class( $error ), array( 'source' => 'trt-renewal' ) );
		return new WP_Error( 'processing_error', 'We could not complete your renewal. Please contact our team before trying again.', array( 'status' => 503 ) );
	} finally {
		myogenix_trt_unlock( $id );
	}
}

function myogenix_trt_handle_continue( WC_Subscription $sub ) {
	if ( ! myogenix_trt_enabled( $sub ) ) { return new WP_Error( 'disabled', 'Online renewal is unavailable.' ); }
	if ( ! myogenix_trt_get_prescribery_patient_id( $sub ) ) { return new WP_Error( 'missing_patient', 'Our team needs to verify your patient record before continuing.' ); }
	$cycle = myogenix_trt_cycle_start_ts( $sub );
	$sub->update_meta_data( '_trt_cycle_start', $cycle );
	$sub->save();
	$order = wc_get_order( $sub->get_meta( '_trt_pending_renewal_order' ) );
	if ( ! $order ) {
		$GLOBALS['myogenix_trt_creating_subscription'] = $sub->get_id();
		try { $order = wcs_create_renewal_order( $sub ); }
		finally { $GLOBALS['myogenix_trt_creating_subscription'] = 0; }
		if ( is_wp_error( $order ) ) { return new WP_Error( 'order_failed', 'We could not prepare your renewal. Please contact our team.' ); }
		// Persist before the external API call, so a timeout cannot create another order.
		$sub->update_meta_data( '_trt_pending_renewal_order', $order->get_id() );
		$sub->save();
	}
	if ( (int) $order->get_meta( '_trt_cycle_start' ) !== (int) $cycle ) { return new WP_Error( 'cycle_mismatch', 'An earlier renewal needs staff review.' ); }
	if ( ! $order->get_meta( '_trt_pricing_ready' ) ) {
		$pricing = myogenix_trt_prepare_renewal_prices( $order, $sub );
		if ( is_wp_error( $pricing ) ) { return $pricing; }
	}
	$state = $order->get_meta( '_trt_lab_state' );
	if ( in_array( $state, array( 'submitting', 'uncertain' ), true ) ) { return new WP_Error( 'lab_needs_review', 'Our team is checking your lab request. Please contact us before trying again.' ); }
	if ( ! $order->get_meta( '_prescribery_requisition_id' ) ) {
		$order->update_meta_data( '_trt_lab_state', 'submitting' );
		$order->save();
		$requisition = myogenix_trt_place_lab_requisition( $sub, $order );
		if ( is_wp_error( $requisition ) ) {
			$order->update_meta_data( '_trt_lab_state', in_array( $requisition->get_error_code(), array( 'lab_rejected', 'lab_config', 'lab_auth' ), true ) ? 'rejected' : 'uncertain' );
			$order->save();
			return $requisition;
		}
		$order->update_meta_data( '_prescribery_requisition_id', $requisition );
		$order->update_meta_data( '_trt_lab_state', 'created' );
		$order->add_order_note( 'Patient consent recorded; lab request accepted. Unpaid renewal awaits provider approval.' );
		$order->save();
	}
	return $order;
}

// Mark a renewal inside WCS's creation hook, before any integration sees it.
add_filter( 'wcs_renewal_order_created', function ( $order, $sub ) {
	if ( (int) $GLOBALS['myogenix_trt_creating_subscription'] !== $sub->get_id() ) { return $order; }
	$order->update_meta_data( '_trt_consent_renewal', 'yes' );
	$order->update_meta_data( '_trt_cycle_start', myogenix_trt_cycle_start_ts( $sub ) );
	$order->update_meta_data( '_trt_subscription_id', $sub->get_id() );
	$order->update_meta_data( '_trt_next_payment_before', $sub->get_time( 'next_payment' ) );
	$order->update_meta_data( '_prescribery_patient_id', myogenix_trt_get_prescribery_patient_id( $sub ) );
	$order->set_transaction_id( '' );
	$order->set_date_paid( null );
	foreach ( array( '_prescription_charge_amount', '_prescription_stripe_charging', '_pharmacy_webhook_sent', '_stripe_intent_id', '_child_order_ids', '_approved_appointment_ids', 'appointment_id', '_lab_fee_paid', '_parent_order_id', '_trt_pricing_ready', '_prescribery_requisition_id', '_trt_provider_approved', '_trt_cycle_advanced', '_trt_wcs_payment_recorded', '_trt_lab_state' ) as $key ) { $order->delete_meta_data( $key ); }
	foreach ( $order->get_items() as $item ) {
		foreach ( array( '_item_approved', '_item_rejected', '_item_approved_appointment', '_item_rejected_appointment' ) as $key ) { $item->delete_meta_data( $key ); }
		$item->save();
	}
	$order->save();
	// WCS relation already exists here; recoverable even if a later callback throws.
	$sub->update_meta_data( '_trt_pending_renewal_order', $order->get_id() );
	$sub->save();
	return $order;
}, -100, 2 );

function myogenix_trt_handle_decline( WC_Subscription $sub ) {
	if ( $sub->get_meta( '_trt_pending_renewal_order' ) ) { return new WP_Error( 'already_started', 'Your renewal has already started. Contact our team to pause it.' ); }
	$sub->update_status( 'on-hold', 'Patient chose to pause the TRT renewal. Staff follow-up required; no charge.' );
	$sub->save();
	myogenix_trt_staff_notice( $sub, 'Patient paused renewal', 'The subscription is on hold. Follow up with the patient before resuming or cancelling.' );
	return true;
}

function myogenix_trt_lab_api_settings() {
	$all = get_option( 'myogenix_trt_lab_api', array() );
	return $all[ $all['active_env'] ?? 'sandbox' ] ?? array();
}

function myogenix_trt_lab_api_token() {
	$s = myogenix_trt_lab_api_settings();
	if ( empty( $s['api_key'] ) || empty( $s['api_base_url'] ) ) { return new WP_Error( 'lab_config', 'Lab connection is not configured.' ); }
	$key = 'myogenix_trt_lab_token_' . md5( $s['api_base_url'] . $s['api_key'] );
	if ( $token = get_transient( $key ) ) { return $token; }
	$r = wp_remote_post( rtrim( $s['api_base_url'], '/' ) . '/access-token', array( 'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ), 'body' => wp_json_encode( array( 'api_key' => $s['api_key'] ) ), 'timeout' => 15 ) );
	if ( is_wp_error( $r ) ) { return new WP_Error( 'lab_auth', 'The lab connection is temporarily unavailable.' ); }
	$b = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( 200 !== wp_remote_retrieve_response_code( $r ) || empty( $b['data']['access_token'] ) ) { return new WP_Error( 'lab_auth', 'The lab connection is temporarily unavailable.' ); }
	set_transient( $key, $b['data']['access_token'], max( 1, (int) ( $b['data']['expires_in'] ?? 3600 ) - 60 ) );
	return $b['data']['access_token'];
}

function myogenix_trt_get_prescribery_patient_id( WC_Subscription $sub ) {
	$id = $sub->get_meta( '_prescribery_patient_id' );
	if ( ! $id && $sub->get_parent() ) { $id = $sub->get_parent()->get_meta( '_prescribery_patient_id' ); }
	return absint( $id );
}

function myogenix_trt_place_lab_requisition( WC_Subscription $sub, $order = null ) {
	$s = myogenix_trt_lab_api_settings();
	foreach ( array( 'api_base_url', 'client_id', 'source_id', 'lab_id', 'test_ids' ) as $key ) {
		if ( empty( $s[ $key ] ) ) { return new WP_Error( 'lab_config', 'The lab connection needs to be configured by our team.' ); }
	}
	$token = myogenix_trt_lab_api_token();
	if ( is_wp_error( $token ) ) { return $token; }
	$body = array( 'patient_id' => myogenix_trt_get_prescribery_patient_id( $sub ), 'order' => array_map( function ( $id ) use ( $s ) { return array( 'lab_id' => (int) $s['lab_id'], 'test_id' => (int) $id ); }, $s['test_ids'] ), 'source_id' => (int) $s['source_id'], 'reason' => ( myogenix_trt_is_qa( $sub ) ? 'TEST ONLY — ' : '' ) . 'TRT renewal; WooCommerce order ' . ( $order ? $order->get_id() : 'test' ), 'payment_status' => 'pending' );
	$r = wp_remote_post( rtrim( $s['api_base_url'], '/' ) . '/lab/' . $s['client_id'] . '/save-test-order', array( 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json', 'Accept' => 'application/json' ), 'body' => wp_json_encode( $body ), 'timeout' => 25 ) );
	if ( is_wp_error( $r ) ) { return new WP_Error( 'lab_uncertain', 'Our team needs to check whether your lab request was received. Please contact us before trying again.' ); }
	$code = wp_remote_retrieve_response_code( $r );
	$b = json_decode( wp_remote_retrieve_body( $r ), true );
	$token = $b['token'] ?? null;
	$token = is_array( $token ) && 1 === count( $token ) ? reset( $token ) : $token;
	if ( ! in_array( $code, array( 200, 201 ), true ) || ! is_string( $token ) || '' === $token ) {
		return new WP_Error( in_array( $code, array( 400, 401, 403, 422 ), true ) ? 'lab_rejected' : 'lab_uncertain', 'We could not confirm your lab request. Our team will help you complete your renewal.' );
	}
	return $token;
}
