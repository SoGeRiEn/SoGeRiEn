<?php
declare(strict_types=1);
/* page_admin_test1.php
 * Полный тест класса Users.
 * Разрешено только Sogerien::Users() и базовые массивы для создания/изменения пользователя.
 */

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

const DB_ALIAS = 'front';

Sogerien::Template()->title = 'Users - full test';
Sogerien::Template()->header();
Sogerien::Template()->mainmenu();

$GLOBALS['TESTS'] = ['total' => 0, 'ok' => 0, 'fail' => 0];

function test_assert(bool $cond, string $name, string $details = ''): void
{
    if (!isset($GLOBALS['TESTS']) || !is_array($GLOBALS['TESTS'])) {
        $GLOBALS['TESTS'] = ['total' => 0, 'ok' => 0, 'fail' => 0];
    }
    $GLOBALS['TESTS']['total'] = (int)($GLOBALS['TESTS']['total'] ?? 0) + 1;
    if ($cond) {
        $GLOBALS['TESTS']['ok'] = (int)($GLOBALS['TESTS']['ok'] ?? 0) + 1;
    } else {
        $GLOBALS['TESTS']['fail'] = (int)($GLOBALS['TESTS']['fail'] ?? 0) + 1;
    }
    $status = $cond ? 'OK' : 'FAIL';
    $color  = $cond ? 'green' : 'red';
    echo "<div style='margin:4px 0;font:13px/1.4 monospace'>";
    echo "<b>{$name}</b>: <span style='color:{$color}'>{$status}</span>";
    if ($details !== '') {
        echo " <small>" . htmlspecialchars($details, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</small>";
    }
    echo "</div>";
}

function test_section(string $title): void
{
    echo "<h2 style='margin-top:20px;font:16px/1.4 monospace'>{$title}</h2>";
}

echo "<main class='container my-4 sog-ui'>";
echo "<div style='padding:20px;font:14px/1.5 var(--font-data, monospace)'>";
echo "<div>DB_ALIAS: <b>" . htmlspecialchars(DB_ALIAS, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</b></div>";
echo "</div>";

$users = Sogerien::Users();
$users->init_db_alias(DB_ALIAS);
test_section('init_db_alias()');
test_assert($users->status === true, 'init_db_alias', $users->error !== '' ? $users->error : 'ok');

// Негативные сценарии: ожидаем null/false — если результат совпадает с ожиданием, тест OK
test_section('Негативные сценарии (поиск несуществующего / невалидные данные)');

$nonexistentLogin = 'nonexistent_login_' . (string)time();
$byLoginBad = $users->get_user_by_login($nonexistentLogin);
test_assert($byLoginBad === null, 'get_user_by_login(несуществующий) → null', $users->status ? 'вернул не null' : (string)$users->error);

$nonexistentEmail = 'nonexistent_' . (string)time() . '@example.com';
$byEmailBad = $users->get_user_by_email($nonexistentEmail);
test_assert($byEmailBad === null, 'get_user_by_email(несуществующий) → null', $users->status ? 'вернул не null' : (string)$users->error);

$byIdBad = $users->get_user_by_id(999999999);
test_assert($byIdBad === null, 'get_user_by_id(несуществующий id) → null', $users->status ? 'вернул не null' : (string)$users->error);

$byIdZero = $users->get_user_by_id(0);
test_assert($byIdZero === null, 'get_user_by_id(0) → null', $users->status ? 'вернул не null' : (string)$users->error);

$byLoginEmpty = $users->get_user_by_login('');
test_assert($byLoginEmpty === null, 'get_user_by_login(пустая строка) → null', $users->status ? 'вернул не null' : (string)$users->error);

$byEmailEmpty = $users->get_user_by_email('');
test_assert($byEmailEmpty === null, 'get_user_by_email(пустая строка) → null', $users->status ? 'вернул не null' : (string)$users->error);

$updateBad = $users->update_user(999999999, ['fio' => 'x']);
test_assert($updateBad === false && $users->status === false, 'update_user(несуществующий id) → false', $users->status ? 'ожидали false' : (string)$users->error);

$updateZero = $users->update_user(0, ['fio' => 'x']);
test_assert($updateZero === false && $users->status === false, 'update_user(id=0) → false', $users->status ? 'ожидали false' : (string)$users->error);

$deleteBad = $users->delete_user(0);
test_assert($deleteBad === false && $users->status === false, 'delete_user(0) → false', $users->status ? 'ожидали false' : (string)$users->error);

$archiveBad = $users->archive_user(0);
test_assert($archiveBad === false && $users->status === false, 'archive_user(0) → false', $users->status ? 'ожидали false' : (string)$users->error);

$confirmBad = $users->confirm_email('');
test_assert($confirmBad === false && $users->status === false, 'confirm_email(пустой code) → false', $users->status ? 'ожидали false' : (string)$users->error);

$resetBad = $users->reset_password('', 'somepass');
test_assert($resetBad === false && $users->status === false, 'reset_password(пустой login) → false', $users->status ? 'ожидали false' : (string)$users->error);

$resetEmptyPass = $users->reset_password('someone@example.com', '');
test_assert($resetEmptyPass === false && $users->status === false, 'reset_password(пустой новый пароль) → false', $users->status ? 'ожидали false' : (string)$users->error);

$createBadId = $users->create_user(['user_id' => 0, 'login' => 'x', 'email' => 'x@y.com', 'password' => 'p']);
test_assert($createBadId === false && $users->status === false, 'create_user(user_id=0) → false', $users->status ? 'ожидали false' : (string)$users->error);

$createBadLogin = $users->create_user(['user_id' => 88888888, 'login' => '', 'email' => 'empty_login@test.com', 'password' => 'p']);
test_assert($createBadLogin === false && $users->status === false, 'create_user(пустой login) → false', $users->status ? 'ожидали false' : (string)$users->error);

$createBadEmail = $users->create_user(['user_id' => 88888889, 'login' => 'empty_email', 'email' => '', 'password' => 'p']);
test_assert($createBadEmail === false && $users->status === false, 'create_user(пустой email) → false', $users->status ? 'ожидали false' : (string)$users->error);

$createBadIdNeg = $users->create_user(['user_id' => -1, 'login' => 'x', 'email' => 'y@z.com', 'password' => 'p']);
test_assert($createBadIdNeg === false && $users->status === false, 'create_user(user_id=-1) → false', $users->status ? 'ожидали false' : (string)$users->error);

$byIdNeg = $users->get_user_by_id(-1);
test_assert($byIdNeg === null, 'get_user_by_id(-1) → null', $users->status ? 'вернул не null' : (string)$users->error);

$updateNeg = $users->update_user(-1, ['fio' => 'x']);
test_assert($updateNeg === false && $users->status === false, 'update_user(id=-1) → false', $users->status ? 'ожидали false' : (string)$users->error);

$deleteNeg = $users->delete_user(-1);
test_assert($deleteNeg === false && $users->status === false, 'delete_user(id=-1) → false', $users->status ? 'ожидали false' : (string)$users->error);

$archiveNeg = $users->archive_user(-1);
test_assert($archiveNeg === false && $users->status === false, 'archive_user(id=-1) → false', $users->status ? 'ожидали false' : (string)$users->error);

$confirmNonexistent = $users->confirm_email('nonexistent_code_' . (string)time());
test_assert($confirmNonexistent === false && $users->status === false, 'confirm_email(несуществующий code) → false', $users->status ? 'ожидали false' : (string)$users->error);

$resetNonexistentLogin = $users->reset_password('nonexistent_user_' . (string)time(), 'newpass');
test_assert($resetNonexistentLogin === false && $users->status === false, 'reset_password(несуществующий login) → false', $users->status ? 'ожидали false' : (string)$users->error);

$resetNonexistentEmail = $users->reset_password('nonexistent_' . (string)time() . '@example.com', 'newpass');
test_assert($resetNonexistentEmail === false && $users->status === false, 'reset_password(несуществующий email) → false', $users->status ? 'ожидали false' : (string)$users->error);

$usersNoInit = Sogerien::Users();
$byIdNoInit = $usersNoInit->get_user_by_id(1);
test_assert($byIdNoInit === null && $usersNoInit->status === false, 'get_user_by_id без init_db_alias → null', $usersNoInit->status ? 'ожидали false' : (string)$usersNoInit->error);

$updateNonexistent = $users->update_user(999999998, ['fio' => 'x']);
test_assert($updateNonexistent === false && $users->status === false, 'update_user(несуществующий id 999999998) → false', $users->status ? 'ожидали false' : (string)$users->error);

$deleteNonexistent = $users->delete_user(999999997);
test_assert($deleteNonexistent === true, 'delete_user(несуществующий id) не падает, возврат true', $deleteNonexistent === false ? 'ожидали true' : 'ok');
$afterDeleteNonexistent = $users->get_user_by_id(999999997);
test_assert($afterDeleteNonexistent === null, 'get_user_by_id после delete несуществующего → null', $users->status ? 'вернул не null' : (string)$users->error);

// Базовый массив для создания пользователя
$rand      = random_int(1000000, 9999999);
$login     = 'test_login_' . $rand;
$email     = 'test_user_' . $rand . '@example.com';
$createData = [
    'user_id'  => $rand,
    'roles'    => ['admin'],
    'login'    => $login,
    'email'    => $email,
    'password' => '123456',
    'fio'      => 'Test User #' . $rand,
    'phone'    => '+1000000' . $rand,
    'code'     => 'code_' . $rand,
    'status'   => 'actual',
];

test_section('create_user()');
$okCreate = $users->create_user($createData);
test_assert($okCreate && $users->status === true, 'create_user', $users->error);

if (!$okCreate && (str_contains((string)$users->error, '23505') || str_contains((string)$users->error, 'duplicate key'))) {
    echo "<div style='margin:8px 0;padding:10px;background:#fff3cd;border:1px solid #ffc107;font:13px monospace'>";
    echo "<strong>Подсказка:</strong> Дубликат ключа <code>sogerien.id</code> — последовательность отстаёт. Выполните в БД:<br>";
    echo "<code>SELECT setval(pg_get_serial_sequence('sogerien','id'), (SELECT COALESCE(MAX(id),1) FROM sogerien));</code>";
    echo "</div>";
}

echo "</main>";

// id записи в sogerien получаем только через Users
$rowByLogin = $users->get_user_by_login($login);
$internalId = is_array($rowByLogin) && isset($rowByLogin['id']) ? (int)$rowByLogin['id'] : 0;
test_assert($internalId > 0, 'id из get_user_by_login после create', 'id=' . $internalId);

// Дальнейшие тесты пользователя имеют смысл только если пользователь создан
if ($internalId <= 0) {
    test_section('Пропуск тестов пользователя (create не выполнен)');
    echo "<div style='margin:8px 0;font:13px monospace'>Остальные проверки (get_user_by_*, update_user, confirm_email, reset_password, archive_user, delete_user) пропущены: нет id записи.</div>";
} else {

test_section('get_user_by_id(), get_user_by_login(), get_user_by_email()');
$byId = $users->get_user_by_id($internalId);
test_assert($byId !== null, 'get_user_by_id (actual)', $users->error);

$byLogin = $users->get_user_by_login($login);
test_assert($byLogin !== null, 'get_user_by_login (actual)', $users->error);

$byEmail = $users->get_user_by_email($email);
test_assert($byEmail !== null, 'get_user_by_email (actual)', $users->error);

test_section('update_user()');
$updatePatch = [
    'fio'   => ($createData['fio'] ?? '') . ' / updated',
    'phone' => ($createData['phone'] ?? '') . '9',
];
$okUpdate = $users->update_user($internalId, $updatePatch);
test_assert($okUpdate && $users->status === true, 'update_user', $users->error);

$afterUpdate = $users->get_user_by_id($internalId);
$tv = is_array($afterUpdate) && isset($afterUpdate['table_value']) && is_array($afterUpdate['table_value']) ? $afterUpdate['table_value'] : [];
$fioFromDb   = (string)($tv['fio'] ?? '');
$phoneFromDb = (string)($tv['phone'] ?? '');
test_assert($fioFromDb === $updatePatch['fio'], 'fio updated', $fioFromDb);
test_assert($phoneFromDb === $updatePatch['phone'], 'phone updated', $phoneFromDb);

test_section('confirm_email()');
$okConfirm = $users->confirm_email($createData['code'] ?? '');
test_assert($okConfirm && $users->status === true, 'confirm_email', $users->error);

$afterConfirm = $users->get_user_by_id($internalId);
$tvAfterConfirm = is_array($afterConfirm) && isset($afterConfirm['table_value']) && is_array($afterConfirm['table_value']) ? $afterConfirm['table_value'] : [];
$validateAfter = is_array($tvAfterConfirm['validate'] ?? null) ? $tvAfterConfirm['validate'] : [];
$emailValidated = (string)($validateAfter['email'] ?? 'false');
test_assert($emailValidated === 'true', 'validate.email после confirm_email() = \"true\"', $emailValidated);

test_section('reset_password()');
$password2 = '123456';
$okResetByLogin = $users->reset_password($login, $password2);
test_assert($okResetByLogin && $users->status === true, 'reset_password (by login)', $users->error);

$okResetByEmail = $users->reset_password($email, $createData['password'] ?? '');
test_assert($okResetByEmail && $users->status === true, 'reset_password (by email)', $users->error);

test_section('archive_user()');
$okArchive = $users->archive_user($internalId);
test_assert($okArchive && $users->status === true, 'archive_user', $users->error);

$checkArchive = $users->get_user_by_id($internalId);
test_assert($checkArchive !== null, 'get_user_by_id (archive)', $users->error);

test_section('delete_user() — после delete выборки не возвращают пользователя');
$okDelete = $users->delete_user($internalId);
test_assert($okDelete && $users->status === true, 'delete_user', $users->error);

$checkDeletedById    = $users->get_user_by_id($internalId);
$checkDeletedByLogin = $users->get_user_by_login($login);
$checkDeletedByEmail = $users->get_user_by_email($email);

test_assert($checkDeletedById === null, 'get_user_by_id after delete', $users->error);
test_assert($checkDeletedByLogin === null, 'get_user_by_login after delete', $users->error);
test_assert($checkDeletedByEmail === null, 'get_user_by_email after delete', $users->error);

}

test_section('create_token(), save_token_to_cookie(), load_token_from_cookie()');
$user_id = (int)($createData['user_id'] ?? 0);
$token = $users->create_token($user_id, ['admin' => true]);
test_assert($token !== '' && $users->status === true, 'create_token', $users->error);

$saved = $token !== '' ? $users->save_token_to_cookie($token, 1) : false;
test_assert($saved && $users->status === true, 'save_token_to_cookie', $users->error);

$users->load_token_from_cookie();
test_assert($users->status === true, 'load_token_from_cookie status', $users->error);

test_section('РЕЗУЛЬТАТЫ');
$t = $GLOBALS['TESTS'] ?? ['total' => 0, 'ok' => 0, 'fail' => 0];
echo "<div style='padding:10px;font:14px/1.5 monospace'>";
echo "<div>Всего тестов: <b>" . (int)($t['total'] ?? 0) . "</b></div>";
echo "<div>Успешно: <b style='color:green'>" . (int)($t['ok'] ?? 0) . "</b></div>";
echo "<div>Провалы: <b style='color:red'>" . (int)($t['fail'] ?? 0) . "</b></div>";
echo "</div>";

echo "</main>";
Sogerien::Template()->footer();
Sogerien::markDone();
