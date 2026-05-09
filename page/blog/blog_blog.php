<?php
declare(strict_types=1);
require_once __DIR__ . '/blog_lib.php';
require_once __DIR__ . '/blog_nav.php';

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function current_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/blog';
    return $scheme . '://' . $host . $uri;
}

function blog_abs_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return 'https://proxymint.com/blog';
    }
    if (preg_match('~^https?://~i', $url) === 1) {
        return $url;
    }
    return 'https://proxymint.com/' . ltrim($url, '/');
}

function blog_url_key(string $url): string
{
    $parts = parse_url($url);
    $host = strtolower((string)($parts['host'] ?? ''));
    $path = '/' . ltrim((string)($parts['path'] ?? '/'), '/');
    $path = rtrim($path, '/');
    if ($path === '') {
        $path = '/';
    }
    return $host . $path;
}

function blog_apply_lang_to_url(string $url, string $lang): string
{
    $lang = blog_lang_normalize($lang);
    if ($url === '') {
        return $url;
    }

    if (preg_match('~^https?://~i', $url) === 1) {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }

        $host = strtolower((string)($parts['host'] ?? ''));
        $allowedHosts = ['proxymint.com', 'www.proxymint.com'];
        if (!in_array($host, $allowedHosts, true)) {
            return $url;
        }

        $query = [];
        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str((string)$parts['query'], $query);
        }

        if ($lang === 'en') {
            unset($query['lang']);
        } else {
            $query['lang'] = $lang;
        }

        $scheme = (string)($parts['scheme'] ?? 'https');
        $path = (string)($parts['path'] ?? '/');
        $fragment = isset($parts['fragment']) ? ('#' . (string)$parts['fragment']) : '';
        $queryString = $query !== [] ? ('?' . http_build_query($query)) : '';

        return $scheme . '://' . $host . $path . $queryString . $fragment;
    }

    $pathOnly = '/' . ltrim($url, '/');
    if (strpos($pathOnly, '/blog') !== 0) {
        return $url;
    }

    $parts = parse_url($pathOnly);
    if (!is_array($parts)) {
        return $url;
    }

    $query = [];
    if (isset($parts['query']) && $parts['query'] !== '') {
        parse_str((string)$parts['query'], $query);
    }

    if ($lang === 'en') {
        unset($query['lang']);
    } else {
        $query['lang'] = $lang;
    }

    $queryString = $query !== [] ? ('?' . http_build_query($query)) : '';
    $fragment = isset($parts['fragment']) ? ('#' . (string)$parts['fragment']) : '';

    return (string)($parts['path'] ?? $pathOnly) . $queryString . $fragment;
}

/**
 * @param array<string,mixed> $row
 */
function blog_post_url(array $row): string
{
    $lang = blog_lang_normalize($_GET['lang'] ?? 'en');
    $canonicalRaw = blog_str($row['canonical_url'] ?? '');
    if ($canonicalRaw !== '') {
        return blog_apply_lang_to_url($canonicalRaw, $lang);
    }
    return blog_url(['slug' => (string)($row['slug'] ?? ''), 'lang' => $lang]);
}

function blog_public_base(): string
{
    $base = trim((string)($_SERVER['BLOG_PUBLIC_URL'] ?? '/api/news_blog.php'));
    if ($base === '') {
        $base = '/api/news_blog.php';
    }
    return rtrim($base, '/');
}

function blog_admin_url(): string
{
    $url = trim((string)($_SERVER['BLOG_ADMIN_URL'] ?? '/api/news_admin.php'));
    return $url !== '' ? $url : '/api/news_admin.php';
}

function blog_url_mode(): string
{
    return strtolower(trim((string)($_SERVER['BLOG_URL_MODE'] ?? 'query')));
}

function blog_asset_url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $base = trim((string)($_SERVER['BLOG_ASSETS_BASE_URL'] ?? ''));
    if ($base === '') {
        return $path;
    }
    return rtrim($base, '/') . $path;
}

