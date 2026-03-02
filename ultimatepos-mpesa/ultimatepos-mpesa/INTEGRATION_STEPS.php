<?php

/**
 * =============================================================================
 * ULTIMATE POS — Integration Checklist & Code Snippets
 * =============================================================================
 *
 * After dropping the files in place, apply these small edits to your
 * existing UltimatePOS files. Each section shows exactly what to add and where.
 * =============================================================================
 */


// -----------------------------------------------------------------------------
// 1. app/Console/Kernel.php
// -----------------------------------------------------------------------------
// Add this inside your existing schedule() method:

protected function schedule(Schedule $schedule): void
{
    // ... existing schedules ...

    // Auto-reconcile M-Pesa transactions that haven't received a callback
    $schedule->command('mpesa:reconcile')->everyFiveMinutes();
}


// -----------------------------------------------------------------------------
// 2. app/Http/Middleware/VerifyCsrfToken.php
// -----------------------------------------------------------------------------
// Add Safaricom callback routes to the exclusion list:

protected $except = [
    // ... any existing exclusions ...
    'api/mpesa/*',
];


// -----------------------------------------------------------------------------
// 3. .env additions
// -----------------------------------------------------------------------------
/*
MPESA_ENVIRONMENT=sandbox

MPESA_CONSUMER_KEY=your_key_here
MPESA_CONSUMER_SECRET=your_secret_here

MPESA_STK_SHORTCODE=174379
MPESA_STK_PASSKEY=your_passkey_here

# Use CustomerBuyGoodsOnline if you're on a Till number instead of Paybill
MPESA_STK_TRANSACTION_TYPE=CustomerPayBillOnline

# Which custom payment slot to use in UltimatePOS for M-Pesa
# Go to Business Settings → Payment Methods and label custom_pay_1 as "M-Pesa"
MPESA_POS_PAYMENT_METHOD=custom_pay_1

MPESA_RECONCILIATION_MINUTES=2
*/


// -----------------------------------------------------------------------------
// 4. config/mpesa.php — add this key after publishing the config
// -----------------------------------------------------------------------------
// The config is published to config/mpesa.php. Add one extra key at the bottom:

/*
    |--------------------------------------------------------------------------
    | UltimatePOS Payment Method Slot
    |--------------------------------------------------------------------------
    | Maps M-Pesa to one of UltimatePOS's custom payment method slots.
    | Label this slot "M-Pesa" in Business Settings → Payment Methods.
    |
    | Options: custom_pay_1 | custom_pay_2 | custom_pay_3
    */
    'pos_payment_method' => env('MPESA_POS_PAYMENT_METHOD', 'custom_pay_1'),


// -----------------------------------------------------------------------------
// 5. routes/web.php — add inside the auth middleware group
// -----------------------------------------------------------------------------

Route::prefix('mpesa')->name('mpesa.')->group(function () {
    Route::post('/pay', [\App\Http\Controllers\MpesaPaymentController::class, 'initiate'])->name('pay');
    Route::get('/status/{transaction_id}', [\App\Http\Controllers\MpesaPaymentController::class, 'status'])->name('status');
});


// -----------------------------------------------------------------------------
// 6. resources/views/sale/pos.blade.php — add the M-Pesa tab
// -----------------------------------------------------------------------------
// Inside the payment method tabs <ul class="nav nav-tabs"> add:

/*
<li class="nav-item">
    <a class="nav-link" id="mpesa-tab" data-toggle="tab" href="#mpesa-payment-pane" role="tab">
        <i class="fas fa-mobile-alt"></i> M-Pesa
    </a>
</li>
*/

// And inside <div class="tab-content"> add:
// @include('mpesa.payment_modal')


// -----------------------------------------------------------------------------
// 7. UltimatePOS Business Settings → Payment Methods
// -----------------------------------------------------------------------------
// After installing:
//   Settings > Business Settings > Payment Methods
//   Enable "Custom Payment 1" (or whichever slot you chose)
//   Set the label to: M-Pesa
//   This makes M-Pesa appear in UltimatePOS reports under "M-Pesa"
