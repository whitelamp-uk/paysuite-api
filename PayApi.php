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
                'PST_TABLE_MANDATE',
                'PST_TABLE_COLLECTION',
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
            $this->error_log (125,curl_error($ch));
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
        * Send a GETT request using cURL
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


    // borrowed from rsm-api
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
        echo file_get_contents ($sql_file);
        exec (
            'mariadb '.escapeshellarg($this->database).' < '.escapeshellarg($sql_file),
            $output,
            $status
        );
        if ($status>0) {
            $this->error_log (127,$sql_file.' '.implode(' ',$output));
            throw new \Exception ("SQL file '$sql_file' execution error");
            return false;
        }
        return $output;
    }

    private function get_bacs_endpoints () {
        foreach (['customer', 'contract', 'payment', 'schedule'] as $entity) {
            $endpoints[$entity] = $this->curl_get('BACS/'.$entity.'/callback');
        }
        print_r($endpoints);
        return $endpoints;
    }

    public function import ($start_date,$rowsm=0,$rowsc=0) {
        //$this->test_customer();
        //$this->test_callback();
        $this->test_schedule();
        return;
        $this->execute (__DIR__.'/create_collection.sql');
        $this->execute (__DIR__.'/create_mandate.sql');
        // Go get mandate and collection data
        // Store in paysuite_mandate and paysuite_collection
        // Use $this->table_load()
        // Set any indexing
        $this->table_alter ('paysuite_collection');
        $this->table_alter ('paysuite_mandate');
        $this->output_mandates ();
        $this->output_collections ();
    }

    private function insert_mandate ($m)  {
        $customer_guid = $this->insert_customer ($m);
        $contract_guid = $this->insert_contract ($m);
        $sql = "
          UPDATE `paysuite_mandate`
          SET
            `CustomerGuid`='$customer_guid'
            `ContractGuid`='$contract_guid'
            WHERE `ClientRef`='{$m['ClientRef']}'
            LIMIT 1
          ;
        ";
        try {
            $result = $this->connection->query ($sql);
            if ($this->connection->affected_rows!=1) {
                $this->error_log (126,"API update mandate '{$m['ClientRef']}' - no affected rows");
                throw new \Exception ("API update mandate '{$m['ClientRef']}' - no affected rows");
                return false;
            }
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (125,"API update mandate '{$m['ClientRef']}' failed: ".$e->getMessage());
            throw new \Exception ("API update mandate '{$m['ClientRef']}' failed: ".$e->getMessage());
            return false;
        }
    }

    public function insert_mandates ($mandates)  {
        foreach ($mandates as $m) {
            $sql = "
                SELECT
                  *
                FROM `paysuite_mandate`
                WHERE
                    `ClientRef`='{$m['ClientRef']}'
                LIMIT 0,1
            ";
            try {
                $result = $this->connection->query ($sql);
                if ($result->num_rows==0) {
                    $start_date = collection_startdate (date('Y-m-d'),$m['PayDay']);
                    $sql = "
                      INSERT IGNORE INTO `paysuite_mandate`
                      SET
                        `ClientRef`='{$m['ClientRef']}'
                       ,`Name`='{$m['Name']}'
                       ,`Sortcode`='{$m['SortCode']}'
                       ,`Account`='{$m['Account']}'
                       ,`StartDate`='$start_date'
                       ,`Freq`='{$m['Freq']}'
                       ,`Amount`='{$m['Amount']}'
                       ,`ChancesCsv`='{$m['Chances']}'
                      ;
                    ";
                    echo $sql."\n";
                    $this->connection->query ($sql);
                    try {
                        $this->insert_mandate ($m);
                    }
                    catch (\Exception $e) {
                        $this->error_log (125,'API insert mandate failed: '.$e->getMessage());
                        throw new \Exception ('API insert mandate failed: '.$e->getMessage());
                        return false;
                    }
                }
            }
            catch (\mysqli_sql_exception $e) {
                $this->error_log (124,'SQL insert failed: '.$e->getMessage());
                throw new \Exception ('SQL insert failed: '.$e->getMessage());
                return false;
            }
        }

// Make sure this bit does not work for now
fwrite (STDERR,"insert_mandates() has chickened out\n");
return true;
        // Make request and handle response
$ok = false;
        if ($ok) {
            return true;
        }
        $this->error_log (126,$e);
        throw new \Exception ('Failed to send new mandates using paysuite-api');
        return false;
    }

    private function output_collections ( ) {
        $sql                = "INSERT INTO `".PST_TABLE_COLLECTION."`\n";
        $sql               .= file_get_contents (__DIR__.'/select_collection.sql');
        $sql                = str_replace ('{{PST_PAY_INTERVAL}}',PST_PAY_INTERVAL,$sql);
        echo $sql;
        try {
            $this->connection->query ($sql);
            tee ("Output {$this->connection->affected_rows} collections\n");
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (125,'SQL insert failed: '.$e->getMessage());
            throw new \Exception ('SQL error');
            return false;
        }
    }

    private function output_mandates ( ) {
        $sql                = "INSERT INTO `".PST_TABLE_MANDATE."`\n";
        $sql               .= file_get_contents (__DIR__.'/select_mandate.sql');
        echo $sql;
        try {
            $this->connection->query ($sql);
            tee ("Output {$this->connection->affected_rows} mandates\n");
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (124,'SQL insert failed: '.$e->getMessage());
            throw new \Exception ('SQL error');
            return false;
        }
    }

    private function setup ( ) {
        foreach ($this->constants as $c) {
            if (!defined($c)) {
                $this->error_log (123,"$c not defined");
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
            $this->error_log (122,'SQL select failed: '.$e->getMessage());
            throw new \Exception ('SQL database error');
            return false;
        }
    }

    private function table_alter ($table) {
        if ($table=='paysuite_mandate') {
            $file = 'alter_mandate.sql';
        }
        elseif ($table=='paysuite_collection') {
            $file = 'alter_collection.sql';
        }
        else {
            $this->error_log (121,"Internal error");
            throw new \Exception ("Table '$table' not recognised");
            return false;
        }
        $this->execute (__DIR__.'/'.$file);
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
            $this->error_log (120,'SQL insert failed: '.$e->getMessage());
            throw new \Exception ('SQL insert error');
            return false;
        }
    }

    private function test_callback() {
        $r = $this->curl_get('BACS/contract/callback');
        echo "\nget: ";print_r($r);
        $r = $this->curl_post('BACS/contract/callback', ['url' => 'http://foobar.com']);
        echo "\npost: ";print_r($r);
        $r = $this->curl_get('BACS/contract/callback');
        echo "\nget: ";print_r($r);
        $r = $this->curl_delete('BACS/contract/callback');
        echo "\ndelete: ";print_r($r);
        $r = $this->curl_get('BACS/contract/callback');
        echo "\n5get: ";print_r($r);
    }

    private function test_customer() {
        $details = [
            "Email" => "john.doe@test.com",
            "Title" => "Mr",
            "CustomerRef" => "Y99999",
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
            "AccountNumber" => "82345671",
            "BankSortCode" => "823456",
        ];


        //$r = $this->curl_post('customer', $details);
        //echo "\npatch: ";print_r($r);

        $r = $this->curl_delete('customer/798e5d5c-c4a8-4375-9a42-06a8002110ed');
        echo "\ndelete: ";print_r($r);

        $r = $this->curl_patch('customer/3a02c36f-65dd-4569-ad7f-f7d420d56cdd', $patchdetails);
        echo "\npatch: ";print_r($r);

        $r = $this->curl_get('customer');
        echo "\nget: ";print_r($r); // ->Customers[2]

    }

    private function test_schedule() {
        $r = $this->curl_get('schedules');
        echo "\nget: ";print_r($r);
    }

}

