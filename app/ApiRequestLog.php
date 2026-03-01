<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * API Request Log Model
 * 
 * Stores API request logs for analytics and monitoring.
 */
class ApiRequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'api_client_id',
        'user_id',
        'method',
        'endpoint',
        'status_code',
        'ip_address',
        'user_agent',
        'response_time_ms',
        'request_headers',
        'request_body',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_body' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Log an API request.
     *
     * @param array $data
     * @return self
     */
    public static function log(array $data): self
    {
        return self::create(array_merge($data, [
            'created_at' => now(),
        ]));
    }

    /**
     * Get the API client.
     */
    public function apiClient()
    {
        return $this->belongsTo(ApiClient::class);
    }

    /**
     * Get the user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for successful requests.
     */
    public function scopeSuccessful($query)
    {
        return $query->whereBetween('status_code', [200, 299]);
    }

    /**
     * Scope for failed requests.
     */
    public function scopeFailed($query)
    {
        return $query->where('status_code', '>=', 400);
    }

    /**
     * Scope for date range.
     */
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Get request count by endpoint for a client.
     *
     * @param int $clientId
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Support\Collection
     */
    public static function getEndpointStats(int $clientId, string $startDate, string $endDate)
    {
        return self::where('api_client_id', $clientId)
            ->dateRange($startDate, $endDate)
            ->selectRaw('endpoint, COUNT(*) as request_count, AVG(response_time_ms) as avg_response_time')
            ->groupBy('endpoint')
            ->orderBy('request_count', 'desc')
            ->get();
    }
}
