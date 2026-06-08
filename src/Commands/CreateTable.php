<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Commands\DevGenerator;

class CreateTable extends DevGenerator
{
    protected string $serviceName;
    protected string $basePath;

    public function execute(): bool
    {
        if (empty($this->args[0]) || !is_string($this->args[0])) {
            $this->error("Table name is required. Usage: gemvc create:table TableName");
            return false;
        }
        $this->serviceName = $this->formatServiceName($this->args[0]);
        $this->basePath = defined('PROJECT_ROOT') ? PROJECT_ROOT : $this->determineProjectRoot();

        try {
            // Create necessary directories
            $this->createDirectories($this->getRequiredDirectories());

            // Create table file
            $this->createTable();

            $this->success("Table {$this->serviceName} created successfully!");
            return true;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            
        }
        return false;
    }


    protected function createTable(): bool
    {
        $template = $this->getTemplate('table');
        $content = $this->replaceTemplateVariables($template, [
            'serviceName' => $this->serviceName,
            'tableName' => strtolower($this->serviceName) . 's'
        ]);

        $path = $this->basePath . "/app/table/{$this->serviceName}Table.php";
        $this->writeFile($path, $content, "Table");
        return true;
    }
} 