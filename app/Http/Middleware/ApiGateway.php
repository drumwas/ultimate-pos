<?php

namespace App\Http\Middleware;

use App\ApiClient;
use App\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * API Gateway Middleware
 * 
 * Handles client identification, rate limiting, request logging, and IP validation
 * for multi-client API access.
 */
class ApiGateway
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        // Get API client key from header
        $clientKey = $request->header('X-Api-Key');
        $apiClient = null;

        if ($clientKey) {
            $apiClient = $this->validateClient($clientKey, $request);

            if ($apiClient instanceof \Illuminate\Http\JsonResponse) {
                return $apiClient; // Return error response
            }

            // Set client in request for later use
            $request->attributes->set('api_client', $apiClient);

            // Check rate limits
            $rateLimitCheck = $this->checkRateLimits($apiClient, $request);
            if ($rateLimitCheck !== true) {
                return $rateLimitCheck;
            }
        }

        // Process the request
        $response = $next($request);

        // Log the request (async in production, sync for now)
        $this->logRequest($request, $response, $apiClient, $startTime);

        // Add rate limit headers to response
        if ($apiClient) {
            $response = $this->addRateLimitHeaders($response, $apiClient);
        }

        return $response;
    }

    /**
     * Validate the API client.
     *
     * @param string $clientKey
     * @param Request $request
     * @return ApiClient|\Illuminate\Http\JsonResponse
     */
    protected function validateClient(string $clientKey, Request $request)
    {
        $client = ApiClient::findByKey($clientKey);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key',
                'error_code' => 'INVALID_API_KEY',
            ], 401);
        }

        // Check if client's business exists
        if (!$client->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Client not associated with a business',
                'error_code' => 'NO_BUSINESS',
            ], 403);
        }

        // Check IP whitelist
        if (!$client->isIpAllowed($request->ip())) {
            return response()->json([
                'success' => false,
                'message' => 'IP address not allowed',
                'error_code' => 'IP_NOT_ALLOWED',
            ], 403);
        }

        // Update last used timestamp (debounced to once per minute)
        $cacheKey = "api_client_touched_{$client->id}";
        if (!Cache::has($cacheKey)) {
            $client->touchLastUsed();
            Cache::put($cacheKey, true, 60);
        }

        return $client;
    }

    /**
     * Check rate limits for the client.
     *
     * @param ApiClient $client
     * @param Request $request
     * @return bool|\Illuminate\Http\JsonResponse
     */
    protected function checkRateLimits(ApiClient $client, Request $request)
    {
        $minuteKey = "api_rate_limit:{$client->id}:minute:" . now()->format('Y-m-d-H-i');
        $dayKey = "api_rate_limit:{$client->id}:day:" . now()->format('Y-m-d');

        // Check per-minute limit
        $minuteCount = (int) Cache::get($minuteKey, 0);
        if ($minuteCount >= $client->rate_limit_per_minute) {
            return response()->json([
                'success' => false,
                'message' => 'Rate limit exceeded. Please slow down.',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => 60 - now()->second,
            ], 429)->header('Retry-After', 60 - now()->second);
        }

        // Check daily limit
        $dayCount = (int) Cache::get($dayKey, 0);
        if ($dayCount >= $client->rate_limit_per_day) {
            return response()->json([
                'success' => false,
                'message' => 'Daily rate limit exceeded. Try again tomorrow.',
                'error_code' => 'DAILY_LIMIT_EXCEEDED',
            ], 429);
        }

        // Increment counters
        Cache::put($minuteKey, $minuteCount + 1, 60);
        Cache::put($dayKey, $dayCount + 1, now()->endOfDay()->diffInSeconds(now()));

        return true;
    }

    /**
     * Log the API request.
     *
     * @param Request $request
     * @param mixed $response
     * @param ApiClient|null $client
     * @param float $startTime
     * @return void
     */
    protected function logRequest(Request $request, $response, ?ApiClient $client, float $startTime): void
    {
        // Skip logging for certain endpoints or in certain conditions
        if ($this->shouldSkipLogging($request)) {
            return;
        }

        $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);
        $statusCode = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;

        // Get error message for failed requests
        $errorMessage = null;
        if ($statusCode >= 400 && method_exists($response, 'getContent')) {
            $content = json_decode($response->getContent(), true);
            $errorMessage = $content['message'] ?? null;
        }

        ApiRequestLog::log([
            'api_client_id' => $client?->id,
            'user_id' => auth()->id(),
            'method' => $request->method(),
            'endpoint' => $request->path(),
            'status_code' => $statusCode,
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 255),
            'response_time_ms' => $responseTimeMs,
            'error_message' => $errorMessage,
            // Only log sensitive data in debug mode
            // 'request_headers' => config('app.debug') ? $request->headers->all() : null,
            // 'request_body' => config('app.debug') ? $request->except(['password']) : null,
        ]);
    }

    /**
     * Add rate limit headers to response.
     *
     * @param mixed $response
     * @param ApiClient $client
     * @return mixed
     */
    protected function addRateLimitHeaders($response, ApiClient $client)
    {
        $minuteKey = "api_rate_limit:{$client->id}:minute:" . now()->format('Y-m-d-H-i');
        $remaining = max(0, $client->rate_limit_per_minute - (int) Cache::get($minuteKey, 0));

        if (method_exists($response, 'header')) {
            $response->header('X-RateLimit-Limit', $client->rate_limit_per_minute);
            $response->header('X-RateLimit-Remaining', $remaining);
            $response->header('X-RateLimit-Reset', now()->addMinute()->startOfMinute()->timestamp);
        }

        return $response;
    }

    /**
     * Determine if logging should be skipped for this request.
     *
     * @param Request $request
     * @return bool
     */
    protected function shouldSkipLogging(Request $request): bool
    {
        // Skip health check endpoints
        $skipPaths = ['api/health', 'api/ping'];

        return in_array($request->path(), $skipPaths);
    }
}
