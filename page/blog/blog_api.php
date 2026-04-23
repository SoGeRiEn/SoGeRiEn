<?php
declare(strict_types=1);

require_once __DIR__ . '/blog_lib.php';
require_once __DIR__ . '/blog_auth.php';

header('Content-Type: application/json; charset=utf-8');

function api_json(array $payload): never
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function api_fail(string $error, array $extra = []): never
{
    api_json(['result' => false, 'error' => $error] + $extra);
}

function api_payload(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $payload = [];
    foreach ($_REQUEST as $k => $v) {
        $payload[(string)$k] = $v;
    }
    return $payload;
}

function api_lang_code(mixed $raw): string
{
    $lang = strtoupper(blog_str($raw, 'EN'));
    return in_array($lang, ['RU', 'EN', 'DE', 'FR', 'ES'], true) ? $lang : 'EN';
}

function api_localized_select_list(string $lang): string
{
    $l = "'" . $lang . "'";
    return "p.id,
            COALESCE(NULLIF(p.language #>> ARRAY[{$l}, 'title'], ''), p.title) AS title,
            p.slug,
            COALESCE(NULLIF(p.language #>> ARRAY[{$l}, 'excerpt'], ''), p.excerpt) AS excerpt,
            p.cover_image_url,
            p.status,
            p.author_name,
            p.published_at,
            p.created_at,
            p.updated_at,
            COALESCE(NULLIF(p.language #>> ARRAY[{$l}, 'seo_title'], ''), p.seo_title) AS seo_title,
            COALESCE(NULLIF(p.language #>> ARRAY[{$l}, 'seo_description'], ''), p.seo_description) AS seo_description";
}

function api_localized_select_full(string $lang): string
{
    $l = "'" . $lang . "'";
    return "p.id,
            COALESCE(NULLIF(p.language #>> ARRAY[{$l}, 'title'], ''), p.title) AS title,
            p.slug,
            COALESCE(NULLIF(p.language #>> ARRAY[{$l}, 'excerpt'], ''), p.excerpt) AS excerpt,
            COALESCE(NULLIF(p.language #>> ARRAY[{$l}, 'content_html'], ''), p.content_html) AS content_html,
            COALESCE(NULLIF(p.language #>> ARRAY[{$l}, 'content_text'], ''), p.content_text) AS content_text,
            p.cover_image_url,
            p.status,
            p.author_name,
            p.published_at,
            p.created_at,
            p.updated_at,
            COALESCE(NULLIF(p.language #>> ARRAY[{$l}, 'seo_title'], ''), p.seo_title) AS seo_title,
            COALESCE(NULLIF(p.language #>> ARRAY[{$l}, 'seo_description'], ''), p.seo_description) AS seo_description,
            COALESCE(NULLIF(p.language #>> ARRAY[{$l}, 'seo_keywords'], ''), p.seo_keywords) AS seo_keywords,
            p.canonical_url,
            p.schema_type";
}

function api_load_post(APIPostgresql $pg, int $id): ?array
{
    $s = BLOG_SCHEMA;
    $rows = blog_db_query(
        $pg,
        "SELECT p.id, p.title, p.slug, p.excerpt, p.content_html, p.content_text, p.cover_image_url,
                p.status, p.author_name, p.published_at, p.created_at, p.updated_at,
                p.seo_title, p.seo_description, p.seo_keywords, p.canonical_url, p.schema_type,
                COALESCE((
                    SELECT json_agg(t.name ORDER BY t.name)
                    FROM {$s}.news_tags t
                    JOIN {$s}.news_post_tags pt ON pt.tag_id = t.id
                    WHERE pt.post_id = p.id
                ), '[]'::json) AS tags
         FROM {$s}.news_posts p
         WHERE p.id = :id
         LIMIT 1",
        [':id' => $id]
    );
    return $rows[0] ?? null;
}

function api_where_filters(bool $isAdmin, string $q, string $tag, array &$params): string
{
    $s = BLOG_SCHEMA;
    $where = [];

    if (!$isAdmin) {
        $where[] = "p.status = 'published'";
        $where[] = "(p.published_at IS NULL OR p.published_at <= NOW())";
    }

    if ($q !== '') {
        $params[':q_like'] = '%' . $q . '%';
        $where[] = "(p.title ILIKE :q_like
                    OR p.excerpt ILIKE :q_like
                    OR p.content_text ILIKE :q_like
                    OR p.seo_title ILIKE :q_like
                    OR p.seo_description ILIKE :q_like
                    OR EXISTS (
                        SELECT 1
                        FROM {$s}.news_post_tags qpt
                        JOIN {$s}.news_tags qt ON qt.id = qpt.tag_id
                        WHERE qpt.post_id = p.id AND qt.name ILIKE :q_like
                    ))";
    }

    if ($tag !== '') {
        $params[':tag_slug'] = $tag;
        $where[] = "EXISTS (
            SELECT 1
            FROM {$s}.news_post_tags tpt
            JOIN {$s}.news_tags tt ON tt.id = tpt.tag_id
            WHERE tpt.post_id = p.id AND tt.slug = :tag_slug
        )";
    }

    return $where === [] ? '1=1' : implode(' AND ', $where);
}

try {
    $payload = api_payload();
    $action = strtolower(blog_str($payload['action'] ?? 'list_public'));
    $lang = api_lang_code($payload['lang'] ?? ($_GET['lang'] ?? 'EN'));
    $isAdmin = news_auth_is_admin();

    $adminOnlyActions = ['list_admin', 'save', 'delete', 'seed_demo'];
    if (in_array($action, $adminOnlyActions, true) && !$isAdmin) {
        $authError = news_auth_error_payload();
        http_response_code((int)$authError['status']);
        api_json([
            'result' => false,
            'error' => $authError['error'],
            'login_url' => $authError['login_url'],
        ]);
    }

    $pg = blog_db_connect();
    blog_ensure_schema($pg);
    $s = BLOG_SCHEMA;

    if ($action === 'list_public' || $action === 'list_admin') {
        $q = blog_str($payload['q'] ?? '');
        $tagRaw = blog_str($payload['tag'] ?? '');
        $tag = $tagRaw !== '' ? blog_slugify($tagRaw) : '';
        $page = max(1, blog_int($payload['page'] ?? 1, 1));
        $perPage = blog_int($payload['per_page'] ?? 10, 10);
        if ($perPage < 1) {
            $perPage = 10;
        }
        if ($perPage > 50) {
            $perPage = 50;
        }
        $offset = ($page - 1) * $perPage;
        $isAdminList = $action === 'list_admin';

        $params = [];
        $whereSql = api_where_filters($isAdminList, $q, $tag, $params);
        $countRows = blog_db_query($pg, "SELECT COUNT(*)::int AS total FROM {$s}.news_posts p WHERE {$whereSql}", $params);
        $total = (int)($countRows[0]['total'] ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));

        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;
        $rows = blog_db_query(
            $pg,
            "SELECT " . api_localized_select_list($lang) . ",
                    COALESCE((
                        SELECT json_agg(t.name ORDER BY t.name)
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

        api_json([
            'result' => true,
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => $pages,
            ],
        ]);
    }

    if ($action === 'get') {
        $id = blog_int($payload['id'] ?? 0);
        $slugRaw = blog_str($payload['slug'] ?? '');
        $slug = $slugRaw !== '' ? blog_slugify($slugRaw) : '';
        $publicOnly = blog_str($payload['public_only'] ?? '0') === '1';
        if (!$isAdmin) {
            $publicOnly = true;
        }

        if ($id <= 0 && $slug === '') {
            api_fail('ID_OR_SLUG_REQUIRED');
        }

        if ($id > 0) {
            $rows = blog_db_query(
                $pg,
                "SELECT " . api_localized_select_full($lang) . ",
                        COALESCE((
                            SELECT json_agg(t.name ORDER BY t.name)
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
                 WHERE p.id = :id
                 LIMIT 1",
                [':id' => $id]
            );
        } else {
            $rows = blog_db_query(
                $pg,
                "SELECT " . api_localized_select_full($lang) . ",
                        COALESCE((
                            SELECT json_agg(t.name ORDER BY t.name)
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
                 LIMIT 1",
                [':slug' => $slug]
            );
        }

        if ($rows === []) {
            api_fail('NOT_FOUND');
        }
        $post = $rows[0];
        if ($publicOnly) {
            if (($post['status'] ?? '') !== 'published') {
                api_fail('NOT_FOUND');
            }
        }
        api_json(['result' => true, 'data' => $post]);
    }

    if ($action === 'save') {
        $id = blog_int($payload['id'] ?? 0);
        $title = blog_str($payload['title'] ?? '');
        $slugInput = blog_str($payload['slug'] ?? '');
        $excerpt = blog_str($payload['excerpt'] ?? '');
        $contentHtml = (string)($payload['content_html'] ?? '');
        $coverImageUrl = blog_str($payload['cover_image_url'] ?? '');
        $authorName = blog_str($payload['author_name'] ?? '');
        $status = strtolower(blog_str($payload['status'] ?? 'draft'));
        $publishedAt = blog_str($payload['published_at'] ?? '');
        $schemaType = blog_str($payload['schema_type'] ?? 'NewsArticle');
        $seoTitle = blog_str($payload['seo_title'] ?? '');
        $seoDescription = blog_str($payload['seo_description'] ?? '');
        $seoKeywords = blog_str($payload['seo_keywords'] ?? '');
        $canonicalUrl = blog_str($payload['canonical_url'] ?? '');
        $tags = blog_normalize_tags($payload['tags'] ?? []);

        if ($title === '') {
            api_fail('TITLE_REQUIRED');
        }
        if (!in_array($status, ['draft', 'published'], true)) {
            $status = 'draft';
        }
        if ($schemaType === '') {
            $schemaType = 'NewsArticle';
        }
        if (!in_array($schemaType, ['NewsArticle', 'BlogPosting', 'Article'], true)) {
            $schemaType = 'NewsArticle';
        }
        if ($authorName === '') {
            $authorName = 'Editorial Team';
        }
        if ($publishedAt === '') {
            $publishedAt = null;
        }
        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = date('c');
        }
        if ($excerpt === '') {
            $excerpt = blog_text_excerpt($contentHtml, 260);
        }

        $baseSlug = blog_slugify($slugInput !== '' ? $slugInput : $title);
        $slug = blog_unique_slug($pg, $baseSlug, $id);
        $contentText = blog_text_excerpt($contentHtml, 50000);

        if ($id > 0) {
            blog_db_query(
                $pg,
                "UPDATE {$s}.news_posts
                 SET title = :title,
                     slug = :slug,
                     excerpt = :excerpt,
                     content_html = :content_html,
                     content_text = :content_text,
                     cover_image_url = :cover_image_url,
                     status = :status,
                     author_name = :author_name,
                     published_at = :published_at,
                     seo_title = :seo_title,
                     seo_description = :seo_description,
                     seo_keywords = :seo_keywords,
                     canonical_url = :canonical_url,
                     schema_type = :schema_type,
                     updated_at = NOW()
                 WHERE id = :id",
                [
                    ':id' => $id,
                    ':title' => $title,
                    ':slug' => $slug,
                    ':excerpt' => $excerpt,
                    ':content_html' => $contentHtml,
                    ':content_text' => $contentText,
                    ':cover_image_url' => $coverImageUrl,
                    ':status' => $status,
                    ':author_name' => $authorName,
                    ':published_at' => $publishedAt,
                    ':seo_title' => $seoTitle,
                    ':seo_description' => $seoDescription,
                    ':seo_keywords' => $seoKeywords,
                    ':canonical_url' => $canonicalUrl,
                    ':schema_type' => $schemaType,
                ]
            );
        } else {
            $rows = blog_db_query(
                $pg,
                "INSERT INTO {$s}.news_posts
                    (title, slug, excerpt, content_html, content_text, cover_image_url, status, author_name,
                     published_at, seo_title, seo_description, seo_keywords, canonical_url, schema_type)
                 VALUES
                    (:title, :slug, :excerpt, :content_html, :content_text, :cover_image_url, :status, :author_name,
                     :published_at, :seo_title, :seo_description, :seo_keywords, :canonical_url, :schema_type)
                 RETURNING id",
                [
                    ':title' => $title,
                    ':slug' => $slug,
                    ':excerpt' => $excerpt,
                    ':content_html' => $contentHtml,
                    ':content_text' => $contentText,
                    ':cover_image_url' => $coverImageUrl,
                    ':status' => $status,
                    ':author_name' => $authorName,
                    ':published_at' => $publishedAt,
                    ':seo_title' => $seoTitle,
                    ':seo_description' => $seoDescription,
                    ':seo_keywords' => $seoKeywords,
                    ':canonical_url' => $canonicalUrl,
                    ':schema_type' => $schemaType,
                ]
            );
            $id = (int)($rows[0]['id'] ?? 0);
        }

        if ($id <= 0) {
            api_fail('SAVE_FAILED');
        }

        blog_sync_tags($pg, $id, $tags);
        $post = api_load_post($pg, $id);
        api_json(['result' => true, 'data' => $post]);
    }

    if ($action === 'delete') {
        $id = blog_int($payload['id'] ?? 0);
        if ($id <= 0) {
            api_fail('ID_REQUIRED');
        }
        blog_db_query($pg, "DELETE FROM {$s}.news_posts WHERE id = :id", [':id' => $id]);
        api_json(['result' => true, 'data' => ['id' => $id]]);
    }

    if ($action === 'tags') {
        $rows = blog_db_query(
            $pg,
            "SELECT t.id, t.name, t.slug, COUNT(pt.post_id)::int AS posts
             FROM {$s}.news_tags t
             LEFT JOIN {$s}.news_post_tags pt ON pt.tag_id = t.id
             GROUP BY t.id, t.name, t.slug
             ORDER BY t.name ASC"
        );
        api_json(['result' => true, 'data' => $rows]);
    }

    if ($action === 'seed_demo') {
        $existing = blog_db_query($pg, "SELECT id FROM {$s}.news_posts ORDER BY id ASC LIMIT 1");
        if ($existing !== []) {
            api_json(['result' => true, 'data' => ['message' => 'Demo already exists']]);
        }

        $demo = [
            [
                'title' => 'Proxy Market Update 2026',
                'slug' => 'proxy-market-update-2026',
                'excerpt' => 'Demand for high-quality residential proxies keeps growing across adtech and analytics.',
                'content_html' => '<p>The proxy market in 2026 is driven by privacy regulation, anti-fraud tooling, and global expansion needs.</p><p>Teams increasingly invest in transparent provider scoring and better traffic governance.</p>',
                'cover_image_url' => '',
                'status' => 'published',
                'author_name' => 'Editorial Team',
                'published_at' => date('c', strtotime('-2 days')),
                'seo_title' => 'Proxy Market Update 2026',
                'seo_description' => 'A short market brief on proxy infrastructure and adoption trends in 2026.',
                'seo_keywords' => 'proxy market, residential proxies, infrastructure',
                'canonical_url' => '',
                'schema_type' => 'NewsArticle',
                'tags' => ['market', 'proxies', 'analytics'],
            ],
            [
                'title' => 'How To Choose Rotating Proxies',
                'slug' => 'how-to-choose-rotating-proxies',
                'excerpt' => 'A practical checklist for rotation policy, geo coverage, and session control.',
                'content_html' => '<p>When evaluating rotating proxies, validate sticky session options, ASN diversity, and request success rates per region.</p><p>Always test against real target workloads before rollout.</p>',
                'cover_image_url' => '',
                'status' => 'published',
                'author_name' => 'Editorial Team',
                'published_at' => date('c', strtotime('-1 day')),
                'seo_title' => 'How To Choose Rotating Proxies',
                'seo_description' => 'Checklist for selecting rotating proxy providers and avoiding common pitfalls.',
                'seo_keywords' => 'rotating proxies, sticky sessions, proxy checklist',
                'canonical_url' => '',
                'schema_type' => 'BlogPosting',
                'tags' => ['guides', 'proxies', 'operations'],
            ],
        ];

        $created = 0;
        foreach ($demo as $row) {
            $rows = blog_db_query(
                $pg,
                "INSERT INTO {$s}.news_posts
                    (title, slug, excerpt, content_html, content_text, cover_image_url, status, author_name, published_at,
                     seo_title, seo_description, seo_keywords, canonical_url, schema_type)
                 VALUES
                    (:title, :slug, :excerpt, :content_html, :content_text, :cover_image_url, :status, :author_name, :published_at,
                     :seo_title, :seo_description, :seo_keywords, :canonical_url, :schema_type)
                 RETURNING id",
                [
                    ':title' => $row['title'],
                    ':slug' => $row['slug'],
                    ':excerpt' => $row['excerpt'],
                    ':content_html' => $row['content_html'],
                    ':content_text' => blog_text_excerpt($row['content_html'], 50000),
                    ':cover_image_url' => $row['cover_image_url'],
                    ':status' => $row['status'],
                    ':author_name' => $row['author_name'],
                    ':published_at' => $row['published_at'],
                    ':seo_title' => $row['seo_title'],
                    ':seo_description' => $row['seo_description'],
                    ':seo_keywords' => $row['seo_keywords'],
                    ':canonical_url' => $row['canonical_url'],
                    ':schema_type' => $row['schema_type'],
                ]
            );
            $id = (int)($rows[0]['id'] ?? 0);
            if ($id > 0) {
                blog_sync_tags($pg, $id, blog_normalize_tags($row['tags']));
                $created++;
            }
        }

        api_json(['result' => true, 'data' => ['message' => 'Demo created', 'count' => $created]]);
    }

    api_fail('UNKNOWN_ACTION', ['action' => $action]);
} catch (Throwable $e) {
    api_fail('INTERNAL_ERROR', ['message' => $e->getMessage()]);
}

