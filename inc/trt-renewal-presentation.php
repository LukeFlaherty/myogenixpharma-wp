<?php
/** Patient communications for the TRT consent flow. */
defined( 'ABSPATH' ) || exit;

function myogenix_trt_email_shell( $title, $content, $preview = '' ) {
	$texture = set_url_scheme( get_stylesheet_directory_uri(), 'https' ) . '/assets/images/grunge-redesign/' . rawurlencode( 'grunge black section bg blank.png' );
	$logo = set_url_scheme( get_stylesheet_directory_uri(), 'https' ) . '/assets/images/trt-email-logo.png';
	return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $title ) . '</title></head>'
		. '<body style="margin:0;background:#090909;color:#fff;font-family:Arial,Helvetica,sans-serif"><div style="display:none;max-height:0;overflow:hidden;opacity:0">' . esc_html( $preview ) . '</div>'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#090909"><tr><td align="center" style="padding:24px 12px"><table role="presentation" width="560" cellpadding="0" cellspacing="0" bgcolor="#111111" style="width:100%;max-width:560px;border:1px solid #442020;background:#111 url(' . esc_url( $texture ) . ') center/cover">'
		. '<tr><td style="padding:28px 28px 22px;border-bottom:2px solid #a82323"><a href="' . esc_url( home_url( '/' ) ) . '"><img src="' . esc_url( $logo ) . '" width="184" alt="MYOGENIX PHARMA" style="display:block;width:184px;max-width:100%;height:auto;border:0;color:#fff"></a></td></tr>'
		. '<tr><td style="padding:30px 28px;font-size:15px;line-height:1.6;color:#e4e4e7"><p style="font-size:11px;font-weight:bold;letter-spacing:3px;color:#ef5350;margin:0 0 14px">YOUR CARE. YOUR CHOICE.</p><h1 style="font-family:Impact,Arial Narrow,Arial,sans-serif;font-size:44px;font-weight:900;text-transform:uppercase;line-height:1.05;letter-spacing:0.5px;margin:0 0 25px;color:#fff">' . esc_html( $title ) . '</h1>' . $content . '</td></tr>'
		. '<tr><td style="padding:20px 28px;background:#0b0b0b;border-top:1px solid #442020;font-size:12px;line-height:1.7;color:#b8b8bd"><a href="' . esc_url( home_url( '/trt-renewal/' ) ) . '" style="color:#fff;text-decoration:underline">How TRT ordering &amp; renewal work</a><br>Need help? Reply or <a style="color:#fff" href="mailto:support@myogenixpharma.com">contact your care team</a>.</td></tr></table></td></tr></table></body></html>';
}

function myogenix_trt_email_button( $url, $label, $secondary = false ) {
	return '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:12px 0;width:100%"><tr><td align="center" bgcolor="' . ( $secondary ? '#141414' : '#c8322e' ) . '" style="border:2px solid ' . ( $secondary ? '#929292' : '#e14b44' ) . '"><a href="' . esc_url( $url ) . '" style="display:block;padding:20px 12px;color:#fff;font-family:Impact,Arial Narrow,Arial,sans-serif;font-size:23px;line-height:1.2;letter-spacing:0.7px;font-weight:bold;text-transform:uppercase;text-decoration:none">' . esc_html( $label ) . '</a></td></tr></table>';
}

function myogenix_trt_consent_email_html( $sub, $cycle ) {
	$args = array( 'subscription_id' => $sub->get_id(), 'cycle_start' => $cycle, 'token' => myogenix_trt_consent_token( $sub->get_id(), $cycle ) );
	$date = wp_date( 'F j, Y', $cycle + MYOGENIX_TRT_CONSENT_TTL );
	$content = myogenix_trt_email_button( myogenix_trt_consent_url( $args + array( 'action' => 'continue' ) ), 'Continue my renewal →' )
		. myogenix_trt_email_button( myogenix_trt_consent_url( $args + array( 'action' => 'decline' ) ), 'Pause my renewal', true )
		. '<p style="margin:24px 0 12px"><strong style="color:#fff">No renewal charge when you confirm.</strong><br>Continue → follow-up labs → provider review → payment after approval.</p>'
		. '<p style="font-size:13px;color:#b8b8bd;margin:0">Confirm your choice on the next page by <strong style="color:#fff">' . esc_html( $date ) . '</strong>. No response? We’ll pause your renewal and follow up.</p><p style="font-size:11px;color:#a1a1aa;margin:16px 0 0">This link is just for you. Please don’t forward it.</p>';
	if ( myogenix_trt_is_qa( $sub ) ) { $content .= '<p style="color:#f6cf82;font-size:11px;margin:14px 0 0"><strong>TEST PREVIEW</strong> · Fake subscription. No live card will be charged.</p>'; }
	return myogenix_trt_email_shell( 'Your TRT. Your next step.', $content, 'Continue or pause your renewal. Confirming does not charge your card.' );
}

