<?php

namespace Blotto\Paysuite;

class PayApi {

    private  $bogon_file;
    private  $connection;
    public   $constants = [
                 'PST_ERROR_LOG',
                 'PST_USER',
                 'PST_PASSWORD',
                 'PST_PAY_INTERVAL',
                 'PST_FILE_DEBOGON',
                 'PST_TABLE_COLLECTION',
                 'PST_TABLE_MANDATE'
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

    public function import ($start_date,$rowsm=0,$rowsc=0) {
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

    public function insert_mandates ($mandates)  {
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

}

