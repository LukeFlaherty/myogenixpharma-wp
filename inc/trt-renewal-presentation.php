<?php
/** Patient communications for the TRT consent flow. */
defined( 'ABSPATH' ) || exit;

function myogenix_trt_email_shell( $title, $content, $preview = '' ) {
	return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $title ) . '</title></head>'
		. '<body style="margin:0;background:#f2f2f2;color:#202020;font-family:Arial,Helvetica,sans-serif"><div style="display:none;max-height:0;overflow:hidden;opacity:0">' . esc_html( $preview ) . '</div>'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f2f2"><tr><td align="center" style="padding:32px 16px"><table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;background:#fff;border:1px solid #dedede">'
		. '<tr><td style="background:#111;padding:27px 32px;border-bottom:4px solid #dc2626"><a href="' . esc_url( home_url( '/' ) ) . '" style="color:#fff;text-decoration:none;font-size:25px;font-weight:800;letter-spacing:2px">MYOGENIX<span style="color:#ef4444">.</span></a><div style="color:#c7c7c7;font-size:10px;letter-spacing:4px;margin-top:5px">PHARMA</div></td></tr>'
		. '<tr><td style="padding:32px;font-size:16px;line-height:1.65"><p style="font-size:11px;font-weight:bold;letter-spacing:2px;color:#b91c1c;margin:0 0 12px">YOUR CARE. YOUR CHOICE.</p><h1 style="font-size:29px;line-height:1.2;letter-spacing:-0.5px;margin:0 0 24px;color:#111">' . esc_html( $title ) . '</h1>' . $content . '</td></tr>'
		. '<tr><td style="padding:23px 32px;background:#fafafa;border-top:1px solid #e5e5e5;font-size:13px;line-height:1.6;color:#666">Here to help.<br>Reply to this email or contact <a style="color:#a61b1b" href="mailto:support@myogenixpharma.com">support@myogenixpharma.com</a>.<br><span style="font-size:11px">Myogenix Pharma · Your renewal support team</span></td></tr></table></td></tr></table></body></html>';
}

function myogenix_trt_email_button( $url, $label, $secondary = false ) {
	return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:14px 0;width:100%"><tr><td align="center" style="border-radius:4px;background:' . ( $secondary ? '#f4f4f4' : '#b91c1c' ) . ';border:1px solid ' . ( $secondary ? '#ddd' : '#b91c1c' ) . '"><a href="' . esc_url( $url ) . '" style="display:block;padding:15px 20px;color:' . ( $secondary ? '#252525' : '#fff' ) . ';font-size:15px;font-weight:bold;text-decoration:none">' . esc_html( $label ) . '</a></td></tr></table>';
}

function myogenix_trt_consent_email_html( $sub, $cycle ) {
	$args = array( 'subscription_id' => $sub->get_id(), 'cycle_start' => $cycle, 'token' => myogenix_trt_consent_token( $sub->get_id(), $cycle ) );
	$date = wp_date( 'F j, Y', $cycle + MYOGENIX_TRT_CONSENT_TTL );
	$content = '<p>Hi ' . esc_html( $sub->get_billing_first_name() ?: 'there' ) . ',</p><p>You’re approaching the next renewal of your testosterone treatment. It’s time to let us know how you’d like to proceed.</p>'
		. '<div style="background:#f7f7f7;border-left:3px solid #dc2626;padding:16px 20px;margin:24px 0"><strong>What happens if you continue?</strong><br>We’ll request your follow-up labs and prepare your renewal for provider review. <strong>No renewal payment is taken when you confirm.</strong> Payment is processed only after provider approval.</div>'
		. myogenix_trt_email_button( myogenix_trt_consent_url( $args + array( 'action' => 'continue' ) ), 'Review & continue treatment' )
		. myogenix_trt_email_button( myogenix_trt_consent_url( $args + array( 'action' => 'decline' ) ), 'Pause my renewal', true )
		. '<p style="font-size:13px;color:#666">You’ll confirm your choice on the next page. Please respond by <strong>' . esc_html( $date ) . '</strong>. If you don’t respond, your renewal will pause for our team to follow up.</p><p style="font-size:13px;color:#666">This link is personal to you. Please don’t forward it.</p><p>With you at every step,<br><strong>The Myogenix Pharma team</strong></p>';
	if ( myogenix_trt_is_qa( $sub ) ) { $content = '<p style="padding:10px;background:#fff1d6;font-size:12px"><strong>TEST PREVIEW</strong> · Fake subscription. No live card will be charged.</p>' . $content; }
	return myogenix_trt_email_shell( 'Ready for your next step?', $content, 'Review your renewal options. No renewal payment is taken when you confirm.' );
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
		? '<p>Thanks for confirming you’d like to continue. We’ve requested your follow-up labs and prepared renewal order <strong>#' . absint( $sub->get_meta( '_trt_pending_renewal_order' ) ) . '</strong> for review.</p><p><strong>No renewal payment has been taken.</strong> Your provider will review your labs before a renewal payment is processed.</p><p>Our team will help with your lab requisition and next steps. If you need assistance, reply to this email.</p>'
		: '<p>We’ve recorded your choice and placed your subscription on hold. <strong>No renewal payment has been taken.</strong></p><p>Our team will follow up with you. If you change your mind, reply to this email and we’ll help you with the next steps.</p>';
	return myogenix_trt_mail( $sub, 'continue' === $action ? 'We’ve received your renewal request' : 'Your renewal has been paused', myogenix_trt_email_shell( $title, '<p>Hi ' . esc_html( $sub->get_billing_first_name() ?: 'there' ) . ',</p>' . $content ) );
}

