<?php
declare(strict_types=1);

if (!class_exists('Sogerien')) {
    $doc = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $bootstrap = $doc . '/sogerien/Sogerien.php';
    if (is_file($bootstrap)) {
        require_once $bootstrap;
    }
}

header('Content-Type: application/json; charset=utf-8');

$method = '';
$data = [];

$raw = file_get_contents('php://input');
if ($raw !== false && $raw !== '') {
    $json = json_decode($raw, true);
    if (is_array($json)) {
        if (isset($json['method']) && is_string($json['method'])) {
            $method = trim($json['method']);
        }
        if (isset($json['data']) && is_array($json['data'])) {
            $data = $json['data'];
        }
    }
}

if ($method === '') {
    if (isset($_REQUEST['method']) && is_string($_REQUEST['method'])) {
        $method = trim($_REQUEST['method']);
    }
    if (isset($_REQUEST['data']) && is_array($_REQUEST['data'])) {
        $data = $_REQUEST['data'];
    }
}

$fail = static function (string $error, array $extra = []): never {
    $payload = ['result' => false, 'error' => $error] + $extra;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

if ($method === '') {
    $fail('METHOD_NOT_DEFINED');
}

$pos = strpos($method, '.');
if ($pos === false) {
    $fail('METHOD_FORMAT_INVALID', ['method' => $method, 'hint' => 'Use Service.method format']);
}

$serviceName = trim(substr($method, 0, $pos));
$targetMethod = trim(substr($method, $pos + 1));

if ($serviceName === '' || $targetMethod === '') {
    $fail('METHOD_FORMAT_INVALID', ['method' => $method]);
}

if (!class_exists('Sogerien')) {
    $fail('BOOTSTRAP_FAILED');
}

$api = Sogerien::API();
$serviceKey = strtolower($serviceName);
$dbServiceKeys = ['postgresql', 'postgres', 'pg', 'db', 'sql'];
if (in_array($serviceKey, $dbServiceKeys, true)) {
    $dbAlias = 'front';
    try {
        $db = Sogerien::DbController();
        $db->DbConfig->DB_HOST = '38.180.192.121';
        $db->DbConfig->DB_PORT = '5432';
        $db->DbConfig->DB_NAME = 'db_proxymint_com';
        $db->DbConfig->DB_USER = 'db_proxymin_usr';
        $db->DbConfig->DB_PASS = 'uQ8oxEskC2LZvoyK';
        $db->DbConfig->DB_CHARSET = 'utf8mb4';
        $db->connect($dbAlias, $db->DbConfig);

        $api->Postgresql()->set_db_alias($dbAlias);
    } catch (Throwable $e) {
        $fail('DB_CONNECT_ERROR', ['message' => $e->getMessage()]);
    }
}

$service = match ($serviceKey) {
    'cyberyozh' => $api->Cyberyozh(),
    'infaticaio' => $api->InfaticaIo(),
    'postgresql', 'postgres', 'pg', 'db', 'sql' => $api->Postgresql(),
    default => null,
};

if (!is_object($service)) {
    $fail('SERVICE_NOT_FOUND', ['service' => $serviceName]);
}

if (!method_exists($service, $targetMethod)) {
    $fail('TARGET_METHOD_NOT_FOUND', ['service' => $serviceName, 'target' => $targetMethod]);
}

try {
    $args = [];
    if ($data !== []) {
        $isList = array_keys($data) === range(0, count($data) - 1);
        $args = $isList ? $data : [$data];
    }

    $result = $service->{$targetMethod}(...$args);

    echo json_encode([
        'result' => true,
        'data' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    $fail('INTERNAL_ERROR', ['message' => $e->getMessage()]);
}

