<?php

namespace Parcy\Mpesa\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array|null stkPush(string $phone, int $amount, string $reference, string $description)
 * @method static array|null stkQuery(string $checkoutRequestId)
 * @method static array|null c2bRegisterUrls()
 * @method static array|null b2c(string $phone, int $amount, string $reference, string $remarks)
 *
 * @see \Parcy\Mpesa\Services\MpesaService
 */
class Mpesa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mpesa';
    }
}
