<?php

namespace App\Listeners;

use App\Models\Transaction;
use App\Models\TransactionPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Parcy\Mpesa\Events\PaymentSuccessful;

class MarkSaleAsPaid
{
    /**
     * Handle the PaymentSuccessful event fired by parcy/laravel-mpesa.
     *
     * Ultimate POS stores each payment in `transaction_payments` and tracks
     * the outstanding balance on `transactions.payment_status`.
     *
     * payment_status values used by UltimatePOS:
     *   paid    — fully settled
     *   partial — partially settled
     *   due     — nothing paid yet
     *
     * transaction_payments.method values accepted by UltimatePOS:
     *   cash | card | cheque | bank_transfer | custom_pay_1 | custom_pay_2 | custom_pay_3
     *
     * We store M-Pesa as 'custom_pay_1' and label it "M-Pesa" in Business settings.
     * Change MPESA_POS_METHOD in .env if you've mapped it to a different custom slot.
     */
    public function handle(PaymentSuccessful $event): void
    {
        $mpesaTransaction = $event->transaction;
        $transactionId    = $mpesaTransaction->reference; // we stored the sale ID as reference

        if (!$transactionId) {
            Log::warning('[MpesaListener] PaymentSuccessful has no reference (transaction ID)', [
                'mpesa_transaction_id' => $mpesaTransaction->id,
            ]);
            return;
        }

        /** @var Transaction|null $sale */
        $sale = Transaction::find($transactionId);

        if (!$sale) {
            Log::warning('[MpesaListener] Sale not found for M-Pesa payment', [
                'transaction_id'       => $transactionId,
                'mpesa_transaction_id' => $mpesaTransaction->id,
            ]);
            return;
        }

        // Guard: already fully paid — nothing to do
        if ($sale->payment_status === 'paid') {
            Log::info('[MpesaListener] Sale already marked paid, skipping', ['transaction_id' => $transactionId]);
            return;
        }

        DB::transaction(function () use ($sale, $mpesaTransaction) {

            $paymentMethod = config('mpesa.pos_payment_method', 'custom_pay_1');

            // 1. Record the payment in transaction_payments
            TransactionPayment::create([
                'transaction_id' => $sale->id,
                'business_id'    => $sale->business_id,
                'amount'         => $mpesaTransaction->amount,
                'method'         => $paymentMethod,
                'paid_on'        => now(),
                'created_by'     => $sale->created_by, // attribute to the cashier who opened the sale
                'note'           => 'M-Pesa | Receipt: ' . $mpesaTransaction->mpesa_receipt_number,
                'payment_for'    => 'business_add_sale',
            ]);

            // 2. Recalculate how much is still outstanding
            $totalPaid = $sale->transaction_payments()->sum('amount');
            $finalTotal = (float) $sale->final_total;

            if ($totalPaid >= $finalTotal) {
                $paymentStatus = 'paid';
            } elseif ($totalPaid > 0) {
                $paymentStatus = 'partial';
            } else {
                $paymentStatus = 'due';
            }

            // 3. Update the transaction status
            $sale->update([
                'payment_status' => $paymentStatus,
            ]);

            Log::info('[MpesaListener] Sale payment recorded', [
                'transaction_id'       => $sale->id,
                'payment_status'       => $paymentStatus,
                'amount_paid'          => $mpesaTransaction->amount,
                'total_paid_to_date'   => $totalPaid,
                'final_total'          => $finalTotal,
                'receipt'              => $mpesaTransaction->mpesa_receipt_number,
            ]);
        });
    }
}
