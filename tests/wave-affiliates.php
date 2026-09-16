<?php
/** Standalone safety tests: no network or patient data. */
define( 'ABSPATH', __DIR__ );
function add_action( ...$args ) {}
function affwp_get_currency(){return 'USD';}
function affwp_get_decimal_count(){return 2;}
function wp_timezone() { return new DateTimeZone('America/New_York'); }
function wp_date($format) { return '2026-09'; }
function get_current_user_id(){ return 99; }
function wp_die($message,...$args){throw new RuntimeException($message);}
function check_admin_referer($action){if(($_POST['_wpnonce']??'')!==$action)throw new RuntimeException('nonce rejected');}
function affwp_add_referral_meta($id,$key,$value){$GLOBALS['ref_meta'][$id]=$value;}
function current_user_can($cap){ return !empty($GLOBALS['caps'][$cap]); }
function wc_get_order($id){return $GLOBALS['orders'][$id] ?? false;}
function is_wp_error($x){return false;}
function affwp_get_affiliate($id){return $GLOBALS['affs'][$id] ?? false;}
function get_userdata($id){return (object)['user_email'=>'affiliate'.$id.'@example.invalid'];}
function affwp_get_affiliate_meta(...$args){return true;}
function affiliate_wp(){return $GLOBALS['app'];}
function affiliate_wp_lifetime_commissions(){return $GLOBALS['lc'];}
function affwp_get_customer($id){return $GLOBALS['customers'][$id] ?? false;}
function affwp_get_customer_by($key,$value){foreach($GLOBALS['customers'] as $c){if($c->$key == $value){return $c;}} return false;}
function affwp_add_customer($fields){$id=count($GLOBALS['customers'])+1;$GLOBALS['customers'][$id]=(object)array_merge(['customer_id'=>$id],$fields);return $id;}
function is_email($email){return filter_var($email,FILTER_VALIDATE_EMAIL);}
function affwp_add_customer_meta($id,$key,$value){$GLOBALS['meta'][$id][$key][]=$value;return true;}
function affwp_get_customer_meta($id,$key,$single){return $GLOBALS['meta'][$id][$key]??[];}
function wp_cache_set(...$args){}
function wave_trt_test_record($o){return $o->test;}
function wc_get_orders(...$args){}
function absint($x){return abs((int)$x);}
class FakeLC {
 public $table_name='fixture_links'; public $cache_group='fixture'; public $rows=[]; public $next=101;
 function add($data){$id=$this->next++;$this->rows[$id]=(object)array_merge($data,['lifetime_customer_id'=>$id]);return $id;}
 function update($id,$data,...$args){if(!isset($this->rows[$id]))return false; foreach($data as $k=>$v)$this->rows[$id]->$k=$v;return true;}
 function delete($id){unset($this->rows[$id]);return true;}
}
class FakeDB {
 function prepare($sql,...$args){return $args;}
 function get_results($args){return array_values(array_filter($GLOBALS['lc']->lifetime_customers->rows,fn($r)=>$r->affwp_customer_id==$args[0]));}
}
class FakeOrder {
 public $status='completed',$refund='0.00',$total='100.00',$transaction='txn',$paid=true,$test=false,$uid=201,$email='customer@example.invalid';
 function get_type(){return 'shop_order';} function has_status($statuses){return in_array($this->status,$statuses,true);} function get_status(){return $this->status;}
 function get_total_refunded(){return $this->refund;} function get_transaction_id(){return $this->transaction;} function get_date_paid(){return $this->paid ? new DateTimeImmutable('2026-09-01 01:00:00',new DateTimeZone('UTC')) : false;}
 function get_total(){return $this->total;} function get_customer_id(){return $this->uid;} function get_billing_email(){return $this->email;}
 function get_billing_first_name(){return 'Fixture';} function get_billing_last_name(){return 'Customer';} function get_currency(){return 'USD';} function add_order_note($text,$customer,$by_user){if($customer)throw new Exception('Public note forbidden');$GLOBALS['notes'][]=$text;}
}
require dirname(__DIR__).'/inc/wave-affiliates.php';
$checks=0;
function check($ok,$label){global $checks; if(!$ok)throw new Exception($label);$checks++;}
function denies($fn,$contains){try{$fn();throw new Exception('Expected rejection: '.$contains);}catch(RuntimeException $e){check(str_contains($e->getMessage(),$contains),$e->getMessage());}}
check(wave_aff_units('0.10')+wave_aff_units('0.20')===3000,'decimal exactness');
check(wave_aff_units('123.4567')===1234567,'four decimals');
foreach(['1e3','12,34','1.23456','NaN','100000000000'] as $bad){check(null===wave_aff_units($bad),'reject malformed amount');}
check('12.34'===wave_aff_amount(123400),'format');check('-0.0001'===wave_aff_amount(-1),'negative');
check(['2026-03-01 05:00:00','2026-04-01 04:00:00']===wave_aff_bounds('2026-03'),'DST month boundary');
check(['2026-11-01 04:00:00','2026-12-01 05:00:00']===wave_aff_bounds('2026-11'),'DST end boundary');
foreach(['=1+1','+SUM(A1)','-42','@cmd',"\ttext",'  =evil'] as $cell){check(str_starts_with(wave_aff_csv_cell($cell),"'"),'formula neutralization');}
check(wave_aff_csv_cell('Normal 42')==='Normal 42','plain CSV text');
$a=(object)['affiliate_id'=>1,'user_id'=>301,'status'=>'active'];$b=(object)['affiliate_id'=>2,'user_id'=>302,'status'=>'active'];$affs=[1=>$a,2=>$b];
$caps=['manage_options'=>true,'manage_woocommerce'=>true,'manage_affiliates'=>true];check(wave_aff_allowed(),'admin allowed');unset($caps['manage_options']);check(!wave_aff_allowed(),'shop manager denied');
$ref=(object)['status'=>'unpaid','amount'=>'10.00','currency'=>'USD','context'=>'woocommerce','payout_id'=>0];$o=new FakeOrder();
check(''===wave_aff_review_reason($ref,$a,$o,false),'eligible paid order');
foreach(['pending','paid','rejected'] as $status){$r=clone $ref;$r->status=$status;check(''!==wave_aff_review_reason($r,$a,$o,false),'non-unpaid excluded');}
$r=clone $ref;$r->amount='10.001';check(str_contains(wave_aff_review_reason($r,$a,$o,false),'precision'),'fractional cents held');
$r=clone $ref;$r->currency='EUR';check(str_contains(wave_aff_review_reason($r,$a,$o,false),'currency'),'foreign currency held');
$r=clone $ref;$r->payout_id=42;check(str_contains(wave_aff_review_reason($r,$a,$o,false),'payout'),'assigned payout excluded');
$r=clone $ref;$r->amount='0';check(str_contains(wave_aff_review_reason($r,$a,$o,false),'Zero'),'zero held');
check(str_contains(wave_aff_review_reason($ref,$a,$o,true),'Multiple'),'duplicates held');
foreach(['refunded','failed','cancelled','rejected','on-hold'] as $status){$x=clone $o;$x->status=$status;check(''!==wave_aff_review_reason($ref,$a,$x,false),'order status hold');}
$x=clone $o;$x->refund='0.01';check(str_contains(wave_aff_review_reason($ref,$a,$x,false),'refund'),'partial refund hold');
$x=clone $o;$x->transaction='';check(str_contains(wave_aff_review_reason($ref,$a,$x,false),'payment'),'unconfirmed payment hold');
$rows=[['affiliate_id'=>1,'affiliate'=>'Fixture','currency'=>'USD','decision'=>'Proposed payable','amount'=>'10.10','period'=>'Selected month'],['affiliate_id'=>1,'affiliate'=>'Fixture','currency'=>'USD','decision'=>'Proposed payable','amount'=>'5.20','period'=>'Prior-month carryover'],['affiliate_id'=>1,'affiliate'=>'Fixture','currency'=>'EUR','decision'=>'Review','amount'=>'8','period'=>'Selected month']];
$s=wave_aff_report_summary($rows);check(count($s)===2 && $s[0]['payable']===153000 && $s[0]['carryover']===52000 && $s[1]['held']===80000,'separate currencies / carryover not double counted');
$lc=(object)['lifetime_customers'=>new FakeLC()];$wpdb=new FakeDB();$customers=[1=>(object)['customer_id'=>1,'user_id'=>201,'email'=>'customer@example.invalid']];
$app=(object)['settings'=>new class {function get($key){return true;}},'utils'=>(object)['wp_offset'=>-14400], 'referrals'=>new class {function get_referrals($args){return $GLOBALS['existing']??[];} function add($data){$GLOBALS['new_referral']=$data;return 222;}}];
$revision=wave_aff_link_revision([]);
check(wave_aff_change_link('c1',1,$revision,1,'Confirmed by fixture')==='c1','create link');
$first=wave_aff_current_links(1);check($first[0]->affiliate_id===1 && $first[0]->lifetime_customer_id===101,'canonical link primary id');
denies(fn()=>wave_aff_change_link('c1',1,$revision,2,'Confirmed by fixture'),'Another change');
check(wave_aff_change_link('c1',1,wave_aff_link_revision($first),2,'Correction from fixture')==='c1','update link');
check(wave_aff_current_links(1)[0]->affiliate_id===2,'update uses primary id, not customer id');
check(wave_aff_change_link('c1',1,wave_aff_link_revision(wave_aff_current_links(1)),0,'Remove attribution fixture')==='c1','delete link');
check([]===wave_aff_current_links(1),'link removed, customer retained');check(isset($customers[1]) && count($GLOBALS['meta'][1]['_wave_aff_link_audit'])===3,'audit events retained');
denies(fn()=>wave_aff_change_link('c1',99,$revision,1,'Verified fixture'),'Customer attribution changed');
denies(fn()=>wave_aff_change_link('c1',1,$revision,999,'Verified fixture'),'active affiliate');
denies(fn()=>wave_aff_change_link('c1',1,$revision,1,'bad'),'reason');
$customers[1]->user_id=301;denies(fn()=>wave_aff_change_link('c1',1,$revision,1,'Verified fixture'),'Self-referrals');$customers[1]->user_id=201;
$lc->lifetime_customers->add(['affwp_customer_id'=>1,'affiliate_id'=>1]);$lc->lifetime_customers->add(['affwp_customer_id'=>1,'affiliate_id'=>2]);
denies(fn()=>wave_aff_change_link('c1',1,wave_aff_link_revision(wave_aff_current_links(1)),2,'Verified fixture'),'Multiple lifetime');
$customers[2]=(object)['customer_id'=>2,'user_id'=>202,'email'=>'other@example.invalid'];$o->email='other@example.invalid';denies(fn()=>wave_aff_resolve_order_customer($o),'different AffiliateWP');
$o->uid=999;denies(fn()=>wave_aff_resolve_order_customer($o),'different customer');
$orders=[22=>new FakeOrder()];denies(fn()=>wave_aff_create_referral(22,1,'101','Fixture commission'),'positive commission');
denies(fn()=>wave_aff_create_referral(22,1,'10','Fixture commission'),'Assign and verify');
$lc->lifetime_customers->rows=[];$lc->lifetime_customers->add(['affwp_customer_id'=>1,'affiliate_id'=>1]);$existing=[(object)['referral_id'=>1]];
denies(fn()=>wave_aff_create_referral(22,1,'10','Fixture commission'),'already has a referral');
$existing=[];check(wave_aff_create_referral(22,1,'10.00','Fixture agreed commission')===222,'pending correction created');
check($GLOBALS['new_referral']['status']==='pending' && $GLOBALS['new_referral']['customer_id']===1 && $GLOBALS['new_referral']['customer']['email']==='customer@example.invalid','pending referral retains real customer attribution');
check($GLOBALS['new_referral']['date']==='2026-08-31 21:00:00','plugin offset input maps back to payment UTC');
check(isset($GLOBALS['ref_meta'][222]) && count($GLOBALS['notes'])===1,'commission evidence and private order audit');
$caps=[];denies(fn()=>wave_aff_handle_action(),'permission');
$caps=['manage_options'=>true,'manage_woocommerce'=>true,'manage_affiliates'=>true];
$_SERVER['REQUEST_METHOD']='GET';denies(fn()=>wave_aff_handle_action(),'POST required');
$_SERVER['REQUEST_METHOD']='POST';$_POST=[];denies(fn()=>wave_aff_handle_action(),'nonce rejected');
echo "PASS: $checks affiliate checks\n";
