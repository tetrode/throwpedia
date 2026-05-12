<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia;

use Nette\Neon\Neon;
use Tetrode\Throwpedia\DTO\AttributeField;
use Tetrode\Throwpedia\DTO\ExceptionCatalog;

class Renderers
{
    /**
     * @throws \JsonException
     */
    public static function toJson(ExceptionCatalog $catalog): string
    {
        $data = [
            'project' => [
                'name'             => $catalog->project->name,
                'php'              => $catalog->project->php,
                'total_exceptions' => $catalog->project->total_exceptions,
            ],
            'meta' => [
                'version'   => $catalog->meta->version,
                'scan_time' => $catalog->meta->scan_time,
            ],
            'entries' => $catalog->entries,
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     */
    public static function toYaml(ExceptionCatalog $catalog): string
    {
        $data = [
            'project' => [
                'name'             => $catalog->project->name,
                'php'              => $catalog->project->php,
                'total_exceptions' => $catalog->project->total_exceptions,
            ],
            'meta' => [
                'version'   => $catalog->meta->version,
                'scan_time' => $catalog->meta->scan_time,
            ],
            'entries' => $catalog->entries,
        ];

        return Neon::encode($data, blockMode: true);
    }

    /**
     * @param array<string, AttributeField[]> $attributeFields
     */
    public static function toMarkdown(ExceptionCatalog $catalog, array $attributeFields): string
    {
        $md = "# Business Exceptions Catalog\n\n";

        $md .= \sprintf("**Project:** %s (%s)\n", $catalog->project->name, $catalog->project->php);
        $md .= \sprintf("**Exceptions found:** %d\n\n", $catalog->project->total_exceptions);

        $md .= \sprintf("**Throwpedia Version:** %s\n", $catalog->meta->version);
        $md .= \sprintf("**Scan time:** %s\n\n", $catalog->meta->scan_time);

        $grouped = [];
        foreach ($catalog->entries as $entry) {
            $grouped[$entry->attributeName][] = $entry;
        }

        foreach ($grouped as $attrName => $entries) {
            $fields = $attributeFields[$attrName] ?? [];
            if (empty($fields)) {
                continue;
            }

            $md .= "## $attrName\n\n";

            $headers = [];
            foreach ($fields as $field) {
                $headers[] = $field->label;
            }
            $headers[] = 'Exception';
            $headers[] = 'Thrown From';

            $md .= '| ' . implode(' | ', $headers) . " |\n";
            $md .= '| ' . implode(' | ', array_map(fn ($h) => str_repeat('-', max(3, \strlen($h))), $headers)) . " |\n";

            foreach ($entries as $data) {
                $row = [];
                foreach ($fields as $field) {
                    $val = $data->values[$field->name] ?? '';
                    $row[] = str_replace('|', '\\|', (string)$val);
                }
                $row[] = "`{$data->exception}`";
                $row[] = implode(' <br>', $data->thrown_from);

                $md .= '| ' . implode(' | ', $row) . " |\n";
            }
            $md .= "\n";
        }

        return trim($md) . "\n";
    }

    /**
     * @param array<string, AttributeField[]> $attributeFields
     */
    public static function toText(ExceptionCatalog $catalog, array $attributeFields): string
    {
        $text = "Business Exceptions Catalog\n";
        $text .= str_repeat('=', 27) . "\n\n";

        $text .= \sprintf("Project: %s (%s)\n", $catalog->project->name, $catalog->project->php);
        $text .= \sprintf("Exceptions found: %d\n\n", $catalog->project->total_exceptions);

        $text .= \sprintf("Throwpedia Version: %s\n", $catalog->meta->version);
        $text .= \sprintf("Scan time: %s\n\n", $catalog->meta->scan_time);

        $grouped = [];
        foreach ($catalog->entries as $entry) {
            $grouped[$entry->attributeName][] = $entry;
        }

        foreach ($grouped as $attrName => $entries) {
            $text .= "[$attrName]\n";
            $text .= str_repeat('-', \strlen($attrName) + 2) . "\n";

            $fields = $attributeFields[$attrName] ?? [];
            $identifierField = 'identifier';
            foreach ($fields as $field) {
                if ($field->isIdentifier) {
                    $identifierField = $field->name;
                    break;
                }
            }

            foreach ($entries as $data) {
                $identifier = $data->values[$identifierField] ?? 'UNKNOWN';
                $text .= "  ($identifier)\n";

                foreach ($fields as $field) {
                    if ($field->isIdentifier) {
                        continue;
                    }
                    $val = $data->values[$field->name] ?? '';
                    $text .= "    {$field->label}: {$val}\n";
                }

                $text .= "    Exception: {$data->exception}\n";
                $text .= "    Thrown from:\n";
                foreach ($data->thrown_from as $loc) {
                    $text .= "      - $loc\n";
                }
                $text .= "\n";
            }
        }

        return $text;
    }
    /**
     * @param array<string, AttributeField[]> $attributeFields
     */
    public static function toCsv(ExceptionCatalog $catalog, array $attributeFields): string
    {
        return self::toDelimited($catalog, $attributeFields, ',');
    }

    /**
     * @param array<string, AttributeField[]> $attributeFields
     */
    public static function toTsv(ExceptionCatalog $catalog, array $attributeFields): string
    {
        return self::toDelimited($catalog, $attributeFields, "\t");
    }

    /**
     * @param array<string, AttributeField[]> $attributeFields
     */
    public static function toPsv(ExceptionCatalog $catalog, array $attributeFields): string
    {
        return self::toDelimited($catalog, $attributeFields, '|');
    }

    public static function toXml(ExceptionCatalog $catalog): string
    {
        if (!\extension_loaded('simplexml') || !\extension_loaded('dom')) {
            throw new \RuntimeException(
                'The "simplexml" and "dom" extensions are required to use the XML output format. ' .
                'Please install them or use a different output format.'
            );
        }

        // Existing XML generation logic follows...
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><catalog/>');

        $project = $xml->addChild('project');
        $project->addChild('name', $catalog->project->name);
        $project->addChild('php', $catalog->project->php);
        $project->addChild('total_exceptions', (string)$catalog->project->total_exceptions);

        $meta = $xml->addChild('meta');
        $meta->addChild('version', $catalog->meta->version);
        $meta->addChild('scan_time', $catalog->meta->scan_time);

        $entries = $xml->addChild('entries');
        foreach ($catalog->entries as $entry) {
            $item = $entries->addChild('entry');
            $item->addChild('attributeName', $entry->attributeName);
            $item->addChild('exception', $entry->exception);

            $values = $item->addChild('values');
            foreach ($entry->values as $key => $value) {
                $values->addChild($key, htmlspecialchars($value));
            }

            $thrownFrom = $item->addChild('thrown_from');
            foreach ($entry->thrown_from as $location) {
                $thrownFrom->addChild('location', $location);
            }
        }

        $domElement = dom_import_simplexml($xml);
        $dom = $domElement->ownerDocument;
        if (null === $dom) {
            throw new \RuntimeException('Failed to get ownerDocument from DOMElement');
        }
        $dom->formatOutput = true;

        $xmlString = $dom->saveXML();
        if (false === $xmlString) {
            throw new \RuntimeException('Failed to save XML');
        }

        return $xmlString;
    }

    public static function toToml(ExceptionCatalog $catalog): string
    {
        $toml = "[project]\n";
        $toml .= 'name = ' . self::tomlValue($catalog->project->name) . "\n";
        $toml .= 'php = ' . self::tomlValue($catalog->project->php) . "\n";
        $toml .= 'total_exceptions = ' . $catalog->project->total_exceptions . "\n\n";

        $toml .= "[meta]\n";
        $toml .= 'version = ' . self::tomlValue($catalog->meta->version) . "\n";
        $toml .= 'scan_time = ' . self::tomlValue($catalog->meta->scan_time) . "\n\n";

        foreach ($catalog->entries as $entry) {
            $toml .= "[[entries]]\n";
            $toml .= 'attributeName = ' . self::tomlValue($entry->attributeName) . "\n";
            $toml .= 'exception = ' . self::tomlValue($entry->exception) . "\n";
            $toml .= 'thrown_from = [' . implode(', ', array_map(self::tomlValue(...), $entry->thrown_from)) . "]\n";

            $toml .= "[entries.values]\n";
            foreach ($entry->values as $key => $val) {
                $toml .= "$key = " . self::tomlValue($val) . "\n";
            }
            $toml .= "\n";
        }

        return $toml;
    }

    private static function tomlValue(mixed $value): string
    {
        if (\is_int($value) || \is_float($value)) {
            return (string)$value;
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return '"' . addcslashes((string)$value, '"\\') . '"';
    }

    /**
     * @param array<string, AttributeField[]> $attributeFields
     */
    private static function toDelimited(ExceptionCatalog $catalog, array $attributeFields, string $separator): string
    {
        $allFieldNames = [];
        foreach ($attributeFields as $fields) {
            foreach ($fields as $field) {
                $allFieldNames[$field->name] = $field->label;
            }
        }
        $uniqueFieldKeys = array_keys($allFieldNames);

        $fp = fopen('php://memory', 'r+');
        if (!$fp) {
            return '';
        }

        $header = array_merge(['Attribute'], array_values($allFieldNames), ['Exception', 'Thrown From']);
        /** @noinspection PhpDeprecatedPassingNonEmptyEscapeToCsvFunctionInspection */
        fputcsv($fp, $header, $separator, '"', "\\", PHP_EOL);

        foreach ($catalog->entries as $entry) {
            $row = [$entry->attributeName];
            foreach ($uniqueFieldKeys as $fieldName) {
                $row[] = $entry->values[$fieldName] ?? '';
            }
            $row[] = $entry->exception;
            $row[] = implode(', ', $entry->thrown_from);
            /** @noinspection PhpDeprecatedPassingNonEmptyEscapeToCsvFunctionInspection */
            fputcsv($fp, $row, $separator, '"', "\\", PHP_EOL);
        }

        rewind($fp);
        $content = stream_get_contents($fp);
        fclose($fp);

        return (string)$content;
    }
}
