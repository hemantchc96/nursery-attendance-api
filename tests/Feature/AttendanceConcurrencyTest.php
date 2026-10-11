<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PDO;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AttendanceConcurrencyTest extends TestCase
{
    public function test_overlapping_check_in_waits_for_writer_then_returns_conflict(): void
    {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $database = tempnam($directory, 'attendance-');
        $writer = null;
        $worker = null;

        try {
            config(['database.connections.sqlite.database' => $database]);
            DB::purge('sqlite');
            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
            $this->seed();

            // SQLite permits reads during a reserved write lock. The competing HTTP
            // request resolves its child, then waits at INSERT until this visit commits.
            $writer = new PDO('sqlite:'.$database);
            $writer->exec('PRAGMA foreign_keys = ON');
            $writer->beginTransaction();
            $writer->exec("INSERT INTO check_ins (tenant_id, room_id, child_id, checked_in_at) VALUES (1, 1, 1, '2026-10-11 09:00:00')");
            $worker = new Process([PHP_BINARY, base_path('tests/Support/check_in_worker.php')], base_path(), [
                'APP_ENV' => 'testing',
                'APP_DEBUG' => 'false',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $database,
                'DB_URL' => '',
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array',
            ]);
            $worker->setTimeout(15);
            $worker->start();
            $ready = $worker->waitUntil(fn ($type, $output) => str_contains($output, 'READY'));
            $this->assertTrue($ready, $worker->getErrorOutput().$worker->getOutput());
            usleep(150000);
            $this->assertTrue($worker->isRunning(), 'The overlapping request should wait for the open writer.');
            $writer->commit();
            $worker->wait();

            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $result = json_decode(trim(substr($worker->getOutput(), strpos($worker->getOutput(), "READY\n") + 6)), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(409, $result['status']);
            $this->assertSame('Child is already checked into a room. Check them out first.', $result['body']['message']);
            $this->assertDatabaseCount('check_ins', 1);
            $this->assertDatabaseHas('check_ins', ['tenant_id' => 1, 'room_id' => 1, 'child_id' => 1, 'checked_out_at' => null]);
        } finally {
            if ($writer?->inTransaction()) {
                $writer->rollBack();
            }
            $worker?->stop();
            $writer = null;
            DB::purge('sqlite');
            if (is_file($database)) {
                unlink($database);
            }
        }
    }
}
