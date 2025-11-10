<?php

/*
Make sure Paysuite have disabled compulsory email!
first use rsm_to_paysuite.sql to
 * recreate paysuite_mandate containing data from rsm_mandate
 * recreate paysuite_collection containing data from rsm_collection
 * recreate paysuite_transfer_supporter containing data to then be used by this script
 * Check that Statuses are either Active or Inactive
 * Check that there is at least something in address_1, towen, and postcode.
 * "Placeholder" and "XY1 1ZZ" should do.
 * Since SHC there is now a TRIM() on the email address to deal with trailing spaces.
 * And a REPLACE to switch '~' to '-'
 * There is now a Status of 'in_pst' which should suppress the "invalid index"  warning
 * Once everything in paysuite_mandate is either "in_pst" or "Inactive" update in_pst -> Active
 * crucible_ticket_zaffo.blotto_ticket needs updating with new ddref and mandate provider

*/

// then configure
$apf = '/opt/paysuite-api/PayApi.php';
$fns = '/opt/crucible/blotto2/scripts/functions.php';
$cfg = '/opt/crucible/config/shc.cfg.php';
$org = 'shc';

// rehearse to just echo stuff
$rehearse = false;

// then this script gets run

// load up
require $apf;
require $fns;
require $cfg;


// this is adapted (simplified) from payment_mandate.php


$zo = connect (BLOTTO_MAKE_DB);
if (!$zo) {
    exit (101);
}

try {
    $mandate_count  = 0;
    $bad = $good = $tooearly = $toolate = 0;
    // Get new candidates
    $mandates = [];
    $qs = "SELECT * FROM `paysuite_transfer_supporter`";
    try {
        $ms = $zo->query ($qs);
        $mandates = $ms->fetch_all (MYSQLI_ASSOC);
    }
    catch (\mysqli_sql_exception $e) {
        fwrite (STDERR, $qs."\n".$zo->error."\n");
        exit (104);
    }
    if ($rehearse) {
        echo "JUST REHEARSING - FIRST MANDATE OF ".count($mandates)." LOOKS LIKE THIS: ";
        print_r ($mandates[0]);
        echo "\n";
    }
    else {
echo "DRAGONS - COMMENT ME OUT IF YOU DARE!\n";
        $api = new \Blotto\Paysuite\PayApi($zo);
        $api->insert_mandates ($mandates,$bad,$good,$tooearly,$toolate);
    }
    $mandate_count += count ($mandates);
    echo "    Processed $mandate_count mandates\n        $good good, $bad bad";
    if ($tooearly>0) {
        echo ", $tooearly too early";
    }
    if ($toolate>0) {
        echo ", $toolate too late";
    }
}
catch (\Exception $e) {
    fwrite (STDERR,$e->getMessage()."\n");
    if (!$api || !$api->errorCode) {
        // Unexpected error
        exit (104);
    }
    exit ($api->errorCode);
}

echo "\n";
