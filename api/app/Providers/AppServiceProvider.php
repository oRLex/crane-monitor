<?php

namespace App\Providers;

use App\Auth\TenantContext;
use App\Telemetry\Bus\RabbitMqBus;
use App\Telemetry\Bus\SyncBus;
use App\Telemetry\Bus\TelemetryBus;
use App\Telemetry\HourlyAggregator;
use App\Telemetry\TelemetryProcessor;
use App\Telemetry\TelemetryStore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);

        $this->app->singleton(TelemetryStore::class, fn () => new TelemetryStore(
            (int) config('telemetry.history_points'),
            config('telemetry.store'),
        ));
        $this->app->singleton(TelemetryProcessor::class, fn ($app) => new TelemetryProcessor(
            $app->make(TelemetryStore::class),
            new HourlyAggregator((int) config('telemetry.tick_ms')),
        ));
        $this->app->singleton(TelemetryBus::class, fn ($app) => match (config('telemetry.bus')) {
            'rabbitmq' => new RabbitMqBus(
                config('telemetry.rabbitmq.url'),
                config('telemetry.rabbitmq.exchange'),
                config('telemetry.rabbitmq.queue'),
            ),
            default => new SyncBus(fn () => $app->make(TelemetryProcessor::class)),
        });
    }

    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        // LLM calls are the expensive path: a tighter per-user limit on top of the daily token budget.
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(10)->by('ai:'.($request->user()?->id ?: $request->ip())));
    }
}
