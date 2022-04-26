<?php

namespace Blotto\Paysuite;

class PayApi {

    private  $bogon_file;
    private  $connection;
    public   $constants = [
                'PST_URL',
                'PST_API_KEY',
                'PST_ERROR_LOG',
                'PST_FILE_DEBOGON',
                'PST_PAY_INTERVAL',
                'PST_REFNO_OFFSET',
                'PST_TABLE_MANDATE',
                'PST_TABLE_COLLECTION',
             ];
    public   $schedules = [
                 1 => PST_SCHEDULE_1,
                 3 => PST_SCHEDULE_3,
                 6 => PST_SCHEDULE_6,
                 12 => PST_SCHEDULE_12,
                 'Monthly' => PST_SCHEDULE_1,
                 'Quarterly' => PST_SCHEDULE_3,
                 '6 Monthly' => PST_SCHEDULE_6,
                 'Annually' => PST_SCHEDULE_12,
             ];
    public   $database;
    public   $diagnostic;
    public   $error;
    public   $errorCode = 0;


    public function __construct ($connection,$org=null) {
        $this->connection   = $connection;
        $this->org          = $org;
        $this->setup ();
    }

    public function __destruct ( ) {
    }

    private function curl_delete ($path,$options=[]) {
    /*
        * Send a DELETE request using cURL
        * @param string $path to request
        * @return string
    */
        if (!is_array($options)) {
            throw new \Exception ('Params and option arguments must be arrays');
            return false;
        }
        $options += [
            CURLOPT_CUSTOMREQUEST => 'DELETE'
        ];
        $result = $this->curl_function($path, $options);
        return $result;
    }

    // errors look like {"ErrorCode":7,"Detail":null,"Message":"API not enabled"}
    private function curl_function ($path,$options=[]) {
    /*
        * Send a generic request using cURL
        * @param string $path to request
        * @param array $options for cURL
        * @return string
    */
        $url = PST_URL.$path;
        $headers = [
            'Accept: application/json',
            'apiKey: '.PST_API_KEY,
            'Content-Type: application/x-www-form-urlencoded', //application/json',
            //'Content-Type: application/json', 

        ];
        $defaults = [
            CURLOPT_HEADER => 0,
            CURLOPT_URL => $url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => $headers,
            //CURLOPT_HEADER => true,
            CURLINFO_HEADER_OUT => true,
        ];
        $ch = curl_init ();
        curl_setopt_array ($ch,$options+$defaults);

        if (!$result=curl_exec($ch)) {
            $this->error_log (127,curl_error($ch));
            throw new \Exception ("cURL error ".print_r(curl_error($ch), true));
            return false;
        }
        $outHeaders = explode("\n", curl_getinfo($ch, CURLINFO_HEADER_OUT));
        $outHeaders = array_filter($outHeaders, function($value) { return $value !== '' && $value !== ' ' && strlen($value) != 1; });
        //print_r($outHeaders);
        curl_close ($ch);
        return json_decode($result);
    }

    private function curl_get ($path,$params=[],$options=[]) {
    /*
        * Send a GET request using cURL
        * @param string $path to request
        * @param array $params query string parameters (in form "foo"=>"bar")
        * @param array $options for cURL
        * @return string
    */
        if (!is_array($params) || !is_array($options)) {
            throw new \Exception ('Params and option arguments must be arrays');
            return false;
        }
        if (count($params)) {
            $path .= '?'.http_build_query($params);
        }
        $result = $this->curl_function($path, $options);
        return $result;
    }


    private function curl_patch ($path,$post,$options=[]) {
    /*
        * Send a POST requst using cURL
        * @param string $path to request
        * @param array $post values to send
        * @param array $options for cURL
        * @return string
    */
        if (!is_array($post) || !is_array($options)) {
            throw new \Exception ('Post and option arguments must be arrays');
            return false;
        }
        $post_options = array (
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            //CURLOPT_POSTFIELDS => json_encode ($post)
        );
        $options += $post_options;
        $path .= '?'.http_build_query($post);
        $result = $this->curl_function($path, $options);
        return $result;
    }

