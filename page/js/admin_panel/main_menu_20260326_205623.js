(function () {
    var body = document.body;
    if (!body) {
        return;
    }

    var storageKey = 'proxymint-admin-theme';
    var sidebarToggle = document.querySelector('[data-pm-sidebar-toggle]');
    var sidebarClose = document.querySelector('[data-pm-sidebar-close]');
    var themeButtons = document.querySelectorAll('[data-pm-theme]');
    var navLinks = document.querySelectorAll('.pm-nav-link');
    var cosmicCanvas = document.querySelector('[data-pm-cosmic]');
    var cosmicState = null;
    var cosmicFrame = 0;

    var cosmicThemes = {
        midnight: {
            particles: ['#2AD9C8', '#7188FF', '#CDD4FF'],
            lines: 'rgba(113, 136, 255, 0.22)',
            glow: 'rgba(42, 217, 200, 0.14)',
            background: 'rgba(7, 13, 23, 0.12)'
        },
        ice: {
            particles: ['#1AD7C6', '#8AD4FF', '#118A84'],
            lines: 'rgba(17, 138, 132, 0.16)',
            glow: 'rgba(26, 215, 198, 0.1)',
            background: 'rgba(239, 251, 248, 0.06)'
        }
    };

    function getTheme() {
        return body.classList.contains('pm-theme-ice') ? 'ice' : 'midnight';
    }

    function stopCosmic() {
        if (cosmicFrame) {
            window.cancelAnimationFrame(cosmicFrame);
            cosmicFrame = 0;
        }
        cosmicState = null;
    }

    function resizeCosmic() {
        if (!cosmicCanvas || !cosmicState) {
            return;
        }

        var ratio = window.devicePixelRatio || 1;
        var width = window.innerWidth;
        var height = window.innerHeight;

        cosmicCanvas.width = Math.max(1, Math.floor(width * ratio));
        cosmicCanvas.height = Math.max(1, Math.floor(height * ratio));
        cosmicCanvas.style.width = width + 'px';
        cosmicCanvas.style.height = height + 'px';

        cosmicState.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        cosmicState.width = width;
        cosmicState.height = height;
    }

    function spawnParticle(width, height, palette) {
        return {
            x: Math.random() * width,
            y: Math.random() * height,
            vx: (Math.random() - 0.5) * 0.24,
            vy: (Math.random() - 0.5) * 0.24,
            r: 1.2 + Math.random() * 2.2,
            color: palette[Math.floor(Math.random() * palette.length)]
        };
    }

    function drawCosmic() {
        if (!cosmicState) {
            return;
        }

        var ctx = cosmicState.ctx;
        var width = cosmicState.width;
        var height = cosmicState.height;
        var theme = cosmicState.theme;
        var particles = cosmicState.particles;

        ctx.clearRect(0, 0, width, height);
        ctx.fillStyle = theme.background;
        ctx.fillRect(0, 0, width, height);

        for (var i = 0; i < particles.length; i += 1) {
            var a = particles[i];
            a.x += a.vx;
            a.y += a.vy;

            if (a.x < -20 || a.x > width + 20) {
                a.vx *= -1;
            }
            if (a.y < -20 || a.y > height + 20) {
                a.vy *= -1;
            }

            for (var j = i + 1; j < particles.length; j += 1) {
                var b = particles[j];
                var dx = a.x - b.x;
                var dy = a.y - b.y;
                var distance = Math.sqrt(dx * dx + dy * dy);

                if (distance > 132) {
                    continue;
                }

                ctx.strokeStyle = theme.lines;
                ctx.globalAlpha = 1 - distance / 132;
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(a.x, a.y);
                ctx.lineTo(b.x, b.y);
                ctx.stroke();
            }

            ctx.globalAlpha = 1;
            ctx.fillStyle = a.color;
            ctx.shadowBlur = 16;
            ctx.shadowColor = theme.glow;
            ctx.beginPath();
            ctx.arc(a.x, a.y, a.r, 0, Math.PI * 2);
            ctx.fill();
        }

        ctx.shadowBlur = 0;
        cosmicFrame = window.requestAnimationFrame(drawCosmic);
    }

    function startCosmic(themeKey) {
        if (!cosmicCanvas || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        stopCosmic();

        var ctx = cosmicCanvas.getContext('2d');
        if (!ctx) {
            return;
        }

        var theme = cosmicThemes[themeKey] || cosmicThemes.midnight;
        var particleCount = window.innerWidth < 820 ? 16 : 28;
        var particles = [];

        cosmicState = {
            ctx: ctx,
            width: 0,
            height: 0,
            theme: theme,
            particles: particles
        };

        resizeCosmic();

        for (var i = 0; i < particleCount; i += 1) {
            particles.push(spawnParticle(cosmicState.width, cosmicState.height, theme.particles));
        }

        drawCosmic();
    }

    function setTheme(theme) {
        var nextTheme = theme === 'ice' ? 'ice' : 'midnight';
        body.classList.remove('pm-theme-ice', 'pm-theme-midnight');
        body.classList.add('pm-theme-' + nextTheme);

        try {
            localStorage.setItem(storageKey, nextTheme);
        } catch (e) {}

        startCosmic(nextTheme);
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

    themeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setTheme(button.getAttribute('data-pm-theme') || getTheme());
        });
    });

    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 1080) {
                setSidebar(false);
            }
        });
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1080) {
            setSidebar(false);
        }
        resizeCosmic();
    });

    loadTheme();
})();
