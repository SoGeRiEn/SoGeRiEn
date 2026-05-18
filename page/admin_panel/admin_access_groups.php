<?php
declare(strict_types=1);

function ag_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ag_s(mixed $value): string
{
    if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
        return trim((string)$value);
    }
    return '';
}

$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}

$adminUsers = Sogerien::Users();
$adminUsers->init_db_alias($dbAlias);
$adminUsers->load_identity_from_token();
$adminId = (int)$adminUsers->user_id;
$adminGroups = is_array($adminUsers->user_group) ? $adminUsers->user_group : [];
if ($adminId <= 0 || !isset($adminGroups['admin'])) {
    http_response_code(403);
    Sogerien::Page()->title = 'Access denied';
    Sogerien::Page()->header();
    Sogerien::Page()->mainmenu();
    echo '<main class="container my-4 sog-ui"><div class="alert alert-danger">Admin access required.</div></main>';
    Sogerien::Page()->footer();
    return;
}

$users = Sogerien::Users();
$users->init_db_alias($dbAlias);
$roles = Sogerien::Roles();
$roles->init_db_alias($dbAlias);

$post = Sogerien::InputRequest()->request_post_get_cookie_json;
$alertType = '';
$alertText = '';

function ag_load_user_logins(string $dbAlias): array
{
    $resp = Sogerien::DbController()->sql_request($dbAlias, [
        'sql' => "SELECT id, table_value->>'login' as login, table_value->'roles' as roles FROM sogerien WHERE table_name='user' AND status<>'delete' ORDER BY id",
        'params' => [],
    ]);
    $decoded = json_decode($resp, true);
    $out = [];
    foreach (($decoded['rows'] ?? []) as $r) {
        $rs = $r['roles'] ?? [];
        if (is_string($rs)) {
            $rs = json_decode($rs, true);
        }
        if (!is_array($rs)) {
            $rs = [];
        }
        $out[] = [
            'id'    => (int)($r['id'] ?? 0),
            'login' => (string)($r['login'] ?? ''),
            'roles' => array_map('strval', $rs),
        ];
    }
    return $out;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = ag_s($post['action'] ?? '');

    if ($action === 'role_add') {
        $name = ag_s($post['role_name'] ?? '');
        if ($name === '') {
            $alertType = 'danger';
            $alertText = 'Имя группы не задано.';
        } else {
            $ok = $roles->add_role($name);
            $alertType = $ok ? 'success' : 'danger';
            $alertText = $ok ? 'Группа добавлена: ' . $name : ($roles->error ?: 'Не удалось добавить группу.');
        }
    } elseif ($action === 'role_rename') {
        $oldName = ag_s($post['old_name'] ?? '');
        $newName = ag_s($post['new_name'] ?? '');
        if ($oldName === '' || $newName === '') {
            $alertType = 'danger';
            $alertText = 'Старое и новое имя группы обязательны.';
        } else {
            $ok = $roles->update_role($oldName, $newName);
            $alertType = $ok ? 'success' : 'danger';
            $alertText = $ok ? 'Группа переименована: ' . $oldName . ' → ' . $newName : ($roles->error ?: 'Не удалось переименовать.');
        }
    } elseif ($action === 'role_delete') {
        $name = ag_s($post['role_name'] ?? '');
        if ($name === '') {
            $alertType = 'danger';
            $alertText = 'Имя группы не задано.';
        } else {
            $ok = $roles->delete_role($name);
            $alertType = $ok ? 'success' : 'danger';
            $alertText = $ok ? 'Группа удалена: ' . $name : ($roles->error ?: 'Не удалось удалить.');
        }
    } elseif ($action === 'role_set_rights') {
        $roleName = ag_s($post['role_name'] ?? '');
        $selectedKeys = is_array($post['rights'] ?? null)
            ? array_values(array_filter(array_map('ag_s', $post['rights'])))
            : [];
        if ($roleName === '') {
            $alertType = 'danger';
            $alertText = 'Не передано имя группы.';
        } else {
            $changed = 0;
            $failed = 0;
            foreach ($roles->get_all_permission_keys() as $key) {
                $p = $roles->get_permission($key);
                if (!is_array($p)) {
                    continue;
                }
                $currentRoles = array_keys(array_filter($p['roles'] ?? []));
                $currentUsers = array_map('intval', array_keys(array_filter($p['users_id'] ?? [])));
                $hasRole = in_array($roleName, $currentRoles, true);
                $shouldHave = in_array($key, $selectedKeys, true);
                if ($hasRole === $shouldHave) {
                    continue;
                }
                $newRoles = $shouldHave
                    ? array_values(array_unique(array_merge($currentRoles, [$roleName])))
                    : array_values(array_diff($currentRoles, [$roleName]));
                $ok = $roles->update_permission($key, $newRoles, $currentUsers, null);
                if ($ok) {
                    $changed++;
                } else {
                    $failed++;
                }
            }
            $alertType = $failed > 0 ? 'warning' : 'success';
            $alertText = 'Обновлено прав: ' . $changed . ($failed > 0 ? '. Ошибок: ' . $failed : '') . '.';
        }
    } elseif ($action === 'role_add_user') {
        $roleName = ag_s($post['role_name'] ?? '');
        $userId = (int)ag_s($post['user_id'] ?? 0);
        if ($roleName === '' || $userId <= 0) {
            $alertType = 'danger';
            $alertText = 'Не указана группа или ID пользователя.';
        } else {
            $row = $users->get_user_by_id($userId);
            if ($row === null) {
                $alertType = 'danger';
                $alertText = 'Пользователь #' . $userId . ' не найден.';
            } else {
                $tv = $row['table_value'] ?? [];
                if (is_string($tv)) {
                    $tv = json_decode($tv, true);
                }
                $cur = isset($tv['roles']) && is_array($tv['roles']) ? array_map('strval', $tv['roles']) : [];
                if (in_array($roleName, $cur, true)) {
                    $alertType = 'info';
                    $alertText = 'Пользователь #' . $userId . ' уже в группе ' . $roleName . '.';
                } else {
                    $cur[] = $roleName;
                    $ok = $users->update_user($userId, ['roles' => $cur]);
                    $alertType = $ok ? 'success' : 'danger';
                    $alertText = $ok
                        ? 'Пользователь #' . $userId . ' добавлен в группу ' . $roleName . '.'
                        : ($users->error ?: 'Не удалось обновить пользователя.');
                }
            }
        }
    } elseif ($action === 'role_remove_user') {
        $roleName = ag_s($post['role_name'] ?? '');
        $userId = (int)ag_s($post['user_id'] ?? 0);
        if ($roleName === '' || $userId <= 0) {
            $alertType = 'danger';
            $alertText = 'Не указана группа или ID пользователя.';
        } else {
            $row = $users->get_user_by_id($userId);
            if ($row === null) {
                $alertType = 'danger';
                $alertText = 'Пользователь #' . $userId . ' не найден.';
            } else {
                $tv = $row['table_value'] ?? [];
                if (is_string($tv)) {
                    $tv = json_decode($tv, true);
                }
                $cur = isset($tv['roles']) && is_array($tv['roles']) ? array_map('strval', $tv['roles']) : [];
                if (!in_array($roleName, $cur, true)) {
                    $alertType = 'info';
                    $alertText = 'Пользователь #' . $userId . ' не состоит в группе ' . $roleName . '.';
                } else {
                    $new = array_values(array_diff($cur, [$roleName]));
                    $ok = $users->update_user($userId, ['roles' => $new]);
                    $alertType = $ok ? 'success' : 'danger';
                    $alertText = $ok
                        ? 'Пользователь #' . $userId . ' удалён из группы ' . $roleName . '.'
                        : ($users->error ?: 'Не удалось обновить пользователя.');
                }
            }
        }
    }
}

