<?php
declare(strict_types=1);

final class APIiproxyonline
{
    use SogerienClassHelp;

    public bool $status = false;
    public string $error = '';

    public string $connection_api_key = '';
    public string $base_url = 'https://iproxy.online';
    public string $action_links_public_base_url = 'https://i.fxdx.in';

    public bool $debug_enabled = true;
    public array $debug = [];
    public array $debug_history = [];

    public int $last_http_code = 0;
    public string $last_url = '';
    public int $request_connect_timeout_seconds = 10;
    public int $request_timeout_seconds = 45;

    private const CN_BASE_PATH = '/api/cn/v1';
    private const CONSOLE_BASE_PATH = '/api/console/v1';

    /** @var array<string,bool> */
    private const ACTION_LINK_ACTIONS = [
        'reboot' => true,
        'changeip' => true,
    ];

    /** @var array<string,bool> */
    private const COMMAND_ACTIONS = [
        'change_connection' => true,
        'changeip' => true,
        'debug_report' => true,
        'find_my_device' => true,
        'fix_lte' => true,
        'refresh_fingerprint' => true,
        'refresh' => true,
        'reboot' => true,
        'speed_test' => true,
        'toggle_proxy' => true,
        'upgrade_app' => true,
        'upload_logs' => true,
    ];

    /** @var array<string,bool> */
    private const TCP_FINGERPRINTS = [
        'WinXP' => true,
        'Win78' => true,
        'Win10' => true,
        'Win11' => true,
        'WinNT' => true,
        'Nintendo' => true,
        'FreeBSD' => true,
        'FreeBSD9' => true,
        'MacOS' => true,
        'iOS' => true,
    ];

    public function set_api_key(string $connection_api_key): void
    {
        $this->connection_api_key = trim($connection_api_key);
        $this->ok();
    }

