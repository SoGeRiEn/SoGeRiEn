<?php
declare(strict_types=1);
require_once __DIR__ . '/blog_auth.php';
require_once __DIR__ . '/blog_nav.php';

$blogAddUrl = trim((string)($_SERVER['BLOG_ADD_URL'] ?? '/api/news_add.php'));
$blogPublicUrl = trim((string)($_SERVER['BLOG_PUBLIC_URL'] ?? '/api/news_blog.php'));
$blogEditUrl = trim((string)($_SERVER['BLOG_EDIT_URL'] ?? '/api/news_edit.php'));
$blogApiUrl = trim((string)($_SERVER['BLOG_API_URL'] ?? '/api/news_api.php'));

if ($blogAddUrl === '') { $blogAddUrl = '/api/news_add.php'; }
if ($blogPublicUrl === '') { $blogPublicUrl = '/api/news_blog.php'; }
if ($blogEditUrl === '') { $blogEditUrl = '/api/news_edit.php'; }
if ($blogApiUrl === '') { $blogApiUrl = '/api/news_api.php'; }

news_auth_require_admin_page();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Blog Admin</title>
  <style>
    <?= news_nav_css() ?>
    :root { --bg:#f4f7fc; --panel:#fff; --line:#d9e1ef; --text:#1a2235; --muted:#63708b; --brand:#0d6ad8; --danger:#c62828; }
    :root {
      --nav-bg:rgba(244,247,252,0.94);
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
    .wrap { max-width:1200px; margin:0 auto; padding:90px 18px 18px; }
    .panel { background:var(--panel); border:1px solid var(--line); border-radius:12px; padding:14px; }
    .top { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:12px; }
    input, select, button, a.btn { border:1px solid var(--line); border-radius:8px; padding:9px 10px; font:inherit; }
    input, select { background:#fff; }
    button, a.btn { background:#fff; cursor:pointer; text-decoration:none; color:var(--text); }
    .primary { background:var(--brand); color:#fff; border-color:var(--brand); }
    table { width:100%; border-collapse:collapse; }
    th, td { border-top:1px solid var(--line); padding:10px; text-align:left; vertical-align:top; }
    th { color:var(--muted); font-size:13px; }
    .tags { font-size:12px; color:#365; }
    .pager { margin-top:12px; display:flex; gap:8px; flex-wrap:wrap; }
    .pager a, .pager span { border:1px solid var(--line); border-radius:8px; padding:7px 10px; text-decoration:none; color:var(--text); background:#fff; }
    .pager .active { background:#e8f1ff; border-color:#9ec0ef; }
  </style>
</head>
<body>
  <?php news_render_nav(); ?>
  <div class="wrap">
    <div class="panel">
      <div class="top">
        <a class="btn primary" href="<?= htmlspecialchars($blogAddUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">+ Add News</a>
        <a class="btn" href="<?= htmlspecialchars($blogPublicUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank">Open Blog</a>
        <button id="seedBtn">Create Demo News</button>
        <input id="q" type="text" placeholder="Search..." style="min-width:240px;">
        <input id="tag" type="text" placeholder="Tag slug..." style="min-width:180px;">
        <button id="searchBtn">Search</button>
        <button id="resetBtn">Reset</button>
      </div>

      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Status</th>
            <th>Published</th>
            <th>Tags</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="rows"></tbody>
      </table>
      <div class="pager" id="pager"></div>
    </div>
  </div>

  <script>
    const rowsEl = document.getElementById('rows');
    const pagerEl = document.getElementById('pager');
    const qEl = document.getElementById('q');
    const tagEl = document.getElementById('tag');
    const blogApiUrl = <?= json_encode($blogApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const blogEditUrl = <?= json_encode($blogEditUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let state = { page: 1, q: '', tag: '' };

    function makeEditUrl(id) {
      if (blogEditUrl.endsWith('.php') || blogEditUrl.includes('?')) {
        return blogEditUrl + '?id=' + Number(id || 0);
      }
      return blogEditUrl.replace(/\/+$/, '') + '/' + Number(id || 0);
    }

    function esc(s) {
      return String(s).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    async function api(action, body = {}) {
      const res = await fetch(blogApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...body })
      });
      return res.json();
    }

    async function load() {
      const r = await api('list_admin', { page: state.page, per_page: 15, q: state.q, tag: state.tag });
      if (!r.result) {
        rowsEl.innerHTML = '<tr><td colspan="6">Error: ' + esc(r.error || 'unknown') + '</td></tr>';
        pagerEl.innerHTML = '';
        return;
      }
      const list = Array.isArray(r.data) ? r.data : [];
      rowsEl.innerHTML = '';
      list.forEach((row) => {
        const tr = document.createElement('tr');
        const tags = Array.isArray(row.tags) ? row.tags.join(', ') : '';
        tr.innerHTML = `
          <td>${Number(row.id || 0)}</td>
          <td>${esc(row.title || '')}<br><small>${esc(row.slug || '')}</small></td>
          <td>${esc(row.status || '')}</td>
          <td>${esc(row.published_at || row.created_at || '')}</td>
          <td class="tags">${esc(tags)}</td>
          <td>
            <a class="btn" href="${makeEditUrl(Number(row.id || 0))}">Edit</a>
            <button class="btn" data-del="${Number(row.id || 0)}" style="border-color:#f1b0b0;color:#8b1b1b;">Delete</button>
          </td>
        `;
        rowsEl.appendChild(tr);
      });

      document.querySelectorAll('[data-del]').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const id = Number(btn.getAttribute('data-del'));
          if (!confirm('Delete post #' + id + '?')) return;
          const del = await api('delete', { id });
          if (del.result) load();
          else alert('Delete error: ' + (del.error || 'unknown'));
        });
      });

      const p = r.pagination || { page: 1, pages: 1 };
      pagerEl.innerHTML = '';
      for (let i = 1; i <= Number(p.pages || 1); i++) {
        const el = document.createElement(i === Number(p.page) ? 'span' : 'a');
        el.textContent = String(i);
        if (i === Number(p.page)) {
          el.className = 'active';
        } else {
          el.href = '#';
          el.onclick = (e) => { e.preventDefault(); state.page = i; load(); };
        }
        pagerEl.appendChild(el);
      }
    }

    document.getElementById('searchBtn').onclick = () => {
      state.page = 1;
      state.q = qEl.value.trim();
      state.tag = tagEl.value.trim();
      load();
    };
    document.getElementById('resetBtn').onclick = () => {
      state = { page: 1, q: '', tag: '' };
      qEl.value = '';
      tagEl.value = '';
      load();
    };
    document.getElementById('seedBtn').onclick = async () => {
      const r = await api('seed_demo');
      if (!r.result) {
        alert('Seed error: ' + (r.error || 'unknown'));
        return;
      }
      alert((r.data && r.data.message) ? r.data.message : 'Done');
      load();
    };

    load();
  </script>
  <script><?= news_nav_script() ?></script>
</body>
</html>