    private function curl_post ($path,$post,$options=[]) {
    /*
        * Send a POST requst using cURL
        * @param string $path to request
        * @param array $post values to send
        * @param array $options for cURL
        * @return string
    */
        if (!is_array($post) || !is_array($options)) {
            throw new \Exception ('Post and option arguments must be arrays');
            return false;
        }
        $post_options = array (
            CURLOPT_POST => true,
            //CURLOPT_POSTFIELDS => http_build_query ($post, null, '&', PHP_QUERY_RFC3986) // this was no good
            //CURLOPT_POSTFIELDS => json_encode ($post) // this was better until I tried to set the callback URL
        );
        $options += $post_options;
        $path .= '?'.http_build_query($post); // so sheesh, even POST needs to be a query string...
        $result = $this->curl_function($path, $options);
        return $result;
    }

    private function error_log ($code,$message) {
        $this->errorCode    = $code;
        $this->error        = $message;
        if (!defined('PST_ERROR_LOG') || !PST_ERROR_LOG) {
            return;
        }
        error_log ($code.' '.$message);
    }

    private function execute ($sql_file) {
        echo $sql_file;
        $sql = file_get_contents ($sql_file);
        try {
            $result = $this->connection->query ($sql);
        }
        catch (\mysqli_sql_exception $e) {
            print_r($e);
            $this->error_log (126,'SQL execute failed: '.$e->getMessage());
            throw new \Exception ('SQL execution error');
            return false;
        }
        return $result;
    }

    private function fetch_collections ($m) {
        // TODO: this is where top down should meet bottom up eg return value:
        $params = ['rows' => 10];
        $response = $this->curl_get('contract/'.$m['ContractGuid'].'/payment');

        $collections = [];
        if (isset($response->Payments)) {
            foreach ($response->Payments as $p) {
                if ($p->Status=='Paid') { // TODO: ignore recent payments? see docs.
                    $collections[] = [
                        'payment_guid' => $p->Id,
                        'date_collected' => substr($p->Date, 0, 10),
                        'amount' => $p->Amount
                    ];
                }
            }
        }
        return $collections;

        /*$collections = [
            [
                'payment_guid' => 'abc-xyz',
                'date_collected' => '2022-02-01',
                'amount' => 4.34
            ]
        ];
        return $collections;*/
    }

    // Q: an old development test or needed?
    // A: might yet prove useful...
    private function get_bacs_endpoints ( ) {
        foreach (['customer', 'contract', 'payment', 'schedule'] as $entity) {
            $endpoints[$entity] = $this->curl_get ('BACS/'.$entity.'/callback');
        }
        print_r ($endpoints);
        return $endpoints;
    }

    public function import ( ) {
//$this->test_customer();
//$this->test_callback();
//$this->test_schedule ();
//$this->test_contract ();
//return;
        // Get all the mandates
        $sql = "
          SELECT
            `MandateId`
           ,`ContractGuid`
           ,`ClientRef`
          FROM `paysuite_mandate`
          ORDER BY `MandateId`
        ";
        try {
            $result = $this->connection->query ($sql);
            while ($m=$result->fetch_assoc()) {
                // Insert recent collections for this mandate
                $this->load_collections ($m);
            }
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (125,'SQL execute failed: '.$e->getMessage());
            throw new \Exception ('SQL execution error');
            return false;
        }
        $this->output_mandates ();
        $this->output_collections ();
    }

