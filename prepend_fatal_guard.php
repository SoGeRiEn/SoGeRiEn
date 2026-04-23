<?php
declare(strict_types=1);

/**
 * Pre-bootstrap fatal guard.
 * Captures startup/parse fatals that happen before framework bootstrap.
 */
if (!function_exists('pm_prebootstrap_log')) {
    /**
     * @param array<string,mixed> $payload
     */
    function pm_prebootstrap_log(array $payload): void
    {
        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($line) || $line === '') {
            return;
        }

        $tmpFile = rtrim((string)sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'proxymint_prebootstrap_fatal.log';
        @file_put_contents($tmpFile, $line . PHP_EOL, FILE_APPEND);
        @error_log('PREBOOT_FATAL ' . $line);
    }
}

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if (!is_array($e)) {
        return;
    }

    $type = (int)($e['type'] ?? 0);
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($type, $fatalTypes, true)) {
        return;
    }

    pm_prebootstrap_log([
        'ts' => gmdate('c'),
        'type' => $type,
        'message' => (string)($e['message'] ?? ''),
        'file' => (string)($e['file'] ?? ''),
        'line' => (int)($e['line'] ?? 0),
        'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
        'host' => (string)($_SERVER['HTTP_HOST'] ?? ''),
        'remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
    ]);
});

