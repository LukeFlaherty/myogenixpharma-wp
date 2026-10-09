<?php
/** Privacy-safe operational diagnostics for the Wave Consulting order desk. */
defined( 'ABSPATH' ) || exit;

function wave_orders_log_request_id() {
	static $id = '';
	if ( ! $id ) { $id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'wave-', true ); }
	return $id;
}

function wave_orders_safe_log_value( $value, $key = '' ) {
	if ( preg_match( '/email|phone|address|card|nonce|token|secret|password|key/i', (string) $key ) ) { return '[redacted]'; }
	if ( is_array( $value ) ) {
		$safe = array(); foreach ( $value as $k => $v ) { $safe[ $k ] = wave_orders_safe_log_value( $v, $k ); } return $safe;
	}
	if ( is_object( $value ) ) { return '[object ' . get_class( $value ) . ']'; }
	if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) { return $value; }
	$value = wp_strip_all_tags( (string) $value );
	$value = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email redacted]', $value );
	$value = preg_replace( '/(?<!\d)(?:\d[ -]?){12,19}(?!\d)/', '[number redacted]', $value );
	return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 1500 ) : substr( $value, 0, 1500 );
}

function wave_orders_log( $level, $event, $context = array() ) {
	if ( ! function_exists( 'wc_get_logger' ) ) { return; }
	$allowed = array( 'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency' );
	$level = in_array( $level, $allowed, true ) ? $level : 'info';
	$base = array( 'event' => $event, 'request_id' => wave_orders_log_request_id(), 'actor_id' => get_current_user_id() );
	$context = wave_orders_safe_log_value( array_merge( $base, (array) $context ) );
	$message = $event . ' ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
	wc_get_logger()->log( $level, $message, array( 'source' => 'wave-orders' ) );
}

function wave_orders_log_order_context( $order, $extra = array() ) {
	if ( ! $order instanceof WC_Order ) { return $extra; }
	return array_merge( array(
		'draft_id'       => absint( $order->get_meta( '_wave_order_draft' ) ),
		'order_id'       => $order->get_id(),
		'customer_id'    => $order->get_customer_id(),
		'status'         => $order->get_status(),
		'gateway'        => $order->get_payment_method(),
		'currency'       => $order->get_currency(),
		'total'          => $order->get_total(),
		'transaction_set'=> (bool) $order->get_transaction_id(),
	), $extra );
}

add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
	if ( ! $order || ! $order->get_meta( '_wave_order_draft' ) ) { return; }
	wave_orders_log( 'info', 'order_status_changed', wave_orders_log_order_context( $order, array( 'from' => $from, 'to' => $to ) ) );
}, 10, 4 );

add_action( 'woocommerce_payment_complete', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || ! $order->get_meta( '_wave_order_draft' ) ) { return; }
	wave_orders_log( 'info', 'payment_complete', wave_orders_log_order_context( $order ) );
}, 20 );

add_filter( 'rest_request_after_callbacks', function ( $response, $handler, $request ) {
	$route = is_object( $request ) && is_callable( array( $request, 'get_route' ) ) ? (string) $request->get_route() : '';
	if ( false === strpos( $route, 'wc-stripe' ) || ! preg_match( '#/pay/?$#', $route ) ) { return $response; }
	$order_id = absint( $request->get_param( 'order_id' ) ); $order = wc_get_order( $order_id );
	if ( ! $order || ! $order->get_meta( '_wave_order_draft' ) ) { return $response; }
	$error = is_wp_error( $response ); $data = $error ? array() : ( is_callable( array( $response, 'get_data' ) ) ? (array) $response->get_data() : (array) $response );
	$codes = $error ? $response->get_error_codes() : ( isset( $data['code'] ) ? array( $data['code'] ) : array() );
	wave_orders_log( $error || empty( $data['success'] ) ? 'error' : 'info', 'stripe_admin_payment_response', wave_orders_log_order_context( $order, array( 'route' => $route, 'success' => ! $error && ! empty( $data['success'] ), 'error_codes' => $codes ) ) );
	return $response;
}, 20, 3 );

add_action( 'shutdown', function () {
	$error = error_get_last();
	if ( ! $error || ! in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) { return; }
	$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
	if ( false === strpos( $uri, 'wave-orders' ) && false === strpos( $uri, 'wc-stripe' ) ) { return; }
	wave_orders_log( 'critical', 'request_fatal_error', array( 'php_type' => $error['type'], 'message' => $error['message'], 'file' => wp_basename( $error['file'] ), 'line' => $error['line'] ) );
} );
