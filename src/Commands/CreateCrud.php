<?php

namespace Gemvc\CLI\Commands;

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
        return new CreateService($args, $options);
    }

    public function execute(): bool
    {
        if (empty($this->args[0])) {
            $this->error("Service name is required. Usage: gemvc create:crud ServiceName");
            return false;
        }

        try {
            // Create service with all components enabled
            $service = $this->newCreateService($this->args, $this->options);
            $service->args = [$this->args[0], '-cmt']; // Use original input + all flags
            $service->execute();

            // @phpstan-ignore-next-line
            $serviceName = $this->formatServiceName($this->args[0]);
            $this->success("CRUD for {$serviceName} created successfully!");
            return true;
            
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return false;
        }
    }
} 