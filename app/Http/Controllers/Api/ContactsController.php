<?php

namespace App\Http\Controllers\Api;

use App\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DB;

/**
 * API Contacts Controller
 * 
 * Provides customer and supplier listing and details.
 */
class ContactsController extends BaseApiController
{
    /**
     * List customers with pagination and filters.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function customers(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;
            $per_page = min($request->input('per_page', 20), 100);

            $query = Contact::where('business_id', $business_id)
                ->whereIn('type', ['customer', 'both'])
                ->select([
                    'id',
                    'name',
                    'contact_id',
                    'mobile',
                    'email',
                    'city',
                    'state',
                    'country',
                    'credit_limit',
                    DB::raw('(SELECT COALESCE(SUM(final_total), 0) - COALESCE(SUM((SELECT COALESCE(SUM(IF(is_return = 1, -1*amount, amount)), 0) FROM transaction_payments WHERE transaction_id = transactions.id)), 0) FROM transactions WHERE contact_id = contacts.id AND type = "sell" AND status = "final") as balance_due')
                ]);

            // Filters
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('mobile', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhere('contact_id', 'LIKE', "%{$search}%");
                });
            }

            if ($request->has('city')) {
                $query->where('city', $request->city);
            }

            $contacts = $query->orderBy('name', 'asc')
                ->paginate($per_page);

            $items = $contacts->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'contact_id' => $item->contact_id,
                    'mobile' => $item->mobile,
                    'email' => $item->email,
                    'city' => $item->city,
                    'state' => $item->state,
                    'country' => $item->country,
                    'credit_limit' => (float) $item->credit_limit,
                    'balance_due' => (float) $item->balance_due,
                ];
            });

            return $this->successResponse([
                'items' => $items,
                'pagination' => [
                    'current_page' => $contacts->currentPage(),
                    'last_page' => $contacts->lastPage(),
                    'per_page' => $contacts->perPage(),
                    'total' => $contacts->total(),
                ],
            ], 'Customers retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve customers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List suppliers with pagination and filters.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function suppliers(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;
            $per_page = min($request->input('per_page', 20), 100);

            $query = Contact::where('business_id', $business_id)
                ->whereIn('type', ['supplier', 'both'])
                ->select([
                    'id',
                    'name',
                    'supplier_business_name',
                    'contact_id',
                    'mobile',
                    'email',
                    'city',
                    'state',
                    'country',
                    DB::raw('(SELECT COALESCE(SUM(final_total), 0) - COALESCE(SUM((SELECT COALESCE(SUM(amount), 0) FROM transaction_payments WHERE transaction_id = transactions.id)), 0) FROM transactions WHERE contact_id = contacts.id AND type = "purchase") as balance_due')
                ]);

            // Filters
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('supplier_business_name', 'LIKE', "%{$search}%")
                        ->orWhere('mobile', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            $contacts = $query->orderBy('name', 'asc')
                ->paginate($per_page);

            $items = $contacts->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'business_name' => $item->supplier_business_name,
                    'contact_id' => $item->contact_id,
                    'mobile' => $item->mobile,
                    'email' => $item->email,
                    'city' => $item->city,
                    'state' => $item->state,
                    'country' => $item->country,
                    'balance_due' => (float) $item->balance_due,
                ];
            });

            return $this->successResponse([
                'items' => $items,
                'pagination' => [
                    'current_page' => $contacts->currentPage(),
                    'last_page' => $contacts->lastPage(),
                    'per_page' => $contacts->perPage(),
                    'total' => $contacts->total(),
                ],
            ], 'Suppliers retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve suppliers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single contact details with transaction summary.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;

            $contact = Contact::where('business_id', $business_id)
                ->where('id', $id)
                ->first();

            if (!$contact) {
                return $this->errorResponse('Contact not found', 404);
            }

            // Get transaction summary
            $sales_summary = \App\Transaction::where('business_id', $business_id)
                ->where('contact_id', $id)
                ->where('type', 'sell')
                ->where('status', 'final')
                ->select([
                    DB::raw('COUNT(*) as total_invoices'),
                    DB::raw('COALESCE(SUM(final_total), 0) as total_sales'),
                    DB::raw('COALESCE(SUM(final_total), 0) - COALESCE(SUM((SELECT COALESCE(SUM(IF(is_return = 1, -1*amount, amount)), 0) FROM transaction_payments WHERE transaction_id = transactions.id)), 0) as balance_due')
                ])
                ->first();

            $purchase_summary = \App\Transaction::where('business_id', $business_id)
                ->where('contact_id', $id)
                ->where('type', 'purchase')
                ->select([
                    DB::raw('COUNT(*) as total_purchases'),
                    DB::raw('COALESCE(SUM(final_total), 0) as total_purchase_amount'),
                    DB::raw('COALESCE(SUM(final_total), 0) - COALESCE(SUM((SELECT COALESCE(SUM(amount), 0) FROM transaction_payments WHERE transaction_id = transactions.id)), 0) as balance_due')
                ])
                ->first();

            // Get recent transactions
            $recent_transactions = \App\Transaction::where('business_id', $business_id)
                ->where('contact_id', $id)
                ->whereIn('type', ['sell', 'purchase'])
                ->select(['id', 'type', 'invoice_no', 'ref_no', 'final_total', 'payment_status', 'transaction_date'])
                ->orderBy('transaction_date', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'type' => $item->type,
                        'reference' => $item->type === 'sell' ? $item->invoice_no : $item->ref_no,
                        'total' => (float) $item->final_total,
                        'payment_status' => $item->payment_status,
                        'date' => $item->transaction_date,
                    ];
                });

            $data = [
                'id' => $contact->id,
                'type' => $contact->type,
                'name' => $contact->name,
                'business_name' => $contact->supplier_business_name,
                'contact_id' => $contact->contact_id,
                'tax_number' => $contact->tax_number,
                'mobile' => $contact->mobile,
                'alternate_number' => $contact->alternate_number,
                'landline' => $contact->landline,
                'email' => $contact->email,
                'address' => [
                    'address_line_1' => $contact->address_line_1,
                    'address_line_2' => $contact->address_line_2,
                    'city' => $contact->city,
                    'state' => $contact->state,
                    'country' => $contact->country,
                    'zip_code' => $contact->zip_code,
                ],
                'credit_limit' => (float) $contact->credit_limit,
                'pay_term_number' => $contact->pay_term_number,
                'pay_term_type' => $contact->pay_term_type,
                'custom_field1' => $contact->custom_field1,
                'custom_field2' => $contact->custom_field2,
                'custom_field3' => $contact->custom_field3,
                'custom_field4' => $contact->custom_field4,
                'sales_summary' => [
                    'total_invoices' => (int) $sales_summary->total_invoices,
                    'total_sales' => (float) $sales_summary->total_sales,
                    'balance_due' => (float) $sales_summary->balance_due,
                ],
                'purchase_summary' => [
                    'total_purchases' => (int) $purchase_summary->total_purchases,
                    'total_amount' => (float) $purchase_summary->total_purchase_amount,
                    'balance_due' => (float) $purchase_summary->balance_due,
                ],
                'recent_transactions' => $recent_transactions,
            ];

            return $this->successResponse($data, 'Contact details retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve contact: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get contacts with outstanding balances.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function withBalances(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;
            $type = $request->input('type', 'customer'); // customer or supplier
            $per_page = min($request->input('per_page', 20), 100);

            if ($type === 'customer') {
                $query = Contact::where('business_id', $business_id)
                    ->whereIn('type', ['customer', 'both'])
                    ->select([
                        'id',
                        'name',
                        'mobile',
                        DB::raw('(SELECT COALESCE(SUM(final_total), 0) - COALESCE(SUM((SELECT COALESCE(SUM(IF(is_return = 1, -1*amount, amount)), 0) FROM transaction_payments WHERE transaction_id = transactions.id)), 0) FROM transactions WHERE contact_id = contacts.id AND type = "sell" AND status = "final") as balance_due')
                    ])
                    ->havingRaw('balance_due > 0');
            } else {
                $query = Contact::where('business_id', $business_id)
                    ->whereIn('type', ['supplier', 'both'])
                    ->select([
                        'id',
                        'name',
                        'supplier_business_name',
                        'mobile',
                        DB::raw('(SELECT COALESCE(SUM(final_total), 0) - COALESCE(SUM((SELECT COALESCE(SUM(amount), 0) FROM transaction_payments WHERE transaction_id = transactions.id)), 0) FROM transactions WHERE contact_id = contacts.id AND type = "purchase") as balance_due')
                    ])
                    ->havingRaw('balance_due > 0');
            }

            $contacts = $query->orderBy('balance_due', 'desc')
                ->paginate($per_page);

            $items = $contacts->map(function ($item) use ($type) {
                return [
                    'id' => $item->id,
                    'name' => $type === 'supplier' ? ($item->supplier_business_name ?? $item->name) : $item->name,
                    'mobile' => $item->mobile,
                    'balance_due' => (float) $item->balance_due,
                ];
            });

            return $this->successResponse([
                'type' => $type,
                'items' => $items,
                'pagination' => [
                    'current_page' => $contacts->currentPage(),
                    'last_page' => $contacts->lastPage(),
                    'per_page' => $contacts->perPage(),
                    'total' => $contacts->total(),
                ],
            ], ucfirst($type) . 's with balances retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve contacts with balances: ' . $e->getMessage(), 500);
        }
    }
}
