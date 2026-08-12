<?php

declare(strict_types=1);

/**
 * Loads application variables from the .env file in the project root.
 * Existing process/server variables always take precedence over file values.
 */
function app_load_environment(string $projectRoot): ?string
{
    $configuredPath = trim((string) (getenv('SKC_ENV_FILE') ?: ''));
    if ($configuredPath !== '') {
        return app_load_environment_file($configuredPath) ? $configuredPath : null;
    }

    $path = rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . '.env';

    return app_load_environment_file($path) ? $path : null;
}

function app_load_environment_file(string $path): bool
{
    if (!is_readable($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return false;
    }

    foreach ($lines as $lineNumber => $line) {
        if ($lineNumber === 0) {
            $line = ltrim($line, "\xEF\xBB\xBF");
        }

        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $rawValue] = explode('=', $line, 2);
        $name = trim($name);
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            continue;
        }

        if (getenv($name) !== false) {
            continue;
        }

        $value = app_parse_environment_value($rawValue);
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    return true;
}

function app_parse_environment_value(string $rawValue): string
{
    $value = trim($rawValue);
    if ($value === '') {
        return '';
    }

    $quote = $value[0];
    if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote)) {
        $value = substr($value, 1, -1);

        return $quote === '"'
            ? str_replace(['\\n', '\\r', '\\t', '\\"', '\\\\'], ["\n", "\r", "\t", '"', '\\'], $value)
            : str_replace(["\\'", '\\\\'], ["'", '\\'], $value);
    }

    $value = preg_replace('/\s+#.*$/', '', $value);

    return trim((string) $value);
}