function myogenix_trt_staff_notice( $sub, $subject, $message ) {
	$to = myogenix_trt_is_qa( $sub ) ? $sub->get_billing_email() : ( 'Week 9 renewal review' === $subject ? MYOGENIX_TRT_ADMIN_EMAILS : array_merge( MYOGENIX_TRT_ADMIN_EMAILS, array( 'support@myogenixpharma.com' ) ) );
	return wp_mail( $to, ( myogenix_trt_is_qa( $sub ) ? '[TEST STAFF NOTICE] ' : 'TRT: ' ) . $subject . ' — #' . $sub->get_id(), $message . "\n\nSubscription: " . $sub->get_edit_order_url() . "\nRenewal order: " . ( $sub->get_meta( '_trt_pending_renewal_order' ) ?: 'Not created' ), array( 'From: Myogenix Pharma <support@myogenixpharma.com>' ) );
}

function myogenix_trt_page_html( $title, $body, $status = 200 ) {
	return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>' . esc_html( $title ) . ' | Myogenix Pharma</title><style>'
		. '*{box-sizing:border-box}body{margin:0;background:#111;color:#1b1b1b;font-family:Arial,Helvetica,sans-serif;line-height:1.65}header{padding:27px 24px;border-bottom:1px solid #333}.brand{font-weight:900;letter-spacing:2px;font-size:24px;color:#fff;text-decoration:none}.brand b{color:#ef4444}.brand small{display:block;font-size:9px;letter-spacing:5px;color:#bdbdbd}main{max-width:640px;margin:48px auto;padding:36px;background:#fff;border-top:4px solid #dc2626;border-radius:5px}.kicker{font-size:11px;letter-spacing:2px;font-weight:bold;color:#b91c1c}h1{font-size:36px;line-height:1.15;letter-spacing:-1px;margin:14px 0 24px}p{margin:18px 0}.steps{background:#f5f5f5;padding:20px 24px;margin:24px 0;border-left:3px solid #dc2626}.steps ol{padding-left:20px;margin:0}.steps li+li{margin-top:10px}button,.button{width:100%;display:block;background:#b91c1c;color:#fff;padding:16px 20px;border:0;border-radius:4px;font-size:16px;font-weight:bold;cursor:pointer;text-align:center;text-decoration:none}button:hover,.button:hover{background:#991b1b}button:focus-visible,a:focus-visible{outline:3px solid #2563eb;outline-offset:4px}.secondary{background:#eee;color:#222;margin-top:12px}.secondary:hover{background:#ddd}.muted{color:#666;font-size:13px}.support{border-top:1px solid #ddd;margin-top:28px;padding-top:20px;font-size:13px;color:#666}a{color:#a61b1b}.test{background:#fff1d6;padding:10px 15px;font-size:12px}footer{text-align:center;font-size:12px;color:#aaa;margin:28px 16px 40px}@media(max-width:680px){header{padding:22px}main{margin:24px 16px;padding:26px 22px}h1{font-size:30px}.steps{padding:16px 18px}.support a{overflow-wrap:anywhere}}'
		. '</style></head><body><header><a class="brand" href="' . esc_url( home_url( '/' ) ) . '">MYOGENIX<b>.</b><small>PHARMA</small></a></header><main><div class="kicker">YOUR CARE. YOUR CHOICE.</div><h1>' . esc_html( $title ) . '</h1>' . $body . '<div class="support">We’re here to help.<br><a href="mailto:support@myogenixpharma.com">support@myogenixpharma.com</a></div></main><footer>Myogenix Pharma · Your renewal support team</footer></body></html>';
}
function myogenix_trt_html_response( $body, $status = 200, $title = 'Your renewal' ) {
	nocache_headers();
	status_header( $status );
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Referrer-Policy: no-referrer' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'" );
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
		myogenix_trt_html_response( $continued ? '<p>We’ve requested your follow-up labs and prepared your renewal for provider review.</p><div class="steps"><strong>No renewal payment has been taken.</strong><p>Our team will help with your lab requisition and next steps. Payment is processed only after provider approval.</p></div><p>Look for a confirmation in your inbox.</p>' : '<p>Your subscription is now on hold. No renewal payment has been taken.</p><p>Our team will follow up. If you change your mind, contact us and we’ll help you with the next steps.</p>', 200, $continued ? 'Your renewal is underway.' : 'Your renewal is paused.' );
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
