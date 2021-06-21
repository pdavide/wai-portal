<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Log;

/**
 * Basic test case.
 */
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use SqliteForeignKeyHotfix;

    public function __construct(?string $name = null, array $data = [], string $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->hotfixSqlite();
    }

    /**
     * Add log message expectation.
     *
     * @param string $logLevel the log level
     * @param mixed $args the expected arguments
     * @param int $times the expected log counter
     */
    protected function expectLogMessage(string $logLevel, $args, int $times = 1): void
    {
        Log::shouldReceive($logLevel)
            ->times($times)
            ->withArgs($args);
    }
}
