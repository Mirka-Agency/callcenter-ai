<?php

namespace Tests\Unit;

use App\Support\CompactPaginationWindow;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompactPaginationWindowTest extends TestCase
{
    #[DataProvider('pageWindows')]
    public function test_it_shows_first_pages_ellipsis_and_last_pages(
        int $currentPage,
        int $lastPage,
        array $expected,
    ): void {
        $this->assertSame(
            $expected,
            CompactPaginationWindow::pages($currentPage, $lastPage),
        );
    }

    /**
     * @return array<string, array{int, int, list<int|string>}>
     */
    public static function pageWindows(): array
    {
        return [
            'no pages' => [1, 0, []],
            'single page' => [1, 1, [1]],
            'few pages stay complete' => [2, 7, [1, 2, 3, 4, 5, 6, 7]],
            'first page of many' => [1, 12, [1, 2, 3, '...', 10, 11, 12]],
            'last page of many' => [12, 12, [1, 2, 3, '...', 10, 11, 12]],
            'middle page of many' => [6, 12, [1, 2, 3, '...', 6, '...', 10, 11, 12]],
            'page adjacent to first edge' => [4, 12, [1, 2, 3, 4, '...', 10, 11, 12]],
            'page adjacent to last edge' => [9, 12, [1, 2, 3, '...', 9, 10, 11, 12]],
        ];
    }
}
