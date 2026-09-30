<?php

namespace App\Services\Learning;

final readonly class TestResult
{
    public function __construct(
        public int $ord,
        public string $type,
        public string $status, // pass | fail | pending
        public string $message,
        public int $weight,
    ) {}

    /** @return array{ord: int, type: string, status: string, message: string, weight: int} */
    public function toArray(): array
    {
        return [
            'ord' => $this->ord,
            'type' => $this->type,
            'status' => $this->status,
            'message' => $this->message,
            'weight' => $this->weight,
        ];
    }
}
