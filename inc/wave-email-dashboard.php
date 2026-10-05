<?php
/** Wave Consulting outbound customer communication timeline. */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	$GLOBALS['wave_email_hook'] = add_submenu_page( 'wave-trt', 'Outbound communications', 'Outbound Email', 'manage_woocommerce', 'wave-email', 'wave_email_render' );
}, 18 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( $hook !== ( $GLOBALS['wave_email_hook'] ?? '' ) ) { return; }
	wp_enqueue_style( 'wave-trt', get_stylesheet_directory_uri() . '/assets/css/wave-trt-dashboard.css', array(), '1.1.1' );
	wp_enqueue_style( 'wave-email', get_stylesheet_directory_uri() . '/assets/css/wave-email-dashboard.css', array( 'wave-trt' ), '1.0.0' );
} );

add_action( 'admin_init', function () {
	if ( isset( $_GET['page'] ) && 'wave-email' === $_GET['page'] ) { nocache_headers(); }
} );

function wave_email_value( $key, $default = '' ) {
	return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default;
}

function wave_email_extract_addresses( $value ) {
	// WP Mail Logging stores multi-recipient separators as both real and literal newlines.
	$value = str_replace( array( '\\n', '\\r' ), "\n", (string) $value );
	preg_match_all( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches );
	return array_values( array_unique( array_map( 'strtolower', $matches[0] ?? array() ) ) );
}

function wave_email_internal_addresses() {
	$addresses = array( 'support@myogenixpharma.com', 'customersupport@myogenixpharma.com', 'adam@myogenixpharma.com', 'adam@myogenix.com' );
	$admin = sanitize_email( get_option( 'admin_email' ) );
	if ( $admin ) { $addresses[] = strtolower( $admin ); }
	return array_values( array_unique( apply_filters( 'wave_email_internal_addresses', $addresses ) ) );
}

function wave_email_is_customer_recipient( $receiver ) {
	$addresses = wave_email_extract_addresses( $receiver );
	return (bool) array_diff( $addresses, wave_email_internal_addresses() );
}

