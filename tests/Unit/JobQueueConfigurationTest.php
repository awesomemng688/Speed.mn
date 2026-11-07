<?php

namespace Tests\Unit;

use App\Jobs\ParseCs2Demo;
use App\Jobs\PollServer;
use PHPUnit\Framework\TestCase;

class JobQueueConfigurationTest extends TestCase
{
    public function test_jobs_use_the_expected_queue_connections(): void
    {
        $poll = new PollServer(123);
        $demo = new ParseCs2Demo(456);

        $this->assertSame('redis', $poll->connection);
        $this->assertSame(123, $poll->serverId);
        $this->assertSame('redis', $demo->connection);
        $this->assertSame('demo-parsing', $demo->queue);
        $this->assertSame(456, $demo->demoVideoId);
    }
}
