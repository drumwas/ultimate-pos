<?php

namespace App\Listeners;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Parcy\Mpesa\Events\PaymentFailed;

class HandleFailedPayment
{
    /**
     * Handle the PaymentFailed event.
     *
     * For the POS we mostly just log it — the cashier will see the "failed"
     * status via the polling endpoint and can retry or switch payment method.
     *
     * Add any extra business logic here if needed (e.g. notifications).
     */
    public function handle(PaymentFailed $event): void
    {
        $mpesaTransaction = $event->transaction;

        Log::warning('[MpesaListener] Payment failed', [
            'transaction_id'  => $mpesaTransaction->reference,
            'result_code'     => $mpesaTransaction->result_code,
            'result_desc'     => $mpesaTransaction->result_desc,
            'receipt'         => $mpesaTransaction->mpesa_receipt_number,
        ]);

        // Optionally update the sale to keep an audit note
        $sale = Transaction::find($mpesaTransaction->reference);
        if ($sale) {
            // Don't change payment_status — sale remains 'due' so cashier can retry
            // But log it against the sale for audit
            Log::info('[MpesaListener] Sale remains unpaid after failed M-Pesa attempt', [
                'sale_id'     => $sale->id,
                'reason'      => $mpesaTransaction->result_desc,
            ]);
        }
    }
}
