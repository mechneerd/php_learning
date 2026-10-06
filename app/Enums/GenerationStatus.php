<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
}