// === Data for view ===
$allRoles = $roles->get_roles();
$permKeys = $roles->get_all_permission_keys();
sort($permKeys);
$permsByRole = [];   // role -> [permission_key, ...]
$permNotes   = [];   // permission_key -> notes
foreach ($permKeys as $k) {
    $p = $roles->get_permission($k);
    if (!is_array($p)) {
        continue;
    }
    $permNotes[$k] = (string)($p['notes'] ?? '');
    foreach (array_keys(array_filter($p['roles'] ?? [])) as $r) {
        $permsByRole[$r][] = $k;
    }
}

$allUsers = ag_load_user_logins($dbAlias);
$usersByRole = [];   // role -> [['id'=>, 'login'=>], ...]
foreach ($allUsers as $u) {
    foreach ($u['roles'] as $r) {
        $usersByRole[$r][] = ['id' => $u['id'], 'login' => $u['login']];
    }
}

Sogerien::Page()->title = 'Admin · Access Groups';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Access Groups</h1>
            <div class="text-muted small">Группы прав (роли). В каждой группе - список разрешений и список пользователей. Из этой страницы добавляем/убираем права и юзеров в группу.</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="/admin/users">Users</a>
            <a class="btn btn-outline-secondary btn-sm" href="/admin/access_list">Access list</a>
        </div>
    </div>

    <?php if ($alertText !== ''): ?>
        <div class="alert alert-<?= ag_h($alertType !== '' ? $alertType : 'info') ?>" role="alert"><?= ag_h($alertText) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/access_groups" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="action" value="role_add">
        <div class="col-md-4">
            <label class="form-label small">Добавить группу</label>
            <input class="form-control form-control-sm" name="role_name" placeholder="например: moderator">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100">Добавить</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered align-middle mb-0">
            <thead>
                <tr>
                    <th>Группа</th>
                    <th style="width:120px">Прав</th>
                    <th style="width:120px">Пользователей</th>
                    <th style="width:300px">Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($allRoles === []): ?>
                <tr><td colspan="4" class="text-muted small text-center">Групп пока нет.</td></tr>
            <?php endif; ?>
            <?php foreach ($allRoles as $role): ?>
                <?php
                    $rRights = $permsByRole[$role] ?? [];
                    $rUsers  = $usersByRole[$role] ?? [];
                ?>
                <tr>
                    <td><code><?= ag_h($role) ?></code></td>
                    <td><?= count($rRights) ?></td>
                    <td><?= count($rUsers) ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary ag-edit-btn"
                            data-role="<?= ag_h($role) ?>"
                            data-rights='<?= ag_h(json_encode(array_values($rRights), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
                            data-users='<?= ag_h(json_encode(array_values($rUsers), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>Edit</button>
                        <form method="post" action="/admin/access_groups" class="d-inline-flex ms-1">
                            <input type="hidden" name="action" value="role_rename">
                            <input type="hidden" name="old_name" value="<?= ag_h($role) ?>">
                            <input class="form-control form-control-sm me-1" style="max-width:140px" name="new_name" placeholder="новое имя" required>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Rename</button>
                        </form>
                        <form method="post" action="/admin/access_groups" class="d-inline ms-1" onsubmit="return confirm('Удалить группу <?= ag_h(addslashes($role)) ?>?');">
                            <input type="hidden" name="action" value="role_delete">
                            <input type="hidden" name="role_name" value="<?= ag_h($role) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Edit modal -->
    <div id="agEditModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="agEditTitle">
        <div class="panel" tabindex="-1" style="width:min(94vw,760px)">
            <div class="head">
                <strong id="agEditTitle">Edit group</strong>
                <button class="close" id="agEditClose" type="button" aria-label="Close">Esc</button>
            </div>
            <div style="padding:16px;display:flex;flex-direction:column;gap:14px;max-height:80vh;overflow:auto">
                <div class="text-muted small">Группа: <code id="agEditRoleName">-</code></div>

                <!-- Rights -->
                <form method="post" action="/admin/access_groups" class="border rounded p-3" style="border-color:var(--line,#444)">
                    <div class="small text-muted mb-2">Права (отметь те, что должны быть у этой группы)</div>
                    <input type="hidden" name="action" value="role_set_rights">
                    <input type="hidden" name="role_name" id="agRightsRoleName">
                    <div class="d-flex flex-wrap gap-2" id="agRightsBox">
                        <?php foreach ($permKeys as $k): ?>
                            <label class="form-check" title="<?= ag_h($permNotes[$k] ?? '') ?>">
                                <input type="checkbox" class="form-check-input ag-right-cb" name="rights[]" value="<?= ag_h($k) ?>">
                                <span class="form-check-label ms-1"><?= ag_h($k) ?></span>
                            </label>
                        <?php endforeach; ?>
                        <?php if ($permKeys === []): ?>
                            <div class="text-muted small">Нет прав. Создайте права на странице <a href="/admin/access_list">Access list</a>.</div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary mt-2">Сохранить права</button>
                </form>

                <!-- Users in role -->
                <div class="border rounded p-3" style="border-color:var(--line,#444)">
                    <div class="small text-muted mb-2">Пользователи в группе</div>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="agUsersBox">
                        <span class="text-muted small">Загрузка...</span>
                    </div>
                    <form method="post" action="/admin/access_groups" class="row g-2 align-items-end">
                        <input type="hidden" name="action" value="role_add_user">
                        <input type="hidden" name="role_name" id="agAddUserRoleName">
                        <div class="col-md-6">
                            <label class="form-label small">Добавить пользователя по ID</label>
                            <input class="form-control form-control-sm" name="user_id" type="number" min="1" required>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-sm btn-primary w-100">Добавить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
