<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
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
            && in_array('--user=comanda', $command, true)
            && in_array('comanda', $command, true)
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

it('is scheduled daily', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('comanda:backup-db');
});
