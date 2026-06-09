<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Feature;

use Gemvc\CLI\CommandCategories;
use Gemvc\CliDev\Tests\Support\FeatureTestCase;

final class CommandDispatchFeatureTest extends FeatureTestCase
{
    public function testAllDevCommandsAreRegistered(): void
    {
        $commands = [];
        foreach (CommandCategories::CATEGORIES as $group) {
            foreach (array_keys($group) as $command) {
                $commands[] = $command;
            }
        }

        $this->assertCount(12, $commands);

        foreach ($commands as $command) {
            $class = CommandCategories::getCommandClass($command);
            $this->assertNotSame('', $class, "Missing class mapping for {$command}");
            $this->assertNotSame('', CommandCategories::getDescription($command));
            $this->assertNotSame('Other', CommandCategories::getCategory($command));
        }
    }

    public function testUnknownCommandIsRejectedByRunner(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->runner->run('does:not-exist');
    }

    public function testCommandExamplesAndUnknownMetadata(): void
    {
        $this->assertSame('Other', CommandCategories::getCategory('unknown:cmd'));
        $this->assertSame('', CommandCategories::getDescription('unknown:cmd'));
        $this->assertSame('', CommandCategories::getCommandClass('init'));

        $examples = CommandCategories::getExamples();
        $this->assertIsArray($examples['create:service']);
        $this->assertSame('vendor/bin/gemvc admin:setadmin', $examples['admin:setadmin']);
    }

    public function testRunnerDispatchesAllCodegenCommands(): void
    {
        $smoke = [
            ['create:service', ['SmokeSvc']],
            ['create:controller', ['SmokeCtl']],
            ['create:model', ['SmokeMdl']],
            ['create:table', ['SmokeTbl']],
            ['create:crud', ['SmokeCrud']],
        ];

        foreach ($smoke as [$command, $args]) {
            $result = $this->runner->run($command, $args);
            $this->assertTrue($result->success, "Expected {$command} to succeed");
        }
    }

    public function testRunnerDispatchesAdminSetpassword(): void
    {
        $result = $this->runCommand(new class([], []) extends \Gemvc\CliDev\Tests\Support\Commands\TestableAdminSetpassword {
            private int $calls = 0;

            protected function readPassword(string $prompt): string
            {
                return (++$this->calls === 1) ? 'dispatch-secret' : 'dispatch-secret';
            }
        });

        $this->assertTrue($result->success);
        $this->assertStringContainsString('ADMIN_PASSWORD="dispatch-secret"', (string) file_get_contents($this->projectRoot . '/.env'));
    }
}
