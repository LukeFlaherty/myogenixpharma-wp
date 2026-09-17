<?php
/** Adapt consent renewals to the existing provider-approved charge pipeline. */
defined( 'ABSPATH' ) || exit;

function myogenix_trt_is_renewal_order( $order ) {
	return $order instanceof WC_Order && 'yes' === $order->get_meta( '_trt_consent_renewal' );
}

/** Keep the patient's established medicine price; discard copied upfront fees. */
function myogenix_trt_prepare_renewal_prices( $order, $sub ) {
	foreach ( $order->get_items() as $item ) {
		if ( MYOGENIX_TRT_PRODUCT_ID !== (int) $item->get_product_id() ) { return new WP_Error( 'mixed_subscription', 'Our team needs to review this subscription’s renewal.' ); }
		$qty = (int) $item->get_quantity();
		$total = (float) $item->get_total();
		if ( $qty < 1 ) { return new WP_Error( 'price_review', 'Our team needs to verify your renewal price.' ); }
		if ( $total <= 0 ) {
			$price = (float) $item->get_meta( '_hidden_approval_price' );
			if ( $price <= 0 ) { return new WP_Error( 'price_review', 'Our team needs to verify your renewal price.' ); }
			$total = $price * $qty;
			$item->set_subtotal( $total );
			$item->set_total( $total );
		}
		// Do not let an old full-price approval value overwrite a discounted plan.
		$item->update_meta_data( '_hidden_approval_price', $total / $qty );
		$item->save();
	}
	foreach ( $order->get_items( 'fee' ) as $id => $fee ) {
		if ( preg_match( '/consultation|blood\s*work|lab fee/i', $fee->get_name() ) ) { $order->remove_item( $id ); }
	}
	foreach ( $order->get_items( 'coupon' ) as $id => $coupon ) {
		if ( strtolower( $coupon->get_code() ) === 'freelabs' ) { $order->remove_item( $id ); }
	}
	$order->delete_meta_data( '_lab_fee_paid' );
	$order->calculate_totals();
	if ( (float) $order->get_total() <= 0 ) { return new WP_Error( 'price_review', 'Our team needs to verify your renewal price.' ); }
	$order->update_meta_data( '_trt_renewal_amount', $order->get_total() );
	$order->update_meta_data( '_trt_pricing_ready', 'yes' );
	$order->save();
	return $order;
}

// Pending renewals cannot be paid from My Account or an old pay-link before approval.
add_filter( 'woocommerce_order_needs_payment', function ( $needs, $order ) {
	if ( myogenix_trt_is_renewal_order( $order ) && 'yes' !== $order->get_meta( '_trt_provider_approved' ) ) { return false; }
	return $needs;
}, PHP_INT_MAX, 2 );
add_filter( 'woocommerce_cancel_unpaid_order', function ( $cancel, $order ) {
	return myogenix_trt_is_renewal_order( $order ) ? false : $cancel;
}, 100, 2 );

/** Preserve the existing endpoint's permission callback; replace only its handler. */
add_filter( 'rest_endpoints', function ( $routes ) {
	foreach ( $routes['/prescription/v1/approve'] ?? array() as $key => $handler ) {
		if ( is_array( $handler ) && ( $handler['callback'] ?? null ) === 'prescriptionHandleApproval' ) {
			$routes['/prescription/v1/approve'][ $key ]['callback'] = 'myogenix_trt_approval_callback';
		}
	}
	return $routes;
} );