function myogenix_trt_mail( $sub, $subject, $body ) {
	return wp_mail( $sub->get_billing_email(), ( myogenix_trt_is_qa( $sub ) ? '[TEST] ' : '' ) . $subject, $body, array( 'Content-Type: text/html; charset=UTF-8', 'From: Myogenix Pharma <support@myogenixpharma.com>', 'Reply-To: Myogenix Pharma <support@myogenixpharma.com>' ) );
}
function myogenix_trt_send_consent_email( WC_Subscription $sub, $cycle ) {
	return myogenix_trt_mail( $sub, 'Your renewal is ready to review', myogenix_trt_consent_email_html( $sub, $cycle ) );
}
function myogenix_trt_send_response_email( $sub, $action ) {
	$title = 'continue' === $action ? 'Your renewal is underway.' : 'Your renewal is paused.';
	$content = 'continue' === $action
		? '<p>Thanks for confirming you’d like to continue. We’ve requested your follow-up labs and prepared renewal order <strong>#' . absint( $sub->get_meta( '_trt_pending_renewal_order' ) ) . '</strong> for review.</p><p><strong>No renewal payment has been taken.</strong> Your provider will review your labs before a renewal payment is processed.</p><p>Watch for “Next Step: Complete Your Lab Work” with your lab form and scheduling links. Need help or can’t find it? Reply to this email.</p>'
		: '<p>We’ve recorded your choice and placed your subscription on hold. <strong>No renewal payment has been taken.</strong></p><p>Our team will follow up with you. If you change your mind, reply to this email and we’ll help you with the next steps.</p>';
	return myogenix_trt_mail( $sub, 'continue' === $action ? 'We’ve received your renewal request' : 'Your renewal has been paused', myogenix_trt_email_shell( $title, '<p>Hi ' . esc_html( $sub->get_billing_first_name() ?: 'there' ) . ',</p>' . $content ) );
}

function myogenix_trt_staff_notice( $sub, $subject, $message ) {
	$to = myogenix_trt_is_qa( $sub ) ? $sub->get_billing_email() : ( 'Week 9 renewal review' === $subject ? MYOGENIX_TRT_ADMIN_EMAILS : array_merge( MYOGENIX_TRT_ADMIN_EMAILS, array( 'support@myogenixpharma.com' ) ) );
	return wp_mail( $to, ( myogenix_trt_is_qa( $sub ) ? '[TEST STAFF NOTICE] ' : 'TRT: ' ) . $subject . ' — #' . $sub->get_id(), $message . "\n\nSubscription: " . $sub->get_edit_order_url() . "\nRenewal order: " . ( $sub->get_meta( '_trt_pending_renewal_order' ) ?: 'Not created' ), array( 'From: Myogenix Pharma <support@myogenixpharma.com>' ) );
}

function myogenix_trt_page_html( $title, $body, $status = 200 ) {
	// Reuse the real navigation/footer without wp_head/wp_footer: signed links
	// must not load analytics, chat, or other third-party tracking scripts.
	ob_start();
	get_template_part( 'template-parts/site-header' );
	$header = ob_get_clean();
	ob_start();
	get_template_part( 'template-parts/site-footer' );
	$footer = ob_get_clean();
	$base = set_url_scheme( get_stylesheet_directory_uri(), 'https' );
	return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>' . esc_html( $title ) . ' | Myogenix Pharma</title>'
		. '<link rel="stylesheet" href="' . esc_url( $base . '/assets/css/home.css?ver=1.5.0' ) . '"><link rel="stylesheet" href="' . esc_url( $base . '/assets/css/grunge-redesign.css?ver=0.3.9' ) . '"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&amp;family=Oswald:wght@700&amp;family=Poppins:wght@400;500;600;700;800&amp;display=swap"><link rel="stylesheet" href="' . esc_url( $base . '/assets/css/trt-renewal.css?ver=1.0.1' ) . '"></head><body class="grunge-redesign-page trt-consent-page">'
		. $header . '<main id="content" class="trt-renewal trt-consent"><div class="trt-consent__layout"><section class="trt-consent__intro"><p class="grunge-kicker">TRT renewal check-in</p><h1><span class="grunge-word grunge-word--white">Your care.</span><span class="grunge-word grunge-word--red">Your choice.</span></h1><p>A clear next step.<br>A care team in your corner.</p><a href="' . esc_url( home_url( '/trt-renewal/' ) ) . '">How your renewal works →</a></section><section class="trt-consent__card" aria-labelledby="renewal-title"><h2 id="renewal-title">' . esc_html( $title ) . '</h2>' . $body
		. '<div class="support">Need a hand? <a href="mailto:support@myogenixpharma.com">Email your care team</a> or <a href="' . esc_url( home_url( '/reach-a-concierge/' ) ) . '">reach a concierge</a>.</div></section></div></main>' . $footer . '<script src="' . esc_url( $base . '/assets/js/home.js?ver=1.6.2' ) . '" defer></script></body></html>';
}
function myogenix_trt_html_response( $body, $status = 200, $title = 'Your renewal' ) {
	nocache_headers();
	status_header( $status );
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Referrer-Policy: no-referrer' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( "Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'" );
	echo myogenix_trt_page_html( $title, $body, $status ); // Generated HTML; dynamic values are escaped at construction.
	exit;
}
function myogenix_trt_render_error( $error ) {
	$titles = array( 'expired' => 'Let’s get you the next step.', 'resolved' => 'You’re all set.', 'busy' => 'We’re working on it.' );
	myogenix_trt_html_response( '<p>' . esc_html( $error->get_error_message() ) . '</p>', $error->get_error_data()['status'] ?? 503, $titles[ $error->get_error_code() ] ?? 'We’re here to help.' );
}

