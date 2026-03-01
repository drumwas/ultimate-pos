<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create api_clients table for API Gateway client management.
 * Each client gets unique API keys for identification and rate limiting.
 */
class CreateApiClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->string('name'); // Client app name (e.g., "iOS App", "Android App", "Partner Integration")
            $table->string('client_key', 64)->unique(); // Public API key for identification
            $table->string('client_secret', 64); // Secret for secure operations
            $table->text('description')->nullable();
            $table->string('type')->default('mobile'); // mobile, web, partner, internal
            $table->boolean('is_active')->default(true);
            $table->integer('rate_limit_per_minute')->default(60); // Per-minute rate limit
            $table->integer('rate_limit_per_day')->default(10000); // Daily limit
            $table->json('allowed_ips')->nullable(); // IP whitelist (null = all allowed)
            $table->json('permissions')->nullable(); // Specific endpoint permissions
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->index(['client_key', 'is_active']);
        });

        // Table for logging API requests
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_client_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('method', 10);
            $table->string('endpoint');
            $table->integer('status_code');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->integer('response_time_ms')->nullable(); // Response time in milliseconds
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at');

            $table->foreign('api_client_id')->references('id')->on('api_clients')->onDelete('set null');
            $table->index(['api_client_id', 'created_at']);
            $table->index(['endpoint', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('api_clients');
    }
}
