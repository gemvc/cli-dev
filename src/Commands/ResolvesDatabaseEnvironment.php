<?php

namespace Gemvc\CLI\Commands;

use Gemvc\Helper\ProjectHelper;

trait ResolvesDatabaseEnvironment
{
    protected function loadProjectEnv(): void
    {
        ProjectHelper::loadEnv();
    }

    protected function resolveDatabaseName(): ?string
    {
        $dbName = $_ENV['DB_NAME'] ?? null;

        if (!is_string($dbName) || $dbName === '') {
            return null;
        }

        return $dbName;
    }

    /**
     * @return string One of: 'mysql', 'pgsql', 'sqlite'
     */
    protected function resolveDriver(): string
    {
        $driver = $_ENV['DB_DRIVER'] ?? 'mysql';
        if (!is_string($driver)) {
            return 'mysql';
        }

        $driver = strtolower($driver);

        return in_array($driver, ['mysql', 'pgsql', 'sqlite'], true) ? $driver : 'mysql';
    }
}
