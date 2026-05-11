# Throwpedia – Business Exception Library

[![Throwpedia](throwpedia.png)](throwpedia.png)

Throwpedia performs static source-code analysis to extract and document business exceptions throughout your PHP
codebase. It bridges the gap between technical implementation and business logic by cataloging potential failure points.

## How it works

Throwpedia scans your source directories for PHP files and identifies methods annotated with the `#[ExceptionReason]`
attribute (or other custom attributes that you can configure in the configuration file). It correlates these annotations 
with `throw` statements to build a comprehensive catalog of business-relevant exceptions.

### Key Features

- **Semantic Cataloging**: Map technical exceptions to clear business and technical reasons using PHP 8 attributes.
- **Multiple Reasons per Method**: Supports multiple `#[ExceptionReason]` attributes on a single method for complex
  logic.
- **Static Factory Detection**: Identifies common patterns like `throw MyException::invalidData()`.
- **Direct Instantiation Tracking**: Optionally tracks and includes direct `throw new \Exception()` calls.
- **Data Deduplication**: Intelligent merging of identical reasons used across different parts of the application.
- **Quality Assurance**: Warns you if the same exception code is used for different business or technical reasons,
  ensuring documentation consistency.

## Configuration

Throwpedia is configured via `throwpedia.neon` in your project root, which looks as follows:

```neon
# Source directories to scan (multiple supported)
source:
    - src

# Attributes to look for (multiple supported).
# You can provide a simple list of attribute names (they will use the global 'fields' or defaults):
# attributes:
#     - ExceptionReason
#     - AuditReason
#
# OR provide a map of attribute names to their specific fields.
# The key is the parameter name in the attribute constructor.
# For deduplication and unique identification, one parameter should be named 'code'.
attributes:
    ExceptionReason:
        code: Error Code
        technicalReason: Technical Reason
        businessReason: Business Reason
    AuditReason:
        code: Audit ID
        action: Audit Action

# Define custom fields for the attributes (optional)
# The key is the parameter name in the attribute constructor.
# 'code: label' indicates the field used for deduplication.
fields:
    code: Error Code
    technicalReason: Technical Reason
    businessReason: Business Reason

# Output files (supports .json, .yaml, .yml, .md, .markdown, .csv, .tsv, .psv, .xml, .toml). Remove the ones that you do not need. Format is deducted from the extension
outputs:
    - output/exceptions.json
    - output/exceptions.yaml
    - output/exceptions.md
    - output/exceptions.csv
    - output/exceptions.tsv
    - output/exceptions.psv
    - output/exceptions.xml
    - output/exceptions.toml

# If true, direct 'new Exception()' is included in the catalog. 
# If false, it is reported as a validation error.
allowDirectNew: false

# If true, duplicate code warnings are suppressed. 
suppressDuplicateCodeWarning: false
```

## Installation

```bash
composer require tetrode/throwpedia
```

## Usage

Run throwpedia from the project root:

```bash
vendor/bin/throwpedia
```

### Options

- `-f <file>`: Specify a custom configuration file path.

### First-time Setup

If no configuration file is found, Throwpedia enters an **interactive setup mode** to help you generate your initial
`throwpedia.neon` configuration.

## Quality & Validation

Throwpedia doesn't just extract data; it validates your documentation quality:

- **Missing Documentation**: Reports methods that throw exceptions but lack the required attributes.
- **Inconsistent Codes**: Warns if the same code is used with different descriptions. In the output, these are
  automatically disambiguated (e.g., `code`, `code_1`).
- **IDE Integration**: Validation issues are reported with project-relative paths and line numbers (e.g.,
  `src/Service/MyService.php:42`), allowing you to click directly to the source in IDEs like PhpStorm.

## Output Formats

- **Markdown**: A clean table perfect for project documentation or GitHub wikis. Includes project and meta information.
- **JSON**: Machine-readable format for automated reporting or frontend integration.
- **YAML**: Human-readable structured format for easy review.
- **CSV/TSV/PSV**: Delimited formats for spreadsheet or data processing tools.
- **XML**: Industry standard for data exchange.
- **TOML**: A minimal configuration format that's easy to read.

Each output format now includes metadata about the project (name, PHP version, total exceptions found) and the scan itself (Throwpedia version, scan time).

## Versions

v0.1.0: First version 