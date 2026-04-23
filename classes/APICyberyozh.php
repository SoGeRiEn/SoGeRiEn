<?php
declare(strict_types=1);

final class APICyberyozh
{
    public bool $status = false;
    public string $error = '';

    public string $api_key = 'тут апи ключ';
    public string $base_url = 'https://app.cyberyozh.com/api';

    public bool $debug_enabled = true;
    public array $debug = [];
    public array $debug_history = [];

    public int $last_http_code = 0;
    public string $last_url = '';

    private string $debug_log_relative_path = 'logs/сyberyozh.log';

    public function set_api_key(string $api_key): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->api_key = trim($api_key);
        $this->ok();
}

    public function set_base_url(string $base_url): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $base_url = trim($base_url);
        if ($base_url === '') {
            $this->fail('base_url is empty');
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }

        $this->base_url = rtrim($base_url, '/');
        $this->ok();
}

    /**
     * Universal JSON request for future API methods.
     *
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>|null
     */
    public function request_json(string $method, string $path, array $query = [], ?array $body = null): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $resp = $this->request($method, $path, $query, $body, true);
        if (($resp['ok'] ?? false) !== true) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $json = $resp['json'] ?? null;
        if (!is_array($json)) {
            $this->fail('Invalid JSON response');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return($json, __CLASS__, __FUNCTION__);
}

    /**
     * Universal TEXT request for endpoints that return plain text files.
     *
     * @param array<string,mixed> $query
     */
    public function request_text(string $method, string $path, array $query = []): ?string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $resp = $this->request($method, $path, $query, null, false);
        if (($resp['ok'] ?? false) !== true) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $body = $resp['raw'] ?? null;
        if (!is_string($body)) {
            $this->fail('Invalid text response');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return($body, __CLASS__, __FUNCTION__);
}

    /**
     * GET /v1/proxies/shop/
     *
     * @return array<string,mixed>|null
     */
    public function get_proxies_shop(
        string $access_type = '',
        string $country = '',
        string $proxy_category = '',
        string $stock_status = '',
        int $page = 0,
        int $page_size = 0
    ): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $access_type = strtolower(trim($access_type));
        $country = strtolower(trim($country));
        $proxy_category = strtolower(trim($proxy_category));
        $stock_status = strtolower(trim($stock_status));

        if (!$this->validate_access_type($access_type)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (!$this->validate_country_code($country)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (!$this->validate_proxy_category($proxy_category)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (!$this->validate_stock_status($stock_status)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $query = [];
        if ($access_type !== '') {
            $query['access_type'] = $access_type;
        }
        if ($country !== '') {
            $query['country'] = $country;
        }
        if ($proxy_category !== '') {
            $query['proxy_category'] = $proxy_category;
        }
        if ($stock_status !== '') {
            $query['stock_status'] = $stock_status;
        }
        if ($page > 0) {
            $query['page'] = $page;
        }
        if ($page_size > 0) {
            $query['page_size'] = $page_size;
        }

        return   Sogerien::Debager()->capture_return($this->request_json('GET', '/v1/proxies/shop/', $query), __CLASS__, __FUNCTION__);
}

    /**
     * POST /v1/proxies/shop/buy_proxies/
     *
     * @param array<int,array<string,mixed>> $items
     * @return array<string,mixed>|null
     */
    public function buy_proxies(array $items, int $page = 0, int $page_size = 0): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($items === []) {
            $this->fail('items is empty');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $body = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                $this->fail('items[' . (string)$index . '] must be array');
                return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }

            $idRaw = $item['id'] ?? '';
            if (!is_string($idRaw)) {
                $this->fail('items[' . (string)$index . '].id must be string');
                return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }

            $id = $this->normalize_required_id($idRaw, 'items[' . (string)$index . '].id');
            if ($id === null) {
                return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }

            if (!array_key_exists('auto_renew', $item) || !is_bool($item['auto_renew'])) {
                $this->fail('items[' . (string)$index . '].auto_renew must be bool');
                return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
            }

            $entry = [
                'id' => $id,
                'auto_renew' => $item['auto_renew'],
            ];

            if (array_key_exists('promo_code', $item)) {
                if (!is_string($item['promo_code'])) {
                    $this->fail('items[' . (string)$index . '].promo_code must be string');
                    return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
                }

                $promo_code = trim($item['promo_code']);
                if ($promo_code !== '') {
                    $entry['promo_code'] = $promo_code;
                }
            }

            $body[] = $entry;
        }

        $query = [];
        if ($page > 0) {
            $query['page'] = $page;
        }
        if ($page_size > 0) {
            $query['page_size'] = $page_size;
        }

        return   Sogerien::Debager()->capture_return($this->request_json('POST', '/v1/proxies/shop/buy_proxies/', $query, $body), __CLASS__, __FUNCTION__);
}

    /**
     * Recommended alias (v2).
     *
     * @return array<string,mixed>|null
     */
    public function get_balance(): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->get_balance_v2(), __CLASS__, __FUNCTION__);
}

    /**
     * GET /v2/users/balance/
     *
     * @return array<string,mixed>|null
     */
    public function get_balance_v2(): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->request_json('GET', '/v2/users/balance/'), __CLASS__, __FUNCTION__);
}

    /**
     * GET /v1/users/balance/
     *
     * @return array<string,mixed>|null
     */
    public function get_balance_v1(): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->request_json('GET', '/v1/users/balance/'), __CLASS__, __FUNCTION__);
}

    /**
     * GET /v1/proxies/proxy-credentials/
     *
     * @return array<string,mixed>|null
     */
    public function get_proxy_credentials(
        string $type_format,
        string $protocol = '',
        int $page = 0,
        int $page_size = 0
    ): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if (!$this->validate_type_format($type_format)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (!$this->validate_protocol($protocol)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $query = [
            'type_format' => $type_format,
        ];
        if ($protocol !== '') {
            $query['protocol'] = $protocol;
        }
        if ($page > 0) {
            $query['page'] = $page;
        }
        if ($page_size > 0) {
            $query['page_size'] = $page_size;
        }

        return   Sogerien::Debager()->capture_return($this->request_json('GET', '/v1/proxies/proxy-credentials/', $query), __CLASS__, __FUNCTION__);
}

    /**
     * GET /v1/proxies/proxy-credentials/download/
     */
    public function download_proxy_credentials(
        string $type_format = '',
        string $protocol = '',
        int $page = 0,
        int $page_size = 0
    ): ?string { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($type_format !== '' && !$this->validate_type_format($type_format)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (!$this->validate_protocol($protocol)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $query = [];
        if ($type_format !== '') {
            $query['type_format'] = $type_format;
        }
        if ($protocol !== '') {
            $query['protocol'] = $protocol;
        }
        if ($page > 0) {
            $query['page'] = $page;
        }
        if ($page_size > 0) {
            $query['page_size'] = $page_size;
        }

        return   Sogerien::Debager()->capture_return($this->request_text('GET', '/v1/proxies/proxy-credentials/download/', $query), __CLASS__, __FUNCTION__);
}

    /**
     * GET /v1/proxies/history/
     *
     * @return array<string,mixed>|null
     */
    public function get_proxy_history(int $page = 0, int $page_size = 0): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $query = [];
        if ($page > 0) {
            $query['page'] = $page;
        }
        if ($page_size > 0) {
            $query['page_size'] = $page_size;
        }

        return   Sogerien::Debager()->capture_return($this->request_json('GET', '/v1/proxies/history/', $query), __CLASS__, __FUNCTION__);
}

    /**
     * GET /v1/proxies/history/?category=residential_rotating&expired=false
     *
     * @return array<string,mixed>|null
     */
    public function get_rotating_proxy_history(bool $expired = false, int $page = 0, int $page_size = 0): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $query = [
            'category' => 'residential_rotating',
            'expired' => $expired ? 'true' : 'false',
        ];
        if ($page > 0) {
            $query['page'] = $page;
        }
        if ($page_size > 0) {
            $query['page_size'] = $page_size;
        }

        return   Sogerien::Debager()->capture_return($this->request_json('GET', '/v1/proxies/history/', $query), __CLASS__, __FUNCTION__);
}

    /**
     * POST /v1/proxies/rotating-credentials/
     *
     * @return array<string,mixed>|null
     */
    public function generate_rotating_credentials(
        string $connection_login,
        string $connection_password,
        string $connection_host,
        int $connection_port,
        string $session_type,
        string $country_code = '',
        string $region = '',
        string $city = '',
        int $amount = 0
    ): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $connection_login = trim($connection_login);
        $connection_password = trim($connection_password);
        $connection_host = trim($connection_host);
        $session_type = trim($session_type);
        $country_code = trim($country_code);
        $region = trim($region);
        $city = trim($city);

        if ($connection_login === '') {
            $this->fail('connection_login is empty');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($connection_password === '') {
            $this->fail('connection_password is empty');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($connection_host === '') {
            $this->fail('connection_host is empty');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($connection_port <= 0) {
            $this->fail('connection_port is invalid');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (!$this->validate_session_type($session_type)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($amount < 0) {
            $this->fail('amount must be >= 0');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($amount > 0 && $session_type !== 'short_session') {
            $this->fail('amount is supported only for short_session');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $body = [
            'connection_login' => $connection_login,
            'connection_password' => $connection_password,
            'connection_host' => $connection_host,
            'connection_port' => $connection_port,
            'session_type' => $session_type,
        ];

        if ($country_code !== '') {
            $body['country_code'] = strtolower($country_code);
        }
        if ($region !== '') {
            $body['region'] = $region;
        }
        if ($city !== '') {
            $body['city'] = $city;
        }
        if ($amount > 0) {
            $body['amount'] = $amount;
        }

        return   Sogerien::Debager()->capture_return($this->request_json('POST', '/v1/proxies/rotating-credentials/', [], $body), __CLASS__, __FUNCTION__);
}

    /**
     * PATCH /v1/proxies/history/{id}/
     *
     * @return array<string,mixed>|null
     */
    public function update_auto_renewal(string $id, bool $auto_renew_request): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $id = $this->normalize_required_id($id, 'Proxy history id');
        if ($id === null) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        return   Sogerien::Debager()->capture_return($this->request_json(
            'PATCH',
            '/v1/proxies/history/' . rawurlencode($id) . '/',
            [],
            ['auto_renew_request' => $auto_renew_request]
        ), __CLASS__, __FUNCTION__);
}

    /**
     * POST /v1/proxies/history/{id}/change-fingerprint/
     *
     * @return array<string,mixed>|null
     */
    public function change_fingerprint(string $id, string $value): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $id = $this->normalize_required_id($id, 'Proxy history id');
        if ($id === null) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        return   Sogerien::Debager()->capture_return($this->request_json(
            'POST',
            '/v1/proxies/history/' . rawurlencode($id) . '/change-fingerprint/',
            [],
            ['value' => $value]
        ), __CLASS__, __FUNCTION__);
}

    /**
     * POST /v1/proxies/user-proxy-server/reboot/
     *
     * Rate limit on provider side: max 1 request per 5 minutes per user.
     *
     * @return array<string,mixed>|null
     */
    public function reboot_modem(string $id): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $id = $this->normalize_required_id($id, 'Proxy server id');
        if ($id === null) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        return   Sogerien::Debager()->capture_return($this->request_json(
            'POST',
            '/v1/proxies/user-proxy-server/reboot/',
            [],
            ['id' => $id]
        ), __CLASS__, __FUNCTION__);
}

    /**
     * POST /v1/proxies/user-proxy-server/refresh-ip/
     *
     * Rate limit on provider side: max 1 request per minute per user.
     *
     * @return array<string,mixed>|null
     */
    public function refresh_ip(string $id): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $id = $this->normalize_required_id($id, 'Proxy server id');
        if ($id === null) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        return   Sogerien::Debager()->capture_return($this->request_json(
            'POST',
            '/v1/proxies/user-proxy-server/refresh-ip/',
            [],
            ['id' => $id]
        ), __CLASS__, __FUNCTION__);
}

    /**
     * POST /v1/numbers/
     *
     * @return array<string,mixed>|null
     */
    public function buy_number(
        string $provider,
        string $period,
        string $service_code,
        string $country_code,
        bool $need_fraud_score = false
    ): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $provider = strtolower(trim($provider));
        $period = strtoupper(trim($period));
        $service_code = trim($service_code);
        $country_code = trim($country_code);

        if (!$this->validate_number_provider($provider)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (!$this->validate_number_period($period)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (!$this->validate_provider_period($provider, $period)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($service_code === '') {
            $this->fail('service_code is empty');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($country_code === '') {
            $this->fail('country_code is empty');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        return   Sogerien::Debager()->capture_return($this->request_json(
            'POST',
            '/v1/numbers/',
            [],
            [
                'provider' => $provider,
                'period' => $period,
                'service_code' => $service_code,
                'country_code' => $country_code,
                'need_fraud_score' => $need_fraud_score,
            ]
        ), __CLASS__, __FUNCTION__);
}

    /**
     * GET /v1/numbers/
     *
     * @return array<int,mixed>|array<string,mixed>|null
     */
    public function get_active_numbers(): array|null
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->request_json('GET', '/v1/numbers/'), __CLASS__, __FUNCTION__);
}

    /**
     * GET /v1/numbers/history/
     *
     * @return array<int,mixed>|array<string,mixed>|null
     */
    public function get_number_history(): array|null
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->request_json('GET', '/v1/numbers/history/'), __CLASS__, __FUNCTION__);
}

    /**
     * GET /v1/numbers/countries/
     *
     * @return array<int,mixed>|array<string,mixed>|null
     */
    public function get_numbers_countries(): array|null
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->request_json('GET', '/v1/numbers/countries/'), __CLASS__, __FUNCTION__);
}

    /**
     * GET /v1/numbers/services/
     *
     * @return array<int,mixed>|array<string,mixed>|null
     */
    public function get_numbers_services(): array|null
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->request_json('GET', '/v1/numbers/services/'), __CLASS__, __FUNCTION__);
}

    /**
     * GET /v1/numbers/search/
     *
     * @return array<string,mixed>|null
     */
    public function search_numbers(
        string $provider,
        string $period,
        string $country = '',
        string $service = ''
    ): ?array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $provider = strtolower(trim($provider));
        $period = strtoupper(trim($period));
        $country = trim($country);
        $service = trim($service);

        if (!$this->validate_number_provider($provider)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (!$this->validate_number_period($period)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (!$this->validate_provider_period($provider, $period)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (!$this->validate_numbers_country_code($country)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $query = [
            'provider' => $provider,
            'period' => $period,
        ];
        if ($country !== '') {
            $query['country'] = $country;
        }
        if ($service !== '') {
            $query['service'] = $service;
        }

        return   Sogerien::Debager()->capture_return($this->request_json('GET', '/v1/numbers/search/', $query), __CLASS__, __FUNCTION__);
}

    /**
     * GET /v1/numbers/{id}/
     *
     * @return array<string,mixed>|null
     */
    public function get_number_details(string $id): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $id = $this->normalize_required_id($id, 'Number id');
        if ($id === null) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        return   Sogerien::Debager()->capture_return($this->request_json('GET', '/v1/numbers/' . rawurlencode($id) . '/'), __CLASS__, __FUNCTION__);
}

    /**
     * PUT /v1/numbers/{id}/cancel/
     *
     * @return array<string,mixed>|null
     */
    public function cancel_number(string $id): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $id = $this->normalize_required_id($id, 'Number id');
        if ($id === null) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        return   Sogerien::Debager()->capture_return($this->request_json('PUT', '/v1/numbers/' . rawurlencode($id) . '/cancel/'), __CLASS__, __FUNCTION__);
}

    /**
     * POST /v1/checkers/phone/
     *
     * @return array<string,mixed>|null
     */
    public function check_phone_number(string $value): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $value = trim($value);
        if (!$this->validate_phone_e164($value)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        return   Sogerien::Debager()->capture_return($this->request_json(
            'POST',
            '/v1/checkers/phone/',
            [],
            ['value' => $value]
        ), __CLASS__, __FUNCTION__);
}

    /**
     * GET /v1/checkers/phone/
     *
     * @return array<string,mixed>|null
     */
    public function get_phone_checks(int $page = 0, int $page_size = 0): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $query = [];
        if ($page > 0) {
            $query['page'] = $page;
        }
        if ($page_size > 0) {
            $query['page_size'] = $page_size;
        }

        return   Sogerien::Debager()->capture_return($this->request_json('GET', '/v1/checkers/phone/', $query), __CLASS__, __FUNCTION__);
}

    /**
     * GET /v1/checkers/phone/{id}/
     *
     * @return array<string,mixed>|null
     */
    public function get_phone_check_details(string $id): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $id = $this->normalize_required_id($id, 'Phone check id');
        if ($id === null) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        return   Sogerien::Debager()->capture_return($this->request_json('GET', '/v1/checkers/phone/' . rawurlencode($id) . '/'), __CLASS__, __FUNCTION__);
}

    /**
     * GET /v1/checkers/history/
     *
     * @return array<int,mixed>|array<string,mixed>|null
     */
    public function get_checker_history(): array|null
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->request_json('GET', '/v1/checkers/history/'), __CLASS__, __FUNCTION__);
}

    /**
     * Aliases for project pages.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxiesList(array $params = []): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $type = strtolower(trim((string)($params['type'] ?? '')));
        $country = strtolower(trim((string)($params['country'] ?? '')));
        $limit = (int)($params['limit'] ?? 100);
        $offset = (int)($params['offset'] ?? 0);

        if ($limit <= 0) {
            $limit = 100;
        }
        if ($limit > 500) {
            $limit = 500;
        }
        if ($offset < 0) {
            $offset = 0;
        }

        $page = (int)floor($offset / $limit) + 1;
        if ($page < 1) {
            $page = 1;
        }

        $query = [
            'page' => $page,
            'page_size' => $limit,
        ];
        if ($type !== '') {
            $query['proxy_category'] = $type;
        }
        if ($country !== '') {
            $query['country'] = $country;
        }

        $resp = $this->request_json('GET', '/v1/proxies/shop/', $query);
        if ($resp === null) {
            return   Sogerien::Debager()->capture_return($this->aliasResult(null), __CLASS__, __FUNCTION__);
        }

        $dataset = $this->buildProxiesListDataset($resp, $limit, $offset, $page);
        return   Sogerien::Debager()->capture_return([
            'ok' => true,
            'data' => $dataset,
        ], __CLASS__, __FUNCTION__);
}

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private function buildProxiesListDataset(array $source, int $limit, int $offset, int $page): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $items = $this->extractProxiesShopItems($source);

        $rows = [];
        foreach ($items as $item) {
            foreach ($this->flattenProxiesShopItem($item) as $row) {
                $rows[] = $row;
            }
        }
        $rows = array_values(array_filter($rows, fn(array $row): bool => $this->isAllowedProxiesListRow($row)));

        $rows = $this->enrichProxiesListRows($rows);

        $columns = $this->collectTableColumns($rows);
        $filters = $this->collectColumnFilters($rows, $columns);

        $countTotal = (int)($source['count'] ?? count($rows));

        return Sogerien::Debager()->capture_return([
            'columns' => $columns,
            'filters' => $filters,
            'rows' => $rows,
            'count' => count($rows),
            'count_total' => $countTotal,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'page' => $page,
                'next' => (string)($source['next'] ?? ''),
                'previous' => (string)($source['previous'] ?? ''),
                'currentPage' => (int)($source['currentPage'] ?? $page),
                'nextPage' => (int)($source['nextPage'] ?? 0),
                'prevPage' => (int)($source['prevPage'] ?? 0),
            ],
        ], __CLASS__, __FUNCTION__);
}

    /**
     * @param array<string,mixed> $row
     */
    private function isAllowedProxiesListRow(array $row): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $id = trim((string)($row['id'] ?? ''));
        if ($id === '') {
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $category = strtolower(trim((string)($row['proxy_category'] ?? '')));
        if ($category === 'residential_static') {
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function enrichProxiesListRows(array $rows): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        foreach ($rows as &$row) {
            $priceUsd = is_numeric((string)($row['price_usd'] ?? '')) ? (float)$row['price_usd'] : null;
            $days = is_numeric((string)($row['days'] ?? '')) ? (int)$row['days'] : 0;
            $trafficRaw = $row['traffic_limitation'] ?? null;

            if ($priceUsd !== null && $days > 0) {
                $row['price_per_day'] = round($priceUsd / $days, 4);
            } else {
                $row['price_per_day'] = null;
            }

            $trafficInt = is_numeric((string)$trafficRaw) ? (int)$trafficRaw : null;
            if ($trafficInt === -1) {
                $row['traffic_limitation'] = -1;
            } elseif ($trafficInt !== null && $trafficInt > 0) {
                $row['traffic_limitation'] = round($trafficInt / 1024, 2);
            }

            $trafficGb = $row['traffic_limitation'] ?? null;
            if ($priceUsd !== null && $trafficGb !== null && $trafficGb !== -1 && $trafficGb > 0) {
                $row['price_per_gb'] = round($priceUsd / $trafficGb, 4);
            } elseif ($trafficGb === -1) {
                $row['price_per_gb'] = -1;
            } else {
                $row['price_per_gb'] = null;
            }
        }
        unset($row);
        return Sogerien::Debager()->capture_return($rows, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $source
     * @return array<int,array<string,mixed>>
     */
    private function extractProxiesShopItems(array $source): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $paths = [
            ['data', 'results'],
            ['data', 'items'],
            ['data', 'proxies'],
            ['results'],
            ['items'],
            ['proxies'],
        ];

        foreach ($paths as $path) {
            $cursor = $source;
            $ok = true;

            foreach ($path as $segment) {
                if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                    $ok = false;
                    break;
                }
                $cursor = $cursor[$segment];
            }

            if (!$ok || !is_array($cursor) || !$this->isListArray($cursor)) {
                continue;
            }

            $items = [];
            foreach ($cursor as $row) {
                if (is_array($row)) {
                    $items[] = $row;
                }
            }
            return Sogerien::Debager()->capture_return($items, __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
}

    /**
     * @param array<string,mixed> $item
     * @return array<int,array<string,mixed>>
     */
    private function flattenProxiesShopItem(array $item): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $base = [
            'API' => 'Cyberyozh',
        ];
        foreach ($item as $key => $value) {
            if ($key === 'proxy_products') {
                continue;
            }
            $base[$key] = $this->flattenScalarForRow($key, $value);
        }

        $products = $item['proxy_products'] ?? null;
        if (is_array($products) && $this->isListArray($products) && count($products) > 0) {
            $rows = [];
            foreach ($products as $product) {
                if (!is_array($product)) {
                    continue;
                }

                $row = $base;
                foreach ($product as $key => $value) {
                    $row[$key] = $this->flattenScalarForRow((string)$key, $value);
                }
                $rows[] = $row;
            }

            return Sogerien::Debager()->capture_return($rows, __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return([$base], __CLASS__, __FUNCTION__);
}

    private function flattenScalarForRow(string $key, mixed $value): mixed
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if ($key === 'location_country_code') {
            $normalized = $this->normalizeCountryCodes($value);
            return Sogerien::Debager()->capture_return($normalized, __CLASS__, __FUNCTION__);
        }

        if (is_scalar($value) || $value === null) {
            return Sogerien::Debager()->capture_return($value, __CLASS__, __FUNCTION__);
        }

        if (is_array($value)) {
            $json = json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );
            return Sogerien::Debager()->capture_return(is_string($json) ? $json : '', __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return(trim((string)$value), __CLASS__, __FUNCTION__);
}

    private function normalizeCountryCodes(mixed $value): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $raw = trim((string)$value);
        if ($raw === '') {
            return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $parts = preg_split('/\s*,\s*/', strtoupper($raw)) ?: [];
        $parts = array_values(array_filter($parts, static fn(string $v): bool => $v !== ''));
        return Sogerien::Debager()->capture_return(implode(',', $parts), __CLASS__, __FUNCTION__);
}

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,string>
     */
    private function collectTableColumns(array $rows): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
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
            'proxy_category',
            'stock_status',
            'traffic_limitation',
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
    private function collectColumnFilters(array $rows, array $columns): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
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
                        if ($code === '') {
                            continue;
                        }
                        $sets[$column][$code] = true;
                    }
                    continue;
                }

                $value = trim((string)$row[$column]);
                if ($value === '') {
                    continue;
                }
                $sets[$column][$value] = true;
            }
        }

        $out = [];
        foreach ($columns as $column) {
            $values = array_keys($sets[$column]);
            $out[$column] = $this->sortFilterValues($values);
        }

        return Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
}

    /**
     * @param array<int,string> $values
     * @return array<int,string>
     */
    private function sortFilterValues(array $values): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        if ($values === []) {
            return Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
        }

        $allNumeric = true;
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                $allNumeric = false;
                break;
            }
        }

        if ($allNumeric) {
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
     * @param array<mixed> $value
     */
    private function isListArray(array $value): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $index = 0;
        foreach ($value as $k => $_v) {
            if ($k !== $index) {
                return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
            }
            $index++;
        }
        return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxyInfo(array $params): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $proxyId = (int)($params['proxy_id'] ?? 0);
        if ($proxyId <= 0) {
            $this->fail('proxy_id must be > 0');
            return   Sogerien::Debager()->capture_return(['ok' => false, 'error' => $this->error], __CLASS__, __FUNCTION__);
        }

        $resp = $this->request_json('GET', '/v1/proxies/' . rawurlencode((string)$proxyId) . '/');
        if ($resp !== null) {
            return   Sogerien::Debager()->capture_return($this->aliasResult($resp), __CLASS__, __FUNCTION__);
        }

        $resp = $this->request_json('GET', '/v1/proxies/history/' . rawurlencode((string)$proxyId) . '/');
        return   Sogerien::Debager()->capture_return($this->aliasResult($resp), __CLASS__, __FUNCTION__);
}

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function orderCreate(array $params): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $proxyId = (int)($params['proxy_id'] ?? 0);
        if ($proxyId <= 0) {
            $this->fail('proxy_id must be > 0');
            return   Sogerien::Debager()->capture_return(['ok' => false, 'error' => $this->error], __CLASS__, __FUNCTION__);
        }

        $resp = $this->buy_proxies([
            [
                'id' => (string)$proxyId,
                'auto_renew' => false,
            ],
        ]);

        return   Sogerien::Debager()->capture_return($this->aliasResult($resp), __CLASS__, __FUNCTION__);
}

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function userProxies(array $params = []): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $query = [];
        $userId = (int)($params['user_id'] ?? 0);
        if ($userId > 0) {
            $query['user_id'] = $userId;
        }

        $resp = $this->request_json('GET', '/v1/proxies/history/', $query);
        return   Sogerien::Debager()->capture_return($this->aliasResult($resp), __CLASS__, __FUNCTION__);
}

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxyChangeIp(array $params): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $proxyId = (int)($params['proxy_id'] ?? 0);
        if ($proxyId <= 0) {
            $this->fail('proxy_id must be > 0');
            return   Sogerien::Debager()->capture_return(['ok' => false, 'error' => $this->error], __CLASS__, __FUNCTION__);
        }

        $resp = $this->refresh_ip((string)$proxyId);
        return   Sogerien::Debager()->capture_return($this->aliasResult($resp), __CLASS__, __FUNCTION__);
}

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxyReset(array $params): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $proxyId = (int)($params['proxy_id'] ?? 0);
        if ($proxyId <= 0) {
            $this->fail('proxy_id must be > 0');
            return   Sogerien::Debager()->capture_return(['ok' => false, 'error' => $this->error], __CLASS__, __FUNCTION__);
        }

        $resp = $this->reboot_modem((string)$proxyId);
        return   Sogerien::Debager()->capture_return($this->aliasResult($resp), __CLASS__, __FUNCTION__);
}

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxyUpdateAuth(array $params): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $proxyId = (int)($params['proxy_id'] ?? 0);
        $login = trim((string)($params['login'] ?? ''));
        $password = trim((string)($params['password'] ?? ''));

        if ($proxyId <= 0) {
            $this->fail('proxy_id must be > 0');
            return   Sogerien::Debager()->capture_return(['ok' => false, 'error' => $this->error], __CLASS__, __FUNCTION__);
        }
        if ($login === '' || $password === '') {
            $this->fail('login/password must be non-empty');
            return   Sogerien::Debager()->capture_return(['ok' => false, 'error' => $this->error], __CLASS__, __FUNCTION__);
        }

        $body = [
            'login' => $login,
            'password' => $password,
        ];

        $resp = $this->request_json(
            'POST',
            '/v1/proxies/history/' . rawurlencode((string)$proxyId) . '/update-auth/',
            [],
            $body
        );
        if ($resp !== null) {
            return   Sogerien::Debager()->capture_return($this->aliasResult($resp), __CLASS__, __FUNCTION__);
        }

        $resp = $this->request_json(
            'POST',
            '/v1/proxies/user-proxy-server/update-auth/',
            [],
            [
                'id' => (string)$proxyId,
                'login' => $login,
                'password' => $password,
            ]
        );

        return   Sogerien::Debager()->capture_return($this->aliasResult($resp), __CLASS__, __FUNCTION__);
}

    /**
     * @param array<string,mixed>|null $resp
     * @return array<string,mixed>
     */
    private function aliasResult(?array $resp): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($resp === null) {
            return   Sogerien::Debager()->capture_return([
                'ok' => false,
                'error' => $this->error !== '' ? $this->error : 'request failed',
            ], __CLASS__, __FUNCTION__);
        }

        return   Sogerien::Debager()->capture_return([
            'ok' => true,
            'data' => $resp,
        ], __CLASS__, __FUNCTION__);
}

    private function validate_type_format(string $type_format): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $type_format = trim($type_format);
        $allowed = [
            'full_url' => true,
            'ip_port_user_pass' => true,
            'user_pass_at_ip_port' => true,
        ];

        if ($type_format === '' || !isset($allowed[$type_format])) {
            $this->fail('Invalid type_format');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    private function validate_number_provider(string $provider): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $allowed = [
            'virtual' => true,
            'virtual_rent' => true,
            'residential' => true,
        ];

        return   Sogerien::Debager()->capture_return($this->validate_enum_value($provider, $allowed, 'provider', false), __CLASS__, __FUNCTION__);
}

    private function validate_number_period(string $period): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $allowed = [
            'MIN_15' => true,
            'DAY' => true,
            'WEEK' => true,
            'MONTH' => true,
        ];

        return   Sogerien::Debager()->capture_return($this->validate_enum_value($period, $allowed, 'period', false), __CLASS__, __FUNCTION__);
}

    private function validate_provider_period(string $provider, string $period): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if (($provider === 'virtual' || $provider === 'residential') && $period !== 'MIN_15') {
            $this->fail('Invalid period for provider');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        if ($provider === 'virtual_rent' && $period === 'MIN_15') {
            $this->fail('Invalid period for provider');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    private function validate_numbers_country_code(string $country_code): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($country_code === '') {
            return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        }

        if (preg_match('/^[0-9]+$/', $country_code) !== 1) {
            $this->fail('Invalid numbers country code');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    private function validate_phone_e164(string $phone): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($phone === '') {
            $this->fail('Phone value is empty');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        if (preg_match('/^\+[1-9][0-9]{7,14}$/', $phone) !== 1) {
            $this->fail('Invalid phone format, expected E.164');
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
    }

    private function validate_access_type(string $access_type): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $allowed = [
            'shared' => true,
            'private' => true,
        ];

        return Sogerien::Debager()->capture_return($this->validate_enum_value($access_type, $allowed, 'access_type', true), __CLASS__, __FUNCTION__);
    }

    private function validate_country_code(string $country): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if ($country === '') {
            return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        }

        if (preg_match('/^[a-z]{2}$/', $country) !== 1) {
            $this->fail('Invalid country code');
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
    }

    private function validate_proxy_category(string $proxy_category): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $allowed = [
            'datacenter' => true,
            'lte' => true,
            'residential_rotating' => true,
            'residential_static' => true,
        ];

        return Sogerien::Debager()->capture_return($this->validate_enum_value($proxy_category, $allowed, 'proxy_category', true), __CLASS__, __FUNCTION__);
    }

    private function validate_stock_status(string $stock_status): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $allowed = [
            'in_stock' => true,
            'out_of_stock' => true,
        ];

        return Sogerien::Debager()->capture_return($this->validate_enum_value($stock_status, $allowed, 'stock_status', true), __CLASS__, __FUNCTION__);
    }

    private function validate_protocol(string $protocol): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $protocol = trim($protocol);
        if ($protocol === '') {
            return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        }

        $allowed = [
            'http' => true,
            'socks5' => true,
        ];

        if (!isset($allowed[$protocol])) {
            $this->fail('Invalid protocol');
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
    }

    private function validate_session_type(string $session_type): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $session_type = trim($session_type);
        $allowed = [
            'random' => true,
            'short_session' => true,
            'long_session' => true,
        ];

        if ($session_type === '' || !isset($allowed[$session_type])) {
            $this->fail('Invalid session_type');
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,bool> $allowed
     */
    private function validate_enum_value(string $value, array $allowed, string $field_name, bool $allow_empty): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $value = trim($value);
        if ($value === '' && $allow_empty) {
            return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        }

        if ($value === '' || !isset($allowed[$value])) {
            $this->fail('Invalid ' . $field_name);
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
    }

    private function normalize_required_id(string $id, string $label): ?string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $id = trim($id);
        if ($id === '') {
            $this->fail($label . ' is empty');
            return Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return($id, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        bool $expect_json = true
    ): array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $this->fail('');
        $this->last_http_code = 0;
        $this->last_url = '';

        $method = strtoupper(trim($method));
        if ($method === '') {
            $this->fail('HTTP method is empty');
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }

        $path = '/' . ltrim(trim($path), '/');
        if (!$this->has_api_key()) {
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }

        $url = rtrim($this->base_url, '/') . $path;
        if ($query !== []) {
            $qs = http_build_query($query);
            if ($qs !== '') {
                $url .= '?' . $qs;
            }
        }

        $this->last_url = $url;

        $headers = [
            'X-Api-Key: ' . $this->api_key,
            'Accept: ' . ($expect_json ? 'application/json' : 'text/plain'),
        ];

        $payload = null;
        if ($body !== null) {
            $jsonBody = json_encode(
                $body,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
            if (!is_string($jsonBody)) {
                $this->fail('Failed to encode request body');
                return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
            }
            $payload = $jsonBody;
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        if ($ch === false) {
            $this->fail('curl_init failed');
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $this->last_http_code = $httpCode;
        $rawText = is_string($raw) ? $raw : '';

        $decodedJson = null;
        if ($expect_json && $rawText !== '') {
            $decodedCandidate = json_decode($rawText, true);
            if (is_array($decodedCandidate)) {
                $decodedJson = $decodedCandidate;
            }
        }

        $debugEntry = [
            'ts' => date('c'),
            'method' => $method,
            'url' => $url,
            'http_code' => $httpCode,
            'request_headers' => $headers,
            'query' => $query,
            'request_body' => $body,
            'request_body_json' => $payload,
            'response_raw' => $rawText,
            'response_json' => $decodedJson,
            'curl_error' => $curlErr,
        ];
        $this->push_debug($debugEntry);
        $this->append_debug_log($debugEntry);

        if ($curlErr !== '') {
            $this->fail('CURL error: ' . $curlErr);
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->fail('HTTP error: ' . $httpCode);
            return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
        }

        if ($expect_json) {
            $json = json_decode($rawText, true);
            if (!is_array($json)) {
                $this->fail('Invalid JSON response');
                return Sogerien::Debager()->capture_return(['ok' => false], __CLASS__, __FUNCTION__);
            }

            $this->ok();
            return Sogerien::Debager()->capture_return([
                'ok' => true,
                'json' => $json,
                'raw' => $rawText,
                'http_code' => $httpCode,
            ], __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return Sogerien::Debager()->capture_return([
            'ok' => true,
            'raw' => $rawText,
            'http_code' => $httpCode,
        ], __CLASS__, __FUNCTION__);
    }

    private function has_api_key(): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if (trim($this->api_key) === '') {
            $this->fail('api_key is empty');
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,mixed> $entry
     */
    private function push_debug(array $entry): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $this->debug = $entry;
        if (!$this->debug_enabled) {
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }

        $this->debug_history[] = $entry;
        if (count($this->debug_history) > 200) {
            array_splice($this->debug_history, 0, count($this->debug_history) - 200);
        }
    }

    /**
     * @param array<string,mixed> $entry
     */
    private function append_debug_log(array $entry): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $path = $this->debug_log_file_path();
        if ($path === '') {
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $json = json_encode(
            $entry,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if (!is_string($json) || $json === '') {
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }

        @file_put_contents($path, $json . PHP_EOL, FILE_APPEND);
    }

    private function debug_log_file_path(): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $base = trim((string)Sogerien::$SOGERIEN_DIR);
        if ($base === '') {
            return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return(
            rtrim($base, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->debug_log_relative_path),
            __CLASS__,
            __FUNCTION__
        );
    }

    private function ok(): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $this->status = true;
        $this->error = '';
}

    private function fail(string $msg): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->status = false;
        $this->error = $msg;
}
}

