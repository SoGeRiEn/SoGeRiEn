<?php
declare(strict_types=1);

function al_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function al_s(mixed $value): string
{
    if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
        return trim((string)$value);
    }
    return '';
}

function al_parse_ids(mixed $raw): array
{
    if (is_array($raw)) {
        $out = [];
        foreach ($raw as $r) {
            $s = al_s($r);
            if ($s !== '' && ctype_digit($s)) {
                $out[] = (int)$s;
            }
        }
        return array_values(array_unique($out));
    }
    $s = al_s($raw);
    if ($s === '') {
        return [];
    }
    $parts = preg_split('/[\s,;]+/', $s) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && ctype_digit($p)) {
            $out[] = (int)$p;
        }
    }
    return array_values(array_unique($out));
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

$roles = Sogerien::Roles();
$roles->init_db_alias($dbAlias);

$post = Sogerien::InputRequest()->request_post_get_cookie_json;
$alertType = '';
$alertText = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = al_s($post['action'] ?? '');

    if ($action === 'permission_add') {
        $key = al_s($post['key'] ?? '');
        $notes = al_s($post['notes'] ?? '');
        $rolesList = is_array($post['roles'] ?? null) ? array_values(array_filter(array_map('al_s', $post['roles']))) : [];
        $usersList = al_parse_ids($post['users_id'] ?? '');
        if ($key === '' || $key === 'roles') {
            $alertType = 'danger';
            $alertText = 'Ключ права обязателен (и не может быть "roles").';
        } else {
            $ok = $roles->add_permission($key, $rolesList, $usersList, $notes);
            $alertType = $ok ? 'success' : 'danger';
            $alertText = $ok ? 'Право добавлено: ' . $key : ($roles->error ?: 'Не удалось добавить.');
        }
    } elseif ($action === 'permission_update') {
        $key = al_s($post['key'] ?? '');
        $newKey = al_s($post['new_key'] ?? '');
        $notes = al_s($post['notes'] ?? '');
        $rolesList = is_array($post['roles'] ?? null) ? array_values(array_filter(array_map('al_s', $post['roles']))) : [];
        $usersList = al_parse_ids($post['users_id'] ?? '');
        if ($key === '') {
            $alertType = 'danger';
            $alertText = 'Ключ права обязателен.';
        } else {
            $ok = $roles->update_permission(
                $key,
                $rolesList,
                $usersList,
                $notes,
                ($newKey !== '' && $newKey !== $key) ? $newKey : null
            );
            $alertType = $ok ? 'success' : 'danger';
            $alertText = $ok ? 'Право обновлено: ' . $key : ($roles->error ?: 'Не удалось обновить.');
        }
    } elseif ($action === 'permission_delete') {
        $key = al_s($post['key'] ?? '');
        if ($key === '') {
            $alertType = 'danger';
            $alertText = 'Ключ права не задан.';
        } else {
            $ok = $roles->delete_permission($key);
            $alertType = $ok ? 'success' : 'danger';
            $alertText = $ok ? 'Право удалено: ' . $key : ($roles->error ?: 'Не удалось удалить.');
        }
    }
}

$allRoles = $roles->get_roles();
$permKeys = $roles->get_all_permission_keys();
sort($permKeys);

$permissions = [];
foreach ($permKeys as $k) {
    $p = $roles->get_permission($k);
    if (is_array($p)) {
        $permissions[$k] = $p;
    }
}