function blog_parse_tag_slugs(mixed $raw): array
{
    $values = [];
    if (is_array($raw)) {
        $values = $raw;
    } elseif (is_string($raw) && trim($raw) !== '') {
        $values = preg_split('/[,;\n]/u', $raw) ?: [];
    }

    $out = [];
    foreach ($values as $value) {
        $slug = blog_slugify(blog_str($value));
        if ($slug === '') {
            continue;
        }
        $out[$slug] = $slug;
    }
    return array_values($out);
}

function blog_preview_text(string $text, int $maxLen = 220): string
{
    $clean = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    $clean = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $clean);
    $clean = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $clean);
    $clean = preg_replace('/<[^>]+>/', ' ', $clean);
    $clean = preg_replace('/[\r\n\t]+/u', ' ', $clean);
    $clean = preg_replace('/(?<=[\p{L}\p{N}\)\]])(?=[\p{Lu}][\p{Ll}])/u', ' ', $clean);
    $clean = preg_replace('/(?<=[\.\!\?\:;])(?=[\p{L}\p{N}])/u', ' ', $clean);
    $clean = preg_replace('/\s+/u', ' ', trim((string)$clean));
    if (!is_string($clean) || $clean === '') {
        return '';
    }

    if (mb_strlen($clean, 'UTF-8') <= $maxLen) {
        return $clean;
    }

    return rtrim(mb_substr($clean, 0, $maxLen, 'UTF-8')) . '...';
}

function blog_lang_normalize(mixed $raw): string
{
    $lang = strtolower(trim((string)$raw));
    return in_array($lang, ['en', 'ru'], true) ? $lang : 'en';
}

/**
 * @param array{slug?:string,tag?:string,tags?:array<int,string>,page?:int,q?:string} $params
 */
function blog_url(array $params = []): string
{
    $base = blog_public_base();
    $mode = blog_url_mode();
    $slug = blog_str($params['slug'] ?? '');
    $tagRaw = blog_str($params['tag'] ?? '');
    $tag = $tagRaw !== '' ? blog_slugify($tagRaw) : '';
    $tags = blog_parse_tag_slugs($params['tags'] ?? ($tag !== '' ? [$tag] : []));
    $page = max(1, blog_int($params['page'] ?? 1, 1));
    $q = blog_str($params['q'] ?? '');
    $lang = blog_lang_normalize($params['lang'] ?? ($_GET['lang'] ?? 'en'));

    if ($mode === 'path') {
        if ($slug !== '') {
            return $base . '/post/' . rawurlencode($slug);
        }

        $path = $base;
        if ($tag !== '') {
            $path .= '/tag/' . rawurlencode($tag);
            if ($page > 1) {
                $path .= '/page/' . $page;
            }
        } elseif ($page > 1) {
            $path .= '/page/' . $page;
        }

        $query = [];
        if ($q !== '') {
            $query['q'] = $q;
        }
        if ($tags !== []) {
            $query['tag'] = $tags;
        }
        if ($lang !== 'en') {
            $query['lang'] = $lang;
        }
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $path;
    }

    $query = [];
    if ($slug !== '') {
        $query['slug'] = $slug;
    } else {
        if ($tags !== []) {
            $query['tag'] = $tags;
        }
        if ($page > 1) {
            $query['page'] = $page;
        }
        if ($q !== '') {
            $query['q'] = $q;
        }
    }
    if ($lang !== 'en') {
        $query['lang'] = $lang;
    }
    return $base . ($query !== [] ? ('?' . http_build_query($query)) : '');
}

$errorText = '';
$post = null;
$items = [];
$allTags = [];
$searchHints = [];
$pagination = ['page' => 1, 'per_page' => 10, 'total' => 0, 'pages' => 1];
$q = blog_str($_GET['q'] ?? '');
$langRaw = blog_str($_GET['lang'] ?? '');
$lang = blog_lang_normalize($langRaw !== '' ? $langRaw : 'en');
$langRawNorm = strtolower($langRaw);
if ($langRaw !== '' && $langRawNorm !== $lang) {
    $redirectUrl = blog_abs_url(blog_apply_lang_to_url(current_url(), $lang));
    header('Location: ' . $redirectUrl, true, 301);
    exit;
}
$langSql = strtoupper($lang);
$tagSlugs = blog_parse_tag_slugs($_GET['tag'] ?? []);
$tag = $tagSlugs[0] ?? '';
$slugRaw = blog_str($_GET['slug'] ?? '');
$slug = $slugRaw !== '' ? blog_slugify($slugRaw) : '';
$page = max(1, blog_int($_GET['page'] ?? 1, 1));
$perPage = 10;

