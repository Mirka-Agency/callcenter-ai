<?php

namespace App\Application\Voip\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Missed calls read straight from the PBX CDR database, with the same rules as Issabel's
 * "Missed Calls" report: one entry per caller/callee pair whose failed attempts were not
 * followed by a connected call between the two numbers, in either direction.
 */
class PbxMissedCallsCounter
{
    public const CONNECTION = 'pbx_cdr';

    public function enabled(): bool
    {
        return filled(config('database.connections.'.self::CONNECTION.'.host'));
    }

    /** Null when the PBX database is not configured or cannot be read. */
    public function count(CarbonInterface $from, CarbonInterface $to): ?int
    {
        if (! $this->enabled()) {
            return null;
        }

        // The CDR stores calldate in the PBX's local time without an offset.
        $timezone = (string) config('voip.pbx_cdr.timezone');
        $start = $from->copy()->setTimezone($timezone)->format('Y-m-d H:i:s');
        $end = $to->copy()->setTimezone($timezone)->format('Y-m-d H:i:s');

        $cached = Cache::get($this->cacheKey($start, $end));
        if (is_int($cached)) {
            return $cached;
        }

        try {
            $count = $this->countBetween($start, $end);
        } catch (\Throwable $e) {
            Log::warning('pbx_missed_calls_unavailable', ['error' => $e->getMessage()]);

            return null;
        }

        Cache::put($this->cacheKey($start, $end), $count, (int) config('voip.pbx_cdr.cache_seconds', 60));

        return $count;
    }

    private function countBetween(string $start, string $end): int
    {
        $rows = DB::connection(self::CONNECTION)->table('cdr')
            ->selectRaw("calldate, IF(TRIM(src) = '', 'UNKNOWN', TRIM(src)) AS src, IF(TRIM(dst) = '', 'UNKNOWN', TRIM(dst)) AS dst, UCASE(TRIM(lastapp)) AS lastapp, UCASE(TRIM(lastdata)) AS lastdata, billsec, UCASE(TRIM(disposition)) AS disposition")
            ->whereBetween('calldate', [$start, $end])
            ->whereIn('lastapp', ['Dial', 'Hangup', 'Voicemail'])
            ->distinct()
            ->get();

        return $this->countFromRows($rows);
    }

    /**
     * @param  iterable<object{calldate: string, src: string, dst: string, lastapp: string, billsec: int|string, disposition: string}>  $rows
     */
    public function countFromRows(iterable $rows): int
    {
        $rows = is_array($rows) ? $rows : iterator_to_array($rows, false);
        $lastConnected = [];
        foreach ($rows as $row) {
            if ($this->connected($row)) {
                $pair = $this->pairKey($row->src, $row->dst);
                $lastConnected[$pair] = max($lastConnected[$pair] ?? '', (string) $row->calldate);
            }
        }

        $missed = [];
        foreach ($rows as $row) {
            if ($this->connected($row)) {
                continue;
            }

            $connectedAt = $lastConnected[$this->pairKey($row->src, $row->dst)] ?? null;
            if ($connectedAt !== null && $connectedAt >= (string) $row->calldate) {
                continue;
            }

            $missed[$row->src."\0".$row->dst] = true;
        }

        return count($missed);
    }

    private function connected(object $row): bool
    {
        return $row->lastapp === 'DIAL' && $row->disposition === 'ANSWERED' && (int) $row->billsec > 0;
    }

    private function pairKey(string $a, string $b): string
    {
        return $a < $b ? $a."\0".$b : $b."\0".$a;
    }

    private function cacheKey(string $start, string $end): string
    {
        return 'pbx-missed-calls:'.$start.':'.$end;
    }
}
