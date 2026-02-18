(function () {
  const prefersReducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const saveDataEnabled = !!(navigator.connection && navigator.connection.saveData);
  const lowMemoryDevice = typeof navigator.deviceMemory === "number" && navigator.deviceMemory <= 4;
  const weakCpuDevice = typeof navigator.hardwareConcurrency === "number" && navigator.hardwareConcurrency <= 4;
  const shouldReduceAnimations = prefersReducedMotion || saveDataEnabled;
  const shouldLightenAnimations = shouldReduceAnimations || lowMemoryDevice || weakCpuDevice;

  const applyProgressiveSectionDelays = () => {
    if (shouldLightenAnimations) return;
    const sections = Array.from(document.querySelectorAll("header.hero, section"));
    sections.forEach((section, sectionIndex) => {
      const sectionOffset = Math.min(sectionIndex * 45, 270);
      const animatedEls = Array.from(section.querySelectorAll("[data-aos]"));
      animatedEls.forEach((el) => {
        const currentDelay = Number.parseInt(el.getAttribute("data-aos-delay") || "0", 10);
        const baseDelay = Number.isFinite(currentDelay) ? currentDelay : 0;
        el.setAttribute("data-aos-delay", String(baseDelay + sectionOffset));
      });
    });
  };

  applyProgressiveSectionDelays();

  // AOS
  if (window.AOS) {
    AOS.init({
      duration: shouldLightenAnimations ? 420 : 650,
      once: true,
      offset: shouldLightenAnimations ? 60 : 90,
      easing: "ease-out",
      disable: shouldReduceAnimations
    });
  }

  // Footer year
  const y = document.getElementById("year");
  if (y) y.textContent = new Date().getFullYear();

  // Page loader (fail-safe)
  const hideLoader = () => {
    const loader = document.getElementById("pageLoader");
    if (loader) loader.classList.add("loaded");
  };
  window.addEventListener("load", hideLoader);
  document.addEventListener("DOMContentLoaded", hideLoader);
  setTimeout(hideLoader, 2000);

  // Floating buttons (back-to-top + WhatsApp)
  const btn = document.getElementById("backToTop");
  const whatsappFloat = document.querySelector(".whatsapp-float");
  const toggleFloatingButtons = () => {
    const show = window.scrollY > 120;
    if (btn) {
      btn.style.opacity = show ? "1" : "0";
      btn.style.visibility = show ? "visible" : "hidden";
    }
    if (whatsappFloat) {
      whatsappFloat.style.opacity = show ? "1" : "0";
      whatsappFloat.style.visibility = show ? "visible" : "hidden";
    }
  };
  window.addEventListener("scroll", toggleFloatingButtons, { passive: true });
  toggleFloatingButtons();

  if (btn) {
    btn.addEventListener("click", () => {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }

  // Theme toggle
  const themeBtns = [document.getElementById("themeToggle"), document.getElementById("themeToggleMobile")]
    .filter(Boolean);
  const root = document.documentElement;
  const themeColorMeta = document.querySelector('meta[name="theme-color"]');
  const isValidTheme = (value) => value === "dark" || value === "light";
  const getTheme = () => {
    const current = root.getAttribute("data-theme");
    return isValidTheme(current) ? current : "dark";
  };

  const updateIcon = (t) => {
    themeBtns.forEach((btnEl) => {
      const icon = btnEl.querySelector("i");
      if (icon) icon.className = t === "dark" ? "bi bi-moon-stars" : "bi bi-sun";
    });
  };

  const updateThemeColor = (t) => {
    if (themeColorMeta) themeColorMeta.setAttribute("content", t === "dark" ? "#0b0f19" : "#f8fafc");
  };

  const updateA11y = (t) => {
    const nextLabel = t === "dark" ? "Ativar tema claro" : "Ativar tema escuro";
    themeBtns.forEach((btnEl) => {
      btnEl.setAttribute("aria-pressed", t === "light" ? "true" : "false");
      btnEl.setAttribute("title", nextLabel);
      btnEl.setAttribute("aria-label", nextLabel);
    });
  };

  const applyTheme = (value) => {
    const theme = isValidTheme(value) ? value : "dark";
    root.setAttribute("data-theme", theme);
    try { localStorage.setItem("theme", theme); } catch (e) {}
    updateIcon(theme);
    updateA11y(theme);
    updateThemeColor(theme);
  };

  const prefersLight = window.matchMedia && window.matchMedia("(prefers-color-scheme: light)").matches;
  const fallbackTheme = prefersLight ? "light" : "dark";
  let initialTheme = getTheme();
  try {
    const saved = localStorage.getItem("theme");
    if (isValidTheme(saved)) {
      initialTheme = saved;
    } else if (!isValidTheme(root.getAttribute("data-theme"))) {
      initialTheme = fallbackTheme;
    }
  } catch (e) {
    initialTheme = isValidTheme(root.getAttribute("data-theme")) ? getTheme() : fallbackTheme;
  }
  applyTheme(initialTheme);

  themeBtns.forEach((btnEl) => {
    btnEl.addEventListener("click", () => {
      const next = getTheme() === "dark" ? "light" : "dark";
      applyTheme(next);
    });
  });

  // Copy mode toggle (soft <-> growth)
  const copyModeToggle = document.getElementById("copyModeToggle");
  if (copyModeToggle) {
    const isValidCopyMode = (value) => value === "soft" || value === "growth";
    const getCurrentCopyMode = () => {
      const fromData = copyModeToggle.getAttribute("data-copy-mode");
      return isValidCopyMode(fromData) ? fromData : "soft";
    };

    const updateCopyToggleUi = (mode) => {
      const current = isValidCopyMode(mode) ? mode : "soft";
      const next = current === "growth" ? "soft" : "growth";
      const currentLabel = current === "growth" ? "Growth" : "Soft";
      const nextLabel = next === "growth" ? "Growth" : "Soft";
      copyModeToggle.textContent = `Copy: ${currentLabel}`;
      copyModeToggle.setAttribute("title", `Alternar para ${nextLabel}`);
      copyModeToggle.setAttribute("aria-label", `Alternar copy para modo ${nextLabel}`);
    };

    const buildNextCopyUrl = (nextMode) => {
      const url = new URL(window.location.href);
      if (nextMode === "soft") {
        url.searchParams.delete("copy");
      } else {
        url.searchParams.set("copy", "growth");
      }
      return url.toString();
    };

    updateCopyToggleUi(getCurrentCopyMode());

    copyModeToggle.addEventListener("click", () => {
      const current = getCurrentCopyMode();
      const next = current === "growth" ? "soft" : "growth";
      window.location.assign(buildNextCopyUrl(next));
    });
  }

  // Collapse mobile menu after clicking a nav link
  const nav = document.getElementById("topNav");
  const toggler = document.querySelector('.navbar-toggler[data-bs-target="#topNav"]');
  const isNavOpen = () => nav && nav.classList.contains("show");
  const closeNav = () => {
    if (!nav) return;
    nav.classList.remove("show", "collapsing");
    nav.style.height = "";
    if (toggler) toggler.classList.add("collapsed");
    if (toggler) toggler.setAttribute("aria-expanded", "false");
  };

  if (nav) {
    document.addEventListener("click", (event) => {
      const link = event.target.closest("a.nav-link, a.btn, button.btn");
      if (!link) return;
      if (!nav.contains(link)) return;
      setTimeout(closeNav, 0);
    });
  }

  document.addEventListener("click", (event) => {
    if (!isNavOpen()) return;
    const isToggler = toggler && (event.target === toggler || toggler.contains(event.target));
    if (nav.contains(event.target) || isToggler) return;
    setTimeout(closeNav, 0);
  });

  window.addEventListener("scroll", () => {
    if (!isNavOpen()) return;
    closeNav();
  }, { passive: true });

  window.addEventListener("hashchange", () => {
    setTimeout(closeNav, 0);
  });

  // Active nav link on scroll (single page)
  const navLinks = Array.from(document.querySelectorAll('.navbar .nav-link[href^="#"]'));
  const sections = navLinks
    .map((link) => {
      const id = link.getAttribute("href").slice(1);
      const el = document.getElementById(id);
      return el ? { link, el } : null;
    })
    .filter(Boolean);

  const setActiveLink = () => {
    if (!sections.length) return;
    const offset = 140;
    const scrollPos = window.scrollY + offset;
    let current = sections[0].link;
    for (const s of sections) {
      if (s.el.offsetTop <= scrollPos) current = s.link;
    }
    navLinks.forEach((l) => l.classList.remove("active"));
    current.classList.add("active");
  };

  window.addEventListener("scroll", setActiveLink, { passive: true });
  window.addEventListener("load", setActiveLink);

  // Tablet carousels (projects + testimonials): arrows + pagination dots
  const tabletRange = window.matchMedia("(min-width: 768px) and (max-width: 1366px)");
  const carouselTargets = [
    { sectionId: "projects", gridSelector: ".projects-grid", label: "projetos" },
    { sectionId: "depoimentos", gridSelector: ".testimonials-grid", label: "depoimentos" }
  ];

  const createTabletCarousel = ({ sectionId, gridSelector, label }) => {
    const section = document.getElementById(sectionId);
    if (!section) return null;
    const grid = section.querySelector(gridSelector);
    if (!grid) return null;
    const slides = Array.from(grid.children);
    if (!slides.length) return null;

    const controls = document.createElement("div");
    controls.className = "tablet-carousel-controls";
    controls.setAttribute("role", "group");
    controls.setAttribute("aria-label", `Navegacao de ${label}`);

    const prevBtn = document.createElement("button");
    prevBtn.type = "button";
    prevBtn.className = "tablet-carousel-arrow";
    prevBtn.setAttribute("aria-label", "Slide anterior");
    prevBtn.innerHTML = '<i class="bi bi-chevron-left" aria-hidden="true"></i>';

    const nextBtn = document.createElement("button");
    nextBtn.type = "button";
    nextBtn.className = "tablet-carousel-arrow";
    nextBtn.setAttribute("aria-label", "Proximo slide");
    nextBtn.innerHTML = '<i class="bi bi-chevron-right" aria-hidden="true"></i>';

    const dots = document.createElement("div");
    dots.className = "tablet-carousel-dots";
    dots.setAttribute("aria-label", `Paginacao de ${label}`);

    const dotButtons = slides.map((_, index) => {
      const dot = document.createElement("button");
      dot.type = "button";
      dot.className = "tablet-carousel-dot";
      dot.setAttribute("aria-label", `Ir para slide ${index + 1}`);
      dot.addEventListener("click", () => scrollToIndex(index));
      dots.appendChild(dot);
      return dot;
    });

    controls.appendChild(prevBtn);
    controls.appendChild(dots);
    controls.appendChild(nextBtn);
    grid.insertAdjacentElement("afterend", controls);

    let activeIndex = 0;
    let scrollTicking = false;

    const getNearestIndex = () => {
      const viewportCenter = grid.scrollLeft + (grid.clientWidth / 2);
      let nearest = 0;
      let nearestDiff = Number.POSITIVE_INFINITY;
      slides.forEach((slide, idx) => {
        const slideCenter = slide.offsetLeft + (slide.clientWidth / 2);
        const diff = Math.abs(slideCenter - viewportCenter);
        if (diff < nearestDiff) {
          nearestDiff = diff;
          nearest = idx;
        }
      });
      return nearest;
    };

    const syncUi = () => {
      activeIndex = getNearestIndex();
      prevBtn.disabled = activeIndex <= 0;
      nextBtn.disabled = activeIndex >= slides.length - 1;
      dotButtons.forEach((dot, idx) => {
        dot.classList.toggle("active", idx === activeIndex);
        if (idx === activeIndex) {
          dot.setAttribute("aria-current", "true");
        } else {
          dot.removeAttribute("aria-current");
        }
      });
    };

    const scrollToIndex = (index) => {
      const clamped = Math.max(0, Math.min(index, slides.length - 1));
      const target = slides[clamped];
      if (!target) return;
      grid.scrollTo({
        left: target.offsetLeft,
        behavior: "smooth"
      });
    };

    prevBtn.addEventListener("click", () => scrollToIndex(activeIndex - 1));
    nextBtn.addEventListener("click", () => scrollToIndex(activeIndex + 1));

    grid.addEventListener("scroll", () => {
      if (scrollTicking) return;
      scrollTicking = true;
      window.requestAnimationFrame(() => {
        syncUi();
        scrollTicking = false;
      });
    }, { passive: true });

    const onViewportChange = () => {
      controls.hidden = !tabletRange.matches;
      if (tabletRange.matches) syncUi();
    };
    onViewportChange();

    return { syncUi, onViewportChange };
  };

  const carouselInstances = carouselTargets
    .map(createTabletCarousel)
    .filter(Boolean);

  const refreshCarousels = () => {
    carouselInstances.forEach((instance) => {
      instance.onViewportChange();
      instance.syncUi();
    });
  };

  if (tabletRange.addEventListener) {
    tabletRange.addEventListener("change", refreshCarousels);
  } else if (tabletRange.addListener) {
    tabletRange.addListener(refreshCarousels);
  }
  window.addEventListener("resize", refreshCarousels);
  window.addEventListener("load", refreshCarousels);
})();
