<?php

declare(strict_types=1);

/**
 * InputRequest (MIN)
 * Только нужные параметры.
 * _REQUEST - это объединение: COOKIE + GET + POST + JSON-body (как было ранее).
 */
final class InputRequest
{
    // ====== CLIENT ======
    public string $fingerprint_md5 = ''; // уникальный отпечаток браузера взяты самые уникальные параметры
    public string $HTTP_SEC_CH_UA = '';
    public string $HTTP_SEC_CH_UA_MOBILE = '';
    public string $HTTP_SEC_CH_UA_PLATFORM = '';
    public string $HTTP_USER_AGENT = '';
    public string $HTTP_ACCEPT_LANGUAGE = '';
    public string $REMOTE_ADDR = '';
    public string $HTTP_X_REAL_IP = '';
    public string $HTTP_X_FORWARDED_FOR = '';

    // ====== SERVER / ROUTE ======
    public string $HTTPS = '';
    public string $HTTP_HOST = '';
    public string $HTTP_X_FORWARDED_PROTO = '';
    public string $SERVER_NAME = '';
    public string $DOCUMENT_ROOT = '';
    public string $REQUEST_SCHEME = '';
    public string $SCRIPT_FILENAME = '';
    public string $REQUEST_URI = '';
    public string $url = '';
    public string $sogerien_domain = '';
    public string $domain = '';

    // ====== SUPERGLOBALS ======
    /** @var array<string,string> */
    public array $_COOKIE = [];
    /** @var array<string,mixed> */
    public array $request_post_get_cookie_json = [];

    /** @var array<string,mixed> */
    public array $current_connect = [];

    public function __construct()
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        // CLIENT
        $this->HTTP_SEC_CH_UA = (string)($_SERVER['HTTP_SEC_CH_UA'] ?? '');
        $this->HTTP_SEC_CH_UA_MOBILE = (string)($_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '');
        $this->HTTP_SEC_CH_UA_PLATFORM = (string)($_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '');
        $this->HTTP_USER_AGENT = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $this->HTTP_ACCEPT_LANGUAGE = (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        $this->REMOTE_ADDR = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $this->HTTP_X_REAL_IP = (string)($_SERVER['HTTP_X_REAL_IP'] ?? '');
        $this->HTTP_X_FORWARDED_FOR = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');

        // SERVER / ROUTE
        $this->HTTPS = (string)($_SERVER['HTTPS'] ?? '');
        $this->HTTP_HOST = (string)($_SERVER['HTTP_HOST'] ?? '');
        $this->HTTP_X_FORWARDED_PROTO = (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        $this->SERVER_NAME = (string)($_SERVER['SERVER_NAME'] ?? '');
        $this->DOCUMENT_ROOT = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        $this->REQUEST_SCHEME = (string)($_SERVER['REQUEST_SCHEME'] ?? '');
        $this->SCRIPT_FILENAME = (string)($_SERVER['SCRIPT_FILENAME'] ?? '');
        $this->REQUEST_URI = (string)($_SERVER['REQUEST_URI'] ?? '');
        $this->url = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

        // START domain ******************************  http(s)://domain.com
        $scheme = '';

        if ($this->HTTP_X_FORWARDED_PROTO !== '') {
            // приоритет прокси
            $scheme = strtolower($this->HTTP_X_FORWARDED_PROTO);
        } elseif ($this->REQUEST_SCHEME !== '') {
            $scheme = strtolower($this->REQUEST_SCHEME);
        } elseif ($this->HTTPS !== '' && $this->HTTPS !== 'off') {
            $scheme = 'https';
        } else {
            $scheme = 'http';
        }

        $host = $this->HTTP_HOST !== ''
            ? $this->HTTP_HOST
            : $this->SERVER_NAME;

        $this->sogerien_domain = $scheme . '://' . $host;
        // END domain ******************************  http(s)://domain.com

        // COOKIE
        /** @var array<string,string> $cookie */
        $cookie = $_COOKIE;
        $this->_COOKIE = $cookie;

        // JSON-body (если есть)
        $raw = file_get_contents('php://input') ?: '';
        $json = null;
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $json = $decoded;
            }
        }

        // _REQUEST (твой объединённый массив как раньше)
        // Важно: порядок как в твоём старом коде - cookie, get, post, json
        $merged = array_merge($_COOKIE, $_GET, $_POST, is_array($json) ? $json : []);

        // как было: requestData['data'] может быть JSON-строкой - декодим
        if (isset($merged['data']) && is_string($merged['data'])) {
            $tmp = json_decode($merged['data'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                $merged['data'] = $tmp;
            }
        }

        $this->request_post_get_cookie_json = $merged;
        $fingerprint = $fingerprint_string =
            (string)($this->HTTP_SEC_CH_UA ?? '') . '|' .
            (string)($this->HTTP_USER_AGENT ?? '') . '|' .
            (string)($this->HTTP_SEC_CH_UA_MOBILE ?? '') . '|' .
            (string)($this->HTTP_ACCEPT_LANGUAGE ?? '') . '|' .
            (string)($this->HTTP_SEC_CH_UA ?? '');
        $this->fingerprint_md5 = md5($fingerprint);

        // current_connect - строго только перечисленное
        $this->current_connect = [
            'fingerprint_md5' => $this->fingerprint_md5,
            'HTTP_SEC_CH_UA' => $this->HTTP_SEC_CH_UA,
            'HTTP_SEC_CH_UA_MOBILE' => $this->HTTP_SEC_CH_UA_MOBILE,
            'HTTP_SEC_CH_UA_PLATFORM' => $this->HTTP_SEC_CH_UA_PLATFORM,
            'HTTP_USER_AGENT' => $this->HTTP_USER_AGENT,
            'HTTP_ACCEPT_LANGUAGE' => $this->HTTP_ACCEPT_LANGUAGE,
            'REMOTE_ADDR' => $this->REMOTE_ADDR,
            'HTTP_X_REAL_IP' => $this->HTTP_X_REAL_IP,
            'HTTP_X_FORWARDED_FOR' => $this->HTTP_X_FORWARDED_FOR,

            'HTTPS' => $this->HTTPS,
            'HTTP_HOST' => $this->HTTP_HOST,
            'HTTP_X_FORWARDED_PROTO' => $this->HTTP_X_FORWARDED_PROTO,
            'SERVER_NAME' => $this->SERVER_NAME,
            'DOCUMENT_ROOT' => $this->DOCUMENT_ROOT,
            'REQUEST_SCHEME' => $this->REQUEST_SCHEME,
            'SCRIPT_FILENAME' => $this->SCRIPT_FILENAME,
            'REQUEST_URI' => $this->REQUEST_URI,

            '_COOKIE' => $this->_COOKIE,
            'url' => $this->url,
            'domain' => $this->sogerien_domain,
        ];
}
}
