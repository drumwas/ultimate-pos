<?php

namespace Parcy\Mpesa\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Parcy\Mpesa\Events\PaymentCancelled;
use Parcy\Mpesa\Events\PaymentFailed;
use Parcy\Mpesa\Events\PaymentSuccessful;
use Parcy\Mpesa\Models\MpesaTransaction;

class ProcessMpesaCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        public readonly string $callbackType,  // stk | c2b | b2c
        public readonly array  $payload
    ) {}

    public function handle(): void
    {
        match ($this->callbackType) {
            'stk' => $this->handleStk(),
            'c2b' => $this->handleC2b(),
            'b2c' => $this->handleB2c(),
            default => Log::warning('[Mpesa] Unknown callback type: ' . $this->callbackType),
        };
    }

    // -------------------------------------------------------------------------
    // STK Push callback
    // -------------------------------------------------------------------------

    private function handleStk(): void
    {
        $callback         = $this->payload['Body']['stkCallback'] ?? null;

        if (!$callback) {
            Log::error('[Mpesa] Invalid STK callback payload', $this->payload);
            return;
        }

        $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
        $resultCode        = (int) ($callback['ResultCode'] ?? -1);
        $resultDesc        = $callback['ResultDesc'] ?? '';

        /** @var MpesaTransaction|null $transaction */
        $transaction = $this->resolveModel()
            ::where('checkout_request_id', $checkoutRequestId)
            ->first();

        if (!$transaction) {
            Log::warning('[Mpesa] STK callback: transaction not found', ['checkout_request_id' => $checkoutRequestId]);
            return;
        }

        // Idempotency — skip if already processed
        if (!$transaction->isPending()) {
            Log::info('[Mpesa] STK callback: already processed, skipping', ['id' => $transaction->id]);
            return;
        }

        DB::transaction(function () use ($transaction, $resultCode, $resultDesc, $callback) {
            if ($resultCode === 0) {
                // Extract metadata from callback
                $items           = collect($callback['CallbackMetadata']['Item'] ?? []);
                $receiptNumber   = $items->firstWhere('Name', 'MpesaReceiptNumber')['Value'] ?? null;
                $transactedPhone = $items->firstWhere('Name', 'PhoneNumber')['Value'] ?? null;
                $amount          = $items->firstWhere('Name', 'Amount')['Value'] ?? $transaction->amount;

                $transaction->update([
                    'status'               => 'completed',
                    'mpesa_receipt_number' => $receiptNumber,
                    'result_code'          => $resultCode,
                    'result_desc'          => $resultDesc,
                    'amount'               => $amount,
                    'phone'                => $transactedPhone ?? $transaction->phone,
                    'raw_callback'         => json_encode($callback),
                ]);

                event(new PaymentSuccessful($transaction->fresh()));

            } elseif ($resultCode === 1032) {
                // 1032 = request cancelled by user
                $transaction->update([
                    'status'       => 'cancelled',
                    'result_code'  => $resultCode,
                    'result_desc'  => $resultDesc,
                    'raw_callback' => json_encode($callback),
                ]);

                event(new PaymentCancelled($transaction->fresh()));

            } else {
                $transaction->update([
                    'status'       => 'failed',
                    'result_code'  => $resultCode,
                    'result_desc'  => $resultDesc,
                    'raw_callback' => json_encode($callback),
                ]);

                event(new PaymentFailed($transaction->fresh()));
            }
        });
    }

    // -------------------------------------------------------------------------
    // C2B Confirmation callback
    // -------------------------------------------------------------------------

    private function handleC2b(): void
    {
        $data = $this->payload;

        $transactionId = $data['TransID'] ?? null;
        $phone         = $data['MSISDN'] ?? null;
        $amount        = (int) ($data['TransAmount'] ?? 0);
        $reference     = $data['BillRefNumber'] ?? null;

        if (!$transactionId) {
            Log::error('[Mpesa] C2B callback missing TransID', $data);
            return;
        }

        // Idempotency — check if this TransID was already recorded
        $existing = $this->resolveModel()::where('mpesa_receipt_number', $transactionId)->first();
        if ($existing) {
            Log::info('[Mpesa] C2B: duplicate callback, skipping', ['TransID' => $transactionId]);
            return;
        }

        DB::transaction(function () use ($data, $transactionId, $phone, $amount, $reference) {
            $transaction = $this->resolveModel()::create([
                'type'                 => 'c2b',
                'status'               => 'completed',
                'phone'                => $phone,
                'amount'               => $amount,
                'reference'            => $reference,
                'mpesa_receipt_number' => $transactionId,
                'result_code'          => 0,
                'result_desc'          => 'Success',
                'raw_callback'         => json_encode($data),
            ]);

            event(new PaymentSuccessful($transaction));
        });
    }

    // -------------------------------------------------------------------------
    // B2C Result callback
    // -------------------------------------------------------------------------

    private function handleB2c(): void
    {
        $result = $this->payload['Result'] ?? null;

        if (!$result) {
            Log::error('[Mpesa] Invalid B2C callback payload', $this->payload);
            return;
        }

        $conversationId = $result['ConversationID'] ?? null;
        $resultCode     = (int) ($result['ResultCode'] ?? -1);
        $resultDesc     = $result['ResultDesc'] ?? '';

        $transaction = $this->resolveModel()
            ::where('conversation_id', $conversationId)
            ->first();

        if (!$transaction) {
            Log::warning('[Mpesa] B2C callback: transaction not found', ['conversation_id' => $conversationId]);
            return;
        }

        if (!$transaction->isPending()) {
            return;
        }

        DB::transaction(function () use ($transaction, $result, $resultCode, $resultDesc) {
            if ($resultCode === 0) {
                $params        = collect($result['ResultParameters']['ResultParameter'] ?? []);
                $receiptNumber = $params->firstWhere('Key', 'TransactionReceipt')['Value'] ?? null;

                $transaction->update([
                    'status'               => 'completed',
                    'mpesa_receipt_number' => $receiptNumber,
                    'result_code'          => $resultCode,
                    'result_desc'          => $resultDesc,
                    'raw_callback'         => json_encode($result),
                ]);

                event(new PaymentSuccessful($transaction->fresh()));
            } else {
                $transaction->update([
                    'status'       => 'failed',
                    'result_code'  => $resultCode,
                    'result_desc'  => $resultDesc,
                    'raw_callback' => json_encode($result),
                ]);

                event(new PaymentFailed($transaction->fresh()));
            }
        });
    }

    // -------------------------------------------------------------------------

    private function resolveModel(): string
    {
        return config('mpesa.transaction_model', MpesaTransaction::class);
    }
}
