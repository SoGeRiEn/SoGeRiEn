<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

function sw_log_file_path(): string
{
    return Sogerien::$SOGERIEN_DIR . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'logs_by.txt';
}

function sw_log_write(string $message, array $context = []): void
{
    $dir = dirname(sw_log_file_path());
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($context !== []) {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (is_string($json) && $json !== '') {
            $line .= ' | ' . $json;
        }
    }
    $line .= PHP_EOL;

    @file_put_contents(sw_log_file_path(), $line, FILE_APPEND);
}

function sw_out(int $code, array $payload): void
{
    sw_log_write('stripe webhook response', [
        'http_code' => $code,
        'payload' => $payload,
    ]);
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    Sogerien::markDone();
    Sogerien::exit();
}

function sw_parse_signature(string $header): array
{
    $pairs = [];
    foreach (explode(',', $header) as $part) {
        $part = trim($part);
        if ($part === '' || !str_contains($part, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $part, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '' || $value === '') {
            continue;
        }
        $pairs[$key][] = $value;
    }
    return $pairs;
}

function sw_verify_stripe_signature(string $payload, string $signatureHeader, string $secret, int $tolerance = 300): bool
{
    if ($payload === '' || $signatureHeader === '' || $secret === '') {
        return false;
    }

    $parts = sw_parse_signature($signatureHeader);
    $timestamp = isset($parts['t'][0]) ? (int)$parts['t'][0] : 0;
    $signatures = $parts['v1'] ?? [];
    if ($timestamp <= 0 || $signatures === []) {
        return false;
    }

    if (abs(time() - $timestamp) > $tolerance) {
        return false;
    }

    $signedPayload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody)) {
    $rawBody = '';
}

sw_log_write('stripe webhook hit', [
    'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
    'uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
    'content_length' => (int)($_SERVER['CONTENT_LENGTH'] ?? 0),
    'stripe_signature_present' => isset($_SERVER['HTTP_STRIPE_SIGNATURE']),
    'body_preview' => mb_substr($rawBody, 0, 2000),
]);

$secret = defined('STRIPE_WEBHOOK_SECRET_LLC') ? trim((string)STRIPE_WEBHOOK_SECRET_LLC) : '';
if ($secret === '') {
    sw_log_write('stripe webhook secret missing');
    sw_out(500, [
        'ok' => false,
        'error' => 'STRIPE_WEBHOOK_SECRET_LLC is empty.',
    ]);
}

if (!is_string($rawBody) || $rawBody === '') {
    sw_log_write('stripe webhook empty payload');
    sw_out(400, [
        'ok' => false,
        'error' => 'Webhook payload is empty.',
    ]);
}

$signatureHeader = trim((string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''));
if (!sw_verify_stripe_signature($rawBody, $signatureHeader, $secret, 300)) {
    sw_log_write('stripe webhook signature verification failed', [
        'signature_header' => $signatureHeader,
    ]);
    sw_out(400, [
        'ok' => false,
        'error' => 'Stripe signature verification failed.',
    ]);
}

$event = json_decode($rawBody, true);
if (!is_array($event)) {
    sw_log_write('stripe webhook invalid json');
    sw_out(400, [
        'ok' => false,
        'error' => 'Webhook payload is not valid JSON.',
    ]);
}

$eventType = trim((string)($event['type'] ?? ''));
sw_log_write('stripe webhook event parsed', [
    'event_id' => (string)($event['id'] ?? ''),
    'event_type' => $eventType,
]);

$supportedEvents = [
    'checkout.session.completed' => true,
    'payment_intent.succeeded' => true,
    'payment_intent.payment_failed' => true,
];

if (!isset($supportedEvents[$eventType])) {
    sw_log_write('stripe webhook event ignored', [
        'event_id' => (string)($event['id'] ?? ''),
        'event_type' => $eventType,
    ]);
    sw_out(200, [
        'ok' => true,
        'ignored' => true,
        'event_type' => $eventType,
    ]);
}

$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}

$shop = new ProxyShop();
$shop->init_db_alias($dbAlias);

$result = $eventType === 'checkout.session.completed'
    ? $shop->finalize_checkout_by_event($event)
    : $shop->handle_payment_intent_event($event);
if (($result['ok'] ?? false) !== true) {
    sw_log_write('stripe webhook finalize failed', [
        'event_id' => (string)($event['id'] ?? ''),
        'event_type' => $eventType,
        'result' => $result,
    ]);
    sw_out(500, [
        'ok' => false,
        'error' => (string)($result['error'] ?? 'Webhook processing failed.'),
    ]);
}

sw_log_write('stripe webhook finalize success', [
    'event_id' => (string)($event['id'] ?? ''),
    'event_type' => $eventType,
    'already_processed' => (bool)($result['already_processed'] ?? false),
    'result' => $result,
]);

sw_out(200, [
    'ok' => true,
    'already_processed' => (bool)($result['already_processed'] ?? false),
]);