try {
    $pg = blog_db_connect();
    blog_ensure_schema($pg);
    $s = BLOG_SCHEMA;
    $allTags = blog_db_query(
        $pg,
        "SELECT COALESCE(NULLIF(t.language->'{$langSql}'->>'name', ''), t.name) AS name, t.slug
         FROM {$s}.news_tags t
         WHERE EXISTS (
             SELECT 1
             FROM {$s}.news_post_tags pt
             JOIN {$s}.news_posts p ON p.id = pt.post_id
             WHERE pt.tag_id = t.id
               AND p.status = 'published'
               AND (p.published_at IS NULL OR p.published_at <= NOW())
         )
         ORDER BY t.name ASC"
    );
    $titleRows = blog_db_query(
        $pg,
        "SELECT COALESCE(NULLIF(p.language->'{$langSql}'->>'title', ''), p.title) AS title
         FROM {$s}.news_posts p
         WHERE p.status = 'published'
           AND (p.published_at IS NULL OR p.published_at <= NOW())
         ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.id DESC
         LIMIT 400"
    );
    $hintsMap = [];
    foreach ($titleRows as $titleRow) {
        $title = blog_str($titleRow['title'] ?? '');
        if ($title !== '') {
            $hintsMap[mb_strtolower($title, 'UTF-8')] = $title;
        }
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $title) ?: [];
        foreach ($parts as $part) {
            $part = blog_str($part);
            if (mb_strlen($part, 'UTF-8') < 3) {
                continue;
            }
            $hintsMap[mb_strtolower($part, 'UTF-8')] = $part;
        }
    }
    $searchHints = array_values($hintsMap);
    sort($searchHints, SORT_NATURAL | SORT_FLAG_CASE);

    if ($slug !== '') {
        $rows = blog_db_query(
            $pg,
            "SELECT p.id,
                    COALESCE(NULLIF(p.language->'{$langSql}'->>'title', ''), p.title) AS title,
                    p.slug,
                    COALESCE(NULLIF(p.language->'{$langSql}'->>'excerpt', ''), p.excerpt) AS excerpt,
                    COALESCE(NULLIF(p.language->'{$langSql}'->>'content_html', ''), p.content_html) AS content_html,
                    COALESCE(NULLIF(p.language->'{$langSql}'->>'content_text', ''), p.content_text) AS content_text,
                    p.cover_image_url,
                    p.status, p.author_name, p.published_at, p.created_at, p.updated_at,
                    COALESCE(NULLIF(p.language->'{$langSql}'->>'seo_title', ''), p.seo_title) AS seo_title,
                    COALESCE(NULLIF(p.language->'{$langSql}'->>'seo_description', ''), p.seo_description) AS seo_description,
                    COALESCE(NULLIF(p.language->'{$langSql}'->>'seo_keywords', ''), p.seo_keywords) AS seo_keywords,
                    p.canonical_url, p.schema_type,
                    COALESCE((
                        SELECT json_agg(COALESCE(NULLIF(t.language->'{$langSql}'->>'name', ''), t.name) ORDER BY t.slug)
                        FROM {$s}.news_tags t
                        JOIN {$s}.news_post_tags pt ON pt.tag_id = t.id
                        WHERE pt.post_id = p.id
                    ), '[]'::json) AS tags,
                    COALESCE((
                        SELECT json_agg(t.slug ORDER BY t.slug)
                        FROM {$s}.news_tags t
                        JOIN {$s}.news_post_tags pt ON pt.tag_id = t.id
                        WHERE pt.post_id = p.id
                    ), '[]'::json) AS tag_slugs
             FROM {$s}.news_posts p
             WHERE p.slug = :slug
               AND p.status = 'published'
               AND (p.published_at IS NULL OR p.published_at <= NOW())
             LIMIT 1",
            [':slug' => $slug]
        );
        $post = $rows[0] ?? null;
    } else {
        $params = [];
        $where = [
            "p.status = 'published'",
            "(p.published_at IS NULL OR p.published_at <= NOW())",
        ];
        if ($q !== '') {
            $params[':q_like'] = '%' . $q . '%';
            $where[] = "(COALESCE(NULLIF(p.language->'{$langSql}'->>'title', ''), p.title) ILIKE :q_like
                        OR COALESCE(NULLIF(p.language->'{$langSql}'->>'excerpt', ''), p.excerpt) ILIKE :q_like
                        OR COALESCE(NULLIF(p.language->'{$langSql}'->>'content_text', ''), p.content_text) ILIKE :q_like
                        OR EXISTS (
                            SELECT 1
                            FROM {$s}.news_post_tags qpt
                            JOIN {$s}.news_tags qt ON qt.id = qpt.tag_id
                            WHERE qpt.post_id = p.id
                              AND COALESCE(NULLIF(qt.language->'{$langSql}'->>'name', ''), qt.name) ILIKE :q_like
                        ))";
        }
        if ($tagSlugs !== []) {
            $tagPlaceholders = [];
            foreach ($tagSlugs as $idx => $tagSlug) {
                $key = ':tag_slug_' . $idx;
                $params[$key] = $tagSlug;
                $tagPlaceholders[] = $key;
            }
            $where[] = "EXISTS (
                SELECT 1
                FROM {$s}.news_post_tags tpt
                JOIN {$s}.news_tags tt ON tt.id = tpt.tag_id
                WHERE tpt.post_id = p.id AND tt.slug IN (" . implode(', ', $tagPlaceholders) . ")
            )";
        }
        $whereSql = implode(' AND ', $where);

        $countRows = blog_db_query($pg, "SELECT COUNT(*)::int AS total FROM {$s}.news_posts p WHERE {$whereSql}", $params);
        $total = (int)($countRows[0]['total'] ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;

        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;
        $items = blog_db_query(
            $pg,
            "SELECT p.id,
                    COALESCE(NULLIF(p.language->'{$langSql}'->>'title', ''), p.title) AS title,
                    p.slug,
                    COALESCE(NULLIF(p.language->'{$langSql}'->>'excerpt', ''), p.excerpt) AS excerpt,
                    COALESCE(NULLIF(p.language->'{$langSql}'->>'content_text', ''), p.content_text) AS content_text,
                    p.cover_image_url, p.author_name, p.canonical_url,
                    p.published_at, p.created_at, p.updated_at,
                    COALESCE((
                        SELECT json_agg(COALESCE(NULLIF(t.language->'{$langSql}'->>'name', ''), t.name) ORDER BY t.slug)
                        FROM {$s}.news_tags t
                        JOIN {$s}.news_post_tags pt ON pt.tag_id = t.id
                        WHERE pt.post_id = p.id
                    ), '[]'::json) AS tags,
                    COALESCE((
                        SELECT json_agg(t.slug ORDER BY t.slug)
                        FROM {$s}.news_tags t
                        JOIN {$s}.news_post_tags pt ON pt.tag_id = t.id
                        WHERE pt.post_id = p.id
                    ), '[]'::json) AS tag_slugs
             FROM {$s}.news_posts p
             WHERE {$whereSql}
             ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.id DESC
             LIMIT :limit OFFSET :offset",
            $params
        );
        $pagination = ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => $pages];
    }
} catch (Throwable $e) {
    $errorText = $e->getMessage();
}

