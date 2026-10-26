<?php

namespace App\Http\Middleware;

use App\Auth\TenantContext;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Binds the authenticated user as the principal for tenant-scoped queries. */
class SetTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->context->set($user);
        }

        return $next($request);
    }
}
