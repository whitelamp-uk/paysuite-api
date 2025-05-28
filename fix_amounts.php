<?php
// copy this somewhere "safe", fill in the config, check that the amount conversion is what you want
// do a test run, check response, comment out the break at the end so it runs to completion.
// could no doubt be modified to patch other attributes of the contract
$un  = '';
$pw  = '';
$db  = '';
$url = 'https://ddcms.accesspaysuite.com/api/v3/client/******/contract/';
$key = '';

$con = new mysqli ('localhost',$un,$pw,$db);

if (!$con) {
	echo "oops\n";
	exit;
}

$q = "SELECT MandateId, ContractGuid, Amount from paysuite_mandate ";
$res = $con->query($q);
while ($row = $res->fetch_assoc()) {
	$id = $row['MandateId'];
	$amnt = $row['Amount'];
	$guid = $row['ContractGuid'];
	echo $guid.' '.$amnt."\n";

	// possibly - divide amount by 4.34, multiply by five, format to 2 dp, only patch if $new != $amnt
	if ($amnt == '4.34') {
		$new = '5.00';
		$comment = 'Amount%20changed%20to%20five%20pounds';
	} else if ($amnt == '8.68') {
		$new = '10.00';
		$comment = 'Amount%20changed%20to%20ten%20pounds';
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
