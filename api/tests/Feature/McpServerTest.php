<?php

namespace Tests\Feature;

use Tests\TestCase;

class McpServerTest extends TestCase
{
    private function rpc(string $token, string $method, array $params = []): array
    {
        return $this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params], [
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2025-06-18',
        ])->assertOk()->json();
    }

    public function test_requires_a_token(): void
    {
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    }

    public function test_tools_and_data_follow_the_token(): void
    {
        $viewer = $this->tokenFor(self::VIEWER);
        $this->app['auth']->forgetGuards();

        $tools = array_column($this->rpc($viewer, 'tools/list')['result']['tools'], 'name');
        $this->assertContains('list_cranes', $tools);
        $this->assertNotContains('acknowledge_alarm', $tools);

        $this->app['auth']->forgetGuards();
        $result = $this->rpc($viewer, 'tools/call', ['name' => 'list_cranes', 'arguments' => (object) []])['result'];
        $this->assertSame(['c-201', 'c-202'], array_column($result['structuredContent']['cranes'], 'id'));

        $this->app['auth']->forgetGuards();
        $foreign = $this->rpc($viewer, 'tools/call', ['name' => 'get_crane_status', 'arguments' => ['crane_id' => 'c-101']])['result'];
        $this->assertTrue($foreign['isError']);
    }

    public function test_a_read_only_token_cannot_acknowledge_even_for_an_admin(): void
    {
        $token = $this->tokenFor(self::ADMIN, ['tenants:all', 'cranes:read', 'telemetry:read', 'alarms:read', 'docs:read']);
        $this->app['auth']->forgetGuards();

        $tools = array_column($this->rpc($token, 'tools/list')['result']['tools'], 'name');
        $this->assertNotContains('acknowledge_alarm', $tools);
    }
}
