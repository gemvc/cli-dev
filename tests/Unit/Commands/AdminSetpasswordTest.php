<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Unit\Commands;

use Gemvc\CLI\Commands\AdminSetpassword;
use Gemvc\CliDev\Tests\Support\CommandTestCase;
use Gemvc\CliDev\Tests\Support\SuppressesCliExit;

final class AdminSetpasswordTest extends CommandTestCase
{
    public function testFailsWhenEnvFileMissing(): void
    {
        $this->assertFalse($this->makeCommand(AdminSetpassword::class, [], [])->execute());
    }

    public function testSetsAdminPasswordInEnvFile(): void
    {
        file_put_contents($this->projectRoot . '/.env', "APP_ENV=dev\n");

        $command = new class([], []) extends AdminSetpassword {
            use SuppressesCliExit;

            private int $calls = 0;

            protected function readPassword(string $prompt): string
            {
                return (++$this->calls === 1) ? 'secret123' : 'secret123';
            }
        };

        $this->assertTrue($command->execute());
        $env = file_get_contents($this->projectRoot . '/.env');
        $this->assertIsString($env);
        $this->assertStringContainsString('ADMIN_PASSWORD="secret123"', $env);
    }

    public function testRejectsMismatchedPasswords(): void
    {
        file_put_contents($this->projectRoot . '/.env', "APP_ENV=dev\n");

        $command = new class([], []) extends AdminSetpassword {
            use SuppressesCliExit;

            private int $calls = 0;

            protected function readPassword(string $prompt): string
            {
                return (++$this->calls === 1) ? 'one' : 'two';
            }
        };

        $this->assertFalse($command->execute());
    }

    public function testUpdateEnvFileReplacesExistingValue(): void
    {
        $path = $this->projectRoot . '/.env';
        file_put_contents($path, "ADMIN_PASSWORD=\"old\"\n");

        $command = $this->makeCommand(AdminSetpassword::class, [], []);
        $result = $this->invokeMethod($command, 'updateEnvFile', [$path, 'new-pass']);
        $this->assertTrue($result);
        $this->assertStringContainsString('ADMIN_PASSWORD="new-pass"', (string) file_get_contents($path));
    }

    public function testUpdateEnvFileAppendsWhenMissing(): void
    {
        $path = $this->projectRoot . '/.env';
        file_put_contents($path, "APP_ENV=dev\n");

        $command = $this->makeCommand(AdminSetpassword::class, [], []);
        $this->assertTrue($this->invokeMethod($command, 'updateEnvFile', [$path, 'new-pass']));
        $this->assertStringContainsString('ADMIN_PASSWORD="new-pass"', (string) file_get_contents($path));
    }

    public function testUpdateEnvFileFailsForMissingPath(): void
    {
        $command = $this->makeCommand(AdminSetpassword::class, [], []);
        $this->assertFalse($this->invokeMethod($command, 'updateEnvFile', ['/no/such/.env', 'x']));
    }

    public function testUpdateEnvFileAppendsToEndWhenNoAppEnv(): void
    {
        $path = $this->projectRoot . '/.env';
        file_put_contents($path, "OTHER=value\n");

        $command = $this->makeCommand(AdminSetpassword::class, [], []);
        $this->assertTrue($this->invokeMethod($command, 'updateEnvFile', [$path, 'new-pass']));
        $this->assertStringContainsString('ADMIN_PASSWORD="new-pass"', (string) file_get_contents($path));
    }

    public function testUpdateEnvFileFailsWhenWriteFails(): void
    {
        $path = $this->projectRoot . '/.env';
        file_put_contents($path, "APP_ENV=dev\n");
        chmod($path, 0444);

        try {
            $command = $this->makeCommand(AdminSetpassword::class, [], []);
            $this->expectingPhpWarnings(
                fn () => $this->assertFalse($this->invokeMethod($command, 'updateEnvFile', [$path, 'x']))
            );
        } finally {
            chmod($path, 0644);
        }
    }
}
