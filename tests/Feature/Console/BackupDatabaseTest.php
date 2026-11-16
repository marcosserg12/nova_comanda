<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    // Any process that is not faked throws instead of running for real (no mysqldump against a database).
    Process::fake();
    Process::preventStrayProcesses();

    $this->dir = storage_path('framework/testing/backups');
    File::deleteDirectory($this->dir);
    File::ensureDirectoryExists($this->dir);
    config([
        'comanda.backup_path' => $this->dir,
        'database.connections.mysql.host' => '127.0.0.1',
        'database.connections.mysql.port' => '3306',
        'database.connections.mysql.username' => 'comanda',
        'database.connections.mysql.password' => 'segredo',
        'database.connections.mysql.database' => 'comanda',
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

it('dumps the database with mysqldump, compresses it and prunes old files', function () {
    Process::fake();
    $old = $this->dir.'/comanda-antigo.sql.gz';
    $recent = $this->dir.'/comanda-recente.sql.gz';
    File::put($old, 'x');
    touch($old, now()->subDays(20)->getTimestamp());
    File::put($recent, 'x');

    $this->artisan('comanda:backup-db', ['--connection' => 'mysql', '--keep' => 14])->assertSuccessful();

    Process::assertRan(function ($process) {
        $command = (array) $process->command;

        return $command[0] === 'mysqldump'
            && in_array('--single-transaction', $command, true)
            && in_array('--no-tablespaces', $command, true)
            && in_array('--user=comanda', $command, true)
            && in_array('comanda', $command, true)
            && collect($command)->contains(fn ($arg) => str_starts_with($arg, '--result-file='))
            && ($process->environment['MYSQL_PWD'] ?? null) === 'segredo';
    });
    Process::assertRan(fn ($process) => ((array) $process->command)[0] === 'gzip');

    expect(File::exists($old))->toBeFalse()->and(File::exists($recent))->toBeTrue();
});

it('fails clearly for non-MySQL connections', function () {
    Process::fake();

    $this->artisan('comanda:backup-db', ['--connection' => 'sqlite'])->assertFailed();

    Process::assertNothingRan();
});

it('fails, leaves no partial file and never gzips when mysqldump fails', function () {
    Process::fake(fn () => Process::result(errorOutput: 'boom', exitCode: 1));

    $this->artisan('comanda:backup-db', ['--connection' => 'mysql'])->assertExitCode(1);

    Process::assertRan(fn ($process) => ((array) $process->command)[0] === 'mysqldump');
    expect(File::glob($this->dir.'/comanda-*.sql*'))->toBeEmpty();
    Process::assertNotRan(fn ($process) => ((array) $process->command)[0] === 'gzip');
});

it('removes the raw dump and fails when gzip fails', function () {
    Process::fake(function ($process) {
        $command = (array) $process->command;

        if ($command[0] === 'mysqldump') {
            $resultFile = collect($command)->first(fn ($arg) => str_starts_with($arg, '--result-file='));
            File::put(substr($resultFile, strlen('--result-file=')), 'dump');

            return Process::result();
        }

        return Process::result(errorOutput: 'gzip boom', exitCode: 1);
    });

    $this->artisan('comanda:backup-db', ['--connection' => 'mysql'])->assertExitCode(1);

    Process::assertRan(fn ($process) => ((array) $process->command)[0] === 'gzip');
    expect(File::glob($this->dir.'/comanda-*.sql*'))->toBeEmpty();
});

it('prunes only old comanda dump files and keeps other files', function () {
    Process::fake();
    $oldDump = $this->dir.'/comanda-antigo.sql.gz';
    $oldRaw = $this->dir.'/comanda-antigo-bruto.sql';
    $notes = $this->dir.'/notes.txt';
    File::put($oldDump, 'x');
    File::put($oldRaw, 'x');
    File::put($notes, 'x');
    touch($oldDump, now()->subDays(20)->getTimestamp());
    touch($oldRaw, now()->subDays(20)->getTimestamp());
    touch($notes, now()->subDays(20)->getTimestamp());

    $this->artisan('comanda:backup-db', ['--connection' => 'mysql', '--keep' => 14])->assertSuccessful();

    expect(File::exists($oldDump))->toBeFalse()
        ->and(File::exists($oldRaw))->toBeFalse()
        ->and(File::exists($notes))->toBeTrue();
});

it('is scheduled daily at 03:00', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('comanda:backup-db');

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'comanda:backup-db'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 3 * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
