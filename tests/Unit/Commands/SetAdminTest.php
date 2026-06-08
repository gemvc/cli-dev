<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Unit\Commands;

use App\Model\UserModel;
use Gemvc\CLI\Commands\DbConnect;
use Gemvc\CLI\Commands\DbInit;
use Gemvc\CLI\Commands\DbMigrate;
use Gemvc\CLI\Commands\SetAdmin;
use Gemvc\CliDev\Tests\Support\CommandTestCase;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbInit;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbMigrate;
use Gemvc\CliDev\Tests\Support\PdoMock;
use Gemvc\CliDev\Tests\Support\SuppressesCliExit;
use Gemvc\Helper\ProjectHelper;

final class SetAdminTest extends CommandTestCase
{
    public function testCreatesFirstAdminUser(): void
    {
        $pdo = $this->pdoWithUsersTable(userCount: 0);
        DbConnect::configure($pdo, $pdo);
        UserModel::$response = (object) [
            'response_code' => 201,
            'message' => 'created',
            'service_message' => 'ok',
        ];

        $this->assertTrue($this->interactiveSetAdmin()->execute());
    }

    public function testRejectsWhenUsersAlreadyExist(): void
    {
        $pdo = $this->pdoWithUsersTable(userCount: 2);
        DbConnect::configure($pdo, $pdo);

        $this->assertFalse($this->interactiveSetAdmin()->execute());
    }

    public function testHandlesForbiddenResponse(): void
    {
        $pdo = $this->pdoWithUsersTable(userCount: 0);
        DbConnect::configure($pdo, $pdo);
        UserModel::$response = (object) [
            'response_code' => 403,
            'message' => 'Admin exists',
        ];

        $this->assertFalse($this->interactiveSetAdmin()->execute());
    }

    public function testHandlesGenericFailureResponse(): void
    {
        $pdo = $this->pdoWithUsersTable(userCount: 0);
        DbConnect::configure($pdo, $pdo);
        UserModel::$response = (object) [
            'response_code' => 500,
            'message' => 'fail',
            'service_message' => 'db error',
        ];

        $this->assertFalse($this->interactiveSetAdmin()->execute());
    }

    public function testUsesLocalhostWhenDockerHostConfigured(): void
    {
        ProjectHelper::configure($this->projectRoot, [
            'DB_NAME' => 'test_db',
            'DB_HOST' => 'mysql',
        ]);

        $pdo = $this->pdoWithUsersTable(userCount: 0);
        DbConnect::configure($pdo, $pdo);

        $this->assertTrue($this->interactiveSetAdmin()->execute());
        $this->assertSame('localhost', $_ENV['DB_HOST']);
    }

    public function testInitializesDatabaseWhenMissing(): void
    {
        $rootPdo = PdoMock::create($this, [
            'SCHEMA_NAME' => [],
        ]);
        $appPdo = $this->pdoWithUsersTable(userCount: 0);
        DbConnect::configure($appPdo, $rootPdo);

        $command = new class extends SetAdmin {
            use SuppressesCliExit;

            private int $step = 0;

            protected function newDbInit(): DbInit
            {
                return new TestableDbInit();
            }

            protected function readInput(string $prompt): string
            {
                if (str_contains($prompt, 'initialize')) {
                    return 'y';
                }

                return ['Admin', 'admin@example.com'][$this->step++] ?? 'Admin';
            }

            protected function readPassword(string $prompt): string
            {
                return 'password123';
            }
        };

        $this->assertTrue($command->execute());
    }

    public function testMigratesUserTableWhenMissing(): void
    {
        $pdo = PdoMock::create($this, [
            'SCHEMA_NAME' => [['SCHEMA_NAME' => 'test_db']],
            'table_schema = ? AND table_name' => [],
            'FROM users' => [['count' => 0]],
        ]);
        DbConnect::configure($pdo, $pdo);
        DbMigrate::$executeResult = true;

        $command = new class extends SetAdmin {
            use SuppressesCliExit;

            private int $step = 0;

            protected function newDbMigrate(array $args): DbMigrate
            {
                return new TestableDbMigrate($args);
            }

            protected function readInput(string $prompt): string
            {
                if (str_contains($prompt, 'migrate')) {
                    return 'y';
                }

                return ['Admin', 'admin@example.com'][$this->step++] ?? 'Admin';
            }

            protected function readPassword(string $prompt): string
            {
                return 'password123';
            }
        };

        $this->assertTrue($command->execute());
    }

    public function testFailsWhenRootConnectionUnavailable(): void
    {
        DbConnect::configure(null, null);
        $this->assertFalse((new class extends SetAdmin {
            use SuppressesCliExit;
        })->execute());
    }

    public function testPromptAdminDetailsCollectsInput(): void
    {
        $command = new class extends SetAdmin {
            use SuppressesCliExit;

            private int $step = 0;

            protected function readInput(string $prompt): string
            {
                return match ($this->step++) {
                    0 => 'Admin',
                    1 => 'admin@example.com',
                    default => 'Admin',
                };
            }

            protected function readPassword(string $prompt): string
            {
                return 'password123';
            }
        };

        $details = $this->invokeMethod($command, 'promptAdminDetails');
        $this->assertSame(
            ['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password123'],
            $details
        );
    }

    public function testCreateAdminUserReturnsTrueOnSuccess(): void
    {
        UserModel::$response = (object) [
            'response_code' => 201,
            'message' => 'created',
            'service_message' => 'ok',
        ];

        $command = new class extends SetAdmin {
            use SuppressesCliExit;
        };

        $this->assertTrue($this->invokeMethod($command, 'createAdminUser', ['Admin', 'admin@example.com', 'pass']));
    }

    public function testIsValidEmail(): void
    {
        $command = $this->makeCommand(SetAdmin::class);
        $this->assertTrue($this->invokeMethod($command, 'isValidEmail', ['user@example.com']));
        $this->assertFalse($this->invokeMethod($command, 'isValidEmail', ['not-email']));
    }

    private function interactiveSetAdmin(): SetAdmin
    {
        return new class extends SetAdmin {
            use SuppressesCliExit;

            private int $step = 0;
            private int $passwordStep = 0;

            protected function readInput(string $prompt): string
            {
                if (str_contains($prompt, 'initialize') || str_contains($prompt, 'migrate')) {
                    return 'n';
                }

                return match ($this->step++) {
                    0 => 'Admin User',
                    1 => 'admin@example.com',
                    default => 'Admin User',
                };
            }

            protected function readPassword(string $prompt): string
            {
                return $this->passwordStep++ === 0 ? 'password123' : 'password123';
            }
        };
    }

    private function pdoWithUsersTable(int $userCount): \PDO
    {
        return PdoMock::create($this, [
            'SCHEMA_NAME' => [['SCHEMA_NAME' => 'test_db']],
            'table_schema = ? AND table_name' => [['count' => 1]],
            'FROM users' => [['count' => $userCount]],
        ]);
    }
}
