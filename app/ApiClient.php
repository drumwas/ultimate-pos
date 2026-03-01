<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * API Client Model
 * 
 * Represents a registered API client application (mobile app, partner, etc.)
 */
class ApiClient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'name',
        'client_key',
        'client_secret',
        'description',
        'type',
        'is_active',
        'rate_limit_per_minute',
        'rate_limit_per_day',
        'allowed_ips',
        'permissions',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allowed_ips' => 'array',
        'permissions' => 'array',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'client_secret',
    ];

    /**
     * Generate a new API key pair.
     *
     * @return array
     */
    public static function generateKeyPair(): array
    {
        return [
            'client_key' => 'pk_' . Str::random(32),
            'client_secret' => 'sk_' . Str::random(48),
        ];
    }

    /**
     * Create a new API client with generated keys.
     *
     * @param array $attributes
     * @return self
     */
    public static function createWithKeys(array $attributes): self
    {
        $keys = self::generateKeyPair();

        return self::create(array_merge($attributes, $keys));
    }

    /**
     * Find client by API key.
     *
     * @param string $clientKey
     * @return self|null
     */
    public static function findByKey(string $clientKey): ?self
    {
        return self::where('client_key', $clientKey)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Update last used timestamp.
     *
     * @return void
     */
    public function touchLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Check if client has specific permission.
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        if (empty($this->permissions)) {
            return true; // No restrictions = all allowed
        }

        return in_array($permission, $this->permissions);
    }

    /**
     * Check if IP is allowed.
     *
     * @param string $ip
     * @return bool
     */
    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->allowed_ips)) {
            return true; // No IP whitelist = all allowed
        }

        return in_array($ip, $this->allowed_ips);
    }

    /**
     * Get the business that owns the client.
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the user who created this client.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get request logs for this client.
     */
    public function requestLogs()
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    /**
     * Get client types for dropdown.
     *
     * @return array
     */
    public static function getTypes(): array
    {
        return [
            'mobile' => 'Mobile App',
            'web' => 'Web Application',
            'partner' => 'Partner Integration',
            'internal' => 'Internal Service',
        ];
    }
}
