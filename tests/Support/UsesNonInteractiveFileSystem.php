<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Support;

use Gemvc\CLI\FileSystemManager;

/**
 * Codegen commands use FileSystemManager(interactive) by default, which blocks on
 * php://stdin when generated files already exist. Tests must overwrite silently.
 */
trait UsesNonInteractiveFileSystem
{
    /**
     * @param array<int, mixed> $args
     * @param array<string, mixed> $options
     */
    public function __construct(array $args = [], array $options = [])
    {
        parent::__construct($args, $options);
        $this->fileSystem = new FileSystemManager(true);
    }
}