    public function set_connection_api_key(string $connection_api_key): void
    {
        $this->set_api_key($connection_api_key);
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

    public function set_action_links_public_base_url(string $base_url): void
    {
        $base_url = trim($base_url);
        if ($base_url === '') {
            $this->fail('action_links_public_base_url is empty');
            return;
        }
        $this->action_links_public_base_url = rtrim($base_url, '/');
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
     * @return array<string,mixed>|null
     */
    public function request_connection_json(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        bool $allow_empty_json = false
    ): ?array {
        return $this->request_json($method, $this->join_path(self::CN_BASE_PATH, $path), $query, $body, true, $allow_empty_json);
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|array<int,mixed>|null $body
     * @return array<string,mixed>|null
     */
    public function request_console_json(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        bool $allow_empty_json = false
    ): ?array {
        return $this->request_json($method, $this->join_path(self::CONSOLE_BASE_PATH, $path), $query, $body, true, $allow_empty_json);
    }

    public function get_connection(): ?array
    {
        return $this->request_connection_json('GET', '/');
    }

    public function update_connection_basic_info(string $name = '', string $description = ''): ?array
    {
        $name = trim($name);
        $description = trim($description);
        $body = [];
        if ($name !== '') {
            $body['name'] = $name;
        }
        if ($description !== '') {
            $body['description'] = $description;
        }
        if ($body === []) {
            $this->fail('Nothing to update');
            return null;
        }
        return $this->request_connection_json('POST', '/update-basic-info', [], $body);
    }

    /**
     * @param array<string,mixed> $settings
     */
    public function update_connection_settings(array $settings): ?array
    {
        if ($settings === []) {
            $this->fail('settings is empty');
            return null;
        }
        if (!$this->validate_connection_settings($settings)) {
            return null;
        }
        return $this->request_connection_json('POST', '/update-settings', [], $settings);
    }

    /**
     * @param array<string,bool> $permissions
     */
    public function modify_team_access(string $email, array $permissions): ?array
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('email is invalid');
            return null;
        }
        if ($permissions === []) {
            $this->fail('permissions is empty');
            return null;
        }
        foreach ($permissions as $key => $value) {
            if (!is_string($key) || trim($key) === '' || !is_bool($value)) {
                $this->fail('permissions must be map<string,bool>');
                return null;
            }
        }

        return $this->request_connection_json('POST', '/team-access/modify', [], [
            'email' => $email,
            'permissions' => $permissions,
        ]);
    }

    public function remove_team_access(string $email): ?array
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('email is invalid');
            return null;
        }
        return $this->request_connection_json('POST', '/team-access/remove', [], ['email' => $email]);
    }

    public function change_connection_plan(string $plan_id): ?array
    {
        $plan_id = trim($plan_id);
        if ($plan_id === '') {
            $this->fail('plan_id is empty');
            return null;
        }
        return $this->request_connection_json('POST', '/change-plan', [], ['plan_id' => $plan_id]);
    }

    public function get_sms_history(): ?array
    {
        return $this->request_connection_json('GET', '/sms-history');
    }

    /**
     * @param array<string,mixed> $action_params
     */
    public function command_push(string $connection_id, string $action, array $action_params = []): ?array
    {
        $connection_id = trim($connection_id);
        $action = strtolower(trim($action));

        if ($connection_id === '') {
            $this->fail('connection_id is empty');
            return null;
        }
        if (!isset(self::COMMAND_ACTIONS[$action])) {
            $this->fail('Invalid action');
            return null;
        }

        $body = ['action' => $action];
        if ($action_params !== []) {
            $body[$action . '_params'] = $action_params;
        }

        return $this->request_console_json('POST', '/connection/' . rawurlencode($connection_id) . '/command-push', [], $body);
    }

    public function generate_pin_code(): ?array
    {
        return $this->request_connection_json('POST', '/pin-code');
    }

    public function create_action_link(string $action, string $comment = ''): ?array
    {
        $action = strtolower(trim($action));
        if (!isset(self::ACTION_LINK_ACTIONS[$action])) {
            $this->fail('action must be reboot|changeip');
            return null;
        }

        $body = ['action' => $action];
        $comment = trim($comment);
        if ($comment !== '') {
            $body['comment'] = $comment;
        }
        return $this->request_connection_json('POST', '/actionlinks', [], $body);
    }

    public function list_action_links(string $action = ''): ?array
    {
        $query = [];
        $action = strtolower(trim($action));
        if ($action !== '') {
            if (!isset(self::ACTION_LINK_ACTIONS[$action])) {
                $this->fail('action must be reboot|changeip');
                return null;
            }
            $query['action'] = $action;
        }
        return $this->request_connection_json('GET', '/actionlinks', $query);
    }

    public function delete_action_link(string $link_id): ?array
    {
        $link_id = trim($link_id);
        if ($link_id === '') {
            $this->fail('link_id is empty');
            return null;
        }
        return $this->request_connection_json('DELETE', '/actionlinks/' . rawurlencode($link_id));
    }

    public function execute_action_link(string $action, string $link_id): ?array
    {
        $action = strtolower(trim($action));
        $link_id = trim($link_id);
        if (!isset(self::ACTION_LINK_ACTIONS[$action])) {
            $this->fail('action must be reboot|changeip');
            return null;
        }
        if ($link_id === '') {
            $this->fail('link_id is empty');
            return null;
        }

        return $this->request_json(
            'GET',
            '/actionlinks/do/' . rawurlencode($action) . '/' . rawurlencode($link_id),
            [],
            null,
            false,
            false,
            $this->action_links_public_base_url
        );
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function create_proxy_access(array $payload): ?array
    {
        if (!$this->validate_proxy_access_payload($payload, false)) {
            return null;
        }
        return $this->request_connection_json('POST', '/proxy-access', [], $payload);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function update_proxy_access(string $proxy_id, array $payload): ?array
    {
        $proxy_id = trim($proxy_id);
        if ($proxy_id === '') {
            $this->fail('proxy_id is empty');
            return null;
        }
        if ($payload === []) {
            $this->fail('payload is empty');
            return null;
        }
        if (!$this->validate_proxy_access_payload($payload, true)) {
            return null;
        }
        return $this->request_connection_json('POST', '/proxy-access/' . rawurlencode($proxy_id) . '/update', [], $payload);
    }

    public function list_proxy_accesses(): ?array
    {
        return $this->request_connection_json('GET', '/proxy-access');
    }

    public function delete_proxy_access(string $proxy_id): ?array
    {
        $proxy_id = trim($proxy_id);
        if ($proxy_id === '') {
            $this->fail('proxy_id is empty');
            return null;
        }
        return $this->request_connection_json('DELETE', '/proxy-access/' . rawurlencode($proxy_id));
    }

    /**
     * @param array<int,array<string,mixed>> $outbound_rules
     */
    public function modify_connection_outbound_rules(array $outbound_rules): bool
    {
        if (!$this->validate_outbound_rules($outbound_rules)) {
            return false;
        }
        $resp = $this->request_connection_json(
            'POST',
            '/traffic-acl/outbound-rules/modify',
            [],
            ['outbound_rules' => $outbound_rules],
            true
        );
        if ($resp === null && $this->last_http_code !== 204) {
            return false;
        }
        $this->ok();
        return true;
    }

    public function get_connection_outbound_rules(): ?array
    {
        return $this->request_connection_json('GET', '/traffic-acl/outbound-rules');
    }

    /**
     * @param array<int,array<string,mixed>> $outbound_rules
     */
    public function modify_proxy_outbound_rules(string $proxy_id, array $outbound_rules): bool
    {
        $proxy_id = trim($proxy_id);
        if ($proxy_id === '') {
            $this->fail('proxy_id is empty');
            return false;
        }
        if (!$this->validate_outbound_rules($outbound_rules)) {
            return false;
        }
        $resp = $this->request_connection_json(
            'POST',
            '/proxy-access/' . rawurlencode($proxy_id) . '/traffic-acl/outbound-rules/modify',
            [],
            ['outbound_rules' => $outbound_rules],
            true
        );
        if ($resp === null && $this->last_http_code !== 204) {
            return false;
        }
        $this->ok();
        return true;
    }

    public function get_proxy_outbound_rules(string $proxy_id): ?array
    {
        $proxy_id = trim($proxy_id);
        if ($proxy_id === '') {
            $this->fail('proxy_id is empty');
            return null;
        }
        return $this->request_connection_json('GET', '/proxy-access/' . rawurlencode($proxy_id) . '/traffic-acl/outbound-rules');
    }

    public function create_ovpn_access(string $name, string $description = ''): ?array
    {
        $name = trim($name);
        $description = trim($description);
        if ($name === '') {
            $this->fail('name is empty');
            return null;
        }
        $body = ['name' => $name];
        if ($description !== '') {
            $body['description'] = $description;
        }
        return $this->request_connection_json('POST', '/ovpn-access', [], $body);
    }

    public function list_ovpn_accesses(): ?array
    {
        return $this->request_connection_json('GET', '/ovpn-access');
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function update_ovpn_access(string $ovpn_id, array $payload): ?array
    {
        $ovpn_id = trim($ovpn_id);
        if ($ovpn_id === '') {
            $this->fail('ovpn_id is empty');
            return null;
        }
        if ($payload === []) {
            $this->fail('payload is empty');
            return null;
        }
        return $this->request_connection_json('POST', '/ovpn-access/' . rawurlencode($ovpn_id) . '/update', [], $payload);
    }

    public function delete_ovpn_access(string $ovpn_id): ?array
    {
        $ovpn_id = trim($ovpn_id);
        if ($ovpn_id === '') {
            $this->fail('ovpn_id is empty');
            return null;
        }
        return $this->request_connection_json('DELETE', '/ovpn-access/' . rawurlencode($ovpn_id));
    }

    public function traffic_by_day(string $from_rfc3339, string $to_rfc3339, string $timezone = ''): ?array
    {
        $from_rfc3339 = trim($from_rfc3339);
        $to_rfc3339 = trim($to_rfc3339);
        $timezone = trim($timezone);
        if ($from_rfc3339 === '' || $to_rfc3339 === '') {
            $this->fail('from/to are required');
            return null;
        }
        $query = ['from' => $from_rfc3339, 'to' => $to_rfc3339];
        if ($timezone !== '') {
            $query['timezone'] = $timezone;
        }
        return $this->request_connection_json('GET', '/traffic/by-day', $query);
    }

    public function traffic_by_hour_port(string $from_rfc3339, string $to_rfc3339): ?array
    {
        $from_rfc3339 = trim($from_rfc3339);
        $to_rfc3339 = trim($to_rfc3339);
        if ($from_rfc3339 === '' || $to_rfc3339 === '') {
            $this->fail('from/to are required');
            return null;
        }
        return $this->request_connection_json('GET', '/traffic/by-hour-port', ['from' => $from_rfc3339, 'to' => $to_rfc3339]);
    }

    public function uptime(string $from_rfc3339, string $to_rfc3339): ?array
    {
        $from_rfc3339 = trim($from_rfc3339);
        $to_rfc3339 = trim($to_rfc3339);
        if ($from_rfc3339 === '' || $to_rfc3339 === '') {
            $this->fail('from/to are required');
            return null;
        }
        return $this->request_connection_json('GET', '/uptime', ['from' => $from_rfc3339, 'to' => $to_rfc3339]);
    }

    public function ip_history(string $from_rfc3339, string $to_rfc3339): ?array
    {
        $from_rfc3339 = trim($from_rfc3339);
        $to_rfc3339 = trim($to_rfc3339);
        if ($from_rfc3339 === '' || $to_rfc3339 === '') {
            $this->fail('from/to are required');
            return null;
        }
        return $this->request_connection_json('GET', '/ip-history', ['from' => $from_rfc3339, 'to' => $to_rfc3339]);
    }

    private function join_path(string $base_path, string $path): string
    {
        $base_path = '/' . trim($base_path, '/');
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return $base_path . '/';
        }
        return $base_path . '/' . ltrim($path, '/');
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|array<int,mixed>|null $body
     */
    private function request_json(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        bool $auth_required = true,
        bool $allow_empty_json = false,
        string $base_url_override = ''
    ): ?array {
        $resp = $this->request(
            $method,
            $path,
            $query,
            $body,
            $body === null ? 'none' : 'json',
            true,
            $auth_required,
            $allow_empty_json,
            $base_url_override
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
        string $base_url_override = ''
    ): array {
        $this->fail('');
        $this->last_http_code = 0;
        $this->last_url = '';

        $method = strtoupper(trim($method));
        if ($method === '') {
            $this->fail('HTTP method is empty');
            return ['ok' => false];
        }

        $url = $this->build_url($path, $query, $base_url_override);
        if ($url === null) {
            return ['ok' => false];
        }
        $this->last_url = $url;

        if ($auth_required && !$this->has_api_key()) {
            return ['ok' => false];
        }

        $headers = ['Accept: ' . ($expect_json ? 'application/json' : '*/*')];
        if ($auth_required) {
            $headers[] = 'Authorization: Bearer ' . $this->connection_api_key;
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
                $headers[] = 'Content-Type: application/json';
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
    private function build_url(string $path, array $query = [], string $base_url_override = ''): ?string
    {
        $path = trim($path);
        if ($path === '') {
            $this->fail('path is empty');
            return null;
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            $url = $path;
        } else {
            $base_url = trim($base_url_override) !== '' ? $base_url_override : $this->base_url;
            $url = rtrim($base_url, '/') . '/' . ltrim($path, '/');
        }

        if ($query !== []) {
            $qs = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            if ($qs !== '') {
                $url .= (str_contains($url, '?') ? '&' : '?') . $qs;
            }
        }
        return $url;
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function validate_connection_settings(array $settings): bool
    {
        foreach ($settings as $key => $value) {
            if ($key === 'tcp_fingerprint') {
                if (!is_string($value)) {
                    $this->fail('tcp_fingerprint must be string');
                    return false;
                }
                $fingerprint = trim($value);
                if ($fingerprint !== '' && !isset(self::TCP_FINGERPRINTS[$fingerprint])) {
                    $this->fail('Invalid tcp_fingerprint');
                    return false;
                }
            }

            if ($key === 'dns') {
                if (!is_array($value)) {
                    $this->fail('dns must be array');
                    return false;
                }
                foreach ($value as $idx => $ip) {
                    if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                        $this->fail('dns[' . (string)$idx . '] must be valid IP');
                        return false;
                    }
                }
            }

            if ($key === 'macros_url') {
                if (!is_string($value)) {
                    $this->fail('macros_url must be string');
                    return false;
                }
                $url = trim($value);
                if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
                    $this->fail('macros_url must be valid URL');
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function validate_proxy_access_payload(array $payload, bool $is_update): bool
    {
        if (!$is_update && $payload === []) {
            $this->fail('payload is empty');
            return false;
        }

        if (isset($payload['listen_service'])) {
            $listen_service = strtolower(trim((string)$payload['listen_service']));
            if ($listen_service !== 'http' && $listen_service !== 'socks5') {
                $this->fail('listen_service must be http|socks5');
                return false;
            }
        } elseif (!$is_update) {
            $this->fail('listen_service is required');
            return false;
        }

        if (isset($payload['auth_type'])) {
            $auth_type = strtolower(trim((string)$payload['auth_type']));
            if ($auth_type !== 'userpass' && $auth_type !== 'noauth') {
                $this->fail('auth_type must be userpass|noauth');
                return false;
            }
        } elseif (!$is_update) {
            $this->fail('auth_type is required');
            return false;
        }

        if (isset($payload['acl_inbound_policy'])) {
            $policy = strtolower(trim((string)$payload['acl_inbound_policy']));
            if ($policy !== 'deny_except') {
                $this->fail('acl_inbound_policy must be deny_except');
                return false;
            }
        }

        if (isset($payload['acl_inbound_ips'])) {
            if (!is_array($payload['acl_inbound_ips'])) {
                $this->fail('acl_inbound_ips must be array');
                return false;
            }
            foreach ($payload['acl_inbound_ips'] as $idx => $ip) {
                if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                    $this->fail('acl_inbound_ips[' . (string)$idx . '] must be valid IP');
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param array<int,array<string,mixed>> $outbound_rules
     */
    private function validate_outbound_rules(array $outbound_rules): bool
    {
        foreach ($outbound_rules as $idx => $rule) {
            if (!is_array($rule)) {
                $this->fail('outbound_rules[' . (string)$idx . '] must be object');
                return false;
            }
            $match = trim((string)($rule['match'] ?? ''));
            $type = strtolower(trim((string)($rule['type'] ?? '')));
            if ($match === '') {
                $this->fail('outbound_rules[' . (string)$idx . '].match is required');
                return false;
            }
            if ($type !== 'deny') {
                $this->fail('outbound_rules[' . (string)$idx . '].type must be deny');
                return false;
            }
        }
        return true;
    }

    private function has_api_key(): bool
    {
        if (trim($this->connection_api_key) === '') {
            $this->fail('connection_api_key is empty');
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
