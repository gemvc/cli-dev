<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Support;

use Gemvc\CLI\CommandCategories;
use Gemvc\CliDev\Tests\Support\Commands\TestableAdminSetpassword;
use Gemvc\CliDev\Tests\Support\Commands\TestableCreateController;
use Gemvc\CliDev\Tests\Support\Commands\TestableCreateCrud;
use Gemvc\CliDev\Tests\Support\Commands\TestableCreateModel;
use Gemvc\CliDev\Tests\Support\Commands\TestableCreateService;
use Gemvc\CliDev\Tests\Support\Commands\TestableCreateTable;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbDescribe;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbDrop;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbInit;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbList;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbUnique;
use Gemvc\CliDev\Tests\Support\Commands\TestableSetAdmin;

final class CommandRunner
{
    /**
     * @param array<int, mixed> $args
     * @param array<string, mixed> $options
     */
    public function run(string $commandName, array $args = [], array $options = []): CliRunResult
    {
        $shortClass = CommandCategories::getCommandClass($commandName);
        if ($shortClass === '') {
            throw new \InvalidArgumentException("Unknown command: {$commandName}");
        }

        $command = $this->createCommand($shortClass, $args, $options);

        ob_start();
        try {
            $success = $command->execute();
        } finally {
            $output = (string) ob_get_clean();
        }

        return new CliRunResult($success, $output);
    }

    /**
     * @param array<int, mixed> $args
     * @param array<string, mixed> $options
     */
    private function createCommand(string $shortClass, array $args, array $options): object
    {
        $class = match ($shortClass) {
            'CreateService' => TestableCreateService::class,
            'CreateController' => TestableCreateController::class,
            'CreateModel' => TestableCreateModel::class,
            'CreateTable' => TestableCreateTable::class,
            'CreateCrud' => TestableCreateCrud::class,
            'DbInit' => TestableDbInit::class,
            'DbList' => TestableDbList::class,
            'DbDescribe' => TestableDbDescribe::class,
            'DbDrop' => TestableDbDrop::class,
            'DbUnique' => TestableDbUnique::class,
            'AdminSetpassword' => TestableAdminSetpassword::class,
            'SetAdmin' => TestableSetAdmin::class,
            default => throw new \InvalidArgumentException("No feature-test binding for {$shortClass}"),
        };

        return new $class($args, $options);
    }
}
