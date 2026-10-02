<?php
/** Prescribery operational truth: authenticated webhooks plus read-only reconciliation. */
defined( 'ABSPATH' ) || exit;

const WAVE_PRESCRIBERY_EVENT_META = '_wave_prescribery_events';
const WAVE_PRESCRIBERY_SYNC_HOOK = 'wave_prescribery_sync_all';

add_filter( 'cron_schedules', function ( $schedules ) {
	$schedules['wave_six_hours'] = array( 'interval' => 6 * HOUR_IN_SECONDS, 'display' => 'Every six hours' );
	return $schedules;
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( WAVE_PRESCRIBERY_SYNC_HOOK ) ) {
		wp_schedule_event( time() + 300, 'wave_six_hours', WAVE_PRESCRIBERY_SYNC_HOOK );
	}
} );
add_action( WAVE_PRESCRIBERY_SYNC_HOOK, 'wave_prescribery_sync_all_patients' );

function wave_prescribery_payload( array $params ) {
	$payload = $params;
	if ( is_array( $params['payload'] ?? null ) ) { $payload = array_merge( $payload, $params['payload'] ); }
	if ( is_array( $params['payload']['data'] ?? null ) ) { $payload = array_merge( $payload, $params['payload']['data'] ); }
	if ( is_array( $params['data'] ?? null ) ) { $payload = array_merge( $payload, $params['data'] ); }
	return $payload;
}

