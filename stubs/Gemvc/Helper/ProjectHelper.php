<?php

declare(strict_types=1);

namespace Gemvc\Helper;

use Composer\InstalledVersions;
use Symfony\Component\Dotenv\Dotenv;

class ProjectHelper
{
    private static ?string $rootDir = null;

    /** @var array<string, string> */
    private static array $testEnv = [];

    private static ?string $testRootDir = null;

    private static bool $testReplaceEnv = false;

    /**
     * @param array<string, string> $env
     *
     * @internal dev/test seam for cli-dev unit tests
     */
    public static function configure(?string $rootDir = null, array $env = [], bool $replaceEnv = false): void
    {
        self::$testRootDir = $rootDir;
        self::$testEnv = $env;
        self::$testReplaceEnv = $replaceEnv;
    }

    /** @internal dev/test seam for cli-dev unit tests */
    public static function reset(): void
    {
        self::$testRootDir = null;
        self::$testEnv = [];
        self::$testReplaceEnv = false;
        self::$rootDir = null;
    }

    public static function rootDir(): string
    {
        if (self::$testRootDir !== null) {
            return self::$testRootDir;
        }

        if (self::$rootDir !== null) {
            return self::$rootDir;
        }

        $currentDir = __DIR__;

        while ($currentDir !== dirname($currentDir)) {
            if (file_exists($currentDir . DIRECTORY_SEPARATOR . 'composer.lock')) {
                self::$rootDir = $currentDir;

                return self::$rootDir;
            }
            $currentDir = dirname($currentDir);
        }
        throw new \Exception('composer.lock not found');
    }

    public static function appDir(): string
    {
        $appDir = self::rootDir() . DIRECTORY_SEPARATOR . 'app';
        if (!file_exists($appDir)) {
            throw new \Exception('app directory not found in root directory');
        }

        return $appDir;
    }

    /**
     * Path to the library's startup/common/system_pages directory (templates, assets).
     * Resolves the gemvc/library install path so this works when helper lives in vendor/gemvc/helper.
     *
     * @return string Absolute path to system_pages directory
     */
    public static function getLibrarySystemPagesPath(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('gemvc/library')) {
            $libraryPath = InstalledVersions::getInstallPath('gemvc/library');
            if (is_string($libraryPath) && $libraryPath !== '') {
                $systemPages = $libraryPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'startup'
                    . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'system_pages';
                if (is_dir($systemPages)) {
                    return $systemPages;
                }
            }
        }

        $fallback = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'startup'
            . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'system_pages';
        if (is_dir($fallback)) {
            return $fallback;
        }

