<?php

namespace App\Helpers;

use Google\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FcmHelper
{
    public static function send($user, $title, $body, $extraData = [], $id = 1, $type = 'notification')
    {
        if (!$user->fcm_token) {
            Log::warning('FCM send skipped: User FCM token not found', [
                'user_id' => $user->id
            ]);
            return ['error' => 'User FCM token not found'];
        }

        // Google OAuth token
        $client = new Client();
        $client->setAuthConfig(config('services.firebase.credentials'));
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $accessToken = $client->fetchAccessTokenWithAssertion();

        $senderName = auth()->check() ? auth()->user()->name : 'System';

        // Merge basic + custom data
        $data = self::flattenData(array_merge([
            "click_action" => "FLUTTER_NOTIFICATION_CLICK",
            "id" => (string) $id,
            "type" => $type,
            "sender_name" => $senderName
        ], $extraData));

        $payload = [
            "message" => [
                "token" => $user->fcm_token,

                "notification" => [
                    "title" => $title,
                    "body" => $body,
                ],

                "data" => $data,

                "android" => [
                    "priority" => "high",
                    "notification" => [
                        "channel_id" => "high_importance_channel"
                    ]
                ],

                "apns" => [
                    "headers" => [
                        "apns-priority" => "10",
                    ],
                    "payload" => [
                        "aps" => [
                            "alert" => [
                                "title" => $title,
                                "body" => $body,
                            ],
                            "badge" => 1,
                            "sound" => "default",
                            "mutable-content" => 1,
                        ]
                    ]
                ]
            ]
        ];

        // 🔹 Log before sending
        Log::info('FCM Push Notification Sending', [
            'user_id' => $user->id,
            'token' => $user->fcm_token,
            'payload' => $payload
        ]);

        $response = Http::withToken($accessToken['access_token'])
            ->post('https://fcm.googleapis.com/v1/projects/' . env('FIREBASE_PROJECT_ID') . '/messages:send', $payload);

        $responseJson = $response->json();

        // 🔹 Log after sending
        if (isset($responseJson['error'])) {
            Log::warning('FCM Push Notification Failed', [
                'user_id' => $user->id,
                'token' => $user->fcm_token,
                'error' => $responseJson['error'],
            ]);
        } else {
            Log::info('FCM Push Notification Sent Successfully', [
                'user_id' => $user->id,
                'token' => $user->fcm_token,
                'response' => $responseJson,
            ]);
        }

        return $responseJson;
    }

    /**
     * FCM data must only contain string key-value pairs.
     * Convert arrays to JSON-strings.
     */
    private static function flattenData(array $data)
    {
        $flat = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Convert array → JSON string
                $flat[$key] = json_encode($value);
            } else {
                // Always convert to string
                $flat[$key] = (string) $value;
            }
        }

        return $flat;
    }
}
