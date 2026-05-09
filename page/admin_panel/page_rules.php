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

$access_ok = Sogerien::AccessCheck()->check_access_or_show_login_form('page_rules', 0, []);
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

$input = Sogerien::InputRequest();
$post = $input->request_post_get_cookie_json;
$is_ajax = (trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'XMLHttpRequest' || (string)($post['ajax'] ?? '') === '1');

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && isset($post['action']) && $is_ajax) {
    $action = trim((string)$post['action']);
    $out = ['ok' => false, 'error' => ''];

    if ($action === 'add') {
        $name = trim((string)($post['name'] ?? ''));
        if ($name === '') {
            $out['error'] = $t('roles.name_required', 'Role name is required');
        } elseif (Sogerien::Roles()->add_role($name)) {
            $out['ok'] = true;
        } else {
            $out['error'] = Sogerien::Roles()->error ?: $t('roles.add_error', 'Add error');
        }
    } elseif ($action === 'update') {
        $old_name = trim((string)($post['old_name'] ?? ''));
        $new_name = trim((string)($post['new_name'] ?? ''));
        if ($old_name === '' || $new_name === '') {
            $out['error'] = $t('roles.rename_required', 'Current and new names are required');
        } elseif (Sogerien::Roles()->update_role($old_name, $new_name)) {
            $out['ok'] = true;
        } else {
            $out['error'] = Sogerien::Roles()->error ?: $t('roles.rename_error', 'Rename error');
        }
    } elseif ($action === 'delete') {
        $name = trim((string)($post['name'] ?? ''));
        if ($name === '') {
            $out['error'] = $t('roles.name_required', 'Role name is required');
        } elseif (Sogerien::Roles()->delete_role($name)) {
            $out['ok'] = true;
        } else {
            $out['error'] = Sogerien::Roles()->error ?: $t('roles.delete_error', 'Delete error');
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

$roles = Sogerien::Roles()->get_roles();

Sogerien::Page()->title = $t('roles.title', 'Role Groups');
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <p class="text-muted"><?= $h($t('roles.help', 'Roles list in table format. Click role name to rename.')) ?></p>

<?php
$tr = Sogerien::TableRenderer();

$rows_for_table = array_map(
    static function (string $roleName): array {
        return ['role' => $roleName];
    },
    is_array($roles) ? $roles : []
);

$tr->set_params->data = $rows_for_table;
$tr->set_params->columns = ['role'];
$tr->set_params->headers = [
    'role' => $t('roles.role', 'Role'),
];
$tr->set_params->gridId = 'roles_grid';
$tr->set_params->searchCols = ['role'];
$tr->set_params->perPage = 50;
$tr->set_params->columnsOrder = ['role'];

$tr->set_params->formatters['role'] = static function ($v, array $row) use ($h): string {
    $role = $h((string)($row['role'] ?? ''));
    return '<a href="javascript:void(0)" class="tr-role-edit text-decoration-none" data-role="' . $role . '">' . $role . '</a>';
};

$tr->render();
?>

    <div class="d-flex gap-2 mt-3">
        <button type="button" class="btn btn-primary" id="rules_btn_add"><?= $h($t('common.add', 'Add')) ?></button>
    </div>

    <div id="rules_message" class="alert mt-3 d-none" role="alert"></div>
</main>

<div class="modal fade" id="roleEditModal" tabindex="-1" aria-labelledby="roleEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roleEditModalLabel"><?= $h($t('roles.rename_role', 'Rename role')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $h($t('common.close', 'Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="roleEdit_old_name" value="">
                <label for="roleEdit_new_name" class="form-label"><?= $h($t('roles.role_name', 'Role name')) ?></label>
                <input type="text" class="form-control" id="roleEdit_new_name" placeholder="<?= $h($t('roles.role_name', 'Role name')) ?>" maxlength="200">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $h($t('common.cancel', 'Cancel')) ?></button>
                <button type="button" class="btn btn-primary" id="roleEdit_btn_save"><?= $h($t('common.save', 'Save')) ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="roleAddModal" tabindex="-1" aria-labelledby="roleAddModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roleAddModalLabel"><?= $h($t('roles.add_role', 'Add role')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $h($t('common.close', 'Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <label for="roleAdd_name" class="form-label"><?= $h($t('roles.role_name', 'Role name')) ?></label>
                <input type="text" class="form-control" id="roleAdd_name" placeholder="<?= $h($t('roles.role_name', 'Role name')) ?>" maxlength="200">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $h($t('common.cancel', 'Cancel')) ?></button>
                <button type="button" class="btn btn-primary" id="roleAdd_btn_save"><?= $h($t('common.save', 'Save')) ?></button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    function t(key, fallback) {
        if (typeof window.sogerienLangGet === 'function') {
            return window.sogerienLangGet(key, fallback || key);
        }
        return fallback || key;
    }

    var msgEl = document.getElementById('rules_message');
    var btnAdd = document.getElementById('rules_btn_add');

    var editModal = document.getElementById('roleEditModal');
    var editOldName = document.getElementById('roleEdit_old_name');
    var editNewName = document.getElementById('roleEdit_new_name');
    var editBtnSave = document.getElementById('roleEdit_btn_save');

    var addModal = document.getElementById('roleAddModal');
    var addName = document.getElementById('roleAdd_name');
    var addBtnSave = document.getElementById('roleAdd_btn_save');

    function showMessage(text, isError) {
        msgEl.textContent = text;
        msgEl.classList.remove('alert-success', 'alert-danger', 'd-none');
        msgEl.classList.add(isError ? 'alert-danger' : 'alert-success');
    }

    function post(action, data, onDone) {
        var form = new FormData();
        form.append('action', action);
        form.append('ajax', '1');
        for (var k in data) { if (Object.prototype.hasOwnProperty.call(data, k)) form.append(k, data[k]); }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            var res = {};
            try { res = JSON.parse(xhr.responseText); } catch (e) { res = { ok: false, error: t('common.response_error', 'Response error') }; }
            if (onDone) onDone(res);
        };
        xhr.onerror = function() { if (onDone) onDone({ ok: false, error: t('common.network_error', 'Network error') }); };
        xhr.send(form);
    }

    function openEditModal(roleName) {
        editOldName.value = roleName;
        editNewName.value = roleName;
        editNewName.focus();
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            new bootstrap.Modal(editModal).show();
        } else {
            editModal.classList.add('show');
            editModal.style.display = 'block';
        }
    }

    function openAddModal() {
        addName.value = '';
        addName.focus();
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            new bootstrap.Modal(addModal).show();
        } else {
            addModal.classList.add('show');
            addModal.style.display = 'block';
        }
    }

    editBtnSave.addEventListener('click', function() {
        var oldName = editOldName.value.trim();
        var newName = editNewName.value.trim();
        if (!newName) { showMessage(t('roles.enter_name', 'Enter role name.'), true); return; }
        if (oldName === newName) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var inst = bootstrap.Modal.getInstance(editModal);
                if (inst) inst.hide();
            } else {
                editModal.classList.remove('show');
                editModal.style.display = 'none';
            }
            return;
        }
        post('update', { old_name: oldName, new_name: newName }, function(res) {
            if (res.ok) {
                showMessage(t('roles.renamed', 'Role renamed.'), false);
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal && bootstrap.Modal.getInstance(editModal)) {
                    bootstrap.Modal.getInstance(editModal).hide();
                } else {
                    editModal.classList.remove('show');
                    editModal.style.display = 'none';
                }
                window.location.reload();
            } else {
                showMessage(res.error || t('roles.rename_error', 'Rename error'), true);
            }
        });
    });

    addBtnSave.addEventListener('click', function() {
        var name = addName.value.trim();
        if (!name) { showMessage(t('roles.enter_name', 'Enter role name.'), true); return; }
        post('add', { name: name }, function(res) {
            if (res.ok) {
                showMessage(t('roles.added', 'Role added.'), false);
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal && bootstrap.Modal.getInstance(addModal)) {
                    bootstrap.Modal.getInstance(addModal).hide();
                } else {
                    addModal.classList.remove('show');
                    addModal.style.display = 'none';
                }
                window.location.reload();
            } else {
                showMessage(res.error || t('roles.add_error', 'Add error'), true);
            }
        });
    });

    btnAdd.addEventListener('click', openAddModal);

    document.addEventListener('click', function(e) {
        var target = e.target;
        if (!(target instanceof Element)) return;
        var link = target.closest('.tr-role-edit');
        if (!link) return;
        e.preventDefault();
        var roleName = link.getAttribute('data-role') || '';
        if (!roleName) return;
        openEditModal(roleName);
    });
})();
</script>

<?php
Sogerien::Page()->footer();
?>
