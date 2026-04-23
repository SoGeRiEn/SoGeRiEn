<?php
declare(strict_types=1);
require_once __DIR__ . '/blog_auth.php';
require_once __DIR__ . '/blog_nav.php';
$mode = $mode ?? 'add';
$postId = isset($postId) ? (int)$postId : 0;
$blogAdminUrl = trim((string)($_SERVER['BLOG_ADMIN_URL'] ?? '/api/news_admin.php'));
$blogPublicUrl = trim((string)($_SERVER['BLOG_PUBLIC_URL'] ?? '/api/news_blog.php'));
$blogApiUrl = trim((string)($_SERVER['BLOG_API_URL'] ?? '/api/news_api.php'));
$blogEditUrl = trim((string)($_SERVER['BLOG_EDIT_URL'] ?? '/api/news_edit.php'));
if ($blogAdminUrl === '') { $blogAdminUrl = '/api/news_admin.php'; }
if ($blogPublicUrl === '') { $blogPublicUrl = '/api/news_blog.php'; }
if ($blogApiUrl === '') { $blogApiUrl = '/api/news_api.php'; }
if ($blogEditUrl === '') { $blogEditUrl = '/api/news_edit.php'; }

news_auth_require_admin_page();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $mode === 'edit' ? 'Edit News' : 'Add News' ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.6/tinymce.min.js" referrerpolicy="origin"></script>
  <style>
    <?= news_nav_css() ?>
    :root { --bg:#f5f7fc; --panel:#fff; --line:#d8e0ee; --text:#1a2235; --muted:#62708a; --brand:#0d6ad8; --danger:#c62828; }
    :root {
      --nav-bg:rgba(245,247,252,0.94);
      --nav-border:rgba(26,34,53,0.10);
      --nav-text:#172033;
      --nav-text-muted:rgba(23,32,51,0.72);
      --nav-hover-bg:rgba(123,110,255,0.08);
      --nav-hover-border:rgba(123,110,255,0.14);
      --nav-active-text:#5f46eb;
      --nav-active-bg:rgba(123,110,255,0.14);
      --nav-active-border:rgba(123,110,255,0.22);
      --nav-outline-bg:rgba(255,255,255,0.72);
      --nav-outline-border:rgba(26,34,53,0.12);
      --nav-primary-bg:#6b4eff;
      --nav-primary-hover-bg:#5b3fff;
    }
    * { box-sizing:border-box; }
    body { margin:0; background:var(--bg); color:var(--text); font-family:"Segoe UI", Tahoma, sans-serif; }
    .wrap { max-width:1200px; margin:0 auto; padding:90px 16px 16px; }
    .panel { background:var(--panel); border:1px solid var(--line); border-radius:12px; padding:14px; }
    h1 { margin:0 0 12px; font-size:28px; }
    .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .grid3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }
    .row { margin-bottom:10px; }
    label { display:block; font-size:12px; color:var(--muted); margin-bottom:5px; }
    input, select, textarea { width:100%; border:1px solid var(--line); border-radius:8px; padding:9px 10px; font:inherit; background:#fff; }
    textarea { min-height:90px; resize:vertical; }
    .actions { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; }
    button, a.btn { border:1px solid var(--line); border-radius:8px; padding:10px 12px; font:inherit; text-decoration:none; color:var(--text); background:#fff; cursor:pointer; }
    .primary { background:var(--brand); border-color:var(--brand); color:#fff; }
    .danger { background:var(--danger); border-color:var(--danger); color:#fff; }
    .hint { color:var(--muted); margin-top:10px; font-size:13px; }
    .tags-hint { color:var(--muted); margin-top:4px; font-size:12px; }
    .select2-container { width:100% !important; }
    .select2-container--bootstrap-5 .select2-selection--multiple {
      min-height: 42px;
      border: 1px solid var(--line) !important;
      border-radius: 10px !important;
      background: #fff !important;
      box-shadow: none !important;
      padding: 2px 4px;
    }
    .select2-container--bootstrap-5.select2-container--focus .select2-selection--multiple {
      border-color: #9ec0ef !important;
      box-shadow: 0 0 0 0.2rem rgba(13,106,216,.12) !important;
    }
    .select2-container--bootstrap-5 .select2-selection__choice {
      margin-top: 4px !important;
      margin-right: 4px !important;
      padding: 2px 8px !important;
      border-radius: 999px !important;
      border: 1px solid rgba(13,106,216,.25) !important;
      background: rgba(13,106,216,.12) !important;
      color: var(--text) !important;
      font-weight: 600;
    }
    .select2-container--bootstrap-5 .select2-selection__choice__remove {
      color: var(--muted) !important;
    }
    .select2-container--bootstrap-5 .select2-dropdown {
      border: 1px solid var(--line) !important;
      border-radius: 10px !important;
      box-shadow: 0 8px 28px rgba(16,30,58,.12);
    }
    @media (max-width: 980px) { .grid2, .grid3 { grid-template-columns:1fr; } }
  </style>
</head>
<body>
  <?php news_render_nav(); ?>
  <div class="wrap">
    <div class="panel">
      <h1><?= $mode === 'edit' ? 'Edit News' : 'Add News' ?></h1>
      <input type="hidden" id="id" value="<?= $postId ?>">

      <div class="row">
        <label for="title">Title</label>
        <input id="title" type="text">
      </div>

      <div class="grid3">
        <div class="row">
          <label for="slug">Unique URL slug</label>
          <input id="slug" type="text" placeholder="generated-from-title">
        </div>
        <div class="row">
          <label for="status">Status</label>
          <select id="status">
            <option value="draft">draft</option>
            <option value="published">published</option>
          </select>
        </div>
        <div class="row">
          <label for="published_at">Published at (ISO datetime)</label>
          <input id="published_at" type="text" placeholder="2026-04-01T13:00:00+03:00">
        </div>
      </div>

      <div class="grid2">
        <div class="row">
          <label for="author_name">Author</label>
          <input id="author_name" type="text" placeholder="Editorial Team">
        </div>
        <div class="row">
          <label for="cover_image_url">Cover image URL</label>
          <input id="cover_image_url" type="text" placeholder="https://...">
        </div>
      </div>

      <div class="row">
        <label for="tags">Tags (categories)</label>
        <select id="tags" multiple></select>
        <div class="tags-hint">Type to search or add new tags</div>
      </div>

      <div class="row">
        <label for="excerpt">Excerpt</label>
        <textarea id="excerpt"></textarea>
      </div>

      <div class="row">
        <label for="editor">Content (TinyMCE)</label>
        <textarea id="editor"></textarea>
      </div>

      <div class="row">
        <h3>SEO + Schema.org</h3>
      </div>
      <div class="grid2">
        <div class="row">
          <label for="seo_title">SEO title</label>
          <input id="seo_title" type="text">
        </div>
        <div class="row">
          <label for="canonical_url">Canonical URL</label>
          <input id="canonical_url" type="text" placeholder="https://example.com/news/slug">
        </div>
      </div>
      <div class="grid2">
        <div class="row">
          <label for="seo_keywords">SEO keywords</label>
          <input id="seo_keywords" type="text" placeholder="proxy, privacy, cybersecurity">
        </div>
        <div class="row">
          <label for="schema_type">Schema type</label>
          <select id="schema_type">
            <option value="NewsArticle">NewsArticle</option>
            <option value="BlogPosting">BlogPosting</option>
            <option value="Article">Article</option>
          </select>
        </div>
      </div>
      <div class="row">
        <label for="seo_description">SEO description</label>
        <textarea id="seo_description"></textarea>
      </div>

      <div class="actions">
        <button class="primary" id="saveBtn" type="button">Save</button>
        <button class="danger" id="deleteBtn" type="button">Delete</button>
        <a class="btn" href="<?= htmlspecialchars($blogAdminUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Back to Admin</a>
        <a class="btn" href="<?= htmlspecialchars($blogPublicUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank">Open Blog</a>
      </div>
      <div class="hint" id="hint">Ready.</div>
    </div>
  </div>

  <script>
    const mode = <?= json_encode($mode) ?>;
    const initialId = Number(<?= json_encode($postId) ?> || 0);
    const blogApiUrl = <?= json_encode($blogApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const blogAdminUrl = <?= json_encode($blogAdminUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const blogEditUrl = <?= json_encode($blogEditUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const hintEl = document.getElementById('hint');
    const byId = (id) => document.getElementById(id);
    const hint = (s) => { hintEl.textContent = s; };
    let tagsOptions = [];
    let tagsLoaded = false;

    function makeEditUrl(id) {
      if (blogEditUrl.endsWith('.php') || blogEditUrl.includes('?')) {
        return blogEditUrl + '?id=' + Number(id || 0);
      }
      return blogEditUrl.replace(/\/+$/, '') + '/' + Number(id || 0);
    }

    function normalizeTags(values) {
      const out = [];
      const seen = new Set();
      if (!Array.isArray(values)) return out;
      values.forEach((raw) => {
        const v = String(raw || '').trim();
        if (v === '' || seen.has(v)) return;
        seen.add(v);
        out.push(v);
      });
      return out;
    }

    async function loadTagsOptionsOnce() {
      if (tagsLoaded) return;
      const r = await api('tags', {});
      if (!r.result) {
        throw new Error(String(r.error || 'tags_load_failed'));
      }
      const rows = Array.isArray(r.data) ? r.data : [];
      tagsOptions = rows
        .map((row) => String(row.name || '').trim())
        .filter((v, i, arr) => v !== '' && arr.indexOf(v) === i);
      tagsLoaded = true;
    }

    function renderTagsOptions(selected = []) {
      const select = byId('tags');
      if (!select) return;
      const all = normalizeTags([...(tagsOptions || []), ...normalizeTags(selected)]);
      const selectedSet = new Set(normalizeTags(selected));
      const esc = (value) => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
      const html = all.map((tag) => (
        '<option value="' + esc(tag) + '"'
        + (selectedSet.has(tag) ? ' selected' : '')
        + '>' + esc(tag) + '</option>'
      )).join('');
      select.innerHTML = html;
    }

    function initTagsSelect2() {
      if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) return;
      const $tags = window.jQuery('#tags');
      if ($tags.data('select2')) {
        $tags.select2('destroy');
      }
      $tags.select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'security, proxies, startup',
        tags: true,
        tokenSeparators: [','],
        closeOnSelect: false,
        dropdownAutoWidth: true
      });
    }

    async function setTags(tags) {
      const selected = normalizeTags(Array.isArray(tags) ? tags : []);
      try {
        await loadTagsOptionsOnce();
      } catch (_) {}
      renderTagsOptions(selected);
      initTagsSelect2();
      if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
        window.jQuery('#tags').val(selected).trigger('change');
      }
    }

    function getTags() {
      const select = byId('tags');
      if (!select) return [];
      if (window.jQuery) {
        const raw = window.jQuery(select).val();
        if (Array.isArray(raw)) return normalizeTags(raw);
      }
      const out = [];
      Array.from(select.options).forEach((option) => {
        if (option.selected) out.push(option.value);
      });
      return normalizeTags(out);
    }

    async function api(action, body = {}) {
      const res = await fetch(blogApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...body })
      });
      return res.json();
    }

    async function fill(post) {
      byId('id').value = String(post.id || 0);
      byId('title').value = post.title || '';
      byId('slug').value = post.slug || '';
      byId('status').value = post.status || 'draft';
      byId('published_at').value = post.published_at || '';
      byId('author_name').value = post.author_name || '';
      byId('cover_image_url').value = post.cover_image_url || '';
      await setTags(post.tags);
      byId('excerpt').value = post.excerpt || '';
      byId('seo_title').value = post.seo_title || '';
      byId('seo_description').value = post.seo_description || '';
      byId('seo_keywords').value = post.seo_keywords || '';
      byId('canonical_url').value = post.canonical_url || '';
      byId('schema_type').value = post.schema_type || 'NewsArticle';
      tinymce.get('editor').setContent(post.content_html || '');
    }

    async function loadPost(id) {
      if (id <= 0) return;
      hint('Loading post...');
      const r = await api('get', { id });
      if (!r.result) {
        hint('Load error: ' + (r.error || 'unknown'));
        return;
      }
      await fill(r.data || {});
      hint('Loaded post #' + id);
    }

    async function savePost() {
      hint('Saving...');
      const payload = {
        id: Number(byId('id').value || '0'),
        title: byId('title').value.trim(),
        slug: byId('slug').value.trim(),
        status: byId('status').value,
        published_at: byId('published_at').value.trim(),
        author_name: byId('author_name').value.trim(),
        cover_image_url: byId('cover_image_url').value.trim(),
        tags: getTags(),
        excerpt: byId('excerpt').value,
        content_html: tinymce.get('editor').getContent(),
        seo_title: byId('seo_title').value.trim(),
        seo_description: byId('seo_description').value,
        seo_keywords: byId('seo_keywords').value.trim(),
        canonical_url: byId('canonical_url').value.trim(),
        schema_type: byId('schema_type').value
      };
      const r = await api('save', payload);
      if (!r.result) {
        hint('Save error: ' + (r.error || 'unknown'));
        return;
      }
      await fill(r.data || {});
      hint('Saved');
      if (mode === 'add' && Number(byId('id').value || '0') > 0) {
        const id = Number(byId('id').value);
        history.replaceState(null, '', makeEditUrl(id));
      }
    }

    async function deletePost() {
      const id = Number(byId('id').value || '0');
      if (id <= 0) { hint('Nothing to delete'); return; }
      if (!confirm('Delete post #' + id + '?')) return;
      const r = await api('delete', { id });
      if (!r.result) { hint('Delete error: ' + (r.error || 'unknown')); return; }
      window.location.href = blogAdminUrl;
    }

    tinymce.init({
      selector: '#editor',
      menubar: true,
      plugins: 'lists link table image code autoresize',
      toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link table image | code',
      min_height: 340
    }).then(async () => {
      byId('saveBtn').onclick = savePost;
      byId('deleteBtn').onclick = deletePost;
      if (mode === 'add') {
        byId('deleteBtn').style.display = 'none';
      }
      await setTags([]);
      if (initialId > 0) {
        await loadPost(initialId);
      }
    });
  </script>
  <script><?= news_nav_script() ?></script>
</body>
</html>

