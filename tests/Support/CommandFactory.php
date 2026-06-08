<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Support;

use Gemvc\CLI\Commands\AdminSetpassword;
use Gemvc\CLI\Commands\CreateController;
use Gemvc\CLI\Commands\CreateCrud;
use Gemvc\CLI\Commands\CreateModel;
use Gemvc\CLI\Commands\CreateService;
use Gemvc\CLI\Commands\CreateTable;
use Gemvc\CLI\Commands\DbDescribe;
use Gemvc\CLI\Commands\DbDrop;
use Gemvc\CLI\Commands\DbInit;
use Gemvc\CLI\Commands\DbList;
use Gemvc\CLI\Commands\DbUnique;
use Gemvc\CLI\Commands\SetAdmin;
use Gemvc\CliDev\Tests\Support\Commands\TestableAdminSetpassword;
use Gemvc\CliDev\Tests\Support\Commands\TestableCreateController;
use Gemvc\CliDev\Tests\Support\Commands\TestableCreateCrud;
use Gemvc\CliDev\Tests\Support\Commands\TestableCreateCrudBase;
use Gemvc\CliDev\Tests\Support\Commands\TestableCreateModel;
use Gemvc\CliDev\Tests\Support\Commands\TestableCreateService;
use Gemvc\CliDev\Tests\Support\Commands\TestableCreateTable;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbDescribe;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbDrop;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbInit;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbList;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbUnique;
use Gemvc\CliDev\Tests\Support\Commands\TestableSetAdmin;

final class CommandFactory
{
    /** @var array<class-string, class-string> */
    private const MAP = [
        CreateService::class => TestableCreateService::class,
        CreateController::class => TestableCreateController::class,
        CreateModel::class => TestableCreateModel::class,
        CreateTable::class => TestableCreateTable::class,
        CreateCrud::class => TestableCreateCrudBase::class,
        DbInit::class => TestableDbInit::class,
        DbList::class => TestableDbList::class,
        DbDescribe::class => TestableDbDescribe::class,
        DbDrop::class => TestableDbDrop::class,
        DbUnique::class => TestableDbUnique::class,
        AdminSetpassword::class => TestableAdminSetpassword::class,
        SetAdmin::class => TestableSetAdmin::class,
    ];

    /**
     * @param class-string $class
     * @param array<int, mixed> $args
     * @param array<string, mixed> $options
     */
    public static function make(string $class, array $args = [], array $options = []): object
    {
        $fqcn = ltrim($class, '\\');
        $testable = self::MAP[$fqcn] ?? null;

        if ($testable === null) {
            throw new \InvalidArgumentException("No testable command registered for {$fqcn}");
        }

        return new $testable($args, $options);
    }
}
