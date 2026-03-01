<?php

namespace App\Http\Controllers\Api;

use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DB;

/**
 * API Reports Controller
 * 
 * Provides various reports for mobile admin oversight.
 */
class ReportsController extends BaseApiController
{
    protected $transactionUtil;
    protected $businessUtil;

    /**
     * Constructor to inject utility dependencies.
     */
    public function __construct(
        TransactionUtil $transactionUtil,
        BusinessUtil $businessUtil
    ) {
        $this->transactionUtil = $transactionUtil;
        $this->businessUtil = $businessUtil;
    }

    /**
     * Get profit/loss report for a date range.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function profitLoss(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;

            // Default: current month
            $start = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $end = $request->input('end_date', Carbon::now()->format('Y-m-d'));
            $location_id = $request->input('location_id');

            $data = $this->transactionUtil->getProfitLossDetails(
                $business_id,
                $location_id,
                $start,
                $end
            );

            return $this->successResponse([
                'date_range' => ['start' => $start, 'end' => $end],
                'opening_stock' => (float) ($data['opening_stock'] ?? 0),
                'closing_stock' => (float) ($data['closing_stock'] ?? 0),
                'total_purchase' => (float) ($data['total_purchase'] ?? 0),
                'total_sell' => (float) ($data['total_sell'] ?? 0),
                'total_sell_return' => (float) ($data['total_sell_return'] ?? 0),
                'total_purchase_return' => (float) ($data['total_purchase_return'] ?? 0),
                'total_expense' => (float) ($data['total_expense'] ?? 0),
                'total_purchase_shipping_charge' => (float) ($data['total_purchase_shipping_charge'] ?? 0),
                'total_sell_shipping_charge' => (float) ($data['total_sell_shipping_charge'] ?? 0),
                'total_purchase_discount' => (float) ($data['total_purchase_discount'] ?? 0),
                'total_sell_discount' => (float) ($data['total_sell_discount'] ?? 0),
                'gross_profit' => (float) ($data['gross_profit'] ?? 0),
                'net_profit' => (float) ($data['net_profit'] ?? 0),
            ], 'Profit/Loss report retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve profit/loss report: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get sales report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sales(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;
            $permitted_locations = $user->permitted_locations();

            $start = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $end = $request->input('end_date', Carbon::now()->format('Y-m-d'));
            $location_id = $request->input('location_id');

            // Get sales summary by status
            $sales_by_status = \App\Transaction::where('business_id', $business_id)
                ->where('type', 'sell')
                ->whereDate('transaction_date', '>=', $start)
                ->whereDate('transaction_date', '<=', $end)
                ->when($location_id, function ($q) use ($location_id) {
                    return $q->where('location_id', $location_id);
                })
                ->select('status', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(final_total), 0) as total'))
                ->groupBy('status')
                ->get()
                ->keyBy('status');

            // Get sales by payment status
            $sales_by_payment = \App\Transaction::where('business_id', $business_id)
                ->where('type', 'sell')
                ->where('status', 'final')
                ->whereDate('transaction_date', '>=', $start)
                ->whereDate('transaction_date', '<=', $end)
                ->when($location_id, function ($q) use ($location_id) {
                    return $q->where('location_id', $location_id);
                })
                ->select('payment_status', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(final_total), 0) as total'))
                ->groupBy('payment_status')
                ->get()
                ->keyBy('payment_status');

            // Get sell totals
            $sell_totals = $this->transactionUtil->getSellTotals($business_id, $start, $end, $location_id, null, $permitted_locations);

            return $this->successResponse([
                'date_range' => ['start' => $start, 'end' => $end],
                'totals' => [
                    'total_sales' => (float) ($sell_totals['total_sell_inc_tax'] ?? 0),
                    'total_exc_tax' => (float) ($sell_totals['total_sell_exc_tax'] ?? 0),
                    'invoice_due' => (float) ($sell_totals['invoice_due'] ?? 0),
                ],
                'by_status' => [
                    'final' => [
                        'count' => (int) ($sales_by_status['final']->count ?? 0),
                        'total' => (float) ($sales_by_status['final']->total ?? 0),
                    ],
                    'draft' => [
                        'count' => (int) ($sales_by_status['draft']->count ?? 0),
                        'total' => (float) ($sales_by_status['draft']->total ?? 0),
                    ],
                    'quotation' => [
                        'count' => (int) ($sales_by_status['quotation']->count ?? 0),
                        'total' => (float) ($sales_by_status['quotation']->total ?? 0),
                    ],
                ],
                'by_payment_status' => [
                    'paid' => [
                        'count' => (int) ($sales_by_payment['paid']->count ?? 0),
                        'total' => (float) ($sales_by_payment['paid']->total ?? 0),
                    ],
                    'partial' => [
                        'count' => (int) ($sales_by_payment['partial']->count ?? 0),
                        'total' => (float) ($sales_by_payment['partial']->total ?? 0),
                    ],
                    'due' => [
                        'count' => (int) ($sales_by_payment['due']->count ?? 0),
                        'total' => (float) ($sales_by_payment['due']->total ?? 0),
                    ],
                ],
            ], 'Sales report retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve sales report: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get purchase report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function purchases(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;
            $permitted_locations = $user->permitted_locations();

            $start = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $end = $request->input('end_date', Carbon::now()->format('Y-m-d'));
            $location_id = $request->input('location_id');

            // Get purchase summary by status
            $purchases_by_status = \App\Transaction::where('business_id', $business_id)
                ->where('type', 'purchase')
                ->whereDate('transaction_date', '>=', $start)
                ->whereDate('transaction_date', '<=', $end)
                ->when($location_id, function ($q) use ($location_id) {
                    return $q->where('location_id', $location_id);
                })
                ->select('status', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(final_total), 0) as total'))
                ->groupBy('status')
                ->get()
                ->keyBy('status');

            // Get purchase totals
            $purchase_totals = $this->transactionUtil->getPurchaseTotals($business_id, $start, $end, $location_id, null, $permitted_locations);

            return $this->successResponse([
                'date_range' => ['start' => $start, 'end' => $end],
                'totals' => [
                    'total_purchases' => (float) ($purchase_totals['total_purchase_inc_tax'] ?? 0),
                    'total_exc_tax' => (float) ($purchase_totals['total_purchase_exc_tax'] ?? 0),
                    'purchase_due' => (float) ($purchase_totals['purchase_due'] ?? 0),
                ],
                'by_status' => [
                    'received' => [
                        'count' => (int) ($purchases_by_status['received']->count ?? 0),
                        'total' => (float) ($purchases_by_status['received']->total ?? 0),
                    ],
                    'pending' => [
                        'count' => (int) ($purchases_by_status['pending']->count ?? 0),
                        'total' => (float) ($purchases_by_status['pending']->total ?? 0),
                    ],
                    'ordered' => [
                        'count' => (int) ($purchases_by_status['ordered']->count ?? 0),
                        'total' => (float) ($purchases_by_status['ordered']->total ?? 0),
                    ],
                ],
            ], 'Purchase report retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve purchase report: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get expense report by category.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function expenses(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;

            $start = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $end = $request->input('end_date', Carbon::now()->format('Y-m-d'));
            $location_id = $request->input('location_id');

            // Get expenses by category
            $expenses = \App\Transaction::where('transactions.business_id', $business_id)
                ->where('transactions.type', 'expense')
                ->whereDate('transactions.transaction_date', '>=', $start)
                ->whereDate('transactions.transaction_date', '<=', $end)
                ->when($location_id, function ($q) use ($location_id) {
                    return $q->where('transactions.location_id', $location_id);
                })
                ->leftJoin('expense_categories as ec', 'transactions.expense_category_id', '=', 'ec.id')
                ->select(
                    'ec.id as category_id',
                    'ec.name as category',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('COALESCE(SUM(transactions.final_total), 0) as total')
                )
                ->groupBy('ec.id', 'ec.name')
                ->orderBy('total', 'desc')
                ->get();

            $total_expense = $expenses->sum('total');

            return $this->successResponse([
                'date_range' => ['start' => $start, 'end' => $end],
                'total_expense' => (float) $total_expense,
                'by_category' => $expenses->map(function ($item) {
                    return [
                        'category_id' => $item->category_id,
                        'category' => $item->category ?? 'Uncategorized',
                        'count' => (int) $item->count,
                        'total' => (float) $item->total,
                    ];
                }),
            ], 'Expense report retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve expense report: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get stock summary report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stockSummary(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;
            $location_id = $request->input('location_id');

            // Get stock value by selling price
            $stock_by_sp = \App\VariationLocationDetails::join('variations', 'variation_location_details.variation_id', '=', 'variations.id')
                ->join('products', 'variation_location_details.product_id', '=', 'products.id')
                ->where('products.business_id', $business_id)
                ->when($location_id, function ($q) use ($location_id) {
                    return $q->where('variation_location_details.location_id', $location_id);
                })
                ->select(
                    DB::raw('COALESCE(SUM(variation_location_details.qty_available), 0) as total_qty'),
                    DB::raw('COALESCE(SUM(variation_location_details.qty_available * variations.sell_price_inc_tax), 0) as stock_value_by_sp'),
                    DB::raw('COALESCE(SUM(variation_location_details.qty_available * variations.dpp_inc_tax), 0) as stock_value_by_pp')
                )
                ->first();

            // Get count of products with low stock
            $low_stock_count = \App\VariationLocationDetails::join('products', 'variation_location_details.product_id', '=', 'products.id')
                ->where('products.business_id', $business_id)
                ->whereRaw('variation_location_details.qty_available <= products.alert_quantity')
                ->where('products.alert_quantity', '>', 0)
                ->count();

            return $this->successResponse([
                'total_quantity' => (float) ($stock_by_sp->total_qty ?? 0),
                'stock_value_by_selling_price' => (float) ($stock_by_sp->stock_value_by_sp ?? 0),
                'stock_value_by_purchase_price' => (float) ($stock_by_sp->stock_value_by_pp ?? 0),
                'potential_profit' => (float) (($stock_by_sp->stock_value_by_sp ?? 0) - ($stock_by_sp->stock_value_by_pp ?? 0)),
                'low_stock_products_count' => (int) $low_stock_count,
            ], 'Stock summary retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve stock summary: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get trending/top selling products.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trendingProducts(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;

            $start = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $end = $request->input('end_date', Carbon::now()->format('Y-m-d'));
            $limit = min($request->input('limit', 10), 50);

            $trending = \App\TransactionSellLine::join('transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
                ->join('products', 'transaction_sell_lines.product_id', '=', 'products.id')
                ->where('transactions.business_id', $business_id)
                ->where('transactions.type', 'sell')
                ->where('transactions.status', 'final')
                ->whereDate('transactions.transaction_date', '>=', $start)
                ->whereDate('transactions.transaction_date', '<=', $end)
                ->select(
                    'products.id',
                    'products.name',
                    'products.sku',
                    DB::raw('SUM(transaction_sell_lines.quantity) as total_qty_sold'),
                    DB::raw('SUM(transaction_sell_lines.quantity * transaction_sell_lines.unit_price_inc_tax) as total_sales')
                )
                ->groupBy('products.id', 'products.name', 'products.sku')
                ->orderBy('total_qty_sold', 'desc')
                ->limit($limit)
                ->get();

            return $this->successResponse([
                'date_range' => ['start' => $start, 'end' => $end],
                'products' => $trending->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'sku' => $item->sku,
                        'quantity_sold' => (float) $item->total_qty_sold,
                        'total_sales' => (float) $item->total_sales,
                    ];
                }),
            ], 'Trending products retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve trending products: ' . $e->getMessage(), 500);
        }
    }
}