    private function insert_mandate ($m)  {
        // Customer ( == player )
        if (!array_key_exists('CustomerGuid',$m) || !$m['CustomerGuid']) {
            $this->put_customer ($m);
            if (!$m['CustomerGuid']) {
                throw new \Exception ("Cannot complete mandate {$m['ClientRef']} without customer GUID");
                return false;
            }
            $sql = "
              UPDATE `paysuite_mandate`
              SET
                `CustomerGuid`='{$m['CustomerGuid']}'
              WHERE `ClientRef`='{$m['ClientRef']}'
              LIMIT 1
              ;
            ";
            try {
                echo $sql."\n";
                $result = $this->connection->query ($sql);
                if ($this->connection->affected_rows!=1) {
                    $this->error_log (123,"API update mandate [1] '{$m['ClientRef']}' - no affected rows");
                    throw new \Exception ("API update mandate [1] '{$m['ClientRef']}' - no affected rows");
                    return false;
                }
            }
            catch (\mysqli_sql_exception $e) {
                $this->error_log (122,"API update mandate [2] '{$m['ClientRef']}' failed: ".$e->getMessage());
                throw new \Exception ("API update mandate [2] '{$m['ClientRef']}' failed: ".$e->getMessage());
                return false;
            }
        }
        // Contract ( == mandate )
        if (!array_key_exists('ContractGuid',$m) || !$m['ContractGuid']) {
            $this->put_contract ($m);
            if (!$m['ContractGuid']) {
                throw new \Exception ("Cannot complete mandate {$m['ClientRef']} without contract GUID");
                return false;
            }
            $sql = "
              UPDATE `paysuite_mandate`
              SET
                `ContractGuid`='{$m["ContractGuid"]}'
               ,`DDRefOrig`='{$m["DDRefOrig"]}'
               ,`Status` = '{$m["Status"]}'
               ,`FailReason` = '{$m["FailReason"]}'
              WHERE `ClientRef`='{$m['ClientRef']}'
              LIMIT 1
              ;
            ";
            try {
                echo $sql."\n";
                $result = $this->connection->query ($sql);
                if ($this->connection->affected_rows!=1) {
                    $this->error_log (121,"API update mandate [3] '{$m['ClientRef']}' - no affected rows");
                    throw new \Exception ("API update mandate [3] '{$m['ClientRef']}' - no affected rows");
                    return false;
                }
            }
            catch (\mysqli_sql_exception $e) {
                $this->error_log (120,"API update mandate [4] '{$m['ClientRef']}' failed: ".$e->getMessage());
                throw new \Exception ("API update mandate [4] '{$m['ClientRef']}' failed: ".$e->getMessage());
                return false;
            }
        }
    }

    public function insert_mandates ($mandates)  {
        if (!count($mandates)) {
            fwrite (STDERR,"No mandates to insert\n");
            return true;
        }
        $good = $bad = 0; // for summary email
        $body = '';
        foreach ($mandates as $m) {
            $ok = false;
            if (!in_array($mandate['Freq'],['1','M','Monthly','OneMonthly'])) {
                $msg = "Freq={$m['Freq']} is not currently supported for ClientRef={$m['ClientRef']}";
                $this->error_log (119,$msg);
                fwrite (STDERR,"$msg\n");
            }
            elseif ($m['PayDay']) {
                $m['StartDate'] = collection_startdate (date('Y-m-d'),$m['PayDay']);
                $sql = "
                  SELECT
                    *
                  FROM `paysuite_mandate`
                  WHERE `ClientRef`='{$m['ClientRef']}'
                  LIMIT 0,1
                  ;
                ";
                try {
                    $result = $this->connection->query ($sql);
                }
                catch (\mysqli_sql_exception $e) {
                    $this->error_log (118,'SQL select failed: '.$e->getMessage());
                }
                if ($result) {
                    if ($result->num_rows==0) {
                        // This is a new row for paysuite_mandate
                        $esc = [];
                        foreach ($m as $k=>$v) {
                            if ($k=='Name') {
                                // ErrorCode 3 - Account holder name must contain only:
                                // upper case letters (A-Z), numbers (0-9), full stop (.),
                                // forward slash (/), dash (-), Ampersand (&) and space
                                $v = preg_replace ('<[^A-z0-9\./\-& ]>','',$v);
                                $m[$k] = $v;
                            }
                            $esc[$k] = $this->connection->real_escape_string ($v);
                        }
                        $sql = "
                          INSERT INTO `paysuite_mandate`
                          SET
                            `ClientRef`='{$esc['ClientRef']}'
                           ,`Name`='{$esc['Name']}'
                           ,`Sortcode`='{$esc['SortCode']}'
                           ,`Account`='{$esc['Account']}'
                           ,`StartDate`='{$esc['StartDate']}'
                           ,`Freq`='{$esc['Freq']}'
                           ,`Amount`='{$esc['Amount']}'
                           ,`ChancesCsv`='{$esc['Chances']}'
                          ON DUPLICATE KEY UPDATE
                            `ClientRef`='{$esc['ClientRef']}'
                          ;
                        ";
                        echo $sql."\n";
                        // Insert a new mandate at this end
                        try {
                            $this->connection->query ($sql);
                        }
                        catch (\mysqli_sql_exception $e) {
                            $this->error_log (117,'SQL insert failed: '.$e->getMessage());
                            fwrite (STDERR,"SQL insert failed: ".$e->getMessage()."\n");
                        }
                    }
                    else {
                        // This is already a row in paysuite_mandate
                        $row = $result->fetch_assoc ();
                        $m['CustomerGuid']  = $row['CustomerGuid'];
                        $m['ContractGuid']  = $row['ContractGuid'];
                        $m['Name']          = $row['Name']; // Sanitised at insert above
                    }
                    try {
                        // Insert a new mandate at that end
                        $this->insert_mandate ($m);
                        $ok = true;
                    }
                    catch (\Exception $e) {
                        $this->error_log (116,'insert_mandate() failed: '.$e->getMessage());
                    }
                }

            }
            else {
                $msg = "PayDay={$m['PayDay']} is not valid for ClientRef={$m['ClientRef']}";
                $this->error_log (115,$msg);
                fwrite (STDERR,"$msg\n");
            }
            if ($ok) {
                $good++;
                $body .= $m['ClientRef']." SUCCESS\n";
            }
            else {
                $bad++;
                $body .= $m['ClientRef']." FAIL\n";
                if (!array_key_exists('CustomerGuid',$m) || !$m['CustomerGuid']) {
                    $body .= "No customer entity created.\n";
                }
                elseif (!array_key_exists('ContractGuid',$m) || !$m['ContractGuid']) {
                    $body .= "No contract entity created.\n";
                }
                if (array_key_exists('FailReason',$m) && $m['FailReason']) {
                    $body .= $m['FailReason']."\n";
                }
            }
        }
        // send
        $subj = "Paysuite insert mandates for ".strtoupper(BLOTTO_ORG_USER).", $good good, $bad bad";
        mail (BLOTTO_EMAIL_WARN_TO,$subj,$body);
        return true;
    }

