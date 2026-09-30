<?php

namespace App\Services\Learning;

/**
 * Result of evaluating a stage's gate_rules: every check with have/need
 * values so the UI can render pass/fail chips.
 */
final readonly class GateResult
{
    /**
     * @param  list<GateCheck>  $checks
     */
    public function __construct(public array $checks) {}

    public function passed(): bool
    {
        foreach ($this->checks as $check) {
            if (! $check->passed) {
                return false;
            }
        }

        return true;
    }

    /**
     * A stage with no rules is not gated at all.
     */
    public function isGated(): bool
    {
        return $this->checks !== [];
    }

    /**
     * @return list<GateCheck>
     */
    public function failed(): array
    {
        return array_values(array_filter($this->checks, fn (GateCheck $check) => ! $check->passed));
    }

    public function passedCount(): int
    {
        return count($this->checks) - count($this->failed());
    }
}
