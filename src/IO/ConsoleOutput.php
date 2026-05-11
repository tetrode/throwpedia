<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\IO;

class ConsoleOutput implements OutputInterface
{
    public function write(string $message): void
    {
        echo $message;
    }

    public function writeln(string $message): void
    {
        echo $message . PHP_EOL;
    }

    public function error(string $message): void
    {
        fwrite(STDERR, "Error: $message" . PHP_EOL);
    }

    public function warning(string $message): void
    {
        fwrite(STDERR, "Warning: $message" . PHP_EOL);
    }
}
