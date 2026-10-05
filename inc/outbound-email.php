<?php
/** Site-wide outbound email policy. */
defined( 'ABSPATH' ) || exit;

const MYOGENIX_OUTBOUND_BCC_EMAIL = 'adam@myogenix.com';

/**
 * Copy Adam on every message sent through WordPress, including WooCommerce.
 *
 * @param array $mail WordPress wp_mail() arguments.
 * @return array
 */
function myogenix_add_outbound_bcc( array $mail ): array {
	$headers = $mail['headers'] ?? array();
	$to      = $mail['to'] ?? array();
	$haystack = implode(
		"\n",
		array_merge(
			(array) $to,
			is_array( $headers ) ? $headers : preg_split( '/\r\n|\r|\n/', (string) $headers )
		)
	);

	// Avoid sending Adam a duplicate when he is already in To, Cc, or Bcc.
	if ( false !== stripos( $haystack, MYOGENIX_OUTBOUND_BCC_EMAIL ) ) {
		return $mail;
	}

	$bcc = 'Bcc: ' . MYOGENIX_OUTBOUND_BCC_EMAIL;
	if ( is_array( $headers ) ) {
		$headers[] = $bcc;
	} else {
		$headers = '' === trim( (string) $headers )
			? $bcc
			: rtrim( (string) $headers, "\r\n" ) . "\r\n" . $bcc;
	}

	$mail['headers'] = $headers;
	return $mail;
}

add_filter( 'wp_mail', 'myogenix_add_outbound_bcc' );
