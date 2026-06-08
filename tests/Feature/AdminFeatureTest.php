<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Feature;

use Gemvc\CLI\Commands\AdminSetpassword;
use Gemvc\CliDev\Tests\Support\FeatureTestCase;
use Gemvc\CliDev\Tests\Support\SuppressesCliExit;
use Gemvc\Helper\ProjectHelper;

final class AdminFeatureTest extends FeatureTestCase
{
    public function testSetPasswordUpdatesEnvFileUsingRealProjectHelper(): void
    {
        ProjectHelper::reset();
        ProjectHelper::configure($this->projectRoot);

        $command = new class([], []) extends AdminSetpassword {
            use SuppressesCliExit;

            private int $calls = 0;

            protected function readPassword(string $prompt): string
            {
                return (++$this->calls === 1) ? 'feature-secret' : 'feature-secret';
            }
        };

        ob_start();
        $this->assertTrue($command->execute());
        ob_end_clean();

        $env = (string) file_get_contents($this->projectRoot . '/.env');
        $this->assertStringContainsString('ADMIN_PASSWORD="feature-secret"', $env);
    }

    public function testSetPasswordFailsWhenEnvFileMissing(): void
    {
        unlink($this->projectRoot . '/.env');

        $command = new class([], []) extends AdminSetpassword {
            use SuppressesCliExit;

            protected function readPassword(string $prompt): string
            {
                return 'secret';
            }
        };

        ob_start();
        $this->assertFalse($command->execute());
        ob_end_clean();
    }

    public function testSetPasswordRejectsMismatchedConfirmation(): void
    {
        ProjectHelper::reset();
        ProjectHelper::configure($this->projectRoot);

        $command = new class([], []) extends AdminSetpassword {
            use SuppressesCliExit;

            private int $calls = 0;

            protected function readPassword(string $prompt): string
            {
                return (++$this->calls === 1) ? 'one' : 'two';
            }
        };

        ob_start();
        $this->assertFalse($command->execute());
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Passwords do not match', $output);
    }

    public function testSetPasswordRejectsEmptyPassword(): void
    {
        ProjectHelper::reset();
        ProjectHelper::configure($this->projectRoot);

        $command = new class([], []) extends AdminSetpassword {
            use SuppressesCliExit;

            protected function readPassword(string $prompt): string
            {
                return '';
            }
        };

        ob_start();
        $this->assertFalse($command->execute());
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Password cannot be empty', $output);
    }
}
