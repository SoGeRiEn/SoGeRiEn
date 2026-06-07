<?php
declare(strict_types=1);

final class AccessToken
{
    use SogerienClassHelp;

    public string $COOKIE_NAME = 'access_token';
    public string $COOKIE_NAME_SERVER = 'access_server_token';
    public string $COOKIE_NAME_IMPERSONATE = 'impersonate_access_token';

    public bool $status = false;
    public string $error = '';
    public string $patch_to_keyFile = '';

    /* =========================
     * создание ключа
     * ========================= */
    public function create_key(string $folder, string $filename): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        Sogerien::AEAD()->generate_key($folder, $filename);

        $this->status = Sogerien::AEAD()->status;
        $this->error = Sogerien::AEAD()->error;

        return   Sogerien::Debager()->capture_return($this->status, __CLASS__, __FUNCTION__);
}

    /* =========================
     * запись и чтение токенов
     * ========================= */

    /**
     * @param array<string, mixed> $userPayload
     */
    public function create_token(array $userPayload = []): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        if ($this->patch_to_keyFile === '') {
            $this->fail('Key file path must be non-empty');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $payload = $userPayload;
        $base = $this->build_payload_from_request();
        foreach ($base as $k => $v) {
            $payload[$k] = $v;
        }

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if (!is_string($json) || $json === '') {
            $this->fail('json_encode failed');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $token = Sogerien::AEAD()->encrypt_base64url($json, $this->patch_to_keyFile);
        if (!Sogerien::AEAD()->status || $token === '') {
            $this->fail(Sogerien::AEAD()->error !== '' ? Sogerien::AEAD()->error : 'AEAD encrypt failed');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return($token, __CLASS__, __FUNCTION__);
}

    /**
     * @return array<string, mixed>|null
     */
    public function read_token(string $token): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        if ($token === '') {
            $this->fail('Token is empty');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($this->patch_to_keyFile === '') {
            $this->fail('Key file path must be non-empty');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $plain = Sogerien::AEAD()->decrypt_base64url($token, $this->patch_to_keyFile);
        if (!Sogerien::AEAD()->status || $plain === '') {
            $this->fail(Sogerien::AEAD()->error !== '' ? Sogerien::AEAD()->error : 'AEAD decrypt failed');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $data = json_decode($plain, true);
        if (!is_array($data)) {
            $this->fail('json_decode failed');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return($data, __CLASS__, __FUNCTION__);
}

    /* =========================
     * работа с Cookie (user token)
     * ========================= */

    public function save_token_for_cookie(string $token, int $days = 30): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($days > 365) $days = 365;
        if ($days < 1) $days = 1;

        $this->fail('');

        if ($token === '') {
            $this->fail('Token is empty');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        if (strlen($token) > 3800) {
            $this->fail('Token too large for cookie');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $ok = setcookie(
            $this->COOKIE_NAME,
            $token,
            [
                'expires' => time() + ($days * 86400),
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );

        if (!$ok) {
            $this->fail('setcookie failed');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    public function save_impersonation_token_for_cookie(string $token, int $hours = 8): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($hours > 24) $hours = 24;
        if ($hours < 1) $hours = 1;

        $this->fail('');

        if ($token === '') {
            $this->fail('Token is empty');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        if (strlen($token) > 3800) {
            $this->fail('Token too large for cookie');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $ok = setcookie(
            $this->COOKIE_NAME_IMPERSONATE,
            $token,
            [
                'expires' => time() + ($hours * 3600),
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );

        if (!$ok) {
            $this->fail('setcookie failed');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    public function load_token_for_cookie(): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');
        $merged = Sogerien::InputRequest()->request_post_get_cookie_json;

        if (!isset($merged[$this->COOKIE_NAME])) {
            $this->fail('Cookie not found');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $token = (string)$merged[$this->COOKIE_NAME];
        if ($token === '') {
            $this->fail('Cookie is empty');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return($token, __CLASS__, __FUNCTION__);
}

    public function load_impersonation_token_for_cookie(): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');
        $merged = Sogerien::InputRequest()->request_post_get_cookie_json;

        if (!isset($merged[$this->COOKIE_NAME_IMPERSONATE])) {
            $this->fail('Impersonation token not found');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $token = (string)$merged[$this->COOKIE_NAME_IMPERSONATE];
        if ($token === '') {
            $this->fail('Impersonation token is empty');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return($token, __CLASS__, __FUNCTION__);
}

    public function delete_token_from_cookie(): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->delete_cookie_by_name($this->COOKIE_NAME), __CLASS__, __FUNCTION__);
}

    public function delete_impersonation_token_from_cookie(): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->delete_cookie_by_name($this->COOKIE_NAME_IMPERSONATE), __CLASS__, __FUNCTION__);
}

    /* =========================
     * Server token
     * ========================= */

    /**
     * @param array<string, mixed> $userPayload
     */
    public function create_token_for_server(array $userPayload = []): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        if ($this->patch_to_keyFile === '') {
            $this->fail('Key file path must be non-empty');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        if (!array_key_exists('allowed_ips', $userPayload)) {
            $this->fail('allowed_ips is required for server token');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $rules = $this->normalize_ip_rules($userPayload['allowed_ips']);
        if ($rules === []) {
            $this->fail('allowed_ips is empty or invalid');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }
        $userPayload['allowed_ips'] = $rules;

        $json = json_encode(
            $userPayload,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if (!is_string($json) || $json === '') {
            $this->fail('json_encode failed');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $token = Sogerien::AEAD()->encrypt_base64url($json, $this->patch_to_keyFile);
        if (!Sogerien::AEAD()->status || $token === '') {
            $this->fail(Sogerien::AEAD()->error !== '' ? Sogerien::AEAD()->error : 'AEAD encrypt failed');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return($token, __CLASS__, __FUNCTION__);
}

    /**
     * Проверка server-token:
     * - берём токен из request_post_get_cookie_json по COOKIE_NAME_SERVER
     * - decrypt
     * - проверяем ip входит ли в allowed_ips
     * - опционально сравниваем кастомные поля (только те что переданы)
     *
     * @param array<string, scalar|null> $compareFields
     * @return array<string, bool>
     */
    public function check_validate_server_token(array $compareFields = []): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        $out = [
            'decrypt' => false,
            'ip_allowed' => false,
        ];

        // ключевое изменение - грузим SERVER токен
        $token = $this->load_server_token();
        if (!$this->status || $token === '') {
            $this->ok();
            return   Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
        }

        $payload = $this->read_token($token);
        if (!$this->status || !is_array($payload)) {
            // ключевое изменение - удаляем SERVER cookie
            $this->delete_server_token_from_cookie();
            $out['decrypt'] = false;
            $out['ip_allowed'] = false;
            $this->ok();
            return   Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
        }

        $out['decrypt'] = true;

        $clientIp = (string)(Sogerien::InputRequest()->REMOTE_ADDR ?? '');
        $rules = $this->normalize_ip_rules($payload['allowed_ips'] ?? null);

        $out['ip_allowed'] = $clientIp !== '' && $rules !== [] && $this->ip_allowed($clientIp, $rules);

        foreach ($compareFields as $k => $expected) {
            if (!is_string($k) || $k === '') continue;
            if (isset($out[$k])) continue;

            if ($expected === null) {
                $out[$k] = array_key_exists($k, $payload) && $payload[$k] === null;
                continue;
            }

            if (is_bool($expected) || is_int($expected) || is_float($expected) || is_string($expected)) {
                $out[$k] = array_key_exists($k, $payload) && $payload[$k] === $expected;
            }
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
}

    /* =========================
     * validate (user token)
     * ========================= */

    /**
     * @param array<string, scalar|null> $compareFields
     * @return array<string, bool>
     */
    public function check_validate_token(array $compareFields = []): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        $out = [
            'decrypt' => false,
            'ip' => false,
            'HTTP_SEC_CH_UA' => false,
            'HTTP_SEC_CH_UA_MOBILE' => false,
            'HTTP_SEC_CH_UA_PLATFORM' => false,
            'user_agent' => false,
            'language' => false,
            'accept_encoding' => false,
        ];

        $token = $this->load_token_for_cookie();
        if (!$this->status || $token === '') {
            $this->ok();
            return   Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
        }

        $payload = $this->read_token($token);
        if (!$this->status || !is_array($payload)) {
            $this->delete_token_from_cookie();
            $out['decrypt'] = false;
            $this->ok();
            return   Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
        }

        $out['decrypt'] = true;

        $base = $this->build_payload_from_request();

        $out['ip'] = $this->cmp_str($payload, 'ip', $base['ip']);
        $out['HTTP_SEC_CH_UA'] = $this->cmp_str($payload, 'HTTP_SEC_CH_UA', $base['HTTP_SEC_CH_UA']);
        $out['HTTP_SEC_CH_UA_MOBILE'] = $this->cmp_str($payload, 'HTTP_SEC_CH_UA_MOBILE', $base['HTTP_SEC_CH_UA_MOBILE']);
        $out['HTTP_SEC_CH_UA_PLATFORM'] = $this->cmp_str($payload, 'HTTP_SEC_CH_UA_PLATFORM', $base['HTTP_SEC_CH_UA_PLATFORM']);
        $out['user_agent'] = $this->cmp_str($payload, 'user_agent', $base['user_agent']);
        $out['language'] = $this->cmp_str($payload, 'language', $base['language']);
        $out['accept_encoding'] = $this->cmp_str($payload, 'accept_encoding', $base['accept_encoding']);

        foreach ($compareFields as $k => $expected) {
            if (!is_string($k) || $k === '') continue;
            if (isset($out[$k])) continue;

            if ($expected === null) {
                $out[$k] = array_key_exists($k, $payload) && $payload[$k] === null;
                continue;
            }

            if (is_bool($expected) || is_int($expected) || is_float($expected) || is_string($expected)) {
                $out[$k] = array_key_exists($k, $payload) && $payload[$k] === $expected;
            }
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
}

    /* =========================
     * helpers
     * ========================= */

    public function load_server_token(): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');
        $merged = Sogerien::InputRequest()->request_post_get_cookie_json;

        if (!isset($merged[$this->COOKIE_NAME_SERVER])) {
            $this->fail('Server token not found');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $token = (string)$merged[$this->COOKIE_NAME_SERVER];
        if ($token === '') {
            $this->fail('Server token is empty');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return($token, __CLASS__, __FUNCTION__);
}

    public function delete_server_token_from_cookie(): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->delete_cookie_by_name($this->COOKIE_NAME_SERVER), __CLASS__, __FUNCTION__);
}

    private function delete_cookie_by_name(string $name): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        if ($name === '') {
            $this->ok();
            return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        }

        if (headers_sent() || PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            if (isset($_COOKIE[$name])) unset($_COOKIE[$name]);
            $this->ok();
            return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        }

        @setcookie(
            $name,
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );

        if (isset($_COOKIE[$name])) unset($_COOKIE[$name]);

        $this->ok();
        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    private function ok(): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->status = true;
        $this->error = '';
}

    private function fail(string $msg): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->status = false;
        $this->error = $msg;
}

    /**
     * @return array<string, string>
     */
    private function build_payload_from_request(): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $in = Sogerien::InputRequest();

        return   Sogerien::Debager()->capture_return([
            'ip' => (string)($in->REMOTE_ADDR ?? ''),
            'HTTP_SEC_CH_UA' => (string)($in->HTTP_SEC_CH_UA ?? ''),
            'HTTP_SEC_CH_UA_MOBILE' => (string)($in->HTTP_SEC_CH_UA_MOBILE ?? ''),
            'HTTP_SEC_CH_UA_PLATFORM' => (string)($in->HTTP_SEC_CH_UA_PLATFORM ?? ''),
            'user_agent' => (string)($in->HTTP_USER_AGENT ?? ''),
            'language' => (string)($in->HTTP_ACCEPT_LANGUAGE ?? ''),
            'accept_encoding' => (string)($in->HTTP_ACCEPT_ENCODING ?? ''),
        ], __CLASS__, __FUNCTION__);
}

    /**
     * @param array<string, mixed> $payload
     */
    private function cmp_str(array $payload, string $key, string $expected): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if (!array_key_exists($key, $payload)) return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        return   Sogerien::Debager()->capture_return((string)$payload[$key] === $expected, __CLASS__, __FUNCTION__);
}

    /**
     * @return array<int, string>
     */
    private function normalize_ip_rules(mixed $rules): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $out = [];

        if (is_string($rules)) {
            $parts = preg_split('/[\s,;]+/u', $rules, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($parts)) {
                foreach ($parts as $p) {
                    if (!is_string($p)) continue;
                    $p = trim($p);
                    if ($p !== '') $out[] = $p;
                }
            }
        } elseif (is_array($rules)) {
            foreach ($rules as $p) {
                if (!is_string($p)) continue;
                $p = trim($p);
                if ($p !== '') $out[] = $p;
            }
        } else {
            return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
        }

        $final = [];
        foreach ($out as $rule) {
            $rule = trim($rule);
            if ($rule === '') continue;

            if (str_contains($rule, '*')) {
                $cidr = $this->ipv4_wildcard_to_cidr($rule);
                if ($cidr !== '') $final[] = $cidr;
                continue;
            }

            if ($this->is_ip($rule) || $this->is_cidr($rule)) {
                $final[] = $rule;
            }
        }

        return Sogerien::Debager()->capture_return(array_values(array_unique($final)), __CLASS__, __FUNCTION__);
    }

    private function is_ip(string $ip): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return(filter_var($ip, FILTER_VALIDATE_IP) !== false, __CLASS__, __FUNCTION__);
    }

    private function is_cidr(string $cidr): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $cidr = trim($cidr);
        if ($cidr === '') return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        $pos = strpos($cidr, '/');
        if ($pos === false) return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);

        $ip = substr($cidr, 0, $pos);
        $mask = substr($cidr, $pos + 1);

        if ($ip === '' || $mask === '') return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        if (!$this->is_ip($ip)) return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        if (!ctype_digit($mask)) return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);

        $m = (int)$mask;
        $isV6 = str_contains($ip, ':');
        if ($isV6) return Sogerien::Debager()->capture_return($m >= 0 && $m <= 128, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($m >= 0 && $m <= 32, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<int, string> $rules
     */
    private function ip_allowed(string $clientIp, array $rules): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if (!$this->is_ip($clientIp)) return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);

        foreach ($rules as $rule) {
            if ($rule === $clientIp) return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);

            if ($this->is_cidr($rule) && $this->cidr_match($clientIp, $rule)) {
                return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
            }
        }

        return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
    }

    private function ipv4_wildcard_to_cidr(string $rule): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $parts = explode('.', $rule);
        if (count($parts) !== 4) return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);

        $mask = 0;
        $octets = [];

        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '*') {
                $octets[] = '0';
                continue;
            }
            if ($p === '' || !ctype_digit($p)) return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
            $n = (int)$p;
            if ($n < 0 || $n > 255) return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
            $octets[] = (string)$n;
            $mask += 8;
        }

        $seenStar = false;
        for ($i = 0; $i < 4; $i++) {
            if ($parts[$i] === '*') $seenStar = true;
            if ($seenStar && $parts[$i] !== '*') return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $ip = implode('.', $octets);
        return Sogerien::Debager()->capture_return($ip . '/' . $mask, __CLASS__, __FUNCTION__);
    }

    private function cidr_match(string $ip, string $cidr): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $pos = strpos($cidr, '/');
        if ($pos === false) return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);

        $subnet = substr($cidr, 0, $pos);
        $maskBits = (int)substr($cidr, $pos + 1);

        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false) return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);

        if (strlen($ipBin) !== strlen($subnetBin)) return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);

        $len = strlen($ipBin);
        $bytes = intdiv($maskBits, 8);
        $bits = $maskBits % 8;

        if ($bytes > 0) {
            if (substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        if ($bits === 0) return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        if ($bytes >= $len) return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);

        $mask = (0xFF << (8 - $bits)) & 0xFF;

        return   Sogerien::Debager()->capture_return(((ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask)), __CLASS__, __FUNCTION__);
    }
}
