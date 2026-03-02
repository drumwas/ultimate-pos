<?php

use Illuminate\Support\Facades\Route;
use Parcy\Mpesa\Http\Controllers\MpesaCallbackController;

$prefix     = config('mpesa.routes.prefix', 'api/mpesa');
$middleware = config('mpesa.routes.middleware', []);

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('mpesa.')
    ->group(function () {

        // -----------------------------------------------------------------
        // STK Push
        // -----------------------------------------------------------------
        // Safaricom hits this after customer responds to the STK push
        Route::post('/stk/callback', [MpesaCallbackController::class, 'stkCallback'])
            ->name('stk.callback');

        // Your POS frontend polls this to know if payment succeeded
        Route::get('/stk/status', [MpesaCallbackController::class, 'stkStatus'])
            ->name('stk.status');

        // -----------------------------------------------------------------
        // C2B
        // -----------------------------------------------------------------
        Route::post('/c2b/validation', [MpesaCallbackController::class, 'c2bValidation'])
            ->name('c2b.validation');

        Route::post('/c2b/confirmation', [MpesaCallbackController::class, 'c2bConfirmation'])
            ->name('c2b.confirmation');

        // -----------------------------------------------------------------
        // B2C
        // -----------------------------------------------------------------
        Route::post('/b2c/result', [MpesaCallbackController::class, 'b2cResult'])
            ->name('b2c.result');

        Route::post('/b2c/timeout', [MpesaCallbackController::class, 'b2cTimeout'])
            ->name('b2c.timeout');
    });
