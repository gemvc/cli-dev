<?php

declare(strict_types=1);

namespace Gemvc\Helper;

/**
 * PHPStan / dev autoload stub — real implementation in gemvc/library.
 */
class ProjectHelper
{
    private static ?string $rootDir = null;

    /** @var array<string, string> */
    private static array $env = [];

    private static bool $replaceEnv = false;

    /**
     * @param array<string, string> $env
     */
    public static function configure(?string $rootDir = null, array $env = [], bool $replaceEnv = false): void
    {
        self::$rootDir = $rootDir;
        self::$env = $env;
        self::$replaceEnv = $replaceEnv;
    }

    public static function reset(): void
    {
        self::$rootDir = null;
        self::$env = [];
        self::$replaceEnv = false;
    }

    public static function loadEnv(): void
    {
        $merged = self::$replaceEnv
            ? self::$env
            : array_merge(['DB_NAME' => 'test_db', 'DB_HOST' => 'localhost'], self::$env);
        foreach ($merged as $key => $value) {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function rootDir(): string
    {
        return self::$rootDir ?? (getcwd() ?: '.');
    }
}
