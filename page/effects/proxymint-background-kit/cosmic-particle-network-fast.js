(function () {
  const cosmicThemes = {
    proxyMintMidnight: {
      name: "ProxyMint Midnight",
      particleColors: ["#1EDBCB", "#7EF4E5", "#6CE7D8", "#B7FFF4"],
      connectionColor: "#1EDBCB",
      mouseGradient: ["#1EDBCB", "#7C9BFF"],
      glowStops: ["#7C9BFF", "#1EDBCB"],
      connectionOpacity: 0.17,
      connectionWidth: 0.55,
      mouseOpacity: 0.62,
      glowAlphaStart: 0.1,
      glowAlphaMid: 0.035,
      particleAlphaMin: 0.22,
      particleAlphaMax: 0.62,
      coreColor: "#FFFFFF",
      coreOpacity: 0.22,
    },
    proxyMintIce: {
      name: "ProxyMint Ice",
      particleColors: ["#0F3B42", "#118A84", "#1AD7C6", "#8AD4FF", "#D8F7FF"],
      connectionColor: "#0F3B42",
      mouseGradient: ["#118A84", "#8AD4FF"],
      glowStops: ["#A7E2FF", "#1AD7C6"],
      connectionOpacity: 0.26,
      connectionWidth: 0.72,
      mouseOpacity: 0.28,
      glowAlphaStart: 0.055,
      glowAlphaMid: 0.02,
      particleAlphaMin: 0.34,
      particleAlphaMax: 0.82,
      coreColor: "#FFFFFF",
      coreOpacity: 0.34,
    },
    cosmicClassic: {
      name: "Cosmic Classic",
      particleColors: ["#4ADE80", "#A855F7", "#22C55E", "#C084FC"],
      connectionColor: "#4ADE80",
      mouseGradient: ["#4ADE80", "#A855F7"],
      glowStops: ["#A855F7", "#4ADE80"],
    },
  }

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value))
  }

  function hexToRgba(color, alpha) {
    if (!String(color).startsWith("#")) {
      return color
    }

    const normalized = color.replace("#", "")
    const hex =
      normalized.length === 3
        ? normalized
            .split("")
            .map(function (char) {
              return char + char
            })
            .join("")
        : normalized

    const red = parseInt(hex.slice(0, 2), 16)
    const green = parseInt(hex.slice(2, 4), 16)
    const blue = parseInt(hex.slice(4, 6), 16)

    return "rgba(" + red + ", " + green + ", " + blue + ", " + alpha + ")"
  }

  function resolveElement(target) {
    if (!target) {
      return document.body
    }

    if (typeof target === "string") {
      return document.querySelector(target)
    }

    return target
  }

  function resolveTheme(theme) {
    if (!theme) {
      return cosmicThemes.proxyMintMidnight
    }

    if (theme === "midnight") {
      return cosmicThemes.proxyMintMidnight
    }

    if (theme === "ice") {
      return cosmicThemes.proxyMintIce
    }

    if (typeof theme === "string" && cosmicThemes[theme]) {
      return cosmicThemes[theme]
    }

    return theme
  }

  function createCosmicParticleNetwork(options) {
    const settings = Object.assign(
      {
        target: document.body,
        className: "",
        particleDensity: 15000,
        minParticles: 24,
        maxParticles: 80,
        minSpeed: 0.25,
        maxSpeed: 0.5,
        connectionDistance: 150,
        mouseConnectionDistance: 200,
        mouseInfluence: 0.00005,
        glowRadius: 100,
        zIndex: 0,
        respectReducedMotion: true,
      },
      options || {},
    )

    let theme = resolveTheme(settings.theme)
    const mountNode = resolveElement(settings.target)

    if (!mountNode) {
      throw new Error("CosmicParticleNetwork target element was not found.")
    }

    const canvas = document.createElement("canvas")
    const context = canvas.getContext("2d")

    if (!context) {
      throw new Error("Canvas 2D context is not supported in this browser.")
    }

    canvas.className = settings.className
    canvas.setAttribute("aria-hidden", "true")
    canvas.style.position = mountNode === document.body ? "fixed" : "absolute"
    canvas.style.inset = "0"
    canvas.style.width = "100%"
    canvas.style.height = "100%"
    canvas.style.pointerEvents = "none"
    canvas.style.zIndex = String(settings.zIndex)

    if (mountNode !== document.body) {
      const computedPosition = window.getComputedStyle(mountNode).position

      if (computedPosition === "static") {
        mountNode.style.position = "relative"
      }
    }

    mountNode.appendChild(canvas)

    const reducedMotion =
      settings.respectReducedMotion &&
      window.matchMedia &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches

    const mouse = { x: -1000, y: -1000, active: false }
    let viewportWidth = 0
    let viewportHeight = 0
    let particles = []
    let animationFrame = 0

    function getSize() {
      if (mountNode === document.body) {
        viewportWidth = window.innerWidth
        viewportHeight = window.innerHeight
        return
      }

      const bounds = mountNode.getBoundingClientRect()
      viewportWidth = bounds.width
      viewportHeight = bounds.height
    }

    function createParticle() {
      const angle = Math.random() * Math.PI * 2
      const speed =
        settings.minSpeed +
        Math.random() * Math.max(settings.maxSpeed - settings.minSpeed, 0.01)

      return {
        x: Math.random() * viewportWidth,
        y: Math.random() * viewportHeight,
        vx: Math.cos(angle) * speed,
        vy: Math.sin(angle) * speed,
        radius: Math.random() * 2 + 1,
        color:
          theme.particleColors[
            Math.floor(Math.random() * theme.particleColors.length)
          ],
        alpha:
          (theme.particleAlphaMin || 0.2) +
          Math.random() *
            Math.max(
              (theme.particleAlphaMax || 0.7) -
                (theme.particleAlphaMin || 0.2),
              0.05,
            ),
      }
    }

    function initializeParticles() {
      const count = clamp(
        Math.floor((viewportWidth * viewportHeight) / settings.particleDensity),
        settings.minParticles,
        settings.maxParticles,
      )

      particles = Array.from({ length: count }, createParticle)
    }

    function resizeCanvas() {
      getSize()

      const dpr = Math.min(window.devicePixelRatio || 1, 2)
      canvas.width = Math.floor(viewportWidth * dpr)
      canvas.height = Math.floor(viewportHeight * dpr)
      context.setTransform(dpr, 0, 0, dpr, 0, 0)

      initializeParticles()
    }

    function handlePointerMove(event) {
      if (mountNode === document.body) {
        mouse.x = event.clientX
        mouse.y = event.clientY
      } else {
        const bounds = mountNode.getBoundingClientRect()
        mouse.x = event.clientX - bounds.left
        mouse.y = event.clientY - bounds.top
      }

      mouse.active = true
    }

    function resetPointer() {
      mouse.x = -1000
      mouse.y = -1000
      mouse.active = false
    }

    function animate() {
      context.clearRect(0, 0, viewportWidth, viewportHeight)

      for (let i = 0; i < particles.length; i += 1) {
        const particle = particles[i]

        particle.x += particle.vx
        particle.y += particle.vy

        if (
          particle.x <= particle.radius ||
          particle.x >= viewportWidth - particle.radius
        ) {
          particle.vx *= -1
        }

        if (
          particle.y <= particle.radius ||
          particle.y >= viewportHeight - particle.radius
        ) {
          particle.vy *= -1
        }

        particle.x = clamp(
          particle.x,
          particle.radius,
          viewportWidth - particle.radius,
        )
        particle.y = clamp(
          particle.y,
          particle.radius,
          viewportHeight - particle.radius,
        )

        if (!reducedMotion && mouse.active) {
          const mouseDx = mouse.x - particle.x
          const mouseDy = mouse.y - particle.y
          const mouseDistance = Math.hypot(mouseDx, mouseDy)

          if (mouseDistance < settings.mouseConnectionDistance) {
            particle.vx += mouseDx * settings.mouseInfluence
            particle.vy += mouseDy * settings.mouseInfluence

            const maxVelocity = settings.maxSpeed * 1.75
            particle.vx = clamp(particle.vx, -maxVelocity, maxVelocity)
            particle.vy = clamp(particle.vy, -maxVelocity, maxVelocity)
          }
        }

        context.beginPath()
        context.arc(particle.x, particle.y, particle.radius, 0, Math.PI * 2)
        context.fillStyle = hexToRgba(particle.color, particle.alpha)
        context.fill()

        if (theme.coreOpacity) {
          context.beginPath()
          context.arc(
            particle.x,
            particle.y,
            Math.max(0.55, particle.radius * 0.38),
            0,
            Math.PI * 2,
          )
          context.fillStyle = hexToRgba(
            theme.coreColor || "#FFFFFF",
            Math.min(1, theme.coreOpacity + particle.alpha * 0.18),
          )
          context.fill()
        }

        for (let j = i + 1; j < particles.length; j += 1) {
          const sibling = particles[j]
          const distance = Math.hypot(
            particle.x - sibling.x,
            particle.y - sibling.y,
          )

          if (distance < settings.connectionDistance) {
            const opacity =
              (1 - distance / settings.connectionDistance) *
              (theme.connectionOpacity || 0.15)
            context.beginPath()
            context.moveTo(particle.x, particle.y)
            context.lineTo(sibling.x, sibling.y)
            context.strokeStyle = hexToRgba(theme.connectionColor, opacity)
            context.lineWidth = theme.connectionWidth || 0.5
            context.stroke()
          }
        }

        if (mouse.active) {
          const mouseDistance = Math.hypot(
            particle.x - mouse.x,
            particle.y - mouse.y,
          )

          if (
            mouseDistance < settings.mouseConnectionDistance &&
            (theme.mouseOpacity || 0) > 0
          ) {
            const opacity =
              (1 - mouseDistance / settings.mouseConnectionDistance) *
              (theme.mouseOpacity || 0.6)
            const gradient = context.createLinearGradient(
              particle.x,
              particle.y,
              mouse.x,
              mouse.y,
            )

            gradient.addColorStop(0, hexToRgba(theme.mouseGradient[0], opacity))
            gradient.addColorStop(1, hexToRgba(theme.mouseGradient[1], opacity))

            context.beginPath()
            context.moveTo(particle.x, particle.y)
            context.lineTo(mouse.x, mouse.y)
            context.strokeStyle = gradient
            context.lineWidth = 1.5
            context.stroke()
          }
        }
      }

      if (
        mouse.active &&
        settings.glowRadius > 0 &&
        ((theme.glowAlphaStart || 0) > 0 || (theme.glowAlphaMid || 0) > 0)
      ) {
        const glow = context.createRadialGradient(
          mouse.x,
          mouse.y,
          0,
          mouse.x,
          mouse.y,
          settings.glowRadius,
        )

        glow.addColorStop(
          0,
          hexToRgba(theme.glowStops[0], theme.glowAlphaStart || 0.15),
        )
        glow.addColorStop(
          0.5,
          hexToRgba(theme.glowStops[1], theme.glowAlphaMid || 0.05),
        )
        glow.addColorStop(1, "rgba(0, 0, 0, 0)")

        context.fillStyle = glow
        context.fillRect(
          mouse.x - settings.glowRadius,
          mouse.y - settings.glowRadius,
          settings.glowRadius * 2,
          settings.glowRadius * 2,
        )
      }

      animationFrame = window.requestAnimationFrame(animate)
    }

    function setTheme(nextTheme) {
      theme = resolveTheme(nextTheme)
      initializeParticles()
    }

    function destroy() {
      window.cancelAnimationFrame(animationFrame)
      window.removeEventListener("resize", resizeCanvas)
      window.removeEventListener("pointermove", handlePointerMove)
      window.removeEventListener("pointerleave", resetPointer)
      window.removeEventListener("blur", resetPointer)

      if (canvas.parentNode) {
        canvas.parentNode.removeChild(canvas)
      }
    }

    resizeCanvas()
    animate()

    window.addEventListener("resize", resizeCanvas)
    window.addEventListener("pointermove", handlePointerMove)
    window.addEventListener("pointerleave", resetPointer)
    window.addEventListener("blur", resetPointer)

    return {
      destroy: destroy,
      setTheme: setTheme,
      canvas: canvas,
      themes: cosmicThemes,
    }
  }

  window.CosmicParticleNetwork = {
    create: createCosmicParticleNetwork,
    themes: cosmicThemes,
  }
})()