function wave_prescribery_related_ids( $value ) {
	$values = is_array( $value ) ? $value : preg_split( '/\s*,\s*/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
	return array_values( array_unique( array_filter( array_map( 'absint', $values ) ) ) );
}

function wave_prescribery_event_type( array $payload ) {
	$status = strtolower( sanitize_key( $payload['order_status'] ?? '' ) );
	if ( in_array( $status, array( 'shipped', 'delivered' ), true ) ) { return $status; }
	foreach ( is_array( $payload['drugs'] ?? null ) ? $payload['drugs'] : array() as $drug ) {
		if ( is_array( $drug ) && ! empty( $drug['drug_expiry'] ) ) { return 'prescription_renewal'; }
	}
	return '';
}

function wave_prescribery_patient_id( $record ) {
	return absint( $record->get_meta( '_prescribery_patient_id' ) ?: $record->get_meta( '_pre_patient_uid' ) );
}

function wave_prescribery_event_time( array $payload ) {
	foreach ( array( 'shipped_at', 'delivered_at', 'occurred_at', 'event_at', 'updated_at', 'timestamp' ) as $key ) {
		if ( empty( $payload[ $key ] ) || ! is_scalar( $payload[ $key ] ) ) { continue; }
		$time = is_numeric( $payload[ $key ] ) ? (int) $payload[ $key ] : strtotime( (string) $payload[ $key ] );
		if ( $time && $time > time() - 2 * YEAR_IN_SECONDS && $time < time() + DAY_IN_SECONDS ) { return $time; }
	}
	return time();
}

/** Find the Woo order even when Prescribery sends its pharmacy order ID. */
function wave_prescribery_resolve_order( array $payload ) {
	$ids = array_merge( array( absint( $payload['order_id'] ?? 0 ) ), wave_prescribery_related_ids( $payload['related_order_ids'] ?? '' ) );
	foreach ( array_unique( array_filter( $ids ) ) as $id ) {
		$order = wc_get_order( $id );
		if ( $order instanceof WC_Order && 'shop_order' === $order->get_type() && wave_trt_contains_product( $order ) && ! wave_trt_test_record( $order ) ) { return $order; }
	}
	$patient_id = absint( $payload['patient_id'] ?? 0 );
	$appointment_id = absint( $payload['appointment_id'] ?? 0 );
	if ( ! $patient_id && ! $appointment_id ) { return null; }
	$candidates = wc_get_orders( array( 'type' => 'shop_order', 'status' => array_keys( wc_get_order_statuses() ), 'limit' => 500, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects' ) );
	$patient_matches = array();
	foreach ( $candidates as $order ) {
		if ( ! wave_trt_contains_product( $order ) || wave_trt_test_record( $order ) ) { continue; }
		if ( $appointment_id && $appointment_id === absint( $order->get_meta( 'appointment_id' ) ) ) { return $order; }
		if ( $patient_id && $patient_id === wave_prescribery_patient_id( $order ) ) { $patient_matches[] = $order; }
	}
	foreach ( $patient_matches as $order ) {
		$created = $order->get_date_created();
		if ( $created && $created->getTimestamp() >= time() - 180 * DAY_IN_SECONDS && ! $order->has_status( array( 'cancelled', 'refunded', 'rejected', 'trash' ) ) && ! $order->get_meta( 'pharmacy_tracking_number' ) ) { return $order; }
	}
	return $patient_matches[0] ?? null;
}

function wave_prescribery_events( $record ) {
	$events = $record ? $record->get_meta( WAVE_PRESCRIBERY_EVENT_META ) : array();
	return is_array( $events ) ? $events : array();
}

function wave_prescribery_event_hash( array $event ) {
	return hash( 'sha256', implode( '|', array( $event['type'] ?? '', $event['external_order_id'] ?? '', $event['appointment_id'] ?? '', $event['tracking_number'] ?? '', $event['renewal_at'] ?? '' ) ) );
}

function wave_prescribery_record_event( WC_Order $order, array $payload, $source = 'Prescribery webhook' ) {
	$type = wave_prescribery_event_type( $payload );
	if ( ! $type ) { return false; }
	$renewal_at = '';
	$drugs = array();
	foreach ( is_array( $payload['drugs'] ?? null ) ? $payload['drugs'] : array() as $drug ) {
		if ( ! is_array( $drug ) ) { continue; }
		if ( ! empty( $drug['drug_expiry'] ) ) {
			$time = strtotime( (string) $drug['drug_expiry'] );
			if ( $time && ( ! $renewal_at || $time < strtotime( $renewal_at ) ) ) { $renewal_at = gmdate( 'Y-m-d', $time ); }
		}
		if ( ! empty( $drug['drug_name'] ) ) { $drugs[] = sanitize_text_field( $drug['drug_name'] ); }
	}
	$event = array(
		'type' => $type,
		'occurred_at' => wave_prescribery_event_time( $payload ),
		'received_at' => time(),
		'source' => sanitize_text_field( $source ),
		'external_order_id' => sanitize_text_field( (string) ( $payload['order_id'] ?? '' ) ),
		'related_order_ids' => wave_prescribery_related_ids( $payload['related_order_ids'] ?? '' ),
		'appointment_id' => absint( $payload['appointment_id'] ?? 0 ),
		'tracking_number' => sanitize_text_field( (string) ( $payload['tracking_number'] ?? '' ) ),
		'renewal_at' => $renewal_at,
		'drugs' => array_values( array_unique( $drugs ) ),
	);
	$event['hash'] = wave_prescribery_event_hash( $event );
	$events = wave_prescribery_events( $order );
	if ( array_filter( $events, function ( $existing ) use ( $event ) { return ( $existing['hash'] ?? '' ) === $event['hash']; } ) ) { return false; }
	$events[] = $event;
	$order->update_meta_data( WAVE_PRESCRIBERY_EVENT_META, array_slice( $events, -50 ) );
	if ( $event['external_order_id'] ) {
		$external = $order->get_meta( '_wave_prescribery_order_ids' );
		$external = is_array( $external ) ? $external : array();
		$external[] = $event['external_order_id'];
		$order->update_meta_data( '_wave_prescribery_order_ids', array_values( array_unique( $external ) ) );
	}
	if ( in_array( $type, array( 'shipped', 'delivered' ), true ) ) {
		$order->update_meta_data( '_pharmacy_order_status', ucfirst( $type ) );
		$work = $order->get_meta( '_wave_trt_workflow' );
		$work = is_array( $work ) ? $work : array();
		$milestone = 'delivered' === $type ? 'delivery' : 'shipped';
		$work['milestones'][ $milestone ] = array( 'at' => $event['occurred_at'], 'by' => $source, 'note' => ucfirst( $type ) . ' status received directly from Prescribery.' );
		$work['status'] = 'delivered' === $type ? 'resolved' : 'pharmacy';
		$work['due'] = 'delivered' === $type ? '' : wp_date( 'Y-m-d', $event['occurred_at'] + 7 * DAY_IN_SECONDS );
		$work['next'] = 'delivered' === $type ? 'Delivery confirmed by Prescribery. Review the aligned renewal date.' : 'Shipment confirmed by Prescribery. Monitor delivery and the aligned renewal date.';
		$work['revision'] = (int) ( $work['revision'] ?? 0 ) + 1;
		$work['updated'] = time();
		$order->update_meta_data( '_wave_trt_workflow', $work );
	}
	if ( $renewal_at ) { $order->update_meta_data( '_wave_prescribery_renewal_at', $renewal_at ); }
	$order->save();
	$label = 'prescription_renewal' === $type ? 'Prescription renewal due ' . $renewal_at : ucfirst( $type ) . ' confirmed by Prescribery';
	$order->add_order_note( $label . ( $event['tracking_number'] ? '; tracking received.' : '.' ) );
	if ( 'shipped' === $type ) { wave_prescribery_align_subscriptions( $order, $event['occurred_at'] ); }
	return true;
}

function wave_prescribery_payment_time( WC_Order $order ) {
	$paid = $order->get_date_paid();
	$paid_at = $paid ? $paid->getTimestamp() : 0;
	foreach ( wc_get_order_notes( array( 'order_id' => $order->get_id(), 'limit' => 100, 'orderby' => 'date_created', 'order' => 'DESC', 'type' => 'internal' ) ) as $note ) {
		$text = wp_strip_all_tags( $note->content );
		if ( false !== strpos( $text, 'Recurring payment captured in Stripe' ) || preg_match( '/Attempt \d+: Payment succeeded/', $text ) ) {
			$paid_at = max( $paid_at, $note->date_created ? $note->date_created->getTimestamp() : 0 );
		}
	}
	return $paid_at;
}

/** A paid shipment may delay, never accelerate, the next automatic TRT payment. */
function wave_prescribery_align_subscriptions( WC_Order $order, $fulfilled_at ) {
	$paid_at = wave_prescribery_payment_time( $order );
	if ( ! $paid_at || $fulfilled_at < $paid_at - DAY_IN_SECONDS || $fulfilled_at > $paid_at + 90 * DAY_IN_SECONDS || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) { return; }
	foreach ( wcs_get_subscriptions_for_order( $order->get_id(), array( 'order_type' => 'any' ) ) as $sub ) {
		if ( ! $sub->has_status( 'active' ) || ! wave_trt_contains_product( $sub ) ) { continue; }
		$next = wcs_add_time( $sub->get_billing_interval(), $sub->get_billing_period(), $fulfilled_at );
		$current = $sub->get_time( 'next_payment' );
		$sub->update_meta_data( '_wave_prescribery_fulfilled_at', $fulfilled_at );
		$sub->update_meta_data( '_wave_prescribery_next_renewal', $next );
		if ( $next > $current + DAY_IN_SECONDS ) {
			$sub->update_dates( array( 'next_payment' => gmdate( 'Y-m-d H:i:s', $next ) ) );
			$sub->update_meta_data( '_trt_cycle_start', $fulfilled_at );
			$sub->add_order_note( 'Next TRT renewal aligned to Prescribery shipment: ' . wp_date( 'M j, Y', $next ) . '. Previous WooCommerce date: ' . ( $current ? wp_date( 'M j, Y', $current ) : 'not scheduled' ) . '.' );
		}
		$sub->save();
	}
}

/** Called inside the already authenticated /prescription/v1/approve route. */
function wave_prescribery_ingest_webhook( WP_REST_Request $request ) {
	$payload = wave_prescribery_payload( $request->get_params() );
	if ( ! wave_prescribery_event_type( $payload ) ) { return null; }
	$order = wave_prescribery_resolve_order( $payload );
	if ( ! $order ) {
		$unmatched = get_option( '_wave_prescribery_unmatched', array() );
		$unmatched = is_array( $unmatched ) ? $unmatched : array();
		$unmatched[] = array( 'at' => time(), 'type' => wave_prescribery_event_type( $payload ), 'patient_id' => absint( $payload['patient_id'] ?? 0 ), 'order_id' => sanitize_text_field( (string) ( $payload['order_id'] ?? '' ) ), 'appointment_id' => absint( $payload['appointment_id'] ?? 0 ) );
		update_option( '_wave_prescribery_unmatched', array_slice( $unmatched, -100 ), false );
		return false;
	}
	wave_prescribery_record_event( $order, $payload );
	$request->set_param( 'order_id', $order->get_id() );
	return $order;
}

function wave_prescribery_api_token() {
	if ( $token = get_transient( 'wave_prescribery_v2_token' ) ) { return $token; }
	$options = (array) get_option( 'pre_woo_options', array() );
	if ( empty( $options['api_key'] ) ) { return new WP_Error( 'prescribery_auth', 'Prescribery API is not configured.' ); }
	$response = wp_remote_post( 'https://staff.prescribery.com/api/v2/access-token', array( 'headers' => array( 'Accept' => 'application/json', 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'api_key' => $options['api_key'] ) ), 'timeout' => 20 ) );
	$body = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true );
	$token = $body['data']['access_token'] ?? $body['access_token'] ?? '';
	if ( ! $token ) { return new WP_Error( 'prescribery_auth', 'Prescribery authentication failed.' ); }
	set_transient( 'wave_prescribery_v2_token', $token, max( 60, (int) ( $body['data']['expires_in'] ?? 3600 ) - 60 ) );
	return $token;
}

function wave_prescribery_api_get( $path, array $query = array() ) {
	$token = wave_prescribery_api_token();
	if ( is_wp_error( $token ) ) { return $token; }
	$url = 'https://staff.prescribery.com/api/v2/' . ltrim( $path, '/' );
	if ( $query ) { $url = add_query_arg( $query, $url ); }
	$response = wp_remote_get( $url, array( 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ), 'timeout' => 25 ) );
	if ( is_wp_error( $response ) ) { return $response; }
	return array( 'code' => wp_remote_retrieve_response_code( $response ), 'body' => json_decode( wp_remote_retrieve_body( $response ), true ) );
}

