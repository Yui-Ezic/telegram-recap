<?php

use danog\MadelineProto\Settings\AppInfo;

include 'madeline.php';

$env = parse_ini_file('.env');
$settings = (new AppInfo())
    ->setApiId($env['API_ID'])
    ->setApiHash($env['API_HASH']);

$MadelineProto = new \danog\MadelineProto\API('session.madeline', $settings);
$MadelineProto->start();

$me = $MadelineProto->getSelf();

$MadelineProto->echo("All done successfully. Your profile:\n" . json_encode($me, JSON_PRETTY_PRINT));