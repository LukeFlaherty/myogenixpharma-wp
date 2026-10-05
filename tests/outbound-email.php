<?php
/** Standalone tests for the global outbound BCC policy. */
define( 'ABSPATH', __DIR__ );

$registered_filter = null;
function add_filter( $hook, $callback ) {
	$GLOBALS['registered_filter'] = array( $hook, $callback );
}

require dirname( __DIR__ ) . '/inc/outbound-email.php';

$checks = 0;
function check( $condition, $label ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
	++$checks;
}

check( array( 'wp_mail', 'myogenix_add_outbound_bcc' ) === $registered_filter, 'Registers the global wp_mail filter' );

$mail = myogenix_add_outbound_bcc( array( 'to' => 'client@example.com', 'headers' => array( 'Content-Type: text/html' ) ) );
check( in_array( 'Bcc: adam@myogenix.com', $mail['headers'], true ), 'Adds Adam to array headers' );

$mail = myogenix_add_outbound_bcc( array( 'to' => array( 'client@example.com' ), 'headers' => "From: support@example.com\r\nReply-To: support@example.com" ) );
check( str_ends_with( $mail['headers'], "\r\nBcc: adam@myogenix.com" ), 'Adds Adam to string headers' );

$mail = myogenix_add_outbound_bcc( array( 'to' => 'Adam <ADAM@MYOGENIX.COM>', 'headers' => array() ) );
check( array() === $mail['headers'], 'Does not duplicate Adam when he is already a direct recipient' );

$headers = array( 'Bcc: adam@myogenix.com' );
$mail = myogenix_add_outbound_bcc( array( 'to' => 'client@example.com', 'headers' => $headers ) );
check( $headers === $mail['headers'], 'Does not duplicate an existing Bcc header' );

echo "PASS: {$checks} outbound email checks\n";
