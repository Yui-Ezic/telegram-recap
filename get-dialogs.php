<?php

namespace TelegramResults;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings\AppInfo;

include 'madeline.php';

$env = parse_ini_file('.env');
$settings = (new AppInfo())
    ->setApiId($env['API_ID'])
    ->setApiHash($env['API_HASH']);

$telegram = new API('session.madeline', $settings);
$telegram->start();

$chats = [];
$users = [];

$peers = $telegram->getDialogIds();
foreach ($peers as $peer) {
    $dialogInfo = $telegram->getInfo($peer);
    if (isset($dialogInfo['Chat'])) {
        $chats[] = [
            'title' => $dialogInfo['Chat']['title'],
            'peer' => $peer
        ];
    } elseif (isset($dialogInfo['User'])) {
        $users[] = [
            'username' => $dialogInfo['User']['username'] ?? null,
            'first_name' => $dialogInfo['User']['first_name'] ?? null,
            'last_name' => $dialogInfo['User']['last_name'] ?? null,
            'peer' => $peer
        ];
    }
}
file_put_contents('chats.json', json_encode($chats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents('users.json', json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$telegram->echo('Dialogs has exported to chats.json and users.json');