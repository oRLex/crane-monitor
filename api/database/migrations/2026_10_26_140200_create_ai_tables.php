<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('title');
            // Optional focus: the crane / alarm the conversation was started from.
            $table->string('crane_id')->nullable();
            $table->string('alarm_id')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role'); // user | assistant
            // Full API content blocks (text, tool_use, tool_result) so history replays exactly.
            $table->jsonb('content');
            $table->timestamps();
        });

        // One row per LLM / embedding call: the basis for cost dashboards, budgets and debugging.
        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->string('feature');  // assistant | report | embeddings | eval
            $table->string('model');
            $table->string('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('stop_reason')->nullable();
            $table->boolean('mock')->default(false);
            $table->timestamp('created_at')->index();
        });

        Schema::create('shift_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->index();
            $table->string('crane_id');
            $table->foreign('crane_id')->references('id')->on('cranes');
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('status')->default('queued'); // queued | running | ready | failed
            $table->string('input_hash', 64)->nullable();
            $table->jsonb('facts')->nullable();   // deterministic aggregates fed to the model
            $table->jsonb('report')->nullable();  // validated structured output
            $table->string('error')->nullable();
            $table->string('model')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_reports');
        Schema::dropIfExists('ai_usage');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
