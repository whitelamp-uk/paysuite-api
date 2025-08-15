<?php
// copy this somewhere "safe", fill in the config, check that the amount conversion is what you want
// do a test run, check response, comment out the break at the end so it runs to completion.
// could no doubt be modified to patch other attributes of the contract

// NB work in progress

$org = 'xyz';
require 'apikeys.php'; // 
$code_key = $apikeys[strtoupper('$org')];
list($clientcode, $key) = explode('::', $code_key);

$un  = '';
$pw  = '';
$db  = 'crucible2_'.$org;
$dbm = $db.'_make';
$url = 'https://ddcms.accesspaysuite.com/api/v3/client/'.$clientcode.'/contract/';
//$key = '';
$dom = '01'; // day of month

$con = new mysqli ('localhost',$un,$pw,$db);
$cnm = new mysqli ('localhost',$un,$pw,$dbm);

if (!$con || !$cnm) {
	echo "oops\ndatabase connection failure\n";
	exit;
}

$q = "SELECT MandateId, ContractGuid, Amount FROM paysuite_mandate WHERE Status = 'Active' AND SUBSTR(StartDate, 9,2) = '".$dom."'";
$res = $con->query($q);
while ($row = $res->fetch_assoc()) {
	$id = $row['MandateId'];
	$amnt = $row['Amount'];
	$guid = $row['ContractGuid'];
	echo $guid.' '.$amnt."\n";

	if (strlen($guid) > 10) {
		$r = exec( "curl --silent -L -g '".$url.$guid."' -H 'apiKey: ".$key."'");
		print_r(json_decode($r));
		exit;
	}
	// possibly - divide amount by 4.34, multiply by five, format to 2 dp, only patch if $new != $amnt
	if ($amnt == '4.34') {
		$new = '5.00';
		$comment = 'Amount%20changed%20to%20five%20pounds';
	} else if ($amnt == '8.68') {
		$new = '10.00';
		$comment = 'Amount%20changed%20to%20ten%20pounds';
	} else if ($amnt == '13.02') {
		$new = '15.00';
		$comment = 'Amount%20changed%20to%20fifteen%20pounds';
	} else if ($amnt == '17.36') {
		$new = '20.00';
		$comment = 'Amount%20changed%20to%20twenty%20pounds';
	} else if ($amnt == '21.70') {
		$new = '25.00';
		$comment = 'Amount%20changed%20to%20twenty-five%20pounds';
	} else {
		echo "amnt is $amnt\n";
		continue;
	}
	if (strlen($guid) > 10) {
		$r = exec( "curl --silent -L -g -X PATCH '".$url.$guid."/amount?amount=".$new."&comment=".$comment."' -H 'apiKey: ".$key."'");
		//print_r(json_decode($r));
	}
	$q2 = "UPDATE paysuite_mandate SET Amount='".$new."' WHERE MandateId='".$id."'";
	$res2 = $con->query($q2);
	// comment these lines out after a test run
	echo json_decode($r)->Message."\n";
	break;
}
