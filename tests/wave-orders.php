<?php
/** Standalone invariants for the staff prepared-order workflow. */
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
function add_action() {}
function add_filter() {}
function absint( $v ) { return abs( (int) $v ); }
function sanitize_email( $v ) { return strtolower( trim( (string) $v ) ); }
function sanitize_text_field( $v ) { return trim( (string) $v ); }
function is_email( $v ) { return false !== filter_var( $v, FILTER_VALIDATE_EMAIL ); }
function wc_format_coupon_code( $v ) { return strtolower( trim( $v ) ); }
function get_current_user_id() { return 99; }
function wp_generate_password() { return 'test-secret'; }
function get_post_meta( $id, $key ) { global $coupon_affiliates; return $coupon_affiliates[ $id ] ?? 0; }
function wave_aff_ready() { return true; }
function affwp_get_affiliate( $id ) { global $affiliates; return $affiliates[ $id ] ?? false; }
function get_userdata( $id ) { global $users_by_id; return $users_by_id[ $id ] ?? false; }
function get_user_by( $field, $value ) { global $users_by_email; return 'email' === $field ? ( $users_by_email[ strtolower( $value ) ] ?? false ) : false; }
class Wave_Test_Countries { public function get_countries() { return array( 'US' => 'United States', 'CA' => 'Canada' ); } }
function WC() { static $wc; if ( ! $wc ) { $wc = (object) array( 'countries' => new Wave_Test_Countries() ); } return $wc; }

class Wave_Test_Product {
	private $id; private $type; private $status; private $stock; private $purchasable; private $sold; private $parent; private $attributes;
	public function __construct( $id, $type = 'simple', $args = array() ) { $this->id = $id; $this->type = $type; $this->status = $args['status'] ?? 'publish'; $this->stock = $args['stock'] ?? 99; $this->purchasable = $args['purchasable'] ?? true; $this->sold = $args['sold'] ?? false; $this->parent = $args['parent'] ?? 0; $this->attributes = $args['attributes'] ?? array(); }
	public function get_id() { return $this->id; }
	public function get_parent_id() { return $this->parent; }
	public function get_status() { return $this->status; }
	public function is_type( $types ) { return in_array( $this->type, (array) $types, true ); }
	public function is_purchasable() { return $this->purchasable; }
	public function get_variation_attributes() { return $this->attributes; }
	public function is_in_stock() { return $this->stock > 0; }
	public function has_enough_stock( $qty ) { return $qty <= $this->stock; }
	public function is_sold_individually() { return $this->sold; }
	public function get_name() { return 'Product ' . $this->id; }
	public function get_price() { return '25.00'; }
}
$products = array();
function wc_get_product( $id ) { global $products; return $products[ $id ] ?? false; }
function wcs_is_subscription_product( $p ) { return $p->is_type( array( 'subscription', 'subscription_variation' ) ); }

class WC_Coupon {
	private $id = 0;
	public function __construct( $code ) { global $coupons; $this->id = $coupons[ $code ] ?? 0; }
	public function get_id() { return $this->id; }
}

require_once dirname( __DIR__ ) . '/inc/wave-orders.php';

