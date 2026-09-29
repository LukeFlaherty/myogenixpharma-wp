<?php
/** Staff-assisted quarterly intake while Prescribery's questionnaire API is pending. */
defined( 'ABSPATH' ) || exit;

function myogenix_trt_launch_exception( $sub ) {
	$exceptions = get_option( 'myogenix_trt_launch_exceptions', array() );
	return $sub instanceof WC_Subscription ? (string) ( $exceptions[ $sub->get_id() ] ?? '' ) : '';
}

/** Evidence must belong to this treatment order, never a copied prior-cycle milestone. */
function myogenix_trt_intake_verified( $order ) {
	$work = $order->get_meta( '_wave_trt_workflow' );
	$evidence = is_array( $work ) ? ( $work['milestones']['intake'] ?? array() ) : array();
	return myogenix_trt_is_renewal_order( $order ) && $order->get_date_created()
		&& (int) ( $evidence['at'] ?? 0 ) >= $order->get_date_created()->getTimestamp()
		&& ! empty( $evidence['by'] ) && ! empty( trim( $evidence['note'] ?? '' ) );
}

function myogenix_trt_queue_intake( $sub, $order ) {
	if ( $order->get_meta( '_trt_staff_intake_queued' ) ) { return; }
	$order->update_meta_data( '_wave_trt_workflow', array(
		'revision' => 1, 'status' => 'patient', 'due' => wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ),
		'next' => 'Coordinate the required quarterly intake with Prescribery. Verify completion for this renewal and record Intake completed with its verification source. Medication payment remains blocked until intake is verified and provider approval is received.',
		'updated' => time(),
	) );
	$order->update_meta_data( '_trt_staff_intake_queued', time() );
	$order->save();
	myogenix_trt_staff_notice( $sub, 'Quarterly intake — staff action required', 'Renewal order #' . $order->get_id() . ' needs its quarterly intake. Coordinate with Prescribery, then record Intake completed on this new order in Wave Consulting. An already received provider approval can proceed to payment after you verify intake. Staff follow-up is due tomorrow.' );
}

// Verification records evidence; only the existing authenticated provider approval
// can authorize medication payment. Resume that exact validated approval internally.
add_action( 'wave_trt_milestone_updated', function ( $order, $milestone, $operation ) {
	if ( 'intake' !== $milestone || ! myogenix_trt_is_renewal_order( $order ) ) { return; }
	$args = array( $order->get_id() );
	if ( 'clear_milestone' === $operation ) { wp_clear_scheduled_hook( 'myogenix_trt_resume_approval', $args ); return; }
	if ( $order->get_meta( '_trt_waiting_approval' ) && ! wp_next_scheduled( 'myogenix_trt_resume_approval', $args ) ) {
		wp_schedule_single_event( time() + 15, 'myogenix_trt_resume_approval', $args );
	}
}, 10, 3 );

add_action( 'myogenix_trt_resume_approval', 'myogenix_trt_resume_approval' );
function myogenix_trt_resume_approval( $id ) {
	$order = wc_get_order( $id );
	if ( ! $order || ! myogenix_trt_intake_verified( $order ) || $order->get_transaction_id() || 'yes' === $order->get_meta( '_trt_qa_test' ) ) { return; }
	$approval = $order->get_meta( '_trt_waiting_approval' );
	if ( ! is_array( $approval ) || (int) ( $approval['order_id'] ?? 0 ) !== $order->get_id() ) { return; }
	$request = new WP_REST_Request( 'POST', '/prescription/v1/approve' );
	$request->set_body_params( $approval );
	$response = myogenix_trt_approval_callback( $request );
	if ( $response->get_status() >= 400 ) {
		$order->add_order_note( 'Stored provider approval could not proceed. Staff must review the renewal; no automatic retry was scheduled.' );
		$sub = wcs_get_subscription( $order->get_meta( '_trt_subscription_id' ) );
		if ( $sub ) { myogenix_trt_staff_notice( $sub, 'Approved renewal needs review', 'Intake was verified, but renewal #' . $id . ' could not proceed. Review its status and payment notes before retrying.' ); }
	}
}

/** Calendar dates describe workflow events, never booked clinical appointments. */
function myogenix_trt_calendar_events( $record ) {
	$events = array();
	$add = function ( $at, $title, $detail, $state = 'recorded' ) use ( &$events ) {
		if ( $at ) { $events[] = array( 'date' => wp_date( 'Y-m-d', $at ), 'title' => $title, 'detail' => $detail, 'state' => $state ); }
	};
	if ( $record instanceof WC_Subscription && myogenix_trt_enabled( $record ) ) {
		$cycle = myogenix_trt_cycle_start_ts( $record );
		if ( ! $cycle ) { return $events; }
		$resolved = (string) $record->get_meta( '_trt_consent_resolved_for' ) === (string) $cycle;
		$sent = (string) $record->get_meta( '_trt_patient_email_for' ) === (string) $cycle;
		if ( $record->has_status( 'active' ) && ! $resolved ) {
			$add( $sent ? (int) $record->get_meta( '_trt_patient_email_sent_at' ) : $cycle + 63 * DAY_IN_SECONDS, $sent ? 'Renewal invitation sent' : 'Week 9 renewal invitation due', 'Continue / Pause email. Sending is checked daily.', $sent ? 'recorded' : 'scheduled' );
			$nudged = (string) $record->get_meta( '_trt_admin_noresponse_sent_for' ) === (string) $cycle;
			$followup = max( $cycle + 75 * DAY_IN_SECONDS, $sent ? (int) $record->get_meta( '_trt_patient_email_sent_at' ) + DAY_IN_SECONDS : 0 );
			$add( $nudged ? (int) $record->get_meta( '_trt_admin_noresponse_sent_at' ) : $followup, $nudged ? 'No-response staff reminder sent' : 'No-response staff follow-up due', 'Applies only while the patient has not responded; at least one day after a late invitation.', $nudged ? 'recorded' : 'scheduled' );
			$add( $cycle + 85 * DAY_IN_SECONDS, 'Renewal response deadline', 'Unanswered invitations pause renewal without a charge. If no invitation was sent, staff must review.', 'scheduled' );
		} elseif ( $resolved ) {
			if ( $sent ) { $add( (int) $record->get_meta( '_trt_patient_email_sent_at' ), 'Renewal invitation sent', 'Invitation for this treatment cycle.' ); }
			$add( (int) $record->get_meta( '_trt_consent_resolved_at' ), 'Renewal response: ' . $record->get_meta( '_trt_consent_resolved_action' ), 'Recorded renewal decision; not evidence of payment or clinical clearance.' );
		}
	} elseif ( myogenix_trt_is_renewal_order( $record ) ) {
		$add( (int) $record->get_meta( '_trt_lab_created_at' ), 'Renewal lab request accepted', 'Requisition requested; this is not a booked lab visit or completed lab work.' );
		$add( (int) $record->get_meta( '_trt_approval_received_at' ), 'Provider approval received', myogenix_trt_intake_verified( $record ) ? 'Quarterly intake has also been verified. Check the order for payment outcome.' : 'Payment remains blocked until staff verifies this renewal’s quarterly intake.' );
	}
	return $events;
}
