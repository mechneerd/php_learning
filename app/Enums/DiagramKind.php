<?php

namespace App\Enums;

enum DiagramKind: string
{
    case Flowchart = 'flowchart';
    case ClassDiagram = 'class';
    case Sequence = 'sequence';
    case State = 'state';
    case Er = 'er';
    case Gantt = 'gantt';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Flowchart => 'Flowchart',
            self::ClassDiagram => 'Class diagram',
            self::Sequence => 'Sequence diagram',
            self::State => 'State diagram',
            self::Er => 'ER diagram',
            self::Gantt => 'Gantt chart',
            self::Other => 'Diagram',
        };
    }
}
