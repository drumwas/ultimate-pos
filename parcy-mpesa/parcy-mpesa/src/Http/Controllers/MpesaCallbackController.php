<?php

namespace Parcy\Mpesa\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Parcy\Mpesa\Jobs\ProcessMpesaCallback;

class MpesaCallbackController extends Controller
{
    /**
     * STK Push callback endpoint.
     * Safaricom POSTs here after the customer responds to the push.
     */
    public function stkCallback(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('[Mpesa] STK callback received', $payload);

        ProcessMpesaCallback::dispatch('stk', $payload);

        // Safaricom expects a quick 200 response — do NOT process inline
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * STK Push status poll endpoint.
     * Called by your POS frontend every few seconds to check if payment landed.
     */
    public function stkStatus(Request $request): JsonResponse
    {
        $request->validate(['reference' => 'required|string']);

        $model       = config('mpesa.transaction_model');
        $transaction = $model::forReference($request->reference)
            ->where('type', 'stk_push')
            ->latest()
            ->first();

        if (!$transaction) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json([
            'status'          => $transaction->status,
            'receipt_number'  => $transaction->mpesa_receipt_number,
            'amount'          => $transaction->amount,
            'result_desc'     => $transaction->result_desc,
        ]);
    }

    /**
     * C2B Validation URL — return 0 to accept, any other code to reject.
     * Only called if validation is enabled on your shortcode.
     */
    public function c2bValidation(Request $request): JsonResponse
    {
        Log::info('[Mpesa] C2B validation received', $request->all());

        // Accept all by default. Override this in your app with your own logic.
        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }

    /**
     * C2B Confirmation URL — called when a customer sends money to your Paybill.
     */
    public function c2bConfirmation(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('[Mpesa] C2B confirmation received', $payload);

        ProcessMpesaCallback::dispatch('c2b', $payload);

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * B2C Result URL.
     */
    public function b2cResult(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('[Mpesa] B2C result received', $payload);

        ProcessMpesaCallback::dispatch('b2c', $payload);

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * B2C Queue Timeout URL — called if a B2C request times out in the queue.
     */
    public function b2cTimeout(Request $request): JsonResponse
    {
        Log::warning('[Mpesa] B2C queue timeout', $request->all());
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
