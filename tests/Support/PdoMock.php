<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Support;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PdoMock
{
    /**
     * @param array<string, callable|string|array<int, mixed>|false> $queryHandlers SQL substring => result
     */
    public static function create(TestCase $test, array $queryHandlers = []): \PDO&MockObject
    {
        /** @var \PDO&MockObject $pdo */
        $pdo = $test->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();

        $pdo->method('query')->willReturnCallback(
            static function (string $sql) use ($test, $queryHandlers): \PDOStatement|false {
                foreach ($queryHandlers as $needle => $handler) {
                    if (stripos($sql, (string) $needle) === false) {
                        continue;
                    }

                    if (is_callable($handler)) {
                        return $handler($sql);
                    }

                    return self::statement($test, $handler);
                }

                return false;
            }
        );

        $pdo->method('prepare')->willReturnCallback(
            static function (string $sql) use ($test, $queryHandlers): \PDOStatement|false {
                foreach ($queryHandlers as $needle => $handler) {
                    if (stripos($sql, (string) $needle) === false) {
                        continue;
                    }

                    if (is_callable($handler)) {
                        return $handler($sql);
                    }

                    return self::statement($test, $handler, true);
                }

                return false;
            }
        );

        $pdo->method('exec')->willReturn(0);

        return $pdo;
    }

    /**
     * @param array<int, mixed>|mixed $result
     */
    public static function statement(TestCase $test, mixed $result, bool $prepared = false): \PDOStatement&MockObject
    {
        /** @var \PDOStatement&MockObject $stmt */
        $stmt = $test->getMockBuilder(\PDOStatement::class)->disableOriginalConstructor()->getMock();

        if (is_array($result) && array_is_list($result) && isset($result[0]) && is_array($result[0])) {
            $rows = $result;
            $stmt->method('fetch')->willReturnCallback(static function () use (&$rows) {
                return array_shift($rows);
            });
            $stmt->method('fetchAll')->willReturn($result);
        } elseif ($result === false) {
            $stmt->method('fetch')->willReturn(false);
            $stmt->method('fetchAll')->willReturn([]);
        } else {
            $rows = is_array($result) ? $result : [$result];
            $stmt->method('fetch')->willReturnCallback(static function () use (&$rows) {
                return array_shift($rows);
            });
            $stmt->method('fetchAll')->willReturn(is_array($result) && array_is_list($result) ? $result : [$result]);
        }

        $stmt->method('rowCount')->willReturn(is_array($result) ? count($result) : 1);
        $stmt->method('execute')->willReturn(true);

        if ($prepared) {
            return $stmt;
        }

        return $stmt;
    }
}
