<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Unit\Commands;

use Gemvc\CLI\Commands\DevGenerator;
use Gemvc\CliDev\Tests\Support\CommandTestCase;

final class DevGeneratorTest extends CommandTestCase
{
    public function testGetTemplatePrefersProjectOverride(): void
    {
        $this->seedTemplates();
        file_put_contents($this->projectRoot . '/templates/cli/service.template', 'PROJECT {$serviceName}');

        $generator = new class([], []) extends DevGenerator {
            public function execute(): bool
            {
                return true;
            }

            public function loadTemplate(string $name): string
            {
                return $this->getTemplate($name);
            }
        };
        $this->setProperty($generator, 'basePath', $this->projectRoot);

        $this->assertSame('PROJECT {$serviceName}', $generator->loadTemplate('service'));
        $this->assertSame(
            'PROJECT User',
            $this->invokeMethod($generator, 'replaceTemplateVariables', ['PROJECT {$serviceName}', ['serviceName' => 'User']])
        );
    }

    public function testGetTemplateUsesPackageTemplatesWhenProjectMissing(): void
    {
        $generator = new class([], []) extends DevGenerator {
            public function execute(): bool
            {
                return true;
            }

            public function loadTemplate(string $name): string
            {
                $this->basePath = dirname(__DIR__, 4);

                return $this->getTemplate($name);
            }
        };

        $content = $generator->loadTemplate('service');
        $this->assertStringContainsString('{$serviceName}', $content);
    }

    public function testGetCliDevInstallPath(): void
    {
        $generator = new class([], []) extends DevGenerator {
            public function execute(): bool
            {
                return true;
            }
        };

        $path = $this->invokeMethod($generator, 'getCliDevInstallPath');
        $this->assertTrue($path === null || is_string($path));
    }

    public function testGetTemplateThrowsWhenMissing(): void
    {
        $generator = new class([], []) extends DevGenerator {
            public function execute(): bool
            {
                return true;
            }
        };

        $this->setProperty($generator, 'basePath', $this->projectRoot);

        $this->expectException(\RuntimeException::class);
        $this->invokeMethod($generator, 'getTemplate', ['missing']);
    }
}
