<?php

namespace App\Models;

use App\Enums\ImportJobStatus;
use App\Enums\ImportJobType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $pdf_document_id
 * @property ImportJobType $type
 * @property ImportJobStatus $status
 * @property array<string, mixed>|null $payload
 * @property array<string, mixed>|null $result
 * @property int $attempts
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['pdf_document_id', 'type', 'status', 'payload', 'result', 'attempts', 'error', 'started_at', 'finished_at'])]
class ImportJob extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ImportJobType::class,
            'status' => ImportJobStatus::class,
            'payload' => 'array',
            'result' => 'array',
            'attempts' => 'integer',
        ];
    }

    /** @return BelongsTo<PdfDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PdfDocument::class, 'pdf_document_id');
    }

    public function markRunning(): void
    {
        $this->forceFill([
            'status' => ImportJobStatus::Running,
            'started_at' => now(),
            'attempts' => $this->attempts + 1,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function markDone(array $result): void
    {
        $this->forceFill([
            'status' => ImportJobStatus::Done,
            'result' => $result,
            'finished_at' => now(),
            'error' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => ImportJobStatus::Failed,
            'error' => $error,
            'finished_at' => now(),
        ])->save();
    }
}
