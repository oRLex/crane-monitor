<?php

namespace App\Domain;

/** What the code checks. Roles are just bundles of these; Sanctum token abilities use the same strings. */
enum Scope: string
{
    case CranesRead = 'cranes:read';
    case TelemetryRead = 'telemetry:read';
    case VideoRead = 'video:read';
    case AlarmsRead = 'alarms:read';
    case AlarmsAck = 'alarms:ack';
    case DocsRead = 'docs:read';
    case DocsWrite = 'docs:write';
    case ReportsWrite = 'reports:write';
    case AiUse = 'ai:use';
    case AiAdmin = 'ai:admin';
    case TenantsAll = 'tenants:all';

    /**
     * @param  array<Scope>  $scopes
     * @return list<string>
     */
    public static function values(array $scopes): array
    {
        return array_values(array_map(fn (self $s) => $s->value, $scopes));
    }
}
