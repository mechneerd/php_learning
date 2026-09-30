<?php

namespace App\Enums;

enum PdfDocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Extracting = 'extracting';
    case Extracted = 'extracted';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
