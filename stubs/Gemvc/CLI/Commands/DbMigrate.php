<?php

declare(strict_types=1);

namespace Gemvc\CLI\Commands;

/**
 * PHPStan / dev autoload stub — real implementation in gemvc/library.
 */
class DbMigrate
{
    public static bool $executeResult = false;

    /**
     * @param array<int, mixed> $args
     * @param array<string, mixed> $options
     */
    public function __construct(array $args = [], array $options = [])
    {
    }

    public function execute(): bool
    {
        return self::$executeResult;
    }

    public static function reset(): void
    {
        self::$executeResult = false;
    }
}
