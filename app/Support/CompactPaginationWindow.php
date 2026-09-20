<?php

namespace App\Support;

final class CompactPaginationWindow
{
    public const EDGE_COUNT = 3;

    /**
     * @return list<int|string>
     */
    public static function pages(int $currentPage, int $lastPage, int $edgeCount = self::EDGE_COUNT): array
    {
        if ($lastPage < 1) {
            return [];
        }

        $edgeCount = max(1, $edgeCount);
        $currentPage = max(1, min($currentPage, $lastPage));

        if ($lastPage <= ($edgeCount * 2) + 1) {
            return range(1, $lastPage);
        }

        $first = range(1, $edgeCount);
        $last = range($lastPage - $edgeCount + 1, $lastPage);
        $middle = [];

        if ($currentPage > $edgeCount && $currentPage < $lastPage - $edgeCount + 1) {
            $middle = [$currentPage];
        }

        return self::mergeChunks([$first, $middle, $last]);
    }

    /**
     * @param  list<list<int>>  $chunks
     * @return list<int|string>
     */
    private static function mergeChunks(array $chunks): array
    {
        $pages = [];
        $seen = [];

        foreach ($chunks as $chunk) {
            $chunk = array_values(array_filter(
                $chunk,
                fn (int $page): bool => ! isset($seen[$page]),
            ));

            if ($chunk === []) {
                continue;
            }

            if ($pages !== []) {
                $previous = (int) $pages[array_key_last($pages)];

                if ($chunk[0] > $previous + 1) {
                    $pages[] = '...';
                }
            }

            foreach ($chunk as $page) {
                $pages[] = $page;
                $seen[$page] = true;
            }
        }

        return $pages;
    }
}
