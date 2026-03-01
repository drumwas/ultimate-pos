<?php

namespace App\Http\Controllers\Api;

use App\ApiClient;
use App\ApiRequestLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DB;

/**
 * API Clients Controller
 * 
 * Manages API clients (registration, listing, analytics).
 * Used by admin users to manage API access for mobile apps and integrations.
 */
class ClientsController extends BaseApiController
{
    /**
     * List all API clients for the business.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $business_id = $user->business_id;

            $clients = ApiClient::where('business_id', $business_id)
                ->select(['id', 'name', 'client_key', 'type', 'is_active', 'rate_limit_per_minute', 'rate_limit_per_day', 'last_used_at', 'created_at'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($client) {
                    return [
                        'id' => $client->id,
                        'name' => $client->name,
                        'client_key' => $client->client_key,
                        'type' => $client->type,
                        'is_active' => $client->is_active,
                        'rate_limit_per_minute' => $client->rate_limit_per_minute,
                        'rate_limit_per_day' => $client->rate_limit_per_day,
                        'last_used_at' => $client->last_used_at?->toIso8601String(),
                        'created_at' => $client->created_at->toIso8601String(),
                    ];
                });

            return $this->successResponse($clients, 'API clients retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve API clients: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new API client.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|in:mobile,web,partner,internal',
                'description' => 'nullable|string',
                'rate_limit_per_minute' => 'nullable|integer|min:1|max:1000',
                'rate_limit_per_day' => 'nullable|integer|min:1|max:1000000',
                'allowed_ips' => 'nullable|array',
                'allowed_ips.*' => 'ip',
            ]);

            $user = auth()->user();

            // Generate keys
            $keys = ApiClient::generateKeyPair();

            $client = ApiClient::create([
                'business_id' => $user->business_id,
                'name' => $request->name,
                'client_key' => $keys['client_key'],
                'client_secret' => bcrypt($keys['client_secret']),
                'type' => $request->type,
                'description' => $request->description,
                'rate_limit_per_minute' => $request->rate_limit_per_minute ?? 60,
                'rate_limit_per_day' => $request->rate_limit_per_day ?? 10000,
                'allowed_ips' => $request->allowed_ips,
                'is_active' => true,
                'created_by' => $user->id,
            ]);

            return $this->successResponse([
                'id' => $client->id,
                'name' => $client->name,
                'client_key' => $keys['client_key'],
                'client_secret' => $keys['client_secret'], // Only returned on creation!
                'type' => $client->type,
                'message' => 'Save these credentials securely. The client_secret will not be shown again.',
            ], 'API client created', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create API client: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get API client details.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $client = ApiClient::where('business_id', $user->business_id)
                ->where('id', $id)
                ->first();

            if (!$client) {
                return $this->errorResponse('API client not found', 404);
            }

            return $this->successResponse([
                'id' => $client->id,
                'name' => $client->name,
                'client_key' => $client->client_key,
                'type' => $client->type,
                'description' => $client->description,
                'is_active' => $client->is_active,
                'rate_limit_per_minute' => $client->rate_limit_per_minute,
                'rate_limit_per_day' => $client->rate_limit_per_day,
                'allowed_ips' => $client->allowed_ips,
                'permissions' => $client->permissions,
                'last_used_at' => $client->last_used_at?->toIso8601String(),
                'created_at' => $client->created_at->toIso8601String(),
            ], 'API client details retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve API client: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update API client.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'sometimes|boolean',
                'rate_limit_per_minute' => 'nullable|integer|min:1|max:1000',
                'rate_limit_per_day' => 'nullable|integer|min:1|max:1000000',
                'allowed_ips' => 'nullable|array',
                'allowed_ips.*' => 'ip',
            ]);

            $user = auth()->user();

            $client = ApiClient::where('business_id', $user->business_id)
                ->where('id', $id)
                ->first();

            if (!$client) {
                return $this->errorResponse('API client not found', 404);
            }

            $client->update($request->only([
                'name',
                'description',
                'is_active',
                'rate_limit_per_minute',
                'rate_limit_per_day',
                'allowed_ips'
            ]));

            return $this->successResponse([
                'id' => $client->id,
                'name' => $client->name,
                'is_active' => $client->is_active,
            ], 'API client updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update API client: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Regenerate API keys for a client.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function regenerateKeys(int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $client = ApiClient::where('business_id', $user->business_id)
                ->where('id', $id)
                ->first();

            if (!$client) {
                return $this->errorResponse('API client not found', 404);
            }

            $keys = ApiClient::generateKeyPair();

            $client->update([
                'client_key' => $keys['client_key'],
                'client_secret' => bcrypt($keys['client_secret']),
            ]);

            return $this->successResponse([
                'client_key' => $keys['client_key'],
                'client_secret' => $keys['client_secret'],
                'message' => 'Keys regenerated. Save these credentials securely. The client_secret will not be shown again.',
            ], 'API keys regenerated');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to regenerate keys: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete API client.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $client = ApiClient::where('business_id', $user->business_id)
                ->where('id', $id)
                ->first();

            if (!$client) {
                return $this->errorResponse('API client not found', 404);
            }

            $client->delete();

            return $this->successResponse(null, 'API client deleted');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete API client: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get usage analytics for an API client.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function analytics(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $client = ApiClient::where('business_id', $user->business_id)
                ->where('id', $id)
                ->first();

            if (!$client) {
                return $this->errorResponse('API client not found', 404);
            }

            $days = min($request->input('days', 7), 30);
            $startDate = now()->subDays($days)->startOfDay();
            $endDate = now()->endOfDay();

            // Request counts by day
            $dailyStats = ApiRequestLog::where('api_client_id', $id)
                ->dateRange($startDate, $endDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as requests, AVG(response_time_ms) as avg_response_time')
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get();

            // Top endpoints
            $topEndpoints = ApiRequestLog::getEndpointStats($id, $startDate, $endDate)
                ->take(10);

            // Error rate
            $totalRequests = ApiRequestLog::where('api_client_id', $id)
                ->dateRange($startDate, $endDate)
                ->count();

            $failedRequests = ApiRequestLog::where('api_client_id', $id)
                ->dateRange($startDate, $endDate)
                ->failed()
                ->count();

            return $this->successResponse([
                'period' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                    'days' => $days,
                ],
                'summary' => [
                    'total_requests' => $totalRequests,
                    'failed_requests' => $failedRequests,
                    'error_rate' => $totalRequests > 0 ? round(($failedRequests / $totalRequests) * 100, 2) : 0,
                ],
                'daily_stats' => $dailyStats->map(function ($day) {
                    return [
                        'date' => $day->date,
                        'requests' => (int) $day->requests,
                        'avg_response_time_ms' => round($day->avg_response_time, 2),
                    ];
                }),
                'top_endpoints' => $topEndpoints->map(function ($endpoint) {
                    return [
                        'endpoint' => $endpoint->endpoint,
                        'requests' => (int) $endpoint->request_count,
                        'avg_response_time_ms' => round($endpoint->avg_response_time, 2),
                    ];
                }),
            ], 'API client analytics retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve analytics: ' . $e->getMessage(), 500);
        }
    }
}
