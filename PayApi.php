<?php

namespace Blotto\Paysuite;

class PayApi {
    private     $status_types = []; // collect all combinations of statuses and types.
    private     $bogon_file;
    private     $connection;
    public      $constants = [
                    'PST_URL',
                    'PST_API_KEY',
                    'PST_ERROR_LOG',
                    'PST_FILE_DEBOGON',
                    'PST_PAY_INTERVAL',
                    'PST_REFNO_OFFSET',
                    'PST_TABLE_MANDATE',
                    'PST_TABLE_COLLECTION',
                ];
    public      $schedules = [
                    '1' => PST_SCHEDULE_1,
                    '3' => PST_SCHEDULE_3,
                    '6' => PST_SCHEDULE_6,
                    '12' => PST_SCHEDULE_12,
                    'M' => PST_SCHEDULE_1,
                    'Q' => PST_SCHEDULE_3,
                    'S' => PST_SCHEDULE_6,
                    'Y' => PST_SCHEDULE_12,
                    'Monthly' => PST_SCHEDULE_1,
                    'Quarterly' => PST_SCHEDULE_3,
                    '6 Monthly' => PST_SCHEDULE_6,
                    'Annually' => PST_SCHEDULE_12,
                    'OneMonthly' => PST_SCHEDULE_1,
                    'ThreeMonthly' => PST_SCHEDULE_3,
                    'SixMonthly' => PST_SCHEDULE_6,
                    'TwelveMonthly' => PST_SCHEDULE_12,
                ];
    public      $database;
    public      $dd_before;
    public      $diagnostic;
    public      $error;
    public      $errorCode = 0;
    protected   $simulateMode;


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
        $result = $this->curl_function ($path,$options);
        return $result;
    }

    /*
        * Send a generic request using cURL
        * @param string $path to request
        * @param array $options for cURL
        * @return string
    */
    private function curl_function ($path,$options=[]) {
        // curl errors look like {"ErrorCode":7,"Detail":null,"Message":"API not enabled"}
        try {
            if ($result=$this->simulate()) {
                return $result;
            }
        }
        catch (\Exception $e) {
            // $this->simulate() honours PST_SIMULATE and $this->simulateMode
            // However if it throws an exception, bail out
            return false;
        }
        $url = PST_URL.$path;
        $headers = [
            'Accept: application/json',
            'apiKey: '.PST_API_KEY,
            'Content-Type: application/x-www-form-urlencoded', //application/json',
            //'Content-Type: application/json', 
            "Transfer-Encoding: ",  // fix php 7.4 bug https://bugs.php.net/bug.php?id=79013
            //"Content-Length: 0", needed? Or does curl work it out for you (likely)
        ];
        // if using verbose logging
        // $fperr = fopen('/home/dom/curlerr', 'w+'); 
        $defaults = [
            CURLOPT_HEADER => 0,
            CURLOPT_URL => $url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => $headers,
            //CURLOPT_HEADER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, // seems to be another php 7.4 bug
            //enable if printing out headers as below; cannot be used at same time as verbose output
            //CURLINFO_HEADER_OUT => true, 
            //CURLOPT_VERBOSE => true,
            //CURLOPT_STDERR => $fperr,
        ];
        $ch = curl_init ();
        curl_setopt_array ($ch,$options+$defaults);

        while (!($result=curl_exec($ch))) {
            /*echo "curl_getinfo:";
            print_r(curl_getinfo($ch));
            echo "curlerr:";
            rewind ($fperr);
            echo stream_get_contents($fperr);
            ftruncate($fperr, 0);*/
            if (curl_errno($ch)==CURLE_OPERATION_TIMEDOUT) { // CURLE_not a typo
                $this->error_log (127,curl_error($ch));
            }
            else {
                $this->error_log (126,curl_error($ch));
            }
            if (++$attempts >= BLOTTO_CURL_ATTEMPTS) {
                //fclose($fperr);
                curl_close ($ch);
                throw new \Exception ("cURL error ".print_r(curl_error($ch), true));
                return false;
            }
        }

        /*
        $outHeaders = explode ("\n", curl_getinfo($ch,CURLINFO_HEADER_OUT));
        $outHeaders = array_filter (
            $outHeaders,
            function ($value) {
                return $value!=='' && $value!==' ' && strlen($value)!=1;
            }
        );
        print_r ($outHeaders);
        */

        //fclose($fperr);
        curl_close ($ch);
        $result = json_decode ($result);
        return $result;
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
        $result = $this->curl_function ($path,$options);
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
        $post_options = [
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            //CURLOPT_POSTFIELDS => json_encode ($post)
        ];
        $options += $post_options;
        $path .= '?'.http_build_query($post);
        $result = $this->curl_function ($path,$options);
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
        $post_options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => array(), // try to fix 411 length required error
            //CURLOPT_POSTFIELDS => http_build_query ($post, null, '&', PHP_QUERY_RFC3986) // this was no good
            //CURLOPT_POSTFIELDS => json_encode ($post) // this was better until I tried to set the callback URL
        ];
        $options += $post_options;
        $path .= '?'.http_build_query ($post); // so sheesh, even POST needs to be a query string...
        $result = $this->curl_function ($path,$options);
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
        //echo $sql_file;
        $sql = file_get_contents ($sql_file);
        try {
            $result = $this->connection->query ($sql);
        }
        catch (\mysqli_sql_exception $e) {
            print_r($e);
            $this->error_log (125,'SQL execute failed: '.$e->getMessage());
            throw new \Exception ('SQL execution error');
            return false;
        }
        return $result;
    }

    private function fetch_collections ($m) {
        $this->simulateMode = 'payment';
        $response = $this->curl_get ('contract/'.$m['ContractGuid'].'/payment');
        $collections = [];
        if (isset($response->Payments)) {
            foreach ($response->Payments as $p) {
                $status_type = $p->Status.':'.$p->Type;
                if (!in_array($status_type, $this->status_types)) {
                    $this->status_types[] = $status_type;
                }
                if ($p->Type == 'BACS') {
                    $date = substr ($p->Date,0,10);
                    if ($date<$this->dd_before) { // TODO: made conditional after seeing collections for 2022-06-01 in paysuite_collection when inspecting the data on 2022-05-30
                        $collections[] = [
                            'payment_guid' => $p->Id,
                            'date_collected' => $date,
                            'amount' => $p->Amount,
                            'status' => $p->Status,
                        ];
                    }
                } else {
                    error_log("Mandate:\n".print_r($m, true));
                    error_log("Collection:\n".print_r($p, true));
                }
            }
        }
/*
        // TODO: or maybe this needs to work by getting all contracts for the customer
        // In the case of BWH supporter Ivor Bennett, two contracts have been created for one customer
        // At the time of writing nobody (including me) is admitting responsibility for that happening
        // So perhaps being tolerant of it is the best way...
        // This is just pseudo-code to make the point
        $this->simulateMode = 'contract';
        $response = $this->curl_get ('customer/'.$m['CustomerGuid'].'/contract');
        $collections = [];
        if (isset($response->Contracts)) {
            foreach ($response->Contracts as $c) {
                $this->simulateMode = 'payment';
                $response2 = $this->curl_get ('contract/'.$c['guid'].'/payment');
                if (isset($response2->Payments)) {
                    foreach ($response2->Payments as $p) {
                        if ($p->Status=='Paid') { // TODO: ignore recent payments? see docs.
                            $date = substr ($p->Date,0,10);
                            if ($date<$this->dd_before) { // TODO: made conditional after seeing collections for 2022-06-01 in paysuite_collection when inspecting the data on 2022-05-30
                                $collections[] = [
                                    'payment_guid' => $p->Id,
                                    'date_collected' => $date,
                                    'amount' => $p->Amount
                                ];
                            }
                        }
                    }
                }
            }
        }
*/
/*
        $collections = [
            [
                'payment_guid' => 'abc-xyz',
                'date_collected' => '2022-02-01',
                'amount' => 4.34
            ]
        ];
        return $collections;
*/
        return $collections;
    }

    private function get_bacs_endpoints ( ) {
        // might yet prove useful...
        foreach (['customer', 'contract', 'payment', 'schedule'] as $entity) {
            $this->simulateMode = $entity.'/callback';
            $endpoints[$entity] = $this->curl_get ('BACS/'.$entity.'/callback');
        }
        print_r ($endpoints);
        return $endpoints;
    }

    public function import ($from='2001-01-01') {
        //$this->test_customer ();
        //$this->test_callback ();
        //$this->test_schedule ();
        //$this->test_contract ();
        //return;
        $from               = new \DateTime ($from);
        $this->from         = $from->format ('Y-m-d');
        // Get all the mandates
        $sql = "
          SELECT
            `MandateId`
           ,`ContractGuid`
           ,`ClientRef`
          FROM `paysuite_mandate`
          WHERE DATE(`MandateCreated`)>='{$this->from}'
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
            $this->error_log (124,'SQL execute failed: '.$e->getMessage());
            throw new \Exception ('SQL execution error');
            return false;
        }
        catch (\Exception $e) {
            $this->error_log (123,'Load collections failed: '.$e->getMessage());
            throw new \Exception ('Load collections error');
            return false;
        }
        $this->output_mandates ();
        $this->output_collections ();
        error_log('Paysuite status and type combos: '.print_r($this->status_types, true));
    }

    private function insert_mandate (&$m)  {
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
                //echo $sql."\n";
                $result = $this->connection->query ($sql);
                if ($this->connection->affected_rows!=1) {
                    $this->error_log (122,"API update mandate [1] '{$m['ClientRef']}' - no affected rows");
                    throw new \Exception ("API update mandate [1] '{$m['ClientRef']}' - no affected rows");
                    return false;
                }
            }
            catch (\mysqli_sql_exception $e) {
                $this->error_log (121,"API update mandate [2] '{$m['ClientRef']}' failed: ".$e->getMessage());
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
                    $this->error_log (120,"API update mandate [3] '{$m['ClientRef']}' - no affected rows");
                    throw new \Exception ("API update mandate [3] '{$m['ClientRef']}' - no affected rows");
                    return false;
                }
            }
            catch (\mysqli_sql_exception $e) {
                $this->error_log (119,"API update mandate [4] '{$m['ClientRef']}' failed: ".$e->getMessage());
                throw new \Exception ("API update mandate [4] '{$m['ClientRef']}' failed: ".$e->getMessage());
                return false;
            }
        }
        return true;
    }

    public function insert_mandates ($mandates,&$bad=0,&$good=0)  {
        if (!count($mandates)) {
            if (defined('STDERR')) {
                fwrite (STDERR,"No mandates to insert\n");
            }
            else {
                error_log ("No mandates to insert");
            }
            return true;
        }
        $body = '';
        foreach ($mandates as $m) {
            $ok = false;
            if (!array_key_exists('StartDate',$m)) {
                $m['StartDate'] = null;
            }
            if (!array_key_exists($m['Freq'],$this->schedules)) {
                $msg = "Freq={$m['Freq']} is not currently supported for ClientRef={$m['ClientRef']}";
                $this->error_log (118,$msg);
                if (defined('STDERR')) {
                    fwrite (STDERR,"$msg\n");
                }
                else {
                    error_log ($msg);
                }
            }
            elseif ($m['PayDay'] || $m['StartDate']) {
                if (!$m['StartDate']) {
                    $m['StartDate'] = collection_startdate (gmdate('Y-m-d'),$m['PayDay']);
                }
                $qs = "
                  SELECT
                    *
                  FROM `paysuite_mandate`
                  WHERE `ClientRef`='{$m['ClientRef']}'
                  LIMIT 0,1
                  ;
                ";
                try {
                    $result = $this->connection->query ($qs);
                }
                catch (\mysqli_sql_exception $e) {
                    $this->error_log (117,'SQL select failed: '.$e->getMessage());
                }
                if ($result) {
                    if ($result->num_rows==0) {
                        // This is a new row for paysuite_mandate
                        $esc = [];
                        foreach ($m as $k=>$v) {
                            if ($k=='Name' || $k=='NamesFamily') {
                                // ErrorCode 3 - Account holder name must contain only:
                                // upper case letters (A-Z), numbers (0-9), full stop (.),
                                // forward slash (/), dash (-), Ampersand (&) and space
                                $v = strtr($v, '_~', '--'); // convert 'alternative' dashes
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
                           ,`Sortcode`='{$esc['Sortcode']}'
                           ,`Account`='{$esc['Account']}'
                           ,`StartDate`='{$esc['StartDate']}'
                           ,`Freq`='{$esc['Freq']}'
                           ,`Amount`='{$esc['Amount']}'
                           ,`ChancesCsv`='{$esc['Chances']}'
                           ,`Status`=''
                           ,`FailReason`=''
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
                            $this->error_log (116,'SQL insert failed: '.$e->getMessage());
                            if (defined('STDERR')) {
                                fwrite (STDERR,"SQL insert failed: ".$e->getMessage()."\n");
                            }
                            else {
                                error_log ("SQL insert failed: ".$e->getMessage());
                            }
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
                        error_log ('insert_mandate() failed: '.$e->getMessage());
                    }
                }

            }
            else {
                $msg = "PayDay={$m['PayDay']} is not valid for ClientRef={$m['ClientRef']}";
                $this->error_log (114,$msg);
                if (defined('STDERR')) {
                    fwrite (STDERR,"$msg\n");
                }
                else {
                    error_log ($msg);
                }
            }
            if ($ok) {
                $good++;
                $body .= $m['ClientRef']." SUCCESS\n";
            }
            else {
                $bad++;
                $body .= $m['ClientRef']." FAIL\n";
                if ($this->errorCode==127) {
                    $body .= "Aborting due to cURL timeout.\n";
                    break;
                }
                else {
                    $ocr = explode (BLOTTO_CREF_SPLITTER,$m['ClientRef']) [0];
                    $body .= adminer('Supporters','original_client_ref','=',$ocr)."\n";
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
        }
        // send
        if ($this->errorCode==127) {
            $subj = "Paysuite insert mandates for ".strtoupper(BLOTTO_ORG_USER).", cURL timeout";
        }
        else {
            $subj = "Paysuite insert mandates for ".strtoupper(BLOTTO_ORG_USER).", $good good, $bad bad";
        }
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
    'status' => 'Paid' // or 'Unpaid'
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
               ,`Status`='{$esc["status"]}'
               ,`OriginalStatus`='{$esc["status"]}'
              ON DUPLICATE KEY UPDATE
                `Status`='{$esc["status"]}'
               ,`StatusChanged`=IF(STRCMP('{$esc["status"]}',`OriginalStatus`) != 0 AND `StatusChanged` IS NULL, NOW(), `StatusChanged`)
              ;
            ";
            try {
                echo $sql."\n";
                $this->connection->query ($sql);
            }
            catch (\mysqli_sql_exception $e) {
                $this->error_log (113,"API insert collection '{$m['ClientRef']}-{$c["payment_guid"]}' failed: ".$e->getMessage());
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
        //echo $sql;
        try {
            $this->connection->query ($sql);
            tee ("Output {$this->connection->affected_rows} collections\n");
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (112,'SQL insert failed: '.$e->getMessage());
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
        $sql                = str_replace ('{{WHERE}}','',$sql);
        $sql                = str_replace ('{{BLOTTO_ORG_ID}}',BLOTTO_ORG_ID,$sql);
        //echo $sql;
        try {
            $this->connection->query ($sql);
            tee ("Output {$this->connection->affected_rows} mandates\n");
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (111,'SQL insert failed: '.$e->getMessage());
            throw new \Exception ('SQL error '.$e->getMessage());
            return false;
        }
    }

    public function player_new ($mandate,$db_live=null) {
        // Use API and insert the internal mandate
        $bad = 0;
        $this->insert_mandates ([$mandate],$bad); // convert mandate to array
        if ($bad>0) {
            // The API did not create the mandate
            return null;
        }
        // The internal mandate has now been inserted
        $crf = $this->connection->real_escape_string ($mandate['ClientRef']);
        // Write out the blotto2 mandate
        $table  = PST_TABLE_MANDATE;
        $sql    = "INSERT INTO `$table`\n";
        $sql   .= file_get_contents (__DIR__.'/select_mandate.sql');
        $sql    = str_replace ('{{PST_REFNO_OFFSET}}',PST_REFNO_OFFSET,$sql);
        $sql    = str_replace ('{{WHERE}}',"AND `ClientRef`='$crf'",$sql);
        $sql                = str_replace ('{{BLOTTO_ORG_ID}}',BLOTTO_ORG_ID,$sql);
        //echo $sql;
        try {
            $this->connection->query ($sql);
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (110,'Find new mandate failed: '.$e->getMessage());
            throw new \Exception ('SQL error '.$e->getMessage());
            // The API created the mandate but other processes did not complete
            return false;
        }
        if ($db_live) {
            // Insert the live internal mandate
            $sql = "
              INSERT INTO `$db_live`.`paysuite_mandate`
              SELECT * FROM `paysuite_mandate`
              WHERE `ClientRef`='$crf'
            ";
            error_log($sql);
            try {
                $this->connection->query ($sql);
            }
            catch (\mysqli_sql_exception $e) {
                $this->error_log (109,'Copy new mandate live failed: '.$e->getMessage());
                throw new \Exception ('SQL error '.$e->getMessage());
                // The API created the mandate but other processes did not complete
                return false;
            }
            // Insert the live blotto2 mandate
            $sql = "
              INSERT INTO `$db_live`.`$table`
              SELECT * FROM `$table`
              WHERE `ClientRef`='$crf'
            ";
            try {
                $this->connection->query ($sql);
            }
            catch (\mysqli_sql_exception $e) {
                $this->error_log (108,'Copy new mandate live failed: '.$e->getMessage());
                throw new \Exception ('SQL error '.$e->getMessage());
                // The API created the mandate but other processes did not complete
                return false;
            }
        }
        // TODO: cancel previous via API using $mandate[ClientRefPrevious]; in short term admin does it via provider dashboard
        // The API created the mandate and all other processes completed
        return true;
    }

    private function put_contract (&$mandate) {
        echo "put_contract ";
        $mandate['FailReason'] = "";
        print_r ($mandate);
        if (!array_key_exists('CustomerGuid',$mandate) || !$mandate['CustomerGuid']) {
            throw new \Exception ("Cannot put contract for {$mandate['ClientRef']} without a customer GUID");
            return false;
        }
        if (defined ('PST_MIGRATE_PREG') && PST_MIGRATE_PREG && preg_match('<'.PST_MIGRATE_PREG.'>',$mandate['ClientRef'])) {
            // This is only required if balances are needed for draws before the migration can be completed
            if (gmdate('Y-m-d')<PST_MIGRATE_DATE) {
                // Only pretend to put the mandate until migration day
                if (defined('STDERR')) {
                    fwrite (STDERR,"WARNING: paysuite-api providing fake contract GUID={$mandate['ClientRef']}\n");
                }
                else {
                    error_log ("WARNING: paysuite-api providing fake contract GUID={$mandate['ClientRef']}");
                }
                $mandate['ContractGuid'] = $mandate['ClientRef'];    
                fwrite (STDERR,"WARNING: paysuite-api providing fake DDRefOrig={$mandate['ClientRef']}\n");
                $mandate['DDRefOrig'] = $mandate['ClientRef'];    
                return true;
            }
        }
        $paymentMonthInYear = intval (substr($mandate['StartDate'],5,2));
        $paymentDayInMonth = intval (substr($mandate['StartDate'],8,2));
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
        echo "details ";
        print_r ($details);
        $this->simulateMode = 'contract';
        $response = $this->curl_post ("customer/{$mandate['CustomerGuid']}/contract",$details);
        echo "response ";
        print_r ($response); // dump to log file
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
        if (defined ('PST_MIGRATE_PREG') && PST_MIGRATE_PREG && preg_match('<'.PST_MIGRATE_PREG.'>',$mandate['ClientRef'])) {
            // This is only required if balances are needed for draws before the migration can be completed
            if (gmdate('Y-m-d')<PST_MIGRATE_DATE) {
                // Only pretend to put the mandate until migration day
                if (defined('STDERR')) {
                    fwrite (STDERR,"WARNING: paysuite-api providing fake customer GUID={$mandate['ClientRef']}\n");
                }
                else {
                    error_log ("WARNING: paysuite-api providing fake customer GUID={$mandate['ClientRef']}");
                }
                $mandate['CustomerGuid'] = $mandate['ClientRef'];    
                return true;
            }
        }
        // Address
        $address_array = [
            $mandate['AddressLine1'],
            $mandate['AddressLine2'],
            $mandate['AddressLine3'],
            $mandate['Town'],
            $mandate['County']
        ];
        foreach ($address_array as $line) {
            if (strlen($line)) {
                $lines[] = $line;
            }
        }
        $numlines = count ($lines);
        if ($numlines==5) {
            $addr1 = $lines[0].', '.$lines[1]; // because often house name + street
            $addr2 = $lines[2];
            $addr3 = $lines[3];
            $addr4 = $lines[4];
        }
        else {
            $addr1 = $lines[0];
            $addr2 = (isset($lines[1])) ? $lines[1] : '';
            $addr3 = (isset($lines[2])) ? $lines[2] : '';
            $addr4 = (isset($lines[3])) ? $lines[3] : '';
        }
        // Build customer details
        $sort = preg_replace ('/\D/','',$mandate['Sortcode']);
        $details = [
            'Email' => $mandate['Email'],
            'Title' => $mandate['Title'],
            'CustomerRef' => $mandate['ClientRef'],
            'FirstName' => $mandate['NamesGiven'],
            'Surname' => $mandate['NamesFamily'],
            // TODO: confirm these lengths are different
            'Line1' => substr ($addr1,0,50),
            'Line2' => substr ($addr2,0,30),
            'PostCode' => $mandate['Postcode'],
            'AccountNumber' => $mandate['Account'],
            'BankSortCode' => $sort,
            'AccountHolderName' => $mandate['Name']
        ];
        // optional
        if (strlen($addr3)) {
            $details['Line3'] = substr ($addr3,0,30);
        }
        if (strlen($addr4)) {
            $details['Line4'] = substr ($addr4,0,30);
        }
        echo "put_customer ";
        print_r ($details); // dump to log file
        $this->simulateMode = 'customer';
        $response = $this->curl_post ('customer',$details); 
        echo "response ";
        print_r ($response); // dump to log file
        // two stages of error handling because Paysuite give us two independent error types
        if (isset( $response->error)) { // e.g.  The requested resource is not found
            $mandate['FailReason'] = $response->error;
            throw new \Exception ($mandate['FailReason']);
            return false;
        }
        if (isset($response->ErrorCode)) { // e.g. badly formatted date
            $mandate['FailReason'] = $response->ErrorCode.'. '.$response->Message.': '.$response->Detail;
            throw new \Exception ($mandate['FailReason']);
            return false;
        }
        $mandate['CustomerGuid'] = $response->Id;    
        return true;
    }

    public function reset_fakes ( ) {
        // A migration method
        if (defined('PST_MIGRATE_PREG') && PST_MIGRATE_PREG) {
            if (!defined(PST_MIGRATE_DATE) || !PST_MIGRATE_DATE) {
                $this->error_log (107,'Migration has no PST_MIGRATE_DATE');
                throw new \Exception ('Migration has no PST_MIGRATE_DATE');
                return true;
            }
            $migrate_date = new \DateTime (PST_MIGRATE_DATE);
            if ($migrate_date->format('Y-m-d')!=PST_MIGRATE_DATE) {
                $mandate['FailReason'] = "";
                $this->error_log (106,'Migration does not understand PST_MIGRATE_DATE='.PST_MIGRATE_DATE);
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
                    $this->error_log (105,'SQL update failed: '.$e->getMessage());
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
                $this->error_log (104,"$c not defined");
                throw new \Exception ('Configuration error');
                return false;
            }
        }
        $sql = "
          SELECT
            DATABASE() AS `db`
           ,DATE_SUB(CURDATE(),INTERVAL ".PST_PAY_INTERVAL.") AS `dd_before`
        ";
        try {
            $db                 = $this->connection->query ($sql);
            $db                 = $db->fetch_assoc ();
            $this->database     = $db['db'];
            $this->dd_before    = $db['dd_before'];
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (103,'SQL select failed: '.$e->getMessage());
            throw new \Exception ('SQL database error');
            return false;
        }
        // Missing tables
        $this->execute (__DIR__.'/create_mandate.sql');
        $this->execute (__DIR__.'/create_collection.sql');
    }

    private function simulate ( ) {
        if (!defined('PST_SIMULATE') || !PST_SIMULATE || !$this->simulateMode) {
            return false;
        }
        $rtn                        = new \stdClass ();
        $rtn->error                 = null;
        $rtn->ErrorCode             = 0;
        if ($this->simulateMode=='contract') {
            $rtn->Id                = 123;
            $rtn->DirectDebitRef    = 'abc123';
        }
        elseif ($this->simulateMode=='customer') {
            $rtn->ErrorCode         = 0;
            $rtn->Message           = '';
            $rtn->Detail            = '';
            $rtn->Id                = '123abc';
        }
        elseif ($this->simulateMode=='payment') {
            $rtn->Payments          = [];
        }
        elseif (preg_match('<^(.+)/callback$>',$this->simulateMode,$matches)) {
            $entity                 = $matches[1];
            if ($entity=='contract') {
            }
            elseif ($entity=='customer') {
            }
            elseif ($entity=='payment') {
            }
        }
        return $rtn;
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
            $this->error_log (102,'SQL insert failed: '.$e->getMessage());
            throw new \Exception ('SQL insert error');
            return false;
        }
    }

    private function test_callback() {
        $this->simulateMode = null;
        $r = $this->curl_get ('BACS/contract/callback');
        echo "\nget: ";
        print_r ($r);
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
        //echo "\npost: ";
        //print_r ($r);
        $this->simulateMode = null;
        $r = $this->curl_get ('customer/'.$customer_guid.'/contract');
        echo "\nget: ";
        print_r ($r);
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

    private function test_customer ( ) {
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

        $this->simulateMode = null;
        $r = $this->curl_get ('customer');
        echo "\nget: "; // ->Customers[2]
        foreach ($r->Customers as $c) {
            echo $c->CustomerRef.' '.$c->Id."\n";
        }

    }

    private function test_schedule ( ) {
        error_log("test_schedule");
        $this->simulateMode = null;
        $r = $this->curl_get ('schedules');
        error_log(print_r($r), true);
        echo "\nget: "; print_r($r);
    }

}

