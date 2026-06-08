<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\CliColor;
use Gemvc\CLI\Commands\DevGenerator;
use Gemvc\CLI\Commands\CreateService;

class CreateCrud extends DevGenerator
{
    /**
     * @param array<int, mixed> $args
     * @param array<string, mixed> $options
     */
    protected function newCreateService(array $args, array $options): CreateService
    {
        return new class($args, $options) extends CreateService {
            protected function success(string $message, bool $shouldExit = true): void
            {
                parent::success($message, false);
            }

            protected function error(string $message): void
            {
                $this->write($message . "\n", CliColor::Red);
            }
        };
    }

    public function execute(): bool
    {
        if (empty($this->args[0])) {
            $this->error("Service name is required. Usage: gemvc create:crud ServiceName");
            return false;
        }

        try {
            return $this->runCrudGeneration();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return false;
        }
    }

    protected function runCrudGeneration(): bool
    {
        $service = $this->newCreateService($this->args, $this->options);
        $service->args = [$this->args[0], '-cmt'];

        if (!$service->execute()) {
            return false;
        }

        if (!is_string($this->args[0])) {
            return false;
        }

        $serviceName = $this->formatServiceName($this->args[0]);
        $this->success("CRUD for {$serviceName} created successfully!");

        return true;
    }
}
