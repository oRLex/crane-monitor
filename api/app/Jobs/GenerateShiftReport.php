<?php

namespace App\Jobs;

use App\Ai\Assistant\Prompts;
use App\Ai\Llm\LlmClient;
use App\Ai\Llm\LlmException;
use App\Ai\Llm\LlmRequest;
use App\Ai\Reports\ReportSchema;
use App\Ai\Reports\ShiftFacts;
use App\Ai\TokenBudget;
use App\Ai\UsageRecorder;
use App\Auth\TenantContext;
use App\Events\ReportReady;
use App\Models\ShiftReport;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;
use Throwable;

class GenerateShiftReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60];

    public int $timeout = 180;

    public function __construct(public readonly string $reportId)
    {
        $this->onQueue('ai');
    }

    public function handle(LlmClient $llm, ShiftFacts $collector, UsageRecorder $usage, TokenBudget $budget, TenantContext $context): void
    {
        $report = ShiftReport::query()->withoutGlobalScopes()->with('crane')->findOrFail($this->reportId);
        $user = User::query()->findOrFail($report->requested_by);

        // The job acts as the requesting user: same tenant scope as their HTTP request.
        $context->runAs($user, function () use ($report, $user, $llm, $collector, $usage, $budget) {
            $report->update(['status' => 'running']);
            $facts = $collector->collect($report->crane, $report->period_start, $report->period_end);
            $hash = hash('sha256', json_encode([$facts, Prompts::VERSION, $llm->model()], JSON_THROW_ON_ERROR));

            // Same facts, prompt and model -> reuse the earlier result instead of paying again.
            $cached = ShiftReport::query()->where('input_hash', $hash)->where('status', 'ready')->whereKeyNot($report->id)->first();
            if ($cached !== null) {
                $report->update(['status' => 'ready', 'facts' => $facts, 'report' => $cached->report, 'input_hash' => $hash, 'model' => $cached->model, 'error' => null]);
                broadcast(new ReportReady($report));

                return;
            }

            $budget->ensureAvailable($user);
            $started = microtime(true);
            $response = $llm->complete(new LlmRequest(
                feature: 'report',
                system: Prompts::report(),
                messages: [['role' => 'user', 'content' => '<facts>'.json_encode($facts, JSON_THROW_ON_ERROR).'</facts>']],
                jsonSchema: ReportSchema::json(),
                effort: (string) config('ai.report_effort'),
                maxTokens: 4000,
            ));
            $usage->record('report', $response, $user, (int) ((microtime(true) - $started) * 1000));

            if ($response->stopReason !== 'end_turn') {
                throw new LlmException("Report generation stopped early ({$response->stopReason})", retryable: $response->stopReason === 'max_tokens');
            }
            $validated = ReportSchema::validate(json_decode($response->text(), true), $facts);

            $report->update(['status' => 'ready', 'facts' => $facts, 'report' => $validated, 'input_hash' => $hash, 'model' => $response->model, 'error' => null]);
            broadcast(new ReportReady($report));
        });
    }

    public function failed(Throwable $e): void
    {
        $message = $e instanceof ValidationException ? 'The generated report did not pass validation.' : mb_substr($e->getMessage(), 0, 250);
        $report = ShiftReport::query()->withoutGlobalScopes()->find($this->reportId);
        $report?->update(['status' => 'failed', 'error' => $message]);
        if ($report !== null) {
            broadcast(new ReportReady($report));
        }
    }
}
