<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Tetrode\Throwpedia\IO\OutputInterface;

class Analyzer
{
    private Parser $parser;
    private Extractor $extractor;
    private bool $allowDirectNew = false;
    private int $verbosity = 0;

    public function __construct(
        private readonly OutputInterface $output,
        array $config = []
    ) {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->extractor = new Extractor($this->output);

        if (isset($config['attributes'])) {
            $this->extractor->setTargetAttributes($config['attributes']);
        }

        if (isset($config['allowDirectNew'])) {
            $this->allowDirectNew = (bool)$config['allowDirectNew'];
        }
    }

    public function setVerbosity(int $level): void
    {
        $this->verbosity = $level;
        $this->extractor->setVerbosity($level);
    }

    /**
     * @param array<string> $files
     * @return array<string, mixed>
     */
    public function analyze(array $files): array
    {
        $nameResolver = new NameResolver();
        foreach ($files as $file) {
            try {
                $code = file_get_contents($file);
                if (false === $code) {
                    continue;
                }

                $stmts = $this->parser->parse($code);
                if (null === $stmts) {
                    continue;
                }

                $this->extractor->setCurrentFile($file);

                $traverser = new NodeTraverser();
                $traverser->addVisitor($nameResolver);
                $stmts = $traverser->traverse($stmts);

                $traverser = new NodeTraverser();
                $traverser->addVisitor($this->extractor);
                $traverser->traverse($stmts);
            } catch (Error $e) {
                $this->output->error("Parse Error in {$file}: {$e->getMessage()}");
            }
        }

        $results = $this->extractor->getResults();
        $directNew = $this->extractor->getDirectNewThrows();

        return $this->buildModel($results, $directNew);
    }

    private function buildModel(array $rawResults, array $directNew): array
    {
        $model = [];
        foreach ($rawResults as $methodData) {
            $attributes = $methodData['attributes'];
            $throws = $methodData['throws'];
            $location = $methodData['class'] . '::' . $methodData['method'];

            // Simplified matching: if there's one attribute and multiple throws, or vice-versa,
            // we associate the attribute with all throws in that method for now.
            // A more complex tool might try to find which throw is closest to the attribute if they were on statements.
            foreach ($attributes as $attr) {
                $code = $attr['code'];
                if (!isset($model[$code])) {
                    $model[$code] = [
                        'business'    => $attr['business'],
                        'technical'   => $attr['technical'],
                        'exception'   => \count($throws) > 0 ? $throws[0] : 'unknown',
                        'thrown_from' => [],
                    ];
                }

                if (!\in_array($location, $model[$code]['thrown_from'], true)) {
                    $model[$code]['thrown_from'][] = $location;
                }

                // If there are multiple different exceptions thrown in the same method with the same code,
                // we just take the first one for the 'exception' field, but it might be better to list them if they vary.
                // But the requirement says 'exception' field is a single string in the example.
            }
        }

        if ($this->allowDirectNew) {
            foreach ($directNew as $throw) {
                $code = 'DIRECT_NEW_' . strtoupper(basename(str_replace('\\', '/', $throw['exception'])));
                // Avoid duplicates if multiple direct news of same exception
                $counter = 1;
                $originalCode = $code;
                while (isset($model[$code])) {
                    $code = $originalCode . '_' . $counter++;
                }

                $model[$code] = [
                    'business'    => 'Direct instantiation of ' . $throw['exception'],
                    'technical'   => 'Thrown from ' . $throw['class'] . '::' . $throw['method'],
                    'exception'   => $throw['exception'],
                    'thrown_from' => [$throw['class'] . '::' . $throw['method']],
                ];
            }
        }

        ksort($model);
        return $model;
    }

    public function getValidationErrors(): array
    {
        $errors = [];
        foreach ($this->extractor->getDirectNewThrows() as $throw) {
            $errors[] = "Direct 'new' usage at {$throw['file']}:{$throw['line']} in {$throw['class']}::{$throw['method']} for {$throw['exception']}";
        }

        foreach ($this->extractor->getResults() as $methodData) {
            if (!empty($methodData['throws']) && empty($methodData['attributes'])) {
                $errors[] = "Missing #[ExceptionReason] at {$methodData['file']}:{$methodData['line']} for method {$methodData['class']}::{$methodData['method']}";
            }
        }

        return $errors;
    }
}
