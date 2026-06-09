<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Feature;

use Gemvc\CLI\Commands\OptionalToolsInstaller;
use Gemvc\CliDev\Tests\Support\FeatureTestCase;
use Gemvc\CliDev\Tests\Support\SuppressesCliExit;

final class OptionalToolsInstallerFeatureTest extends FeatureTestCase
{
    private string $packagePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->packagePath = dirname(__DIR__, 2);
    }

    public function testOfferOptionalToolsSkipsInNonInteractiveMode(): void
    {
        $installer = new class($this->projectRoot, $this->packagePath, true) extends OptionalToolsInstaller {
            use SuppressesCliExit;
        };

        $output = $this->captureOutput(static fn () => $installer->offerOptionalTools());

        $this->assertStringContainsString('Skipped optional tools', $output);
    }

    public function testInstallPhpunitCreatesProjectTestHarness(): void
    {
        $installer = new class($this->projectRoot, $this->packagePath) extends OptionalToolsInstaller {
            use SuppressesCliExit;

            protected function runComposerCommand(string $command): void
            {
            }
        };

        $installer->installPhpunit();

        $this->assertFileExists($this->projectRoot . '/phpunit.xml');
        $this->assertDirectoryExists($this->projectRoot . '/tests');
    }

    public function testInstallPhpstanCopiesConfigWhenTemplateExists(): void
    {
        $packageWithTemplate = $this->projectRoot . '/package-with-template';
        $templateDir = $packageWithTemplate . '/src/startup/common';
        mkdir($templateDir, 0777, true);
        file_put_contents($templateDir . '/phpstan.neon', 'parameters: {}');
        file_put_contents($this->projectRoot . '/composer.json', '{}');

        $installer = new class($this->projectRoot, $packageWithTemplate) extends OptionalToolsInstaller {
            use SuppressesCliExit;

            protected function runComposerCommand(string $command): void
            {
            }
        };

        $installer->installPhpstan();

        $this->assertFileExists($this->projectRoot . '/phpstan.neon');
    }

    public function testOfferPhpstanSkipsWhenUserDeclines(): void
    {
        $installer = $this->installerWithInput(['n']);

        $output = $this->captureOutput(static fn () => $installer->offerPhpstanInstallation());

        $this->assertStringContainsString('PHPStan installation skipped', $output);
    }

    /**
     * @param array<int, string> $lines
     */
    private function installerWithInput(array $lines): OptionalToolsInstaller
    {
        return new class($this->projectRoot, $this->packagePath, $lines) extends OptionalToolsInstaller {
            use SuppressesCliExit;

            private int $index = 0;

            /** @param array<int, string> $inputLines */
            public function __construct(string $basePath, string $packagePath, private array $inputLines)
            {
                parent::__construct($basePath, $packagePath);
            }

            protected function readStdinLine(): string|false
            {
                return $this->inputLines[$this->index++] ?? '';
            }
        };
    }

    private function captureOutput(callable $callback): string
    {
        ob_start();
        try {
            $callback();
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}
