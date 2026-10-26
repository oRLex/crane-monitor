<?php

namespace App\Http\Controllers;

use App\Domain\Scope;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController
{
    /**
     * Demo sign-in: pick a seeded user, get a Sanctum token whose abilities are the role's scopes.
     * Stands in for Entra ID / OIDC; the rest of the API only sees the token and the user.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string'],
            'name' => ['sometimes', 'string', 'max:60'],
        ]);
        $user = User::query()->where('email', $data['email'])->first();
        if ($user === null) {
            throw ValidationException::withMessages(['email' => 'Unknown user']);
        }

        // A client may ask for a narrower token (e.g. a read-only MCP token), never a wider one.
        $abilities = Scope::values($user->role->scopes());
        if (isset($data['abilities'])) {
            $abilities = array_values(array_intersect($abilities, $data['abilities']));
        }
        $token = $user->createToken($data['name'] ?? 'web', $abilities, now()->addHours(12));

        return response()->json(['token' => $token->plainTextToken, 'user' => $this->profile($user, $abilities)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['user' => $this->profile($user, Scope::values($user->scopes()))]);
    }

    public function users(): JsonResponse
    {
        // Public list for the demo sign-in picker.
        return response()->json(['users' => User::query()->with('tenant')->orderBy('id')->get()->map(fn (User $u) => [
            'email' => $u->email,
            'name' => $u->name,
            'role' => $u->role->value,
            'roleLabel' => $u->role->label(),
            'tenant' => $u->tenant?->name,
        ])]);
    }

    /**
     * @param  list<string>  $scopes
     * @return array<string, mixed>
     */
    private function profile(User $user, array $scopes): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'roleLabel' => $user->role->label(),
            'tenantId' => $user->tenant_id,
            'tenantName' => $user->tenant?->name,
            'scopes' => $scopes,
        ];
    }
}
