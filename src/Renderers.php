<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia;

use Nette\Neon\Neon;
use Tetrode\Throwpedia\DTO\AttributeField;
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
     * @param AttributeField[] $fields
     */
    public static function toMarkdown(ExceptionCatalog $catalog, array $fields): string
    {
        $md = "# Business Exceptions Catalog\n\n";

        $headers = [];
        foreach ($fields as $field) {
            $headers[] = $field->label;
        }
        $headers[] = 'Exception';
        $headers[] = 'Thrown From';

        $md .= "| " . implode(' | ', $headers) . " |\n";
        $md .= "| " . implode(' | ', array_map(fn($h) => str_repeat('-', max(3, strlen($h))), $headers)) . " |\n";

        foreach ($catalog->entries as $key => $data) {
            $row = [];
            foreach ($fields as $field) {
                $val = $data->values[$field->name] ?? '';
                $row[] = str_replace('|', '\\|', (string)$val);
            }
            $row[] = "`{$data->exception}`";
            $row[] = implode('<br>', $data->thrown_from);

            $md .= "| " . implode(' | ', $row) . " |\n";
        }

        return $md;
    }

    /**
     * @param ExceptionCatalog $catalog
     * @param AttributeField[] $fields
     */
    public static function toText(ExceptionCatalog $catalog, array $fields): string
    {
        $text = "Business Exceptions Catalog\n";
        $text .= str_repeat('=', 27) . "\n\n";

        $codeField = 'code';
        foreach ($fields as $field) {
            if ($field->isCode) {
                $codeField = $field->name;
                break;
            }
        }

        foreach ($catalog->entries as $key => $data) {
            $code = $data->values[$codeField] ?? $key;
            $text .= "[$code]\n";

            foreach ($fields as $field) {
                if ($field->isCode) {
                    continue;
                }
                $val = $data->values[$field->name] ?? '';
                $text .= "  {$field->label}: {$val}\n";
            }

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
