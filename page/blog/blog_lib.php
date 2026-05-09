<?php
declare(strict_types=1);

$DOC = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$candidates = [
    $DOC . '/sogerien/Sogerien.php',
    rtrim((string)dirname($DOC), '/') . '/sogerien/Sogerien.php',
    rtrim((string)dirname(dirname(__DIR__)), '/') . '/Sogerien.php',
];
$bootstrap = '';
foreach ($candidates as $candidate) {
    if (is_file($candidate) && is_readable($candidate)) {
        $bootstrap = $candidate;
        break;
    }
}
if ($bootstrap === '') {
    throw new RuntimeException('Sogerien bootstrap not found');
}
require_once $bootstrap;

function blog_db_connect(): APIPostgresql
{
    if (!defined('BLOG_DB_ALIAS') || !defined('BLOG_DB_HOST') || !defined('BLOG_DB_PORT') || !defined('BLOG_DB_NAME') || !defined('BLOG_DB_USER') || !defined('BLOG_DB_PASS')) {
        throw new RuntimeException('Blog DB config is not defined in index.php');
    }
    $db = Sogerien::DbController();
    $db->DbConfig->DB_HOST = (string)BLOG_DB_HOST;
    $db->DbConfig->DB_PORT = (string)BLOG_DB_PORT;
    $db->DbConfig->DB_NAME = (string)BLOG_DB_NAME;
    $db->DbConfig->DB_USER = (string)BLOG_DB_USER;
    $db->DbConfig->DB_PASS = (string)BLOG_DB_PASS;
    $db->DbConfig->DB_CHARSET = 'utf8mb4';
    $db->connect((string)BLOG_DB_ALIAS, $db->DbConfig);

    $pg = Sogerien::API()->Postgresql();
    $pg->set_db_alias((string)BLOG_DB_ALIAS);
    return $pg;
}

function blog_db_query(APIPostgresql $pg, string $sql, array $params = []): array
{
    $result = $pg->sql($sql, $params);
    if (($result['result'] ?? false) !== true) {
        $error = 'SQL_ERROR';
        if (isset($result['error']) && is_array($result['error']) && isset($result['error']['message']) && is_string($result['error']['message'])) {
            $error = trim($result['error']['message']);
        } elseif (isset($result['error']) && is_string($result['error'])) {
            $error = trim($result['error']);
        }
        throw new RuntimeException($error);
    }
    $rows = $result['rows'] ?? [];
    return is_array($rows) ? $rows : [];
}

function blog_ensure_schema(APIPostgresql $pg): void
{
    $s = defined('BLOG_SCHEMA') ? (string)BLOG_SCHEMA : 'blog';
    $ddl = [
        "CREATE SCHEMA IF NOT EXISTS {$s}",
        "CREATE TABLE IF NOT EXISTS {$s}.news_posts (
            id BIGSERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            excerpt TEXT NOT NULL DEFAULT '',
            content_html TEXT NOT NULL DEFAULT '',
            content_text TEXT NOT NULL DEFAULT '',
            cover_image_url TEXT NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            author_name VARCHAR(120) NOT NULL DEFAULT '',
            published_at TIMESTAMPTZ NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            seo_title VARCHAR(255) NOT NULL DEFAULT '',
            seo_description TEXT NOT NULL DEFAULT '',
            seo_keywords TEXT NOT NULL DEFAULT '',
            canonical_url TEXT NOT NULL DEFAULT '',
            schema_type VARCHAR(40) NOT NULL DEFAULT 'NewsArticle'
        )",
        "CREATE TABLE IF NOT EXISTS {$s}.news_tags (
            id BIGSERIAL PRIMARY KEY,
            name VARCHAR(120) NOT NULL UNIQUE,
            slug VARCHAR(120) NOT NULL UNIQUE,
            language JSONB NOT NULL DEFAULT '{\"RU\":{\"name\":\"\"},\"EN\":{\"name\":\"\"},\"DE\":{\"name\":\"\"},\"FR\":{\"name\":\"\"},\"ES\":{\"name\":\"\"}}'::jsonb,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS {$s}.news_post_tags (
            post_id BIGINT NOT NULL REFERENCES {$s}.news_posts(id) ON DELETE CASCADE,
            tag_id BIGINT NOT NULL REFERENCES {$s}.news_tags(id) ON DELETE CASCADE,
            PRIMARY KEY (post_id, tag_id)
        )",
        "CREATE INDEX IF NOT EXISTS idx_news_posts_status_pub ON {$s}.news_posts(status, published_at DESC, id DESC)",
        "CREATE INDEX IF NOT EXISTS idx_news_posts_slug ON {$s}.news_posts(slug)",
        "CREATE INDEX IF NOT EXISTS idx_news_post_tags_post ON {$s}.news_post_tags(post_id)",
        "CREATE INDEX IF NOT EXISTS idx_news_post_tags_tag ON {$s}.news_post_tags(tag_id)",
    ];

    foreach ($ddl as $sql) {
        blog_db_query($pg, $sql);
    }
}