$metaTitle = 'ProxyMint Blog';
$metaDescription = 'Latest news and imported knowledge pages from proxymint.com';
$canonical = blog_abs_url(blog_apply_lang_to_url(current_url(), $lang));
if ($q !== '') {
    $canonical = blog_abs_url(blog_url(['lang' => $lang]));
}
$tagOptions = [];
foreach ($allTags as $dbTag) {
    $dbTagSlug = blog_str($dbTag['slug'] ?? '');
    $dbTagName = blog_str($dbTag['name'] ?? '');
    if ($dbTagSlug === '' || $dbTagName === '') {
        continue;
    }
    $tagOptions[] = ['id' => $dbTagSlug, 'text' => $dbTagName];
}

if (is_array($post)) {
    $metaTitle = blog_str($post['seo_title'] ?? '') !== '' ? blog_str($post['seo_title']) : blog_str($post['title'] ?? 'News');
    $metaDescription = blog_str($post['seo_description'] ?? '') !== '' ? blog_str($post['seo_description']) : blog_str($post['excerpt'] ?? '');
    $postCanonicalRaw = blog_str($post['canonical_url'] ?? '');
    if ($postCanonicalRaw === '') {
        $postCanonicalRaw = blog_url(['slug' => (string)($post['slug'] ?? '')]);
    }
    $canonical = blog_abs_url($postCanonicalRaw);
    $current = current_url();
    if (blog_url_key($current) !== blog_url_key($canonical)) {
        header('Location: ' . $canonical, true, 301);
        exit;
    }
}
?>
<!doctype html>
<html lang="<?= esc($lang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($metaTitle) ?></title>
  <meta name="description" content="<?= esc($metaDescription) ?>">
  <link rel="canonical" href="<?= esc($canonical) ?>">
  <link rel="icon" type="image/svg+xml" href="<?= esc(blog_asset_url('/sogerien/page/img/blog/favicon-news.svg?v=3')) ?>">
  <link rel="shortcut icon" type="image/svg+xml" href="<?= esc(blog_asset_url('/sogerien/page/img/blog/favicon-news.svg?v=3')) ?>">
  <link rel="apple-touch-icon" href="<?= esc(blog_asset_url('/sogerien/page/img/apple-touch-icon.png')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800;900&family=Figtree:wght@500;600;700;800&family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= esc(blog_asset_url('/sogerien/page/css/BasePage/forms.css')) ?>">
  <link rel="stylesheet" href="<?= esc(blog_asset_url('/sogerien/page/css/BasePage/table_renderer.css')) ?>">
  <link rel="stylesheet" href="<?= esc(blog_asset_url('/sogerien/page/css/blog/blog.css')) ?>">
  <link rel="stylesheet" href="<?= esc(blog_asset_url('/sogerien/page/effects/proxymint-background-kit/proxymint-background-kit.css')) ?>">
  
