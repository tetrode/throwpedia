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

This flow demonstrates how Throwpedia handles direct `new` throws (e.g., `throw new \Exception()`) when allowed by configuration.

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
- Custom attribute `#[CustomReason]` instead of the default `#[ExceptionReason]`.
- Custom fields (`code`, `severity`, `ticket`) defined in `throwpedia.neon`.
- Automatic validation of the custom attribute class.
- Dynamic generation of documentation with custom labels.

To run this example:
```bash
./bin/throwpedia -f examples/04-custom-fields-flow/throwpedia.neon
```

## Running throwpedia
You can run throwpedia from the project root using the provided `bin/throwpedia` script.
Make sure you have dependencies installed via `composer install`.
