<?php

namespace Parcy\Mpesa\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Parcy\Mpesa\Models\MpesaTransaction;

class MpesaService
{
    protected string $environment;
    protected string $consumerKey;
    protected string $consumerSecret;

    // Base URLs
    protected string $sandboxBase = 'https://sandbox.safaricom.co.ke';
    protected string $productionBase = 'https://api.safaricom.co.ke';

    public function __construct()
    {
        $this->environment    = config('mpesa.environment', 'sandbox');
        $this->consumerKey    = config('mpesa.consumer_key');
        $this->consumerSecret = config('mpesa.consumer_secret');
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    /**
     * Get a cached OAuth access token from Daraja.
     */
    public function authenticate(): string
    {
        return Cache::remember('mpesa_access_token', now()->addMinutes(50), function () {
            $url = $this->baseUrl() . '/oauth/v1/generate?grant_type=client_credentials';

            $response = Http::retry(3, 200)
                ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get($url);

            if ($response->failed()) {
                Log::error('[Mpesa] Authentication failed', $response->json() ?? []);
                throw new \RuntimeException('M-Pesa authentication failed: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    // -------------------------------------------------------------------------
    // STK Push
    // -------------------------------------------------------------------------

    /**
     * Initiate an STK Push to a customer's phone.
     *
     * @return array|null  Daraja response (contains CheckoutRequestID on success)
     */
    public function stkPush(string $phone, int $amount, string $reference, string $description): ?array
    {
        $phone       = $this->normalizePhone($phone);
        $shortcode   = config('mpesa.stk.shortcode');
        $passkey     = config('mpesa.stk.passkey');
        $timestamp   = now()->format('YmdHis');
        $password    = base64_encode($shortcode . $passkey . $timestamp);
        $callbackUrl = config('mpesa.stk.callback_url') ?: route('mpesa.stk.callback');

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => config('mpesa.stk.transaction_type', 'CustomerPayBillOnline'),
            'Amount'            => $amount,
            'PartyA'            => $phone,
            'PartyB'            => $shortcode,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => $callbackUrl,
            'AccountReference'  => $reference,
            'TransactionDesc'   => $description,
        ];

        Log::info('[Mpesa] STK Push initiated', ['phone' => $phone, 'amount' => $amount, 'reference' => $reference]);

        $response = $this->post('/mpesa/stkpush/v1/processrequest', $payload);

        if (!$response) {
            return null;
        }

        // Persist the pending transaction
        $this->createTransaction([
            'type'                => 'stk_push',
            'status'              => 'pending',
            'phone'               => $phone,
            'amount'              => $amount,
            'reference'           => $reference,
            'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
            'merchant_request_id' => $response['MerchantRequestID'] ?? null,
            'raw_response'        => json_encode($response),
        ]);

        return $response;
    }

    /**
     * Query the status of an STK Push transaction directly from Daraja.
     */
    public function stkQuery(string $checkoutRequestId): ?array
    {
        $shortcode = config('mpesa.stk.shortcode');
        $passkey   = config('mpesa.stk.passkey');
        $timestamp = now()->format('YmdHis');
        $password  = base64_encode($shortcode . $passkey . $timestamp);

        return $this->post('/mpesa/stkpushquery/v1/query', [
            'BusinessShortCode' => $shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ]);
    }

    // -------------------------------------------------------------------------
    // C2B
    // -------------------------------------------------------------------------

    /**
     * Register C2B confirmation and validation URLs with Safaricom.
     */
    public function c2bRegisterUrls(): ?array
    {
        return $this->post('/mpesa/c2b/v1/registerurl', [
            'ShortCode'       => config('mpesa.c2b.shortcode'),
            'ResponseType'    => config('mpesa.c2b.response_type', 'Completed'),
            'ConfirmationURL' => config('mpesa.c2b.confirmation_url') ?: route('mpesa.c2b.confirmation'),
            'ValidationURL'   => config('mpesa.c2b.validation_url') ?: route('mpesa.c2b.validation'),
        ]);
    }

    // -------------------------------------------------------------------------
    // B2C
    // -------------------------------------------------------------------------

    /**
     * Send money from business to customer.
     */
    public function b2c(string $phone, int $amount, string $reference, string $remarks): ?array
    {
        $phone = $this->normalizePhone($phone);

        $payload = [
            'InitiatorName'      => config('mpesa.b2c.initiator_name'),
            'SecurityCredential' => config('mpesa.b2c.security_credential'),
            'CommandID'          => 'BusinessPayment',
            'Amount'             => $amount,
            'PartyA'             => config('mpesa.b2c.shortcode'),
            'PartyB'             => $phone,
            'Remarks'            => $remarks,
            'QueueTimeOutURL'    => config('mpesa.b2c.queue_timeout_url') ?: route('mpesa.b2c.timeout'),
            'ResultURL'          => config('mpesa.b2c.result_url') ?: route('mpesa.b2c.result'),
            'Occassion'          => $reference,
        ];

        Log::info('[Mpesa] B2C initiated', ['phone' => $phone, 'amount' => $amount]);

        $response = $this->post('/mpesa/b2c/v1/paymentrequest', $payload);

        if (!$response) {
            return null;
        }

        $this->createTransaction([
            'type'            => 'b2c',
            'status'          => 'pending',
            'phone'           => $phone,
            'amount'          => $amount,
            'reference'       => $reference,
            'conversation_id' => $response['ConversationID'] ?? null,
            'raw_response'    => json_encode($response),
        ]);

        return $response;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Normalize Kenyan phone numbers to 2547XXXXXXXX format.
     */
    public function normalizePhone(string $phone): string
    {
        // Strip spaces, dashes
        $phone = preg_replace('/[\s\-]/', '', $phone);

        // 07XXXXXXXX or 01XXXXXXXX → 2547... / 2541...
        if (preg_match('/^0(7|1)\d{8}$/', $phone)) {
            return '254' . ltrim($phone, '0');
        }

        // +254XXXXXXXXX → 254XXXXXXXXX
        if (str_starts_with($phone, '+254')) {
            return ltrim($phone, '+');
        }

        // Already 254XXXXXXXXX
        if (preg_match('/^254(7|1)\d{8}$/', $phone)) {
            return $phone;
        }

        throw new \InvalidArgumentException("Invalid Kenyan phone number: {$phone}");
    }

    /**
     * Resolve the correct Daraja base URL.
     */
    protected function baseUrl(): string
    {
        return $this->environment === 'production'
            ? $this->productionBase
            : $this->sandboxBase;
    }

    /**
     * Make an authenticated POST request to Daraja.
     */
    protected function post(string $path, array $data): ?array
    {
        try {
            $token    = $this->authenticate();
            $url      = $this->baseUrl() . $path;

            Log::debug('[Mpesa] POST ' . $path, $data);

            $response = Http::retry(3, 100)
                ->withToken($token)
                ->timeout(30)
                ->post($url, $data);

            Log::debug('[Mpesa] Response ' . $path, $response->json() ?? []);

            if ($response->failed()) {
                Log::error('[Mpesa] Request failed: ' . $path, [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('[Mpesa] Exception on ' . $path . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Persist a transaction record using the configured model.
     */
    protected function createTransaction(array $attributes): mixed
    {
        $model = config('mpesa.transaction_model', MpesaTransaction::class);
        return $model::create($attributes);
    }
}
