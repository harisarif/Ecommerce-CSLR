<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('private-notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('private-chat.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
    