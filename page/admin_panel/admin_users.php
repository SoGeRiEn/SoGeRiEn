<?php
declare(strict_types=1);

function au_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function au_s(mixed $value): string
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
$allRoles = $roles->get_roles();

$post = Sogerien::InputRequest()->request_post_get_cookie_json;
$alertType = '';
$alertText = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = au_s($post['action'] ?? '');
    $targetUserId = (int)au_s($post['user_id'] ?? 0);

    if ($targetUserId <= 0) {
        $alertType = 'danger';
        $alertText = 'Invalid user_id.';
    } elseif ($action === 'reset_password') {
        $newPassword = (string)($post['new_password'] ?? '');
        if (strlen($newPassword) < 8) {
            $alertType = 'danger';
            $alertText = 'Password must be at least 8 characters.';
        } else {
            $ok = $users->update_user($targetUserId, ['password' => $newPassword]);
            $alertType = $ok ? 'success' : 'danger';
            $alertText = $ok
                ? 'Password updated for user #' . $targetUserId . '.'
                : ($users->error !== '' ? $users->error : 'Failed to update password.');
        }
    } elseif ($action === 'update_roles') {
        $rolesRaw = $post['roles'] ?? [];
        $rolesList = [];
        if (is_array($rolesRaw)) {
            foreach ($rolesRaw as $r) {
                $r = au_s($r);
                if ($r !== '') {
                    $rolesList[] = $r;
                }
            }
        }
        $ok = $users->update_user($targetUserId, ['roles' => $rolesList]);
        $alertType = $ok ? 'success' : 'danger';
        $alertText = $ok
            ? 'Roles updated for user #' . $targetUserId . '.'
            : ($users->error !== '' ? $users->error : 'Failed to update roles.');
    } elseif ($action === 'update_profile') {
        $patch = [];
        foreach (['login', 'email', 'fio'] as $field) {
            if (isset($post[$field])) {
                $patch[$field] = au_s($post[$field]);
            }
        }
        if ($patch === []) {
            $alertType = 'danger';
            $alertText = 'Nothing to update.';
        } else {
            $ok = $users->update_user($targetUserId, $patch);
            $alertType = $ok ? 'success' : 'danger';
            $alertText = $ok
                ? 'Profile updated for user #' . $targetUserId . '.'
                : ($users->error !== '' ? $users->error : 'Failed to update profile.');
        }
    } elseif ($action === 'set_email_verified') {
        $verified = au_s($post['verified'] ?? '') === '1';
        $ok = $users->update_user($targetUserId, [
            'validate' => ['email' => $verified ? 'true' : 'false', 'phone' => 'false'],
        ]);
        // Workaround for nested validate: write the whole validate object via update_user.
        $alertType = $ok ? 'success' : 'danger';
        $alertText = $ok
            ? 'Email verification updated for user #' . $targetUserId . '.'
            : ($users->error !== '' ? $users->error : 'Failed to update email verification.');
    } elseif ($action === 'set_status') {
        $newStatus = au_s($post['new_status'] ?? '');
        if (!in_array($newStatus, ['actual', 'archive', 'delete'], true)) {
            $alertType = 'danger';
            $alertText = 'Invalid status.';
        } elseif ($targetUserId === $adminId && $newStatus !== 'actual') {
            // Защита: админ не может удалить/архивировать сам себя.
            $alertType = 'danger';
            $alertText = 'Нельзя изменить статус собственной учётной записи.';
        } else {
            $ok = $users->set_user_status($targetUserId, $newStatus);
            $alertType = $ok ? 'success' : 'danger';
            $alertText = $ok
                ? 'Статус user #' . $targetUserId . ' изменён на: ' . $newStatus . '. (Запись в БД не удалена, soft-delete.)'
                : ($users->error !== '' ? $users->error : 'Не удалось сменить статус.');
        }
    }
}

// Fetch user rows with all fields we need in a single SQL query.
$statusFilter = au_s($_GET['status'] ?? 'actual');
if (!in_array($statusFilter, ['actual', 'archive', 'delete', 'all'], true)) {
    $statusFilter = 'actual';
}