function blog_str(mixed $value, string $default = ''): string
{
    return is_string($value) ? trim($value) : $default;
}

function blog_int(mixed $value, int $default = 0): int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
        return (int)$value;
    }
    return $default;
}

function blog_slugify(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '-', $text) ?? '';
    $text = preg_replace('/[\s_-]+/u', '-', $text) ?? '';
    $text = trim($text, '-');
    if ($text === '') {
        $text = 'post-' . date('Ymd-His');
    }
    return $text;
}

function blog_text_excerpt(string $html, int $max = 260): string
{
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
    if (mb_strlen($plain, 'UTF-8') <= $max) {
        return $plain;
    }
    return rtrim(mb_substr($plain, 0, $max - 1, 'UTF-8')) . '...';
}

function blog_unique_slug(APIPostgresql $pg, string $baseSlug, int $excludeId = 0): string
{
    $s = defined('BLOG_SCHEMA') ? (string)BLOG_SCHEMA : 'blog';
    $slug = $baseSlug;
    $n = 1;
    while (true) {
        $params = [':slug' => $slug];
        $sql = "SELECT id FROM {$s}.news_posts WHERE slug = :slug";
        if ($excludeId > 0) {
            $sql .= " AND id <> :id";
            $params[':id'] = $excludeId;
        }
        $rows = blog_db_query($pg, $sql, $params);
        if ($rows === []) {
            return $slug;
        }
        $n++;
        $slug = $baseSlug . '-' . $n;
    }
}

function blog_normalize_tags(mixed $tagsRaw): array
{
    $values = [];
    if (is_string($tagsRaw)) {
        $values = preg_split('/[,;\n]/u', $tagsRaw) ?: [];
    } elseif (is_array($tagsRaw)) {
        $values = $tagsRaw;
    }

    $out = [];
    foreach ($values as $value) {
        $name = trim((string)$value);
        if ($name === '') {
            continue;
        }
        $slug = blog_slugify($name);
        $out[$slug] = [
            'name' => mb_substr($name, 0, 120, 'UTF-8'),
            'slug' => mb_substr($slug, 0, 120, 'UTF-8'),
        ];
    }
    return array_values($out);
}

function blog_sync_tags(APIPostgresql $pg, int $postId, array $tags): void
{
    $s = defined('BLOG_SCHEMA') ? (string)BLOG_SCHEMA : 'blog';
    blog_db_query($pg, "DELETE FROM {$s}.news_post_tags WHERE post_id = :post_id", [':post_id' => $postId]);
    foreach ($tags as $tag) {
        $name = blog_str($tag['name'] ?? '');
        $slug = blog_str($tag['slug'] ?? '');
        if ($name === '' || $slug === '') {
            continue;
        }

        $rows = blog_db_query(
            $pg,
            "INSERT INTO {$s}.news_tags (name, slug)
             VALUES (:name, :slug)
             ON CONFLICT (slug) DO UPDATE SET name = EXCLUDED.name
             RETURNING id",
            [':name' => $name, ':slug' => $slug]
        );
        if ($rows === [] || !isset($rows[0]['id'])) {
            continue;
        }
        $tagId = (int)$rows[0]['id'];
        blog_db_query(
            $pg,
            "INSERT INTO {$s}.news_post_tags (post_id, tag_id)
             VALUES (:post_id, :tag_id)
             ON CONFLICT (post_id, tag_id) DO NOTHING",
            [':post_id' => $postId, ':tag_id' => $tagId]
        );
    }
}

