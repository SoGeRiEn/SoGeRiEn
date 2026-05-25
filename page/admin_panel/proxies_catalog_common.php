<?php
declare(strict_types=1);

function pm_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pm_t(string $key, string $fallback = ''): string
{
    $value = Sogerien::Lang()->get($key);
    return $fallback !== '' && $value === $key ? $fallback : $value;
}

function pm_render_infatica_catalog(string $type, string $title): void
{
    $tpl = Sogerien::Template();
    $tpl->title = $title;
    $tpl->header();
    $tpl->mainmenu();

    $resp = Sogerien::API()->InfaticaIo()->proxiesList([
        'type' => $type,
        'limit' => 1000,
        'offset' => 0,
    ]);
    $rows = is_array($resp['data']['rows'] ?? null) ? $resp['data']['rows'] : [];

    echo '<main class="pm-content"><section class="pm-panel"><div class="pm-panel-head"><h1>' . pm_h($title) . '</h1></div>';
    if (($resp['ok'] ?? false) !== true) {
        echo '<div class="alert alert-danger">' . pm_h((string)($resp['error'] ?? pm_t('common.api_error', 'API error'))) . '</div>';
    }
    if (isset($resp['warning'])) {
        echo '<div class="alert alert-warning">' . pm_h((string)$resp['warning']) . '</div>';
    }
    echo '<div class="pm-card-grid">';
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $category = strtolower((string)($row['proxy_category'] ?? $row['proxy_api_type'] ?? ''));
        if ($category !== $type) {
            continue;
        }
        $name = (string)($row['title'] ?? $row['name'] ?? ucfirst($type));
        $country = (string)($row['country'] ?? $row['country_name'] ?? '');
        $price = (string)($row['traffic_price_usd'] ?? $row['price_usd'] ?? $row['price_per_gb'] ?? '');
        echo '<article class="pm-proxy-card">';
        echo '<div class="pm-proxy-card-kicker">' . pm_h(strtoupper($type)) . '</div>';
        echo '<h2>' . pm_h($name) . '</h2>';
        if ($country !== '') {
            echo '<p>' . pm_h($country) . '</p>';
        }
        if ($price !== '') {
            echo '<div class="pm-proxy-price">$' . pm_h($price) . '</div>';
        }
        echo '<a class="pm-cta pm-cta-primary" href="/proxy/checkout?provider=infatica_io&type=' . pm_h($type) . '&id=' . pm_h((string)($row['id'] ?? '')) . '">' . pm_h(Sogerien::Lang()->get('common.order')) . '</a>';
        echo '</article>';
    }
    if ($rows === []) {
        echo '<div class="alert alert-info">' . pm_h(Sogerien::Lang()->get('common.no_data')) . '</div>';
    }
    echo '</div></section></main>';
    $tpl->footer();
}
