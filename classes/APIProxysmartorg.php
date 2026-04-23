<?php
declare(strict_types=1);

final class APIProxysmartorg
{
    public bool $status = false;
    public string $error = '';

    public string $base_url = 'http://localhost:8080';
    public string $username = 'proxy';
    public string $password = 'proxy';

    public bool $debug_enabled = true;
    public array $debug = [];
    public array $debug_history = [];

    public int $last_http_code = 0;
    public string $last_url = '';
    public int $request_connect_timeout_seconds = 10;
    public int $request_timeout_seconds = 45;

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

    public function set_auth(string $username, string $password): void
    {
        $username = trim($username);
        $password = trim($password);

        if ($username === '' || $password === '') {
            $this->fail('username/password is empty');
            return;
        }

        $this->username = $username;
        $this->password = $password;
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
     * @param array<string,mixed>|array<int,mixed>|string|null $body
     * @param array<int,string> $headers
     * @return array<string,mixed>|null
     */
    public function request_json(
        string $method,
        string $path,
        array $query = [],
        array|string|null $body = null,
        string $body_type = 'none',
        bool $auth_required = true,
        array $headers = []
    ): ?array {
        $resp = $this->request(
            $method,
            $path,
            $query,
            $body,
            $body_type,
            true,
            $auth_required,
            $headers
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
     * @param array<int,string> $headers
     */
    public function request_text(
        string $method,
        string $path,
        array $query = [],
        array|string|null $body = null,
        string $body_type = 'none',
        bool $auth_required = true,
        array $headers = []
    ): ?string {
        $resp = $this->request(
            $method,
            $path,
            $query,
            $body,
            $body_type,
            false,
            $auth_required,
            $headers
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
     * POST /crud/store_modem
     *
     * @param array<string,mixed> $modem
     * @return array<string,mixed>|null
     */
    public function store_modem(array $modem): ?array
    {
        $imei = trim((string)($modem['IMEI'] ?? ''));
        $name = trim((string)($modem['name'] ?? ''));

        if (!$this->validate_imei($imei)) {
            return null;
        }
        if ($name === '') {
            $this->fail('name is empty');
            return null;
        }

        $json = $this->encode_json($modem);
        if ($json === null) {
            return null;
        }

        return $this->request_json(
            'POST',
            '/crud/store_modem',
            [],
            ['data' => $json],
            'form'
        );
    }

    /**
     * POST /modem/settings
     *
     * @return array<string,mixed>|null
     */
    public function apply_modem_settings(string $imei): ?array
    {
        $imei = trim($imei);
        if (!$this->validate_imei($imei)) {
            return null;
        }

        return $this->request_json('POST', '/modem/settings', [], ['imei' => $imei], 'form');
    }

    /**
     * GET /apix/show_status_json
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function show_status_json(): array|null
    {
        return $this->request_json('GET', '/apix/show_status_json');
    }

    /**
     * GET /apix/show_single_status_json?arg=...
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function show_single_status_json(string $arg): array|null
    {
        $arg = trim($arg);
        if ($arg === '') {
            $this->fail('arg is empty');
            return null;
        }

        return $this->request_json('GET', '/apix/show_single_status_json', ['arg' => $arg]);
    }

    /**
     * GET /apix/reset_modem_by_imei?IMEI=...
     *
     * @return array<string,mixed>|null
     */
    public function reset_modem_by_imei(string $imei): ?array
    {
        $imei = trim($imei);
        if (!$this->validate_imei($imei)) {
            return null;
        }

        return $this->request_json('GET', '/apix/reset_modem_by_imei', ['IMEI' => $imei]);
    }

    /**
     * GET /apix/reset_modem_by_nick?NICK=...
     *
     * @return array<string,mixed>|null
     */
    public function reset_modem_by_nick(string $nick): ?array
    {
        $nick = trim($nick);
        if ($nick === '') {
            $this->fail('NICK is empty');
            return null;
        }

        return $this->request_json('GET', '/apix/reset_modem_by_nick', ['NICK' => $nick]);
    }

    /**
     * GET /apix/reboot_modem_by_imei?IMEI=...
     *
     * @return array<string,mixed>|null
     */
    public function reboot_modem_by_imei(string $imei): ?array
    {
        $imei = trim($imei);
        if (!$this->validate_imei($imei)) {
            return null;
        }

        return $this->request_json('GET', '/apix/reboot_modem_by_imei', ['IMEI' => $imei]);
    }

    /**
     * GET /apix/reboot_modem_by_nick?NICK=...
     *
     * @return array<string,mixed>|null
     */
    public function reboot_modem_by_nick(string $nick): ?array
    {
        $nick = trim($nick);
        if ($nick === '') {
            $this->fail('NICK is empty');
            return null;
        }

        return $this->request_json('GET', '/apix/reboot_modem_by_nick', ['NICK' => $nick]);
    }

    /**
     * GET /apix/get_rotation_log?arg=...
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function get_rotation_log(string $arg): array|null
    {
        $arg = trim($arg);
        if ($arg === '') {
            $this->fail('arg is empty');
            return null;
        }

        return $this->request_json('GET', '/apix/get_rotation_log', ['arg' => $arg]);
    }

    /**
     * GET /apix/usb_reset_modem_json?arg=...
     *
     * @return array<string,mixed>|null
     */
    public function usb_reset_modem_json(string $arg): ?array
    {
        $arg = trim($arg);
        if ($arg === '') {
            $this->fail('arg is empty');
            return null;
        }

        return $this->request_json('GET', '/apix/usb_reset_modem_json', ['arg' => $arg]);
    }

    /**
     * POST /modem/send-sms
     *
     * @return array<string,mixed>|null
     */
    public function send_sms(string $imei, string $phone, string $sms): ?array
    {
        $imei = trim($imei);
        $phone = trim($phone);
        $sms = trim($sms);

        if (!$this->validate_imei($imei)) {
            return null;
        }
        if ($phone === '') {
            $this->fail('phone is empty');
            return null;
        }
        if ($sms === '') {
            $this->fail('sms is empty');
            return null;
        }

        return $this->request_json(
            'POST',
            '/modem/send-sms',
            [],
            [
                'imei' => $imei,
                'phone' => $phone,
                'sms' => $sms,
            ],
            'form'
        );
    }

    /**
     * POST /modem/send-ussd
     *
     * @return array<string,mixed>|null
     */
    public function send_ussd(string $imei, string $ussd): ?array
    {
        $imei = trim($imei);
        $ussd = trim($ussd);

        if (!$this->validate_imei($imei)) {
            return null;
        }
        if ($ussd === '') {
            $this->fail('ussd is empty');
            return null;
        }

        return $this->request_json(
            'POST',
            '/modem/send-ussd',
            [],
            [
                'imei' => $imei,
                'ussd' => $ussd,
            ],
            'form'
        );
    }

    /**
     * GET /apix/speedtest?arg=...
     *
     * @return array<string,mixed>|null
     */
    public function speedtest(string $arg): ?array
    {
        $arg = trim($arg);
        if ($arg === '') {
            $this->fail('arg is empty');
            return null;
        }

        return $this->request_json('GET', '/apix/speedtest', ['arg' => $arg]);
    }

    /**
     * GET /modem/sms/{imei}?json=1
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function read_sms(string $imei, bool $json = true): array|null
    {
        $imei = trim($imei);
        if (!$this->validate_imei($imei)) {
            return null;
        }

        $query = $json ? ['json' => 1] : [];
        return $this->request_json('GET', '/modem/sms/' . rawurlencode($imei), $query);
    }

    /**
     * GET /apix/purge_sms_json?arg=...
     *
     * @return array<string,mixed>|null
     */
    public function purge_sms_json(string $arg): ?array
    {
        $arg = trim($arg);
        if ($arg === '') {
            $this->fail('arg is empty');
            return null;
        }

        return $this->request_json('GET', '/apix/purge_sms_json', ['arg' => $arg]);
    }

    /**
     * @param array<string,mixed> $port
     * @return array<string,mixed>|null
     */
    public function store_port(array $port): ?array
    {
        $imei = trim((string)($port['IMEI'] ?? ''));
        $port_id = trim((string)($port['portID'] ?? ''));
        $port_name = trim((string)($port['portName'] ?? ''));
        $proxy_login = trim((string)($port['proxy_login'] ?? ''));
        $proxy_password = trim((string)($port['proxy_password'] ?? ''));
        $http_port = (int)($port['http_port'] ?? 0);
        $socks_port = (int)($port['socks_port'] ?? 0);

        if (!$this->validate_imei($imei)) {
            return null;
        }
        if ($port_id === '') {
            $this->fail('portID is empty');
            return null;
        }
        if ($port_name === '') {
            $this->fail('portName is empty');
            return null;
        }
        if (strlen($proxy_login) < 1 || strlen($proxy_password) < 1) {
            $this->fail('proxy_login/proxy_password is empty');
            return null;
        }
        if ($http_port <= 0) {
            $this->fail('http_port must be > 0');
            return null;
        }
        if ($socks_port <= 0) {
            $this->fail('socks_port must be > 0');
            return null;
        }

        $json = $this->encode_json($port);
        if ($json === null) {
            return null;
        }

        return $this->request_json(
            'POST',
            '/crud/store_port',
            [],
            ['data' => $json],
            'form'
        );
    }

    /**
     * GET /apix/apply_port?arg=...
     *
     * @return array<string,mixed>|null
     */
    public function apply_port(string $port_id): ?array
    {
        $port_id = trim($port_id);
        if ($port_id === '') {
            $this->fail('portID is empty');
            return null;
        }

        return $this->request_json('GET', '/apix/apply_port', ['arg' => $port_id]);
    }

    /**
     * GET /apix/list_ports_json
     *
     * @return array<string,mixed>|array<int,mixed>|null
     */
    public function list_ports_json(): array|null
    {
        return $this->request_json('GET', '/apix/list_ports_json');
    }

    /**
     * GET /apix/get_free_tcp_ports
     *
     * @return array<int,mixed>|array<string,mixed>|null
     */
    public function get_free_tcp_ports(): array|null
    {
        return $this->request_json('GET', '/apix/get_free_tcp_ports');
    }

    /**
     * GET /apix/purge_port?arg=...
     *
     * @return array<string,mixed>|null
     */
    public function purge_port(string $port_id): ?array
    {
        $port_id = trim($port_id);
        if ($port_id === '') {
            $this->fail('portID is empty');
            return null;
        }

        return $this->request_json('GET', '/apix/purge_port', ['arg' => $port_id]);
    }

    /**
     * GET /apix/top_hosts?arg=...
     *
     * @return array<string,mixed>|null
     */
    public function top_hosts(string $port_id): ?array
    {
        $port_id = trim($port_id);
        if ($port_id === '') {
            $this->fail('portID is empty');
            return null;
        }

        return $this->request_json('GET', '/apix/top_hosts', ['arg' => $port_id]);
    }

    /**
     * GET /apix/bandwidth_report_json?arg=...
     *
     * @return array<string,mixed>|null
     */
    public function bandwidth_report_json(string $port_id): ?array
    {
        $port_id = trim($port_id);
        if ($port_id === '') {
            $this->fail('portID is empty');
            return null;
        }

        return $this->request_json('GET', '/apix/bandwidth_report_json', ['arg' => $port_id]);
    }

    /**
     * GET /apix/get_counters_port?PORTID=...&START=...&END=...
     *
     * @return array<string,mixed>|null
     */
    public function get_counters_port(string $port_id, string $start, string $end): ?array
    {
        $port_id = trim($port_id);
        $start = trim($start);
        $end = trim($end);

        if ($port_id === '') {
            $this->fail('PORTID is empty');
            return null;
        }
        if ($start === '' || $end === '') {
            $this->fail('START/END is empty');
            return null;
        }

        return $this->request_json(
            'GET',
            '/apix/get_counters_port',
            [
                'PORTID' => $port_id,
                'START' => $start,
                'END' => $end,
            ]
        );
    }

    /**
     * GET /apix/bandwidth_report_all
     *
     * @return array<string,mixed>|null
     */
    public function bandwidth_report_all(): ?array
    {
        return $this->request_json('GET', '/apix/bandwidth_report_all');
    }

    /**
     * GET /apix/bandwidth_reset_counter?arg=...
     *
     * @return array<string,mixed>|null
     */
    public function bandwidth_reset_counter(string $port_id): ?array
    {
        $port_id = trim($port_id);
        if ($port_id === '') {
            $this->fail('portID is empty');
            return null;
        }

        return $this->request_json('GET', '/apix/bandwidth_reset_counter', ['arg' => $port_id]);
    }

    /**
     * GET /get_vpn_profile/{portID}.ovpn
     */
    public function download_vpn_profile(string $port_id, bool $auth_required = false): ?string
    {
        $port_id = trim($port_id);
        if ($port_id === '') {
            $this->fail('portID is empty');
            return null;
        }

        return $this->request_text(
            'GET',
            '/get_vpn_profile/' . rawurlencode($port_id) . '.ovpn',
            [],
            null,
            'none',
            $auth_required
        );
    }

    /**
     * GET /crud/backup_export
     *
     * @return array<string,mixed>|null
     */
    public function backup_export(): ?array
    {
        return $this->request_json('GET', '/crud/backup_export');
    }

    /**
     * GET /crud/backend_proxies
     *
     * @return array<int,mixed>|array<string,mixed>|null
     */
    public function backend_proxies_get(): array|null
    {
        return $this->request_json('GET', '/crud/backend_proxies');
    }

    /**
     * POST /crud/backend_proxies (application/json)
     *
     * @param array<int,array<string,mixed>> $items
     * @return array<string,mixed>|null
     */
    public function backend_proxies_store(array $items): ?array
    {
        if ($items === []) {
            $this->fail('items is empty');
            return null;
        }

        return $this->request_json(
            'POST',
            '/crud/backend_proxies',
            [],
            $items,
            'json',
            true,
            ['Content-Type: application/json']
        );
    }

    /**
     * GET /crud/lanmodems
     *
     * @return array<int,mixed>|array<string,mixed>|null
     */
    public function lanmodems_get(): array|null
    {
        return $this->request_json('GET', '/crud/lanmodems');
    }

    /**
     * POST /crud/lanmodems (application/json)
     *
     * @param array<int,array<string,mixed>> $items
     * @return array<string,mixed>|null
     */
    public function lanmodems_store(array $items): ?array
    {
        if ($items === []) {
            $this->fail('items is empty');
            return null;
        }

        return $this->request_json(
            'POST',
            '/crud/lanmodems',
            [],
            $items,
            'json',
            true,
            ['Content-Type: application/json']
        );
    }

    /**
     * GET /apix/unique_ips_json
     *
     * @return array<string,mixed>|null
     */
    public function unique_ips_json(): ?array
    {
        return $this->request_json('GET', '/apix/unique_ips_json');
    }

    /**
     * GET /apix/shop_report/{shop}/{period}
     *
     * @return array<string,mixed>|null
     */
    public function shop_report(string $shop, string $period): ?array
    {
        $shop = trim($shop);
        $period = trim($period);

        if ($shop === '') {
            $this->fail('shop is empty');
            return null;
        }
        if ($period === '') {
            $this->fail('period is empty');
            return null;
        }

        return $this->request_json(
            'GET',
            '/apix/shop_report/' . rawurlencode($shop) . '/' . rawurlencode($period)
        );
    }

    /**
     * GET /modem/common_status
     *
     * @return array<string,mixed>|null
     */
    public function common_status(): ?array
    {
        return $this->request_json('GET', '/modem/common_status');
    }

    /**
     * Alias: list ports as table dataset (same contract as other provider adapters).
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

        $resp = $this->list_ports_json();
        if ($resp === null) {
            return $this->alias_result(null);
        }

        $rows = $this->extract_ports_rows($resp);
        $rows = $this->filter_rows_by_params($rows, $params);
        $count_total = count($rows);

        $rows = array_slice($rows, $offset, $limit);
        $columns = $this->collect_table_columns($rows);
        $filters = $this->collect_column_filters($rows, $columns);

        $this->ok();
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
     * Alias: returns one proxy by portID.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxyInfo(array $params): array
    {
        $id = trim((string)($params['proxy_id'] ?? $params['portID'] ?? $params['id'] ?? ''));
        if ($id === '') {
            $this->fail('proxy_id/portID is empty');
            return ['ok' => false, 'error' => $this->error];
        }

        $resp = $this->list_ports_json();
        if ($resp === null) {
            return $this->alias_result(null);
        }

        $rows = $this->extract_ports_rows($resp);
        foreach ($rows as $row) {
            $row_id = trim((string)($row['portID'] ?? $row['id'] ?? ''));
            if ($row_id === $id) {
                return ['ok' => true, 'data' => $row];
            }
        }

        $this->fail('proxy not found by portID');
        return ['ok' => false, 'error' => $this->error];
    }

    /**
     * Alias: create proxy (port) and apply settings.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function orderCreate(array $params): array
    {
        $port = isset($params['port']) && is_array($params['port']) ? $params['port'] : $params;
        if (!isset($port['portID']) || trim((string)$port['portID']) === '') {
            $port['portID'] = 'port' . substr(bin2hex(random_bytes(8)), 0, 10);
        }

        $store = $this->store_port($port);
        if ($store === null) {
            return $this->alias_result(null);
        }

        $apply = $this->apply_port((string)$port['portID']);
        if ($apply === null) {
            return $this->alias_result(null);
        }

        return [
            'ok' => true,
            'data' => [
                'store_port' => $store,
                'apply_port' => $apply,
                'portID' => (string)$port['portID'],
            ],
        ];
    }

    /**
     * Alias: list user proxies.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function userProxies(array $params = []): array
    {
        $resp = $this->list_ports_json();
        if ($resp === null) {
            return $this->alias_result(null);
        }

        $rows = $this->extract_ports_rows($resp);
        $rows = $this->filter_rows_by_params($rows, $params);

        return [
            'ok' => true,
            'data' => [
                'items' => $rows,
                'rows' => $rows,
                'count' => count($rows),
            ],
        ];
    }

    /**
     * Alias: rotate modem IP by imei/nick/portID.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxyChangeIp(array $params): array
    {
        $imei = trim((string)($params['imei'] ?? ''));
        if ($imei !== '') {
            return $this->alias_result($this->reset_modem_by_imei($imei));
        }

        $nick = trim((string)($params['nick'] ?? $params['NICK'] ?? ''));
        if ($nick !== '') {
            return $this->alias_result($this->reset_modem_by_nick($nick));
        }

        $port_id = trim((string)($params['proxy_id'] ?? $params['portID'] ?? $params['id'] ?? ''));
        if ($port_id === '') {
            $this->fail('imei/nick/portID is empty');
            return ['ok' => false, 'error' => $this->error];
        }

        $imei_from_port = $this->resolve_imei_by_port_id($port_id);
        if ($imei_from_port === null) {
            return ['ok' => false, 'error' => $this->error];
        }

        return $this->alias_result($this->reset_modem_by_imei($imei_from_port));
    }

    /**
     * Alias: reboot modem by imei/nick/portID.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxyReset(array $params): array
    {
        $imei = trim((string)($params['imei'] ?? ''));
        if ($imei !== '') {
            return $this->alias_result($this->reboot_modem_by_imei($imei));
        }

        $nick = trim((string)($params['nick'] ?? $params['NICK'] ?? ''));
        if ($nick !== '') {
            return $this->alias_result($this->reboot_modem_by_nick($nick));
        }

        $port_id = trim((string)($params['proxy_id'] ?? $params['portID'] ?? $params['id'] ?? ''));
        if ($port_id === '') {
            $this->fail('imei/nick/portID is empty');
            return ['ok' => false, 'error' => $this->error];
        }

        $imei_from_port = $this->resolve_imei_by_port_id($port_id);
        if ($imei_from_port === null) {
            return ['ok' => false, 'error' => $this->error];
        }

        return $this->alias_result($this->reboot_modem_by_imei($imei_from_port));
    }

    /**
     * Alias: update auth by rewriting port object + apply.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxyUpdateAuth(array $params): array
    {
        $port_id = trim((string)($params['proxy_id'] ?? $params['portID'] ?? $params['id'] ?? ''));
        $login = trim((string)($params['login'] ?? ''));
        $password = trim((string)($params['password'] ?? ''));

        if ($port_id === '') {
            $this->fail('proxy_id/portID is empty');
            return ['ok' => false, 'error' => $this->error];
        }
        if ($login === '' || $password === '') {
            $this->fail('login/password is empty');
            return ['ok' => false, 'error' => $this->error];
        }

        $port_row = $this->resolve_port_row_by_port_id($port_id);
        if ($port_row === null) {
            return ['ok' => false, 'error' => $this->error];
        }

        $imei = trim((string)($port_row['IMEI'] ?? ''));
        $port_name = trim((string)($port_row['portName'] ?? $port_row['portID'] ?? ''));
        $http_port = (int)($port_row['HTTP_PORT'] ?? 0);
        $socks_port = (int)($port_row['SOCKS_PORT'] ?? 0);

        if (!$this->validate_imei($imei)) {
            return ['ok' => false, 'error' => $this->error];
        }
        if ($port_name === '') {
            $this->fail('portName is empty');
            return ['ok' => false, 'error' => $this->error];
        }
        if ($http_port <= 0 || $socks_port <= 0) {
            $this->fail('HTTP_PORT/SOCKS_PORT is invalid for update_auth');
            return ['ok' => false, 'error' => $this->error];
        }

        $store_payload = [
            'IMEI' => $imei,
            'portID' => $port_id,
            'portName' => $port_name,
            'proxy_login' => $login,
            'proxy_password' => $password,
            'http_port' => $http_port,
            'socks_port' => $socks_port,
        ];

        $store = $this->store_port($store_payload);
        if ($store === null) {
            return ['ok' => false, 'error' => $this->error];
        }

        $apply = $this->apply_port($port_id);
        if ($apply === null) {
            return ['ok' => false, 'error' => $this->error];
        }

        return [
            'ok' => true,
            'data' => [
                'store_port' => $store,
                'apply_port' => $apply,
            ],
        ];
    }

    /**
     * Alias: purge port by portID.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxyDelete(array $params): array
    {
        $port_id = trim((string)($params['proxy_id'] ?? $params['portID'] ?? $params['id'] ?? ''));
        if ($port_id === '') {
            $this->fail('proxy_id/portID is empty');
            return ['ok' => false, 'error' => $this->error];
        }

        return $this->alias_result($this->purge_port($port_id));
    }

    /**
     * @param array<string,mixed>|null $resp
     * @return array<string,mixed>
     */
    private function alias_result(?array $resp): array
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
     * @param array<string,mixed>|array<int,mixed> $payload
     * @return array<int,array<string,mixed>>
     */
    private function extract_ports_rows(array $payload): array
    {
        $candidates = [$payload];
        if (isset($payload['data']) && is_array($payload['data'])) {
            $candidates[] = $payload['data'];
        }

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            if ($this->is_list_array($candidate)) {
                $rows = [];
                foreach ($candidate as $row) {
                    if (is_array($row)) {
                        $rows[] = $this->flatten_port_row($row, '');
                    }
                }
                if ($rows !== []) {
                    return $rows;
                }
            }

            if ($this->looks_like_ports_map($candidate)) {
                $rows = [];
                foreach ($candidate as $imei => $items) {
                    if (!is_array($items)) {
                        continue;
                    }
                    foreach ($items as $item) {
                        if (is_array($item)) {
                            $rows[] = $this->flatten_port_row($item, (string)$imei);
                        }
                    }
                }
                if ($rows !== []) {
                    return $rows;
                }
            }
        }

        return [];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function flatten_port_row(array $row, string $imei): array
    {
        $out = [
            'API' => 'Proxysmartorg',
        ];

        if ($imei !== '' && !isset($row['IMEI'])) {
            $out['IMEI'] = $imei;
        }

        foreach ($row as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (is_array($value)) {
                $out[$key] = $this->encode_json($value) ?? '';
            } else {
                $out[$key] = $value;
            }
        }

        $out['portID'] = trim((string)($out['portID'] ?? ''));
        if ($out['portID'] !== '') {
            $out['id'] = $out['portID'];
            $out['proxy_id'] = $out['portID'];
        }

        $http_port = trim((string)($out['HTTP_PORT'] ?? ''));
        if ($http_port !== '') {
            $out['port'] = $http_port;
        }

        $login = trim((string)($out['LOGIN'] ?? ''));
        if ($login !== '') {
            $out['login'] = $login;
        }

        $status = '';
        $redirector_raw = trim((string)($out['redirector'] ?? ''));
        if ($redirector_raw !== '') {
            $decoded = json_decode($redirector_raw, true);
            if (is_array($decoded)) {
                $status = trim((string)($decoded['ActiveState'] ?? $decoded['SubState'] ?? ''));
            }
        }
        if ($status !== '') {
            $out['status'] = strtolower($status);
        }

        if (isset($out['PROXY_VALID_BEFORE'])) {
            $out['expire_at'] = (string)$out['PROXY_VALID_BEFORE'];
        }

        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    private function filter_rows_by_params(array $rows, array $params): array
    {
        $port_id = trim((string)($params['portID'] ?? $params['proxy_id'] ?? ''));
        $imei = trim((string)($params['imei'] ?? $params['IMEI'] ?? ''));
        $login = trim((string)($params['login'] ?? $params['LOGIN'] ?? ''));
        $status = strtolower(trim((string)($params['status'] ?? '')));
        $port_name = trim((string)($params['portName'] ?? ''));

        return array_values(array_filter($rows, static function (array $row) use ($port_id, $imei, $login, $status, $port_name): bool {
            if ($port_id !== '' && trim((string)($row['portID'] ?? '')) !== $port_id) {
                return false;
            }
            if ($imei !== '' && trim((string)($row['IMEI'] ?? '')) !== $imei) {
                return false;
            }
            if ($login !== '' && trim((string)($row['LOGIN'] ?? $row['login'] ?? '')) !== $login) {
                return false;
            }
            if ($status !== '' && strtolower(trim((string)($row['status'] ?? ''))) !== $status) {
                return false;
            }
            if ($port_name !== '' && trim((string)($row['portName'] ?? '')) !== $port_name) {
                return false;
            }
            return true;
        }));
    }

    private function resolve_imei_by_port_id(string $port_id): ?string
    {
        $port = $this->resolve_port_row_by_port_id($port_id);
        if ($port === null) {
            return null;
        }

        $imei = trim((string)($port['IMEI'] ?? ''));
        if (!$this->validate_imei($imei)) {
            return null;
        }
        return $imei;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolve_port_row_by_port_id(string $port_id): ?array
    {
        $port_id = trim($port_id);
        if ($port_id === '') {
            $this->fail('portID is empty');
            return null;
        }

        $resp = $this->list_ports_json();
        if ($resp === null) {
            return null;
        }

        $rows = $this->extract_ports_rows($resp);
        foreach ($rows as $row) {
            if (trim((string)($row['portID'] ?? $row['id'] ?? '')) === $port_id) {
                return $row;
            }
        }

        $this->fail('portID not found');
        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,string>
     */
    private function collect_table_columns(array $rows): array
    {
        $seen = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                $column = (string)$key;
                if ($column !== '') {
                    $seen[$column] = true;
                }
            }
        }

        $preferred = [
            'API',
            'IMEI',
            'portID',
            'portName',
            'HTTP_PORT',
            'SOCKS_PORT',
            'LOGIN',
            'PASSWORD',
            'http_creds',
            'socks5_creds',
            'PROXY_VALID_BEFORE',
            'IS_EXPIRED',
            'IS_OVER_QUOTA',
            'status',
        ];

        $out = [];
        foreach ($preferred as $col) {
            if (isset($seen[$col])) {
                $out[] = $col;
                unset($seen[$col]);
            }
        }

        foreach (array_keys($seen) as $col) {
            $out[] = $col;
        }

        return $out;
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
     * @param array<string,mixed>|array<int,mixed> $input
     */
    private function looks_like_ports_map(array $input): bool
    {
        if ($input === []) {
            return false;
        }

        foreach ($input as $key => $value) {
            if (!is_string($key) && !is_int($key)) {
                return false;
            }
            if (!is_array($value) || !$this->is_list_array($value)) {
                return false;
            }
        }

        return true;
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

    private function validate_imei(string $imei): bool
    {
        if ($imei === '') {
            $this->fail('IMEI is empty');
            return false;
        }

        if (preg_match('/^[0-9]{15}$/', $imei) !== 1) {
            $this->fail('IMEI must contain 15 digits');
            return false;
        }

        return true;
    }

    /**
     * @param array<string,mixed>|array<int,mixed> $value
     */
    private function encode_json(array $value): ?string
    {
        $json = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        if (!is_string($json)) {
            $this->fail('Failed to encode JSON');
            return null;
        }
        return $json;
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|array<int,mixed>|string|null $body
     * @param array<int,string> $headers
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
        array $headers = []
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
        $this->last_url = $url;

        $final_headers = $headers;
        $final_headers[] = 'Accept: ' . ($expect_json ? 'application/json' : 'text/plain,*/*');

        $payload = null;
        $body_type = strtolower(trim($body_type));
        if ($body !== null && $body_type === 'none') {
            $body_type = 'form';
        }

        if ($body !== null) {
            if ($body_type === 'form') {
                if (!is_array($body)) {
                    $this->fail('Form body must be array');
                    return ['ok' => false];
                }
                $payload = http_build_query($body, '', '&', PHP_QUERY_RFC3986);
                $final_headers[] = 'Content-Type: application/x-www-form-urlencoded';
            } elseif ($body_type === 'json') {
                if (!is_array($body)) {
                    $this->fail('JSON body must be array');
                    return ['ok' => false];
                }
                $payload = $this->encode_json($body);
                if ($payload === null) {
                    return ['ok' => false];
                }
                $final_headers[] = 'Content-Type: application/json';
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, $final_headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->request_connect_timeout_seconds);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->request_timeout_seconds);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        if ($auth_required) {
            if (!$this->has_auth()) {
                curl_close($ch);
                return ['ok' => false];
            }
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }

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
            $this->fail('HTTP error: ' . $http_code . $suffix);
            return ['ok' => false];
        }

        if (!$expect_json) {
            $this->ok();
            return [
                'ok' => true,
                'raw' => $raw_text,
                'http_code' => $http_code,
            ];
        }

        $json = json_decode($raw_text, true);
        if (!is_array($json)) {
            $this->fail('Invalid JSON response');
            return ['ok' => false];
        }

        $this->ok();
        return [
            'ok' => true,
            'raw' => $raw_text,
            'json' => $json,
            'http_code' => $http_code,
        ];
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

    private function has_auth(): bool
    {
        if (trim($this->username) === '' || trim($this->password) === '') {
            $this->fail('username/password is empty');
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
