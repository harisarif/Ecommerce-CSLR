<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class PusherHelper
{
    public static function trigger($channel, $event, $data = [])
    {
        // ✅ Pusher credentials (keep these in .env)
        $appId = config('services.pusher.app_id');
        $key = config('services.pusher.key');
        $secret = config('services.pusher.secret');
        $cluster = config('services.pusher.options.cluster');

        // URL for triggering events manually
        $url = "https://api-$cluster.pusher.com/apps/$appId/events";

        // Required Pusher payload
        $payload = [
            'name' => $event,
            'channel' => $channel,
            'data' => json_encode($data),
        ];

        // Create auth signature
        $bodyMd5 = md5(json_encode($payload));
        $queryString = "auth_key=$key&auth_timestamp=" . time() . "&auth_version=1.0&body_md5=$bodyMd5";
        $authSignature = hash_hmac('sha256', "POST\n/apps/$appId/events\n$queryString", $secret);
        $signedUrl = "$url?$queryString&auth_signature=$authSignature";

        // Send via cURL (or Laravel Http)
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post($signedUrl, $payload);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Pusher Trigger Error: ' . $e->getMessage());
            return false;
        }
    }
}
