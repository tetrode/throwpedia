<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->exclude([
        'vendor',
        'var',
        'storage',
        'bootstrap/cache',
        'node_modules',
    ])
    ->name('*.php');

return new Config()
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setRules([
        // Base
        '@PSR12'                    => true,
        '@PHP81Migration'           => true,
        '@PHPUnit84Migration:risky' => true,

        // Arrays
        'array_syntax'                    => ['syntax' => 'short'],
        'no_trailing_comma_in_singleline' => true,

        // Imports
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order'  => ['class', 'function', 'const'],
        ],
        'no_unused_imports' => true,

        // Strictness & safety
        'declare_strict_types' => true,
        'strict_param'         => true,
        'strict_comparison'    => true,

        // Clean code
        'no_superfluous_phpdoc_tags' => true,
        'phpdoc_trim'                => true,
        'phpdoc_align'               => false,
        'phpdoc_separation'          => true,

        // Formatting
        'binary_operator_spaces' => [
            'default'   => 'single_space',
            'operators' => [
                '=>' => 'align_single_space_minimal',
            ],
        ],
        'single_quote'          => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],

        // Modern PHP
        'modernize_types_casting'    => true,
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
        ],

        // Misc
        'no_empty_statement'   => true,
        'no_extra_blank_lines' => true,
        'yoda_style'           => true
    ])
    ->setFinder($finder);
