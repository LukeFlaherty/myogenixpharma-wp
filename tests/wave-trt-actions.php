<?php
/** Isolated POST-handler regression checks. No network, WordPress DB, or patient data. */
define( 'ABSPATH', __DIR__ );
function add_action( ...$args ) {}
function absint( $s ) { return abs( (int) $s ); }
function wp_unslash( $s ) { return stripslashes( $s ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( $s ) ); }
function esc_html( $s ) { return htmlspecialchars( $s ); }
class ActionDenied extends Exception {}
class ActionRedirect extends Exception {}
function wp_die( $s, ...$args ) { throw new ActionDenied( $s ); }
function current_user_can( $cap ) { return $GLOBALS['allowed']; }
function check_admin_referer( $action ) { if ( ( $_POST['_wpnonce'] ?? '' ) !== $action ) { throw new ActionDenied( 'nonce' ); } }
function wp_get_current_user() { return (object) array( 'ID' => 9, 'display_name' => 'Fixture operator' ); }
function admin_url( $s ) { return 'https://example.invalid/wp-admin/' . $s; }
function wp_safe_redirect( $s ) { throw new ActionRedirect( $s ); }
function wc_get_order( $id ) { return 123 === $id ? $GLOBALS['order'] : false; }
class FakeDB {
	public $locked = false;
	function prepare( $sql, ...$values ) { return $sql; }
	function get_var( $sql ) { if ( str_contains( $sql, 'RELEASE_LOCK' ) ) { $this->locked = false; return '1'; } $this->locked = true; return '1'; }
}
class FakeOrder {
	public $meta = array(); public $notes = array(); public $writes = 0; public $product = 883;
	function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	function get_type() { return 'shop_order'; }
	function get_items() { $p = $this->product; return array( new class($p) { function __construct( public $p ) {} function get_product_id() { return $this->p; } } ); }
	function get_formatted_billing_full_name() { return 'Fictional Fixture'; }
	function has_status( $status ) { return false; }
	function update_meta_data( $key, $value ) { if ( '_wave_trt_workflow' !== $key ) { throw new Exception('Unsafe metadata write'); } $this->meta[ $key ] = $value; }
	function save_meta_data() { $this->writes++; }
	function add_order_note( $text, $customer, $added ) { if ( $customer ) { throw new Exception('Customer message forbidden'); } $this->notes[] = $text; }
}
require dirname( __DIR__ ) . '/inc/wave-trt-dashboard.php';
$wpdb = new FakeDB(); $allowed = true; $order = new FakeOrder(); $checks = 0;
function expect_action( $operation, $fields = array(), $success = true ) {
	global $order, $wpdb, $checks;
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST = array_merge( array( 'record_id' => '123', '_wpnonce' => 'wave_trt_action_123', 'revision' => (string) ( wave_trt_work( $order )['revision'] ?? 0 ), 'operation' => $operation ), $fields );
	$writes = $order->writes;
	try { wave_trt_handle_action(); throw new Exception('Handler did not terminate'); }
	catch ( ActionRedirect $e ) { if ( ! $success || $order->writes !== $writes + 1 || $wpdb->locked ) { throw new Exception('Unexpected success or lock leak'); } }
	catch ( ActionDenied $e ) { if ( $success || $order->writes !== $writes || $wpdb->locked ) { throw new Exception('Unexpected denial/write: '.$e->getMessage()); } }
	$checks++;
}
expect_action('assign');
if ( wave_trt_work($order)['owner'] !== 9 ) { throw new Exception('Assignment not persisted'); }
expect_action('save', array('workflow_status'=>'pharmacy','due'=>'2026-10-01','next'=>'Request shipment confirmation'));
expect_action('note', array('note'=>'Contact logged; no message sent.'));
expect_action('verify', array('milestone'=>'delivery','note'=>'Carrier delivery confirmed by staff.'));
if ( ! isset(wave_trt_work($order)['milestones']['delivery']) ) { throw new Exception('Milestone missing'); }
expect_action('clear_milestone', array('milestone'=>'delivery','note'=>'Correction: wrong shipment.'));
if ( isset(wave_trt_work($order)['milestones']['delivery']) ) { throw new Exception('Correction failed'); }
expect_action('resolve', array('note'=>'Follow-up completed.'));
expect_action('reopen');
expect_action('unassign');
expect_action('resolve', array(), false);
expect_action('verify', array('milestone'=>'provider_approved','note'=>'Disallowed clinical mutation'), false);
expect_action('save', array('workflow_status'=>'open','due'=>'2026-02-31','next'=>'Next step'), false);
expect_action('save', array('workflow_status'=>'open','next'=>''), false);
expect_action('assign', array('revision'=>'0'), false);
expect_action('assign', array('_wpnonce'=>'invalid'), false);
expect_action('assign', array('record_id'=>'999'), false);
$order->product = 999; expect_action('assign', array(), false); $order->product = 883;
$allowed = false; expect_action('assign', array(), false); $allowed = true;
expect_action('charge', array(), false);
expect_action('note', array('note'=>str_repeat('x',2001)), false);
if (count($order->notes) !== 8) { throw new Exception('Private audit note mismatch'); }
echo "$checks action-handler checks passed; all mutations isolated to staff metadata/private notes.\n";
