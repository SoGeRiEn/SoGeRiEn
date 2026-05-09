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

$access_ok = Sogerien::AccessCheck()->check_access_or_show_login_form('page_users', 0, []);
if (!$access_ok) {
    $request = Sogerien::InputRequest()->request_post_get_cookie_json;
    $is_ajax = (trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'XMLHttpRequest' || (string)($request['ajax'] ?? '') === '1');

    if ($is_ajax || strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => $t('common.access_denied_admin_only', 'Access denied. Allowed role: admin.')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        Sogerien::exit();
    }

    http_response_code(403);
    Sogerien::Page()->title = $t('common.access_denied', 'Access denied');
    Sogerien::Page()->header();
    Sogerien::Page()->mainmenu();
    echo '<main class="container my-4 sog-ui">';
    echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($t('common.access_denied_admin_only', 'Access denied. Allowed role: admin.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
    echo '</main>';
    Sogerien::Page()->footer();
    Sogerien::exit();
}

$db_alias = Sogerien::AccessCheck()->db_alias;
if ($db_alias === '') {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => $t('common.db_alias_not_set', 'db_alias not set')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    Sogerien::exit();
}

Sogerien::Users()->init_db_alias($db_alias);
Sogerien::Roles()->init_db_alias($db_alias);

$input = Sogerien::InputRequest();
$post = $input->request_post_get_cookie_json;
$is_ajax = (trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'XMLHttpRequest' || (string)($post['ajax'] ?? '') === '1');

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && isset($post['action']) && $is_ajax) {
    $action = trim((string)$post['action']);
    $out = ['ok' => false, 'error' => ''];

    if ($action === 'add') {
        $login = trim((string)($post['login'] ?? ''));
        $email = trim((string)($post['email'] ?? ''));
        $password = (string)($post['password'] ?? '');
        $fio = trim((string)($post['fio'] ?? ''));
        $roles = isset($post['roles']) && is_array($post['roles']) ? array_map('strval', $post['roles']) : [];

        if ($login === '' || $email === '') {
            $out['error'] = $t('users.login_email_required', 'Login and email are required');
        } elseif ($password === '') {
            $out['error'] = $t('users.password_required', 'Password is required');
        } else {
            $data = [
                'login'    => $login,
                'email'    => $email,
                'password' => $password,
                'fio'      => $fio,
                'roles'    => $roles,
            ];
            if (Sogerien::Users()->create_user($data)) {
                $out['ok'] = true;
                $new_user = Sogerien::Users()->get_user_by_login($login);
                $tv = $new_user['table_value'] ?? null;
                if (is_string($tv)) {
                    $tv = json_decode($tv, true);
                }
                $out['user'] = $new_user ? [
                    'id'    => (int)($new_user['id'] ?? 0),
                    'name'  => (string)($new_user['name'] ?? ''),
                    'login' => $login,
                    'email' => $email,
                    'roles' => isset($tv['roles']) && is_array($tv['roles']) ? array_values(array_map('strval', $tv['roles'])) : [],
                ] : null;
            } else {
                $out['error'] = Sogerien::Users()->error ?: $t('users.add_error', 'Add error');
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($post['id'] ?? 0);
        $login = trim((string)($post['login'] ?? ''));
        $email = trim((string)($post['email'] ?? ''));
        $password = (string)($post['password'] ?? '');
        $fio = trim((string)($post['fio'] ?? ''));
        $roles = isset($post['roles']) && is_array($post['roles']) ? array_map('strval', $post['roles']) : [];

        if ($id <= 0) {
            $out['error'] = $t('users.invalid_user_id', 'Invalid user id');
        } elseif ($login === '' || $email === '') {
            $out['error'] = $t('users.login_email_required', 'Login and email are required');
        } else {
            $by_currency = [];
            $credit_limit_json = trim((string)($post['credit_limit_by_currency'] ?? ''));
            if ($credit_limit_json !== '') {
                $decoded = json_decode($credit_limit_json, true);
                if (is_array($decoded)) {
                    $by_currency = $decoded;
                }
            }

            $patch = [
                'login' => $login,
                'email' => $email,
                'fio' => $fio,
                'phone' => trim((string)($post['phone'] ?? '')),
                'code' => trim((string)($post['code'] ?? '')),
                'utm' => [
                    'source' => trim((string)($post['utm_source'] ?? '')),
                    'campaign' => trim((string)($post['utm_campaign'] ?? '')),
                ],
                'balance' => ['USD' => trim((string)($post['balance_USD'] ?? '0.00'))],
                'settings' => [
                    'tz' => trim((string)($post['settings_tz'] ?? 'Europe/Warsaw')),
                    'lang' => trim((string)($post['settings_lang'] ?? 'ru')),
                ],
                'validate' => [
                    'email' => in_array((string)($post['validate_email'] ?? ''), ['1', 'true', 'yes'], true) ? 'true' : 'false',
                    'phone' => in_array((string)($post['validate_phone'] ?? ''), ['1', 'true', 'yes'], true) ? 'true' : 'false',
                ],
                'partner_id' => (int)($post['partner_id'] ?? 0),
                'credit_limit' => [
                    'mode' => trim((string)($post['credit_limit_mode'] ?? '')),
                    'by_currency' => $by_currency,
                ],
                'partner_percent' => trim((string)($post['partner_percent'] ?? '')),
                'discount_percent' => trim((string)($post['discount_percent'] ?? '')),
                'roles' => $roles,
            ];
            if ($password !== '') {
                $patch['password'] = $password;
            }

            if (Sogerien::Users()->update_user($id, $patch)) {
                $out['ok'] = true;
            } else {
                $out['error'] = Sogerien::Users()->error ?: $t('users.save_error', 'Save error');
            }
        }
    } elseif ($action === 'set_status') {
        $id = (int)($post['id'] ?? 0);
        $status = trim((string)($post['status'] ?? ''));
        if ($id <= 0) {
            $out['error'] = $t('users.invalid_user_id', 'Invalid user id');
        } elseif (!in_array($status, ['actual', 'archive', 'delete'], true)) {
            $out['error'] = $t('users.invalid_status', 'Invalid status');
        } else {
            if (Sogerien::Users()->set_user_status($id, $status)) {
                $out['ok'] = true;
            } else {
                $out['error'] = Sogerien::Users()->error ?: $t('users.status_change_error', 'Status change error');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($post['id'] ?? 0);
        if ($id <= 0) {
            $out['error'] = $t('users.invalid_user_id', 'Invalid user id');
        } elseif (Sogerien::Users()->delete_user($id)) {
            $out['ok'] = true;
        } else {
            $out['error'] = Sogerien::Users()->error ?: $t('users.delete_error', 'Delete error');
        }
    } elseif ($action === 'archive') {
        $id = (int)($post['id'] ?? 0);
        if ($id <= 0) {
            $out['error'] = $t('users.invalid_user_id', 'Invalid user id');
        } elseif (Sogerien::Users()->archive_user($id)) {
            $out['ok'] = true;
        } else {
            $out['error'] = Sogerien::Users()->error ?: $t('users.archive_error', 'Archive error');
        }
    } elseif ($action === 'get_user') {
        $id = (int)($post['id'] ?? 0);
        if ($id <= 0) {
            $out['error'] = $t('users.invalid_user_id', 'Invalid user id');
        } else {
            $user = Sogerien::Users()->get_user_for_edit($id);
            if ($user !== null) {
                $out['ok'] = true;
                $out['user'] = $user;
            } else {
                $out['error'] = Sogerien::Users()->error ?: $t('users.not_found', 'User not found');
            }
        }
    } else {
        $out['error'] = $t('common.unknown_action', 'Unknown action');
    }

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    Sogerien::exit();
}

$statusFilter = (string)($_GET['status'] ?? 'active');
if (!in_array($statusFilter, ['active', 'archive', 'deleted', 'all'], true)) {
    $statusFilter = 'active';
}

$statusForUsers = null;
if ($statusFilter === 'active') {
    $statusForUsers = 'actual';
} elseif ($statusFilter === 'archive') {
    $statusForUsers = 'archive';
} elseif ($statusFilter === 'deleted') {
    $statusForUsers = 'delete';
} elseif ($statusFilter === 'all') {
    $statusForUsers = 'all';
}

$users = Sogerien::Users()->get_users_list($statusForUsers);
$roles_list = Sogerien::Roles()->get_roles();

Sogerien::Page()->title = $t('users.title', 'Users');
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui page-users-page">
    <?php
    $statusOptions = [
        'active' => $t('users.filter_active', 'Active'),
        'archive' => $t('users.filter_archive', 'Archived'),
        'deleted' => $t('users.filter_deleted', 'Deleted'),
        'all' => $t('users.filter_all', 'All'),
    ];
    $statusLabel = $statusOptions[$statusFilter] ?? $statusOptions['active'];
    $statusBaseQuery = $_GET;
    ?>
    <div id="pmUsersStatusFacet" class="tr-facet">
        <div class="small text-muted"><?= $h($t('users.show', 'Show')) ?>:</div>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle tr-cols-dd-toggle" type="button" id="users_grid__status_btn" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="tr-cols-dd-label"><?= $h($statusLabel) ?></span>
                <span class="badge bg-secondary-subtle text-secondary-emphasis tr-cols-dd-count d-none">0</span>
            </button>
            <div class="dropdown-menu p-2 tr-cols-dd-menu" aria-labelledby="users_grid__status_btn" style="min-width:260px; max-height:320px; overflow:auto;">
                <div class="d-flex gap-2 mb-2">
                    <?php $statusBaseQuery['status'] = 'all'; ?>
                    <a class="btn btn-sm btn-outline-secondary" href="?<?= $h(http_build_query($statusBaseQuery)) ?>">All</a>
                    <?php $statusBaseQuery['status'] = 'active'; ?>
                    <a class="btn btn-sm btn-outline-secondary" href="?<?= $h(http_build_query($statusBaseQuery)) ?>">Active</a>
                </div>
                <div id="users_grid__status" class="d-flex flex-column gap-1">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <?php $statusBaseQuery['status'] = $value; ?>
                        <a class="dropdown-item<?= $statusFilter === $value ? ' active' : '' ?>" href="?<?= $h(http_build_query($statusBaseQuery)) ?>">
                            <?= $h($label) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <p class="text-muted"><?= $h($t('users.help', 'Users list in table format. Click login or Edit button to update user.')) ?></p>

<?php
$tr = Sogerien::TableRenderer();

$rows_for_table = array_map(
    static function (array $row): array {
        $roles = isset($row['roles']) && is_array($row['roles']) ? array_values(array_map('strval', $row['roles'])) : [];
        return [
            'id' => $row['id'] ?? null,
            'login' => $row['login'] ?? '',
            'email' => $row['email'] ?? '',
            'name' => $row['name'] ?? '',
            'roles' => $roles === [] ? '' : implode(', ', $roles),
            'roles_raw' => $roles,
        ];
    },
    is_array($users) ? $users : []
);

$tr->set_params->data = $rows_for_table;
$tr->set_params->columns = ['id', 'login', 'email', 'name', 'roles'];
$tr->set_params->headers = [
    'id' => $t('common.id', 'ID'),
    'login' => $t('common.login', 'Login'),
    'email' => $t('common.email', 'Email'),
    'name' => $t('common.name', 'Name'),
    'roles' => $t('roles.role', 'Role'),
];
$tr->set_params->gridId = 'users_grid';
$tr->set_params->searchCols = ['login', 'email', 'name', 'roles'];
$tr->set_params->reset_query_params = ['status'];
$tr->set_params->perPage = 50;
$tr->set_params->columnsOrder = ['id', 'login', 'email', 'name', 'roles'];

$tr->set_params->formatters['login'] = static function ($v, array $row) use ($h): string {
    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) {
        return $h((string)$v);
    }
    $login = $h((string)($row['login'] ?? ''));
    $email = $h((string)($row['email'] ?? ''));
    $name = $h((string)($row['name'] ?? ''));
    $roles = isset($row['roles_raw']) && is_array($row['roles_raw']) ? $row['roles_raw'] : [];
    $rolesJ = $h((string)json_encode(array_values(array_map('strval', $roles)), JSON_UNESCAPED_UNICODE));

    return '<a href="javascript:void(0)" class="tr-action-edit text-decoration-none" data-id="' . $id . '" data-login="' . $login . '" data-email="' . $email . '" data-name="' . $name . '" data-roles="' . $rolesJ . '">' . $h((string)$v) . '</a>';
};

$tr->render();
?>
    <script>
    (function () {
        function mountUsersStatusFacet() {
            const source = document.getElementById('pmUsersStatusFacet');
            const facetsRoot = document.getElementById('users_grid__facets');
            if (!source || !facetsRoot) {
                return false;
            }

            const target = facetsRoot.querySelector('.tr-filters-unified-body > .tr-facets') || facetsRoot;
            if (!target) {
                return false;
            }

            const colsBtn = target.querySelector('#users_grid__cols_btn');
            const colsFacet = colsBtn ? colsBtn.closest('.tr-facet') : null;

            if (colsFacet && colsFacet.parentElement === target) {
                if (colsFacet.nextElementSibling !== source) {
                    colsFacet.insertAdjacentElement('afterend', source);
                }
            } elseif (source.parentElement !== target) {
                target.appendChild(source);
            }

            return true;
        }

        if (mountUsersStatusFacet()) {
            return;
        }

        const obs = new MutationObserver(function () {
            if (mountUsersStatusFacet()) {
                obs.disconnect();
            }
        });
        obs.observe(document.documentElement, { childList: true, subtree: true });
        setTimeout(function () { obs.disconnect(); }, 8000);
    })();
    </script>
    <div id="users_list" class="d-none" aria-hidden="true"></div>
    <div id="users_message" class="alert mt-3 d-none" role="alert"></div>
    <button type="button" id="users_btn_add" class="d-none"><?= $h($t('common.add', 'Add')) ?></button>

<?php
Sogerien::Forms()->render_crud_modals([
    'list_id' => 'users_list',
    'empty_id' => 'users_empty',
    'message_id' => 'users_message',
    'btn_add_id' => 'users_btn_add',
    'row_primary_key' => 'id',
    'row_display' => ['id', 'login', 'email', 'name'],
    'row_roles_key' => 'roles',
    'roles_list' => array_values($roles_list),
    'row_to_edit_map' => ['name' => 'fio'],
    'edit_to_row_map' => ['fio' => 'name'],
    'status_actions' => true,
    'status_key' => 'status',
    'btn_status_class' => 'users-btn-status',
    'reload_on_edit_success' => true,
    'get_user_on_edit' => true,
    'edit_modal' => [
        'id' => 'userEditModal',
        'title' => $t('users.edit_user', 'Edit user'),
        'dialog_class' => 'modal-dialog modal-lg modal-dialog-scrollable',
        'save_btn_id' => 'userEdit_btn_save',
        'fields' => [
            'id' => ['id' => 'userEdit_id', 'type' => 'hidden'],
            'login' => ['id' => 'userEdit_login', 'label' => $t('common.login', 'Login'), 'placeholder' => $t('common.login', 'Login'), 'maxlength' => 200],
            'email' => ['id' => 'userEdit_email', 'label' => $t('common.email', 'Email'), 'type' => 'email', 'placeholder' => 'email@example.com', 'maxlength' => 200],
            'fio' => ['id' => 'userEdit_fio', 'label' => $t('users.full_name', 'Full name'), 'placeholder' => $t('users.full_name', 'Full name'), 'maxlength' => 500],
            'phone' => ['id' => 'userEdit_phone', 'label' => $t('common.phone', 'Phone'), 'placeholder' => '+380...', 'maxlength' => 50],
            'code' => ['id' => 'userEdit_code', 'label' => $t('users.code', 'Code'), 'placeholder' => $t('users.code', 'Code'), 'maxlength' => 100],
            'utm_source' => ['id' => 'userEdit_utm_source', 'label' => $t('users.utm_source', 'UTM source'), 'placeholder' => 'source', 'maxlength' => 200],
            'utm_campaign' => ['id' => 'userEdit_utm_campaign', 'label' => $t('users.utm_campaign', 'UTM campaign'), 'placeholder' => 'campaign', 'maxlength' => 200],
            'balance_USD' => ['id' => 'userEdit_balance_USD', 'label' => $t('users.balance_usd', 'Balance USD'), 'placeholder' => '0.00', 'maxlength' => 50],
            'settings_tz' => ['id' => 'userEdit_settings_tz', 'label' => $t('users.timezone', 'Timezone'), 'placeholder' => 'Europe/Warsaw', 'maxlength' => 100],
            'settings_lang' => ['id' => 'userEdit_settings_lang', 'label' => $t('users.language', 'Language'), 'placeholder' => 'ru', 'maxlength' => 10],
            'validate_email' => ['id' => 'userEdit_validate_email', 'label' => $t('users.email_confirmed', 'Email confirmed'), 'type' => 'checkbox'],
            'validate_phone' => ['id' => 'userEdit_validate_phone', 'label' => $t('users.phone_confirmed', 'Phone confirmed'), 'type' => 'checkbox'],
            'partner_id' => ['id' => 'userEdit_partner_id', 'label' => $t('users.partner_id', 'Partner ID'), 'type' => 'number', 'maxlength' => 20],
            'credit_limit_mode' => ['id' => 'userEdit_credit_limit_mode', 'label' => $t('users.credit_limit_mode', 'Credit limit mode'), 'placeholder' => 'manual', 'maxlength' => 50],
            'credit_limit_by_currency' => ['id' => 'userEdit_credit_limit_by_currency', 'label' => $t('users.credit_limit_currency', 'Credit limit by currency (JSON)'), 'type' => 'textarea', 'rows' => 3, 'maxlength' => 2000],
            'partner_percent' => ['id' => 'userEdit_partner_percent', 'label' => $t('users.partner_percent', 'Partner %'), 'placeholder' => '0', 'maxlength' => 20],
            'discount_percent' => ['id' => 'userEdit_discount_percent', 'label' => $t('users.discount_percent', 'Discount %'), 'placeholder' => '0', 'maxlength' => 20],
            'password' => ['id' => 'userEdit_password', 'label' => $t('users.new_password_hint', 'New password (empty means no change)'), 'type' => 'password', 'placeholder' => $t('common.password', 'Password'), 'maxlength' => 200],
            'roles' => ['container_id' => 'userEdit_roles'],
        ],
    ],
    'add_modal' => [
        'id' => 'userAddModal',
        'title' => $t('users.add_user', 'Add user'),
        'save_btn_id' => 'userAdd_btn_save',
        'fields' => [
            'login' => ['id' => 'userAdd_login', 'label' => $t('common.login', 'Login'), 'placeholder' => $t('common.login', 'Login'), 'maxlength' => 200],
            'email' => ['id' => 'userAdd_email', 'label' => $t('common.email', 'Email'), 'type' => 'email', 'placeholder' => 'email@example.com', 'maxlength' => 200],
            'fio' => ['id' => 'userAdd_fio', 'label' => $t('users.full_name', 'Full name'), 'placeholder' => $t('users.full_name', 'Full name'), 'maxlength' => 500],
            'password' => ['id' => 'userAdd_password', 'label' => $t('common.password', 'Password'), 'type' => 'password', 'placeholder' => $t('common.password', 'Password'), 'maxlength' => 200],
            'roles' => ['container_id' => 'userAdd_roles'],
        ],
    ],
    'roles_label' => $t('roles.role', 'Role'),
]);
?>

</main>

<?php
Sogerien::Page()->footer();
?>
