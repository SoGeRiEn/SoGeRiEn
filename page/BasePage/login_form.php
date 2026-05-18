<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

const DB_ALIAS = 'front';

$lang = Sogerien::Lang();
$t = static fn(string $key): string => $lang->get($key);

Sogerien::AccessCheck()->init_db_alias(DB_ALIAS);
$accessOk = Sogerien::AccessCheck()->check_access('login_form', 0, []);
if (!$accessOk) {
    Sogerien::Page()->title = $t('auth.access_denied');
    Sogerien::Page()->header();
    Sogerien::Page()->mainmenu();
    echo '<style>
        .pm-admin-shell > .pm-sidebar,
        .pm-admin-shell > .pm-mobile-backdrop { display: none !important; }
        .pm-admin-shell > .pm-main {
            margin-left: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        .pm-admin-shell .pm-mobile-toggle { display: none !important; }
    </style>';
    echo '<main class="pm-login-screen pm-login-screen-in-shell"><div class="pm-auth-card"><p class="text-danger mb-0">' .
        htmlspecialchars(Sogerien::AccessCheck()->errors ?: $t('auth.access_denied'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
        '</p></div></main>';
    Sogerien::Page()->footer();
    Sogerien::markDone();
    Sogerien::exit();
}

$domain = (string)(Sogerien::InputRequest()->domain ?? '');
$uri = (string)(Sogerien::InputRequest()->REQUEST_URI ?? '/login');
$pathOnly = strtok($uri, '?') ?: $uri;
$baseLoginUrl = $domain . $pathOnly;
$currentUrl = $baseLoginUrl;

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    setcookie('access_token', '', time() - 3600, '/', '', true, true);
    setcookie('access_token', '', time() - 3600, '/', '', false, true);

    if (!headers_sent()) {
        header('Location: ' . $baseLoginUrl, true, 302);
    }
    Sogerien::markDone();
    Sogerien::exit();
}

$formError = '';
$formInfo = '';
$formLogin = '';
$formRegisterLogin = '';
$formRegisterEmail = '';
$isAuthed = !empty($_COOKIE['access_token']);
$justLoggedIn = false;
$formMode = trim((string)($_POST['auth_action'] ?? ($_GET['mode'] ?? 'login')));
$resetTokenFromUrl = trim((string)($_GET['token'] ?? ''));
if ($resetTokenFromUrl !== '' && $formMode === 'login') {
    $formMode = 'reset';
}
if (!in_array($formMode, ['login', 'register', 'forgot', 'reset'], true)) {
    $formMode = 'login';
}

$nextPath = trim((string)($_POST['next'] ?? ($_GET['next'] ?? '')));
if ($nextPath === '' || !str_starts_with($nextPath, '/') || str_starts_with($nextPath, '//')) {
    $requestUriRaw = trim((string)($_SERVER['REQUEST_URI'] ?? ''));
    $requestPathRaw = trim((string)(parse_url($requestUriRaw, PHP_URL_PATH) ?? ''));
    if (
        $requestPathRaw !== ''
        && str_starts_with($requestPathRaw, '/')
        && !str_starts_with($requestPathRaw, '//')
        && !in_array($requestPathRaw, ['/admin', '/admin/', '/login', '/login/'], true)
    ) {
        $requestQueryRaw = trim((string)(parse_url($requestUriRaw, PHP_URL_QUERY) ?? ''));
        $nextPath = $requestPathRaw . ($requestQueryRaw !== '' ? ('?' . $requestQueryRaw) : '');
    } else {
        $nextPath = '/all_proxy';
    }
}
$refererPath = '/all_proxy';
$refererRaw = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
if ($refererRaw !== '') {
    $refererParts = parse_url($refererRaw);
    if (is_array($refererParts)) {
        $refererHost = (string)($refererParts['host'] ?? '');
        $currentHost = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($refererHost === '' || strcasecmp($refererHost, $currentHost) === 0) {
            $candidatePath = (string)($refererParts['path'] ?? '');
            $candidateQuery = (string)($refererParts['query'] ?? '');
            if ($candidatePath !== '' && str_starts_with($candidatePath, '/') && !str_starts_with($candidatePath, '//')) {
                $refererPath = $candidatePath;
                if ($candidateQuery !== '') {
                    $refererPath .= '?' . $candidateQuery;
                }
            }
        }
    }
}

$authPaths = ['/admin', '/admin/', '/login', '/login/'];
if (in_array($nextPath, $authPaths, true)) {
    $nextPath = $refererPath;
    if (in_array($nextPath, $authPaths, true)) {
        $nextPath = '/all_proxy';
    }
}

$issueSession = static function (array $row) use (&$formError, &$isAuthed, &$justLoggedIn, $t): void {
    $users = Sogerien::Users();
    $users->init_db_alias(DB_ALIAS);

    $tv = $row['table_value'] ?? [];
    if (is_string($tv)) {
        $tv = json_decode($tv, true);
    }
    $tv = is_array($tv) ? $tv : [];

    $rawRoles = $tv['roles'] ?? [];
    $userGroup = [];
    if (is_array($rawRoles)) {
        foreach ($rawRoles as $role) {
            $role = trim((string)$role);
            if ($role !== '') {
                $userGroup[$role] = true;
            }
        }
    }
    if ($userGroup === []) {
        $userGroup = ['user' => true];
    }

    $userId = (int)($row['id'] ?? 0);
    $token = $users->create_token($userId, $userGroup);
    if ($token === '' || !$users->status) {
        $formError = $users->error ?: $t('auth.session_create_error');
        return;
    }

    $saved = $users->save_token_to_cookie($token, 30);
    if (!$saved) {
        $formError = $users->error ?: $t('auth.session_save_error');
        return;
    }

    $isAuthed = true;
    $justLoggedIn = true;
};

$findUserByResetToken = static function (string $token): ?array {
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    $json = Sogerien::DbController()->sql_request(DB_ALIAS, [
        'sql' => "
            SELECT *
            FROM sogerien
            WHERE table_name = 'user'
              AND status <> 'delete'
              AND (table_value->>'reset_token') = :token
            LIMIT 1;
        ",
        'params' => ['token' => $token],
    ]);

    $decoded = json_decode($json, true);
    if (!is_array($decoded) || ($decoded['result'] ?? false) !== true) {
        return null;
    }

    $rows = $decoded['rows'] ?? [];
    if (!is_array($rows) || count($rows) < 1 || !is_array($rows[0])) {
        return null;
    }

    return $rows[0];
};

$sendResetPasswordEmail = static function (array $row, string $resetToken, string $resetUrl): bool {
    $tv = $row['table_value'] ?? [];
    if (is_string($tv)) {
        $tv = json_decode($tv, true);
    }
    $tv = is_array($tv) ? $tv : [];

    $login = trim((string)($tv['login'] ?? ''));
    $email = trim((string)($tv['email'] ?? ''));
    if ($email === '') {
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

    $subject = 'Password reset link - ' . $host;
    $bodyLines = [
        'Password reset request on ' . $host . '.',
        '',
        'Login: ' . $login,
        'Email: ' . $email,
        '',
        'Reset link: ' . $resetUrl,
        '',
        'If you did not request this, ignore this email.',
    ];
    $body = implode("\r\n", $bodyLines);

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
        error_log('Reset email send failed: ' . $e->getMessage());
        return false;
    }
};

$sendRegistrationEmail = static function (string $email, string $login, string $password): bool {
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

    $from = 'noreply@' . $host;
    $subject = 'Registration completed - ' . $host;

    $bodyLines = [
        'You are registered on ' . $host . '.',
        '',
        'Login: ' . $login,
        'Email: ' . $email,
        'Password: ' . $password,
        '',
        'Login page: https://' . $host . '/admin',
    ];
    $body = implode("\r\n", $bodyLines);

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
        error_log('SMTP send failed: ' . $e->getMessage());

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ProxyMint <' . $from . '>',
        ];

        return @mail($email, $subject, $body, implode("\r\n", $headers));
    }
};

