<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Support;

final class CliRunResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $output,
    ) {
    }
}
