<?php

namespace Tests\Feature;

use App\Domain\AlarmKind;
use App\Domain\Severity;
use App\Models\Alarm;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    /** @return array<string, array{string, int, bool}> */
    public static function roleMatrix(): array
    {
        return [
            'admin sees all, with video' => [self::ADMIN, 5, true],
            'operator sees all, with video' => [self::OPERATOR, 5, true],
            'BudInvest manager sees own 3, with video' => [self::MANAGER, 3, true],
            'Skyline viewer sees own 2, no video' => [self::VIEWER, 2, false],
        ];
    }

    #[DataProvider('roleMatrix')]
    public function test_crane_list_follows_role_and_tenant(string $email, int $count, bool $video): void
    {
        $cranes = $this->getAs($email, '/api/cranes')->assertOk()->json('data');

        $this->assertCount($count, $cranes);
        $withStream = array_filter($cranes, fn ($c) => $c['streamUrl'] !== null);
        $this->assertSame($video, $withStream !== []);
    }

    public function test_other_tenants_crane_is_404_not_403(): void
    {
        $this->getAs(self::VIEWER, '/api/cranes/c-101')->assertNotFound();
        $this->getAs(self::VIEWER, '/api/cranes/c-101/telemetry')->assertNotFound();
        $this->getAs(self::VIEWER, '/api/cranes/c-201')->assertOk()->assertJsonPath('data.streamUrl', null);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/cranes')->assertUnauthorized();
    }

    public function test_viewer_cannot_acknowledge(): void
    {
        $alarm = $this->alarm('c-201');

        $this->as(self::VIEWER)->postJson("/api/alarms/{$alarm->id}/ack")->assertForbidden();
        $this->assertNull($alarm->fresh()?->acknowledged_at);
    }

    public function test_manager_acknowledges_own_alarm_but_not_another_tenants(): void
    {
        $own = $this->alarm('c-101');
        $foreign = $this->alarm('c-201');

        $this->as(self::MANAGER)->postJson("/api/alarms/{$own->id}/ack")
            ->assertOk()->assertJsonPath('alarm.acknowledgedBy', 'Iryna (BudInvest)');
        $this->as(self::MANAGER)->postJson("/api/alarms/{$foreign->id}/ack")->assertNotFound();
    }

    public function test_narrower_token_cannot_exceed_its_abilities(): void
    {
        $alarm = $this->alarm('c-101');

        // An admin-issued read-only token (e.g. for an MCP client) cannot ack.
        $this->as(self::ADMIN, ['cranes:read', 'alarms:read', 'tenants:all'])
            ->postJson("/api/alarms/{$alarm->id}/ack")->assertForbidden();
        // ...and asking for abilities the role doesn't have grants nothing extra.
        $token = $this->postJson('/api/auth/login', ['email' => self::VIEWER, 'abilities' => ['alarms:ack', 'cranes:read']])->json('user.scopes');
        $this->assertSame(['cranes:read'], $token);
    }

    public function test_alarm_list_is_tenant_scoped(): void
    {
        $this->alarm('c-101');
        $this->alarm('c-201');

        $ids = array_column($this->getAs(self::VIEWER, '/api/alarms')->assertOk()->json('alarms'), 'craneId');
        $this->assertSame(['c-201'], array_values(array_unique($ids)));
    }

    public function test_broadcast_channel_authorization(): void
    {
        // The null broadcaster authorizes everything; use the real (Pusher-protocol) one, which signs offline.
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => 'a']);
        require base_path('routes/channels.php'); // channels register on the default broadcaster
        $this->as(self::VIEWER)->postJson('/api/broadcasting/auth', ['channel_name' => 'private-tenant.t-skyline', 'socket_id' => '1.1'])->assertOk();
        $this->as(self::VIEWER)->postJson('/api/broadcasting/auth', ['channel_name' => 'private-tenant.t-budinvest', 'socket_id' => '1.1'])->assertForbidden();
        $this->as(self::VIEWER)->postJson('/api/broadcasting/auth', ['channel_name' => 'private-platform', 'socket_id' => '1.1'])->assertForbidden();
        $this->as(self::OPERATOR)->postJson('/api/broadcasting/auth', ['channel_name' => 'private-platform', 'socket_id' => '1.1'])->assertOk();
    }

    private function alarm(string $craneId): Alarm
    {
        return Alarm::query()->create([
            'crane_id' => $craneId,
            'tenant_id' => str_starts_with($craneId, 'c-1') ? 't-budinvest' : 't-skyline',
            'kind' => AlarmKind::Wind,
            'severity' => Severity::Warning,
            'message' => 'Wind 15 m/s (limit 20 m/s)',
            'raised_at' => now(),
        ]);
    }
}
