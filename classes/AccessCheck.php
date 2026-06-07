<?php

declare(strict_types=1);

/* AccessCheck.php */

final class AccessCheck
{
    use SogerienClassHelp;

    public string $db_alias = '';
    public string $errors = '';

    /** @var array<string,array{roles:array<string,bool>,users_id:array<string,bool>}>|null */
    private ?array $rules = null;

    private int $user_id = 0;

    /** @var array<string,bool> set: group => true */
    private array $user_group = [];

    public function init_db_alias(string $alias): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->db_alias = trim($alias);
        $this->rules = null;
        $this->errors = '';
        $this->user_id = 0;
        $this->user_group = [];
}

    /**
     * @param array<string,bool> $user_group set: ['admin'=>true,'user'=>true]
     */
    public function check_access(string $method_name, int $user_id = 0, array $user_group = []): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->errors = '';
        $method_name = trim($method_name);

        $this->user_id = max($user_id, 0);
        $this->user_group = $user_group;

        if ($method_name === '') {
            $this->errors .= "not set method\n";
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        if ($this->db_alias === '') {
            $this->errors .= "вы не указали название базы данных\n";
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $this->load_rules();

        if (!is_array($this->rules) || $this->rules === []) {
            $this->errors .= "не удалось получить с базы данных права доступов\n";
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        // метод должен существовать в rules
        if (!isset($this->rules[$method_name])) {
            $this->errors .= "такого метода в базе данных нету\n";
            return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        // guest - всегда true по наличию ключа
        if (isset($this->rules[$method_name]['roles']['guest'])) return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);

        // если не передали identity - пробуем токен
        if ($this->user_id === 0 && $this->user_group === []) {
            $this->hydrateUserFromToken();
            if ($this->user_id === 0 && $this->user_group === []) {
                $this->setAccessDeniedMessage($method_name);
                return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
            }
        }

        // roles check - user_group это set, ищем пересечение ключей
        if ($this->user_group !== []) {
            foreach ($this->user_group as $groupKey => $_true) {
                $groupKey = trim((string)$groupKey);
                if ($groupKey === '') continue;

                if (isset($this->rules[$method_name]['roles'][$groupKey])) return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
            }
        }

        // user_id check (ключи jsonb приходят строками)
        if ($this->user_id > 0 && isset($this->rules[$method_name]['users_id'][(string)$this->user_id])) return Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);

        $this->setAccessDeniedMessage($method_name);
        return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
    }

    /**
     * @param array<string,bool> $user_group
     */
    public function check_access_or_show_login_form(string $method_name, int $user_id = 0, array $user_group = []): bool
    {
        $access_ok = $this->check_access($method_name, $user_id, $user_group);
        if ($access_ok) {
            return true;
        }

        if ($this->mustShowLoginFormForDeniedGuest($method_name, $user_id, $user_group)) {
            $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
            $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '/');
            if (!str_starts_with($requestPath, '/') || str_starts_with($requestPath, '//')) {
                $requestUri = '/';
            }
            if (!isset($_GET['next']) || trim((string)$_GET['next']) === '') {
                $_GET['next'] = $requestUri;
            }
            $login_form_path = dirname(__DIR__) . '/page/admin_panel/page_login_form.php';
            if (is_file($login_form_path)) {
                require $login_form_path;
                Sogerien::markDone();
                Sogerien::exit();
            }
        }

        return false;
    }

    /**
     * @param array<string,bool> $user_group
     */
    private function mustShowLoginFormForDeniedGuest(string $method_name, int $user_id, array $user_group): bool
    {
        $method_name = trim($method_name);
        $identity_user_id = max($user_id, 0);
        $identity_user_group = $user_group;

        if ($identity_user_id === 0 && $identity_user_group === []) {
            $this->user_id = 0;
            $this->user_group = [];
            $this->hydrateUserFromToken();

            $identity_user_id = $this->user_id;
            $identity_user_group = $this->user_group;
        }

        $is_unauthorized = ($identity_user_id === 0 && $identity_user_group === []);
        if (!$is_unauthorized) {
            return false;
        }

        if ($method_name === '') {
            return true;
        }

        $this->load_rules();
        if (!is_array($this->rules) || !isset($this->rules[$method_name])) {
            // если ключ правила отсутствует, доступ уже отклонен check_access - неавторизованному показываем login form
            return true;
        }

        if (isset($this->rules[$method_name]['roles']['guest'])) {
            return false;
        }

        return true;
    }

    private function setAccessDeniedMessage(string $method_name): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $idLabel = $this->user_id > 0 ? (string)$this->user_id : 'none';
        $lines = [
            'rules: ' . $method_name,
            'user_id: ' . $idLabel . ' - no access',
            'user_groups:',
        ];
        if ($this->user_group !== []) {
            foreach (array_keys($this->user_group) as $group) {
                $group = trim((string)$group);
                if ($group !== '') {
                    $lines[] = '  ' . $group . ': no access';
                }
            }
        } else {
            $lines[] = '  none';
        }
        $this->errors = implode("\n", $lines);
    }

    private function load_rules(): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if ($this->rules !== null) do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);

        $this->rules = [];

        $respJson = Sogerien::DbController()->sql_request($this->db_alias, [
            'sql' => "
        SELECT table_value FROM sogerien WHERE table_name = :table_name AND name = :name LIMIT 1",
            'params' => [
                ':table_name' => 'config',
                ':name'       => 'rules',
            ],
        ]);

        if (!is_string($respJson) || $respJson === '') do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);

        $resp = json_decode($respJson, true);
        if (!is_array($resp) || !($resp['result'] ?? false)) do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);

        $table_value = $resp['rows'][0]['table_value'] ?? null;
        // DbController может вернуть jsonb как array или как string
        if (is_array($table_value)) {
            $this->rules = $table_value;
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }

        if (is_string($table_value)) {
            $decoded = json_decode($table_value, true);
            if (is_array($decoded)) $this->rules = $decoded;
        }
    }

    private function hydrateUserFromToken(): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $keyFile = (string)(Sogerien::$patch_to_cookies_keyFile ?? '');
        if ($keyFile === '') do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);

        $at = Sogerien::AccessToken();
        $at->patch_to_keyFile = $keyFile;

        $token = $at->load_token_for_cookie();
        if (!$at->status || $token === '') do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);

        $payload = $at->read_token($token);
        if (!$at->status || !is_array($payload)) do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);

        // user_id - строго int
        $uid = (int)($payload['user_id'] ?? 0);
        if ($uid > 0) {
            $this->user_id = $uid;
        }

        // user_group - строго set array<string,bool>
        $grp = $payload['user_group'] ?? [];
        if (is_array($grp) && $grp !== []) {
            $this->user_group = $grp;
        }
    }
}
