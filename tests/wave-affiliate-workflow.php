<?php
/** Resolution safety tests using synthetic source records only. */
require __DIR__ . '/wave-affiliates.php';
function affwp_get_referral($id){return isset($GLOBALS['source_refs'][$id]) ? clone $GLOBALS['source_refs'][$id] : false;}
function affwp_set_referral_status($id,$status){$GLOBALS['sequence'][]='status:'.$status;$GLOBALS['source_refs'][$id]->status=$status;return true;}
$caps['manage_referrals']=true;
$base=(object)['referral_id'=>501,'affiliate_id'=>1,'amount'=>'10.00','currency'=>'USD','status'=>'pending','payout_id'=>0,'reference'=>'22','context'=>'woocommerce'];
$source_refs=[501=>clone $base];$orders=[22=>new FakeOrder()];$sequence=[];
$app->referrals=new class {
 function get_referrals($args){return !empty($GLOBALS['duplicate']) ? [1,2] : [1];}
 function update_referral($id,$args){$GLOBALS['sequence'][]='amount:'.$args['amount'];$GLOBALS['source_refs'][$id]->amount=$args['amount'];return true;}
};
$workflow_start=$checks;
check(wave_aff_display_money(['USD'=>24540420])==='≈ USD 2,454.04','rounded display marked approximate');
check(wave_aff_display_money(['USD'=>40024500])==='USD 4,002.45','exact ready total');
check(wave_aff_display_money(['USD'=>123450])==='≈ USD 12.35','round half cent display');
check(wave_aff_month_payable(['payable'=>['USD'=>40024500],'carryover'=>['USD'=>37825500]])===['USD'=>2199000],'month component does not double-count older');
foreach(['Source status: pending'=>'approval','Order payment not confirmed'=>'payment','Commission exceeds payout currency precision'=>'rounding','Zero or invalid commission'=>'amount','Order refunded'=>'refund','Order failed'=>'refund','Affiliate not active'=>'affiliate','Multiple referrals on this order'=>'duplicate','Already assigned to a payout'=>'payout'] as $reason=>$key){check(wave_aff_issue(['reason'=>$reason,'decision'=>'Review'])[0]===$key,'issue routing '.$key);}
$rev=wave_aff_referral_revision($source_refs[501],$orders[22]);
wave_aff_resolve_referral(501,$rev,'approve','10.00','Verified synthetic payment and agreement');
check($source_refs[501]->status==='unpaid' && $source_refs[501]->amount==='10.00','pending becomes unpaid, never paid');
$source_refs[501]=clone $base;$source_refs[501]->amount='10.001';$sequence=[];
wave_aff_resolve_referral(501,wave_aff_referral_revision($source_refs[501],$orders[22]),'approve','10.00','Rounding confirmed by synthetic agreement');
check($sequence===['amount:10.00','status:unpaid'],'correct amount before approving');
$source_refs[501]=clone $base;$source_refs[501]->status='unpaid';$sequence=[];
wave_aff_resolve_referral(501,wave_aff_referral_revision($source_refs[501],$orders[22]),'correct','12.00','Agreed amount corrected');
check($source_refs[501]->amount==='12.00' && $source_refs[501]->status==='unpaid' && $sequence===['amount:12.00'],'amount corrections preserve status');
$source_refs[501]=clone $base;$source_refs[501]->status='unpaid';$sequence=[];
wave_aff_resolve_referral(501,wave_aff_referral_revision($source_refs[501],$orders[22]),'reject','','No commission owed for synthetic fixture');
check($source_refs[501]->amount==='10.00' && $sequence===['status:rejected'],'reject preserves source amount for plugin accounting');
foreach(['paid','rejected'] as $status){$source_refs[501]=clone $base;$source_refs[501]->status=$status;denies(fn()=>wave_aff_resolve_referral(501,'','approve','10','Fixture reason'),'Only pending');}
$source_refs[501]=clone $base;$source_refs[501]->payout_id=9;denies(fn()=>wave_aff_resolve_referral(501,'','correct','10','Fixture reason'),'outside a payout');
$source_refs[501]=clone $base;$orders[22]->refund='5.00';denies(fn()=>wave_aff_resolve_referral(501,$rev,'approve','10','Fixture reason'),'changed');
$rev=wave_aff_referral_revision($source_refs[501],$orders[22]);denies(fn()=>wave_aff_resolve_referral(501,$rev,'approve','10','Fixture reason'),'refund');
$orders[22]->refund='0.00';$orders[22]->transaction='';$rev=wave_aff_referral_revision($source_refs[501],$orders[22]);denies(fn()=>wave_aff_resolve_referral(501,$rev,'approve','10','Fixture reason'),'payment');
$orders[22]->transaction='txn';$rev=wave_aff_referral_revision($source_refs[501],$orders[22]);$duplicate=true;denies(fn()=>wave_aff_resolve_referral(501,$rev,'approve','10','Fixture reason'),'Multiple referrals');$duplicate=false;
denies(fn()=>wave_aff_resolve_referral(501,$rev,'approve','10.001','Fixture reason'),'precision');
denies(fn()=>wave_aff_resolve_referral(501,$rev,'approve','101','Fixture reason'),'order total');
denies(fn()=>wave_aff_resolve_referral(501,$rev,'paid','10','Fixture reason'),'supported review decision');
denies(fn()=>wave_aff_resolve_referral(501,$rev,'approve','10','bad'),'reason or evidence');
denies(fn()=>wave_aff_resolve_referral(501,$rev,'correct','10','Fixture reason'),'unchanged');
$caps['manage_referrals']=false;denies(fn()=>wave_aff_resolve_referral(501,$rev,'approve','10','Fixture reason'),'permission');
echo 'PASS: '.($checks-$workflow_start).' workflow / resolution checks'.PHP_EOL;
