<?php
declare(strict_types=1);

final class APIInfaticaIo_transport
{
    public bool $status = false;
    public string $error = '';

    public string $api_key = 'С‚СѓС‚ Р°РїРё РєР»СЋС‡';
    public string $api_key_residential = '';
    public string $api_key_mobile = '';
    public string $api_key_isp = '';
    public string $api_key_dc = '';
    public string $base_url = 'https://api.infatica.io';
    public string $client_api_base_url = 'https://dashboard.infatica.io/includes/api/client';
    public string $scraper_api_base_url = 'https://scrape.infatica.io';
    public string $client_email = '';
    public string $client_password = '';
    public string $scraper_api_key = '';

    public bool $debug_enabled = true;
    public array $debug = [];
    public array $debug_history = [];

    public int $last_http_code = 0;
    public string $last_url = '';
    public string $last_err_code = '';
    public string $last_err_msg = '';
    public int $request_connect_timeout_seconds = 6;
    public int $request_timeout_seconds = 18;
    public string $shared_proxy_host = 'pool.infatica.io';
    public int $shared_proxy_port = 10000;

    /** @var array<string,array<int,float>> pricing: type => [min_volume => price_per_unit] */
    public array $pricing = [];

    /** @var array<string,string> */
    private const SHARED_PROXY_ERR_CODES = [
        '503.0' => 'Unspecified error',
        '503.1.1' => 'Invalid user or password',
        '503.1.2' => 'Unable to get user',
        '503.2.1' => 'No traffic left',
        '503.3.1' => 'Target host denied',
        '503.4.1' => 'Target port denied',
        '503.5.1' => 'Node is offline',
        '503.6.1' => 'Connection is closed',
        '503.7.1' => 'Incorrect host name',
        '503.8.1' => 'Node has rejected the request',
        '503.9.1' => 'No exit node',
        '503.9.2' => 'Node is not connected',
        '503.9.3' => 'User session is empty',
        '503.9.4' => 'Empty session result',
        '503.9.5' => 'Unable to get node or node not connected',
        '503.9.6' => 'Unable to get node for required filters',
        '503.9.6a' => 'Unable to get node for required filters after session TTL expired',
        '503.9.6b' => 'Unable to get node for required filters after node bindTTL expired',
        '503.9.7' => 'Unable to get node for required filters as not selected or not connected',
        '503.9.8' => 'Empty next node for required filters',
        '503.9.8a' => 'Empty next node for required filters after session TTL expired',
        '503.9.8b' => 'Empty next node for required filters after node bindTTL expired',
        '503.9.9' => 'Node not assigned to port as not selected or not connected',
        '503.9.10' => 'Bad port index type 1',
        '503.9.11' => 'Bad port index type 2',
        '503.9.12' => 'Unable to assign node to port type 1',
        '503.9.13' => 'Unable to assign node to port type 2',
        '503.9.14' => 'Unable to assign node to port type 3',
        '503.9.15' => 'Unable to assign node to port as not match',
        '503.9.16' => 'Unable to get node from master',
        '504.1.1' => 'Node connect timed out',
    ];

    /** @var array<int,string> */
    private const SHARED_PROXY_HTTP_CODES = [
        400 => 'Bad Request',
        407 => 'Proxy Authentication Required',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    public function set_api_key(string $api_key): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $this->api_key = trim($api_key);
        $this->ok();
    }

