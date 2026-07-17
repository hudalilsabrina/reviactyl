<?php

namespace App\Services\ConfigRevisions;

class DiffService
{
    /**
     * Compute a unified diff between two strings.
     *
     * @return array{additions: int, deletions: int, hunks: array}
     */
    public function compute(string $oldContent, string $newContent): array
    {
        $oldLines = explode("\n", $oldContent);
        $newLines = explode("\n", $newContent);

        $edits = $this->myersDiff($oldLines, $newLines);

        return $this->buildHunks($edits, $oldLines, $newLines);
    }

    /**
     * Myers diff algorithm — O(ND) time, O(N) space.
     * Returns edit script: array of [' '=>context, '-'=>delete, '+'=>insert] with line indices.
     *
     * @return array<array{type: string, old: int|null, new: int|null, text: string}>
     */
    private function myersDiff(array $old, array $new): array
    {
        $n = count($old);
        $m = count($new);
        $max = $n + $m;

        if ($max === 0) {
            return [];
        }

        // For very large files, fall back to simple line-by-line comparison
        if ($max > 50000) {
            return $this->simpleDiff($old, $new);
        }

        $offset = $max;
        $v = array_fill(0, 2 * $max + 1, 0);
        $trace = [];

        for ($d = 0; $d <= $max; $d++) {
            $trace[] = $v;

            for ($k = -$d; $k <= $d; $k += 2) {
                if ($k === -$d || ($k !== $d && $v[$k - 1 + $offset] < $v[$k + 1 + $offset])) {
                    $x = $v[$k + 1 + $offset];
                } else {
                    $x = $v[$k - 1 + $offset] + 1;
                }

                $y = $x - $k;

                while ($x < $n && $y < $m && $old[$x] === $new[$y]) {
                    $x++;
                    $y++;
                }

                $v[$k + $offset] = $x;

                if ($x >= $n && $y >= $m) {
                    return $this->backtrack($trace, $old, $new, $offset);
                }
            }
        }

        // Fallback (should never reach here)
        return $this->simpleDiff($old, $new);
    }

    /**
     * Backtrack through Myers trace to produce edit script.
     */
    private function backtrack(array $trace, array $old, array $new, int $offset): array
    {
        $edits = [];
        $x = count($old);
        $y = count($new);

        for ($d = count($trace) - 1; $d >= 0; $d--) {
            $v = $trace[$d];
            $k = $x - $y;

            if ($k === -$d || ($k !== $d && $v[$k - 1 + $offset] < $v[$k + 1 + $offset])) {
                $prevK = $k + 1;
            } else {
                $prevK = $k - 1;
            }

            $prevX = $v[$prevK + $offset];
            $prevY = $prevX - $prevK;

            // Diagonal (context lines)
            while ($x > $prevX && $y > $prevY) {
                $x--;
                $y--;
                $edits[] = ['type' => ' ', 'old' => $x, 'new' => $y, 'text' => $old[$x]];
            }

            if ($d > 0) {
                if ($x === $prevX) {
                    // Insert
                    $y--;
                    $edits[] = ['type' => '+', 'old' => null, 'new' => $y, 'text' => $new[$y]];
                } else {
                    // Delete
                    $x--;
                    $edits[] = ['type' => '-', 'old' => $x, 'new' => null, 'text' => $old[$x]];
                }
            }
        }

        return array_reverse($edits);
    }

    /**
     * Simple line-by-line diff fallback for very large files.
     */
    private function simpleDiff(array $old, array $new): array
    {
        $edits = [];
        $n = count($old);
        $m = count($new);
        $max = max($n, $m);

        // Hash lines for fast comparison
        $oldHashes = array_map('crc32', $old);
        $newHashes = array_map('crc32', $new);

        // Find common prefix
        $prefixLen = 0;
        while ($prefixLen < $n && $prefixLen < $m && $oldHashes[$prefixLen] === $newHashes[$prefixLen]) {
            $prefixLen++;
        }

        // Find common suffix
        $suffixLen = 0;
        while ($suffixLen < ($n - $prefixLen) && $suffixLen < ($m - $prefixLen)
            && $oldHashes[$n - 1 - $suffixLen] === $newHashes[$m - 1 - $suffixLen]) {
            $suffixLen++;
        }

        // Emit prefix context
        for ($i = 0; $i < $prefixLen; $i++) {
            $edits[] = ['type' => ' ', 'old' => $i, 'new' => $i, 'text' => $old[$i]];
        }

        $oldMid = array_slice($old, $prefixLen, $n - $prefixLen - $suffixLen);
        $newMid = array_slice($new, $prefixLen, $m - $prefixLen - $suffixLen);

        // Emit deletes and inserts for the middle section
        foreach ($oldMid as $i => $line) {
            $edits[] = ['type' => '-', 'old' => $prefixLen + $i, 'new' => null, 'text' => $line];
        }
        foreach ($newMid as $i => $line) {
            $edits[] = ['type' => '+', 'old' => null, 'new' => $prefixLen + $i, 'text' => $line];
        }

        // Emit suffix context
        for ($i = 0; $i < $suffixLen; $i++) {
            $oldIdx = $n - $suffixLen + $i;
            $newIdx = $m - $suffixLen + $i;
            $edits[] = ['type' => ' ', 'old' => $oldIdx, 'new' => $newIdx, 'text' => $old[$oldIdx]];
        }

        return $edits;
    }

