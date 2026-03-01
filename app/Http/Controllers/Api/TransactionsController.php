<?php

namespace App\Http\Controllers\Api;

use App\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DB;

/**
 * API Transactions Controller
 * 
 * Provides listing and details for sales and purchase transactions.
 */
class TransactionsController extends BaseApiController
{
    /**
     * List sales transactions with pagination and filters.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sales(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;
            $per_page = min($request->input('per_page', 20), 100);

            $query = Transaction::where('business_id', $business_id)
                ->where('type', 'sell')
                ->with(['contact:id,name,mobile', 'location:id,name'])
                ->select([
                    'id',
                    'invoice_no',
                    'contact_id',
                    'location_id',
                    'status',
                    'payment_status',
                    'final_total',
                    'transaction_date',
                    'created_at'
                ]);

            // Filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            if ($request->has('start_date')) {
                $query->whereDate('transaction_date', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('transaction_date', '<=', $request->end_date);
            }

            if ($request->has('location_id')) {
                $query->where('location_id', $request->location_id);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_no', 'LIKE', "%{$search}%")
                        ->orWhereHas('contact', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('mobile', 'LIKE', "%{$search}%");
                        });
                });
            }

            $transactions = $query->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->paginate($per_page);

            $items = $transactions->map(function ($item) {
                return [
                    'id' => $item->id,
                    'invoice_no' => $item->invoice_no,
                    'customer' => $item->contact->name ?? 'Walk-in Customer',
                    'customer_mobile' => $item->contact->mobile ?? null,
                    'location' => $item->location->name ?? null,
                    'status' => $item->status,
                    'payment_status' => $item->payment_status,
                    'total' => (float) $item->final_total,
                    'date' => $item->transaction_date,
                    'created_at' => $item->created_at->toIso8601String(),
                ];
            });

            return $this->successResponse([
                'items' => $items,
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ], 'Sales transactions retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve sales: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single sale details.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function showSale(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;

            $transaction = Transaction::where('business_id', $business_id)
                ->where('type', 'sell')
                ->where('id', $id)
                ->with([
                    'contact:id,name,mobile,email,supplier_business_name',
                    'location:id,name',
                    'sell_lines.product:id,name,sku',
                    'sell_lines.variations:id,name,sub_sku',
                    'payment_lines',
                    'sales_person:id,first_name,last_name',
                ])
                ->first();

            if (!$transaction) {
                return $this->errorResponse('Sale not found', 404);
            }

            $data = [
                'id' => $transaction->id,
                'invoice_no' => $transaction->invoice_no,
                'status' => $transaction->status,
                'payment_status' => $transaction->payment_status,
                'date' => $transaction->transaction_date,
                'customer' => [
                    'id' => $transaction->contact->id ?? null,
                    'name' => $transaction->contact->name ?? 'Walk-in Customer',
                    'mobile' => $transaction->contact->mobile ?? null,
                    'email' => $transaction->contact->email ?? null,
                ],
                'location' => [
                    'id' => $transaction->location->id ?? null,
                    'name' => $transaction->location->name ?? null,
                ],
                'sales_person' => $transaction->sales_person ?
                    $transaction->sales_person->first_name . ' ' . $transaction->sales_person->last_name : null,
                'amounts' => [
                    'subtotal' => (float) $transaction->total_before_tax,
                    'discount' => (float) $transaction->discount_amount,
                    'discount_type' => $transaction->discount_type,
                    'tax' => (float) $transaction->tax_amount,
                    'shipping' => (float) $transaction->shipping_charges,
                    'total' => (float) $transaction->final_total,
                ],
                'items' => $transaction->sell_lines->map(function ($line) {
                    return [
                        'id' => $line->id,
                        'product_id' => $line->product_id,
                        'product_name' => $line->product->name ?? null,
                        'sku' => $line->product->sku ?? null,
                        'variation' => $line->variations->name ?? null,
                        'quantity' => (float) $line->quantity,
                        'unit_price' => (float) $line->unit_price,
                        'unit_price_inc_tax' => (float) $line->unit_price_inc_tax,
                        'line_discount' => (float) $line->line_discount_amount,
                        'line_total' => (float) ($line->quantity * $line->unit_price_inc_tax),
                    ];
                }),
                'payments' => $transaction->payment_lines->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => (float) $payment->amount,
                        'method' => $payment->method,
                        'date' => $payment->paid_on,
                        'note' => $payment->note,
                    ];
                }),
                'notes' => $transaction->additional_notes,
                'staff_note' => $transaction->staff_note,
            ];

            return $this->successResponse($data, 'Sale details retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve sale details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List purchase transactions with pagination and filters.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function purchases(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;
            $per_page = min($request->input('per_page', 20), 100);

            $query = Transaction::where('business_id', $business_id)
                ->where('type', 'purchase')
                ->with(['contact:id,name,supplier_business_name', 'location:id,name'])
                ->select([
                    'id',
                    'ref_no',
                    'contact_id',
                    'location_id',
                    'status',
                    'payment_status',
                    'final_total',
                    'transaction_date',
                    'created_at'
                ]);

            // Filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            if ($request->has('start_date')) {
                $query->whereDate('transaction_date', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('transaction_date', '<=', $request->end_date);
            }

            if ($request->has('location_id')) {
                $query->where('location_id', $request->location_id);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('ref_no', 'LIKE', "%{$search}%")
                        ->orWhereHas('contact', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('supplier_business_name', 'LIKE', "%{$search}%");
                        });
                });
            }

            $transactions = $query->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->paginate($per_page);

            $items = $transactions->map(function ($item) {
                return [
                    'id' => $item->id,
                    'ref_no' => $item->ref_no,
                    'supplier' => $item->contact->supplier_business_name ?? $item->contact->name ?? 'N/A',
                    'location' => $item->location->name ?? null,
                    'status' => $item->status,
                    'payment_status' => $item->payment_status,
                    'total' => (float) $item->final_total,
                    'date' => $item->transaction_date,
                    'created_at' => $item->created_at->toIso8601String(),
                ];
            });

            return $this->successResponse([
                'items' => $items,
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ], 'Purchase transactions retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve purchases: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single purchase details.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function showPurchase(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;

            $transaction = Transaction::where('business_id', $business_id)
                ->where('type', 'purchase')
                ->where('id', $id)
                ->with([
                    'contact:id,name,mobile,email,supplier_business_name',
                    'location:id,name',
                    'purchase_lines.product:id,name,sku',
                    'purchase_lines.variations:id,name,sub_sku',
                    'payment_lines',
                ])
                ->first();

            if (!$transaction) {
                return $this->errorResponse('Purchase not found', 404);
            }

            $data = [
                'id' => $transaction->id,
                'ref_no' => $transaction->ref_no,
                'status' => $transaction->status,
                'payment_status' => $transaction->payment_status,
                'date' => $transaction->transaction_date,
                'supplier' => [
                    'id' => $transaction->contact->id ?? null,
                    'name' => $transaction->contact->name ?? null,
                    'business_name' => $transaction->contact->supplier_business_name ?? null,
                    'mobile' => $transaction->contact->mobile ?? null,
                    'email' => $transaction->contact->email ?? null,
                ],
                'location' => [
                    'id' => $transaction->location->id ?? null,
                    'name' => $transaction->location->name ?? null,
                ],
                'amounts' => [
                    'subtotal' => (float) $transaction->total_before_tax,
                    'discount' => (float) $transaction->discount_amount,
                    'discount_type' => $transaction->discount_type,
                    'tax' => (float) $transaction->tax_amount,
                    'shipping' => (float) $transaction->shipping_charges,
                    'total' => (float) $transaction->final_total,
                ],
                'items' => $transaction->purchase_lines->map(function ($line) {
                    return [
                        'id' => $line->id,
                        'product_id' => $line->product_id,
                        'product_name' => $line->product->name ?? null,
                        'sku' => $line->product->sku ?? null,
                        'variation' => $line->variations->name ?? null,
                        'quantity' => (float) $line->quantity,
                        'purchase_price' => (float) $line->purchase_price,
                        'purchase_price_inc_tax' => (float) $line->purchase_price_inc_tax,
                        'line_total' => (float) ($line->quantity * $line->purchase_price_inc_tax),
                    ];
                }),
                'payments' => $transaction->payment_lines->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => (float) $payment->amount,
                        'method' => $payment->method,
                        'date' => $payment->paid_on,
                        'note' => $payment->note,
                    ];
                }),
                'notes' => $transaction->additional_notes,
            ];

            return $this->successResponse($data, 'Purchase details retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve purchase details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get recent transactions (both sales and purchases).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recent(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;
            $limit = min($request->input('limit', 10), 50);

            $transactions = Transaction::where('business_id', $business_id)
                ->whereIn('type', ['sell', 'purchase'])
                ->with(['contact:id,name,supplier_business_name'])
                ->select(['id', 'type', 'invoice_no', 'ref_no', 'contact_id', 'status', 'final_total', 'transaction_date'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'type' => $item->type,
                        'reference' => $item->type === 'sell' ? $item->invoice_no : $item->ref_no,
                        'contact' => $item->contact->supplier_business_name ?? $item->contact->name ?? 'N/A',
                        'status' => $item->status,
                        'total' => (float) $item->final_total,
                        'date' => $item->transaction_date,
                    ];
                });

            return $this->successResponse($transactions, 'Recent transactions retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve recent transactions: ' . $e->getMessage(), 500);
        }
    }
}