        throw new \RuntimeException('gemvc/library package not found; cannot resolve system_pages path');
    }

    public static function loadEnv(): void
    {
        if (self::$testRootDir !== null && (self::$testReplaceEnv || self::$testEnv !== [])) {
            $merged = self::$testReplaceEnv
                ? self::$testEnv
                : array_merge(['DB_NAME' => 'test_db', 'DB_HOST' => 'localhost'], self::$testEnv);
            foreach ($merged as $key => $value) {
                $_ENV[$key] = $value;
                putenv($key . '=' . $value);
            }

            return;
        }

        $dotenv = new Dotenv();

        $rootEnvFile = self::rootDir() . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($rootEnvFile)) {
            $dotenv->overload($rootEnvFile);

            return;
        }

        $appEnvFile = self::appDir() . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($appEnvFile)) {
            $dotenv->overload($appEnvFile);

            return;
        }

        throw new \Exception('No .env file found in root or app directory');
    }

    /**
     * @return string e.g. 'http://localhost:9550' or 'http://localhost'
     */
    public static function getBaseUrl(): string
    {
        return self::buildBaseUrlParts()['url'];
    }

    public static function getApiBaseUrl(): string
    {
        if (!isset($_ENV['APP_ENV_PUBLIC_SERVER_PORT'])) {
            self::loadEnv();
        }

        $baseUrl = self::buildBaseUrlParts()['url'];

        $apiSubUrl = isset($_ENV['APP_ENV_API_DEFAULT_SUB_URL']) && is_string($_ENV['APP_ENV_API_DEFAULT_SUB_URL'])
            ? trim(trim($_ENV['APP_ENV_API_DEFAULT_SUB_URL'], '\'"'), '/')
            : '';
        $apiSubUrl = $apiSubUrl !== '' ? '/' . $apiSubUrl : '';

        return rtrim($baseUrl . $apiSubUrl, '/');
    }

    /**
     * @return array{url: string, protocol: string, host: string, port: int, portDisplay: string}
     */
    private static function buildBaseUrlParts(): array
    {
        if (!isset($_ENV['APP_ENV_PUBLIC_SERVER_PORT'])) {
            self::loadEnv();
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST'])
            ? $_SERVER['HTTP_HOST']
            : 'localhost';

        $detectedPort = null;
        if (preg_match('/:(\d+)$/', $host, $matches)) {
            $detectedPort = (int) $matches[1];
            $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        }

        if ($detectedPort !== null) {
            $port = $detectedPort;
        } else {
            $portEnv = $_ENV['APP_ENV_PUBLIC_SERVER_PORT'] ?? '80';
            $port = is_numeric($portEnv) ? (int) $portEnv : 80;
        }

        $portDisplay = ($port !== 80 && $port !== 443) ? ':' . $port : '';
        $url = $protocol . '://' . $host . $portDisplay;

        return ['url' => $url, 'protocol' => $protocol, 'host' => $host, 'port' => $port, 'portDisplay' => $portDisplay];
    }

    public static function getVersion(): string
    {
        try {
            $rootDir = self::rootDir();
            $composerLockPath = $rootDir . DIRECTORY_SEPARATOR . 'composer.lock';

            if (!file_exists($composerLockPath)) {
                return 'unknown';
            }

            $lockContent = file_get_contents($composerLockPath);
            if ($lockContent === false) {
                return 'unknown';
            }

            $lockData = json_decode($lockContent, true);
            if (!is_array($lockData) || !isset($lockData['packages']) || !is_array($lockData['packages'])) {
                return 'unknown';
            }

            foreach ($lockData['packages'] as $package) {
                if (is_array($package) && isset($package['name']) && $package['name'] === 'gemvc/library') {
                    $version = $package['version'] ?? $package['pretty_version'] ?? null;
                    if (is_string($version)) {
                        return $version;
                    }

                    return 'unknown';
                }
            }

            if (isset($lockData['packages-dev']) && is_array($lockData['packages-dev'])) {
                foreach ($lockData['packages-dev'] as $package) {
                    if (is_array($package) && isset($package['name']) && $package['name'] === 'gemvc/library') {
                        $version = $package['version'] ?? $package['pretty_version'] ?? null;
                        if (is_string($version)) {
                            return $version;
                        }

                        return 'unknown';
                    }
                }
            }

            return 'unknown';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    public static function isDevEnvironment(): bool
    {
        return ($_ENV['APP_ENV'] ?? '') === 'dev';
    }

    public static function getAppEnv(): string
    {
        $env = $_ENV['APP_ENV'] ?? 'production';

        return is_string($env) ? $env : 'production';
    }

    public static function disableOpcacheIfDev(): void
    {
        if (!self::isDevEnvironment()) {
            return;
        }
        if (function_exists('opcache_reset')) {
            @\opcache_reset();
        }
        if (function_exists('opcache_disable')) {
            @\opcache_disable();
        }
    }

    /**
     * @param array<string, string> $variables
     */
    public static function updateEnvVariables(array $variables): bool
    {
        $envPath = self::rootDir() . DIRECTORY_SEPARATOR . '.env';

        if (!file_exists($envPath)) {
            return false;
        }

        $envContent = file_get_contents($envPath);
        if ($envContent === false) {
            return false;
        }

        foreach ($variables as $key => $value) {
            $pattern = '/^' . preg_quote($key, '/') . '\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\n\r]*)/m';
            $escapedValue = self::escapeEnvValue($value);
            $replacement = $key . '=' . $escapedValue;

            if (preg_match($pattern, $envContent)) {
                $replaced = preg_replace($pattern, $replacement, $envContent);
                $envContent = is_string($replaced) ? $replaced : $envContent;
            } else {
                if (preg_match('/^APP_ENV\s*=.*$/m', $envContent, $matches, PREG_OFFSET_CAPTURE)) {
                    $pos = $matches[0][1] + strlen($matches[0][0]);
                    $envContent = substr_replace($envContent, "\n" . $replacement, $pos, 0);
                } else {
                    $envContent .= "\n" . $replacement . "\n";
                }
            }
        }

        return file_put_contents($envPath, $envContent) !== false;
    }

    private static function escapeEnvValue(string $value): string
    {
        if (preg_match('/[\s"\'=#]/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return $value;
    }
}
