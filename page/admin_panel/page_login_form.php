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
        <div class="text-center text-muted small my-3">— или —</div>
        <a class="btn btn-outline-dark w-100 d-flex align-items-center justify-content-center gap-2" href="/auth/google?next=<?= $h(rawurlencode($next)) ?>">
            <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.345 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.655 3.58 9 3.58z"/></svg>
            Авторизация через Google
        </a>
        <div class="text-center small mt-3">
            <a href="/register?next=<?= $h(rawurlencode($next)) ?>">Create account</a>
        </div>
    </div>
</div>

</body>
</html>
<?php
Sogerien::exit();
?>
