<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia;

use Nette\Neon\Neon;

class Renderers
{
    /**
     * @param array<string, mixed> $model
     */
    public static function toJson(array $model): string
    {
        return json_encode($model, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $model
     */
    public static function toYaml(array $model): string
    {
        return Neon::encode($model, blockMode: true);
    }

    public static function toMarkdown(array $model): string
    {
        $md = "# Business Exceptions Catalog\n\n";
        $md .= "| Code | Business Description | Technical Description | Exception | Thrown From |\n";
        $md .= "|------|----------------------|-----------------------|-----------|-------------|\n";

        foreach ($model as $key => $data) {
            $code = $data['code'] ?? $key;
            $business = str_replace('|', '\\|', (string)$data['business']);
            $technical = str_replace('|', '\\|', (string)$data['technical']);
            $thrownFrom = implode('<br>', $data['thrown_from']);
            $md .= "| $code | $business | $technical | `{$data['exception']}` | $thrownFrom |\n";
        }

        return $md;
    }

    public static function toText(array $model): string
    {
        $text = "Business Exceptions Catalog\n";
        $text .= str_repeat('=', 27) . "\n\n";

        foreach ($model as $key => $data) {
            $code = $data['code'] ?? $key;
            $text .= "[$code]\n";
            $text .= "  Business:  {$data['business']}\n";
            $text .= "  Technical: {$data['technical']}\n";
            $text .= "  Exception: {$data['exception']}\n";
            $text .= "  Thrown from:\n";
            foreach ($data['thrown_from'] as $loc) {
                $text .= "    - $loc\n";
            }
            $text .= "\n";
        }

        return $text;
    }
}
