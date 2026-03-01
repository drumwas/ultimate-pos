<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientsController;
use App\Http\Controllers\Api\ContactsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProductsController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\TransactionsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
|--------------------------------------------------------------------------
| Health Check (No Auth Required)
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/ping', function () {
    return response()->json(['pong' => true]);
});

// Default Laravel route
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Mobile Admin API Routes
|--------------------------------------------------------------------------
|
| API endpoints for mobile admin app oversight functionality.
| Authentication is handled via Laravel Passport (OAuth2).
| API Gateway middleware handles client identification, rate limiting, and logging.
|
*/

// Authentication Routes (no auth required for login, but gateway tracks requests)
Route::prefix('auth')->middleware(['api.gateway'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    // Protected auth routes
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
});

// Protected API Routes - All require authentication + gateway
Route::middleware(['api.gateway', 'auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Dashboard Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('dashboard')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/today', [DashboardController::class, 'todayStats']);
        Route::get('/stock-alerts', [DashboardController::class, 'stockAlerts']);
        Route::get('/payment-dues', [DashboardController::class, 'paymentDues']);
    });

    /*
    |--------------------------------------------------------------------------
    | Reports Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('reports')->group(function () {
        Route::get('/profit-loss', [ReportsController::class, 'profitLoss']);
        Route::get('/sales', [ReportsController::class, 'sales']);
        Route::get('/purchases', [ReportsController::class, 'purchases']);
        Route::get('/expenses', [ReportsController::class, 'expenses']);
        Route::get('/stock-summary', [ReportsController::class, 'stockSummary']);
        Route::get('/trending-products', [ReportsController::class, 'trendingProducts']);
    });

    /*
    |--------------------------------------------------------------------------
    | Transactions Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('transactions')->group(function () {
        Route::get('/recent', [TransactionsController::class, 'recent']);
        Route::get('/sales', [TransactionsController::class, 'sales']);
        Route::get('/sales/{id}', [TransactionsController::class, 'showSale']);
        Route::get('/purchases', [TransactionsController::class, 'purchases']);
        Route::get('/purchases/{id}', [TransactionsController::class, 'showPurchase']);
    });

    /*
    |--------------------------------------------------------------------------
    | Products Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductsController::class, 'index']);
        Route::get('/categories', [ProductsController::class, 'categories']);
        Route::get('/brands', [ProductsController::class, 'brands']);
        Route::get('/low-stock', [ProductsController::class, 'lowStock']);
        Route::get('/{id}', [ProductsController::class, 'show']);
    });

    /*
    |--------------------------------------------------------------------------
    | Contacts Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('contacts')->group(function () {
        Route::get('/customers', [ContactsController::class, 'customers']);
        Route::get('/suppliers', [ContactsController::class, 'suppliers']);
        Route::get('/with-balances', [ContactsController::class, 'withBalances']);
        Route::get('/{id}', [ContactsController::class, 'show']);
    });

    /*
    |--------------------------------------------------------------------------
    | API Client Management Routes (Admin Only)
    |--------------------------------------------------------------------------
    */
    Route::prefix('clients')->group(function () {
        Route::get('/', [ClientsController::class, 'index']);
        Route::post('/', [ClientsController::class, 'store']);
        Route::get('/{id}', [ClientsController::class, 'show']);
        Route::put('/{id}', [ClientsController::class, 'update']);
        Route::delete('/{id}', [ClientsController::class, 'destroy']);
        Route::post('/{id}/regenerate-keys', [ClientsController::class, 'regenerateKeys']);
        Route::get('/{id}/analytics', [ClientsController::class, 'analytics']);
    });
});
