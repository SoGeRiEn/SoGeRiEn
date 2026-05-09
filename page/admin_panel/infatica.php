<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

$accessOk = Sogerien::AccessCheck()->check_access_or_show_login_form('page_user_balances', 0, []);
if (!$accessOk) {
    http_response_code(403);
    echo 'Access denied - allowed role: admin.';
    Sogerien::markDone();
    Sogerien::exit();
}

echo "<pre>";

$apiResidential = 'ilEAuAgqcUIV7yivB18u';
$apiMobile      = 'IRY9ZDA4LxiVB7nxjUxm';
$apiIsp         = '5ju0PkORl1JWQgBTeLy0';

function infatica_get(string $url, string $apiKey): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'api-key: ' . $apiKey,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);

    return is_string($raw) ? $raw : '';
}

function infatica_post(string $url, string $apiKey, array $form): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($form),
        CURLOPT_HTTPHEADER => [
            'api-key: ' . $apiKey,
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);

    return is_string($raw) ? $raw : '';
}

$r = infatica_get('https://api.infatica.io/nodes-info.php', $apiResidential);
print_r($r);
$r = infatica_get('https://api.infatica.io/mobile-nodes-info.php', $apiMobile);
print_r($r);
$r = infatica_get('https://api.infatica.io/isp/balance.php', $apiIsp);
print_r($r);
$r = infatica_get('https://api.infatica.io/isp/countries.php', $apiIsp);
print_r($r);
$r = infatica_get('https://api.infatica.io/packages.php', $apiResidential);
print_r($r);
$r = infatica_get('https://api.infatica.io/packages.php', $apiMobile);
print_r($r);

$packageKey = 'YOUR_PACKAGE_KEY';
$r = infatica_get('https://api.infatica.io/lists.php?key=' . urlencode($packageKey), $apiResidential);
print_r($r);

$listId = 'YOUR_LIST_ID';
$listName = 'YOUR_LIST_NAME';
$r = infatica_post('https://api.infatica.io/viewlist.php?key=' . urlencode($packageKey), $apiResidential, ['id' => $listId, 'name' => $listName]);
print_r($r);

echo "</pre>";

