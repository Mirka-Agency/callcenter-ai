<?php

namespace Tests\Unit;

use App\Infrastructure\Voip\Ami\AsteriskAmiEventParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AsteriskAmiEventParserTest extends TestCase
{
    private AsteriskAmiEventParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AsteriskAmiEventParser;
    }

    #[Test]
    public function it_parses_ami_event_blocks(): void
    {
        $event = $this->parser->parseBlock(<<<'BLOCK'
Event: Hangup
Privilege: call,all
Channel: SIP/trunk-00000001
Uniqueid: 1730000000.1
CallerIDNum: 09121234567
Cause-txt: Normal Clearing
BLOCK);

        $this->assertSame('Hangup', $event['Event']);
        $this->assertSame('1730000000.1', $event['Uniqueid']);
        $this->assertSame('09121234567', $event['CallerIDNum']);
        $this->assertSame('Normal Clearing', $event['Cause-txt']);
    }

    #[Test]
    public function it_detects_success_responses(): void
    {
        $response = $this->parser->parseBlock("Response: Success\r\nMessage: Authentication accepted");

        $this->assertTrue($this->parser->isResponse($response));
        $this->assertTrue($this->parser->isSuccess($response));
    }
}
