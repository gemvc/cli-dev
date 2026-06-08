<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Unit\Commands;

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
}
