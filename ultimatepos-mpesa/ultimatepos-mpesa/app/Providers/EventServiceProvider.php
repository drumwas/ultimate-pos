<?php

/**
 * -----------------------------------------------------------------------
 * ULTIMATE POS — EventServiceProvider
 * -----------------------------------------------------------------------
 * Add the M-Pesa event listeners to your existing EventServiceProvider.
 * File: app/Providers/EventServiceProvider.php
 *
 * The three events (PaymentSuccessful, PaymentFailed, PaymentCancelled)
 * are fired by the parcy/laravel-mpesa package after processing Safaricom
 * callbacks. Each listener handles the UltimatePOS-specific business logic.
 * -----------------------------------------------------------------------
 */

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Parcy\Mpesa\Events\PaymentCancelled;
use Parcy\Mpesa\Events\PaymentFailed;
use Parcy\Mpesa\Events\PaymentSuccessful;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // ---------------------------------------------------------------
        // M-Pesa events from parcy/laravel-mpesa
        // ---------------------------------------------------------------
        PaymentSuccessful::class => [
            \App\Listeners\MarkSaleAsPaid::class,
        ],

        PaymentFailed::class => [
            \App\Listeners\HandleFailedPayment::class,
        ],

        PaymentCancelled::class => [
            \App\Listeners\HandleCancelledPayment::class,
        ],

        // ---------------------------------------------------------------
        // Keep any existing UltimatePOS event listeners below
        // ---------------------------------------------------------------
    ];

    public function boot(): void
    {
        //
    }
}
