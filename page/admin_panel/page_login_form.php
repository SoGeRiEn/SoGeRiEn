<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

$t = static function (string $key, string $fallback = ''): string {
    $value = Sogerien::Lang()->get($key);
    if ($fallback !== '' && $value === $key) {
        return $fallback;
    }

    return $value;
};

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}

$loginError = '';
$next = trim((string)($_GET['next'] ?? $_POST['next'] ?? '/'));
if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
    $next = '/';
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $login = trim((string)($_POST['login'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        $loginError = $t('auth.invalid_login_or_password', 'Invalid login or password');
    } else {
        $users = Sogerien::Users();
        $users->init_db_alias($dbAlias);
        $row = str_contains($login, '@') ? $users->get_user_by_email($login) : $users->get_user_by_login($login);
        $tableValue = is_array($row) && isset($row['table_value']) && is_array($row['table_value']) ? $row['table_value'] : [];
        $passHash = is_array($tableValue) ? (string)($tableValue['pass_hash'] ?? '') : '';

        if ($passHash === '' || !password_verify($password, $passHash)) {
            $loginError = $t('auth.invalid_login_or_password', 'Invalid login or password');
        } else {
            $rolesRaw = is_array($tableValue['roles'] ?? null) ? $tableValue['roles'] : [];
            $roles = [];
            foreach ($rolesRaw as $role) {
                $role = trim((string)$role);
                if ($role !== '') {
                    $roles[$role] = true;
                }
            }
            if ($roles === []) {
                $roles['user'] = true;
            }

            $userId = (int)($row['id'] ?? 0);
            $token = $users->create_token($userId, $roles);
            if ($token === '' || !$users->save_token_to_cookie($token, 30)) {
                $loginError = $users->error !== '' ? $users->error : $t('auth.login_failed', 'Login failed');
            } else {
                header('Location: ' . $next, true, 302);
                Sogerien::markDone();
                Sogerien::exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $h(Sogerien::Lang()->get_current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $h($t('auth.title_login', 'Authorization')) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/vue@3"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div id="app" class="d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow p-4" style="min-width: 300px; max-width: 400px; width: 100%;">
        <h4 class="mb-3 text-center"><?= $h($t('auth.login_to_system', 'Login to system')) ?></h4>
        <?php if ($loginError !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= $h($loginError) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="next" value="<?= $h($next) ?>">
            <div class="mb-3">
                <label class="form-label"><?= $h($t('common.login', 'Login')) ?></label>
                <input name="login" type="text" class="form-control" value="<?= $h((string)($_POST['login'] ?? '')) ?>" placeholder="<?= $h($t('auth.enter_login', 'Enter login')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label"><?= $h($t('common.password', 'Password')) ?></label>
                <input name="password" type="password" class="form-control" placeholder="<?= $h($t('auth.enter_password', 'Enter password')) ?>">
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= $h($t('auth.sign_in', 'Sign in')) ?></button>
        </form>
    </div>
</div>

</body>
</html>
<?php
Sogerien::exit();
?>
