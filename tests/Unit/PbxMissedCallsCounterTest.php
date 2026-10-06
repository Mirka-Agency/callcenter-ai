<?php

namespace Tests\Unit;

use App\Application\Voip\Services\PbxMissedCallsCounter;
use Carbon\Carbon;
use Tests\TestCase;

class PbxMissedCallsCounterTest extends TestCase
{
    public function test_counts_one_entry_per_pair_that_was_never_reached_afterwards(): void
    {
        $rows = [
            $this->cdr('2026-10-05 09:00:00', '107', '909121111111', 'DIAL', 'NO ANSWER'),
            $this->cdr('2026-10-05 09:05:00', '107', '909121111111', 'DIAL', 'BUSY'),
            $this->cdr('2026-10-05 09:10:00', '111', '119', 'DIAL', 'NO ANSWER'),
            $this->cdr('2026-10-05 09:20:00', '119', '111', 'DIAL', 'ANSWERED', 30),
            $this->cdr('2026-10-05 10:00:00', '09120000000', '5002', 'HANGUP', 'NO ANSWER'),
            $this->cdr('2026-10-05 10:30:00', '120', '909122222222', 'DIAL', 'ANSWERED', 0),
        ];

        $count = app(PbxMissedCallsCounter::class)->countFromRows($rows);

        // 107→9091…1111 (two attempts, one entry), the caller to 5002, and 120's zero-second answer.
        // 111→119 is not missed: 119 called 111 back and connected afterwards.
        $this->assertSame(3, $count);
    }

    public function test_a_failed_attempt_after_the_last_connected_call_is_missed_again(): void
    {
        $rows = [
            $this->cdr('2026-10-05 09:00:00', '111', '119', 'DIAL', 'ANSWERED', 45),
            $this->cdr('2026-10-05 11:00:00', '111', '119', 'DIAL', 'NO ANSWER'),
        ];

        $this->assertSame(1, app(PbxMissedCallsCounter::class)->countFromRows($rows));
    }

    public function test_returns_null_when_the_pbx_database_is_not_configured(): void
    {
        config(['database.connections.pbx_cdr.host' => null]);

        $this->assertNull(app(PbxMissedCallsCounter::class)->count(Carbon::now()->startOfDay(), Carbon::now()));
    }

    private function cdr(string $calldate, string $src, string $dst, string $lastapp, string $disposition, int $billsec = 0): object
    {
        return (object) compact('calldate', 'src', 'dst', 'lastapp', 'disposition', 'billsec');
    }
}
