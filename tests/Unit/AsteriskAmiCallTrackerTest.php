<?php

namespace Tests\Unit;

use App\Application\Voip\Services\AsteriskAmiCallTracker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AsteriskAmiCallTrackerTest extends TestCase
{
    private AsteriskAmiCallTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tracker = new AsteriskAmiCallTracker;
    }

    #[Test]
    public function it_builds_payload_from_hangup_event(): void
    {
        $this->tracker->handle([
            'Event' => 'Newchannel',
            'Uniqueid' => '1730000000.1',
            'Linkedid' => '1730000000.1',
            'CallerIDNum' => '09121234567',
            'Context' => 'from-trunk',
            'Exten' => '101',
        ]);

        $this->tracker->handle([
            'Event' => 'Dial',
            'SubEvent' => 'End',
            'Uniqueid' => '1730000000.1',
            'Linkedid' => '1730000000.1',
            'DialStatus' => 'ANSWER',
            'DestCallerIDNum' => '101',
        ]);

        $payload = $this->tracker->handle([
            'Event' => 'Hangup',
            'Uniqueid' => '1730000000.1',
            'Linkedid' => '1730000000.1',
            'CallerIDNum' => '09121234567',
            'Cause-txt' => 'Normal Clearing',
            'BillableSeconds' => '45',
        ]);

        $this->assertNotNull($payload);
        $this->assertSame('call.ended', $payload['event']);
        $this->assertSame('1730000000.1', $payload['call_id']);
        $this->assertSame('09121234567', $payload['from']);
        $this->assertSame('101', $payload['extension']);
        $this->assertSame(45, $payload['duration']);
        $this->assertSame('inbound', $payload['direction']);
    }

    #[Test]
    public function it_builds_payload_from_cdr_event(): void
    {
        $payload = $this->tracker->handle([
            'Event' => 'Cdr',
            'UniqueID' => '1730000001.2',
            'LinkedID' => '1730000001.2',
            'Source' => '101',
            'Destination' => '09129876543',
            'Disposition' => 'ANSWERED',
            'Billsec' => '120',
        ]);

        $this->assertNotNull($payload);
        $this->assertSame('1730000001.2', $payload['call_id']);
        $this->assertSame('101', $payload['from']);
        $this->assertSame('09129876543', $payload['to']);
        $this->assertSame(120, $payload['duration']);
        $this->assertSame('outbound', $payload['direction']);
    }

    #[Test]
    public function it_does_not_ingest_twice_for_same_call(): void
    {
        $hangup = [
            'Event' => 'Hangup',
            'Uniqueid' => '1730000002.3',
            'Linkedid' => '1730000002.3',
            'CallerIDNum' => '09120000000',
            'Cause-txt' => 'Normal Clearing',
        ];

        $this->assertNotNull($this->tracker->handle($hangup));
        $this->assertNull($this->tracker->handle($hangup));
    }
}
