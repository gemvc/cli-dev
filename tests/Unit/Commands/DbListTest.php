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
            'SHOW TABLES' => ['users'],
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
        $this->assertStringContainsString('users', $output);
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
            'SHOW TABLES' => [],
        ]);
        DbConnect::configure($pdo);

        $this->assertFalse($this->makeCommand(DbList::class)->execute());
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
