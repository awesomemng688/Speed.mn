<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'speedmn:backup';

    protected $description = 'Create and validate a compressed database backup';

    public function handle(): int
    {
        $connection = DB::connection();
        $connectionConfig = $connection->getConfig();

        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->error('Database backups currently require a MySQL or MariaDB connection.');

            return self::FAILURE;
        }

        $finder = new ExecutableFinder();
        $dumpBinary = $finder->find('mariadb-dump') ?? $finder->find('mysqldump');
        $gzipBinary = $finder->find('gzip');
        if (! $dumpBinary || ! $gzipBinary) {
            $this->error('Install mariadb-dump or mysqldump and gzip before running backups.');

            return self::FAILURE;
        }

        $directory = storage_path('app/private/backups');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->error('Could not create the private backup directory.');

            return self::FAILURE;
        }
        chmod($directory, 0700);

        $credentialsFile = tempnam($directory, '.db-client-');
        if ($credentialsFile === false) {
            $this->error('Could not create a temporary database client file.');

            return self::FAILURE;
        }

        $dumpPath = $directory.'/speedmn-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sql';
        $backupPath = $dumpPath.'.gz';

        try {
            chmod($credentialsFile, 0600);
            if (file_put_contents($credentialsFile, $this->clientOptions($connectionConfig)) === false) {
                throw new \RuntimeException('Could not write the temporary database client file.');
            }

            $database = (string) ($connectionConfig['database'] ?? '');
            if ($database === '') {
                throw new \RuntimeException('The database name is not configured.');
            }

            $dump = new Process([
                $dumpBinary,
                '--defaults-extra-file='.$credentialsFile,
                '--single-transaction',
                '--routines',
                '--triggers',
                '--events',
                '--hex-blob',
                $database,
                '--result-file='.$dumpPath,
            ]);
            $dump->setTimeout(3600);
            $dump->mustRun();

            $gzip = new Process([$gzipBinary, '-f', $dumpPath]);
            $gzip->setTimeout(3600);
            $gzip->mustRun();

            $verify = new Process([$gzipBinary, '-t', $backupPath]);
            $verify->setTimeout(60);
            $verify->mustRun();
            chmod($backupPath, 0600);

            $this->pruneOldBackups($directory);
            $this->info('Database backup created and gzip-verified: '.$backupPath);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            @unlink($dumpPath);
            @unlink($backupPath);
            $this->error('Database backup failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            @unlink($credentialsFile);
        }
    }

    private function clientOptions(array $config): string
    {
        $options = [];
        foreach (['user' => 'username', 'password' => 'password', 'host' => 'host', 'port' => 'port', 'socket' => 'unix_socket'] as $option => $key) {
            $value = (string) ($config[$key] ?? '');
            if ($value === '') {
                continue;
            }
            if (str_contains($value, "\n") || str_contains($value, "\r")) {
                throw new \RuntimeException('Database connection values cannot contain newlines.');
            }

            $options[] = $option.'="'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return "[client]\n".implode("\n", $options)."\n";
    }

    private function pruneOldBackups(string $directory): void
    {
        $retentionDays = max(1, (int) config('speedmn.backup_retention_days', 14));
        $cutoff = now()->subDays($retentionDays)->getTimestamp();

        foreach (glob($directory.'/speedmn-*.sql.gz') ?: [] as $backup) {
            if (filemtime($backup) < $cutoff) {
                @unlink($backup);
            }
        }
    }
}