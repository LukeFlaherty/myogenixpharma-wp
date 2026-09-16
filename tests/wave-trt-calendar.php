<?php
/** Fictional calendar fixtures; no WordPress DB, network, or patient records. */
define( 'ABSPATH', __DIR__ );
function add_action( ...$args ) {}
function admin_url( $path ) { return 'https://example.invalid/wp-admin/' . $path; }
function wp_date( $format, $timestamp = null ) { return ( new DateTimeImmutable('@' . ( $timestamp ?? time() )) )->setTimezone(new DateTimeZone('America/New_York'))->format($format); }
function wc_get_order_status_name( $status ) { return $status; }
function wcs_order_contains_renewal( $record ) { return $record->renewal; }
function wave_trt_work( $record ) { return $record->work; }
function wave_trt_order_facts( $record ) { return array( 'events' => $record->events ); }
function wave_trt_milestones() { return array( 'delivery' => 'Delivery verified' ); }
require dirname(__DIR__) . '/inc/wave-trt-calendar.php';
class CalendarRecord {
	public $work = array(); public $events = array(); public $refunds = array(); public $renewal = false; public $next = 0; public $status = 'processing';
	function __construct(public $id, public $created) {}
	function get_formatted_billing_full_name() { return 'Fictional Person'; }
	function get_customer_id() { return 1; }
	function get_id() { return $this->id; }
	function get_edit_order_url() { return 'https://example.invalid/order/' . $this->id; }
	function get_date_created() { return $this->created; }
	function get_status() { return $this->status; }
	function get_refunds() { return $this->refunds; }
	function has_status($status) { return $this->status === $status; }
	function get_time($key) { return $this->next; }
}
$checks = 0;
function ensure($condition, $message) { global $checks; if (!$condition) { throw new Exception($message); } $checks++; }
ensure(wave_trt_calendar_year(array('2026'),2026) === 2026, 'Array year rejected');
ensure(wave_trt_calendar_year('2030',2026) === 2030, 'Year navigation accepted');
ensure(wave_trt_calendar_year('<script>',2026) === 2026, 'Invalid year rejected');
ensure(wave_trt_calendar_checkin_date('2026-01-10') === '2025-12-20', 'Check-in crosses year boundary');
ensure(wave_trt_calendar_checkin_date('2024-03-10') === '2024-02-18', 'Leap year planning arithmetic');
ensure(wave_trt_calendar_checkin_date('2026-02-31') === '', 'Invalid planning dates rejected');
$order = new CalendarRecord(123,new DateTimeImmutable('2026-01-10 00:30:00+00:00'));
$order->renewal = true;
$order->refunds[] = new CalendarRecord(124,new DateTimeImmutable('2026-02-05 12:00:00+00:00'));
$order->work = array('due'=>'2026-01-20','status'=>'open','next'=>'Confirm shipment','history'=>array(array('label'=>'Logged contact / internal note','at'=>strtotime('2026-01-15 17:00:00+00:00'),'by'=>'Fixture staff')), 'milestones'=>array('delivery'=>array('at'=>strtotime('2026-01-18 17:00:00+00:00'),'by'=>'Fixture staff')));
$sub = new CalendarRecord(200,new DateTimeImmutable('2025-10-10')); $sub->status = 'active'; $sub->next = strtotime('2026-01-10 17:00:00+00:00');
$patients = array('user-1'=>array('orders'=>array($order),'subscriptions'=>array($sub)));
$result = wave_trt_calendar_collect($patients,2026);
ensure(count($result['events']) === 6, 'All expected actual events retained');
ensure($result['events'][0]['date'] === '2026-01-09', 'UTC creation date uses local calendar day');
ensure($result['events'][0]['type'] === 'renewal_order', 'Renewal order distinguished from initial order');
ensure(count($result['undated']) === 0, 'Scheduled patient has no gap');
$prior = wave_trt_calendar_collect($patients,2025);
ensure(count($prior['events']) === 1 && $prior['events'][0]['state'] === 'suggested', 'Suggested check-in appears in prior year without inventing renewals');
$order->work['status'] = 'resolved';
$closed = wave_trt_calendar_collect($patients,2026);
ensure(count(array_filter($closed['events'], fn($e)=>$e['type']==='followup' && $e['state']==='closed')) === 1, 'Closed follow-up not overdue');
ensure(count($closed['undated']) === 1, 'Closed task is not an open follow-up date');
$sub->status = 'on-hold';
$paused = wave_trt_calendar_collect($patients,2026);
ensure(count(array_filter($paused['events'],fn($e)=>$e['type']==='renewal')) === 0, 'Paused subscription not shown as scheduled');
ensure(str_contains($paused['undated'][0]['reason'],'No active scheduled renewal'), 'Missing renewal surfaced');
$order->work['milestones'] = array();
$removed = wave_trt_calendar_collect($patients,2026);
ensure(count(array_filter($removed['events'],fn($e)=>$e['type']==='milestone')) === 0, 'Removed staff verification no longer shown');
ensure(count($removed['directory']) === 1, 'Patient remains accessible even with scheduling gaps');
echo "$checks calendar checks passed.\n";
