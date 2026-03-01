<?php

return [

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Environment
    |--------------------------------------------------------------------------
    | Options: sandbox | production
    */
    'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Daraja App Credentials
    |--------------------------------------------------------------------------
    */
    'consumer_key' => env('MPESA_CONSUMER_KEY', ''),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | STK Push (Lipa Na M-Pesa Online)
    |--------------------------------------------------------------------------
    */
    'stk' => [
        'shortcode' => env('MPESA_STK_SHORTCODE', ''),
        'passkey' => env('MPESA_STK_PASSKEY', ''),
        // CustomerPayBillOnline | CustomerBuyGoodsOnline
        'transaction_type' => env('MPESA_STK_TRANSACTION_TYPE', 'CustomerPayBillOnline'),
        'callback_url' => env('MPESA_STK_CALLBACK_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | C2B (Customer to Business)
    |--------------------------------------------------------------------------
    */
    'c2b' => [
        'shortcode' => env('MPESA_C2B_SHORTCODE', ''),
        'confirmation_url' => env('MPESA_C2B_CONFIRMATION_URL', ''),
        'validation_url' => env('MPESA_C2B_VALIDATION_URL', ''),
        // Completed | Cancelled
        'response_type' => env('MPESA_C2B_RESPONSE_TYPE', 'Completed'),
    ],

    /*
    |--------------------------------------------------------------------------
    | B2C (Business to Customer)
    |--------------------------------------------------------------------------
    */
    'b2c' => [
        'shortcode' => env('MPESA_B2C_SHORTCODE', ''),
        'initiator_name' => env('MPESA_B2C_INITIATOR_NAME', ''),
        'security_credential' => env('MPESA_B2C_SECURITY_CREDENTIAL', ''),
        'queue_timeout_url' => env('MPESA_B2C_QUEUE_TIMEOUT_URL', ''),
        'result_url' => env('MPESA_B2C_RESULT_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Query (Status Check)
    |--------------------------------------------------------------------------
    */
    'query' => [
        'shortcode' => env('MPESA_QUERY_SHORTCODE', ''),
        'initiator_name' => env('MPESA_QUERY_INITIATOR_NAME', ''),
        'security_credential' => env('MPESA_QUERY_SECURITY_CREDENTIAL', ''),
        'queue_timeout_url' => env('MPESA_QUERY_TIMEOUT_URL', ''),
        'result_url' => env('MPESA_QUERY_RESULT_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Model
    |--------------------------------------------------------------------------
    | Override this in your app if you want to use a custom model.
    */
    'transaction_model' => \Parcy\Mpesa\Models\MpesaTransaction::class,

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'prefix' => 'api/mpesa',
        'middleware' => [], // e.g. ['api'] — leave empty for raw Safaricom callbacks
    ],

    /*
    |--------------------------------------------------------------------------
    | Pending Transaction Auto-Reconciliation
    |--------------------------------------------------------------------------
    | How many minutes before a pending STK transaction is queried automatically.
    */
    'reconciliation_after_minutes' => env('MPESA_RECONCILIATION_MINUTES', 2),

    /*
    |--------------------------------------------------------------------------
    | UltimatePOS Payment Method Slot
    |--------------------------------------------------------------------------
    | Maps M-Pesa to one of UltimatePOS's custom payment method slots.
    | Label this slot "M-Pesa" in Business Settings → Payment Methods.
    | Options: custom_pay_1 | custom_pay_2 | custom_pay_3
    */
    'pos_payment_method' => env('MPESA_POS_PAYMENT_METHOD', 'custom_pay_1'),

];
