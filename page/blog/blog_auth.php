<?php
declare(strict_types=1);

/**
 * @return array<string,bool>
 */
function news_auth_user_group_from_cookie_token(): array
{
    $keyFile = (string)(Sogerien::$patch_to_cookies_keyFile ?? '');
    if ($keyFile === '') {
        return [];
    }

    $accessToken = Sogerien::AccessToken();
    $accessToken->patch_to_keyFile = $keyFile;

    $token = $accessToken->load_token_for_cookie();
    if (!$accessToken->status || $token === '') {
        return [];
    }

    $payload = $accessToken->read_token($token);
    if (!$accessToken->status || !is_array($payload)) {
        return [];
    }

    $rawUserGroup = $payload['user_group'] ?? [];
    if (!is_array($rawUserGroup)) {
        return [];
    }

    $userGroup = [];
    foreach ($rawUserGroup as $roleKey => $roleValue) {
        if (is_string($roleKey) && trim($roleKey) !== '') {
            $userGroup[trim($roleKey)] = (bool)$roleValue;
            continue;
        }

        $role = trim((string)$roleValue);
        if ($role !== '') {
            $userGroup[$role] = true;
        }
    }

    return $userGroup;
}

function news_auth_is_admin(): bool
{
    $group = news_auth_user_group_from_cookie_token();
    return isset($group['admin']) && $group['admin'] === true;
}

function news_auth_has_token_cookie(): bool
{
    return trim((string)($_COOKIE['access_token'] ?? '')) !== '';
}

function news_auth_login_url(): string
{
    $next = (string)($_SERVER['REQUEST_URI'] ?? '/blog/admin');
    return '/admin?next=' . rawurlencode($next);
}

/**
 * @return array{status:int,error:string,login_url:string}
 */
function news_auth_error_payload(): array
{
    if (news_auth_has_token_cookie()) {
        return [
            'status' => 403,
            'error' => 'ACCESS_DENIED_ADMIN_ONLY',
            'login_url' => '/admin?logout=1',
        ];
    }

    return [
        'status' => 401,
        'error' => 'AUTH_REQUIRED',
        'login_url' => news_auth_login_url(),
    ];
}

function news_auth_require_admin_page(): void
{
    if (news_auth_is_admin()) {
        return;
    }

    $payload = news_auth_error_payload();
    if ((int)$payload['status'] === 401) {
        header('Location: ' . $payload['login_url'], true, 302);
        exit;
    }

    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Access Denied</title></head><body>';
    echo '<p>Access denied - allowed role: admin.</p>';
    echo '<p><a href="' . htmlspecialchars($payload['login_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Sign in as admin</a></p>';
    echo '</body></html>';
    exit;
}

