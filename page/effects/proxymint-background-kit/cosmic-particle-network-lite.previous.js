(function () {
  var TAU = Math.PI * 2;

  var cosmicThemes = {
    proxyMintMidnight: {
      name: "ProxyMint Midnight",
      particleColors: ["#1EDBCB", "#7EF4E5", "#6CE7D8", "#B7FFF4"],
      connectionColor: "#1EDBCB",
      mouseGradient: ["#1EDBCB", "#7C9BFF"],
      glowStops: ["#7C9BFF", "#1EDBCB"],
      connectionOpacity: 0.16,
      connectionWidth: 0.55,
      mouseOpacity: 0.34,
      glowAlphaStart: 0.065,
      glowAlphaMid: 0.025,
      particleAlphaMin: 0.22,
      particleAlphaMax: 0.62,
      coreColor: "#FFFFFF",
      coreOpacity: 0.2,
    },
    proxyMintIce: {
      name: "ProxyMint Ice",
      particleColors: ["#0F3B42", "#118A84", "#1AD7C6", "#8AD4FF", "#D8F7FF"],
      connectionColor: "#0F3B42",
      mouseGradient: ["#118A84", "#8AD4FF"],
      glowStops: ["#A7E2FF", "#1AD7C6"],
      connectionOpacity: 0.18,
      connectionWidth: 0.64,
      mouseOpacity: 0.18,
      glowAlphaStart: 0.035,
      glowAlphaMid: 0.014,
      particleAlphaMin: 0.34,
      particleAlphaMax: 0.78,
      coreColor: "#FFFFFF",
      coreOpacity: 0.3,
    },
    cosmicClassic: {
      name: "Cosmic Classic",
      particleColors: ["#4ADE80", "#A855F7", "#22C55E", "#C084FC"],
      connectionColor: "#4ADE80",
      mouseGradient: ["#4ADE80", "#A855F7"],
      glowStops: ["#A855F7", "#4ADE80"],
      connectionOpacity: 0.15,
      connectionWidth: 0.55,
      mouseOpacity: 0.24,
      glowAlphaStart: 0.05,
      glowAlphaMid: 0.02,
      particleAlphaMin: 0.25,
      particleAlphaMax: 0.64,
      coreColor: "#FFFFFF",
      coreOpacity: 0.2,
    },
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
    if (!theme) {
      return cosmicThemes.proxyMintMidnight;
    }

    if (typeof theme === "string" && cosmicThemes[theme]) {
      return cosmicThemes[theme];
    }

    return theme;
  }

  function pick(items, index) {
    return items[index % items.length];
  }

  function createCosmicParticleNetwork(options) {
    var settings = Object.assign(
      {
        target: document.body,
        className: "",
        particleDensity: 32000,
        minParticles: 16,
        maxParticles: 38,
        hardMaxParticles: 38,
        minSpeed: 0.08,
        maxSpeed: 0.18,
        connectionDistance: 160,
        mouseConnectionDistance: 185,
        glowRadius: 80,
        zIndex: 0,
        respectReducedMotion: true,
        maxFps: 24,
        maxDevicePixelRatio: 1.2,
        neighborLimit: 3,
      },
      options || {},
    );

    var theme = resolveTheme(settings.theme);
    var mountNode = resolveElement(settings.target);

    if (!mountNode) {
      throw new Error("CosmicParticleNetwork target element was not found.");
    }

    var canvas = document.createElement("canvas");
    var context = canvas.getContext("2d", { alpha: true });

    if (!context) {
      throw new Error("Canvas 2D context is not supported in this browser.");
    }

    canvas.className = settings.className;
    canvas.setAttribute("aria-hidden", "true");
    canvas.style.position = mountNode === document.body ? "fixed" : "absolute";
    canvas.style.inset = "0";
    canvas.style.width = "100%";
    canvas.style.height = "100%";
    canvas.style.pointerEvents = "none";
    canvas.style.zIndex = String(settings.zIndex);

    if (mountNode !== document.body) {
      var computedPosition = window.getComputedStyle(mountNode).position;

      if (computedPosition === "static") {
        mountNode.style.position = "relative";
      }
    }

    mountNode.appendChild(canvas);

    var reducedMotion =
      settings.respectReducedMotion &&
      window.matchMedia &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var frameDelay = Math.max(1000 / clamp(settings.maxFps || 24, 12, 30), 32);
    var mouse = { x: -1000, y: -1000, active: false, lastSeen: 0 };
    var viewportWidth = 0;
    var viewportHeight = 0;
    var particles = [];
    var links = [];
    var animationFrame = 0;
    var frameTimer = 0;
    var resizeTimer = 0;
    var running = true;

    function getSize() {
      if (mountNode === document.body) {
        viewportWidth = window.innerWidth;
        viewportHeight = window.innerHeight;
        return;
      }

      var bounds = mountNode.getBoundingClientRect();
      viewportWidth = bounds.width;
      viewportHeight = bounds.height;
    }

    function resolveParticleCount() {
      var mobileCap = viewportWidth < 820 ? 24 : settings.hardMaxParticles;
      var hardMax = Math.min(settings.hardMaxParticles, mobileCap);
      var maxParticles = Math.min(settings.maxParticles, hardMax);
      var minParticles = Math.min(settings.minParticles, maxParticles);
      var effectiveDensity = Math.max(settings.particleDensity, 30000);
      var count = Math.round((viewportWidth * viewportHeight) / effectiveDensity);

      return clamp(count, minParticles, maxParticles);
    }

    function createParticle(index, count) {
      var angle = (index / Math.max(count, 1)) * TAU + Math.random() * 0.8;
      var speed =
        settings.minSpeed +
        Math.random() * Math.max(settings.maxSpeed - settings.minSpeed, 0.01);

      return {
        baseX: Math.random() * viewportWidth,
        baseY: Math.random() * viewportHeight,
        x: 0,
        y: 0,
        phaseX: angle,
        phaseY: Math.random() * TAU,
        driftX: 18 + Math.random() * 34,
        driftY: 16 + Math.random() * 30,
        speed: speed,
        radius: 1.05 + Math.random() * 1.75,
        color: pick(theme.particleColors, index),
        alpha:
          (theme.particleAlphaMin || 0.2) +
          Math.random() *
            Math.max(
              (theme.particleAlphaMax || 0.7) -
                (theme.particleAlphaMin || 0.2),
              0.05,
            ),
      };
    }

    function updateParticle(particle, time) {
      particle.x = clamp(
        particle.baseX + Math.sin(time * particle.speed + particle.phaseX) * particle.driftX,
        particle.radius,
        Math.max(particle.radius, viewportWidth - particle.radius),
      );
      particle.y = clamp(
        particle.baseY + Math.cos(time * particle.speed * 0.86 + particle.phaseY) * particle.driftY,
        particle.radius,
        Math.max(particle.radius, viewportHeight - particle.radius),
      );
    }

    function buildLinks() {
      var maxDistance = settings.connectionDistance * 1.35;
      var maxDistanceSquared = maxDistance * maxDistance;
      var used = {};
      var nextLinks = [];

      for (var i = 0; i < particles.length; i += 1) {
        var candidates = [];

        for (var j = 0; j < particles.length; j += 1) {
          if (i === j) {
            continue;
          }

          var dx = particles[i].baseX - particles[j].baseX;
          var dy = particles[i].baseY - particles[j].baseY;
          var distanceSquared = dx * dx + dy * dy;

          if (distanceSquared <= maxDistanceSquared) {
            candidates.push({ index: j, distanceSquared: distanceSquared });
          }
        }

        candidates.sort(function (a, b) {
          return a.distanceSquared - b.distanceSquared;
        });

        for (
          var linkIndex = 0;
          linkIndex < Math.min(settings.neighborLimit, candidates.length);
          linkIndex += 1
        ) {
          var a = Math.min(i, candidates[linkIndex].index);
          var b = Math.max(i, candidates[linkIndex].index);
          var key = a + ":" + b;

          if (!used[key]) {
            used[key] = true;
            nextLinks.push([a, b]);
          }
        }
      }

      links = nextLinks;
    }

    function initializeParticles() {
      var count = resolveParticleCount();

      particles = [];

      for (var i = 0; i < count; i += 1) {
        particles.push(createParticle(i, count));
      }

      buildLinks();
    }

    function resizeCanvas() {
      getSize();

      var dpr = Math.min(window.devicePixelRatio || 1, settings.maxDevicePixelRatio);
      canvas.width = Math.max(1, Math.floor(viewportWidth * dpr));
      canvas.height = Math.max(1, Math.floor(viewportHeight * dpr));
      context.setTransform(dpr, 0, 0, dpr, 0, 0);

      initializeParticles();
      draw(performance.now());
    }

    function drawConnections() {
      if (!links.length) {
        return;
      }

      context.save();
      context.globalAlpha = theme.connectionOpacity || 0.15;
      context.strokeStyle = theme.connectionColor || "#1EDBCB";
      context.lineWidth = theme.connectionWidth || 0.55;
      context.beginPath();

      for (var i = 0; i < links.length; i += 1) {
        var a = particles[links[i][0]];
        var b = particles[links[i][1]];

        if (!a || !b) {
          continue;
        }

        context.moveTo(a.x, a.y);
        context.lineTo(b.x, b.y);
      }

      context.stroke();
      context.restore();
    }

    function drawMouse(time) {
      if (!mouse.active || time - mouse.lastSeen > 1300) {
        mouse.active = false;
        return;
      }

      var distanceLimit = settings.mouseConnectionDistance;
      var distanceLimitSquared = distanceLimit * distanceLimit;
      var drawn = 0;

      context.save();
      context.globalAlpha = theme.mouseOpacity || 0.24;
      context.strokeStyle = theme.mouseGradient ? theme.mouseGradient[0] : theme.connectionColor;
      context.lineWidth = 1;
      context.beginPath();

      for (var i = 0; i < particles.length && drawn < 10; i += 1) {
        var particle = particles[i];
        var dx = particle.x - mouse.x;
        var dy = particle.y - mouse.y;
        var distanceSquared = dx * dx + dy * dy;

        if (distanceSquared <= distanceLimitSquared) {
          context.moveTo(particle.x, particle.y);
          context.lineTo(mouse.x, mouse.y);
          drawn += 1;
        }
      }

      context.stroke();
      context.restore();

      if (settings.glowRadius > 0 && (theme.glowAlphaStart || theme.glowAlphaMid)) {
        var glow = context.createRadialGradient(
          mouse.x,
          mouse.y,
          0,
          mouse.x,
          mouse.y,
          settings.glowRadius,
        );

        glow.addColorStop(0, theme.glowStops ? theme.glowStops[0] : theme.connectionColor);
        glow.addColorStop(0.52, theme.glowStops ? theme.glowStops[1] : theme.connectionColor);
        glow.addColorStop(1, "rgba(0, 0, 0, 0)");

        context.save();
        context.globalAlpha = theme.glowAlphaStart || 0.045;
        context.fillStyle = glow;
        context.fillRect(
          mouse.x - settings.glowRadius,
          mouse.y - settings.glowRadius,
          settings.glowRadius * 2,
          settings.glowRadius * 2,
        );
        context.restore();
      }
    }

    function drawParticles() {
      for (var i = 0; i < particles.length; i += 1) {
        var particle = particles[i];

        context.save();
        context.globalAlpha = particle.alpha;
        context.fillStyle = particle.color;
        context.beginPath();
        context.arc(particle.x, particle.y, particle.radius, 0, TAU);
        context.fill();
        context.restore();

        if (theme.coreOpacity) {
          context.save();
          context.globalAlpha = Math.min(1, theme.coreOpacity + particle.alpha * 0.12);
          context.fillStyle = theme.coreColor || "#FFFFFF";
          context.beginPath();
          context.arc(particle.x, particle.y, Math.max(0.5, particle.radius * 0.36), 0, TAU);
          context.fill();
          context.restore();
        }
      }
    }

    function draw(timestamp) {
      var time = timestamp * 0.001;

      context.clearRect(0, 0, viewportWidth, viewportHeight);

      for (var i = 0; i < particles.length; i += 1) {
        updateParticle(particles[i], reducedMotion ? 0 : time);
      }

      drawConnections();
      drawMouse(timestamp);
      drawParticles();
    }

    function clearSchedule() {
      if (animationFrame) {
        window.cancelAnimationFrame(animationFrame);
        animationFrame = 0;
      }

      if (frameTimer) {
        window.clearTimeout(frameTimer);
        frameTimer = 0;
      }
    }

    function schedule() {
      if (!running || reducedMotion || document.hidden) {
        return;
      }

      frameTimer = window.setTimeout(function () {
        animationFrame = window.requestAnimationFrame(function (timestamp) {
          draw(timestamp);
          schedule();
        });
      }, frameDelay);
    }

    function handlePointerMove(event) {
      if (mountNode === document.body) {
        mouse.x = event.clientX;
        mouse.y = event.clientY;
      } else {
        var bounds = mountNode.getBoundingClientRect();
        mouse.x = event.clientX - bounds.left;
        mouse.y = event.clientY - bounds.top;
      }

      mouse.active = true;
      mouse.lastSeen = performance.now();
    }

    function resetPointer() {
      mouse.x = -1000;
      mouse.y = -1000;
      mouse.active = false;
    }

    function handleResize() {
      window.clearTimeout(resizeTimer);
      resizeTimer = window.setTimeout(resizeCanvas, 120);
    }

    function handleVisibilityChange() {
      clearSchedule();

      if (!document.hidden) {
        draw(performance.now());
        schedule();
      }
    }

    function setTheme(nextTheme) {
      theme = resolveTheme(nextTheme);

      for (var i = 0; i < particles.length; i += 1) {
        particles[i].color = pick(theme.particleColors, i);
        particles[i].alpha =
          (theme.particleAlphaMin || 0.2) +
          Math.random() *
            Math.max(
              (theme.particleAlphaMax || 0.7) -
                (theme.particleAlphaMin || 0.2),
              0.05,
            );
      }

      draw(performance.now());
    }

    function destroy() {
      running = false;
      clearSchedule();
      window.clearTimeout(resizeTimer);
      window.removeEventListener("resize", handleResize);
      window.removeEventListener("pointermove", handlePointerMove);
      window.removeEventListener("pointerleave", resetPointer);
      window.removeEventListener("blur", resetPointer);
      document.removeEventListener("visibilitychange", handleVisibilityChange);

      if (canvas.parentNode) {
        canvas.parentNode.removeChild(canvas);
      }
    }

    resizeCanvas();
    schedule();

    window.addEventListener("resize", handleResize, { passive: true });
    window.addEventListener("pointermove", handlePointerMove, { passive: true });
    window.addEventListener("pointerleave", resetPointer, { passive: true });
    window.addEventListener("blur", resetPointer);
    document.addEventListener("visibilitychange", handleVisibilityChange);

    return {
      destroy: destroy,
      setTheme: setTheme,
      canvas: canvas,
      themes: cosmicThemes,
      mode: "lite",
    };
  }

  window.CosmicParticleNetwork = {
    create: createCosmicParticleNetwork,
    themes: cosmicThemes,
    mode: "lite",
  };
})();
