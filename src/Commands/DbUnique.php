<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;
use Gemvc\CLI\Commands\DbConnect;

/**
 * CLI Command to add a unique constraint to a table column.
 *
 * Usage:
 *   vendor/bin/gemvc db:unique table/column
 * Example:
 *   vendor/bin/gemvc db:unique users/email,name
 *
 * This command will:
 *   - Check for duplicate values in the specified column
 *   - If no duplicates, add a unique constraint to the column
 *   - If duplicates exist, abort and list the duplicates
 */
class DbUnique extends Command
{
    use ResolvesDatabaseEnvironment;

    public function execute(): bool
    {
        if (empty($this->args[0]) || !is_string($this->args[0])) {
            $this->error("Usage: gemvc db:unique table/column");
            return false;
        }

        $parsed = $this->parseUniqueArgument($this->args[0]);
        if ($parsed === null) {
            $this->error("Invalid format. Use: gemvc db:unique table/col1,col2,...");
            return false;
        }

        $this->loadProjectEnv();
        $pdo = DbConnect::connect();
        if (!$pdo) {
            $this->error("Could not connect to database.");
            return false;
        }

        $duplicates = $this->findDuplicateRows($pdo, $parsed['table'], $parsed['columns']);
        if ($duplicates === false) {
            $this->error("Failed to check for duplicates");
            return false;
        }

        if ($duplicates !== []) {
            return $this->reportDuplicates($parsed['table'], $parsed['columns'], $duplicates);
        }

        return $this->addUniqueConstraint($pdo, $parsed['table'], $parsed['columns']);
    }

    /**
     * @return array{table: string, columns: list<string>}|null
     */
    protected function parseUniqueArgument(string $argument): ?array
    {
        if (!str_contains($argument, '/')) {
            return null;
        }

        [$table, $columns] = explode('/', $argument, 2);
        $columnList = array_values(array_filter(
            array_map('trim', explode(',', $columns)),
            static fn (string $column): bool => $column !== ''
        ));

        if ($table === '' || $columnList === []) {
            return null;
        }

        return ['table' => $table, 'columns' => $columnList];
    }

    protected function quoteIdentifier(string $identifier): string
    {
        $driver = $this->resolveDriver();

        if ($driver === 'pgsql' || $driver === 'sqlite') {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }

        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * @param list<string> $columns
     */
    protected function buildDuplicateCheckSql(string $table, array $columns): string
    {
        $quotedColumns = array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns);
        $colSql = implode(',', $quotedColumns);

        return "SELECT $colSql, COUNT(*) as cnt FROM " . $this->quoteIdentifier($table) . " GROUP BY $colSql HAVING cnt > 1";
    }

    /**
     * @param list<string> $columns
     * @return list<array<string, mixed>>|false
     */
    protected function findDuplicateRows(\PDO $pdo, string $table, array $columns): array|false
    {
        $stmt = $pdo->query($this->buildDuplicateCheckSql($table, $columns));
        if ($stmt === false) {
            return false;
        }

        return array_values($stmt->fetchAll());
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>> $duplicates
     */
    protected function reportDuplicates(string $table, array $columns, array $duplicates): bool
    {
        $this->error(
            "Cannot add unique constraint: Duplicate value combinations found in (" . implode(', ', $columns) . ")."
        );

        foreach ($duplicates as $row) {
            $values = [];
            foreach ($columns as $col) {
                $cell = $row[$col] ?? '';
                $values[] = $col . '=' . (is_scalar($cell) ? (string) $cell : '');
            }
            $this->write("Duplicate: " . implode(', ', $values));
        }

        return false;
    }

    /**
     * @param list<string> $columns
     */
    protected function addUniqueConstraint(\PDO $pdo, string $table, array $columns): bool
    {
        $driver = $this->resolveDriver();
        $constraintName = 'unique_' . implode('_', $columns);
        $quotedColumns = implode(',', array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns));
        $quotedTable = $this->quoteIdentifier($table);

        try {
            if ($driver === 'sqlite') {
                // SQLite does not support ALTER TABLE ... ADD CONSTRAINT; use a unique index instead.
                $quotedIndexName = $this->quoteIdentifier($constraintName);
                $pdo->exec("CREATE UNIQUE INDEX {$quotedIndexName} ON {$quotedTable} ($quotedColumns)");
            } else {
                $quotedConstraintName = $this->quoteIdentifier($constraintName);
                $pdo->exec("ALTER TABLE {$quotedTable} ADD CONSTRAINT {$quotedConstraintName} UNIQUE ($quotedColumns)");
            }

            $this->success("Unique constraint added to {$quotedTable} on (" . implode(', ', $columns) . ") successfully!");

            return true;
        } catch (\PDOException $e) {
            $this->error("Failed to add unique constraint: " . $e->getMessage());

            return false;
        }
    }
}
