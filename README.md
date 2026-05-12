# Throwpedia – Business Exception Library

[![Throwpedia](throwpedia.png)](throwpedia.png)

Throwpedia performs static source-code analysis to extract and document business exceptions throughout your PHP
codebase. It bridges the gap between technical implementation and business logic by cataloging potential failure points.

Exceptions in code are technical artifacts, but they often represent business-relevant failure scenarios. Throwpedia
lets you document both the technical reason (what went wrong in code) and the business reason (what it means to the
user/stakeholder) in one place using PHP 8 attributes.

## How it works

Throwpedia scans your source directories for PHP files and identifies methods annotated with an attribute (default
the `#[ExceptionReason]` attribute). It correlates these annotations with `throw` statements to build a comprehensive
catalog of business-relevant exceptions. So, instead of maintaining separate documentation that inevitably goes stale, 
your exception documentation lives directly in your code via a PHP attribute. Running Throwpedia generates up-to-date
catalogs in multiple formats (Markdown, JSON, YAML, CSV, XML, etc.).

### Key Features

- **Semantic Cataloging**: Map technical exceptions to clear business and technical reasons using PHP 8 attributes.
- **Multiple Reasons per Method**: Supports multiple `#[ExceptionReason]` attributes on a single method for complex
  logic.
- **Static Factory Detection**: Identifies common patterns like `throw MyException::invalidData()`.
- **Direct Instantiation Tracking**: Optionally tracks and includes direct `throw new \Exception()` calls.
- **Data Deduplication**: Intelligent merging of identical reasons used across different parts of the application.
- **Quality Assurance**: Warns you if the same exception identifier is used for different business or technical reasons,
  ensuring documentation consistency.

## Example

When an unexpected or exceptional condition occurs in a function, an exception is thrown.

These exceptions are either caught by a `catch ()` statement and handled, or they are propagated to the caller in the
form of an HTTP response.

As an example, the following UserService class throws two Exceptions:

### Source code

```php 
class UserService
{
    #[ExceptionReason(
        identifier: 'USER_NOT_FOUND',
        technicalReason: 'The requested user ID does not exist in the database.',
        businessReason: 'The user you are looking for could not be found.'
    )]
    public function getUser(int $id): void
    {
        // ... some logic
        throw UserException::notFound($id);
    }

    #[ExceptionReason(
        identifier: 'USER_ALREADY_EXISTS',
        technicalReason: 'Duplicate entry for unique email field.',
        businessReason: 'An account with this email already exists.'
    )]
    public function register(string $email): void
    {
        // ... some logic
        throw UserException::emailTaken($email);
    }
}
```

Which Throwpedia automatically converts to the following Markdown:

### Report

```markdown 

# Business Exceptions Catalog

**Project:** unknown (unknown)
**Exceptions found:** 2

**Throwpedia Version:** 0.2.0
**Scan time:** 2026-05-12 13:36:47

## ExceptionReason

| Code                | Technical Reason                                      | Business Reason                                  | Exception                                 | Thrown From                          |
|---------------------|-------------------------------------------------------|--------------------------------------------------|-------------------------------------------|--------------------------------------|
| USER_ALREADY_EXISTS | Duplicate entry for unique email field.               | An account with this email already exists.       | `App\Exception\UserException::emailTaken` | App\Service\UserService::register:31 |
| USER_NOT_FOUND      | The requested user ID does not exist in the database. | The user you are looking for could not be found. | `App\Exception\UserException::notFound`   | App\Service\UserService::getUser:20  |

```

(Or to json, xml, yaml, toml, csv, tsv,psv if you want)

## Configuration

Throwpedia is configured via a file called `throwpedia.neon` in your project root, which looks as follows:

```neon
# Sources, relative to the throwpedia path
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
# For deduplication and unique identification, one parameter should be named 'identifier'.
attributes:
    ExceptionReason:
        identifier: Error Identfier
        technicalReason: Technical Reason
        businessReason: Business Reason
    AuditReason:
        identifier: Audit ID
        action: Audit Action

# Define custom fields for the attributes (optional)
# The key is the parameter name in the attribute constructor.
# 'identifier: label' indicates the field used for deduplication.
fields:
    identifier: Error Identifier
    technicalReason: Technical Reason
    businessReason: Business Reason

# Output files (supports .json, .yaml, .yml, .md, .markdown, .csv, .tsv, .psv, .xml, .toml). 
# Remove the ones that you do not need. Format is deducted from the extension
outputs:
    - output/exceptions.json
    - output/exceptions.md

# If true, direct 'throw new Exception()' is included in the catalog. This can be useful to gradually convert all 
# existing 'throw new Exception()' code towards 'throw MyException::MyReason()' 
# If false, it is reported as a validation error.
allowDirectNew: false

# If true, duplicate identifier warnings are suppressed. 
suppressDuplicateIdentifierWarning: false
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

Throwpedia doesn't just extract data; it validates your exception documentation:

- **Detects Missing Documentation**: Reports methods that throw exceptions but lack the required attributes.
- **Inconsistent Identifiers**: Warns if the same identifier is used with different descriptions. In the output, these
  are automatically disambiguated (e.g., `identifier`, `identifier_1`).
- **IDE Integration**: Validation issues are reported with project-relative paths and line numbers (e.g.,
  `src/Service/MyService.php:42`), allowing you to click directly to the source in IDEs like PhpStorm.

## Output Formats

- **Markdown**: A clean table perfect for project documentation or GitHub wikis. Includes project and meta information.
- **JSON**: Machine-readable format for automated reporting or frontend integration.
- **YAML**: Human-readable structured format for easy review.
- **CSV/TSV/PSV**: Delimited formats for spreadsheet or data processing tools.
- **XML**: Industry standard for data exchange.
- **TOML**: A minimal configuration format that's easy to read.

Each output format now includes metadata about the project (name, PHP version, total exceptions found) and the scan
itself (Throwpedia version, scan time).

## Versions

* v0.2.0: 2026-05-11: Added examples, support more output formats, added fields mapping, line numbers, improved deduplication
* v0.1.0: 2026-05-10: First version 
