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
    /** @var string[] */
    private array $targetAttributes = ['ExceptionReason'];

    public function __construct(
        private readonly OutputInterface $output,
        array $config = []
    ) {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->extractor = new Extractor($this->output);

        if (isset($config['attributes'])) {
            $this->targetAttributes = (array)$config['attributes'];
            $this->extractor->setTargetAttributes($this->targetAttributes);
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
        $resolverTraverser = new NodeTraverser();
        $resolverTraverser->addVisitor($nameResolver);

        $extractorTraverser = new NodeTraverser();
        $extractorTraverser->addVisitor($this->extractor);

        foreach ($files as $file) {
            try {
                $code = @file_get_contents($file);
                if (false === $code) {
                    continue;
                }

                $stmts = $this->parser->parse($code);
                if (null === $stmts) {
                    continue;
                }

                $this->extractor->setCurrentFile($file);

                $stmts = $resolverTraverser->traverse($stmts);
                $extractorTraverser->traverse($stmts);
            } catch (Error $e) {
                $this->output->error("Parse Error in {$file}: {$e->getMessage()}");
            }
        }

        return $this->buildModel(
            $this->extractor->getResults(),
            $this->extractor->getDirectNewThrows()
        );
    }

    private function buildModel(array $rawResults, array $directNew): array
    {
        $model = [];
        foreach ($rawResults as $methodData) {
            $location = sprintf('%s::%s', $methodData['class'], $methodData['method']);
            $firstException = $methodData['throws'][0] ?? 'unknown';

            foreach ($methodData['attributes'] as $attr) {
                $code = $attr['code'];
                if (!isset($model[$code])) {
                    $model[$code] = [
                        'business'    => $attr['business'],
                        'technical'   => $attr['technical'],
                        'exception'   => $firstException,
                        'thrown_from' => [],
                    ];
                }

                if (!\in_array($location, $model[$code]['thrown_from'], true)) {
                    $model[$code]['thrown_from'][] = $location;
                }
            }
        }

        if ($this->allowDirectNew) {
            $this->appendDirectNewThrows($model, $directNew);
        }

        ksort($model);
        return $model;
    }

    /**
     * @param array<string, mixed> $model
     * @param array<array{file: string, line: int, class: string, method: string, exception: string}> $directNew
     */
    private function appendDirectNewThrows(array &$model, array $directNew): void
    {
        foreach ($directNew as $throw) {
            $exceptionBaseName = strtoupper(basename(str_replace('\\', '/', $throw['exception'])));
            $code = 'DIRECT_NEW_' . $exceptionBaseName;

            // Avoid duplicates if multiple direct news of same exception
            $counter = 1;
            $originalCode = $code;
            while (isset($model[$code])) {
                $code = $originalCode . '_' . $counter++;
            }

            $location = sprintf('%s::%s', $throw['class'], $throw['method']);
            $model[$code] = [
                'business'    => 'Direct instantiation of ' . $throw['exception'],
                'technical'   => 'Thrown from ' . $location,
                'exception'   => $throw['exception'],
                'thrown_from' => [$location],
            ];
        }
    }

    public function getValidationErrors(): array
    {
        $errors = [];
        foreach ($this->extractor->getDirectNewThrows() as $throw) {
            $errors[] = sprintf(
                "Direct 'new' usage at %s:%d in %s::%s for %s",
                $throw['file'],
                $throw['line'],
                $throw['class'],
                $throw['method'],
                $throw['exception']
            );
        }

        foreach ($this->extractor->getResults() as $methodData) {
            if (!empty($methodData['throws']) && empty($methodData['attributes'])) {
                $errors[] = sprintf(
                    "Missing #[%s] at %s:%d for method %s::%s",
                    implode('|', $this->targetAttributes),
                    $methodData['file'],
                    $methodData['line'],
                    $methodData['class'],
                    $methodData['method']
                );
            }
        }

        return $errors;
    }
}
