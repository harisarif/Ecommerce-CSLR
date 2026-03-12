<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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