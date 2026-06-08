<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;
use Gemvc\Helper\ProjectHelper;
use Gemvc\CLI\Commands\DbConnect;

class DbInit extends Command
{
    public function execute(): bool
    {
        ProjectHelper::loadEnv();

        try {
            $this->info("Initializing database...");
            $pdo = DbConnect::connectAsRoot();
            if (!$pdo) {
                return false;
            }

            $dbName = $this->resolveDatabaseName();
            if ($dbName === null) {
                $this->error("Database name not found in environment variables");
                return false;
            }

            $this->initializeDatabase($pdo, $dbName);
            $this->success("Database '{$dbName}' initialized successfully!");

            return true;
        } catch (\Exception $e) {
            $this->error("Failed to initialize database: " . $e->getMessage());
            return false;
        }
    }

    protected function resolveDatabaseName(): ?string
    {
        $dbName = $_ENV['DB_NAME'] ?? null;

        return is_string($dbName) ? $dbName : null;
    }

    protected function buildCreateDatabaseSql(string $dbName): string
    {
        return "CREATE DATABASE IF NOT EXISTS `{$dbName}`";
    }

    protected function initializeDatabase(\PDO $pdo, string $dbName): void
    {
        $pdo->exec($this->buildCreateDatabaseSql($dbName));
    }
}
