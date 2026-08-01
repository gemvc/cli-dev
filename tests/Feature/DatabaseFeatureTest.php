<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Feature;

use Gemvc\CLI\Commands\DbConnect;
use Gemvc\CliDev\Tests\Support\Commands\TestableDbDrop;
use Gemvc\CliDev\Tests\Support\FeatureTestCase;
use Gemvc\CliDev\Tests\Support\PdoMock;
use Gemvc\Helper\ProjectHelper;
use PHPUnit\Framework\MockObject\MockObject;

final class DatabaseFeatureTest extends FeatureTestCase
{
    public function testDbListReadsDatabaseNameFromEnvFile(): void
    {
        $this->useProjectEnv();

        $pdo = PdoMock::create($this, [
            'SHOW FULL TABLES' => [['users', 'BASE TABLE']],
            'SHOW COLUMNS' => [[
                'Field' => 'id',
                'Type' => 'int(11)',
                'Null' => 'NO',
                'Key' => 'PRI',
                'Default' => null,
                'Extra' => '',
            ]],
        ]);
        DbConnect::configure($pdo);

        $result = $this->runner->run('db:list');

        $this->assertTrue($result->success);
        $this->assertStringContainsString("Relations in database 'test_db'", $result->output);
        $this->assertStringContainsString('Table: users', $result->output);
    }

    public function testDbListWhenDatabaseHasNoTables(): void
    {
        $this->useProjectEnv();
        DbConnect::configure(PdoMock::create($this, ['SHOW FULL TABLES' => []]));

        $result = $this->runner->run('db:list');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('No tables or views found', $result->output);
    }

