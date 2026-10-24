<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Header action that downloads rows as a UTF-8 CSV. The BOM makes Excel read Persian text correctly.
 */
class CsvExport
{
    /** @param  Closure(): iterable<array<string, mixed>>  $rows */
    public static function action(string $name, Closure $rows): Action
    {
        return Action::make('exportCsv')
            ->label(__('admin.common.export_csv'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(fn (): StreamedResponse => self::download($name.'-'.now()->format('Ymd-His').'.csv', $rows()));
    }

    /** @param  iterable<array<string, mixed>>  $rows */
    public static function download(string $filename, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            $headerWritten = false;

            foreach ($rows as $row) {
                if (! $headerWritten) {
                    fputcsv($handle, array_keys($row), escape: '');
                    $headerWritten = true;
                }

                fputcsv($handle, array_map(
                    fn (mixed $value): string => $value instanceof \BackedEnum ? (string) $value->value : (string) ($value ?? ''),
                    array_values($row),
                ), escape: '');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
