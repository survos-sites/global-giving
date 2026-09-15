<?php

declare(strict_types=1);

namespace App\Lab;

final class Ranking
{
    /** Equal-weight reciprocal rank fusion; raw engine scores are deliberately ignored. */
    public static function fuse(array $keyword, array $vector, int $limit = 10): array
    {
        $documents = []; $scores = [];
        foreach ([$keyword, $vector] as $list) {
            $seen = [];
            foreach ($list as $rank => $hit) {
                $id = (string) $hit['id'];
                if (isset($seen[$id])) { continue; }
                $seen[$id] = true; $documents[$id] = $hit;
                $scores[$id] = ($scores[$id] ?? 0.0) + 1 / (60 + $rank + 1);
            }
        }
        uksort($scores, static fn ($a, $b) => ($scores[$b] <=> $scores[$a]) ?: strcmp((string) $a, (string) $b));
        return array_map(static fn ($id) => $documents[$id], array_slice(array_keys($scores), 0, $limit));
    }
}