    public function set_residential_api_key(string $api_key): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $this->api_key_residential = trim($api_key);
        $this->ok();
    }

    public function set_mobile_api_key(string $api_key): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $this->api_key_mobile = trim($api_key);
        $this->ok();
    }

    public function set_isp_api_key(string $api_key): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $this->api_key_isp = trim($api_key);
        $this->ok();
    }

    public function set_dc_api_key(string $api_key): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $this->api_key_dc = trim($api_key);
        $this->ok();
    }

    /**
     * @param array<string,array<int,float>> $pricing type => [min_volume => price_per_unit]
     */
    public function set_pricing(array $pricing): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $this->pricing = $pricing;
        $this->ok();
    }

    /**
     * @return array<string,array<int,float>>
     */
    public function retail_pricing(): array
    {
        return $this->normalize_pricing_matrix($this->pricing['retail'] ?? $this->pricing);
    }

    /**
     * @return array<string,array<int,float>>
     */
    public function cost_pricing(): array
    {
        return $this->normalize_pricing_matrix($this->pricing['cost'] ?? []);
    }

    /**
     * @return array<string,array<string,float|int>>
     */
    public function trial_retail_pricing(): array
    {
        return $this->normalize_trial_pricing($this->pricing['trial_retail'] ?? []);
    }

    /**
     * @return array<string,array<string,float|int>>
     */
    public function trial_cost_pricing(): array
    {
        return $this->normalize_trial_pricing($this->pricing['trial_cost'] ?? []);
    }

    public function set_api_key_by_type(string $proxy_type, string $api_key): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $proxy_type = $this->normalize_proxy_api_type($proxy_type);
        if ($proxy_type === '') {
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }

        $api_key = trim($api_key);
        if ($proxy_type === 'residential' || $proxy_type === 'residential_ipv6') {
            $this->api_key_residential = $api_key;
        } elseif ($proxy_type === 'mobile') {
            $this->api_key_mobile = $api_key;
        } elseif ($proxy_type === 'dc' || $proxy_type === 'dc_shared') {
            $this->api_key_dc = $api_key;
        } else {
            $this->api_key_isp = $api_key;
        }

        $this->ok();
    }

    public function set_client_auth(string $email, string $password): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $email = trim($email);
        $password = trim($password);
        if ($email === '' || $password === '') {
            $this->fail('client email/password is empty');
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }
        $this->client_email = $email;
        $this->client_password = $password;
        $this->ok();
    }

    public function set_client_base_url(string $base_url): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $base_url = trim($base_url);
        if ($base_url === '') {
            $this->fail('client base_url is empty');
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }
        $this->client_api_base_url = rtrim($base_url, '/');
        $this->ok();
    }

    public function set_scraper_api_key(string $api_key): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $api_key = trim($api_key);
        if ($api_key === '') {
            $this->fail('scraper_api_key is empty');
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }
        $this->scraper_api_key = $api_key;
        $this->ok();
    }

    public function set_scraper_base_url(string $base_url): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $base_url = trim($base_url);
        if ($base_url === '') {
            $this->fail('scraper base_url is empty');
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }
        $this->scraper_api_base_url = rtrim($base_url, '/');
        $this->ok();
    }

    public function set_base_url(string $base_url): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $base_url = trim($base_url);
        if ($base_url === '') {
            $this->fail('base_url is empty');
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }
        $this->base_url = rtrim($base_url, '/');
        $this->ok();
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function get(string $path, array $query = []): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->request_json('GET', $path, $query), __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $form
     * @return array<mixed>|null
     */
    public function post(string $path, array $form = []): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->request_json('POST', $path, [], $form === [] ? null : $form), __CLASS__, __FUNCTION__);
    }

    /** @return array<string,string> */
    public function shared_proxy_error_codes(): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return(self::SHARED_PROXY_ERR_CODES, __CLASS__, __FUNCTION__);
    }

    public function set_shared_proxy_host(string $host): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $host = trim($host);
        if ($host === '') {
            $this->fail('shared_proxy_host is empty');
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }
        $this->shared_proxy_host = $host;
        $this->ok();
    }

    public function set_shared_proxy_port(int $port): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if ($port < 10000 || $port > 10999) {
            $this->fail('shared_proxy_port must be in range 10000..10999');
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }
        $this->shared_proxy_port = $port;
        $this->ok();
    }

    /**
     * Build API-tool login with geo/session modifiers.
     *
     * @param array<string,mixed> $options
     */
    public function shared_proxy_login(string $base_login, array $options = []): ?string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $base_login = trim($base_login);
        if ($base_login === '') {
            $this->fail('base_login is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (preg_match('/\s/', $base_login)) {
            $this->fail('base_login must not contain spaces');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $parts = [$base_login];

        $country = strtoupper(trim((string)($options['country'] ?? '')));
        if ($country !== '') {
            if (!$this->is_iso2($country)) {
                $this->fail('country must be ISO2 code');
                return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }
            $parts[] = 'c';
            $parts[] = $country;
        }

        $subdivision = $this->normalize_positive_id($options['subdivision_id'] ?? null, 'subdivision_id');
        if ($subdivision === false) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (is_string($subdivision)) {
            $parts[] = 'sd';
            $parts[] = $subdivision;
        }

        $city = trim((string)($options['city'] ?? ''));
        if ($city !== '') {
            $city = preg_replace('/\s+/u', '-', $city);
            $city = trim((string)$city, '-');
            if ($city === '') {
                $this->fail('city is invalid');
                return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }
            $parts[] = 'city';
            $parts[] = $city;
        }

        $isp = $this->normalize_positive_id($options['isp_id'] ?? null, 'isp_id');
        if ($isp === false) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (is_string($isp)) {
            $parts[] = 'isp';
            $parts[] = $isp;
        }

        $asn = $this->normalize_positive_id($options['asn'] ?? null, 'asn');
        if ($asn === false) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (is_string($asn)) {
            $parts[] = 'asn';
            $parts[] = $asn;
        }

        $zip = trim((string)($options['zip'] ?? ''));
        if ($zip !== '') {
            if ($country === '') {
                $this->fail('zip requires country');
                return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }
            $parts[] = 'zip';
            $parts[] = $zip;
        }

        $session_id = trim((string)($options['session_id'] ?? ''));
        if ($session_id !== '') {
            if (!preg_match('/^[A-Za-z0-9]+$/', $session_id)) {
                $this->fail('session_id must be alphanumeric');
                return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }
            $parts[] = 's';
            $parts[] = $session_id;
        }

        $ttl = trim((string)($options['ttl'] ?? ''));
        if ($ttl !== '') {
            if ($session_id === '') {
                $this->fail('ttl requires session_id');
                return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }
            if (!preg_match('/^[1-9][0-9]*[smh]$/', $ttl)) {
                $this->fail('ttl must match ^[1-9][0-9]*[smh]$');
                return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }
            $parts[] = 'ttl';
            $parts[] = $ttl;
        }

        $this->ok();
        return Sogerien::Debager()->capture_return(implode('_', $parts), __CLASS__, __FUNCTION__);
    }

    public function shared_proxy_auth(string $login, string $password, ?int $port = null, string $host = ''): ?string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $login = trim($login);
        $password = trim($password);
        $host = trim($host) !== '' ? trim($host) : $this->shared_proxy_host;
        $resolvedPort = $port ?? $this->shared_proxy_port;
        if ($login === '' || $password === '') {
            $this->fail('login/password is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($host === '') {
            $this->fail('host is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($resolvedPort < 10000 || $resolvedPort > 10999) {
            $this->fail('port must be in range 10000..10999');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return Sogerien::Debager()->capture_return($login . ':' . $password . '@' . $host . ':' . (string)$resolvedPort, __CLASS__, __FUNCTION__);
    }

    public function shared_proxy_host_port(?int $port = null, string $host = ''): ?string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $host = trim($host) !== '' ? trim($host) : $this->shared_proxy_host;
        $resolvedPort = $port ?? $this->shared_proxy_port;
        if ($host === '') {
            $this->fail('host is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($resolvedPort < 10000 || $resolvedPort > 10999) {
            $this->fail('port must be in range 10000..10999');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return Sogerien::Debager()->capture_return($host . ':' . (string)$resolvedPort, __CLASS__, __FUNCTION__);
    }

    public function shared_proxy_http_url(string $login, string $password, ?int $port = null, string $host = ''): ?string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $auth = $this->shared_proxy_auth($login, $password, $port, $host);
        if ($auth === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return Sogerien::Debager()->capture_return('http://' . $auth, __CLASS__, __FUNCTION__);
    }

    public function shared_proxy_socks5_url(string $login, string $password, ?int $port = null, string $host = ''): ?string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $auth = $this->shared_proxy_auth($login, $password, $port, $host);
        if ($auth === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return Sogerien::Debager()->capture_return('socks5://' . $auth, __CLASS__, __FUNCTION__);
    }

    /**
     * HTTPS proxy URL format is supported by provider, but usually slower than HTTP/SOCKS5.
     */
    public function shared_proxy_https_url(string $login, string $password, ?int $port = null, string $host = ''): ?string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $auth = $this->shared_proxy_auth($login, $password, $port, $host);
        if ($auth === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return Sogerien::Debager()->capture_return('https://' . $auth, __CLASS__, __FUNCTION__);
    }

    /**
     * @return array<string,array<int,string>>
     */
    public function shared_proxy_gateway_locations(): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return([
            'NL' => ['Amsterdam'],
            'US' => ['New York', 'California'],
            'JP' => ['Tokyo'],
        ], __CLASS__, __FUNCTION__);
    }

    /**
     * Build login from options and return HTTP/SOCKS5/HTTPS URLs at once.
     *
     * @param array<string,mixed> $options
     * @return array<string,string>|null
     */
    public function shared_proxy_urls_from_options(
        string $base_login,
        string $password,
        array $options = [],
        ?int $port = null,
        string $host = ''
    ): ?array {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $login = $this->shared_proxy_login($base_login, $options);
        if ($login === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $http = $this->shared_proxy_http_url($login, $password, $port, $host);
        if ($http === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $socks5 = $this->shared_proxy_socks5_url($login, $password, $port, $host);
        if ($socks5 === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $https = $this->shared_proxy_https_url($login, $password, $port, $host);
        if ($https === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return Sogerien::Debager()->capture_return([
            'login' => $login,
            'proxy' => $login . ':' . $password . '@' . (trim($host) !== '' ? trim($host) : $this->shared_proxy_host) . ':' . (string)($port ?? $this->shared_proxy_port),
            'http' => $http,
            'socks5' => $socks5,
            'https' => $https,
        ], __CLASS__, __FUNCTION__);
    }

    /**
     * Build curl command with -x login:password@host:port.
     *
     * @param array<string,mixed> $options
     */
    public function shared_proxy_curl_command(
        string $target_url,
        string $base_login,
        string $password,
        array $options = [],
        ?int $port = null,
        string $host = ''
    ): ?string {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $target_url = trim($target_url);
        if ($target_url === '') {
            $this->fail('target_url is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $login = $this->shared_proxy_login($base_login, $options);
        if ($login === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $proxyAuth = $this->shared_proxy_auth($login, $password, $port, $host);
        if ($proxyAuth === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return Sogerien::Debager()->capture_return('curl -v -x ' . $proxyAuth . ' ' . $target_url, __CLASS__, __FUNCTION__);
    }

    /**
     * General shared proxy API-tool guidelines from docs.
     *
     * @return array<string,mixed>
     */
    public function shared_proxy_guidelines(): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return([
            'host' => $this->shared_proxy_host,
            'port_range' => ['from' => 10000, 'to' => 10999],
            'recommended_protocols' => ['http', 'socks5'],
            'supported_protocols' => ['http', 'socks5', 'https'],
            'https_note' => 'HTTPS is supported but slower in performance-sensitive tasks.',
            'geodns' => true,
            'api_tool_single_login_only' => true,
            'new_ip_per_request_without_session' => true,
            'session_inactive_ttl_minutes' => 60,
            'gateway_locations' => $this->shared_proxy_gateway_locations(),
            'ip_whitelist_priority' => 'IP whitelist rules override regular proxy lists and API tool logins.',
        ], __CLASS__, __FUNCTION__);
    }

    /**
     * Mobile plan specifics from docs.
     *
     * @return array<string,mixed>
     */
    public function mobile_proxy_guidelines(): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return([
            'plan' => 'mobile',
            'host' => $this->shared_proxy_host,
            'port_range' => ['from' => 10000, 'to' => 10999],
            'simultaneous_ports_per_list' => 100,
            'each_port_is_unique_ip' => true,
            'recommended_protocols' => ['http', 'socks5'],
            'supported_protocols' => ['http', 'socks5', 'https'],
            'trial_offer' => ['price_usd' => 8, 'traffic_gb' => 1, 'days' => 7],
            'ip_whitelist_priority' => true,
        ], __CLASS__, __FUNCTION__);
    }

    /**
     * Residential IPv6 plan specifics from docs.
     *
     * @return array<string,mixed>
     */
    public function residential_ipv6_proxy_guidelines(): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return([
            'plan' => 'residential_ipv6',
            'host' => $this->shared_proxy_host,
            'port_range' => ['from' => 10000, 'to' => 10999],
            'simultaneous_ports_per_list' => 1000,
            'each_port_is_unique_ip' => true,
            'recommended_protocols' => ['http', 'socks5'],
            'supported_protocols' => ['http', 'socks5', 'https'],
            'https_note' => 'HTTPS is supported but slower in performance-sensitive tasks.',
            'geodns' => true,
            'gateway_locations' => $this->shared_proxy_gateway_locations(),
            'trial_offer' => ['price_usd' => 6, 'traffic_gb' => 1, 'days' => 7],
            'ip_whitelist_priority' => true,
        ], __CLASS__, __FUNCTION__);
    }

    /**
     * Ethical Residential (IPv4) plan specifics from docs.
     *
     * @return array<string,mixed>
     */
    public function residential_proxy_guidelines(): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return([
            'plan' => 'residential',
            'host' => $this->shared_proxy_host,
            'port_range' => ['from' => 10000, 'to' => 10999],
            'simultaneous_ports_per_list' => 1000,
            'each_port_is_unique_ip' => true,
            'recommended_protocols' => ['http', 'socks5'],
            'supported_protocols' => ['http', 'socks5', 'https'],
            'https_note' => 'HTTPS is supported but slower in performance-sensitive tasks.',
            'geodns' => true,
            'gateway_locations' => $this->shared_proxy_gateway_locations(),
            'trial_offer' => ['price_usd' => 4, 'traffic_gb' => 1, 'days' => 7],
            'ip_whitelist_priority' => true,
        ], __CLASS__, __FUNCTION__);
    }

    /**
     * Alias for ethical residential wording from docs.
     *
     * @return array<string,mixed>
     */
    public function ethical_residential_proxy_guidelines(): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->residential_proxy_guidelines(), __CLASS__, __FUNCTION__);
    }

    /**
     * Unified access to proxy plan guidelines.
     *
     * Supported values: shared, mobile, residential, ethical_residential, residential_ipv6.
     *
     * @return array<string,mixed>|null
     */
    public function proxy_plan_guidelines(string $plan): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $plan = strtolower(trim($plan));
        if ($plan === '') {
            $this->fail('plan is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        if ($plan === 'shared' || $plan === 'datacenter' || $plan === 'shared_datacenter') {
            return Sogerien::Debager()->capture_return($this->shared_proxy_guidelines(), __CLASS__, __FUNCTION__);
        }
        if ($plan === 'mobile') {
            return Sogerien::Debager()->capture_return($this->mobile_proxy_guidelines(), __CLASS__, __FUNCTION__);
        }
        if ($plan === 'residential' || $plan === 'ethical_residential') {
            return Sogerien::Debager()->capture_return($this->residential_proxy_guidelines(), __CLASS__, __FUNCTION__);
        }
        if ($plan === 'residential_ipv6' || $plan === 'ipv6') {
            return Sogerien::Debager()->capture_return($this->residential_ipv6_proxy_guidelines(), __CLASS__, __FUNCTION__);
        }

        $this->fail('unsupported plan: ' . $plan);
        return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
    }

    /**
     * Universal HTTP request via proxy URL (e.g. http://login:password@host:port).
     *
     * @param array<int,string> $headers
     * @return array<string,mixed>|null
     */
    public function http_via_proxy(
        string $url,
        string $proxy_url,
        string $method = 'GET',
        string $body = '',
        array $headers = [],
        int $timeout = 45
    ): ?array {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $url = trim($url);
        $proxy_url = trim($proxy_url);
        $method = strtoupper(trim($method));

        if ($url === '') {
            $this->fail('url is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($proxy_url === '') {
            $this->fail('proxy_url is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($method === '') {
            $this->fail('method is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($timeout <= 0) {
            $timeout = 45;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            $this->fail('curl_init failed');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_PROXY, $proxy_url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HEADER, true);

        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($body !== '' && $method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $this->last_http_code = $httpCode;
        $responseRaw = is_string($raw) ? $raw : '';
        $rawHeaders = $headerSize > 0 ? substr($responseRaw, 0, $headerSize) : '';
        $rawBody = $headerSize > 0 ? substr($responseRaw, $headerSize) : $responseRaw;

        $this->push_debug([
            'ts' => date('c'),
            'api_scope' => 'proxy_request',
            'method' => $method,
            'url' => $url,
            'proxy_url' => $proxy_url,
            'http_code' => $httpCode,
            'request_body' => $body,
            'request_headers' => $headers,
            'response_headers_raw' => $rawHeaders,
            'response_raw' => $rawBody,
            'curl_error' => $curlErr,
        ]);

        if ($curlErr !== '') {
            $this->fail('CURL error: ' . $curlErr);
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $this->fail('HTTP error: ' . $httpCode);
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return Sogerien::Debager()->capture_return([
            'http_code' => $httpCode,
            'headers_raw' => $rawHeaders,
            'body' => $rawBody,
        ], __CLASS__, __FUNCTION__);
    }

    /**
     * Quick check: run request to ip-api.com via proxy and parse JSON.
     *
     * @return array<string,mixed>|null
     */
    public function proxy_check_ip_api(string $proxy_url, string $url = 'http://ip-api.com/json'): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $resp = $this->http_via_proxy($url, $proxy_url, 'GET');
        if (!is_array($resp)) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $body = (string)($resp['body'] ?? '');
        $json = json_decode($body, true);
        if (!is_array($json)) {
            $this->fail('Invalid JSON response from ip-api');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return Sogerien::Debager()->capture_return($json, __CLASS__, __FUNCTION__);
    }

    /**
     * UI presets map for quick reference.
     *
     * @return array<string,string>
     */
    public function proxy_list_location_presets(): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return([
            'world_mix' => 'World Mix',
            'europe' => 'Europe',
            'asia' => 'Asia',
            'north_america' => 'North America',
            'latin_america_caribbean' => 'Latin America and the Caribbean',
            'africa' => 'Africa',
            'oceania' => 'Oceania',
            'custom' => 'Custom location',
        ], __CLASS__, __FUNCTION__);
    }

    /**
     * @return array<string,int>
     */
    public function proxy_list_rotation_modes(): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return([
            'instant' => 0,
            '5_seconds' => 1,
            'no_rotation' => 2,
        ], __CLASS__, __FUNCTION__);
    }

    /**
     * @return array<int,string>
     */
    public function proxy_list_formats(): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return([
            1 => 'login:password@host:port',
            2 => 'host,port,login,password',
            3 => 'http://login:password@host:port',
            4 => 'socks5://login:password@host:port',
            5 => 'login:password:host:port',
            6 => 'host:port:login:password',
            7 => 'login@password@host@port',
        ], __CLASS__, __FUNCTION__);
    }

    /**
     * Build generate/update form payload from readable options.
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>|null
     */
    public function build_proxy_list_payload(array $options): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $name = trim((string)($options['name'] ?? $options['list_name'] ?? ''));
        if ($name === '') {
            $this->fail('name is required');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $auth_mode = strtolower(trim((string)($options['auth_mode'] ?? 'login_password')));
        if ($auth_mode !== 'login_password' && $auth_mode !== 'ip_whitelist') {
            $this->fail('auth_mode must be login_password or ip_whitelist');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $form = [
            'proxy-list-name' => $name,
        ];

        $login = trim((string)($options['login'] ?? $options['proxy_list_login'] ?? ''));
        $password = trim((string)($options['password'] ?? $options['proxy_list_password'] ?? ''));
        $network = trim((string)($options['network'] ?? $options['whitelist'] ?? $options['proxy_list_network'] ?? ''));

        if ($auth_mode === 'login_password') {
            if ($login === '') {
                $this->fail('login is required for login_password auth_mode');
                return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }
            $form['proxy-list-login'] = $login;
            if ($password !== '') {
                $form['proxy-list-password'] = $password;
            }
            if ($network !== '') {
                $form['proxy-list-network'] = $network;
            }
        } else {
            if ($network === '') {
                $this->fail('network/whitelist is required for ip_whitelist auth_mode');
                return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }
            $form['proxy-list-network'] = $network;
            if ($login !== '') {
                $form['proxy-list-login'] = $login;
            }
            if ($password !== '') {
                $form['proxy-list-password'] = $password;
            }
        }

        $countryRaw = $options['country'] ?? $options['countries'] ?? null;
        if ($countryRaw !== null && $countryRaw !== '') {
            $countryValue = $this->normalize_proxy_country($countryRaw);
            if ($countryValue === null) {
                return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }
            $form['proxy-list-country'] = $countryValue;
        }

        $region = trim((string)($options['region'] ?? ''));
        if ($region !== '') {
            $form['proxy-list-region'] = $region;
        }
        $city = trim((string)($options['city'] ?? ''));
        if ($city !== '') {
            $form['proxy-list-city'] = $city;
        }

        $ispId = $this->normalize_positive_id($options['isp_id'] ?? null, 'isp_id');
        if ($ispId === false) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (is_string($ispId)) {
            $form['proxy-list-isp'] = $ispId;
        } else {
            $isp = trim((string)($options['isp'] ?? ''));
            if ($isp !== '') {
                $form['proxy-list-isp'] = $isp;
            }
        }

        $zip = trim((string)($options['zip'] ?? ''));
        if ($zip !== '') {
            $countryCurrent = $form['proxy-list-country'] ?? null;
            if (!is_string($countryCurrent)) {
                $this->fail('zip requires single country code');
                return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }
            $form['proxy-list-zip'] = $zip;
        }

        $rotationPeriod = $this->normalize_rotation_period($options['rotation_period'] ?? null);
        if ($rotationPeriod === false) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($rotationPeriod !== null) {
            $form['proxy-list-rotation-period'] = $rotationPeriod;
        }

        $rotationMode = $this->normalize_rotation_mode($options['rotation_mode'] ?? null);
        if ($rotationMode === false) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($rotationMode !== null) {
            $form['proxy-list-rotation-mode'] = $rotationMode;
        }

        $format = $this->normalize_proxy_format($options['format'] ?? null, (string)($options['protocol'] ?? ''));
        if ($format === false) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($format !== null) {
            $form['proxy-list-format'] = $format;
        }

        $this->ok();
        return Sogerien::Debager()->capture_return($form, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $options
     * @return array<mixed>|null
     */
    public function package_generate_from_options(string $package_key, array $options): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $form = $this->build_proxy_list_payload($options);
        if ($form === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->package_generate($package_key, $form), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function client_get_traffic(int|string $pid, string $login = ''): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $pid_str = $this->normalize_client_pid($pid);
        if ($pid_str === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $body = ['pid' => $pid_str];
        $login = trim($login);
        if ($login !== '') {
            $body['login'] = $login;
        }
        return Sogerien::Debager()->capture_return($this->client_request_json('get_traffic.php', $body), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function client_remaining_traffic(int|string $pid): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $pid_str = $this->normalize_client_pid($pid);
        if ($pid_str === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->client_request_json('remaining_traffic.php', ['pid' => $pid_str]), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function client_get_balance(): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->client_request_json('get_balance.php', []), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function client_count_nodes(bool $mobile = false, bool $dc = false): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $flags = $this->build_client_proxy_type_flags($mobile, $dc);
        if ($flags === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->client_request_json('count_nodes.php', $flags), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function client_geo_nodes(bool $mobile = false, bool $dc = false, bool $v6 = false): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $flags = $this->build_client_proxy_type_flags($mobile, $dc, $v6);
        if ($flags === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->client_request_json('geo_nodes.php', $flags), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function client_day_online(bool $mobile = false, bool $dc = false): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $flags = $this->build_client_proxy_type_flags($mobile, $dc);
        if ($flags === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->client_request_json('day_online.php', $flags), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function client_isp_codes(): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->client_request_json('isp_codes.php', []), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function client_zip_codes(string $country = ''): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $country = strtoupper(trim($country));
        if ($country !== '' && !$this->is_iso2($country)) {
            $this->fail('country must be ISO2 code');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $body = $country === '' ? [] : ['country' => $country];
        return Sogerien::Debager()->capture_return($this->client_request_json('zip-codes.php', $body), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function client_subdivision_codes(): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->client_request_json('subdivision_codes.php', []), __CLASS__, __FUNCTION__);
    }

    /**
     * Alias for project pages: unified proxies list for residential/mobile/isp keys.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxiesList(array $params = []): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $type = strtolower(trim((string)($params['type'] ?? '')));
        $limit = (int)($params['limit'] ?? 100);
        $offset = (int)($params['offset'] ?? 0);

        if ($limit <= 0) {
            $limit = 100;
        }
        if ($limit > 10000) {
            $limit = 10000;
        }
        if ($offset < 0) {
            $offset = 0;
        }

        $types = $this->collect_proxy_list_types($type);
        if ($types === []) {
            return Sogerien::Debager()->capture_return(['ok' => false, 'error' => $this->error], __CLASS__, __FUNCTION__);
        }

        $countryNameByCode = $this->load_country_name_map();
        $rows = [];
        $errors = [];
        foreach ($types as $proxy_type) {
            if ($proxy_type === 'isp') {
                $ispRows = $this->build_isp_country_rows($countryNameByCode);
                if (!is_array($ispRows)) {
                    $errors[] = $proxy_type . ': ' . $this->error;
                    continue;
                }
                foreach ($ispRows as $row) {
                    $rows[] = $row;
                }
                continue;
            }

            $geoRows = $this->build_geo_rows_by_type($proxy_type, $countryNameByCode);
            if ($geoRows === null) {
                $errors[] = $proxy_type . ': ' . $this->error;
                continue;
            }
            if ($geoRows !== []) {
                foreach ($geoRows as $row) {
                    $rows[] = $row;
                }
                continue;
            }

            $resp = $this->request_json_by_proxy_api_type($proxy_type, 'GET', '/packages.php');
            if (!is_array($resp)) {
                $resp = $this->request_json_by_proxy_api_type($proxy_type, 'GET', '/packages');
            }
            if (!is_array($resp)) {
                $errors[] = $proxy_type . ': ' . $this->error;
            } else {
                $items = $this->extract_proxy_packages_items($resp);
                $index = 0;
                foreach ($items as $item) {
                    $rows[] = $this->flatten_proxy_package_item($item, $proxy_type, $index);
                    $index++;
                }
            }
        }

        $sourceCountTotal = count($rows);
        if ($sourceCountTotal === 0 && $errors !== []) {
            $this->fail('Failed to load Infatica proxy list: ' . implode(' | ', $errors));
            return Sogerien::Debager()->capture_return(['ok' => false, 'error' => $this->error], __CLASS__, __FUNCTION__);
        }

        $rows = $this->expand_rows_by_pricing($rows);
        if ($type === '' || $type === 'all') {
            $rows = $this->interleave_rows_by_column($rows, 'proxy_api_type');
        }
        $countTotal = count($rows);

        $rows = array_slice($rows, $offset, $limit);
        $columns = $this->collect_proxy_list_columns($rows);
        $filters = $this->collect_proxy_list_filters($rows, $columns);

        $this->ok();
        $out = [
            'ok' => true,
            'data' => [
                'columns' => $columns,
                'filters' => $filters,
                'rows' => $rows,
                'count' => count($rows),
                'count_total' => $countTotal,
                'source_count_total' => $sourceCountTotal,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'page' => (int)floor($offset / $limit) + 1,
                ],
            ],
        ];
        if ($errors !== []) {
            $out['warning'] = implode(' | ', $errors);
        }

        return Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
    }

    /**
     * @return array<int,string>
     */
    private function collect_proxy_list_types(string $type): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $type = strtolower(trim($type));
        if ($type === '' || $type === 'all') {
            return Sogerien::Debager()->capture_return(['residential', 'residential_ipv6', 'mobile', 'isp'], __CLASS__, __FUNCTION__);
        }

        $normalized = $this->normalize_proxy_api_type($type);
        if ($normalized === '') {
            return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return([$normalized], __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function expand_rows_by_pricing(array $rows): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $markupUsd = $this->resolve_pricing_markup_usd();
        $days = 364;
        $result = [];
        $trialAdded = [];

        foreach ($rows as $row) {
            $category = $this->normalize_proxy_api_type((string)($row['proxy_api_type'] ?? $row['proxy_category'] ?? ''));
            $tiers = $this->resolve_pricing_tiers_by_category($category);

            if ($tiers === []) {
                $base_row = $row;
                $base_row['days'] = (string)$days;
                $base_row['price_per_day'] = '';
                $base_row['price_per_gb'] = '';
                $base_row['traffic_gb'] = '';
                $base_row['price_usd'] = '';
                if (trim((string)($base_row['is_auto_renewal_possible'] ?? '')) === '') {
                    $base_row['is_auto_renewal_possible'] = '1';
                }
                $result[] = $base_row;
                continue;
            }

            $trialKey = $category . ':' . (string)($row['location_country_code'] ?? $row['key'] ?? $row['id'] ?? count($trialAdded));
            if (!isset($trialAdded[$trialKey])) {
                $trialRow = $this->build_trial_pricing_row($row, $category);
                if ($trialRow !== null) {
                    $result[] = $trialRow;
                    $trialAdded[$trialKey] = true;
                }
            }

            foreach ($tiers as $volume => $base_price) {
                $volume_int = (int)$volume;
                $provider_total_price = round(((float)$base_price) * (float)$volume_int, 2);
                $total_price = round($provider_total_price + $markupUsd, 2);
                $unit_price = round($total_price / (float)$volume_int, 4);
                $daily_price = round($total_price / (float)$days, 4);

                $new_row = $row;
                $new_row['proxy_api_type'] = $category;
                $new_row['days'] = (string)$days;
                $new_row['traffic_gb'] = (string)$volume_int;
                $new_row['provider_unit_price_usd'] = number_format((float)$base_price, 4, '.', '');
                $new_row['provider_cost_usd'] = number_format($provider_total_price, 2, '.', '');
                $new_row['markup_usd'] = number_format($markupUsd, 2, '.', '');
                $new_row['price_per_gb'] = number_format($unit_price, 4, '.', '');
                $new_row['price_usd'] = number_format($total_price, 2, '.', '');
                $new_row['price_per_day'] = number_format($daily_price, 4, '.', '');

                $id = trim((string)($new_row['id'] ?? ''));
                if ($id === '') {
                    $id = $category;
                }
                $new_row['id'] = $id . '-gb' . (string)$volume_int;

                if (trim((string)($new_row['is_auto_renewal_possible'] ?? '')) === '') {
                    $new_row['is_auto_renewal_possible'] = '1';
                }
                if (trim((string)($new_row['stock_status'] ?? '')) === '') {
                    $new_row['stock_status'] = 'in_stock';
                }

                $result[] = $new_row;
            }
        }

        return Sogerien::Debager()->capture_return($result, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function interleave_rows_by_column(array $rows, string $column): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $groups = [];
        $order = [];
        foreach ($rows as $row) {
            $key = trim((string)($row[$column] ?? ''));
            if ($key === '') {
                $key = '_empty';
            }
            if (!isset($groups[$key])) {
                $groups[$key] = [];
                $order[] = $key;
            }
            $groups[$key][] = $row;
        }

        if (count($groups) <= 1) {
            return Sogerien::Debager()->capture_return($rows, __CLASS__, __FUNCTION__);
        }

        $out = [];
        $indexes = array_fill_keys($order, 0);
        while (count($out) < count($rows)) {
            $added = false;
            foreach ($order as $key) {
                $index = (int)($indexes[$key] ?? 0);
                if (!isset($groups[$key][$index])) {
                    continue;
                }
                $out[] = $groups[$key][$index];
                $indexes[$key] = $index + 1;
                $added = true;
            }
            if (!$added) {
                break;
            }
        }

        return Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    private function build_trial_pricing_row(array $row, string $category): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $offer = $this->resolve_trial_offer_by_category($category);
        if ($offer === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $trafficGb = (int)$offer['traffic_gb'];
        $days = (int)$offer['days'];
        $priceUsd = (float)$offer['price_usd'];
        if ($trafficGb <= 0 || $days <= 0 || $priceUsd <= 0.0) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $trial = $row;
        $trial['proxy_api_type'] = $category;
        $trial['days'] = (string)$days;
        $trial['traffic_gb'] = (string)$trafficGb;
        $trial['provider_unit_price_usd'] = number_format($priceUsd / (float)$trafficGb, 4, '.', '');
        $trial['provider_cost_usd'] = number_format($priceUsd, 2, '.', '');
        $trial['markup_usd'] = '0.00';
        $trial['price_per_gb'] = number_format($priceUsd / (float)$trafficGb, 4, '.', '');
        $trial['price_usd'] = number_format($priceUsd, 2, '.', '');
        $trial['price_per_day'] = number_format($priceUsd / (float)$days, 4, '.', '');
        $trial['is_trial'] = '1';
        $trial['billing_period'] = 'trial';
        $trial['stock_status'] = trim((string)($trial['stock_status'] ?? '')) !== '' ? $trial['stock_status'] : 'in_stock';
        $trial['is_auto_renewal_possible'] = '0';

        $id = trim((string)($trial['id'] ?? ''));
        if ($id === '') {
            $id = $category;
        }
        $trial['id'] = $id . '-trial-gb' . (string)$trafficGb;

        return Sogerien::Debager()->capture_return($trial, __CLASS__, __FUNCTION__);
    }

    /**
     * @return array{price_usd:float,traffic_gb:int,days:int}|null
     */
    private function resolve_trial_offer_by_category(string $category): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $category = $this->normalize_proxy_api_type($category);
        $guidelines = null;
        if ($category === 'mobile') {
            $guidelines = $this->mobile_proxy_guidelines();
        } elseif ($category === 'residential') {
            $guidelines = $this->residential_proxy_guidelines();
        } elseif ($category === 'residential_ipv6') {
            $guidelines = $this->residential_ipv6_proxy_guidelines();
        }
        if (!is_array($guidelines) || !isset($guidelines['trial_offer']) || !is_array($guidelines['trial_offer'])) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $offer = $guidelines['trial_offer'];
        return Sogerien::Debager()->capture_return([
            'price_usd' => (float)($offer['price_usd'] ?? 0),
            'traffic_gb' => (int)($offer['traffic_gb'] ?? 0),
            'days' => (int)($offer['days'] ?? 0),
        ], __CLASS__, __FUNCTION__);
    }

    private function resolve_pricing_markup_usd(): float
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $markup = (float)($this->pricing['markupUsd'] ?? 50.0);
        if ($markup < 0.0) {
            $markup = 50.0;
        }
        return Sogerien::Debager()->capture_return($markup, __CLASS__, __FUNCTION__);
    }

    /**
     * @return array<int,float>
     */
    private function resolve_pricing_tiers_by_category(string $category): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $category = $this->normalize_proxy_api_type($category);
        $tiers = [];
        $pricing = $this->retail_pricing();
        if (isset($pricing[$category]) && is_array($pricing[$category])) {
            foreach ($pricing[$category] as $volume => $price) {
                if (!is_numeric((string)$volume) || !is_numeric((string)$price)) {
                    continue;
                }
                $v = (int)$volume;
                if ($v <= 0) {
                    continue;
                }
                $tiers[$v] = (float)$price;
            }
        }
        ksort($tiers, SORT_NUMERIC);
        return Sogerien::Debager()->capture_return($tiers, __CLASS__, __FUNCTION__);
    }

    /**
     * @param mixed $matrix
     * @return array<string,array<int,float>>
     */
    private function normalize_pricing_matrix(mixed $matrix): array
    {
        if (!is_array($matrix)) {
            return [];
        }

        $normalized = [];
        foreach ($matrix as $category => $tiers) {
            $category = $this->normalize_proxy_api_type((string)$category);
            if ($category === '' || !is_array($tiers)) {
                continue;
            }
            foreach ($tiers as $volume => $price) {
                if (!is_numeric((string)$volume) || !is_numeric((string)$price)) {
                    continue;
                }
                $volumeInt = (int)$volume;
                $priceFloat = (float)$price;
                if ($volumeInt <= 0 || $priceFloat <= 0.0) {
                    continue;
                }
                $normalized[$category][$volumeInt] = $priceFloat;
            }
            if (isset($normalized[$category])) {
                ksort($normalized[$category], SORT_NUMERIC);
            }
        }
        return $normalized;
    }

    /**
     * @param mixed $matrix
     * @return array<string,array<string,float|int>>
     */
    private function normalize_trial_pricing(mixed $matrix): array
    {
        if (!is_array($matrix)) {
            return [];
        }

        $normalized = [];
        foreach ($matrix as $category => $offer) {
            $category = $this->normalize_proxy_api_type((string)$category);
            if ($category === '' || !is_array($offer)) {
                continue;
            }
            $traffic = (int)($offer['traffic'] ?? $offer['traffic_gb'] ?? 0);
            $price = (float)($offer['price'] ?? $offer['price_usd'] ?? 0);
            $days = (int)($offer['days'] ?? 7);
            if ($traffic <= 0 || $price <= 0.0 || $days <= 0) {
                continue;
            }
            $normalized[$category] = ['traffic' => $traffic, 'price' => $price, 'days' => $days];
        }
        return $normalized;
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $body
     * @return array<mixed>|null
     */
    private function request_json_by_proxy_api_type(string $proxy_type, string $method, string $path, array $query = [], ?array $body = null): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $api_key = $this->resolve_api_key_for_proxy_type($proxy_type);
        if ($api_key === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $prev = $this->api_key;
        $this->api_key = $api_key;
        try {
            $resp = $this->request_json($method, $path, $query, $body);
        } finally {
            $this->api_key = $prev;
        }

        return Sogerien::Debager()->capture_return($resp, __CLASS__, __FUNCTION__);
    }

    /**
     * @return array<string,string>
     */
    private function load_country_name_map(): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $types = ['residential', 'mobile'];
        foreach ($types as $type) {
            $resp = $this->request_json_by_proxy_api_type($type, 'GET', '/countries.php');
            if (!is_array($resp)) {
                continue;
            }
            $map = $this->extract_country_name_map_from_response($resp);
            if ($map !== []) {
                return Sogerien::Debager()->capture_return($map, __CLASS__, __FUNCTION__);
            }
        }
        return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,string>
     */
    private function extract_country_name_map_from_response(array $source): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $candidates = [$source];
        if (isset($source['data']) && is_array($source['data'])) {
            $candidates[] = $source['data'];
        }
        if (isset($source['result']) && is_array($source['result'])) {
            $candidates[] = $source['result'];
        }
        if (isset($source['results']) && is_array($source['results'])) {
            $candidates[] = $source['results'];
        }

        foreach ($candidates as $candidate) {
            if (!is_array($candidate) || $this->is_list_array($candidate)) {
                continue;
            }

            $map = [];
            foreach ($candidate as $k => $v) {
                if (!is_string($k)) {
                    continue;
                }
                $code = strtoupper(trim($k));
                if (!$this->is_iso2($code)) {
                    continue;
                }
                $name = trim((string)$v);
                if ($name === '') {
                    continue;
                }
                $map[$code] = $name;
            }
            if ($map !== []) {
                return Sogerien::Debager()->capture_return($map, __CLASS__, __FUNCTION__);
            }
        }

        return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,string> $countryNameByCode
     * @return array<int,array<string,mixed>>|null
     */
    private function build_geo_rows_by_type(string $proxy_type, array $countryNameByCode): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $endpoint = $proxy_type === 'mobile' ? '/mobile-nodes-info.php' : '/nodes-info.php';
        $resp = $this->request_json_by_proxy_api_type($proxy_type, 'GET', $endpoint);
        if (!is_array($resp)) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $payload = $this->unwrap_assoc_payload($resp);
        if ($payload === [] || $this->is_list_array($payload)) {
            return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
        }

        $rows = [];
        foreach ($payload as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            $code = strtoupper(trim($k));
            if (!$this->is_iso2($code)) {
                continue;
            }

            $nodesCount = is_numeric((string)$v) ? (int)$v : 0;
            $name = trim((string)($countryNameByCode[$code] ?? $code));

            $rows[] = [
                'API' => 'InfaticaIo',
                'proxy_api_type' => $proxy_type,
                'id' => $proxy_type . '-' . $code,
                'key' => $proxy_type . '-' . $code,
                'title' => $name,
                'name' => $name,
                'location_country_code' => $code,
                'nodes_count' => (string)$nodesCount,
                'proxy_category' => $proxy_type,
                'stock_status' => $nodesCount > 0 ? 'in_stock' : 'out_of_stock',
                'access_type' => 'public',
            ];
        }

        usort(
            $rows,
            static function (array $a, array $b): int {
                $na = (int)($a['nodes_count'] ?? 0);
                $nb = (int)($b['nodes_count'] ?? 0);
                if ($na !== $nb) {
                    return $nb <=> $na;
                }
                return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
            }
        );

        return Sogerien::Debager()->capture_return($rows, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,string> $countryNameByCode
     * @return array<int,array<string,mixed>>|null
     */
    private function build_isp_country_rows(array $countryNameByCode): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $balanceResp = $this->request_json_by_proxy_api_type('isp', 'GET', '/isp/balance.php');
        if (!is_array($balanceResp)) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $balanceData = $this->unwrap_assoc_payload($balanceResp);

        $countriesResp = $this->request_json_by_proxy_api_type('isp', 'GET', '/isp/countries.php');
        $countries = [];
        if (is_array($countriesResp)) {
            $countries = $this->extract_scalar_list_from_response($countriesResp);
        }

        $priceRaw = trim((string)($balanceData['price for 1 IP'] ?? $balanceData['price_per_ip'] ?? $balanceData['price'] ?? ''));
        if ($priceRaw !== '' && is_numeric($priceRaw)) {
            $priceRaw = number_format((float)$priceRaw, 2, '.', '');
        }
        $lookup = [];
        foreach ($countryNameByCode as $code => $name) {
            $lookup[strtolower(trim($name))] = strtoupper(trim($code));
        }

        $rows = [];
        if ($countries !== []) {
            $idx = 1;
            foreach ($countries as $countryName) {
                $countryName = trim($countryName);
                if ($countryName === '') {
                    continue;
                }
                $code = $lookup[strtolower($countryName)] ?? '';
                $location = $code !== '' ? $code : strtoupper($countryName);
                $rows[] = [
                    'API' => 'InfaticaIo',
                    'proxy_api_type' => 'isp',
                    'id' => 'isp-' . ($code !== '' ? strtolower($code) : (string)$idx),
                    'key' => 'isp-' . ($code !== '' ? strtolower($code) : (string)$idx),
                    'title' => $countryName,
                    'name' => 'ISP',
                    'location_country_code' => $location,
                    'proxy_category' => 'isp',
                    'stock_status' => 'in_stock',
                    'access_type' => 'private',
                    'price_usd' => $priceRaw,
                ];
                $idx++;
            }
        }

        if ($rows === []) {
            $rows[] = [
                'API' => 'InfaticaIo',
                'proxy_api_type' => 'isp',
                'id' => 'isp-overview',
                'key' => 'isp-overview',
                'title' => 'ISP Overview',
                'name' => 'ISP',
                'proxy_category' => 'isp',
                'stock_status' => 'in_stock',
                'access_type' => 'private',
                'price_usd' => $priceRaw,
            ];
        }

        return Sogerien::Debager()->capture_return($rows, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private function unwrap_assoc_payload(array $source): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if (isset($source['data']) && is_array($source['data']) && !$this->is_list_array($source['data'])) {
            return Sogerien::Debager()->capture_return($source['data'], __CLASS__, __FUNCTION__);
        }
        if (isset($source['result']) && is_array($source['result']) && !$this->is_list_array($source['result'])) {
            return Sogerien::Debager()->capture_return($source['result'], __CLASS__, __FUNCTION__);
        }
        if (isset($source['results']) && is_array($source['results']) && !$this->is_list_array($source['results'])) {
            return Sogerien::Debager()->capture_return($source['results'], __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($source, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $source
     * @return array<int,string>
     */
    private function extract_scalar_list_from_response(array $source): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $candidates = [];
        if ($this->is_list_array($source)) {
            $candidates[] = $source;
        }
        if (isset($source['data']) && is_array($source['data']) && $this->is_list_array($source['data'])) {
            $candidates[] = $source['data'];
        }
        if (isset($source['results']) && is_array($source['results']) && $this->is_list_array($source['results'])) {
            $candidates[] = $source['results'];
        }
        if (isset($source['items']) && is_array($source['items']) && $this->is_list_array($source['items'])) {
            $candidates[] = $source['items'];
        }

        foreach ($candidates as $candidate) {
            $out = [];
            foreach ($candidate as $item) {
                if (!is_scalar($item) && $item !== null) {
                    $out = [];
                    break;
                }
                $value = trim((string)$item);
                if ($value !== '') {
                    $out[] = $value;
                }
            }
            if ($out !== []) {
                $out = array_values(array_unique($out));
                return Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
            }
        }

        return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $source
     * @return array<int,array<string,mixed>>
     */
    private function extract_proxy_packages_items(array $source): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $paths = [
            ['data', 'packages'],
            ['data', 'results'],
            ['data', 'items'],
            ['packages'],
            ['results'],
            ['items'],
            ['data'],
            [],
        ];

        foreach ($paths as $path) {
            $items = $this->extract_list_by_path($source, $path);
            if ($items !== []) {
                return Sogerien::Debager()->capture_return($items, __CLASS__, __FUNCTION__);
            }
        }

        if ($this->looks_like_proxy_package_item($source)) {
            return Sogerien::Debager()->capture_return([$source], __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $source
     * @param array<int,string> $path
     * @return array<int,array<string,mixed>>
     */
    private function extract_list_by_path(array $source, array $path): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $cursor = $source;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
            }
            $cursor = $cursor[$segment];
        }
        if (!is_array($cursor) || !$this->is_list_array($cursor)) {
            return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
        }

        $items = [];
        foreach ($cursor as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }
        return Sogerien::Debager()->capture_return($items, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function flatten_proxy_package_item(array $item, string $proxy_type, int $index): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $row = [
            'API' => 'InfaticaIo',
            'proxy_api_type' => $proxy_type,
        ];

        foreach ($item as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $key = trim($key);
            if ($key === '') {
                continue;
            }
            $row[$key] = $this->flatten_proxy_package_scalar($key, $value);
        }

        if (trim((string)($row['id'] ?? '')) === '') {
            $id = $this->first_non_empty_scalar($item, ['id', 'key', 'package_id', 'package_key', 'pid']);
            if ($id === '') {
                $id = $proxy_type . '-' . (string)($index + 1);
            }
            $row['id'] = $id;
        }

        if (trim((string)($row['title'] ?? '')) === '') {
            $title = $this->first_non_empty_scalar($item, ['title', 'name', 'package_name', 'key', 'login']);
            if ($title === '') {
                $title = ucfirst($proxy_type) . ' package ' . (string)($index + 1);
            }
            $row['title'] = $title;
        }

        if (trim((string)($row['proxy_category'] ?? '')) === '') {
            $row['proxy_category'] = $this->detect_proxy_category($item, $proxy_type);
        }

        if (trim((string)($row['location_country_code'] ?? '')) === '') {
            $country = $this->detect_country_codes($item);
            if ($country !== '') {
                $row['location_country_code'] = $country;
            }
        }

        if (trim((string)($row['stock_status'] ?? '')) === '') {
            $stock_status = $this->detect_stock_status($item);
            if ($stock_status !== '') {
                $row['stock_status'] = $stock_status;
            }
        }

        if (trim((string)($row['price_usd'] ?? '')) === '') {
            $price = $this->detect_price_usd($item);
            if ($price !== '') {
                $row['price_usd'] = $price;
            }
        }

        if (trim((string)($row['days'] ?? '')) === '') {
            $days = $this->detect_days($item);
            if ($days !== '') {
                $row['days'] = $days;
            }
        }

        if (trim((string)($row['access_type'] ?? '')) === '') {
            $access_type = $this->detect_access_type($item, $proxy_type);
            if ($access_type !== '') {
                $row['access_type'] = $access_type;
            }
        }

        return Sogerien::Debager()->capture_return($row, __CLASS__, __FUNCTION__);
    }

    private function flatten_proxy_package_scalar(string $key, mixed $value): mixed
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $key_lower = strtolower(trim($key));
        if (str_contains($key_lower, 'country')) {
            $normalized = $this->normalize_country_codes_value($value);
            if ($normalized !== '') {
                return Sogerien::Debager()->capture_return($normalized, __CLASS__, __FUNCTION__);
            }
        }

        if (is_scalar($value) || $value === null) {
            return Sogerien::Debager()->capture_return($value, __CLASS__, __FUNCTION__);
        }

        if (is_array($value)) {
            if ($this->is_list_array($value)) {
                $parts = [];
                $all_scalar = true;
                foreach ($value as $item) {
                    if (is_scalar($item) || $item === null) {
                        $part = trim((string)$item);
                        if ($part !== '') {
                            $parts[] = $part;
                        }
                        continue;
                    }
                    $all_scalar = false;
                    break;
                }
                if ($all_scalar && $parts !== []) {
                    return Sogerien::Debager()->capture_return(implode(',', $parts), __CLASS__, __FUNCTION__);
                }
            }

            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            return Sogerien::Debager()->capture_return(is_string($json) ? $json : '', __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return(trim((string)$value), __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $item
     */
    private function looks_like_proxy_package_item(array $item): bool
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $keys = ['id', 'key', 'name', 'title', 'package_key', 'status', 'country', 'countries'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $item)) {
                return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
            }
        }
        return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
    }

    private function normalize_country_codes_value(mixed $value): string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $codes = [];

        if (is_array($value)) {
            if ($this->is_list_array($value)) {
                foreach ($value as $item) {
                    $code = strtoupper(trim((string)$item));
                    if ($code !== '') {
                        $codes[$code] = true;
                    }
                }
            } else {
                foreach ($value as $k => $v) {
                    if (is_bool($v)) {
                        if ($v === true) {
                            $code = strtoupper(trim((string)$k));
                            if ($code !== '') {
                                $codes[$code] = true;
                            }
                        }
                        continue;
                    }

                    $raw = strtoupper(trim((string)$v));
                    if ($raw !== '') {
                        $codes[$raw] = true;
                    }
                }
            }
        } else {
            $raw = strtoupper(trim((string)$value));
            if ($raw !== '') {
                $parts = preg_split('/\s*,\s*/', $raw) ?: [];
                foreach ($parts as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $codes[$part] = true;
                    }
                }
            }
        }

        $out = array_keys($codes);
        sort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return Sogerien::Debager()->capture_return(implode(',', $out), __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $item
     */
    private function detect_proxy_category(array $item, string $proxy_type): string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $raw = strtolower($this->first_non_empty_scalar($item, ['proxy_category', 'category', 'type', 'package_type', 'plan']));
        if ($raw === '') {
            return Sogerien::Debager()->capture_return($proxy_type, __CLASS__, __FUNCTION__);
        }
        if (str_contains($raw, 'mobil')) {
            return Sogerien::Debager()->capture_return('mobile', __CLASS__, __FUNCTION__);
        }
        if (str_contains($raw, 'residen')) {
            return Sogerien::Debager()->capture_return('residential', __CLASS__, __FUNCTION__);
        }
        if (str_contains($raw, 'isp') || str_contains($raw, 'datacenter') || $raw === 'dc') {
            return Sogerien::Debager()->capture_return('isp', __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($raw, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $item
     */
    private function detect_country_codes(array $item): string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $keys = [
            'location_country_code',
            'country',
            'country_code',
            'country_iso_code',
            'country_iso',
            'countries',
            'country_list',
            'geo',
        ];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $item)) {
                continue;
            }
            $country = $this->normalize_country_codes_value($item[$key]);
            if ($country !== '') {
                return Sogerien::Debager()->capture_return($country, __CLASS__, __FUNCTION__);
            }
        }
        return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $item
     */
    private function detect_stock_status(array $item): string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $status_raw = strtolower($this->first_non_empty_scalar($item, ['stock_status', 'status', 'state', 'package_status']));
        if ($status_raw === '') {
            if (array_key_exists('is_active', $item)) {
                return Sogerien::Debager()->capture_return((bool)$item['is_active'] ? 'in_stock' : 'out_of_stock', __CLASS__, __FUNCTION__);
            }
            if (array_key_exists('active', $item)) {
                return Sogerien::Debager()->capture_return((bool)$item['active'] ? 'in_stock' : 'out_of_stock', __CLASS__, __FUNCTION__);
            }
            return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $positive = ['actual' => true, 'active' => true, 'enabled' => true, 'in_stock' => true, 'ok' => true];
        $negative = ['delete' => true, 'archive' => true, 'inactive' => true, 'disabled' => true, 'suspended' => true, 'cancelled' => true, 'canceled' => true, 'out_of_stock' => true];
        if (isset($positive[$status_raw])) {
            return Sogerien::Debager()->capture_return('in_stock', __CLASS__, __FUNCTION__);
        }
        if (isset($negative[$status_raw])) {
            return Sogerien::Debager()->capture_return('out_of_stock', __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($status_raw, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $item
     */
    private function detect_price_usd(array $item): string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $keys = ['price_usd', 'price', 'price_monthly', 'cost', 'cost_usd', 'usd', 'amount'];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $item)) {
                continue;
            }
            $raw = trim((string)$item[$key]);
            if ($raw === '') {
                continue;
            }
            if (is_numeric($raw)) {
                return Sogerien::Debager()->capture_return(number_format((float)$raw, 2, '.', ''), __CLASS__, __FUNCTION__);
            }
            return Sogerien::Debager()->capture_return($raw, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $item
     */
    private function detect_days(array $item): string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $keys = ['days', 'period_days', 'term_days', 'duration_days', 'expire_in_days', 'ttl_days', 'period'];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $item)) {
                continue;
            }
            $raw = trim((string)$item[$key]);
            if ($raw !== '') {
                return Sogerien::Debager()->capture_return($raw, __CLASS__, __FUNCTION__);
            }
        }
        return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $item
     */
    private function detect_access_type(array $item, string $proxy_type): string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $raw = strtolower($this->first_non_empty_scalar($item, ['access_type', 'access', 'mode', 'auth_mode']));
        if ($raw !== '') {
            if ($raw === 'shared' || $raw === 'public') {
                return Sogerien::Debager()->capture_return('public', __CLASS__, __FUNCTION__);
            }
            if ($raw === 'private' || $raw === 'dedicated') {
                return Sogerien::Debager()->capture_return('private', __CLASS__, __FUNCTION__);
            }
            return Sogerien::Debager()->capture_return($raw, __CLASS__, __FUNCTION__);
        }

        if ($proxy_type === 'isp') {
            return Sogerien::Debager()->capture_return('private', __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return('public', __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $source
     * @param array<int,string> $keys
     */
    private function first_non_empty_scalar(array $source, array $keys): string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }
            $raw = trim((string)$source[$key]);
            if ($raw !== '') {
                return Sogerien::Debager()->capture_return($raw, __CLASS__, __FUNCTION__);
            }
        }
        return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,string>
     */
    private function collect_proxy_list_columns(array $rows): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $seen = [];
        $order = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $column) {
                $name = (string)$column;
                if (!isset($seen[$name])) {
                    $seen[$name] = true;
                    $order[] = $name;
                }
            }
        }

        $preferred = [
            'API',
            'id',
            'title',
            'location_country_code',
            'price_usd',
            'price_per_day',
            'days',
            'proxy_api_type',
            'stock_status',
            'traffic_gb',
            'price_per_gb',
            'is_auto_renewal_possible',
            'access_type',
        ];

        $columns = [];
        $added = [];
        foreach ($preferred as $name) {
            if (isset($seen[$name])) {
                $columns[] = $name;
                $added[$name] = true;
            }
        }
        foreach ($order as $name) {
            if (!isset($added[$name])) {
                $columns[] = $name;
            }
        }
        return Sogerien::Debager()->capture_return($columns, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $columns
     * @return array<string,array<int,string>>
     */
    private function collect_proxy_list_filters(array $rows, array $columns): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $sets = [];
        foreach ($columns as $column) {
            $sets[$column] = [];
        }

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    continue;
                }

                if ($column === 'location_country_code') {
                    $raw = strtoupper(trim((string)$row[$column]));
                    if ($raw === '') {
                        continue;
                    }
                    $parts = preg_split('/\s*,\s*/', $raw) ?: [];
                    foreach ($parts as $code) {
                        $code = trim($code);
                        if ($code !== '') {
                            $sets[$column][$code] = true;
                        }
                    }
                    continue;
                }

                $value = trim((string)$row[$column]);
                if ($value !== '') {
                    $sets[$column][$value] = true;
                }
            }
        }

        $out = [];
        foreach ($columns as $column) {
            $values = array_keys($sets[$column]);
            $out[$column] = $this->sort_proxy_filter_values($values);
        }
        return Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<int,string> $values
     * @return array<int,string>
     */
    private function sort_proxy_filter_values(array $values): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if ($values === []) {
            return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
        }

        $all_numeric = true;
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                $all_numeric = false;
                break;
            }
        }

        if ($all_numeric) {
            usort(
                $values,
                static fn(string $a, string $b): int => ((float)$a <=> (float)$b) ?: strcmp($a, $b)
            );
            return Sogerien::Debager()->capture_return(array_values($values), __CLASS__, __FUNCTION__);
        }

        natcasesort($values);
        return Sogerien::Debager()->capture_return(array_values($values), __CLASS__, __FUNCTION__);
    }

    /**
     * Scraper endpoint: POST https://scrape.infatica.io/
     *
     * @param array<string,mixed> $payload
     * @return array<mixed>|string|null
     */
    public function scraper(array $payload): array|string|null
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->scraper_request_endpoint('/', $payload), __CLASS__, __FUNCTION__);
    }

    /**
     * Render endpoint: POST https://scrape.infatica.io/render
     *
     * @param array<string,mixed> $payload
     * @return array<mixed>|string|null
     */
    public function scraper_render(array $payload): array|string|null
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->scraper_request_endpoint('/render', $payload), __CLASS__, __FUNCTION__);
    }

    /**
     * Google SERP endpoint: POST https://scrape.infatica.io/serp
     *
     * @param array<string,mixed> $payload
     * @return array<mixed>|string|null
     */
    public function scraper_serp(array $payload): array|string|null
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->scraper_request_endpoint('/serp', $payload), __CLASS__, __FUNCTION__);
    }

    /**
     * @return array<mixed>|string|null
     */
    public function scraper_chatgpt(string $query, bool $return_html = false): array|string|null
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->scraper_ai('/chatgpt', $query, $return_html), __CLASS__, __FUNCTION__);
    }

    /**
     * @return array<mixed>|string|null
     */
    public function scraper_gemini(string $query, bool $return_html = false): array|string|null
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->scraper_ai('/gemini', $query, $return_html), __CLASS__, __FUNCTION__);
    }

    /**
     * @return array<mixed>|string|null
     */
    public function scraper_perplexity(string $query, bool $return_html = false): array|string|null
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->scraper_ai('/perplexity', $query, $return_html), __CLASS__, __FUNCTION__);
    }

    public function scraper_decode_base64_html(string $value): ?string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $value = trim($value);
        if ($value === '') {
            $this->fail('base64 value is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $decoded = base64_decode($value, true);
        if (!is_string($decoded)) {
            $this->fail('Invalid base64 html');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return Sogerien::Debager()->capture_return($decoded, __CLASS__, __FUNCTION__);
    }

    // METHODS
    /**
     * POST /isp/package
     *
     * @return array<mixed>|null
     */
    public function package_create(string $country, int $count = 1): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $country = trim($country);
        if ($country === '') {
            $this->fail('country is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($count <= 0) {
            $this->fail('count must be > 0');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->post('/isp/package', [
            'country' => $country,
            'count' => $count,
        ]), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function create_package(string $country, int $count = 1): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->package_create($country, $count), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function package_suspend(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->post('/isp/package/' . rawurlencode($package_key) . '/suspend'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function suspend_package(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->package_suspend($package_key), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function package_cancel(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->post('/isp/package/' . rawurlencode($package_key) . '/cancel'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function cancel_package(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->package_cancel($package_key), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function package_uncancel(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->post('/isp/package/' . rawurlencode($package_key) . '/uncancel'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function uncancel_package(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->package_uncancel($package_key), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function package_deactivate(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->post('/isp/package/' . rawurlencode($package_key) . '/deactivate'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function deactivate_package(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->package_deactivate($package_key), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function package_resume(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->post('/isp/package/' . rawurlencode($package_key) . '/resume'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function resume_package(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->package_resume($package_key), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function package_info(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->get('/isp/package/' . rawurlencode($package_key) . '/info'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function info_package(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->package_info($package_key), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function countries(): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->get('/isp/countries'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function get_countries(): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->countries(), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function balance(): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->get('/isp/balance'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function get_balance(): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->balance(), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function isp_package_create(string $country, int $count = 1): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->package_create($country, $count), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function isp_package_suspend(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->package_suspend($package_key), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function isp_package_cancel(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->package_cancel($package_key), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function isp_package_uncancel(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->package_uncancel($package_key), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function isp_package_deactivate(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->package_deactivate($package_key), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function isp_package_resume(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->package_resume($package_key), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function isp_package_info(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->package_info($package_key), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function isp_countries(): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->countries(), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function isp_balance(): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->balance(), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function dc_package_create(string $country, int $count = 1): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $country = trim($country);
        if ($country === '') {
            $this->fail('country is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($count <= 0) {
            $this->fail('count must be > 0');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->post('/dc/package', [
            'country' => $country,
            'count' => $count,
        ]), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function dc_package_suspend(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->post('/dc/package/' . rawurlencode($package_key) . '/suspend'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function dc_package_cancel(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->post('/dc/package/' . rawurlencode($package_key) . '/cancel'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function dc_package_deactivate(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->post('/dc/package/' . rawurlencode($package_key) . '/deactivate'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function dc_package_resume(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->post('/dc/package/' . rawurlencode($package_key) . '/resume'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function dc_package_info(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->get('/dc/package/' . rawurlencode($package_key) . '/info'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function dc_countries(): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->get('/dc/countries'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function dc_balance(): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->get('/dc/balance'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function stats(): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return($this->get('/stats'), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function nodes_info(): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return($this->get('/nodes-info'), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function mobile_nodes_info(): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return($this->get('/mobile-nodes-info'), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function dc_nodes_info(): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return($this->get('/dc-nodes-info'), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function count_by_geo(): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return($this->get('/count-by-geo'), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function count_by_geo_mob(): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return($this->get('/count-by-geo-mob'), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function count_by_geo_dc(): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return($this->get('/count-by-geo-dc'), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function count_by_geo_v6(): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return($this->get('/count-by-geo-v6'), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function keys(): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return($this->get('/keys'), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function subdivision_codes(): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return($this->get('/subdivision-codes'), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function isp_codes(): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return($this->get('/isp-codes'), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function geo(): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return($this->get('/geo'), __CLASS__, __FUNCTION__); }

    /**
     * POST /online-info
     *
     * @return array<mixed>|null
     */
    public function online_info(string $country = '', string $type = '', string $period = '', int $interval = 1): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $country = strtoupper(trim($country));
        $type = strtolower(trim($type));
        $period = strtolower(trim($period));

        if ($country !== '' && !$this->is_iso2($country)) {
            $this->fail('country must be ISO2 code');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($type !== '' && $type !== 'mobile') {
            $this->fail('type must be mobile or empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($period !== '' && $period !== 'hour' && $period !== 'day') {
            $this->fail('period must be hour or day');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($period !== '' && $interval < 1) {
            $this->fail('interval must be >= 1');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($period === '' && $interval !== 1) {
            $this->fail('period and interval must be used together');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $form = [];
        if ($country !== '') $form['country'] = $country;
        if ($type !== '') $form['type'] = $type;
        if ($period !== '') { $form['period'] = $period; $form['interval'] = $interval; }

        return Sogerien::Debager()->capture_return($this->post('/online-info', $form), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function zip_codes(string $country = ''): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $country = strtoupper(trim($country));
        if ($country !== '' && !$this->is_iso2($country)) {
            $this->fail('country must be ISO2 code');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->post('/zip-codes', $country === '' ? [] : ['country' => $country]), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function traffic_details(string $key, string $period): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $key = trim($key);
        $period = strtolower(trim($period));
        if ($key === '') {
            $this->fail('key is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $allowed = ['daily' => true, 'weekly' => true, 'monthly' => true, 'all' => true];
        if (!isset($allowed[$period])) {
            $this->fail('period must be daily|weekly|monthly|all');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->post('/traffic-details', ['key' => $key, 'period' => $period]), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function reseller_package_create(array $form = []): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->post('/package', $form), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function reseller_package_update(string $package_key, array $form = []): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->post('/package/' . rawurlencode($package_key), $form), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function reseller_package_info(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->get('/package/' . rawurlencode($package_key)), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function reseller_packages(): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($this->get('/packages'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function reseller_packages_filtered(string $packages = ''): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $packages = trim($packages);
        return Sogerien::Debager()->capture_return($this->post('/packages', $packages === '' ? [] : ['packages' => $packages]), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function reseller_package_prolongate(string $package_key, string $expired_at): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        $expired_at = trim($expired_at);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        if ($expired_at === '') {
            $this->fail('expired_at is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->post('/package/' . rawurlencode($package_key) . '/prolongate', ['expired_at' => $expired_at]), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function reseller_package_suspend(string $package_key): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $package_key = $this->normalize_package_key($package_key); if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__); return Sogerien::Debager()->capture_return($this->post('/package/' . rawurlencode($package_key) . '/suspend'), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function reseller_package_resume(string $package_key, array $form = []): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $package_key = $this->normalize_package_key($package_key); if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__); return Sogerien::Debager()->capture_return($this->post('/package/' . rawurlencode($package_key) . '/resume', $form), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function reseller_package_deactivate(string $package_key): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $package_key = $this->normalize_package_key($package_key); if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__); return Sogerien::Debager()->capture_return($this->post('/package/' . rawurlencode($package_key) . '/deactivate'), __CLASS__, __FUNCTION__); }

    /** @return array<mixed>|null */
    public function usage(bool $post = false): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return($post ? $this->post('/usage') : $this->get('/usage'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function usage_pagination(int $page = 1): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if ($page < 1) {
            $this->fail('page must be >= 1');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->post('/usage-pagination', ['page' => $page]), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function package_usage(string $package_key): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return($this->get('/package/' . rawurlencode($package_key) . '/usage'), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function proxylist_countries(bool $post = false): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return($post ? $this->post('/countries') : $this->get('/countries'), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function regions(string $country_iso_code, bool $post = false): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $country_iso_code = strtoupper(trim($country_iso_code)); if (!$this->is_iso2($country_iso_code)) { $this->fail('country_iso_code must be ISO2 code'); return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__); } return Sogerien::Debager()->capture_return($post ? $this->post('/regions/' . rawurlencode($country_iso_code)) : $this->get('/regions/' . rawurlencode($country_iso_code)), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function cities(string $country_iso_code, string $region_name, bool $post = false): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $country_iso_code = strtoupper(trim($country_iso_code)); $region_name = trim($region_name); if (!$this->is_iso2($country_iso_code)) { $this->fail('country_iso_code must be ISO2 code'); return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__); } if ($region_name === '') { $this->fail('region_name is empty'); return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__); } $path = '/cities/' . rawurlencode($country_iso_code) . '/' . rawurlencode($region_name); return Sogerien::Debager()->capture_return($post ? $this->post($path) : $this->get($path), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function isps(string $country_iso_code, string $region_name, string $city_name, bool $post = false): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $country_iso_code = strtoupper(trim($country_iso_code)); $region_name = trim($region_name); $city_name = trim($city_name); if (!$this->is_iso2($country_iso_code)) { $this->fail('country_iso_code must be ISO2 code'); return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__); } if ($region_name === '' || $city_name === '') { $this->fail('region_name/city_name is empty'); return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__); } $path = '/isps/' . rawurlencode($country_iso_code) . '/' . rawurlencode($region_name) . '/' . rawurlencode($city_name); return Sogerien::Debager()->capture_return($post ? $this->post($path) : $this->get($path), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function formats(bool $post = false): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return($post ? $this->post('/formats') : $this->get('/formats'), __CLASS__, __FUNCTION__); }

    /** @return array<mixed>|null */
    public function package_generate(string $package_key, array $form): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        $hasLogin = trim((string)($form['proxy-list-login'] ?? '')) !== '';
        $hasNetwork = trim((string)($form['proxy-list-network'] ?? '')) !== '';
        if (trim((string)($form['proxy-list-name'] ?? '')) === '' || (!$hasLogin && !$hasNetwork)) {
            $this->fail('proxy-list-name and proxy-list-login/proxy-list-network are required');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->post('/package/' . rawurlencode($package_key) . '/generate', $form), __CLASS__, __FUNCTION__);
    }
    /** @return array<mixed>|null */
    public function package_api_tool(string $package_key, array $form): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        $hasLogin = trim((string)($form['proxy-list-login'] ?? '')) !== '';
        $hasNetwork = trim((string)($form['proxy-list-network'] ?? '')) !== '';
        if (trim((string)($form['proxy-list-name'] ?? '')) === '' || (!$hasLogin && !$hasNetwork)) {
            $this->fail('proxy-list-name and proxy-list-login/proxy-list-network are required');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->post('/package/' . rawurlencode($package_key) . '/api-tool', $form), __CLASS__, __FUNCTION__);
    }

    /** @return array<mixed>|null */
    public function package_pwd_regenerate(string $package_key, int|string $id, string $name): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        $name = trim($name);
        $id_str = trim((string)$id);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        if ($id_str === '' || $name === '') {
            $this->fail('id/name is required');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->post('/package/' . rawurlencode($package_key) . '/pwd-regenerate', ['id' => $id_str, 'name' => $name]), __CLASS__, __FUNCTION__);
    }
    /** @return array<mixed>|null */
    public function package_updatelist(string $package_key, array $form): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = $this->normalize_package_key($package_key);
        if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        if (trim((string)($form['id'] ?? '')) === '' || trim((string)($form['name'] ?? '')) === '') {
            $this->fail('id and name are required');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->post('/package/' . rawurlencode($package_key) . '/updatelist', $form), __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $options
     * @return array<mixed>|null
     */
    public function package_updatelist_from_options(string $package_key, int|string $id, string $name, array $options = []): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $options['name'] = $name;
        $form = $this->build_proxy_list_payload($options);
        if ($form === null) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $form['id'] = trim((string)$id);
        $form['name'] = $name;
        return Sogerien::Debager()->capture_return($this->package_updatelist($package_key, $form), __CLASS__, __FUNCTION__);
    }
    /** @return array<mixed>|null */
    public function package_lists(string $package_key, bool $post = false): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $package_key = $this->normalize_package_key($package_key); if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__); $path = '/package/' . rawurlencode($package_key) . '/lists'; return Sogerien::Debager()->capture_return($post ? $this->post($path) : $this->get($path), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function package_viewlist(string $package_key, int|string $id, string $name): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $package_key = $this->normalize_package_key($package_key); $name = trim($name); $id_str = trim((string)$id); if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__); if ($id_str === '' || $name === '') { $this->fail('id/name is required'); return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__); } return Sogerien::Debager()->capture_return($this->post('/package/' . rawurlencode($package_key) . '/viewlist', ['id' => $id_str, 'name' => $name]), __CLASS__, __FUNCTION__); }
    /** @return array<mixed>|null */
    public function package_removelist(string $package_key, int|string $id, string $name): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $package_key = $this->normalize_package_key($package_key); $name = trim($name); $id_str = trim((string)$id); if ($package_key === null) return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__); if ($id_str === '' || $name === '') { $this->fail('id/name is required'); return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__); } return Sogerien::Debager()->capture_return($this->post('/package/' . rawurlencode($package_key) . '/removelist', ['id' => $id_str, 'name' => $name]), __CLASS__, __FUNCTION__); }

    public function check_ip_block(string $ip): ?string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $ip = trim($ip);
        if ($ip === '') {
            $this->fail('ip is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->request_text('GET', '/check-ip-block/' . rawurlencode($ip)), __CLASS__, __FUNCTION__);
    }

    public function ip_unblock(string $ip): ?string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $ip = trim($ip);
        if ($ip === '') {
            $this->fail('ip is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->request_text('GET', '/ip-unblock/' . rawurlencode($ip)), __CLASS__, __FUNCTION__);
    }

    /**
     * @return array<mixed>|string|null
     */
    private function scraper_ai(string $endpoint, string $query, bool $return_html): array|string|null
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $query = trim($query);
        if ($query === '') {
            $this->fail('query is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($this->scraper_request_endpoint($endpoint, [
            'query' => $query,
            'return_html' => $return_html,
        ]), __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<mixed>|string|null
     */
    private function scraper_request_endpoint(string $endpoint, array $payload): array|string|null
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if (!$this->has_scraper_api_key()) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $endpoint = '/' . ltrim(trim($endpoint), '/');
        $url = rtrim($this->scraper_api_base_url, '/') . $endpoint;
        $this->last_url = $url;
        $this->last_http_code = 0;
        $this->last_err_code = '';
        $this->last_err_msg = '';
        $this->fail('');

        if (($endpoint === '/' || $endpoint === '/render' || $endpoint === '/serp') && trim((string)($payload['url'] ?? '')) === '') {
            $this->fail('url is required');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $country = strtoupper(trim((string)($payload['country'] ?? '')));
        if ($country !== '' && !$this->is_iso2($country)) {
            $this->fail('country must be ISO2 code');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($country !== '') {
            $payload['country'] = $country;
        }

        $language = trim((string)($payload['language'] ?? ''));
        if ($language !== '' && !preg_match('/^[a-z]{2}-[A-Z]{2}$/', $language)) {
            $this->fail('language must match xx-YY');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        if (!is_string($json)) {
            $this->fail('Failed to encode scraper payload');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $expect_html = (bool)($payload['return_html'] ?? false);

        $headers = [
            'Content-Type: application/json',
            'Accept: ' . ($expect_html ? 'text/html,application/xhtml+xml' : 'application/json'),
            'X-API-Key: ' . $this->scraper_api_key,
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            $this->fail('curl_init failed');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $this->last_http_code = $httpCode;
        $rawText = is_string($raw) ? $raw : '';

        $this->push_debug([
            'ts' => date('c'),
            'api_scope' => 'scraper',
            'method' => 'POST',
            'url' => $url,
            'http_code' => $httpCode,
            'request_body' => $payload,
            'request_body_json' => $json,
            'response_raw' => $rawText,
            'curl_error' => $curlErr,
        ]);

        if ($curlErr !== '') {
            $this->fail('CURL error: ' . $curlErr);
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = 'HTTP error: ' . $httpCode;
            $jsonError = json_decode($rawText, true);
            if (is_array($jsonError) && isset($jsonError['error']) && is_string($jsonError['error'])) {
                $msg .= ' - ' . trim($jsonError['error']);
            } elseif (trim($rawText) !== '') {
                $msg .= ' - ' . trim($rawText);
            }
            $this->fail($msg);
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        if ($expect_html) {
            $this->ok();
            return Sogerien::Debager()->capture_return($rawText, __CLASS__, __FUNCTION__);
        }

        $decoded = json_decode($rawText, true);
        if (!is_array($decoded)) {
            $this->fail('Invalid JSON response');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return Sogerien::Debager()->capture_return($decoded, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $body
     * @return array<mixed>|null
     */
    private function client_request_json(string $script, array $body): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $resp = $this->client_request($script, $body, true);
        if (($resp['ok'] ?? false) !== true) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $json = $resp['json'] ?? null;
        if (!is_array($json)) {
            $this->fail('Invalid JSON response');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return Sogerien::Debager()->capture_return($json, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function client_request(string $script, array $body, bool $expect_json = true): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $this->fail('');
        $this->last_http_code = 0;
        $this->last_url = '';
        $this->last_err_code = '';
        $this->last_err_msg = '';

        $script = ltrim(trim($script), '/');
        if ($script === '') {
            $this->fail('Client API script is empty');
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }
        if (!$this->has_client_auth()) {
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }

        $url = rtrim($this->client_api_base_url, '/') . '/' . $script;
        $this->last_url = $url;

        $requestBody = [
            'email' => $this->client_email,
            'password' => $this->client_password,
        ];
        foreach ($body as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            $key = trim($k);
            if ($key === '' || $key === 'email' || $key === 'password') {
                continue;
            }
            $requestBody[$key] = $v;
        }
        $requestForm = $this->normalize_form($requestBody);
        if ($this->status === false && $this->error !== '') {
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }
        $payload = http_build_query($requestForm, '', '&', PHP_QUERY_RFC3986);

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: ' . ($expect_json ? 'application/json' : 'text/plain'),
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            $this->fail('curl_init failed');
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $raw = curl_exec($ch);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $this->last_http_code = $httpCode;
        $responseRaw = is_string($raw) ? $raw : '';
        $rawHeaders = $headerSize > 0 ? substr($responseRaw, 0, $headerSize) : '';
        $rawText = $headerSize > 0 ? substr($responseRaw, $headerSize) : $responseRaw;
        $headersAssoc = $this->parse_response_headers($rawHeaders);
        $errCode = trim((string)($headersAssoc['err-code'] ?? ''));
        $errMsgFromHeader = trim((string)($headersAssoc['err-msg'] ?? ''));
        $mappedErrMsg = $errCode !== '' ? (self::SHARED_PROXY_ERR_CODES[$errCode] ?? '') : '';
        $this->last_err_code = $errCode;
        $this->last_err_msg = $errMsgFromHeader !== '' ? $errMsgFromHeader : $mappedErrMsg;

        $this->push_debug([
            'ts' => date('c'),
            'api_scope' => 'client',
            'method' => 'POST',
            'url' => $url,
            'http_code' => $httpCode,
            'request_body' => $requestBody,
            'request_form' => $requestForm,
            'request_payload' => $payload,
            'response_headers_raw' => $rawHeaders,
            'response_headers' => $headersAssoc,
            'response_raw' => $rawText,
            'curl_error' => $curlErr,
            'proxy_err_code' => $this->last_err_code,
            'proxy_err_msg' => $this->last_err_msg,
        ]);

        if ($curlErr !== '') {
            $this->fail('CURL error: ' . $curlErr);
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = $this->build_http_error_message($httpCode, $rawText);
            $this->fail($msg);
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }

        if ($expect_json) {
            $json = json_decode($rawText, true);
            if (!is_array($json)) {
                $this->fail('Invalid JSON response');
                return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
            }
            $this->ok();
            return Sogerien::Debager()->capture_return(['ok' => true, 'json' => $json, 'raw' => $rawText, 'http_code' => $httpCode], __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return Sogerien::Debager()->capture_return(['ok' => true, 'raw' => $rawText, 'http_code' => $httpCode], __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $body
     * @return array<mixed>|null
     */
    public function request_json(string $method, string $path, array $query = [], ?array $body = null): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $resp = $this->request($method, $path, $query, $body, true);
        if (($resp['ok'] ?? false) !== true) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $json = $resp['json'] ?? null;
        if (!is_array($json)) {
            $this->fail('Invalid JSON response');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return Sogerien::Debager()->capture_return($json, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $body
     */
    public function request_text(string $method, string $path, array $query = [], ?array $body = null): ?string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $resp = $this->request($method, $path, $query, $body, false);
        if (($resp['ok'] ?? false) !== true) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $raw = $resp['raw'] ?? null;
        if (!is_string($raw)) {
            $this->fail('Invalid text response');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return Sogerien::Debager()->capture_return($raw, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, array $query = [], ?array $body = null, bool $expect_json = true): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $this->fail('');
        $this->last_http_code = 0;
        $this->last_url = '';
        $this->last_err_code = '';
        $this->last_err_msg = '';

        $method = strtoupper(trim($method));
        if ($method === '') {
            $this->fail('HTTP method is empty');
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }
        if (!$this->has_api_key()) {
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }

        $path = '/' . ltrim(trim($path), '/');
        $url = rtrim($this->base_url, '/') . $path;
        if ($query !== []) {
            $qs = http_build_query($query);
            if ($qs !== '') $url .= '?' . $qs;
        }
        $this->last_url = $url;

        $headers = [
            'api-key: ' . $this->api_key,
            'Accept: ' . ($expect_json ? 'application/json' : 'text/plain'),
        ];

        $payload = null;
        if ($body !== null) {
            $payload = $this->normalize_form($body);
            if ($this->status === false && $this->error !== '') {
                return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
            }
        }

        $ch = curl_init($url);
        if ($ch === false) {
            $this->fail('curl_init failed');
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        $connectTimeout = $this->request_connect_timeout_seconds > 0 ? $this->request_connect_timeout_seconds : 6;
        $requestTimeout = $this->request_timeout_seconds > 0 ? $this->request_timeout_seconds : 18;
        if ($requestTimeout < $connectTimeout) {
            $requestTimeout = $connectTimeout;
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $requestTimeout);
        if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $raw = curl_exec($ch);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $this->last_http_code = $httpCode;
        $responseRaw = is_string($raw) ? $raw : '';
        $rawHeaders = $headerSize > 0 ? substr($responseRaw, 0, $headerSize) : '';
        $rawText = $headerSize > 0 ? substr($responseRaw, $headerSize) : $responseRaw;
        $headersAssoc = $this->parse_response_headers($rawHeaders);
        $errCode = trim((string)($headersAssoc['err-code'] ?? ''));
        $errMsgFromHeader = trim((string)($headersAssoc['err-msg'] ?? ''));
        $mappedErrMsg = $errCode !== '' ? (self::SHARED_PROXY_ERR_CODES[$errCode] ?? '') : '';
        $this->last_err_code = $errCode;
        $this->last_err_msg = $errMsgFromHeader !== '' ? $errMsgFromHeader : $mappedErrMsg;

        $this->push_debug([
            'ts' => date('c'),
            'method' => $method,
            'url' => $url,
            'http_code' => $httpCode,
            'query' => $query,
            'request_body' => $body,
            'request_form' => $payload,
            'response_headers_raw' => $rawHeaders,
            'response_headers' => $headersAssoc,
            'response_raw' => $rawText,
            'curl_error' => $curlErr,
            'proxy_err_code' => $this->last_err_code,
            'proxy_err_msg' => $this->last_err_msg,
        ]);

        if ($curlErr !== '') {
            $this->fail('CURL error: ' . $curlErr);
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = $this->build_http_error_message($httpCode, $rawText);
            $this->fail($msg);
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }

        if ($expect_json) {
            $json = json_decode($rawText, true);
            if (!is_array($json)) {
                $this->fail('Invalid JSON response');
                return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
            }
            $this->ok();
            return Sogerien::Debager()->capture_return(['ok' => true, 'json' => $json, 'raw' => $rawText, 'http_code' => $httpCode], __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return Sogerien::Debager()->capture_return(['ok' => true, 'raw' => $rawText, 'http_code' => $httpCode], __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function normalize_form(array $body): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $out = [];
        foreach ($body as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                $this->fail('Invalid form key');
                return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
            }
            $out[trim($key)] = $this->normalize_form_value($value);
        }
        return Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
    }

    private function normalize_form_value(mixed $value): mixed
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) $result[$k] = $this->normalize_form_value($v);
            return Sogerien::Debager()->capture_return($result, __CLASS__, __FUNCTION__);
        }
        if (is_bool($value)) return Sogerien::Debager()->capture_return($value ? '1' : '0', __CLASS__, __FUNCTION__);
        if ($value === null) return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        if (is_int($value) || is_float($value) || is_string($value)) return Sogerien::Debager()->capture_return((string)$value, __CLASS__, __FUNCTION__);

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (!is_string($encoded)) {
            $this->fail('Failed to encode form field');
            return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($encoded, __CLASS__, __FUNCTION__);
    }

    private function normalize_package_key(string $package_key): ?string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $package_key = trim($package_key);
        if ($package_key === '') {
            $this->fail('package_key is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($package_key, __CLASS__, __FUNCTION__);
    }

    private function is_iso2(string $value): bool
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        return Sogerien::Debager()->capture_return((bool)preg_match('/^[A-Z]{2}$/', $value), __CLASS__, __FUNCTION__);
    }

    private function normalize_positive_id(mixed $value, string $field): string|false|null
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if ($value === null || $value === '') {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $raw = trim((string)$value);
        if ($raw === '') {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (!ctype_digit($raw) || (int)$raw <= 0) {
            $this->fail($field . ' must be positive integer');
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($raw, __CLASS__, __FUNCTION__);
    }

    /**
     * @return string|array<int,string>|null
     */
    private function normalize_proxy_country(mixed $value): string|array|null
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if (is_string($value)) {
            $country = strtoupper(trim($value));
            if ($country === '') {
                return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }
            if (!$this->is_iso2($country)) {
                $this->fail('country must be ISO2 code');
                return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }
            return Sogerien::Debager()->capture_return($country, __CLASS__, __FUNCTION__);
        }

        if (!is_array($value)) {
            $this->fail('country must be string or array');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $out = [];
        foreach ($value as $item) {
            $country = strtoupper(trim((string)$item));
            if ($country === '') {
                continue;
            }
            if (!$this->is_iso2($country)) {
                $this->fail('country array must contain ISO2 codes');
                return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }
            $out[] = $country;
        }
        $out = array_values(array_unique($out));
        if ($out === []) {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
    }

    private function normalize_rotation_period(mixed $value): int|false|null
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if ($value === null || $value === '') {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (is_int($value)) {
            if ($value === 0 || $value === -1 || $value > 0) {
                return Sogerien::Debager()->capture_return($value, __CLASS__, __FUNCTION__);
            }
            $this->fail('rotation_period int must be -1, 0, or > 0');
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $raw = strtolower(trim((string)$value));
        if ($raw === 'sticky' || $raw === 'no_rotation') {
            return Sogerien::Debager()->capture_return(-1, __CLASS__, __FUNCTION__);
        }
        if ($raw === 'each_request' || $raw === 'per_request') {
            return Sogerien::Debager()->capture_return(0, __CLASS__, __FUNCTION__);
        }
        if (preg_match('/^([1-9][0-9]*)m$/', $raw, $m)) {
            return Sogerien::Debager()->capture_return(((int)$m[1]) * 60, __CLASS__, __FUNCTION__);
        }
        if (preg_match('/^([1-9][0-9]*)s$/', $raw, $m)) {
            return Sogerien::Debager()->capture_return((int)$m[1], __CLASS__, __FUNCTION__);
        }
        if (ctype_digit($raw) && (int)$raw > 0) {
            return Sogerien::Debager()->capture_return((int)$raw, __CLASS__, __FUNCTION__);
        }

        $this->fail('rotation_period is invalid');
        return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
    }

    private function normalize_rotation_mode(mixed $value): int|false|null
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if ($value === null || $value === '') {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (is_int($value)) {
            if ($value >= 0 && $value <= 2) {
                return Sogerien::Debager()->capture_return($value, __CLASS__, __FUNCTION__);
            }
            $this->fail('rotation_mode int must be 0..2');
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $raw = strtolower(trim((string)$value));
        if ($raw === 'instant' || $raw === '0') return Sogerien::Debager()->capture_return(0, __CLASS__, __FUNCTION__);
        if ($raw === '5_seconds' || $raw === '5s' || $raw === '1') return Sogerien::Debager()->capture_return(1, __CLASS__, __FUNCTION__);
        if ($raw === 'no_rotation' || $raw === '2') return Sogerien::Debager()->capture_return(2, __CLASS__, __FUNCTION__);

        $this->fail('rotation_mode is invalid');
        return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
    }

    private function normalize_proxy_format(mixed $value, string $protocol = ''): int|false|null
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $protocol = strtolower(trim($protocol));

        $formatByProtocol = null;
        if ($protocol !== '') {
            if ($protocol === 'http' || $protocol === 'https') {
                $formatByProtocol = 3;
            } elseif ($protocol === 'socks5' || $protocol === 'socks') {
                $formatByProtocol = 4;
            } else {
                $this->fail('protocol must be http/https/socks5');
                return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
            }
        }

        if ($value === null || $value === '') {
            return Sogerien::Debager()->capture_return($formatByProtocol, __CLASS__, __FUNCTION__);
        }

        $format = null;
        if (is_int($value)) {
            $format = $value;
        } else {
            $raw = strtolower(trim((string)$value));
            $map = [
                '1' => 1,
                '2' => 2,
                '3' => 3,
                '4' => 4,
                '5' => 5,
                '6' => 6,
                '7' => 7,
                'login:password@host:port' => 1,
                'host,port,login,password' => 2,
                'http://login:password@host:port' => 3,
                'socks5://login:password@host:port' => 4,
                'login:password:host:port' => 5,
                'host:port:login:password' => 6,
                'login@password@host@port' => 7,
            ];
            $format = $map[$raw] ?? null;
        }

        if (!is_int($format) || $format < 1 || $format > 7) {
            $this->fail('format is invalid');
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        if ($formatByProtocol !== null && $format !== $formatByProtocol) {
            $this->fail('format conflicts with protocol');
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return($format, __CLASS__, __FUNCTION__);
    }

    private function normalize_proxy_api_type(string $proxy_type): string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $proxy_type = strtolower(trim($proxy_type));
        if ($proxy_type === 'residential' || $proxy_type === 'residential_ipv6' || $proxy_type === 'mobile' || $proxy_type === 'isp' || $proxy_type === 'dc' || $proxy_type === 'dc_shared') {
            return Sogerien::Debager()->capture_return($proxy_type, __CLASS__, __FUNCTION__);
        }
        if ($proxy_type === 'ipv6') {
            return Sogerien::Debager()->capture_return('residential_ipv6', __CLASS__, __FUNCTION__);
        }
        if ($proxy_type === 'datacenter' || $proxy_type === 'dedicated_dc') {
            return Sogerien::Debager()->capture_return('dc', __CLASS__, __FUNCTION__);
        }
        if ($proxy_type === 'shared_dc' || $proxy_type === 'dc_bandwidth') {
            return Sogerien::Debager()->capture_return('dc_shared', __CLASS__, __FUNCTION__);
        }
        $this->fail('proxy type must be residential|residential_ipv6|mobile|isp|dc|dc_shared');
        return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
    }

    private function resolve_api_key_for_proxy_type(string $proxy_type): ?string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $proxy_type = $this->normalize_proxy_api_type($proxy_type);
        if ($proxy_type === '') {
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $api_key = '';
        if ($proxy_type === 'residential' || $proxy_type === 'residential_ipv6') {
            $api_key = trim($this->api_key_residential);
        } elseif ($proxy_type === 'mobile') {
            $api_key = trim($this->api_key_mobile);
        } elseif ($proxy_type === 'dc' || $proxy_type === 'dc_shared') {
            $api_key = trim($this->api_key_dc);
        } else {
            $api_key = trim($this->api_key_isp);
        }

        if ($api_key === '') {
            $api_key = trim($this->api_key);
        }
        if ($api_key === '') {
            $this->fail('api_key for ' . $proxy_type . ' is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return($api_key, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<mixed> $value
     */
    private function is_list_array(array $value): bool
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $index = 0;
        foreach ($value as $k => $_v) {
            if ($k !== $index) {
                return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
            }
            $index++;
        }
        return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
    }

    private function normalize_client_pid(int|string $pid): ?string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $pid_str = trim((string)$pid);
        if ($pid_str === '' || !ctype_digit($pid_str) || (int)$pid_str <= 0) {
            $this->fail('pid must be positive integer');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($pid_str, __CLASS__, __FUNCTION__);
    }

    /**
     * @return array<string,string>|null
     */
    private function build_client_proxy_type_flags(bool $mobile, bool $dc, bool $v6 = false): ?array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $selected = (int)$mobile + (int)$dc + (int)$v6;
        if ($selected > 1) {
            $this->fail('mobile, dc and v6 cannot be true together');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($mobile) {
            return Sogerien::Debager()->capture_return(['mobile' => '1'], __CLASS__, __FUNCTION__);
        }
        if ($dc) {
            return Sogerien::Debager()->capture_return(['dc' => '1'], __CLASS__, __FUNCTION__);
        }
        if ($v6) {
            return Sogerien::Debager()->capture_return(['v6' => '1'], __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
    }

    private function has_client_auth(): bool
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if (trim($this->client_email) === '' || trim($this->client_password) === '') {
            $this->fail('client email/password is empty');
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
    }

    private function has_scraper_api_key(): bool
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if (trim($this->scraper_api_key) === '') {
            $this->fail('scraper_api_key is empty');
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
    }

    private function has_api_key(): bool
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if (trim($this->api_key) === '') {
            $this->fail('api_key is empty');
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
    }

    /**
     * @return array<string,string>
     */
    private function parse_response_headers(string $rawHeaders): array
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $rawHeaders = trim($rawHeaders);
        if ($rawHeaders === '') {
            return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
        }

        $blocks = preg_split("/\r\n\r\n|\n\n|\r\r/", $rawHeaders);
        if (!is_array($blocks) || $blocks === []) {
            return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
        }

        $lastBlock = trim((string)end($blocks));
        if ($lastBlock === '') {
            return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
        }

        $lines = preg_split("/\r\n|\n|\r/", $lastBlock);
        if (!is_array($lines) || $lines === []) {
            return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
        }

        $out = [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || str_starts_with($line, 'HTTP/')) {
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            if ($name === '') {
                continue;
            }
            $out[$name] = $value;
        }

        return Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
    }

    private function build_http_error_message(int $httpCode, string $rawBody): string
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $parts = [];
        $httpText = self::SHARED_PROXY_HTTP_CODES[$httpCode] ?? '';
        if ($httpText !== '') {
            $parts[] = $httpText;
        }

        if ($this->last_err_code !== '') {
            $parts[] = 'Err-Code: ' . $this->last_err_code;
        }
        if ($this->last_err_msg !== '') {
            $parts[] = 'Err-Msg: ' . $this->last_err_msg;
        }

        $jsonError = json_decode($rawBody, true);
        if (is_array($jsonError) && isset($jsonError['description']) && is_string($jsonError['description'])) {
            $desc = trim($jsonError['description']);
            if ($desc !== '' && $desc !== $this->last_err_msg) {
                $parts[] = $desc;
            }
        } elseif (trim($rawBody) !== '') {
            $text = trim($rawBody);
            if (strlen($text) > 320) {
                $text = substr($text, 0, 320) . '...';
            }
            if ($text !== $this->last_err_msg) {
                $parts[] = $text;
            }
        }

        $suffix = $parts !== [] ? (' - ' . implode(' | ', $parts)) : '';
        return Sogerien::Debager()->capture_return('HTTP error: ' . $httpCode . $suffix, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $entry
     */
    private function push_debug(array $entry): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $this->debug = $entry;
        if (!$this->debug_enabled) {
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }
        $this->debug_history[] = $entry;
        if (count($this->debug_history) > 200) {
            array_splice($this->debug_history, 0, count($this->debug_history) - 200);
        }
    }

    private function ok(): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $this->status = true;
        $this->error = '';
    }

    private function fail(string $msg): void
    {
        if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $this->status = false;
        $this->error = $msg;
    }
}

