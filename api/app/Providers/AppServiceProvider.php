<?php

namespace App\Providers;

use App\Ai\Knowledge\Embeddings;
use App\Ai\Knowledge\HashEmbeddings;
use App\Ai\Knowledge\VoyageEmbeddings;
use App\Ai\Llm\AnthropicLlmClient;
use App\Ai\Llm\FakeLlmClient;
use App\Ai\Llm\LlmClient;
use App\Ai\TokenBudget;
use App\Ai\Tools\AcknowledgeAlarm;
use App\Ai\Tools\GetCraneStatus;
use App\Ai\Tools\GetHourlyStats;
use App\Ai\Tools\ListAlarms;
use App\Ai\Tools\ListCranes;
use App\Ai\Tools\SearchDocs;
use App\Ai\Tools\ToolRegistry;
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

        $this->app->singleton(LlmClient::class, fn () => filled(config('ai.anthropic_key'))
            ? new AnthropicLlmClient(config('ai.anthropic_key'), config('ai.model'), config('ai.effort'), (float) config('ai.timeout'), (int) config('ai.max_retries'))
            : new FakeLlmClient);
        $this->app->singleton(Embeddings::class, fn () => filled(config('ai.embeddings.voyage_key'))
            ? new VoyageEmbeddings(config('ai.embeddings.voyage_key'), config('ai.embeddings.model'), (int) config('ai.embeddings.dimensions'))
            : new HashEmbeddings((int) config('ai.embeddings.dimensions')));
        $this->app->singleton(TokenBudget::class, fn () => new TokenBudget((int) config('ai.daily_token_budget')));
        $this->app->tag([ListCranes::class, GetCraneStatus::class, GetHourlyStats::class, ListAlarms::class, SearchDocs::class, AcknowledgeAlarm::class], 'ai.tools');
        $this->app->bind(ToolRegistry::class, fn ($app) => new ToolRegistry($app->tagged('ai.tools'), $app->make(TenantContext::class)));

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
