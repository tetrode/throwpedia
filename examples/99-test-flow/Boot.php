<?php

declare(strict_types=1);

class Boot
{
    public static int $previousErrorReporting;
    /** @var callable|null */
    public static $previousErrorHandler;
    /** @var callable|null */
    public static $previousExceptionHandler;

    public static function init(): void
    {
        self::$previousErrorReporting = self::setMaxErrorReporting();
        self::$previousErrorHandler = self::setErrorHandler();
        self::$previousExceptionHandler = self::setExceptionHandler();
        self::registerShutdownFunction();
    }

    private static function setMaxErrorReporting(): int
    {
        // Enable maximum error reporting
        $previous = error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        return $previous;
    }

    private static function setErrorHandler(): ?callable
    {
        // Convert PHP errors/warnings/notices into ErrorException
        $previous = set_error_handler(
            static function (int $severity, string $message, string $file, int $line): bool {
                // Respect the @ operator
                if (!(error_reporting() & $severity)) {
                    return false;
                }

                throw new ErrorException($message, 0, $severity, $file, $line);
            }
        );

        return $previous;
    }

    private static function setExceptionHandler(): ?callable
    {
        // Catch uncaught exceptions
        $previousExceptionHandler = set_exception_handler(
            static function (Throwable $e): void {
                self::handleThrowable($e);
                exit(1);
            }
        );

        return $previousExceptionHandler;
    }

    public static function handleThrowable(Throwable $e): void
    {
        error_log(
            sprintf(
                '[%s] %s in %s:%d%sStack trace:%s',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                PHP_EOL,
                $e->getTraceAsString()
            )
        );

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $e.PHP_EOL);
        } else {
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo 'Internal Server Error';
        }
    }

    private static function registerShutdownFunction(): void
    {
        // Convert fatal errors into exceptions-ish handling
        register_shutdown_function(
            static function (): void {
                $error = error_get_last();

                if (null === $error) {
                    return;
                }

                $fatalTypes = [
                    E_ERROR,
                    E_PARSE,
                    E_CORE_ERROR,
                    E_CORE_WARNING,
                    E_COMPILE_ERROR,
                    E_COMPILE_WARNING,
                    E_USER_ERROR,
                ];

                if (!in_array($error['type'], $fatalTypes, true)) {
                    return;
                }

                $exception = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
                self::handleThrowable($exception);
            }
        );
    }
}

function safeRun(string $title, callable $cb): void
{
    echo PHP_EOL;
    echo "--- START Example: $title ---".PHP_EOL;
    try {
        $cb();
    } catch (Throwable $e) {
        Boot::handleThrowable($e);
    }
    echo "--- END  Example: $title ---".PHP_EOL;
}

Boot::init();

safeRun('Standard Warning', static function () {
    // handled by setErrorHandler() -> throws ErrorException
    file_get_contents('non_existent');
});

safeRun('Suppressed Error', static function () {
    // handled by setErrorHandler() but ignored due to @
    @file_get_contents('non_existent');
    echo '(Error was suppressed, so this line is reached)'.PHP_EOL;
});

safeRun('Standard Notice', static function () {
    // handled by setErrorHandler() -> throws ErrorException
    /** @noinspection PhpUndefinedVariableInspection */
    echo $undefined_variable;
});

safeRun('User Triggered Error', static function () {
    // handled by setErrorHandler() -> throws ErrorException
    /** @noinspection PhpDeprecatedTriggerErrorInspection */
    trigger_error('User error', E_USER_ERROR);
});

safeRun('Uncaught Exception', static function () {
    // caught by safeRun's try-catch -> handled by handleThrowable()
    /** @noinspection PhpUnhandledExceptionInspection */
    throw new Exception('Uncaught Exception');
});

safeRun('Throwable Error', static function () {
    // ArgumentCountError due to strict_types=1
    // caught by safeRun's try-catch -> handled by handleThrowable()
    /** @noinspection PhpExpressionResultUnusedInspection */
    /** @noinspection PhpParamsInspection */
    strpos();
});

safeRun('Fatal-ish Error (require)', static function () {
    // triggers warning then Error exception
    // caught by safeRun's try-catch -> handled by handleThrowable()
    /** @noinspection PhpIncludeInspection */
    require 'missing_file.php';
});

safeRun('Deprecated Warning', static function () {
    // handled by setErrorHandler() -> throws ErrorException
    trigger_error('Deprecated feature', E_USER_DEPRECATED);
});

echo '--- All examples finished ---'.PHP_EOL;
