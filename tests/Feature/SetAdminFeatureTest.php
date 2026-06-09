<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Feature;

use App\Model\UserModel;
use Gemvc\CLI\Commands\DbConnect;
use Gemvc\CLI\Commands\DbInit;
use Gemvc\CLI\Commands\DbMigrate;
use Gemvc\CLI\Commands\SetAdmin;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbInit;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbMigrate;
use Gemvc\CliDev\Tests\Support\Commands\TestableSetAdmin;
use Gemvc\CliDev\Tests\Support\FeatureTestCase;
use Gemvc\CliDev\Tests\Support\PdoMock;
use Gemvc\Helper\ProjectHelper;

final class SetAdminFeatureTest extends FeatureTestCase
{
    public function testCreatesFirstAdminThroughCommandRunner(): void
    {
        $this->useProjectEnv();
        $this->configureDatabase(userCount: 0);
        DbMigrate::$executeResult = true;
        UserModel::$response = (object) [
            'response_code' => 201,
            'message' => 'Admin created',
        ];

        $result = $this->runCommand($this->interactiveSetAdmin(migrate: 'y'));

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Admin user created successfully', $result->output);
        $this->assertStringContainsString('admin@feature.test', $result->output);
    }

    public function testRejectsWhenUsersAlreadyExist(): void
    {
        $this->useProjectEnv();
        $this->configureDatabase(userCount: 3);

        $result = $this->runCommand($this->interactiveSetAdmin());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Users already exist', $result->output);
    }

    public function testHandlesForbiddenResponse(): void
    {
        $this->useProjectEnv();
        $this->configureDatabase(userCount: 0);
        UserModel::$response = (object) [
            'response_code' => 403,
            'message' => 'Admin exists',
        ];

        $result = $this->runCommand($this->interactiveSetAdmin());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Admin exists', $result->output);
    }

    public function testHandlesGenericFailureResponse(): void
    {
        $this->useProjectEnv();
        $this->configureDatabase(userCount: 0);
        UserModel::$response = (object) [
            'response_code' => 500,
            'message' => 'fail',
            'service_message' => 'db error',
        ];

        $result = $this->runCommand($this->interactiveSetAdmin());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('db error', $result->output);
    }

    public function testUsesLocalhostWhenDockerHostConfigured(): void
    {
        ProjectHelper::reset();
        ProjectHelper::configure($this->projectRoot, [
            'DB_NAME' => 'test_db',
            'DB_HOST' => 'mysql',
        ]);
        $this->configureDatabase(userCount: 0);
        UserModel::$response = (object) ['response_code' => 201, 'message' => 'ok'];

        $result = $this->runCommand($this->interactiveSetAdmin());

        $this->assertTrue($result->success);
        $this->assertSame('localhost', $_ENV['DB_HOST']);
        $this->assertStringContainsString('localhost for database connection', $result->output);
    }

    public function testInitializesDatabaseWhenMissing(): void
    {
        $this->useProjectEnv();

        $rootPdo = PdoMock::create($this, ['SCHEMA_NAME' => []]);
        $appPdo = $this->pdoWithUsersTable(userCount: 0);
        DbConnect::configure($appPdo, $rootPdo);
        UserModel::$response = (object) ['response_code' => 201, 'message' => 'ok'];

        $result = $this->runCommand(new class extends TestableSetAdmin {
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
        });

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Initializing database', $result->output);
    }

    public function testMigratesUserTableWhenMissing(): void
    {
        $this->useProjectEnv();

        $pdo = PdoMock::create($this, [
            'SCHEMA_NAME' => [['SCHEMA_NAME' => 'test_db']],
            'table_schema = ? AND table_name' => [],
            'FROM users' => [['count' => 0]],
        ]);
        DbConnect::configure($pdo, $pdo);
        DbMigrate::$executeResult = true;
        UserModel::$response = (object) ['response_code' => 201, 'message' => 'ok'];

        $result = $this->runCommand(new class extends TestableSetAdmin {
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
        });

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Migrating UserTable', $result->output);
    }

    public function testRejectsMismatchedPasswords(): void
    {
        $this->useProjectEnv();
        $this->configureDatabase(userCount: 0);

        $result = $this->runCommand(new class extends TestableSetAdmin {
            private int $step = 0;
            private int $passwordStep = 0;

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
                return $this->passwordStep++ === 0 ? 'one' : 'two';
            }
        });

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Passwords do not match', $result->output);
    }

    public function testFailsWhenRootConnectionUnavailable(): void
    {
        $this->useProjectEnv();
        DbConnect::configure(null, null);

        $result = $this->runCommand(new TestableSetAdmin());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Cannot connect to MySQL server', $result->output);
    }

    private function useProjectEnv(): void
    {
        ProjectHelper::reset();
        ProjectHelper::configure($this->projectRoot);
    }

    private function configureDatabase(int $userCount): void
    {
        DbConnect::configure($this->pdoWithUsersTable($userCount), $this->pdoWithUsersTable($userCount));
    }

    private function interactiveSetAdmin(string $migrate = 'n'): SetAdmin
    {
        return new class($migrate) extends TestableSetAdmin {
            private int $step = 0;

            public function __construct(private string $migrateResponse)
            {
                parent::__construct([], []);
            }

            protected function readInput(string $prompt): string
            {
                if (str_contains($prompt, 'migrate')) {
                    return $this->migrateResponse;
                }

                return match ($this->step++) {
                    0 => 'Feature Admin',
                    1 => 'admin@feature.test',
                    default => 'Feature Admin',
                };
            }

            protected function readPassword(string $prompt): string
            {
                return 'feature-pass-123';
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
