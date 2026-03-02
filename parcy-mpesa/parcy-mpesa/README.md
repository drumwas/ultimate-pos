# parcy/laravel-mpesa

M-Pesa Daraja API package for Laravel applications by Parcy Analytics.

---

## Installation

### 1. Add the package locally (Ultimate POS)

In your Ultimate POS `composer.json`, add a path repository pointing to where you cloned this package:

```json
"repositories": [
    {
        "type": "path",
        "url": "../parcy-mpesa"
    }
],
"require": {
    "parcy/laravel-mpesa": "*"
}
```

Then run:
```bash
composer update parcy/laravel-mpesa
```

### 2. Publish config and run migrations

```bash
php artisan vendor:publish --tag=mpesa-config
php artisan vendor:publish --tag=mpesa-migrations
php artisan migrate
```

### 3. Add environment variables

Copy the block below into your `.env`:

```env
MPESA_ENVIRONMENT=sandbox

MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret

# STK Push
MPESA_STK_SHORTCODE=174379
MPESA_STK_PASSKEY=your_passkey
MPESA_STK_TRANSACTION_TYPE=CustomerPayBillOnline   # or CustomerBuyGoodsOnline for Till

# C2B (optional — only if using Paybill manual payments as fallback)
MPESA_C2B_SHORTCODE=600000

# B2C (optional — only if issuing refunds or payouts)
MPESA_B2C_SHORTCODE=600000
MPESA_B2C_INITIATOR_NAME=testapi
MPESA_B2C_SECURITY_CREDENTIAL=your_encrypted_credential

# Auto-reconciliation — resolve pending STK after this many minutes
MPESA_RECONCILIATION_MINUTES=2
```

> **Callback URLs**: In sandbox, use [ngrok](https://ngrok.com) to expose your local server.
> In production, the package auto-generates URLs from your `APP_URL`. You can override any
> callback URL directly in `.env` (e.g. `MPESA_STK_CALLBACK_URL=https://yourdomain.com/api/mpesa/stk/callback`).

---

## Usage

### Initiate STK Push (in your POS payment controller)

```php
use Parcy\Mpesa\Facades\Mpesa;

$response = Mpesa::stkPush(
    phone: '0712345678',
    amount: 1500,
    reference: $sale->id,       // your invoice/sale ID
    description: 'Payment for Sale #' . $sale->id
);

if (!$response) {
    // Daraja API call failed — show error to cashier
}

// The transaction is now saved as 'pending' in mpesa_transactions
// Poll /api/mpesa/stk/status?reference={sale_id} from your frontend
```

### Poll for payment status (frontend JS)

```javascript
async function pollPaymentStatus(reference) {
    const interval = setInterval(async () => {
        const res  = await fetch(`/api/mpesa/stk/status?reference=${reference}`);
        const data = await res.json();

        if (data.status === 'completed') {
            clearInterval(interval);
            showSuccess(data.receipt_number);
        } else if (['failed', 'cancelled'].includes(data.status)) {
            clearInterval(interval);
            showError(data.result_desc);
        }
    }, 3000); // poll every 3 seconds

    // Stop polling after 90 seconds regardless (STK push expires in ~60s)
    setTimeout(() => clearInterval(interval), 90000);
}
```

---

## Listening to Events in Ultimate POS

The package fires events after processing callbacks. Register listeners in your
`app/Providers/EventServiceProvider.php` to react when payment lands:

```php
use Parcy\Mpesa\Events\PaymentSuccessful;
use Parcy\Mpesa\Events\PaymentFailed;
use Parcy\Mpesa\Events\PaymentCancelled;

protected $listen = [
    PaymentSuccessful::class => [
        \App\Listeners\MarkSaleAsPaid::class,
    ],
    PaymentFailed::class => [
        \App\Listeners\HandleFailedPayment::class,
    ],
    PaymentCancelled::class => [
        \App\Listeners\HandleCancelledPayment::class,
    ],
];
```

### Example Listener — MarkSaleAsPaid

```php
namespace App\Listeners;

use Parcy\Mpesa\Events\PaymentSuccessful;
use App\Models\Sale;

class MarkSaleAsPaid
{
    public function handle(PaymentSuccessful $event): void
    {
        $transaction = $event->transaction;

        $sale = Sale::find($transaction->reference);
        if (!$sale) return;

        $sale->update([
            'payment_status'    => 'paid',
            'payment_method'    => 'mpesa',
            'mpesa_receipt'     => $transaction->mpesa_receipt_number,
        ]);
    }
}
```

---

## Auto-Reconciliation (Scheduled Command)

Add to your `app/Console/Kernel.php` to automatically resolve pending transactions:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('mpesa:reconcile')->everyFiveMinutes();
}
```

Or run manually:
```bash
php artisan mpesa:reconcile --minutes=3
```

---

## Available Routes

| Method | URL | Description |
|--------|-----|-------------|
| POST | `/api/mpesa/stk/callback` | Safaricom STK callback |
| GET  | `/api/mpesa/stk/status`   | Poll payment status (POS frontend) |
| POST | `/api/mpesa/c2b/validation` | C2B Validation URL |
| POST | `/api/mpesa/c2b/confirmation` | C2B Confirmation URL |
| POST | `/api/mpesa/b2c/result` | B2C Result URL |
| POST | `/api/mpesa/b2c/timeout` | B2C Timeout URL |

> Make sure callback routes are **excluded from CSRF protection** in your
> `app/Http/Middleware/VerifyCsrfToken.php`:
> ```php
> protected $except = [
>     'api/mpesa/*',
> ];
> ```

---

## Queue Setup

The package dispatches jobs for callback processing. Ensure your queue worker is running:

```bash
php artisan queue:work --tries=3
```

For production, use Supervisor to keep the worker alive.

---

## Facade Reference

```php
Mpesa::stkPush(string $phone, int $amount, string $reference, string $description): ?array
Mpesa::stkQuery(string $checkoutRequestId): ?array
Mpesa::c2bRegisterUrls(): ?array
Mpesa::b2c(string $phone, int $amount, string $reference, string $remarks): ?array
```

---

## Package Structure

```
src/
  MpesaServiceProvider.php
  Facades/Mpesa.php
  Services/MpesaService.php
  Jobs/ProcessMpesaCallback.php
  Models/MpesaTransaction.php
  Http/Controllers/MpesaCallbackController.php
  Events/PaymentSuccessful.php
  Events/PaymentFailed.php
  Events/PaymentCancelled.php
  Console/ReconcilePendingTransactions.php
database/migrations/create_mpesa_transactions_table.php
config/mpesa.php
routes/api.php
```
