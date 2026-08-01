<?php

namespace Gemvc\CLI\Commands;
use Gemvc\CLI\CliColor;

use Gemvc\CLI\CliLine;
use Gemvc\CLI\Command;
use Gemvc\CLI\Commands\CliBoxShow;
use Gemvc\CLI\Commands\DbConnect;
class DbDescribe extends Command
{
    use ResolvesDatabaseEnvironment;
    use ResolvesDatabaseRelations;

    private const TABLE_BOX_WIDTH = 78;

    protected string $description = "Describe a specific database table or view structure in detail. Shows columns, indexes, foreign keys, statistics, and view definition for views.";

    public function execute(): bool
    {
        try {
            $tableName = $this->parseTableArgument();
            if ($tableName === null) {
                return false;
            }

            $this->loadProjectEnv();

            $dbName = $this->resolveDatabaseName();
            if ($dbName === null) {
                throw new \Exception("Database name not found in configuration (DB_NAME)");
            }

            $pdo = DbConnect::connect();
            if (!$pdo) {
                $this->error("Failed to connect to database");
                return false;
            }

            $kind = $this->resolveRelationKind($pdo, $dbName, $tableName);
            if ($kind === null) {
                $this->error("Table or view '{$tableName}' not found in database '{$dbName}'");
                return false;
            }

            $this->renderTableDescription($pdo, $tableName, $dbName, $kind);

            $this->write("\n");

            return true;
        } catch (\Exception $e) {
            $this->error("Failed to describe table: " . $e->getMessage());
            return false;
        }
    }

    protected function parseTableArgument(): ?string
    {
        if (empty($this->args[0])) {
            $this->error("Table or view name is required. Usage: gemvc db:describe Name");
            return null;
        }

        if (!is_string($this->args[0])) {
            $this->error("Table name must be a string");
            return null;
        }

        return $this->args[0];
    }

    /**
     * @param 'table'|'view' $kind
     */
    protected function renderTableDescription(\PDO $pdo, string $tableName, string $dbName, string $kind): void
    {
        $this->displayTableHeader($tableName, $kind);
        $this->showTableStructure($pdo, $tableName);

        if ($kind === 'view') {
            $this->showViewDefinition($pdo, $tableName);
        }

        $this->showIndexes($pdo, $tableName, $kind);
        $this->showForeignKeys($pdo, $tableName, $dbName, $kind);
        $this->showTableStatistics($pdo, $tableName, $dbName);
        $this->showTableOptions($pdo, $tableName, $dbName);
    }

