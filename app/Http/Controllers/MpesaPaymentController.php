<?php

namespace App\Http\Controllers;

use App\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Parcy\Mpesa\Facades\Mpesa;
use Parcy\Mpesa\Models\MpesaTransaction;

class MpesaPaymentController extends Controller
{
    /**
     * Initiate an STK Push for a POS sale.
     *
     * Called via AJAX from the POS payment modal when the cashier
     * selects M-Pesa and clicks "Request Payment".
     *
     * POST /mpesa/pay
     * Body: { transaction_id, phone, amount }
     */
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'transaction_id' => 'required|integer|exists:transactions,id',
            'phone' => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        $sale = Transaction::findOrFail($request->transaction_id);

        // Prevent re-initiating if already paid
        if ($sale->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'This sale is already fully paid.',
            ], 422);
        }

        // Prevent duplicate pending STK for the same sale
        $pending = MpesaTransaction::where('reference', $sale->id)
            ->where('type', 'stk_push')
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(2))
            ->exists();

        if ($pending) {
            return response()->json([
                'success' => false,
                'message' => 'A payment request is already pending for this sale. Please wait.',
            ], 422);
        }

        try {
            $response = Mpesa::stkPush(
                phone: $request->phone,
                amount: (int) $request->amount,
                reference: (string) $sale->id,
                description: 'Payment for Sale #' . $sale->id,
            );

            if (!$response) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not reach M-Pesa. Please try again.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment prompt sent to ' . $request->phone . '. Awaiting PIN entry.',
                'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
            ]);

        } catch (\InvalidArgumentException $e) {
            // Phone number normalization failed
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number: ' . $e->getMessage(),
            ], 422);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
            ], 500);
        }
    }

    /**
     * Poll the status of an M-Pesa payment for a given sale.
     *
     * Called every 3 seconds by the POS frontend while the cashier
     * is waiting for the customer to enter their PIN.
     *
     * GET /mpesa/status/{transaction_id}
     */
    public function status(int $transactionId): JsonResponse
    {
        // Get the most recent STK push for this sale
        $mpesaTransaction = MpesaTransaction::where('reference', $transactionId)
            ->where('type', 'stk_push')
            ->latest()
            ->first();

        if (!$mpesaTransaction) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json([
            'status' => $mpesaTransaction->status,
            'receipt_number' => $mpesaTransaction->mpesa_receipt_number,
            'amount' => $mpesaTransaction->amount,
            'result_desc' => $mpesaTransaction->result_desc,
        ]);
    }

    /**
     * Show the M-Pesa transactions report page.
     *
     * GET /mpesa/transactions
     */
    public function transactions(Request $request)
    {
        if (!auth()->user()->can('business_settings.access') && !auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $query = MpesaTransaction::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        $transactions = $query->paginate(25)->withQueryString();

        $summary = [
            'total' => MpesaTransaction::count(),
            'completed' => MpesaTransaction::where('status', 'completed')->count(),
            'pending' => MpesaTransaction::where('status', 'pending')->count(),
            'failed' => MpesaTransaction::whereIn('status', ['failed', 'cancelled'])->count(),
            'revenue' => MpesaTransaction::where('status', 'completed')->sum('amount'),
        ];

        return view('mpesa.transactions', compact('transactions', 'summary'));
    }
}
