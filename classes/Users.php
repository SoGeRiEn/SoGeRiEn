<?php
declare(strict_types=1);

/* User.php
 * Объект-хранилище пользователя (поля + сборка ассок-массива как table_value)
 */

final class User
{
    public string $fio = '';

    /** @var string[] */
    public array $roles = [];

    /** @var array<string,mixed> */
    public array $utm = ['source' => '', 'campaign' => ''];

    public string $code = '';
    public string $email = '';
    public string $login = '';
    public string $phone = '';

    /** @var array<string,string> */
    public array $balance = ['USD' => '0.00'];

    /** @var array{tz:string,lang:string} */
    public array $settings = ['tz' => 'Europe/Warsaw', 'lang' => 'ru'];

    /** @var array{email:string,phone:string} */
    public array $validate = ['email' => 'false', 'phone' => 'false'];

    // хранится в базе в table_value в виде криптостойкого хеша (password_hash)
    public string $pass_hash = '';

    public int $partner_id = 0;

    /** @var array{mode:string,by_currency:array<string,string>} */
    public array $credit_limit = ['mode' => '', 'by_currency' => []];

    public string $partner_percent = '';
    public string $discount_percent = '';

    /**
     * Вернуть table_value как ассок-массив строго в твоём формате.
     *
     * @return array<string,mixed>
     */
    public function get_assoc_array(): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return([
            'roles' => $this->roles,
            'fio' => $this->fio,
            'utm' => $this->utm,
            'code' => $this->code,
            'email' => $this->email,
            'login' => $this->login,
            'phone' => $this->phone,
            'balance' => $this->balance,
            'settings' => $this->settings,
            'validate' => $this->validate,
            'pass_hash' => $this->pass_hash,
            'partner_id' => $this->partner_id,
            'credit_limit' => $this->credit_limit,
            'partner_percent' => $this->partner_percent,
            'discount_percent' => $this->discount_percent,
        ], __CLASS__, __FUNCTION__);
}
}

final class Users
{
    public bool $status = false;
    public string $error = '';

    // на будущее - чтобы Users мог работать с разными базами как AccessCheck
    public string $db_alias = '';
    public int $user_id = 0;

    /** @var array<string,mixed> */
    public array $user_data = [];

    /** @var array<string,bool> */
    public array $user_group = [];

