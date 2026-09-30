<?php

namespace App\Services\Learning;

use App\Enums\HintGrade;

/**
 * SM-2 lite scheduler (docs/04 §7): first exposure -> 1 day, then
 * hard x1.2, ok x2.5, easy x4; lapses reset to 1 day. Ease starts at 2.50
 * and is stored for future tuning.
 */
final class CardScheduler
{
    public const FIRST_INTERVAL = 1;

    public const INITIAL_EASE = 2.50;

    public function schedule(int $currentIntervalDays, HintGrade $grade): int
    {
        if ($currentIntervalDays <= 0) {
            return self::FIRST_INTERVAL;
        }

        $next = match ($grade) {
            HintGrade::Hard => $currentIntervalDays * 1.2,
            HintGrade::Ok => $currentIntervalDays * 2.5,
            HintGrade::Easy => $currentIntervalDays * 4,
        };

        return max(1, (int) round($next));
    }

    /**
     * A failed recall resets the card to one day.
     */
    public function lapse(): int
    {
        return self::FIRST_INTERVAL;
    }
}
