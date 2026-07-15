<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Support;

use App\Model\UserModel;
use Gemvc\CLI\Commands\DbConnect;
use Gemvc\CLI\Commands\DbMigrate;
use Gemvc\CLI\Commands\OptionalToolsInstaller;
use Gemvc\Helper\ProjectHelper;
use PHPUnit\Framework\TestCase;

abstract class CommandTestCase extends TestCase
{
    protected string $projectRoot;

    private ?string $previousCwd = null;

    protected function setUp(): void
    {
        parent::setUp();

        $root = dirname(__DIR__, 2) . '/build/test-projects';
        if (!is_dir($root)) {
            mkdir($root, 0777, true);
        }
        $this->projectRoot = $root . '/' . uniqid('run-', true);
        mkdir($this->projectRoot, 0777, true);

        $this->previousCwd = getcwd() ?: null;
        chdir($this->projectRoot);

        ProjectHelper::configure($this->projectRoot, ['DB_NAME' => 'test_db', 'DB_HOST' => 'localhost']);
        DbConnect::reset();
        DbConnect::configure(null, null);
        DbMigrate::reset();
        DbMigrate::enableTestMode();
        UserModel::reset();
    }

    protected function tearDown(): void
    {
        if ($this->previousCwd !== null) {
            chdir($this->previousCwd);
        }

        $this->removeDirectory($this->projectRoot);
        ProjectHelper::reset();
        DbConnect::reset();
        DbMigrate::reset();
        UserModel::reset();
        unset($_ENV['DB_DRIVER']);
        putenv('DB_DRIVER');

        parent::tearDown();
    }

    protected function seedTemplates(): void
    {
        $source = dirname(__DIR__, 2) . '/templates/cli';
        $target = $this->projectRoot . '/templates/cli';
        mkdir($target, 0777, true);

        foreach (glob($source . '/*.template') ?: [] as $file) {
            copy($file, $target . '/' . basename($file));
        }
    }

    protected function seedAppDirectories(): void
    {
        foreach (['app/api', 'app/controller', 'app/model', 'app/table'] as $directory) {
            mkdir($this->projectRoot . '/' . $directory, 0777, true);
        }
    }

    /**
     * @param class-string $class
     * @param array<int, mixed> $args
     * @param array<string, mixed> $options
     */
    protected function makeCommand(string $class, array $args = [], array $options = []): object
    {
        return CommandFactory::make($class, $args, $options);
    }

    protected function makeInstaller(bool $nonInteractive = false): OptionalToolsInstaller
    {
        return new class($this->projectRoot, dirname(__DIR__, 2), $nonInteractive) extends OptionalToolsInstaller {
            use SuppressesCliExit;

            public function __construct(string $basePath, string $packagePath, bool $nonInteractive = false)
            {
                parent::__construct($basePath, $packagePath, $nonInteractive);
            }
        };
    }

    protected function captureOutput(callable $callback): string
    {
        ob_start();
        try {
            $callback();
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }

    /**
     * @param array<int, mixed> $args
     */
    protected function invokeMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);

        return $reflection->invoke($object, ...$args);
    }

    protected function setProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setValue($object, $value);
    }

    /**
     * Run code that is expected to trigger PHP warnings (e.g. intentional I/O failures).
     */
    protected function expectingPhpWarnings(callable $callback): mixed
    {
        set_error_handler(static function (int $severity): bool {
            return $severity === E_WARNING;
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
