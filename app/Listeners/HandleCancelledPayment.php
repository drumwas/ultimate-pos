<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Parcy\Mpesa\Events\PaymentCancelled;

class HandleCancelledPayment
{
    /**
     * Handle the PaymentCancelled event (customer dismissed the STK prompt).
     *
     * Result code 1032 — user cancelled on their phone.
     * Sale stays as 'due' so the cashier can retry or switch payment method.
     */
    public function handle(PaymentCancelled $event): void
    {
        $mpesaTransaction = $event->transaction;

        Log::info('[MpesaListener] Payment cancelled by customer', [
            'transaction_id' => $mpesaTransaction->reference,
            'result_desc'    => $mpesaTransaction->result_desc,
        ]);

        // No sale update needed — just let the POS frontend know via the status poll
    }
}
