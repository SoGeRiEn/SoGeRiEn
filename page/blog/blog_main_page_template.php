<?php
declare(strict_types=1);

$pmLandingLang = Sogerien::Lang()->get_current_lang();
$pmLandingT = static function (string $key, string $fallback = ''): string {
    $value = Sogerien::Lang()->get($key);
    return $fallback !== '' && $value === $key ? $fallback : $value;
};
$pmLandingH = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$pmLandingTextMap = [];
foreach (Sogerien::Lang()->get_current_lang_map() as $pmLandingKey => $pmLandingValue) {
    if (!is_string($pmLandingKey) || !is_string($pmLandingValue) || strpos($pmLandingKey, 'landing.text.') !== 0) {
        continue;
    }
    $pmLandingSource = Sogerien::Lang()->get($pmLandingKey, 'en');
    if ($pmLandingSource !== '' && $pmLandingValue !== $pmLandingSource) {
        $pmLandingTextMap[$pmLandingSource] = $pmLandingValue;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $pmLandingH($pmLandingLang) ?>" data-pm-default-theme="midnight">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta data-pm-theme-color content="#0a101b">
<title><?= $pmLandingH($pmLandingT('landing.meta.title', 'proxymint - access any public data. power any workflow.')) ?></title>
<meta name="description" content="<?= $pmLandingH($pmLandingT('landing.meta.description', '100M+ residential IPs. 195 countries. 99.4% uptime. The proxy infrastructure that scales with you.')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800;900&family=Figtree:wght@500;600;700;800&family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════
   SHARED TOKENS — never change with theme
   ═══════════════════════════════════════════ */
:root {
  --font-d: 'Archivo', sans-serif;
  --font-b: 'Manrope', sans-serif;
  --font-m: 'Figtree', sans-serif;
  --font-c: 'IBM Plex Mono', monospace;
  --w: 1200px;
  --r-s:8px; --r-m:16px; --r-l:24px; --r-xl:32px; --r-2xl:48px;
}

/* ═══════════════════════════════════════════
   MIDNIGHT THEME — dark mode (default)
   ═══════════════════════════════════════════ */
body.pm-theme-midnight {
  --bg:          #09101d;
  --bg-2:        #0d1525;
  --bg-3:        #111c2e;
  --text:        #eef1ff;
  --text-2:      rgba(238,241,255,0.64);
  --text-3:      rgba(238,241,255,0.34);
  --line:        rgba(255,255,255,0.08);
  --surface:     rgba(12,18,34,0.86);
  --card-bg:     #101826;
  --card-border: rgba(255,255,255,0.08);
  --accent:      #2AD9C8;
  --accent-2:    #7188FF;
  --accent-text: #07111a;
  --purple:      #7B6EFF;
  --purple-2:    #9B8EFF;
  --purple-bg:   rgba(123,110,255,0.09);
  --purple-border:rgba(123,110,255,0.20);
  --success:     #3dd68c;
  --warning:     #f0c95d;
  --dark-hero:   transparent;
  --dark-card:   rgba(255,255,255,0.028);
  --nav-bg:      rgba(8,14,26,0.88);
  --nav-border:  rgba(255,255,255,0.08);
  --shadow-sm:   0 1px 3px rgba(0,0,0,0.28),0 1px 2px rgba(0,0,0,0.18);
  --shadow:      0 4px 16px rgba(0,0,0,0.30),0 2px 6px rgba(0,0,0,0.18);
  --shadow-lg:   0 20px 56px rgba(0,0,0,0.44),0 8px 24px rgba(0,0,0,0.26);
}

/* ═══════════════════════════════════════════
   ICE THEME — soft light mode (Apple-calibrated)
   ═══════════════════════════════════════════ */
body.pm-theme-ice {
  --bg:          #f0f5f3;
  --bg-2:        #e8efed;
  --bg-3:        #dfe9e7;
  --text:        #19292e;
  --text-2:      rgba(25,41,46,0.64);
  --text-3:      rgba(25,41,46,0.40);
  --line:        rgba(17,138,132,0.11);
  --surface:     rgba(236,246,244,0.90);
  --card-bg:     rgba(246,252,250,0.86);
  --card-border: rgba(17,138,132,0.11);
  --accent:      #18C4B5;
  --accent-2:    #6BA8D8;
  --accent-text: #052322;
  --purple:      #6B4EFF;
  --purple-2:    #8B72FF;
  --purple-bg:   rgba(107,78,255,0.07);
  --purple-border:rgba(107,78,255,0.16);
  --success:     #0e9062;
  --warning:     #b57a00;
  --dark-hero:   #0c1422;
  --dark-card:   #111827;
  --nav-bg:      rgba(232,244,241,0.94);
  --nav-border:  rgba(17,138,132,0.14);
  --shadow-sm:   0 1px 3px rgba(20,56,67,0.06),0 1px 2px rgba(20,56,67,0.03);
  --shadow:      0 4px 16px rgba(20,56,67,0.09),0 2px 6px rgba(20,56,67,0.04);
  --shadow-lg:   0 20px 56px rgba(20,56,67,0.12),0 8px 24px rgba(20,56,67,0.06);
}

/* ═══════════════════════════════════════════
   FULL-PAGE BACKGROUND EFFECT SYSTEM
   ═══════════════════════════════════════════ */
.pm-admin-app{position:relative;min-height:100vh;overflow:clip;isolation:isolate;}
.pm-admin-bg,
.pm-admin-aura,
.pm-admin-grid,
.pm-admin-vignette,
.pm-admin-cosmic{position:fixed;inset:0;pointer-events:none;}

/* Base gradient — midnight */
.pm-admin-bg{z-index:0;overflow:hidden;}
.pm-admin-bg::before{
  content:"";position:fixed;inset:0;z-index:0;pointer-events:none;opacity:0.16;
  background-image:
    radial-gradient(circle at 18% 24%,rgba(42,217,200,0.24) 0 1.5px,transparent 2px),
    radial-gradient(circle at 74% 32%,rgba(113,136,255,0.20) 0 1.25px,transparent 1.75px),
    radial-gradient(circle at 54% 72%,rgba(42,217,200,0.16) 0 1px,transparent 1.5px);
  background-size:280px 280px,360px 360px,420px 420px;
  background-position:0 0,120px 40px,40px 180px;
  transition:opacity 0.5s ease;
}
.pm-admin-bg::after{
  content:"";position:fixed;inset:0;z-index:0;pointer-events:none;opacity:0.97;
  background:
    radial-gradient(88% 92% at 0% 14%,rgba(42,217,200,0.12),transparent 46%),
    radial-gradient(84% 112% at 0% 82%,rgba(113,136,255,0.09),transparent 48%),
    radial-gradient(100% 100% at 100% 0%,rgba(107,78,255,0.14) 0%,transparent 60%),
    linear-gradient(125deg,#07101c 0%,#0b1524 30%,#0e1729 58%,#10192c 100%);
  transition:background 0.5s ease,opacity 0.5s ease;
}

/* Midnight overrides */
body.pm-theme-midnight .pm-admin-bg::after{
  opacity:0.97;
  background:
    radial-gradient(88% 92% at 0% 14%,rgba(42,217,200,0.12),transparent 46%),
    radial-gradient(84% 112% at 0% 82%,rgba(113,136,255,0.09),transparent 48%),
    radial-gradient(100% 100% at 100% 0%,rgba(107,78,255,0.14) 0%,transparent 60%),
    linear-gradient(125deg,#07101c 0%,#0b1524 30%,#0e1729 58%,#10192c 100%);
}

/* Ice overrides — soft cyan-tinted white, NOT pure white */
body.pm-theme-ice .pm-admin-bg::before{
  opacity:0.14;mix-blend-mode:normal;
  background-image:
    radial-gradient(circle at 18% 24%,rgba(26,215,198,0.16) 0 1.45px,transparent 1.95px),
    radial-gradient(circle at 74% 32%,rgba(107,164,210,0.18) 0 1.25px,transparent 1.75px),
    radial-gradient(circle at 54% 72%,rgba(15,59,66,0.07) 0 1px,transparent 1.5px);
}
body.pm-theme-ice .pm-admin-bg::after{
  opacity:0.98;
  background:
    radial-gradient(92% 88% at 0% 14%,rgba(26,215,198,0.07),transparent 44%),
    radial-gradient(86% 108% at 0% 80%,rgba(107,164,210,0.09),transparent 46%),
    linear-gradient(112deg,#ebf3f1 0%,#eef4f2 34%,#f1f5f3 68%,#f3f7f5 100%);
}

/* Cosmic canvas layer */
.pm-admin-cosmic{z-index:1;overflow:hidden;}
.pm-admin-cosmic canvas{
  display:block;position:absolute;inset:0;width:100%;height:100%;pointer-events:none;
  opacity:0.86;
  transition:opacity 0.5s ease;
}
body.pm-theme-ice .pm-admin-cosmic canvas{opacity:0.32;}

/* Aura */
.pm-admin-aura{z-index:2;filter:blur(88px);opacity:0.42;transition:opacity 0.5s,filter 0.5s;}
body.pm-theme-ice .pm-admin-aura{opacity:0.22;filter:blur(100px);}
.pm-admin-aura-one{
  position:absolute;top:-140px;left:-110px;width:520px;height:520px;
  background:radial-gradient(circle,rgba(42,217,200,0.20),transparent 68%);
  transition:background 0.5s;
}
body.pm-theme-ice .pm-admin-aura-one{background:radial-gradient(circle,rgba(26,215,198,0.14),transparent 70%);}
.pm-admin-aura-two{
  position:absolute;top:-80px;right:-80px;width:460px;height:460px;
  background:radial-gradient(circle,rgba(107,78,255,0.20),transparent 68%);
  transition:background 0.5s;
}
body.pm-theme-ice .pm-admin-aura-two{background:radial-gradient(circle,rgba(107,164,210,0.18),transparent 70%);}
.pm-admin-aura-three{
  position:absolute;bottom:-100px;right:20%;width:380px;height:380px;
  background:radial-gradient(circle,rgba(113,136,255,0.14),transparent 68%);
  transition:background 0.5s;
}
body.pm-theme-ice .pm-admin-aura-three{background:radial-gradient(circle,rgba(107,164,210,0.12),transparent 68%);}

/* Grid */
.pm-admin-grid{
  z-index:3;opacity:0.10;
  background-image:
    linear-gradient(to right,rgba(255,255,255,0.12) 1px,transparent 1px),
    linear-gradient(to bottom,rgba(255,255,255,0.12) 1px,transparent 1px);
  background-size:64px 64px;
  mask-image:radial-gradient(circle at center,rgba(0,0,0,0.8),transparent 84%);
  -webkit-mask-image:radial-gradient(circle at center,rgba(0,0,0,0.8),transparent 84%);
  transition:opacity 0.5s;
}
body.pm-theme-ice .pm-admin-grid{
  opacity:0.08;
  background-image:
    linear-gradient(to right,rgba(17,138,132,0.18) 1px,transparent 1px),
    linear-gradient(to bottom,rgba(17,138,132,0.18) 1px,transparent 1px);
}

/* Vignette */
.pm-admin-vignette{
  z-index:4;
  background:linear-gradient(180deg,rgba(3,8,16,0.02),rgba(3,8,16,0.08) 70%,rgba(3,8,16,0.14));
  transition:background 0.5s;
}
body.pm-theme-ice .pm-admin-vignette{
  background:linear-gradient(180deg,rgba(240,248,246,0.04),rgba(240,248,246,0) 40%,rgba(10,38,40,0.03));
}

/* Shell */
.pm-admin-shell{position:relative;z-index:5;min-height:100vh;}

/* ═══════════════════════════════════════════
   RESET
   ═══════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{
  font-family:var(--font-b);font-size:16px;color:var(--text);line-height:1.65;
  background:var(--bg);-webkit-font-smoothing:antialiased;overflow-x:hidden;
  transition:color 0.4s ease,background 0.4s ease;
}
img{max-width:100%;display:block;}
a{color:inherit;text-decoration:none;}

/* ═══════════════════════════════════════════
   LAYOUT
   ═══════════════════════════════════════════ */
.wrap{max-width:var(--w);margin:0 auto;padding:0 48px;}
@media(max-width:900px){.wrap{padding:0 24px;}}
@media(max-width:480px){.wrap{padding:0 18px;}}

/* ═══════════════════════════════════════════
   REVEAL ANIMATION
   ═══════════════════════════════════════════ */
.reveal{opacity:0;transform:translateY(18px);transition:opacity 0.65s cubic-bezier(0.2,0.8,0.2,1),transform 0.65s cubic-bezier(0.2,0.8,0.2,1);}
.reveal.visible{opacity:1;transform:none;}
.reveal-d1{transition-delay:0.08s;}.reveal-d2{transition-delay:0.16s;}.reveal-d3{transition-delay:0.24s;}.reveal-d4{transition-delay:0.32s;}

/* ═══════════════════════════════════════════
   TYPOGRAPHY
   ═══════════════════════════════════════════ */
.eyebrow{font-family:var(--font-c);font-size:11px;font-weight:500;letter-spacing:0.14em;text-transform:uppercase;color:var(--purple);margin-bottom:16px;}
body.pm-theme-ice .eyebrow{color:var(--accent);}
.h1{font-family:var(--font-d);font-weight:900;font-size:clamp(36px,5vw,64px);letter-spacing:-0.055em;line-height:1.0;color:#fff;margin-bottom:24px;}
.h2{font-family:var(--font-d);font-weight:800;font-size:clamp(28px,3.5vw,48px);letter-spacing:-0.05em;line-height:1.05;color:var(--text);margin-bottom:14px;transition:color 0.4s;}
.h3{font-family:var(--font-d);font-weight:700;font-size:clamp(20px,2vw,28px);letter-spacing:-0.04em;line-height:1.1;color:var(--text);margin-bottom:10px;transition:color 0.4s;}
.h3-white{color:#fff;}
.lead{font-size:17px;font-weight:500;color:var(--text-2);line-height:1.7;margin-bottom:32px;max-width:560px;transition:color 0.4s;}
.lead-white{color:rgba(255,255,255,0.70);}
.gradient-text{background:linear-gradient(135deg,var(--accent),var(--accent-2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.purple-text{color:var(--purple);}

/* ═══════════════════════════════════════════
   BUTTONS
   ═══════════════════════════════════════════ */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 28px;border-radius:999px;font-family:var(--font-b);font-size:14px;font-weight:700;letter-spacing:-0.01em;cursor:pointer;border:none;transition:transform 0.15s,box-shadow 0.18s,background 0.18s,color 0.18s;white-space:nowrap;}
.btn:hover{transform:translateY(-1px);}
.btn-purple{background:#6B4EFF;color:#fff;box-shadow:0 5px 20px rgba(107,78,255,0.24);}
.btn-purple:hover{background:#7B5EFF;box-shadow:0 10px 30px rgba(107,78,255,0.34);}
.btn-white{background:#fff;color:#6B4EFF;box-shadow:var(--shadow);}
.btn-white:hover{box-shadow:var(--shadow-lg);}
.btn-outline{background:transparent;color:var(--text-2);border:1.5px solid var(--line);transition:border-color 0.2s,color 0.2s,background 0.2s;}
.btn-outline:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-bg);}
.btn-outline-white{background:transparent;color:#fff;border:1.5px solid rgba(255,255,255,0.28);}
.btn-outline-white:hover{background:rgba(255,255,255,0.08);border-color:rgba(255,255,255,0.46);}
body.pm-theme-ice .btn-outline-white{color:var(--accent-text);border-color:rgba(5,35,34,0.26);}
body.pm-theme-ice .btn-outline-white:hover{background:rgba(24,196,181,0.08);}
.btn-ghost-purple{background:transparent;color:var(--purple);border:1.5px solid var(--purple-border);}
.btn-ghost-purple:hover{background:var(--purple-bg);}
.btn-sm{padding:9px 18px;font-size:13px;}
.btn-lg{padding:16px 36px;font-size:16px;}
.btn-icon{display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:600;color:var(--purple);}
body.pm-theme-ice .btn-icon{color:var(--accent);}
.btn-icon svg{transition:transform 0.15s;}
.btn-icon:hover svg{transform:translateX(3px);}

/* ═══════════════════════════════════════════
   LIVE DOT
   ═══════════════════════════════════════════ */
.live-dot{width:7px;height:7px;border-radius:50%;background:var(--success);box-shadow:0 0 6px var(--success);flex-shrink:0;animation:pulse 1.8s ease-in-out infinite;}
@keyframes pulse{0%,100%{opacity:1;box-shadow:0 0 5px var(--success);}50%{opacity:0.7;box-shadow:0 0 12px var(--success);}}

/* ═══════════════════════════════════════════
   APPLE-STYLE THEME TOGGLE SWITCH
   ═══════════════════════════════════════════ */
.theme-switch{
  position:relative;width:40px;height:22px;border-radius:11px;
  background:rgba(255,255,255,0.14);border:1px solid rgba(255,255,255,0.18);
  cursor:pointer;flex-shrink:0;padding:0;outline:none;
  transition:background 0.28s ease,border-color 0.28s ease,box-shadow 0.2s;
  -webkit-appearance:none;appearance:none;
}
.theme-switch:focus-visible{box-shadow:0 0 0 3px rgba(42,217,200,0.36);}
.theme-switch-thumb{
  position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;
  background:rgba(255,255,255,0.88);
  box-shadow:0 1px 4px rgba(0,0,0,0.22),0 1px 1px rgba(0,0,0,0.10);
  transition:transform 0.26s cubic-bezier(0.175,0.885,0.32,1.275),background 0.26s;
  pointer-events:none;
}
body.pm-theme-ice .theme-switch{background:rgba(24,196,181,0.22);border-color:rgba(24,196,181,0.32);}
body.pm-theme-ice .theme-switch-thumb{transform:translateX(18px);background:#fff;}

/* ═══════════════════════════════════════════
   NAV
   ═══════════════════════════════════════════ */
.pm-nav{
  position:fixed;top:0;left:0;right:0;z-index:500;
  height:64px;display:flex;align-items:center;
  background:var(--nav-bg);backdrop-filter:blur(22px) saturate(1.3);-webkit-backdrop-filter:blur(22px) saturate(1.3);
  border-bottom:1px solid var(--nav-border);
  transition:background 0.4s,border-color 0.4s,box-shadow 0.3s;
}
.pm-nav.scrolled{box-shadow:0 4px 22px rgba(0,0,0,0.16);}
body.pm-theme-ice .pm-nav.scrolled{box-shadow:0 4px 22px rgba(20,56,67,0.10);}
.nav-inner{max-width:var(--w);margin:0 auto;padding:0 48px;width:100%;display:flex;align-items:center;gap:0;}
.nav-brand{display:flex;align-items:center;gap:8px;text-decoration:none;flex-shrink:0;margin-right:32px;}
.nav-logo-img{height:32px;width:auto;object-fit:contain;}
.nav-wordmark{font-family:var(--font-d);font-weight:800;font-size:18px;letter-spacing:-0.055em;color:var(--text);transition:color 0.4s;}
.nav-wordmark span{color:var(--purple);}
body.pm-theme-ice .nav-wordmark span{color:var(--accent);}
.nav-links{display:flex;gap:2px;list-style:none;align-items:center;flex:1;}
.nav-links a{color:var(--text-2);font-size:14px;font-weight:500;padding:7px 12px;border-radius:var(--r-s);transition:color 0.15s,background 0.15s;white-space:nowrap;}
.nav-links a:hover{color:var(--text);background:rgba(255,255,255,0.06);}
body.pm-theme-ice .nav-links a:hover{background:rgba(0,0,0,0.04);}
.nav-links .has-arrow::after{content:' ▾';font-size:10px;opacity:0.45;}
.nav-right{display:flex;align-items:center;gap:10px;flex-shrink:0;}
.nav-login{font-size:14px;font-weight:600;color:var(--text-2);padding:7px 14px;border-radius:var(--r-s);transition:color 0.15s;}
.nav-login:hover{color:var(--text);}
.pm-lang-dropdown{position:relative;min-width:170px;}
.pm-lang-dd-toggle{
  width:100%;height:36px;padding:0 12px 0 14px;border-radius:999px;border:1px solid var(--line);
  display:inline-flex;align-items:center;justify-content:space-between;gap:10px;
  background:rgba(255,255,255,0.06);color:var(--text);font-family:var(--font-b);
  font-size:13px;font-weight:700;line-height:1;cursor:pointer;outline:none;
  transition:border-color 0.16s,background 0.16s,box-shadow 0.16s;
}
.pm-lang-dd-toggle:hover,
.pm-lang-dropdown.is-open .pm-lang-dd-toggle{background:rgba(255,255,255,0.09);border-color:color-mix(in srgb,var(--accent) 48%,var(--line));}
.pm-lang-dd-toggle:focus-visible{box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 24%,transparent);}
.pm-lang-current{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pm-lang-chevron{width:8px;height:8px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg) translateY(-2px);opacity:.72;transition:transform .16s;}
.pm-lang-dropdown.is-open .pm-lang-chevron{transform:rotate(225deg) translateY(-1px);}
.pm-lang-dd-menu{
  position:absolute;top:calc(100% + 8px);left:0;right:0;z-index:700;display:none;padding:6px;
  border-radius:16px;border:1px solid color-mix(in srgb,var(--line) 82%,var(--accent));
  background:rgba(8,14,26,0.97);box-shadow:0 18px 42px rgba(0,0,0,0.36);
  backdrop-filter:blur(18px) saturate(1.2);-webkit-backdrop-filter:blur(18px) saturate(1.2);
}
.pm-lang-dropdown.is-open .pm-lang-dd-menu{display:block;}
.pm-lang-dd-item{
  width:100%;border:0;border-radius:11px;padding:10px 11px;display:flex;align-items:center;justify-content:space-between;gap:12px;
  background:transparent;color:var(--text-2);font-family:var(--font-b);font-size:13px;font-weight:700;text-align:left;cursor:pointer;
}
.pm-lang-dd-item:hover{background:rgba(255,255,255,0.07);color:var(--text);}
.pm-lang-dd-item.is-active{background:rgba(123,110,255,0.18);color:#fff;}
.pm-lang-code{font-family:var(--font-c);font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:var(--accent);}
body.pm-theme-ice .pm-lang-dd-toggle{background:rgba(5,35,34,0.05);}
body.pm-theme-ice .pm-lang-dd-toggle:hover,
body.pm-theme-ice .pm-lang-dropdown.is-open .pm-lang-dd-toggle{background:rgba(5,35,34,0.08);}
body.pm-theme-ice .pm-lang-dd-menu{background:rgba(246,252,250,0.97);box-shadow:0 18px 42px rgba(20,56,67,0.14);}
body.pm-theme-ice .pm-lang-dd-item.is-active{background:rgba(107,78,255,0.12);color:var(--text);}
@media(max-width:1000px){.nav-links{display:none;}.nav-inner{padding:0 24px;}}
@media(max-width:700px){.pm-lang-dropdown{display:none;}}
@media(max-width:540px){.nav-right .btn-outline{display:none;}}

/* ═══════════════════════════════════════════
   HERO
   ═══════════════════════════════════════════ */
.pm-hero{padding-top:64px;background:transparent;}
.hero-card{
  margin:0 48px;
  border-radius:0 0 var(--r-2xl) var(--r-2xl);
  background:rgba(255,255,255,0.028);
  border:1px solid rgba(255,255,255,0.07);border-top:none;
  position:relative;overflow:hidden;min-height:520px;
  backdrop-filter:blur(24px) saturate(1.2);-webkit-backdrop-filter:blur(24px) saturate(1.2);
  box-shadow:0 28px 64px rgba(0,0,0,0.36),inset 0 0 0 0.5px rgba(255,255,255,0.04);
  transition:background 0.4s,border-color 0.4s;
}
body.pm-theme-ice .hero-card{
  background:var(--dark-hero);
  border-color:rgba(255,255,255,0.08);
  backdrop-filter:none;-webkit-backdrop-filter:none;
  box-shadow:0 28px 64px rgba(12,40,52,0.28),0 8px 20px rgba(12,40,52,0.14);
}
/* Hero subtle glow overlay */
.hero-card::before{
  content:'';position:absolute;inset:0;z-index:0;pointer-events:none;
  background:
    radial-gradient(72% 56% at 18% 26%,rgba(42,217,200,0.10),transparent 60%),
    radial-gradient(58% 50% at 80% 72%,rgba(107,78,255,0.12),transparent 60%);
}
/* Hero shimmer top line */
.hero-card::after{
  content:'';position:absolute;top:0;left:12%;right:12%;height:1px;z-index:0;
  background:linear-gradient(90deg,transparent,rgba(42,217,200,0.5),rgba(113,136,255,0.4),transparent);
  opacity:0.55;pointer-events:none;
}
/* Dot pattern overlay */
.hero-dots{
  position:absolute;inset:0;z-index:0;pointer-events:none;opacity:0.14;
  background-image:
    radial-gradient(circle at 18% 24%,rgba(42,217,200,0.22) 0 1.5px,transparent 2px),
    radial-gradient(circle at 74% 32%,rgba(113,136,255,0.20) 0 1.25px,transparent 1.75px),
    radial-gradient(circle at 54% 72%,rgba(42,217,200,0.16) 0 1px,transparent 1.5px);
  background-size:280px 280px,360px 360px,420px 420px;
  background-position:0 0,120px 40px,40px 180px;
}
.hero-card-inner{
  position:relative;z-index:2;
  max-width:var(--w);margin:0 auto;padding:80px 72px;
  display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center;
}
.hero-badge{
  display:inline-flex;align-items:center;gap:8px;padding:6px 14px;
  border-radius:999px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.13);
  font-family:var(--font-c);font-size:11px;letter-spacing:0.10em;text-transform:uppercase;color:rgba(255,255,255,0.78);
  margin-bottom:28px;animation:badge-in 0.8s ease both;
}
.hero-badge .dot-red{width:7px;height:7px;border-radius:50%;background:#ef4444;box-shadow:0 0 5px #ef4444;flex-shrink:0;}
@keyframes badge-in{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:none;}}
.hero-h1{animation:hl-in 0.9s 0.08s ease both;}
@keyframes hl-in{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:none;}}
.hero-sub{font-size:17px;font-weight:500;color:rgba(255,255,255,0.66);max-width:480px;line-height:1.7;margin-bottom:36px;animation:sub-in 0.9s 0.16s ease both;}
@keyframes sub-in{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:none;}}
.hero-form{display:flex;gap:10px;margin-bottom:20px;max-width:460px;animation:cta-in 0.9s 0.24s ease both;}
@keyframes cta-in{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:none;}}
.hero-input{
  flex:1;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.16);
  border-radius:999px;padding:14px 22px;font-family:var(--font-b);
  font-size:14px;font-weight:500;color:#fff;outline:none;
  transition:border-color 0.2s,background 0.2s;
}
.hero-input::placeholder{color:rgba(255,255,255,0.34);}
.hero-input:focus{border-color:rgba(107,78,255,0.5);background:rgba(255,255,255,0.10);}
.hero-book{display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:600;color:rgba(255,255,255,0.64);transition:color 0.15s,gap 0.15s;}
.hero-book:hover{color:#fff;gap:9px;}
.hero-status{display:flex;align-items:center;gap:7px;font-family:var(--font-c);font-size:11px;letter-spacing:0.08em;color:rgba(255,255,255,0.44);margin-top:18px;animation:sub-in 0.9s 0.32s ease both;}

/* Hero visual */
.hero-visual{position:relative;z-index:2;animation:vis-in 1.1s 0.18s ease both;}
@keyframes vis-in{from{opacity:0;transform:translateY(24px) scale(0.97);}to{opacity:1;transform:none;}}
.hero-visual-inner{
  background:rgba(255,255,255,0.038);border:1px solid rgba(255,255,255,0.09);
  border-radius:var(--r-xl);overflow:hidden;
  box-shadow:0 32px 72px rgba(0,0,0,0.44),inset 0 1px 0 rgba(255,255,255,0.07);
  backdrop-filter:blur(12px);
}
.hv-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.028);}
.hv-label{font-family:var(--font-c);font-size:9px;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.32);}
.hv-big-num{font-family:var(--font-m);font-weight:800;font-size:clamp(40px,5vw,64px);letter-spacing:-0.055em;color:#fff;line-height:1;padding:24px 24px 0;}
.hv-big-sub{font-family:var(--font-c);font-size:10px;letter-spacing:0.10em;text-transform:uppercase;color:rgba(255,255,255,0.36);padding:6px 24px 20px;}
.hv-chart{padding:0 24px 20px;}
.hv-chart svg{width:100%;height:80px;}
.hv-stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;border-top:1px solid rgba(255,255,255,0.07);background:rgba(255,255,255,0.07);}
.hv-stat{background:rgba(255,255,255,0.028);padding:16px 18px;}
.hv-stat-val{font-family:var(--font-m);font-weight:800;font-size:20px;letter-spacing:-0.05em;color:#fff;margin-bottom:2px;}
.hv-stat-val.acc{color:#2AD9C8;}.hv-stat-val.pur{color:#9B8EFF;}
.hv-stat-lbl{font-family:var(--font-c);font-size:8px;letter-spacing:0.10em;text-transform:uppercase;color:rgba(255,255,255,0.32);}

/* Floating badges */
.hv-float{
  position:absolute;background:rgba(8,13,26,0.90);border:1px solid rgba(255,255,255,0.11);
  border-radius:14px;padding:10px 14px;backdrop-filter:blur(16px);
  box-shadow:0 10px 28px rgba(0,0,0,0.36);
}
.hv-float-val{font-family:var(--font-m);font-weight:800;font-size:20px;letter-spacing:-0.055em;color:#2AD9C8;line-height:1;}
.hv-float-lbl{font-family:var(--font-c);font-size:8px;letter-spacing:0.10em;text-transform:uppercase;color:rgba(255,255,255,0.32);margin-top:3px;}
.hv-float-tl{top:-18px;left:32px;animation:fl1 4s ease-in-out infinite;}
.hv-float-br{bottom:-18px;right:32px;animation:fl2 4.5s ease-in-out infinite 0.8s;}
.hv-float-val.pur2{color:#9B8EFF;}
@keyframes fl1{0%,100%{transform:translateY(0);}50%{transform:translateY(-6px);}}
@keyframes fl2{0%,100%{transform:translateY(0);}50%{transform:translateY(6px);}}

@media(max-width:960px){.hero-card-inner{grid-template-columns:1fr;gap:44px;padding:60px 36px;}.hero-card{margin:0 24px;}.hv-float{display:none;}}
@media(max-width:540px){.hero-card{margin:0 16px;}.hero-card-inner{padding:48px 24px;}.hero-form{flex-direction:column;}.hero-form .btn-purple{width:100%;}}

/* ═══════════════════════════════════════════
   TRUST STRIP
   ═══════════════════════════════════════════ */
.pm-trust{padding:44px 0;background:var(--bg);border-bottom:1px solid var(--line);transition:background 0.4s,border-color 0.4s;}
.trust-label{text-align:center;font-family:var(--font-c);font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-3);margin-bottom:28px;}
.trust-logos{display:flex;align-items:center;justify-content:center;gap:48px;flex-wrap:wrap;}
.trust-logo{font-family:var(--font-d);font-weight:700;font-size:15px;color:var(--text-3);letter-spacing:-0.02em;opacity:0.5;transition:opacity 0.2s;}
.trust-logo:hover{opacity:0.9;}

/* ═══════════════════════════════════════════
   SECTION WRAPPER
   ═══════════════════════════════════════════ */
.section{padding:96px 0;}
.section-bg2{background:var(--bg-2);}
.section-bg3{background:var(--bg-3);}
.section-center{text-align:center;}
.section-center .h2,.section-center .lead{margin-left:auto;margin-right:auto;}
.section-hdr{max-width:640px;margin-bottom:56px;}
.section-hdr.center{margin-left:auto;margin-right:auto;text-align:center;}

/* ═══════════════════════════════════════════
   VALUE PROP — TABS + 2-COL CARDS
   ═══════════════════════════════════════════ */
.tabs-row{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:40px;}
.tab-pill{padding:8px 18px;border-radius:999px;font-size:13px;font-weight:600;color:var(--text-2);background:transparent;border:1.5px solid var(--line);cursor:pointer;transition:all 0.18s;font-family:var(--font-b);}
.tab-pill.active{background:var(--purple);color:#fff;border-color:var(--purple);box-shadow:0 3px 12px rgba(107,78,255,0.22);}
.tab-pill:not(.active):hover{border-color:var(--purple);color:var(--purple);}
body.pm-theme-ice .tab-pill.active{background:var(--accent);color:var(--accent-text);border-color:var(--accent);box-shadow:0 3px 12px rgba(24,196,181,0.22);}
body.pm-theme-ice .tab-pill:not(.active):hover{border-color:var(--accent);color:var(--accent);}

.val-grid{display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;}
.val-dark-card{
  background:var(--dark-card);border-radius:var(--r-xl);overflow:hidden;
  position:relative;min-height:400px;box-shadow:var(--shadow-lg);
  border:1px solid rgba(255,255,255,0.07);
  transition:background 0.4s;
}
body.pm-theme-ice .val-dark-card{background:#111827;border-color:rgba(255,255,255,0.07);}
.val-dark-bg{
  position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(70% 60% at 60% 40%,rgba(107,78,255,0.22),transparent 70%),
             radial-gradient(50% 50% at 20% 80%,rgba(42,217,200,0.14),transparent 60%);
}
.val-dark-inner{position:relative;z-index:1;padding:36px;}
.val-dark-eyebrow{font-family:var(--font-c);font-size:10px;letter-spacing:0.14em;text-transform:uppercase;color:rgba(255,255,255,0.40);margin-bottom:16px;}
.val-dark-h{font-family:var(--font-d);font-weight:800;font-size:clamp(20px,2.2vw,28px);letter-spacing:-0.04em;line-height:1.1;color:#fff;margin-bottom:14px;}
.val-dark-p{font-size:14px;color:rgba(255,255,255,0.52);line-height:1.7;margin-bottom:28px;max-width:360px;}
.val-dark-chip{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.11);font-family:var(--font-c);font-size:9px;letter-spacing:0.10em;text-transform:uppercase;color:rgba(255,255,255,0.46);margin-bottom:6px;}
.val-dark-illustration{
  margin-top:28px;background:rgba(0,0,0,0.36);border:1px solid rgba(255,255,255,0.07);
  border-radius:var(--r-m);padding:18px;font-family:var(--font-c);font-size:11px;color:rgba(255,255,255,0.56);line-height:1.8;
}
.val-dark-illustration .kw{color:#c792ea;}.val-dark-illustration .str{color:#2AD9C8;}.val-dark-illustration .key{color:#9B8EFF;}

.val-right-stack{display:flex;flex-direction:column;gap:16px;}
.val-light-card{
  background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--r-xl);
  padding:28px;transition:border-color 0.2s,transform 0.2s,box-shadow 0.2s,background 0.4s;
}
.val-light-card:hover{border-color:var(--purple-border);transform:translateY(-2px);box-shadow:var(--shadow);}
body.pm-theme-ice .val-light-card:hover{border-color:rgba(24,196,181,0.28);}
.val-card-icon{width:44px;height:44px;border-radius:var(--r-m);margin-bottom:16px;background:var(--purple-bg);border:1px solid var(--purple-border);display:flex;align-items:center;justify-content:center;transition:background 0.4s,border-color 0.4s;}
.val-card-icon svg{width:22px;height:22px;stroke:var(--purple);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
body.pm-theme-ice .val-card-icon svg{stroke:var(--accent);}
.val-card-h{font-family:var(--font-d);font-weight:700;font-size:17px;letter-spacing:-0.03em;margin-bottom:6px;color:var(--text);}
.val-card-p{font-size:13px;color:var(--text-2);line-height:1.6;}
.val-card-metric{font-family:var(--font-m);font-weight:800;font-size:32px;letter-spacing:-0.055em;color:var(--purple);line-height:1;margin-top:12px;}
body.pm-theme-ice .val-card-metric{color:var(--accent);}
.val-card-metric-lbl{font-family:var(--font-c);font-size:9px;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-3);}
@media(max-width:900px){.val-grid{grid-template-columns:1fr;}}

/* ═══════════════════════════════════════════
   PRODUCT ROWS
   ═══════════════════════════════════════════ */
.product-sub-nav{display:flex;gap:0;border-bottom:1.5px solid var(--line);margin-bottom:56px;transition:border-color 0.4s;}
.psn-item{padding:12px 20px;font-size:14px;font-weight:600;color:var(--text-2);border-bottom:2px solid transparent;margin-bottom:-1.5px;cursor:pointer;transition:color 0.15s,border-color 0.15s;}
.psn-item.active{color:var(--purple);border-bottom-color:var(--purple);}
body.pm-theme-ice .psn-item.active{color:var(--accent);border-bottom-color:var(--accent);}
.psn-item:hover:not(.active){color:var(--text);}

.feat-row{display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center;margin-bottom:80px;}
.feat-row.rev{direction:rtl;}
.feat-row.rev>*{direction:ltr;}
.feat-row:last-child{margin-bottom:0;}
.feat-icon-wrap{width:52px;height:52px;border-radius:var(--r-m);background:var(--purple-bg);border:1px solid var(--purple-border);display:flex;align-items:center;justify-content:center;margin-bottom:20px;transition:background 0.4s,border-color 0.4s;}
.feat-icon-wrap svg{width:26px;height:26px;stroke:var(--purple);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
body.pm-theme-ice .feat-icon-wrap svg{stroke:var(--accent);}
body.pm-theme-ice .feat-icon-wrap{background:rgba(24,196,181,0.08);border-color:rgba(24,196,181,0.20);}
.feat-copy .h3{color:var(--text);margin-bottom:12px;}
.feat-copy .lead{font-size:15px;margin-bottom:24px;}
.feat-checks{list-style:none;display:flex;flex-direction:column;gap:10px;margin-bottom:28px;}
.feat-checks li{display:flex;align-items:flex-start;gap:10px;font-size:14px;color:var(--text-2);font-weight:500;line-height:1.6;}
.feat-checks li::before{content:'';flex-shrink:0;width:18px;height:18px;border-radius:50%;margin-top:1px;background:var(--purple-bg) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' fill='none' stroke='%236B4EFF' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='2,6 5,9 10,3'/%3E%3C/svg%3E") center/10px no-repeat;border:1.5px solid var(--purple-border);}
body.pm-theme-ice .feat-checks li::before{background:rgba(24,196,181,0.08) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' fill='none' stroke='%2318C4B5' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='2,6 5,9 10,3'/%3E%3C/svg%3E") center/10px no-repeat;border-color:rgba(24,196,181,0.22);}

.feat-visual{
  background:var(--bg-2);border:1px solid var(--line);border-radius:var(--r-xl);
  overflow:hidden;box-shadow:var(--shadow-lg);
  transition:transform 0.3s,box-shadow 0.3s,background 0.4s,border-color 0.4s;
}
.feat-visual:hover{transform:translateY(-3px);box-shadow:0 28px 72px rgba(0,0,0,0.16);}
body.pm-theme-midnight .feat-visual:hover{box-shadow:0 28px 72px rgba(0,0,0,0.44);}
.fv-topbar{display:flex;align-items:center;gap:8px;padding:12px 18px;border-bottom:1px solid var(--line);background:var(--bg);transition:background 0.4s,border-color 0.4s;}
.fv-dots{display:flex;gap:5px;}
.fv-dot{width:10px;height:10px;border-radius:50%;}
.fv-dot:nth-child(1){background:#ff5f57;}.fv-dot:nth-child(2){background:#ffbd2e;}.fv-dot:nth-child(3){background:#28ca42;}
.fv-title{font-family:var(--font-c);font-size:10px;color:var(--text-3);letter-spacing:0.06em;margin-left:8px;}
.fv-body{padding:20px;}
.fv-kpi-row{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:14px;}
.fv-kpi{background:var(--bg);border:1px solid var(--line);border-radius:var(--r-m);padding:14px 16px;transition:background 0.4s,border-color 0.4s;}
.fv-kpi-lbl{font-family:var(--font-c);font-size:8px;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-3);margin-bottom:6px;}
.fv-kpi-val{font-family:var(--font-m);font-weight:800;font-size:22px;letter-spacing:-0.055em;color:var(--purple);}
.fv-kpi-val.acc{color:var(--accent);}
body.pm-theme-ice .fv-kpi-val{color:var(--purple);}
.fv-kpi-delta{font-family:var(--font-c);font-size:8px;color:var(--success);margin-top:2px;}
.fv-chart{background:var(--bg);border:1px solid var(--line);border-radius:var(--r-m);padding:14px 16px;margin-bottom:12px;transition:background 0.4s,border-color 0.4s;}
.fv-chart-lbl{font-family:var(--font-c);font-size:8px;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-3);margin-bottom:10px;}
.fv-chart svg{width:100%;height:64px;}
.fv-table{background:var(--bg);border:1px solid var(--line);border-radius:var(--r-m);overflow:hidden;transition:background 0.4s,border-color 0.4s;}
.fv-tr{display:grid;grid-template-columns:2fr 1fr 1fr;padding:9px 14px;border-bottom:1px solid color-mix(in srgb,var(--line) 60%,transparent);align-items:center;}
.fv-tr:last-child{border-bottom:none;}
.fv-th{font-family:var(--font-c);font-size:8px;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-3);}
.fv-td{font-size:11px;font-weight:600;color:var(--text);}
.fv-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:999px;font-family:var(--font-c);font-size:8px;letter-spacing:0.06em;font-weight:500;}
.fv-badge.pur{background:var(--purple-bg);color:var(--purple);border:1px solid var(--purple-border);}
.fv-badge.acc{background:rgba(42,217,200,0.09);color:#0d9488;border:1px solid rgba(42,217,200,0.22);}
.fv-badge.ok{background:rgba(34,197,94,0.09);color:#16a34a;border:1px solid rgba(34,197,94,0.22);}
.fv-status{display:flex;align-items:center;gap:4px;font-family:var(--font-c);font-size:9px;}
.fv-status .dot{width:5px;height:5px;border-radius:50%;background:currentColor;}
.fv-status.on{color:var(--success);}.fv-status.warn{color:var(--warning);}
.fv-code{background:#0d1117;border-radius:var(--r-m);padding:20px;font-family:var(--font-c);font-size:12px;line-height:1.8;color:rgba(255,255,255,0.66);overflow:hidden;}
.fv-code .kw{color:#c792ea;}.fv-code .str{color:#4ec9b0;}.fv-code .fn{color:#82aaff;}.fv-code .cmt{color:rgba(255,255,255,0.26);font-style:italic;}.fv-code .num{color:#f78c6c;}.fv-code .op{color:rgba(255,255,255,0.42);}

@media(max-width:900px){.feat-row,.feat-row.rev{grid-template-columns:1fr;direction:ltr;gap:36px;margin-bottom:60px;}}

/* ═══════════════════════════════════════════
   DARK WORKFLOW BAND
   ═══════════════════════════════════════════ */
.pm-dark-band{
  margin:0 48px;border-radius:var(--r-2xl);
  background:var(--dark-card);
  position:relative;overflow:hidden;padding:64px 72px;
  border:1px solid rgba(255,255,255,0.06);
  transition:background 0.4s;
}
body.pm-theme-ice .pm-dark-band{background:var(--dark-card);border-color:rgba(255,255,255,0.07);}
.pm-dark-band-bg{
  position:absolute;inset:0;pointer-events:none;
  background:
    radial-gradient(60% 80% at 80% 50%,rgba(107,78,255,0.20),transparent 70%),
    radial-gradient(40% 50% at 20% 30%,rgba(42,217,200,0.12),transparent 60%);
}
.db-inner{position:relative;z-index:1;display:grid;grid-template-columns:300px 1fr;gap:64px;align-items:start;}
.db-left .eyebrow{color:var(--accent);}
.db-left .h2{color:#fff;margin-bottom:16px;}
.db-menu{list-style:none;display:flex;flex-direction:column;gap:4px;margin-top:28px;}
.db-menu li{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:var(--r-m);font-size:14px;font-weight:600;color:rgba(255,255,255,0.46);cursor:default;transition:all 0.18s;}
.db-menu li.active,.db-menu li:hover{color:#fff;background:rgba(255,255,255,0.07);}
.db-menu li::before{content:'';width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,0.18);flex-shrink:0;}
.db-menu li.active::before{background:var(--accent);box-shadow:0 0 7px var(--accent);}
.db-right{
  background:rgba(255,255,255,0.038);border:1px solid rgba(255,255,255,0.07);
  border-radius:var(--r-xl);overflow:hidden;
}
.db-code-header{display:flex;align-items:center;gap:8px;padding:12px 18px;border-bottom:1px solid rgba(255,255,255,0.07);background:rgba(255,255,255,0.028);}
.db-code-dots{display:flex;gap:5px;}
.db-code-dot{width:10px;height:10px;border-radius:50%;}
.db-code-dot:nth-child(1){background:#ff5f57;}.db-code-dot:nth-child(2){background:#ffbd2e;}.db-code-dot:nth-child(3){background:#28ca42;}
.db-code-tab{font-family:var(--font-c);font-size:10px;letter-spacing:0.08em;color:rgba(255,255,255,0.44);margin-left:10px;padding:3px 10px;border-radius:6px;background:rgba(255,255,255,0.06);}
.db-code-tab.active{background:var(--purple);color:#fff;}
.db-code-body{padding:24px 28px;}
.db-pre{font-family:var(--font-c);font-size:13px;line-height:1.85;color:rgba(255,255,255,0.68);}
.db-pre .kw{color:#c792ea;}.db-pre .str{color:#4ec9b0;}.db-pre .fn{color:#82aaff;}.db-pre .cmt{color:rgba(255,255,255,0.26);font-style:italic;}.db-pre .num{color:#f78c6c;}.db-pre .op{color:rgba(255,255,255,0.42);}
@media(max-width:960px){.pm-dark-band{margin:0 24px;padding:48px 32px;}.db-inner{grid-template-columns:1fr;gap:36px;}}
@media(max-width:540px){.pm-dark-band{margin:0 16px;padding:36px 24px;}}

/* ═══════════════════════════════════════════
   STATS / ONE VIEW
   ═══════════════════════════════════════════ */
.stats-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;}
.stat-card{
  background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--r-xl);
  padding:28px;overflow:hidden;position:relative;
  transition:transform 0.2s,box-shadow 0.2s,border-color 0.2s,background 0.4s;
}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow);border-color:var(--purple-border);}
body.pm-theme-ice .stat-card:hover{border-color:rgba(24,196,181,0.24);}
.stat-card-lbl{font-family:var(--font-c);font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-3);margin-bottom:20px;}
.ip-list{display:flex;flex-direction:column;gap:7px;}
.ip-row{display:flex;align-items:center;gap:10px;padding:8px 12px;background:var(--bg);border:1px solid var(--line);border-radius:var(--r-s);transition:background 0.4s,border-color 0.4s;}
.ip-flag{font-size:14px;flex-shrink:0;}
.ip-addr{font-family:var(--font-c);font-size:11px;color:var(--text-2);flex:1;}
.ip-badge{font-family:var(--font-c);font-size:8px;padding:2px 8px;border-radius:999px;letter-spacing:0.06em;}
.ip-badge.ok{background:rgba(34,197,94,0.09);color:#16a34a;border:1px solid rgba(34,197,94,0.20);}
.ip-badge.act{background:var(--purple-bg);color:var(--purple);border:1px solid var(--purple-border);}
.bar-chart{display:flex;align-items:flex-end;gap:6px;height:80px;}
.bar-col{flex:1;border-radius:4px 4px 0 0;background:linear-gradient(180deg,var(--purple),color-mix(in srgb,var(--purple) 60%,transparent));transition:opacity 0.2s;}
.bar-col.dim{background:color-mix(in srgb,var(--purple) 24%,var(--bg-3));}
.bar-labels{display:flex;gap:6px;margin-top:6px;}
.bar-lbl{flex:1;text-align:center;font-family:var(--font-c);font-size:8px;letter-spacing:0.06em;color:var(--text-3);}
@media(max-width:900px){.stats-cards{grid-template-columns:1fr;}}

/* ═══════════════════════════════════════════
   NETWORK MAP METRICS
   ═══════════════════════════════════════════ */
.pm-network{background:var(--bg);}
.network-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:var(--line);border:1px solid var(--line);border-radius:var(--r-xl);overflow:hidden;transition:background 0.4s,border-color 0.4s;}
.nm-cell{background:var(--bg);padding:40px 28px;text-align:center;position:relative;transition:background 0.2s;}
.nm-cell:hover{background:var(--bg-2);}
.nm-cell::before{content:'';position:absolute;top:0;left:20%;right:20%;height:2px;background:linear-gradient(90deg,transparent,var(--purple),transparent);opacity:0;transition:opacity 0.4s;}
body.pm-theme-ice .nm-cell::before{background:linear-gradient(90deg,transparent,var(--accent),transparent);}
.nm-cell.lit::before{opacity:1;}
.nm-icon{width:44px;height:44px;border-radius:var(--r-m);background:var(--purple-bg);border:1px solid var(--purple-border);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;transition:background 0.4s,border-color 0.4s;}
.nm-icon svg{width:22px;height:22px;stroke:var(--purple);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
body.pm-theme-ice .nm-icon{background:rgba(24,196,181,0.08);border-color:rgba(24,196,181,0.20);}
body.pm-theme-ice .nm-icon svg{stroke:var(--accent);}
.nm-val{font-family:var(--font-m);font-weight:800;font-size:clamp(32px,3.5vw,48px);letter-spacing:-0.06em;color:var(--text);line-height:1;margin-bottom:8px;}
.nm-val.acc{color:var(--purple);}
body.pm-theme-ice .nm-val.acc{color:var(--accent);}
.nm-lbl{font-family:var(--font-c);font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-3);}
@media(max-width:768px){.network-metrics{grid-template-columns:repeat(2,1fr);}}

/* ═══════════════════════════════════════════
   RATINGS
   ═══════════════════════════════════════════ */
.ratings-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;}
.rating-card{
  background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--r-xl);
  padding:24px;text-align:center;
  transition:transform 0.2s,box-shadow 0.2s,background 0.4s,border-color 0.4s;
}
.rating-card:hover{transform:translateY(-2px);box-shadow:var(--shadow);}
.rating-platform{font-family:var(--font-d);font-weight:800;font-size:16px;color:var(--text);margin-bottom:8px;}
.rating-stars{display:flex;justify-content:center;gap:3px;margin-bottom:8px;}
.rs{width:16px;height:16px;fill:#f59e0b;}
.rating-score{font-family:var(--font-m);font-weight:800;font-size:32px;letter-spacing:-0.06em;color:var(--text);}
.rating-count{font-family:var(--font-c);font-size:9px;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-3);margin-top:4px;}
@media(max-width:768px){.ratings-row{grid-template-columns:repeat(2,1fr);}}

/* ═══════════════════════════════════════════
   TESTIMONIALS
   ═══════════════════════════════════════════ */
.testi-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;}
.testi-card{
  background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--r-xl);
  padding:0;overflow:hidden;display:flex;flex-direction:column;
  transition:transform 0.2s,box-shadow 0.2s,border-color 0.2s,background 0.4s;
}
.testi-card:hover{transform:translateY(-2px);box-shadow:var(--shadow);border-color:var(--purple-border);}
body.pm-theme-ice .testi-card:hover{border-color:rgba(24,196,181,0.28);}
.testi-photo{height:180px;display:flex;align-items:center;justify-content:center;font-family:var(--font-d);font-weight:900;font-size:52px;letter-spacing:-0.04em;color:rgba(255,255,255,0.8);position:relative;overflow:hidden;}
.testi-photo::after{content:'';position:absolute;bottom:0;left:0;right:0;height:60px;background:linear-gradient(transparent,rgba(0,0,0,0.28));}
.testi-body{padding:24px;}
.testi-stars{display:flex;gap:3px;margin-bottom:12px;}
.tsr{width:14px;height:14px;fill:#f59e0b;}
.testi-quote{font-family:var(--font-d);font-size:14px;font-weight:600;letter-spacing:-0.02em;line-height:1.6;color:var(--text);margin-bottom:16px;flex:1;}
.testi-footer-t{display:flex;align-items:center;gap:10px;padding-top:14px;border-top:1px solid var(--line);}
.testi-av{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:var(--font-d);font-weight:800;font-size:13px;color:#fff;flex-shrink:0;}
.testi-name{font-weight:700;font-size:13px;color:var(--text);}
.testi-role{font-family:var(--font-c);font-size:9px;letter-spacing:0.06em;color:var(--text-3);margin-top:1px;}
@media(max-width:900px){.testi-grid{grid-template-columns:1fr;}}

/* ═══════════════════════════════════════════
   AWARDS BAND
   ═══════════════════════════════════════════ */
.pm-awards{background:var(--bg-2);padding:72px 0;transition:background 0.4s;}
.awards-inner{text-align:center;}
.awards-badges{display:flex;align-items:center;justify-content:center;gap:24px;flex-wrap:wrap;margin:36px 0;}
.award-badge{
  background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--r-xl);
  padding:20px 28px;min-width:140px;text-align:center;
  transition:transform 0.2s,box-shadow 0.2s,background 0.4s,border-color 0.4s;
}
.award-badge:hover{transform:translateY(-2px);box-shadow:var(--shadow);}
.award-badge-title{font-family:var(--font-d);font-weight:800;font-size:13px;color:var(--purple);margin-bottom:4px;}
body.pm-theme-ice .award-badge-title{color:var(--accent);}
.award-badge-sub{font-family:var(--font-c);font-size:9px;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-3);}
.awards-socials{display:flex;align-items:center;justify-content:center;gap:12px;margin-top:36px;}
.aw-social{width:38px;height:38px;border-radius:var(--r-s);background:var(--card-bg);border:1px solid var(--card-border);display:flex;align-items:center;justify-content:center;transition:border-color 0.2s,background 0.2s;}
.aw-social:hover{border-color:var(--purple-border);background:var(--purple-bg);}
body.pm-theme-ice .aw-social:hover{border-color:rgba(24,196,181,0.24);background:rgba(24,196,181,0.08);}
.aw-social svg{width:16px;height:16px;stroke:var(--text-2);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}

/* ═══════════════════════════════════════════
   DEV / INTEGRATIONS
   ═══════════════════════════════════════════ */
.dev-grid{display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center;}
.dev-support-cards{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:28px;}
.dev-sc{background:var(--bg-2);border:1px solid var(--line);border-radius:var(--r-m);padding:16px;transition:border-color 0.2s,background 0.4s;}
.dev-sc:hover{border-color:var(--purple-border);}
body.pm-theme-ice .dev-sc:hover{border-color:rgba(24,196,181,0.24);}
.dev-sc-icon{width:32px;height:32px;border-radius:var(--r-s);background:var(--purple-bg);display:flex;align-items:center;justify-content:center;margin-bottom:8px;transition:background 0.4s;}
.dev-sc-icon svg{width:16px;height:16px;stroke:var(--purple);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
body.pm-theme-ice .dev-sc-icon{background:rgba(24,196,181,0.08);}
body.pm-theme-ice .dev-sc-icon svg{stroke:var(--accent);}
.dev-sc-title{font-weight:700;font-size:13px;color:var(--text);margin-bottom:2px;}
.dev-sc-desc{font-size:12px;color:var(--text-2);}
.dev-visual{
  background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--r-xl);
  overflow:hidden;box-shadow:var(--shadow-lg);
  transition:background 0.4s,border-color 0.4s;
}
.dev-chat-header{display:flex;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid var(--line);background:var(--bg);transition:background 0.4s,border-color 0.4s;}
.dev-chat-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--purple),var(--accent));display:flex;align-items:center;justify-content:center;font-family:var(--font-d);font-weight:800;font-size:13px;color:#fff;flex-shrink:0;}
.dev-chat-name{font-weight:700;font-size:13px;color:var(--text);}
.dev-chat-status{font-family:var(--font-c);font-size:10px;color:var(--success);display:flex;align-items:center;gap:4px;}
.dev-chat-body{padding:20px;}
.dev-msg{max-width:80%;margin-bottom:12px;}
.dev-msg.them{margin-right:auto;}.dev-msg.me{margin-left:auto;}
.dev-msg-bubble{padding:10px 14px;border-radius:16px;font-size:13px;line-height:1.55;}
.dev-msg.them .dev-msg-bubble{background:var(--bg-2);border:1px solid var(--line);color:var(--text);}
.dev-msg.me .dev-msg-bubble{background:var(--purple);color:#fff;}
body.pm-theme-ice .dev-msg.me .dev-msg-bubble{background:var(--accent);color:var(--accent-text);}
.dev-msg-time{font-family:var(--font-c);font-size:9px;letter-spacing:0.06em;color:var(--text-3);margin-top:4px;text-align:right;}
.dev-msg.them .dev-msg-time{text-align:left;}
.lang-grid{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px;}
.lang-pill{padding:6px 14px;border-radius:999px;background:var(--bg-2);border:1px solid var(--line);font-size:12px;font-weight:600;color:var(--text-2);transition:all 0.18s;}
.lang-pill:hover{border-color:var(--purple-border);color:var(--purple);background:var(--purple-bg);}
body.pm-theme-ice .lang-pill:hover{border-color:rgba(24,196,181,0.24);color:var(--accent);background:rgba(24,196,181,0.08);}
@media(max-width:900px){.dev-grid{grid-template-columns:1fr;gap:40px;}}

/* ═══════════════════════════════════════════
   CTA JOURNEY — PURPLE CARD
   ═══════════════════════════════════════════ */
.pm-cta-journey{padding:0 48px;margin-bottom:0;}
.cta-journey-card{
  background:#6B4EFF;border-radius:var(--r-2xl);
  padding:64px 72px;
  display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:start;
  position:relative;overflow:hidden;
}
.cta-journey-card::before{
  content:'';position:absolute;inset:0;pointer-events:none;
  background:
    radial-gradient(60% 80% at 80% 50%,rgba(255,255,255,0.09),transparent 70%),
    radial-gradient(40% 50% at 20% 20%,rgba(255,255,255,0.07),transparent 60%);
}
.cj-left{position:relative;z-index:1;}
.cj-left .h2{color:#fff;margin-bottom:20px;}
.cj-left .lead{color:rgba(255,255,255,0.70);margin-bottom:32px;}
.cj-right{position:relative;z-index:1;}
.cj-accordion{display:flex;flex-direction:column;gap:2px;}
.cj-item{background:rgba(255,255,255,0.09);border-radius:var(--r-m);overflow:hidden;border:1px solid rgba(255,255,255,0.11);}
.cj-item-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;cursor:pointer;font-weight:600;font-size:14px;color:#fff;transition:background 0.15s;}
.cj-item-header:hover{background:rgba(255,255,255,0.06);}
.cj-chevron{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;transition:transform 0.2s;}
.cj-item.open .cj-chevron{transform:rotate(180deg);}
.cj-item-body{display:none;padding:0 20px 16px;font-size:13px;color:rgba(255,255,255,0.68);line-height:1.65;}
.cj-item.open .cj-item-body{display:block;}
@media(max-width:900px){.cta-journey-card{grid-template-columns:1fr;gap:36px;padding:48px 36px;}.pm-cta-journey{padding:0 24px;}}
@media(max-width:540px){.cta-journey-card{padding:36px 24px;}.pm-cta-journey{padding:0 16px;}}

/* ═══════════════════════════════════════════
   BLOG / NEWS
   ═══════════════════════════════════════════ */
.blog-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;}
.blog-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--r-xl);overflow:hidden;display:flex;flex-direction:column;transition:transform 0.2s,box-shadow 0.2s,border-color 0.2s,background 0.4s;}
.blog-card:hover{transform:translateY(-2px);box-shadow:var(--shadow);border-color:var(--purple-border);}
body.pm-theme-ice .blog-card:hover{border-color:rgba(24,196,181,0.24);}
.blog-thumb{height:140px;display:flex;align-items:center;justify-content:center;}
.blog-body{padding:20px;flex:1;display:flex;flex-direction:column;}
.blog-tag{font-family:var(--font-c);font-size:9px;letter-spacing:0.12em;text-transform:uppercase;color:var(--purple);margin-bottom:8px;}
body.pm-theme-ice .blog-tag{color:var(--accent);}
.blog-title{font-family:var(--font-d);font-weight:700;font-size:15px;letter-spacing:-0.03em;line-height:1.4;color:var(--text);margin-bottom:12px;flex:1;}
.blog-date{font-family:var(--font-c);font-size:9px;letter-spacing:0.08em;color:var(--text-3);}
@media(max-width:900px){.blog-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:540px){.blog-grid{grid-template-columns:1fr;}}

/* ═══════════════════════════════════════════
   FOOTER
   ═══════════════════════════════════════════ */
.pm-footer{background:var(--bg-2);border-top:1px solid var(--line);padding:72px 0 36px;transition:background 0.4s,border-color 0.4s;}
.footer-top{display:grid;grid-template-columns:260px repeat(4,1fr);gap:48px;margin-bottom:56px;}
.f-brand-logo{display:flex;align-items:center;gap:8px;text-decoration:none;margin-bottom:14px;}
.f-brand-wordmark{font-family:var(--font-d);font-weight:800;font-size:18px;letter-spacing:-0.055em;color:var(--text);}
.f-brand-wordmark span{color:var(--purple);}
body.pm-theme-ice .f-brand-wordmark span{color:var(--accent);}
.f-tagline{font-size:13px;color:var(--text-2);line-height:1.65;margin-bottom:20px;max-width:200px;}
.f-socials{display:flex;gap:8px;}
.f-soc{width:34px;height:34px;border-radius:var(--r-s);border:1px solid var(--line);display:flex;align-items:center;justify-content:center;transition:border-color 0.2s,background 0.2s;}
.f-soc:hover{border-color:var(--purple-border);background:var(--purple-bg);}
body.pm-theme-ice .f-soc:hover{border-color:rgba(24,196,181,0.22);background:rgba(24,196,181,0.07);}
.f-soc svg{width:15px;height:15px;stroke:var(--text-2);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
.f-col-title{font-weight:700;font-size:13px;color:var(--text);margin-bottom:16px;}
.f-col-links{list-style:none;display:flex;flex-direction:column;gap:9px;}
.f-col-links a{font-size:13px;color:var(--text-2);transition:color 0.15s;}
.f-col-links a:hover{color:var(--purple);}
body.pm-theme-ice .f-col-links a:hover{color:var(--accent);}
.footer-bottom{display:flex;justify-content:space-between;align-items:center;padding-top:28px;border-top:1px solid var(--line);flex-wrap:wrap;gap:12px;}
.f-copy{font-family:var(--font-c);font-size:11px;letter-spacing:0.06em;color:var(--text-3);}
.f-legal{display:flex;gap:20px;}
.f-legal a{font-family:var(--font-c);font-size:10px;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-3);transition:color 0.15s;}
.f-legal a:hover{color:var(--purple);}
body.pm-theme-ice .f-legal a:hover{color:var(--accent);}
.f-pay{display:flex;gap:8px;align-items:center;}
.f-pay-icon{background:var(--card-bg);border:1px solid var(--card-border);border-radius:6px;padding:4px 8px;font-family:var(--font-c);font-size:9px;letter-spacing:0.06em;color:var(--text-3);}
@media(max-width:1000px){.footer-top{grid-template-columns:1fr 1fr 1fr;}}
@media(max-width:700px){.footer-top{grid-template-columns:1fr 1fr;}}
@media(max-width:480px){.footer-top{grid-template-columns:1fr;}}
</style>
</head>
<body class="pm-theme-midnight" data-pm-default-theme="midnight">

<div class="pm-admin-app">

  <!-- ╔══ FIXED BACKGROUND LAYERS ══╗ -->
  <div class="pm-admin-bg"></div>
  <div class="pm-admin-cosmic" aria-hidden="true"></div>
  <div class="pm-admin-aura" aria-hidden="true">
    <div class="pm-admin-aura-one"></div>
    <div class="pm-admin-aura-two"></div>
    <div class="pm-admin-aura-three"></div>
  </div>
  <div class="pm-admin-grid" aria-hidden="true"></div>
  <div class="pm-admin-vignette" aria-hidden="true"></div>

  <!-- ╔══ CONTENT SHELL ══╗ -->
  <div class="pm-admin-shell">

<!-- ═══════════════════════════════════════════
     NAV
     ═══════════════════════════════════════════ -->
<nav class="pm-nav" id="pm-nav">
  <div class="nav-inner">
    <a href="/" class="nav-brand">
          <img src="https://proxymint.com/sogerien/page/img/admin_panel/pm-admin-logo-6-full-vector-no-bg.png" class="nav-logo-img" alt="proxymint" onerror="this.style.display='none'">
      <span class="nav-wordmark">proxy<span>mint</span></span>
    </a>
    <ul class="nav-links">
      <li><a href="#products" class="has-arrow"><?= $pmLandingH($pmLandingT('menu.proxy', 'Proxies')) ?></a></li>
      <li><a href="#products" class="has-arrow"><?= $pmLandingH($pmLandingT('menu.scraper', 'Scrapers')) ?></a></li>
      <li><a href="#use-cases" class="has-arrow"><?= $pmLandingH($pmLandingT('landing.nav.solutions', 'Solutions')) ?></a></li>
      <li><a href="#" class="has-arrow"><?= $pmLandingH($pmLandingT('landing.nav.enterprise', 'Enterprise')) ?></a></li>
      <li><a href="/blog" class="has-arrow"><?= $pmLandingH($pmLandingT('blog.nav.blog', 'Blog')) ?></a></li>
      <li><a href="#pricing"><?= $pmLandingH($pmLandingT('landing.nav.pricing', 'Pricing')) ?></a></li>
    </ul>
    <div class="nav-right">
      <div class="pm-lang-dropdown" id="lang-switch" data-current-lang="<?= $pmLandingH($pmLandingLang) ?>">
        <button class="pm-lang-dd-toggle" type="button" aria-haspopup="true" aria-expanded="false" aria-label="<?= $pmLandingH($pmLandingT('menu.language', 'Language')) ?>">
          <span class="pm-lang-current"><?= $pmLandingH($pmLandingT('lang.' . $pmLandingLang, 'English')) ?></span>
          <span class="pm-lang-chevron" aria-hidden="true"></span>
        </button>
        <div class="pm-lang-dd-menu" role="menu">
          <button class="pm-lang-dd-item<?= $pmLandingLang === 'en' ? ' is-active' : '' ?>" type="button" role="menuitem" data-lang="en"><span><?= $pmLandingH($pmLandingT('lang.en', 'English')) ?></span><span class="pm-lang-code">EN</span></button>
          <button class="pm-lang-dd-item<?= $pmLandingLang === 'ru' ? ' is-active' : '' ?>" type="button" role="menuitem" data-lang="ru"><span><?= $pmLandingH($pmLandingT('lang.ru', 'Russian')) ?></span><span class="pm-lang-code">RU</span></button>
          <button class="pm-lang-dd-item<?= $pmLandingLang === 'de' ? ' is-active' : '' ?>" type="button" role="menuitem" data-lang="de"><span><?= $pmLandingH($pmLandingT('lang.de', 'German')) ?></span><span class="pm-lang-code">DE</span></button>
        </div>
      </div>
      <a href="/admin" class="nav-login"><?= $pmLandingH($pmLandingT('auth.sign_in', 'Sign in')) ?></a>
      <!-- Apple-style theme switch — no labels, pure toggle -->
      <button class="theme-switch" id="theme-switch" role="switch" aria-checked="false" aria-label="<?= $pmLandingH($pmLandingT('theme.toggle_light', 'Toggle light mode')) ?>" tabindex="0">
        <span class="theme-switch-thumb"></span>
      </button>
      <a href="/blog" class="btn btn-outline btn-sm"><?= $pmLandingH($pmLandingT('blog.nav.blog', 'Blog')) ?></a>
      <a href="/admin" class="btn btn-purple btn-sm"><?= $pmLandingH($pmLandingT('auth.sign_in', 'Sign in')) ?></a>
    </div>
  </div>
</nav>
<script>
(function(){
  var box = document.getElementById('lang-switch');
  if (!box) return;
  var toggle = box.querySelector('.pm-lang-dd-toggle');
  var items = box.querySelectorAll('.pm-lang-dd-item[data-lang]');
  function setOpen(open) {
    box.classList.toggle('is-open', open);
    if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  function changeLang(lang) {
    lang = String(lang || 'en').toLowerCase();
    try {
      document.cookie = 'sogerien_lang=' + encodeURIComponent(lang) + '; path=/; max-age=31536000; samesite=lax';
      var url = new URL(window.location.href);
      if (lang === 'en') url.searchParams.delete('lang'); else url.searchParams.set('lang', lang);
      window.location.href = url.toString();
    } catch (e) {
      window.location.href = lang === 'en' ? '/' : ('/?lang=' + encodeURIComponent(lang));
    }
  }
  if (toggle) {
    toggle.addEventListener('click', function(e) {
      e.stopPropagation();
      setOpen(!box.classList.contains('is-open'));
    });
  }
  items.forEach(function(item) {
    item.addEventListener('click', function() {
      setOpen(false);
      changeLang(item.getAttribute('data-lang'));
    });
  });
  document.addEventListener('click', function(e) {
    if (!box.contains(e.target)) setOpen(false);
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') setOpen(false);
  });
})();
</script>
<script>
window.pmLandingTextMap = <?= json_encode($pmLandingTextMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}' ?>;
(function(){
  var map = window.pmLandingTextMap || {};
  if (!Object.keys(map).length || !document.createTreeWalker) return;
  function applyLandingTextMap() {
    if (!document.body) return;
    var skip = {SCRIPT:1, STYLE:1, PRE:1, CODE:1, TEXTAREA:1, SELECT:1, OPTION:1};
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
      acceptNode: function(node) {
        var parent = node.parentElement;
        if (!parent || skip[parent.tagName]) return NodeFilter.FILTER_REJECT;
        if (parent.closest('pre,code,.db-pre,.kw,.fn,.key,.str,.num,.op')) return NodeFilter.FILTER_REJECT;
        return node.nodeValue && node.nodeValue.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
      }
    });
    var node;
    while ((node = walker.nextNode())) {
      var text = node.nodeValue || '';
      var trimmed = text.trim();
      if (!Object.prototype.hasOwnProperty.call(map, trimmed)) continue;
      var prefix = text.match(/^\s*/)[0];
      var suffix = text.match(/\s*$/)[0];
      node.nodeValue = prefix + map[trimmed] + suffix;
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyLandingTextMap, {once:true});
  } else {
    applyLandingTextMap();
  }
})();
</script>

<!-- ═══════════════════════════════════════════
     HERO — DARK ROUNDED CARD
     ═══════════════════════════════════════════ -->
<section class="pm-hero" id="hero">
  <div class="hero-card">
    <div class="hero-dots"></div>
    <div class="hero-card-inner">
      <!-- Left: Copy -->
      <div class="hero-left">
        <div class="hero-badge">
          <span class="dot-red"></span>
          new: web scraper API — now available
        </div>
        <h1 class="h1 hero-h1">
          Access Any<br>Public Data.<br>
          <span class="gradient-text">Power Any Workflow.</span>
        </h1>
        <p class="hero-sub">
          Residential, ISP, datacenter &amp; mobile proxies. 100M+ IPs across 195 countries, 99.4% uptime. The proxy infrastructure that scales without limits.
        </p>
        <div class="hero-form">
          <input class="hero-input" type="text" placeholder="<?= $pmLandingH($pmLandingT('landing.hero.input_placeholder', 'Enter a URL to scrape')) ?>">
          <a href="#" class="btn btn-purple btn-lg">Start free trial</a>
        </div>
        <a href="#" class="hero-book">
          Book a demo
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="5,12 19,12"/><polyline points="13,6 19,12 13,18"/></svg>
        </a>
        <div class="hero-status">
          <span class="live-dot"></span>
          All systems operational · 99.4% uptime this month
        </div>
      </div>
      <!-- Right: Visual dashboard -->
      <div class="hero-visual">
        <div class="hv-float hv-float-tl">
          <div class="hv-float-val">99.4%</div>
          <div class="hv-float-lbl">success rate</div>
        </div>
        <div class="hero-visual-inner">
          <div class="hv-header">
            <span class="hv-label">proxymint · network overview</span>
            <span class="hv-label" style="display:flex;align-items:center;gap:6px;"><span class="live-dot"></span>live</span>
          </div>
          <div class="hv-big-num">177 M+</div>
          <div class="hv-big-sub">total IP pool — residential, datacenter, ISP &amp; mobile</div>
          <div class="hv-chart">
            <svg viewBox="0 0 360 80" preserveAspectRatio="none">
              <defs>
                <linearGradient id="hcg" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#7B6EFF" stop-opacity="0.36"/>
                  <stop offset="100%" stop-color="#7B6EFF" stop-opacity="0.02"/>
                </linearGradient>
                <linearGradient id="hcg2" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#2AD9C8" stop-opacity="0.26"/>
                  <stop offset="100%" stop-color="#2AD9C8" stop-opacity="0.02"/>
                </linearGradient>
              </defs>
              <path d="M0,72 L45,62 L90,55 L130,44 L175,32 L220,22 L270,13 L320,7 L360,3" fill="none" stroke="#7B6EFF" stroke-width="2" stroke-linecap="round"/>
              <path d="M0,72 L45,62 L90,55 L130,44 L175,32 L220,22 L270,13 L320,7 L360,3 L360,80 L0,80Z" fill="url(#hcg)"/>
              <path d="M0,76 L45,70 L90,65 L130,60 L175,54 L220,46 L270,40 L320,35 L360,31" fill="none" stroke="#2AD9C8" stroke-width="1.4" stroke-linecap="round" opacity="0.55"/>
              <path d="M0,76 L45,70 L90,65 L130,60 L175,54 L220,46 L270,40 L320,35 L360,31 L360,80 L0,80Z" fill="url(#hcg2)"/>
            </svg>
          </div>
          <div class="hv-stat-row">
            <div class="hv-stat"><div class="hv-stat-val acc">100m+</div><div class="hv-stat-lbl">residential</div></div>
            <div class="hv-stat"><div class="hv-stat-val pur">2m+</div><div class="hv-stat-lbl">datacenter</div></div>
            <div class="hv-stat"><div class="hv-stat-val" style="color:#f0c95d;">195+</div><div class="hv-stat-lbl">countries</div></div>
          </div>
        </div>
        <div class="hv-float hv-float-br">
          <div class="hv-float-val pur2">＜1s</div>
          <div class="hv-float-lbl">avg response</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     TRUST — LOGO STRIP
     ═══════════════════════════════════════════ -->
<section class="pm-trust">
  <div class="wrap">
    <div class="trust-label">Trusted by 1,000+ companies worldwide</div>
    <div class="trust-logos">
      <span class="trust-logo">Siemens</span>
      <span class="trust-logo">Grammarly</span>
      <span class="trust-logo">Nasdaq</span>
      <span class="trust-logo">Nielsen</span>
      <span class="trust-logo">Adidas</span>
      <span class="trust-logo">DataDog</span>
      <span class="trust-logo">Cloudflare</span>
      <span class="trust-logo">Rakuten</span>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     VALUE PROP — TABS + 2-COL CARDS
     ═══════════════════════════════════════════ -->
<section class="section" id="use-cases">
  <div class="wrap">
    <div class="section-hdr center reveal">
      <div class="eyebrow">use cases</div>
      <h2 class="h2">Built for every use case.<br>Powered by one platform.</h2>
      <p class="lead" style="margin:0 auto 32px;">From AI model training to real-time brand protection — proxymint handles every data extraction challenge at enterprise scale.</p>
    </div>
    <div class="tabs-row reveal">
      <button class="tab-pill active">E-commerce</button>
      <button class="tab-pill">Ad Verification</button>
      <button class="tab-pill">Brand Protection</button>
      <button class="tab-pill">Cybersecurity</button>
      <button class="tab-pill">Real Estate</button>
    </div>
    <div class="val-grid reveal">
      <div class="val-dark-card">
        <div class="val-dark-bg"></div>
        <div class="val-dark-inner">
          <div class="val-dark-eyebrow">AI &amp; Machine Learning</div>
          <div class="val-dark-h">Fuel your LLMs with real-time, at-scale web data</div>
          <p class="val-dark-p">Power AI training pipelines, RAG systems, and LLM fine-tuning with fresh, structured web data. Collect terabytes from any target without blocks or interruptions.</p>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;">
            <span class="val-dark-chip">100M+ IPs</span>
            <span class="val-dark-chip">99.4% success</span>
            <span class="val-dark-chip">195 countries</span>
          </div>
          <a href="#" class="btn btn-white btn-sm">Start free trial</a>
          <div class="val-dark-illustration">
<span class="kw">import</span> <span class="fn">proxymint</span>

<span class="cmt"># fetch e-commerce data at scale</span>
<span class="key">client</span> = proxymint.<span class="fn">Client</span>(api_key=<span class="str">"YOUR_KEY"</span>)
<span class="key">result</span> = client.<span class="fn">scrape</span>(
  url=<span class="str">"https://shop.example.com/products"</span>,
  <span class="key">geo</span>=<span class="str">"US"</span>, <span class="key">type</span>=<span class="str">"residential"</span>
)</div>
        </div>
      </div>
      <div class="val-right-stack">
        <div class="val-light-card">
          <div class="val-card-icon"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
          <div class="val-card-h">99.4%+ Success Rate</div>
          <p class="val-card-p">Industry-leading success rate across all proxy types, maintained by our AI-powered routing engine.</p>
          <div class="val-card-metric">99.4%</div>
          <div class="val-card-metric-lbl">avg success rate</div>
        </div>
        <div class="val-light-card">
          <div class="val-card-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg></div>
          <div class="val-card-h">Sub-1 Second Response</div>
          <p class="val-card-p">Millisecond-level latency on residential IPs — fast enough for real-time price monitoring and live data feeds.</p>
          <div class="val-card-metric">＜1s</div>
          <div class="val-card-metric-lbl">avg response time</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     PRODUCT ROWS — ALTERNATING FEATURE SPLITS
     ═══════════════════════════════════════════ -->
<section class="section section-bg2" id="products">
  <div class="wrap">
    <div class="section-hdr center reveal">
      <div class="eyebrow">solutions</div>
      <h2 class="h2">Collect data with proxymint proxies<br>&amp; public data collection tools</h2>
    </div>
    <div class="product-sub-nav reveal">
      <div class="psn-item active">Proxies</div>
      <div class="psn-item">Scraper APIs</div>
      <div class="psn-item">Web Unblocker</div>
      <div class="psn-item">Datasets</div>
    </div>

    <!-- Feature row 1 — Residential -->
    <div class="feat-row reveal">
      <div class="feat-copy">
        <div class="feat-icon-wrap"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
        <div class="eyebrow">residential proxies</div>
        <h3 class="h3">Reach difficult targets<br>with real-device IPs</h3>
        <p class="lead" style="font-size:15px;">Real peer IPs from 100M+ devices in 195 countries. Bypass aggressive anti-bot systems with authentic residential footprints.</p>
        <ul class="feat-checks">
          <li>100M+ rotating residential IPs</li>
          <li>City &amp; ASN-level geo targeting in 195+ countries</li>
          <li>Sticky sessions up to 30 minutes</li>
          <li>Bandwidth-based billing — no per-request fees</li>
        </ul>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
          <a href="#" class="btn btn-purple btn-sm">Explore residential</a>
          <a href="#" class="btn-icon btn-sm">View pricing <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="5,12 19,12"/><polyline points="13,6 19,12 13,18"/></svg></a>
        </div>
      </div>
      <div class="feat-visual reveal reveal-d1">
        <div class="fv-topbar"><div class="fv-dots"><div class="fv-dot"></div><div class="fv-dot"></div><div class="fv-dot"></div></div><div class="fv-title">proxymint dashboard · residential</div></div>
        <div class="fv-body">
          <div class="fv-kpi-row">
            <div class="fv-kpi"><div class="fv-kpi-lbl">active IPs</div><div class="fv-kpi-val">128.4k</div><div class="fv-kpi-delta">↑ +12.3%</div></div>
            <div class="fv-kpi"><div class="fv-kpi-lbl">success rate</div><div class="fv-kpi-val acc">99.4%</div><div class="fv-kpi-delta">↑ +0.2%</div></div>
          </div>
          <div class="fv-chart">
            <div class="fv-chart-lbl">requests — 7 day trend</div>
            <svg viewBox="0 0 280 64" preserveAspectRatio="none">
              <defs><linearGradient id="fg1" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#7B6EFF" stop-opacity="0.26"/><stop offset="100%" stop-color="#7B6EFF" stop-opacity="0.02"/></linearGradient></defs>
              <path d="M0,58 L40,50 L80,44 L120,34 L160,24 L200,16 L240,9 L280,4" fill="none" stroke="#7B6EFF" stroke-width="2" stroke-linecap="round"/>
              <path d="M0,58 L40,50 L80,44 L120,34 L160,24 L200,16 L240,9 L280,4 L280,64 L0,64Z" fill="url(#fg1)"/>
            </svg>
          </div>
          <div class="fv-table">
            <div class="fv-tr"><div class="fv-th">pool</div><div class="fv-th">type</div><div class="fv-th">status</div></div>
            <div class="fv-tr"><div class="fv-td">pool alpha — US</div><div><span class="fv-badge pur">residential</span></div><div><span class="fv-status on"><span class="dot"></span>active</span></div></div>
            <div class="fv-tr"><div class="fv-td">pool beta — EU</div><div><span class="fv-badge acc">residential</span></div><div><span class="fv-status on"><span class="dot"></span>active</span></div></div>
            <div class="fv-tr"><div class="fv-td">pool gamma — APAC</div><div><span class="fv-badge ok">ISP</span></div><div><span class="fv-status warn"><span class="dot"></span>trial</span></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Feature row 2 — Datacenter (reversed) -->
    <div class="feat-row rev reveal">
      <div class="feat-copy">
        <div class="feat-icon-wrap"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="4" rx="1"/><rect x="2" y="10" width="20" height="4" rx="1"/><rect x="2" y="17" width="20" height="4" rx="1"/></svg></div>
        <div class="eyebrow">datacenter proxies</div>
        <h3 class="h3">Reach difficult targets<br>with high-speed, premium data</h3>
        <p class="lead" style="font-size:15px;">2M+ high-speed datacenter IPs across 188 countries. Cost-effective for high-volume scraping with sub-250ms response times.</p>
        <ul class="feat-checks">
          <li>2M+ datacenter IPs across 188+ countries</li>
          <li>Sub-250ms average response time</li>
          <li>Unlimited bandwidth on dedicated plans</li>
          <li>Shared and dedicated pool options</li>
        </ul>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
          <a href="#" class="btn btn-purple btn-sm">Explore datacenter</a>
          <a href="#" class="btn-icon btn-sm">View docs <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="5,12 19,12"/><polyline points="13,6 19,12 13,18"/></svg></a>
        </div>
      </div>
      <div class="feat-visual reveal reveal-d1">
        <div class="fv-topbar"><div class="fv-dots"><div class="fv-dot"></div><div class="fv-dot"></div><div class="fv-dot"></div></div><div class="fv-title">proxymint dashboard · datacenter</div></div>
        <div class="fv-body">
          <div class="fv-kpi-row">
            <div class="fv-kpi"><div class="fv-kpi-lbl">avg latency</div><div class="fv-kpi-val">0.25s</div><div class="fv-kpi-delta">↓ industry low</div></div>
            <div class="fv-kpi"><div class="fv-kpi-lbl">IP pool</div><div class="fv-kpi-val acc">2m+</div><div class="fv-kpi-delta">↑ growing</div></div>
          </div>
          <div class="fv-chart">
            <div class="fv-chart-lbl">latency distribution (ms)</div>
            <svg viewBox="0 0 280 64" preserveAspectRatio="none">
              <defs><linearGradient id="fg2" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#2AD9C8" stop-opacity="0.26"/><stop offset="100%" stop-color="#2AD9C8" stop-opacity="0.02"/></linearGradient></defs>
              <path d="M0,62 L35,54 L70,40 L110,20 L140,8 L160,5 L190,10 L220,24 L255,44 L280,58" fill="none" stroke="#2AD9C8" stroke-width="2" stroke-linecap="round"/>
              <path d="M0,62 L35,54 L70,40 L110,20 L140,8 L160,5 L190,10 L220,24 L255,44 L280,58 L280,64 L0,64Z" fill="url(#fg2)"/>
            </svg>
          </div>
          <div class="fv-table">
            <div class="fv-tr"><div class="fv-th">location</div><div class="fv-th">IPs</div><div class="fv-th">latency</div></div>
            <div class="fv-tr"><div class="fv-td">🇺🇸 US East</div><div class="fv-td" style="font-family:var(--font-c);font-size:11px;">482k</div><div class="fv-td" style="font-family:var(--font-c);font-size:11px;color:var(--purple);">0.18s</div></div>
            <div class="fv-tr"><div class="fv-td">🇩🇪 EU West</div><div class="fv-td" style="font-family:var(--font-c);font-size:11px;">318k</div><div class="fv-td" style="font-family:var(--font-c);font-size:11px;color:var(--purple);">0.24s</div></div>
            <div class="fv-tr"><div class="fv-td">🇸🇬 APAC</div><div class="fv-td" style="font-family:var(--font-c);font-size:11px;">124k</div><div class="fv-td" style="font-family:var(--font-c);font-size:11px;color:var(--purple);">0.31s</div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Feature row 3 — ISP -->
    <div class="feat-row reveal">
      <div class="feat-copy">
        <div class="feat-icon-wrap"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M4.93 4.93a10 10 0 0 0 0 14.14"/></svg></div>
        <div class="eyebrow">ISP proxies</div>
        <h3 class="h3">Reach difficult targets<br>for website scraping</h3>
        <p class="lead" style="font-size:15px;">Premium ISP-grade IPs that combine datacenter speed with residential legitimacy. Unlimited bandwidth on all plans from top-tier ASN providers.</p>
        <ul class="feat-checks">
          <li>Premium ASN providers (Cox, Deutsche Telekom…)</li>
          <li>99.9% uptime guaranteed on all plans</li>
          <li>Unlimited bandwidth — no caps</li>
          <li>Sub-400ms global response times</li>
        </ul>
        <a href="#" class="btn btn-purple btn-sm">Explore ISP proxies</a>
      </div>
      <div class="feat-visual reveal reveal-d1">
        <div class="fv-topbar"><div class="fv-dots"><div class="fv-dot"></div><div class="fv-dot"></div><div class="fv-dot"></div></div><div class="fv-title">proxymint dashboard · ISP</div></div>
        <div class="fv-body">
          <div class="fv-kpi-row">
            <div class="fv-kpi"><div class="fv-kpi-lbl">uptime</div><div class="fv-kpi-val">99.9%</div><div class="fv-kpi-delta">↑ premium SLA</div></div>
            <div class="fv-kpi"><div class="fv-kpi-lbl">bandwidth</div><div class="fv-kpi-val acc">∞</div><div class="fv-kpi-delta">all plans</div></div>
          </div>
          <div class="fv-table">
            <div class="fv-tr"><div class="fv-th">provider</div><div class="fv-th">ASN grade</div><div class="fv-th">status</div></div>
            <div class="fv-tr"><div class="fv-td">Cox Communications</div><div><span class="fv-badge pur">premium</span></div><div><span class="fv-status on"><span class="dot"></span>active</span></div></div>
            <div class="fv-tr"><div class="fv-td">Deutsche Telekom</div><div><span class="fv-badge pur">premium</span></div><div><span class="fv-status on"><span class="dot"></span>active</span></div></div>
            <div class="fv-tr"><div class="fv-td">Comcast Business</div><div><span class="fv-badge acc">tier-1</span></div><div><span class="fv-status on"><span class="dot"></span>active</span></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Feature row 4 — Mobile (reversed) -->
    <div class="feat-row rev reveal">
      <div class="feat-copy">
        <div class="feat-icon-wrap"><svg viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></div>
        <div class="eyebrow">mobile proxies</div>
        <h3 class="h3">Reach difficult targets<br>with high-speed, premium 4G/5G</h3>
        <p class="lead" style="font-size:15px;">Real 4G/5G mobile IPs from 20M+ devices globally. The highest-trust proxy type for mobile-specific targets, ad verification, and fraud detection.</p>
        <ul class="feat-checks">
          <li>20M+ real 4G/5G mobile IPs in 150+ countries</li>
          <li>Carrier and OS-level targeting</li>
          <li>Automatic rotation on every request</li>
          <li>Dedicated mobile subnet access</li>
        </ul>
        <a href="#" class="btn btn-purple btn-sm">Explore mobile proxies</a>
      </div>
      <div class="feat-visual reveal reveal-d1">
        <div class="fv-topbar"><div class="fv-dots"><div class="fv-dot"></div><div class="fv-dot"></div><div class="fv-dot"></div></div><div class="fv-title">proxymint dashboard · mobile</div></div>
        <div class="fv-body">
          <div class="fv-kpi-row">
            <div class="fv-kpi"><div class="fv-kpi-lbl">mobile IPs</div><div class="fv-kpi-val">20m+</div><div class="fv-kpi-delta">↑ 4G/5G only</div></div>
            <div class="fv-kpi"><div class="fv-kpi-lbl">trust score</div><div class="fv-kpi-val acc">highest</div><div class="fv-kpi-delta">↑ vs all types</div></div>
          </div>
          <div class="fv-table">
            <div class="fv-tr"><div class="fv-th">carrier</div><div class="fv-th">network</div><div class="fv-th">coverage</div></div>
            <div class="fv-tr"><div class="fv-td">🇺🇸 T-Mobile</div><div><span class="fv-badge pur">5G</span></div><div class="fv-td" style="font-family:var(--font-c);font-size:11px;color:var(--purple);">48 states</div></div>
            <div class="fv-tr"><div class="fv-td">🇬🇧 EE UK</div><div><span class="fv-badge acc">4G</span></div><div class="fv-td" style="font-family:var(--font-c);font-size:11px;color:var(--purple);">nationwide</div></div>
            <div class="fv-tr"><div class="fv-td">🇩🇪 Vodafone DE</div><div><span class="fv-badge pur">5G</span></div><div class="fv-td" style="font-family:var(--font-c);font-size:11px;color:var(--purple);">nationwide</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     DARK WORKFLOW BAND — CODE PANEL
     ═══════════════════════════════════════════ -->
<div class="pm-dark-band section" id="workflow">
  <div class="pm-dark-band-bg"></div>
  <div class="db-inner reveal">
    <div class="db-left">
      <div class="eyebrow" style="color:#2AD9C8;">developer-first</div>
      <h2 class="h2" style="color:#fff;">Power your<br>workflow.</h2>
      <ul class="db-menu">
        <li class="active">Residential Proxies</li>
        <li>Datacenter Proxies</li>
        <li>ISP Proxies</li>
        <li>Mobile Proxies</li>
        <li>Web Scraper API</li>
        <li>Web Unblocker</li>
      </ul>
    </div>
    <div class="db-right">
      <div class="db-code-header">
        <div class="db-code-dots"><div class="db-code-dot"></div><div class="db-code-dot"></div><div class="db-code-dot"></div></div>
        <span class="db-code-tab active">python</span>
        <span class="db-code-tab">cURL</span>
        <span class="db-code-tab">node.js</span>
        <span class="db-code-tab">php</span>
      </div>
      <div class="db-code-body">
<pre class="db-pre"><span class="kw">import</span> <span class="fn">requests</span>

<span class="cmt"># proxymint residential — configure once, route everywhere</span>
<span class="fn">username</span> <span class="op">=</span> <span class="str">"customer-YOUR_USER"</span>
<span class="fn">password</span> <span class="op">=</span> <span class="str">"YOUR_PASS"</span>
<span class="fn">endpoint</span> <span class="op">=</span> <span class="str">"gate.proxymint.io:7777"</span>

<span class="fn">proxies</span> <span class="op">=</span> {
  <span class="str">'http'</span>:  <span class="kw">f</span><span class="str">'http://{username}:{password}@{endpoint}'</span>,
  <span class="str">'https'</span>: <span class="kw">f</span><span class="str">'http://{username}:{password}@{endpoint}'</span>,
}

<span class="fn">response</span> <span class="op">=</span> requests.<span class="fn">get</span>(
  <span class="str">'https://target-site.com/data'</span>,
  proxies<span class="op">=</span>proxies, timeout<span class="op">=</span><span class="num">30</span>
)
<span class="kw">print</span>(response.json())</pre>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     STATS — ALL YOUR STATS ONE VIEW
     ═══════════════════════════════════════════ -->
<section class="section section-bg2" id="dashboard">
  <div class="wrap">
    <div class="section-hdr center reveal">
      <div class="eyebrow">platform</div>
      <h2 class="h2">All your stats — one view</h2>
      <p class="lead" style="margin:0 auto 16px;">Monitor every pool, request, and IP in a single real-time dashboard. No ops overhead, no context switching.</p>
      <a href="#" class="btn btn-ghost-purple btn-sm" style="margin:0 auto 48px;display:inline-flex;">View all features</a>
    </div>
    <div class="stats-cards reveal">
      <div class="stat-card">
        <div class="stat-card-lbl">active IP pools</div>
        <div class="ip-list">
          <div class="ip-row"><span class="ip-flag">🇺🇸</span><span class="ip-addr">104.28.14.★★★</span><span class="ip-badge ok">active</span></div>
          <div class="ip-row"><span class="ip-flag">🇬🇧</span><span class="ip-addr">188.40.212.★★★</span><span class="ip-badge act">residential</span></div>
          <div class="ip-row"><span class="ip-flag">🇩🇪</span><span class="ip-addr">5.187.76.★★★</span><span class="ip-badge ok">active</span></div>
          <div class="ip-row"><span class="ip-flag">🇯🇵</span><span class="ip-addr">110.4.80.★★★</span><span class="ip-badge act">ISP</span></div>
          <div class="ip-row"><span class="ip-flag">🇸🇬</span><span class="ip-addr">180.210.★★★</span><span class="ip-badge ok">active</span></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-lbl">request volume by pool</div>
        <div class="bar-chart">
          <div class="bar-col" style="height:40%"></div>
          <div class="bar-col" style="height:65%"></div>
          <div class="bar-col" style="height:88%"></div>
          <div class="bar-col dim" style="height:55%"></div>
          <div class="bar-col" style="height:100%"></div>
          <div class="bar-col dim" style="height:72%"></div>
          <div class="bar-col" style="height:80%"></div>
        </div>
        <div class="bar-labels">
          <span class="bar-lbl">Mon</span><span class="bar-lbl">Tue</span><span class="bar-lbl">Wed</span><span class="bar-lbl">Thu</span><span class="bar-lbl">Fri</span><span class="bar-lbl">Sat</span><span class="bar-lbl">Sun</span>
        </div>
        <div style="margin-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div><div style="font-family:var(--font-m);font-weight:800;font-size:24px;letter-spacing:-0.05em;color:var(--purple);">3.24m</div><div style="font-family:var(--font-c);font-size:8px;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-3);">requests / day</div></div>
          <div><div style="font-family:var(--font-m);font-weight:800;font-size:24px;letter-spacing:-0.05em;color:var(--purple);">99.4%</div><div style="font-family:var(--font-c);font-size:8px;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-3);">success rate</div></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-lbl">bandwidth usage</div>
        <svg viewBox="0 0 240 80" preserveAspectRatio="none" style="width:100%;height:80px;">
          <defs><linearGradient id="scg" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#7B6EFF" stop-opacity="0.30"/><stop offset="100%" stop-color="#7B6EFF" stop-opacity="0.02"/></linearGradient></defs>
          <path d="M0,74 L30,65 L60,56 L90,44 L120,30 L150,18 L180,10 L210,5 L240,2" fill="none" stroke="#7B6EFF" stroke-width="2.2" stroke-linecap="round"/>
          <path d="M0,74 L30,65 L60,56 L90,44 L120,30 L150,18 L180,10 L210,5 L240,2 L240,80 L0,80Z" fill="url(#scg)"/>
        </svg>
        <div style="margin-top:12px;display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div><div style="font-family:var(--font-m);font-weight:800;font-size:24px;letter-spacing:-0.05em;color:var(--purple);">2.41 TB</div><div style="font-family:var(--font-c);font-size:8px;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-3);">this month</div></div>
          <div><div style="font-family:var(--font-m);font-weight:800;font-size:24px;letter-spacing:-0.05em;color:var(--purple);">↑ 18%</div><div style="font-family:var(--font-c);font-size:8px;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-3);">vs last month</div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     NETWORK SCALE METRICS
     ═══════════════════════════════════════════ -->
<section class="section pm-network" id="network">
  <div class="wrap">
    <div class="section-hdr center reveal">
      <div class="eyebrow">global network</div>
      <h2 class="h2">The largest proxy network<br>in the world</h2>
    </div>
    <div class="network-metrics reveal">
      <div class="nm-cell" data-nm><div class="nm-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div><div class="nm-val acc">195+</div><div class="nm-lbl">countries covered</div></div>
      <div class="nm-cell" data-nm><div class="nm-icon"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div><div class="nm-val acc">100m+</div><div class="nm-lbl">residential IPs</div></div>
      <div class="nm-cell" data-nm><div class="nm-icon"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="4" rx="1"/><rect x="2" y="10" width="20" height="4" rx="1"/><rect x="2" y="17" width="20" height="4" rx="1"/></svg></div><div class="nm-val acc">2m+</div><div class="nm-lbl">datacenter IPs</div></div>
      <div class="nm-cell" data-nm><div class="nm-icon"><svg viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></div><div class="nm-val acc">20m+</div><div class="nm-lbl">mobile IPs</div></div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     RATINGS
     ═══════════════════════════════════════════ -->
<section class="section section-bg2">
  <div class="wrap">
    <div class="section-hdr center reveal">
      <div class="eyebrow">reviews</div>
      <h2 class="h2">Trusted by innovators worldwide</h2>
    </div>
    <div class="ratings-row reveal">
      <div class="rating-card">
        <div class="rating-platform">Trustpilot</div>
        <div class="rating-stars"><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
        <div class="rating-score">4.8</div>
        <div class="rating-count">based on 1,200+ reviews</div>
      </div>
      <div class="rating-card">
        <div class="rating-platform">G2</div>
        <div class="rating-stars"><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
        <div class="rating-score">4.7</div>
        <div class="rating-count">Leader · Spring 2026</div>
      </div>
      <div class="rating-card">
        <div class="rating-platform">Capterra</div>
        <div class="rating-stars"><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
        <div class="rating-score">4.9</div>
        <div class="rating-count">Top rated 2026</div>
      </div>
      <div class="rating-card">
        <div class="rating-platform">Product Hunt</div>
        <div class="rating-stars"><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="rs" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
        <div class="rating-score">5.0</div>
        <div class="rating-count">#1 Product of the Day</div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     TESTIMONIALS
     ═══════════════════════════════════════════ -->
<section class="section">
  <div class="wrap">
    <div class="section-hdr reveal" style="display:flex;align-items:flex-end;justify-content:space-between;max-width:100%;">
      <div>
        <div class="eyebrow">customers</div>
        <h2 class="h2" style="margin-bottom:0;">Why top companies choose us</h2>
      </div>
      <a href="#" class="btn-icon" style="flex-shrink:0;padding-bottom:6px;">
        Read stories
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="5,12 19,12"/><polyline points="13,6 19,12 13,18"/></svg>
      </a>
    </div>
    <div class="testi-grid reveal">
      <div class="testi-card">
        <div class="testi-photo" style="background:linear-gradient(135deg,#3b82f6,#6366f1);">DK</div>
        <div class="testi-body">
          <div class="testi-stars"><svg class="tsr" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="tsr" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="tsr" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="tsr" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="tsr" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
          <div class="testi-quote">"Vast residential pool and near-100% uptime — we execute critical data operations and deliver the freshest retail insights without a single outage."</div>
          <div class="testi-footer-t"><div class="testi-av" style="background:linear-gradient(135deg,#3b82f6,#6366f1);">DK</div><div><div class="testi-name">Devon Kelly</div><div class="testi-role">SVP Operations · RetailSense</div></div></div>
        </div>
      </div>
      <div class="testi-card">
        <div class="testi-photo" style="background:linear-gradient(135deg,#ec4899,#f97316);">JR</div>
        <div class="testi-body">
          <div class="testi-stars"><svg class="tsr" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="tsr" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="tsr" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="tsr" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="tsr" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
          <div class="testi-quote">"proxymint not only simplified our entire web crawling approach — it cut our total scraping infrastructure costs by 40% while doubling throughput."</div>
          <div class="testi-footer-t"><div class="testi-av" style="background:linear-gradient(135deg,#ec4899,#f97316);">JR</div><div><div class="testi-name">Javier Rodriguez</div><div class="testi-role">Managing Director · DataBridge</div></div></div>
        </div>
      </div>
      <div class="testi-card">
        <div class="testi-photo" style="background:linear-gradient(135deg,#10b981,#0ea5e9);">WZ</div>
        <div class="testi-body">
          <div class="testi-stars"><svg class="tsr" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="tsr" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="tsr" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="tsr" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg class="tsr" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
          <div class="testi-quote">"We now monitor markets we couldn't access before. proxymint's geo-targeting let us reach customers with better, faster, more accurate data."</div>
          <div class="testi-footer-t"><div class="testi-av" style="background:linear-gradient(135deg,#10b981,#0ea5e9);">WZ</div><div><div class="testi-name">Wei Zheng</div><div class="testi-role">Chief Product Officer · NexusAI</div></div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     AWARDS BAND
     ═══════════════════════════════════════════ -->
<div class="pm-awards">
  <div class="wrap">
    <div class="awards-inner reveal">
      <div class="eyebrow" style="display:block;text-align:center;">transparency &amp; excellence</div>
      <h2 class="h2" style="text-align:center;">Driven by transparency<br>and excellence</h2>
      <p class="lead" style="margin:0 auto 0;max-width:480px;text-align:center;">Recognized by the world's leading independent software review platforms and certified to the highest security standards.</p>
      <div class="awards-badges">
        <div class="award-badge"><div class="award-badge-title">G2 Leader</div><div class="award-badge-sub">Spring 2026</div></div>
        <div class="award-badge"><div class="award-badge-title">ISO 27001</div><div class="award-badge-sub">certified</div></div>
        <div class="award-badge"><div class="award-badge-title">SOC 2 Type II</div><div class="award-badge-sub">compliant</div></div>
        <div class="award-badge"><div class="award-badge-title">Trustpilot 4.8★</div><div class="award-badge-sub">excellent</div></div>
        <div class="award-badge"><div class="award-badge-title">Capterra 4.9★</div><div class="award-badge-sub">top rated</div></div>
      </div>
      <div class="awards-socials">
        <a href="#" class="aw-social"><svg viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></a>
        <a href="#" class="aw-social"><svg viewBox="0 0 24 24"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg></a>
        <a href="#" class="aw-social"><svg viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.74l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" fill="currentColor"/></svg></a>
        <a href="#" class="aw-social"><svg viewBox="0 0 24 24"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58A2.78 2.78 0 0 0 3.41 19.5C5.12 20 12 20 12 20s6.88 0 8.59-.5a2.78 2.78 0 0 0 1.95-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="white"/></svg></a>
        <a href="#" class="aw-social"><svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg></a>
        <a href="#" class="btn btn-purple btn-sm" style="border-radius:999px;">Join community</a>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     DEVELOPER / INTEGRATIONS
     ═══════════════════════════════════════════ -->
<section class="section" id="integrations">
  <div class="wrap">
    <div class="dev-grid">
      <div class="dev-copy reveal">
        <div class="eyebrow">developer-first</div>
        <h2 class="h2">Fits easily into<br>your projects.</h2>
        <p class="lead">One endpoint. Any language. Drop proxymint into your existing scraper, browser, or API with a single line change. 50+ integrations ready out of the box.</p>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:32px;">
          <a href="#" class="btn btn-purple">See documentation</a>
          <a href="#" class="btn btn-outline">Talk to our experts</a>
        </div>
        <div class="dev-support-cards">
          <div class="dev-sc"><div class="dev-sc-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg></div><div class="dev-sc-title">24/7 Support</div><div class="dev-sc-desc">Expert help around the clock</div></div>
          <div class="dev-sc"><div class="dev-sc-icon"><svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></div><div class="dev-sc-title">Knowledge Base</div><div class="dev-sc-desc">Guides, tutorials &amp; recipes</div></div>
          <div class="dev-sc"><div class="dev-sc-icon"><svg viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></div><div class="dev-sc-title">API Docs</div><div class="dev-sc-desc">Full reference, always up to date</div></div>
          <div class="dev-sc"><div class="dev-sc-icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div><div class="dev-sc-title">Free Trial</div><div class="dev-sc-desc">Start with no credit card required</div></div>
        </div>
        <div class="lang-grid" style="margin-top:24px;">
          <span class="lang-pill">Python</span><span class="lang-pill">Node.js</span><span class="lang-pill">PHP</span><span class="lang-pill">Java</span><span class="lang-pill">Go</span><span class="lang-pill">.NET</span><span class="lang-pill">Ruby</span><span class="lang-pill">cURL</span>
        </div>
      </div>
      <div class="dev-visual reveal reveal-d1">
        <div class="dev-chat-header">
          <div class="dev-chat-avatar">PM</div>
          <div>
            <div class="dev-chat-name">proxymint support</div>
            <div class="dev-chat-status"><span class="live-dot"></span> Online now</div>
          </div>
        </div>
        <div class="dev-chat-body">
          <div class="dev-msg them"><div class="dev-msg-bubble">Hi! Need any help getting started with proxymint?</div><div class="dev-msg-time">Support · Just now</div></div>
          <div class="dev-msg me"><div class="dev-msg-bubble">How do I set up residential proxies with Python?</div><div class="dev-msg-time">You · Just now</div></div>
          <div class="dev-msg them"><div class="dev-msg-bubble">Great choice! Here's the quickest setup — it's literally 5 lines:</div><div class="dev-msg-time">Support · Just now</div></div>
          <div style="background:var(--bg-2);border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin:8px 0;font-family:var(--font-c);font-size:11px;line-height:1.8;color:var(--text-2);transition:background 0.4s,border-color 0.4s;">
            proxies = {'http': 'http://user:pass@gate.proxymint.io:7777'}<br>
            requests.get('https://target.com', proxies=proxies)
          </div>
          <div class="dev-msg them"><div class="dev-msg-bubble">Let me know if you need city-level targeting or sticky sessions too!</div><div class="dev-msg-time">Support · Just now</div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     BEGIN JOURNEY CTA — PURPLE CARD
     ═══════════════════════════════════════════ -->
<div class="pm-cta-journey section">
  <div class="cta-journey-card reveal">
    <div class="cj-left">
      <h2 class="h2" style="color:#fff;">Begin your journey<br>with proxymint</h2>
      <p class="lead lead-white">Join 5,000+ teams already running on proxymint. Start free, scale without limits. No credit card required.</p>
      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="#" class="btn btn-white btn-lg">Start free trial</a>
        <a href="#" class="btn btn-outline-white btn-lg">Talk to sales</a>
      </div>
    </div>
    <div class="cj-right">
      <div class="cj-accordion">
        <div class="cj-item open">
          <div class="cj-item-header">Documentation <svg class="cj-chevron" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg></div>
          <div class="cj-item-body">Full API reference, integration guides, quickstart tutorials, and code examples in 8 languages.</div>
        </div>
        <div class="cj-item">
          <div class="cj-item-header">Proxy API Setup <svg class="cj-chevron" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg></div>
          <div class="cj-item-body">Configure your proxy pool in minutes via our dashboard or REST API — no devops required.</div>
        </div>
        <div class="cj-item">
          <div class="cj-item-header">Scraper API Quickstart <svg class="cj-chevron" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg></div>
          <div class="cj-item-body">Start returning structured JSON from any URL in under 5 minutes using our Web Scraper API.</div>
        </div>
        <div class="cj-item">
          <div class="cj-item-header">Residential Proxy Guide <svg class="cj-chevron" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg></div>
          <div class="cj-item-body">Step-by-step guide to targeting by country, city, ASN, and carrier — with sticky session examples.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     BLOG / NEWS
     ═══════════════════════════════════════════ -->
<section class="section section-bg2" id="blog">
  <div class="wrap">
    <div class="section-hdr reveal" style="display:flex;align-items:flex-end;justify-content:space-between;max-width:100%;margin-bottom:36px;">
      <div>
        <div class="eyebrow">what's new</div>
        <h2 class="h2" style="margin-bottom:0;">Latest from proxymint</h2>
      </div>
      <a href="/blog" class="btn-icon" style="flex-shrink:0;padding-bottom:6px;">View all posts <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="5,12 19,12"/><polyline points="13,6 19,12 13,18"/></svg></a>
    </div>
    <div class="blog-grid reveal">
      <div class="blog-card"><div class="blog-thumb" style="background:linear-gradient(135deg,rgba(107,78,255,0.12),rgba(107,78,255,0.24));"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#7B6EFF" stroke-width="1.5" stroke-linecap="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div><div class="blog-body"><div class="blog-tag">proxy</div><div class="blog-title">How residential proxies are transforming AI data collection in 2026</div><div class="blog-date">Mar 28, 2026</div></div></div>
      <div class="blog-card"><div class="blog-thumb" style="background:linear-gradient(135deg,rgba(42,217,200,0.10),rgba(42,217,200,0.22));"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#2AD9C8" stroke-width="1.5" stroke-linecap="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></div><div class="blog-body"><div class="blog-tag">scraper api</div><div class="blog-title">Web Scraper API vs custom scraping: Which is right for your team?</div><div class="blog-date">Mar 21, 2026</div></div></div>
      <div class="blog-card"><div class="blog-thumb" style="background:linear-gradient(135deg,rgba(245,158,11,0.10),rgba(245,158,11,0.22));"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg></div><div class="blog-body"><div class="blog-tag">web scraping</div><div class="blog-title">The definitive guide to bypassing anti-bot systems in 2026</div><div class="blog-date">Mar 14, 2026</div></div></div>
      <div class="blog-card"><div class="blog-thumb" style="background:linear-gradient(135deg,rgba(236,72,153,0.10),rgba(236,72,153,0.22));"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="1.5" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div><div class="blog-body"><div class="blog-tag">brand protection</div><div class="blog-title">How to detect counterfeit listings at scale with proxymint APIs</div><div class="blog-date">Mar 7, 2026</div></div></div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     FOOTER
     ═══════════════════════════════════════════ -->
<footer class="pm-footer" id="footer">
  <div class="wrap">
    <div class="footer-top">
      <div class="f-brand">
        <a href="#" class="f-brand-logo">
          <img src="/sogerien/page/img/admin_panel/pm-admin-logo-6-full-vector-no-bg.png" style="height:28px;width:auto;" alt="proxymint" onerror="this.style.display='none'">
          <span class="f-brand-wordmark">proxy<span>mint</span></span>
        </a>
        <p class="f-tagline">Premium proxy infrastructure for teams who demand reliability at any scale.</p>
        <div class="f-socials">
          <a href="#" class="f-soc"><svg viewBox="0 0 24 24"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg></a>
          <a href="#" class="f-soc"><svg viewBox="0 0 24 24"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg></a>
          <a href="#" class="f-soc"><svg viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.74l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" fill="currentColor"/></svg></a>
          <a href="#" class="f-soc"><svg viewBox="0 0 24 24" fill="none"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z" fill="currentColor"/></svg></a>
        </div>
      </div>
      <div>
        <div class="f-col-title">Products</div>
        <ul class="f-col-links">
          <li><a href="#">Residential Proxies</a></li><li><a href="#">Datacenter Proxies</a></li>
          <li><a href="#">ISP Proxies</a></li><li><a href="#">Mobile Proxies</a></li>
          <li><a href="#">Web Scraper API</a></li><li><a href="#">Web Unblocker</a></li>
        </ul>
      </div>
      <div>
        <div class="f-col-title">Solutions</div>
        <ul class="f-col-links">
          <li><a href="#">E-commerce</a></li><li><a href="#">Ad Verification</a></li>
          <li><a href="#">Brand Protection</a></li><li><a href="#">Cybersecurity</a></li>
          <li><a href="#">AI &amp; Machine Learning</a></li><li><a href="#">Real Estate</a></li>
        </ul>
      </div>
      <div>
        <div class="f-col-title">Resources</div>
        <ul class="f-col-links">
          <li><a href="#">Documentation</a></li><li><a href="#">API Reference</a></li>
          <li><a href="#">Integration Guides</a></li><li><a href="#">Blog</a></li>
          <li><a href="#">Case Studies</a></li><li><a href="#">Status Page</a></li>
        </ul>
      </div>
      <div>
        <div class="f-col-title">Company</div>
        <ul class="f-col-links">
          <li><a href="#">About us</a></li><li><a href="#">Careers</a></li>
          <li><a href="#">Affiliate Program</a></li><li><a href="#">Trust &amp; Safety</a></li>
          <li><a href="#">Contact</a></li><li><a href="#">Help Center</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="f-copy">© 2026 Routing Tech Ltd · proxymint · All rights reserved</div>
      <div class="f-legal">
        <a href="#">Privacy Policy</a><a href="#">Terms of Service</a>
        <a href="#">Cookie Policy</a><a href="#">KYC Policy</a>
      </div>
      <div class="f-pay">
        <span class="f-pay-icon">Visa</span><span class="f-pay-icon">MC</span>
        <span class="f-pay-icon">GPay</span><span class="f-pay-icon">AMEX</span>
      </div>
    </div>
  </div>
</footer>

  </div><!-- /.pm-admin-shell -->
</div><!-- /.pm-admin-app -->

<!-- ═══════════════════════════════════════════
     PARTICLE SYSTEM — V2-based, V6-tuned (ambient, not demo)
     ═══════════════════════════════════════════ -->
<script>
(function() {
  'use strict';

  function hexRgba(c, a) {
    if (!String(c).startsWith('#')) return c;
    var n = c.replace('#', '');
    if (n.length === 3) n = n.split('').map(function(x){ return x+x; }).join('');
    return 'rgba('+parseInt(n.slice(0,2),16)+','+parseInt(n.slice(2,4),16)+','+parseInt(n.slice(4,6),16)+','+a+')';
  }

  function clamp(v, mn, mx) { return Math.min(mx, Math.max(mn, v)); }

  /* V6 theme configs — tuned for ambient depth, not visible demo effect */
  var THEMES = {
    midnight: {
      colors: ['#1EDBCB','#8B80FF','#CDD4FF','#A8FFF5'],
      connColor: '#6B4EFF',
      mouseGrad: ['#2AD9C8','#7B72FF'],
      glowStops: ['#7B72FF','#2AD9C8'],
      connOp:   0.07,   /* was 0.17 — reduced 60% */
      connW:    0.42,
      mouseOp:  0.20,   /* was 0.62 — reduced 68% */
      glowA:    0.040,
      glowB:    0.012,
      alphaMin: 0.12,
      alphaMax: 0.34,
      coreC:    '#fff',
      coreA:    0.09,
    },
    ice: {
      colors: ['#0F3B42','#118A84','#1AD7C6','#8AD4FF'],
      connColor: '#0F3B42',
      mouseGrad: ['#118A84','#8AD4FF'],
      glowStops: ['#A7E2FF','#1AD7C6'],
      connOp:   0.11,   /* was 0.26 — reduced 58% */
      connW:    0.48,
      mouseOp:  0.07,   /* was 0.28 — reduced 75% */
      glowA:    0.020,
      glowB:    0.006,
      alphaMin: 0.15,
      alphaMax: 0.36,
      coreC:    '#fff',
      coreA:    0.12,
    }
  };

  /* V6 particle settings — calmer density and interaction */
  var DENSITY       = 28000; /* higher = fewer particles (was 15000) */
  var MIN_P         = 14;
  var MAX_P         = 44;    /* was 80 */
  var MIN_SPD       = 0.16;  /* slower drift */
  var MAX_SPD       = 0.30;  /* was 0.5 */
  var CONN_DIST     = 108;   /* was 150 */
  var MOUSE_DIST    = 130;   /* was 200 */
  var MOUSE_INF     = 0.000012; /* was 0.00005 — reduced 76% */
  var GLOW_R        = 34;    /* was 100 */

  var canvas, ctx, W, H, dpr, ps = [], mouse = {x:-2000, y:-2000, on: false};
  var raf, curTheme = THEMES.midnight;
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function mkParticle() {
    var t = curTheme;
    var angle = Math.random() * Math.PI * 2;
    var spd = MIN_SPD + Math.random() * Math.max(MAX_SPD - MIN_SPD, 0.01);
    return {
      x: Math.random() * W,
      y: Math.random() * H,
      vx: Math.cos(angle) * spd,
      vy: Math.sin(angle) * spd,
      r: Math.random() * 1.3 + 0.55,
      c: t.colors[Math.floor(Math.random() * t.colors.length)],
      a: t.alphaMin + Math.random() * Math.max(t.alphaMax - t.alphaMin, 0.05),
    };
  }

  function spawn() {
    var n = clamp(Math.floor(W * H / DENSITY), MIN_P, MAX_P);
    ps = [];
    for (var i = 0; i < n; i++) ps.push(mkParticle());
  }

  function resize() {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    W = window.innerWidth;
    H = window.innerHeight;
    canvas.width  = Math.floor(W * dpr);
    canvas.height = Math.floor(H * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    spawn();
  }

  function draw() {
    raf = requestAnimationFrame(draw);
    ctx.clearRect(0, 0, W, H);
    var t = curTheme;
    var plen = ps.length;

    for (var i = 0; i < plen; i++) {
      var p = ps[i];
      p.x += p.vx; p.y += p.vy;

      /* Bounce at edges */
      if (p.x <= p.r || p.x >= W - p.r) p.vx *= -1;
      if (p.y <= p.r || p.y >= H - p.r) p.vy *= -1;
      p.x = clamp(p.x, p.r, W - p.r);
      p.y = clamp(p.y, p.r, H - p.r);

      /* Subtle mouse influence */
      if (!reduced && mouse.on) {
        var mdx = mouse.x - p.x, mdy = mouse.y - p.y;
        var md  = Math.sqrt(mdx * mdx + mdy * mdy);
        if (md < MOUSE_DIST) {
          p.vx += mdx * MOUSE_INF;
          p.vy += mdy * MOUSE_INF;
          var mv = MAX_SPD * 1.5;
          p.vx = clamp(p.vx, -mv, mv);
          p.vy = clamp(p.vy, -mv, mv);
        }
      }

      /* Particle dot */
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = hexRgba(p.c, p.a);
      ctx.fill();

      /* Core highlight (subtle) */
      if (t.coreA > 0) {
        ctx.beginPath();
        ctx.arc(p.x, p.y, Math.max(0.35, p.r * 0.36), 0, Math.PI * 2);
        ctx.fillStyle = hexRgba(t.coreC, t.coreA);
        ctx.fill();
      }

      /* Connections to siblings */
      for (var j = i + 1; j < plen; j++) {
        var q = ps[j];
        var dx = p.x - q.x, dy = p.y - q.y;
        var d  = Math.sqrt(dx * dx + dy * dy);
        if (d < CONN_DIST) {
          ctx.beginPath();
          ctx.moveTo(p.x, p.y);
          ctx.lineTo(q.x, q.y);
          ctx.strokeStyle = hexRgba(t.connColor, (1 - d / CONN_DIST) * t.connOp);
          ctx.lineWidth   = t.connW;
          ctx.stroke();
        }
      }

      /* Mouse connection lines (very subtle) */
      if (mouse.on && t.mouseOp > 0) {
        var mdd = Math.sqrt((p.x-mouse.x)*(p.x-mouse.x)+(p.y-mouse.y)*(p.y-mouse.y));
        if (mdd < MOUSE_DIST) {
          var mop = (1 - mdd / MOUSE_DIST) * t.mouseOp;
          var gr  = ctx.createLinearGradient(p.x, p.y, mouse.x, mouse.y);
          gr.addColorStop(0, hexRgba(t.mouseGrad[0], mop));
          gr.addColorStop(1, hexRgba(t.mouseGrad[1], mop * 0.5));
          ctx.beginPath();
          ctx.moveTo(p.x, p.y);
          ctx.lineTo(mouse.x, mouse.y);
          ctx.strokeStyle = gr;
          ctx.lineWidth   = 0.8;
          ctx.stroke();
        }
      }
    }

    /* Mouse glow (very subtle) */
    if (mouse.on && GLOW_R > 0 && t.glowA > 0) {
      var gl = ctx.createRadialGradient(mouse.x, mouse.y, 0, mouse.x, mouse.y, GLOW_R);
      gl.addColorStop(0,   hexRgba(t.glowStops[0], t.glowA));
      gl.addColorStop(0.5, hexRgba(t.glowStops[1], t.glowB));
      gl.addColorStop(1,   'rgba(0,0,0,0)');
      ctx.fillStyle = gl;
      ctx.fillRect(mouse.x - GLOW_R, mouse.y - GLOW_R, GLOW_R * 2, GLOW_R * 2);
    }
  }

  function init() {
    var target = document.querySelector('.pm-admin-cosmic');
    if (!target) return;
    canvas = document.createElement('canvas');
    canvas.setAttribute('aria-hidden', 'true');
    canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;';
    target.appendChild(canvas);
    ctx = canvas.getContext('2d');
    resize();
    window.addEventListener('resize', resize, {passive: true});
    window.addEventListener('pointermove', function(e) {
      mouse.x = e.clientX; mouse.y = e.clientY; mouse.on = true;
    }, {passive: true});
    window.addEventListener('pointerleave', function() { mouse.x=-2000;mouse.y=-2000;mouse.on=false; });
    window.addEventListener('blur', function() { mouse.x=-2000;mouse.y=-2000;mouse.on=false; });
    draw();
  }

  /* Public API for theme switching */
  window.pmParticles = {
    setTheme: function(name) {
      curTheme = THEMES[name] || THEMES.midnight;
      spawn();
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>

<!-- ═══════════════════════════════════════════
     THEME TOGGLE — Apple switch logic
     ═══════════════════════════════════════════ -->
<script>
(function() {
  var body       = document.body;
  var sw         = document.getElementById('theme-switch');
  var metaColor  = document.querySelector('meta[data-pm-theme-color]');
  var storageKey = 'pm-v6-theme';

  function getTheme() {
    return body.classList.contains('pm-theme-ice') ? 'ice' : 'midnight';
  }

  function setTheme(theme) {
    var next = (theme === 'ice') ? 'ice' : 'midnight';
    body.classList.remove('pm-theme-ice', 'pm-theme-midnight');
    body.classList.add('pm-theme-' + next);
    if (sw) sw.setAttribute('aria-checked', String(next === 'ice'));
    if (metaColor) metaColor.content = (next === 'ice') ? '#eff6f5' : '#0a101b';
    if (window.pmParticles) window.pmParticles.setTheme(next);
    try { localStorage.setItem(storageKey, next); } catch(e) {}
  }

  function toggleTheme() {
    setTheme(getTheme() === 'midnight' ? 'ice' : 'midnight');
  }

  /* Wire up the switch */
  if (sw) {
    sw.addEventListener('click', toggleTheme);
    sw.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleTheme(); }
    });
  }

  /* Restore from localStorage or use html attr default */
  var init = body.getAttribute('data-pm-default-theme') || 'midnight';
  try {
    var saved = localStorage.getItem(storageKey);
    if (saved === 'ice' || saved === 'midnight') init = saved;
  } catch(e) {}
  setTheme(init);
})();
</script>

<!-- ═══════════════════════════════════════════
     INTERACTIONS
     ═══════════════════════════════════════════ -->
<script>
(function() {
  /* Reveal on scroll */
  var io = new IntersectionObserver(function(entries) {
    entries.forEach(function(e) {
      if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); }
    });
  }, {threshold: 0.1});
  document.querySelectorAll('.reveal').forEach(function(el) { io.observe(el); });

  /* Nav scroll shadow */
  var nav = document.getElementById('pm-nav');
  window.addEventListener('scroll', function() {
    nav.classList.toggle('scrolled', window.scrollY > 10);
  }, {passive: true});

  /* Tab pills */
  document.querySelectorAll('.tab-pill').forEach(function(tab) {
    tab.addEventListener('click', function() {
      document.querySelectorAll('.tab-pill').forEach(function(t) { t.classList.remove('active'); });
      tab.classList.add('active');
    });
  });

  /* Product sub-nav */
  document.querySelectorAll('.psn-item').forEach(function(item) {
    item.addEventListener('click', function() {
      document.querySelectorAll('.psn-item').forEach(function(i) { i.classList.remove('active'); });
      item.classList.add('active');
    });
  });

  /* Network metrics top-line glow */
  document.querySelectorAll('[data-nm]').forEach(function(cell) {
    new IntersectionObserver(function(entries) {
      entries.forEach(function(e) { if (e.isIntersecting) cell.classList.add('lit'); });
    }, {threshold: 0.5}).observe(cell);
  });

  /* CTA accordion */
  document.querySelectorAll('.cj-item-header').forEach(function(hdr) {
    hdr.addEventListener('click', function() {
      var item   = hdr.closest('.cj-item');
      var wasOpen = item.classList.contains('open');
      document.querySelectorAll('.cj-item').forEach(function(i) { i.classList.remove('open'); });
      if (!wasOpen) item.classList.add('open');
    });
  });

  /* Dark band menu */
  document.querySelectorAll('.db-menu li').forEach(function(li) {
    li.addEventListener('click', function() {
      document.querySelectorAll('.db-menu li').forEach(function(l) { l.classList.remove('active'); });
      li.classList.add('active');
    });
  });

  /* Code tabs */
  document.querySelectorAll('.db-code-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      document.querySelectorAll('.db-code-tab').forEach(function(t) { t.classList.remove('active'); });
      tab.classList.add('active');
    });
  });
})();
</script>

</body>
</html>