if (!$isAuthed && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $authAction = trim((string)($_POST['auth_action'] ?? 'login'));

    if ($authAction === 'forgot') {
        $formMode = 'forgot';
        $forgotLogin = trim((string)($_POST['reset_login'] ?? ''));
        $forgotEmail = trim((string)($_POST['reset_email'] ?? ''));

        if ($forgotLogin === '' && $forgotEmail === '') {
            $formError = $t('auth.forgot_login_or_email_required');
        } elseif ($forgotEmail !== '' && !filter_var($forgotEmail, FILTER_VALIDATE_EMAIL)) {
            $formError = $t('auth.invalid_email');
        } else {
            $users = Sogerien::Users();
            $users->init_db_alias(DB_ALIAS);
            $userRow = null;

            if ($forgotLogin !== '') {
                $userRow = $users->get_user_by_login($forgotLogin);
            } elseif ($forgotEmail !== '') {
                $userRow = $users->get_user_by_email($forgotEmail);
            }

            if ($userRow === null) {
                $formError = $t('auth.user_not_found');
            } else {
                $userId = (int)($userRow['id'] ?? 0);
                if ($userId <= 0) {
                    $formError = $t('auth.user_not_found');
                } else {
                    try {
                        $resetToken = bin2hex(random_bytes(32));
                    } catch (Throwable $e) {
                        $resetToken = '';
                    }
                    if ($resetToken === '') {
                        $formError = $t('auth.reset_link_create_error');
                    } else {
                        $resetExpiresAt = time() + 3600;
                        $savedToken = $users->update_user($userId, [
                            'reset_token' => $resetToken,
                            'reset_token_expire_at' => $resetExpiresAt,
                        ]);

                        if (!$savedToken) {
                            $formError = $t('auth.reset_link_create_error');
                        } else {
                            $resetUrl = $baseLoginUrl . '?mode=reset&token=' . rawurlencode($resetToken);
                            $mailOk = $sendResetPasswordEmail($userRow, $resetToken, $resetUrl);
                            if (!$mailOk) {
                                $formError = $t('auth.reset_link_send_error');
                            } else {
                                $formInfo = $t('auth.reset_link_sent');
                                $formMode = 'login';
                            }
                        }
                    }
                }
            }
        }
    } elseif ($authAction === 'reset_password') {
        $formMode = 'reset';
        $resetToken = trim((string)($_POST['reset_token'] ?? ''));
        $newPassword = (string)($_POST['new_password'] ?? '');

        if ($resetToken === '') {
            $formError = $t('auth.reset_token_invalid');
        } elseif ($newPassword === '') {
            $formError = $t('auth.new_password_required');
        } elseif (mb_strlen($newPassword) < 8) {
            $formError = $t('auth.new_password_too_short');
        } else {
            $row = $findUserByResetToken($resetToken);
            if ($row === null) {
                $formError = $t('auth.reset_token_invalid');
            } else {
                $tv = $row['table_value'] ?? [];
                if (is_string($tv)) {
                    $tv = json_decode($tv, true);
                }
                $tv = is_array($tv) ? $tv : [];
                $exp = (int)($tv['reset_token_expire_at'] ?? 0);

                if ($exp <= 0 || $exp < time()) {
                    $formError = $t('auth.reset_token_expired');
                } else {
                    $users = Sogerien::Users();
                    $users->init_db_alias(DB_ALIAS);
                    $userId = (int)($row['id'] ?? 0);
                    if ($userId <= 0) {
                        $formError = $t('auth.user_not_found');
                    } else {
                        $okUpdate = $users->update_user($userId, [
                            'password' => $newPassword,
                            'reset_token' => '',
                            'reset_token_expire_at' => 0,
                        ]);
                        if (!$okUpdate) {
                            $formError = $t('auth.password_update_error');
                        } else {
                            $formInfo = $t('auth.password_reset_done');
                            $formMode = 'login';
                        }
                    }
                }
            }
        }
    } elseif ($authAction === 'register') {
        $formMode = 'register';

        $registerLogin = trim((string)($_POST['register_login'] ?? ''));
        $registerEmail = trim((string)($_POST['register_email'] ?? ''));
        $registerPassword = (string)($_POST['register_password'] ?? '');
        $registerPasswordRepeat = (string)($_POST['register_password_repeat'] ?? '');

        $formRegisterLogin = $registerLogin;
        $formRegisterEmail = $registerEmail;

        if ($registerLogin === '' || $registerEmail === '' || $registerPassword === '' || $registerPasswordRepeat === '') {
            $formError = $t('auth.fill_all_fields');
        } elseif ($registerPassword !== $registerPasswordRepeat) {
            $formError = $t('auth.passwords_do_not_match');
        } else {
            $users = Sogerien::Users();
            $users->init_db_alias(DB_ALIAS);
            $row = $users->register_user([
                'login' => $registerLogin,
                'email' => $registerEmail,
                'password' => $registerPassword,
            ]);

            if ($row === null) {
                $errorMap = [
                    'Login already exists' => $t('auth.login_taken'),
                    'Email already exists' => $t('auth.email_in_use'),
                    'Login format is invalid' => $t('auth.login_format_invalid'),
                    'Email format is invalid' => $t('auth.invalid_email'),
                    'Password must be at least 8 characters' => $t('auth.password_min8'),
                ];
                $formError = $errorMap[$users->error] ?? ($users->error !== '' ? $users->error : $t('auth.registration_failed'));
            } else {
                $mailOk = $sendRegistrationEmail($registerEmail, $registerLogin, $registerPassword);
                if (!$mailOk) {
                    error_log('Registration email send failed for: ' . $registerEmail);
                }
                $issueSession($row);
            }
        }
    } else {
        $login = trim((string)($_POST['login'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($login === '' || $password === '') {
            $formError = $t('auth.enter_credentials');
            $formLogin = $login;
        } else {
            $users = Sogerien::Users();
            $users->init_db_alias(DB_ALIAS);
            $row = $users->get_user_by_login($login);

            if ($row === null) {
                $formError = $t('auth.invalid_credentials');
                $formLogin = $login;
            } else {
                $tv = $row['table_value'] ?? [];
                if (is_string($tv)) {
                    $tv = json_decode($tv, true);
                }
                $tv = is_array($tv) ? $tv : [];

                $passHash = (string)($tv['pass_hash'] ?? '');
                $userId = (int)($row['id'] ?? 0);

                if ($userId <= 0 || $passHash === '' || !password_verify($password, $passHash)) {
                    $formError = $t('auth.invalid_credentials');
                    $formLogin = $login;
                } else {
                    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
                    if (password_needs_rehash($passHash, $algo)) {
                        $users->update_user($userId, ['password' => $password]);
                    }

                    $issueSession($row);
                }
            }
        }
    }
}

if ($justLoggedIn) {
    if (!headers_sent()) {
        header('Location: ' . $nextPath, true, 302);
    }
    Sogerien::markDone();
    Sogerien::exit();
}

Sogerien::Page()->title = $isAuthed ? $t('auth.title_ready') : (
    $formMode === 'register'
        ? $t('auth.title_register')
        : ($formMode === 'forgot' ? $t('auth.title_forgot') : ($formMode === 'reset' ? $t('auth.title_reset') : $t('auth.title_login'))
));
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
echo '<style>
    .pm-admin-shell > .pm-sidebar,
    .pm-admin-shell > .pm-mobile-backdrop { display: none !important; }
    .pm-admin-shell > .pm-main {
        margin-left: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    .pm-admin-shell .pm-mobile-toggle { display: none !important; }
</style>';

$currentUrlEsc = htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formLoginEsc = htmlspecialchars($formLogin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formRegisterLoginEsc = htmlspecialchars($formRegisterLogin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formRegisterEmailEsc = htmlspecialchars($formRegisterEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formErrorEsc = htmlspecialchars($formError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formInfoEsc = htmlspecialchars($formInfo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$logoutUrlEsc = htmlspecialchars($baseLoginUrl . '?logout=1', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$nextPathEsc = htmlspecialchars($nextPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$brandLogoHtml = Sogerien::Page()->brand_logo_html('pm-auth-brand-mark');

if ($isAuthed) {
    $alreadyLoggedIn = htmlspecialchars($t('auth.already_logged_in'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $sessionActive = htmlspecialchars($t('auth.session_active'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $logoutLabel = htmlspecialchars($t('auth.logout_from_system'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo <<<HTML
<main class="pm-login-screen pm-login-screen-in-shell">
    <div class="pm-auth-card">
        <div class="pm-auth-brand">
            {$brandLogoHtml}
            <div class="pm-auth-brand-copy">
                <div class="pm-auth-brand-title">ProxyMint</div>
                <div class="pm-auth-brand-sub">Dashboard 1.1</div>
            </div>
        </div>
        <h4 class="mb-3 text-center">{$alreadyLoggedIn}</h4>
        <p class="text-center mb-4">{$sessionActive}</p>
        <a href="{$logoutUrlEsc}" class="btn btn-outline-danger w-100">{$logoutLabel}</a>
    </div>
</main>
HTML;

    Sogerien::Page()->footer();
    Sogerien::markDone();
    return;
}

$errorHtml = '';
if ($formError !== '') {
    $errorHtml = '<div class="alert alert-danger" role="alert">' . $formErrorEsc . '</div>';
}
$infoHtml = '';
if ($formInfo !== '') {
    $infoHtml = '<div class="alert alert-success" role="alert">' . $formInfoEsc . '</div>';
}

$loginTabClass = $formMode === 'login' ? 'btn btn-primary' : 'btn btn-outline-primary';
$registerTabClass = $formMode === 'register' ? 'btn btn-primary' : 'btn btn-outline-primary';
$loginTabClassEsc = htmlspecialchars($loginTabClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$registerTabClassEsc = htmlspecialchars($registerTabClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$forgotTabClass = $formMode === 'forgot' ? 'btn btn-primary' : 'btn btn-outline-primary';
$forgotTabClassEsc = htmlspecialchars($forgotTabClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$loginTabUrlEsc = htmlspecialchars($currentUrl . '?mode=login&next=' . rawurlencode($nextPath), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$registerTabUrlEsc = htmlspecialchars($currentUrl . '?mode=register&next=' . rawurlencode($nextPath), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$forgotTabUrlEsc = htmlspecialchars($currentUrl . '?mode=forgot&next=' . rawurlencode($nextPath), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$switchLogin = htmlspecialchars($t('auth.sign_in'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$switchRegister = htmlspecialchars($t('auth.register'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$switchForgot = htmlspecialchars($t('auth.forgot_password'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$titleAuth = htmlspecialchars(
    $formMode === 'register'
        ? $t('auth.create_account')
        : ($formMode === 'forgot' ? $t('auth.forgot_password_title') : ($formMode === 'reset' ? $t('auth.reset_password_title') : $t('auth.login_to_system'))),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

if ($formMode === 'register') {
    $labelRegisterLogin = htmlspecialchars($t('common.login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $labelRegisterEmail = htmlspecialchars($t('common.email'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $labelRegisterPassword = htmlspecialchars($t('common.password'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $labelRegisterPasswordRepeat = htmlspecialchars($t('auth.repeat_password'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $placeholderRegisterLogin = htmlspecialchars($t('auth.enter_login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $placeholderRegisterEmail = htmlspecialchars($t('auth.enter_email'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $placeholderRegisterPassword = htmlspecialchars($t('auth.enter_password'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $placeholderRegisterPasswordRepeat = htmlspecialchars($t('auth.repeat_password'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $buttonRegister = htmlspecialchars($t('auth.create_account'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo <<<HTML
<main class="pm-login-screen pm-login-screen-in-shell">
    <div class="pm-auth-card">
        <div class="pm-auth-brand">
            {$brandLogoHtml}
            <div class="pm-auth-brand-copy">
                <div class="pm-auth-brand-title">ProxyMint</div>
                <div class="pm-auth-brand-sub">Dashboard 1.1</div>
            </div>
        </div>
        <div class="d-flex gap-2 mb-3">
            <a href="{$loginTabUrlEsc}" class="{$loginTabClassEsc} w-100">{$switchLogin}</a>
            <a href="{$registerTabUrlEsc}" class="{$registerTabClassEsc} w-100">{$switchRegister}</a>
            <a href="{$forgotTabUrlEsc}" class="{$forgotTabClassEsc} w-100">{$switchForgot}</a>
        </div>
        <h4 class="mb-3 text-center">{$titleAuth}</h4>
        {$errorHtml}
        {$infoHtml}
        <form method="post" action="{$currentUrlEsc}">
            <input type="hidden" name="auth_action" value="register">
            <input type="hidden" name="next" value="{$nextPathEsc}">
            <div class="mb-3">
                <label class="form-label">{$labelRegisterLogin}</label>
                <input name="register_login" type="text" class="form-control" placeholder="{$placeholderRegisterLogin}" value="{$formRegisterLoginEsc}">
            </div>
            <div class="mb-3">
                <label class="form-label">{$labelRegisterEmail}</label>
                <input name="register_email" type="email" class="form-control" placeholder="{$placeholderRegisterEmail}" value="{$formRegisterEmailEsc}">
            </div>
            <div class="mb-3">
                <label class="form-label">{$labelRegisterPassword}</label>
                <input name="register_password" type="password" class="form-control" placeholder="{$placeholderRegisterPassword}">
            </div>
            <div class="mb-3">
                <label class="form-label">{$labelRegisterPasswordRepeat}</label>
                <input name="register_password_repeat" type="password" class="form-control" placeholder="{$placeholderRegisterPasswordRepeat}">
            </div>
            <button type="submit" class="btn btn-primary w-100">{$buttonRegister}</button>
        </form>
    </div>
</main>
HTML;
} elseif ($formMode === 'forgot') {
    $labelResetLogin = htmlspecialchars($t('auth.enter_login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $labelOr = htmlspecialchars($t('auth.or'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $labelResetEmail = htmlspecialchars($t('auth.enter_email'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $buttonSendResetLink = htmlspecialchars($t('auth.send_reset_link'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $resetLoginEsc = htmlspecialchars((string)($_POST['reset_login'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $resetEmailEsc = htmlspecialchars((string)($_POST['reset_email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo <<<HTML
<main class="pm-login-screen pm-login-screen-in-shell">
    <div class="pm-auth-card">
        <div class="pm-auth-brand">
            {$brandLogoHtml}
            <div class="pm-auth-brand-copy">
                <div class="pm-auth-brand-title">ProxyMint</div>
                <div class="pm-auth-brand-sub">Dashboard 1.1</div>
            </div>
        </div>
        <div class="d-flex gap-2 mb-3">
            <a href="{$loginTabUrlEsc}" class="{$loginTabClassEsc} w-100">{$switchLogin}</a>
            <a href="{$registerTabUrlEsc}" class="{$registerTabClassEsc} w-100">{$switchRegister}</a>
            <a href="{$forgotTabUrlEsc}" class="{$forgotTabClassEsc} w-100">{$switchForgot}</a>
        </div>
        <h4 class="mb-3 text-center">{$titleAuth}</h4>
        {$errorHtml}
        {$infoHtml}
        <form method="post" action="{$currentUrlEsc}">
            <input type="hidden" name="auth_action" value="forgot">
            <input type="hidden" name="next" value="{$nextPathEsc}">
            <div class="mb-3">
                <label class="form-label">{$labelResetLogin}</label>
                <input name="reset_login" type="text" class="form-control" value="{$resetLoginEsc}">
            </div>
            <div class="mb-3 text-center fw-bold">{$labelOr}</div>
            <div class="mb-3">
                <label class="form-label">{$labelResetEmail}</label>
                <input name="reset_email" type="email" class="form-control" value="{$resetEmailEsc}">
            </div>
            <button type="submit" class="btn btn-primary w-100">{$buttonSendResetLink}</button>
        </form>
    </div>
</main>
HTML;
} elseif ($formMode === 'reset') {
    $resetTokenEsc = htmlspecialchars($resetTokenFromUrl !== '' ? $resetTokenFromUrl : (string)($_POST['reset_token'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $labelNewPassword = htmlspecialchars($t('auth.enter_new_password'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $buttonSetNewPassword = htmlspecialchars($t('auth.set_new_password'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo <<<HTML
<main class="pm-login-screen pm-login-screen-in-shell">
    <div class="pm-auth-card">
        <div class="pm-auth-brand">
            {$brandLogoHtml}
            <div class="pm-auth-brand-copy">
                <div class="pm-auth-brand-title">ProxyMint</div>
                <div class="pm-auth-brand-sub">Dashboard 1.1</div>
            </div>
        </div>
        <h4 class="mb-3 text-center">{$titleAuth}</h4>
        {$errorHtml}
        {$infoHtml}
        <form method="post" action="{$currentUrlEsc}">
            <input type="hidden" name="auth_action" value="reset_password">
            <input type="hidden" name="reset_token" value="{$resetTokenEsc}">
            <div class="mb-3">
                <label class="form-label">{$labelNewPassword}</label>
                <input name="new_password" type="password" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary w-100">{$buttonSetNewPassword}</button>
        </form>
    </div>
</main>
HTML;
} else {
    $labelLogin = htmlspecialchars($t('common.login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $placeholderLogin = htmlspecialchars($t('auth.enter_login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $labelPassword = htmlspecialchars($t('common.password'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $placeholderPassword = htmlspecialchars($t('auth.enter_password'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $buttonSignIn = htmlspecialchars($t('auth.sign_in'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo <<<HTML
<main class="pm-login-screen pm-login-screen-in-shell">
    <div class="pm-auth-card">
        <div class="pm-auth-brand">
            {$brandLogoHtml}
            <div class="pm-auth-brand-copy">
                <div class="pm-auth-brand-title">ProxyMint</div>
                <div class="pm-auth-brand-sub">Dashboard 1.1</div>
            </div>
        </div>
        <div class="d-flex gap-2 mb-3">
            <a href="{$loginTabUrlEsc}" class="{$loginTabClassEsc} w-100">{$switchLogin}</a>
            <a href="{$registerTabUrlEsc}" class="{$registerTabClassEsc} w-100">{$switchRegister}</a>
            <a href="{$forgotTabUrlEsc}" class="{$forgotTabClassEsc} w-100">{$switchForgot}</a>
        </div>
        <h4 class="mb-3 text-center">{$titleAuth}</h4>
        {$errorHtml}
        {$infoHtml}
        <form method="post" action="{$currentUrlEsc}">
            <input type="hidden" name="auth_action" value="login">
            <input type="hidden" name="next" value="{$nextPathEsc}">
            <div class="mb-3">
                <label class="form-label">{$labelLogin}</label>
                <input name="login" type="text" class="form-control" placeholder="{$placeholderLogin}" value="{$formLoginEsc}">
            </div>
            <div class="mb-3">
                <label class="form-label">{$labelPassword}</label>
                <input name="password" type="password" class="form-control" placeholder="{$placeholderPassword}">
            </div>
            <button type="submit" class="btn btn-primary w-100">{$buttonSignIn}</button>
        </form>
        <div class="text-center text-muted small my-3">— или —</div>
        <a class="btn btn-outline-dark w-100 d-flex align-items-center justify-content-center gap-2" href="/auth/google?next={$nextPathEsc}">
            <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.345 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.655 3.58 9 3.58z"/></svg>
            Sign in with Google
        </a>
    </div>
</main>
HTML;
}

Sogerien::Page()->footer();
Sogerien::markDone();

