<?php

namespace App\Domain;

enum Role: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case CustomerManager = 'customer_manager';
    case CustomerViewer = 'customer_viewer';

    /**
     * Role -> scopes. Tenant isolation is a separate axis: every role without
     * `tenants:all` is restricted to its own tenant's data.
     *
     * @return list<Scope>
     */
    public function scopes(): array
    {
        $read = [Scope::CranesRead, Scope::TelemetryRead, Scope::AlarmsRead, Scope::DocsRead, Scope::AiUse];

        return match ($this) {
            self::Admin => [Scope::TenantsAll, ...$read, Scope::VideoRead, Scope::AlarmsAck, Scope::DocsWrite, Scope::ReportsWrite, Scope::AiAdmin],
            self::Operator => [Scope::TenantsAll, ...$read, Scope::VideoRead, Scope::AlarmsAck, Scope::ReportsWrite],
            self::CustomerManager => [...$read, Scope::VideoRead, Scope::AlarmsAck, Scope::DocsWrite, Scope::ReportsWrite],
            self::CustomerViewer => $read,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Platform admin',
            self::Operator => 'Control-room operator',
            self::CustomerManager => 'Customer manager',
            self::CustomerViewer => 'Customer viewer',
        };
    }
}
