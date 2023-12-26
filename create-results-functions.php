<?php

function getMessages(string $directoryPath, DateTimeImmutable $fromDate): Generator
{
    $historyFiles = scandir($directoryPath, SCANDIR_SORT_DESCENDING);
    foreach ($historyFiles as $historyFile) {
        if ($historyFile === 'done.json' || !str_ends_with($historyFile, '.json')) {
            continue;
        }

        $history = json_decode(file_get_contents($directoryPath . '/' . $historyFile), true);

        $users = [];
        foreach ($history['users'] as $user) {
            $id = $user['id'];
            if (empty($users[$id])) {
                $users[$id] = $user;
            }
        }

        $messages = $history['messages'];
        foreach ($messages as $message) {
            if ($message['_'] === 'messageService') {
                continue;
            }
            $message['date'] = new DateTimeImmutable('@' . $message['date']);
            if ($message['date'] < $fromDate) {
                continue;
            }
            if (empty($message['from_id'])) {
                $message['from_id'] = $message['peer_id'];
            }
            $message['from'] = $users[$message['from_id']];
            yield $message;
        }
    }
}

function isGif(array $message): bool
{
    $attributes = $message['media']['document']['attributes'] ?? [];
    foreach ($attributes as $attribute) {
        if ($attribute['_'] === 'documentAttributeAnimated') {
            return true;
        }
    }
    return false;
}

function isSticker(array $message): bool
{
    $attributes = $message['media']['document']['attributes'] ?? [];
    foreach ($attributes as $attribute) {
        if ($attribute['_'] === 'documentAttributeSticker') {
            return true;
        }
    }
    return false;
}

function createUsersResults(string $cachePath, DateTimeImmutable $fromDate): array
{
    $usersResults = [];
    foreach (getMessages($cachePath, $fromDate) as $message) {
        if (empty($usersResults[$message['from_id']])) {
            $usersResults[$message['from_id']]['user'] = $message['from'];
        }
        $userResult = &$usersResults[$message['from_id']];

        $month = $message['date']->format('m');

        // Message count by month
        $groupedId = $message['grouped_id'] ?? null;
        if (isset($message['fwd_from'])) {
            if ($groupedId !== null) {
                $userResult['grouped_forwards'][$month][$groupedId] = true;
            } else {
                if (isset($userResult['forwards'][$month])) {
                    $userResult['forwards'][$month]++;
                } else {
                    $userResult['forwards'][$month] = 1;
                }
            }
        } else {
            if ($groupedId !== null) {
                $userResult['grouped_messages'][$month][$groupedId] = true;
            } else {
                if (isset($userResult['messages'][$month])) {
                    $userResult['messages'][$month]++;
                } else {
                    $userResult['messages'][$month] = 1;
                }
            }
        }

        // gif by user
        if (isGif($message)) {
            $gifId = $message['media']['document']['id'];
            if (isset($userResult['gifs'][$gifId])) {
                $userResult['gifs'][$gifId]['count']++;
            } else {
                $userResult['gifs'][$gifId]['count'] = 1;
                $userResult['gifs'][$gifId]['media'] = $message['media'];
            }
        }

        // sticker by user
        if (isSticker($message)) {
            $stickerId = $message['media']['document']['id'];
            if (isset($userResult['stickers'][$stickerId])) {
                $userResult['stickers'][$stickerId]['count']++;
            } else {
                $userResult['stickers'][$stickerId]['count'] = 1;
                $userResult['stickers'][$stickerId]['media'] = $message['media'];
            }
        }
    }

    foreach ($usersResults as &$userResult) {
        // Forwards count
        if (isset($userResult['forwards'])) {
            foreach ($userResult['forwards'] as $month => $count) {
                $userResult['forwards'][$month] += count($userResult['grouped_forwards'][$month] ?? []);
            }
        }
        unset($userResult['grouped_forwards']);

        $countForwards = array_reduce($userResult['forwards'] ?? [], static function(int $count, int $item){
            $count += $item;
            return $count;
        }, 0);
        $userResult['total_forwards'] = $countForwards;

        // Messages count
        if (isset($userResult['messages'])) {
            foreach ($userResult['messages'] as $month => $count) {
                $userResult['messages'][$month] += count($userResult['grouped_messages'][$month] ?? []);
            }
        }
        unset($userResult['grouped_messages']);

        $countMessages = array_reduce($userResult['messages'] ?? [], static function(int $count, int $item){
            $count += $item;
            return $count;
        }, 0);
        $userResult['total_messages'] = $countMessages;

        // Total count
        $userResult['total_all'] = $countMessages + $countForwards;

        // Favorite gifs
        $gifs = $userResult['gifs'] ?? [];
        array_multisort( array_column($gifs, "count"), SORT_DESC, $gifs);
        $userResult['favorite_gifs'] = array_slice($gifs, 0, 3);
        unset($userResult['gifs']);

        // Favorite stickers
        $stickers = $userResult['stickers'] ?? [];
        array_multisort( array_column($stickers, "count"), SORT_DESC, $stickers);
        $userResult['favorite_stickers'] = array_slice($stickers, 0, 3);
        unset($userResult['stickers']);
    }
    return $usersResults;
}