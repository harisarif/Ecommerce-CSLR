<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PaymentTransfer;
use Stripe\Stripe;
use Stripe\Transfer;
use Illuminate\Support\Facades\Log;

class ReleaseHoldPayments extends Command
{
    protected $signature = 'payments:release-hold';
    protected $description = 'Release payments on hold after 7 days to connected shops';

    public function handle()
    {
        Log::info('🕒 Starting ReleaseHoldPayments command'); // ✅ Top log
        $this->info('🕒 Starting ReleaseHoldPayments command'); // Console output
        Stripe::setApiKey(env('STRIPE_SECRET'));

        $today = now();

        // Get all PaymentTransfer on hold and release_at <= today
        $transfers = PaymentTransfer::where('status', 'on_hold')
            ->where('release_at', '<=', $today)
            ->get();
            
       Log::info("✅ Payment Transfer data{$transfers}");
        foreach ($transfers as $transferRecord) {
            try {
                // Get shop's stripe account
                $shop = $transferRecord->shop;
                 Log::info("✅ shop data{$shop}");
                if (!$shop || !$shop->stripe_account_id) {
                    Log::warning("Shop not connected to Stripe: ID {$transferRecord->shop_id}");
                    continue;
                }

                 // If shop is illegal → refund instead of sending payment
                    if ($shop && $shop->illegal) {
                        if ($transferRecord->payment_intent_id) {
                            \Stripe\Refund::create([
                                'payment_intent' => $transferRecord->payment_intent_id,
                                'reason' => 'requested_by_customer',
                                'metadata' => [
                                    'payment_transfer_id' => $transferRecord->id,
                                    'shop_id' => $transferRecord->shop_id,
                                ],
                            ]);

                            $transferRecord->status = 'refunded';
                            $transferRecord->release_at = now();
                            $transferRecord->save();

                            Log::warning("⚠️ PaymentTransfer {$transferRecord->id} refunded due to illegal shop ID {$shop->id}");
                        }
                        continue; // skip sending money to illegal shop
                    }
                 Log::info("✅ shop data stripe id{$shop->stripe_account_id}");

                // Amount in cents
                // $netAmount = $transferRecord->net_amount_cents;
                $amountToSend = ($transferRecord->gross_amount_cents ?? 0) - ($transferRecord->platform_fee_cents ?? 0);
                $transfer = Transfer::create([
                    'amount' => $amountToSend, // ✅ USD cents
                    'currency' => $transferRecord->settlement_currency, // 'usd'
                    'destination' => $shop->stripe_account_id,
                    'metadata' => [
                        'payment_transfer_id' => $transferRecord->id,
                        'order_id' => $transferRecord->order_id,
                    ],
                ]);

                // Update record
                $transferRecord->status = 'released';
                $transferRecord->stripe_transfer_id = $transfer->id;
                $transferRecord->release_at = now();
                $transferRecord->save();

                Log::info("✅ Released payment to shop ID {$shop->id}, transfer ID {$transfer->id}");

            } catch (\Exception $e) {
                Log::error("❌ Failed to release payment ID {$transferRecord->id}: " . $e->getMessage());
            }
        }

        $this->info('✅ Payment release cron completed.');
    }
}
