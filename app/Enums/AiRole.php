<?php

namespace App\Enums;

enum AiRole: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
}