    private function load_collections ($m)  {
/* Example:
return true;
$m = [
    'MandateId' => 1234
    'ContractGuid' => 'b4da372f-893f-47ea-89fb-f90d6c30d370'
    'ClientRef' => 'BB5273_227740'
];
*/




// This is only required if balances are needed for draws before the migration can be completed
//define ( 'PST_MIGRATE_PREG',            null            ); // ClientRefs like this are in transit
define ( 'PST_MIGRATE_PREG',            '^STG[0-9]+$'   ); // ClientRefs like this are in transit
define ( 'PST_MIGRATE_DATE',            '2022-05-03'    ); // Pass the pending data to Paysuite (agreed with Paysuite)





        // The remote bit
        $collections = $this->fetch_collections ($m);
        // The local bit
        $esc = [];
        foreach ($m as $k=>$v) {
            $esc[$k] = $this->connection->real_escape_string ($v);
        }
        foreach ($collections as $c) {
/* Example:
$c = [
    'payment_guid' => '93fef2b8-e553-4a7a-aa88-f17b61bf787a'
    'date_collected' => '2022-04-15'
    'amount' => 8.68
];
*/
            // Payment GUID is unique
            // Do nothing on duplicate key
            foreach ($c as $k=>$v) {
                $esc[$k] = $this->connection->real_escape_string ($v);
            }
            $sql = "
              INSERT INTO `paysuite_collection`
              SET
                `MandateId`='{$esc["MandateId"]}'
               ,`ClientRef`='{$esc["ClientRef"]}'
               ,`PaymentGuid`='{$esc["payment_guid"]}'
               ,`DateDue`='{$esc["date_collected"]}'
               ,`Amount`='{$esc["amount"]}'
              ON DUPLICATE KEY UPDATE
                `PaymentGuid`='{$esc["payment_guid"]}'
              ;
            ";
            try {
                echo $sql."\n";
                $this->connection->query ($sql);
            }
            catch (\mysqli_sql_exception $e) {
                $this->error_log (114,"API insert collection '{$m['ClientRef']}-{$c["payment_guid"]}' failed: ".$e->getMessage());
                throw new \Exception ("API insert collection '{$m['ClientRef']}-{$c["payment_guid"]}' failed: ".$e->getMessage());
                return false;
            }
        }
    }

    private function output_collections ( ) {
        $sql                = "INSERT INTO `".PST_TABLE_COLLECTION."`\n";
        $sql               .= file_get_contents (__DIR__.'/select_collection.sql');
        $sql                = str_replace ('{{PST_PAY_INTERVAL}}',PST_PAY_INTERVAL,$sql);
        $sql                = str_replace ('{{PST_REFNO_OFFSET}}',PST_REFNO_OFFSET,$sql);
        echo $sql;
        try {
            $this->connection->query ($sql);
            tee ("Output {$this->connection->affected_rows} collections\n");
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (113,'SQL insert failed: '.$e->getMessage());
            throw new \Exception ('SQL error');
            return false;
        }
    }

