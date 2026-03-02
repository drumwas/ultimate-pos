<?php

namespace Parcy\Mpesa;

use Illuminate\Support\ServiceProvider;
use Parcy\Mpesa\Services\MpesaService;
use Parcy\Mpesa\Console\ReconcilePendingTransactions;

class MpesaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mpesa.php', 'mpesa');

        $this->app->singleton(MpesaService::class, function ($app) {
            return new MpesaService();
        });

        $this->app->alias(MpesaService::class, 'mpesa');
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/mpesa.php' => config_path('mpesa.php'),
        ], 'mpesa-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'mpesa-migrations');

        // Load migrations automatically (so they run without publishing)
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load package routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        // Register artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ReconcilePendingTransactions::class,
            ]);
        }
    }
}
