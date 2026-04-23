<?php
declare(strict_types=1);

function news_nav_css(): string
{
    return <<<'CSS'
.pm-nav {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 700;
  height: 64px;
  display: flex;
  align-items: center;
  background: var(--nav-bg, rgba(8, 14, 26, 0.88));
  border-bottom: 1px solid var(--nav-border, rgba(255, 255, 255, 0.08));
  backdrop-filter: blur(22px) saturate(1.3);
  -webkit-backdrop-filter: blur(22px) saturate(1.3);
  transition: box-shadow 0.2s;
}
.pm-nav.scrolled {
  box-shadow: 0 4px 22px rgba(0, 0, 0, 0.16);
}
.nav-inner {
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
  padding: 0 48px;
  display: flex;
  align-items: center;
  gap: 0;
}
.nav-brand {
  display: flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
  flex-shrink: 0;
  margin-right: 32px;
}
.nav-brand-logo {
  width: 28px;
  height: 28px;
  object-fit: contain;
  display: block;
}
.nav-wordmark {
  font-family: var(--font-d, "Segoe UI", Tahoma, sans-serif);
  font-weight: 800;
  font-size: 18px;
  letter-spacing: -0.05em;
  color: var(--nav-text, var(--text, #eef1ff));
}
.nav-wordmark span {
  color: var(--nav-accent, var(--purple, #7b6eff));
}
.nav-links {
  display: flex;
  gap: 2px;
  list-style: none;
  align-items: center;
  flex: 1;
  margin: 0;
  padding: 0;
}
.nav-links a {
  color: var(--nav-text-muted, var(--text-2, rgba(238, 241, 255, 0.7)));
  font-size: 14px;
  font-weight: 600;
  padding: 8px 12px;
  border: 1px solid transparent;
  border-radius: 10px;
  transition: color 0.15s, background 0.15s, border-color 0.15s;
  white-space: nowrap;
}
.nav-links a:hover {
  color: var(--nav-text, var(--text, #eef1ff));
  background: var(--nav-hover-bg, rgba(255, 255, 255, 0.06));
  border-color: var(--nav-hover-border, transparent);
}
.nav-links a.is-active {
  color: var(--nav-active-text, #fff);
  background: var(--nav-active-bg, rgba(123, 110, 255, 0.18));
  border-color: var(--nav-active-border, rgba(123, 110, 255, 0.34));
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
}
.nav-links .has-arrow::after {
  content: 'v';
  font-size: 10px;
  opacity: 0.6;
}
.nav-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}
.nav-login {
  font-size: 14px;
  font-weight: 600;
  color: var(--nav-text-muted, var(--text-2, rgba(238, 241, 255, 0.7)));
  padding: 8px 14px;
  border-radius: 10px;
  transition: color 0.15s, background 0.15s;
}
.nav-login:hover {
  color: var(--nav-text, var(--text, #eef1ff));
  background: var(--nav-hover-bg, rgba(255, 255, 255, 0.06));
}
.pm-topbar-right { display: flex; align-items: center; gap: 10px; }
.pm-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 9px 18px;
  border-radius: 999px;
  border: 1px solid transparent;
  font-size: 13px;
  font-weight: 700;
  line-height: 1;
  text-decoration: none;
}
.pm-btn-outline {
  color: var(--nav-text, var(--text-2, rgba(238, 241, 255, 0.7)));
  border-color: var(--nav-outline-border, var(--line, rgba(255, 255, 255, 0.1)));
  background: var(--nav-outline-bg, transparent);
}
.pm-btn-outline:hover {
  color: var(--nav-text, var(--text, #eef1ff));
  background: var(--nav-hover-bg, rgba(255, 255, 255, 0.06));
}
.pm-btn-primary {
  background: var(--nav-primary-bg, #6b4eff);
  border-color: var(--nav-primary-bg, #6b4eff);
  color: #fff;
}
.pm-btn-primary:hover {
  background: var(--nav-primary-hover-bg, #7b5eff);
  border-color: var(--nav-primary-hover-bg, #7b5eff);
}
.pm-pill-group { display:inline-flex; align-items:center; gap:4px; padding:3px; border-radius:999px; border:1px solid var(--nav-outline-border, var(--line, rgba(255,255,255,0.1))); background:var(--nav-hover-bg, rgba(255,255,255,0.06)); }
.pm-pill-btn { border:0; min-width:84px; padding:7px 12px; border-radius:999px; font-size:12px; font-weight:700; line-height:1.2; color:var(--nav-text-muted, var(--text-2, rgba(238,241,255,0.7))); background:transparent; }
.pm-pill-btn.is-active { color:var(--nav-active-text, #fff); background:var(--nav-active-bg, rgba(123,110,255,0.18)); }
.pm-lang-control { display:inline-flex; align-items:center; gap:8px; padding:3px 3px 3px 10px; border-radius:999px; border:1px solid var(--nav-outline-border, var(--line, rgba(255,255,255,0.1))); background:var(--nav-hover-bg, rgba(255,255,255,0.06)); }
.pm-lang-control-label { font-size:11px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:var(--nav-text-muted, var(--text-2, rgba(238,241,255,0.7))); }
.pm-lang-dropdown .pm-lang-dd-toggle { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; border:1px solid transparent; background:rgba(255,255,255,0.04); color:var(--nav-text, var(--text, #eef1ff)); font-size:12px; font-weight:700; }
.pm-lang-dd-menu { min-width:200px; border-radius:14px; border:1px solid var(--nav-outline-border, var(--line, rgba(255,255,255,0.1))); background:var(--nav-bg, rgba(8,14,26,0.96)); }
.pm-lang-dd-item { display:flex; align-items:center; justify-content:space-between; gap:10px; border-radius:10px; font-size:13px; font-weight:600; }
.pm-lang-dd-item.active,.pm-lang-dd-item:active { color:var(--nav-active-text,#fff); background:var(--nav-active-bg, rgba(123,110,255,0.18)); }
@media (max-width: 1000px) {
  .nav-inner { padding: 0 24px; }
  .nav-links { display: none; }
}
@media (max-width: 700px) {
  .pm-lang-control-label { display: none; }
}
@media (max-width: 560px) {
  .nav-right .pm-btn-outline { display: none; }
  .pm-lang-dropdown .pm-lang-dd-toggle .pm-lang-name { display: none; }
  .pm-pill-btn { min-width: 70px; padding: 7px 10px; }
}
CSS;
}

function news_render_nav(string $currentLang = 'en'): void
{
    $lang = strtolower(trim($currentLang));
    if (!in_array($lang, ['en', 'ru'], true)) {
        $lang = 'en';
    }
    $langLabel = $lang === 'ru' ? 'Русский' : 'English';
    $langCode = strtoupper($lang);
    $blogHref = $lang === 'en' ? '/blog' : ('/blog?lang=' . rawurlencode($lang));
    $langRuHref = '/blog?lang=ru';
    $langEnHref = '/blog?lang=en';
    $langRuActive = $lang === 'ru' ? ' active' : '';
    $langEnActive = $lang === 'en' ? ' active' : '';
    echo <<<HTML
<nav class="pm-nav" id="pm-nav">
  <div class="nav-inner">
    <a href="/" class="nav-brand">
      <img class="nav-brand-logo" src="https://proxymint.com/sogerien/page/img/admin_panel/pm-admin-logo-6-full-vector-no-bg.png" alt="ProxyMint logo">
      <span class="nav-wordmark">proxy<span>mint</span></span>
    </a>
    <ul class="nav-links">
      <li><a href="/#products" class="has-arrow">Proxies</a></li>
      <li><a href="/#products" class="has-arrow">Scrapers</a></li>
      <li><a href="/#use-cases" class="has-arrow">Solutions</a></li>
      <li><a href="/#pricing">Pricing</a></li>
      <li><a href="{$blogHref}" class="is-active">Blog</a></li>
    </ul>
    <div class="nav-right pm-topbar-right">
      <div class="pm-pill-group" role="group" aria-label="Theme switcher"><button type="button" class="pm-pill-btn" data-pm-theme="ice">Ice</button><button type="button" class="pm-pill-btn" data-pm-theme="midnight">Midnight</button></div>
      <div class="pm-lang-control" role="group" aria-label="Язык"><span class="pm-lang-control-label">Язык</span><div class="dropdown pm-lang-dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle pm-lang-dd-toggle" type="button" id="pm_lang_dropdown_toggle" data-bs-toggle="dropdown" aria-expanded="false" title="{$langLabel}"><span class="pm-lang-code" id="pm-lang-code">{$langCode}</span><span class="pm-lang-name" id="pm-lang-name">{$langLabel}</span></button><div class="dropdown-menu p-2 pm-lang-dd-menu" aria-labelledby="pm_lang_dropdown_toggle"><a class="dropdown-item pm-lang-dd-item{$langRuActive}" href="{$langRuHref}" data-pm-lang="ru" title="Русский" aria-label="Русский"><span class="pm-lang-code">RU</span><span class="pm-lang-name">Русский</span></a><a class="dropdown-item pm-lang-dd-item{$langEnActive}" href="{$langEnHref}" data-pm-lang="en" title="English" aria-label="English"><span class="pm-lang-code">EN</span><span class="pm-lang-name">English</span></a></div></div></div>
      <a href="/admin" class="pm-btn pm-btn-primary">Sign in</a>
    </div>
  </div>
</nav>
HTML;
}

function news_nav_script(): string
{
    return <<<'JS'
(function() {
  var nav = document.getElementById('pm-nav');
  var body = document.body;
  var themeButtons = document.querySelectorAll('.pm-pill-btn[data-pm-theme]');
  var storageKey = 'pm-blog-theme';
  var langKey = 'pm-blog-lang';
  var allowedLangs = ['en', 'ru'];

  function normalizeLang(raw) {
    var next = String(raw || 'en').toLowerCase();
    if (allowedLangs.indexOf(next) === -1) {
      return 'en';
    }
    return next;
  }

  function readCookie(name) {
    var escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    var m = document.cookie.match(new RegExp('(?:^|; )' + escaped + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : '';
  }

  function writeLangCookie(lang) {
    try {
      document.cookie = 'sogerien_lang=' + encodeURIComponent(lang) + '; path=/; max-age=31536000; samesite=lax';
    } catch (e) {}
  }

  function applyLangToUrl(urlText, lang) {
    try {
      var u = new URL(urlText, window.location.origin);
      if (u.origin !== window.location.origin) {
        return u.toString();
      }
      if (lang === 'en') {
        u.searchParams.delete('lang');
      } else {
        u.searchParams.set('lang', lang);
      }
      return u.toString();
    } catch (e) {
      return urlText;
    }
  }

  function patchBlogLinks(lang) {
    var links = document.querySelectorAll('a[href]');
    links.forEach(function(a) {
      if (a.hasAttribute('data-pm-lang')) {
        return;
      }
      var href = String(a.getAttribute('href') || '');
      if (!href || href.indexOf('#') === 0 || href.indexOf('javascript:') === 0 || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) {
        return;
      }
      try {
        var u = new URL(href, window.location.origin);
        if (u.origin !== window.location.origin) {
          return;
        }
        var isBlogLink = u.pathname === '/blog' || u.pathname.indexOf('/blog/') === 0;
        if (!isBlogLink) {
          return;
        }
        a.setAttribute('href', applyLangToUrl(u.toString(), lang));
      } catch (e) {}
    });
  }

  function syncLangUi(lang){var langCode=document.getElementById('pm-lang-code');var langName=document.getElementById('pm-lang-name');var isRu=lang==='ru';if(langCode){langCode.textContent=isRu?'RU':'EN';}if(langName){langName.textContent=isRu?'Русский':'English';}var menuItems=document.querySelectorAll('.pm-lang-dd-item[data-pm-lang]');menuItems.forEach(function(item){var isActive=String(item.getAttribute('data-pm-lang')||'')===lang;item.classList.toggle('active',isActive);});}

  function applyTheme(theme, persist) {
    if (!body) return;
    var next = theme === 'ice' ? 'ice' : 'midnight';
    body.classList.remove('pm-theme-ice', 'pm-theme-midnight');
    body.classList.add('pm-theme-' + next);
    themeButtons.forEach(function(btn){var btnTheme=String(btn.getAttribute('data-pm-theme')||'');btn.classList.toggle('is-active',btnTheme===next);});
    if (persist !== false) {
      try {
        localStorage.setItem(storageKey, next);
      } catch (e) {}
    }
  }

  function syncNavShadow() {
    if (nav) {
      nav.classList.toggle('scrolled', window.scrollY > 10);
    }
  }

  themeButtons.forEach(function(btn){btn.addEventListener('click',function(){var next=String(btn.getAttribute('data-pm-theme')||'midnight');applyTheme(next,true);});});
  var currentUrl;
  var urlLang = 'en';
  try {
    currentUrl = new URL(window.location.href);
    urlLang = normalizeLang(currentUrl.searchParams.get('lang'));
  } catch (e) {
    currentUrl = null;
  }

  var storedLang = 'en';
  try {
    storedLang = normalizeLang(localStorage.getItem(langKey) || readCookie('sogerien_lang') || 'en');
  } catch (e) {
    storedLang = normalizeLang(readCookie('sogerien_lang') || 'en');
  }

  if (currentUrl && !currentUrl.searchParams.has('lang') && storedLang !== 'en') {
    currentUrl.searchParams.set('lang', storedLang);
    window.location.replace(currentUrl.toString());
    return;
  }

  var effectiveLang = currentUrl ? urlLang : storedLang;
  try {
    localStorage.setItem(langKey, effectiveLang);
  } catch (e) {}
  writeLangCookie(effectiveLang);
  syncLangUi(effectiveLang);
  patchBlogLinks(effectiveLang);

  var langItems=document.querySelectorAll('.pm-lang-dd-item[data-pm-lang]');
  langItems.forEach(function(item){item.addEventListener('click',function(){var nextLang=normalizeLang(item.getAttribute('data-pm-lang')||'en');
      try {
        localStorage.setItem(langKey, nextLang);
      } catch (e) {}
      writeLangCookie(nextLang);
    });});

  var startTheme = body && body.classList.contains('pm-theme-ice') ? 'ice' : 'midnight';
  try {
    var savedTheme = localStorage.getItem(storageKey);
    if (savedTheme === 'ice' || savedTheme === 'midnight') {
      startTheme = savedTheme;
    }
  } catch (e) {}
  applyTheme(startTheme, false);

  if (nav) {
    window.addEventListener('scroll', syncNavShadow, { passive: true });
    syncNavShadow();
  }
})();
JS;
}