function wave_email_redact_message( $message, $limit = 4000 ) {
	$text = html_entity_decode( wp_strip_all_tags( (string) $message, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = preg_replace( '~https?://[^\s<>"\']+~i', '[secure link hidden]', $text );
	$text = preg_replace( '/\b(token|key|nonce|signature)\s*[:=]\s*[^\s&]+/i', '$1=[hidden]', $text );
	$text = preg_replace( '/[\t ]+/', ' ', $text );
	$text = preg_replace( '/\R{3,}/', "\n\n", $text );
	return mb_substr( trim( $text ), 0, $limit );
}

function wave_email_source( $subject, $message = '' ) {
	$value = strtolower( (string) $subject . ' ' . (string) $message );
	if ( preg_match( '/\btrt\b|renewal is ready|renewal has been paused|renewal request/', $value ) ) { return 'TRT renewal'; }
	if ( preg_match( '/woocommerce|your order|new order|subscription|account has been created|reset your password|payment/', $value ) ) { return 'WooCommerce'; }
	return 'WordPress';
}

function wave_email_range() {
	$timezone = wp_timezone();
	$default_to = wp_date( 'Y-m-d' );
	$default_from = wp_date( 'Y-m-d', time() - 364 * DAY_IN_SECONDS );
	$from_value = wave_email_value( 'from', $default_from );
	$to_value = wave_email_value( 'to', $default_to );
	$from = DateTimeImmutable::createFromFormat( '!Y-m-d', $from_value, $timezone );
	$to = DateTimeImmutable::createFromFormat( '!Y-m-d', $to_value, $timezone );
	if ( ! $from || $from->format( 'Y-m-d' ) !== $from_value ) { $from = new DateTimeImmutable( $default_from, $timezone ); }
	if ( ! $to || $to->format( 'Y-m-d' ) !== $to_value ) { $to = new DateTimeImmutable( $default_to, $timezone ); }
	if ( $from > $to ) { $swap = $from; $from = $to; $to = $swap; }
	if ( $from->diff( $to )->days > 1826 ) { $from = $to->modify( '-1826 days' ); }
	return array( $from, $to, $from->getTimestamp(), $to->modify( '+1 day' )->getTimestamp() );
}

/** Resolve an email, order number, or registered customer name to recipient emails. */
function wave_email_customer_addresses( $query ) {
	$query = trim( (string) $query );
	if ( ! $query ) { return array(); }
	if ( is_email( $query ) ) { return array( strtolower( $query ) ); }
	$emails = array();
	if ( ctype_digit( ltrim( $query, '#' ) ) && function_exists( 'wc_get_order' ) ) {
		$order = wc_get_order( absint( ltrim( $query, '#' ) ) );
		if ( $order && $order->get_billing_email() ) { $emails[] = strtolower( $order->get_billing_email() ); }
	}
	foreach ( get_users( array( 'search' => '*' . $query . '*', 'search_columns' => array( 'user_login', 'user_email', 'display_name' ), 'number' => 20, 'fields' => array( 'user_email' ) ) ) as $user ) {
		if ( ! empty( $user->user_email ) ) { $emails[] = strtolower( $user->user_email ); }
	}
	if ( function_exists( 'wc_get_orders' ) ) {
		$parts = preg_split( '/\s+/', $query, 2, PREG_SPLIT_NO_EMPTY );
		$queries = count( $parts ) > 1
			? array( array( 'billing_first_name' => $parts[0], 'billing_last_name' => $parts[1] ) )
			: array( array( 'billing_first_name' => $query ), array( 'billing_last_name' => $query ) );
		foreach ( $queries as $address_query ) {
			$orders = wc_get_orders( array_merge( $address_query, array( 'limit' => 20, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects' ) ) );
			foreach ( $orders as $order ) { if ( $order->get_billing_email() ) { $emails[] = strtolower( $order->get_billing_email() ); } }
		}
	}
	return array_values( array_unique( array_filter( $emails ) ) );
}

function wave_email_log_timestamp( $value ) {
	$date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $value, wp_timezone() );
	return $date ? $date->getTimestamp() : 0;
}

function wave_email_load_wp_entries( $start, $end, $search, $customer_emails ) {
	global $wpdb;
	$table = $wpdb->prefix . 'wpml_mails';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { return array( array(), false ); }
	$where = array( 'timestamp >= %s', 'timestamp < %s' );
	$args = array( wp_date( 'Y-m-d H:i:s', $start ), wp_date( 'Y-m-d H:i:s', $end ) );
	if ( $search ) {
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		$where[] = '(receiver LIKE %s OR subject LIKE %s OR message LIKE %s)';
		array_push( $args, $like, $like, $like );
	}
	if ( $customer_emails ) {
		$email_clauses = array();
		foreach ( $customer_emails as $email ) { $email_clauses[] = 'receiver LIKE %s'; $args[] = '%' . $wpdb->esc_like( $email ) . '%'; }
		$where[] = '(' . implode( ' OR ', $email_clauses ) . ')';
	}
	$sql = 'SELECT mail_id, timestamp, receiver, subject, message, headers, error FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY timestamp DESC, mail_id DESC LIMIT 5001';
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$limited = count( $rows ) > 5000;
	$entries = array();
	foreach ( array_slice( $rows, 0, 5000 ) as $row ) {
		$addresses = wave_email_extract_addresses( $row['receiver'] );
		$entries[] = array(
			'id' => 'wp-' . absint( $row['mail_id'] ), 'timestamp' => wave_email_log_timestamp( $row['timestamp'] ), 'source' => wave_email_source( $row['subject'], $row['message'] ),
			'status' => empty( $row['error'] ) ? 'accepted' : 'failed', 'audience' => wave_email_is_customer_recipient( $row['receiver'] ) ? 'customer' : 'internal',
			'recipients' => $addresses, 'subject' => (string) $row['subject'], 'message' => wave_email_redact_message( $row['message'] ),
			'detail' => empty( $row['error'] ) ? 'Accepted by WordPress mail transport; inbox delivery is not verified.' : sanitize_text_field( $row['error'] ), 'order_id' => 0,
		);
	}
	return array( $entries, $limited );
}

function wave_email_prescribery_meta_rows() {
	global $wpdb;
	$rows = array();
	$hpos = $wpdb->prefix . 'wc_orders_meta';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos ) ) === $hpos ) {
		$rows = array_merge( $rows, $wpdb->get_results( $wpdb->prepare( "SELECT order_id AS record_id, meta_value FROM {$hpos} WHERE meta_key = %s", WAVE_PRESCRIBERY_EVENT_META ), ARRAY_A ) );
	}
	$legacy = $wpdb->postmeta;
	$rows = array_merge( $rows, $wpdb->get_results( $wpdb->prepare( "SELECT post_id AS record_id, meta_value FROM {$legacy} WHERE meta_key = %s", WAVE_PRESCRIBERY_EVENT_META ), ARRAY_A ) );
	return $rows;
}

function wave_email_load_prescribery_entries( $start, $end, $customer_emails ) {
	if ( ! defined( 'WAVE_PRESCRIBERY_EVENT_META' ) || ! function_exists( 'wc_get_order' ) ) { return array(); }
	$entries = array(); $seen = array();
	foreach ( wave_email_prescribery_meta_rows() as $row ) {
		$record = wc_get_order( absint( $row['record_id'] ) );
		if ( ! $record || ! $record->get_billing_email() ) { continue; }
		$email = strtolower( $record->get_billing_email() );
		if ( $customer_emails && ! in_array( $email, $customer_emails, true ) ) { continue; }
		$events = maybe_unserialize( $row['meta_value'] );
		foreach ( is_array( $events ) ? $events : array() as $event ) {
			$message = trim( (string) ( $event['message_to_patient'] ?? '' ) );
			$timestamp = absint( $event['occurred_at'] ?? 0 );
			if ( ! $message || $timestamp < $start || $timestamp >= $end ) { continue; }
			$key = absint( $event['appointment_id'] ?? 0 ) . '|' . hash( 'sha256', $email . '|' . $message );
			if ( isset( $seen[ $key ] ) ) { continue; }
			$seen[ $key ] = true;
			$entries[] = array(
				'id' => 'pre-' . hash( 'sha256', $key ), 'timestamp' => $timestamp, 'source' => 'Prescribery', 'status' => 'recorded', 'audience' => 'customer',
				'recipients' => array( $email ), 'subject' => 'Appointment message to patient', 'message' => wave_email_redact_message( $message ),
				'detail' => 'Message text was returned by Prescribery with the appointment. The API does not expose its email/SMS delivery status.', 'order_id' => $record->get_id(),
			);
		}
	}
	return $entries;
}

function wave_email_customer_context( $email ) {
	static $cache = array();
	$email = strtolower( (string) $email );
	if ( isset( $cache[ $email ] ) ) { return $cache[ $email ]; }
	$context = array( 'name' => '', 'order_id' => 0, 'url' => '' );
	$user = get_user_by( 'email', $email );
	if ( $user ) { $context['name'] = $user->display_name; }
	if ( function_exists( 'wc_get_orders' ) ) {
		$orders = wc_get_orders( array( 'billing_email' => $email, 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects' ) );
		if ( $orders ) {
			$order = reset( $orders ); $context['order_id'] = $order->get_id(); $context['url'] = $order->get_edit_order_url();
			$name = trim( $order->get_formatted_billing_full_name() ); if ( $name ) { $context['name'] = $name; }
		}
	}
	$cache[ $email ] = $context;
	return $context;
}

function wave_email_page_url( $args = array() ) { return add_query_arg( array_merge( array( 'page' => 'wave-email' ), $args ), admin_url( 'admin.php' ) ); }

function wave_email_render() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'You do not have permission to view customer communications.', '', array( 'response' => 403 ) ); }
	list( $from, $to, $start, $end ) = wave_email_range();
	$customer = wave_email_value( 'customer' ); $search = wave_email_value( 'search' );
	$source = wave_email_value( 'source', 'all' ); $status = wave_email_value( 'status', 'all' ); $audience = wave_email_value( 'audience', 'customer' );
	$page = max( 1, absint( wave_email_value( 'paged', '1' ) ) ); $per_page = 50;
	$customer_emails = wave_email_customer_addresses( $customer );
	if ( $customer && ! $customer_emails ) {
		$entries = array(); $limited = false;
	} else {
		list( $entries, $limited ) = wave_email_load_wp_entries( $start, $end, $search, $customer_emails );
		if ( 'all' === $source || 'prescribery' === $source ) { $entries = array_merge( $entries, wave_email_load_prescribery_entries( $start, $end, $customer_emails ) ); }
	}
	$entries = array_values( array_filter( $entries, function ( $entry ) use ( $source, $status, $audience, $search ) {
		if ( 'all' !== $source && ( 'wordpress' === $source ? 'Prescribery' === $entry['source'] : 'Prescribery' !== $entry['source'] ) ) { return false; }
		if ( 'all' !== $status && $status !== $entry['status'] ) { return false; }
		if ( 'all' !== $audience && $audience !== $entry['audience'] ) { return false; }
		if ( $search && 'Prescribery' === $entry['source'] && false === stripos( implode( ' ', $entry['recipients'] ) . ' ' . $entry['subject'] . ' ' . $entry['message'], $search ) ) { return false; }
		return true;
	} ) );
	usort( $entries, function ( $a, $b ) { return $b['timestamp'] <=> $a['timestamp']; } );
	$counts = array( 'all' => count( $entries ), 'accepted' => 0, 'failed' => 0, 'prescribery' => 0 );
	foreach ( $entries as $entry ) { if ( isset( $counts[ $entry['status'] ] ) ) { $counts[ $entry['status'] ]++; } if ( 'Prescribery' === $entry['source'] ) { $counts['prescribery']++; } }
	$total_pages = max( 1, (int) ceil( count( $entries ) / $per_page ) ); $page = min( $page, $total_pages );
	$visible = array_slice( $entries, ( $page - 1 ) * $per_page, $per_page );
	?>
	<div class="wrap wave-trt wave-email">
		<div class="wave-brand">WAVE CONSULTING <span>Customer communications</span></div>
		<header class="wave-heading"><div><p class="wave-eyebrow">MYOGENIX PHARMA</p><h1>Outbound communication timeline</h1><p>One searchable history of customer messages sent from WordPress and available Prescribery appointment messages.</p></div><a class="button" href="<?php echo esc_url( wave_email_page_url() ); ?>">Refresh records</a></header>
		<div class="wave-notice"><strong>What the statuses mean</strong><p><b>Accepted by WordPress</b> means the site handed the message to its mail transport; it does not prove inbox delivery. <b>Prescribery recorded</b> means its API returned message text on an appointment; Prescribery does not expose sent-email history or delivery status through the available API.</p></div>
		<?php if ( $customer && ! $customer_emails ) : ?><div class="notice notice-warning inline"><p>No exact email, order, or registered customer matched “<?php echo esc_html( $customer ); ?>”. General text search is still available below.</p></div><?php endif; ?>
		<?php if ( $limited ) : ?><div class="notice notice-warning inline"><p>The selected range contains more than 5,000 WordPress mail records. Narrow the dates or customer filter for a complete result.</p></div><?php endif; ?>
		<div class="wave-email-stats"><div><span>Matching communications</span><strong><?php echo esc_html( $counts['all'] ); ?></strong></div><div><span>Accepted by WordPress</span><strong><?php echo esc_html( $counts['accepted'] ); ?></strong></div><div><span>WordPress failures</span><strong><?php echo esc_html( $counts['failed'] ); ?></strong></div><div><span>Prescribery messages</span><strong><?php echo esc_html( $counts['prescribery'] ); ?></strong></div></div>
		<form class="wave-email-filters" method="get">
			<input type="hidden" name="page" value="wave-email">
			<label>From<input type="date" name="from" value="<?php echo esc_attr( $from->format( 'Y-m-d' ) ); ?>"></label>
			<label>To<input type="date" name="to" value="<?php echo esc_attr( $to->format( 'Y-m-d' ) ); ?>"></label>
			<label>Customer<input type="search" name="customer" value="<?php echo esc_attr( $customer ); ?>" placeholder="Email, name or order #"></label>
			<label>Message search<input type="search" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="Recipient, subject or message"></label>
			<label>Source<select name="source"><option value="all">All sources</option><option value="wordpress" <?php selected( $source, 'wordpress' ); ?>>WordPress / WooCommerce</option><option value="prescribery" <?php selected( $source, 'prescribery' ); ?>>Prescribery</option></select></label>
			<label>Status<select name="status"><option value="all">All statuses</option><option value="accepted" <?php selected( $status, 'accepted' ); ?>>Accepted by WordPress</option><option value="failed" <?php selected( $status, 'failed' ); ?>>WordPress failed</option><option value="recorded" <?php selected( $status, 'recorded' ); ?>>Prescribery recorded</option></select></label>
			<label>Audience<select name="audience"><option value="customer" <?php selected( $audience, 'customer' ); ?>>Customers</option><option value="all" <?php selected( $audience, 'all' ); ?>>All recipients</option><option value="internal" <?php selected( $audience, 'internal' ); ?>>Internal only</option></select></label>
			<div class="wave-email-filter-actions"><button class="button button-primary">Apply filters</button><a class="button" href="<?php echo esc_url( wave_email_page_url() ); ?>">Clear</a></div>
		</form>
		<p class="wave-fresh"><?php echo esc_html( count( $entries ) ); ?> matching records · <?php echo esc_html( $from->format( 'M j, Y' ) ); ?> through <?php echo esc_html( $to->format( 'M j, Y' ) ); ?> · newest first</p>
		<div class="wave-email-timeline">
		<?php foreach ( $visible as $entry ) :
			$recipient = $entry['recipients'][0] ?? 'Recipient unavailable'; $context = wave_email_customer_context( $recipient );
			$order_url = $entry['order_id'] ? ( ( $order = wc_get_order( $entry['order_id'] ) ) ? $order->get_edit_order_url() : '' ) : $context['url'];
			$tone = 'failed' === $entry['status'] ? 'red' : ( 'recorded' === $entry['status'] ? 'amber' : 'blue' );
			$status_label = array( 'accepted' => 'Accepted by WordPress', 'failed' => 'Send failed', 'recorded' => 'Prescribery recorded' )[ $entry['status'] ];
			?>
			<article class="wave-email-entry">
				<div class="wave-email-time"><time datetime="<?php echo esc_attr( wp_date( DATE_ATOM, $entry['timestamp'] ) ); ?>"><?php echo esc_html( wp_date( 'M j, Y', $entry['timestamp'] ) ); ?><span><?php echo esc_html( wp_date( 'g:i a T', $entry['timestamp'] ) ); ?></span></time></div>
				<div class="wave-email-card"><div class="wave-email-meta"><span class="wave-badge <?php echo esc_attr( $tone ); ?>"><?php echo esc_html( $status_label ); ?></span><span class="wave-email-source"><?php echo esc_html( $entry['source'] ); ?></span></div>
					<h2><?php echo esc_html( $entry['subject'] ?: '(No subject)' ); ?></h2>
					<p class="wave-email-recipient"><strong><?php echo esc_html( $context['name'] ?: $recipient ); ?></strong><?php if ( $context['name'] ) : ?> · <?php echo esc_html( $recipient ); ?><?php endif; ?><?php if ( count( $entry['recipients'] ) > 1 ) : ?> · +<?php echo esc_html( count( $entry['recipients'] ) - 1 ); ?> recipients<?php endif; ?><?php if ( $order_url ) : ?> · <a href="<?php echo esc_url( $order_url ); ?>">View latest related order</a><?php endif; ?></p>
					<p class="wave-email-detail"><?php echo esc_html( $entry['detail'] ); ?></p>
					<?php if ( $entry['message'] ) : ?><details><summary>View message text</summary><pre><?php echo esc_html( $entry['message'] ); ?></pre></details><?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
		<?php if ( ! $visible ) : ?><div class="wave-email-empty"><h2>No communications match these filters.</h2><p>Try a wider date range or clear the customer and message filters.</p></div><?php endif; ?>
		</div>
		<?php if ( $total_pages > 1 ) : ?><nav class="wave-email-pages" aria-label="Communication pages"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $page, 'total' => $total_pages, 'prev_text' => '← Previous', 'next_text' => 'Next →' ) ) ); ?></nav><?php endif; ?>
		<footer class="wave-footer">Wave Consulting · Message bodies are shown as plain text with secure links and tokens hidden. WordPress history begins when WP Mail Logging was enabled. Prescribery only contributes message text its supported API returns.</footer>
	</div>
	<?php
}
