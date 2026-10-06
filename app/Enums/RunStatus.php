<?php

namespace App\Enums;

enum RunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Done = 'done';
    case Timeout = 'timeout';
    case Failed = 'failed';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Done => 'Finished',
            self::Timeout => 'Timed out',
            self::Failed => 'Failed',
            self::Blocked => 'Blocked',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Queued, self::Running => 'bg-sky-500/15 text-sky-400 ring-sky-500/30',
            self::Done => 'bg-emerald-500/15 text-emerald-400 ring-emerald-500/30',
            self::Timeout => 'bg-amber-500/15 text-amber-400 ring-amber-500/30',
            self::Failed => 'bg-rose-500/15 text-rose-400 ring-rose-500/30',
            self::Blocked => 'bg-zinc-500/15 text-zinc-400 ring-zinc-500/30',
        };
    }
}
