<?php
/**
 * TRT Renewal Redesign — self-detected week-9 trigger + consent-gated renewal.
 *
 * Replaces WooCommerce Subscriptions' native auto-renewal for TRT
 * (TESTOSTERONE CYPIONATE, product #883) only. Other subscription products
 * (Progesterone, Estrogen) are untouched and keep renewing natively.
 *
 * Flow: daily cron finds active TRT subscriptions at week 9 of their current
 * 3-month cycle -> emails a Continue/Decline consent link -> Continue places
 * a lab requisition with Prescribery, creates a renewal order via WCS's own
 * wcs_create_renewal_order(), and leaves it unpaid until Prescribery's
 * existing approval webhook (prescription/v1/approve) charges it -> Decline
 * puts the subscription on hold for staff review.
 *
 * See /Users/lukeflaherty/.claude/plans/tranquil-petting-reddy.md for the
 * full design and rationale (git history here won't have that context).
 *
 * IMPORTANT: this file only edits theme code. Two related fixes described in
 * the plan live in plugin files outside this repo (prescription-charge-
 * previous-cart.php's cancel_subscription no-op, and prescribery-wc-
 * integration.php's duplicate renewal callback) and are NOT touched here.
 * Instead: the native-renewal suppression below removes WCS's own hook
 * callbacks by their exact registered reference (no plugin file edit needed),
 * and the duplicate-callback suppression short-circuits the specific
 * outbound HTTP call via `pre_http_request` rather than unhooking it.
 */
defined( 'ABSPATH' ) || exit;

const MYOGENIX_TRT_PRODUCT_ID   = 883;
const MYOGENIX_TRT_WEEK_TARGET  = 9;
const MYOGENIX_TRT_CONSENT_TTL  = 85 * DAY_IN_SECONDS; // must expire before the ~90-day native renewal date

// Flip to false once Prescribery's requisition endpoint is wired in and this
// has been verified end-to-end (see plan step 4/5). While true, the week-9
// cron still runs its detection but does not send any email.
const MYOGENIX_TRT_REDESIGN_LIVE = false;

// ─── Helpers ───────────────────────────────────────────────────────────────

function myogenix_trt_subscription_has_product( WC_Subscription $subscription, $product_id ) {
	foreach ( $subscription->get_items() as $item ) {
		if ( (int) $item->get_product_id() === (int) $product_id ) {
			return true;
		}
	}
	return false;
}

function myogenix_trt_cycle_start_ts( WC_Subscription $subscription ) {
	$last_order_date = $subscription->get_date( 'last_order_date_created' );
	$cycle_start      = $last_order_date ? $last_order_date : $subscription->get_date( 'start' );
	return $cycle_start ? strtotime( $cycle_start . ' UTC' ) : false;
}

function myogenix_trt_consent_token( $subscription_id, $cycle_start_ts ) {
	return wp_hash( "trt_renewal_consent|{$subscription_id}|{$cycle_start_ts}" );
}

// ─── 1. Native WCS auto-renewal suppression, TRT only ─────────────────────
//
// WCS still creates a renewal order (and puts the subscription on-hold) on
// its own native cron date even for a manual-renewal subscription — it only
// skips auto-charging (verified in class-wc-subscriptions-manager.php
// process_renewal()). That's the wrong timing for us, so for TRT we remove
// WCS's own callbacks on this one dispatch before they run.

add_action( 'woocommerce_scheduled_subscription_payment', 'myogenix_trt_suppress_native_renewal', -10, 1 );

function myogenix_trt_suppress_native_renewal( $subscription_id ) {
	// Until the redesign is fully wired and verified (plan step 4/5), leave
	// native WCS renewal completely alone — suppressing it early would leave
	// real patients with neither an auto-renewal order nor a consent email.
	if ( ! MYOGENIX_TRT_REDESIGN_LIVE ) {
		return;
	}

	$subscription = wcs_get_subscription( $subscription_id );
	if ( ! $subscription instanceof WC_Subscription ) {
		return;
	}
	if ( ! myogenix_trt_subscription_has_product( $subscription, MYOGENIX_TRT_PRODUCT_ID ) ) {
		return;
	}

	// Exact callables WCS core registers on this hook — removed for this
	// dispatch only; WordPress re-registers them fresh on the next request,
	// so non-TRT subscriptions processed in a different request are unaffected.
	remove_action( 'woocommerce_scheduled_subscription_payment', 'WC_Subscriptions_Manager::maybe_process_failed_renewal_for_repair', 0 );
	remove_action( 'woocommerce_scheduled_subscription_payment', 'WC_Subscriptions_Manager::prepare_renewal', 1 );
	remove_action( 'woocommerce_scheduled_subscription_payment', array( 'WC_Subscriptions_Payment_Gateways', 'gateway_scheduled_subscription_payment' ), 10 );

	$subscription->add_order_note( 'Native WCS renewal suppressed — TRT renewals are now driven by the week-9 consent flow.' );
}

