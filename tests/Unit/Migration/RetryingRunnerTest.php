<?php

namespace Tests\Unit\Migration;

use App\Migration\Exceptions\StorageUploadException;
use App\Migration\Support\RetryingRunner;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class RetryingRunnerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
    }

    public function test_retries_transient_then_succeeds(): void
    {
        $runner = new RetryingRunner(tries: 3, baseDelayMs: 10, maxDelayMs: 50);
        $calls = 0;

        $result = $runner->run(function () use (&$calls) {
            $calls++;
            if ($calls < 3) {
                throw StorageUploadException::transient('boom');
            }

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(3, $calls);
    }

    public function test_gives_up_after_max_tries(): void
    {
        $runner = new RetryingRunner(tries: 2, baseDelayMs: 1, maxDelayMs: 2);
        $calls = 0;

        try {
            $runner->run(function () use (&$calls) {
                $calls++;
                throw StorageUploadException::transient('always');
            });
            $this->fail('expected exception');
        } catch (StorageUploadException $e) {
            $this->assertSame(2, $calls);
        }
    }

    public function test_non_retryable_short_circuits_immediately(): void
    {
        $runner = new RetryingRunner(tries: 5, baseDelayMs: 1, maxDelayMs: 2);
        $calls = 0;

        try {
            $runner->run(function () use (&$calls) {
                $calls++;
                throw StorageUploadException::permanent('nope');
            });
            $this->fail('expected exception');
        } catch (StorageUploadException $e) {
            $this->assertSame(1, $calls);
        }
    }
}
