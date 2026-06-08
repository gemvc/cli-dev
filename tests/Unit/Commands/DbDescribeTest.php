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
            'SHOW TABLES FROM' => [['Tables_in_test_db (users)' => 'users']],
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
    }

    public function testHandlesMissingTable(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW TABLES FROM' => [],
        ]);
        DbConnect::configure($pdo);

        $this->assertFalse($this->makeCommand(DbDescribe::class, ['missing'])->execute());
    }

    public function testHandlesEmptySections(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW TABLES FROM' => [['Tables_in_test_db (empty_tbl)' => 'empty_tbl']],
            'SHOW COLUMNS' => [],
            'SHOW INDEX' => [],
            'KEY_COLUMN_USAGE' => [],
            'TABLE_ROWS' => false,
            'ENGINE' => false,
        ]);
        DbConnect::configure($pdo);

        $this->assertTrue($this->makeCommand(DbDescribe::class, ['empty_tbl'])->execute());
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
}
