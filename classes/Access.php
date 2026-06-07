<?php
declare(strict_types=1);

final class Access
{
    use SogerienClassHelp;

    public bool $status = true;
    public string $error = '';

    /** alias подключения к БД (у тебя это 'front') */
    public string $dbAlias = 'front';

    public function check_access(string $method): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->fail('');

        $method = trim($method);
        if ($method === '') {
            $this->fail('method is empty');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        // 1) токен из cookie (access_token) c корректным ключом
        $keyFile = (string)(Sogerien::$patch_to_cookies_keyFile ?? '');
        if ($keyFile === '') {
            $this->fail('Sogerien::$patch_to_cookies_keyFile is empty');
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $at = Sogerien::AccessToken();
        $at->patch_to_keyFile = $keyFile;

        $token = $at->load_token_for_cookie();
        if (!$at->status || $token === '') {
            $this->ok(); // просто нет авторизации
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        // 2) decrypt токена
        $payload = $at->read_token($token);
        if (!$at->status || !is_array($payload)) {
            $this->ok();
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        $userId = $this->extract_user_id($payload);
        if ($userId < 1) {
            $this->ok();
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        // быстрый “бог-режим”, если ты захочешь
        if (($payload['superuser'] ?? false) === true) {
            $this->ok();
            return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        }

        // 3) грузим правило доступа из config/rules через Roles
        Sogerien::Roles()->init_db_alias($this->dbAlias);
        $perm = Sogerien::Roles()->get_permission($method);
        if ($perm === null) {
            $this->ok();
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        // 4.1 прямое назначение на user
        $usersSet = $perm['users_id'] ?? [];
        if (is_array($usersSet) && isset($usersSet[(string)$userId])) {
            $this->ok();
            return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        }

        // 4.2 назначение по ролям
        $needRoles = $perm['roles'] ?? null;
        if (!is_array($needRoles) || $needRoles === []) {
            $this->ok();
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }

        // user_group в токене: set ['admin'=>true,'user'=>true]
        $userGroups = [];
        $grp = $payload['user_group'] ?? [];
        if (is_array($grp)) {
            foreach ($grp as $g => $_true) {
                $g = trim((string)$g);
                if ($g === '') continue;
                $userGroups[$g] = true;
            }
        }

        foreach ($needRoles as $g => $_true) {
            $g = trim((string)$g);
            if ($g !== '' && isset($userGroups[$g])) {
                $this->ok();
                return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
            }
        }

        $this->ok();
        return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
}

    private function extract_user_id(array $payload): int
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        foreach (['user_id','id','uid','sogerien_id'] as $k) {
            $v = $payload[$k] ?? null;
            if (is_int($v) && $v > 0) return   Sogerien::Debager()->capture_return($v, __CLASS__, __FUNCTION__);
            if (is_string($v)) {
                $n = (int)$v;
                if ($n > 0) return   Sogerien::Debager()->capture_return($n, __CLASS__, __FUNCTION__);
            }
        }
        return   Sogerien::Debager()->capture_return(0, __CLASS__, __FUNCTION__);
}

    private function ok(): void { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }  $this->status = true; $this->error = '';
}
    private function fail(string $m): void { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }  $this->status = ($m === ''); $this->error = $m;
}
}
