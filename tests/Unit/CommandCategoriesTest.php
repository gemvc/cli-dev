<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Unit;

use Gemvc\CLI\CommandCategories;
use PHPUnit\Framework\TestCase;

final class CommandCategoriesTest extends TestCase
{
    public function testDevCommandsAreRegistered(): void
    {
        $this->assertSame('CreateService', CommandCategories::getCommandClass('create:service'));
        $this->assertSame('DbInit', CommandCategories::getCommandClass('db:init'));
        $this->assertSame('AdminSetpassword', CommandCategories::getCommandClass('admin:setpassword'));
        $this->assertSame('', CommandCategories::getCommandClass('init'));
        $this->assertSame('', CommandCategories::getCommandClass('db:migrate'));
    }

    public function testCategoriesExcludeCoreInit(): void
    {
        $flat = [];
        foreach (CommandCategories::CATEGORIES as $commands) {
            foreach (array_keys($commands) as $name) {
                $flat[] = $name;
            }
        }

        $this->assertContains('create:crud', $flat);
        $this->assertNotContains('init', $flat);
        $this->assertNotContains('db:migrate', $flat);
    }

    public function testMetadataHelpers(): void
    {
        $this->assertSame('Code Generation', CommandCategories::getCategory('create:model'));
        $this->assertSame('Database (development)', CommandCategories::getCategory('db:list'));
        $this->assertSame('Admin', CommandCategories::getCategory('admin:setadmin'));
        $this->assertSame('Other', CommandCategories::getCategory('unknown'));

        $this->assertStringContainsString('CRUD', CommandCategories::getDescription('create:crud'));
        $this->assertSame('', CommandCategories::getDescription('missing'));

        $examples = CommandCategories::getExamples();
        $this->assertIsArray($examples['create:service']);
        $this->assertSame('vendor/bin/gemvc db:drop users', $examples['db:drop']);
    }
}
