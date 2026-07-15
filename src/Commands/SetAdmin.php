<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;
use Gemvc\Helper\ProjectHelper;
use Gemvc\CLI\Commands\DbInit;
use Gemvc\CLI\Commands\DbMigrate;
use Gemvc\CLI\Commands\DbConnect;
use Gemvc\Database\DatabaseManagerFactory;
use App\Model\UserModel;

class SetAdmin extends Command
{
    use ResolvesDatabaseEnvironment;

    protected function readInput(string $prompt): string
    {
        echo $prompt;
        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            return '';
        }
        $input = fgets($handle);
        fclose($handle);

        return $input !== false ? trim($input) : '';
    }

    protected function readPassword(string $prompt): string
    {
        echo $prompt;

        if (DIRECTORY_SEPARATOR !== '\\' && function_exists('shell_exec')) {
            shell_exec('stty -echo');
            $password = trim(fgets(STDIN) ?: '');
            shell_exec('stty echo');
            echo "\n";

            return $password;
        }

        return trim(fgets(STDIN) ?: '');
    }

    protected function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function newDbInit(): DbInit
    {
        return new DbInit();
    }

    /**
     * @param array<int, mixed> $args
     */
    protected function newDbMigrate(array $args): DbMigrate
    {
        return new DbMigrate($args);
    }

    protected function ensureDatabaseInitialized(): bool
    {
        ProjectHelper::loadEnv();
        $driver = $this->resolveDriver();

        if ($driver === 'sqlite') {
            // SQLite databases are just files; db:init lazily creates them on first connect.
            return true;
        }

        $pdoRoot = DbConnect::connectAsRoot();
        if ($pdoRoot === null) {
            $this->error("Cannot connect to database server. Please check your database configuration.");
            return false;
        }

        $dbName = is_string($_ENV['DB_NAME'] ?? null) ? $_ENV['DB_NAME'] : '';
        if ($dbName === '') {
            $this->error("Database name not found in environment variables");
            return false;
        }

        try {
            if ($driver === 'pgsql') {
                $stmt = $pdoRoot->prepare("SELECT 1 FROM pg_database WHERE datname = ?");
            } else {
                $stmt = $pdoRoot->prepare("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?");
            }
            if ($stmt === false) {
                $this->error("Failed to check if database exists");
                return false;
            }
            $stmt->execute([$dbName]);
            $result = $stmt->fetch();
            $dbExists = $result !== false && is_array($result);

            if ($dbExists) {
                $pdo = DbConnect::connect();
                if ($pdo !== null) {
                    return true;
                }
            }

            $this->warning("Database '{$dbName}' is not initialized.");
            $response = strtolower(trim($this->readInput("Do you want to initialize it now? [Y/n]: ")));

            if ($response === '' || $response === 'y' || $response === 'yes') {
                $this->info("Initializing database...");
                $dbInit = $this->newDbInit();
                if (!$dbInit->execute()) {
                    $this->error("Failed to initialize database");
                    return false;
                }
            } else {
                $this->warning("Database initialization skipped. You can run 'gemvc db:init' later.");
            }

            return true;
        } catch (\Exception $e) {
            $this->error("Error checking database: " . $e->getMessage());
            return false;
        }
    }

    protected function ensureUserTableMigrated(): bool
    {
        ProjectHelper::loadEnv();
        $pdo = DbConnect::connect();
        if ($pdo === null) {
            $this->error("Cannot connect to database to check UserTable");
            return false;
        }

        $driver = $this->resolveDriver();
        $dbName = is_string($_ENV['DB_NAME'] ?? null) ? $_ENV['DB_NAME'] : '';
        try {
            if ($driver === 'pgsql') {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'users'");
                $params = [];
            } elseif ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sqlite_master WHERE type = 'table' AND name = 'users'");
                $params = [];
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ? AND table_name = 'users'");
                $params = [$dbName];
            }

            if ($stmt === false) {
                $this->error("Failed to prepare query to check if users table exists");
                return false;
            }
            $stmt->execute($params);
            $result = $stmt->fetch();
            $count = (is_array($result) && isset($result['count']) && is_numeric($result['count'])) ? (int) $result['count'] : 0;
            $tableExists = $count > 0;
        } catch (\Exception $e) {
            $tableExists = false;
        }

        if ($tableExists) {
            return true;
        }

        $this->warning("UserTable is not migrated. You can migrate it with command: gemvc db:migrate UserTable");
        $response = strtolower(trim($this->readInput("Do you want to migrate it now? [Y/n]: ")));

        if ($response === '' || $response === 'y' || $response === 'yes') {
            $this->info("Migrating UserTable...");
            $dbMigrate = $this->newDbMigrate(['UserTable']);
            if (!$dbMigrate->execute()) {
                $this->error("Failed to migrate UserTable");
                return false;
            }
        } else {
            $this->warning("UserTable migration skipped. You can run 'gemvc db:migrate UserTable' later.");
        }

        return true;
    }

    protected function configureCliDatabaseHost(bool $verbose = true): void
    {
        $currentHost = is_string($_ENV['DB_HOST'] ?? null) ? $_ENV['DB_HOST'] : 'localhost';
        $dockerHostnames = ['db', 'mysql', 'database', 'postgres', 'pgsql'];

        if (!in_array(strtolower($currentHost), $dockerHostnames, true)) {
            return;
        }

        $_ENV['DB_HOST'] = 'localhost';
        putenv('DB_HOST=localhost');
        $_ENV['DB_HOST_CLI_DEV'] = 'localhost';
        putenv('DB_HOST_CLI_DEV=localhost');
        DatabaseManagerFactory::resetInstance();

        if ($verbose) {
            $this->info("Using localhost for database connection (CLI mode)");
        }
    }

    protected function ensureNoExistingUsers(): bool
    {
        try {
            $pdo = DbConnect::connect();
            if ($pdo === null) {
                $this->error("Cannot connect to database to check existing users");
                return false;
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
            if ($stmt === false) {
                $this->error("Failed to prepare query to count users");
                return false;
            }

            $stmt->execute();
            $result = $stmt->fetch();
            $userCount = (is_array($result) && isset($result['count']) && is_numeric($result['count'])) ? (int) $result['count'] : 0;

            if ($userCount > 0) {
                $this->error("Cannot perform this operation: Users already exist in the database (count: {$userCount})");
                $this->error("This command can only be used when the database is empty for security reasons.");
                return false;
            }
        } catch (\Exception $e) {
            $this->warning("Could not check user count: " . $e->getMessage());
        }

        return true;
    }

    /**
     * @return array{name: string, email: string, password: string}|null
     */
    protected function promptAdminDetails(): ?array
    {
        $name = '';
        while ($name === '') {
            $name = $this->readInput("Enter admin name: ");
            if ($name === '') {
                $this->warning("Name cannot be empty. Please try again.");
            }
        }

        $email = '';
        while ($email === '') {
            $email = $this->readInput("Enter admin email: ");
            if ($email === '') {
                $this->warning("Email cannot be empty. Please try again.");
            } elseif (!$this->isValidEmail($email)) {
                $this->warning("Invalid email format. Please try again.");
                $email = '';
            }
        }

        $password = '';
        while ($password === '') {
            $password = $this->readPassword("Enter admin password: ");
            if ($password === '') {
                $this->warning("Password cannot be empty. Please try again.");
            }
        }

        $confirmPassword = $this->readPassword("Confirm admin password: ");
        if ($password !== $confirmPassword) {
            $this->error("Passwords do not match");
            return null;
        }

        return ['name' => $name, 'email' => $email, 'password' => $password];
    }

    protected function createAdminUser(string $name, string $email, string $password): bool
    {
        ProjectHelper::loadEnv();
        $this->configureCliDatabaseHost(false);

        $userModel = new UserModel();
        $response = $userModel->firstAdminUser($email, $password, $name);

        if ($response->response_code === 201) {
            $this->success("Admin user created successfully!");
            $this->info("Name: {$name}");
            $this->info("Email: {$email}");
            $this->info("Role: admin");

            return true;
        }

        if ($response->response_code === 403) {
            $this->error($response->message ?? "Admin user already exists");
            return false;
        }

        $errorMessage = $response->service_message ?? $response->message ?? "Unknown error";
        $this->error("Failed to create admin user: {$errorMessage}");

        return false;
    }

    public function execute(): bool
    {
        try {
            $this->info("Creating first admin user...");

            ProjectHelper::loadEnv();

            if (!$this->ensureDatabaseInitialized()) {
                return false;
            }

            if (!$this->ensureUserTableMigrated()) {
                return false;
            }

            $this->configureCliDatabaseHost();

            if (!$this->ensureNoExistingUsers()) {
                return false;
            }

            $details = $this->promptAdminDetails();
            if ($details === null) {
                return false;
            }

            return $this->createAdminUser($details['name'], $details['email'], $details['password']);
        } catch (\Exception $e) {
            $this->error("Failed to create admin user: " . $e->getMessage());
            return false;
        }
    }
}
