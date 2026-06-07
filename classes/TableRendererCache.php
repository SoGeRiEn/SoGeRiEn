<?php
declare(strict_types=1);

final class TableRendererCache
{
    use SogerienClassHelp;

    public bool $status = false;
    public string $error = '';

    /**
     * Directory for cache files.
     * Example: /var/www/example.com/cache
     */
    public string $patch = '';

    /**
     * Простой API:
     * Sogerien::Cache()->TableRendererCache->save($html, 'file.damp');
     */
    public function save(string|callable $content_or_renderer, string $file_name): bool
    {
        if ($this->patch === '') {
            $this->patch = Sogerien::Cache()->folders() . DIRECTORY_SEPARATOR . 'table_renderer';
        }

        return $this->save_cache($content_or_renderer, $file_name);
    }

    /**
     * Простой API:
     * Sogerien::Cache()->TableRendererCache->load('file.damp');
     */
    public function load(string $file_name): string
    {
        if ($this->patch === '') {
            $this->patch = Sogerien::Cache()->folders() . DIRECTORY_SEPARATOR . 'table_renderer';
        }

        return $this->load_cache($file_name);
    }

    public function save_cache(string|callable $content_or_renderer, string $file_name): bool
    {
        $file_patch = $this->get_cache_file_patch($file_name);
        if ($file_patch === '') {
            return false;
        }

        $content = is_callable($content_or_renderer)
            ? $this->capture_render($content_or_renderer)
            : $content_or_renderer;

        if (!$this->status && is_callable($content_or_renderer)) {
            return false;
        }

        if (!$this->write_file_atomic($file_patch, $content)) {
            return false;
        }

        return $this->write_time_update_file($file_patch, time(), time());
    }

    public function load_cache(string $file_name): string
    {
        $file_patch = $this->get_cache_file_patch($file_name);
        if ($file_patch === '') {
            return '';
        }

        if (!is_file($file_patch)) {
            $this->fail('cache file not found: ' . $file_patch);
            return '';
        }

        $content = file_get_contents($file_patch);
        if ($content === false) {
            $this->fail('failed to read cache file: ' . $file_patch);
            return '';
        }

        $this->ensure_time_update_file_exists($file_patch);
        $this->ok();
        return $content;
    }

    public function cache_is_actual(string $file_name, string $last_update_patch = '', string $source_patch = ''): bool
    {
        $file_patch = $this->get_cache_file_patch($file_name);
        if ($file_patch === '') {
            return false;
        }

        if (!is_file($file_patch)) {
            $this->ok();
            return false;
        }

        $cache_time = filemtime($file_patch);
        if ($cache_time === false) {
            $this->fail('failed to read cache mtime: ' . $file_patch);
            return false;
        }

        $source_time = $this->get_source_updated_at($last_update_patch, $source_patch);
        if (!$this->status) {
            return false;
        }

        $this->ok();
        return $source_time <= 0 || $cache_time >= $source_time;
    }

    public function get_cache_or_render(
        string $file_name,
        callable $renderer,
        string $last_update_patch = '',
        string $source_patch = '',
        string $cache_last_update_file_name = ''
    ): string {
        $cache_is_actual = $this->cache_is_actual($file_name, $last_update_patch, $source_patch);
        $cache_check_status = $this->status;
        $cache_check_error = $this->error;

        if ($cache_is_actual) {
            $content = $this->load_cache($file_name);
            if ($this->status) {
                return $content;
            }
        }

        if (!$cache_is_actual && !$cache_check_status && $cache_check_error !== '') {
            $this->status = false;
            $this->error = $cache_check_error;
            return '';
        }

        $content = $this->capture_render($renderer);
        if (!$this->save_cache($content, $file_name)) {
            return '';
        }

        if ($cache_last_update_file_name !== '') {
            if ($last_update_patch !== '' && is_file($last_update_patch)) {
                $this->save_source_last_update($last_update_patch, $cache_last_update_file_name);
            } else {
                $this->save_cache((string)time(), $cache_last_update_file_name);
            }
        }

        $this->ok();
        return $content;
    }