    private function output_mandates ( ) {
        $rows = $this->connection->query ("show tables");
        while ($r=$rows->fetch_assoc()) {
            print_r($r);
        }
        $sql                = "INSERT INTO `".PST_TABLE_MANDATE."`\n";
        $sql               .= file_get_contents (__DIR__.'/select_mandate.sql');
        $sql                = str_replace ('{{PST_REFNO_OFFSET}}',PST_REFNO_OFFSET,$sql);
        echo $sql;
        try {
            $this->connection->query ($sql);
            tee ("Output {$this->connection->affected_rows} mandates\n");
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (112,'SQL insert failed: '.$e->getMessage());
            throw new \Exception ('SQL error '.$e->getMessage());
            return false;
        }
    }

    private function put_contract (&$mandate) {
        $mandate['FailReason'] = "";
        print_r ($mandate);
        if (!array_key_exists('CustomerGuid',$mandate) || !$mandate['CustomerGuid']) {
            throw new \Exception ("Cannot put contract for {$mandate['ClientRef']} without a customer GUID");
            return false;
        }
        if (PST_MIGRATE_PREG && preg_match('<'.PST_MIGRATE_PREG.'>',$mandate['ClientRef'])) {
            // This is only required if balances are needed for draws before the migration can be completed
            if (gmdate('Y-m-d')<PST_MIGRATE_DATE) {
                // Only pretend to put the mandate until migration day
                fwrite (STDERR,"WARNING: paysuite-api providing fake contract GUID={$mandate['ClientRef']}\n");
                $mandate['ContractGuid'] = $mandate['ClientRef'];    
                fwrite (STDERR,"WARNING: paysuite-api providing fake DDRefOrig={$mandate['ClientRef']}\n");
                $mandate['DDRefOrig'] = $mandate['ClientRef'];    
                return true;
            }
        }
        if (!array_key_exists($mandate['Freq'],$this->schedules)) {
            throw new \Exception ("No schedule found for mandate frequency '{$mandate['Freq']}'");
            return false;
        }
        $paymentMonthInYear = intval (substr($mandate['StartDate'], 5, 2));
        $paymentDayInMonth = intval (substr($mandate['StartDate'], 8, 2));
        $details = [
            'scheduleName' => $this->schedules[$mandate['Freq']], // required (Either Name or ID)
            'start' => $mandate['StartDate'].'T00:00:00.000', // docs say to pass a microsecond value!
            'isGiftAid' => 'false', // required 
            'amount' => $mandate['Amount'],
            'paymentMonthInYear' => $paymentMonthInYear, // must match start date
            'paymentDayInMonth' => $paymentDayInMonth,  // must match start date
            'terminationType' => 'Until further notice', // required 
            'atTheEnd' => 'Switch to Further Notice', // required 
            'additionalReference' => $mandate['Chances'], // used for chances
        ];
        print_r ($details);
        $response = $this->curl_post ("customer/{$mandate['CustomerGuid']}/contract",$details);
        print_r ($response); // for now, dump to log file
        // two stages of error handling because Paysuite give us two independent error types
        if (isset( $response->error)) { // e.g.  The requested resource is not found
            $mandate['FailReason'] = $response->error;
            throw new \Exception ($mandate['FailReason']);
            return false;
        }
        if (isset( $response->ErrorCode)) { // e.g. badly formatted date
            $mandate['FailReason'] = $response->ErrorCode.'. '.$response->Message.': '.$response->Detail;
            throw new \Exception ($mandate['FailReason']);
            return false;
        }
        $mandate['ContractGuid'] = $response->Id;    
        $mandate['DDRefOrig'] = $response->DirectDebitRef;    
        return true;
    }

