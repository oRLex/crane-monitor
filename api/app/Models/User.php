<?php

namespace App\Models;

use App\Domain\Role;
use App\Domain\Scope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\Contracts\HasAbilities;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property string|null $tenant_id
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory;

    protected $fillable = ['name', 'email', 'role', 'tenant_id', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['role' => Role::class, 'password' => 'hashed'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Effective scopes = what the role grants ∩ what the current token was issued with.
     * A narrower token (e.g. a read-only MCP token) can only reduce access, never extend it.
     *
     * @return list<Scope>
     */
    public function scopes(): array
    {
        // Personal access tokens check their abilities; a TransientToken (first-party session,
        // actingAs in tests) allows everything, so the role alone decides.
        // No token at all (queued job / console acting for the user): the role decides.
        $token = $this->currentAccessToken();

        return array_values(array_filter($this->role->scopes(), fn (Scope $s) => $token === null || $token->can($s->value)));
    }

    /** Sanctum documents this as non-null, but it is null outside an authenticated request. */
    public function currentAccessToken(): ?HasAbilities
    {
        return $this->accessToken;
    }

    public function hasScope(Scope $scope): bool
    {
        return in_array($scope, $this->scopes(), true);
    }

    public function canAccessTenant(?string $tenantId): bool
    {
        if ($this->hasScope(Scope::TenantsAll)) {
            return true;
        }

        return $this->tenant_id !== null && $this->tenant_id === $tenantId;
    }

    /** Bucket used for rate limits and AI budgets. */
    public function budgetKey(): string
    {
        return $this->tenant_id ?? 'platform';
    }
}
