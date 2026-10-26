<?php

namespace App\Http\Middleware;

use App\Auth\ForbiddenScope;
use App\Domain\Scope;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware: `scope:alarms:ack`. Checks effective scopes (role ∩ token abilities). */
class RequireScope
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $user = $request->user();
        foreach ($scopes as $value) {
            $scope = Scope::from($value);
            if (! $user instanceof User || ! $user->hasScope($scope)) {
                throw new ForbiddenScope($scope);
            }
        }

        return $next($request);
    }
}
