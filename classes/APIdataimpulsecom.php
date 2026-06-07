<?php
declare(strict_types=1);

final class APIdataimpulsecom
{
    use SogerienClassHelp;

    public bool $status = false;
    public string $error = '';

    public string $api_key = '';
    public string $api_login = '';
    public string $api_password = '';
    public string $base_url = 'https://api.dataimpulse.com/reseller';

    public bool $debug_enabled = true;
    public array $debug = [];
    public array $debug_history = [];

    public int $last_http_code = 0;
    public string $last_url = '';
    public int $request_connect_timeout_seconds = 10;
    public int $request_timeout_seconds = 45;
    public string $proxy_host_dns = 'gw.dataimpulse.com';
    public string $proxy_host_ip = '74.81.81.81';
    public int $proxy_rotating_http_port = 823;
    public int $proxy_rotating_socks5_port = 824;
    public int $proxy_sticky_default_port = 10000;

    /** @var array<string,bool> */
    private const SUPPORTED_POOL_TYPES = [
        'datacenter' => true,
        'residential' => true,
        'mobile' => true,
    ];

    /** @var array<string,bool> */
    private const SUPPORTED_PROTOCOLS = [
        'http' => true,
        'socks5' => true,
    ];

    /** @var array<string,bool> */
    private const SUPPORTED_PERIODS = [
        'day' => true,
        'week' => true,
        'month' => true,
        '3months' => true,
        '6months' => true,
        'year' => true,
        '2years' => true,
    ];

    /** @var array<string,bool> */
    private const SUPPORTED_DATETIME_AGGREGATES = [
        'minute' => true,
        'hour' => true,
        'day' => true,
    ];

    /** @var array<int|string,string> */
    private const PROXY_HTTP_ERRORS = [
        400 => 'Bad Request',
        403 => 'PORT_BLOCKED / SITE_PERMANENTLY_BLOCKED / HOST_BLOCKED',
        407 => 'NO_USER / TRAFFIC_EXHAUSTED / THREADS_EXHAUSTED / PORT_NOT_ALLOWED / USER_BLOCKED',
        500 => 'INTERNAL_SERVER_ERROR',
        502 => 'NO_HOST_CONNECTION',
        503 => 'NO_RAY',
    ];

    private const STICKY_MIN_PORT = 10000;
    private const STICKY_MAX_PORT = 20000;

    public function set_api_key(string $api_key): void
    {
        $this->api_key = trim($api_key);
        $this->ok();
    }

    public function set_api_credentials(string $login, string $password): void
    {
        $login = trim($login);
        $password = trim($password);
        if ($login === '' || $password === '') {
            $this->fail('login/password is empty');
            return;
        }

        $this->api_login = $login;
        $this->api_password = $password;
        $this->ok();
    }

    public function set_base_url(string $base_url): void
    {
        $base_url = trim($base_url);
        if ($base_url === '') {
            $this->fail('base_url is empty');
            return;
        }

        $this->base_url = rtrim($base_url, '/');
        $this->ok();
    }

    public function set_request_timeouts(int $connect_timeout_seconds, int $timeout_seconds): void
    {
        if ($connect_timeout_seconds <= 0 || $timeout_seconds <= 0) {
            $this->fail('timeouts must be > 0');
            return;
        }
        $this->request_connect_timeout_seconds = $connect_timeout_seconds;
        $this->request_timeout_seconds = $timeout_seconds;
        $this->ok();
    }

    public function set_proxy_hosts(string $dns_host, string $ip_host = ''): void
    {
        $dns_host = trim($dns_host);
        if ($dns_host === '') {
            $this->fail('dns_host is empty');
            return;
        }

        if ($ip_host !== '') {
            $ip_host = trim($ip_host);
            if (filter_var($ip_host, FILTER_VALIDATE_IP) === false) {
                $this->fail('ip_host is invalid');
                return;
            }
            $this->proxy_host_ip = $ip_host;
        }

        $this->proxy_host_dns = $dns_host;
        $this->ok();
    }

    public function set_proxy_ports(int $rotating_http_port = 823, int $rotating_socks5_port = 824, int $sticky_default_port = 10000): void
    {
        if ($rotating_http_port <= 0 || $rotating_socks5_port <= 0) {
            $this->fail('rotating ports must be > 0');
            return;
        }
        if ($sticky_default_port < self::STICKY_MIN_PORT || $sticky_default_port > self::STICKY_MAX_PORT) {
            $this->fail('sticky_default_port must be in range 10000..20000');
            return;
        }

        $this->proxy_rotating_http_port = $rotating_http_port;
        $this->proxy_rotating_socks5_port = $rotating_socks5_port;
        $this->proxy_sticky_default_port = $sticky_default_port;
        $this->ok();
    }

    /** @return array<int|string,string> */
    public function proxy_http_errors(): array
    {
        $this->ok();
        return self::PROXY_HTTP_ERRORS;
    }

