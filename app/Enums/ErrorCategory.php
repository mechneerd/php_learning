<?php

namespace App\Enums;

enum ErrorCategory: string
{
    case Parse = 'parse';
    case Type = 'type';
    case Undefined = 'undefined';
    case Method = 'method';
    case Fatal = 'fatal';
    case Exception = 'exception';
    case Runtime = 'runtime';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Parse => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
            self::Type => 'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300',
            self::Undefined => 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
            self::Method => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300',
            self::Fatal => 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
            self::Exception => 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300',
            self::Runtime => 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
        };
    }
}
