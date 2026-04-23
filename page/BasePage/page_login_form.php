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

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= $h(Sogerien::Lang()->get_current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $h($t('auth.title_login', 'Authorization')) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/vue@3"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div id="app" class="d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow p-4" style="min-width: 300px; max-width: 400px; width: 100%;">
        <h4 class="mb-3 text-center"><?= $h($t('auth.login_to_system', 'Login to system')) ?></h4>

        <form method="post">
            <div class="mb-3">
                <label class="form-label"><?= $h($t('common.login', 'Login')) ?></label>
                <input name="login" type="text" class="form-control" placeholder="<?= $h($t('auth.enter_login', 'Enter login')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label"><?= $h($t('common.password', 'Password')) ?></label>
                <input name="password" type="password" class="form-control" placeholder="<?= $h($t('auth.enter_password', 'Enter password')) ?>">
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= $h($t('auth.sign_in', 'Sign in')) ?></button>
        </form>
    </div>
</div>

</body>
</html>
<?php
Sogerien::exit();
?>
