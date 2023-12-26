<?php

use danog\MadelineProto\API;
use const TelegramResults\MESSAGES_BATCH_SIZE;

function resolveOffset(string $directoryPath): ?int
{
    if (!is_dir($directoryPath)) {
        return null;
    }
    $files = scandir($directoryPath, SCANDIR_SORT_ASCENDING);
    foreach ($files as $file) {
        if (str_ends_with($file, '.json')) {
            $history = json_decode(file_get_contents($directoryPath . '/' . $file), true);
            return end($history['messages'])['id'];
        }
    }
    return null;
}

function downloadHistory(API $telegram, int $peer, DateTimeImmutable $fromDate, string $cachePath): void
{
    $doneFile = $cachePath . '/done.json';
    if (file_exists($doneFile)) {
        return;
    }
    if (!is_dir($cachePath)) {
        mkdir($cachePath);
    }
    $offset = resolveOffset($cachePath);
    do {
        $history = $telegram->messages->getHistory(peer: $peer, offset_id: $offset, limit: MESSAGES_BATCH_SIZE);

        $lastMessage = end($history['messages']);
        $lastMessageDate = new DateTimeImmutable('@' . $lastMessage['date']);
        $offset = $lastMessage['id'];

        file_put_contents($cachePath . "/$offset.json", json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    } while ($lastMessageDate > $fromDate);
    file_put_contents($doneFile, json_encode(['ok' => true]));
}