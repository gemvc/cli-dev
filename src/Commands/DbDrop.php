<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\CliColor;
use Gemvc\CLI\Command;
use PDO;

/**
 * Drop Table from the database
 */
class DbDrop extends Command
{
    use ResolvesDatabaseEnvironment;

    protected function readConfirmation(): string
    {
        $this->write("\nAre you sure you want to drop this table? (yes/no): ", CliColor::Yellow);
        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            $this->error('Failed to open stdin');

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

            if (!$parsed['force'] && !$this->confirmDrop($parsed['table'])) {
                return false;
            }

            $pdo = DbConnect::connect();
            if (!$pdo) {
                $this->error("Failed to connect to database");
                return false;
            }

            if (!$this->tableExists($pdo, $parsed['table'])) {
                throw new \Exception("Table '{$parsed['table']}' does not exist.");
            }

            $this->showCreateTable($pdo, $parsed['table']);
            $this->dropTable($pdo, $parsed['table']);

            $this->success("Table '{$parsed['table']}' has been dropped successfully!");

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
            $this->error("Table name is required. Usage: db:drop TableName [--force]");
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

    protected function confirmDrop(string $tableName): bool
    {
        $this->error("\nWARNING: This will permanently delete the table '{$tableName}' and all its data!");
        $this->error("This action cannot be undone.");

        if (strtolower($this->readConfirmation()) !== 'yes') {
            $this->info("Operation cancelled.");
            return false;
        }

        return true;
    }

    protected function tableExists(\PDO $pdo, string $tableName): bool
    {
        $driver = $this->resolveDriver();

        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT to_regclass(:tableName)");
            $stmt->execute([':tableName' => $tableName]);
            $result = $stmt->fetchColumn();

            return $result !== false && $result !== null;
        }

        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
            $stmt->execute([$tableName]);

            return $stmt->fetchColumn() !== false;
        }

        $stmt = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        if ($stmt === false) {
            throw new \Exception("Failed to check if table exists");
        }

        return $stmt->rowCount() > 0;
    }

    protected function showCreateTable(\PDO $pdo, string $tableName): void
    {
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

    protected function dropTable(\PDO $pdo, string $tableName): void
    {
        $driver = $this->resolveDriver();
        $quoted = $driver === 'pgsql' || $driver === 'sqlite'
            ? '"' . str_replace('"', '""', $tableName) . '"'
            : "`{$tableName}`";

        $pdo->exec("DROP TABLE {$quoted}");
    }
}
