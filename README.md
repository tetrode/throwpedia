# Exception Analysis Tool

This tool performs static source-code analysis to extract and document business exceptions throughout the codebase.

## How it works

The tool scans the `src/` directory for PHP files and identifies methods annotated with specific attributes (default: `#[ExceptionReason]`). It matches these attributes with `throw` statements in the same method to build a catalog of potential business failures.

### Supported Patterns

- **Static Factory Methods**: It detects `throw SomeException::factory()` calls.
- **Attributes**: It extracts metadata from `#[ExceptionReason('CODE', 'Technical Reason', 'Business Reason')]`.
- **Direct New**: It can optionally detect and report direct `new SomeException()` usage.

## Configuration

The tool is configured via `tool/analyze-exception.neon`.

```neon
# Source directories to scan (one or more)
source:
    - src
    - tests

# Attributes to look for
attributes:
    - ExceptionReason
    - BusinessReason

# Output files (extensions: .json, .yaml, .md)
outputs:
    - ./output/exceptions.json
    - ./output/exceptions.yaml
    - ./output/exceptions.md

# Whether to include direct 'new' usage in the catalog
allowDirectNew: false
```

## Installation

Before using Throwpedia, install the dependencies:

```bash
composer install
```

## Usage

Run the analysis tool from the project root:

```bash
php tool/analyze-exceptions.php
```

### Options

- `-f <file>`: Specify a custom configuration file.
- `-v`: Verbose mode (shows files being analyzed).
- `-vv`: Very verbose mode (shows files and methods being analyzed).

### First-time Setup

If no configuration file is found, the tool will enter an interactive mode to help you create `tool/analyze-exception.neon`.

## Output

The tool generates machine-readable and human-readable documentation:

- **JSON**: Structured data for integration with other tools.
- **YAML**: Clean format for configuration or documentation.
- **Markdown**: A formatted table suitable for project documentation.

## Validation

The tool reports:
- Methods that throw exceptions but lack the required attribute.
- Direct `new` usage (unless `allowDirectNew` is true, it is reported as a validation error).
