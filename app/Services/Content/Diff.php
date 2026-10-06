<?php

namespace App\Services\Content;

/**
 * Minimal line-based LCS diff for the admin review screen: enough to see
 * "what changed" between the last approved version and the AI draft
 * without pulling in a diff library (docs/08 /admin/review).
 */
final class Diff
{
    /**
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return list<array{op: string, line: string}>
     */
    public function lines(array $old, array $new): array
    {
        $a = $old;
        $b = $new;
        $n = count($a);
        $m = count($b);

        // LCS table.
        $table = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $table[$i][$j] = $a[$i] === $b[$j]
                    ? $table[$i + 1][$j + 1] + 1
                    : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        $result = [];
        $i = 0;
        $j = 0;

        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $result[] = ['op' => '=', 'line' => $a[$i]];
                $i++;
                $j++;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $result[] = ['op' => '-', 'line' => $a[$i]];
                $i++;
            } else {
                $result[] = ['op' => '+', 'line' => $b[$j]];
                $j++;
            }
        }

        while ($i < $n) {
            $result[] = ['op' => '-', 'line' => $a[$i]];
            $i++;
        }

        while ($j < $m) {
            $result[] = ['op' => '+', 'line' => $b[$j]];
            $j++;
        }

        return $result;
    }

    /**
     * One-line summary stored on content_versions rows.
     *
     * @param  list<string>|null  $old
     * @param  list<string>  $new
     */
    public function summary(?array $old, array $new): string
    {
        if ($old === null) {
            return 'first version ('.count($new).' lines)';
        }

        $ops = $this->lines($old, $new);
        $added = count(array_filter($ops, static fn (array $o): bool => $o['op'] === '+'));
        $removed = count(array_filter($ops, static fn (array $o): bool => $o['op'] === '-'));

        return "{$added} added, {$removed} removed";
    }
}
