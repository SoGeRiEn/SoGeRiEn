<?php
declare(strict_types=1);

/**
 * Google OAuth login/register endpoint.
 * Маршруты:
 *   /auth/google           - редиректит на Google
 *   /auth/google/callback  - принимает code и state, логинит / создаёт пользователя
 *
 * Все credentials читаются из Sogerien::API()->GoogleOAuth() (set в index.php).
 */

function ga_s(mixed $v): string
{
    if (is_string($v) || is_int($v) || is_float($v) || is_bool($v)) {
        return trim((string)$v);
    }
    return '';
}

function ga_render_error(string $title, string $details): never
{
    http_response_code(400);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    Sogerien::Page()->title = 'Google auth';
    Sogerien::Page()->header();
    Sogerien::Page()->mainmenu();
    $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<main class="container my-4 sog-ui">';
    echo '<div class="alert alert-danger"><strong>' . $h($title) . '</strong><br><span class="small text-muted">' . $h($details) . '</span></div>';
    echo '<p><a class="btn btn-outline-secondary" href="/admin">Назад на вход</a></p>';
    echo '</main>';
    Sogerien::Page()->footer();
    Sogerien::markDone();
    Sogerien::exit();
}

$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}

$g = Sogerien::API()->GoogleOAuth();
if (!$g->is_configured()) {
    ga_render_error('Google OAuth не настроен', 'В index.php заполните set_credentials(client_id, client_secret, redirect_uri) для Sogerien::API()->GoogleOAuth().');
}

$requestPath = trim((string)(Sogerien::InputRequest()->url ?? ''), '/');
$isCallback = ($requestPath === 'auth/google/callback');

if (!$isCallback) {
    // --- /auth/google : start flow ---
    $next = ga_s($_GET['next'] ?? '/client/dashboard');
    if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
        $next = '/client/dashboard';
    }

    $state = bin2hex(random_bytes(16));
    setcookie('google_oauth_state', $state, [
        'expires'  => time() + 600,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    setcookie('google_oauth_next', $next, [
        'expires'  => time() + 600,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $url = $g->get_auth_url($state);
    if (!headers_sent()) {
        header('Location: ' . $url, true, 302);
    }
    Sogerien::markDone();
    Sogerien::exit();
}

// --- /auth/google/callback : finalize flow ---

if (isset($_GET['error'])) {
    ga_render_error('Google вернул ошибку', ga_s($_GET['error']) . ' ' . ga_s($_GET['error_description'] ?? ''));
}

$code  = ga_s($_GET['code'] ?? '');
$state = ga_s($_GET['state'] ?? '');
$expectedState = ga_s($_COOKIE['google_oauth_state'] ?? '');
$next  = ga_s($_COOKIE['google_oauth_next'] ?? '/client/dashboard');
if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
    $next = '/client/dashboard';
}

// Очистим временные cookies сразу.
setcookie('google_oauth_state', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
setcookie('google_oauth_next',  '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);

if ($code === '' || $state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
    ga_render_error('Невалидный OAuth-возврат', 'Не совпадает state или отсутствует code. Попробуйте ещё раз: /auth/google');
}

$tokenData = $g->exchange_code($code);
if (!is_array($tokenData)) {
    ga_render_error('Не удалось обменять code на токен', (string)$g->error);
}
$accessToken = ga_s($tokenData['access_token'] ?? '');
if ($accessToken === '') {
    ga_render_error('Google не вернул access_token', 'Ответ Google: ' . json_encode($tokenData, JSON_UNESCAPED_UNICODE));
}

$info = $g->fetch_userinfo($accessToken);
if (!is_array($info)) {
    ga_render_error('Не удалось получить userinfo от Google', (string)$g->error);
}

$email = mb_strtolower(ga_s($info['email'] ?? ''));
$name  = ga_s($info['name'] ?? '');
$emailVerified = !empty($info['email_verified']);
$givenName = ga_s($info['given_name'] ?? '');
$familyName = ga_s($info['family_name'] ?? '');
$sub = ga_s($info['sub'] ?? '');

if ($email === '') {
    ga_render_error('Google не вернул email', 'Без email невозможно создать или найти пользователя.');
}

$users = Sogerien::Users();
$users->init_db_alias($dbAlias);

// Найти существующего по email
$existing = $users->get_user_by_email($email);

if ($existing === null) {
    // Регистрация нового
    $baseLogin = explode('@', $email)[0];
    $baseLogin = preg_replace('/[^a-zA-Z0-9._-]/', '', $baseLogin) ?? '';
    if (mb_strlen($baseLogin) < 3) {
        $baseLogin = 'g' . substr(bin2hex(random_bytes(4)), 0, 6);
    }
    $randomPassword = bin2hex(random_bytes(16));

    $registered = $users->register_user([
        'login'    => $baseLogin,
        'email'    => $email,
        'password' => $randomPassword,
        'fio'      => $name !== '' ? $name : trim($givenName . ' ' . $familyName),
        'validate' => ['email' => $emailVerified ? 'true' : 'false', 'phone' => 'false'],
        'google_sub' => $sub,
        'auth_provider' => 'google',
    ]);

    if (!is_array($registered)) {
        ga_render_error('Не удалось создать пользователя', (string)$users->error);
    }
    $existing = $users->get_user_by_email($email);
    if (!is_array($existing)) {
        ga_render_error('Пользователь создан, но не загружен', (string)$users->error);
    }
} else {
    // Обновляем привязку и метку верификации (если Google подтвердил email).
    $tv = $existing['table_value'] ?? [];
    if (is_string($tv)) {
        $tv = json_decode($tv, true);
    }
    if (!is_array($tv)) {
        $tv = [];
    }
    $patch = ['google_sub' => $sub, 'auth_provider' => 'google'];
    if ($emailVerified) {
        $patch['validate'] = ['email' => 'true', 'phone' => (string)($tv['validate']['phone'] ?? 'false')];
    }
    if (($tv['fio'] ?? '') === '' && $name !== '') {
        $patch['fio'] = $name;
    }
    $userId = (int)($existing['id'] ?? 0);
    if ($userId > 0) {
        $users->update_user($userId, $patch);
    }
}

// Создать сессионный токен и установить cookie
$userId = (int)($existing['id'] ?? 0);
$tv = $existing['table_value'] ?? [];
if (is_string($tv)) {
    $tv = json_decode($tv, true);
}
$rolesArr = (is_array($tv ?? null) && isset($tv['roles']) && is_array($tv['roles'])) ? $tv['roles'] : [];
$userGroup = [];
foreach ($rolesArr as $r) {
    $r = ga_s($r);
    if ($r !== '') {
        $userGroup[$r] = true;
    }
}
if ($userGroup === []) {
    $userGroup = ['user' => true];
}

$sessionToken = $users->create_token($userId, $userGroup);
if ($sessionToken === '' || !$users->status) {
    ga_render_error('Ошибка создания сессии', (string)$users->error);
}
$saved = $users->save_token_to_cookie($sessionToken, 30);
if (!$saved) {
    ga_render_error('Ошибка сохранения сессии', (string)$users->error);
}

if (!headers_sent()) {
    header('Location: ' . $next, true, 302);
}
Sogerien::markDone();
Sogerien::exit();
