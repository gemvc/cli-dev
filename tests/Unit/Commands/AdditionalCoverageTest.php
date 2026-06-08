<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Unit\Commands;

use App\Model\UserModel;
use Gemvc\CLI\Commands\AdminSetpassword;
use Gemvc\CLI\Commands\CreateCrud;
use Gemvc\CLI\Commands\CreateService;
use Gemvc\CLI\Commands\DbConnect;
use Gemvc\CLI\Commands\DbDrop;
use Gemvc\CLI\Commands\DbInit;
use Gemvc\CLI\Commands\DbMigrate;
use Gemvc\CLI\Commands\DevGenerator;
use Gemvc\CLI\Commands\OptionalToolsInstaller;
use Gemvc\CLI\Commands\SetAdmin;
use Gemvc\CliDev\Tests\Support\CommandTestCase;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbMigrate;
use Gemvc\CliDev\Tests\Support\PdoMock;
use Gemvc\CliDev\Tests\Support\SuppressesCliExit;
use Gemvc\Helper\ProjectHelper;

final class AdditionalCoverageTest extends CommandTestCase
{
    public function testDbInitHandlesException(): void
    {
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('exec')->willThrowException(new \PDOException('boom'));
        DbConnect::configure(null, $pdo);

        $this->assertFalse($this->makeCommand(DbInit::class)->execute());
    }

