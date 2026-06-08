<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Support;

/**
 * Testable command wrappers use a Testable* class name; codegen directory
 * resolution in cli-base keys off the parent command short name.
 */
trait UsesParentCommandDirectories
{
    /**
     * @return array<string>
     */
    protected function getRequiredDirectories(): array
    {
        $basePath = $this->getBasePath();
        $parent = (new \ReflectionClass(parent::class))->getShortName();

        $directories = match ($parent) {
            'CreateService' => $this->directoriesForCreateService($basePath),
            'CreateController' => $this->directoriesForCreateController($basePath),
            'CreateModel' => $this->directoriesForCreateModel($basePath),
            'CreateTable' => [$basePath . '/app/table'],
            'CreateCrud' => [
                $basePath . '/app/api',
                $basePath . '/app/controller',
                $basePath . '/app/model',
                $basePath . '/app/table',
            ],
            default => [],
        };

        return array_values(array_unique($directories));
    }

    /**
     * @return array<string>
     */
    private function directoriesForCreateService(string $basePath): array
    {
        $directories = [$basePath . '/app/api'];

        if ($this->flags['controller'] ?? false) {
            $directories[] = $basePath . '/app/controller';
        }
        if ($this->flags['model'] ?? false) {
            $directories[] = $basePath . '/app/model';
        }
        if ($this->flags['table'] ?? false) {
            $directories[] = $basePath . '/app/table';
        }

        return $directories;
    }

    /**
     * @return array<string>
     */
    private function directoriesForCreateController(string $basePath): array
    {
        $directories = [$basePath . '/app/controller'];

        if ($this->flags['model'] ?? false) {
            $directories[] = $basePath . '/app/model';
        }
        if ($this->flags['table'] ?? false) {
            $directories[] = $basePath . '/app/table';
        }

        return $directories;
    }

    /**
     * @return array<string>
     */
    private function directoriesForCreateModel(string $basePath): array
    {
        $directories = [$basePath . '/app/model'];

        if ($this->flags['table'] ?? false) {
            $directories[] = $basePath . '/app/table';
        }

        return $directories;
    }
}
