<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\IO;

class NullOutput implements OutputInterface
{
    public function write(string $message): void
    {
    }

    public function writeln(string $message): void
    {
    }

    public function error(string $message): void
    {
    }

    public function warning(string $message): void
    {
    }
}
