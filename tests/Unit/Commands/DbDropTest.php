<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Unit\Commands;

use Gemvc\CLI\Commands\DbConnect;
use Gemvc\CLI\Commands\DbDrop;
use Gemvc\CliDev\Tests\Support\CommandTestCase;
use Gemvc\CliDev\Tests\Support\PdoMock;

final class DbDropTest extends CommandTestCase
{
    public function testRequiresTableName(): void
    {
        $this->assertFalse($this->makeCommand(DbDrop::class, [])->execute());
    }

    public function testDropsTableWithForceFlag(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW TABLES LIKE' => [['Tables_in_test_db (users)' => 'users']],
            'SHOW CREATE TABLE' => [['Create Table' => 'CREATE TABLE `users` (id INT)']],
        ]);
        DbConnect::configure($pdo);

        $this->assertTrue($this->makeCommand(DbDrop::class, ['users', '--force'])->execute());
    }

    public function testFailsWhenTableDoesNotExist(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW TABLES LIKE' => [],
        ]);
        DbConnect::configure($pdo);

        $this->assertFalse($this->makeCommand(DbDrop::class, ['missing', '--force'])->execute());
    }

    public function testFailsWhenConnectionMissing(): void
    {
        DbConnect::configure(null);
        $this->assertFalse($this->makeCommand(DbDrop::class, ['users', '--force'])->execute());
    }
}
