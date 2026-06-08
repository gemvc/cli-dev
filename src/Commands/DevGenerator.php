<?php

namespace Gemvc\CLI\Commands;

/**
 * Codegen base for gemvc/cli-dev — resolves templates from this package, not gemvc/library.
 */
abstract class DevGenerator extends AbstractBaseCrudGenerator
{
    protected function getCliDevInstallPath(): ?string
    {
        if (!class_exists(\Composer\InstalledVersions::class, false)) {
            return null;
        }
        $path = \Composer\InstalledVersions::getInstallPath('gemvc/cli-dev');
        return is_string($path) && $path !== '' ? $path : null;
    }

    protected function getTemplate(string $templateName): string
    {
        $projectRoot = $this->basePath ?? $this->determineProjectRoot();
        $templatePath = $projectRoot . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . "{$templateName}.template";

        if (file_exists($templatePath)) {
            $content = file_get_contents($templatePath);
            if ($content === false) {
                throw new \RuntimeException("Failed to read template: {$templatePath}");
            }
            return $content;
        }

        $cliDevRoot = $this->getCliDevInstallPath();
        if ($cliDevRoot !== null) {
            $vendorTemplatePath = $cliDevRoot . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . "{$templateName}.template";
            if (file_exists($vendorTemplatePath)) {
                $this->warning("Using vendor template for {$templateName} - consider copying templates to project root");
                $content = file_get_contents($vendorTemplatePath);
                if ($content === false) {
                    throw new \RuntimeException("Failed to read vendor template: {$vendorTemplatePath}");
                }
                return $content;
            }
        }

        $packageRoot = dirname(__DIR__, 2);
        $stagingPath = $packageRoot . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . "{$templateName}.template";
        if (file_exists($stagingPath)) {
            $content = file_get_contents($stagingPath);
            if ($content === false) {
                throw new \RuntimeException("Failed to read template: {$stagingPath}");
            }
            return $content;
        }

        throw new \RuntimeException("Template not found: {$templateName} (checked project templates and gemvc/cli-dev)");
    }
}
