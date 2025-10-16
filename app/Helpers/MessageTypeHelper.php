<?php

namespace App\Helpers;

class MessageTypeHelper
{
    public static function identifyType($meta)
    {
        if (!empty($meta['type'])) {
            return $meta['type'];
        }

        // Fallback checks
        if (!empty($meta['offer_id'])) return 'offer_chat';
        if (!empty($meta['price_offered'])) return 'offer';
        return 'chat';
    }

    public static function notificationText($message, $senderName)
    {
        $type = self::identifyType($message->meta ?? []);

        return match ($type) {
            'chat' => "$senderName sent you a message",
            'offer' => "$senderName sent you a new offer",
            'offer_chat' => "$senderName replied in offer chat",
            'counter_offer' => "$senderName sent a counter offer",
            'offer_response' => "$senderName responded to your offer",
            default => "$senderName sent a message",
        };
    }
}
