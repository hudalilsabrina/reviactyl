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

        $hunks = [];
        $additions = 0;
        $deletions = 0;

        // Simple line-by-line diff using LCS approach
        $diff = $this->lineDiff($oldLines, $newLines);

        $hunk = null;
        $oldLine = 1;
        $newLine = 1;

        foreach ($diff as $entry) {
            $type = $entry[0];
            $line = $entry[1];

            if ($hunk === null) {
                $hunk = [
                    'old_start' => $oldLine,
                    'old_lines' => 0,
                    'new_start' => $newLine,
                    'new_lines' => 0,
                    'lines' => [],
                ];
            }

            if ($type === ' ') {
                $hunk['lines'][] = ' '.$line;
                $hunk['old_lines']++;
                $hunk['new_lines']++;
                $oldLine++;
                $newLine++;
            } elseif ($type === '-') {
                $hunk['lines'][] = '-'.$line;
                $hunk['old_lines']++;
                $deletions++;
                $oldLine++;
            } elseif ($type === '+') {
                $hunk['lines'][] = '+'.$line;
                $hunk['new_lines']++;
                $additions++;
                $newLine++;
            }

            // Close hunk after context gap
            if ($type !== ' ' && count($hunk['lines']) >= 6) {
                $hunks[] = $hunk;
                $hunk = null;
            }
        }

        if ($hunk !== null && ! empty($hunk['lines'])) {
            $hunks[] = $hunk;
        }

        return [
            'additions' => $additions,
            'deletions' => $deletions,
            'hunks' => $hunks,
        ];
    }

    /**
     * Simple line diff using Myers-like algorithm (simplified).
     *
     * @return array<array{0: string, 1: string}>
     */
    private function lineDiff(array $old, array $new): array
    {
        $result = [];

        $oldCount = count($old);
        $newCount = count($new);
        $max = $oldCount + $newCount;

        // Use a simplified LCS-based approach
        $lcs = $this->lcs($old, $new);

        $oldIdx = 0;
        $newIdx = 0;
        $lcsIdx = 0;

        while ($oldIdx < $oldCount || $newIdx < $newCount) {
            if ($lcsIdx < count($lcs) && $oldIdx < $oldCount && $newIdx < $newCount
                && $old[$oldIdx] === $lcs[$lcsIdx] && $new[$newIdx] === $lcs[$lcsIdx]) {
                $result[] = [' ', $old[$oldIdx]];
                $oldIdx++;
                $newIdx++;
                $lcsIdx++;
            } elseif ($oldIdx < $oldCount && ($lcsIdx >= count($lcs) || $old[$oldIdx] !== $lcs[$lcsIdx])) {
                $result[] = ['-', $old[$oldIdx]];
                $oldIdx++;
            } elseif ($newIdx < $newCount) {
                $result[] = ['+', $new[$newIdx]];
                $newIdx++;
            }
        }

        return $result;
    }

    /**
     * Find longest common subsequence of lines.
     */
    private function lcs(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b);

        // Optimization: skip if arrays are very large
        if ($m * $n > 1000000) {
            return [];
        }

        $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                if ($a[$i - 1] === $b[$j - 1]) {
                    $dp[$i][$j] = $dp[$i - 1][$j - 1] + 1;
                } else {
                    $dp[$i][$j] = max($dp[$i - 1][$j], $dp[$i][$j - 1]);
                }
            }
        }

        // Backtrack to find the LCS
        $result = [];
        $i = $m;
        $j = $n;

        while ($i > 0 && $j > 0) {
            if ($a[$i - 1] === $b[$j - 1]) {
                $result[] = $a[$i - 1];
                $i--;
                $j--;
            } elseif ($dp[$i - 1][$j] > $dp[$i][$j - 1]) {
                $i--;
            } else {
                $j--;
            }
        }

        return array_reverse($result);
    }
}
