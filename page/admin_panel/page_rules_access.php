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

$access_ok = Sogerien::AccessCheck()->check_access_or_show_login_form('page_rules_access', 0, []);
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

Sogerien::Roles()->init_db_alias($db_alias);
Sogerien::Users()->init_db_alias($db_alias);

$input = Sogerien::InputRequest();
$post = $input->request_post_get_cookie_json;
$is_ajax = (trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'XMLHttpRequest' || (string)($post['ajax'] ?? '') === '1');

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && isset($post['action']) && $is_ajax) {
    $action = trim((string)$post['action']);
    $out = ['ok' => false, 'error' => ''];

    if ($action === 'get_list') {
        $keys = Sogerien::Roles()->get_all_permission_keys();
        $list = [];
        foreach ($keys as $key) {
            $perm = Sogerien::Roles()->get_permission($key);
            if ($perm !== null) {
                $userIds = array_keys($perm['users_id'] ?? []);
                $list[] = [
                    'key' => $key,
                    'id' => $key,
                    'notes' => $perm['notes'] ?? '',
                    'roles' => array_keys($perm['roles'] ?? []),
                    'users_id' => $userIds,
                    'users_id_str' => implode(', ', $userIds),
                ];
            }
        }
        $out['ok'] = true;
        $out['list'] = $list;
        $out['roles_available'] = Sogerien::Roles()->get_roles();
    } elseif ($action === 'update') {
        $id = trim((string)($post['id'] ?? ''));
        if ($id === '' || $id === 'roles') {
            $out['error'] = $t('rules_access.key_invalid', 'Invalid key');
        } else {
            $new_key = isset($post['key']) ? trim((string)$post['key']) : null;
            if ($new_key !== null && $new_key === '') {
                $new_key = null;
            }

            $notes = isset($post['notes']) ? trim((string)$post['notes']) : null;
            $roles = null;
            if (isset($post['roles']) && is_array($post['roles'])) {
                $roles = array_values(array_map('trim', $post['roles']));
                $roles = array_filter($roles, static fn($v) => $v !== '');
            }

            $users_id = null;
            $usersIdStr = isset($post['users_id_str']) ? trim((string)$post['users_id_str']) : '';
            if ($usersIdStr !== '') {
                $ids = preg_split('/[\s,;]+/u', $usersIdStr, -1, PREG_SPLIT_NO_EMPTY);
                $users_id = array_values(array_filter(array_map('trim', $ids), static fn(string $v): bool => $v !== ''));
            }

            if (Sogerien::Roles()->update_permission($id, $roles, $users_id, $notes, $new_key)) {
                $out['ok'] = true;
            } else {
                $out['error'] = Sogerien::Roles()->error ?: $t('rules_access.update_error', 'Update error');
            }
        }
    } elseif ($action === 'add') {
        $key = trim((string)($post['key'] ?? ($post['id'] ?? '')));
        $notes = trim((string)($post['notes'] ?? ''));

        $roles = [];
        if (isset($post['roles']) && is_array($post['roles'])) {
            $roles = array_values(array_filter(array_map('trim', $post['roles']), static fn($v) => $v !== ''));
        }

        $users_id = [];
        $usersIdStr = isset($post['users_id_str']) ? trim((string)$post['users_id_str']) : '';
        if ($usersIdStr !== '') {
            $ids = preg_split('/[\s,;]+/u', $usersIdStr, -1, PREG_SPLIT_NO_EMPTY);
            $users_id = array_values(array_filter(array_map('trim', $ids), static fn(string $v): bool => $v !== ''));
        }

        if ($key === '' || $key === 'roles') {
            $out['error'] = $t('rules_access.key_invalid', 'Invalid key');
        } elseif (Sogerien::Roles()->add_permission($key, $roles, $users_id, $notes)) {
            $out['ok'] = true;
            $out['user'] = [
                'id' => $key,
                'notes' => $notes,
                'users_id_str' => implode(', ', $users_id),
                'roles' => array_values($roles),
            ];
        } else {
            $out['error'] = Sogerien::Roles()->error ?: $t('rules_access.add_error', 'Add error');
        }
    } elseif ($action === 'delete') {
        $id = trim((string)($post['id'] ?? ''));
        if ($id === '' || $id === 'roles') {
            $out['error'] = $t('rules_access.key_invalid', 'Invalid key');
        } elseif (Sogerien::Roles()->delete_permission($id)) {
            $out['ok'] = true;
        } else {
            $out['error'] = Sogerien::Roles()->error ?: $t('rules_access.delete_error', 'Delete error');
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

$permission_keys = Sogerien::Roles()->get_all_permission_keys();
$roles_available = Sogerien::Roles()->get_roles();

$rows = [];
$users_for_picker = [];
foreach ($permission_keys as $k) {
    $perm = Sogerien::Roles()->get_permission($k);
    if ($perm === null) {
        continue;
    }

    $roles = array_keys($perm['roles'] ?? []);
    $userIds = array_keys($perm['users_id'] ?? []);
    $rows[] = [
        'id' => $k,
        'notes' => (string)($perm['notes'] ?? ''),
        'users_id_str' => implode(', ', $userIds),
        'roles' => array_values($roles),
    ];
}

$users_raw = Sogerien::Users()->get_users_list('actual');
foreach ($users_raw as $u) {
    $users_for_picker[] = [
        'id' => (int)($u['id'] ?? 0),
        'login' => (string)($u['login'] ?? ''),
        'name' => (string)($u['name'] ?? ''),
        'email' => (string)($u['email'] ?? ''),
    ];
}

$roles_for_picker = [];
foreach ($roles_available as $r) {
    $r = trim((string)$r);
    if ($r === '') {
        continue;
    }
    $roles_for_picker[] = ['id' => $r, 'name' => $r];
}

Sogerien::Page()->title = $t('rules_access.title', 'Direct Access Rights');
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui rules-access-page">
    <p class="text-muted"><?= $h($t('rules_access.help', 'Edit method permissions: notes, roles, direct users_id.')) ?></p>

<?php
$tr = Sogerien::TableRenderer();

$rows_for_table = array_map(
    static function (array $row): array {
        $roles = isset($row['roles']) && is_array($row['roles']) ? array_values(array_map('strval', $row['roles'])) : [];
        $usersIdStr = (string)($row['users_id_str'] ?? '');

        return [
            'roles_name' => (string)($row['id'] ?? ''),
            'roles' => $roles === [] ? '' : implode(', ', $roles),
            'roles_raw' => $roles,
            'users_id' => $usersIdStr,
            'notes' => (string)($row['notes'] ?? ''),
        ];
    },
    $rows
);

$tr->set_params->data = $rows_for_table;
$tr->set_params->columns = ['roles_name', 'roles', 'users_id', 'notes'];
$tr->set_params->headers = [
    'roles_name' => $t('rules_access.key', 'Permission key'),
    'roles' => $t('rules_access.groups', 'Role groups'),
    'users_id' => $t('common.users', 'Users'),
    'notes' => $t('rules_access.right_description', 'Description'),
];
$tr->set_params->gridId = 'rules_access_grid';
$tr->set_params->searchCols = ['roles_name', 'roles', 'users_id', 'notes'];
$tr->set_params->perPage = 50;
$tr->set_params->columnsOrder = ['roles_name', 'roles', 'users_id', 'notes'];

$tr->set_params->column_cell_types['users_id'] = [
    'type' => 'multiselect_search',
    'options' => $users_for_picker,
    'value_key' => 'id',
    'label_key' => 'login',
    'row_primary_key' => 'roles_name',
    'save_param' => 'users_id_str',
];

$tr->set_params->column_cell_types['roles'] = [
    'type' => 'multiselect_search',
    'options' => $roles_for_picker,
    'value_key' => 'id',
    'label_key' => 'name',
    'row_primary_key' => 'roles_name',
    'save_param' => 'roles[]',
];
$tr->set_params->multiselect_save_message_id = 'rules_access_message';

$tr->set_params->formatters['roles_name'] = static function ($v, array $row): string {
    $key = TableRenderer::h((string)($row['roles_name'] ?? ''));
    $notes = TableRenderer::h((string)($row['notes'] ?? ''));
    $usersId = TableRenderer::h((string)($row['users_id'] ?? ''));
    $roles = isset($row['roles_raw']) && is_array($row['roles_raw']) ? $row['roles_raw'] : [];
    $rolesJson = TableRenderer::h((string)json_encode(array_values(array_map('strval', $roles)), JSON_UNESCAPED_UNICODE));

    return '<a href="javascript:void(0)" class="tr-action-edit text-decoration-none" data-id="' . $key . '" data-notes="' . $notes . '" data-users_id_str="' . $usersId . '" data-roles="' . $rolesJson . '">' . $key . '</a>';
};

$tr->set_params->formatters['roles'] = static function ($v, array $row): string {
    $key = TableRenderer::h((string)($row['roles_name'] ?? ''));
    $notes = TableRenderer::h((string)($row['notes'] ?? ''));
    $usersId = TableRenderer::h((string)($row['users_id'] ?? ''));
    $roles = isset($row['roles_raw']) && is_array($row['roles_raw']) ? $row['roles_raw'] : [];
    $rolesText = $roles === [] ? '' : implode(', ', array_map('strval', $roles));
    $rolesJson = TableRenderer::h((string)json_encode(array_values(array_map('strval', $roles)), JSON_UNESCAPED_UNICODE));
    $label = TableRenderer::h($rolesText);

    return '<a href="javascript:void(0)" class="tr-action-edit text-decoration-none" data-id="' . $key . '" data-notes="' . $notes . '" data-users_id_str="' . $usersId . '" data-roles="' . $rolesJson . '">' . $label . '</a>';
};

$tr->set_params->formatters['notes'] = static function ($v, array $row): string {
    $key = TableRenderer::h((string)($row['roles_name'] ?? ''));
    $notes = (string)($row['notes'] ?? '');
    $notesEsc = TableRenderer::h($notes);
    $usersId = TableRenderer::h((string)($row['users_id'] ?? ''));
    $roles = isset($row['roles_raw']) && is_array($row['roles_raw']) ? $row['roles_raw'] : [];
    $rolesJson = TableRenderer::h((string)json_encode(array_values(array_map('strval', $roles)), JSON_UNESCAPED_UNICODE));
    $short = mb_strlen($notes) > 80 ? (mb_substr($notes, 0, 80) . '...') : $notes;

    return '<a href="javascript:void(0)" class="tr-action-edit text-decoration-none" data-id="' . $key . '" data-notes="' . $notesEsc . '" data-users_id_str="' . $usersId . '" data-roles="' . $rolesJson . '">' . TableRenderer::h($short) . '</a>';
};

$tr->render();
?>

    <div id="rules_access_list" class="d-none" aria-hidden="true"></div>
    <div id="rules_access_message" class="alert mt-3 d-none" role="alert"></div>
    <button type="button" id="rules_access_btn_add" class="btn btn-primary"><?= $h($t('rules_access.add_right', 'Add right')) ?></button>

<?php
Sogerien::Forms()->render_crud_modals([
    'list_id' => 'rules_access_list',
    'empty_id' => 'rules_access_empty',
    'message_id' => 'rules_access_message',
    'btn_add_id' => 'rules_access_btn_add',
    'row_primary_key' => 'id',
    'row_display' => ['id', 'notes', 'users_id_str'],
    'row_roles_key' => 'roles',
    'roles_list' => array_values($roles_available),
    'users_dataset' => $users_for_picker,
    'row_to_edit_map' => ['roles_name' => 'key'],
    'edit_to_row_map' => ['key' => 'roles_name'],
    'reload_on_edit_success' => true,
    'edit_modal' => [
        'id' => 'rulesAccessEditModal',
        'title' => $t('rules_access.edit_right', 'Edit direct access right'),
        'dialog_class' => 'modal-dialog modal-lg modal-dialog-scrollable',
        'save_btn_id' => 'rulesAccessEdit_btn_save',
        'fields' => [
            'id' => ['id' => 'rulesAccessEdit_id', 'type' => 'hidden'],
            'key' => ['id' => 'rulesAccessEdit_key', 'label' => $t('rules_access.key', 'Permission key'), 'maxlength' => 200],
            'notes' => ['id' => 'rulesAccessEdit_notes', 'label' => $t('rules_access.right_description', 'Description'), 'type' => 'textarea', 'rows' => 4, 'maxlength' => 2000],
            'users_id_str' => ['id' => 'rulesAccessEdit_users_id_str', 'label' => $t('rules_access.users_ids', 'Users (IDs comma separated)'), 'maxlength' => 1000, 'attrs' => ['data-widget' => 'users_picker']],
            'roles' => ['container_id' => 'rulesAccessEdit_roles'],
        ],
    ],
    'add_modal' => [
        'id' => 'rulesAccessAddModal',
        'title' => $t('rules_access.add_right', 'Add direct access right'),
        'save_btn_id' => 'rulesAccessAdd_btn_save',
        'fields' => [
            'id' => ['id' => 'rulesAccessAdd_id', 'label' => $t('rules_access.key', 'Permission key'), 'maxlength' => 200],
            'notes' => ['id' => 'rulesAccessAdd_notes', 'label' => $t('rules_access.right_description', 'Description'), 'type' => 'textarea', 'rows' => 4, 'maxlength' => 2000],
            'users_id_str' => ['id' => 'rulesAccessAdd_users_id_str', 'label' => $t('rules_access.users_ids', 'Users (IDs comma separated)'), 'maxlength' => 1000, 'attrs' => ['data-widget' => 'users_picker']],
            'roles' => ['container_id' => 'rulesAccessAdd_roles'],
        ],
    ],
    'roles_label' => $t('roles.role', 'Role'),
]);
?>
</main>
<?php
Sogerien::Page()->footer();
?>
