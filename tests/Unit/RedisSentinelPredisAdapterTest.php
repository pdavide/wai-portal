<?php

namespace Tests\Unit;

use App\Adapter\RedisSentinelPredisAdapter;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Redis sentinel adapter tests.
 */
class RedisSentinelPredisAdapterTest extends TestCase
{
    /**
     * Test custom predis adapter match redis sentinel configuration.
     */
    public function testIndexSentinelAdapter(): void
    {
        $this->assertEquals(Redis::connection('websites-sentinel')->client(), (new RedisSentinelPredisAdapter('websites'))->connect()->redis);
    }
}