$searchQuery = strtolower(au_s($_GET['q'] ?? ''));

$statusSql = match ($statusFilter) {
    'archive' => "status = 'archive'",
    'delete'  => "status = 'delete'",
    'all'     => "status IN ('actual','archive','delete')",
    default   => "status = 'actual'",
};

$resp = Sogerien::DbController()->sql_request($dbAlias, [
    'sql' => "
        SELECT id, status, table_value
        FROM sogerien
        WHERE table_name = 'user'
          AND {$statusSql}
        ORDER BY id
        LIMIT 5000
    ",
    'params' => [],
]);
$resp = json_decode($resp, true);
$rows = is_array($resp['rows'] ?? null) ? $resp['rows'] : [];

$allUsers = [];
foreach ($rows as $row) {
    $tv = $row['table_value'] ?? [];
    if (is_string($tv)) {
        $decoded = json_decode($tv, true);
        $tv = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($tv)) {
        $tv = [];
    }
    $rolesArr = $tv['roles'] ?? [];
    if (!is_array($rolesArr)) {
        $rolesArr = [];
    }
    $validate = $tv['validate'] ?? [];
    if (!is_array($validate)) {
        $validate = [];
    }
    $emailVerified = au_s($validate['email'] ?? '') === 'true';
    $authProvider = strtolower(au_s($tv['auth_provider'] ?? ''));
    $googleSub = au_s($tv['google_sub'] ?? '');
    if ($authProvider === '' && $googleSub !== '') {
        $authProvider = 'google';
    }
    $allUsers[] = [
        'id'             => (int)($row['id'] ?? 0),
        'login'          => au_s($tv['login'] ?? ''),
        'email'          => au_s($tv['email'] ?? ''),
        'fio'            => au_s($tv['fio'] ?? ''),
        'roles'          => array_values(array_map('strval', $rolesArr)),
        'status'         => au_s($row['status'] ?? ''),
        'email_verified' => $emailVerified,
        'auth_provider'  => $authProvider !== '' ? $authProvider : 'local',
        'google_sub'     => $googleSub,
    ];
}

if ($searchQuery !== '') {
    $allUsers = array_values(array_filter($allUsers, static function (array $row) use ($searchQuery): bool {
        $hay = strtolower($row['id'] . ' ' . $row['login'] . ' ' . $row['email'] . ' ' . $row['fio']);
        return str_contains($hay, $searchQuery);
    }));
}

