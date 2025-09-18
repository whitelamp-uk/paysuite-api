<?php

/*
first use rsm_to_paysuite.sql to
 * recreate paysuite_mandate containing data from rsm_mandate
 * recreate paysuite_collection containing data from rsm_collection
 * recreate paysuite_transfer_supporter containing data to then be used by this script
*/

// then configure
$apf = '/opt/paysuite-api/PayApi.php';
$fns = '/opt/crucible/blotto2/scripts/functions.php';
$cfg = '/opt/crucible/config/shc.cfg.php';
$org = 'shc';

// rehearse to just echo stuff
$rehearse = true;



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
/*
    if (method_exists($api,'reset_fakes')) {
        // Migrating mandates
        $api->reset_fakes ();
    }
*/
    // Get new candidates
    $mandates = [];
    $select = BLOTTO_PAY_API_PST_SELECT;
    $qs = "
      SELECT
        `cand`.*
--      FROM `tmp_supporter` AS `cand`
      FROM `paysuite_transfer_supporter` AS `cand`
      JOIN `blotto_supporter` AS `s`
        ON `s`.`client_ref`=`cand`.`ClientRef`
       AND `s`.`mandate_blocked`=0
      LEFT JOIN (
        $select
      ) AS `m`
        ON `m`.`crf`=`cand`.`ClientRef`
      -- No mandate exists
      WHERE `m`.`crf` IS NULL
        AND (
             0
          OR `s`.`inserted`>DATE_SUB(NOW(),INTERVAL $interval)
      )
    ";
    try {
        $errors = [];
        $ms = $zo->query ($qs);
        while ($m=$ms->fetch_assoc()) {
/*
            if (territory_permitted($m['Postcode'])) {
                $mandates[] = $m;
            }
            else {
                $e = "Postcode '{$m['Postcode']}' is outside territory '".BLOTTO_TERRITORIES_CSV."' - $ccc - for '{$m['ClientRef']}'\n";
                fwrite (STDERR,$e);
                $errors[] = $e;
            }
*/
// instead
            $mandates[] = $m;
        }
        if ($count=count($errors)) {
            $message = "The following $count mandates have been rejected:\n";
            foreach ($errors as $e) {
                $message .= $e;
            }
/*
            notify (BLOTTO_EMAIL_WARN_TO,"$count rejected mandates",$message);
*/
//instead:
echo $message."\n";
            $bad += $count;
        }
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
//        $api->insert_mandates ($mandates,$bad,$good,$tooearly,$toolate);
    }
    $mandate_count += count ($mandates);
    echo "    Processed $mandate_count mandates using $class\n        $good good, $bad bad";
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

