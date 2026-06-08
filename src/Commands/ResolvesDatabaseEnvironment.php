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
}
