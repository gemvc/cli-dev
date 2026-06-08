<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Feature;

use App\Model\UserModel;
use Gemvc\CLI\Commands\DbConnect;
use Gemvc\CLI\Commands\DbMigrate;
use Gemvc\CLI\Commands\SetAdmin;
use Gemvc\CliDev\Tests\Support\FeatureTestCase;
use Gemvc\CliDev\Tests\Support\PdoMock;
use Gemvc\CliDev\Tests\Support\SuppressesCliExit;
use Gemvc\Helper\ProjectHelper;

final class SetAdminFeatureTest extends FeatureTestCase
{
    public function testCreatesFirstAdminThroughCommandRunner(): void
    {
        ProjectHelper::reset();
        ProjectHelper::configure($this->projectRoot);

        $pdo = PdoMock::create($this, [
            'SCHEMA_NAME' => [['SCHEMA_NAME' => 'test_db']],
            'table_schema = ? AND table_name' => [['count' => 1]],
            'FROM users' => [['count' => 0]],
        ]);
        DbConnect::configure($pdo, $pdo);
        DbMigrate::$executeResult = true;
        UserModel::$response = (object) [
            'response_code' => 201,
            'message' => 'Admin created',
        ];

        $command = new class extends SetAdmin {
            use SuppressesCliExit;

            private int $step = 0;

            protected function readInput(string $prompt): string
            {
                if (str_contains($prompt, 'migrate')) {
                    return 'y';
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

        ob_start();
        $this->assertTrue($command->execute());
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Admin user created successfully', $output);
        $this->assertStringContainsString('admin@feature.test', $output);
    }
}
