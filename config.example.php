<?php

define ( 'BLOTTO_PAY_API_PST',          '/some/paysuite-api/PayApi.php'   );
define ( 'BLOTTO_PAY_API_PST_CLASS',    '\Blotto\Paysuite\PayApi'         );

define ( 'PST_CODE',                    'PST' );
define ( 'PST_LIVE_URL',                'https://ecm3.eazycollect.co.uk/api/v3/client/client_code/' );
define ( 'PST_TEST_URL',                'https://ecm3.eazycollect.co.uk/api/v3/client/client_code/' );
define ( 'PST_URL',                     PST_TEST_URL                      );
define ( 'PST_LIVE_API_KEY',            '**********'                      );
define ( 'PST_TEST_API_KEY',            '**********'                      );
define ( 'PST_API_KEY',                 '**********'                      );
define ( 'PST_ERROR_LOG',               PST_TEST_API_KEY                  );
define ( 'PST_FILE_DEBOGON',            '/my/debogon.sql'                 ); // No bogon-handling feature yet
define ( 'PST_PAY_INTERVAL',            '2 DAY' ); // Ignore recent collections - see BACS behaviour
define ( 'PST_TABLE_MANDATE',           'blotto_build mandate'            );
define ( 'PST_TABLE_COLLECTION',        'blotto_build_collection'         );
