<?php

/**
 * -----------------------------------------------------------------------
 * ULTIMATE POS — Routes
 * -----------------------------------------------------------------------
 * Add these routes to your existing routes/web.php inside the auth
 * middleware group so only logged-in staff can trigger payments.
 *
 * The package's own callback routes (for Safaricom) are auto-registered
 * at api/mpesa/* by the MpesaServiceProvider — no changes needed there.
 * -----------------------------------------------------------------------
 */

use App\Http\Controllers\MpesaPaymentController;
use Illuminate\Support\Facades\Route;

// Inside your existing: Route::middleware(['auth', ...'])->group(function () { ... });

Route::prefix('mpesa')->name('mpesa.')->group(function () {

    // Cashier initiates STK Push from POS payment modal
    Route::post('/pay', [MpesaPaymentController::class, 'initiate'])->name('pay');

    // Frontend polls this every 3 seconds to check payment status
    Route::get('/status/{transaction_id}', [MpesaPaymentController::class, 'status'])->name('status');

});
