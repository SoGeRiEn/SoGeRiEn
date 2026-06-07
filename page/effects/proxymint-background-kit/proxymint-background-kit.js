(function () {
  const themeMap = {
    midnight: {
      cosmicTheme: "proxyMintMidnight",
      particleDensity: 15000,
      minParticles: 24,
      maxParticles: 84,
      connectionDistance: 150,
      mouseConnectionDistance: 210,
      mouseInfluence: 0.00005,
      glowRadius: 64,
    },
    ice: {
      cosmicTheme: "proxyMintIce",
      particleDensity: 12800,
      minParticles: 30,
      maxParticles: 96,
      connectionDistance: 176,
      mouseConnectionDistance: 184,
      mouseInfluence: 0.00003,
      glowRadius: 46,
    },
  }

  function resolveElement(target) {
    if (!target) {
      return null
    }

    if (typeof target === "string") {
      return document.querySelector(target)
    }

    return target
  }

  function resolveTheme(theme) {
    return theme === "ice" ? "ice" : "midnight"
  }

  function createLayer(className) {
    const node = document.createElement("div")
    node.className = className
    return node
  }

  function ensureStructure(root) {
    root.classList.add("pm-bg-kit")

    let layers = root.querySelector(":scope > .pm-bg-kit__layers")
    if (!layers) {
      layers = document.createElement("div")
      layers.className = "pm-bg-kit__layers"
      root.prepend(layers)
    }

    let field = layers.querySelector(":scope > .pm-bg-kit__field")
    if (!field) {
      field = createLayer("pm-bg-kit__field")
      layers.appendChild(field)
    }

    let cosmic = layers.querySelector(":scope > .pm-bg-kit__cosmic")
    if (!cosmic) {
      cosmic = createLayer("pm-bg-kit__cosmic")
      layers.appendChild(cosmic)
    }

    let auraOne = layers.querySelector(":scope > .pm-bg-kit__aura--one")
    if (!auraOne) {
      auraOne = createLayer("pm-bg-kit__aura pm-bg-kit__aura--one")
      layers.appendChild(auraOne)
    }

    let auraTwo = layers.querySelector(":scope > .pm-bg-kit__aura--two")
    if (!auraTwo) {
      auraTwo = createLayer("pm-bg-kit__aura pm-bg-kit__aura--two")
      layers.appendChild(auraTwo)
    }

    let grid = layers.querySelector(":scope > .pm-bg-kit__grid")
    if (!grid) {
      grid = createLayer("pm-bg-kit__grid")
      layers.appendChild(grid)
    }

    let vignette = layers.querySelector(":scope > .pm-bg-kit__vignette")
    if (!vignette) {
      vignette = createLayer("pm-bg-kit__vignette")
      layers.appendChild(vignette)
    }

    let content = root.querySelector(":scope > .pm-bg-kit__content")
    if (!content) {
      content = document.createElement("div")
      content.className = "pm-bg-kit__content"

      const children = Array.from(root.childNodes)
      children.forEach(function (child) {
        if (child !== layers) {
          content.appendChild(child)
        }
      })

      root.appendChild(content)
    }

    return {
      layers: layers,
      cosmic: cosmic,
      content: content,
    }
  }

  function createInstance(root, options) {
    const elements = ensureStructure(root)
    const theme = resolveTheme(options.theme)
    let cosmicEffect = null

    root.setAttribute("data-pm-bg-theme", theme)

    function mountCosmic(nextTheme, nextOptions) {
      const preset = themeMap[nextTheme]
      const settings = Object.assign(
        {
          target: elements.cosmic,
          className: "pm-bg-kit__cosmic-canvas",
          theme: preset.cosmicTheme,
          zIndex: 0,
          respectReducedMotion: true,
        },
        preset,
        nextOptions || {},
      )

      delete settings.cosmicTheme

      if (cosmicEffect && typeof cosmicEffect.destroy === "function") {
        cosmicEffect.destroy()
      }

      cosmicEffect = window.CosmicParticleNetwork.create(settings)
    }

    function setTheme(nextTheme, nextOptions) {
      const resolvedTheme = resolveTheme(nextTheme)
      root.setAttribute("data-pm-bg-theme", resolvedTheme)
      mountCosmic(
        resolvedTheme,
        Object.assign({}, options, nextOptions || {}, { theme: resolvedTheme }),
      )
    }

    function destroy() {
      if (cosmicEffect && typeof cosmicEffect.destroy === "function") {
        cosmicEffect.destroy()
      }

      cosmicEffect = null
      delete root.__pmBackgroundKit
    }

    mountCosmic(theme, options)

    return {
      root: root,
      elements: elements,
      setTheme: setTheme,
      destroy: destroy,
    }
  }

  function mount(target, options) {
    if (
      !window.CosmicParticleNetwork ||
      typeof window.CosmicParticleNetwork.create !== "function"
    ) {
      throw new Error(
        "ProxyMintBackgroundKit requires cosmic-particle-network.js to be loaded first.",
      )
    }

    const root = resolveElement(target)
    if (!root) {
      throw new Error("ProxyMintBackgroundKit target element was not found.")
    }

    if (
      root.__pmBackgroundKit &&
      typeof root.__pmBackgroundKit.destroy === "function"
    ) {
      root.__pmBackgroundKit.destroy()
    }

    const instance = createInstance(
      root,
      Object.assign({ theme: "midnight" }, options || {}),
    )
    root.__pmBackgroundKit = instance
    return instance
  }

  function mountAll(selector) {
    const targetSelector = selector || "[data-pm-background-kit]"
    return Array.from(document.querySelectorAll(targetSelector)).map(function (
      node
    ) {
      return mount(node, {
        theme: node.getAttribute("data-pm-background-kit") || "midnight",
      })
    })
  }

  function autoMount() {
    if (
      !window.CosmicParticleNetwork ||
      typeof window.CosmicParticleNetwork.create !== "function"
    ) {
      return
    }

    if (!document.querySelector("[data-pm-background-kit]")) {
      return
    }

    mountAll()
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", autoMount)
  } else {
    autoMount()
  }

  window.ProxyMintBackgroundKit = {
    mount: mount,
    mountAll: mountAll,
    themes: Object.keys(themeMap),
  }
})()