add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'myogenix_trt_consent' ) ) { return; }
	if ( ! in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'HEAD', 'POST' ), true ) ) { myogenix_trt_html_response( '<p>This request is not supported.</p>', 405 ); }
	$params = wp_unslash( 'POST' === $_SERVER['REQUEST_METHOD'] ? $_POST : $_GET );
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
		$result = myogenix_trt_process_consent( $params );
		if ( is_wp_error( $result ) ) { myogenix_trt_render_error( $result ); }
		$continued = 'continue' === $result['action'];
		myogenix_trt_html_response( $continued ? '<p>We’ve requested your follow-up labs and prepared your renewal for provider review.</p><div class="steps"><strong>No renewal payment has been taken.</strong><p>Watch for “Next Step: Complete Your Lab Work” in your inbox. Download your lab form and follow the scheduling instructions. Payment is processed only after provider approval.</p></div><p>Look for a confirmation in your inbox.</p>' : '<p>Your subscription is now on hold. No renewal payment has been taken.</p><p>Our team will follow up. If you change your mind, contact us and we’ll help you with the next steps.</p>', 200, $continued ? 'Your renewal is underway.' : 'Your renewal is paused.' );
	}
	$result = myogenix_trt_validate_consent_request( $params );
	if ( is_wp_error( $result ) ) { myogenix_trt_render_error( $result ); }
	list( $sub, $cycle, $action ) = $result;
	$continued = 'continue' === $action;
	$body = myogenix_trt_is_qa( $sub ) ? '<p class="test"><strong>TEST PREVIEW</strong> · Fake subscription. No live card will be charged.</p>' : '';
	$body .= $continued ? '<p>You’re choosing to continue your testosterone treatment. Here’s what happens next:</p><div class="steps"><ol><li>We request your follow-up labs.</li><li>Your provider reviews your labs and renewal.</li><li>Payment is processed only after provider approval.</li></ol></div><p><strong>No renewal payment is taken when you confirm.</strong></p>' : '<p>We’ll place your subscription on hold and let our team know you’d like to pause your renewal.</p><div class="steps"><strong>No renewal payment will be taken.</strong><p>Our team will follow up with you before any further steps.</p></div>';
	$body .= '<form method="post" action="' . esc_url( myogenix_trt_consent_url( array() ) ) . '">';
	foreach ( array( 'subscription_id', 'cycle_start', 'token', 'action' ) as $key ) { $body .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $params[ $key ] ) . '">'; }
	$body .= '<button type="submit">' . ( $continued ? 'Confirm & continue' : 'Confirm pause' ) . '</button></form>';
	$params['action'] = $continued ? 'decline' : 'continue';
	$body .= '<a class="button secondary" href="' . esc_url( myogenix_trt_consent_url( array_intersect_key( $params, array_flip( array( 'subscription_id', 'cycle_start', 'token', 'action' ) ) ) ) ) . '">' . ( $continued ? 'I’d like to pause instead' : 'I’d like to continue instead' ) . '</a><p class="muted">Subscription #' . $sub->get_id() . ' · Please respond by ' . esc_html( wp_date( 'F j, Y', $cycle + MYOGENIX_TRT_CONSENT_TTL ) ) . '.</p>';
	myogenix_trt_html_response( $body, 200, $continued ? 'Continue your treatment.' : 'Pause your renewal?' );
} );
