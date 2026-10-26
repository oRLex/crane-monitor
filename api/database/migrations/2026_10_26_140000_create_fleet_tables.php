<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('city');
            $table->string('tenant_id')->index();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->timestamps();
        });

        Schema::create('cranes', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('model');
            $table->string('serial_number')->unique();
            $table->float('max_load_t');
            $table->float('jib_length_m');
            $table->float('rated_moment_tm');
            $table->string('stream_url')->nullable();
            $table->string('site_id')->index();
            $table->string('tenant_id')->index();
            $table->foreign('site_id')->references('id')->on('sites');
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->timestamps();
        });

        Schema::create('alarms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('crane_id');
            // Denormalised so tenant isolation is a single WHERE, like every other tenant-owned table.
            $table->string('tenant_id')->index();
            $table->string('kind');      // wind | overload | offline
            $table->string('severity');  // warning | critical
            $table->string('message');
            $table->timestamp('raised_at', 3);
            $table->timestamp('cleared_at', 3)->nullable();
            $table->timestamp('acknowledged_at', 3)->nullable();
            $table->string('acknowledged_by')->nullable();
            $table->foreign('crane_id')->references('id')->on('cranes');
            $table->index(['crane_id', 'raised_at']);
            $table->index(['crane_id', 'cleared_at']);
        });

        // Raw telemetry lives in Redis (live window) and would go to a time-series store in
        // production; PostgreSQL keeps hourly aggregates for reports and the AI shift report.
        Schema::create('telemetry_hourly', function (Blueprint $table) {
            $table->string('crane_id');
            $table->timestamp('hour');
            $table->float('max_load_t');
            $table->float('max_moment_pct');
            $table->float('max_wind_ms');
            $table->float('wind_sum');
            $table->integer('samples');
            $table->integer('operating_s');
            $table->integer('offline_s');
            $table->integer('lifts');
            $table->primary(['crane_id', 'hour']);
            $table->foreign('crane_id')->references('id')->on('cranes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_hourly');
        Schema::dropIfExists('alarms');
        Schema::dropIfExists('cranes');
        Schema::dropIfExists('sites');
    }
};
