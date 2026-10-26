<?php

namespace App\Ai;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Daily token budget per tenant (platform roles share one bucket). Checked before every
 * LLM call, charged after it, so one runaway client can't spend the whole month's money.
 */
class TokenBudget
{
    public function __construct(private readonly int $dailyLimit) {}

    /** @throws BudgetExceeded */
    public function ensureAvailable(User $user): void
    {
        if ($this->used($user) >= $this->dailyLimit) {
            throw new BudgetExceeded($this->dailyLimit);
        }
    }

    public function consume(User $user, int $tokens): void
    {
        $key = $this->key($user);
        Cache::add($key, 0, now()->endOfDay());
        Cache::increment($key, $tokens);
    }

    public function used(User $user): int
    {
        return (int) Cache::get($this->key($user), 0);
    }

    public function limit(): int
    {
        return $this->dailyLimit;
    }

    private function key(User $user): string
    {
        return 'ai:budget:'.$user->budgetKey().':'.now()->toDateString();
    }
}
