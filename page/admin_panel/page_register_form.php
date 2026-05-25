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

$next = trim((string)($_GET['next'] ?? $_POST['next'] ?? '/client/dashboard'));
if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
    $next = '/client/dashboard';
}

$registerError = '';
$email = '';
$login = '';

$sendRegistrationEmail = static function (string $email, string $login): bool {
    $email = trim($email);
    $login = trim($login);
    if ($email === '' || $login === '') {
        return false;
    }

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'proxymint.com'));
    if ($host === '') {
        $host = 'proxymint.com';
    }
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    $host = preg_replace('/[^a-zA-Z0-9.-]/', '', $host) ?? $host;
    if ($host === '') {
        $host = 'proxymint.com';
    }

    $subject = 'Registration completed - proxymint.com';
    $body = implode("\r\n", [
        'You are registered on proxymint.com.',
        '',
        'Login: ' . $login,
        'Email: ' . $email,
        '',
        'Login page: https://' . $host . '/login',
    ]);

    try {
        $smtp = Sogerien::SmtpMailer();
        $smtp->send(
            ['email' => SMTP_USER, 'name' => 'ProxyMint'],
            $email,
            $subject,
            [
                'text' => $body,
                'reply_to' => ['email' => SMTP_USER, 'name' => 'ProxyMint'],
                'headers' => ['X-Mailer' => 'ProxyMint-Sogerien-SMTP'],
            ]
        );
        return true;
    } catch (Throwable $e) {
        error_log('Registration SMTP send failed: ' . $e->getMessage());

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ProxyMint <noreply@' . $host . '>',
        ];

        return @mail($email, $subject, $body, implode("\r\n", $headers));
    }
};

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $login = trim((string)($_POST['login'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $passwordRepeat = (string)($_POST['password_repeat'] ?? '');

    if ($login === '' && $email !== '') {
        $login = (string)(explode('@', $email)[0] ?? '');
        $login = preg_replace('/[^a-zA-Z0-9._-]/', '', $login) ?? '';
    }

    if ($email === '' || $login === '' || $password === '' || $passwordRepeat === '') {
        $registerError = $t('auth.fill_all_fields', 'Fill all fields');
    } elseif ($password !== $passwordRepeat) {
        $registerError = $t('auth.passwords_do_not_match', 'Passwords do not match');
    } else {
        $users = Sogerien::Users();
        $users->init_db_alias($dbAlias);
        $row = $users->register_user([
            'login' => $login,
            'email' => $email,
            'password' => $password,
            'validate' => ['email' => 'false', 'phone' => 'false'],
            'auth_provider' => 'email',
        ]);

        if (!is_array($row)) {
            $errorMap = [
                'Email already exists' => $t('auth.email_in_use', 'Email already exists'),
                'Login format is invalid' => $t('auth.login_format_invalid', 'Login can contain only letters, digits, dot, underscore and dash'),
                'Email format is invalid' => $t('auth.invalid_email', 'Invalid email'),
                'Password must be at least 8 characters' => $t('auth.password_min8', 'Password must be at least 8 characters'),
                'login, email and password are required' => $t('auth.fill_all_fields', 'Fill all fields'),
            ];
            $registerError = $errorMap[$users->error] ?? ($users->error !== '' ? $users->error : $t('auth.registration_failed', 'Registration failed'));
        } else {
            $tableValue = $row['table_value'] ?? [];
            if (is_string($tableValue)) {
                $decoded = json_decode($tableValue, true);
                $tableValue = is_array($decoded) ? $decoded : [];
            }
            $rolesRaw = is_array($tableValue['roles'] ?? null) ? $tableValue['roles'] : ['user'];
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
                $registerError = $users->error !== '' ? $users->error : $t('auth.login_failed', 'Login failed');
            } else {
                if (!$sendRegistrationEmail($email, $login)) {
                    error_log('Registration email send failed for: ' . $email);
                }
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
    <title><?= $h($t('auth.title_register', 'Registration')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="d-flex justify-content-center align-items-center min-vh-100 py-4">
    <div class="card shadow p-4" style="min-width: 300px; max-width: 430px; width: 100%;">
        <h4 class="mb-3 text-center"><?= $h($t('auth.create_account', 'Create account')) ?></h4>
        <?php if ($registerError !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= $h($registerError) ?></div>
        <?php endif; ?>

        <a class="btn btn-outline-dark w-100 d-flex align-items-center justify-content-center gap-2 mb-3" href="/auth/google?next=<?= $h(rawurlencode($next)) ?>">
            Continue with Google
        </a>

        <div class="text-center text-muted small mb-3">or register with email</div>

        <form method="post">
            <input type="hidden" name="next" value="<?= $h($next) ?>">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input name="email" type="email" class="form-control" value="<?= $h($email) ?>" required autocomplete="email">
            </div>
            <div class="mb-3">
                <label class="form-label">Login</label>
                <input name="login" type="text" class="form-control" value="<?= $h($login) ?>" required autocomplete="username" pattern="[a-zA-Z0-9._-]{3,64}">
            </div>
            <div class="mb-3">
                <label class="form-label"><?= $h($t('common.password', 'Password')) ?></label>
                <input name="password" type="password" class="form-control" required autocomplete="new-password" minlength="8">
            </div>
            <div class="mb-3">
                <label class="form-label"><?= $h($t('auth.repeat_password', 'Repeat password')) ?></label>
                <input name="password_repeat" type="password" class="form-control" required autocomplete="new-password" minlength="8">
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= $h($t('auth.sign_up', 'Sign up')) ?></button>
        </form>

        <div class="text-center small mt-3">
            <a href="/login?next=<?= $h(rawurlencode($next)) ?>"><?= $h($t('auth.already_have_account', 'Already have an account? Sign in')) ?></a>
        </div>
    </div>
</div>

</body>
</html>
<?php
Sogerien::exit();
?>
