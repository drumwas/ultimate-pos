<?php

namespace App\Http\Controllers\Api;

use App\Product;
use App\Category;
use App\Brands;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DB;

/**
 * API Products Controller
 * 
 * Provides product listing and details for mobile admin.
 */
class ProductsController extends BaseApiController
{
    /**
     * List products with pagination and filters.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;
            $per_page = min($request->input('per_page', 20), 100);

            $query = Product::where('business_id', $business_id)
                ->with([
                    'category:id,name',
                    'brand:id,name',
                    'unit:id,short_name',
                ])
                ->select([
                    'id',
                    'name',
                    'sku',
                    'type',
                    'category_id',
                    'brand_id',
                    'unit_id',
                    'enable_stock',
                    'alert_quantity',
                    'is_inactive',
                    'image'
                ]);

            // Filters
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('is_inactive')) {
                $query->where('is_inactive', $request->is_inactive);
            } else {
                $query->where('is_inactive', 0); // By default show active products
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('sku', 'LIKE', "%{$search}%");
                });
            }

            $products = $query->orderBy('name', 'asc')
                ->paginate($per_page);

            $items = $products->map(function ($item) use ($business_id) {
                // Get total stock for this product across all locations
                $stock = \App\VariationLocationDetails::join('variations', 'variation_location_details.variation_id', '=', 'variations.id')
                    ->where('variation_location_details.product_id', $item->id)
                    ->sum('variation_location_details.qty_available');

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'type' => $item->type,
                    'category' => $item->category->name ?? null,
                    'brand' => $item->brand->name ?? null,
                    'unit' => $item->unit->short_name ?? null,
                    'stock_enabled' => (bool) $item->enable_stock,
                    'current_stock' => $item->enable_stock ? (float) $stock : null,
                    'alert_quantity' => (float) $item->alert_quantity,
                    'is_inactive' => (bool) $item->is_inactive,
                    'image' => $item->image ? url('uploads/img/' . $item->image) : null,
                ];
            });

            return $this->successResponse([
                'items' => $items,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ], 'Products retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve products: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single product details with variations and stock.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;

            $product = Product::where('business_id', $business_id)
                ->where('id', $id)
                ->with([
                    'category:id,name',
                    'sub_category:id,name',
                    'brand:id,name',
                    'unit:id,short_name,actual_name',
                    'variations.variation_location_details.location:id,name',
                    'product_tax:id,name,amount',
                ])
                ->first();

            if (!$product) {
                return $this->errorResponse('Product not found', 404);
            }

            $variations = $product->variations->map(function ($variation) {
                $stock_by_location = $variation->variation_location_details->map(function ($vld) {
                    return [
                        'location_id' => $vld->location_id,
                        'location' => $vld->location->name ?? null,
                        'qty_available' => (float) $vld->qty_available,
                    ];
                });

                $total_stock = $variation->variation_location_details->sum('qty_available');

                return [
                    'id' => $variation->id,
                    'name' => $variation->name,
                    'sub_sku' => $variation->sub_sku,
                    'default_purchase_price' => (float) $variation->default_purchase_price,
                    'dpp_inc_tax' => (float) $variation->dpp_inc_tax,
                    'default_sell_price' => (float) $variation->default_sell_price,
                    'sell_price_inc_tax' => (float) $variation->sell_price_inc_tax,
                    'total_stock' => (float) $total_stock,
                    'stock_by_location' => $stock_by_location,
                ];
            });

            $data = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'type' => $product->type,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
                'sub_category' => $product->sub_category ? [
                    'id' => $product->sub_category->id,
                    'name' => $product->sub_category->name,
                ] : null,
                'brand' => $product->brand ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name,
                ] : null,
                'unit' => $product->unit ? [
                    'id' => $product->unit->id,
                    'name' => $product->unit->actual_name,
                    'short_name' => $product->unit->short_name,
                ] : null,
                'tax' => $product->product_tax ? [
                    'id' => $product->product_tax->id,
                    'name' => $product->product_tax->name,
                    'rate' => (float) $product->product_tax->amount,
                ] : null,
                'stock_enabled' => (bool) $product->enable_stock,
                'alert_quantity' => (float) $product->alert_quantity,
                'is_inactive' => (bool) $product->is_inactive,
                'image' => $product->image ? url('uploads/img/' . $product->image) : null,
                'product_description' => $product->product_description,
                'variations' => $variations,
            ];

            return $this->successResponse($data, 'Product details retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve product: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get categories list.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function categories(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;

            $categories = Category::where('business_id', $business_id)
                ->where('category_type', 'product')
                ->whereNull('parent_id')
                ->with(['sub_categories:id,name,parent_id'])
                ->select(['id', 'name', 'short_code', 'description'])
                ->orderBy('name')
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'short_code' => $category->short_code,
                        'description' => $category->description,
                        'subcategories' => $category->sub_categories->map(function ($sub) {
                            return [
                                'id' => $sub->id,
                                'name' => $sub->name,
                            ];
                        }),
                    ];
                });

            return $this->successResponse($categories, 'Categories retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve categories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get brands list.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function brands(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;

            $brands = Brands::where('business_id', $business_id)
                ->select(['id', 'name', 'description'])
                ->orderBy('name')
                ->get()
                ->map(function ($brand) {
                    return [
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'description' => $brand->description,
                    ];
                });

            return $this->successResponse($brands, 'Brands retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve brands: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get products with low stock.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function lowStock(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;
            $per_page = min($request->input('per_page', 20), 100);

            $products = Product::where('products.business_id', $business_id)
                ->where('products.enable_stock', 1)
                ->where('products.is_inactive', 0)
                ->join('variations', 'products.id', '=', 'variations.product_id')
                ->join('variation_location_details as vld', 'variations.id', '=', 'vld.variation_id')
                ->select([
                    'products.id',
                    'products.name',
                    'products.sku',
                    'products.alert_quantity',
                    'variations.name as variation_name',
                    'variations.sub_sku',
                    DB::raw('SUM(vld.qty_available) as current_stock')
                ])
                ->groupBy('products.id', 'products.name', 'products.sku', 'products.alert_quantity', 'variations.id', 'variations.name', 'variations.sub_sku')
                ->havingRaw('current_stock <= products.alert_quantity')
                ->orderBy('current_stock', 'asc')
                ->paginate($per_page);

            $items = $products->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'variation' => $item->variation_name !== 'DUMMY' ? $item->variation_name : null,
                    'sub_sku' => $item->sub_sku,
                    'current_stock' => (float) $item->current_stock,
                    'alert_quantity' => (float) $item->alert_quantity,
                    'shortage' => (float) ($item->alert_quantity - $item->current_stock),
                ];
            });

            return $this->successResponse([
                'items' => $items,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ], 'Low stock products retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve low stock products: ' . $e->getMessage(), 500);
        }
    }
}
