<?php
declare(strict_types=1);

final class APIhypeproxyio
{
    use SogerienClassHelp;

    public bool $status = false;
    public string $error = '';

    /**
     * JWT token for HypeProxy API.
     */
    public string $api_key = '';
    public string $base_url = 'https://api.hypeproxy.io';
    public string $secondary_base_url = 'https://hypeproxy-api.azurewebsites.net';
    public bool $use_query_auth = false;

    public bool $debug_enabled = true;
    public array $debug = [];
    public array $debug_history = [];

    public int $last_http_code = 0;
    public string $last_url = '';
    public int $request_connect_timeout_seconds = 10;
    public int $request_timeout_seconds = 45;

    /** @var array<string,bool> */
    private const BILLING_PERIODS = [
        'Daily' => true,
        'Weekly' => true,
        'Monthly' => true,
        'Yearly' => true,
    ];

    /** @var array<string,bool> */
    private const PAYMENT_METHODS = [
        'CreditCard' => true,
        'Cryptos' => true,
    ];

    public function set_api_key(string $api_key): void
    {
        $this->api_key = trim($api_key);
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

    public function set_secondary_base_url(string $base_url): void
    {
        $base_url = trim($base_url);
        if ($base_url === '') {
            $this->fail('secondary_base_url is empty');
            return;
        }
        $this->secondary_base_url = rtrim($base_url, '/');
        $this->ok();
    }

    public function set_use_query_auth(bool $use_query_auth): void
    {
        $this->use_query_auth = $use_query_auth;
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

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|array<int,mixed>|null $body
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function request_json(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        bool $auth_required = true,
        bool $allow_empty_json = false,
        string $content_type = 'application/json'
    ): array|null {
        $resp = $this->request(
            $method,
            $path,
            $query,
            $body,
            $body === null ? 'none' : 'json',
            true,
            $auth_required,
            $allow_empty_json,
            $content_type
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
        bool $auth_required = true,
        string $content_type = 'application/json'
    ): ?string {
        $resp = $this->request(
            $method,
            $path,
            $query,
            $body,
            $body_type,
            false,
            $auth_required,
            false,
            $content_type
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
     * GET /health
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function health(): array|null
    {
        return $this->request_json('GET', '/health', [], null, false);
    }

    /**
     * POST /v2/Authentication/SignIn
     *
     * @return array<string,mixed>|null
     */
    public function sign_in(string $email, string $password, string $otp_code = ''): ?array
    {
        $email = trim($email);
        $password = trim($password);
        $otp_code = trim($otp_code);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('email is invalid');
            return null;
        }
        if ($password === '') {
            $this->fail('password is empty');
            return null;
        }

        $payload = [
            'email' => $email,
            'password' => $password,
        ];
        if ($otp_code !== '') {
            $payload['otpCode'] = $otp_code;
        }

        $raw = $this->request_text(
            'POST',
            '/v2/Authentication/SignIn',
            [],
            $payload,
            'json',
            false,
            'application/json-patch+json'
        );
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            $this->fail('Empty token response');
            return null;
        }

        $decoded = json_decode($trimmed, true);

        if (is_string($decoded) && trim($decoded) !== '') {
            $token = trim($decoded);
            $this->api_key = $token;
            $this->ok();
            return ['token' => $token];
        }

        if (is_array($decoded)) {
            $token = trim((string)($decoded['token'] ?? $decoded['jwt'] ?? $decoded['accessToken'] ?? ''));
            if ($token !== '') {
                $this->api_key = $token;
                $this->ok();
                return [
                    'token' => $token,
                    'data' => $decoded,
                ];
            }

            $this->ok();
            return ['data' => $decoded];
        }

        if (preg_match('/^[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$/', $trimmed) === 1) {
            $this->api_key = $trimmed;
            $this->ok();
            return ['token' => $trimmed];
        }

        $this->ok();
        return ['raw' => $trimmed];
    }

    /**
     * GET /v2/Profile
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function profile_get(): array|null
    {
        return $this->request_json('GET', '/v2/Profile');
    }

    /**
     * POST /v2/Profile
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function profile_update(array $payload): array|null
    {
        if ($payload === []) {
            $this->fail('payload is empty');
            return null;
        }
        return $this->request_json('POST', '/v2/Profile', [], $payload);
    }

    /**
     * GET /v2/Products
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function products_list(string $sort = '', bool $reverse = false): array|null
    {
        $query = [];
        $sort = trim($sort);
        if ($sort !== '') {
            $query['sort'] = $sort;
        }
        if ($reverse) {
            $query['reverse'] = 'true';
        }
        return $this->request_json('GET', '/v2/Products', $query);
    }

    /**
     * GET /v2/Products/{productId}
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function product_get(string $product_id): array|null
    {
        $product_id = $this->normalize_required_uuid($product_id, 'product_id');
        if ($product_id === null) {
            return null;
        }
        return $this->request_json('GET', '/v2/Products/' . rawurlencode($product_id));
    }

    /**
     * POST /v2/Orders/Validate
     *
     * @param array<string,mixed> $model
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function orders_validate(array $model): array|null
    {
        $payload = $this->normalize_order_model($model);
        if ($payload === null) {
            return null;
        }
        return $this->request_json('POST', '/v2/Orders/Validate', [], $payload);
    }

    /**
     * POST /v2/Orders/Create
     *
     * @param array<string,mixed> $model
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function orders_create(array $model): array|null
    {
        $payload = $this->normalize_order_model($model);
        if ($payload === null) {
            return null;
        }
        return $this->request_json('POST', '/v2/Orders/Create', [], $payload);
    }

    /**
     * GET /v2/Renews/{productDetailsId}
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function renews_get(string $product_details_id): array|null
    {
        $product_details_id = $this->normalize_required_uuid($product_details_id, 'product_details_id');
        if ($product_details_id === null) {
            return null;
        }
        return $this->request_json('GET', '/v2/Renews/' . rawurlencode($product_details_id));
    }

    /**
     * POST /v2/Renews/{productDetailsId}?delay={minutes}
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function renews_set_delay(string $product_details_id, int $delay_minutes): array|null
    {
        $product_details_id = $this->normalize_required_uuid($product_details_id, 'product_details_id');
        if ($product_details_id === null) {
            return null;
        }
        if ($delay_minutes < 1) {
            $this->fail('delay must be >= 1 minute');
            return null;
        }

        return $this->request_json(
            'POST',
            '/v2/Renews/' . rawurlencode($product_details_id),
            ['delay' => $delay_minutes],
            null,
            true,
            true
        );
    }

    /**
     * DELETE /v2/Renews/{productDetailsId}
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function renews_delete(string $product_details_id): array|null
    {
        $product_details_id = $this->normalize_required_uuid($product_details_id, 'product_details_id');
        if ($product_details_id === null) {
            return null;
        }

        return $this->request_json(
            'DELETE',
            '/v2/Renews/' . rawurlencode($product_details_id),
            [],
            null,
            true,
            true
        );
    }

    /**
     * GET /v2/Renews/Execute/{productDetailsId}
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function renews_execute(string $product_details_id): array|null
    {
        $product_details_id = $this->normalize_required_uuid($product_details_id, 'product_details_id');
        if ($product_details_id === null) {
            return null;
        }

        return $this->request_json(
            'GET',
            '/v2/Renews/Execute/' . rawurlencode($product_details_id),
            [],
            null,
            true,
            true
        );
    }

    /**
     * GET /v2/Insight/Ping/{productDetailsId}
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function insight_ping(string $product_details_id): array|null
    {
        $product_details_id = $this->normalize_required_uuid($product_details_id, 'product_details_id');
        if ($product_details_id === null) {
            return null;
        }
        return $this->request_json('GET', '/v2/Insight/Ping/' . rawurlencode($product_details_id));
    }

    /**
     * GET /v2/Insight/Ip/{productDetailsId}
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function insight_ip(string $product_details_id): array|null
    {
        $product_details_id = $this->normalize_required_uuid($product_details_id, 'product_details_id');
        if ($product_details_id === null) {
            return null;
        }
        return $this->request_json('GET', '/v2/Insight/Ip/' . rawurlencode($product_details_id));
    }

    /**
     * GET /v2/Insight/IpDetails/{productDetailsId}
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function insight_ip_details(string $product_details_id): array|null
    {
        $product_details_id = $this->normalize_required_uuid($product_details_id, 'product_details_id');
        if ($product_details_id === null) {
            return null;
        }

        return $this->request_json(
            'GET',
            '/v2/Insight/IpDetails/' . rawurlencode($product_details_id),
            [],
            null,
            true,
            true
        );
    }

    /**
     * GET /v2/Insight/IpThreats/{productDetailsId}
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function insight_ip_threats(string $product_details_id): array|null
    {
        $product_details_id = $this->normalize_required_uuid($product_details_id, 'product_details_id');
        if ($product_details_id === null) {
            return null;
        }

        return $this->request_json(
            'GET',
            '/v2/Insight/IpThreats/' . rawurlencode($product_details_id),
            [],
            null,
            true,
            true
        );
    }

    /**
     * Alias for project pages.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxiesList(array $params = []): array
    {
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

        $sort = trim((string)($params['sort'] ?? ''));
        $reverse = (bool)($params['reverse'] ?? false);

        $resp = $this->products_list($sort, $reverse);
        if ($resp === null) {
            return $this->alias_result(null);
        }

        $rows = $this->extract_rows_for_list($resp);
        $count_total = count($rows);
        $rows = array_slice($rows, $offset, $limit);

        $columns = $this->collect_table_columns($rows);
        $filters = $this->collect_column_filters($rows, $columns);

        return [
            'ok' => true,
            'data' => [
                'columns' => $columns,
                'filters' => $filters,
                'rows' => $rows,
                'count' => count($rows),
                'count_total' => $count_total,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'page' => (int)floor($offset / $limit) + 1,
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxyInfo(array $params): array
    {
        $product_details_id = trim((string)($params['product_details_id'] ?? $params['proxy_id'] ?? $params['id'] ?? ''));
        if ($product_details_id === '') {
            $this->fail('product_details_id/proxy_id is empty');
            return ['ok' => false, 'error' => $this->error];
        }

        $resp = $this->insight_ip_details($product_details_id);
        if ($resp !== null) {
            return $this->alias_result($resp);
        }
        $resp = $this->insight_ip($product_details_id);
        if ($resp !== null) {
            return $this->alias_result($resp);
        }
        $resp = $this->renews_get($product_details_id);
        return $this->alias_result($resp);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function orderCreate(array $params): array
    {
        $model = [
            'productId' => (string)($params['productId'] ?? $params['product_id'] ?? ''),
            'locationId' => (string)($params['locationId'] ?? $params['location_id'] ?? ''),
            'billingPeriod' => (string)($params['billingPeriod'] ?? $params['billing_period'] ?? 'Monthly'),
            'paymentMethod' => (string)($params['paymentMethod'] ?? $params['payment_method'] ?? 'CreditCard'),
            'quantity' => (int)($params['quantity'] ?? 1),
            'isAutoRenewed' => (bool)($params['isAutoRenewed'] ?? $params['is_auto_renewed'] ?? false),
            'couponCode' => (string)($params['couponCode'] ?? $params['coupon_code'] ?? ''),
        ];

        $resp = $this->orders_create($model);
        return $this->alias_result($resp);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function userProxies(array $params = []): array
    {
        $resp = $this->profile_get();
        if ($resp === null) {
            return $this->alias_result(null);
        }

        return [
            'ok' => true,
            'data' => [
                'profile' => $resp,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxyChangeIp(array $params): array
    {
        $product_details_id = trim((string)($params['product_details_id'] ?? $params['proxy_id'] ?? $params['id'] ?? ''));
        if ($product_details_id === '') {
            $this->fail('product_details_id/proxy_id is empty');
            return ['ok' => false, 'error' => $this->error];
        }

        return $this->alias_result($this->renews_execute($product_details_id));
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxyReset(array $params): array
    {
        return $this->proxyChangeIp($params);
    }

    /**
     * HypeProxy API does not expose proxy auth change endpoint in current manual.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxyUpdateAuth(array $params): array
    {
        $this->fail('proxyUpdateAuth is not supported by HypeProxy API v2');
        return ['ok' => false, 'error' => $this->error];
    }

    /**
     * @param array<string,mixed>|array<int,mixed>|null $resp
     * @return array<string,mixed>
     */
    private function alias_result(array|null $resp): array
    {
        if ($resp === null) {
            return [
                'ok' => false,
                'error' => $this->error !== '' ? $this->error : 'request failed',
            ];
        }

        return [
            'ok' => true,
            'data' => $resp,
        ];
    }

    /**
     * @param array<string,mixed> $model
     * @return array<string,mixed>|null
     */
    private function normalize_order_model(array $model): ?array
    {
        $product_id = $this->normalize_required_uuid((string)($model['productId'] ?? ''), 'productId');
        if ($product_id === null) {
            return null;
        }

        $location_id = $this->normalize_required_uuid((string)($model['locationId'] ?? ''), 'locationId');
        if ($location_id === null) {
            return null;
        }

        $billing_period = trim((string)($model['billingPeriod'] ?? ''));
        if ($billing_period === '' || !isset(self::BILLING_PERIODS[$billing_period])) {
            $this->fail('billingPeriod must be Daily|Weekly|Monthly|Yearly');
            return null;
        }

        $payment_method = trim((string)($model['paymentMethod'] ?? ''));
        if ($payment_method === '' || !isset(self::PAYMENT_METHODS[$payment_method])) {
            $this->fail('paymentMethod must be CreditCard|Cryptos');
            return null;
        }

        $quantity = (int)($model['quantity'] ?? 0);
        if ($quantity <= 0) {
            $this->fail('quantity must be > 0');
            return null;
        }

        if (!array_key_exists('isAutoRenewed', $model) || !is_bool($model['isAutoRenewed'])) {
            $this->fail('isAutoRenewed must be bool');
            return null;
        }

        $payload = [
            'productId' => $product_id,
            'locationId' => $location_id,
            'billingPeriod' => $billing_period,
            'paymentMethod' => $payment_method,
            'quantity' => $quantity,
            'isAutoRenewed' => $model['isAutoRenewed'],
        ];

        $coupon_code = trim((string)($model['couponCode'] ?? ''));
        if ($coupon_code !== '') {
            $payload['couponCode'] = $coupon_code;
        }

        $this->ok();
        return $payload;
    }

    private function normalize_required_uuid(string $value, string $field_name): ?string
    {
        $value = trim($value);
        if ($value === '') {
            $this->fail($field_name . ' is empty');
            return null;
        }
        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $value) !== 1) {
            $this->fail($field_name . ' must be UUID');
            return null;
        }

        return strtolower($value);
    }

    /**
     * @param array<string,mixed>|array<int,mixed> $payload
     * @return array<int,array<string,mixed>>
     */
    private function extract_rows_for_list(array $payload): array
    {
        $source = [];
        if ($this->is_list_array($payload)) {
            $source = $payload;
        } elseif (isset($payload['data']) && is_array($payload['data']) && $this->is_list_array($payload['data'])) {
            $source = $payload['data'];
        } elseif (isset($payload['items']) && is_array($payload['items']) && $this->is_list_array($payload['items'])) {
            $source = $payload['items'];
        } elseif (isset($payload['results']) && is_array($payload['results']) && $this->is_list_array($payload['results'])) {
            $source = $payload['results'];
        }

        $rows = [];
        foreach ($source as $row) {
            if (!is_array($row)) {
                continue;
            }
            $flat = $this->flatten_row($row);
            $flat['API'] = 'Hypeproxyio';
            if (!isset($flat['proxy_id']) && isset($flat['id'])) {
                $flat['proxy_id'] = $flat['id'];
            }
            $rows[] = $flat;
        }
        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function flatten_row(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $out[$key] = $value;
                continue;
            }

            if (is_array($value)) {
                $encoded = json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_INVALID_UTF8_SUBSTITUTE
                    | JSON_PARTIAL_OUTPUT_ON_ERROR
                );
                $out[$key] = is_string($encoded) ? $encoded : '';
                continue;
            }

            $out[$key] = (string)$value;
        }
        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,string>
     */
    private function collect_table_columns(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $column) {
                $name = (string)$column;
                if ($name !== '') {
                    $columns[$name] = true;
                }
            }
        }
        return array_values(array_keys($columns));
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $columns
     * @return array<string,array<int,string>>
     */
    private function collect_column_filters(array $rows, array $columns): array
    {
        $sets = [];
        foreach ($columns as $column) {
            $sets[$column] = [];
        }

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
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
            natcasesort($values);
            $out[$column] = array_values($values);
        }

        return $out;
    }

    /**
     * @param array<mixed> $value
     */
    private function is_list_array(array $value): bool
    {
        $index = 0;
        foreach ($value as $k => $_v) {
            if ($k !== $index) {
                return false;
            }
            $index++;
        }
        return true;
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
        bool $allow_empty_json = false,
        string $content_type = 'application/json'
    ): array {
        $this->fail('');
        $this->last_http_code = 0;
        $this->last_url = '';

        $method = strtoupper(trim($method));
        if ($method === '') {
            $this->fail('HTTP method is empty');
            return ['ok' => false];
        }

        $url = $this->build_url($path, $query);
        if ($url === null) {
            return ['ok' => false];
        }

        if ($auth_required) {
            if (!$this->has_api_key()) {
                return ['ok' => false];
            }
            if ($this->use_query_auth) {
                $url .= (str_contains($url, '?') ? '&' : '?') . 'apikey=' . rawurlencode($this->api_key);
            }
        }

        $this->last_url = $url;

        $headers = ['Accept: ' . ($expect_json ? 'application/json' : '*/*')];
        if ($auth_required && !$this->use_query_auth) {
            $headers[] = 'Authorization: Bearer ' . $this->api_key;
        }

        $payload = null;
        $body_type = strtolower(trim($body_type));
        if ($body !== null && $body_type === 'none') {
            $body_type = 'json';
        }

        if ($body !== null) {
            if ($body_type === 'json') {
                if (!is_array($body)) {
                    $this->fail('JSON body must be array');
                    return ['ok' => false];
                }
                $payload = json_encode(
                    $body,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_INVALID_UTF8_SUBSTITUTE
                    | JSON_PARTIAL_OUTPUT_ON_ERROR
                );
                if (!is_string($payload)) {
                    $this->fail('Failed to encode JSON body');
                    return ['ok' => false];
                }
                $headers[] = 'Content-Type: ' . trim($content_type);
            } elseif ($body_type === 'raw') {
                if (!is_string($body)) {
                    $this->fail('Raw body must be string');
                    return ['ok' => false];
                }
                $payload = $body;
            } else {
                $this->fail('Unsupported body_type');
                return ['ok' => false];
            }
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
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        if ($payload !== null && $payload !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $raw = curl_exec($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        $this->last_http_code = $http_code;
        $raw_text = is_string($raw) ? $raw : '';

        $this->push_debug([
            'ts' => date('c'),
            'method' => $method,
            'url' => $url,
            'query' => $query,
            'body_type' => $body_type,
            'request_body' => $body,
            'request_payload' => $payload,
            'http_code' => $http_code,
            'response_raw' => $raw_text,
            'curl_error' => $curl_error,
        ]);

        if ($curl_error !== '') {
            $this->fail('CURL error: ' . $curl_error);
            return ['ok' => false];
        }

        if ($http_code < 200 || $http_code >= 300) {
            $text = trim($raw_text);
            if (strlen($text) > 320) {
                $text = substr($text, 0, 320) . '...';
            }
            $suffix = $text !== '' ? (' - ' . $text) : '';
            $this->fail('HTTP error: ' . (string)$http_code . $suffix);
            return ['ok' => false];
        }

        if (!$expect_json) {
            $this->ok();
            return ['ok' => true, 'raw' => $raw_text, 'http_code' => $http_code];
        }

        if (trim($raw_text) === '') {
            if (!$allow_empty_json) {
                $this->fail('Empty JSON response');
                return ['ok' => false];
            }
            $this->ok();
            return ['ok' => true, 'raw' => $raw_text, 'json' => [], 'http_code' => $http_code];
        }

        $json = json_decode($raw_text, true);
        if (!is_array($json)) {
            $this->fail('Invalid JSON response');
            return ['ok' => false];
        }

        $this->ok();
        return ['ok' => true, 'raw' => $raw_text, 'json' => $json, 'http_code' => $http_code];
    }

    /**
     * @param array<string,mixed> $query
     */
    private function build_url(string $path, array $query = []): ?string
    {
        $path = trim($path);
        if ($path === '') {
            $this->fail('path is empty');
            return null;
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            $url = $path;
        } else {
            $url = rtrim($this->base_url, '/') . '/' . ltrim($path, '/');
        }

        if ($query !== []) {
            $qs = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            if ($qs !== '') {
                $url .= (str_contains($url, '?') ? '&' : '?') . $qs;
            }
        }

        return $url;
    }

    private function has_api_key(): bool
    {
        if (trim($this->api_key) === '') {
            $this->fail('api_key is empty');
            return false;
        }
        return true;
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
