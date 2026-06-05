<?php
declare(strict_types=1);

$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}

$users = Sogerien::Users();
$users->init_db_alias($dbAlias);
$users->load_identity_from_token();
if ((int)$users->user_id <= 0) {
    $_GET['next'] = (string)($_SERVER['REQUEST_URI'] ?? '/client/scraper/playground');
    require __DIR__ . '/page_login_form.php';
    Sogerien::markDone();
    return;
}

Sogerien::Page()->title = 'Scraper Playground';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui client-ops-page">
    <style>
        .pm-ops-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:16px}
        .pm-ops-head h1{font-size:28px;margin:0 0 6px}
        .pm-ops-head p{margin:0;color:var(--muted)}
        .pm-ops-head-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .pm-scraper-console{border:1px solid var(--line);border-radius:8px;background:var(--surface);color:var(--text);box-shadow:var(--shadow);overflow:hidden}
        .pm-scraper-console-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;padding:18px 20px;border-bottom:1px solid var(--line)}
        .pm-scraper-console h2{margin:0 0 6px;font-size:24px;color:var(--text)}
        .pm-scraper-console p{margin:0;color:var(--muted)}
        .pm-scraper-modes{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
        .pm-scraper-modes span{border:1px solid color-mix(in srgb,var(--accent) 36%,var(--line));border-radius:6px;padding:5px 9px;font-size:12px;font-weight:700;color:var(--accent);background:color-mix(in srgb,var(--accent) 12%,var(--surface-soft))}
        .pm-scraper-flow{display:grid;grid-template-columns:1.2fr .8fr;gap:0}
        .pm-scraper-pane{padding:18px 20px}
        .pm-scraper-pane + .pm-scraper-pane{border-left:1px solid var(--line);background:color-mix(in srgb,var(--surface-soft) 88%,transparent)}
        .pm-scraper-kv{display:grid;grid-template-columns:120px 1fr;gap:8px 14px;font-size:14px}
        .pm-scraper-kv span{color:var(--muted)}
        .pm-scraper-kv strong{color:var(--text)}
        .pm-scraper-endpoint{display:block;margin-top:12px;padding:10px 12px;border:1px solid rgba(148,163,184,.35);border-radius:6px;background:#0f172a;color:#e2e8f0;white-space:normal;word-break:break-all}
        @media(max-width:800px){.pm-ops-head{display:block}.pm-scraper-console-head{display:block}.pm-scraper-modes{justify-content:flex-start;margin-top:12px}.pm-scraper-flow{grid-template-columns:1fr}.pm-scraper-pane + .pm-scraper-pane{border-left:0;border-top:1px solid var(--line)}}
    </style>
    <div class="pm-ops-head">
        <div>
            <h1>Scraper Playground</h1>
            <p>Test forms for URL scrape, JS render, SERP and AI search gateway.</p>
        </div>
        <div class="pm-ops-head-actions">
            <a class="btn btn-outline-primary" href="/client/all_proxy">Order proxies</a>
        </div>
    </div>
    <section class="pm-scraper-console mb-3">
        <div class="pm-scraper-console-head">
            <div>
                <h2>Scraper playground</h2>
                <p>Gateway test console for scraping modes without exposing provider keys.</p>
            </div>
            <div class="pm-scraper-modes">
                <span>URL scrape</span><span>JS render</span><span>SERP</span><span>AI search</span>
            </div>
        </div>
        <div class="pm-scraper-flow">
            <div class="pm-scraper-pane">
                <div class="pm-scraper-kv">
                    <span>Route</span><strong>Client - ProxyMint gateway - Infatica API</strong>
                    <span>Auth</span><strong>Client API key only</strong>
                    <span>Billing</span><strong>Successful requests</strong>
                </div>
            </div>
            <div class="pm-scraper-pane">
                <div class="small text-muted">Endpoint</div>
                <code class="pm-scraper-endpoint">POST /client/scraper/playground</code>
            </div>
        </div>
    </section>
    <section class="card shadow-sm mb-3"><div class="card-header">Gateway request</div><div class="card-body">
        <?php
        $form = new Forms(['id' => 'scraper_playground_form', 'action' => '/client/scraper/playground', 'method' => 'POST', 'ajax' => false]);
        $form->addSelect('mode', 'Mode', [
            ['value' => 'scrape', 'label' => 'URL scrape'],
            ['value' => 'render', 'label' => 'JS render'],
            ['value' => 'serp', 'label' => 'SERP'],
            ['value' => 'chatgpt', 'label' => 'ChatGPT search'],
            ['value' => 'gemini', 'label' => 'Gemini search'],
            ['value' => 'perplexity', 'label' => 'Perplexity search'],
        ])
            ->addInput('target', 'URL or query', 'text', [], 'https://example.com')
            ->addTextarea('payload', 'Payload JSON', ['rows' => '6'], '{}')
            ->addButton('Run test', ['type' => 'button']);
        $form->render();
        ?>
    </div></section>
    <section class="card shadow-sm"><div class="card-body text-muted">Client keys are not exposed here. Production flow: client - ProxyMint gateway - Infatica scraper API.</div></section>
</main>
<?php
Sogerien::Page()->footer();
