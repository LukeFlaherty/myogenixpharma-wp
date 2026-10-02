<?php
/** Payload normalization checks; no network, WordPress DB, or patient data. */
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'YEAR_IN_SECONDS', 31536000 );
function add_filter( ...$args ) {}
function add_action( ...$args ) {}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function absint( $value ) { return abs( (int) $value ); }
require dirname( __DIR__ ) . '/inc/wave-prescribery-sync.php';
$checks = 0;
function verify_sync( $condition, $label ) { global $checks; if ( ! $condition ) { throw new Exception( $label ); } $checks++; }
$payload = wave_prescribery_payload( array( 'payload' => array( 'data' => array( 'order_id' => '123', 'order_status' => 'Shipped' ) ) ) );
verify_sync( 123 === (int) $payload['order_id'], 'Nested order ID normalized' );
verify_sync( 'shipped' === wave_prescribery_event_type( $payload ), 'Shipment recognized' );
verify_sync( array( 12, 13 ) === wave_prescribery_related_ids( '12, 13,12' ), 'Related IDs normalized and deduplicated' );
verify_sync( 'prescription_renewal' === wave_prescribery_event_type( array( 'drugs' => array( array( 'drug_expiry' => '2026-12-01' ) ) ) ), 'Renewal payload recognized' );
verify_sync( '' === wave_prescribery_event_type( array( 'status' => 'approved' ) ), 'Approval remains with the established charge handler' );
echo "$checks Prescribery sync checks passed.\n";
