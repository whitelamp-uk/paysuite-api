<?php

if (!array_key_exists(1,$argv)) {
    fwrite (STDERR,"Usage: {$argv[0]} blotto_config_file\n");
    exit (1);
}
$cfg = $argv[1];
require $cfg;

$zo = new \mysqli ('localhost',BLOTTO_UN,BLOTTO_PW,BLOTTO_MAKE_DB);
if (!$zo) {
    fwrite (STDERR,"Could not connect to database\n");
    exit (1);
}

echo "org = ".BLOTTO_ORG_USER."\n";

require __DIR__.'/PayApi.php';
$api = new \Blotto\Paysuite\PayApi ($zo,BLOTTO_ORG_USER);
print_r ($api->info());

