(function () {
    function initMainMenu() {
        var body = document.body;
        if (!body) {
            return;
        }

        var storageKey = 'proxymint-admin-theme';
        var sidebarToggle = document.querySelector('[data-pm-sidebar-toggle]');
        var sidebarClose = document.querySelector('[data-pm-sidebar-close]');
        var themeButtons = document.querySelectorAll('[data-pm-theme]');
        var navLinks = document.querySelectorAll('.pm-nav-link');
        var cosmicRoot = document.querySelector('[data-pm-cosmic]');
        var themeColorMeta = document.querySelector('meta[data-pm-theme-color]');
        var cosmicEffect = null;
        var cosmicThemeMap = {
            midnight: 'proxyMintMidnight',
            ice: 'proxyMintIce'
        };

        function getTheme() {
            return body.classList.contains('pm-theme-ice') ? 'ice' : 'midnight';
        }

        function resolveCosmicTheme(themeKey) {
            return cosmicThemeMap[themeKey] || cosmicThemeMap.midnight;
        }

        function destroyCosmic() {
            if (!cosmicEffect || typeof cosmicEffect.destroy !== 'function') {
                cosmicEffect = null;
                return;
            }

            cosmicEffect.destroy();
            cosmicEffect = null;
        }

        function initCosmic(themeKey) {
            if (!cosmicRoot || !window.CosmicParticleNetwork || typeof window.CosmicParticleNetwork.create !== 'function') {
                body.classList.add('pm-cosmic-fallback');
                return;
            }

            destroyCosmic();

            try {
                cosmicEffect = window.CosmicParticleNetwork.create({
                    target: cosmicRoot,
                    className: 'pm-admin-cosmic-canvas',
                    theme: resolveCosmicTheme(themeKey),
                    zIndex: 0,
                    respectReducedMotion: false
                });

                body.classList.remove('pm-cosmic-fallback');
            } catch (e) {
                cosmicEffect = null;
                body.classList.add('pm-cosmic-fallback');
            }
        }

        function syncCosmicTheme(themeKey) {
            initCosmic(themeKey);
        }

        function setTheme(theme) {
            var nextTheme = theme === 'ice' ? 'ice' : 'midnight';

            body.classList.remove('pm-theme-ice', 'pm-theme-midnight');
            body.classList.add('pm-theme-' + nextTheme);

            if (document.documentElement) {
                document.documentElement.style.colorScheme = nextTheme === 'ice' ? 'only light' : 'only dark';
            }

            if (themeColorMeta) {
                themeColorMeta.setAttribute('content', nextTheme === 'ice' ? '#eef6f3' : '#0a101b');
            }

            try {
                localStorage.setItem(storageKey, nextTheme);
            } catch (e) {}

            syncCosmicTheme(nextTheme);
        }

        function loadTheme() {
            var nextTheme = body.getAttribute('data-pm-default-theme') || 'midnight';

            try {
                var saved = localStorage.getItem(storageKey);
                if (saved === 'ice' || saved === 'midnight') {
                    nextTheme = saved;
                }
            } catch (e) {}

            setTheme(nextTheme);
        }

        function setSidebar(open) {
            body.classList.toggle('pm-sidebar-open', !!open);

            if (sidebarToggle) {
                sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                setSidebar(!body.classList.contains('pm-sidebar-open'));
            });
        }

        if (sidebarClose) {
            sidebarClose.addEventListener('click', function () {
                setSidebar(false);
            });
        }

        if (themeButtons && themeButtons.length) {
            var buttonIndex = 0;
            for (buttonIndex = 0; buttonIndex < themeButtons.length; buttonIndex += 1) {
                themeButtons[buttonIndex].addEventListener('click', function () {
                    setTheme(this.getAttribute('data-pm-theme') || getTheme());
                });
            }
        }

        if (navLinks && navLinks.length) {
            var linkIndex = 0;
            for (linkIndex = 0; linkIndex < navLinks.length; linkIndex += 1) {
                navLinks[linkIndex].addEventListener('click', function () {
                    if (window.innerWidth <= 1080) {
                        setSidebar(false);
                    }
                });
            }
        }

        window.addEventListener('resize', function () {
            if (window.innerWidth > 1080) {
                setSidebar(false);
            }
        });

        window.addEventListener('pageshow', function () {
            if (!cosmicEffect) {
                initCosmic(getTheme());
            }
        });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && !cosmicEffect) {
                initCosmic(getTheme());
            }
        });

        loadTheme();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMainMenu);
        return;
    }

    initMainMenu();
})();
