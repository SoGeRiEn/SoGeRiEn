<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function tai_t(string $key, string $fallback = ''): string
{
    $value = Sogerien::Lang()->get($key);
    if ($fallback !== '' && $value === $key) {
        return $fallback;
    }

    return $value;
}

function tai_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tai_str(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (is_string($value)) {
        return trim($value);
    }
    if (is_int($value) || is_float($value) || is_bool($value)) {
        return trim((string)$value);
    }
    return '';
}

/**
 * @param array<string,mixed>|null $response
 * @return array<string,mixed>
 */
function tai_build_result(string $title, bool $ok, string $error, ?array $response, APIInfaticaIo $api): array
{
    return [
        'title' => $title,
        'ok' => $ok,
        'error' => $error,
        'http_code' => $api->last_http_code,
        'url' => $api->last_url,
        'response' => $response,
    ];
}

$request = Sogerien::InputRequest()->request_post_get_cookie_json;

$apiKey = tai_str($request['api_key'] ?? '');
$apiKeyResidential = tai_str($request['api_key_residential'] ?? '');
$apiKeyMobile = tai_str($request['api_key_mobile'] ?? '');
$apiKeyIsp = tai_str($request['api_key_isp'] ?? '');

$results = [];
$alertType = '';
$alertText = '';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $api = Sogerien::API()->InfaticaIo();

    if ($apiKey !== '') {
        $api->set_api_key($apiKey);
    }
    if ($apiKeyResidential !== '') {
        $api->set_residential_api_key($apiKeyResidential);
    }
    if ($apiKeyMobile !== '') {
        $api->set_mobile_api_key($apiKeyMobile);
    }
    if ($apiKeyIsp !== '') {
        $api->set_isp_api_key($apiKeyIsp);
    }

    $baseResp = $api->keys();
    $baseOk = is_array($baseResp);
    $baseError = $baseOk ? '' : ($api->error !== '' ? $api->error : tai_t('test.request_failed', 'Request failed'));
    $results[] = tai_build_result(tai_t('test.infatica_base', 'Base key test: GET /keys'), $baseOk, $baseError, $baseResp, $api);

    $residentialResp = $api->proxiesList([
        'type' => 'residential',
        'limit' => 1,
        'offset' => 0,
    ]);
    $residentialOk = (($residentialResp['ok'] ?? false) === true);
    $residentialError = $residentialOk ? '' : tai_str($residentialResp['error'] ?? $api->error);
    if ($residentialError === '') {
        $residentialError = tai_t('test.request_failed', 'Request failed');
    }
    $results[] = tai_build_result(tai_t('test.infatica_residential', 'Residential key test: proxiesList(type=residential)'), $residentialOk, $residentialError, $residentialResp, $api);

    $mobileResp = $api->proxiesList([
        'type' => 'mobile',
        'limit' => 1,
        'offset' => 0,
    ]);
    $mobileOk = (($mobileResp['ok'] ?? false) === true);
    $mobileError = $mobileOk ? '' : tai_str($mobileResp['error'] ?? $api->error);
    if ($mobileError === '') {
        $mobileError = tai_t('test.request_failed', 'Request failed');
    }
    $results[] = tai_build_result(tai_t('test.infatica_mobile', 'Mobile key test: proxiesList(type=mobile)'), $mobileOk, $mobileError, $mobileResp, $api);

    $ispResp = $api->proxiesList([
        'type' => 'isp',
        'limit' => 1,
        'offset' => 0,
    ]);
    $ispOk = (($ispResp['ok'] ?? false) === true);
    $ispError = $ispOk ? '' : tai_str($ispResp['error'] ?? $api->error);
    if ($ispError === '') {
        $ispError = tai_t('test.request_failed', 'Request failed');
    }
    $results[] = tai_build_result(tai_t('test.infatica_isp', 'ISP key test: proxiesList(type=isp)'), $ispOk, $ispError, $ispResp, $api);

    $failedCount = 0;
    foreach ($results as $result) {
        if (($result['ok'] ?? false) !== true) {
            $failedCount++;
        }
    }

    if ($failedCount === 0) {
        $alertType = 'success';
        $alertText = tai_t('test.all_passed', 'All API key tests passed.');
    } else {
        $alertType = 'danger';
        $alertText = tai_t('test.failed_count', 'Failed tests:') . ' ' . (string)$failedCount . ' / ' . (string)count($results);
    }
}

