<?php

namespace App\Mcp;

use App\Ai\Tools\ToolRegistry;
use App\Auth\TenantContext;
use App\Models\User;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('CraneWatch')]
#[Version('1.0.0')]
#[Instructions('Live tower crane monitoring data: cranes, telemetry, alarms, hourly stats and the crane documentation library. Results are limited to what the signed-in token may see. Cite documentation passages by their citation id.')]
class CraneWatchServer extends Server
{
    protected function boot(): void
    {
        // Advertise only the tools this token may call (same rule as the in-app assistant).
        $user = request()->user() ?? auth()->user();
        if ($user === null && app()->runningInConsole() && ($email = config('ai.mcp_local_user'))) {
            // stdio transport has no HTTP auth: act as the configured local user.
            $user = User::query()->where('email', $email)->firstOrFail();
            auth()->setUser($user);
            app(TenantContext::class)->set($user);
        }
        $registry = app(ToolRegistry::class);
        $tools = $user instanceof User ? $registry->for($user) : $registry->all();
        $this->tools = array_map(fn ($t) => new RegistryTool($t), $tools);
    }
}
