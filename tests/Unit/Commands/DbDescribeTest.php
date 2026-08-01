<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Unit\Commands;

use Gemvc\CLI\Commands\DbConnect;
use Gemvc\CLI\Commands\DbDescribe;
use Gemvc\CliDev\Tests\Support\CommandTestCase;
use Gemvc\CliDev\Tests\Support\PdoMock;

final class DbDescribeTest extends CommandTestCase
{
    public function testRequiresTableName(): void
    {
        $this->assertFalse($this->makeCommand(DbDescribe::class, [])->execute());
    }

    public function testDescribesTableWithFullMetadata(): void
    {
        $pdo = PdoMock::create($this, [
            'TABLE_TYPE' => 'BASE TABLE',
            'SHOW COLUMNS' => [[
                'Field' => 'id',
                'Type' => 'int(11)',
                'Null' => 'NO',
                'Key' => 'PRI',
                'Default' => null,
                'Extra' => 'auto_increment',
            ], [
                'Field' => 'email',
                'Type' => 'varchar(255)',
                'Null' => 'NO',
                'Key' => 'UNI',
                'Default' => null,
                'Extra' => '',
            ]],
            'SHOW INDEX' => [[
                'Key_name' => 'PRIMARY',
                'Non_unique' => 0,
                'Index_type' => 'BTREE',
                'Column_name' => 'id',
                'Sub_part' => null,
            ]],
            'KEY_COLUMN_USAGE' => [[
                'CONSTRAINT_NAME' => 'fk_role',
                'COLUMN_NAME' => 'role_id',
                'REFERENCED_TABLE_NAME' => 'roles',
                'REFERENCED_COLUMN_NAME' => 'id',
            ]],
            'REFERENTIAL_CONSTRAINTS' => [[
                'CONSTRAINT_NAME' => 'fk_role',
                'DELETE_RULE' => 'CASCADE',
                'UPDATE_RULE' => 'RESTRICT',
            ]],
            'TABLE_ROWS' => [[
                'row_count' => 10,
                'data_size' => 2048,
                'index_size' => 1024,
                'total_size' => 3072,
                'next_auto_increment' => 11,
            ]],
            'ENGINE' => [[
                'ENGINE' => 'InnoDB',
                'TABLE_COLLATION' => 'utf8mb4_unicode_ci',
                'CREATE_TIME' => '2026-01-01 00:00:00',
                'UPDATE_TIME' => '2026-06-01 00:00:00',
                'TABLE_COMMENT' => 'Users table',
            ]],
        ]);
        DbConnect::configure($pdo);

        $output = $this->captureOutput(fn () => $this->makeCommand(DbDescribe::class, ['users'])->execute());
        $this->assertStringContainsString('users', strtolower($output));
        $this->assertStringContainsString('TABLE:', $output);
    }

    public function testDescribesTableWithFullMetadataOnPostgres(): void
    {
        \Gemvc\Helper\ProjectHelper::configure($this->projectRoot, [
            'DB_NAME' => 'test_db',
            'DB_HOST' => 'localhost',
            'DB_DRIVER' => 'pgsql',
        ]);

        $pdo = PdoMock::create($this, [
            'table_type IN' => 'BASE TABLE',
            'reltuples' => [[
                'row_count' => 5,
                'data_size' => 0,
                'index_size' => 0,
                'total_size' => 0,
                'next_auto_increment' => null,
            ]],
            'current_setting' => [[
                'ENGINE' => 'PostgreSQL',
                'TABLE_COLLATION' => '160000',
                'CREATE_TIME' => null,
                'UPDATE_TIME' => null,
                'TABLE_COMMENT' => null,
            ]],
            'pg_index' => [[
                'Key_name' => 'users_pkey',
                'Non_unique' => 0,
                'Column_name' => 'id',
                'Sub_part' => null,
                'Index_type' => 'PRIMARY',
            ]],
            'key_column_usage' => [],
            'information_schema.columns' => [[
                'Field' => 'id',
                'Type' => 'int4',
                'Null' => 'NO',
                'Default' => null,
                'Ordinal_Position' => 1,
            ]],
        ]);
        DbConnect::configure($pdo);

        $output = $this->captureOutput(fn () => $this->makeCommand(DbDescribe::class, ['users'])->execute());
        $this->assertStringContainsString('users', strtolower($output));
    }

    public function testHandlesMissingTable(): void
    {
        $pdo = PdoMock::create($this, [
            'TABLE_TYPE' => [],
        ]);
        DbConnect::configure($pdo);

        $this->assertFalse($this->makeCommand(DbDescribe::class, ['missing'])->execute());
    }

