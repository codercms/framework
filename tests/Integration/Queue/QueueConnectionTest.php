<?php

namespace Illuminate\Tests\Integration\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Bus;
use Mockery as m;
use Orchestra\Testbench\TestCase;
use Throwable;

/**
 * @group integration
 */
class QueueConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        if (\PHP_VERSION_ID >= 80100) {
            $this->markTestSkipped('Test failing in PHP 8.1');
        }

        parent::setUp();
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.debug', 'true');
        $app['config']->set('queue.default', 'sqs');
        $app['config']->set('queue.connections.sqs.after_commit', true);
    }

    protected function tearDown(): void
    {
        QueueConnectionTestJob::$ran = false;

        m::close();
    }

    public function testJobWontGetDispatchedInsideATransaction()
    {
        $this->app->singleton('db.transactions', function () {
            $transactionManager = m::mock(DatabaseTransactionsManager::class);
            $transactionManager->shouldReceive('addCallback')->once()->andReturn(null);

            return $transactionManager;
        });

        Bus::dispatch(new QueueConnectionTestJob);
    }

    public function testJobWillGetDispatchedInsideATransactionWhenExplicitlyIndicated()
    {
        $this->app->singleton('db.transactions', function () {
            $transactionManager = m::mock(DatabaseTransactionsManager::class);
            $transactionManager->shouldNotReceive('addCallback')->andReturn(null);

            return $transactionManager;
        });

        try {
            Bus::dispatch((new QueueConnectionTestJob)->beforeCommit());
        } catch (Throwable $e) {
            // This job was dispatched
        }
    }

    public function testJobWontGetDispatchedInsideATransactionWhenExplicitlyIndicated()
    {
        $this->app['config']->set('queue.connections.sqs.after_commit', false);

        $this->app->singleton('db.transactions', function () {
            $transactionManager = m::mock(DatabaseTransactionsManager::class);
            $transactionManager->shouldReceive('addCallback')->once()->andReturn(null);

            return $transactionManager;
        });

        try {
            Bus::dispatch((new QueueConnectionTestJob)->afterCommit());
        } catch (SqsException $e) {
            // This job was dispatched
        }
    }
}

class QueueConnectionTestJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public static $ran = false;

    public function handle()
    {
        static::$ran = true;
    }
}
