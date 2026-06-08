<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Unit\Commands;

use Gemvc\CLI\Commands\DbConnect;
use Gemvc\CLI\Commands\DbUnique;
use Gemvc\CliDev\Tests\Support\CommandTestCase;
use Gemvc\CliDev\Tests\Support\PdoMock;
use PHPUnit\Framework\MockObject\MockObject;

final class DbUniqueTest extends CommandTestCase
{
    public function testRequiresArgument(): void
    {
        $this->assertFalse($this->makeCommand(DbUnique::class, [])->execute());
    }

    public function testRejectsInvalidFormat(): void
    {
        $this->assertFalse($this->makeCommand(DbUnique::class, ['invalid'])->execute());
    }

    public function testAddsUniqueConstraint(): void
    {
        /** @var \PDO&MockObject $pdo */
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $duplicateStmt = PdoMock::statement($this, []);
        $pdo->method('query')->willReturn($duplicateStmt);
        $pdo->method('exec')->willReturn(1);
        DbConnect::configure($pdo);

        $this->assertTrue($this->makeCommand(DbUnique::class, ['users/email'])->execute());
    }

    public function testAbortsWhenDuplicatesExist(): void
    {
        /** @var \PDO&MockObject $pdo */
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $duplicateStmt = PdoMock::statement($this, [['email' => 'a@b.com', 'cnt' => 2]]);
        $pdo->method('query')->willReturn($duplicateStmt);
        DbConnect::configure($pdo);

        $this->assertFalse($this->makeCommand(DbUnique::class, ['users/email'])->execute());
    }

    public function testHandlesPdoExceptionOnAlter(): void
    {
        /** @var \PDO&MockObject $pdo */
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $duplicateStmt = PdoMock::statement($this, []);
        $pdo->method('query')->willReturn($duplicateStmt);
        $pdo->method('exec')->willThrowException(new \PDOException('duplicate key'));
        DbConnect::configure($pdo);

        $this->assertFalse($this->makeCommand(DbUnique::class, ['users/email,name'])->execute());
    }

    public function testFailsWhenConnectionMissing(): void
    {
        DbConnect::configure(null);
        $this->assertFalse($this->makeCommand(DbUnique::class, ['users/email'])->execute());
    }
}
