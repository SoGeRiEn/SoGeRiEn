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

Sogerien::Template()->title = $t('roles.title', 'Role Groups');
Sogerien::Template()->header();
Sogerien::Template()->mainmenu();

echo '<main class="container my-4 sog-ui">';
echo '<p class="text-muted">'
    . htmlspecialchars($t('roles.page_stub', 'Role groups management page.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '</p></main>';

Sogerien::Template()->footer();