    private function put_customer (&$mandate) {
        $mandate['FailReason'] = '';
        if (PST_MIGRATE_PREG && preg_match('<'.PST_MIGRATE_PREG.'>',$mandate['ClientRef'])) {
            // This is only required if balances are needed for draws before the migration can be completed
            if (gmdate('Y-m-d')<PST_MIGRATE_DATE) {
                // Only pretend to put the mandate until migration day
                fwrite (STDERR,"WARNING: paysuite-api providing fake customer GUID={$mandate['ClientRef']}\n");
                $mandate['CustomerGuid'] = $mandate['ClientRef'];    
                return true;
            }
        }
        $sort = preg_replace ('/\D/','',$mandate['SortCode']);
        $details = [
            'Email' => $mandate['Email'],
            'Title' => $mandate['Title'],
            'CustomerRef' => $mandate['ClientRef'],
            'FirstName' => $mandate['NamesGiven'],
            'Surname' => $mandate['NamesFamily'],
// TODO: confirm these lengths are different
            'Line1' => substr ($mandate['AddressLine1'],0,50),
            'Line2' => substr ($mandate['AddressLine2'],0,30),
            'PostCode' => $mandate['Postcode'],
            'AccountNumber' => $mandate['Account'],
            'BankSortCode' => $sort,
            'AccountHolderName' => $mandate['Name']
        ];
        // optional
        if (strlen($mandate['AddressLine3'])) {
            $details['Line3'] = substr ($mandate['AddressLine3'],0,30);
        }
        print_r ($details); // for now, dump to log file
        $response = $this->curl_post ('customer',$details);
        print_r ($response); // for now, dump to log file
        // two stages of error handling because Paysuite give us two independent error types
        if (isset( $response->error)) { // e.g.  The requested resource is not found
            $mandate['FailReason'] = $response->error;
            throw new \Exception ($mandate['FailReason']);
            return false;
        }
        if (isset( $response->ErrorCode)) { // e.g. badly formatted date
            $mandate['FailReason'] = $response->ErrorCode.'. '.$response->Message.': '.$response->Detail;
            throw new \Exception ($mandate['FailReason']);
            return false;
        }
        $mandate['CustomerGuid'] = $response->Id;    
        return true;
    }

    public function reset_fakes ( ) {
        // A migration method
        if (defined(PST_MIGRATE_PREG) && PST_MIGRATE_PREG) {
            if (!defined(PST_MIGRATE_DATE) || !PST_MIGRATE_DATE) {
                $this->error_log (111,'Migration has no PST_MIGRATE_DATE');
                throw new \Exception ('Migration has no PST_MIGRATE_DATE');
                return true;
            }
            $migrate_date = new \DateTime (PST_MIGRATE_DATE);
            if ($migrate_date->format('Y-m-d')!=PST_MIGRATE_DATE) {
                $mandate['FailReason'] = "";
                $this->error_log (110,'Migration does not understand PST_MIGRATE_DATE='.PST_MIGRATE_DATE);
                throw new \Exception ('Migration does not understand PST_MIGRATE_DATE='.PST_MIGRATE_DATE);
                return true;
            }
            if (gmdate('Y-m-d')>=PST_MIGRATE_DATE) {
                // So only when migration day is reached
                // clear fake datas in order to allow put_*() for creation of migrating customers/contracts
                $r = PST_MIGRATE_PREG;
                $t = PST_TABLE_MANDATE;
                $sql = "
                  UPDATE `$t`
                  SET
                    `CustomerGuid`=''
                   ,`ContractGuid`=''
                   ,`DDRefOrig`=''
                  WHERE `ClientRef` IS NOT NULL
                    AND LENGTH(`ClientRef`)>0
                    AND `CustomerGuid`=`ClientRef`
                    AND `ClientRef` REGEXP '$r'
                  ;
                ";
                try {
                    $this->connection->query ($sql);
                }
                catch (\mysqli_sql_exception $e) {
                    $this->error_log (109,'SQL update failed: '.$e->getMessage());
                    throw new \Exception ('SQL update error');
                    return false;
                }
            }
        }
        return true;
    }

    private function setup ( ) {
        foreach ($this->constants as $c) {
            if (!defined($c)) {
                $this->error_log (108,"$c not defined");
                throw new \Exception ('Configuration error');
                return false;
            }
        }
        $sql                = "SELECT DATABASE() AS `db`";
        try {
            $db             = $this->connection->query ($sql);
            $db             = $db->fetch_assoc ();
            $this->database = $db['db'];
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (107,'SQL select failed: '.$e->getMessage());
            throw new \Exception ('SQL database error');
            return false;
        }
        // Missing tables
        $this->execute (__DIR__.'/create_mandate.sql');
        $this->execute (__DIR__.'/create_collection.sql');
    }

