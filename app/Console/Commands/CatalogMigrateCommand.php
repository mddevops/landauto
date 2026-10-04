<?php

namespace App\Console\Commands;

use App\Catalog\CatalogDatabase;
use Illuminate\Console\Command;
use RuntimeException;

class CatalogMigrateCommand extends Command
{
    protected $signature = 'catalog:migrate
        {--force : Run without confirmation in production}
        {--pretend : Show the SQL without running it}';

    protected $description = 'Run Global Automotive Catalog migrations on the separate "catalog" connection only';

    public function handle(CatalogDatabase $catalog): int
    {
        try {
            $catalog->assertSeparateFromMain();
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->call('migrate', [
            '--database' => CatalogDatabase::CONNECTION,
            '--path' => CatalogDatabase::MIGRATIONS,
            '--force' => (bool) $this->option('force'),
            '--pretend' => (bool) $this->option('pretend'),
        ]);
    }
}
