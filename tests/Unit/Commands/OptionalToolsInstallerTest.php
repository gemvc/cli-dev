<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Unit\Commands;

use Gemvc\CLI\Commands\OptionalToolsInstaller;
use Gemvc\CliDev\Tests\Support\CommandTestCase;
use Gemvc\CliDev\Tests\Support\SuppressesCliExit;

final class OptionalToolsInstallerTest extends CommandTestCase
{
    private string $packagePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->packagePath = dirname(__DIR__, 3);
    }

    public function testExecuteIsNotAllowedDirectly(): void
    {
        $this->assertFalse($this->makeInstaller()->execute());
    }

    public function testSkipsInNonInteractiveMode(): void
    {
        $output = $this->captureOutput(fn () => $this->makeInstaller(true)->offerOptionalTools());
        $this->assertStringContainsString('Skipped optional tools', $output);
    }

    public function testSkipsPhpstanWhenUserDeclines(): void
    {
        $installer = $this->installerWithInput(['n', '3']);
        $output = $this->captureOutput(static fn () => $installer->offerOptionalTools());
        $this->assertStringContainsString('PHPStan installation skipped', $output);
        $this->assertStringContainsString('Testing framework installation skipped', $output);
    }

    public function testInstallPhpstanFailsWithoutComposerJson(): void
    {
        $output = $this->captureOutput(fn () => $this->makeInstaller()->installPhpstan());
        $this->assertStringContainsString('PHPStan installation failed', $output);
    }

    public function testInstallPhpunitCreatesConfig(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', '{}');
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

    public function testCreatePhpunitConfigSkipsExistingFile(): void
    {
        file_put_contents($this->projectRoot . '/phpunit.xml', '<phpunit/>');
        $installer = $this->makeInstaller();
        $this->invokeMethod($installer, 'createPhpunitConfig');
        $this->assertSame('<phpunit/>', file_get_contents($this->projectRoot . '/phpunit.xml'));
    }

    public function testCopyPhpstanConfigWarnsWhenTemplateMissing(): void
    {
        $emptyPackage = $this->projectRoot . '/empty-package';
        mkdir($emptyPackage, 0777, true);
        $installer = new class($this->projectRoot, $emptyPackage) extends OptionalToolsInstaller {
            use SuppressesCliExit;
        };
        $output = $this->captureOutput(fn () => $this->invokeMethod($installer, 'copyPhpstanConfig'));
        $this->assertStringContainsString('PHPStan configuration template not found', $output);
    }

    public function testCopyPhpstanConfigCopiesTemplate(): void
    {
        $packageWithTemplate = $this->projectRoot . '/package-with-template';
        $templateDir = $packageWithTemplate . '/src/startup/common';
        mkdir($templateDir, 0777, true);
        file_put_contents($templateDir . '/phpstan.neon', 'parameters: {}');

        $installer = new class($this->projectRoot, $packageWithTemplate) extends OptionalToolsInstaller {
            use SuppressesCliExit;
        };
        $this->invokeMethod($installer, 'copyPhpstanConfig');
        $this->assertFileExists($this->projectRoot . '/phpstan.neon');
    }

    public function testCreateDirectoryIfNotExists(): void
    {
        $installer = $this->makeInstaller();
        $target = $this->projectRoot . '/nested/dir';
        $this->invokeMethod($installer, 'createDirectoryIfNotExists', [$target]);
        $this->assertDirectoryExists($target);
        $this->invokeMethod($installer, 'createDirectoryIfNotExists', [$target]);
    }

    public function testInvalidTestingFrameworkChoiceShowsWarning(): void
    {
        $installer = $this->installerWithInput(['n', '9']);
        $output = $this->captureOutput(static fn () => $installer->offerOptionalTools());
        $this->assertStringContainsString('Invalid choice', $output);
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
}
