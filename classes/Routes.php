<?php
declare(strict_types=1);
/* Routes.php */
final class Routes
{
    /** @var array<string,string> url => absolute_path */
    private array $templates = [];

    private function safe_string(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return trim((string)$value);
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return '';
        }

        return $json;
    }

    /** @return array<string, string> */
    private function extract_query_values(string $url): array
    {
        $queryString = (string)(parse_url($url, PHP_URL_QUERY) ?? '');
        if ($queryString === '') {
            return [];
        }

        $parsed = [];
        parse_str($queryString, $parsed);

        $flat = [];
        foreach ($parsed as $key => $value) {
            $normalizedKey = trim((string)$key);
            if ($normalizedKey === '') {
                continue;
            }

            $flat[$normalizedKey] = $this->safe_string($value);
        }

        return $flat;
    }

    /**
     * @param array<string, string> $query
     * @return array<string, string>
     */
    private function extract_utm(array $query): array
    {
        $utm = [];
        foreach ($query as $key => $value) {
            if (!str_starts_with(strtolower($key), 'utm_')) {
                continue;
            }

            if ($value !== '') {
                $utm[$key] = $value;
            }
        }

        return $utm;
    }

    /**
     * @param array<string, string> $query
     * @return array<string, string>
     */
    private function extract_keywords(array $query): array
    {
        $keywords = [];
        foreach (['q', 'query', 'keyword', 'keywords', 'search', 's', 'term'] as $key) {
            if (!isset($query[$key])) {
                continue;
            }

            $value = trim($query[$key]);
            if ($value !== '') {
                $keywords[$key] = $value;
            }
        }

        return $keywords;
    }

    private function log_404_request(string $current_url): void
    {
        $siteRoot = dirname(Sogerien::$SOGERIEN_DIR);
        $logPath = $siteRoot . '/404.txt';

        $requestUri = $this->safe_string($_SERVER['REQUEST_URI'] ?? '');
        $referer = $this->safe_string($_SERVER['HTTP_REFERER'] ?? '');

        $query = [];
        foreach ($_GET as $key => $value) {
            $normalizedKey = trim((string)$key);
            if ($normalizedKey === '') {
                continue;
            }
            $query[$normalizedKey] = $this->safe_string($value);
        }

        $queryFromUri = $this->extract_query_values($requestUri);
        if ($queryFromUri) {
            $query += $queryFromUri;
        }

        $refererQuery = $this->extract_query_values($referer);

        $utm = $this->extract_utm($query);
        $utmFromReferer = $this->extract_utm($refererQuery);
        if ($utmFromReferer) {
            $utm += $utmFromReferer;
        }

        $keywords = $this->extract_keywords($query);
        $keywordsFromReferer = $this->extract_keywords($refererQuery);
        if ($keywordsFromReferer) {
            $keywords += $keywordsFromReferer;
        }

        $record = [
            'timestamp' => gmdate('c'),
            'status' => 404,
            'method' => $this->safe_string($_SERVER['REQUEST_METHOD'] ?? ''),
            'host' => $this->safe_string($_SERVER['HTTP_HOST'] ?? ''),
            'uri' => $requestUri,
            'path' => $current_url,
            'referer' => $referer,
            'ip' => $this->safe_string($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => $this->safe_string($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'keywords' => $keywords,
            'utm' => $utm,
            'query' => $query,
        ];

        if (!is_file($logPath)) {
            @file_put_contents($logPath, '');
        }

        $json = json_encode(
            $record,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if (!is_string($json)) {
            return;
        }

        @file_put_contents($logPath, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public function add_template(string $url, string $page): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $d = Sogerien::$SOGERIEN_DIR;

        // url: "/test4"
        $url = '/' . ltrim($url, '/');
        $url = rtrim($url, '/');
        if ($url === '') { $url = '/'; }

        // page: "/page/test4.php"
        $page = '/' . ltrim($page, '/');

        $this->templates[$url] = $d . $page;
        return Sogerien::Debager()->capture_return($this, __CLASS__, __FUNCTION__);
    }

    public function template(): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $d = Sogerien::$SOGERIEN_DIR;

        // 1) РЅРѕСЂРјР°Р»РёР·СѓРµРј С‚РµРєСѓС‰РёР№ URL
        $current_url = (string) (Sogerien::InputRequest()->url ?? '/');
        $current_url = '/' . ltrim($current_url, '/');
        $current_url = rtrim($current_url, '/');
        if ($current_url === '') { $current_url = '/'; }

        // 2) Р±Р°Р·РѕРІР°СЏ РєР°СЂС‚Р° РјР°СЂС€СЂСѓС‚РѕРІ
        $t = [
            '/users' => $d . '/page/page_users.php',
            '/test1' => $d . '/page/test1.php',
            '/users_edit' => $d . '/page/page_users_edit.php',
            '/users_add' => $d . '/page/page_users_add.php',
            '/users_type' => $d . '/page/page_users_type.php',
            '/users_type_add' => $d . '/page/page_users_type_add.php',
            '/users_type_edit' => $d . '/page/page_users_type_edit.php',
            '/access' => $d . '/page/page_access.php',
            '/access_add' => $d . '/page/page_access_add.php',
            '/access_edit' => $d . '/page/page_access_edit.php',
            '/service' => $d . '/page/page_service.php',
            '/service_edit' => $d . '/page/page_service_edit.php',
            '/service_add' => $d . '/page/page_service_add.php',
            '/service_calc' => $d . '/page/page_service_calc.php',
            '/service_complex' => $d . '/page/page_service_complex.php',
            '/legal' => $d . '/page/page_legal.php',
            '/legal_edit' => $d . '/page/page_legal_edit.php',
            '/legal_add' => $d . '/page/page_legal_add.php',
            '/service_order' => $d . '/page/page_service_order.php',
            '/service_order_add' => $d . '/page/page_service_order_add.php',
            '/service_order_edit' => $d . '/page/page_service_order_edit.php',
            '/new_partner_hom' => $d . '/page/page2_partner_hom.php',
            '/new_partner_add' => $d . '/page/page2_add_partner.php',
            '/new_referal' => $d . '/page/page2_new_referal.php',
            '/new_proviyder_hom' => $d . '/page/page2_proviyder_hom.php',
            '/new_client_hom' => $d . '/page/page2_client_hom.php',
            '/new_client_add' => $d . '/page/page2_client_add.php',
            '/new_client_referal' => $d . '/page/page2_client_referal.php',
            '/new_calc' => $d . '/page/page2_calc.php',
            '/lending-referal' => $d . '/page/page2_partner_reklama.php',
            '/proxies' => $d . '/page/proxies_list.php',
            '/infatica_io' => $d . '/page/proxies_list_infatica_io.php',
            '/all_proxy' => $d . '/page/all_proxy.php',
            '/proxy/view' => $d . '/page/proxy_view.php',
            '/my/proxies' => $d . '/page/my_proxies.php',
            '/my/payments' => $d . '/page/my_payments.php',
            '/profile' => $d . '/page/profile.php',
            '/proxy/manage' => $d . '/page/proxy_manage.php',
        ];

        // 3) РґРѕР±Р°РІР»РµРЅРЅС‹Рµ С‚РµСЃС‚РѕРІС‹Рµ РјР°СЂС€СЂСѓС‚С‹ (РїРµСЂРµРєСЂС‹РІР°СЋС‚ Р±Р°Р·РѕРІС‹Рµ РїСЂРё СЃРѕРІРїР°РґРµРЅРёРё РєР»СЋС‡Р°)
        if ($this->templates) {
            $t = $this->templates + $t; // РґРѕР±Р°РІР»РµРЅРЅС‹Рµ РёРјРµСЋС‚ РїСЂРёРѕСЂРёС‚РµС‚
        }

        // 4) СЂРѕСѓС‚РёРЅРі
        if (isset($t[$current_url])) {
            $path = $t[$current_url];
            if (is_file($path)) { include $path; do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false); }
            http_response_code(500);
            echo "С€Р°Р±Р»РѕРЅ РЅРµ РЅР°Р№РґРµРЅ - $path";
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }

        // 5) 404
        http_response_code(404);
        $this->log_404_request($current_url);
        echo "404 - страница не найдена";
    }
}
