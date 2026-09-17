<?php
/** Register one consent renewal with Prescribery before requesting its labs. */
defined( 'ABSPATH' ) || exit;

/** Caller holds the subscription lock throughout intake and lab submission. */
function myogenix_trt_register_intake( WC_Subscription $sub, WC_Order $order ) {
	if ( ! myogenix_trt_enabled( $sub ) || ! myogenix_trt_is_renewal_order( $order ) || (int) $order->get_meta( '_trt_subscription_id' ) !== $sub->get_id() || ! $order->get_meta( '_trt_pricing_ready' ) ) {
		return new WP_Error( 'intake_config', 'Our team needs to review your renewal before continuing.' );
	}
	$state = $order->get_meta( '_trt_intake_state' );
	if ( 'sent' === $state ) { return true; }
	if ( in_array( $state, array( 'submitting', 'uncertain' ), true ) ) {
		return new WP_Error( 'intake_needs_review', 'Our team is checking your renewal request. Please contact us before trying again.' );
	}
	$s = myogenix_trt_lab_api_settings();
	$host = wp_parse_url( $s['api_base_url'] ?? '', PHP_URL_HOST );
	$patient = myogenix_trt_get_prescribery_patient_id( $sub );
	if ( ! in_array( $host, array( 'staff.prescribery.com', 'staging.prescribery.com' ), true ) || empty( $s['client_id'] ) || empty( $s['source_id'] ) || ! $patient || ! is_callable( array( 'PreWoo_Utils', 'encode_order_id' ) ) ) {
		return new WP_Error( 'intake_config', 'The intake connection needs to be configured by our team.' );
	}
	$options = get_option( 'pre_woo_options', array() );
	$base = $s['intake_base_url'] ?? '';
	if ( ! $base && (int) ( $options['client_id'] ?? 0 ) === (int) $s['client_id'] && (int) ( $options['source_id'] ?? 0 ) === (int) $s['source_id'] && wp_parse_url( $options['dashcallback_base_url'] ?? '', PHP_URL_HOST ) === $host ) {
		$base = $options['external_base_url'] ?? '';
	}
	if ( 'https' !== wp_parse_url( $base, PHP_URL_SCHEME ) || ! preg_match( '/(^|\.)prescribery\.com$/', wp_parse_url( $base, PHP_URL_HOST ) ?? '' ) ) {
		return new WP_Error( 'intake_config', 'Our team needs to configure your questionnaire link.' );
	}
	$payload = array(
		'uuid' => PreWoo_Utils::encode_order_id( $order->get_id(), $s['client_id'] ),
		'orderId' => $order->get_id(),
		'platform' => 'woocommerce',
		'patient_id' => $patient,
		'source_id' => (int) $s['source_id'],
		'client_id' => (int) $s['client_id'],
	);
	$order->update_meta_data( '_pre_patient_uid', $patient );
	$order->update_meta_data( '_trt_intake_state', 'submitting' );
	$order->save();
	// Permit just this explicit send. Legacy creation/status hooks remain blocked.
	$GLOBALS['myogenix_trt_sending_intake_order'] = $order->get_id();
	try {
		$response = wp_remote_post( 'https://' . $host . '/shopify/callback', array(
			'myogenix_trt_intake_order_id' => $order->get_id(),
			'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json', 'Origin' => $options['pre_origin'] ?? home_url() ),
			'body' => wp_json_encode( $payload ), 'timeout' => 25, 'redirection' => 0,
		) );
	} finally {
		$GLOBALS['myogenix_trt_sending_intake_order'] = 0;
	}
	$code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
	$body = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
	$accepted = in_array( $code, array( 200, 201 ), true ) && is_array( $body ) && true === ( $body['ok'] ?? false );
	$order->update_meta_data( '_trt_intake_http_code', $code );
	$order->update_meta_data( '_trt_intake_state', $accepted ? 'sent' : 'uncertain' );
	if ( $accepted ) {
		$order->update_meta_data( '_trt_intake_sent_at', time() );
		$order->update_meta_data( '_trt_intake_url', add_query_arg( 'token', $payload['uuid'], $base ) );
		$order->add_order_note( 'Prescribery accepted this renewal for intake. Duplicate callback sends are blocked.' );
	}
	$order->save();
	return $accepted ? true : new WP_Error( 'intake_needs_review', 'We could not confirm your intake request. Our team will check it before requesting your labs.' );
}

function myogenix_trt_intake_url( $order ) {
	return myogenix_trt_is_renewal_order( $order ) && 'sent' === $order->get_meta( '_trt_intake_state' ) ? esc_url_raw( $order->get_meta( '_trt_intake_url' ) ) : '';
}