    /**
     * Build login with DataImpulse manual parameters.
     *
     * @param array<string,mixed> $targeting
     */
    public function build_proxy_login(string $base_login, array $targeting = []): ?string
    {
        $base_login = trim($base_login);
        if ($base_login === '') {
            $this->fail('base_login is empty');
            return null;
        }
        if (preg_match('/\s/', $base_login) === 1) {
            $this->fail('base_login must not contain spaces');
            return null;
        }

        $cr = $this->normalize_proxy_values($targeting['cr'] ?? $targeting['countries'] ?? null, 'country_codes');
        if ($cr === false) {
            return null;
        }
        $nocr = $this->normalize_proxy_values($targeting['nocr'] ?? $targeting['exclude_countries'] ?? null, 'country_codes');
        if ($nocr === false) {
            return null;
        }
        $state = $this->normalize_proxy_values($targeting['state'] ?? $targeting['states'] ?? null, 'geo_code');
        if ($state === false) {
            return null;
        }
        $nostate = $this->normalize_proxy_values($targeting['nostate'] ?? $targeting['exclude_states'] ?? null, 'geo_code');
        if ($nostate === false) {
            return null;
        }
        $city = $this->normalize_proxy_values($targeting['city'] ?? $targeting['cities'] ?? null, 'geo_code');
        if ($city === false) {
            return null;
        }
        $nocity = $this->normalize_proxy_values($targeting['nocity'] ?? $targeting['exclude_cities'] ?? null, 'geo_code');
        if ($nocity === false) {
            return null;
        }
        $zip = $this->normalize_proxy_values($targeting['zip'] ?? $targeting['zipcodes'] ?? null, 'zip');
        if ($zip === false) {
            return null;
        }
        $nozip = $this->normalize_proxy_values($targeting['nozip'] ?? $targeting['exclude_zipcodes'] ?? null, 'zip');
        if ($nozip === false) {
            return null;
        }
        $asn = $this->normalize_proxy_values($targeting['asn'] ?? $targeting['asns'] ?? null, 'asn');
        if ($asn === false) {
            return null;
        }
        $noasn = $this->normalize_proxy_values($targeting['noasn'] ?? $targeting['exclude_asn'] ?? null, 'asn');
        if ($noasn === false) {
            return null;
        }

        $has_target_filters = (
            $state !== []
            || $nostate !== []
            || $city !== []
            || $nocity !== []
            || $zip !== []
            || $nozip !== []
            || $asn !== []
        );
        if ($has_target_filters && $cr === []) {
            $this->fail('country is required for state/city/zip/asn filters');
            return null;
        }

        $anon_raw = $targeting['anon'] ?? $targeting['anonymous'] ?? null;
        $anon = false;
        if ($anon_raw !== null) {
            $anon = $this->normalize_bool($anon_raw, 'anon');
            if ($anon === null) {
                return null;
            }
        }

        $sessid = trim((string)($targeting['sessid'] ?? $targeting['session_id'] ?? ''));
        if ($sessid !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $sessid) !== 1) {
            $this->fail('sessid must match ^[A-Za-z0-9._-]+$');
            return null;
        }

        $sessttl_raw = $targeting['sessttl'] ?? $targeting['session_ttl'] ?? $targeting['rotation_interval'] ?? null;
        $sessttl = null;
        if ($sessttl_raw !== null && $sessttl_raw !== '') {
            $sessttl = (int)$sessttl_raw;
            if ($sessttl < 1 || $sessttl > 120) {
                $this->fail('sessttl must be in range 1..120');
                return null;
            }
        }

        $parts = [];
        if ($cr !== []) {
            $parts[] = 'cr.' . implode(',', $cr);
        }
        if ($nocr !== []) {
            $parts[] = 'nocr.' . implode(',', $nocr);
        }
        if ($noasn !== []) {
            $parts[] = 'noasn.' . implode(',', $noasn);
        }
        if ($state !== []) {
            $parts[] = 'state.' . implode(',', $state);
        }
        if ($nostate !== []) {
            $parts[] = 'nostate.' . implode(',', $nostate);
        }
        if ($city !== []) {
            $parts[] = 'city.' . implode(',', $city);
        }
        if ($nocity !== []) {
            $parts[] = 'nocity.' . implode(',', $nocity);
        }
        if ($zip !== []) {
            $parts[] = 'zip.' . implode(',', $zip);
        }
        if ($nozip !== []) {
            $parts[] = 'nozip.' . implode(',', $nozip);
        }
        if ($asn !== []) {
            $parts[] = 'asn.' . implode(',', $asn);
        }
        if ($anon) {
            $parts[] = 'anon.1';
        }
        if ($sessid !== '') {
            $parts[] = 'sessid.' . $sessid;
        }
        if ($sessttl !== null) {
            $parts[] = 'sessttl.' . (string)$sessttl;
        }

        $this->ok();
        if ($parts === []) {
            return $base_login;
        }

