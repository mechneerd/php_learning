<?php

namespace App\Enums;

enum NoteKind: string
{
    case Note = 'note';
    case Highlight = 'highlight';
    case Bookmark = 'bookmark';
    case Important = 'important';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
