<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$input = Sogerien::InputRequest()->request_post_get_cookie_json;
$limit = (int)($input['limit'] ?? 200);

$result = Sogerien::ProxyCatalogCache()->refresh_cyberyozh_cache($limit);

echo json_encode(
    $result,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
);
