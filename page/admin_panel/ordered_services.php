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

$h = static function (mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$accessOk = Sogerien::AccessCheck()->check_access_or_show_login_form('page_user_balances', 0, []);
if (!$accessOk) {
    http_response_code(403);
    Sogerien::Page()->title = $t('common.access_denied', 'Access denied');
    Sogerien::Page()->header();
    Sogerien::Page()->mainmenu();
    echo '<main class="container my-4 sog-ui">';
    echo '<div class="alert alert-danger" role="alert">' . $h($t('common.access_denied_admin_only', 'Access denied. Allowed role: admin.')) . '</div>';
    echo '</main>';
    Sogerien::Page()->footer();
    Sogerien::exit();
}

$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}

$responseJson = Sogerien::DbController()->sql_request($dbAlias, [
    'sql' => "
        WITH users AS (
            SELECT
                id AS user_id,
                COALESCE(NULLIF(table_value->>'login', ''), NULLIF(name, ''), 'User #' || id::text) AS user_label,
                COALESCE(NULLIF(table_value->>'email', ''), '') AS email
            FROM sogerien
            WHERE table_name = 'user'
        ),
        direct_services AS (
            SELECT
                s.id AS source_id,
                COALESCE(NULLIF(s.table_value->>'service_id', ''), s.id::text) AS service_key,
                COALESCE(NULLIF(s.table_value->>'order_id', ''), NULLIF(s.table_index, '')) AS order_id,
                COALESCE(
                    NULLIF(s.table_value->>'title', ''),
                    NULLIF(s.table_value->>'service', ''),
                    NULLIF(s.table_value->>'service_name', ''),
                    NULLIF(s.name, ''),
                    'Service #' || s.id::text
                ) AS service,
                COALESCE(
                    NULLIF(s.table_value->>'created_at', ''),
                    NULLIF(s.table_value->>'ordered_at', ''),
                    to_char(s.created_at, 'YYYY-MM-DD HH24:MI:SS')
                ) AS ordered_at,
                COALESCE(
                    NULLIF(s.table_value->>'amount_usd', ''),
                    NULLIF(s.table_value->>'price_usd', ''),
                    NULLIF(s.table_value->>'total_usd', '')
                ) AS amount_usd,
                NULLIF(s.table_value->>'currency', '') AS currency,
                NULLIF(s.table_value->>'user_id', '') AS user_id_raw
            FROM sogerien s
            WHERE s.status <> 'delete'
              AND (
                  s.table_name IN ('proxy_service', 'service', 'ordered_service', 'user_service')
                  OR (
                      s.table_value ? 'service_id'
                      AND (s.table_value ? 'user_id' OR s.table_value ? 'order_id')
                  )
              )
        ),
        order_items AS (
            SELECT
                o.id AS source_id,
                COALESCE(NULLIF(item.value->>'service_id', ''), NULLIF(item.value->>'id', ''), o.id::text || ':' || item.ordinality::text) AS service_key,
                COALESCE(NULLIF(o.table_value->>'order_id', ''), o.id::text) AS order_id,
                COALESCE(
                    NULLIF(item.value->>'title', ''),
                    NULLIF(item.value->>'service', ''),
                    NULLIF(item.value->>'service_name', ''),
                    NULLIF(o.table_value->>'title', ''),
                    NULLIF(o.name, ''),
                    'Service #' || o.id::text
                ) AS service,
                COALESCE(
                    NULLIF(item.value->>'created_at', ''),
                    NULLIF(o.table_value->>'created_at', ''),
                    NULLIF(o.table_value->>'ordered_at', ''),
                    to_char(o.created_at, 'YYYY-MM-DD HH24:MI:SS')
                ) AS ordered_at,
                COALESCE(
                    NULLIF(item.value->>'amount_usd', ''),
                    NULLIF(item.value->>'price_usd', ''),
                    NULLIF(item.value->>'total_usd', ''),
                    NULLIF(o.table_value->>'amount_usd', ''),
                    NULLIF(o.table_value->>'total_usd', '')
                ) AS amount_usd,
                COALESCE(NULLIF(item.value->>'currency', ''), NULLIF(o.table_value->>'currency', '')) AS currency,
                COALESCE(NULLIF(item.value->>'user_id', ''), NULLIF(o.table_value->>'user_id', '')) AS user_id_raw
            FROM sogerien o
            CROSS JOIN LATERAL jsonb_array_elements(
                CASE
                    WHEN jsonb_typeof(o.table_value->'services') = 'array' THEN o.table_value->'services'
                    WHEN jsonb_typeof(o.table_value->'items') = 'array' THEN o.table_value->'items'
                    ELSE '[]'::jsonb
                END
            ) WITH ORDINALITY AS item(value, ordinality)
            WHERE o.status <> 'delete'
              AND (
                  o.table_name IN ('proxy_order', 'order', 'checkout_order')
                  OR o.table_value ? 'order_id'
              )
        ),
        rows_union AS (
            SELECT * FROM direct_services
            UNION ALL
            SELECT * FROM order_items
        ),
        normalized AS (
            SELECT DISTINCT ON (COALESCE(order_id, ''), service_key, service, COALESCE(user_id_raw, ''))
                source_id,
                service_key,
                order_id,
                service,
                ordered_at,
                amount_usd,
                COALESCE(NULLIF(upper(currency), ''), 'USD') AS currency,
                CASE WHEN user_id_raw ~ '^[0-9]+$' THEN user_id_raw::int ELSE 0 END AS user_id
            FROM rows_union
            WHERE service <> ''
            ORDER BY COALESCE(order_id, ''), service_key, service, COALESCE(user_id_raw, ''), source_id DESC
        )
        SELECT
            n.source_id,
            n.service,
            n.ordered_at,
            COALESCE(NULLIF(n.amount_usd, ''), '-') AS amount,
            n.currency,
            n.user_id,
            COALESCE(u.user_label, CASE WHEN n.user_id > 0 THEN 'User #' || n.user_id::text ELSE '-' END) AS user_label,
            COALESCE(u.email, '') AS email,
            COALESCE(n.order_id, '') AS order_id
        FROM normalized n
        LEFT JOIN users u ON u.user_id = n.user_id
        ORDER BY n.ordered_at DESC, n.source_id DESC
        LIMIT 1000;
    ",
    'params' => [],
]);

