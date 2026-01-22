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
                 Log::info("✅ shop data stripe id{$shop->stripe_account_id}");

                // Amount in cents
                // $netAmount = $transferRecord->amount_cents - $transferRecord->platform_fee_cents;
                $netAmount = $transferRecord->net_amount_cents;

                $transfer = Transfer::create([
                    'amount' => $transferRecord->net_amount_cents, // ✅ USD cents
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
