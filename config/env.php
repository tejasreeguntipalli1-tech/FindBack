<?php
/**
 * Lightweight Zero-Dependency Environment Loader
 * Loads key-value pairs from .env into getenv(), $_ENV, and $_SERVER
 * Safely ignores missing file, comments (#), and blank lines.
 */

if (!function_exists('load_env_file')) {
    function load_env_file($filePath = null) {
        if ($filePath === null) {
            $filePath = dirname(__DIR__) . '/.env';
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);

                // Strip outer quotes if present
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }

                // Don't overwrite existing system environment variables if already set
                if (getenv($key) === false) {
                    putenv("{$key}={$val}");
                }
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $val;
                }
                if (!isset($_SERVER[$key])) {
                    $_SERVER[$key] = $val;
                }
            }
        }

        return true;
    }
}

// Automatically load .env if present
load_env_file();
