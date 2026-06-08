<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Unit\Commands;

use Gemvc\CLI\Commands\DbConnect;
use Gemvc\CLI\Commands\DbInit;
use Gemvc\CliDev\Tests\Support\CommandTestCase;
use Gemvc\CliDev\Tests\Support\PdoMock;

final class DbInitTest extends CommandTestCase
{
    public function testInitializeDatabase(): void
    {
        $pdo = PdoMock::create($this);
        DbConnect::configure(null, $pdo);

        $command = $this->makeCommand(DbInit::class);
        $this->assertTrue($command->execute());
    }

    public function testFailsWhenRootConnectionMissing(): void
    {
        DbConnect::configure(null, null);

        $command = $this->makeCommand(DbInit::class);
        $this->assertFalse($command->execute());
    }

    public function testFailsWhenDatabaseNameMissing(): void
    {
        $pdo = PdoMock::create($this);
        DbConnect::configure(null, $pdo);
        \Gemvc\Helper\ProjectHelper::configure($this->projectRoot, [], true);
        unset($_ENV['DB_NAME']);
        putenv('DB_NAME');

        $command = $this->makeCommand(DbInit::class);
        $this->assertFalse($command->execute());
    }

    public function testResolveDatabaseName(): void
    {
        \Gemvc\Helper\ProjectHelper::loadEnv();
        $command = $this->makeCommand(DbInit::class);
        $this->assertSame('test_db', $this->invokeMethod($command, 'resolveDatabaseName'));
    }

    public function testBuildCreateDatabaseSql(): void
    {
        $command = $this->makeCommand(DbInit::class);
        $this->assertSame(
            'CREATE DATABASE IF NOT EXISTS `my_app`',
            $this->invokeMethod($command, 'buildCreateDatabaseSql', ['my_app'])
        );
    }

    public function testInitializeDatabaseExecutesSql(): void
    {
        $pdo = PdoMock::create($this);
        $command = $this->makeCommand(DbInit::class);
        $this->invokeMethod($command, 'initializeDatabase', [$pdo, 'test_db']);

        $this->assertTrue(true);
    }
}
