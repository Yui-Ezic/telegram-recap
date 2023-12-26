<?php

namespace TelegramResults;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings\AppInfo;
use DateTimeImmutable;
use Throwable;
use function Amp\File\deleteDirectory;

include 'madeline.php';
include 'functions.php';
include 'download-history-functions.php';
include 'create-results-functions.php';

const MESSAGES_BATCH_SIZE = 100;
const PEER = -1001128223373;

$env = parse_ini_file('.env');
$settings = (new AppInfo())
    ->setApiId($env['API_ID'])
    ->setApiHash($env['API_HASH']);
$fromDate = new DateTimeImmutable('01-01-2023');

$telegram = new API('session.madeline', $settings);
$telegram->start();

//$telegram->downloadToDir(json_decode(file_get_contents(__DIR__ . '/media.json'), true), __DIR__);
//die;

$cachePath = __DIR__ . '/cache/' . PEER;
downloadHistory($telegram, PEER, $fromDate, $cachePath);

$usersResults = createUsersResults($cachePath, $fromDate);
file_put_contents(__DIR__ . '/user-results.json', json_encode($usersResults, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$results = [
    'total_all' => 0,
    'total_messages' => 0,
    'total_forwards' => 0,
    'messages' => [],
    'forwards' => []
];

foreach ($usersResults as $usersResult) {
    $results['total_all'] += $usersResult['total_all'];
    $results['total_messages'] += $usersResult['total_messages'];
    $results['total_forwards'] += $usersResult['total_forwards'];

    $messagesByMonth = $usersResult['messages'] ?? [];
    foreach ($messagesByMonth as $month => $count) {
        if (isset($results['messages'][$month])) {
            $results['messages'][$month] += $count;
        } else {
            $results['messages'][$month] = $count;
        }
    }

    $forwardsByMonth = $usersResult['forwards'] ?? [];
    foreach ($forwardsByMonth as $month => $count) {
        if (isset($results['forwards'][$month])) {
            $results['forwards'][$month] += $count;
        } else {
            $results['forwards'][$month] = $count;
        }
    }
}

$sitePath = __DIR__ . '/result/' . PEER;
removeDir($sitePath);
recurseCopy(__DIR__ . '/site-template', $sitePath);

array_multisort( array_column($usersResults, "total_all"), SORT_DESC, $usersResults);
$topUsers = array_slice($usersResults, 0, 10);

foreach ($topUsers as &$userResult) {
    // download profile picture
    if (isset($userResult['user']['photo'])) {
        $profilePictureInfo = $telegram->getPropicInfo($userResult['user']);
        $profilePictureDir = $sitePath . '/profile-pictures';
        createDirIfNot($profilePictureDir);
        $profilePicturePath = $telegram->downloadToDir($profilePictureInfo, $profilePictureDir);
        $userResult['profile_picture'] = getRelativePath($sitePath, $profilePicturePath);
    } else {
        $userResult['profile_picture'] = 'profile-pictures/placeholder.png';
    }

    // download favorite gifs
    foreach ($userResult['favorite_gifs'] as &$gif) {
        $gifDir = $sitePath . '/favorite-gifs';
        createDirIfNot($gifDir);
        $gifPath = $telegram->downloadToDir($gif['media'], $gifDir);
        $gif['path'] = getRelativePath($sitePath, $gifPath);
        unset($gif['media']);
    }

    // downloading favorite stickers
    foreach ($userResult['favorite_stickers'] as &$sticker) {
        $stickerDir = $sitePath . '/favorite-stickers';
        createDirIfNot($stickerDir);
        $stickerPath = $telegram->downloadToDir($sticker['media'], $stickerDir);
        $sticker['path'] = getRelativePath($sitePath, $stickerPath);
        unset($sticker['media']);
    }
}
$results['top_users'] = $topUsers;

file_put_contents($sitePath . '/results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$telegram->echo('OK, done!');