Sogerien::Page()->title = 'Admin · Users';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Users</h1>
            <div class="text-muted small">Управление пользователями: пароль, ФИО, email, группы доступа, верификация email.</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="/admin/access_groups">Access groups</a>
            <a class="btn btn-outline-secondary btn-sm" href="/admin/access_list">Access list</a>
            <a class="btn btn-outline-secondary btn-sm" href="/admin/provider">Provider</a>
        </div>
    </div>

    <?php if ($alertText !== ''): ?>
        <div class="alert alert-<?= au_h($alertType !== '' ? $alertType : 'info') ?>" role="alert"><?= au_h($alertText) ?></div>
    <?php endif; ?>

    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-md-3">
            <label class="form-label small text-muted">Статус</label>
            <select class="form-select form-select-sm" name="status">
                <?php foreach (['actual' => 'Активные', 'archive' => 'Архивные', 'delete' => 'Удалённые', 'all' => 'Все'] as $val => $label): ?>
                    <option value="<?= au_h($val) ?>"<?= $val === $statusFilter ? ' selected' : '' ?>><?= au_h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label small text-muted">Поиск (login / email / ФИО / id)</label>
            <input class="form-control form-control-sm" name="q" value="<?= au_h($_GET['q'] ?? '') ?>" placeholder="Начните вводить...">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary btn-sm w-100">Фильтр</button>
        </div>
        <div class="col-md-2 text-muted small text-end align-self-end">Найдено: <?= count($allUsers) ?></div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:60px">ID</th>
                    <th>Login</th>
                    <th style="width:110px">Pass</th>
                    <th>ФИО</th>
                    <th>Email</th>
                    <th>Access group</th>
                    <th style="width:130px">Email status</th>
                    <th style="width:90px">Auth</th>
                    <th style="width:90px">Edit</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($allUsers === []): ?>
                <tr><td colspan="9" class="text-center text-muted small">Пользователи не найдены.</td></tr>
            <?php endif; ?>
            <?php foreach ($allUsers as $row): ?>
                <?php $uid = (int)$row['id']; ?>
                <tr>
                    <td><code><?= au_h((string)$uid) ?></code></td>
                    <td><strong><?= au_h($row['login']) ?></strong></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-warning au-pass-btn"
                            data-user-id="<?= $uid ?>"
                            data-login="<?= au_h($row['login']) ?>">Изменить</button>
                    </td>
                    <td><?= au_h($row['fio'] ?: '-') ?></td>
                    <td><?= au_h($row['email'] ?: '-') ?></td>
                    <td>
                        <?php foreach ($row['roles'] as $r): ?>
                            <span class="badge bg-secondary me-1"><?= au_h($r) ?></span>
                        <?php endforeach; ?>
                        <?php if ($row['roles'] === []): ?>
                            <span class="text-muted small">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['email_verified']): ?>
                            <span class="badge bg-success">Verified</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Not verified</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['auth_provider'] === 'google'): ?>
                            <span class="badge bg-primary d-inline-flex align-items-center gap-1" title="Зарегистрирован через Google. Sub: <?= au_h($row['google_sub']) ?>">
                                <svg width="11" height="11" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="#fff" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/></svg>
                                Google
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Local</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary au-edit-btn"
                            data-user-id="<?= $uid ?>"
                            data-login="<?= au_h($row['login']) ?>"
                            data-email="<?= au_h($row['email']) ?>"
                            data-fio="<?= au_h($row['fio']) ?>"
                            data-roles='<?= au_h(json_encode($row['roles'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
                            data-email-verified="<?= $row['email_verified'] ? '1' : '0' ?>"
                            data-status="<?= au_h($row['status']) ?>"
                            data-self="<?= $uid === $adminId ? '1' : '0' ?>">Edit</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Password modal -->
    <div id="auPassModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="auPassTitle">
        <div class="panel" tabindex="-1" style="width:min(94vw,420px)">
            <div class="head">
                <strong id="auPassTitle">Новый пароль</strong>
                <button class="close" id="auPassClose" type="button" aria-label="Close">Esc</button>
            </div>
            <form method="post" action="/admin/users" style="padding:16px;display:flex;flex-direction:column;gap:12px">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="auPassUserId">
                <div class="text-muted small">User: <strong id="auPassUserLogin">-</strong></div>
                <div>
                    <label class="form-label small">Новый пароль (минимум 8 символов)</label>
                    <input type="password" class="form-control form-control-sm" name="new_password" autocomplete="new-password" required minlength="8" placeholder="••••••••">
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="auPassCancel">Отмена</button>
                    <button type="submit" class="btn btn-sm btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit modal: profile + roles + email verified -->
    <div id="auEditModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="auEditTitle">
        <div class="panel" tabindex="-1" style="width:min(94vw,640px)">
            <div class="head">
                <strong id="auEditTitle">Редактирование пользователя</strong>
                <button class="close" id="auEditClose" type="button" aria-label="Close">Esc</button>
            </div>
            <div style="padding:16px;display:flex;flex-direction:column;gap:14px;max-height:80vh;overflow:auto">
                <div class="text-muted small">User ID: <code id="auEditUserId">-</code></div>

                <form method="post" action="/admin/users" class="border rounded p-3" style="border-color:var(--line,#444)">
                    <div class="small text-muted mb-2">Профиль</div>
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="user_id" id="auProfileUserId">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small">Login</label>
                            <input class="form-control form-control-sm" name="login" id="auProfileLogin">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Email</label>
                            <input class="form-control form-control-sm" name="email" id="auProfileEmail">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small">ФИО</label>
                            <input class="form-control form-control-sm" name="fio" id="auProfileFio">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary mt-2">Сохранить профиль</button>
                </form>

                <form method="post" action="/admin/users" class="border rounded p-3" style="border-color:var(--line,#444)">
                    <div class="small text-muted mb-2">Access group (роли)</div>
                    <input type="hidden" name="action" value="update_roles">
                    <input type="hidden" name="user_id" id="auRolesUserId">
                    <div id="auRolesBox" class="d-flex flex-wrap gap-2">
                        <?php foreach ($allRoles as $r): ?>
                            <label class="form-check">
                                <input type="checkbox" class="form-check-input au-role-cb" name="roles[]" value="<?= au_h($r) ?>">
                                <span class="form-check-label ms-1"><?= au_h($r) ?></span>
                            </label>
                        <?php endforeach; ?>
                        <?php if ($allRoles === []): ?>
                            <div class="text-muted small">Нет ролей в системе.</div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary mt-2">Сохранить роли</button>
                </form>

                <form method="post" action="/admin/users" class="border rounded p-3" style="border-color:var(--line,#444)">
                    <div class="small text-muted mb-2">Email верификация</div>
                    <input type="hidden" name="action" value="set_email_verified">
                    <input type="hidden" name="user_id" id="auEmailUserId">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-9">
                            <select class="form-select form-select-sm" name="verified" id="auEmailVerifiedSelect">
                                <option value="1">Verified (подтверждён)</option>
                                <option value="0">Not verified (не подтверждён)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-sm btn-primary w-100">Сохранить</button>
                        </div>
                    </div>
                </form>

                <div class="border rounded p-3" id="auStatusBox" style="border-color:var(--line,#444)">
                    <div class="small text-muted mb-2">Статус учётной записи <span class="text-muted" id="auStatusCurrent">-</span></div>
                    <div class="text-muted small mb-2">Soft-delete: запись в БД сохраняется, меняется только поле status. Восстановить можно в любой момент.</div>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="post" action="/admin/users" class="m-0" onsubmit="return confirm('Восстановить пользователя (status = actual)?');">
                            <input type="hidden" name="action" value="set_status">
                            <input type="hidden" name="user_id" class="au-status-user-id">
                            <input type="hidden" name="new_status" value="actual">
                            <button type="submit" class="btn btn-sm btn-outline-success au-status-actual">Restore (actual)</button>
                        </form>
                        <form method="post" action="/admin/users" class="m-0" onsubmit="return confirm('Архивировать пользователя (status = archive)?');">
                            <input type="hidden" name="action" value="set_status">
                            <input type="hidden" name="user_id" class="au-status-user-id">
                            <input type="hidden" name="new_status" value="archive">
                            <button type="submit" class="btn btn-sm btn-outline-warning au-status-archive">Archive</button>
                        </form>
                        <form method="post" action="/admin/users" class="m-0" onsubmit="return confirm('Удалить пользователя (soft-delete, status = delete)?\n\nЗапись в БД сохранится, но юзер не сможет войти и не появится в списке Активные.');">
                            <input type="hidden" name="action" value="set_status">
                            <input type="hidden" name="user_id" class="au-status-user-id">
                            <input type="hidden" name="new_status" value="delete">
                            <button type="submit" class="btn btn-sm btn-danger au-status-delete">Удалить</button>
                        </form>
                    </div>
                    <div class="alert alert-warning small mt-2 mb-0 d-none" id="auSelfWarn">Это ваша собственная учётная запись - архивирование и удаление заблокированы.</div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
