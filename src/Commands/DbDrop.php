<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\CliColor;
use Gemvc\CLI\Command;
use PDO;

/**
 * Drop a table or view from the database
 */
class DbDrop extends Command
{
    use ResolvesDatabaseEnvironment;
    use ResolvesDatabaseRelations;

    protected function readConfirmation(): string
    {
        $this->write("\nAre you sure you want to drop this relation? (yes/no): ", CliColor::Yellow);
        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            $this->write("Failed to open stdin\n", CliColor::Red);

            return '';
        }
        $line = fgets($handle);
        fclose($handle);

        return $line !== false ? trim($line) : '';
    }

    public function execute(): bool
    {
        $this->loadProjectEnv();

        try {
            $parsed = $this->parseDropArguments();
            if ($parsed === null) {
                return false;
            }

            $pdo = DbConnect::connect();
            if (!$pdo) {
                $this->error("Failed to connect to database");
                return false;
            }

            $dbName = $this->resolveDatabaseName();
            if ($dbName === null) {
                $this->error("Database name not found in configuration (DB_NAME)");
                return false;
            }

            $kind = $this->resolveRelationKind($pdo, $dbName, $parsed['table']);
            if ($kind === null) {
                $this->error("Table or view '{$parsed['table']}' not found");
                return false;
            }

            if (!$parsed['force'] && !$this->confirmDrop($parsed['table'], $kind)) {
                return false;
            }

            $this->showCreateTable($pdo, $parsed['table'], $kind);
            $this->dropRelation($pdo, $parsed['table'], $kind);

            $label = $kind === 'view' ? 'View' : 'Table';
            $this->success("{$label} '{$parsed['table']}' has been dropped successfully!");

            return true;
        } catch (\Exception $e) {
            $this->error("Failed to drop table: " . $e->getMessage());
            return false;
        }
    }

    /**
     * @return array{table: string, force: bool}|null
     */
    protected function parseDropArguments(): ?array
    {
        if ($this->args === []) {
            $this->error("Table or view name is required. Usage: db:drop Name [--force]");
            return null;
        }

        if (!is_string($this->args[0])) {
            $this->error("Table name must be a string");
            return null;
        }

        return [
            'table' => strtolower($this->args[0]),
            'force' => in_array('--force', $this->args, true),
        ];
    }

    /**
     * @param 'table'|'view' $kind
     */
    protected function confirmDrop(string $tableName, string $kind = 'table'): bool
    {
        if ($kind === 'view') {
            $this->write("\nWARNING: This will permanently delete the VIEW '{$tableName}'!\n", CliColor::Red);
        } else {
            $this->write("\nWARNING: This will permanently delete the table '{$tableName}' and all its data!\n", CliColor::Red);
        }
        $this->write("This action cannot be undone.\n", CliColor::Red);

        if (strtolower($this->readConfirmation()) !== 'yes') {
            $this->info("Operation cancelled.");
            return false;
        }

        return true;
    }

    /**
     * @param 'table'|'view' $kind
     */
    protected function showCreateTable(\PDO $pdo, string $tableName, string $kind = 'table'): void
    {
        if ($kind === 'view') {
            $this->info("\nView definition to be dropped:");
            $definition = $this->fetchViewDefinition($pdo, $tableName);
            if ($definition !== null) {
                $this->info($definition);
            }

            return;
        }

        $this->info("\nTable structure to be dropped:");
        $driver = $this->resolveDriver();

        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = :tableName AND table_schema = current_schema() ORDER BY ordinal_position");
            $stmt->execute([':tableName' => $tableName]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $name = is_string($column['column_name'] ?? null) ? $column['column_name'] : '';
                $type = is_string($column['data_type'] ?? null) ? $column['data_type'] : '';
                $nullable = ($column['is_nullable'] ?? null) === 'YES' ? 'NULL' : 'NOT NULL';
                $this->info("  {$name} {$type} {$nullable}");
            }

            return;
        }

        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?");
            $stmt->execute([$tableName]);
            $sql = $stmt->fetchColumn();
            if (is_string($sql)) {
                $this->info($sql);
            }

            return;
        }

        $stmt = $pdo->query("SHOW CREATE TABLE `{$tableName}`");
        if ($stmt === false) {
            throw new \Exception("Failed to get table structure");
        }

        $tableInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($tableInfo) && isset($tableInfo['Create Table']) && is_string($tableInfo['Create Table'])) {
            $this->info($tableInfo['Create Table']);
        }
    }

    /**
     * @param 'table'|'view' $kind
     */
    protected function dropRelation(\PDO $pdo, string $tableName, string $kind): void
    {
        $sql = $this->dropRelationSql($tableName, $kind);
        $pdo->exec($sql);
    }
}
