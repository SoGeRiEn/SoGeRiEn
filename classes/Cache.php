<?php
declare(strict_types=1);

final class Cache
{
    use SogerienClassHelp;

    public bool $status = false;
    public string $error = '';

    /**
     * Базовая директория кеша.
     * Берется из Sogerien::$patch_to_cache_File, иначе: <SOGERIEN_DIR>/cache
     */
    public string $base_patch = '';

    /** Кеш для HTML-рендера таблиц */
    public TableRendererCache $TableRendererCache;

    public function __construct()
    {
        $configured_patch = trim((string)Sogerien::$patch_to_cache_File);
        $default_patch = Sogerien::$SOGERIEN_DIR . DIRECTORY_SEPARATOR . 'cache';
        $this->base_patch = rtrim($configured_patch !== '' ? $configured_patch : $default_patch, "/\\");

        $this->TableRendererCache                        = new TableRendererCache();
        $this->TableRendererCache->patch                 = $this->folders() . DIRECTORY_SEPARATOR . 'table_renderer';
    }

    /**
     * Путь до корня кеша.
     */
    public function folders(): string
    {
        $configured_patch = trim((string)Sogerien::$patch_to_cache_File);
        if ($configured_patch !== '') {
            $normalized = rtrim($configured_patch, "/\\");
            if ($normalized !== '' && $normalized !== $this->base_patch) {
                $this->base_patch = $normalized;
                $this->TableRendererCache->patch = $this->base_patch . DIRECTORY_SEPARATOR . 'table_renderer';
            }
        }

        return $this->base_patch;
    }

    /**
     * Сохранить любые данные в файл кеша.
     * @param mixed $data
     */
    public function save(mixed $data, string $file_name, ?int $updated_at = null): bool
    {
        $this->reset();

        $file_path = $this->get_cache_file_path($file_name);
        if ($file_path === '') {
            return false;
        }

        $server_updated_at = time();
        $stored_updated_at = $updated_at ?? $server_updated_at;

        $payload = [
            'updated_at' => $stored_updated_at,
            'data'       => $data,
        ];

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($json === false) {
            return $this->fail('failed to json_encode cache payload');
        }

        if (!$this->write_file_atomic($file_path, $json)) {
            return false;
        }

        return $this->write_time_update_file($file_name, $server_updated_at, $stored_updated_at);
    }

    /**
     * Загрузить данные из кеша.
     * @return mixed|null
     */
    public function load(string $file_name, ?int &$updated_at = null): mixed
    {
        $this->reset();
        $updated_at = $this->get_last_update($file_name);
        if (!$this->status) {
            return null;
        }

        $file_path = $this->get_cache_file_path($file_name);
        if ($file_path === '') {
            return null;
        }

        if (!is_file($file_path)) {
            $this->fail('cache file not found: ' . $file_path);
            return null;
        }

        $content = file_get_contents($file_path);
        if ($content === false) {
            $this->fail('failed to read cache file: ' . $file_path);
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            // старый формат или битый файл
            $this->fail('invalid cache format in file: ' . $file_path);
            return null;
        }

        if (isset($decoded['updated_at'])) {
            $decoded_updated_at = $this->parse_timestamp($decoded['updated_at']);
            if ($decoded_updated_at > 0) {
                $updated_at = $decoded_updated_at;
            }
        }

        $this->ok();
        return $decoded['data'] ?? null;
    }

    /**
     * Получить время последнего обновления кеша из отдельного файла времени.
     * По умолчанию возвращается серверное время записи.
     * Если $use_manual_time=true, возвращается вручную переданное время записи.
     */
    public function get_last_update(string $file_name, bool $use_manual_time = false): int
    {
        $this->reset();

        $time_update_file_path = $this->get_time_update_file_path($file_name);
        if ($time_update_file_path === '') {
            return 0;
        }

        if (is_file($time_update_file_path)) {
            $time_payload = $this->read_time_update_payload($time_update_file_path);
            if (is_array($time_payload)) {
                $key_priority = $use_manual_time
                    ? ['last_update_custom', 'last_update_manual', 'last_update']
                    : ['last_update_server', 'last_update'];

                foreach ($key_priority as $key) {
                    if (!array_key_exists($key, $time_payload)) {
                        continue;
                    }

                    $timestamp = $this->parse_timestamp($time_payload[$key]);
                    if ($timestamp > 0) {
                        $this->ok();
                        return $timestamp;
                    }
                }
            }
        }

        $cache_file_path = $this->get_cache_file_path($file_name);
        if ($cache_file_path === '') {
            return 0;
        }

        if (!is_file($cache_file_path)) {
            $this->ok();
            return 0;
        }

        $mtime = filemtime($cache_file_path);
        if ($mtime === false) {
            return $this->fail_with_int('failed to read cache mtime: ' . $cache_file_path);
        }

        $this->ok();
        return $mtime;
    }