(function(){
    var passModal = document.getElementById('auPassModal');
    var editModal = document.getElementById('auEditModal');
    if (!passModal || !editModal) return;

    function openModal(modal){
        modal.setAttribute('aria-hidden','false');
        document.documentElement.style.overflow = 'hidden';
    }
    function closeModal(modal){
        modal.setAttribute('aria-hidden','true');
        document.documentElement.style.overflow = '';
    }

    function openPass(data){
        document.getElementById('auPassUserId').value = data.userId;
        document.getElementById('auPassUserLogin').textContent = data.login || '-';
        var pwInput = passModal.querySelector('input[name="new_password"]');
        if (pwInput) pwInput.value = '';
        openModal(passModal);
        window.setTimeout(function(){ if (pwInput) pwInput.focus(); }, 0);
    }

    function openEdit(data){
        document.getElementById('auEditUserId').textContent = data.userId;
        document.getElementById('auProfileUserId').value    = data.userId;
        document.getElementById('auRolesUserId').value      = data.userId;
        document.getElementById('auEmailUserId').value      = data.userId;

        document.getElementById('auProfileLogin').value     = data.login || '';
        document.getElementById('auProfileEmail').value     = data.email || '';
        document.getElementById('auProfileFio').value       = data.fio   || '';
        document.getElementById('auEmailVerifiedSelect').value = data.emailVerified ? '1' : '0';

        var set = {};
        (data.roles || []).forEach(function(r){ set[r] = true; });
        Array.prototype.forEach.call(document.querySelectorAll('.au-role-cb'), function(cb){
            cb.checked = !!set[cb.value];
        });

        // Status section: write user_id into each hidden input, show current status, lock destructive actions for self.
        Array.prototype.forEach.call(document.querySelectorAll('.au-status-user-id'), function(inp){
            inp.value = data.userId;
        });
        var curEl = document.getElementById('auStatusCurrent');
        if (curEl) curEl.textContent = '(текущий: ' + (data.status || '-') + ')';
        var warn = document.getElementById('auSelfWarn');
        var archBtn = document.querySelector('.au-status-archive');
        var delBtn  = document.querySelector('.au-status-delete');
        if (data.self){
            if (warn) warn.classList.remove('d-none');
            if (archBtn) archBtn.disabled = true;
            if (delBtn)  delBtn.disabled  = true;
        } else {
            if (warn) warn.classList.add('d-none');
            if (archBtn) archBtn.disabled = false;
            if (delBtn)  delBtn.disabled  = false;
        }

        openModal(editModal);
    }

    document.addEventListener('click', function(e){
        var pb = e.target.closest('.au-pass-btn');
        if (pb){
            e.preventDefault();
            openPass({
                userId: pb.getAttribute('data-user-id'),
                login:  pb.getAttribute('data-login')
            });
            return;
        }
        var eb = e.target.closest('.au-edit-btn');
        if (eb){
            e.preventDefault();
            var rolesAttr = eb.getAttribute('data-roles') || '[]';
            var roles = [];
            try { roles = JSON.parse(rolesAttr); } catch (err) { roles = []; }
            openEdit({
                userId: eb.getAttribute('data-user-id'),
                login:  eb.getAttribute('data-login'),
                email:  eb.getAttribute('data-email'),
                fio:    eb.getAttribute('data-fio'),
                roles:  Array.isArray(roles) ? roles : [],
                emailVerified: eb.getAttribute('data-email-verified') === '1',
                status: eb.getAttribute('data-status') || '',
                self:   eb.getAttribute('data-self') === '1'
            });
            return;
        }
    });

    document.getElementById('auPassClose').addEventListener('click', function(){ closeModal(passModal); });
    document.getElementById('auPassCancel').addEventListener('click', function(){ closeModal(passModal); });
    passModal.addEventListener('click', function(e){ if (e.target === passModal) closeModal(passModal); });

    document.getElementById('auEditClose').addEventListener('click', function(){ closeModal(editModal); });
    editModal.addEventListener('click', function(e){ if (e.target === editModal) closeModal(editModal); });

    window.addEventListener('keydown', function(e){
        if (e.key !== 'Escape') return;
        if (passModal.getAttribute('aria-hidden') === 'false'){ e.preventDefault(); closeModal(passModal); return; }
        if (editModal.getAttribute('aria-hidden') === 'false'){ e.preventDefault(); closeModal(editModal); }
    });
})();
</script>
<?php
Sogerien::Page()->footer();
