<?php

namespace Tests\Feature;

use App\Ai\Llm\FakeLlmClient;
use App\Domain\AlarmKind;
use App\Domain\Severity;
use App\Jobs\GenerateShiftReport;
use App\Models\Alarm;
use App\Models\ShiftReport;
use App\Models\TelemetryHourly;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShiftReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeLlmClient::reset();
        TelemetryHourly::query()->create([
            'crane_id' => 'c-101', 'hour' => now()->subHours(2)->startOfHour(), 'max_load_t' => 6.1, 'max_moment_pct' => 103.2,
            'max_wind_ms' => 15.4, 'wind_sum' => 3000, 'samples' => 360, 'operating_s' => 2400, 'offline_s' => 0, 'lifts' => 14,
        ]);
        Alarm::query()->create([
            'crane_id' => 'c-101', 'tenant_id' => 't-budinvest', 'kind' => AlarmKind::Overload, 'severity' => Severity::Critical,
            'message' => 'Load moment 103.2% (3.4 t at 52 m)', 'raised_at' => now()->subHours(2)->addMinutes(10), 'cleared_at' => now()->subHours(2)->addMinutes(11),
        ]);
    }

    public function test_report_is_generated_validated_and_reused(): void
    {
        $first = $this->as(self::MANAGER)->postJson('/api/reports', ['crane_id' => 'c-101', 'hours' => 8])->assertStatus(202)->json('report.id');

        $report = ShiftReport::query()->findOrFail($first);
        $this->assertSame('ready', $report->status);
        $this->assertSame('medium', $report->report['risk_level']);
        $this->assertSame(14, $report->facts['totals']['lifts'], 'numbers come from SQL, not the model');
        $this->assertCount(1, FakeLlmClient::$requests);

        // Same facts + prompt + model -> cached result, no second LLM call.
        $this->as(self::MANAGER)->postJson('/api/reports', ['crane_id' => 'c-101', 'hours' => 8])->assertStatus(202);
        $this->assertCount(1, FakeLlmClient::$requests);
    }

    public function test_invented_safety_events_fail_validation(): void
    {
        $bad = json_encode([
            'summary' => 'A calm shift with nothing unusual to report at all.', 'risk_level' => 'low', 'highlights' => [],
            'safety_events' => [['time' => '2020-01-01T00:00:00+00:00', 'kind' => 'wind', 'severity' => 'critical', 'description' => 'Storm']],
            'recommendations' => [],
        ]);
        FakeLlmClient::script(FakeLlmClient::text((string) $bad));
        Queue::fake();

        $this->as(self::MANAGER)->postJson('/api/reports', ['crane_id' => 'c-201', 'hours' => 8])->assertNotFound();
        $id = $this->as(self::OPERATOR)->postJson('/api/reports', ['crane_id' => 'c-101', 'hours' => 8])->assertStatus(202)->json('report.id');

        $job = new GenerateShiftReport($id);
        try {
            app()->call([$job, 'handle']);
            $this->fail('expected validation to reject the invented event');
        } catch (ValidationException $e) {
            $job->failed($e); // what the queue does after the last attempt
        }

        $report = ShiftReport::query()->findOrFail($id);
        $this->assertSame('failed', $report->status);
        $this->assertSame('The generated report did not pass validation.', $report->error);
    }

    public function test_viewer_cannot_request_reports_but_can_read_own_tenants(): void
    {
        $this->as(self::VIEWER)->postJson('/api/reports', ['crane_id' => 'c-201'])->assertForbidden();
        $this->getAs(self::VIEWER, '/api/reports')->assertOk();
    }
}
