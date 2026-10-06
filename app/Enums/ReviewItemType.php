<?php

namespace App\Enums;

enum ReviewItemType: string
{
    case Card = 'card';
    case Concept = 'concept';
    case Exercise = 'exercise';
    case Error = 'error';
    case Lesson = 'lesson';
    case Interview = 'interview';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
