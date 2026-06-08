<?php

declare(strict_types=1);

namespace Gemvc\CliDev\Tests\Support;

use Gemvc\CLI\CliColor;

/**
 * Prevents gemvc/cli-base Command::{error,success} from calling exit() during tests.
 */
trait SuppressesCliExit
{
    protected function error(string $message): void
    {
        $this->write($message . "\n", CliColor::Red);
    }

    protected function success(string $message, bool $shouldExit = true): void
    {
        $this->write('SUCCESS: ' . $message . "\n", CliColor::Green);
    }
}