Sogerien::Template()->title = tai_t('test.api_infatica_title', 'Infatica API key checks');
Sogerien::Template()->header();
Sogerien::Template()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <p class="text-muted mb-3"><?= tai_h(tai_t('test.api_infatica_subtitle', 'API key checks for APIInfaticaIo.php')) ?></p>

    <?php if ($alertText !== ''): ?>
        <div class="alert alert-<?= tai_h($alertType !== '' ? $alertType : 'info') ?>" role="alert"><?= tai_h($alertText) ?></div>
    <?php endif; ?>

    <form method="post" class="card card-body mb-4">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="tai_api_key">api_key (base)</label>
                <input id="tai_api_key" class="form-control form-control-sm" type="text" name="api_key" value="<?= tai_h($apiKey) ?>" placeholder="<?= tai_h(tai_t('test.leave_empty_use_default', 'Leave empty to use configured value')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="tai_api_key_residential">api_key_residential</label>
                <input id="tai_api_key_residential" class="form-control form-control-sm" type="text" name="api_key_residential" value="<?= tai_h($apiKeyResidential) ?>" placeholder="<?= tai_h(tai_t('test.leave_empty_use_default', 'Leave empty to use configured value')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="tai_api_key_mobile">api_key_mobile</label>
                <input id="tai_api_key_mobile" class="form-control form-control-sm" type="text" name="api_key_mobile" value="<?= tai_h($apiKeyMobile) ?>" placeholder="<?= tai_h(tai_t('test.leave_empty_use_default', 'Leave empty to use configured value')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="tai_api_key_isp">api_key_isp</label>
                <input id="tai_api_key_isp" class="form-control form-control-sm" type="text" name="api_key_isp" value="<?= tai_h($apiKeyIsp) ?>" placeholder="<?= tai_h(tai_t('test.leave_empty_use_default', 'Leave empty to use configured value')) ?>">
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-primary btn-sm"><?= tai_h(tai_t('test.run_tests', 'Run tests')) ?></button>
        </div>
    </form>

    <?php if ($results !== []): ?>
        <?php foreach ($results as $index => $result): ?>
            <?php
            $ok = (($result['ok'] ?? false) === true);
            $title = tai_str($result['title'] ?? 'Test');
            $error = tai_str($result['error'] ?? '');
            $httpCode = (int)($result['http_code'] ?? 0);
            $url = tai_str($result['url'] ?? '');
            $response = $result['response'] ?? null;
            $responseJson = '';
            if (is_array($response)) {
                $tmpJson = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                if (is_string($tmpJson)) {
                    $responseJson = $tmpJson;
                }
            }
            ?>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?= tai_h(((string)($index + 1)) . '. ' . $title) ?></span>
                    <span class="badge text-bg-<?= $ok ? 'success' : 'danger' ?>"><?= tai_h($ok ? 'OK' : 'FAIL') ?></span>
                </div>
                <div class="card-body">
                    <?php if (!$ok && $error !== ''): ?>
                        <div class="alert alert-danger py-2 mb-2"><?= tai_h($error) ?></div>
                    <?php endif; ?>
                    <div class="small text-muted mb-2">
                        HTTP: <strong><?= (string)$httpCode ?></strong>
                        <?php if ($url !== ''): ?>
                            | URL: <code><?= tai_h($url) ?></code>
                        <?php endif; ?>
                    </div>
                    <?php if ($responseJson !== ''): ?>
                        <pre class="mb-0"><code><?= tai_h($responseJson) ?></code></pre>
                    <?php else: ?>
                        <div class="text-muted small"><?= tai_h(tai_t('test.no_json_response', 'No JSON response body.')) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php
Sogerien::Template()->footer();
?>