// ─── 2. Duplicate Prescribery callback suppression, TRT renewal orders only ─
//
// wcs_create_renewal_order() (called by us in the Continue handler) fires
// WCS's own `wcs_renewal_order_created` hook internally, which
// prescribery-wc-integration.php still listens to and would POST the old
// "shopify/callback" notification for. We supersede that with our own lab
// requisition call, so we short-circuit just that one outbound request while
// our flag is set — no plugin file edit required.

$GLOBALS['myogenix_trt_suppress_shopify_callback'] = false;

add_filter( 'pre_http_request', 'myogenix_trt_maybe_block_shopify_callback', 10, 3 );

function myogenix_trt_maybe_block_shopify_callback( $preempt, $args, $url ) {
	if ( empty( $GLOBALS['myogenix_trt_suppress_shopify_callback'] ) ) {
		return $preempt;
	}
	if ( false === strpos( $url, '/shopify/callback' ) ) {
		return $preempt;
	}
	return array(
		'headers'  => array(),
		'body'     => wp_json_encode( array( 'suppressed' => 'trt-renewal-redesign' ) ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
}

// ─── 3. Week-9 detector + consent email (daily cron) ───────────────────────

add_action( 'init', 'myogenix_trt_schedule_week9_cron' );

function myogenix_trt_schedule_week9_cron() {
	if ( ! wp_next_scheduled( 'myogenix_trt_week9_cron' ) ) {
		wp_schedule_event( time(), 'daily', 'myogenix_trt_week9_cron' );
	}
}

add_action( 'myogenix_trt_week9_cron', 'myogenix_trt_run_week9_check' );

function myogenix_trt_run_week9_check() {
	if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
		return;
	}

	$subs = wcs_get_subscriptions( array(
		'subscription_status'    => 'active',
		'subscriptions_per_page' => -1,
		'product_id'             => MYOGENIX_TRT_PRODUCT_ID,
	) );

	foreach ( $subs as $subscription_id => $subscription ) {
		$cycle_start_ts = myogenix_trt_cycle_start_ts( $subscription );
		if ( ! $cycle_start_ts ) {
			continue;
		}

		$days_elapsed = (int) floor( ( time() - $cycle_start_ts ) / DAY_IN_SECONDS );
		$week         = (int) floor( $days_elapsed / 7 );

		if ( MYOGENIX_TRT_WEEK_TARGET !== $week ) {
			continue;
		}

		$already_sent = $subscription->get_meta( '_trt_consent_sent_for' );
		if ( (string) $already_sent === (string) $cycle_start_ts ) {
			continue; // already handled this cycle
		}

		if ( MYOGENIX_TRT_REDESIGN_LIVE ) {
			myogenix_trt_send_consent_email( $subscription, $cycle_start_ts );
		}

		$subscription->update_meta_data( '_trt_consent_sent_for', $cycle_start_ts );
		$subscription->save();
	}
}

function myogenix_trt_send_consent_email( WC_Subscription $subscription, $cycle_start_ts ) {
	$token = myogenix_trt_consent_token( $subscription->get_id(), $cycle_start_ts );

	$continue_url = add_query_arg( array(
		'subscription_id' => $subscription->get_id(),
		'cycle_start'     => $cycle_start_ts,
		'token'           => $token,
		'action'          => 'continue',
	), rest_url( 'myogenix/v1/trt-renewal-consent' ) );

	$decline_url = add_query_arg( array(
		'subscription_id' => $subscription->get_id(),
		'cycle_start'     => $cycle_start_ts,
		'token'           => $token,
		'action'          => 'decline',
	), rest_url( 'myogenix/v1/trt-renewal-consent' ) );

	$to   = $subscription->get_billing_email();
	$name = $subscription->get_billing_first_name();

	$subject = 'Continue your TRT treatment?';
	$body    = "Hi {$name},\n\n"
		. "It's time to plan your next testosterone therapy renewal. Let us know how you'd like to proceed:\n\n"
		. "Continue treatment: {$continue_url}\n\n"
		. "Not right now: {$decline_url}\n\n"
		. "Questions? Reply to this email or reach us at support@myogenixpharma.com.\n\n"
		. "— Myogenix Pharma";

	wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
}

// ─── 4. Consent REST route ──────────────────────────────────────────────────
//
// GET renders a confirmation page with no side effects (protects against
// email-client link prefetching/scanning silently triggering an action).
// POST from that page's own form performs the actual mutation.

add_action( 'rest_api_init', function () {
	register_rest_route( 'myogenix/v1', '/trt-renewal-consent', array(
		array(
			'methods'             => 'GET',
			'callback'            => 'myogenix_trt_consent_confirm_page',
			'permission_callback' => '__return_true',
		),
		array(
			'methods'             => 'POST',
			'callback'            => 'myogenix_trt_consent_submit',
			'permission_callback' => '__return_true',
		),
	) );
} );

function myogenix_trt_validate_consent_request( WP_REST_Request $request ) {
	$subscription_id = absint( $request->get_param( 'subscription_id' ) );
	$cycle_start_ts   = absint( $request->get_param( 'cycle_start' ) );
	$token            = (string) $request->get_param( 'token' );
	$action           = (string) $request->get_param( 'action' );

	if ( ! in_array( $action, array( 'continue', 'decline' ), true ) ) {
		return new WP_Error( 'trt_bad_action', 'Invalid action.', array( 'status' => 400 ) );
	}

	$subscription = wcs_get_subscription( $subscription_id );
	if ( ! $subscription instanceof WC_Subscription ) {
		return new WP_Error( 'trt_bad_subscription', 'Subscription not found.', array( 'status' => 404 ) );
	}

	if ( ! hash_equals( myogenix_trt_consent_token( $subscription_id, $cycle_start_ts ), $token ) ) {
		return new WP_Error( 'trt_bad_token', 'This link is invalid.', array( 'status' => 403 ) );
	}

	if ( time() > $cycle_start_ts + MYOGENIX_TRT_CONSENT_TTL ) {
		return new WP_Error( 'trt_expired', 'This link has expired. Please contact support@myogenixpharma.com.', array( 'status' => 410 ) );
	}

	$resolved = $subscription->get_meta( '_trt_consent_resolved_for' );
	if ( (string) $resolved === (string) $cycle_start_ts ) {
		return new WP_Error( 'trt_already_resolved', 'This renewal has already been handled.', array( 'status' => 409 ) );
	}

	return array( $subscription, $cycle_start_ts, $action );
}

function myogenix_trt_consent_confirm_page( WP_REST_Request $request ) {
	$result = myogenix_trt_validate_consent_request( $request );
	if ( is_wp_error( $result ) ) {
		return myogenix_trt_html_response( '<p>' . esc_html( $result->get_error_message() ) . '</p>' );
	}

	list( $subscription, $cycle_start_ts, $action ) = $result;

	$label = 'continue' === $action ? 'Continue my treatment' : 'Not right now';
	$copy  = 'continue' === $action
		? 'Confirm you want to continue your TRT treatment. We\'ll place your lab requisition and prepare your renewal order.'
		: 'Confirm you\'d like to pause. Our team will follow up before anything is cancelled.';

	ob_start();
	?>
	<form method="post" action="<?php echo esc_url( rest_url( 'myogenix/v1/trt-renewal-consent' ) ); ?>">
		<p><?php echo esc_html( $copy ); ?></p>
		<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $subscription->get_id() ); ?>">
		<input type="hidden" name="cycle_start" value="<?php echo esc_attr( $cycle_start_ts ); ?>">
		<input type="hidden" name="token" value="<?php echo esc_attr( $request->get_param( 'token' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
		<button type="submit"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
	return myogenix_trt_html_response( ob_get_clean() );
}

