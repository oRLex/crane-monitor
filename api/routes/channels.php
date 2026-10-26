<?php

use App\Domain\Scope;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Same rules as the REST API: a tenant's channel for its own users (and platform roles),
// the platform channel (all tenants) only for `tenants:all`.
Broadcast::channel('tenant.{tenantId}', fn (User $user, string $tenantId) => $user->hasScope(Scope::TelemetryRead) && $user->canAccessTenant($tenantId), ['guards' => ['sanctum']]);

Broadcast::channel('platform', fn (User $user) => $user->hasScope(Scope::TelemetryRead) && $user->hasScope(Scope::TenantsAll), ['guards' => ['sanctum']]);
