<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Thrown when a user's daily token budget is spent; the caller surfaces a
 * friendly notice instead of calling the provider.
 */
final class BudgetExceeded extends RuntimeException
{
    public function __construct(public readonly int $used, public readonly int $budget)
    {
        parent::__construct("AI daily token budget exceeded ({$used}/{$budget}).");
    }
}
