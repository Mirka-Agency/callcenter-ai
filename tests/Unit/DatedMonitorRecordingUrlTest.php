<?php

namespace Tests\Unit;

use App\Infrastructure\Voip\Support\DatedMonitorRecordingUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DatedMonitorRecordingUrlTest extends TestCase
{
    #[DataProvider('urlProvider')]
    public function test_inserts_mixmonitor_date_folders(string $input, string $expected): void
    {
        $this->assertSame($expected, DatedMonitorRecordingUrl::normalize($input));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function urlProvider(): array
    {
        $correct = 'http://192.168.2.16/mirka-call-recordings/2026/09/20/exten-117-09907647524-20260920-115642-1789889197.32913.wav';

        return [
            'missing date folders' => [
                'http://192.168.2.16/mirka-call-recordings/exten-116-09309194604-20260920-092247-1789879949.32755.wav',
                'http://192.168.2.16/mirka-call-recordings/2026/09/20/exten-116-09309194604-20260920-092247-1789879949.32755.wav',
            ],
            'already dated' => [
                $correct,
                $correct,
            ],
            'queue recording missing date folders' => [
                'http://192.168.2.16/mirka-call-recordings/q-5001-09171768277-20260920-115642-1789889197.32913.wav',
                'http://192.168.2.16/mirka-call-recordings/2026/09/20/q-5001-09171768277-20260920-115642-1789889197.32913.wav',
            ],
            'https and query string' => [
                'https://pbx.example.test/monitor/exten-101-88530814-20260920-151855-1789901327.33144.wav?download=1',
                'https://pbx.example.test/monitor/2026/09/20/exten-101-88530814-20260920-151855-1789901327.33144.wav?download=1',
            ],
            'unrelated url' => [
                'https://example.test/rec.mp3',
                'https://example.test/rec.mp3',
            ],
            'simotel identifier' => [
                'simotel://20260920_1610778618.378.mp3',
                'simotel://20260920_1610778618.378.mp3',
            ],
        ];
    }

    public function test_download_candidates_prefer_dated_path(): void
    {
        $original = 'http://192.168.2.16/mirka-call-recordings/exten-116-09309194604-20260920-092247-1789879949.32755.wav';

        $this->assertSame([
            'http://192.168.2.16/mirka-call-recordings/2026/09/20/exten-116-09309194604-20260920-092247-1789879949.32755.wav',
            $original,
        ], DatedMonitorRecordingUrl::downloadCandidates($original));
    }
}
