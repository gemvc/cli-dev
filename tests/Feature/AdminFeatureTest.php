<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Feature;

use Gemvc\CliDev\Tests\Support\Commands\TestableAdminSetpassword;
use Gemvc\CliDev\Tests\Support\FeatureTestCase;
use Gemvc\Helper\ProjectHelper;

final class AdminFeatureTest extends FeatureTestCase
{
    public function testSetPasswordUpdatesEnvFileUsingRealProjectHelper(): void
    {
        ProjectHelper::reset();
        ProjectHelper::configure($this->projectRoot);

        $result = $this->runCommand(new class([], []) extends TestableAdminSetpassword {
            private int $calls = 0;

            protected function readPassword(string $prompt): string
            {
                return (++$this->calls === 1) ? 'feature-secret' : 'feature-secret';
            }
        });

        $this->assertTrue($result->success);

        $env = (string) file_get_contents($this->projectRoot . '/.env');
        $this->assertStringContainsString('ADMIN_PASSWORD="feature-secret"', $env);
    }

    public function testSetPasswordFailsWhenEnvFileMissing(): void
    {
        unlink($this->projectRoot . '/.env');

        $result = $this->runCommand(new class([], []) extends TestableAdminSetpassword {
            protected function readPassword(string $prompt): string
            {
                return 'secret';
            }
        });

        $this->assertFalse($result->success);
    }

    public function testSetPasswordRejectsMismatchedConfirmation(): void
    {
        ProjectHelper::reset();
        ProjectHelper::configure($this->projectRoot);

        $result = $this->runCommand(new class([], []) extends TestableAdminSetpassword {
            private int $calls = 0;

            protected function readPassword(string $prompt): string
            {
                return (++$this->calls === 1) ? 'one' : 'two';
            }
        });

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Passwords do not match', $result->output);
    }

    public function testSetPasswordRejectsEmptyPassword(): void
    {
        ProjectHelper::reset();
        ProjectHelper::configure($this->projectRoot);

        $result = $this->runCommand(new class([], []) extends TestableAdminSetpassword {
            protected function readPassword(string $prompt): string
            {
                return '';
            }
        });

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Password cannot be empty', $result->output);
    }

    public function testSetPasswordViaCommandRunner(): void
    {
        ProjectHelper::reset();
        ProjectHelper::configure($this->projectRoot);

        $result = $this->runCommand(new class([], []) extends TestableAdminSetpassword {
            private int $calls = 0;

            protected function readPassword(string $prompt): string
            {
                return (++$this->calls === 1) ? 'runner-secret' : 'runner-secret';
            }
        });

        $this->assertTrue($result->success);
        $this->assertStringContainsString('ADMIN_PASSWORD="runner-secret"', (string) file_get_contents($this->projectRoot . '/.env'));
    }

    public function testSetPasswordReplacesExistingAdminPassword(): void
    {
        ProjectHelper::reset();
        ProjectHelper::configure($this->projectRoot);
        file_put_contents($this->projectRoot . '/.env', "APP_ENV=dev\nADMIN_PASSWORD=\"old\"\n");

        $result = $this->runCommand(new class([], []) extends TestableAdminSetpassword {
            private int $calls = 0;

            protected function readPassword(string $prompt): string
            {
                return (++$this->calls === 1) ? 'new-secret' : 'new-secret';
            }
        });

        $this->assertTrue($result->success);
        $this->assertStringContainsString('ADMIN_PASSWORD="new-secret"', (string) file_get_contents($this->projectRoot . '/.env'));
        $this->assertStringNotContainsString('old', (string) file_get_contents($this->projectRoot . '/.env'));
    }
}
