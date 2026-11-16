<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class BackupDatabase extends Command
{
    protected $signature = 'comanda:backup-db {--connection= : Conexão do config/database.php} {--keep= : Dias de retenção}';

    protected $description = 'Faz o backup diário do banco do Nana Comanda (mysqldump + gzip) e apaga os antigos';

    public function handle(): int
    {
        $name = (string) ($this->option('connection') ?: config('database.default'));
        $connection = (array) config("database.connections.{$name}");

        if (($connection['driver'] ?? null) !== 'mysql') {
            $this->error("Backup só é suportado para MySQL (conexão: {$name}).");

            return self::FAILURE;
        }

        $dir = (string) config('comanda.backup_path');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/comanda-'.now()->format('Y-m-d_His').'.sql';

        $dump = Process::env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])
            ->timeout(300)
            ->run([
                'mysqldump',
                '--single-transaction',
                '--no-tablespaces',
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--user='.$connection['username'],
                '--result-file='.$file,
                $connection['database'],
            ]);

        if ($dump->failed()) {
            $this->error('mysqldump falhou: '.$dump->errorOutput());

            return self::FAILURE;
        }

        Process::run(['gzip', '-f', $file]);

        $keepDays = (int) ($this->option('keep') ?: config('comanda.backup_keep_days'));
        $limit = now()->subDays($keepDays)->getTimestamp();

        foreach (File::files($dir) as $existing) {
            if ($existing->getMTime() < $limit) {
                File::delete($existing->getPathname());
            }
        }

        $this->info('Backup criado: '.basename($file).'.gz');

        return self::SUCCESS;
    }
}