    public function testHandlesEmptySections(): void
    {
        $pdo = PdoMock::create($this, [
            'TABLE_TYPE' => 'BASE TABLE',
            'SHOW COLUMNS' => [],
            'SHOW INDEX' => [],
            'KEY_COLUMN_USAGE' => [],
            'TABLE_ROWS' => false,
            'ENGINE' => false,
        ]);
        DbConnect::configure($pdo);

        $this->assertTrue($this->makeCommand(DbDescribe::class, ['empty_tbl'])->execute());
    }

    public function testDescribesSqliteView(): void
    {
        \Gemvc\Helper\ProjectHelper::configure($this->projectRoot, [
            'DB_NAME' => 'test_db',
            'DB_DRIVER' => 'sqlite',
        ]);

        $test = $this;
        $pdo = PdoMock::create($this, [
            'sqlite_master' => static function (string $sql) use ($test) {
                if (stripos($sql, 'SELECT sql') !== false) {
                    return PdoMock::statement($test, 'CREATE VIEW user_access AS SELECT 1 AS user_id');
                }

                return PdoMock::statement($test, 'view');
            },
            'PRAGMA table_info' => [[
                'name' => 'user_id',
                'type' => 'INTEGER',
                'notnull' => 0,
                'pk' => 0,
                'dflt_value' => null,
            ]],
        ]);
        DbConnect::configure($pdo);

        $output = $this->captureOutput(fn () => $this->makeCommand(DbDescribe::class, ['user_access'])->execute());
        $this->assertStringContainsString('VIEW:', $output);
        $this->assertStringContainsString('VIEW DEFINITION', $output);
        $this->assertStringContainsString('CREATE VIEW', $output);
        $this->assertStringContainsString('No indexes found', $output);
    }

    public function testDescribesMysqlView(): void
    {
        $pdo = PdoMock::create($this, [
            'TABLE_TYPE' => 'VIEW',
            'SHOW COLUMNS' => [[
                'Field' => 'user_id',
                'Type' => 'int(11)',
                'Null' => 'YES',
                'Key' => '',
                'Default' => null,
                'Extra' => '',
            ]],
            'SHOW CREATE VIEW' => [[
                'Create View' => 'CREATE VIEW `user_access` AS SELECT 1 AS user_id',
            ]],
            'TABLE_ROWS' => false,
            'ENGINE' => false,
        ]);
        DbConnect::configure($pdo);

        $output = $this->captureOutput(fn () => $this->makeCommand(DbDescribe::class, ['user_access'])->execute());
        $this->assertStringContainsString('VIEW:', $output);
        $this->assertStringContainsString('VIEW DEFINITION', $output);
        $this->assertStringContainsString('No indexes found', $output);
        $this->assertStringContainsString('No foreign keys found', $output);
    }

    public function testFormatBytesViaReflection(): void
    {
        $command = $this->makeCommand(DbDescribe::class, []);
        $this->assertSame('1 KB', $this->invokeMethod($command, 'formatBytes', [1024]));
        $this->assertSame('1 B', $this->invokeMethod($command, 'formatBytes', [1]));
    }

    public function testTruncateLongCellContent(): void
    {
        $command = $this->makeCommand(DbDescribe::class, []);
        $truncated = $this->invokeMethod($command, 'truncateString', [str_repeat('字', 40), 10]);
        $this->assertLessThanOrEqual(13, mb_strlen($truncated));
    }

    public function testParseTableArgument(): void
    {
        $command = $this->makeCommand(DbDescribe::class, ['users']);

        $this->assertSame('users', $this->invokeMethod($command, 'parseTableArgument'));
        $this->assertNull($this->invokeMethod($this->makeCommand(DbDescribe::class, []), 'parseTableArgument'));
    }

    public function testFetchColumns(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW COLUMNS' => [[
                'Field' => 'id',
                'Type' => 'int(11)',
                'Null' => 'NO',
                'Key' => 'PRI',
                'Default' => null,
                'Extra' => '',
            ]],
        ]);
        $command = $this->makeCommand(DbDescribe::class, []);

        $columns = $this->invokeMethod($command, 'fetchColumns', [$pdo, 'users']);
        $this->assertIsArray($columns);
        $this->assertSame('id', $columns[0]['Field']);
    }

    public function testPadString(): void
    {
        $command = $this->makeCommand(DbDescribe::class, []);

        $this->assertSame('id  ', $this->invokeMethod($command, 'padString', ['id', 4]));
    }
}