    public function testDbDropConfirmsAndDropsTable(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW TABLES LIKE' => [['Tables_in_test_db (users)' => 'users']],
            'SHOW CREATE TABLE' => [['Create Table' => 'CREATE TABLE `users` (id INT)']],
        ]);
        DbConnect::configure($pdo);

        $command = new class(['users']) extends DbDrop {
            use SuppressesCliExit;

            protected function readConfirmation(): string
            {
                return 'yes';
            }
        };

        $this->assertTrue($command->execute());
    }

    public function testDbDropRejectsInvalidTableNameType(): void
    {
        $command = $this->makeCommand(DbDrop::class, [[1]]);
        $this->assertFalse($command->execute());
    }

    public function testAdminSetpasswordRejectsEmptyPassword(): void
    {
        file_put_contents($this->projectRoot . '/.env', "APP_ENV=dev\n");

        $command = new class([], []) extends AdminSetpassword {
            use SuppressesCliExit;

            protected function readPassword(string $prompt): string
            {
                return '';
            }
        };

        $this->assertFalse($command->execute());
    }

    public function testCreateCrudHandlesException(): void
    {
        $command = new class extends CreateCrud {
            use SuppressesCliExit;

            protected function newCreateService(array $args, array $options): CreateService
            {
                return new class($args, $options) extends CreateService {
                    use SuppressesCliExit;

                    public function execute(): bool
                    {
                        throw new \RuntimeException('fail');
                    }
                };
            }
        };

        $this->assertFalse($command->execute());
    }

    public function testDevGeneratorUsesPackageStagingTemplates(): void
    {
        $generator = new class([], []) extends DevGenerator {
            public function execute(): bool
            {
                return true;
            }
        };
        $this->setProperty($generator, 'basePath', '/no/project/templates');

        $content = $this->invokeMethod($generator, 'getTemplate', ['service']);
        $this->assertStringContainsString('{$serviceName}', $content);
    }

    public function testOptionalToolsInstallerOffersPhpstanInstall(): void
    {
        $installer = new class($this->projectRoot, $this->projectRoot) extends OptionalToolsInstaller {
            use SuppressesCliExit;

            protected function readStdinLine(): string|false
            {
                return 'y';
            }
        };

        $output = $this->captureOutput(static fn () => $installer->offerPhpstanInstallation());
        $this->assertStringContainsString('PHPStan installation failed', $output);
    }

    public function testOptionalToolsInstallerInstallsPhpunitChoice(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', '{}');
        $installer = new class($this->projectRoot, $this->projectRoot) extends OptionalToolsInstaller {
            use SuppressesCliExit;

            private int $step = 0;

            protected function readStdinLine(): string|false
            {
                return match ($this->step++) {
                    0 => 'n',
                    1 => '1',
                    default => '',
                };
            }

            protected function runComposerCommand(string $command): void
            {
            }
        };

        $installer->offerOptionalTools();
        $this->assertFileExists($this->projectRoot . '/phpunit.xml');
    }

    public function testOptionalToolsInstallerInstallPestFailure(): void
    {
        $installer = new class($this->projectRoot, $this->projectRoot) extends OptionalToolsInstaller {
            use SuppressesCliExit;
        };
        $output = $this->captureOutput(static fn () => $installer->installPest());
        $this->assertStringContainsString('Pest installation failed', $output);
    }

    public function testSetAdminSkipsDatabaseInitializationWhenDeclined(): void
    {
        $rootPdo = PdoMock::create($this, ['SCHEMA_NAME' => []]);
        $appPdo = PdoMock::create($this, [
            'SCHEMA_NAME' => [['SCHEMA_NAME' => 'test_db']],
            'table_schema = ? AND table_name' => [['count' => 1]],
            'FROM users' => [['count' => 0]],
        ]);
        DbConnect::configure($appPdo, $rootPdo);
        UserModel::$response = (object) ['response_code' => 201, 'message' => 'ok'];

        $command = new class extends SetAdmin {
            use SuppressesCliExit;

            private int $step = 0;

            protected function readInput(string $prompt): string
            {
                if (str_contains($prompt, 'initialize')) {
                    return 'n';
                }
                if (str_contains($prompt, 'migrate')) {
                    return 'n';
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
        };

        $this->assertTrue($command->execute());
    }

    public function testSetAdminRetriesInvalidEmail(): void
    {
        $pdo = PdoMock::create($this, [
            'SCHEMA_NAME' => [['SCHEMA_NAME' => 'test_db']],
            'table_schema = ? AND table_name' => [['count' => 1]],
            'FROM users' => [['count' => 0]],
        ]);
        DbConnect::configure($pdo, $pdo);
        UserModel::$response = (object) ['response_code' => 201, 'message' => 'ok'];

        $command = new class extends SetAdmin {
            use SuppressesCliExit;

            private int $step = 0;

            protected function readInput(string $prompt): string
            {
                if (str_contains($prompt, 'initialize') || str_contains($prompt, 'migrate')) {
                    return 'n';
                }

                return match ($this->step++) {
                    0 => 'Admin',
                    1 => 'not-an-email',
                    2 => 'admin@example.com',
                    default => 'Admin',
                };
            }

            protected function readPassword(string $prompt): string
            {
                return 'password123';
            }
        };

        $this->assertTrue($command->execute());
    }

    public function testDbListHandlesColumnQueryFailure(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW TABLES' => ['users'],
            'SHOW COLUMNS' => false,
        ]);
        DbConnect::configure($pdo);

        $this->assertFalse($this->makeCommand(\Gemvc\CLI\Commands\DbList::class)->execute());
    }

    public function testDbListHandlesEmptyColumns(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW TABLES' => ['users'],
            'SHOW COLUMNS' => [],
        ]);
        DbConnect::configure($pdo);

        $this->assertFalse($this->makeCommand(\Gemvc\CLI\Commands\DbList::class)->execute());
    }

    public function testDbListHandlesTableQueryFailure(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW TABLES' => false,
        ]);
        DbConnect::configure($pdo);

        $this->assertFalse($this->makeCommand(\Gemvc\CLI\Commands\DbList::class)->execute());
    }

    public function testSetAdminRejectsMismatchedPassword(): void
    {
        $pdo = PdoMock::create($this, [
            'SCHEMA_NAME' => [['SCHEMA_NAME' => 'test_db']],
            'table_schema = ? AND table_name' => [['count' => 1]],
            'FROM users' => [['count' => 0]],
        ]);
        DbConnect::configure($pdo, $pdo);

        $command = new class extends SetAdmin {
            use SuppressesCliExit;

            private int $passwordStep = 0;

            protected function readInput(string $prompt): string
            {
                return str_contains($prompt, 'initialize') || str_contains($prompt, 'migrate')
                    ? 'n'
                    : 'admin@example.com';
            }

            protected function readPassword(string $prompt): string
            {
                return $this->passwordStep++ === 0 ? 'one' : 'two';
            }
        };

        $this->assertFalse($command->execute());
    }

    public function testSetAdminHandlesUserCountQueryFailure(): void
    {
        $pdo = PdoMock::create($this, [
            'SCHEMA_NAME' => [['SCHEMA_NAME' => 'test_db']],
            'table_schema = ? AND table_name' => [['count' => 1]],
            'FROM users' => false,
        ]);
        DbConnect::configure($pdo, $pdo);
        UserModel::$response = (object) ['response_code' => 201, 'message' => 'ok'];

        $command = new class extends SetAdmin {
            use SuppressesCliExit;

            private int $step = 0;

            protected function readInput(string $prompt): string
            {
                if (str_contains($prompt, 'initialize') || str_contains($prompt, 'migrate')) {
                    return 'n';
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
        };

        $this->assertTrue($command->execute());
    }

    public function testOptionalToolsInstallerPhpstanConfigAlreadyExists(): void
    {
        $packageWithTemplate = $this->projectRoot . '/pkg';
        $templateDir = $packageWithTemplate . '/src/startup/common';
        mkdir($templateDir, 0777, true);
        file_put_contents($templateDir . '/phpstan.neon', 'parameters: {}');
        file_put_contents($this->projectRoot . '/phpstan.neon', 'existing');

        $installer = new class($this->projectRoot, $packageWithTemplate) extends OptionalToolsInstaller {
            use SuppressesCliExit;

            protected function runComposerCommand(string $command): void
            {
            }
        };

        $installer->installPhpstan();
        $this->assertSame('existing', file_get_contents($this->projectRoot . '/phpstan.neon'));
    }

    public function testOptionalToolsInstallerSuccessfulPhpstanInstall(): void
    {
        $packageWithTemplate = $this->projectRoot . '/pkg';
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

        $output = $this->captureOutput(static fn () => $installer->installPhpstan());
        $this->assertStringContainsString('PHPStan installed successfully', $output);
    }

    public function testOptionalToolsInstallerPestChoice(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', '{}');
        $installer = new class($this->projectRoot, $this->projectRoot) extends OptionalToolsInstaller {
            use SuppressesCliExit;

            private int $step = 0;

            protected function readStdinLine(): string|false
            {
                return match ($this->step++) {
                    0 => 'n',
                    1 => '2',
                    default => '',
                };
            }

            protected function runComposerCommand(string $command): void
            {
            }

            protected function initializePest(): void
            {
            }
        };

        $output = $this->captureOutput(static fn () => $installer->offerOptionalTools());
        $this->assertStringContainsString('Pest installed successfully', $output);
    }

    public function testDevGeneratorInstallPathHelper(): void
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

    public function testCreateServiceHandlesWriteFailure(): void
    {
        $this->seedTemplates();
        $command = $this->makeCommand(\Gemvc\CLI\Commands\CreateService::class, ['User'], []);
        $this->setProperty($command, 'basePath', '/root/not-writable');
        $this->expectingPhpWarnings(fn () => $this->assertFalse($command->execute()));
    }

    public function testOptionalToolsInstallerRunComposerCommandFailure(): void
    {
        file_put_contents(
            $this->projectRoot . '/composer.json',
            json_encode(['name' => 'test/app', 'minimum-stability' => 'stable'], JSON_THROW_ON_ERROR)
        );

        $installer = new class($this->projectRoot, $this->projectRoot) extends OptionalToolsInstaller {
            use SuppressesCliExit;
        };

        $this->expectException(\RuntimeException::class);
        $this->invokeMethod(
            $installer,
            'runComposerCommand',
            ['require --dev gemvc/definitely-missing-package-12345']
        );
    }

    public function testOptionalToolsInstallerInitializePestFailure(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', '{}');
        $installer = new class($this->projectRoot, $this->projectRoot) extends OptionalToolsInstaller {
            use SuppressesCliExit;
        };

        $this->expectException(\RuntimeException::class);
        $this->invokeMethod($installer, 'initializePest');
    }

    public function testSetAdminRetriesEmptyName(): void
    {
        $pdo = PdoMock::create($this, [
            'SCHEMA_NAME' => [['SCHEMA_NAME' => 'test_db']],
            'table_schema = ? AND table_name' => [['count' => 1]],
            'FROM users' => [['count' => 0]],
        ]);
        DbConnect::configure($pdo, $pdo);
        UserModel::$response = (object) ['response_code' => 201, 'message' => 'ok'];

        $command = new class extends SetAdmin {
            use SuppressesCliExit;

            private int $step = 0;

            protected function readInput(string $prompt): string
            {
                if (str_contains($prompt, 'initialize') || str_contains($prompt, 'migrate')) {
                    return 'n';
                }

                return match ($this->step++) {
                    0 => '',
                    1 => 'Admin',
                    2 => 'admin@example.com',
                    default => 'Admin',
                };
            }

            protected function readPassword(string $prompt): string
            {
                return 'password123';
            }
        };

        $this->assertTrue($command->execute());
    }

    public function testDbDescribeCoversWideTableLayout(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW TABLES FROM' => [['Tables_in_test_db (wide)' => 'wide']],
            'SHOW COLUMNS' => [[
                'Field' => str_repeat('column', 20),
                'Type' => 'varchar(255)',
                'Null' => 'NO',
                'Key' => '',
                'Default' => null,
                'Extra' => '',
            ]],
            'SHOW INDEX' => [],
            'KEY_COLUMN_USAGE' => [],
            'TABLE_ROWS' => [['row_count' => 1, 'data_size' => 10, 'index_size' => 10, 'total_size' => 20]],
            'ENGINE' => [['ENGINE' => 'InnoDB', 'TABLE_COLLATION' => 'utf8mb4_unicode_ci']],
        ]);
        DbConnect::configure($pdo);

        $this->assertTrue($this->makeCommand(\Gemvc\CLI\Commands\DbDescribe::class, ['wide'])->execute());
    }

    public function testCreateTableDetermineProjectRoot(): void
    {
        $command = $this->makeCommand(\Gemvc\CLI\Commands\CreateTable::class, ['Item'], []);
        $path = $this->invokeMethod($command, 'determineProjectRoot');
        $this->assertSame($this->projectRoot, $path);
    }

    public function testOptionalToolsInstallerComposerFailureHints(): void
    {
        $installer = $this->makeInstaller();
        $this->expectException(\RuntimeException::class);
        $this->invokeMethod($installer, 'handleComposerFailure', [
            ['Permission denied while opening stream', 'Downgrading gemvc/library version conflict'],
            'require --dev phpstan/phpstan',
        ]);
    }

    public function testSetAdminFailsWhenSchemaPrepareFails(): void
    {
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $stmt = $this->getMockBuilder(\PDOStatement::class)->disableOriginalConstructor()->getMock();
        $pdo->method('prepare')->willReturn(false);
        DbConnect::configure(null, $pdo);

        $this->assertFalse($this->makeCommand(SetAdmin::class)->execute());
    }

    public function testSetAdminFailsWhenUserMigrationFails(): void
    {
        $pdo = PdoMock::create($this, [
            'SCHEMA_NAME' => [['SCHEMA_NAME' => 'test_db']],
            'table_schema = ? AND table_name' => [],
        ]);
        DbConnect::configure($pdo, $pdo);
        DbMigrate::$executeResult = false;

        $command = new class extends SetAdmin {
            use SuppressesCliExit;

            protected function newDbMigrate(array $args): DbMigrate
            {
                return new TestableDbMigrate($args);
            }

            protected function readInput(string $prompt): string
            {
                return str_contains($prompt, 'migrate') ? 'y' : 'n';
            }
        };

        $this->assertFalse($command->execute());
    }

    public function testDbDropHandlesShowCreateFailure(): void
    {
        /** @var \PDO&\PHPUnit\Framework\MockObject\MockObject $pdo */
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('query')->willReturnCallback(function (string $sql) {
            if (str_contains($sql, 'SHOW TABLES LIKE')) {
                return PdoMock::statement($this, [['Tables_in_test_db (users)' => 'users']]);
            }
            if (str_contains($sql, 'SHOW CREATE TABLE')) {
                return false;
            }

            return false;
        });
        DbConnect::configure($pdo);

        $command = new class(['users', '--force']) extends DbDrop {
            use SuppressesCliExit;
        };
        $this->assertFalse($command->execute());
    }

    public function testDbListCatchesUnexpectedException(): void
    {
        /** @var \PDO&\PHPUnit\Framework\MockObject\MockObject $pdo */
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('query')->willThrowException(new \RuntimeException('db down'));
        DbConnect::configure($pdo);

        $this->assertFalse($this->makeCommand(\Gemvc\CLI\Commands\DbList::class)->execute());
    }

    public function testSetAdminFailsWhenExistingDatabaseIsUnreachable(): void
    {
        $rootPdo = PdoMock::create($this, [
            'SCHEMA_NAME' => [['SCHEMA_NAME' => 'test_db']],
        ]);
        DbConnect::configure(null, $rootPdo);

        $command = new class extends SetAdmin {
            use SuppressesCliExit;

            protected function readInput(string $prompt): string
            {
                return 'n';
            }
        };

        $this->assertFalse($command->execute());
    }

    public function testCreateControllerDetermineProjectRoot(): void
    {
        $this->seedTemplates();
        $this->seedAppDirectories();
        $command = $this->makeCommand(\Gemvc\CLI\Commands\CreateController::class, ['X'], []);
        $this->assertSame($this->projectRoot, $this->invokeMethod($command, 'determineProjectRoot'));
    }

    public function testCreateModelWithOnlyServiceFlagPaths(): void
    {
        $this->seedTemplates();
        $this->seedAppDirectories();
        $command = $this->makeCommand(\Gemvc\CLI\Commands\CreateModel::class, ['Z'], []);
        $this->assertTrue($command->execute());
    }

    public function testAdminSetpasswordUpdateEnvInsertsAfterAppEnv(): void
    {
        $path = $this->projectRoot . '/.env';
        file_put_contents($path, "APP_ENV=local\nOTHER=1\n");
        $command = $this->makeCommand(AdminSetpassword::class, [], []);
        $this->assertTrue($this->invokeMethod($command, 'updateEnvFile', [$path, 'secret']));
        $this->assertStringContainsString('ADMIN_PASSWORD="secret"', (string) file_get_contents($path));
    }

    public function testSetAdminHandlesUserTablePrepareFailure(): void
    {
        /** @var \PDO&\PHPUnit\Framework\MockObject\MockObject $pdo */
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $schemaStmt = PdoMock::statement($this, [['SCHEMA_NAME' => 'test_db']]);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($schemaStmt) {
            if (str_contains($sql, 'SCHEMA_NAME')) {
                return $schemaStmt;
            }

            return false;
        });
        $pdo->method('query')->willReturn(false);
        DbConnect::configure($pdo, $pdo);

        $this->assertFalse($this->makeCommand(SetAdmin::class)->execute());
    }

    public function testDbUniqueFailsWhenDuplicateQueryFails(): void
    {
        /** @var \PDO&\PHPUnit\Framework\MockObject\MockObject $pdo */
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('query')->willReturn(false);
        DbConnect::configure($pdo);

        $this->assertFalse($this->makeCommand(\Gemvc\CLI\Commands\DbUnique::class, ['users/email'])->execute());
    }

    public function testCreateTableHandlesWriteErrors(): void
    {
        $this->seedTemplates();
        $command = $this->makeCommand(\Gemvc\CLI\Commands\CreateTable::class, ['Bad'], []);
        $this->setProperty($command, 'basePath', '/cannot-create-here');
        $this->expectingPhpWarnings(fn () => $this->assertFalse($command->execute()));
    }

    public function testOptionalToolsInstallerCopyPhpstanConfigFailure(): void
    {
        $packageWithTemplate = $this->projectRoot . '/pkg-copy-fail';
        $templateDir = $packageWithTemplate . '/src/startup/common';
        mkdir($templateDir, 0777, true);
        file_put_contents($templateDir . '/phpstan.neon', 'parameters: {}');

        $targetDir = $this->projectRoot . '/locked';
        mkdir($targetDir, 0400);

        $installer = new class($targetDir, $packageWithTemplate) extends OptionalToolsInstaller {
            use SuppressesCliExit;
        };

        $this->expectException(\RuntimeException::class);
        $this->expectingPhpWarnings(fn () => $this->invokeMethod($installer, 'copyPhpstanConfig'));
    }

    public function testDevGeneratorReadsBundledTemplates(): void
    {
        $generator = new class([], []) extends DevGenerator {
            public function execute(): bool
            {
                return true;
            }

            protected function getCliDevInstallPath(): ?string
            {
                return dirname(__DIR__, 3);
            }
        };
        $this->setProperty($generator, 'basePath', $this->projectRoot . '/missing-templates');
        $content = $this->invokeMethod($generator, 'getTemplate', ['service']);
        $this->assertStringContainsString('{$serviceName}', $content);
    }

    public function testSetAdminHandlesSchemaCheckException(): void
    {
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('prepare')->willThrowException(new \RuntimeException('schema error'));
        DbConnect::configure(null, $pdo);

        $this->assertFalse($this->makeCommand(SetAdmin::class)->execute());
    }

    public function testSetAdminSkipsMigrationWhenDeclined(): void
    {
        $pdo = PdoMock::create($this, [
            'SCHEMA_NAME' => [['SCHEMA_NAME' => 'test_db']],
            'table_schema = ? AND table_name' => [],
            'FROM users' => [['count' => 0]],
        ]);
        DbConnect::configure($pdo, $pdo);
        UserModel::$response = (object) ['response_code' => 201, 'message' => 'ok'];

        $command = new class extends SetAdmin {
            use SuppressesCliExit;

            private int $step = 0;

            protected function readInput(string $prompt): string
            {
                if (str_contains($prompt, 'migrate')) {
                    return 'n';
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
        };

        $this->assertTrue($command->execute());
    }

    public function testDbDropReadConfirmationFailure(): void
    {
        $command = new class(['users']) extends DbDrop {
            use SuppressesCliExit;

            protected function readConfirmation(): string
            {
                $this->error('Failed to open stdin');

                return '';
            }
        };

        $this->assertFalse($command->execute());
    }

    public function testOptionalToolsInstallerReadStdinFailure(): void
    {
        $installer = new class($this->projectRoot, $this->projectRoot) extends OptionalToolsInstaller {
            use SuppressesCliExit;

            protected function readStdinLine(): string|false
            {
                $this->error('Failed to open stdin');

                return false;
            }
        };

        $installer->offerPhpstanInstallation();
        $this->assertTrue(true);
    }

    public function testCreateServiceDetermineProjectRoot(): void
    {
        $command = $this->makeCommand(\Gemvc\CLI\Commands\CreateService::class, [], []);
        $this->assertSame($this->projectRoot, $this->invokeMethod($command, 'determineProjectRoot'));
    }

    public function testOptionalToolsInstallerRunComposerCommandSuccess(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', '{}');
        $installer = $this->makeInstaller();
        $this->invokeMethod($installer, 'runComposerCommand', ['--version']);
        $this->assertTrue(true);
    }

    public function testSetAdminFailsWhenUserCountPrepareFails(): void
    {
        $pdo = PdoMock::create($this, [
            'SCHEMA_NAME' => [['SCHEMA_NAME' => 'test_db']],
            'table_schema = ? AND table_name' => [['count' => 1]],
        ]);
        /** @var \PDO&\PHPUnit\Framework\MockObject\MockObject $pdo */
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $schemaStmt = PdoMock::statement($this, [['SCHEMA_NAME' => 'test_db']]);
        $tableStmt = PdoMock::statement($this, [['count' => 1]]);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($schemaStmt, $tableStmt) {
            if (str_contains($sql, 'SCHEMA_NAME')) {
                return $schemaStmt;
            }
            if (str_contains($sql, 'information_schema.tables')) {
                return $tableStmt;
            }
            if (str_contains($sql, 'FROM users')) {
                return false;
            }

            return false;
        });
        DbConnect::configure($pdo, $pdo);

        $this->assertFalse($this->makeCommand(SetAdmin::class)->execute());
    }

    public function testDbDescribeForeignKeysWithoutConstraintRules(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW TABLES FROM' => [['Tables_in_test_db (fk)' => 'fk']],
            'SHOW COLUMNS' => [[
                'Field' => 'id',
                'Type' => 'int(11)',
                'Null' => 'NO',
                'Key' => 'PRI',
                'Default' => null,
                'Extra' => '',
            ]],
            'SHOW INDEX' => [],
            'KEY_COLUMN_USAGE' => [[
                'CONSTRAINT_NAME' => 'fk_example',
                'COLUMN_NAME' => 'ref_id',
                'REFERENCED_TABLE_NAME' => 'other',
                'REFERENCED_COLUMN_NAME' => 'id',
            ]],
            'REFERENTIAL_CONSTRAINTS' => [],
            'TABLE_ROWS' => [['row_count' => 0, 'data_size' => 0, 'index_size' => 0, 'total_size' => 0]],
            'ENGINE' => [['ENGINE' => 'InnoDB', 'TABLE_COLLATION' => 'utf8mb4_unicode_ci']],
        ]);
        DbConnect::configure($pdo);

        $this->assertTrue($this->makeCommand(\Gemvc\CLI\Commands\DbDescribe::class, ['fk'])->execute());
    }

    public function testDbDescribeIndexWithSubPart(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW TABLES FROM' => [['Tables_in_test_db (idx)' => 'idx']],
            'SHOW COLUMNS' => [[
                'Field' => 'slug',
                'Type' => 'varchar(100)',
                'Null' => 'NO',
                'Key' => 'MUL',
                'Default' => null,
                'Extra' => '',
            ]],
            'SHOW INDEX' => [[
                'Key_name' => 'slug_idx',
                'Non_unique' => 1,
                'Index_type' => 'BTREE',
                'Column_name' => 'slug',
                'Sub_part' => 10,
            ]],
            'KEY_COLUMN_USAGE' => [],
            'TABLE_ROWS' => [['row_count' => 0, 'data_size' => 0, 'index_size' => 0, 'total_size' => 0]],
            'ENGINE' => [['ENGINE' => 'InnoDB', 'TABLE_COLLATION' => 'utf8mb4_unicode_ci']],
        ]);
        DbConnect::configure($pdo);

        $this->assertTrue($this->makeCommand(\Gemvc\CLI\Commands\DbDescribe::class, ['idx'])->execute());
    }

    public function testSetAdminValidatesEmailViaReflection(): void
    {
        $command = $this->makeCommand(SetAdmin::class);
        $this->assertTrue($this->invokeMethod($command, 'isValidEmail', ['user@example.com']));
        $this->assertFalse($this->invokeMethod($command, 'isValidEmail', ['not-email']));
    }

    public function testSetAdminFailsWhenDatabaseNameMissingDuringInit(): void
    {
        ProjectHelper::configure($this->projectRoot, [], true);
        unset($_ENV['DB_NAME']);
        putenv('DB_NAME');

        $pdo = PdoMock::create($this);
        DbConnect::configure(null, $pdo);

        $this->assertFalse($this->makeCommand(SetAdmin::class)->execute());
    }

    public function testDbDropFailsWhenShowTablesQueryFails(): void
    {
        /** @var \PDO&\PHPUnit\Framework\MockObject\MockObject $pdo */
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('query')->willReturn(false);
        DbConnect::configure($pdo);

        $this->assertFalse($this->makeCommand(DbDrop::class, ['users', '--force'])->execute());
    }

    public function testAdminSetpasswordCannotReadEnvDirectory(): void
    {
        mkdir($this->projectRoot . '/.env');
        $command = $this->makeCommand(AdminSetpassword::class, [], []);
        $this->assertFalse($this->invokeMethod($command, 'updateEnvFile', [$this->projectRoot . '/.env', 'x']));
    }

    public function testCreateModelDetermineProjectRoot(): void
    {
        $command = $this->makeCommand(\Gemvc\CLI\Commands\CreateModel::class, [], []);
        $this->assertSame($this->projectRoot, $this->invokeMethod($command, 'determineProjectRoot'));
    }

    public function testOptionalToolsInstallerCreatePhpunitConfigWriteFailure(): void
    {
        $readOnlyProject = $this->projectRoot . '/ro-phpunit';
        mkdir($readOnlyProject, 0777, true);
        file_put_contents($readOnlyProject . '/composer.json', '{}');
        chmod($readOnlyProject, 0555);

        $installer = new class($readOnlyProject, $this->projectRoot) extends OptionalToolsInstaller {
            use SuppressesCliExit;

            protected function runComposerCommand(string $command): void
            {
            }
        };

        $output = $this->expectingPhpWarnings(
            fn () => $this->captureOutput(static fn () => $installer->installPhpunit())
        );
        $this->assertStringContainsString('PHPUnit installation failed', $output);
        chmod($readOnlyProject, 0755);
    }

    public function testDbDescribeStatisticsWithoutAutoIncrement(): void
    {
        $pdo = PdoMock::create($this, [
            'SHOW TABLES FROM' => [['Tables_in_test_db (stats)' => 'stats']],
            'SHOW COLUMNS' => [[
                'Field' => 'id',
                'Type' => 'int(11)',
                'Null' => 'NO',
                'Key' => 'PRI',
                'Default' => null,
                'Extra' => '',
            ]],
            'SHOW INDEX' => [],
            'KEY_COLUMN_USAGE' => [],
            'TABLE_ROWS' => [['row_count' => 2, 'data_size' => 100, 'index_size' => 50, 'total_size' => 150]],
            'ENGINE' => [['ENGINE' => 'InnoDB', 'TABLE_COLLATION' => 'utf8mb4_unicode_ci']],
        ]);
        DbConnect::configure($pdo);

        $this->assertTrue($this->makeCommand(\Gemvc\CLI\Commands\DbDescribe::class, ['stats'])->execute());
    }

    public function testSetAdminFailsWhenUsersTablePrepareFails(): void
    {
        $pdo = PdoMock::create($this, [
            'SCHEMA_NAME' => [['SCHEMA_NAME' => 'test_db']],
        ]);
        /** @var \PDO&\PHPUnit\Framework\MockObject\MockObject $pdo */
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $schemaStmt = PdoMock::statement($this, [['SCHEMA_NAME' => 'test_db']]);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($schemaStmt) {
            if (str_contains($sql, 'SCHEMA_NAME')) {
                return $schemaStmt;
            }
            if (str_contains($sql, 'information_schema.tables')) {
                return false;
            }

            return false;
        });
        DbConnect::configure($pdo, $pdo);

        $this->assertFalse($this->makeCommand(SetAdmin::class)->execute());
    }

    public function testDbUniqueRejectsEmptyColumnList(): void
    {
        $this->assertFalse($this->makeCommand(\Gemvc\CLI\Commands\DbUnique::class, ['users/'])->execute());
    }

    public function testDbListRejectsNonStringDatabaseName(): void
    {
        ProjectHelper::loadEnv();
        $_ENV['DB_NAME'] = 123;
        DbConnect::configure(PdoMock::create($this));

        $this->assertFalse($this->makeCommand(\Gemvc\CLI\Commands\DbList::class)->execute());
    }

    public function testDbDescribeRejectsNonStringTableName(): void
    {
        $this->assertFalse($this->makeCommand(\Gemvc\CLI\Commands\DbDescribe::class, [[1]])->execute());
    }

    public function testCreateControllerHandlesGenerationErrors(): void
    {
        $command = $this->makeCommand(\Gemvc\CLI\Commands\CreateController::class, ['Fail'], []);
        $this->setProperty($command, 'basePath', '/not-writable');
        $this->expectingPhpWarnings(fn () => $this->assertFalse($command->execute()));
    }

    public function testDevGeneratorThrowsWhenTemplateUnreadable(): void
    {
        $this->seedTemplates();
        $template = $this->projectRoot . '/templates/cli/service.template';
        chmod($template, 0000);

        $generator = new class([], []) extends DevGenerator {
            public function execute(): bool
            {
                return true;
            }
        };
        $this->setProperty($generator, 'basePath', $this->projectRoot);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectingPhpWarnings(fn () => $this->invokeMethod($generator, 'getTemplate', ['service']));
        } finally {
            chmod($template, 0644);
        }
    }

    public function testCreateCrudUsesDefaultServiceFactory(): void
    {
        $crud = $this->makeCommand(CreateCrud::class, [], []);
        $service = $this->invokeMethod($crud, 'newCreateService', [['Item'], []]);
        $this->assertInstanceOf(CreateService::class, $service);
    }

    public function testSetAdminForbiddenResponseWithoutMessage(): void
    {
        $pdo = PdoMock::create($this, [
            'SCHEMA_NAME' => [['SCHEMA_NAME' => 'test_db']],
            'table_schema = ? AND table_name' => [['count' => 1]],
            'FROM users' => [['count' => 0]],
        ]);
        DbConnect::configure($pdo, $pdo);
        UserModel::$response = (object) ['response_code' => 403];

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

        $this->assertFalse($command->execute());
    }
}
