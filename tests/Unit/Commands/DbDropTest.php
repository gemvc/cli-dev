<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Unit\Commands;

use Gemvc\CLI\Commands\DbConnect;
use Gemvc\CLI\Commands\DbDrop;
use Gemvc\CliDev\Tests\Support\CommandTestCase;
use Gemvc\CliDev\Tests\Support\PdoMock;
use Gemvc\CliDev\Tests\Support\SuppressesCliExit;

final class DbDropTest extends CommandTestCase
{
    public function testRequiresTableName(): void
    {
        $this->assertFalse($this->makeCommand(DbDrop::class, [])->execute());
    }

    public function testDropsTableWithForceFlag(): void
    {
        $pdo = PdoMock::create($this, [
            'TABLE_TYPE' => 'BASE TABLE',
            'SHOW CREATE TABLE' => [['Create Table' => 'CREATE TABLE `users` (id INT)']],
        ]);
        DbConnect::configure($pdo);

        $this->assertTrue($this->makeCommand(DbDrop::class, ['users', '--force'])->execute());
    }

    public function testDropsViewWithForceFlag(): void
    {
        $execSql = null;
        $pdo = PdoMock::create($this, [
            'TABLE_TYPE' => 'VIEW',
            'SHOW CREATE VIEW' => [['Create View' => 'CREATE VIEW `user_access` AS SELECT 1']],
        ]);
        $pdo->method('exec')->willReturnCallback(static function (string $sql) use (&$execSql): int {
            $execSql = $sql;

            return 0;
        });
        DbConnect::configure($pdo);

        $this->assertTrue($this->makeCommand(DbDrop::class, ['user_access', '--force'])->execute());
        $this->assertIsString($execSql);
        $this->assertStringStartsWith('DROP VIEW', $execSql);
    }

    public function testFailsWhenTableDoesNotExist(): void
    {
        $pdo = PdoMock::create($this, [
            'TABLE_TYPE' => [],
        ]);
        DbConnect::configure($pdo);

        $this->assertFalse($this->makeCommand(DbDrop::class, ['missing', '--force'])->execute());
    }

    public function testFailsWhenConnectionMissing(): void
    {
        DbConnect::configure(null);
        $this->assertFalse($this->makeCommand(DbDrop::class, ['users', '--force'])->execute());
    }

    public function testParseDropArguments(): void
    {
        $command = $this->makeCommand(DbDrop::class, ['Users', '--force']);

        $this->assertSame(
            ['table' => 'users', 'force' => true],
            $this->invokeMethod($command, 'parseDropArguments')
        );
        $this->assertNull($this->invokeMethod($this->makeCommand(DbDrop::class, []), 'parseDropArguments'));
    }

    public function testConfirmDropRejectsNonYes(): void
    {
        $command = new class([], []) extends DbDrop {
            use SuppressesCliExit;

            protected function readConfirmation(): string
            {
                return 'no';
            }
        };

        $this->assertFalse($this->invokeMethod($command, 'confirmDrop', ['users', 'table']));
    }

    public function testRelationExists(): void
    {
        $pdo = PdoMock::create($this, [
            'TABLE_TYPE' => 'BASE TABLE',
        ]);
        $command = $this->makeCommand(DbDrop::class);

        $this->assertTrue($this->invokeMethod($command, 'relationExists', [$pdo, 'test_db', 'users']));
    }

    public function testDropsTableWithForceFlagOnPostgres(): void
    {
        \Gemvc\Helper\ProjectHelper::configure($this->projectRoot, [
            'DB_NAME' => 'test_db',
            'DB_HOST' => 'localhost',
            'DB_DRIVER' => 'pgsql',
        ]);

        $pdo = PdoMock::create($this, [
            'table_type IN' => 'BASE TABLE',
            'information_schema.columns' => [[
                'column_name' => 'id',
                'data_type' => 'integer',
                'is_nullable' => 'NO',
            ]],
        ]);
        DbConnect::configure($pdo);

        $this->assertTrue($this->makeCommand(DbDrop::class, ['users', '--force'])->execute());
    }

    public function testRelationExistsOnPostgresWhenMissing(): void
    {
        \Gemvc\Helper\ProjectHelper::configure($this->projectRoot, [
            'DB_NAME' => 'test_db',
            'DB_HOST' => 'localhost',
            'DB_DRIVER' => 'pgsql',
        ]);
        \Gemvc\Helper\ProjectHelper::loadEnv();

        $pdo = PdoMock::create($this, [
            'table_type IN' => null,
        ]);
        $command = $this->makeCommand(DbDrop::class);

        $this->assertFalse($this->invokeMethod($command, 'relationExists', [$pdo, 'test_db', 'missing']));
    }
}
