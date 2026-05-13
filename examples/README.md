# Throwpedia Examples

This directory contains examples of different "happy flows" for Throwpedia.
The output can be found in the output subdirectories.

## Flow 01: Standard Flow

Located in `01-standard-flow/`.

This flow demonstrates the recommended way to use Throwpedia:

- Exception classes use static factory methods.
- Methods that throw these exceptions are decorated with `#[ExceptionReason]` attributes.
- The configuration disallows direct `new` throws.

To run this example:

```bash
./bin/throwpedia -f examples/01-standard-flow/throwpedia.neon
```

## Flow 02: Direct New Flow

Located in `02-direct-new-flow/`.

This flow demonstrates how Throwpedia handles direct `new` throws (e.g., `throw new \Exception()`) when allowed by
configuration.

To run this example:

```bash
./bin/throwpedia -f examples/02-direct-new-flow/throwpedia.neon
```

## Flow 03: Mixed Flow

Located in `03-mixed-flow/`.

This flow demonstrates a more complex scenario:

- Multiple reasons for the same exception factory method.
- A mix of factory methods and direct `new` throws.

To run this example:

```bash
./bin/throwpedia -f examples/03-mixed-flow/throwpedia.neon
```

## Flow 04: Custom Fields Flow

Located in `04-custom-fields-flow/`.

This flow demonstrates the power of the new configurable architecture:

- Multiple custom attributes (`#[CustomReason]`, `#[AnotherReason]`, `#[AuditReason]`) with **different sets of fields**
  for each.
- Per-attribute field definitions in `throwpedia.neon`.
- Automatic validation of each attribute class against its specific schema.
- Dynamic generation of documentation grouped by attribute with correct columns for each.

To run this example:

```bash
./bin/throwpedia -f examples/04-custom-fields-flow/throwpedia.neon
```

## Flow 05: Call Tree Flow

Located in `05-call-tree-flow/`.

This flow demonstrates the call tree and diagramming features:

- Automatically builds a reverse call graph from entry points (Controllers) to the methods where exceptions are thrown.
- Renders hierarchical calling trees in the Markdown report.
- Generates visual diagrams in Mermaid and PlantUML formats.

To run this example:

```bash
./bin/throwpedia -f examples/05-call-tree-flow/throwpedia.neon
```

## Running throwpedia

You can run throwpedia from the project root using the provided `bin/throwpedia` script.
Make sure you have dependencies installed via `composer install`.
