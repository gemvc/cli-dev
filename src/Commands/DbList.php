<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\CliColor;
use Gemvc\CLI\Command;
use Gemvc\CLI\Commands\DbConnect;

class DbList extends Command
{
    use ResolvesDatabaseEnvironment;
    use ResolvesDatabaseRelations;

    public function execute(): bool
    {
        try {
            $this->info("Fetching database tables and views...");

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

            $relations = $this->fetchRelations($pdo, $dbName);
            if ($relations === false) {
                $this->error("Failed to query database tables/views");
                return false;
            }

            if ($relations === []) {
                $this->info("No tables or views found in database '{$dbName}'");
                return false;
            }

            return $this->displayRelations($pdo, $dbName, $relations);
        } catch (\Exception $e) {
            $this->error("Failed to list tables/views: " . $e->getMessage());
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>|false
     */
    protected function fetchColumns(\PDO $pdo, string $table): array|false
    {
        $driver = $this->resolveDriver();

        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT column_name AS \"Field\", udt_name AS \"Type\", is_nullable AS \"Null\", column_default AS \"Default\", '' AS \"Key\", '' AS \"Extra\" FROM information_schema.columns WHERE table_name = :tableName AND table_schema = current_schema() ORDER BY ordinal_position");
            $stmt->execute([':tableName' => $table]);

            return array_values($stmt->fetchAll(\PDO::FETCH_ASSOC));
        }

        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info('{$table}')");
            if ($stmt === false) {
                return false;
            }

            $columns = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $columns[] = [
                    'Field' => $row['name'] ?? '',
                    'Type' => $row['type'] ?? '',
                    'Null' => (isset($row['notnull']) && (int) $row['notnull'] === 1) ? 'NO' : 'YES',
                    'Key' => (isset($row['pk']) && (int) $row['pk'] === 1) ? 'PRI' : '',
                    'Default' => $row['dflt_value'] ?? null,
                    'Extra' => '',
                ];
            }

            return $columns;
        }

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
     * @param list<array{name: string, kind: 'table'|'view'}> $relations
     */
    protected function displayRelations(\PDO $pdo, string $dbName, array $relations): bool
    {
        $tables = [];
        $views = [];
        foreach ($relations as $rel) {
            if ($rel['kind'] === 'view') {
                $views[] = $rel['name'];
            } else {
                $tables[] = $rel['name'];
            }
        }

        $this->write("\nRelations in database '{$dbName}':\n", CliColor::Yellow);

        if ($tables !== []) {
            $this->write("\nTables:\n", CliColor::Yellow);
            foreach ($tables as $table) {
                if (!$this->displayOneRelation($pdo, $table, 'table')) {
                    return false;
                }
            }
        }

        if ($views !== []) {
            $this->write("\nViews:\n", CliColor::Yellow);
            foreach ($views as $view) {
                if (!$this->displayOneRelation($pdo, $view, 'view')) {
                    return false;
                }
            }
        }

        $this->write("\n");

        return true;
    }

    /**
     * @param 'table'|'view' $kind
     */
    protected function displayOneRelation(\PDO $pdo, string $name, string $kind): bool
    {
        $label = $kind === 'view' ? 'View' : 'Table';
        $this->write("\n{$label}: {$name}\n", CliColor::Green);

        $columns = $this->fetchColumns($pdo, $name);
        if ($columns === false) {
            $this->warning("Failed to get columns for {$label}: {$name}");

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

        return true;
    }
}