function myogenix_trt_approval_callback( WP_REST_Request $request ) {
	$p = $request->get_params();
	$id = absint( $p['order_id'] ?? $p['data']['order_id'] ?? $p['payload']['data']['order_id'] ?? 0 );
	$order = wc_get_order( $id );
	// Protect fake parents too: a miscorrelated provider callback must never charge
	// or release their medication through the legacy handler. The REST route still
	// requires its existing authentication before reaching this observer.
	if ( $order && 'yes' === $order->get_meta( '_trt_qa_test' ) && ! ( defined( 'WP_CLI' ) && WP_CLI && apply_filters( 'myogenix_trt_allow_qa_approval', false, $order ) ) ) {
		$order->update_meta_data( '_trt_qa_callback_seen', array( 'at' => time(), 'order_id' => $id, 'patient_id' => absint( $p['patient_id'] ?? 0 ), 'appointment_id' => absint( $p['appointment_id'] ?? 0 ), 'status' => sanitize_key( $p['status'] ?? $p['data']['event'] ?? $p['payload']['event'] ?? '' ), 'keys' => array_map( 'sanitize_key', array_keys( $p ) ) ) );
		$order->save();
		return new WP_REST_Response( array( 'error' => 'Test order: external approval disabled.' ), 403 );
	}
	if ( ! myogenix_trt_is_renewal_order( $order ) ) { return prescriptionHandleApproval( $request ); }
	$sub = wcs_get_subscription( $order->get_meta( '_trt_subscription_id' ) );
	if ( ! $sub || ! myogenix_trt_lock( $sub->get_id() ) ) { return new WP_REST_Response( array( 'error' => 'Renewal is being processed.' ), 409 ); }
	try {
		$status = strtolower( sanitize_key( $p['status'] ?? $p['data']['event'] ?? $p['payload']['event'] ?? '' ) );
		if ( 'approved' !== $status ) { return prescriptionHandleApproval( $request ); }
		if ( $order->get_transaction_id() ) { return new WP_REST_Response( array( 'success' => true, 'message' => 'Already paid; no additional charge.' ) ); }
		if ( ! $sub->has_status( 'active' ) || ! $order->has_status( array( 'pending', 'failed', 'on-hold' ) ) || 'continue' !== $sub->get_meta( '_trt_consent_resolved_action' ) || (int) $sub->get_meta( '_trt_pending_renewal_order' ) !== $order->get_id() || 'sent' !== $order->get_meta( '_trt_intake_state' ) || 'created' !== $order->get_meta( '_trt_lab_state' ) || ! $order->get_meta( '_prescribery_requisition_id' ) ) {
			return new WP_REST_Response( array( 'error' => 'Renewal requires consent, intake registration, and a confirmed lab request before approval.' ), 409 );
		}
		$patient_id = absint( $p['patient_id'] ?? 0 );
		$appointment_id = absint( $p['appointment_id'] ?? 0 );
		if ( ! $patient_id || ! $appointment_id || $patient_id !== (int) $order->get_meta( '_prescribery_patient_id' ) ) { return new WP_REST_Response( array( 'error' => 'Patient and appointment must match this renewal.' ), 422 ); }
		$amount = (float) $order->get_meta( '_trt_renewal_amount' );
		if ( $amount <= 0 || abs( $amount - (float) $order->get_total() ) > 0.009 ) { return new WP_REST_Response( array( 'error' => 'Renewal amount needs review.' ), 409 ); }
		$reasons = prescription_parse_appointment_reason( $p['appointment_reason'] ?? '' );
		foreach ( $order->get_items() as $item ) {
			if ( $reasons && ! array_filter( $reasons, function ( $name ) use ( $item ) { return prescription_fuzzy_match( $name, $item->get_name() ); } ) ) { return new WP_REST_Response( array( 'error' => 'Approval does not match the renewal medicine.' ), 422 ); }
		}
		$order->update_meta_data( 'appointment_id', $appointment_id );
		$order->update_meta_data( '_order_origin', 'doctor_approval_api' );
		$order->update_meta_data( '_trt_provider_approved', 'yes' );
		$order->update_meta_data( '_prescription_charge_amount', $amount );
		$order->update_meta_data( '_wc_stripe_customer', prescription_get_stripe_customer( $order, $order->get_customer_id() ) );
		$order->update_meta_data( '_payment_method_token', prescription_get_payment_method( $order, $order->get_customer_id() ) );
		$order->set_payment_method( 'stripe_cc' );
		$order->add_order_note( 'Provider approved this consent renewal. Amount due: ' . wc_format_decimal( $amount, 2 ) . '. No upfront-payment credit applies.' );
		$order->save();
		// Existing implementation owns Stripe, retries, receipts and pharmacy release.
		$payment = prescriptionChargeStripe( $order );
		return new WP_REST_Response( array( 'success' => true, 'order_id' => $id, 'payment' => $payment ) );
	} finally {
		myogenix_trt_unlock( $sub->get_id() );
	}
}

add_action( 'woocommerce_subscription_renewal_payment_complete', function ( $sub, $order ) {
	if ( myogenix_trt_is_renewal_order( $order ) ) { $order->update_meta_data( '_trt_wcs_payment_recorded', 'yes' ); $order->save(); }
}, 100, 2 );

add_action( 'woocommerce_payment_complete', function ( $id ) {
	$order = wc_get_order( $id );
	if ( ! myogenix_trt_is_renewal_order( $order ) || ! $order->get_transaction_id() || $order->get_meta( '_trt_cycle_advanced' ) ) { return; }
	$sub = wcs_get_subscription( $order->get_meta( '_trt_subscription_id' ) );
	if ( ! $sub || (int) $sub->get_meta( '_trt_pending_renewal_order' ) !== $id ) { return; }
	// A consent renewal can be paid while the subscription is still active.
	// WCS's status-change handler only advances on-hold subscriptions; explicitly
	// invoke its normal bookkeeping once for this active-subscription case.
	if ( ! $order->get_meta( '_trt_wcs_payment_recorded' ) ) {
		$order->update_meta_data( '_trt_wcs_payment_recorded', 'yes' );
		$order->save();
		$sub->payment_complete_for_order( $order );
	}
	// Preserve the existing paid-through period when approval happens early.
	$due_before = (int) $order->get_meta( '_trt_next_payment_before' );
	$new_cycle = max( $due_before, $order->get_date_paid()->getTimestamp() );
	$next = wcs_add_time( $sub->get_billing_interval(), $sub->get_billing_period(), $new_cycle );
	$end = $sub->get_time( 'end' );
	$sub->update_dates( array( 'next_payment' => $end && $next >= $end ? 0 : gmdate( 'Y-m-d H:i:s', $next ) ) );
	$sub->update_meta_data( '_trt_cycle_start', $new_cycle );
	$sub->delete_meta_data( '_trt_pending_renewal_order' );
	$sub->save();
	$order->update_meta_data( '_trt_cycle_advanced', 'yes' );
	$order->save();
}, 30 );
