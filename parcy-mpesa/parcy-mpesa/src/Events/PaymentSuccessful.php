<?php

namespace Parcy\Mpesa\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Parcy\Mpesa\Models\MpesaTransaction;

class PaymentSuccessful
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly MpesaTransaction $transaction
    ) {}
}