$response = json_decode($responseJson, true);
$rows = [];
if (is_array($response) && ($response['result'] ?? false) === true && is_array($response['rows'] ?? null)) {
    $rows = $response['rows'];
}

$tableRows = [];
$totalUsd = 0.0;

foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }

    $amount = trim((string)($row['amount'] ?? '-'));
    $currency = strtoupper(trim((string)($row['currency'] ?? 'USD')));
    if ($currency === '') {
        $currency = 'USD';
    }

    if ($currency === 'USD' && is_numeric($amount)) {
        $totalUsd += (float)$amount;
    }

    $tableRows[] = [
        'service' => (string)($row['service'] ?? ''),
        'ordered_at' => (string)($row['ordered_at'] ?? ''),
        'amount' => is_numeric($amount) ? number_format((float)$amount, 2, '.', '') . ' ' . $currency : $amount,
        'user' => (string)($row['user_label'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'order_id' => (string)($row['order_id'] ?? ''),
    ];
}

Sogerien::Page()->title = 'Ordered Services';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="text-muted small">Ordered services</div>
                    <div class="h5 mb-0"><?= count($tableRows) ?></div>
                </div>
                <div>
                    <div class="text-muted small">Total USD</div>
                    <div class="h5 mb-0"><?= $h(number_format($totalUsd, 2, '.', '')) ?></div>
                </div>
            </div>
        </div>
    </div>

<?php
$tr = Sogerien::TableRenderer();
$tr->set_params->data = $tableRows;
$tr->set_params->columns = ['service', 'ordered_at', 'amount', 'user', 'email', 'order_id'];
$tr->set_params->headers = [
    'service' => 'Service',
    'ordered_at' => 'Date',
    'amount' => 'Amount',
    'user' => 'User',
    'email' => 'Email',
    'order_id' => 'Order ID',
];
$tr->set_params->gridId = 'ordered_services_grid';
$tr->set_params->searchCols = ['service', 'ordered_at', 'amount', 'user', 'email', 'order_id'];
$tr->set_params->perPage = 100;
$tr->set_params->columnsOrder = ['service', 'ordered_at', 'amount', 'user', 'email', 'order_id'];
$tr->set_params->column_view['service'] = ['width' => '280px', 'ellipsis' => true];
$tr->set_params->column_view['email'] = ['width' => '240px', 'ellipsis' => true];
$tr->render();
?>
</main>
<?php
Sogerien::Page()->footer();
