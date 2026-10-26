<?php

namespace App\Auth;

use App\Domain\Scope;
use App\Models\User;
use Closure;

/**
 * The principal whose tenant every tenant-owned query is restricted to.
 *
 * Set by middleware for HTTP/MCP requests and explicitly (runAs) by jobs that act on
 * behalf of a user. With no principal (console, system jobs) queries are unrestricted.
 */
class TenantContext
{
    private ?User $principal = null;

    public function set(?User $principal): void
    {
        $this->principal = $principal;
    }

    public function principal(): ?User
    {
        return $this->principal;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAs(User $principal, Closure $callback): mixed
    {
        $previous = $this->principal;
        $this->principal = $principal;
        try {
            return $callback();
        } finally {
            $this->principal = $previous;
        }
    }

    /** null = unrestricted, otherwise the single tenant id (or '' when the principal has none). */
    public function restrictedTenant(): ?string
    {
        if ($this->principal === null || $this->principal->hasScope(Scope::TenantsAll)) {
            return null;
        }

        return $this->principal->tenant_id ?? '';
    }
}