</head>
<body class="pm-theme-midnight pm-template-blog" data-template-blog="1">
  <div class="pm-admin-app">
    <div class="pm-admin-bg"></div>
    <div class="pm-admin-aura">
      <div class="pm-admin-aura-one"></div>
      <div class="pm-admin-aura-two"></div>
      <div class="pm-admin-aura-three"></div>
    </div>
    <div class="pm-admin-grid"></div>
    <div class="pm-admin-vignette"></div>

    <div class="pm-admin-shell">
      <?php news_render_nav($lang); ?>
      <header class="pm-hero wrap reveal">
        <p class="eyebrow">ProxyMint Blog Engine</p>
        <h1 class="h1">Latest From ProxyMint</h1>
        <div class="pm-actions">
          <a class="btn btn-outline" href="/">Main page</a>
          <a class="btn btn-purple" href="/admin">Sign in</a>
          <a class="btn btn-outline" href="<?= esc(blog_admin_url()) ?>">Blog admin</a>
        </div>
      </header>

      <section class="pm-toolbar wrap reveal reveal-d1">
        <?php
        $form = Sogerien::Forms()->configure([
            'id' => 'pm_blog_filters',
            'class' => 'pm-search tr-filters-unified tr-filters-unified-body',
            'action' => blog_url(),
            'method' => 'GET',
            'ajax' => false,
        ]);
