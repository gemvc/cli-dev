<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Unit\Commands;

use Gemvc\CLI\Command;
use Gemvc\CLI\Commands\ResolvesDatabaseEnvironment;
use Gemvc\CliDev\Tests\Support\CommandTestCase;
use Gemvc\Helper\ProjectHelper;

final class ResolvesDatabaseEnvironmentTest extends CommandTestCase
{
    public function testLoadProjectEnvPopulatesDatabaseName(): void
    {
        ProjectHelper::configure($this->projectRoot, ['DB_NAME' => 'app_db']);
        $command = $this->makeCommandUsingTrait();

        $this->invokeMethod($command, 'loadProjectEnv');

        $this->assertSame('app_db', $_ENV['DB_NAME'] ?? null);
    }

    public function testResolveDatabaseNameReturnsConfiguredName(): void
    {
        ProjectHelper::loadEnv();
        $command = $this->makeCommandUsingTrait();

        $this->assertSame('test_db', $this->invokeMethod($command, 'resolveDatabaseName'));
    }

    public function testResolveDatabaseNameRejectsEmptyString(): void
    {
        ProjectHelper::configure($this->projectRoot, ['DB_NAME' => '']);
        ProjectHelper::loadEnv();
        $command = $this->makeCommandUsingTrait();

        $this->assertNull($this->invokeMethod($command, 'resolveDatabaseName'));
    }

    public function testResolveDatabaseNameRejectsMissingValue(): void
    {
        ProjectHelper::configure($this->projectRoot, [], true);
        unset($_ENV['DB_NAME']);
        putenv('DB_NAME');
        $command = $this->makeCommandUsingTrait();

        $this->assertNull($this->invokeMethod($command, 'resolveDatabaseName'));
    }

    private function makeCommandUsingTrait(): object
    {
        return new class([], []) extends Command {
            use ResolvesDatabaseEnvironment;

            public function execute(): bool
            {
                return true;
            }
        };
    }
}