$checks = 0;
function check_order( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
function rejects_order( $callable, $message ) { try { $callable(); } catch ( RuntimeException $e ) { check_order( true, $message ); return; } check_order( false, $message ); }

$products[10] = new Wave_Test_Product( 10 );
$products[20] = new Wave_Test_Product( 20, 'subscription' );
$products[30] = new Wave_Test_Product( 30, 'variable' );
$products[40] = new Wave_Test_Product( 40, 'variation', array( 'parent' => 41, 'attributes' => array( 'dose' => '' ) ) );
$products[41] = new Wave_Test_Product( 41, 'variable' );
$products[50] = new Wave_Test_Product( 50, 'simple', array( 'stock' => 1 ) );
$products[60] = new Wave_Test_Product( 60, 'simple', array( 'sold' => true ) );

$items = wave_orders_validate_items( array( array( 'product' => 10, 'quantity' => '2' ), array( 'product' => 10, 'quantity' => '3' ), array( 'product' => 20, 'quantity' => '1' ) ) );
check_order( 2 === count( $items ) && 5 === $items[0]['quantity'], 'duplicate product lines merge safely' );
check_order( true === $items[1]['subscription'], 'subscription items are flagged for existing-subscription review' );
rejects_order( function () { wave_orders_validate_items( array() ); }, 'an empty order is rejected' );
rejects_order( function () { wave_orders_validate_items( array( array( 'product' => 10, 'quantity' => '1.5' ) ) ); }, 'fractional quantities are rejected' );
rejects_order( function () { wave_orders_validate_items( array( array( 'product' => 30, 'quantity' => '1' ) ) ); }, 'a variable parent without an exact option is rejected' );
rejects_order( function () { wave_orders_validate_items( array( array( 'product' => 40, 'quantity' => '1' ) ) ); }, 'an incomplete variation is rejected' );
rejects_order( function () { wave_orders_validate_items( array( array( 'product' => 50, 'quantity' => '2' ) ) ); }, 'insufficient stock is rejected' );
rejects_order( function () { wave_orders_validate_items( array( array( 'product' => 60, 'quantity' => '2' ) ) ); }, 'sold-individually quantities are rejected' );

$existing = (object) array( 'ID' => 7, 'user_email' => 'existing@example.com' );
$users_by_email = array( 'existing@example.com' => $existing );
$users_by_id = array( 70 => (object) array( 'ID' => 70, 'user_email' => 'affiliate@example.com' ) );
$affiliates = array( 5 => (object) array( 'affiliate_id' => 5, 'user_id' => 70, 'status' => 'active' ) );
$coupons = array( 'save10' => 100, 'other-affiliate' => 101 );
$coupon_affiliates = array( 101 => 8 );

$prepared = wave_orders_prepare( array( 'customer_id' => 7, 'email' => 'existing@example.com', 'first_name' => 'Existing', 'last_name' => 'Customer', 'country' => 'us', 'address_1' => '10 Main St', 'items' => array( array( 'product' => 10, 'quantity' => '1' ) ), 'affiliate_id' => 5, 'coupons' => 'SAVE10, save10' ) );
check_order( 7 === $prepared['customer_id'], 'an exact email reuses the existing customer account' );
check_order( array( 'save10' ) === $prepared['coupons'], 'coupon input is normalized and deduplicated' );
check_order( 'US' === $prepared['address']['country'] && '10 Main St' === $prepared['address']['address_1'], 'billing details are normalized and stored with the draft' );
check_order( 7 * DAY_IN_SECONDS === $prepared['expires'] - $prepared['created'], 'payment links expire after seven days' );
rejects_order( function () { wave_orders_prepare( array( 'customer_id' => 8, 'email' => 'existing@example.com', 'first_name' => 'Wrong', 'last_name' => 'Account', 'items' => array( array( 'product' => 10, 'quantity' => '1' ) ) ) ); }, 'a selected account/email mismatch is rejected' );
rejects_order( function () { wave_orders_prepare( array( 'email' => 'affiliate@example.com', 'first_name' => 'Self', 'last_name' => 'Referral', 'items' => array( array( 'product' => 10, 'quantity' => '1' ) ), 'affiliate_id' => 5 ) ); }, 'affiliate self-referrals are rejected by email' );
rejects_order( function () { wave_orders_prepare( array( 'email' => 'new@example.com', 'first_name' => 'Coupon', 'last_name' => 'Conflict', 'items' => array( array( 'product' => 10, 'quantity' => '1' ) ), 'affiliate_id' => 5, 'coupons' => 'other-affiliate' ) ); }, 'conflicting coupon attribution is rejected' );

echo "PASS: {$checks} prepared-order checks\n";
