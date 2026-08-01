<?php

namespace Gemvc\CLI\Commands;

/**
 * Shared relation (base table + view) introspection for db:list / db:describe / db:drop.
 * Keeps driver SQL in one place; does not depend on gemvc/library dialects.
 */
trait ResolvesDatabaseRelations
{
    /**
     * @return list<array{name: string, kind: 'table'|'view'}>|false
     */
    protected function fetchRelations(\PDO $pdo, string $dbName): array|false
    {
        $driver = $this->resolveDriver();

        if ($driver === 'pgsql') {
            $stmt = $pdo->query(
                "SELECT table_name AS name, table_type AS kind
                 FROM information_schema.tables
                 WHERE table_schema = current_schema()
                   AND table_type IN ('BASE TABLE', 'VIEW')
                 ORDER BY CASE table_type WHEN 'BASE TABLE' THEN 0 ELSE 1 END, table_name"
            );
        } elseif ($driver === 'sqlite') {
            $stmt = $pdo->query(
                "SELECT name AS name, type AS kind
                 FROM sqlite_master
                 WHERE type IN ('table', 'view')
                   AND name NOT LIKE 'sqlite_%'
                 ORDER BY CASE type WHEN 'table' THEN 0 ELSE 1 END, name"
            );
        } else {
            // MySQL / MariaDB — FULL TABLES returns Table_type: BASE TABLE | VIEW
            $stmt = $pdo->query("SHOW FULL TABLES FROM `{$dbName}`");
        }

        if ($stmt === false) {
            return false;
        }

        if ($driver === 'mysql' || ($driver !== 'pgsql' && $driver !== 'sqlite')) {
            $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
            $out = [];
            foreach ($rows as $row) {
                if (!is_array($row) || !isset($row[0], $row[1]) || !is_string($row[0]) || !is_string($row[1])) {
                    continue;
                }
                $kind = $this->normalizeRelationKind($row[1]);
                if ($kind === null) {
                    continue;
                }
                $out[] = ['name' => $row[0], 'kind' => $kind];
            }

            return $out;
        }

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = $row['name'] ?? null;
            $rawKind = $row['kind'] ?? null;
            if (!is_string($name) || !is_string($rawKind)) {
                continue;
            }
            $kind = $this->normalizeRelationKind($rawKind);
            if ($kind === null) {
                continue;
            }
            $out[] = ['name' => $name, 'kind' => $kind];
        }

        return $out;
    }

    /**
     * @return 'table'|'view'|null
     */
    protected function normalizeRelationKind(string $raw): ?string
    {
        $raw = strtoupper(trim($raw));

        return match ($raw) {
            'BASE TABLE', 'TABLE' => 'table',
            'VIEW' => 'view',
            default => null,
        };
    }

    /**
     * Whether a base table or view with this name exists.
     */
    protected function relationExists(\PDO $pdo, string $dbName, string $name): bool
    {
        return $this->resolveRelationKind($pdo, $dbName, $name) !== null;
    }

    /**
     * @return 'table'|'view'|null
     */
    protected function resolveRelationKind(\PDO $pdo, string $dbName, string $name): ?string
    {
        $driver = $this->resolveDriver();

        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare(
                "SELECT table_type
                 FROM information_schema.tables
                 WHERE table_schema = current_schema()
                   AND table_name = :name
                   AND table_type IN ('BASE TABLE', 'VIEW')
                 LIMIT 1"
            );
            if ($stmt === false) {
                return null;
            }
            $stmt->execute([':name' => $name]);
            $raw = $stmt->fetchColumn();

            return is_string($raw) ? $this->normalizeRelationKind($raw) : null;
        }

        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare(
                "SELECT type FROM sqlite_master
                 WHERE name = ? AND type IN ('table', 'view')
                 LIMIT 1"
            );
            if ($stmt === false) {
                return null;
            }
            $stmt->execute([$name]);
            $raw = $stmt->fetchColumn();

            return is_string($raw) ? $this->normalizeRelationKind($raw) : null;
        }

        $stmt = $pdo->prepare(
            "SELECT TABLE_TYPE
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND TABLE_TYPE IN ('BASE TABLE', 'VIEW')
             LIMIT 1"
        );
        if ($stmt === false) {
            return null;
        }
        $stmt->execute([$dbName, $name]);
        $raw = $stmt->fetchColumn();

        return is_string($raw) ? $this->normalizeRelationKind($raw) : null;
    }

    /**
     * Best-effort CREATE VIEW / definition text for describe output.
     */
    protected function fetchViewDefinition(\PDO $pdo, string $viewName): ?string
    {
        $driver = $this->resolveDriver();

        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare('SELECT pg_get_viewdef(:name::regclass, true)');
            $stmt->execute([':name' => $viewName]);
            $def = $stmt->fetchColumn();

            return is_string($def) && $def !== '' ? $def : null;
        }

        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare(
                "SELECT sql FROM sqlite_master WHERE type = 'view' AND name = ? LIMIT 1"
            );
            $stmt->execute([$viewName]);
            $sql = $stmt->fetchColumn();

            return is_string($sql) && $sql !== '' ? $sql : null;
        }

        $stmt = $pdo->query('SHOW CREATE VIEW `' . str_replace('`', '``', $viewName) . '`');
        if ($stmt === false) {
            return null;
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        foreach (['Create View', 'Create view', 'CREATE VIEW'] as $key) {
            if (isset($row[$key]) && is_string($row[$key]) && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    protected function dropRelationSql(string $name, string $kind): string
    {
        $driver = $this->resolveDriver();
        $quoted = $driver === 'pgsql' || $driver === 'sqlite'
            ? '"' . str_replace('"', '""', $name) . '"'
            : '`' . str_replace('`', '``', $name) . '`';

        if ($kind === 'view') {
            return "DROP VIEW {$quoted}";
        }

        return "DROP TABLE {$quoted}";
    }
}
