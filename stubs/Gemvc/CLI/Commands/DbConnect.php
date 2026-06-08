<?php

declare(strict_types=1);

namespace Gemvc\CLI\Commands;

/**
 * PHPStan / dev autoload stub — real implementation in gemvc/library.
 */
class DbConnect
{
    private static ?\PDO $connection = null;

    private static ?\PDO $rootConnection = null;

    public static function configure(?\PDO $connection = null, ?\PDO $rootConnection = null): void
    {
        self::$connection = $connection;
        self::$rootConnection = $rootConnection;
    }

    public static function reset(): void
    {
        self::$connection = null;
        self::$rootConnection = null;
    }

    public static function connect(): ?\PDO
    {
        return self::$connection;
    }

    public static function connectAsRoot(): ?\PDO
    {
        return self::$rootConnection ?? self::$connection;
    }
}
