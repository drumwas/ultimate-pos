<?php

namespace App\Http\Controllers\Api;

use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API Dashboard Controller
 * 
 * Provides dashboard summary data for mobile admin oversight.
 */
class DashboardController extends BaseApiController
{
    protected $transactionUtil;
    protected $productUtil;
    protected $moduleUtil;
    protected $businessUtil;

    /**
     * Constructor to inject utility dependencies.
     */
    public function __construct(
        TransactionUtil $transactionUtil,
        ProductUtil $productUtil,
        ModuleUtil $moduleUtil,
        BusinessUtil $businessUtil
    ) {
        $this->transactionUtil = $transactionUtil;
        $this->productUtil = $productUtil;
        $this->moduleUtil = $moduleUtil;
        $this->businessUtil = $businessUtil;
    }

    /**
     * Get dashboard summary with totals for sales, purchases, and expenses.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;
            $permitted_locations = $user->permitted_locations();

            // Default date range: current month
            $start = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $end = $request->input('end_date', Carbon::now()->format('Y-m-d'));
            $location_id = $request->input('location_id');

            // Get purchase totals
            $purchase_details = $this->transactionUtil->getPurchaseTotals(
                $business_id,
                $start,
                $end,
                $location_id,
                null,
                $permitted_locations
            );

            // Get sell totals
            $sell_details = $this->transactionUtil->getSellTotals(
                $business_id,
                $start,
                $end,
                $location_id,
                null,
                $permitted_locations
            );

            // Get expense, sell return, and purchase return totals
            $transaction_types = ['purchase_return', 'sell_return', 'expense'];
            $transaction_totals = $this->transactionUtil->getTransactionTotals(
                $business_id,
                $transaction_types,
                $start,
                $end,
                $location_id
            );

            // Get ledger discount
            $total_ledger_discount = $this->transactionUtil->getTotalLedgerDiscount($business_id, $start, $end);

            $data = [
                'date_range' => [
                    'start' => $start,
                    'end' => $end,
                ],
                'sales' => [
                    'total' => (float) ($sell_details['total_sell_inc_tax'] ?? 0),
                    'total_exc_tax' => (float) ($sell_details['total_sell_exc_tax'] ?? 0),
                    'invoice_due' => (float) ($sell_details['invoice_due'] ?? 0) - (float) ($total_ledger_discount['total_sell_discount'] ?? 0),
                ],
                'purchases' => [
                    'total' => (float) ($purchase_details['total_purchase_inc_tax'] ?? 0),
                    'total_exc_tax' => (float) ($purchase_details['total_purchase_exc_tax'] ?? 0),
                    'purchase_due' => (float) ($purchase_details['purchase_due'] ?? 0) - (float) ($total_ledger_discount['total_purchase_discount'] ?? 0),
                ],
                'returns' => [
                    'sell_return' => (float) ($transaction_totals['total_sell_return_inc_tax'] ?? 0),
                    'purchase_return' => (float) ($transaction_totals['total_purchase_return_inc_tax'] ?? 0),
                ],
                'expenses' => [
                    'total' => (float) ($transaction_totals['total_expense'] ?? 0),
                ],
            ];

            // Calculate net profit indicator
            $data['net'] = $data['sales']['total'] - $data['sales']['invoice_due'] - $data['expenses']['total'];

            return $this->successResponse($data, 'Dashboard summary retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve dashboard summary: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get stock alerts - products below alert quantity.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stockAlerts(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;
            $permitted_locations = $user->permitted_locations();

            $products = $this->productUtil->getProductAlert($business_id, $permitted_locations);

            $alerts = $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'product' => $product->product,
                    'sku' => $product->sku,
                    'type' => $product->type,
                    'variation' => $product->type !== 'single' ? $product->variation : null,
                    'sub_sku' => $product->sub_sku,
                    'current_stock' => (float) ($product->stock ?? 0),
                    'alert_quantity' => (float) ($product->alert_quantity ?? 0),
                    'unit' => $product->unit,
                ];
            });

            return $this->successResponse([
                'count' => $alerts->count(),
                'products' => $alerts->values(),
            ], 'Stock alerts retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve stock alerts: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get payment dues - pending payments for purchases and sales.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function paymentDues(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;
            $permitted_locations = $user->permitted_locations();

            // Get purchase payment dues
            $purchase_dues = \App\Transaction::where('business_id', $business_id)
                ->where('type', 'purchase')
                ->whereRaw('(SELECT COALESCE(SUM(amount), 0) FROM transaction_payments WHERE transaction_id = transactions.id) < final_total')
                ->with(['contact:id,name,supplier_business_name'])
                ->select('id', 'ref_no', 'contact_id', 'final_total', 'transaction_date')
                ->selectRaw('final_total - (SELECT COALESCE(SUM(amount), 0) FROM transaction_payments WHERE transaction_id = transactions.id) as due_amount')
                ->orderBy('transaction_date', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'ref_no' => $item->ref_no,
                        'supplier' => $item->contact->supplier_business_name ?? $item->contact->name ?? 'N/A',
                        'total' => (float) $item->final_total,
                        'due' => (float) $item->due_amount,
                        'date' => $item->transaction_date,
                    ];
                });

            // Get sales payment dues
            $sales_dues = \App\Transaction::where('business_id', $business_id)
                ->where('type', 'sell')
                ->where('status', 'final')
                ->whereRaw('(SELECT COALESCE(SUM(IF(is_return = 1, -1*amount, amount)), 0) FROM transaction_payments WHERE transaction_id = transactions.id) < final_total')
                ->with(['contact:id,name'])
                ->select('id', 'invoice_no', 'contact_id', 'final_total', 'transaction_date')
                ->selectRaw('final_total - (SELECT COALESCE(SUM(IF(is_return = 1, -1*amount, amount)), 0) FROM transaction_payments WHERE transaction_id = transactions.id) as due_amount')
                ->orderBy('transaction_date', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'invoice_no' => $item->invoice_no,
                        'customer' => $item->contact->name ?? 'Walk-in Customer',
                        'total' => (float) $item->final_total,
                        'due' => (float) $item->due_amount,
                        'date' => $item->transaction_date,
                    ];
                });

            return $this->successResponse([
                'purchase_dues' => [
                    'count' => $purchase_dues->count(),
                    'items' => $purchase_dues,
                ],
                'sales_dues' => [
                    'count' => $sales_dues->count(),
                    'items' => $sales_dues,
                ],
            ], 'Payment dues retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve payment dues: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get quick stats for today.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function todayStats(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;
            $today = Carbon::today()->format('Y-m-d');

            // Today's sales
            $today_sales = \App\Transaction::where('business_id', $business_id)
                ->where('type', 'sell')
                ->where('status', 'final')
                ->whereDate('transaction_date', $today)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(final_total), 0) as total')
                ->first();

            // Today's purchases
            $today_purchases = \App\Transaction::where('business_id', $business_id)
                ->where('type', 'purchase')
                ->whereDate('transaction_date', $today)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(final_total), 0) as total')
                ->first();

            // Today's expenses
            $today_expenses = \App\Transaction::where('business_id', $business_id)
                ->where('type', 'expense')
                ->whereDate('transaction_date', $today)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(final_total), 0) as total')
                ->first();

            return $this->successResponse([
                'date' => $today,
                'sales' => [
                    'count' => (int) $today_sales->count,
                    'total' => (float) $today_sales->total,
                ],
                'purchases' => [
                    'count' => (int) $today_purchases->count,
                    'total' => (float) $today_purchases->total,
                ],
                'expenses' => [
                    'count' => (int) $today_expenses->count,
                    'total' => (float) $today_expenses->total,
                ],
            ], 'Today\'s stats retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve today\'s stats: ' . $e->getMessage(), 500);
        }
    }
}
