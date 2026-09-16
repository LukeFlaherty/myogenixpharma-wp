<?php
/** Authorized annual TRT calendar. Dates are records or explicitly labeled suggestions. */
defined( 'ABSPATH' ) || exit;
add_action( 'admin_menu', function () {
	$GLOBALS['wave_trt_calendar_hook'] = add_submenu_page( 'wave-trt', 'TRT Patient Calendar', 'TRT Patient Calendar', 'manage_woocommerce', 'wave-trt-calendar', 'wave_trt_calendar_render' );
}, 20 );
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ( $GLOBALS['wave_trt_calendar_hook'] ?? '' ) !== $hook ) { return; }
	wp_enqueue_style( 'wave-trt', get_stylesheet_directory_uri() . '/assets/css/wave-trt-dashboard.css', array(), '1.1.0' );
	wp_enqueue_style( 'wave-trt-calendar', get_stylesheet_directory_uri() . '/assets/css/wave-trt-calendar.css', array( 'wave-trt' ), '1.0.1' );
	wp_enqueue_script( 'wave-trt-calendar', get_stylesheet_directory_uri() . '/assets/js/wave-trt-calendar.js', array(), '1.0.1', true );
} );
add_action( 'admin_init', function () {
	if ( isset( $_GET['page'] ) && 'wave-trt-calendar' === $_GET['page'] ) { nocache_headers(); }
} );
function wave_trt_calendar_types() {
	return array( 'order' => 'Orders', 'renewal_order' => 'Renewal orders', 'renewal' => 'Scheduled renewals', 'followup' => 'Check-ins / follow-ups', 'contact' => 'Contact logged', 'milestone' => 'Progress recorded', 'refund' => 'Refunds', 'suggested' => 'Suggested check-ins' );
}
function wave_trt_calendar_year( $value, $default ) {
	return is_scalar( $value ) && preg_match( '/^20\d{2}$/', (string) $value ) ? (int) $value : $default;
}
/** Local calendar arithmetic avoids DST shifts in suggested dates. */
function wave_trt_calendar_checkin_date( $renewal_day ) {
	$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $renewal_day );
	return $date && $date->format( 'Y-m-d' ) === $renewal_day ? $date->modify( '-21 days' )->format( 'Y-m-d' ) : '';
}
function wave_trt_calendar_collect( $patients, $year ) {
	$events = array(); $undated = array(); $directory = array();
	foreach ( $patients as $key => $patient ) {
		$latest = $patient['orders'][0] ?? $patient['subscriptions'][0];
		$name = trim( $latest->get_formatted_billing_full_name() ) ?: 'Customer #' . $latest->get_customer_id();
		$patient_url = admin_url( 'admin.php?page=wave-trt' ) . '#wave-record-' . $latest->get_id();
		$directory[] = array( 'id' => (string) $key, 'name' => $name, 'url' => $patient_url );
		$had_followup = false; $has_renewal = false;
		$add = function ( $day, $type, $title, $record, $detail = '', $state = 'recorded' ) use ( &$events, $key, $name, $patient_url, $year ) {
			if ( ! preg_match( '/^' . $year . '-\d{2}-\d{2}$/', $day ) ) { return; }
			$events[] = array( 'date' => $day, 'type' => $type, 'title' => $title, 'patient' => $name, 'patientId' => (string) $key, 'recordId' => $record->get_id(), 'source' => $record->get_edit_order_url(), 'patientUrl' => $patient_url, 'detail' => $detail, 'state' => $state );
		};
		foreach ( $patient['orders'] as $order ) {
			$is_renewal = function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order );
			if ( $order->get_date_created() ) { $add( wp_date( 'Y-m-d', $order->get_date_created()->getTimestamp() ), $is_renewal ? 'renewal_order' : 'order', ( $is_renewal ? 'Renewal order #' : 'Order #' ) . $order->get_id(), $order, 'Current status: ' . wc_get_order_status_name( $order->get_status() ) . '. Created on this date; not a delivery date.' ); }
			foreach ( $order->get_refunds() as $refund ) {
				if ( $refund->get_date_created() ) { $add( wp_date( 'Y-m-d', $refund->get_date_created()->getTimestamp() ), 'refund', 'Refund recorded · order #' . $order->get_id(), $order, 'Open the order for the amount, reason and subscription context.' ); }
			}
			// Only recognized operational notes; never expose raw provider payloads.
			$facts = wave_trt_order_facts( $order );
			foreach ( $facts['events'] as $event ) {
				if ( in_array( $event['label'], array( 'Provider approval recorded', 'Pharmacy handoff acknowledged (not shipment confirmation)', 'Medication payment succeeded' ), true ) && $event['date'] ) { $add( wp_date( 'Y-m-d', $event['date']->getTimestamp() ), 'milestone', $event['label'], $order, 'System note recorded on this date. Open source order for context.' ); }
			}
		}
		foreach ( array_merge( $patient['orders'], $patient['subscriptions'] ) as $record ) {
			$work = wave_trt_work( $record );
			if ( ! empty( $work['due'] ) ) {
				$closed = 'resolved' === ( $work['status'] ?? '' );
				$had_followup = $had_followup || ! $closed;
				$add( $work['due'], 'followup', $closed ? 'Follow-up closed' : 'Staff check-in / follow-up', $record, ( $work['next'] ?? 'Review this patient’s next step.' ) . ( $closed ? ' Closed by staff; shown on its originally scheduled date.' : '' ), $closed ? 'closed' : 'scheduled' );
			}
			foreach ( $work['history'] ?? array() as $event ) {
				if ( 'Logged contact / internal note' === $event['label'] ) { $add( wp_date( 'Y-m-d', $event['at'] ), 'contact', 'Contact / internal note logged', $record, 'Recorded by ' . $event['by'] . '. A logged note does not prove a message was delivered.' ); }
			}
			foreach ( $work['milestones'] ?? array() as $milestone => $event ) {
				if ( isset( wave_trt_milestones()[ $milestone ] ) ) { $add( wp_date( 'Y-m-d', $event['at'] ), 'milestone', wave_trt_milestones()[ $milestone ] . ' · staff verified', $record, 'Verification recorded by ' . $event['by'] . ' on this date; the actual event may have occurred earlier.' ); }
			}
		}
		foreach ( $patient['subscriptions'] as $sub ) {
			$next = $sub->get_time( 'next_payment' );
			if ( ! $sub->has_status( 'active' ) || ! $next ) { continue; }
			$has_renewal = true;
			$day = wp_date( 'Y-m-d', $next );
			$add( $day, 'renewal', 'Scheduled renewal · #' . $sub->get_id(), $sub, 'Next payment date stored in WooCommerce. This does not confirm consent, clinical clearance, payment, or shipment.', 'scheduled' );
			$add( wave_trt_calendar_checkin_date( $day ), 'suggested', 'Suggested pre-renewal check-in', $sub, 'Planning suggestion: 21 days before the stored renewal date (' . $day . '). Not a booked appointment, reminder, or patient message.', 'suggested' );
		}
		if ( ! $had_followup || ! $has_renewal ) { $undated[] = array( 'patientId' => (string) $key, 'name' => $name, 'url' => $patient_url, 'reason' => implode( ' · ', array_filter( array( ! $had_followup ? 'No open dated follow-up' : '', ! $has_renewal ? 'No active scheduled renewal' : '' ) ) ) ); }
	}
	usort( $events, function ( $a, $b ) { return strcmp( $a['date'], $b['date'] ) ?: strcmp( $a['patient'], $b['patient'] ) ?: strcmp( $a['type'], $b['type'] ); } );
	usort( $directory, function ( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );
	return compact( 'events', 'undated', 'directory' );
}
function wave_trt_calendar_render() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'You do not have permission to view this page.', '', array( 'response' => 403 ) ); }
	if ( ! function_exists( 'wc_get_orders' ) ) { echo '<div class="wrap"><h1>TRT Patient Calendar</h1><p>WooCommerce must be active.</p></div>'; return; }
	$year = wave_trt_calendar_year( $_GET['year'] ?? null, (int) wp_date( 'Y' ) );
	list( $patients, $limited, $excluded ) = wave_trt_load_patients();
	$data = wave_trt_calendar_collect( $patients, $year );
	$data['year'] = $year; $data['today'] = wp_date( 'Y-m-d' ); $data['types'] = wave_trt_calendar_types();
	$base = admin_url( 'admin.php?page=wave-trt-calendar' );
	?>
	<div class="wrap wave-trt wave-calendar" id="wave-calendar">
		<div class="wave-brand">WAVE CONSULTING <span>Patient operations</span></div>
		<header class="wave-cal-heading"><div><p class="wave-eyebrow">MYOGENIX PHARMA</p><h1>TRT patient calendar</h1><p>A full year of patient activity, follow-ups and renewal dates.</p></div><div class="wave-year-nav"><a class="button" aria-label="Previous year" href="<?php echo esc_url( add_query_arg( 'year', max( 2000, $year - 1 ), $base ) ); ?>">←</a><form method="get"><input type="hidden" name="page" value="wave-trt-calendar"><label for="wave-year">Year</label><input id="wave-year" name="year" type="number" min="2000" max="2099" value="<?php echo esc_attr( $year ); ?>"><button class="button">Go</button></form><a class="button" aria-label="Next year" href="<?php echo esc_url( add_query_arg( 'year', min( 2099, $year + 1 ), $base ) ); ?>">→</a><a class="button" href="<?php echo esc_url( $base ); ?>">Current year</a></div></header>
		<p class="wave-fresh">Updated <?php echo esc_html( wp_date( 'M j, Y · g:i a T' ) ); ?> · <?php echo esc_html( count( $patients ) ); ?> patients · Dates use <?php echo esc_html( wp_timezone_string() ); ?> · <?php echo esc_html( $excluded ); ?> test records excluded</p>
		<?php if ( $limited ) : ?><div class="notice notice-warning inline"><p>Partial calendar: the 2,000-record scan limit was reached. Older events and patients may be missing.</p></div><?php endif; ?>
		<div class="wave-cal-controls"><label>Find a patient or record<input type="search" id="wave-cal-search" placeholder="Patient name or order / subscription number" autocomplete="off"></label><label>Patient<select id="wave-cal-patient"><option value="">All patients</option><?php foreach ( $data['directory'] as $person ) : ?><option value="<?php echo esc_attr( $person['id'] ); ?>"><?php echo esc_html( $person['name'] ); ?></option><?php endforeach; ?></select></label><button class="button" type="button" id="wave-cal-reset">Reset filters</button><a class="button" href="#wave-year-grid">View all 12 months</a><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wave-trt' ) ); ?>">Patient dashboard</a></div>
		<fieldset class="wave-cal-legend"><legend>Show events</legend><?php foreach ( wave_trt_calendar_types() as $type => $label ) : ?><label><input type="checkbox" value="<?php echo esc_attr( $type ); ?>" <?php checked( 'suggested' !== $type ); ?>><span class="wave-cal-dot <?php echo esc_attr( $type ); ?>"></span><?php echo esc_html( $label ); ?></label><?php endforeach; ?></fieldset>
		<p class="wave-cal-context">Renewals show the next payment date currently stored in WooCommerce; later cycles are not yet scheduled here. Check-ins use staff follow-up dates. Suggested check-ins are optional planning dates, 21 days before renewal. <strong>Click any day to see every event and open the patient record.</strong></p>
		<div id="wave-cal-summary" class="wave-cal-summary" role="status" aria-live="polite"></div>
		<div class="wave-year-grid" id="wave-year-grid" aria-label="Twelve-month patient calendar">
		<?php for ( $month = 1; $month <= 12; $month++ ) :
			$first = new DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ) ); $offset = (int) $first->format( 'w' ); $days = (int) $first->format( 't' );
			?><section class="wave-month"><h2><?php echo esc_html( $first->format( 'F' ) ); ?><span data-month-count="<?php echo esc_attr( sprintf( '%04d-%02d', $year, $month ) ); ?>">0</span></h2><div class="wave-weekdays" aria-hidden="true"><?php foreach ( array( 'S', 'M', 'T', 'W', 'T', 'F', 'S' ) as $day ) { echo '<span>' . esc_html( $day ) . '</span>'; } ?></div><div class="wave-days">
			<?php for ( $cell = 0; $cell < 42; $cell++ ) : $day = $cell - $offset + 1; if ( $day < 1 || $day > $days ) : ?><span class="wave-day-blank" aria-hidden="true"></span><?php else : $date = sprintf( '%04d-%02d-%02d', $year, $month, $day ); ?><button type="button" class="wave-day <?php echo $data['today'] === $date ? 'is-today' : ''; ?>" data-date="<?php echo esc_attr( $date ); ?>" <?php echo $data['today'] === $date ? 'aria-current="date"' : ''; ?>><span><?php echo esc_html( $day ); ?></span><span class="wave-day-marks" aria-hidden="true"></span></button><?php endif; endfor; ?>
			</div></section>
		<?php endfor; ?>
		</div>
		<div class="wave-cal-bottom"><section><div class="wave-agenda-heading"><h2>Patient agenda</h2><label>Range<select id="wave-cal-range"><option value="upcoming">Upcoming in this year</option><option value="overdue">Past-due scheduled items</option><option value="all">All events in this year</option></select></label></div><p class="wave-muted">Past-due means the scheduled date has passed and the item remains open; verify its outcome with staff.</p><div id="wave-cal-agenda"></div><button type="button" class="button" id="wave-cal-more" hidden>Show more events</button></section><section><h2>Scheduling gaps</h2><p class="wave-muted">Patients without an open follow-up date or an active scheduled renewal. Closed plans may not need another date. Open the patient to review or schedule a check-in.</p><div id="wave-cal-gaps"></div></section></div>
		<dialog id="wave-day-dialog" aria-labelledby="wave-day-title"><div class="wave-dialog-head"><h2 id="wave-day-title"></h2><button class="button" type="button" id="wave-day-close">Close</button></div><div id="wave-day-events"></div></dialog>
		<noscript><p>Enable JavaScript to display calendar events and filters. Patient records remain available from the Patient dashboard link.</p></noscript>
		<script id="wave-cal-data" type="application/json"><?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
	</div>
	<?php
}
