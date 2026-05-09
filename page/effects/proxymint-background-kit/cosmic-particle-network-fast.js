(function () {
  "use strict";

  var TAU = Math.PI * 2;
  var STYLE_ID = "pm-cosmic-fast-style";

  var cosmicThemes = {
    proxyMintMidnight: {
      particleColors: ["#1EDBCB", "#7EF4E5", "#6CE7D8", "#B7FFF4"],
      connectionColor: "#1EDBCB",
      figureColor: "#7C9BFF",
      glowColor: "#1EDBCB",
      particleAlphaMin: 0.32,
      particleAlphaMax: 0.74,
      connectionOpacity: 0.2,
      figureOpacity: 0.09,
      glowOpacity: 0.12
    },
    proxyMintIce: {
      particleColors: ["#0F3B42", "#118A84", "#1AD7C6", "#8AD4FF", "#D8F7FF"],
      connectionColor: "#0F3B42",
      figureColor: "#1AD7C6",
      glowColor: "#8AD4FF",
      particleAlphaMin: 0.38,
      particleAlphaMax: 0.8,
      connectionOpacity: 0.18,
      figureOpacity: 0.075,
      glowOpacity: 0.1
    },
    cosmicClassic: {
      particleColors: ["#4ADE80", "#A855F7", "#22C55E", "#C084FC"],
      connectionColor: "#4ADE80",
      figureColor: "#A855F7",
      glowColor: "#4ADE80",
      particleAlphaMin: 0.3,
      particleAlphaMax: 0.68,
      connectionOpacity: 0.18,
      figureOpacity: 0.085,
      glowOpacity: 0.11
    }
  };

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  function resolveElement(target) {
    if (!target) {
      return document.body;
    }

    if (typeof target === "string") {
      return document.querySelector(target);
    }

    return target;
  }

  function resolveTheme(theme) {
    if (theme === "midnight") {
      return cosmicThemes.proxyMintMidnight;
    }

    if (theme === "ice") {
      return cosmicThemes.proxyMintIce;
    }

    if (typeof theme === "string" && cosmicThemes[theme]) {
      return cosmicThemes[theme];
    }

    return theme || cosmicThemes.proxyMintMidnight;
  }

  function pick(items, index) {
    return items[index % items.length];
  }

  function injectStyle() {
    if (document.getElementById(STYLE_ID)) {
      return;
    }

    var style = document.createElement("style");
    style.id = STYLE_ID;
    style.textContent =
      "@keyframes pmCosmicFastDriftA{0%{transform:translate3d(-1.6%,-1.2%,0) scale(1.045) rotate(0deg)}50%{transform:translate3d(1.2%,1.4%,0) scale(1.055) rotate(.18deg)}100%{transform:translate3d(-1.6%,-1.2%,0) scale(1.045) rotate(0deg)}}" +
      "@keyframes pmCosmicFastDriftB{0%{transform:translate3d(1.4%,-.8%,0) scale(1.04) rotate(.1deg)}50%{transform:translate3d(-1.1%,1.1%,0) scale(1.052) rotate(-.14deg)}100%{transform:translate3d(1.4%,-.8%,0) scale(1.04) rotate(.1deg)}}" +
      ".pm-cosmic-fast-layer{position:absolute;inset:-5%;width:110%;height:110%;pointer-events:none;will-change:transform;backface-visibility:hidden}" +
      ".pm-cosmic-fast-layer-a{animation:pmCosmicFastDriftA 34s ease-in-out infinite}" +
      ".pm-cosmic-fast-layer-b{animation:pmCosmicFastDriftB 46s ease-in-out infinite;mix-blend-mode:screen;opacity:.72}" +
      "@media (prefers-reduced-motion: reduce){.pm-cosmic-fast-layer{animation:none!important}}";

    document.head.appendChild(style);
  }

  function createCanvas(className, layerClassName, zIndex) {
    var canvas = document.createElement("canvas");
    canvas.className = (className ? className + " " : "") + layerClassName;
    canvas.setAttribute("aria-hidden", "true");
    canvas.style.zIndex = String(zIndex);
    return canvas;
  }

  function createParticle(width, height, index, count, theme, layer) {
    var angle = (index / Math.max(count, 1)) * TAU;
    var radius = Math.min(width, height) * (0.2 + Math.random() * 0.34);

    return {
      x: width * 0.5 + Math.cos(angle) * radius + (Math.random() - 0.5) * width * 0.34,
      y: height * 0.5 + Math.sin(angle * 1.17) * radius + (Math.random() - 0.5) * height * 0.34,
      radius: (layer === 0 ? 1.05 : 0.85) + Math.random() * (layer === 0 ? 1.95 : 1.35),
      color: pick(theme.particleColors, index + layer),
      alpha: theme.particleAlphaMin + Math.random() * Math.max(theme.particleAlphaMax - theme.particleAlphaMin, 0.05)
    };
  }

  function drawGlow(context, width, height, theme, layer) {
    var points = layer === 0
      ? [[0.1, 0.2, 0.34], [0.14, 0.84, 0.32], [0.9, 0.9, 0.28]]
      : [[0.78, 0.16, 0.24], [0.42, 0.62, 0.18]];

    for (var i = 0; i < points.length; i += 1) {
      var x = width * points[i][0];
      var y = height * points[i][1];
      var radius = Math.min(width, height) * points[i][2];
      var gradient = context.createRadialGradient(x, y, 0, x, y, radius);

      gradient.addColorStop(0, theme.glowColor);
      gradient.addColorStop(1, "rgba(0,0,0,0)");
      context.save();
      context.globalAlpha = theme.glowOpacity * (layer === 0 ? 1 : 0.62);
      context.fillStyle = gradient;
      context.fillRect(x - radius, y - radius, radius * 2, radius * 2);
      context.restore();
    }
  }

  function drawFigures(context, width, height, theme, layer) {
    var count = layer === 0 ? 5 : 4;

    context.save();
    context.strokeStyle = theme.figureColor;
    context.fillStyle = theme.figureColor;
    context.lineWidth = layer === 0 ? 1 : 0.75;

    for (var i = 0; i < count; i += 1) {
      var x = width * (0.12 + Math.random() * 0.76);
      var y = height * (0.1 + Math.random() * 0.78);
      var size = Math.min(width, height) * (0.055 + Math.random() * 0.075);
      var sides = i % 3 === 0 ? 3 : 4;
      var start = Math.random() * TAU;

      context.beginPath();

      for (var side = 0; side < sides; side += 1) {
        var angle = start + (side / sides) * TAU;
        var px = x + Math.cos(angle) * size;
        var py = y + Math.sin(angle) * size;

        if (side === 0) {
          context.moveTo(px, py);
        } else {
          context.lineTo(px, py);
        }
      }

      context.closePath();
      context.globalAlpha = theme.figureOpacity * 0.72;
      context.fill();
      context.globalAlpha = theme.figureOpacity * 2.2;
      context.stroke();
    }

    context.restore();
  }

  function drawConnections(context, particles, theme, maxDistance) {
    var maxDistanceSquared = maxDistance * maxDistance;

    context.save();
    context.globalAlpha = theme.connectionOpacity;
    context.strokeStyle = theme.connectionColor;
    context.lineWidth = 0.7;
    context.beginPath();

    for (var i = 0; i < particles.length; i += 1) {
      var linked = 0;

      for (var j = i + 1; j < particles.length && linked < 3; j += 1) {
        var dx = particles[i].x - particles[j].x;
        var dy = particles[i].y - particles[j].y;

        if (dx * dx + dy * dy <= maxDistanceSquared) {
          context.moveTo(particles[i].x, particles[i].y);
          context.lineTo(particles[j].x, particles[j].y);
          linked += 1;
        }
      }
    }

    context.stroke();
    context.restore();
  }

  function drawParticles(context, particles) {
    for (var i = 0; i < particles.length; i += 1) {
      var particle = particles[i];

      context.save();
      context.globalAlpha = particle.alpha;
      context.fillStyle = particle.color;
      context.beginPath();
      context.arc(particle.x, particle.y, particle.radius, 0, TAU);
      context.fill();
      context.globalAlpha = Math.min(1, particle.alpha + 0.18);
      context.fillStyle = "#fff";
      context.beginPath();
      context.arc(particle.x, particle.y, Math.max(0.45, particle.radius * 0.32), 0, TAU);
      context.fill();
      context.restore();
    }
  }

  function renderLayer(canvas, theme, options, layer) {
    var parentBounds = canvas.parentElement
      ? canvas.parentElement.getBoundingClientRect()
      : canvas.getBoundingClientRect();
    var width = Math.max(1, Math.ceil(parentBounds.width * 1.1));
    var height = Math.max(1, Math.ceil(parentBounds.height * 1.1));
    var scale = Math.min(window.devicePixelRatio || 1, options.maxDevicePixelRatio);
    var context = canvas.getContext("2d", { alpha: true });

    if (!context) {
      return;
    }

    canvas.width = Math.max(1, Math.floor(width * scale));
    canvas.height = Math.max(1, Math.floor(height * scale));
    context.setTransform(scale, 0, 0, scale, 0, 0);
    context.clearRect(0, 0, width, height);

    var baseCount = Math.round((width * height) / options.particleDensity);
    var count = clamp(baseCount, options.minParticles, layer === 0 ? options.maxParticles : Math.ceil(options.maxParticles * 0.55));
    var particles = [];

    for (var i = 0; i < count; i += 1) {
      particles.push(createParticle(width, height, i, count, theme, layer));
    }

    drawGlow(context, width, height, theme, layer);
    drawFigures(context, width, height, theme, layer);
    drawConnections(context, particles, theme, options.connectionDistance * (layer === 0 ? 1 : 1.18));
    drawParticles(context, particles);
  }

  function createCosmicParticleNetwork(options) {
    var settings = Object.assign(
      {
        target: document.body,
        className: "",
        particleDensity: 38000,
        minParticles: 18,
        maxParticles: 42,
        connectionDistance: 170,
        zIndex: 0,
        respectReducedMotion: true,
        maxDevicePixelRatio: 1
      },
      options || {}
    );
    var mountNode = resolveElement(settings.target);
    var theme = resolveTheme(settings.theme);
    var resizeTimer = 0;
    var destroyed = false;
    var layerA = createCanvas(settings.className, "pm-cosmic-fast-layer pm-cosmic-fast-layer-a", settings.zIndex);
    var layerB = createCanvas(settings.className, "pm-cosmic-fast-layer pm-cosmic-fast-layer-b", settings.zIndex);

    if (!mountNode) {
      throw new Error("CosmicParticleNetwork target element was not found.");
    }

    injectStyle();

    if (mountNode !== document.body && window.getComputedStyle(mountNode).position === "static") {
      mountNode.style.position = "relative";
    }

    mountNode.appendChild(layerA);
    mountNode.appendChild(layerB);

    function render() {
      if (destroyed) {
        return;
      }

      try {
        renderLayer(layerA, theme, settings, 0);
      } catch (error) {
        layerA.setAttribute("data-pm-cosmic-error", error && error.message ? error.message : "render");
      }

      try {
        renderLayer(layerB, theme, settings, 1);
      } catch (error) {
        layerB.setAttribute("data-pm-cosmic-error", error && error.message ? error.message : "render");
      }
    }

    function handleResize() {
      window.clearTimeout(resizeTimer);
      resizeTimer = window.setTimeout(render, 180);
    }

    function setTheme(nextTheme) {
      theme = resolveTheme(nextTheme);
      render();
    }

    function destroy() {
      destroyed = true;
      window.clearTimeout(resizeTimer);
      window.removeEventListener("resize", handleResize);

      if (layerA.parentNode) {
        layerA.parentNode.removeChild(layerA);
      }

      if (layerB.parentNode) {
        layerB.parentNode.removeChild(layerB);
      }
    }

    render();
    window.requestAnimationFrame(render);
    window.setTimeout(render, 120);
    window.addEventListener("resize", handleResize, { passive: true });

    return {
      destroy: destroy,
      setTheme: setTheme,
      canvas: layerA,
      themes: cosmicThemes,
      mode: "fast"
    };
  }

  window.CosmicParticleNetwork = {
    create: createCosmicParticleNetwork,
    themes: cosmicThemes,
    mode: "fast"
  };
})();
