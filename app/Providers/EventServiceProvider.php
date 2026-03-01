<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Parcy\Mpesa\Events\PaymentSuccessful;
use Parcy\Mpesa\Events\PaymentFailed;
use Parcy\Mpesa\Events\PaymentCancelled;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
            // M-Pesa payment events (parcy/laravel-mpesa)
        PaymentSuccessful::class => [
            \App\Listeners\MarkSaleAsPaid::class,
        ],
        PaymentFailed::class => [
            \App\Listeners\HandleFailedPayment::class,
        ],
        PaymentCancelled::class => [
            \App\Listeners\HandleCancelledPayment::class,
        ],

        // UltimatePOS accounting events
        \App\Events\TransactionPaymentAdded::class => [
            \App\Listeners\AddAccountTransaction::class,
        ],

        \App\Events\TransactionPaymentUpdated::class => [
            \App\Listeners\UpdateAccountTransaction::class,
        ],

        \App\Events\TransactionPaymentDeleted::class => [
            \App\Listeners\DeleteAccountTransaction::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {

        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
