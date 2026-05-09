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

$h = static function (string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
        SELECT id, name, table_value, status
        FROM sogerien
        WHERE table_name = 'user'
          AND status <> 'delete'
        ORDER BY id;
    ",
    'params' => [],
]);

$response = json_decode($responseJson, true);
$rows = [];
$queryError = '';
if (is_array($response) && ($response['result'] ?? false) === true && is_array($response['rows'] ?? null)) {
    $rows = $response['rows'];
} else {
    $error = is_array($response['error'] ?? null) ? $response['error'] : [];
    $queryError = (string)($error['message'] ?? 'Failed to load users balances.');
}

$tableRows = [];
$totalUsd = 0.0;

foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }

    $tableValue = $row['table_value'] ?? [];
    if (is_string($tableValue)) {
        $decoded = json_decode($tableValue, true);
        $tableValue = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($tableValue)) {
        $tableValue = [];
    }

    $balance = $tableValue['balance'] ?? [];
    if (!is_array($balance)) {
        $balance = [];
    }

    $balanceParts = [];
    foreach ($balance as $currency => $amount) {
        $currencyKey = strtoupper(trim((string)$currency));
        if ($currencyKey === '') {
            continue;
        }

        $amountString = is_numeric($amount) ? number_format((float)$amount, 2, '.', '') : (string)$amount;
        $balanceParts[] = $currencyKey . ' ' . $amountString;

        if ($currencyKey === 'USD' && is_numeric($amount)) {
            $totalUsd += (float)$amount;
        }
    }

    if ($balanceParts === []) {
        $balanceParts[] = 'USD 0.00';
    }

    $roles = $tableValue['roles'] ?? [];
    if (!is_array($roles)) {
        $roles = [];
    }

    $tableRows[] = [
        'id' => (int)($row['id'] ?? 0),
        'login' => (string)($tableValue['login'] ?? ''),
        'email' => (string)($tableValue['email'] ?? ''),
        'name' => (string)($row['name'] ?? ($tableValue['fio'] ?? '')),
        'roles' => implode(', ', array_values(array_map('strval', $roles))),
        'balance' => implode(', ', $balanceParts),
        'status' => (string)($row['status'] ?? ''),
    ];
}

Sogerien::Page()->title = 'User Balances';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <?php if ($queryError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= $h($queryError) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="text-muted small">Users</div>
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
$tr->set_params->columns = ['id', 'login', 'email', 'name', 'roles', 'balance', 'status'];
$tr->set_params->headers = [
    'id' => $t('common.id', 'ID'),
    'login' => $t('common.login', 'Login'),
    'email' => $t('common.email', 'Email'),
    'name' => $t('common.name', 'Name'),
    'roles' => $t('roles.role', 'Role'),
    'balance' => $t('users.balance', 'Balance'),
    'status' => $t('common.status', 'Status'),
];
$tr->set_params->gridId = 'user_balances_grid';
$tr->set_params->searchCols = ['login', 'email', 'name', 'roles', 'balance', 'status'];
$tr->set_params->perPage = 50;
$tr->set_params->columnsOrder = ['id', 'login', 'email', 'name', 'roles', 'balance', 'status'];
$tr->render();
?>
</main>
<?php
Sogerien::Page()->footer();
