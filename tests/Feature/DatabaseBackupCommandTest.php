<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_command_requires_mysql_or_mariadb(): void
    {
        $this->artisan('speedmn:backup')
            ->expectsOutput('Database backups currently require a MySQL or MariaDB connection.')
            ->assertExitCode(1);
    }
}