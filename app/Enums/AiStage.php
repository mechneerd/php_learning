<?php

namespace App\Enums;

enum AiStage: string
{
    case Lesson = 'lesson';
    case Exercise = 'exercise';
    case Quiz = 'quiz';
    case Cards = 'cards';
    case Diagram = 'diagram';
    case Outdated = 'outdated';
}