function myogenix_trt_consent_submit( WP_REST_Request $request ) {
	$result = myogenix_trt_validate_consent_request( $request );
	if ( is_wp_error( $result ) ) {
		return myogenix_trt_html_response( '<p>' . esc_html( $result->get_error_message() ) . '</p>' );
	}

	list( $subscription, $cycle_start_ts, $action ) = $result;

	if ( 'continue' === $action ) {
		$outcome = myogenix_trt_handle_continue( $subscription );
	} else {
		$outcome = myogenix_trt_handle_decline( $subscription );
	}

	$subscription->update_meta_data( '_trt_consent_resolved_for', $cycle_start_ts );
	$subscription->update_meta_data( '_trt_consent_resolved_action', $action );
	$subscription->save();

	if ( is_wp_error( $outcome ) ) {
		return myogenix_trt_html_response( '<p>Something went wrong: ' . esc_html( $outcome->get_error_message() ) . '. Our team has been notified — please contact support@myogenixpharma.com.</p>' );
	}

	$message = 'continue' === $action
		? 'Thanks — we\'ve started your renewal. You\'ll hear from us once your labs are reviewed.'
		: 'Got it — we\'ve paused your renewal. Our team will follow up shortly.';

	return myogenix_trt_html_response( '<p>' . esc_html( $message ) . '</p>' );
}