    private function table_load ($data,$tablename,$fields) {
        $sql                = "INSERT INTO ".$tablename." (`".implode('`, `', $fields)."`) VALUES\n";
        foreach ($data as $record) {
            $dbline         = [];
            foreach ($fields as $srcname=>$destname) {
                if (is_array($record[$srcname])) {
                    $record[$srcname] = '';
                }
                $dbline[]   = $this->connection->real_escape_string (trim($record[$srcname]));
            }
            $sql           .= "('".implode("','", $dbline)."'),\n";
        }
        $sql                = substr ($sql,0,-2);
        try {
            $this->connection->query ($sql);
            echo "Inserted {$this->connection->affected_rows} rows into `$tablename`\n";
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (106,'SQL insert failed: '.$e->getMessage());
            throw new \Exception ('SQL insert error');
            return false;
        }
    }

    private function test_callback() {
        $r = $this->curl_get('BACS/contract/callback');
        echo "\nget: ";print_r($r);
        /*$r = $this->curl_post('BACS/contract/callback', ['url' => '']);
        echo "\npost: ";print_r($r);
        $r = $this->curl_get('BACS/contract/callback');
        echo "\nget: ";print_r($r);
        $r = $this->curl_delete('BACS/contract/callback');
        echo "\ndelete: ";print_r($r);
        $r = $this->curl_get('BACS/contract/callback');
        echo "\n5get: ";print_r($r);*/
    }

    private function test_contract() {
        $customer_guid = '3a02c36f-65dd-4569-ad7f-f7d420d56cdd';
        $details = [

            "scheduleName" => "Default Schedule", // required (Either Name or ID)
            //"scheduleId" => "", // required 
            "start" => "2022-04-01T00:00:00.000", // required, yes the docs say to pass a microsecond value!
            // "numberOfDebits" => "", used if it's a "take certain number"
            // "every" => "", use this to do every three months and so on
            "isGiftAid" => "false", // required 
            //"initialAmount" => "", if different to normal
            //"extraInitialAmounts" => "", e.g. for a registration fee
            "amount" => "4.34",
            //"finalAmount" => "",
            "paymentMonthInYear" => "4", // must match start date
            "paymentDayInMonth" => "1",  // must match start date
            //"paymentDayInWeek" => "", for weekly contracts
            "terminationType" => "Until further notice", // required 
            "atTheEnd" => "Switch to Further Notice", // required 
            //"terminationDate" => "",
            "additionalReference" => "1", // used for chances
            //"customDirectDebitRef" => "", only to be used if instructed to do so!

        ];

        //$r = $this->curl_post('customer/'.$customer_guid.'/contract', $details);
        //echo "\npost: ";print_r($r);

        $r = $this->curl_get('customer/'.$customer_guid.'/contract');
        echo "\nget: ";print_r($r);


    }

/*
    Bad response
    [ErrorCode] => 3
    [Detail] => There is an existing Customer with the same Client and CustomerRef in the database already.
    [Message] => Validation error

    Good response
    [CustomerRef] => BB1234_1235
    [Id] => 908a0f38-6b38-42d1-8f16-1972ddaff594
    [Message] => 
*/

    private function test_customer() {
        $details = [
            "Email" => "", //john.doe@test.com
            "Title" => "Mr",
            "CustomerRef" => "BB1234_1235", // client_ref
            "FirstName" => "John",
            "Surname" => "Doe",
            "Line1" => "1 Tebbit Mews",
            "Line2" => "Winchcombe Street",
            "PostCode" => "GL52 2NF",
            "AccountNumber" => "12345678",
            "BankSortCode" => "123456",
            "AccountHolderName" => "Mr John Doe"
        ];

        $patchdetails = [
            "CustomerRef" => "BB1234_1234", // client_ref
            "AccountNumber" => "82345671",
            "BankSortCode" => "823456",
        ];

        //$r = $this->curl_post('customer', $details);
        //echo "\npost: ";print_r($r);

        //$r = $this->curl_delete('customer/798e5d5c-c4a8-4375-9a42-06a8002110ed');
        //echo "\ndelete: ";print_r($r);

        //$r = $this->curl_patch('customer/3a02c36f-65dd-4569-ad7f-f7d420d56cdd', $patchdetails);
        //echo "\npatch: ";print_r($r);

        $r = $this->curl_get('customer');
        echo "\nget: "; // ->Customers[2]
        foreach ($r->Customers as $c) {
            echo $c->CustomerRef.' '.$c->Id."\n";
        }

    }

    private function test_schedule() {
        $r = $this->curl_get('schedules');
        echo "\nget: "; print_r($r);
    }

}