    /**
     * Build unified diff hunks from edit script.
     *
     * @return array{additions: int, deletions: int, hunks: array}
     */
    private function buildHunks(array $edits, array $oldLines, array $newLines): array
    {
        if (empty($edits)) {
            return ['additions' => 0, 'deletions' => 0, 'hunks' => []];
        }

        $contextLines = 3;
        $hunks = [];
        $additions = 0;
        $deletions = 0;

        // Find ranges of non-context edits
        $changeRanges = [];
        $currentRange = null;

        foreach ($edits as $idx => $edit) {
            if ($edit['type'] !== ' ') {
                if ($currentRange === null) {
                    $currentRange = ['start' => $idx, 'end' => $idx];
                } else {
                    $currentRange['end'] = $idx;
                }
            } else {
                if ($currentRange !== null) {
                    $changeRanges[] = $currentRange;
                    $currentRange = null;
                }
            }
        }
        if ($currentRange !== null) {
            $changeRanges[] = $currentRange;
        }

        if (empty($changeRanges)) {
            return ['additions' => 0, 'deletions' => 0, 'hunks' => []];
        }

        // Merge overlapping ranges (with context padding)
        $mergedRanges = [];
        $first = $changeRanges[0];
        $currentStart = max(0, $first['start'] - $contextLines);
        $currentEnd = min(count($edits) - 1, $first['end'] + $contextLines);

        for ($i = 1; $i < count($changeRanges); $i++) {
            $range = $changeRanges[$i];
            $rangeStart = max(0, $range['start'] - $contextLines);

            if ($rangeStart <= $currentEnd + 1) {
                $currentEnd = min(count($edits) - 1, $range['end'] + $contextLines);
            } else {
                $mergedRanges[] = ['start' => $currentStart, 'end' => $currentEnd];
                $currentStart = $rangeStart;
                $currentEnd = min(count($edits) - 1, $range['end'] + $contextLines);
            }
        }
        $mergedRanges[] = ['start' => $currentStart, 'end' => $currentEnd];

        // Build hunks
        foreach ($mergedRanges as $range) {
            $hunkLines = [];
            $oldStart = null;
            $newStart = null;
            $oldCount = 0;
            $newCount = 0;

            for ($i = $range['start']; $i <= $range['end']; $i++) {
                $edit = $edits[$i];

                if ($oldStart === null && ($edit['type'] === ' ' || $edit['type'] === '-') && $edit['old'] !== null) {
                    $oldStart = $edit['old'] + 1;
                }
                if ($newStart === null && ($edit['type'] === ' ' || $edit['type'] === '+') && $edit['new'] !== null) {
                    $newStart = $edit['new'] + 1;
                }

                if ($edit['type'] === ' ') {
                    $hunkLines[] = ' '.$edit['text'];
                    $oldCount++;
                    $newCount++;
                } elseif ($edit['type'] === '-') {
                    $hunkLines[] = '-'.$edit['text'];
                    $oldCount++;
                    $deletions++;
                } elseif ($edit['type'] === '+') {
                    $hunkLines[] = '+'.$edit['text'];
                    $newCount++;
                    $additions++;
                }
            }

            if (! empty($hunkLines)) {
                $hunks[] = [
                    'old_start' => $oldStart ?? 1,
                    'old_lines' => $oldCount,
                    'new_start' => $newStart ?? 1,
                    'new_lines' => $newCount,
                    'lines' => $hunkLines,
                ];
            }
        }

        return [
            'additions' => $additions,
            'deletions' => $deletions,
            'hunks' => $hunks,
        ];
    }
}
