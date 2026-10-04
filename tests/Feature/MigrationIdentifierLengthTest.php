<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

/**
 * Tests run on SQLite, but development and production use MySQL, which rejects identifiers
 * longer than 64 characters. Generated index names must stay within that limit.
 */
class MigrationIdentifierLengthTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private const MYSQL_IDENTIFIER_LIMIT = 64;

    public function test_every_table_and_index_name_fits_mysql_identifier_limit(): void
    {
        $tooLong = [];

        foreach ([null, 'catalog'] as $connection) {
            $names = DB::connection($connection)
                ->table('sqlite_master')
                ->whereIn('type', ['table', 'index'])
                ->where('name', 'not like', 'sqlite_%')
                ->pluck('name');

            foreach ($names as $name) {
                if (strlen((string) $name) > self::MYSQL_IDENTIFIER_LIMIT) {
                    $tooLong[] = ($connection ?? 'main').': '.$name;
                }
            }
        }

        $this->assertSame([], $tooLong);
    }
}
