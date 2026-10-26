<?php

namespace App\Auth;

use App\Domain\Scope;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ForbiddenScope extends AccessDeniedHttpException
{
    public function __construct(public readonly Scope $scope)
    {
        parent::__construct("Missing scope: {$scope->value}");
    }
}
