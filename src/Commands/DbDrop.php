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
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        if ($stmt === false) {
            throw new \Exception("Failed to check if table exists");
        }

        return $stmt->rowCount() > 0;
    }

    protected function showCreateTable(\PDO $pdo, string $tableName): void
    {
        $this->info("\nTable structure to be dropped:");
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
        $pdo->exec("DROP TABLE `{$tableName}`");
    }
}
