<?php

// paysuite-api, an Access Paysuite payment class


// Global
// Used by core handling of API - list of mandates where creation should not be attempted
define ( 'BLOTTO_PAY_API_PST_SELECT',   'SELECT DISTINCT(`ClientRef`) AS `crf` FROM `paysuite_mandate` WHERE LENGTH(`ContractGuid`)>0 AND `ContractGuid`!=`ClientRef`' );
define ( 'PST_TABLE_MANDATE',           'blotto_build mandate'            );
define ( 'PST_TABLE_COLLECTION',        'blotto_build_collection'         );
define ( 'PST_PAY_INTERVAL',            '2 DAY' ); // Ignore recent collections - see BACS behaviour
define ( 'PST_PAY_RECENT',              '2 MONTH' ); // Ignore old mandates eg searching for inactive aka pending mandates



// Used by core handling of API
define ( 'BLOTTO_PAY_API_PST',          '/some/paysuite-api/PayApi.php'   );
define ( 'BLOTTO_PAY_API_PST_CLASS',    '\Blotto\Paysuite\PayApi'         );

// This is only required if balances are needed for draws before the migration can be completed
//define ( 'PST_MIGRATE_PREG',            null            ); // ClientRefs like this are in transit
define ( 'PST_MIGRATE_PREG',            '^STG[0-9]+$'   ); // ClientRefs like this are in transit
define ( 'PST_MIGRATE_DATE',            '2022-05-03'    ); // Push the pending data (agree with Paysuite)

define ( 'PST_LIVE_URL',                'https://ecm3.eazycollect.co.uk/api/v3/client/client_code/' );
define ( 'PST_LIVE_API_KEY',            '*************************'       );
define ( 'PST_LIVE_SCHEDULE',           'Standard DD Schedule - Rolling'  );
define ( 'PST_LIVE_SCHEDULE_1',         'Standard DD Schedule - Rolling'  );
define ( 'PST_LIVE_SCHEDULE_3',         '3-Monthly'                       );
define ( 'PST_LIVE_SCHEDULE_6',         '6-Monthly'                       );
define ( 'PST_LIVE_SCHEDULE_12',        '12-Monthly'                      );

define ( 'PST_TEST_URL',                'https://playpen.eazycollect.co.uk/api/v3/client/client_code/' );
define ( 'PST_TEST_API_KEY',            '*************************'       );
define ( 'PST_TEST_SCHEDULE',           'Default Schedule'                );
define ( 'PST_TEST_SCHEDULE_1',         'Default Schedule'                );
define ( 'PST_TEST_SCHEDULE_3',         '3-Monthly'                       );
define ( 'PST_TEST_SCHEDULE_6',         '6-Monthly'                       );
define ( 'PST_TEST_SCHEDULE_12',        '12-Monthly'                      );

define ( 'PST_URL',                     PST_TEST_URL                      );
define ( 'PST_API_KEY',                 PST_TEST_API_KEY                  );
define ( 'PST_SCHEDULE',                PST_TEST_SCHEDULE                 );
define ( 'PST_SCHEDULE_1',              PST_TEST_SCHEDULE_1               );
define ( 'PST_SCHEDULE_3',              PST_TEST_SCHEDULE_3               );
define ( 'PST_SCHEDULE_6',              PST_TEST_SCHEDULE_6               );
define ( 'PST_SCHEDULE_12',             PST_TEST_SCHEDULE_12              );

define ( 'PST_ERROR_LOG',               false                             );
//define ( 'PST_FILE_DEBOGON',            '/my/debogon.sql'                 ); // No bogon-handling feature yet

// What to add to mandate ID to provide an integer RefNo for the core code
define ( 'PST_REFNO_OFFSET',            100000000                         );

