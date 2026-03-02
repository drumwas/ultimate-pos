<?php

namespace Parcy\Mpesa\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Parcy\Mpesa\Models\MpesaTransaction;
use Parcy\Mpesa\Services\MpesaService;
use Parcy\Mpesa\Events\PaymentSuccessful;
use Parcy\Mpesa\Events\PaymentFailed;

class ReconcilePendingTransactions extends Command
{
    protected $signature   = 'mpesa:reconcile {--minutes=2 : Transactions older than this many minutes}';
    protected $description = 'Query Daraja for pending STK Push transactions and update their status';

    public function handle(MpesaService $mpesa): int
    {
        $minutes = (int) $this->option('minutes');
        $model   = config('mpesa.transaction_model', MpesaTransaction::class);

        $pending = $model::pending()
            ->where('type', 'stk_push')
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->whereNotNull('checkout_request_id')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending transactions to reconcile.');
            return self::SUCCESS;
        }

        $this->info("Reconciling {$pending->count()} pending transaction(s)...");

        foreach ($pending as $transaction) {
            try {
                $result = $mpesa->stkQuery($transaction->checkout_request_id);

                if (!$result) {
                    $this->warn("  ↳ [{$transaction->id}] No response from Daraja, skipping.");
                    continue;
                }

                $resultCode = (int) ($result['ResultCode'] ?? -1);
                $resultDesc = $result['ResultDesc'] ?? '';

                if ($resultCode === 0) {
                    $transaction->update([
                        'status'      => 'completed',
                        'result_code' => $resultCode,
                        'result_desc' => $resultDesc,
                    ]);
                    event(new PaymentSuccessful($transaction->fresh()));
                    $this->info("  ✓ [{$transaction->id}] Marked completed.");
                } else {
                    $transaction->update([
                        'status'      => 'failed',
                        'result_code' => $resultCode,
                        'result_desc' => $resultDesc,
                    ]);
                    event(new PaymentFailed($transaction->fresh()));
                    $this->warn("  ✗ [{$transaction->id}] Marked failed: {$resultDesc}");
                }
            } catch (\Throwable $e) {
                Log::error('[Mpesa] Reconciliation error for transaction ' . $transaction->id . ': ' . $e->getMessage());
                $this->error("  ! [{$transaction->id}] Error: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