    // cookie key file берём из Sogerien::$patch_to_cookies_keyFile
    public function init_db_alias(string $alias): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->db_alias = trim($alias);
        $this->ok();
}

    public function load_identity_from_token(): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');
        $this->user_id = 0;
        $this->user_data = [];
        $this->user_group = [];

        $keyFile = (string)(Sogerien::$patch_to_cookies_keyFile ?? '');
        if ($keyFile === '') {
            $this->fail('Sogerien::$patch_to_cookies_keyFile is empty');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $at = Sogerien::AccessToken();
        $at->patch_to_keyFile = $keyFile;

        $adminViewUserId = $this->admin_view_user_id_from_request();
        $useImpersonation = $adminViewUserId <= 0 && $this->should_use_impersonation_token();
        $token = $useImpersonation ? $at->load_impersonation_token_for_cookie() : $at->load_token_for_cookie();
        if (!$at->status || $token === '') {
            $this->fail($at->error !== '' ? $at->error : 'load_token_for_cookie failed');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $payload = $at->read_token($token);
        if (!$at->status || !is_array($payload)) {
            $this->fail($at->error !== '' ? $at->error : 'read_token failed');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        if ($useImpersonation) {
            $purpose = (string)($payload['purpose'] ?? '');
            $until = (int)($payload['impersonation_until'] ?? 0);
            if ($purpose !== 'admin_impersonation' || $until < time()) {
                $at->delete_impersonation_token_from_cookie();
                $this->fail('Impersonation token expired');
                return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
            }
        }

        $uid = (int)($payload['user_id'] ?? 0);
        if ($uid <= 0) {
            $this->fail('user_id not found in token');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $groups = $payload['user_group'] ?? [];
        if (is_array($groups)) {
            foreach ($groups as $k => $_v) {
                $k = trim((string)$k);
                if ($k === '') {
                    continue;
                }
                $this->user_group[$k] = true;
            }
        }

        if ($adminViewUserId > 0) {
            if (!isset($this->user_group['admin'])) {
                $this->fail('Admin access required for user_id view');
                return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
            }

            $targetRow = $this->get_user_by_id($adminViewUserId);
            if (!is_array($targetRow)) {
                $this->fail('User not found');
                return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
            }

            $targetData = $this->normalize_user_table_value($targetRow['table_value'] ?? []);
            $targetGroups = $this->roles_to_group_set($targetData['roles'] ?? ['user']);
            if ($targetGroups === []) {
                $targetGroups['user'] = true;
            }

            $this->user_id = $adminViewUserId;
            $this->user_data = $targetData;
            $this->user_group = $targetGroups;
            $this->ok();
            return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        }

        $row = $this->get_user_by_id($uid);
        if (is_array($row)) {
            $this->user_data = $this->normalize_user_table_value($row['table_value'] ?? []);
        }

        $this->user_id = $uid;
        $this->ok();
        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    private function should_use_impersonation_token(): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        // Impersonation токен использовать ТОЛЬКО если он реально есть в куках,
        // иначе откатываемся на обычный access_token. Иначе любой /client/* путь
        // ломается у обычных юзеров - они даже не админы, нет impersonation cookie.
        $cookieName = Sogerien::AccessToken()->COOKIE_NAME_IMPERSONATE;
        if ($cookieName === '' || !isset($_COOKIE[$cookieName]) || trim((string)$_COOKIE[$cookieName]) === '') {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        $path = trim((string)(Sogerien::InputRequest()->url ?? ''), '/');
        return   Sogerien::Debager()->capture_return($path === 'client' || str_starts_with($path, 'client/'), __CLASS__, __FUNCTION__);
}

    private function admin_view_user_id_from_request(): int
    {
        $path = trim((string)(Sogerien::InputRequest()->url ?? ''), '/');
        if ($path !== 'client' && !str_starts_with($path, 'client/')) {
            return 0;
        }

        $raw = trim((string)($_GET['user_id'] ?? ''));
        if ($raw === '' || preg_match('/^[1-9]\d*$/', $raw) !== 1) {
            return 0;
        }

        return (int)$raw;
    }

    /**
     * @return array<string,mixed>
     */
    private function normalize_user_table_value(mixed $tableValue): array
    {
        if (is_string($tableValue)) {
            $decoded = json_decode($tableValue, true);
            $tableValue = is_array($decoded) ? $decoded : [];
        }

        return is_array($tableValue) ? $tableValue : [];
    }

    /**
     * @return array<string,bool>
     */
    private function roles_to_group_set(mixed $rolesRaw): array
    {
        if (!is_array($rolesRaw)) {
            return [];
        }

        $groups = [];
        foreach ($rolesRaw as $role) {
            $role = trim((string)$role);
            if ($role !== '') {
                $groups[$role] = true;
            }
        }

        return $groups;
    }

    /**
     * Создать токен по твоему формату:
     * [
     *   'ip'             => InputRequest->REMOTE_ADDR,
     *   'fingerprint_md5'=> InputRequest->fingerprint_md5,
     *   'user_id'        => $user_id,
     *   'user_group'     => $user_group
     * ]
     *
     * @param array<string,bool> $user_group set: ['admin'=>true,'user'=>true]
     */
    public function create_token(int $user_id, array $user_group = []): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        $user_id = max($user_id, 0);

        // user_group - ожидаем set, но чистим ключи от мусора
        $ug = [];
        foreach ($user_group as $k => $_v) {
            $k = trim((string)$k);
            if ($k === '') continue;
            $ug[$k] = true;
        }

        $in = Sogerien::InputRequest();

        $payload = [
            'ip' => (string)($in->REMOTE_ADDR ?? ''),
            'fingerprint_md5' => (string)($in->fingerprint_md5 ?? ''),
            'user_id' => $user_id,
            'user_group' => $ug,
        ];

        $keyFile = (string)(Sogerien::$patch_to_cookies_keyFile ?? '');
        if ($keyFile === '') {
            $this->fail('Sogerien::$patch_to_cookies_keyFile is empty');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $at = Sogerien::AccessToken();
        $at->patch_to_keyFile = $keyFile;

        $token = $at->create_token($payload);
        if (!$at->status || $token === '') {
            $this->fail($at->error !== '' ? $at->error : 'create_token failed');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return($token, __CLASS__, __FUNCTION__);
}

    /**
     * Записать уже созданный токен в cookie access_token
     */
    public function save_token_to_cookie(string $token, int $days = 30): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        $keyFile = (string)(Sogerien::$patch_to_cookies_keyFile ?? '');
        if ($keyFile === '') {
            $this->fail('Sogerien::$patch_to_cookies_keyFile is empty');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $at = Sogerien::AccessToken();
        $at->patch_to_keyFile = $keyFile;

        $ok = $at->save_token_for_cookie($token, $days);
        if (!$ok || !$at->status) {
            $this->fail($at->error !== '' ? $at->error : 'save_token_for_cookie failed');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    /**
     * Загрузить токен из cookie access_token (просто строка токена)
     */
    public function load_token_from_cookie(): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        $keyFile = (string)(Sogerien::$patch_to_cookies_keyFile ?? '');
        if ($keyFile === '') {
            $this->fail('Sogerien::$patch_to_cookies_keyFile is empty');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $at = Sogerien::AccessToken();
        $at->patch_to_keyFile = $keyFile;

        $token = $at->load_token_for_cookie();
        if (!$at->status || $token === '') {
            $this->fail($at->error !== '' ? $at->error : 'load_token_for_cookie failed');
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return($token, __CLASS__, __FUNCTION__);
}

    /* =========================
     * Методы работы с пользователем
     * ========================= */

    /**
     * Вернуть полную строку из sogerien по user_id (включая table_value).
     *
     * @return array<string,mixed>|null
     */
    public function get_user_by_id(int $id): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        if ($id <= 0) {
            $this->fail('Invalid user id');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (!$this->ensureDbAlias()) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $sql = "
            SELECT *
            FROM sogerien
            WHERE table_name = 'user'
              AND id = :user_id
              AND status <> 'delete'
            LIMIT 1;
            ";

        $res = $this->dbQuery($sql, ['user_id' => $id]);
        if (($res['result'] ?? false) !== true) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $rows = $res['rows'] ?? [];
        if (!is_array($rows) || count($rows) === 0) {
            $this->fail('User not found');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        /** @var array<string,mixed> $row */
        $row = $rows[0];
        $this->ok();
        return   Sogerien::Debager()->capture_return($row, __CLASS__, __FUNCTION__);
}

    /**
     * Получить пользователя по id для формы редактирования (без фильтра по status).
     * Возвращает «плоский» массив для подстановки в поля формы.
     *
     * @return array<string,mixed>|null
     */
    public function get_user_for_edit(int $id): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        if ($id <= 0 || !$this->ensureDbAlias()) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $sql = "
            SELECT id, name, table_value
            FROM sogerien
            WHERE table_name = 'user' AND id = :user_id
            LIMIT 1;
        ";
        $res = $this->dbQuery($sql, ['user_id' => $id]);
        if (($res['result'] ?? false) !== true) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $rows = $res['rows'] ?? [];
        if (!is_array($rows) || count($rows) === 0) {
            $this->fail('User not found');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $row = $rows[0];
        $tv = $row['table_value'] ?? null;
        if (is_string($tv)) {
            $tv = json_decode($tv, true);
        }
        if (!is_array($tv)) {
            $tv = [];
        }

        $utm = $tv['utm'] ?? [];
        $balance = $tv['balance'] ?? [];
        $settings = $tv['settings'] ?? [];
        $validate = $tv['validate'] ?? [];
        $credit_limit = $tv['credit_limit'] ?? [];
        $by_currency = isset($credit_limit['by_currency']) && is_array($credit_limit['by_currency'])
            ? $credit_limit['by_currency']
            : [];

        $out = [
            'id'                    => (int)($row['id'] ?? 0),
            'name'                  => (string)($row['name'] ?? ''),
            'login'                 => (string)($tv['login'] ?? ''),
            'email'                 => (string)($tv['email'] ?? ''),
            'fio'                   => (string)($tv['fio'] ?? ''),
            'phone'                 => (string)($tv['phone'] ?? ''),
            'code'                  => (string)($tv['code'] ?? ''),
            'utm_source'            => (string)($utm['source'] ?? ''),
            'utm_campaign'          => (string)($utm['campaign'] ?? ''),
            'balance_USD'           => (string)($balance['USD'] ?? '0.00'),
            'settings_tz'           => (string)($settings['tz'] ?? 'Europe/Warsaw'),
            'settings_lang'         => (string)($settings['lang'] ?? 'ru'),
            'validate_email'        => (string)($validate['email'] ?? 'false'),
            'validate_phone'        => (string)($validate['phone'] ?? 'false'),
            'partner_id'            => (int)($tv['partner_id'] ?? 0),
            'credit_limit_mode'      => (string)($credit_limit['mode'] ?? ''),
            'credit_limit_by_currency' => json_encode($by_currency, JSON_UNESCAPED_UNICODE),
            'partner_percent'        => (string)($tv['partner_percent'] ?? ''),
            'discount_percent'       => (string)($tv['discount_percent'] ?? ''),
        ];

        $roles = $tv['roles'] ?? [];
        $out['roles'] = is_array($roles) ? array_values(array_map('strval', $roles)) : [];

        $this->ok();
        return   Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
}

    /**
     * Получить пользователя по логину (login: нормализуем lower(trim(login))).
     *
     * @return array<string,mixed>|null
     */
    public function get_user_by_login(string $login): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        $login = trim($login);
        if ($login == '') {
            $this->fail('Empty login');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (!$this->ensureDbAlias()) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $loginNorm = mb_strtolower($login);

        $sql = "
            SELECT *
            FROM sogerien
            WHERE table_name = 'user'
              AND lower(trim(table_value->>'login')) = :login
              AND status <> 'delete'
            LIMIT 1;
            ";

        $res = $this->dbQuery($sql, ['login' => $loginNorm]);
        if (($res['result'] ?? false) !== true) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $rows = $res['rows'] ?? [];
        if (!is_array($rows) || count($rows) === 0) {
            $this->fail('User not found');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        /** @var array<string,mixed> $row */
        $row = $rows[0];
        $this->ok();
        return   Sogerien::Debager()->capture_return($row, __CLASS__, __FUNCTION__);
}

    /**
     * Проверить, существует ли пользователь с таким логином (ВКЛЮЧАЯ удалённых).
     * Сравнение регистронезависимое.
     */
    public function login_exists_anywhere(string $login): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $this->fail('');

        $login = trim($login);
        if ($login === '') {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        if (!$this->ensureDbAlias()) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $sql = "
            SELECT 1
            FROM sogerien
            WHERE table_name = 'user'
              AND lower(trim(table_value->>'login')) = :login
            LIMIT 1;
            ";
        $res = $this->dbQuery($sql, ['login' => mb_strtolower($login)]);
        if (($res['result'] ?? false) !== true) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        $rows = $res['rows'] ?? [];
        $exists = is_array($rows) && count($rows) > 0;
        $this->ok();
        return   Sogerien::Debager()->capture_return($exists, __CLASS__, __FUNCTION__);
    }

    /**
     * Подобрать уникальный логин на основе переданного.
     * Если base уже занят (в любом статусе) - добавляет суффикс _2, _3, ...
     * Возвращает '' если не удалось подобрать.
     */
    public function find_unique_login(string $base): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $this->fail('');

        $base = trim($base);
        // Оставляем только разрешённые символы (тот же набор что в register_user).
        $base = preg_replace('/[^a-zA-Z0-9._-]/', '', $base) ?? '';
        if ($base === '') {
            return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }
        if (mb_strlen($base) < 3) {
            $base = 'user' . substr(bin2hex(random_bytes(4)), 0, 6);
        }
        if (mb_strlen($base) > 60) {
            $base = mb_substr($base, 0, 60);
        }

        if (!$this->login_exists_anywhere($base)) {
            $this->ok();
            return   Sogerien::Debager()->capture_return($base, __CLASS__, __FUNCTION__);
        }

        for ($n = 2; $n <= 9999; $n++) {
            $suffix = '_' . $n;
            $maxBaseLen = 64 - mb_strlen($suffix);
            $candidate = mb_substr($base, 0, $maxBaseLen) . $suffix;
            if (!$this->login_exists_anywhere($candidate)) {
                $this->ok();
                return   Sogerien::Debager()->capture_return($candidate, __CLASS__, __FUNCTION__);
            }
        }

        // Fallback: случайный 6-символьный суффикс на случай если все 2..9999 заняты.
        $fallback = mb_substr($base, 0, 57) . '_' . bin2hex(random_bytes(3));
        if (!$this->login_exists_anywhere($fallback)) {
            $this->ok();
            return   Sogerien::Debager()->capture_return($fallback, __CLASS__, __FUNCTION__);
        }
        $this->fail('Cannot find unique login');
        return   Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
    }

    /**
     * Получить пользователя по email (email: нормализуем lower(trim(email))).
     *
     * @return array<string,mixed>|null
     */
    public function get_user_by_email(string $email): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        $email = trim($email);
        if ($email == '') {
            $this->fail('Empty email');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if (!$this->ensureDbAlias()) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $emailNorm = mb_strtolower($email);

        $sql = "
            SELECT *
            FROM sogerien
            WHERE table_name = 'user'
              AND lower(trim(table_value->>'email')) = :email
              AND status <> 'delete'
            LIMIT 1;
            ";

        $res = $this->dbQuery($sql, ['email' => $emailNorm]);
        if (($res['result'] ?? false) !== true) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $rows = $res['rows'] ?? [];
        if (!is_array($rows) || count($rows) === 0) {
            $this->fail('User not found');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        /** @var array<string,mixed> $row */
        $row = $rows[0];
        $this->ok();
        return   Sogerien::Debager()->capture_return($row, __CLASS__, __FUNCTION__);
}

    /**
     * Список пользователей (id, name, login, email) для админки.
     *
     * @return array<int,array<string,mixed>>
     */
    /**
     * Список пользователей (id, name, login, email, roles, status) для админки.
     *
     * @param string|null $status one of: null (все, кроме delete), 'actual', 'archive', 'delete', 'all'
     * @return array<int,array<string,mixed>>
     */
    public function get_users_list(?string $status = null): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        if (!$this->ensureDbAlias()) {
            return   Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
        }

        $whereStatus = "status <> 'delete'";
        if ($status === 'actual') {
            $whereStatus = "status = 'actual'";
        } elseif ($status === 'archive') {
            $whereStatus = "status = 'archive'";
        } elseif ($status === 'delete') {
            $whereStatus = "status = 'delete'";
        } elseif ($status === 'all') {
            $whereStatus = "status IN ('actual','archive','delete')";
        }

        $sql = "
            SELECT id, name, table_value, status
            FROM sogerien
            WHERE table_name = 'user'
              AND {$whereStatus}
            ORDER BY id;
            ";
        $res = $this->dbQuery($sql, []);
        if (($res['result'] ?? false) !== true) {
            return   Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
        }

        $rows = $res['rows'] ?? [];
        if (!is_array($rows)) {
            return   Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
        }

        $list = [];
        foreach ($rows as $row) {
            $tv = $row['table_value'] ?? null;
            if (is_string($tv)) {
                $decoded = json_decode($tv, true);
                $tv = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($tv)) {
                $tv = [];
            }
            $roles = $tv['roles'] ?? [];
            if (!is_array($roles)) {
                $roles = [];
            }
            $list[] = [
                'id'     => (int)($row['id'] ?? 0),
                'name'   => (string)($row['name'] ?? ''),
                'login'  => (string)($tv['login'] ?? ''),
                'email'  => (string)($tv['email'] ?? ''),
                'roles'  => array_values(array_map('strval', $roles)),
                'status' => (string)($row['status'] ?? ''),
            ];
        }
        $this->ok();
        return   Sogerien::Debager()->capture_return($list, __CLASS__, __FUNCTION__);
}

    /**
     * Следующий свободный id в таблице sogerien (для создания пользователя).
     */
    public function get_next_user_id(): int
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        if (!$this->ensureDbAlias()) {
            return   Sogerien::Debager()->capture_return(1, __CLASS__, __FUNCTION__);
        }

        $sql = "SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM sogerien;";
        $res = $this->dbQuery($sql, []);
        if (($res['result'] ?? false) !== true) {
            return   Sogerien::Debager()->capture_return(1, __CLASS__, __FUNCTION__);
        }

        $rows = $res['rows'] ?? [];
        $next = (is_array($rows) && isset($rows[0]['next_id'])) ? (int)$rows[0]['next_id'] : 1;
        $this->ok();
        return   Sogerien::Debager()->capture_return($next, __CLASS__, __FUNCTION__);
}

    /**
     * Создание пользователя — одна запись в sogerien (table_name = 'user').
     * Логин, email, pass_hash хранятся в table_value.
     *
     * Ожидаемые ключи в $array (минимум):
     * - user_id (int > 0)
     * - login (string)
     * - email (string)
     * - password (string, обычный пароль) ИЛИ pass_hash (готовый password_hash)
     *
     * Остальные поля (fio, utm, phone, settings и т.д.) подтягиваются, если заданы.
     *
     * @param array<string,mixed> $array
     */
    public function create_user(array $array): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        if (!$this->ensureDbAlias()) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $user_id = (int)($array['user_id'] ?? 0);
        if ($user_id <= 0) {
            $user_id = $this->get_next_user_id();
            $array['user_id'] = $user_id;
        }

        $login = trim((string)($array['login'] ?? ''));
        $email = trim((string)($array['email'] ?? ''));
        if ($login === '' || $email === '') {
            $this->fail('login and email are required');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $u = new User();
        $u->fio             = (string)($array['fio'] ?? '');
        if (isset($array['roles']) && is_array($array['roles'])) {
            $u->roles = $this->filter_roles_by_allowed(array_map('strval', $array['roles']));
        }
        if (isset($array['utm']) && is_array($array['utm'])) {
            $u->utm = $array['utm'];
        }
        $u->code            = (string)($array['code'] ?? '');
        $u->email           = $email;
        $u->login           = $login;
        $u->phone           = (string)($array['phone'] ?? '');
        if (isset($array['balance']) && is_array($array['balance'])) {
            $u->balance = $array['balance'];
        }
        if (isset($array['settings']) && is_array($array['settings'])) {
            $u->settings = $array['settings'];
        }
        if (isset($array['validate']) && is_array($array['validate'])) {
            $u->validate = $array['validate'];
        }
        $u->partner_id      = (int)($array['partner_id'] ?? 0);
        if (isset($array['credit_limit']) && is_array($array['credit_limit'])) {
            $u->credit_limit = $array['credit_limit'];
        }
        $u->partner_percent  = (string)($array['partner_percent'] ?? '');
        $u->discount_percent = (string)($array['discount_percent'] ?? '');


        // пароль в базе данных храним только как password_hash
        if (isset($array['pass_hash']) && $array['pass_hash'] !== '') {
            $givenHash = (string)$array['pass_hash'];
            if (!$this->is_password_hash($givenHash)) {
                $this->fail('pass_hash must be a valid password_hash value');
                return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
            }
            $u->pass_hash = $givenHash;
        } elseif (isset($array['password']) && $array['password'] !== '') {
            $hash = $this->make_password_hash((string)$array['password']);
            if ($hash === null) {
                return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
            }
            $u->pass_hash = $hash;
        }

        $userValue = $u->get_assoc_array();
        $userJson  = $this->encodeJsonPatch($userValue);

        $pdo = Sogerien::DbController()->pdo($this->db_alias);

        try {
            $pdo->beginTransaction();

            $sqlUser = "
                INSERT INTO sogerien (table_name, name, table_index, table_value, status, created_at, updated_at)
                VALUES (
                'user',
                :name,
                NULL,
                :user_value::jsonb,
                :status,
                now(),
                now()
                );
                ";


            $stUser = $pdo->prepare($sqlUser);
            $stUser->execute([
                ':name'       => $u->fio,
                ':user_value' => $userJson,
                ':status'     => (string)($array['status'] ?? 'actual'),
            ]);

            $pdo->commit();
            $this->ok();
            return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->fail($e->getMessage());
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
}

    /**
     * Регистрация клиента с автоприсвоением роли user.
     *
     * @param array<string,mixed> $array
     * @return array<string,mixed>|null
     */
    public function register_user(array $array): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
        $this->fail('');

        if (!$this->ensureDbAlias()) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $login = trim((string)($array['login'] ?? ''));
        $email = mb_strtolower(trim((string)($array['email'] ?? '')));
        $password = (string)($array['password'] ?? '');

        if ($login === '' || $email === '' || $password === '') {
            $this->fail('login, email and password are required');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        if (!preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $login)) {
            $this->fail('Login format is invalid');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->fail('Email format is invalid');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        if (mb_strlen($password) < 8) {
            $this->fail('Password must be at least 8 characters');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        // Логин - уникальный. Если совпадает с любой записью (включая удалённые) -
        // авто-инкрементируем: base, base_2, base_3 и т.д.
        $uniqueLogin = $this->find_unique_login($login);
        if ($uniqueLogin === '') {
            $this->fail('Failed to generate unique login');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        $login = $uniqueLogin;

        $existsByEmail = $this->get_user_by_email($email);
        if ($existsByEmail !== null) {
            $this->fail('Email already exists');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }
        if ($this->error !== 'User not found') {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        Sogerien::Roles()->init_db_alias($this->db_alias);
        if (!Sogerien::Roles()->add_role('user') && Sogerien::Roles()->error !== '') {
            $this->fail(Sogerien::Roles()->error);
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $roles = ['user'];
        if (isset($array['roles']) && is_array($array['roles'])) {
            $roles = array_values(array_unique(array_merge($roles, array_map('strval', $array['roles']))));
        }

        $payload = $array;
        $payload['login'] = $login;
        $payload['email'] = $email;
        $payload['password'] = $password;
        $payload['roles'] = $roles;

        if (!$this->create_user($payload)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $row = $this->get_user_by_login($login);
        if ($row === null) {
            $this->fail($this->error !== '' ? $this->error : 'User created but reload failed');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return($row, __CLASS__, __FUNCTION__);
    }

    /**
     * Обновление пользователя (top-level merge по table_value).
     * Логин, email, пароль хранятся в table_value пользователя.
     *
     * @param array<string,mixed> $array
     */
    public function update_user(int $id, array $array): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        if ($id <= 0) {
            $this->fail('Invalid user id');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        if (!$this->ensureDbAlias()) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        if (isset($array['roles']) && is_array($array['roles'])) {
            $array['roles'] = $this->filter_roles_by_allowed(array_map('strval', $array['roles']));
        }

        if (isset($array['password']) && (string)$array['password'] !== '') {
            $hash = $this->make_password_hash((string)$array['password']);
            if ($hash === null) {
                return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
            }
            $array['pass_hash'] = $hash;
            unset($array['password']);
        }

        $patchJson = $this->encodeJsonPatch($array);

        $pdo = Sogerien::DbController()->pdo($this->db_alias);

        try {
            $sqlUser = "
                UPDATE sogerien
                SET table_value = COALESCE(table_value, '{}'::jsonb) || :patch::jsonb,
                    updated_at = now()
                WHERE table_name = 'user'
                AND id = :user_id
                AND status <> 'delete';
                ";
            $stUser = $pdo->prepare($sqlUser);
            $stUser->execute([
                ':patch'   => $patchJson,
                ':user_id' => $id,
            ]);

            if ($stUser->rowCount() < 1) {
                $this->fail('User not found or deleted');
                return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
            }

            $this->ok();
            return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
}

    /**
     * delete_user(id) - статус delete у записи пользователя (table_name = 'user').
     */
    public function get_balance_amount(int $id, string $currency = 'USD'): ?float
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        $currency = strtoupper(trim($currency));
        if ($currency === '') {
            $currency = 'USD';
        }

        $row = $this->get_user_by_id($id);
        if (!is_array($row)) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $tableValue = $row['table_value'] ?? [];
        if (!is_array($tableValue)) {
            $tableValue = [];
        }

        $balance = $tableValue['balance'] ?? [];
        if (!is_array($balance)) {
            $balance = [];
        }

        $value = (string)($balance[$currency] ?? '0.00');
        if (!is_numeric($value)) {
            $value = '0.00';
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return((float)$value, __CLASS__, __FUNCTION__);
}

    public function increase_balance_amount(int $id, float $amount, string $currency = 'USD'): ?string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        if ($amount < 0) {
            $this->fail('amount must be >= 0');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $currency = strtoupper(trim($currency));
        if ($currency === '') {
            $currency = 'USD';
        }

        $current = $this->get_balance_amount($id, $currency);
        if ($current === null) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $newValue = number_format($current + $amount, 2, '.', '');
        $ok = $this->update_user($id, [
            'balance' => [
                $currency => $newValue,
            ],
        ]);
        if (!$ok) {
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return($newValue, __CLASS__, __FUNCTION__);
}

    public function delete_user(int $id): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->set_user_status($id, 'delete'), __CLASS__, __FUNCTION__);
}

    /**
     * archive_user(id) - статус archive у записи пользователя (table_name = 'user').
     */
    public function archive_user(int $id): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($this->set_user_status($id, 'archive'), __CLASS__, __FUNCTION__);
}

    /**
     * set_user_status(id, status) - сменить статус записи пользователя.
     */
    public function set_user_status(int $id, string $status): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        if ($id <= 0) {
            $this->fail('Invalid user id');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        if (!$this->ensureDbAlias()) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $status = trim($status);
        if (!in_array($status, ['actual', 'archive', 'delete'], true)) {
            $this->fail('Invalid status');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $sql = "
            UPDATE sogerien
            SET status = :status, updated_at = now()
            WHERE table_name = 'user'
            AND id = :user_id;
            ";
        $res = $this->dbQuery($sql, ['user_id' => $id, 'status' => $status]);
        if (($res['result'] ?? false) !== true) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    /**
     * confirm_email(code) - отмечаем validate.email = true для пользователя с данным code.
     */
    public function confirm_email(string $code): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        $code = trim($code);
        if ($code === '') {
            $this->fail('Empty code');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        if (!$this->ensureDbAlias()) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $sql = "
            UPDATE sogerien
            SET table_value = jsonb_set(
                    COALESCE(table_value,'{}'::jsonb),
                    '{validate,email}',
                    to_jsonb('true'::text),
                    true
                ),
                updated_at = now()
            WHERE table_name = 'user'
            AND status <> 'delete'
            AND (table_value->>'code') = :code;
            ";

        $res = $this->dbQuery($sql, ['code' => $code]);
        if (($res['result'] ?? false) !== true) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $rowCount = (int)($res['rowCount'] ?? 0);
        if ($rowCount < 1) {
            $this->fail('Code not found');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    /**
     * reset_password(emailOrLogin, newPassword) - меняем pass_hash по email ИЛИ login.
     */
    public function reset_password(string $emailOrLogin, string $newPassword): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        $emailOrLogin = trim($emailOrLogin);
        if ($emailOrLogin === '') {
            $this->fail('Empty email/login');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        if ($newPassword === '') {
            $this->fail('Empty new password');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        if (!$this->ensureDbAlias()) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        // Ищем пользователя по login или email в table_value
        $norm = mb_strtolower(trim($emailOrLogin));
        $isEmail = str_contains($emailOrLogin, '@');

        $sqlFind = $isEmail
            ? "
            SELECT id
            FROM sogerien
            WHERE table_name = 'user'
              AND lower(trim(table_value->>'email')) = :val
              AND status <> 'delete'
            LIMIT 1;
            "
            : "
            SELECT id
            FROM sogerien
            WHERE table_name = 'user'
              AND lower(trim(table_value->>'login')) = :val
              AND status <> 'delete'
            LIMIT 1;
            ";

        $res = $this->dbQuery($sqlFind, ['val' => $norm]);
        if (($res['result'] ?? false) !== true) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        $rows = $res['rows'] ?? [];
        if (!is_array($rows) || count($rows) === 0) {
            $this->fail('User not found for given email/login');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        $rowId = (int)($rows[0]['id'] ?? 0);
        if ($rowId <= 0) {
            $this->fail('User not found');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $pass_hash = $this->make_password_hash($newPassword);
        if ($pass_hash === null) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $sqlUpdate = "
            UPDATE sogerien
            SET table_value = jsonb_set(
                    COALESCE(table_value,'{}'::jsonb),
                    '{pass_hash}',
                    to_jsonb(:pass_hash::text),
                    true
                ),
                updated_at = now()
            WHERE table_name = 'user'
            AND id = :id
            AND status <> 'delete';
            ";
        $resUpd = $this->dbQuery($sqlUpdate, [
            'pass_hash' => $pass_hash,
            'id'        => $rowId,
        ]);
        if (($resUpd['result'] ?? false) !== true) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    /* =========================
     * helpers
     * ========================= */

    /**
     * Оставить только роли, существующие в config/rules (через Roles).
     *
     * @param array<int,string> $roles
     * @return array<int,string>
     */
    private function filter_roles_by_allowed(array $roles): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        Sogerien::Roles()->init_db_alias($this->db_alias);
        $allowed = Sogerien::Roles()->get_roles();
        if ($allowed === []) {
            return   Sogerien::Debager()->capture_return($roles, __CLASS__, __FUNCTION__);
        }
        return   Sogerien::Debager()->capture_return(array_values(array_intersect($roles, $allowed)), __CLASS__, __FUNCTION__);
}

    private function ensureDbAlias(): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($this->db_alias === '') {
            $this->fail('db_alias is empty, call init_db_alias()');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    /**
     * Обёртка над DbController->sql_request с JSON-decode и обработкой ошибок.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function dbQuery(string $sql, array $params = []): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $json = Sogerien::DbController()->sql_request($this->db_alias, [
            'sql'    => $sql,
            'params' => $params,
        ]);

        $data = json_decode($json, true);
        if (!is_array($data) || !array_key_exists('result', $data)) {
            $this->fail('Invalid DB response');
            return   Sogerien::Debager()->capture_return(['result' => false], __CLASS__, __FUNCTION__);
        }

        if ($data['result'] !== true) {
            $msg = '';
            if (isset($data['error']['message']) && is_string($data['error']['message'])) {
                $msg = $data['error']['message'];
            }
            $this->fail($msg !== '' ? $msg : 'DB error');
            return   Sogerien::Debager()->capture_return(['result' => false], __CLASS__, __FUNCTION__);
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return($data, __CLASS__, __FUNCTION__);
}

    /**
     * @param array<string,mixed> $patch
     */
    private function encodeJsonPatch(array $patch): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $json = json_encode(
            $patch,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if ($json === false) {
            throw new \RuntimeException('Failed to encode JSON patch');
        }

        return   Sogerien::Debager()->capture_return(str_replace("\0", '', $json), __CLASS__, __FUNCTION__);
}

    private function ok(): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->status = true;
        $this->error  = '';
}

    private function fail(string $msg): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->status = false;
        $this->error  = $msg;
}

    private function is_password_hash(string $hash): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($hash === '') {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        $info = password_get_info($hash);
        return   Sogerien::Debager()->capture_return(isset($info['algo']) && $info['algo'] !== null && $info['algo'] !== 0, __CLASS__, __FUNCTION__);
}

    private function password_algo(): string|int
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if (defined('PASSWORD_ARGON2ID')) {
            return   Sogerien::Debager()->capture_return(PASSWORD_ARGON2ID, __CLASS__, __FUNCTION__);
        }
        return   Sogerien::Debager()->capture_return(PASSWORD_BCRYPT, __CLASS__, __FUNCTION__);
}

    private function make_password_hash(string $password): ?string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($password === '') {
            $this->fail('Password is empty');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $algo = $this->password_algo();
        $options = [];
        if ($algo === PASSWORD_BCRYPT) {
            $options = ['cost' => 12];
        }

        $hash = password_hash($password, $algo, $options);
        if (!is_string($hash) || $hash === '') {
            $this->fail('Password hash failed');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        return   Sogerien::Debager()->capture_return($hash, __CLASS__, __FUNCTION__);
}
}
