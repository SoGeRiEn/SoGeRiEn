<?php
declare(strict_types=1);

/**
 * Google OAuth 2.0 client. Stateless - все секреты передаются через index.php
 * через set_credentials() (фреймворк не хранит credentials).
 *
 * Flow:
 *   1. get_auth_url($state)  -> редиректим пользователя на accounts.google.com
 *   2. Google возвращает code+state на redirect_uri
 *   3. exchange_code($code)  -> получаем access_token (+id_token)
 *   4. fetch_userinfo($token) -> получаем email/name/email_verified/sub
 */
final class APIGoogleOAuth
{
    use SogerienClassHelp;

    public bool $status = false;
    public string $error = '';

    public string $client_id = '';
    public string $client_secret = '';
    public string $redirect_uri = '';

    public string $auth_endpoint  = 'https://accounts.google.com/o/oauth2/v2/auth';
    public string $token_endpoint = 'https://oauth2.googleapis.com/token';
    public string $userinfo_endpoint = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public int $connect_timeout = 6;
    public int $request_timeout = 18;

    private function ok(): void { $this->status = true; $this->error = ''; }
    private function fail(string $msg): void { $this->status = false; $this->error = $msg; }

    public function set_credentials(string $client_id, string $client_secret, string $redirect_uri): void
    {
        $this->client_id = trim($client_id);
        $this->client_secret = trim($client_secret);
        $this->redirect_uri = trim($redirect_uri);
    }

    public function is_configured(): bool
    {
        return $this->client_id !== '' && $this->client_secret !== '' && $this->redirect_uri !== '';
    }

    /**
     * Build the URL to which the user should be redirected to start OAuth.
     * $state - произвольная строка для CSRF-защиты (сохраняем в cookie + сверяем в callback).
     */
    public function get_auth_url(string $state, string $scope = 'openid email profile', string $loginHint = ''): string
    {
        $params = [
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->redirect_uri,
            'response_type' => 'code',
            'scope'         => $scope,
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
            'include_granted_scopes' => 'true',
        ];
        if ($loginHint !== '') {
            $params['login_hint'] = $loginHint;
        }
        return $this->auth_endpoint . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange authorization code for tokens.
     * Returns array with keys: access_token, expires_in, scope, token_type, id_token (optional).
     * Returns null on failure ($this->error set).
     *
     * @return array<string,mixed>|null
     */
    public function exchange_code(string $code): ?array
    {
        if (!$this->is_configured()) {
            $this->fail('Google OAuth is not configured (set client_id/client_secret/redirect_uri in index.php).');
            return null;
        }
        if ($code === '') {
            $this->fail('Empty code.');
            return null;
        }
        $body = http_build_query([
            'code'          => $code,
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri'  => $this->redirect_uri,
            'grant_type'    => 'authorization_code',
        ], '', '&', PHP_QUERY_RFC3986);

        [$resp, $http] = $this->post_form($this->token_endpoint, $body);
        if ($http < 200 || $http >= 300) {
            $this->fail('Token exchange failed (HTTP ' . $http . '): ' . substr((string)$resp, 0, 500));
            return null;
        }
        $data = json_decode((string)$resp, true);
        if (!is_array($data) || !isset($data['access_token'])) {
            $this->fail('Token response is invalid.');
            return null;
        }
        $this->ok();
        return $data;
    }

    /**
     * Fetch userinfo using access_token.
     * Returns array with keys: sub, email, email_verified, name, given_name, family_name, picture, locale.
     *
     * @return array<string,mixed>|null
     */
    public function fetch_userinfo(string $access_token): ?array
    {
        if ($access_token === '') {
            $this->fail('Empty access_token.');
            return null;
        }
        [$resp, $http] = $this->get_with_bearer($this->userinfo_endpoint, $access_token);
        if ($http < 200 || $http >= 300) {
            $this->fail('Userinfo failed (HTTP ' . $http . '): ' . substr((string)$resp, 0, 500));
            return null;
        }
        $data = json_decode((string)$resp, true);
        if (!is_array($data) || !isset($data['email'])) {
            $this->fail('Userinfo response is invalid.');
            return null;
        }
        $this->ok();
        return $data;
    }

    /**
     * @return array{0:string|false,1:int}
     */
    private function post_form(string $url, string $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['curl_init failed', 0];
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connect_timeout,
            CURLOPT_TIMEOUT        => $this->request_timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['curl error: ' . $err, $http];
        }
        curl_close($ch);
        return [(string)$resp, $http];
    }

    /**
     * @return array{0:string|false,1:int}
     */
    private function get_with_bearer(string $url, string $access_token): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['curl_init failed', 0];
        }
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $access_token, 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connect_timeout,
            CURLOPT_TIMEOUT        => $this->request_timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['curl error: ' . $err, $http];
        }
        curl_close($ch);
        return [(string)$resp, $http];
    }
}
