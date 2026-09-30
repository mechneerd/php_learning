<?php

namespace App\Enums;

enum ImportJobStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