    /**
     * @return list<array<string, mixed>>|false
     */
    protected function fetchColumns(\PDO $pdo, string $tableName): array|false
    {
        $driver = $this->resolveDriver();

        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT column_name AS \"Field\", udt_name AS \"Type\", is_nullable AS \"Null\", column_default AS \"Default\", ordinal_position AS \"Ordinal_Position\" FROM information_schema.columns WHERE table_name = :tableName ORDER BY ordinal_position");
            $stmt->execute([':tableName' => $tableName]);
            return array_values($stmt->fetchAll(\PDO::FETCH_ASSOC));
        }

        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info('{$tableName}')");
            if ($stmt === false) {
                return false;
            }

            $columns = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $columns[] = [
                    'Field' => $row['name'] ?? '',
                    'Type' => $row['type'] ?? '',
                    'Null' => (isset($row['notnull']) && (int) $row['notnull'] === 1) ? 'NO' : 'YES',
                    'Key' => (isset($row['pk']) && (int) $row['pk'] === 1) ? 'PRI' : '',
                    'Default' => $row['dflt_value'] ?? null,
                    'Extra' => '',
                ];
            }

            return $columns;
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM `{$tableName}`");
        if ($stmt === false) {
            return false;
        }

        return array_values($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    protected function showViewDefinition(\PDO $pdo, string $viewName): void
    {
        $this->displaySectionHeader("VIEW DEFINITION");

        $definition = $this->fetchViewDefinition($pdo, $viewName);
        if ($definition === null) {
            $this->echoBoxRow('No view definition available');
            $this->echoBoxClose();
            return;
        }

        foreach (preg_split("/\r\n|\n|\r/", $definition) ?: [] as $line) {
            $this->echoBoxRow($line);
        }
        $this->echoBoxClose();
    }

    protected function showTableStructure(\PDO $pdo, string $tableName): void
    {
        $this->displaySectionHeader("📋 COLUMNS");

        $columns = $this->fetchColumns($pdo, $tableName);
        if ($columns === false) {
            $this->error("Failed to query table columns");
            return;
        }

        if (empty($columns)) {
            $this->echoBoxRow('No columns found');
            $this->echoBoxClose();
            return;
        }

        // Prepare data for table formatting
        $tableData = [];
        $headers = ['Field', 'Type', 'Null', 'Key', 'Default', 'Extra'];
        
        foreach ($columns as $column) {
            $key = isset($column['Key']) && is_string($column['Key']) ? $column['Key'] : '';
            $keyType = match ($key) {
                'PRI' => '🔑 PRI',
                'UNI' => '🔒 UNI',
                'MUL' => '📚 MUL',
                default => $key !== '' ? $key : '-',
            };

            $nullFlag = isset($column['Null']) && is_string($column['Null']) ? $column['Null'] : '';
            $null = $nullFlag === 'YES' ? '✓' : '✗';

            $tableData[] = [
                $this->stringifyCell($column['Field'] ?? null),
                $this->stringifyCell($column['Type'] ?? null),
                $null,
                $keyType,
                $this->stringifyCell($column['Default'] ?? null),
                $this->stringifyCell($column['Extra'] ?? null),
            ];
        }

        $this->displayTable($headers, $tableData);
    }

    /**
     * @param 'table'|'view' $kind
     * @return list<array<string, mixed>>|false
     */
    protected function fetchIndexes(\PDO $pdo, string $tableName, string $kind = 'table'): array|false
    {
        $driver = $this->resolveDriver();

        if ($driver === 'sqlite' || $kind === 'view') {
            return [];
        }

        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT i.relname AS \"Key_name\", ix.indisunique AS \"Non_unique\", a.attname AS \"Column_name\", NULL AS \"Sub_part\", CASE WHEN ix.indisprimary THEN 'PRIMARY' ELSE 'INDEX' END AS \"Index_type\" FROM pg_index ix JOIN pg_class t ON t.oid = ix.indrelid JOIN pg_class i ON i.oid = ix.indexrelid JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey) WHERE t.relname = :tableName AND t.relkind IN ('r', 'v') ORDER BY i.relname, a.attnum");
            $stmt->execute([':tableName' => $tableName]);
            return array_values($stmt->fetchAll(\PDO::FETCH_ASSOC));
        }

        $stmt = $pdo->query("SHOW INDEX FROM `{$tableName}`");
        if ($stmt === false) {
            return false;
        }

        return array_values($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @param 'table'|'view' $kind
     */
    protected function showIndexes(\PDO $pdo, string $tableName, string $kind = 'table'): void
    {
        $this->displaySectionHeader("🔍 INDEXES");

        $indexes = $this->fetchIndexes($pdo, $tableName, $kind);
        if ($indexes === false) {
            $this->error("Failed to query table indexes");
            return;
        }

        if (empty($indexes)) {
            $this->echoBoxRow('No indexes found');
            $this->echoBoxClose();
            return;
        }

        $groupedIndexes = [];
        foreach ($indexes as $index) {
            $keyName = isset($index['Key_name']) && is_string($index['Key_name']) ? $index['Key_name'] : '';
            $groupedIndexes[$keyName][] = $index;
        }

        $tableData = [];
        $headers = ['Index Name', 'Type', 'Unique', 'Columns'];

        foreach ($groupedIndexes as $indexName => $indexColumns) {
            $firstColumn = $indexColumns[0];
            $nonUnique = $firstColumn['Non_unique'] ?? 1;
            $unique = $nonUnique == 0 ? '🔒 Yes' : '❌ No';
            $type = $this->stringifyCell($firstColumn['Index_type'] ?? null);

            $columns = array_map(function (array $col): string {
                $name = $this->stringifyCell($col['Column_name'] ?? null);
                $subPart = $col['Sub_part'] ?? null;

                return $name . (is_scalar($subPart) && $subPart !== '' ? '(' . (string) $subPart . ')' : '');
            }, $indexColumns);
            
            $indexIcon = match($indexName) {
                'PRIMARY' => '🔑',
                default => match(true) {
                    $firstColumn['Non_unique'] == 0 => '🔒',
                    default => '📋'
                }
            };
            
            $tableData[] = [
                $indexIcon . ' ' . $indexName,
                $type,
                $unique,
                implode(', ', $columns),
            ];
        }

        $this->displayTable($headers, $tableData);
    }

    /**
     * @param 'table'|'view' $kind
     * @return list<array<string, mixed>>
     */
    protected function fetchForeignKeys(\PDO $pdo, string $tableName, string $dbName, string $kind = 'table'): array
    {
        $driver = $this->resolveDriver();

        if ($driver === 'sqlite' || $kind === 'view') {
            return [];
        }

        if ($driver === 'pgsql') {
            $query = "
                SELECT
                    tc.constraint_name AS CONSTRAINT_NAME,
                    kcu.column_name AS COLUMN_NAME,
                    ccu.table_name AS REFERENCED_TABLE_NAME,
                    ccu.column_name AS REFERENCED_COLUMN_NAME
                FROM information_schema.table_constraints AS tc
                JOIN information_schema.key_column_usage AS kcu
                    ON tc.constraint_name = kcu.constraint_name
                JOIN information_schema.constraint_column_usage AS ccu
                    ON ccu.constraint_name = tc.constraint_name
                WHERE tc.constraint_type = 'FOREIGN KEY'
                  AND tc.table_name = :tableName
                  AND tc.table_schema = current_schema()
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute([':tableName' => $tableName]);
            return array_values($stmt->fetchAll(\PDO::FETCH_ASSOC));
        }

        $query = "
            SELECT 
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ? 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$dbName, $tableName]);

        return array_values($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @param 'table'|'view' $kind
     * @return list<array<string, mixed>>
     */
    protected function fetchReferentialConstraints(\PDO $pdo, string $tableName, string $dbName, string $kind = 'table'): array
    {
        $driver = $this->resolveDriver();

        if ($driver === 'sqlite' || $kind === 'view') {
            return [];
        }

        if ($driver === 'pgsql') {
            $constraintQuery = "
                SELECT
                    tc.constraint_name AS CONSTRAINT_NAME,
                    rc.delete_rule AS DELETE_RULE,
                    rc.update_rule AS UPDATE_RULE
                FROM information_schema.table_constraints tc
                JOIN information_schema.referential_constraints rc
                    ON tc.constraint_name = rc.constraint_name
                WHERE tc.constraint_type = 'FOREIGN KEY'
                  AND tc.table_name = :tableName
                  AND tc.table_schema = current_schema()
            ";

            $constraintStmt = $pdo->prepare($constraintQuery);
            $constraintStmt->execute([':tableName' => $tableName]);
            return array_values($constraintStmt->fetchAll(\PDO::FETCH_ASSOC));
        }

        $constraintQuery = "
            SELECT 
                CONSTRAINT_NAME,
                DELETE_RULE,
                UPDATE_RULE
            FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = ? 
            AND TABLE_NAME = ?
        ";

        $constraintStmt = $pdo->prepare($constraintQuery);
        $constraintStmt->execute([$dbName, $tableName]);

        return array_values($constraintStmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @param 'table'|'view' $kind
     */
    protected function showForeignKeys(\PDO $pdo, string $tableName, string $dbName, string $kind = 'table'): void
    {
        $this->displaySectionHeader("🔗 FOREIGN KEYS");

        $foreignKeys = $this->fetchForeignKeys($pdo, $tableName, $dbName, $kind);

        if ($foreignKeys === []) {
            $this->echoBoxRow('No foreign keys found');
            $this->echoBoxClose();
            return;
        }

        $constraints = $this->fetchReferentialConstraints($pdo, $tableName, $dbName, $kind);

        // Create a lookup array for constraints
        $constraintRules = [];
        foreach ($constraints as $constraint) {
            $constraintName = isset($constraint['CONSTRAINT_NAME']) && is_string($constraint['CONSTRAINT_NAME'])
                ? $constraint['CONSTRAINT_NAME']
                : '';
            $constraintRules[$constraintName] = [
                'DELETE_RULE' => $this->stringifyCell($constraint['DELETE_RULE'] ?? null),
                'UPDATE_RULE' => $this->stringifyCell($constraint['UPDATE_RULE'] ?? null),
            ];
        }

        $tableData = [];
        $headers = ['Constraint', 'Column', 'References', 'On Delete', 'On Update'];

        foreach ($foreignKeys as $fk) {
            $constraintName = isset($fk['CONSTRAINT_NAME']) && is_string($fk['CONSTRAINT_NAME'])
                ? $fk['CONSTRAINT_NAME']
                : '';
            $deleteRule = 'N/A';
            $updateRule = 'N/A';

            if (isset($constraintRules[$constraintName])) {
                $rules = $constraintRules[$constraintName];
                $deleteRule = $rules['DELETE_RULE'];
                $updateRule = $rules['UPDATE_RULE'];
            }

            $tableData[] = [
                '🔗 ' . $constraintName,
                $this->stringifyCell($fk['COLUMN_NAME'] ?? null),
                $this->stringifyCell($fk['REFERENCED_TABLE_NAME'] ?? null) . '.'
                    . $this->stringifyCell($fk['REFERENCED_COLUMN_NAME'] ?? null),
                $deleteRule,
                $updateRule,
            ];
        }

        $this->displayTable($headers, $tableData);
    }

    /**
     * @return array<string, mixed>|false
     */
    protected function fetchTableStatistics(\PDO $pdo, string $tableName, string $dbName): array|false
    {
        $driver = $this->resolveDriver();

        if ($driver === 'sqlite') {
            return false;
        }

        if ($driver === 'pgsql') {
            $query = "
                SELECT
                    CASE WHEN (SELECT reltuples::bigint FROM pg_class WHERE oid = to_regclass(:tableName)) < 0 THEN 0 ELSE (SELECT reltuples::bigint FROM pg_class WHERE oid = to_regclass(:tableName)) END AS row_count,
                    0 AS data_size,
                    0 AS index_size,
                    0 AS total_size,
                    NULL AS next_auto_increment
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute([':tableName' => $tableName]);
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $this->normalizeAssocRow($stats);
        }

        $query = "
            SELECT 
                TABLE_ROWS as row_count,
                DATA_LENGTH as data_size,
                INDEX_LENGTH as index_size,
                (DATA_LENGTH + INDEX_LENGTH) as total_size,
                AUTO_INCREMENT as next_auto_increment
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$dbName, $tableName]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $this->normalizeAssocRow($stats);
    }

    protected function showTableStatistics(\PDO $pdo, string $tableName, string $dbName): void
    {
        $this->displaySectionHeader("📊 STATISTICS");

        $stats = $this->fetchTableStatistics($pdo, $tableName, $dbName);

        if ($stats !== false) {
            $rowCount = isset($stats['row_count']) && is_numeric($stats['row_count']) ? (int) $stats['row_count'] : 0;
            $dataSize = isset($stats['data_size']) && is_numeric($stats['data_size']) ? (int) $stats['data_size'] : 0;
            $indexSize = isset($stats['index_size']) && is_numeric($stats['index_size']) ? (int) $stats['index_size'] : 0;
            $totalSize = isset($stats['total_size']) && is_numeric($stats['total_size']) ? (int) $stats['total_size'] : 0;
            
            $tableData = [
                ['📋 Total Rows', number_format($rowCount)],
                ['💾 Data Size', $this->formatBytes($dataSize)],
                ['🔍 Index Size', $this->formatBytes($indexSize)],
                ['📦 Total Size', $this->formatBytes($totalSize)],
            ];
            
            if (isset($stats['next_auto_increment']) && $stats['next_auto_increment'] && is_numeric($stats['next_auto_increment'])) {
                $tableData[] = ['🔢 Next Auto Increment', number_format((int) $stats['next_auto_increment'])];
            }

            $this->displayTable(['Metric', 'Value'], $tableData);
        } else {
            $this->echoBoxRow('No statistics available');
            $this->echoBoxClose();
        }
    }

    /**
     * @return array<string, mixed>|false
     */
    protected function fetchTableOptions(\PDO $pdo, string $tableName, string $dbName): array|false
    {
        $driver = $this->resolveDriver();

        if ($driver === 'sqlite') {
            return false;
        }

        if ($driver === 'pgsql') {
            $query = "
                SELECT
                    'PostgreSQL' AS ENGINE,
                    current_setting('server_version_num') AS TABLE_COLLATION,
                    NULL AS CREATE_TIME,
                    NULL AS UPDATE_TIME,
                    NULL AS TABLE_COMMENT
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $options = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $this->normalizeAssocRow($options);
        }

        $query = "
            SELECT 
                ENGINE,
                TABLE_COLLATION,
                CREATE_TIME,
                UPDATE_TIME,
                TABLE_COMMENT
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$dbName, $tableName]);
        $options = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $this->normalizeAssocRow($options);
    }

    protected function showTableOptions(\PDO $pdo, string $tableName, string $dbName): void
    {
        $this->displaySectionHeader("⚙️ TABLE OPTIONS");

        $options = $this->fetchTableOptions($pdo, $tableName, $dbName);

        if ($options !== false) {
            $engine = isset($options['ENGINE']) && is_string($options['ENGINE']) ? $options['ENGINE'] : 'Unknown';
            $collation = isset($options['TABLE_COLLATION']) && is_string($options['TABLE_COLLATION']) ? $options['TABLE_COLLATION'] : 'Unknown';
            
            $tableData = [
                ['🚀 Engine', $engine],
                ['🔤 Collation', $collation],
            ];
            
            if (isset($options['CREATE_TIME']) && $options['CREATE_TIME'] && is_string($options['CREATE_TIME'])) {
                $tableData[] = ['📅 Created', $options['CREATE_TIME']];
            }
            
            if (isset($options['UPDATE_TIME']) && $options['UPDATE_TIME'] && is_string($options['UPDATE_TIME'])) {
                $tableData[] = ['🔄 Last Updated', $options['UPDATE_TIME']];
            }
            
            if (isset($options['TABLE_COMMENT']) && $options['TABLE_COMMENT'] && is_string($options['TABLE_COMMENT'])) {
                $tableData[] = ['💬 Comment', $options['TABLE_COMMENT']];
            }

            $this->displayTable(['Option', 'Value'], $tableData);
        } else {
            $this->echoBoxRow('No table options available');
            $this->echoBoxClose();
        }
    }

    /**
     * @return array<string, mixed>|false
     */
    protected function normalizeAssocRow(mixed $row): array|false
    {
        if (!is_array($row)) {
            return false;
        }

        $normalized = [];
        foreach ($row as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    protected function stringifyCell(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '-';
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * @param 'table'|'view' $kind
     */
    protected function displayTableHeader(string $tableName, string $kind = 'table'): void
    {
        $prefix = $kind === 'view' ? 'VIEW: ' : 'TABLE: ';
        $boxShow = new CliBoxShow();
        $boxShow->displayInfoBox($prefix . strtoupper($tableName), []);
    }

    protected function displaySectionHeader(string $title): void
    {
        $this->write("\n", CliColor::White);
        $this->write('┌' . str_repeat('─', self::TABLE_BOX_WIDTH) . "┐\n", CliColor::Blue);

        $titleWidth = $this->getDisplayWidth($title);
        $padding = self::TABLE_BOX_WIDTH - $titleWidth - 2;
        
        $this->write("│ " . $title . str_repeat(" ", $padding) . " │\n", CliColor::Green);
        $this->write('├' . str_repeat('─', self::TABLE_BOX_WIDTH) . "┤\n", CliColor::Blue);
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string>> $data
     */
    protected function displayTable(array $headers, array $data): void
    {
        if (empty($data)) {
            $this->echoBoxRow('No data available');
            $this->echoBoxClose();
            return;
        }

        // Calculate column widths more accurately
        $columnWidths = [];
        $numColumns = count($headers);
        $totalOuterWidth = self::TABLE_BOX_WIDTH;
        $totalBorderWidth = 1 + ($numColumns * 3) + 1; // │ + (numColumns * " │ ") + │
        $availableWidth = $totalOuterWidth - $totalBorderWidth;
        
        // Initialize with header widths (accounting for unicode/emoji properly)
        foreach ($headers as $i => $header) {
            $columnWidths[$i] = $this->getDisplayWidth($header);
        }
        
        // Check data widths
        foreach ($data as $row) {
            foreach ($row as $i => $cell) {
                $cellWidth = $this->getDisplayWidth($cell);
                if ($cellWidth > $columnWidths[$i]) {
                    $columnWidths[$i] = $cellWidth;
                }
            }
        }
        
        // Adjust widths if they exceed available space
        $totalUsed = array_sum($columnWidths);
        if ($totalUsed > $availableWidth) {
            // Distribute available width proportionally
            $factor = $availableWidth / $totalUsed;
            foreach ($columnWidths as $i => $width) {
                $columnWidths[$i] = max(6, floor($width * $factor)); // Minimum 6 chars
            }
        }

        // Display headers
        $this->write("│", CliColor::Blue);
        foreach ($headers as $i => $header) {
            $this->write(" " . $this->padString($header, (int) $columnWidths[$i]), CliColor::Yellow);
            $this->write(" │", CliColor::Blue);
        }
        $this->write("\n");

        // Header separator
        $this->write("├", CliColor::Blue);
        foreach ($columnWidths as $i => $width) {
            $this->write(str_repeat("─", (int) $width + 2), CliColor::Blue);
            if ($i < count($columnWidths) - 1) {
                $this->write("┼", CliColor::Blue);
            }
        }
        $this->write("┤\n", CliColor::Blue);

        // Display data rows
        foreach ($data as $row) {
            $this->write("│", CliColor::Blue);
            foreach ($row as $i => $cell) {
                $truncated = $this->getDisplayWidth($cell) > $columnWidths[$i] 
                    ? $this->truncateString($cell, (int) $columnWidths[$i] - 3) . '...'
                    : $cell;
                $this->write(" " . $this->padString($truncated, (int) $columnWidths[$i]), CliColor::White);
                $this->write(" │", CliColor::Blue);
            }
            $this->write("\n");
        }

        $this->echoBoxClose();
    }

    protected function echoBoxRow(string $content): void
    {
        echo CliLine::boxRow($content, self::TABLE_BOX_WIDTH, CliColor::Blue);
    }

    protected function echoBoxClose(): void
    {
        $this->write('└' . str_repeat('─', self::TABLE_BOX_WIDTH) . "┘\n", CliColor::Blue);
    }

    protected function getDisplayWidth(string $text): int
    {
        return CliLine::displayWidth($text);
    }

    protected function padString(string $text, int $width): string
    {
        $displayWidth = $this->getDisplayWidth($text);
        $padding = $width - $displayWidth;
        return $text . str_repeat(' ', max(0, $padding));
    }

    protected function truncateString(string $text, int $maxWidth): string
    {
        if ($this->getDisplayWidth($text) <= $maxWidth) {
            return $text;
        }
        
        $truncated = '';
        $currentWidth = 0;
        
        for ($i = 0; $i < mb_strlen($text); $i++) {
            $char = mb_substr($text, $i, 1);
            $charWidth = mb_strwidth($char);
            
            if ($currentWidth + $charWidth > $maxWidth) {
                break;
            }
            
            $truncated .= $char;
            $currentWidth += $charWidth;
        }
        
        return $truncated;
    }
} 