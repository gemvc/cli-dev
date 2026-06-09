<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Support;

use App\Model\UserModel;
use Gemvc\CLI\Commands\DbConnect;
use Gemvc\CLI\Commands\DbMigrate;
use Gemvc\Helper\ProjectHelper;
use PHPUnit\Framework\TestCase;

abstract class FeatureTestCase extends TestCase
{
    protected string $projectRoot;

    protected CommandRunner $runner;

    private ?string $previousCwd = null;

    protected function setUp(): void
    {
        parent::setUp();

        $root = dirname(__DIR__, 2) . '/build/feature-projects';
        if (!is_dir($root)) {
            mkdir($root, 0777, true);
        }
        $this->projectRoot = $root . '/' . uniqid('feature-', true);
        mkdir($this->projectRoot, 0777, true);

        $this->previousCwd = getcwd() ?: null;
        chdir($this->projectRoot);

        $this->seedProject();
        $this->runner = new CommandRunner();

        ProjectHelper::configure($this->projectRoot);
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

        parent::tearDown();
    }

    protected function runCommand(object $command): CliRunResult
    {
        ob_start();
        try {
            $success = $command->execute();
        } finally {
            $output = (string) ob_get_clean();
        }

        return new CliRunResult($success, $output);
    }

    protected function seedProject(): void
    {
        file_put_contents(
            $this->projectRoot . '/composer.json',
            json_encode(['name' => 'test/gemvc-app', 'minimum-stability' => 'stable'], JSON_THROW_ON_ERROR)
        );
        file_put_contents($this->projectRoot . '/composer.lock', '{}');
        file_put_contents(
            $this->projectRoot . '/.env',
            "APP_ENV=dev\nDB_NAME=test_db\nDB_HOST=localhost\nDB_USER=root\nDB_PASSWORD=\n"
        );

        $source = dirname(__DIR__, 2) . '/templates/cli';
        $target = $this->projectRoot . '/templates/cli';
        mkdir($target, 0777, true);
        foreach (glob($source . '/*.template') ?: [] as $file) {
            copy($file, $target . '/' . basename($file));
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
