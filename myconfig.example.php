<?php

define ( 'BLOTTO_PAY_API_PST',          '/some/paysuite-api/PayApi.php'   );
define ( 'BLOTTO_PAY_API_PST_CLASS',    '\Blotto\Paysuite\PayApi'         );

define ( 'PST_LIVE_URL',                'https://ecm3.eazycollect.co.uk/api/v3/client/client_code/' );
define ( 'PST_TEST_URL',                'https://ecm3.eazycollect.co.uk/api/v3/client/client_code/' );
define ( 'PST_URL',                     PST_TEST_URL                      );
define ( 'PST_API_KEY',                 '**********'                      );
define ( 'PST_ERROR_LOG',               false                             );
define ( 'PST_FILE_DEBOGON',            '/my/debogon.sql'                 ); // No bogon-handling feature yet
define ( 'PST_PAY_INTERVAL',            '2 DAY' ); // Ignore recent collections - see BACS behaviour
define ( 'PST_TABLE_MANDATE',           'blotto_build mandate'            );
define ( 'PST_TABLE_COLLECTION',        'blotto_build_collection'         );

/*
 * These are in the settings.py file in the python SDK.
 *
    direct_debit_processing_days = {
        'initial': 10,
        'ongoing': 5,
    }

    contracts = {
        'auto_start_date': False,
        'auto_fix_ad_hoc_termination_type': False,
        'auto_fix_ad_hoc_at_the_end': False,
        'auto_fix_payment_day_in_month': False,
        'auto_fix_payment_month_in_year': False,
    }

    payments = {
        'auto_fix_payment_date': False,
        'is_credit_allowed': False,
    }

    warnings = {
        'customer_search': True,
    }

    other = {
        'bank_holidays_update_days': 30,
        'force_schedule_updates': False,
    }
*/