function myogenix_trt_html_response( $body_html ) {
	// WP_REST_Response only serializes JSON by default; this route is
	// deliberately browser-facing (email links, a form submit), so it prints
	// HTML directly and exits rather than returning through the REST
	// response/serialization pipeline.
	nocache_headers();
	status_header( 200 );
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!doctype html><html><head><meta charset="utf-8"><title>Myogenix Pharma</title></head><body>' . $body_html . '</body></html>';
	exit;
}

// ─── 5. Continue / Decline handlers ────────────────────────────────────────

function myogenix_trt_handle_continue( WC_Subscription $subscription ) {
	$requisition_id = myogenix_trt_place_lab_requisition( $subscription );
	if ( is_wp_error( $requisition_id ) ) {
		return $requisition_id;
	}

	$GLOBALS['myogenix_trt_suppress_shopify_callback'] = true;
	$renewal_order = wcs_create_renewal_order( $subscription );
	$GLOBALS['myogenix_trt_suppress_shopify_callback'] = false;

	if ( is_wp_error( $renewal_order ) ) {
		return $renewal_order;
	}

	$renewal_order->update_meta_data( '_prescribery_requisition_id', $requisition_id );
	$renewal_order->add_order_note( "TRT renewal: lab requisition {$requisition_id} placed via week-9 consent flow. Order left unpaid pending Prescribery approval." );
	$renewal_order->save();

	return $renewal_order;
}

/**
 * Places a lab requisition with Prescribery for a TRT renewal.
 *
 * BLOCKED: Prescribery has not yet provided the endpoint/payload spec for
 * this (confirmed via full read of prescribery-wc-integration/includes/
 * class-api.php and prescription-charge-previous-cart.php — the only
 * existing outbound Prescribery calls are patient CRUD, phone validation,
 * saveTransectionDetails, and the post-charge release-erx-to-pharmacy call;
 * nothing places a new lab order). Wire in the real call here once the spec
 * arrives — everything downstream (order creation, meta mapping, hold-for-
 * approval) is already built and does not need to change.
 */
function myogenix_trt_place_lab_requisition( WC_Subscription $subscription ) {
	return new WP_Error( 'trt_requisition_not_wired', 'Lab requisition API not yet available — see myogenix_trt_place_lab_requisition() in inc/trt-renewal-redesign.php.' );
}

function myogenix_trt_handle_decline( WC_Subscription $subscription ) {
	$subscription->update_status( 'on-hold', 'Patient declined TRT renewal via week-9 consent email — awaiting staff review.' );
	$subscription->save();

	wp_mail(
		'support@myogenixpharma.com',
		"TRT renewal declined — subscription #{$subscription->get_id()}",
		"Patient {$subscription->get_billing_first_name()} {$subscription->get_billing_last_name()} ({$subscription->get_billing_email()}) declined their TRT renewal.\n\nSubscription: " . $subscription->get_edit_order_url() . "\n\nPlease review and close out manually."
	);

	return true;
}
