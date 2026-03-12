<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PaymentTransfer;
use Stripe\Stripe;
use Stripe\Refund;
use Illuminate\Support\Facades\Log;

class RefundIllegalPayments extends Command
{
    protected $signature = 'payments:refund-illegal';
    protected $description = 'Refund PaymentIntents for illegal / rejected shops';

    public function handle()
    {
        $this->info('🕒 Starting illegal payment refund job');
        Log::info('🕒 Starting illegal payment refund job');

        Stripe::setApiKey(env('STRIPE_SECRET'));

        $refunds = PaymentTransfer::whereIn('status', ['on_hold'])
            ->whereNotNull('payment_intent_id')
            ->get();

        foreach ($refunds as $record) {
            try {

                // // 🧪 TEST MODE — simulate refund
                // if (data_get($record->meta, 'test_mode') === true) {
                //     $record->status = 'refunded';
                //     $record->save();

                //     Log::info("🧪 Test refund simulated for PaymentTransfer {$record->id}");
                //     continue;
                // }

                // 🔐 REAL STRIPE REFUND (if live payment)
                $refund = \Stripe\Refund::create([
                    'payment_intent' => $record->payment_intent_id,
                    'reason' => 'requested_by_customer',
                    'metadata' => [
                        'payment_transfer_id' => $record->id,
                        'shop_id' => $record->shop_id,
                    ],
                ]);

                // update status only, no refund id field
                $record->status = 'refunded';
                $record->save();

                Log::info("✅ Refunded PaymentIntent {$record->payment_intent_id}");

            } catch (\Exception $e) {
                Log::error("❌ Refund failed for PaymentTransfer {$record->id}: " . $e->getMessage());
            }
        }

        $this->info('✅ Refund cron completed');
    }
}