    public function save_source_last_update(string $source_last_update_patch, string $file_name): bool
    {
        $source_last_update_patch = trim($source_last_update_patch);
        if ($source_last_update_patch === '') {
            return $this->fail('source_last_update_patch is empty');
        }

        if (!is_file($source_last_update_patch)) {
            return $this->fail('last_update file not found: ' . $source_last_update_patch);
        }

        $content = file_get_contents($source_last_update_patch);
        if ($content === false) {
            return $this->fail('failed to read last_update file: ' . $source_last_update_patch);
        }

        return $this->save_cache($content, $file_name);
    }

    public function get_cache_file_patch(string $file_name): string
    {
        $this->error = '';

        $base_patch = rtrim(trim($this->patch), "/\\");
        if ($base_patch === '') {
            $this->fail('patch is empty');
            return '';
        }

        $normalized_file_name = $this->normalize_file_name($file_name);
        if ($normalized_file_name === '') {
            return '';
        }

        $this->ok();
        return $base_patch . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $normalized_file_name);
    }

    private function normalize_file_name(string $file_name): string
    {
        $file_name = trim(str_replace('\\', '/', $file_name));
        $file_name = ltrim($file_name, '/');

        if ($file_name === '') {
            $this->fail('file_name is empty');
            return '';
        }

        $parts = array_values(array_filter(explode('/', $file_name), static fn(string $part): bool => $part !== ''));
        foreach ($parts as $part) {
            if ($part === '.' || $part === '..') {
                $this->fail('file_name contains invalid path segments');
                return '';
            }
        }

        $this->ok();
        return implode('/', $parts);
    }

    private function capture_render(callable $renderer): string
    {
        $level = ob_get_level();
        ob_start();

        try {
            $result = $renderer();
            $content = ob_get_clean();
            if ($content === false) {
                $this->fail('failed to capture renderer output');
                return '';
            }
            if ($content === '' && is_string($result)) {
                $content = $result;
            }
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                @ob_end_clean();
            }
            throw $e;
        }

        $this->ok();
        return $content;
    }

    private function write_file_atomic(string $file_patch, string $content): bool
    {
        $dir_patch = dirname($file_patch);
        if ($dir_patch === '' || $dir_patch === '.' || $dir_patch === DIRECTORY_SEPARATOR) {
            return $this->fail('invalid cache directory for file: ' . $file_patch);
        }

        if (!is_dir($dir_patch) && !mkdir($dir_patch, 0775, true) && !is_dir($dir_patch)) {
            return $this->fail('failed to create cache directory: ' . $dir_patch);
        }

        $tmp_patch = $file_patch . '.tmp_' . bin2hex(random_bytes(8));
        $bytes = file_put_contents($tmp_patch, $content, LOCK_EX);
        if ($bytes === false) {
            @unlink($tmp_patch);
            return $this->fail('failed to write cache file: ' . $file_patch);
        }

        if (!@rename($tmp_patch, $file_patch)) {
            if (!@copy($tmp_patch, $file_patch)) {
                @unlink($tmp_patch);
                return $this->fail('failed to replace cache file: ' . $file_patch);
            }
            @unlink($tmp_patch);
        }

        $this->ok();
        return true;
    }

    private function get_time_update_file_patch(string $file_patch): string
    {
        $dir_patch = dirname($file_patch);
        $filename = pathinfo($file_patch, PATHINFO_FILENAME);
        $extension = pathinfo($file_patch, PATHINFO_EXTENSION);

        if ($filename === '' || $filename === '.') {
            $this->fail('invalid cache filename: ' . $file_patch);
            return '';
        }

        $time_file_name = $filename . '_time_update';
        if ($extension !== '') {
            $time_file_name .= '.' . $extension;
        }

        return $dir_patch . DIRECTORY_SEPARATOR . $time_file_name;
    }

    private function write_time_update_file(string $cache_file_patch, int $server_updated_at, int $manual_updated_at): bool
    {
        $time_update_file_patch = $this->get_time_update_file_patch($cache_file_patch);
        if ($time_update_file_patch === '') {
            return false;
        }

        $payload = [
            'last_update' => (string)$server_updated_at,
            'last_update_server' => (string)$server_updated_at,
            'last_update_custom' => (string)$manual_updated_at,
        ];

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($json === false) {
            return $this->fail('failed to json_encode time_update payload');
        }

        return $this->write_file_atomic($time_update_file_patch, $json);
    }

    private function ensure_time_update_file_exists(string $cache_file_patch): void
    {
        $dir_patch = dirname($cache_file_patch);
        $filename = pathinfo($cache_file_patch, PATHINFO_FILENAME);
        $extension = pathinfo($cache_file_patch, PATHINFO_EXTENSION);
        if ($filename === '' || $filename === '.') {
            return;
        }

        $time_file_name = $filename . '_time_update';
        if ($extension !== '') {
            $time_file_name .= '.' . $extension;
        }

        $time_update_file_patch = $dir_patch . DIRECTORY_SEPARATOR . $time_file_name;
        if (is_file($time_update_file_patch)) {
            return;
        }

        $mtime = filemtime($cache_file_patch);
        $timestamp = is_int($mtime) && $mtime > 0 ? $mtime : time();

        $payload = [
            'last_update' => (string)$timestamp,
            'last_update_server' => (string)$timestamp,
            'last_update_custom' => (string)$timestamp,
        ];

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if (!is_string($json)) {
            return;
        }

        $tmp_patch = $time_update_file_patch . '.tmp_' . bin2hex(random_bytes(8));
        $bytes = @file_put_contents($tmp_patch, $json, LOCK_EX);
        if ($bytes === false) {
            @unlink($tmp_patch);
            return;
        }

        if (!@rename($tmp_patch, $time_update_file_patch)) {
            if (!@copy($tmp_patch, $time_update_file_patch)) {
                @unlink($tmp_patch);
                return;
            }
            @unlink($tmp_patch);
        }
    }

    private function get_source_updated_at(string $last_update_patch = '', string $source_patch = ''): int
    {
        $last_update_patch = trim($last_update_patch);
        if ($last_update_patch !== '') {
            if (!is_file($last_update_patch)) {
                return $this->fail_with_int('last_update file not found: ' . $last_update_patch);
            }

            $content = file_get_contents($last_update_patch);
            if ($content === false) {
                return $this->fail_with_int('failed to read last_update file: ' . $last_update_patch);
            }

            $timestamp = $this->extract_timestamp_from_string($content);
            if ($timestamp > 0) {
                $this->ok();
                return $timestamp;
            }

            $mtime = filemtime($last_update_patch);
            if ($mtime === false) {
                return $this->fail_with_int('failed to read last_update mtime: ' . $last_update_patch);
            }

            $this->ok();
            return $mtime;
        }

        $source_patch = trim($source_patch);
        if ($source_patch !== '') {
            if (!is_file($source_patch)) {
                return $this->fail_with_int('source file not found: ' . $source_patch);
            }

            $mtime = filemtime($source_patch);
            if ($mtime === false) {
                return $this->fail_with_int('failed to read source mtime: ' . $source_patch);
            }

            $this->ok();
            return $mtime;
        }

        $this->ok();
        return 0;
    }

    private function extract_timestamp_from_string(string $content): int
    {
        $content = trim($content);
        if ($content === '') {
            return 0;
        }

        if (is_numeric($content)) {
            return (int)$content;
        }

        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $timestamp = $this->extract_timestamp_from_value($decoded);
            if ($timestamp > 0) {
                return $timestamp;
            }
        }

        $timestamp = strtotime($content);
        return $timestamp === false ? 0 : $timestamp;
    }

    private function extract_timestamp_from_value(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $this->extract_timestamp_from_string($value);
        }

        if (!is_array($value)) {
            return 0;
        }

        $keys = ['last_update', 'updated_at', 'update_at', 'date', 'datetime', 'time', 'timestamp'];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $value)) {
                continue;
            }

            $timestamp = $this->extract_timestamp_from_value($value[$key]);
            if ($timestamp > 0) {
                return $timestamp;
            }
        }

        foreach ($value as $item) {
            $timestamp = $this->extract_timestamp_from_value($item);
            if ($timestamp > 0) {
                return $timestamp;
            }
        }

        return 0;
    }

    private function ok(): void
    {
        $this->status = true;
        $this->error = '';
    }

    private function fail(string $error): bool
    {
        $this->status = false;
        $this->error = $error;
        return false;
    }

    private function fail_with_int(string $error): int
    {
        $this->status = false;
        $this->error = $error;
        return 0;
    }
}
