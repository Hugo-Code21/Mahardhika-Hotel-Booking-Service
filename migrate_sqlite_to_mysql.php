<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('APP_SKIP_SEED', true);
require __DIR__ . '/includes/bootstrap.php';

$sqlitePath = __DIR__ . DIRECTORY_SEPARATOR . 'database.sqlite';
if (!is_file($sqlitePath)) {
    fwrite(STDERR, "SQLite source database not found: database.sqlite\n");
    exit(1);
}

try {
    $source = new PDO('sqlite:' . $sqlitePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $target = db();
    $tables = ['hotels', 'users', 'rooms', 'room_types', 'bookings', 'payments', 'notifications', 'reviews'];

    foreach ($tables as $table) {
        if ((int) $target->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() > 0) {
            throw new RuntimeException('The MySQL database is not empty. Migration was stopped to protect existing records.');
        }
    }

    $sourceTables = $source->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
    $target->beginTransaction();
    $totalRows = 0;

    foreach ($tables as $table) {
        if (!in_array($table, $sourceTables, true)) {
            continue;
        }

        $rows = $source->query('SELECT * FROM `' . $table . '`')->fetchAll();
        foreach ($rows as $row) {
            if ($table === 'users') {
                if ($row['role'] === 'receptionist') {
                    $row['role'] = 'hotel_head_admin';
                    $row['hotel_id'] = 1;
                    if ($row['email'] === 'reception@stayease.com') {
                        $row['name'] = 'Hotel Head Admin';
                        $row['email'] = 'hoteladmin@stayease.com';
                    }
                } elseif (!in_array($row['role'], ['admin', 'customer', 'hotel_head_admin'], true)) {
                    $row['role'] = 'customer';
                } else {
                    $row['hotel_id'] = $row['hotel_id'] ?? null;
                }
            }

            $columns = array_keys($row);
            $quotedColumns = array_map(static fn (string $column): string => '`' . $column . '`', $columns);
            $placeholders = array_map(static fn (int $index): string => ':value' . $index, array_keys($columns));
            $statement = $target->prepare(
                'INSERT INTO `' . $table . '` (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $placeholders) . ')'
            );
            $values = [];
            foreach ($columns as $index => $column) {
                $values[':value' . $index] = $row[$column];
            }
            $statement->execute($values);
            $totalRows++;
        }

        fwrite(STDOUT, sprintf("Migrated %d %s row(s).\n", count($rows), $table));
    }

    ensureJakartaDestination();
    $target->commit();
    fwrite(STDOUT, sprintf("Migration complete. %d total row(s) copied to MySQL.\n", $totalRows));
} catch (Throwable $exception) {
    if (isset($target) && $target->inTransaction()) {
        $target->rollBack();
    }
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