    public function testDbDescribeShowsFullTableMetadata(): void
    {
        $this->useProjectEnv();

        $pdo = PdoMock::create($this, [
            'TABLE_TYPE' => 'BASE TABLE',
            'SHOW COLUMNS' => [[
                'Field' => 'id',
                'Type' => 'int(11)',
                'Null' => 'NO',
                'Key' => 'PRI',
                'Default' => null,
                'Extra' => 'auto_increment',
            ], [
                'Field' => 'email',
                'Type' => 'varchar(255)',
                'Null' => 'NO',
                'Key' => 'UNI',
                'Default' => null,
                'Extra' => '',
            ]],
            'SHOW INDEX' => [[
                'Key_name' => 'PRIMARY',
                'Non_unique' => 0,
                'Index_type' => 'BTREE',
                'Column_name' => 'id',
                'Sub_part' => null,
            ]],
            'KEY_COLUMN_USAGE' => [[
                'CONSTRAINT_NAME' => 'fk_role',
                'COLUMN_NAME' => 'role_id',
                'REFERENCED_TABLE_NAME' => 'roles',
                'REFERENCED_COLUMN_NAME' => 'id',
            ]],
            'REFERENTIAL_CONSTRAINTS' => [[
                'CONSTRAINT_NAME' => 'fk_role',
                'DELETE_RULE' => 'CASCADE',
                'UPDATE_RULE' => 'RESTRICT',
            ]],
            'TABLE_ROWS' => [[
                'row_count' => 10,
                'data_size' => 2048,
                'index_size' => 1024,
                'total_size' => 3072,
                'next_auto_increment' => 11,
            ]],
            'ENGINE' => [[
                'ENGINE' => 'InnoDB',
                'TABLE_COLLATION' => 'utf8mb4_unicode_ci',
                'CREATE_TIME' => '2026-01-01 00:00:00',
                'UPDATE_TIME' => '2026-06-01 00:00:00',
                'TABLE_COMMENT' => 'Users table',
            ]],
        ]);
        DbConnect::configure($pdo);

        $result = $this->runner->run('db:describe', ['users']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('users', strtolower($result->output));
        $this->assertStringContainsString('COLUMNS', $result->output);
        $this->assertStringContainsString('INDEXES', $result->output);
    }

    public function testDbDescribeReportsMissingTable(): void
    {
        $this->useProjectEnv();
        DbConnect::configure(PdoMock::create($this, ['TABLE_TYPE' => []]));

        $result = $this->runner->run('db:describe', ['missing']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString("not found", $result->output);
    }

    public function testDbDescribeRequiresTableName(): void
    {
        $result = $this->runner->run('db:describe', []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Table or view name is required', $result->output);
    }

    public function testDbUniqueAddsConstraint(): void
    {
        $this->useProjectEnv();

        /** @var \PDO&MockObject $pdo */
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('query')->willReturn(PdoMock::statement($this, []));
        $pdo->method('exec')->willReturn(1);
        DbConnect::configure($pdo);

        $result = $this->runner->run('db:unique', ['users/email']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Unique constraint added', $result->output);
    }

    public function testDbUniqueAbortsWhenDuplicatesExist(): void
    {
        $this->useProjectEnv();

        /** @var \PDO&MockObject $pdo */
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('query')->willReturn(PdoMock::statement($this, [['email' => 'a@b.com', 'cnt' => 2]]));
        DbConnect::configure($pdo);

        $result = $this->runner->run('db:unique', ['users/email']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Duplicate value combinations', $result->output);
    }

    public function testDbUniqueRejectsInvalidFormatBeforeConnecting(): void
    {
        $result = $this->runner->run('db:unique', ['not-valid']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid format', $result->output);
    }

    public function testDbUniqueRejectsEmptyColumnList(): void
    {
        $result = $this->runner->run('db:unique', ['users/']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid format', $result->output);
    }

    public function testDbDropWithForceFlag(): void
    {
        $this->useProjectEnv();

        $pdo = PdoMock::create($this, [
            'TABLE_TYPE' => 'BASE TABLE',
            'SHOW CREATE TABLE' => [['Create Table' => 'CREATE TABLE `users` (id INT)']],
        ]);
        DbConnect::configure($pdo);

        $result = $this->runner->run('db:drop', ['users', '--force']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString("dropped successfully", $result->output);
    }

    public function testDbDropRequiresTableName(): void
    {
        $result = $this->runner->run('db:drop', []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Table or view name is required', $result->output);
    }

    public function testDbInitCreatesDatabaseFromEnvFile(): void
    {
        $this->useProjectEnv();
        DbConnect::configure(null, PdoMock::create($this));

        $result = $this->runner->run('db:init');

        $this->assertTrue($result->success);
        $this->assertStringContainsString("initialized successfully", $result->output);
    }

    public function testDbInitFailsWhenRootConnectionUnavailable(): void
    {
        $this->useProjectEnv();
        DbConnect::configure(null, null);

        $result = $this->runner->run('db:init');

        $this->assertFalse($result->success);
    }

    public function testDbInitFailsWhenDatabaseNameMissing(): void
    {
        ProjectHelper::reset();
        ProjectHelper::configure($this->projectRoot, ['DB_NAME' => '']);
        DbConnect::configure(null, PdoMock::create($this));

        $result = $this->runner->run('db:init');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Database name not found', $result->output);
    }

    public function testDbListFailsWhenDatabaseNameMissing(): void
    {
        ProjectHelper::reset();
        ProjectHelper::configure($this->projectRoot, ['DB_NAME' => '']);

        $result = $this->runner->run('db:list');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Database name not found', $result->output);
    }

    public function testDbListFailsWhenConnectionUnavailable(): void
    {
        $this->useProjectEnv();
        DbConnect::configure(null);

        $result = $this->runner->run('db:list');

        $this->assertFalse($result->success);
    }

    public function testDbDescribeFailsWhenDatabaseNameMissing(): void
    {
        ProjectHelper::reset();
        ProjectHelper::configure($this->projectRoot, ['DB_NAME' => '']);

        $result = $this->runner->run('db:describe', ['users']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Failed to describe table', $result->output);
    }

    public function testDbDescribeShowsEmptySections(): void
    {
        $this->useProjectEnv();

        $pdo = PdoMock::create($this, [
            'TABLE_TYPE' => 'BASE TABLE',
            'SHOW COLUMNS' => [],
            'SHOW INDEX' => [],
            'KEY_COLUMN_USAGE' => [],
            'TABLE_ROWS' => false,
            'ENGINE' => false,
        ]);
        DbConnect::configure($pdo);

        $result = $this->runner->run('db:describe', ['empty_tbl']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No columns found', $result->output);
        $this->assertStringContainsString('No indexes found', $result->output);
        $this->assertStringContainsString('No foreign keys found', $result->output);
    }

    public function testDbUniqueRequiresArgument(): void
    {
        $result = $this->runner->run('db:unique', []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Usage: gemvc db:unique', $result->output);
    }

    public function testDbUniqueFailsWhenConnectionUnavailable(): void
    {
        $this->useProjectEnv();
        DbConnect::configure(null);

        $result = $this->runner->run('db:unique', ['users/email']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Could not connect', $result->output);
    }

    public function testDbUniqueAddsMultiColumnConstraint(): void
    {
        $this->useProjectEnv();

        /** @var \PDO&MockObject $pdo */
        $pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('query')->willReturn(PdoMock::statement($this, []));
        $pdo->method('exec')->willReturn(1);
        DbConnect::configure($pdo);

        $result = $this->runner->run('db:unique', ['users/email,name']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('email, name', $result->output);
    }

    public function testDbDropReportsMissingTable(): void
    {
        $this->useProjectEnv();
        DbConnect::configure(PdoMock::create($this, ['TABLE_TYPE' => []]));

        $result = $this->runner->run('db:drop', ['missing', '--force']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->output);
    }

    public function testDbDropFailsWhenConnectionUnavailable(): void
    {
        $this->useProjectEnv();
        DbConnect::configure(null);

        $result = $this->runner->run('db:drop', ['users', '--force']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Failed to connect', $result->output);
    }

    public function testDbDropWithConfirmation(): void
    {
        $this->useProjectEnv();

        $pdo = PdoMock::create($this, [
            'TABLE_TYPE' => 'BASE TABLE',
            'SHOW CREATE TABLE' => [['Create Table' => 'CREATE TABLE `users` (id INT)']],
        ]);
        DbConnect::configure($pdo);

        $result = $this->runCommand(new class(['users']) extends TestableDbDrop {
            protected function readConfirmation(): string
            {
                return 'yes';
            }
        });

        $this->assertTrue($result->success);
        $this->assertStringContainsString('dropped successfully', $result->output);
    }

    public function testDbDropCancelsWhenNotConfirmed(): void
    {
        $this->useProjectEnv();
        DbConnect::configure(PdoMock::create($this, [
            'TABLE_TYPE' => 'BASE TABLE',
        ]));

        $result = $this->runCommand(new class(['users']) extends TestableDbDrop {
            protected function readConfirmation(): string
            {
                return 'no';
            }
        });

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Operation cancelled', $result->output);
    }

    private function useProjectEnv(): void
    {
        ProjectHelper::reset();
        ProjectHelper::configure($this->projectRoot);
    }
}
