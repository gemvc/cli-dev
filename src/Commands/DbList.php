<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\CliColor;
use Gemvc\CLI\Command;
use Gemvc\CLI\Commands\DbConnect;

class DbList extends Command
{
    use ResolvesDatabaseEnvironment;

    public function execute(): bool
    {
        try {
            $this->info("Fetching database tables...");

            $this->loadProjectEnv();

            $dbName = $this->resolveDatabaseName();
            if ($dbName === null) {
                $this->error("Database name not found in configuration (DB_NAME)");
                return false;
            }

            $pdo = DbConnect::connect();
            if (!$pdo) {
                return false;
            }

            $tables = $this->fetchTableNames($pdo, $dbName);
            if ($tables === false) {
                $this->error("Failed to query database tables");
                return false;
            }

            if ($tables === []) {
                $this->info("No tables found in database '{$dbName}'");
                return false;
            }

            return $this->displayTables($pdo, $dbName, $tables);
        } catch (\Exception $e) {
            $this->error("Failed to list tables: " . $e->getMessage());
            return false;
        }
    }

    /**
     * @return list<string>|false
     */
    protected function fetchTableNames(\PDO $pdo, string $dbName): array|false
    {
        $stmt = $pdo->query("SHOW TABLES FROM `{$dbName}`");
        if ($stmt === false) {
            return false;
        }

        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $names = [];
        foreach ($tables as $table) {
            if (is_string($table)) {
                $names[] = $table;
            }
        }

        return $names;
    }

    /**
     * @return list<array<string, mixed>>|false
     */
    protected function fetchColumns(\PDO $pdo, string $table): array|false
    {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        if ($stmt === false) {
            return false;
        }

        $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_values($columns);
    }

    /**
     * @param array<string, mixed> $column
     */
    protected function formatColumnLine(array $column): string
    {
        $field = isset($column['Field']) && is_string($column['Field']) ? $column['Field'] : '';
        $type = isset($column['Type']) && is_string($column['Type']) ? $column['Type'] : '';
        $nullFlag = isset($column['Null']) && is_string($column['Null']) ? $column['Null'] : '';
        $null = $nullFlag === 'YES' ? 'NULL' : 'NOT NULL';
        $keyValue = isset($column['Key']) && is_string($column['Key']) ? $column['Key'] : '';
        $key = $keyValue !== '' ? "({$keyValue})" : '';
        $defaultValue = $column['Default'] ?? null;
        $default = is_string($defaultValue) || is_int($defaultValue) || is_float($defaultValue)
            ? 'DEFAULT ' . (string) $defaultValue
            : '';
        $extraValue = isset($column['Extra']) && is_string($column['Extra']) ? $column['Extra'] : '';
        $extra = $extraValue !== '' ? " {$extraValue}" : '';

        $columnInfo = sprintf(
            "    - %s: %s %s %s %s %s",
            $field,
            $type,
            $null,
            $key,
            $default,
            $extra
        );

        return trim($columnInfo);
    }

    /**
     * @param list<string> $tables
     */
    protected function displayTables(\PDO $pdo, string $dbName, array $tables): bool
    {
        $this->write("\nTables in database '{$dbName}':\n", CliColor::Yellow);

        foreach ($tables as $table) {
            $this->write("\nTable: {$table}\n", CliColor::Green);

            $columns = $this->fetchColumns($pdo, $table);
            if ($columns === false) {
                $this->warning("Failed to get columns for table: {$table}");

                return false;
            }

            if ($columns === []) {
                $this->write("  No columns found\n", CliColor::Red);

                return false;
            }

            $this->write("  Columns:\n", CliColor::Blue);
            foreach ($columns as $column) {
                $this->write($this->formatColumnLine($column) . "\n", CliColor::White);
            }
        }

        $this->write("\n");

        return true;
    }
}