    /**
     * Проверка, прошел ли интервал от времени апдейта.
     * - default: server_now vs server_saved_time
     * - manual: if $compare_time is provided, compares it vs manual_saved_time
     */
    public function is_interval_elapsed(string $file_name, int $interval_seconds, ?int $compare_time = null): bool
    {
        $this->reset();

        if ($interval_seconds < 0) {
            return $this->fail('interval_seconds must be >= 0');
        }

        $manual_compare = $compare_time !== null;
        $last_update = $this->get_last_update($file_name, $manual_compare);
        if (!$this->status) {
            return false;
        }

        if ($last_update <= 0) {
            $this->ok();
            return true;
        }

        $now = $compare_time ?? time();
        if ($now < 0) {
            return $this->fail('compare_time must be >= 0');
        }

        $this->ok();
        return ($now - $last_update) >= $interval_seconds;
    }

    private function get_cache_file_path(string $file_name): string
    {
        $file_name = trim(str_replace('\\', '/', $file_name));
        $file_name = ltrim($file_name, '/');

        if ($file_name === '') {
            $this->fail('file_name is empty');
            return '';
        }

        $parts = array_values(array_filter(
            explode('/', $file_name),
            static fn(string $part): bool => $part !== ''
        ));

        foreach ($parts as $part) {
            if ($part === '.' || $part === '..') {
                $this->fail('file_name contains invalid path segments');
                return '';
            }
        }

        $base = rtrim($this->folders(), "/\\");
        if ($base === '') {
            $this->fail('base cache folder is empty');
            return '';
        }

        return $base . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
    }

    private function get_time_update_file_path(string $file_name): string
    {
        $cache_file_path = $this->get_cache_file_path($file_name);
        if ($cache_file_path === '') {
            return '';
        }

        $dir = dirname($cache_file_path);
        $filename = pathinfo($cache_file_path, PATHINFO_FILENAME);
        $extension = pathinfo($cache_file_path, PATHINFO_EXTENSION);

        if ($filename === '' || $filename === '.') {
            $this->fail('invalid cache filename: ' . $cache_file_path);
            return '';
        }

        $time_file_name = $filename . '_time_update';
        if ($extension !== '') {
            $time_file_name .= '.' . $extension;
        }

        return $dir . DIRECTORY_SEPARATOR . $time_file_name;
    }

    private function write_time_update_file(string $file_name, int $server_updated_at, int $manual_updated_at): bool
    {
        $time_update_file_path = $this->get_time_update_file_path($file_name);
        if ($time_update_file_path === '') {
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

        return $this->write_file_atomic($time_update_file_path, $json);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function read_time_update_payload(string $time_update_file_path): ?array
    {
        $content = file_get_contents($time_update_file_path);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function parse_timestamp(mixed $value): int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : 0;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return 0;
            }

            if (is_numeric($value)) {
                $timestamp = (int)$value;
                return $timestamp > 0 ? $timestamp : 0;
            }

            $timestamp = strtotime($value);
            if ($timestamp === false || $timestamp <= 0) {
                return 0;
            }

            return $timestamp;
        }

        return 0;
    }

    private function write_file_atomic(string $file_path, string $content): bool
    {
        $dir = dirname($file_path);
        if ($dir === '' || $dir === '.' || $dir === DIRECTORY_SEPARATOR) {
            return $this->fail('invalid cache directory for file: ' . $file_path);
        }

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return $this->fail('failed to create cache directory: ' . $dir);
        }

        $tmp_path = $file_path . '.tmp_' . bin2hex(random_bytes(8));
        $bytes    = file_put_contents($tmp_path, $content, LOCK_EX);
        if ($bytes === false) {
            @unlink($tmp_path);
            return $this->fail('failed to write cache file: ' . $file_path);
        }

        if (!@rename($tmp_path, $file_path)) {
            if (!@copy($tmp_path, $file_path)) {
                @unlink($tmp_path);
                return $this->fail('failed to replace cache file: ' . $file_path);
            }
            @unlink($tmp_path);
        }

        $this->ok();
        return true;
    }

    private function reset(): void
    {
        $this->status = false;
        $this->error  = '';
    }

    private function ok(): void
    {
        $this->status = true;
        $this->error  = '';
    }

    private function fail(string $error): bool
    {
        $this->status = false;
        $this->error  = $error;
        return false;
    }

    private function fail_with_int(string $error): int
    {
        $this->status = false;
        $this->error  = $error;
        return 0;
    }
}

