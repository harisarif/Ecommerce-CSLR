<?php


namespace App\Helpers;

use Google\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class FcmHelper
{
    public static function send($user, $title, $body, $id = 1, $type = 'notification', $extraData = [])
    {
        // ✅ Skip if user has no FCM token
        if (empty($user->fcm_token)) {
            return ['error' => 'User has no FCM token'];
        }

        $client = new Client();
        $client->setAuthConfig(config('services.firebase.credentials'));
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $accessToken = $client->fetchAccessTokenWithAssertion();

        $senderName = auth()->check() ? auth()->user()->name : ($extraData['sender']['username'] ?? 'System');

        // Base payload
        $data = [
            "message" => [
                "token" => $user->fcm_token,
                "notification" => [
                    "title" => $title,
                    "body" => $body,
                ],
                "data" => array_merge([
                    "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                    "id" => (string) $id,
                    "type" => $type,
                    "sender_name" => $senderName,
                ], $extraData), // merge additional data like offer/product
                "android" => [
                    "priority" => "high",
                    "notification" => [
                        "channel_id" => "high_importance_channel",
                    ],
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
                        ],
                    ],
                ],
            ],
        ];

        // Send FCM request
        $response = Http::withToken($accessToken['access_token'])
            ->post('https://fcm.googleapis.com/v1/projects/' . env('FIREBASE_PROJECT_ID') . '/messages:send', $data);

        $responseJson = $response->json();

        // Optional logging (uncomment if needed)
        /*
        if (isset($responseJson['error'])) {
            \Log::warning('FCM Push Notification Failed', [
                'user_id' => $user->id,
                'token' => $user->fcm_token,
                'error' => $responseJson['error'],
            ]);
        } else {
            \Log::info('FCM Push Notification Sent Successfully', [
                'user_id' => $user->id,
                'token' => $user->fcm_token,
                'response' => $responseJson,
            ]);
        }
        */

        return $responseJson;
    }
}
