<?php
declare(strict_types=1);

final class Lang
{
    public bool $status = true;
    public string $error = '';

    private const COOKIE_NAME = 'sogerien_lang';
    private const DEFAULT_LANG = 'ru';
    private const FALLBACK_LANG = 'en';
    private const CHECK_INTERVAL_SECONDS = 900;

    /** @var array<string,bool> */
    private array $supported_langs = [
        'ru' => true,
        'en' => true,
        'de' => true,
    ];

    private string $i18n_dir = '';
    private string $lang_file = '';
    private string $last_update_file = '';
    private string $current_lang = self::DEFAULT_LANG;

    /** @var array<string,array<string,string>> */
    private array $lang_cache = [];

    public function __construct()
    {
        $this->i18n_dir = Sogerien::$SOGERIEN_DIR . DIRECTORY_SEPARATOR . 'i18n';
        $this->lang_file = $this->i18n_dir . DIRECTORY_SEPARATOR . 'lang.json';
        $this->last_update_file = $this->i18n_dir . DIRECTORY_SEPARATOR . 'last_update.json';

        $this->ensure_bootstrap_files();
        $this->refresh_cache_if_needed();
        $this->current_lang = $this->resolve_current_lang();
    }

    public function get(string $key, string $lang = ''): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }

        $target_lang = $this->normalize_lang($lang);
        if ($target_lang === '') {
            $target_lang = $this->current_lang;
        }

        $map = $this->load_lang_map($target_lang);
        if (isset($map[$key]) && is_string($map[$key])) {
            return $map[$key];
        }

        if ($target_lang !== self::DEFAULT_LANG) {
            $fallback_map = $this->load_lang_map(self::DEFAULT_LANG);
            if (isset($fallback_map[$key]) && is_string($fallback_map[$key])) {
                return $fallback_map[$key];
            }
        }

        return $key;
    }

    public function get_current_lang(): string
    {
        return $this->current_lang;
    }

    /**
     * @return array<int,string>
     */
    public function get_supported_langs(): array
    {
        return array_keys($this->supported_langs);
    }

    /**
     * @return array<string,string>
     */
    public function get_current_lang_map(): array
    {
        return $this->load_lang_map($this->current_lang);
    }

    public function get_current_lang_map_json(): string
    {
        $json = json_encode(
            $this->get_current_lang_map(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return is_string($json) ? $json : '{}';
    }

    private function ensure_bootstrap_files(): void
    {
        if (!is_dir($this->i18n_dir) && !mkdir($this->i18n_dir, 0775, true) && !is_dir($this->i18n_dir)) {
            $this->fail('failed to create i18n directory: ' . $this->i18n_dir);
            return;
        }

        if (!is_file($this->lang_file)) {
            $this->write_json_file($this->lang_file, $this->default_lang_data());
        }

        if (!is_file($this->last_update_file)) {
            $payload = [
                'lang.json' => (string)$this->source_lang_mtime(),
                'last_chack' => '0',
                'lang_cache' => '0',
            ];
            $this->write_json_file($this->last_update_file, $payload);
        }
    }

    private function refresh_cache_if_needed(): void
    {
        $meta = $this->read_last_update();
        $now = time();
        $source_mtime = $this->source_lang_mtime();
        $has_cache = $this->has_cache_files();
        $last_check = (int)($meta['last_chack'] ?? 0);
        $is_check_due = ($now - $last_check) >= self::CHECK_INTERVAL_SECONDS;

        if (!$has_cache) {
            $this->generate_cache_files();
            $meta['lang.json'] = (string)$source_mtime;
            $meta['last_chack'] = (string)$now;
            $meta['lang_cache'] = (string)$now;
            $this->write_last_update($meta);
            return;
        }

        if (!$is_check_due) {
            return;
        }

        $stored_source_mtime = (int)($meta['lang.json'] ?? 0);
        if ($stored_source_mtime !== $source_mtime) {
            $this->generate_cache_files();
            $meta['lang.json'] = (string)$source_mtime;
            $meta['lang_cache'] = (string)$now;
        }

        $meta['last_chack'] = (string)$now;
        $this->write_last_update($meta);
    }

    private function read_last_update(): array
    {
        $decoded = $this->read_json_file($this->last_update_file);
        if (!is_array($decoded)) {
            return [
                'lang.json' => '0',
                'last_chack' => '0',
                'lang_cache' => '0',
            ];
        }

        return [
            'lang.json' => (string)($decoded['lang.json'] ?? '0'),
            'last_chack' => (string)($decoded['last_chack'] ?? '0'),
            'lang_cache' => (string)($decoded['lang_cache'] ?? '0'),
        ];
    }

    private function write_last_update(array $meta): void
    {
        $payload = [
            'lang.json' => (string)($meta['lang.json'] ?? '0'),
            'last_chack' => (string)($meta['last_chack'] ?? '0'),
            'lang_cache' => (string)($meta['lang_cache'] ?? '0'),
        ];
        $this->write_json_file($this->last_update_file, $payload);
    }

    private function has_cache_files(): bool
    {
        foreach (array_keys($this->supported_langs) as $lang) {
            $path = $this->lang_cache_path($lang);
            if (!is_file($path)) {
                return false;
            }
        }

        return true;
    }

    private function generate_cache_files(): void
    {
        $master = $this->read_json_file($this->lang_file);
        if (!is_array($master)) {
            $master = $this->default_lang_data();
            $this->write_json_file($this->lang_file, $master);
        }

        /** @var array<string,array<string,string>> $per_lang */
        $per_lang = [];
        foreach (array_keys($this->supported_langs) as $lang) {
            $per_lang[$lang] = [];
        }

        foreach ($master as $key => $row) {
            if (!is_string($key)) {
                continue;
            }
            if (!is_array($row)) {
                continue;
            }

            foreach (array_keys($this->supported_langs) as $lang) {
                $value = $row[$lang] ?? ($row[self::FALLBACK_LANG] ?? ($row[self::DEFAULT_LANG] ?? $key));
                $per_lang[$lang][$key] = is_string($value) ? $value : (string)$value;
            }
        }

        foreach ($per_lang as $lang => $map) {
            $path = $this->lang_cache_path($lang);
            $this->write_json_file($path, $map);
            $this->lang_cache[$lang] = $map;
        }
    }

    /**
     * @return array<string,string>
     */
    private function load_lang_map(string $lang): array
    {
        $lang = $this->normalize_lang($lang);
        if ($lang === '') {
            $lang = self::DEFAULT_LANG;
        }

        if (isset($this->lang_cache[$lang])) {
            return $this->lang_cache[$lang];
        }

        $path = $this->lang_cache_path($lang);
        if (!is_file($path)) {
            $this->generate_cache_files();
        }

        $decoded = $this->read_json_file($path);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        /** @var array<string,string> $map */
        $map = [];
        foreach ($decoded as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            $map[$k] = is_string($v) ? $v : (string)$v;
        }

        $this->lang_cache[$lang] = $map;
        return $map;
    }

    private function resolve_current_lang(): string
    {
        $input = Sogerien::InputRequest();

        $requested_lang = $this->normalize_lang((string)($input->request_post_get_cookie_json['lang'] ?? ''));
        $cookie_lang = $this->normalize_lang((string)($input->_COOKIE[self::COOKIE_NAME] ?? ''));

        if ($requested_lang !== '') {
            if ($requested_lang !== $cookie_lang) {
                $this->write_lang_cookie($requested_lang);
            }
            return $requested_lang;
        }

        if ($cookie_lang !== '') {
            return $cookie_lang;
        }

        $browser_lang = $this->detect_browser_lang((string)$input->HTTP_ACCEPT_LANGUAGE);
        if ($browser_lang !== '') {
            return $browser_lang;
        }

        return self::DEFAULT_LANG;
    }

    private function write_lang_cookie(string $lang): void
    {
        if (headers_sent()) {
            return;
        }

        $lang = $this->normalize_lang($lang);
        if ($lang === '') {
            return;
        }

        $expires = time() + 365 * 24 * 60 * 60;
        $secure = $this->is_https();

        if (PHP_VERSION_ID >= 70300) {
            setcookie(self::COOKIE_NAME, $lang, [
                'expires' => $expires,
                'path' => '/',
                'secure' => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
            return;
        }

        setcookie(self::COOKIE_NAME, $lang, $expires, '/; samesite=Lax', '', $secure, false);
    }

    private function detect_browser_lang(string $accept_language): string
    {
        $accept_language = trim($accept_language);
        if ($accept_language === '') {
            return '';
        }

        $chunks = explode(',', $accept_language);
        foreach ($chunks as $chunk) {
            $candidate = trim(explode(';', $chunk)[0] ?? '');
            if ($candidate === '') {
                continue;
            }

            $candidate = strtolower($candidate);
            $exact = $this->normalize_lang($candidate);
            if ($exact !== '') {
                return $exact;
            }

            $short = strtolower(substr($candidate, 0, 2));
            $short = $this->normalize_lang($short);
            if ($short !== '') {
                return $short;
            }
        }

        return '';
    }

    private function normalize_lang(string $lang): string
    {
        $lang = strtolower(trim($lang));
        if ($lang === '') {
            return '';
        }

        if (strlen($lang) > 2) {
            $lang = substr($lang, 0, 2);
        }

        return isset($this->supported_langs[$lang]) ? $lang : '';
    }

    private function lang_cache_path(string $lang): string
    {
        return $this->i18n_dir . DIRECTORY_SEPARATOR . $lang . '.json';
    }

    private function source_lang_mtime(): int
    {
        if (!is_file($this->lang_file)) {
            return time();
        }

        $mtime = filemtime($this->lang_file);
        return is_int($mtime) && $mtime > 0 ? $mtime : time();
    }

    private function is_https(): bool
    {
        $input = Sogerien::InputRequest();
        if (strtolower((string)$input->HTTP_X_FORWARDED_PROTO) === 'https') {
            return true;
        }
        if (strtolower((string)$input->REQUEST_SCHEME) === 'https') {
            return true;
        }
        return $input->HTTPS !== '' && strtolower($input->HTTPS) !== 'off';
    }

    /**
     * @return array<string,mixed>
     */
    private function read_json_file(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function write_json_file(string $path, array $payload): void
    {
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if (!is_string($json)) {
            $this->fail('json_encode failed for: ' . $path);
            return;
        }

        $tmp = $path . '.tmp_' . bin2hex(random_bytes(6));
        $written = file_put_contents($tmp, $json, LOCK_EX);
        if ($written === false) {
            @unlink($tmp);
            $this->fail('failed to write file: ' . $path);
            return;
        }

        if (!@rename($tmp, $path)) {
            if (!@copy($tmp, $path)) {
                @unlink($tmp);
                $this->fail('failed to replace file: ' . $path);
                return;
            }
            @unlink($tmp);
        }

        $this->ok();
    }

    /**
     * @return array<string,array<string,string>>
     */
    private function default_lang_data(): array
    {
        return [
            'login' => [
                'ru' => 'Логин',
                'en' => 'Login',
            ],
            'password' => [
                'ru' => 'Пароль',
                'en' => 'Password',
            ],
        ];
    }

    private function ok(): void
    {
        $this->status = true;
        $this->error = '';
    }

    private function fail(string $error): void
    {
        $this->status = false;
        $this->error = $error;
    }
}
