<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia;

use Nette\Neon\Neon;
use Tetrode\Throwpedia\DTO\ExceptionCatalog;
use Tetrode\Throwpedia\DTO\ExceptionModelEntry;

class Renderers
{
    /**
     * @param ExceptionCatalog $catalog
     */
    public static function toJson(ExceptionCatalog $catalog): string
    {
        return json_encode($catalog->entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param ExceptionCatalog $catalog
     */
    public static function toYaml(ExceptionCatalog $catalog): string
    {
        return Neon::encode($catalog->entries, blockMode: true);
    }

    /**
     * @param ExceptionCatalog $catalog
     */
    public static function toMarkdown(ExceptionCatalog $catalog): string
    {
        $md = "# Business Exceptions Catalog\n\n";
        $md .= "| Code | Business Description | Technical Description | Exception | Thrown From |\n";
        $md .= "|------|----------------------|-----------------------|-----------|-------------|\n";

        foreach ($catalog->entries as $key => $data) {
            $code = $data->code ?? $key;
            $business = str_replace('|', '\\|', (string)$data->business);
            $technical = str_replace('|', '\\|', (string)$data->technical);
            $thrownFrom = implode('<br>', $data->thrown_from);
            $md .= "| $code | $business | $technical | `{$data->exception}` | $thrownFrom |\n";
        }

        return $md;
    }

    /**
     * @param ExceptionCatalog $catalog
     */
    public static function toText(ExceptionCatalog $catalog): string
    {
        $text = "Business Exceptions Catalog\n";
        $text .= str_repeat('=', 27) . "\n\n";

        foreach ($catalog->entries as $key => $data) {
            $code = $data->code ?? $key;
            $text .= "[$code]\n";
            $text .= "  Business:  {$data->business}\n";
            $text .= "  Technical: {$data->technical}\n";
            $text .= "  Exception: {$data->exception}\n";
            $text .= "  Thrown from:\n";
            foreach ($data->thrown_from as $loc) {
                $text .= "    - $loc\n";
            }
            $text .= "\n";
        }

        return $text;
    }
}
