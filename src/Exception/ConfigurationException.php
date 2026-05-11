<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\Exception;

class ConfigurationException extends ThrowpediaException
{
    public static function FileNotFound(string $configFile): self
    {
        return new self("Configuration file '$configFile' not found.");
    }

    public static function FileNotParsable(string $configFile): self
    {
        return new self("Configuration file '$configFile' is empty or not parsable.");
    }

    public static function FilePathNotGiven(): self
    {
        return new self('Configuration file path not given, -f requires a file path');
    }
}
