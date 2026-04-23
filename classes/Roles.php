<?php
declare(strict_types=1);

/* Roles.php
 * Управление правилами доступа: список ролей (roles) и права на методы (login_form, perm.orders.read и т.д.).
 * Хранится в sogerien: table_name = 'config', name = 'rules', table_value = JSON.
 */

final class Roles
{
    public bool $status = false;
    public string $error = '';

    /**
     * Публичный снапшот документа rules из БД.
     * Заполняется один раз при первом успешном чтении и далее не меняется изнутри класса.
     *
     * @var array<string,mixed>
     */
    public array $rules_snapshot = [];

    public string $db_alias = '';

    /** @var array<string,mixed>|null полный документ rules из БД (roles + все права) */
    private ?array $rules_data = null;

    /** @var int|null id строки config/rules в sogerien для UPDATE */
    private ?int $rules_row_id = null;

    public function init_db_alias(string $alias): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->db_alias = trim($alias);
        $this->rules_data = null;
        $this->rules_row_id = null;
        $this->ok();
}

    /**
     * Загрузить документ rules из БД (лениво).
     *
     * @return array<string,mixed>
     */
    private function load_rules(): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($this->rules_data !== null) {
            return   Sogerien::Debager()->capture_return($this->rules_data, __CLASS__, __FUNCTION__);
        }

        $this->rules_data = ['roles' => []];

        if ($this->db_alias === '') {
            return   Sogerien::Debager()->capture_return($this->rules_data, __CLASS__, __FUNCTION__);
        }

        $json = Sogerien::DbController()->sql_request($this->db_alias, [
            'sql'   => "
                SELECT id, table_value
                FROM sogerien
                WHERE table_name = :table_name AND name = :name
                LIMIT 1",
            'params' => [
                ':table_name' => 'config',
                ':name'       => 'rules',
            ],
        ]);

        $resp = json_decode($json, true);
        if (!is_array($resp) || !($resp['result'] ?? false)) {
            return   Sogerien::Debager()->capture_return($this->rules_data, __CLASS__, __FUNCTION__);
        }

        $rows = $resp['rows'] ?? [];
        if (!is_array($rows) || count($rows) === 0) {
            return   Sogerien::Debager()->capture_return($this->rules_data, __CLASS__, __FUNCTION__);
        }

        $row = $rows[0];
        $this->rules_row_id = isset($row['id']) ? (int)$row['id'] : null;

        $table_value = $row['table_value'] ?? null;
        if (is_array($table_value)) {
            $this->rules_data = $table_value;
        } elseif (is_string($table_value)) {
            $decoded = json_decode($table_value, true);
            if (is_array($decoded)) {
                $this->rules_data = $decoded;
            }
        }

        if (!isset($this->rules_data['roles']) || !is_array($this->rules_data['roles'])) {
            $this->rules_data['roles'] = [];
        }

        // Публичный нередактируемый снапшот для использования в других классах (Rules и др.).
        // Заполняем только при первом успешном чтении из БД и больше не трогаем.
        if ($this->rules_snapshot === []) {
            $this->rules_snapshot = $this->rules_data;
        }

        return   Sogerien::Debager()->capture_return($this->rules_data, __CLASS__, __FUNCTION__);
}

    /**
     * Сохранить текущий rules_data в БД.
     */
    private function save_rules(): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($this->db_alias === '') {
            $this->fail('db_alias is empty, call init_db_alias()');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $json = json_encode(
            $this->rules_data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($json === false) {
            $this->fail('Failed to encode rules JSON');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $pdo = Sogerien::DbController()->pdo($this->db_alias);

        try {
            if ($this->rules_row_id !== null && $this->rules_row_id > 0) {
                $sql = "
                    UPDATE sogerien
                    SET table_value = :tv::jsonb, updated_at = now()
                    WHERE id = :id
                ";
                $st = $pdo->prepare($sql);
                $st->execute([':tv' => $json, ':id' => $this->rules_row_id]);
            } else {
                $sql = "
                    INSERT INTO sogerien (table_name, name, table_index, table_value, status, created_at, updated_at)
                    VALUES ('config', 'rules', NULL, :tv::jsonb, 'actual', now(), now())
                ";
                $st = $pdo->prepare($sql);
                $st->execute([':tv' => $json]);
                $idJson = Sogerien::DbController()->sql_request($this->db_alias, [
                    'sql'   => "SELECT id FROM sogerien WHERE table_name = 'config' AND name = 'rules' ORDER BY id DESC LIMIT 1",
                    'params' => [],
                ]);
                $idResp = json_decode($idJson, true);
                if (is_array($idResp) && !empty($idResp['rows'][0]['id'])) {
                    $this->rules_row_id = (int)$idResp['rows'][0]['id'];
                }
            }
            $this->ok();
            return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
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

    /* =========================
     * Группы прав (roles)
     * ========================= */

    /**
     * Получить список имён ролей (ключи из rules['roles']).
     *
     * @return array<int,string>
     */
    public function get_roles(): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');
        $data = $this->load_rules();
        $roles = $data['roles'] ?? [];
        if (!is_array($roles)) {
            $this->ok();
            return   Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return   Sogerien::Debager()->capture_return(array_keys($roles), __CLASS__, __FUNCTION__);
}

    /**
     * Добавить роль. Если уже есть — успех без изменений.
     */
    public function add_role(string $name): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');
        $name = trim($name);
        if ($name === '') {
            $this->fail('Role name is empty');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        if ($this->db_alias === '') {
            $this->fail('db_alias is empty, call init_db_alias()');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $this->load_rules();
        if (!isset($this->rules_data['roles']) || !is_array($this->rules_data['roles'])) {
            $this->rules_data['roles'] = [];
        }
        $this->rules_data['roles'][$name] = true;

        if (!$this->save_rules()) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    /**
     * Переименовать роль. Обновляет имя в rules['roles'] и во всех правах (в каждом permission['roles']).
     */
    public function update_role(string $old_name, string $new_name): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');
        $old_name = trim($old_name);
        $new_name = trim($new_name);
        if ($old_name === '' || $new_name === '') {
            $this->fail('Old and new role names must be non-empty');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        if ($old_name === $new_name) {
            $this->ok();
            return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        }
        if ($this->db_alias === '') {
            $this->fail('db_alias is empty, call init_db_alias()');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $this->load_rules();
        $roles = &$this->rules_data['roles'];
        if (!is_array($roles) || !isset($roles[$old_name])) {
            $this->fail('Role not found: ' . $old_name);
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        unset($roles[$old_name]);
        $roles[$new_name] = true;

        foreach ($this->rules_data as $key => $entry) {
            if ($key === 'roles' || !is_array($entry)) {
                continue;
            }
            if (isset($entry['roles']) && is_array($entry['roles']) && isset($entry['roles'][$old_name])) {
                $entry['roles'][$new_name] = true;
                unset($entry['roles'][$old_name]);
                $this->rules_data[$key] = $entry;
            }
        }

        if (!$this->save_rules()) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    /**
     * Удалить роль. Удаляет из rules['roles'] и из roles во всех правах.
     */
    public function delete_role(string $name): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');
        $name = trim($name);
        if ($name === '') {
            $this->fail('Role name is empty');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        if ($this->db_alias === '') {
            $this->fail('db_alias is empty, call init_db_alias()');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $this->load_rules();
        $roles = &$this->rules_data['roles'];
        if (!is_array($roles) || !isset($roles[$name])) {
            $this->fail('Role not found: ' . $name);
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        unset($roles[$name]);

        foreach ($this->rules_data as $key => $entry) {
            if ($key === 'roles' || !is_array($entry)) {
                continue;
            }
            if (isset($entry['roles']) && is_array($entry['roles']) && isset($entry['roles'][$name])) {
                unset($entry['roles'][$name]);
                $this->rules_data[$key] = $entry;
            }
        }

        if (!$this->save_rules()) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    /* =========================
     * Права доступа (permission entries)
     * ========================= */

    /**
     * Нормализовать массив в формат set (key => true).
     *
     * Поддерживает два варианта входа:
     *  - список значений: ['admin','guest'] или [0=>'admin',1=>'guest'] => ['admin'=>true,'guest'=>true]
     *  - уже set (key=>value): ['admin'=>true,'guest'=>false] или [23=>1,25=>1] => берём ключи с truthy
     *
     * Важно: [23=>1, 25=>1] из БД — это set (id пользователей), ключи 23,25, а не список значений 1,1.
     *
     * @param array<int|string,mixed> $arr
     * @return array<string,bool>
     */
    private function to_set(array $arr): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $out = [];
        if ($arr === []) {
            return   Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
        }

        $keys = array_keys($arr);
        $isList = ($keys === range(0, count($arr) - 1));

        if ($isList) {
            // Список значений: ключами делаем значения
            foreach ($arr as $v) {
                $v = trim((string)$v);
                if ($v === '') {
                    continue;
                }
                $out[$v] = true;
            }
            return   Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
        }

        // Уже set (key=>value): используем ключи, берём только truthy значения
        foreach ($arr as $k => $v) {
            $k = trim((string)$k);
            if ($k === '' || !$v) {
                continue;
            }
            $out[$k] = true;
        }

        return   Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
}

    /**
     * Получить одно право по ключу (login_form, perm.orders.read и т.д.).
     *
     * @return array{roles:array<string,bool>,users_id:array<string,bool>,notes?:string}|null
     */
    public function get_permission(string $key): ?array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');
        $key = trim($key);
        if ($key === '' || $key === 'roles') {
            $this->fail('Invalid permission key');
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $data = $this->load_rules();
        if (!isset($data[$key]) || !is_array($data[$key])) {
            $this->ok();
            return   Sogerien::Debager()->capture_return(null, __CLASS__, __FUNCTION__);
        }

        $entry = $data[$key];
        $out = [
            'roles'   => $this->to_set($entry['roles'] ?? []),
            'users_id' => $this->to_set($entry['users_id'] ?? []),
        ];
        if (isset($entry['notes']) && is_string($entry['notes'])) {
            $out['notes'] = $entry['notes'];
        }
        $this->ok();
        return   Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
}

    /**
     * Список всех ключей прав (без 'roles').
     *
     * @return array<int,string>
     */
    public function get_all_permission_keys(): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');
        $data = $this->load_rules();
        $keys = [];
        foreach (array_keys($data) as $k) {
            if ($k === 'roles') {
                continue;
            }
            $keys[] = $k;
        }
        $this->ok();
        return   Sogerien::Debager()->capture_return($keys, __CLASS__, __FUNCTION__);
}

    /**
     * Добавить право доступа.
     *
     * @param array<int|string,mixed> $roles список ролей (будут нормализованы в set)
     * @param array<int|string,mixed> $users_id список user id (будут нормализованы в set)
     */
    public function add_permission(string $key, array $roles = [], array $users_id = [], string $notes = ''): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');
        $key = trim($key);
        if ($key === '' || $key === 'roles') {
            $this->fail('Invalid permission key');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        if ($this->db_alias === '') {
            $this->fail('db_alias is empty, call init_db_alias()');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $this->load_rules();
        if (isset($this->rules_data[$key])) {
            $this->fail('Permission already exists: ' . $key);
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $this->rules_data[$key] = [
            'roles'   => $this->to_set($roles),
            'users_id' => $this->to_set($users_id),
        ];
        if ($notes !== '') {
            $this->rules_data[$key]['notes'] = $notes;
        }

        if (!$this->save_rules()) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    /**
     * Изменить право доступа. Переданные roles/users_id/notes заменяют текущие.
     *
     * @param array<int|string,mixed>|null $roles null = не менять
     * @param array<int|string,mixed>|null $users_id null = не менять
     */
    public function update_permission(
        string $key,
        ?array $roles = null,
        ?array $users_id = null,
        ?string $notes = null,
        ?string $new_key = null
    ): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');
        $key = trim($key);
        if ($key === '' || $key === 'roles') {
            $this->fail('Invalid permission key');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        if ($this->db_alias === '') {
            $this->fail('db_alias is empty, call init_db_alias()');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $this->load_rules();
        if (!isset($this->rules_data[$key]) || !is_array($this->rules_data[$key])) {
            $this->fail('Permission not found: ' . $key);
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $target_key = $key;
        if ($new_key !== null) {
            $new_key = trim($new_key);
            if ($new_key === '' || $new_key === 'roles') {
                $this->fail('Invalid new permission key');
                return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
            }
            if ($new_key !== $key && isset($this->rules_data[$new_key])) {
                $this->fail('Permission already exists: ' . $new_key);
                return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
            }
            $target_key = $new_key;
        }

        $entry = $this->rules_data[$key];
        if ($roles !== null) {
            $entry['roles'] = $this->to_set($roles);
        }
        if ($users_id !== null) {
            $entry['users_id'] = $this->to_set($users_id);
        }
        if ($notes !== null) {
            $entry['notes'] = $notes;
        }

        if ($target_key !== $key) {
            unset($this->rules_data[$key]);
        }
        $this->rules_data[$target_key] = $entry;

        if (!$this->save_rules()) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}

    /**
     * Удалить право доступа.
     */
    public function delete_permission(string $key): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');
        $key = trim($key);
        if ($key === '' || $key === 'roles') {
            $this->fail('Invalid permission key');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        if ($this->db_alias === '') {
            $this->fail('db_alias is empty, call init_db_alias()');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $this->load_rules();
        if (!isset($this->rules_data[$key])) {
            $this->fail('Permission not found: ' . $key);
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        unset($this->rules_data[$key]);

        if (!$this->save_rules()) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
        $this->ok();
        return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
}
}
