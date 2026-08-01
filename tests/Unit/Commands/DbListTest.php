<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Unit\Commands;

use Gemvc\CLI\Commands\DbConnect;
use Gemvc\CLI\Commands\DbList;
use Gemvc\CliDev\Tests\Support\CommandTestCase;
use Gemvc\CliDev\Tests\Support\PdoMock;

final class DbListTest extends CommandTestCase
{
    public function testListsTablesAndColumns(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW FULL TABLES' => [
                ['users', 'BASE TABLE'],
            ],
            'SHOW COLUMNS' => [[
                'Field' => 'id',
                'Type' => 'int(11)',
                'Null' => 'NO',
                'Key' => 'PRI',
                'Default' => null,
                'Extra' => 'auto_increment',
            ]],
        ]);
        DbConnect::configure($pdo);

        $output = $this->captureOutput(fn () => $this->makeCommand(DbList::class)->execute());
        $this->assertStringContainsString('Table: users', $output);
        $this->assertStringContainsString('id', $output);
    }

    public function testFailsWithoutDatabaseName(): void
    {
        \Gemvc\Helper\ProjectHelper::configure($this->projectRoot, ['DB_NAME' => '']);

        $this->assertFalse($this->makeCommand(DbList::class)->execute());
    }

    public function testReportsEmptyDatabase(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW FULL TABLES' => [],
        ]);
        DbConnect::configure($pdo);

        $output = $this->captureOutput(function () {
            $this->assertFalse($this->makeCommand(DbList::class)->execute());
        });
        $this->assertStringContainsString('No tables or views found', $output);
    }

    public function testFailsWhenConnectionMissing(): void
    {
        DbConnect::configure(null);
        $this->assertFalse($this->makeCommand(DbList::class)->execute());
    }

    public function testResolveDatabaseName(): void
    {
        $command = $this->makeCommand(DbList::class);
        $this->assertSame('test_db', $this->invokeMethod($command, 'resolveDatabaseName'));
    }

    public function testListsTablesAndColumnsOnPostgres(): void
    {
        \Gemvc\Helper\ProjectHelper::configure($this->projectRoot, [
            'DB_NAME' => 'test_db',
            'DB_HOST' => 'localhost',
            'DB_DRIVER' => 'pgsql',
        ]);

        $pdo = PdoMock::create($this, [
            'information_schema.tables' => [
                ['name' => 'users', 'kind' => 'BASE TABLE'],
            ],
            'information_schema.columns' => [[
                'Field' => 'id',
                'Type' => 'int4',
                'Null' => 'NO',
                'Default' => null,
                'Key' => '',
                'Extra' => '',
            ]],
        ]);
        DbConnect::configure($pdo);

        $output = $this->captureOutput(fn () => $this->makeCommand(DbList::class)->execute());
        $this->assertStringContainsString('users', $output);
        $this->assertStringContainsString('id', $output);
    }

    public function testListsViewsOnMysql(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW FULL TABLES' => [
                ['users', 'BASE TABLE'],
                ['user_access', 'VIEW'],
            ],
            'SHOW COLUMNS' => [[
                'Field' => 'id',
                'Type' => 'int(11)',
                'Null' => 'NO',
                'Key' => 'PRI',
                'Default' => null,
                'Extra' => '',
            ]],
        ]);
        DbConnect::configure($pdo);

        $output = $this->captureOutput(fn () => $this->makeCommand(DbList::class)->execute());
        $this->assertStringContainsString('Tables:', $output);
        $this->assertStringContainsString('Table: users', $output);
        $this->assertStringContainsString('Views:', $output);
        $this->assertStringContainsString('View: user_access', $output);
    }

    public function testListsViewsOnPostgres(): void
    {
        \Gemvc\Helper\ProjectHelper::configure($this->projectRoot, [
            'DB_NAME' => 'test_db',
            'DB_DRIVER' => 'pgsql',
        ]);

        $pdo = PdoMock::create($this, [
            'information_schema.tables' => [
                ['name' => 'users', 'kind' => 'BASE TABLE'],
                ['name' => 'user_access', 'kind' => 'VIEW'],
            ],
            'information_schema.columns' => [[
                'Field' => 'id',
                'Type' => 'int4',
                'Null' => 'NO',
                'Default' => null,
                'Key' => '',
                'Extra' => '',
            ]],
        ]);
        DbConnect::configure($pdo);

        $output = $this->captureOutput(fn () => $this->makeCommand(DbList::class)->execute());
        $this->assertStringContainsString('View: user_access', $output);
        $this->assertStringContainsString('Table: users', $output);
    }

    public function testListsViewsOnSqlite(): void
    {
        \Gemvc\Helper\ProjectHelper::configure($this->projectRoot, [
            'DB_NAME' => 'test_db',
            'DB_DRIVER' => 'sqlite',
        ]);

        $pdo = PdoMock::create($this, [
            'sqlite_master' => [
                ['name' => 'users', 'kind' => 'table'],
                ['name' => 'user_access', 'kind' => 'view'],
            ],
            'PRAGMA table_info' => [[
                'name' => 'id',
                'type' => 'INTEGER',
                'notnull' => 1,
                'pk' => 1,
                'dflt_value' => null,
            ]],
        ]);
        DbConnect::configure($pdo);

        $output = $this->captureOutput(fn () => $this->makeCommand(DbList::class)->execute());
        $this->assertStringContainsString('View: user_access', $output);
    }

    public function testFormatColumnLine(): void
    {
        $command = $this->makeCommand(DbList::class);
        $line = $this->invokeMethod($command, 'formatColumnLine', [[
            'Field' => 'id',
            'Type' => 'int(11)',
            'Null' => 'NO',
            'Key' => 'PRI',
            'Default' => null,
            'Extra' => 'auto_increment',
        ]]);

        $this->assertStringContainsString('id:', $line);
        $this->assertStringContainsString('int(11)', $line);
        $this->assertStringContainsString('NOT NULL', $line);
        $this->assertStringContainsString('(PRI)', $line);
        $this->assertStringContainsString('auto_increment', $line);
    }
}
