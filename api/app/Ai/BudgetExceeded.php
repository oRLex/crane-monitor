<?php

namespace App\Ai;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class BudgetExceeded extends TooManyRequestsHttpException
{
    public function __construct(int $limit)
    {
        parent::__construct(
            retryAfter: (int) now()->diffInSeconds(now()->endOfDay()),
            message: "Daily AI budget of {$limit} tokens for your organisation is used up. It resets at midnight UTC.",
        );
    }
}
