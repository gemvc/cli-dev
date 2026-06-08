<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Unit\Commands;

use Gemvc\CLI\Commands\CreateController;
use Gemvc\CLI\Commands\CreateCrud;
use Gemvc\CLI\Commands\CreateModel;
use Gemvc\CLI\Commands\CreateService;
use Gemvc\CLI\Commands\CreateTable;
use Gemvc\CliDev\Tests\Support\CommandTestCase;

final class CreateCommandsTest extends CommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTemplates();
        $this->seedAppDirectories();
    }

    public function testCreateServiceRequiresName(): void
    {
        $command = $this->makeCommand(CreateService::class, [], []);
        $this->assertFalse($command->execute());
    }

    public function testCreateServiceWithAllFlags(): void
    {
        $command = $this->makeCommand(CreateService::class, ['User', '-cmt'], []);
        $this->assertTrue($command->execute());
        $this->assertFileExists($this->projectRoot . '/app/api/User.php');
        $this->assertFileExists($this->projectRoot . '/app/controller/UserController.php');
        $this->assertFileExists($this->projectRoot . '/app/model/UserModel.php');
        $this->assertFileExists($this->projectRoot . '/app/table/UserTable.php');
    }

    public function testCreateControllerRequiresName(): void
    {
        $command = $this->makeCommand(CreateController::class, [], []);
        $this->assertFalse($command->execute());
    }

    public function testCreateControllerWithModelAndTableFlags(): void
    {
        $command = $this->makeCommand(CreateController::class, ['Order', '-mt'], []);
        $this->assertTrue($command->execute());
        $this->assertFileExists($this->projectRoot . '/app/controller/OrderController.php');
        $this->assertFileExists($this->projectRoot . '/app/model/OrderModel.php');
        $this->assertFileExists($this->projectRoot . '/app/table/OrderTable.php');
    }

    public function testCreateModelRequiresName(): void
    {
        $command = $this->makeCommand(CreateModel::class, [], []);
        $this->assertFalse($command->execute());
    }

    public function testCreateModelWithTableFlag(): void
    {
        $command = $this->makeCommand(CreateModel::class, ['Item', '-t'], []);
        $this->assertTrue($command->execute());
        $this->assertFileExists($this->projectRoot . '/app/model/ItemModel.php');
        $this->assertFileExists($this->projectRoot . '/app/table/ItemTable.php');
    }

    public function testCreateTableRequiresName(): void
    {
        $command = $this->makeCommand(CreateTable::class, [], []);
        $this->assertFalse($command->execute());
    }

    public function testCreateTableSuccess(): void
    {
        $command = $this->makeCommand(CreateTable::class, ['Product'], []);
        $this->assertTrue($command->execute());
        $this->assertFileExists($this->projectRoot . '/app/table/ProductTable.php');
    }

    public function testCreateCrudRequiresName(): void
    {
        $command = $this->makeCommand(CreateCrud::class, [], []);
        $this->assertFalse($command->execute());
    }

    public function testCreateCrudCreatesFullStack(): void
    {
        $command = new class(['Blog'], []) extends CreateCrud {
            use \Gemvc\CliDev\Tests\Support\SuppressesCliExit;
        };
        $this->assertTrue($command->execute());
        $this->assertFileExists($this->projectRoot . '/app/api/Blog.php');
        $this->assertFileExists($this->projectRoot . '/app/controller/BlogController.php');
    }

    public function testRunCrudGenerationUsesDefaultServiceFactory(): void
    {
        $command = new class(['Blog'], []) extends CreateCrud {
            use \Gemvc\CliDev\Tests\Support\SuppressesCliExit;
        };

        $this->assertTrue($this->invokeMethod($command, 'runCrudGeneration'));
        $this->assertFileExists($this->projectRoot . '/app/api/Blog.php');
    }

    public function testCreateServiceFormatsName(): void
    {
        $command = $this->makeCommand(CreateService::class, ['myuser'], []);
        $this->assertTrue($command->execute());
        $this->assertFileExists($this->projectRoot . '/app/api/Myuser.php');
    }
}
