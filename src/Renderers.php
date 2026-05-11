<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia;

class Renderers
{
    public static function toJson(array $model): string
    {
        return json_encode($model, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function toYaml(array $model): string
    {
        // Simple YAML generator to avoid adding dependencies
        $yaml = '';
        foreach ($model as $code => $data) {
            $yaml .= "$code:\n";
            $yaml .= "  business: \"{$data['business']}\"\n";
            $yaml .= "  technical: \"{$data['technical']}\"\n";
            $yaml .= "  exception: \"{$data['exception']}\"\n";
            $yaml .= "  thrown_from:\n";
            foreach ($data['thrown_from'] as $loc) {
                $yaml .= "    - \"$loc\"\n";
            }
        }
        return $yaml;
    }

    public static function toMarkdown(array $model): string
    {
        $md = "# Business Exceptions Catalog\n\n";
        $md .= "| Code | Business Description | Technical Description | Exception | Thrown From |\n";
        $md .= "|------|----------------------|-----------------------|-----------|-------------|\n";

        foreach ($model as $code => $data) {
            $thrownFrom = implode('<br>', $data['thrown_from']);
            $md .= "| $code | {$data['business']} | {$data['technical']} | `{$data['exception']}` | $thrownFrom |\n";
        }

        return $md;
    }

    public static function toText(array $model): string
    {
        $text = "Business Exceptions Catalog\n";
        $text .= str_repeat('=', 27) . "\n\n";

        foreach ($model as $code => $data) {
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
