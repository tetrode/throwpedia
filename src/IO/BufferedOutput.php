<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\IO;

class BufferedOutput implements OutputInterface
{
    private string $buffer = '';

    public function write(string $message): void
    {
        $this->buffer .= $message;
    }

    public function writeln(string $message): void
    {
        $this->buffer .= $message . PHP_EOL;
    }

    public function error(string $message): void
    {
        $this->buffer .= "Error: $message" . PHP_EOL;
    }

    public function warning(string $message): void
    {
        $this->buffer .= "Warning: $message" . PHP_EOL;
    }

    public function fetch(): string
    {
        return $this->buffer;
    }
}
