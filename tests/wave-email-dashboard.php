<?php
/** Standalone safety tests for outbound communication helpers. */
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
function add_action( ...$args ) {}
function apply_filters( $tag, $value ) { return $value; }
function get_option( $key ) { return 'support@myogenixpharma.com'; }
function sanitize_email( $value ) { return filter_var( $value, FILTER_VALIDATE_EMAIL ) ?: ''; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
require dirname( __DIR__ ) . '/inc/wave-email-dashboard.php';
$checks = 0;
function check( $ok, $label ) { global $checks; if ( ! $ok ) { throw new RuntimeException( $label ); } $checks++; }
check( array( 'one@example.com', 'two@example.org' ) === wave_email_extract_addresses( "One <ONE@example.com>,\n two@example.org" ), 'Extract and normalize recipients' );
check( array( 'one@example.com', 'two@example.org' ) === wave_email_extract_addresses( 'one@example.com,\\ntwo@example.org' ), 'Normalize mail logger literal newlines' );
check( wave_email_is_customer_recipient( 'support@myogenixpharma.com, client@example.com' ), 'Mixed recipient list is customer communication' );
check( ! wave_email_is_customer_recipient( 'adam@myogenix.com, support@myogenixpharma.com' ), 'Internal-only message is not customer communication' );
$redacted = wave_email_redact_message( '<p>Continue at https://example.com/path?token=secret</p>' );
check( false === strpos( $redacted, 'secret' ) && false !== strpos( $redacted, '[secure link hidden]' ), 'Secure links are hidden from dashboard text' );
$redacted = wave_email_redact_message( 'token=secret-value' );
check( 'token=[hidden]' === $redacted, 'Standalone security tokens are hidden' );
check( 'TRT renewal' === wave_email_source( 'Your renewal is ready to review' ), 'TRT source classification' );
check( 'WooCommerce' === wave_email_source( 'Your order is complete' ), 'WooCommerce source classification' );
check( 'WordPress' === wave_email_source( 'Welcome to the newsletter' ), 'WordPress source classification' );
echo "PASS: {$checks} outbound communication checks\n";