$form->addInput('q', 'Search', 'text', ['placeholder' => 'Search...', 'list' => 'pm_blog_filters__q_suggestions', 'autocomplete' => 'off'], $q)->col(12, 12, 5, 5);
$form->addFacetDropdownMulti('tag', 'Tags', $tagOptions, [], $tagSlugs, true)->col(12, 12, 4, 4);
        $form->addHTML(
            '<div class="pm-form-actions">'
            . '<button class="btn btn-purple" type="submit">Search</button>'
            . '<a class="btn btn-outline" href="' . esc(blog_url()) . '">Reset</a>'
            . '</div>',
            [],
            'pm_form_actions'
        )->col(12, 12, 2, 2);
        $form->render();
        if ($searchHints !== []):
        ?>
          <datalist id="pm_blog_filters__q_suggestions">
            <?php foreach ($searchHints as $hint): ?>
              <?php $hint = blog_str($hint); if ($hint === '') { continue; } ?>
              <option value="<?= esc($hint) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        <?php
        endif;
        ?>
      </section>

      <main class="pm-main wrap reveal reveal-d2">
        <?php if ($errorText !== ''): ?>
          <article class="pm-card pm-post">
            <h2>Database Error</h2>
            <div class="pm-meta"><?= esc($errorText) ?></div>
          </article>
        <?php elseif ($post !== null): ?>
          <article class="pm-card pm-article">
            <h2><?= esc((string)$post['title']) ?></h2>
            <div class="pm-meta">
              <?= esc((string)($post['author_name'] ?: 'Editorial Team')) ?> |
              <?= esc((string)($post['published_at'] ?: $post['created_at'])) ?>
            </div>
            <?php if (blog_str($post['cover_image_url'] ?? '') !== ''): ?>
              <img class="pm-cover" src="<?= esc((string)$post['cover_image_url']) ?>" alt="<?= esc((string)$post['title']) ?>">
            <?php endif; ?>
            <div class="article-body"><?= (string)($post['content_html'] ?? '') ?></div>
            <?php if (is_array($post['tags'] ?? null) && $post['tags'] !== []): ?>
              <div class="tags">
                <?php foreach ((array)$post['tags'] as $tagIdx => $tagName): ?>
                  <?php $tagSlug = (string)(((array)($post['tag_slugs'] ?? []))[(int)$tagIdx] ?? blog_slugify((string)$tagName)); ?>
                  <a class="tag" href="<?= esc(blog_url(['tag' => $tagSlug])) ?>"><?= esc((string)$tagName) ?></a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </article>
          <?php
          $schema = [
              '@context' => 'https://schema.org',
              '@type' => blog_str($post['schema_type'] ?? 'NewsArticle'),
              'headline' => (string)($post['title'] ?? ''),
              'description' => blog_str($post['seo_description'] ?? '') !== '' ? (string)$post['seo_description'] : (string)($post['excerpt'] ?? ''),
              'datePublished' => (string)($post['published_at'] ?? $post['created_at'] ?? ''),
              'dateModified' => (string)($post['updated_at'] ?? ''),
              'author' => ['@type' => 'Person', 'name' => (string)($post['author_name'] ?: 'Editorial Team')],
              'publisher' => ['@type' => 'Organization', 'name' => 'proxymint.com'],
              'mainEntityOfPage' => $canonical,
              'keywords' => (string)($post['seo_keywords'] ?? ''),
              'articleBody' => (string)($post['content_text'] ?? ''),
          ];
          if (blog_str($post['cover_image_url'] ?? '') !== '') {
              $schema['image'] = (string)$post['cover_image_url'];
          }
          if (is_array($post['tags'] ?? null) && $post['tags'] !== []) {
              $schema['articleSection'] = array_map(static fn($v) => (string)$v, (array)$post['tags']);
          }
          ?>
          <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
        <?php else: ?>
          <?php if ($items === []): ?>
            <article class="pm-card pm-post">
              <h2>No Posts Found</h2>
              <p class="pm-excerpt">Try another query or tag.</p>
            </article>
          <?php else: ?>
            <div class="blog-grid">
              <?php foreach ($items as $row): ?>
                <?php
                $postUrl = blog_post_url($row);
                $postDate = (string)($row['published_at'] ?: $row['created_at']);
                $postTag = 'news';
                if (is_array($row['tags'] ?? null) && $row['tags'] !== []) {
                    $postTag = strtolower((string)$row['tags'][0]);
                }
                ?>
                <article class="blog-card">
                  <?php if (blog_str($row['cover_image_url'] ?? '') !== ''): ?>
                    <a class="blog-thumb blog-thumb-cover" href="<?= esc($postUrl) ?>">
                      <img src="<?= esc((string)$row['cover_image_url']) ?>" alt="<?= esc((string)$row['title']) ?>">
                    </a>
                  <?php else: ?>
                    <div class="blog-thumb blog-thumb-fallback">
                      <?php if (is_array($row['tags'] ?? null) && $row['tags'] !== []): ?>
                        <?php foreach ((array)$row['tags'] as $tagPos => $tagName): ?>
                          <?php
                          $tagName = (string)$tagName;
                          $rowTagSlugs = (array)($row['tag_slugs'] ?? []);
                          $tagSlug = (string)($rowTagSlugs[(int)$tagPos] ?? blog_slugify($tagName));
                          ?>
                          <a class="blog-thumb-fallback-link" href="<?= esc(blog_url(['tag' => $tagSlug])) ?>"><?= esc($tagName) ?></a>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <a class="blog-thumb-fallback-link" href="<?= esc($postUrl) ?>">news</a>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                  <div class="blog-body">
                    <div class="blog-tag"><?= esc($postTag) ?></div>
                    <h2 class="blog-title"><a href="<?= esc($postUrl) ?>"><?= esc((string)$row['title']) ?></a></h2>
                    <?php
                    $rawExcerpt = (string)($row['excerpt'] ?? '');
                    if (trim($rawExcerpt) === '') {
                        $rawExcerpt = (string)($row['content_text'] ?? '');
                    }
                    $safeExcerpt = blog_preview_text($rawExcerpt, 210);
                    ?>
                    <p class="pm-excerpt"><?= esc($safeExcerpt) ?></p>
                    <div class="blog-date"><?= esc($postDate) ?></div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if (($pagination['pages'] ?? 1) > 1): ?>
            <div class="pager">
              <?php
              $current = (int)$pagination['page'];
              $pages = (int)$pagination['pages'];
              for ($i = 1; $i <= $pages; $i++):
                  $url = blog_url(['page' => $i, 'q' => $q, 'tags' => $tagSlugs]);
              ?>
                <?php if ($i === $current): ?>
                  <span class="active"><?= $i ?></span>
                <?php else: ?>
                  <a href="<?= esc($url) ?>"><?= $i ?></a>
                <?php endif; ?>
              <?php endfor; ?>
            </div>
          <?php endif; ?>

          <?php
          $listSchema = [
              '@context' => 'https://schema.org',
              '@type' => 'ItemList',
              'itemListElement' => array_map(
                  static function (array $row, int $idx): array {
                      return [
                          '@type' => 'ListItem',
                          'position' => $idx + 1,
                          'url' => blog_abs_url(blog_post_url($row)),
                          'name' => (string)$row['title'],
                      ];
                  },
                  $items,
                  array_keys($items)
              ),
          ];
          ?>
          <script type="application/ld+json"><?= json_encode($listSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
        <?php endif; ?>
      </main>
    </div>
  </div>
  <script>
    (function () {
      var nodes = document.querySelectorAll('.reveal');
      if (!nodes.length) return;
      requestAnimationFrame(function () {
        nodes.forEach(function (node) { node.classList.add('visible'); });
      });
    })();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
  <script src="<?= esc(blog_asset_url('/sogerien/page/effects/proxymint-background-kit/cosmic-particle-network.js')) ?>" defer></script>
  <script src="<?= esc(blog_asset_url('/sogerien/page/js/BasePage/forms.js')) ?>" defer></script>
  <script src="<?= esc(blog_asset_url('/sogerien/page/js/BasePage/table_renderer.js')) ?>" defer></script>
  <script src="<?= esc(blog_asset_url('/sogerien/page/js/blog/blog.js')) ?>" defer></script>
  <script><?= news_nav_script() ?></script>
</body>
</html>