Sogerien::Page()->title = 'Admin · Access List';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Access List</h1>
            <div class="text-muted small">Список прав доступа (используются в коде проекта). Каждое право можно присвоить группам и/или конкретным пользователям.</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="/admin/users">Users</a>
            <a class="btn btn-outline-secondary btn-sm" href="/admin/access_groups">Access groups</a>
        </div>
    </div>

    <?php if ($alertText !== ''): ?>
        <div class="alert alert-<?= al_h($alertType !== '' ? $alertType : 'info') ?>" role="alert"><?= al_h($alertText) ?></div>
    <?php endif; ?>

    <details class="mb-3">
        <summary class="text-primary small" style="cursor:pointer">Добавить новое право</summary>
        <form method="post" action="/admin/access_list" class="row g-2 mt-2">
            <input type="hidden" name="action" value="permission_add">
            <div class="col-md-4">
                <label class="form-label small">Ключ права (как используется в коде)</label>
                <input class="form-control form-control-sm" name="key" placeholder="например: orders.read" required>
            </div>
            <div class="col-md-8">
                <label class="form-label small">Комментарий (зачем создали)</label>
                <input class="form-control form-control-sm" name="notes" placeholder="что защищает это право">
            </div>
            <div class="col-md-6">
                <label class="form-label small">Группы (любая из)</label>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($allRoles as $r): ?>
                        <label class="form-check"><input type="checkbox" class="form-check-input" name="roles[]" value="<?= al_h($r) ?>"><span class="form-check-label ms-1"><?= al_h($r) ?></span></label>
                    <?php endforeach; ?>
                    <?php if ($allRoles === []): ?>
                        <span class="text-muted small">Нет групп. Создайте на <a href="/admin/access_groups">Access groups</a>.</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label small">Пользователи (ID через запятую)</label>
                <input class="form-control form-control-sm" name="users_id" placeholder="например: 23, 107">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary w-100">Добавить право</button>
            </div>
        </form>
    </details>

    <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered align-middle mb-0">
            <thead>
                <tr>
                    <th>Ключ (key)</th>
                    <th>Комментарий</th>
                    <th>Назначено группам</th>
                    <th>Назначено пользователям</th>
                    <th style="width:140px">Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($permissions === []): ?>
                <tr><td colspan="5" class="text-muted small text-center">Прав пока нет.</td></tr>
            <?php endif; ?>
            <?php foreach ($permissions as $key => $p): ?>
                <?php
                    $rolesSet = is_array($p['roles'] ?? null) ? array_keys(array_filter($p['roles'])) : [];
                    $usersSet = is_array($p['users_id'] ?? null) ? array_keys(array_filter($p['users_id'])) : [];
                    $notes = (string)($p['notes'] ?? '');
                ?>
                <tr>
                    <td><code><?= al_h($key) ?></code></td>
                    <td class="small text-muted"><?= al_h($notes) ?></td>
                    <td>
                        <?php foreach ($rolesSet as $r): ?>
                            <span class="badge bg-info text-dark me-1"><?= al_h($r) ?></span>
                        <?php endforeach; ?>
                        <?php if ($rolesSet === []): ?><span class="text-muted small">-</span><?php endif; ?>
                    </td>
                    <td>
                        <?php foreach ($usersSet as $u): ?>
                            <span class="badge bg-secondary me-1">#<?= al_h((string)$u) ?></span>
                        <?php endforeach; ?>
                        <?php if ($usersSet === []): ?><span class="text-muted small">-</span><?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary al-edit-btn"
                            data-key="<?= al_h($key) ?>"
                            data-roles='<?= al_h(json_encode($rolesSet, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
                            data-users='<?= al_h(json_encode($usersSet, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
                            data-notes="<?= al_h($notes) ?>">Edit</button>
                        <form method="post" action="/admin/access_list" class="d-inline" onsubmit="return confirm('Удалить право <?= al_h(addslashes($key)) ?>?');">
                            <input type="hidden" name="action" value="permission_delete">
                            <input type="hidden" name="key" value="<?= al_h($key) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Edit modal -->
    <div id="alEditModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="alEditTitle">
        <div class="panel" tabindex="-1" style="width:min(94vw,720px)">
            <div class="head">
                <strong id="alEditTitle">Редактирование права</strong>
                <button class="close" id="alEditClose" type="button" aria-label="Close">Esc</button>
            </div>
            <form method="post" action="/admin/access_list" style="padding:16px;display:flex;flex-direction:column;gap:14px;max-height:80vh;overflow:auto">
                <input type="hidden" name="action" value="permission_update">
                <input type="hidden" name="key" id="alOldKey">
                <div class="row g-2">
                    <div class="col-md-12">
                        <label class="form-label small">Ключ (key)</label>
                        <input class="form-control form-control-sm" name="new_key" id="alNewKey" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small">Комментарий</label>
                        <input class="form-control form-control-sm" name="notes" id="alNotes">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Группы (любая из)</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($allRoles as $r): ?>
                                <label class="form-check"><input type="checkbox" class="form-check-input al-role-cb" name="roles[]" value="<?= al_h($r) ?>"><span class="form-check-label ms-1"><?= al_h($r) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Пользователи (ID через запятую)</label>
                        <input class="form-control form-control-sm" name="users_id" id="alUsers" placeholder="например: 23, 107">
                    </div>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</main>
<script>
(function(){
    var modal = document.getElementById('alEditModal');
    if (!modal) return;
    var closeEl = document.getElementById('alEditClose');

    function openModal(data){
        document.getElementById('alOldKey').value = data.key;
        document.getElementById('alNewKey').value = data.key;
        document.getElementById('alNotes').value  = data.notes || '';
        document.getElementById('alUsers').value  = (data.users || []).join(', ');
        var set = {};
        (data.roles || []).forEach(function(r){ set[r] = true; });
        Array.prototype.forEach.call(document.querySelectorAll('.al-role-cb'), function(cb){
            cb.checked = !!set[cb.value];
        });
        modal.setAttribute('aria-hidden','false');
        document.documentElement.style.overflow = 'hidden';
    }
    function closeModal(){
        modal.setAttribute('aria-hidden','true');
        document.documentElement.style.overflow = '';
    }

    document.addEventListener('click', function(e){
        var btn = e.target.closest('.al-edit-btn');
        if (!btn) return;
        e.preventDefault();
        var roles = [], users = [];
        try { roles = JSON.parse(btn.getAttribute('data-roles') || '[]'); } catch (err) {}
        try { users = JSON.parse(btn.getAttribute('data-users') || '[]'); } catch (err) {}
        openModal({
            key:   btn.getAttribute('data-key'),
            roles: Array.isArray(roles) ? roles : [],
            users: Array.isArray(users) ? users : [],
            notes: btn.getAttribute('data-notes') || ''
        });
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
