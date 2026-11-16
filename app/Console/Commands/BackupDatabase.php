<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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
            File::delete($file);
            Log::error('comanda:backup-db: mysqldump falhou', ['error' => $dump->errorOutput()]);
            $this->error('mysqldump falhou: '.$dump->errorOutput());

            return self::FAILURE;
        }

        $gz = Process::timeout(300)->run(['gzip', '-f', $file]);

        if ($gz->failed()) {
            File::delete([$file, $file.'.gz']);
            Log::error('comanda:backup-db: gzip falhou', ['error' => $gz->errorOutput()]);
            $this->error('gzip falhou: '.$gz->errorOutput());

            return self::FAILURE;
        }

        if (File::exists($file.'.gz')) {
            File::chmod($file.'.gz', 0600);
        }

        $keepDays = (int) ($this->option('keep') ?: config('comanda.backup_keep_days'));
        $limit = now()->subDays($keepDays)->getTimestamp();

        $dumps = array_merge(
            File::glob($dir.'/comanda-*.sql.gz'),
            File::glob($dir.'/comanda-*.sql'),
        );

        foreach ($dumps as $existing) {
            if (File::lastModified($existing) < $limit) {
                File::delete($existing);
            }
        }

        $this->info('Backup criado: '.basename($file).'.gz');

        return self::SUCCESS;
    }
}
