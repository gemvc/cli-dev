<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;
use Gemvc\Helper\ProjectHelper;

class AdminSetpassword extends Command
{
    protected function readEnvContent(string $envPath): ?string
    {
        if (!is_file($envPath)) {
            $this->error(!file_exists($envPath)
                ? ".env file not found at: {$envPath}"
                : "Failed to read .env file");

            return null;
        }

        $envContent = file_get_contents($envPath);
        if ($envContent === false) {
            $this->error("Failed to read .env file");

            return null;
        }

        return $envContent;
    }

    protected function mergeAdminPassword(string $envContent, string $password): string
    {
        $pattern = '/^ADMIN_PASSWORD\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\n\r]*)/m';

        if (preg_match($pattern, $envContent)) {
            $replacement = 'ADMIN_PASSWORD="' . $password . '"';
            $merged = preg_replace($pattern, $replacement, $envContent);

            return is_string($merged) ? $merged : $envContent;
        }

        if (preg_match('/^APP_ENV\s*=.*$/m', $envContent, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);

            return substr_replace($envContent, "\nADMIN_PASSWORD=\"" . $password . "\"", $pos, 0);
        }

        return $envContent . "\nADMIN_PASSWORD=\"" . $password . "\"\n";
    }

    protected function writeEnvContent(string $envPath, string $envContent): bool
    {
        if (file_put_contents($envPath, $envContent) === false) {
            $this->error("Failed to write to .env file");

            return false;
        }

        return true;
    }

    /**
     * Update ADMIN_PASSWORD in .env file
     */
    protected function updateEnvFile(string $envPath, string $password): bool
    {
        $envContent = $this->readEnvContent($envPath);
        if ($envContent === null) {
            return false;
        }

        $merged = $this->mergeAdminPassword($envContent, $password);

        return $this->writeEnvContent($envPath, $merged);
    }

    /**
     * Read password from user input (hidden on Unix, visible on Windows)
     */
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

    public function execute(): bool
    {
        try {
            $this->info("Setting admin password for system pages...");

            $rootDir = ProjectHelper::rootDir();
            $envPath = $rootDir . DIRECTORY_SEPARATOR . '.env';

            if (!file_exists($envPath)) {
                $this->error(".env file not found. Please run 'gemvc init' first.");
                return false;
            }

            $password = $this->readPassword("Enter admin password: ");

            if (empty($password)) {
                $this->error("Password cannot be empty");
                return false;
            }

            $confirmPassword = $this->readPassword("Confirm admin password: ");

            if ($password !== $confirmPassword) {
                $this->error("Passwords do not match");
                return false;
            }

            if ($this->updateEnvFile($envPath, $password)) {
                $this->success("Admin password set successfully!");
                $this->info("You can now access system pages using this password.");

                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->error("Failed to set admin password: " . $e->getMessage());
            return false;
        }
    }
}