function wave_prescribery_sync_all_patients() {
	if ( ! function_exists( 'wc_get_orders' ) || get_transient( 'wave_prescribery_sync_lock' ) ) { return; }
	set_transient( 'wave_prescribery_sync_lock', 1, 15 * MINUTE_IN_SECONDS );
	$summary = array( 'started_at' => time(), 'patients' => 0, 'mapped' => 0, 'appointments' => 0, 'errors' => 0 );
	try {
		list( $patients ) = wave_trt_load_patients();
		foreach ( $patients as $patient ) {
			$record = $patient['orders'][0] ?? $patient['subscriptions'][0] ?? null;
			if ( ! $record ) { continue; }
			$summary['patients']++;
			$patient_id = 0;
			foreach ( array_merge( $patient['orders'], $patient['subscriptions'] ) as $candidate ) { $patient_id = $patient_id ?: wave_prescribery_patient_id( $candidate ); }
			if ( ! $patient_id && $record->get_billing_email() ) {
				$lookup = wave_prescribery_api_get( 'patients', array( 'search' => $record->get_billing_email(), 'per_page' => 5 ) );
				foreach ( ! is_wp_error( $lookup ) && 200 === $lookup['code'] ? ( $lookup['body']['data'] ?? array() ) : array() as $match ) {
					if ( strtolower( (string) ( $match['email'] ?? '' ) ) === strtolower( $record->get_billing_email() ) ) { $patient_id = absint( $match['patient_id'] ?? $match['id'] ?? 0 ); break; }
				}
			}
			if ( ! $patient_id ) { $summary['errors']++; continue; }
			$summary['mapped']++;
			foreach ( array_merge( $patient['orders'], $patient['subscriptions'] ) as $candidate ) {
				if ( ! wave_prescribery_patient_id( $candidate ) ) { $candidate->update_meta_data( '_prescribery_patient_id', $patient_id ); $candidate->save(); }
			}
			$from = gmdate( 'Y-m-d', time() - YEAR_IN_SECONDS );
			$to = gmdate( 'Y-m-d', time() + YEAR_IN_SECONDS );
			foreach ( array( 'completed', 'upcoming', 'cancelled' ) as $type ) {
				$response = wave_prescribery_api_get( 'patient/' . $patient_id . '/appointments', array( 'type' => $type, 'from_date' => $from, 'to_date' => $to ) );
				if ( is_wp_error( $response ) || ! in_array( $response['code'], array( 200, 404 ), true ) ) { $summary['errors']++; continue; }
				foreach ( $response['body']['data'] ?? array() as $appointment ) {
					if ( ! is_array( $appointment ) ) { continue; }
					$event = array( 'type' => 'appointment_' . sanitize_key( $appointment['status'] ?? $type ), 'occurred_at' => strtotime( (string) ( $appointment['updated_at'] ?? $appointment['start_date_time'] ?? '' ) ) ?: time(), 'received_at' => time(), 'source' => 'Prescribery API', 'external_order_id' => '', 'related_order_ids' => array(), 'appointment_id' => absint( $appointment['id'] ?? 0 ), 'tracking_number' => '', 'renewal_at' => '', 'drugs' => array(), 'hash' => '' );
					$event['hash'] = wave_prescribery_event_hash( $event );
					$events = wave_prescribery_events( $record );
					if ( ! array_filter( $events, function ( $existing ) use ( $event ) { return ( $existing['hash'] ?? '' ) === $event['hash']; } ) ) { $events[] = $event; $record->update_meta_data( WAVE_PRESCRIBERY_EVENT_META, array_slice( $events, -50 ) ); $summary['appointments']++; }
				}
			}
			$record->update_meta_data( '_wave_prescribery_synced_at', time() );
			$record->save();
		}
	} finally {
		$summary['finished_at'] = time();
		update_option( 'wave_prescribery_last_sync', $summary, false );
		delete_transient( 'wave_prescribery_sync_lock' );
	}
	return $summary;
}
