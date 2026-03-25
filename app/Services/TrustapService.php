<?php

namespace App\Services;

use App\Models\TrustapOauthState;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TrustapService
{
    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.trustap.base_url');
        $this->apiKey = config('services.trustap.api_key');
    }

    private function client()
    {
        return Http::withBasicAuth($this->apiKey, '')
            ->acceptJson()
            ->contentType('application/json');
    }

    public function createUser($email, $firstName = 'Test', $lastName = 'User')
    {
        $response = $this->client()->post($this->baseUrl . '/guest_users', [
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'country_code' => 'us',
            'tos_acceptance' => [
                'unix_timestamp' => time(),
                'ip' => request()->ip() ?? '127.0.0.1'
            ]
        ]);

        return $response->json();
    }

    public function getTrustapOauthUrl(User $user): string
    {
        $clientId = config('services.trustap.client_id');
        $redirectUri = urlencode(config('app.frontend_url') . '/api/v1/trustap/callback');
        $state = Str::random(32);

        // Save state for later verification
        TrustapOauthState::create([
            'user_id' => $user->id,
            'state'   => $state,
        ]);

        $scope = urlencode("openid basic_tx:offline_create_join basic_tx:offline_accept_payment basic_tx:offline_cancel basic_tx:offline_accept_payment");

        return "https://sso.trustap.com/auth/realms/trustap-stage/protocol/openid-connect/auth?" .
            "client_id={$clientId}&redirect_uri={$redirectUri}&response_type=code&scope={$scope}&state={$state}";
    }

    public function calculateCharge($price, $currency = 'aed')
    {
        $response = $this->client()->get($this->baseUrl . '/charge', [
            'price' => $price,
            'currency' => $currency
        ]);

        return $response->json();
    }

    public function createTransaction($buyerId, $sellerId, $price, $description)
    {
        $charge = $this->calculateCharge($price);

        $response = $this->client()->post(
            $this->baseUrl . '/me/transactions/create_with_guest_user',
            [
                'seller_id' => $sellerId,
                'buyer_id' => $buyerId,
                'creator_role' => 'buyer',
                'currency' => 'aed',
                'description' => $description,
                'price' => $price,
                'charge' => $charge['charge'],
                'charge_calculator_version' => $charge['charge_calculator_version']
            ]
        );

        return $response->json();
    }

    // public function createTransactionRegistered(User $buyer, User $seller, $price, $description)
    // {
    //     if (!$buyer->trustap_access_token) {
    //         throw new \Exception('Buyer Trustap OAuth token missing');
    //     }

    //     if (!$seller->trustap_user_id) {
    //         throw new \Exception('Seller Trustap ID missing');
    //     }

    //     // 1️⃣ Calculate charge (can still use API key)
    //     $chargeResponse = $this->calculateCharge($price);

    //     $body = [
    //         'price' => $price,
    //         'charge' => $chargeResponse['charge'],
    //         'charge_calculator_version' => $chargeResponse['charge_calculator_version'],
    //         'currency' => 'aed',
    //         'description' => $description,
    //         'seller_id' => $seller->trustap_user_id,
    //         'creator_role' => 'buyer',
    //     ];

    //     // 2️⃣ Make the transaction call on behalf of buyer using OAuth token
    //     $response = Http::withToken($buyer->trustap_access_token) // Buyer OAuth token
    //         ->acceptJson()
    //         ->post($this->baseUrl . '/me/transactions', $body);

    //     // 3️⃣ Handle errors properly
    //     if ($response->failed()) {
    //         \Log::error('Trustap registered transaction failed', [
    //             'buyer_id' => $buyer->id,
    //             'seller_id' => $seller->id,
    //             'response' => $response->body()
    //         ]);

    //         throw new \Exception('Trustap transaction creation failed: ' . $response->body());
    //     }

    //     return $response->json();
    // }


    public function refreshAccessToken(User $user)
    {
        $response = Http::asForm()->post($this->baseUrl . '/auth/realms/trustap-stage/protocol/openid-connect/token', [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.trustap.client_id'),
            'client_secret' => config('services.trustap.client_secret'),
            'refresh_token' => $user->trustap_refresh_token
        ]);

        if ($response->failed()) {
            throw new \Exception('Failed to refresh Trustap token: ' . $response->body());
        }

        $data = $response->json();

        $user->update([
            'trustap_access_token' => $data['access_token'],
            'trustap_refresh_token' => $data['refresh_token'] ?? $user->trustap_refresh_token,
            // optionally store expiry time
            'trustap_token_expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return $data['access_token'];
    }

    public function addTracking($transactionId, $sellerTrustapId, $carrier, $trackingCode)
    {
        $response = Http::withBasicAuth($this->apiKey, '')
            ->withHeaders([
                'Trustap-User' => $sellerTrustapId,
                'Content-Type' => 'application/json'
            ])
            ->post(
                $this->baseUrl . "/transactions/{$transactionId}/track_with_guest_seller",
                [
                    'carrier' => $carrier,
                    'tracking_code' => $trackingCode
                ]
            );

        return $response->json();
    }


    public function confirmDelivery($transactionId, $sellerTrustapId)
    {
        $response = Http::withBasicAuth($this->apiKey, '')
            ->withHeaders([
                'Trustap-User' => $sellerTrustapId,
                'Content-Type' => 'application/json'
            ])
            ->post($this->baseUrl . "/transactions/{$transactionId}/confirm_delivery_with_guest_buyer");

        return $response->json();
    }

}