        return $base_login . '__' . implode(';', $parts);
    }

    public function proxy_auth(
        string $login,
        string $password,
        string $protocol = 'http',
        bool $sticky = false,
        int $sticky_port = 0,
        string $host = '',
        bool $use_ip_host = false
    ): ?string {
        $login = trim($login);
        $password = trim($password);
        if ($login === '' || $password === '') {
            $this->fail('login/password is empty');
            return null;
        }

        $resolved_protocol = $this->normalize_proxy_protocol($protocol);
        if ($resolved_protocol === null) {
            return null;
        }
        $resolved_host = $this->resolve_proxy_host($host, $use_ip_host);
        if ($resolved_host === null) {
            return null;
        }

        $port = $this->resolve_proxy_port($resolved_protocol, $sticky, $sticky_port);
        if ($port === null) {
            return null;
        }

        $this->ok();
        return $login . ':' . $password . '@' . $resolved_host . ':' . (string)$port;
    }

    public function proxy_url(
        string $login,
        string $password,
        string $protocol = 'http',
        bool $sticky = false,
        int $sticky_port = 0,
        string $host = '',
        bool $use_ip_host = false
    ): ?string {
        $resolved_protocol = $this->normalize_proxy_protocol($protocol);
        if ($resolved_protocol === null) {
            return null;
        }

        $auth = $this->proxy_auth($login, $password, $resolved_protocol, $sticky, $sticky_port, $host, $use_ip_host);
        if ($auth === null) {
            return null;
        }

        $scheme = $resolved_protocol === 'socks5' ? 'socks5' : 'http';
        $this->ok();
        return $scheme . '://' . $auth;
    }

    public function rotating_proxy_url(
        string $login,
        string $password,
        string $protocol = 'http',
        string $host = '',
        bool $use_ip_host = false
    ): ?string {
        return $this->proxy_url($login, $password, $protocol, false, 0, $host, $use_ip_host);
    }

    public function sticky_proxy_url(
        string $login,
        string $password,
        string $protocol = 'http',
        int $port = 10000,
        string $host = '',
        bool $use_ip_host = false
    ): ?string {
        return $this->proxy_url($login, $password, $protocol, true, $port, $host, $use_ip_host);
    }

    /**
     * @param array<string,mixed> $targeting
     */
    public function rotating_proxy_url_with_targeting(
        string $base_login,
        string $password,
        array $targeting = [],
        string $protocol = 'http',
        string $host = '',
        bool $use_ip_host = false
    ): ?string {
        $login = $this->build_proxy_login($base_login, $targeting);
        if ($login === null) {
            return null;
        }
        return $this->rotating_proxy_url($login, $password, $protocol, $host, $use_ip_host);
    }

    /**
     * @param array<string,mixed> $targeting
     */
    public function sticky_proxy_url_with_targeting(
        string $base_login,
        string $password,
        array $targeting = [],
        string $protocol = 'http',
        int $port = 10000,
        string $host = '',
        bool $use_ip_host = false
    ): ?string {
        $login = $this->build_proxy_login($base_login, $targeting);
        if ($login === null) {
            return null;
        }
        return $this->sticky_proxy_url($login, $password, $protocol, $port, $host, $use_ip_host);
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|array<int,mixed>|string|null $body
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function request_json(
        string $method,
        string $path,
        array $query = [],
        array|string|null $body = null,
        string $body_type = 'none',
        bool $auth_required = true,
        bool $allow_empty_json = false
    ): array|null {
        $resp = $this->request(
            $method,
            $path,
            $query,
            $body,
            $body_type,
            true,
            $auth_required,
            $allow_empty_json
        );
        if (($resp['ok'] ?? false) !== true) {
            return null;
        }

        $json = $resp['json'] ?? null;
        if (!is_array($json)) {
            $this->fail('Invalid JSON response');
            return null;
        }

        $this->ok();
        return $json;
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|array<int,mixed>|string|null $body
     */
    public function request_text(
        string $method,
        string $path,
        array $query = [],
        array|string|null $body = null,
        string $body_type = 'none',
        bool $auth_required = true
    ): ?string {
        $resp = $this->request(
            $method,
            $path,
            $query,
            $body,
            $body_type,
            false,
            $auth_required
        );
        if (($resp['ok'] ?? false) !== true) {
            return null;
        }

        $raw = $resp['raw'] ?? null;
        if (!is_string($raw)) {
            $this->fail('Invalid text response');
            return null;
        }

        $this->ok();
        return $raw;
    }

    /**
     * POST /user/token/get
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function user_token_get(string $login = '', string $password = ''): array|null
    {
        $login = trim($login) !== '' ? trim($login) : trim($this->api_login);
        $password = trim($password) !== '' ? trim($password) : trim($this->api_password);

        if ($login === '' || $password === '') {
            $this->fail('login/password is empty');
            return null;
        }

        $resp = $this->request_json(
            'POST',
            '/user/token/get',
            [],
            ['login' => $login, 'password' => $password],
            'form',
            false
        );
        if ($resp === null) {
            return null;
        }

        $token = trim((string)($resp['token'] ?? ''));
        if ($token !== '') {
            $this->api_key = $token;
        }

        $this->ok();
        return $resp;
    }

    /**
     * GET /user/balance
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function user_balance(): array|null
    {
        return $this->request_json('GET', '/user/balance');
    }

    /**
     * POST /sub-user/allowed-ips/add
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_allowed_ips_add(int|string $subuser_id, string $ip): array|null
    {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        $ip = trim($ip);
        if ($subuser_id === null) {
            return null;
        }
        if (!$this->validate_ip($ip)) {
            return null;
        }

        return $this->request_json('POST', '/sub-user/allowed-ips/add', [], [
            'subuser_id' => $subuser_id,
            'ip' => $ip,
        ], 'json');
    }

    /**
     * POST /sub-user/allowed-ips/remove
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_allowed_ips_remove(int|string $subuser_id, string $ip): array|null
    {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        $ip = trim($ip);
        if ($subuser_id === null) {
            return null;
        }
        if (!$this->validate_ip($ip)) {
            return null;
        }

        return $this->request_json('POST', '/sub-user/allowed-ips/remove', [], [
            'subuser_id' => $subuser_id,
            'ip' => $ip,
        ], 'json');
    }

    /**
     * GET /sub-user/balance/get
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_balance_get(int|string $subuser_id): array|null
    {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        if ($subuser_id === null) {
            return null;
        }
        return $this->request_json('GET', '/sub-user/balance/get', ['subuser_id' => $subuser_id]);
    }

    /**
     * POST /sub-user/balance/add
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_balance_add(int|string $subuser_id, int|float $traffic): array|null
    {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        if ($subuser_id === null) {
            return null;
        }
        if ($traffic <= 0) {
            $this->fail('traffic must be > 0');
            return null;
        }

        return $this->request_json('POST', '/sub-user/balance/add', [], [
            'subuser_id' => $subuser_id,
            'traffic' => $traffic,
        ], 'json');
    }

    /**
     * POST /sub-user/balance/drop
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_balance_drop(int|string $subuser_id): array|null
    {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        if ($subuser_id === null) {
            return null;
        }
        return $this->request_json('POST', '/sub-user/balance/drop', [], [
            'subuser_id' => $subuser_id,
        ], 'json');
    }

    /**
     * GET /sub-user/balance/addition-history
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_balance_addition_history(int|string $subuser_id): array|null
    {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        if ($subuser_id === null) {
            return null;
        }
        return $this->request_json('GET', '/sub-user/balance/addition-history', ['subuser_id' => $subuser_id]);
    }

    /**
     * GET /sub-user/usage-stat/get
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_usage_stat_get(int|string $subuser_id, string $period = 'week'): array|null
    {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        $period = $this->normalize_period($period);
        if ($subuser_id === null || $period === null) {
            return null;
        }

        return $this->request_json('GET', '/sub-user/usage-stat/get', [
            'subuser_id' => $subuser_id,
            'period' => $period,
        ]);
    }

    /**
     * GET /sub-user/usage-stat/detail
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_usage_stat_detail(
        int|string $subuser_id,
        string $period = 'month',
        int $limit = 100,
        int $offset = 0,
        string $datetime_aggregate = 'minute'
    ): array|null {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        $period = $this->normalize_period($period);
        $datetime_aggregate = $this->normalize_datetime_aggregate($datetime_aggregate);
        if ($subuser_id === null || $period === null || $datetime_aggregate === null) {
            return null;
        }
        if ($limit <= 0) {
            $this->fail('limit must be > 0');
            return null;
        }
        if ($offset < 0) {
            $this->fail('offset must be >= 0');
            return null;
        }

        return $this->request_json('GET', '/sub-user/usage-stat/detail', [
            'subuser_id' => $subuser_id,
            'period' => $period,
            'limit' => $limit,
            'offset' => $offset,
            'datetime_aggregate' => $datetime_aggregate,
        ]);
    }

    /**
     * GET /sub-user/usage-stat/errors
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_usage_stat_errors(
        int|string $subuser_id,
        string $period = 'month',
        int $limit = 100,
        int $offset = 0,
        string $datetime_aggregate = 'minute'
    ): array|null {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        $period = $this->normalize_period($period);
        $datetime_aggregate = $this->normalize_datetime_aggregate($datetime_aggregate);
        if ($subuser_id === null || $period === null || $datetime_aggregate === null) {
            return null;
        }
        if ($limit <= 0) {
            $this->fail('limit must be > 0');
            return null;
        }
        if ($offset < 0) {
            $this->fail('offset must be >= 0');
            return null;
        }

        return $this->request_json('GET', '/sub-user/usage-stat/errors', [
            'subuser_id' => $subuser_id,
            'period' => $period,
            'limit' => $limit,
            'offset' => $offset,
            'datetime_aggregate' => $datetime_aggregate,
        ]);
    }

    /**
     * GET /sub-user/supported-protocols/get
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_supported_protocols_get(int|string $subuser_id): array|null
    {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        if ($subuser_id === null) {
            return null;
        }
        return $this->request_json('GET', '/sub-user/supported-protocols/get', ['subuser_id' => $subuser_id]);
    }

    /**
     * POST /sub-user/supported-protocols/set
     *
     * @param array<int,string>|null $supported_protocols
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_supported_protocols_set(int|string $subuser_id, ?array $supported_protocols): array|null
    {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        if ($subuser_id === null) {
            return null;
        }

        if ($supported_protocols !== null) {
            $normalized = [];
            foreach ($supported_protocols as $protocol) {
                $protocol = strtolower(trim((string)$protocol));
                if (!isset(self::SUPPORTED_PROTOCOLS[$protocol])) {
                    $this->fail('supported_protocols must contain only http|socks5');
                    return null;
                }
                $normalized[$protocol] = true;
            }
            $supported_protocols = array_keys($normalized);
        }

        return $this->request_json('POST', '/sub-user/supported-protocols/set', [], [
            'subuser_id' => $subuser_id,
            'supported_protocols' => $supported_protocols,
        ], 'json');
    }

    /**
     * GET /sub-user/list
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_list(int $limit = 100, int $offset = 0): array|null
    {
        if ($limit <= 0 || $limit > 1000) {
            $this->fail('limit must be in range 1..1000');
            return null;
        }
        if ($offset < 0) {
            $this->fail('offset must be >= 0');
            return null;
        }

        return $this->request_json('GET', '/sub-user/list', [
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * POST /sub-user/create
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_create(array $payload): array|null
    {
        $payload = $this->normalize_sub_user_create_payload($payload);
        if ($payload === null) {
            return null;
        }
        return $this->request_json('POST', '/sub-user/create', [], $payload, 'json');
    }

    /**
     * POST /sub-user/update
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_update(array $payload): array|null
    {
        $subuser_id = $this->normalize_subuser_id($payload['subuser_id'] ?? null);
        if ($subuser_id === null) {
            return null;
        }
        $payload['subuser_id'] = $subuser_id;

        if (isset($payload['sticky_range'])) {
            if (!is_array($payload['sticky_range'])) {
                $this->fail('sticky_range must be object');
                return null;
            }
            $payload['sticky_range'] = $this->normalize_sticky_range($payload['sticky_range']);
            if ($payload['sticky_range'] === null) {
                return null;
            }
        }

        if (isset($payload['threads'])) {
            $threads = (int)$payload['threads'];
            if ($threads < 1 || $threads > 2000) {
                $this->fail('threads must be in range 1..2000');
                return null;
            }
            $payload['threads'] = $threads;
        }

        return $this->request_json('POST', '/sub-user/update', [], $payload, 'json');
    }

    /**
     * POST /sub-user/delete
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_delete(int|string $subuser_id): array|null
    {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        if ($subuser_id === null) {
            return null;
        }
        return $this->request_json('POST', '/sub-user/delete', [], [
            'subuser_id' => $subuser_id,
        ], 'json');
    }

    /**
     * GET /sub-user/get
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_get(int|string $subuser_id): array|null
    {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        if ($subuser_id === null) {
            return null;
        }
        return $this->request_json('GET', '/sub-user/get', ['subuser_id' => $subuser_id]);
    }

    /**
     * POST /sub-user/reset-password
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_reset_password(int|string $subuser_id): array|null
    {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        if ($subuser_id === null) {
            return null;
        }
        return $this->request_json('POST', '/sub-user/reset-password', [], [
            'subuser_id' => $subuser_id,
        ], 'json');
    }

    /**
     * POST /sub-user/set-blocked
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_set_blocked(int|string $subuser_id, bool $blocked): array|null
    {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        if ($subuser_id === null) {
            return null;
        }
        return $this->request_json('POST', '/sub-user/set-blocked', [], [
            'subuser_id' => $subuser_id,
            'blocked' => $blocked,
        ], 'json');
    }

    /**
     * POST /sub-user/set-blocked-hosts
     *
     * @param array<int,string> $blocked_hosts
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_set_blocked_hosts(int|string $subuser_id, array $blocked_hosts): array|null
    {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        if ($subuser_id === null) {
            return null;
        }

        $blocked_hosts = $this->normalize_string_array($blocked_hosts, 'blocked_hosts');
        if ($blocked_hosts === null || $blocked_hosts === []) {
            $this->fail('blocked_hosts is empty');
            return null;
        }

        return $this->request_json('POST', '/sub-user/set-blocked-hosts', [], [
            'subuser_id' => $subuser_id,
            'blocked_hosts' => $blocked_hosts,
        ], 'json');
    }

    /**
     * POST /sub-user/set-default-pool-parameters
     *
     * @param array<string,mixed> $default_pool_parameters
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function sub_user_set_default_pool_parameters(int|string $subuser_id, array $default_pool_parameters): array|null
    {
        $subuser_id = $this->normalize_subuser_id($subuser_id);
        if ($subuser_id === null) {
            return null;
        }
        $default_pool_parameters = $this->normalize_default_pool_parameters($default_pool_parameters);
        if ($default_pool_parameters === null) {
            return null;
        }

        return $this->request_json('POST', '/sub-user/set-default-pool-parameters', [], [
            'subuser_id' => $subuser_id,
            'default_pool_parameters' => $default_pool_parameters,
        ], 'json');
    }

    /**
     * POST /common/locations/countries
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function locations_countries(string $pool_type, string $order_by = ''): array|null
    {
        $pool_type = $this->normalize_pool_type($pool_type);
        if ($pool_type === null) {
            return null;
        }

        $body = ['pool_type' => $pool_type];
        if ($order_by !== '') {
            $order_by = $this->normalize_order_by($order_by);
            if ($order_by === null) {
                return null;
            }
            $body['order_by'] = $order_by;
        }

        return $this->request_json('POST', '/common/locations/countries', [], $body, 'json');
    }

    /**
     * POST /common/locations/states
     *
     * @param array<int,string> $countries
     * @param array<string,mixed> $filters
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function locations_states(string $pool_type, array $countries, array $filters = []): array|null
    {
        return $this->location_filtered_request('/common/locations/states', $pool_type, $countries, $filters, [
            'cities' => true,
            'zipcodes' => true,
            'asns' => true,
        ]);
    }

    /**
     * POST /common/locations/cities
     *
     * @param array<int,string> $countries
     * @param array<string,mixed> $filters
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function locations_cities(string $pool_type, array $countries, array $filters = []): array|null
    {
        return $this->location_filtered_request('/common/locations/cities', $pool_type, $countries, $filters, [
            'states' => true,
            'zipcodes' => true,
            'asns' => true,
        ]);
    }

    /**
     * POST /common/locations/zipcodes
     *
     * @param array<int,string> $countries
     * @param array<string,mixed> $filters
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function locations_zipcodes(string $pool_type, array $countries, array $filters = []): array|null
    {
        return $this->location_filtered_request('/common/locations/zipcodes', $pool_type, $countries, $filters, [
            'states' => true,
            'asns' => true,
        ]);
    }

    /**
     * POST /common/locations/asns
     *
     * @param array<int,string> $countries
     * @param array<string,mixed> $filters
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function locations_asns(string $pool_type, array $countries, array $filters = []): array|null
    {
        return $this->location_filtered_request('/common/locations/asns', $pool_type, $countries, $filters, [
            'states' => true,
            'cities' => true,
            'zipcodes' => true,
        ]);
    }

    /**
     * GET /common/locations
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function locations(string $pool_type): array|null
    {
        $pool_type = $this->normalize_pool_type($pool_type);
        if ($pool_type === null) {
            return null;
        }
        return $this->request_json('GET', '/common/locations', ['pool_type' => $pool_type]);
    }

    /**
     * GET /common/pool_stats
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function pool_stats(string $pool_type): array|null
    {
        $pool_type = $this->normalize_pool_type($pool_type);
        if ($pool_type === null) {
            return null;
        }
        return $this->request_json('GET', '/common/pool_stats', ['pool_type' => $pool_type]);
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|array<int,mixed>|string|null $body
     * @return array<string,mixed>
     */
    private function request(
        string $method,
        string $path,
        array $query = [],
        array|string|null $body = null,
        string $body_type = 'none',
        bool $expect_json = true,
        bool $auth_required = true,
        bool $allow_empty_json = false
    ): array {
        $this->fail('');
        $this->last_http_code = 0;
        $this->last_url = '';

        $method = strtoupper(trim($method));
        if ($method === '') {
            $this->fail('HTTP method is empty');
            return ['ok' => false];
        }

        $path = '/' . ltrim(trim($path), '/');
        $body_type = strtolower(trim($body_type));
        $allowed_body_types = ['none' => true, 'json' => true, 'form' => true];
        if (!isset($allowed_body_types[$body_type])) {
            $this->fail('Invalid body_type, expected none|json|form');
            return ['ok' => false];
        }

        if ($auth_required && !$this->has_api_key()) {
            return ['ok' => false];
        }

        $url = rtrim($this->base_url, '/') . $path;
        if ($query !== []) {
            $qs = http_build_query($query);
            if ($qs !== '') {
                $url .= '?' . $qs;
            }
        }
        $this->last_url = $url;

        $headers = ['Accept: application/json'];
        if ($auth_required) {
            $headers[] = 'Authorization: Bearer ' . $this->api_key;
        }

        $payload = null;
        if ($body_type === 'json') {
            $jsonBody = json_encode(
                $body,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
            if (!is_string($jsonBody)) {
                $this->fail('Failed to encode request body');
                return ['ok' => false];
            }
            $payload = $jsonBody;
            $headers[] = 'Content-Type: application/json';
        } elseif ($body_type === 'form') {
            if (is_array($body)) {
                $payload = http_build_query($body);
            } elseif (is_string($body)) {
                $payload = $body;
            } else {
                $payload = '';
            }
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        $ch = curl_init($url);
        if ($ch === false) {
            $this->fail('curl_init failed');
            return ['ok' => false];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->request_connect_timeout_seconds);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->request_timeout_seconds);

        if ($payload !== null && $body_type !== 'none') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $raw = curl_exec($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        $raw_text = is_string($raw) ? $raw : '';
        $this->last_http_code = $http_code;

        $this->push_debug([
            'ts' => date('c'),
            'method' => $method,
            'url' => $url,
            'http_code' => $http_code,
            'query' => $query,
            'request_body_type' => $body_type,
            'request_body' => $body,
            'request_payload' => $payload,
            'response_raw' => $raw_text,
            'curl_error' => $curl_error,
        ]);

        if ($curl_error !== '') {
            $this->fail('CURL error: ' . $curl_error);
            return ['ok' => false];
        }

        if ($http_code < 200 || $http_code >= 300) {
            $this->fail($this->build_http_error_message($http_code, $raw_text));
            return ['ok' => false];
        }

        if ($expect_json) {
            if (trim($raw_text) === '') {
                if ($allow_empty_json) {
                    $this->ok();
                    return [
                        'ok' => true,
                        'json' => [],
                        'raw' => $raw_text,
                        'http_code' => $http_code,
                    ];
                }
                $this->fail('Empty JSON response');
                return ['ok' => false];
            }

            $json = json_decode($raw_text, true);
            if (!is_array($json)) {
                $this->fail('Invalid JSON response');
                return ['ok' => false];
            }

            $this->ok();
            return [
                'ok' => true,
                'json' => $json,
                'raw' => $raw_text,
                'http_code' => $http_code,
            ];
        }

        $this->ok();
        return [
            'ok' => true,
            'raw' => $raw_text,
            'http_code' => $http_code,
        ];
    }

    /**
     * @param array<int,string> $countries
     * @param array<string,mixed> $filters
     * @param array<string,bool> $allowed_filter_keys
     * @return array<string,mixed>|array<int,mixed>|null
     */
    private function location_filtered_request(
        string $path,
        string $pool_type,
        array $countries,
        array $filters,
        array $allowed_filter_keys
    ): array|null {
        $pool_type = $this->normalize_pool_type($pool_type);
        if ($pool_type === null) {
            return null;
        }

        $countries = $this->normalize_country_codes($countries);
        if ($countries === null || $countries === []) {
            $this->fail('countries is empty');
            return null;
        }

        $body = [
            'pool_type' => $pool_type,
            'countries' => $countries,
        ];

        foreach ($allowed_filter_keys as $key => $_) {
            if (!array_key_exists($key, $filters)) {
                continue;
            }
            $value = $filters[$key];
            if (!is_array($value)) {
                $this->fail($key . ' must be array of strings');
                return null;
            }
            $normalized = $this->normalize_string_array($value, $key);
            if ($normalized === null) {
                return null;
            }
            if ($normalized !== []) {
                $body[$key] = $normalized;
            }
        }

        if (isset($filters['order_by'])) {
            $order_by_raw = trim((string)$filters['order_by']);
            if ($order_by_raw !== '') {
                $order_by = $this->normalize_order_by($order_by_raw);
                if ($order_by === null) {
                    return null;
                }
                $body['order_by'] = $order_by;
            }
        }

        return $this->request_json('POST', $path, [], $body, 'json');
    }

    private function normalize_proxy_protocol(string $protocol): ?string
    {
        $protocol = strtolower(trim($protocol));
        if ($protocol === 'https') {
            $protocol = 'http';
        }
        if (!isset(self::SUPPORTED_PROTOCOLS[$protocol])) {
            $this->fail('protocol must be http|https|socks5');
            return null;
        }
        return $protocol;
    }

    private function resolve_proxy_host(string $host = '', bool $use_ip_host = false): ?string
    {
        $host = trim($host);
        if ($host !== '') {
            return $host;
        }

        if ($use_ip_host) {
            $ip_host = trim($this->proxy_host_ip);
            if ($ip_host === '') {
                $this->fail('proxy_host_ip is empty');
                return null;
            }
            return $ip_host;
        }

        $dns_host = trim($this->proxy_host_dns);
        if ($dns_host === '') {
            $this->fail('proxy_host_dns is empty');
            return null;
        }
        return $dns_host;
    }

    private function resolve_proxy_port(string $protocol, bool $sticky = false, int $sticky_port = 0): ?int
    {
        if ($sticky) {
            $port = $sticky_port > 0 ? $sticky_port : $this->proxy_sticky_default_port;
            if ($port < self::STICKY_MIN_PORT || $port > self::STICKY_MAX_PORT) {
                $this->fail('sticky port must be in range 10000..20000');
                return null;
            }
            return $port;
        }

        if ($protocol === 'socks5') {
            return $this->proxy_rotating_socks5_port;
        }
        return $this->proxy_rotating_http_port;
    }

    /**
     * @return array<int,string>|false
     */
    private function normalize_proxy_values(mixed $raw, string $mode): array|false
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $values = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $values[] = (string)$item;
            }
        } else {
            $values = explode(',', (string)$raw);
        }

        $out = [];
        foreach ($values as $value) {
            $value = strtolower(trim($value));
            if ($value === '') {
                continue;
            }

            if ($mode === 'country_codes') {
                if (preg_match('/^[a-z]{2}$/', $value) !== 1) {
                    $this->fail('country code must be ISO2');
                    return false;
                }
            } elseif ($mode === 'geo_code') {
                $value = str_replace([' ', "\t"], '', $value);
                if ($value === '' || preg_match('/^[a-z0-9._-]+$/', $value) !== 1) {
                    $this->fail('geo value contains invalid characters');
                    return false;
                }
            } elseif ($mode === 'zip') {
                if (preg_match('/^[a-z0-9-]+$/', $value) !== 1) {
                    $this->fail('zip value contains invalid characters');
                    return false;
                }
            } elseif ($mode === 'asn') {
                if (preg_match('/^[a-z0-9_-]+$/', $value) !== 1) {
                    $this->fail('asn value contains invalid characters');
                    return false;
                }
            }

            $out[$value] = true;
        }

        return array_keys($out);
    }

    private function normalize_bool(mixed $value, string $field_name): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            if ($value === 1) {
                return true;
            }
            if ($value === 0) {
                return false;
            }
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'on') {
                return true;
            }
            if ($v === '0' || $v === 'false' || $v === 'no' || $v === 'off') {
                return false;
            }
        }

        $this->fail($field_name . ' must be bool');
        return null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    private function normalize_sub_user_create_payload(array $payload): ?array
    {
        if (isset($payload['label'])) {
            $payload['label'] = trim((string)$payload['label']);
            if ($payload['label'] === '') {
                unset($payload['label']);
            }
        }

        if (isset($payload['threads'])) {
            $threads = (int)$payload['threads'];
            if ($threads < 1 || $threads > 2000) {
                $this->fail('threads must be in range 1..2000');
                return null;
            }
            $payload['threads'] = $threads;
        }

        if (isset($payload['sticky_range'])) {
            if (!is_array($payload['sticky_range'])) {
                $this->fail('sticky_range must be object');
                return null;
            }
            $payload['sticky_range'] = $this->normalize_sticky_range($payload['sticky_range']);
            if ($payload['sticky_range'] === null) {
                return null;
            }
        }

        if (isset($payload['pool_type'])) {
            $pool_type = $this->normalize_pool_type((string)$payload['pool_type']);
            if ($pool_type === null) {
                return null;
            }
            $payload['pool_type'] = $pool_type;
        }

        if (isset($payload['allowed_ips'])) {
            if (!is_array($payload['allowed_ips'])) {
                $this->fail('allowed_ips must be array');
                return null;
            }

            $allowed_ips = [];
            foreach ($payload['allowed_ips'] as $ip) {
                $ip = trim((string)$ip);
                if ($ip === '') {
                    continue;
                }
                if (!$this->validate_ip($ip)) {
                    return null;
                }
                $allowed_ips[$ip] = true;
            }
            $payload['allowed_ips'] = array_keys($allowed_ips);

            if (count($payload['allowed_ips']) > 5) {
                $this->fail('allowed_ips max items is 5');
                return null;
            }
        }

        if (isset($payload['default_pool_parameters'])) {
            if (!is_array($payload['default_pool_parameters'])) {
                $this->fail('default_pool_parameters must be object');
                return null;
            }
            $normalized = $this->normalize_default_pool_parameters($payload['default_pool_parameters']);
            if ($normalized === null) {
                return null;
            }
            $payload['default_pool_parameters'] = $normalized;
        }

        if ($payload === []) {
            $this->fail('payload is empty');
            return null;
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $sticky_range
     * @return array<string,int>|null
     */
    private function normalize_sticky_range(array $sticky_range): ?array
    {
        $start = (int)($sticky_range['start'] ?? 0);
        $end = (int)($sticky_range['end'] ?? 0);
        if ($start < 10000 || $start > 30000 || $end < 10000 || $end > 30000) {
            $this->fail('sticky_range start/end must be in range 10000..30000');
            return null;
        }
        if ($end < $start) {
            $this->fail('sticky_range end must be >= start');
            return null;
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>|null
     */
    private function normalize_default_pool_parameters(array $params): ?array
    {
        $out = [];
        $arrayKeys = [
            'countries' => 'country_codes',
            'cities' => 'plain',
            'states' => 'plain',
            'zipcodes' => 'plain',
            'asns' => 'plain',
            'exclude_countries' => 'country_codes',
            'exclude_asn' => 'plain',
        ];

        foreach ($arrayKeys as $key => $mode) {
            if (!array_key_exists($key, $params)) {
                continue;
            }

            $value = $params[$key];
            if (!is_array($value)) {
                $this->fail($key . ' must be array');
                return null;
            }

            if ($mode === 'country_codes') {
                $normalized = $this->normalize_country_codes($value);
            } else {
                $normalized = $this->normalize_string_array($value, $key);
            }
            if ($normalized === null) {
                return null;
            }

            $out[$key] = $normalized;
        }

        if (isset($params['anonymous_filter'])) {
            if (!is_bool($params['anonymous_filter'])) {
                $this->fail('anonymous_filter must be bool');
                return null;
            }
            $out['anonymous_filter'] = $params['anonymous_filter'];
        }

        if (array_key_exists('rotation_interval', $params)) {
            if ($params['rotation_interval'] === null || $params['rotation_interval'] === '') {
                $out['rotation_interval'] = null;
            } else {
                $rotation_interval = (int)$params['rotation_interval'];
                if ($rotation_interval < 1 || $rotation_interval > 120) {
                    $this->fail('rotation_interval must be in range 1..120');
                    return null;
                }
                $out['rotation_interval'] = $rotation_interval;
            }
        }

        if (isset($out['countries']) && isset($out['exclude_countries']) && $out['countries'] !== [] && $out['exclude_countries'] !== []) {
            $this->fail('countries and exclude_countries cannot be set together');
            return null;
        }

        return $out;
    }

    private function normalize_subuser_id(mixed $subuser_id): ?int
    {
        $subuser_str = trim((string)$subuser_id);
        if ($subuser_str === '' || !ctype_digit($subuser_str)) {
            $this->fail('subuser_id must be positive integer');
            return null;
        }
        $subuser_int = (int)$subuser_str;
        if ($subuser_int <= 0) {
            $this->fail('subuser_id must be > 0');
            return null;
        }
        return $subuser_int;
    }

    private function normalize_pool_type(string $pool_type): ?string
    {
        $pool_type = strtolower(trim($pool_type));
        if ($pool_type === 'residental') {
            $pool_type = 'residential';
        }
        if (!isset(self::SUPPORTED_POOL_TYPES[$pool_type])) {
            $this->fail('pool_type must be datacenter|residential|mobile');
            return null;
        }
        return $pool_type;
    }

    private function normalize_period(string $period): ?string
    {
        $period = strtolower(trim($period));
        if (!isset(self::SUPPORTED_PERIODS[$period])) {
            $this->fail('period is invalid');
            return null;
        }
        return $period;
    }

    private function normalize_datetime_aggregate(string $datetime_aggregate): ?string
    {
        $datetime_aggregate = strtolower(trim($datetime_aggregate));
        if (!isset(self::SUPPORTED_DATETIME_AGGREGATES[$datetime_aggregate])) {
            $this->fail('datetime_aggregate must be minute|hour|day');
            return null;
        }
        return $datetime_aggregate;
    }

    /**
     * @param array<int,string> $countries
     * @return array<int,string>|null
     */
    private function normalize_country_codes(array $countries): ?array
    {
        $out = [];
        foreach ($countries as $country) {
            $country = strtolower(trim((string)$country));
            if ($country === '') {
                continue;
            }
            if (preg_match('/^[a-z]{2}$/', $country) !== 1) {
                $this->fail('countries must contain ISO-2 codes');
                return null;
            }
            $out[$country] = true;
        }
        return array_keys($out);
    }

    /**
     * @param array<int,mixed> $values
     * @return array<int,string>|null
     */
    private function normalize_string_array(array $values, string $field_name): ?array
    {
        $out = [];
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value === '') {
                continue;
            }
            $out[$value] = true;
        }

        if ($out === [] && $values !== []) {
            $this->fail($field_name . ' contains only empty values');
            return null;
        }

        return array_keys($out);
    }

    private function normalize_order_by(string $order_by): ?string
    {
        $order_by = strtolower(trim($order_by));
        if ($order_by === '') {
            return null;
        }

        $parts = array_map('trim', explode(',', $order_by, 2));
        if (count($parts) !== 2) {
            $this->fail('order_by must be in format field,order');
            return null;
        }

        $field = $parts[0];
        $order = $parts[1];
        $allowed_fields = ['code' => true, 'name' => true, 'count' => true];
        $allowed_orders = ['asc' => true, 'desc' => true];
        if (!isset($allowed_fields[$field]) || !isset($allowed_orders[$order])) {
            $this->fail('order_by supports only code|name|count and asc|desc');
            return null;
        }

        return $field . ',' . $order;
    }

    private function validate_ip(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $this->fail('ip is invalid');
            return false;
        }
        return true;
    }

    private function has_api_key(): bool
    {
        if (trim($this->api_key) === '') {
            $this->fail('api_key is empty');
            return false;
        }
        return true;
    }

    private function build_http_error_message(int $http_code, string $raw_body): string
    {
        $suffix = '';
        $known = self::PROXY_HTTP_ERRORS[$http_code] ?? '';
        if ($known !== '') {
            $suffix = ' - ' . $known;
        }
        $decoded = json_decode($raw_body, true);
        if (is_array($decoded)) {
            $message = trim((string)($decoded['message'] ?? $decoded['error'] ?? $decoded['detail'] ?? ''));
            if ($message !== '') {
                $suffix .= ($suffix !== '' ? ' | ' : ' - ') . $message;
            }
        } else {
            $raw_body = trim($raw_body);
            if ($raw_body !== '') {
                if (strlen($raw_body) > 300) {
                    $raw_body = substr($raw_body, 0, 300) . '...';
                }
                $suffix .= ($suffix !== '' ? ' | ' : ' - ') . $raw_body;
            }
        }

        return 'HTTP error: ' . $http_code . $suffix;
    }

    /**
     * @param array<string,mixed> $entry
     */
    private function push_debug(array $entry): void
    {
        $this->debug = $entry;
        if (!$this->debug_enabled) {
            return;
        }
        $this->debug_history[] = $entry;
        if (count($this->debug_history) > 200) {
            array_splice($this->debug_history, 0, count($this->debug_history) - 200);
        }
    }

    private function ok(): void
    {
        $this->status = true;
        $this->error = '';
    }

    private function fail(string $msg): void
    {
        $this->status = false;
        $this->error = $msg;
    }
}
