<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PostgresSequence
{
    /**
     * Align a PostgreSQL serial/identity sequence with MAX(id) after explicit-id inserts.
     */
    public static function sync(string $table, string $column = 'id', ?string $connection = null): void
    {
        $connection = DB::connection($connection);

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::connection($connection->getName())->hasTable($table)) {
            return;
        }

        $max = (int) ($connection->table($table)->max($column) ?? 0);

        $row = $connection->selectOne(
            'SELECT pg_get_serial_sequence(?, ?) AS seq',
            [$table, $column]
        );

        if (! is_object($row) || empty($row->seq)) {
            return;
        }

        if ($max < 1) {
            $connection->statement('SELECT setval(?, ?, ?)', [$row->seq, 1, false]);

            return;
        }

        $connection->statement('SELECT setval(?, ?, ?)', [$row->seq, $max, true]);
    }
}