(function(){
    var modal = document.getElementById('agEditModal');
    if (!modal) return;
    var closeEl = document.getElementById('agEditClose');

    function esc(s){
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(m){
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]);
        });
    }

    function openModal(){
        modal.setAttribute('aria-hidden','false');
        document.documentElement.style.overflow = 'hidden';
    }
    function closeModal(){
        modal.setAttribute('aria-hidden','true');
        document.documentElement.style.overflow = '';
    }

    function renderUsers(role, users){
        var box = document.getElementById('agUsersBox');
        if (!box) return;
        if (!users.length){
            box.innerHTML = '<span class="text-muted small">Никого нет в группе.</span>';
            return;
        }
        var html = '';
        users.forEach(function(u){
            html += '<form method="post" action="/admin/access_groups" class="d-inline-block m-0" onsubmit="return confirm(\'Удалить пользователя #'+esc(u.id)+' из группы '+esc(role)+'?\');">'
                  + '<input type="hidden" name="action" value="role_remove_user">'
                  + '<input type="hidden" name="role_name" value="'+esc(role)+'">'
                  + '<input type="hidden" name="user_id" value="'+esc(u.id)+'">'
                  + '<span class="badge bg-secondary d-inline-flex align-items-center gap-1" style="padding:6px 8px">'
                  +     '<span><code style="background:transparent;color:inherit">#'+esc(u.id)+'</code> '+esc(u.login||'-')+'</span>'
                  +     '<button type="submit" class="btn btn-sm p-0 ms-1" style="line-height:1;background:transparent;border:0;color:inherit" aria-label="Remove">×</button>'
                  + '</span>'
                  + '</form> ';
        });
        box.innerHTML = html;
    }

    document.addEventListener('click', function(e){
        var btn = e.target.closest('.ag-edit-btn');
        if (!btn) return;
        e.preventDefault();
        var role = btn.getAttribute('data-role') || '';
        var rights = [];
        var users = [];
        try { rights = JSON.parse(btn.getAttribute('data-rights') || '[]'); } catch (err) { rights = []; }
        try { users  = JSON.parse(btn.getAttribute('data-users')  || '[]'); } catch (err) { users  = []; }

        document.getElementById('agEditRoleName').textContent = role;
        document.getElementById('agRightsRoleName').value     = role;
        document.getElementById('agAddUserRoleName').value    = role;

        var set = {};
        rights.forEach(function(r){ set[r] = true; });
        Array.prototype.forEach.call(document.querySelectorAll('.ag-right-cb'), function(cb){
            cb.checked = !!set[cb.value];
        });

        renderUsers(role, Array.isArray(users) ? users : []);
        openModal();
    });

    closeEl.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
    window.addEventListener('keydown', function(e){
        if (modal.getAttribute('aria-hidden') === 'true') return;
        if (e.key === 'Escape'){ e.preventDefault(); closeModal(); }
    });
})();
</script>
<?php
Sogerien::Page()->footer();
