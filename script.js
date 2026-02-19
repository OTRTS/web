const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

const host = String(location.hostname || "");
const isStaticHost = /\.github\.io$/i.test(host);

const toastEl = $("#toast");
let toastTimeout = null;

const showToast = (message) => {
  if (!toastEl) return;
  toastEl.textContent = message;
  toastEl.classList.add("show");
  window.clearTimeout(toastTimeout);
  toastTimeout = window.setTimeout(() => {
    toastEl.classList.remove("show");
  }, 2400);
};

const initYear = () => {
  const year = $("#year");
  if (year) year.textContent = String(new Date().getFullYear());
};

const initPageLoader = () => {
  const loader = $("#page-loader");
  if (!loader) return;

  const hide = () => {
    if (loader.classList.contains("is-hidden")) return;
    loader.classList.add("is-hidden");
    window.setTimeout(() => {
      loader.remove();
    }, 450);
  };

  window.addEventListener("load", () => {
    window.setTimeout(hide, 250);
  });
};

const initMobileNav = () => {
  const toggle = $(".nav-toggle");
  const menu = $("#site-nav-more");
  if (!toggle || !menu) return;

  const setOpen = (open) => {
    menu.hidden = !open;
    menu.classList.toggle("is-open", open);
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    toggle.setAttribute("aria-label", open ? "Cerrar menú" : "Abrir menú");
    if (open) {
      const firstLink = menu.querySelector("a");
      if (firstLink instanceof HTMLElement) firstLink.focus();
    }
  };

  setOpen(false);
  window.addEventListener("pageshow", () => setOpen(false));

  toggle.addEventListener("click", (e) => {
    e.preventDefault();
    e.stopPropagation();
    const open = toggle.getAttribute("aria-expanded") !== "true";
    setOpen(open);
  });

  $$("#site-nav-more a").forEach((a) => {
    a.addEventListener("click", () => setOpen(false));
  });

  document.addEventListener("click", (e) => {
    const open = toggle.getAttribute("aria-expanded") === "true";
    if (!open) return;
    const target = e.target;
    if (!(target instanceof Node)) return;
    if (menu.contains(target) || toggle.contains(target)) return;
    setOpen(false);
  });

  window.addEventListener("keydown", (e) => {
    if (e.key === "Escape") setOpen(false);
  });
};

const initDisablePhpLinks = () => {
  if (!isStaticHost) return;
  const links = $$("a[href$='.php']");
  links.forEach((a) => {
    if (!(a instanceof HTMLAnchorElement)) return;
    a.href = "#";
    a.setAttribute("aria-disabled", "true");
    a.addEventListener("click", (e) => {
      e.preventDefault();
    });
  });
};

const initAuthNav = () => {
  const authItems = $$("[data-auth]");
  if (!authItems.length) return;

  if (isStaticHost) {
    authItems.forEach((el) => {
      el.hidden = true;
    });
    return;
  }

  const endpoints = ["./login.php?status=1", "../login.php?status=1", "../../login.php?status=1"];

  const fetchStatus = async () => {
    for (const url of endpoints) {
      try {
        const res = await fetch(url, { method: "GET", cache: "no-store", credentials: "same-origin" });
        const data = await res.json().catch(() => null);
        if (res.ok && data && typeof data.loggedIn === "boolean") return data;
      } catch {}
    }
    return { loggedIn: false };
  };

  fetchStatus().then((status) => {
    const loggedIn = Boolean(status && status.loggedIn);
    const role = status && typeof status.role === "string" ? status.role : "";

    authItems.forEach((el) => {
      const required = el.getAttribute("data-auth") || "";
      const show =
        (required === "admin" && loggedIn && role === "admin") ||
        (required === "user" && loggedIn) ||
        (required === "guest" && !loggedIn);
      el.hidden = !show;
    });
  });
};

const initCotizacionesBell = () => {
  const badges = $$(".js-cotizaciones-badge");
  if (!badges.length) return;

  const authEndpoints = ["./login.php?status=1", "../login.php?status=1", "../../login.php?status=1"];
  const endpoints = ["./cotizaciones.php?count=1", "../cotizaciones.php?count=1", "../../cotizaciones.php?count=1"];

  const setCount = (count) => {
    const n = Number.isFinite(count) ? Math.max(0, Math.trunc(count)) : 0;
    badges.forEach((el) => {
      el.textContent = String(n);
      el.hidden = n <= 0;
    });
  };

  const fetchStatus = async () => {
    for (const url of authEndpoints) {
      try {
        const res = await fetch(url, { method: "GET", cache: "no-store", credentials: "same-origin" });
        const data = await res.json().catch(() => null);
        if (res.ok && data && typeof data.loggedIn === "boolean") return data;
      } catch {}
    }
    return { loggedIn: false };
  };

  const fetchCount = async () => {
    for (const url of endpoints) {
      try {
        const res = await fetch(url, { method: "GET", cache: "no-store", credentials: "same-origin" });
        const data = await res.json().catch(() => null);
        if (res.ok && data && typeof data.count === "number") {
          setCount(data.count);
          return;
        }
      } catch {}
    }
    setCount(0);
  };

  fetchStatus().then((status) => {
    const loggedIn = Boolean(status && status.loggedIn);
    const role = status && typeof status.role === "string" ? status.role : "";
    if (!(loggedIn && role === "admin")) {
      setCount(0);
      return;
    }
    fetchCount();
    window.setInterval(fetchCount, 30000);
  });
};

const initBrandReload = () => {
  const brand = $(".brand");
  if (!brand) return;

  brand.addEventListener("click", (e) => {
    e.preventDefault();
    const url = new URL(brand.getAttribute("href") || "./index.html", window.location.href);
    const current = new URL(window.location.href);
    current.hash = "";
    if (current.href === url.href) {
      window.location.reload();
      return;
    }
    window.location.href = url.href;
  });
};

const initTopAnchors = () => {
  const links = $$('a[href="#inicio"]');
  if (!links.length) return;

  const scrollTop = (behavior) => {
    window.scrollTo({ top: 0, left: 0, behavior });
  };

  const syncFromHash = () => {
    if (window.location.hash === "#inicio") scrollTop("auto");
  };

  links.forEach((link) => {
    link.addEventListener("click", (e) => {
      e.preventDefault();
      try {
        history.pushState(null, "", "#inicio");
      } catch {
        window.location.hash = "inicio";
      }
      scrollTop("smooth");
    });
  });

  window.addEventListener("hashchange", syncFromHash);
  syncFromHash();
};

const initRevealOnScroll = () => {
  const autoTargets = $$(
    'main .section-head, main .team-photo, main .mv-flip, main .team-feature-media, main .team-feature-copy, main .course-card, main .video-card, main .timeline-item, main .card, main .info-card, main form.card'
  );

  autoTargets.forEach((el, idx) => {
    if (!(el instanceof HTMLElement)) return;
    if (!el.classList.contains("reveal")) el.classList.add("reveal");
    if (!el.dataset.reveal) {
      if (el.classList.contains("section-head")) el.dataset.reveal = "up";
      else if (el.classList.contains("timeline-item") && el.classList.contains("align-right")) el.dataset.reveal = "right";
      else if (el.classList.contains("timeline-item")) el.dataset.reveal = "left";
      else el.dataset.reveal = idx % 2 === 0 ? "left" : "right";
    }
  });

  const elements = $$(".reveal");
  if (!elements.length) return;

  const show = (el) => el.classList.add("is-visible");
  const hide = (el) => el.classList.remove("is-visible");

  if (!("IntersectionObserver" in window)) {
    elements.forEach(show);
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        const el = entry.target;
        if (!(el instanceof HTMLElement)) return;

        if (entry.isIntersecting && entry.intersectionRatio >= 0.18) {
          show(el);
          return;
        }
        if (!entry.isIntersecting || entry.intersectionRatio <= 0.08) {
          hide(el);
        }
      });
    },
    { threshold: [0, 0.08, 0.18, 0.32, 0.6], rootMargin: "0px 0px -10% 0px" }
  );

  elements.forEach((el) => observer.observe(el));
};

const initAutoplayVideosInView = () => {
  const videos = $$('video[data-autoplay="inview"]');
  if (!videos.length) return;

  const lockPlaybackRate = (video) => {
    try {
      if (video.dataset.rateLocked === "1") return;
      video.dataset.rateLocked = "1";
      video.playbackRate = 1;
      video.addEventListener("ratechange", () => {
        if (video.playbackRate !== 1) video.playbackRate = 1;
      });
    } catch {}
  };

  const safePlay = async (video) => {
    try {
      video.playsInline = true;
      if (video.dataset.userAudio !== "1") video.muted = true;
      const res = video.play();
      if (res && typeof res.then === "function") await res;
    } catch {}
  };

  const pause = (video) => {
    try {
      video.pause();
    } catch {}
  };

  videos.forEach((video) => {
    const markAudio = () => {
      if (!video.muted && video.volume > 0) video.dataset.userAudio = "1";
    };
    video.addEventListener("volumechange", markAudio);
    video.addEventListener("play", markAudio);
    video.addEventListener("click", markAudio);

    lockPlaybackRate(video);
  });

  if (!("IntersectionObserver" in window)) {
    videos.forEach((video) => safePlay(video));
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        const video = entry.target;
        if (!(video instanceof HTMLVideoElement)) return;
        if (entry.isIntersecting && entry.intersectionRatio >= 0.32) {
          if (video.dataset.userAudio === "1") {
            Promise.resolve(video.play()).catch(() => safePlay(video));
          } else {
            safePlay(video);
          }
        } else {
          pause(video);
        }
      });
    },
    { threshold: [0, 0.32, 0.6], rootMargin: "0px 0px -12% 0px" }
  );

  videos.forEach((video) => observer.observe(video));
};

const initTipsCarousel = () => {
  const root = $("#tips-carousel");
  if (!root) return;

  const viewport = root.querySelector("[data-carousel-viewport]");
  const prev = root.querySelector("[data-carousel-prev]");
  const next = root.querySelector("[data-carousel-next]");
  const dotsHost = root.querySelector("[data-carousel-dots]");
  if (!(viewport instanceof HTMLElement) || !(prev instanceof HTMLButtonElement) || !(next instanceof HTMLButtonElement)) return;
  if (!(dotsHost instanceof HTMLElement)) return;

  const items = $$("[data-carousel-item]", root).filter((el) => el instanceof HTMLElement);
  if (!items.length) return;

  const lockPlaybackRate = (video) => {
    try {
      if (video.dataset.rateLocked === "1") return;
      video.dataset.rateLocked = "1";
      video.playbackRate = 1;
      video.addEventListener("ratechange", () => {
        if (video.playbackRate !== 1) video.playbackRate = 1;
      });
    } catch {}
  };

  $$('video[data-lock-rate="1"]', root).forEach((video) => {
    if (video instanceof HTMLVideoElement) lockPlaybackRate(video);
  });

  const videos = $$("video", root).filter((el) => el instanceof HTMLVideoElement);
  videos.forEach((video) => {
    if (!(video instanceof HTMLVideoElement)) return;
    video.addEventListener("play", () => {
      videos.forEach((v) => {
        if (!(v instanceof HTMLVideoElement)) return;
        if (v === video) return;
        try {
          v.pause();
        } catch {}
      });
    });
  });

  const scrollToIndex = (index) => {
    const clamped = Math.max(0, Math.min(items.length - 1, index));
    items[clamped].scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" });
    window.setTimeout(sync, 260);
  };

  const getActiveIndex = () => {
    const rect = viewport.getBoundingClientRect();
    const center = rect.left + rect.width / 2;
    let bestIndex = 0;
    let bestDist = Number.POSITIVE_INFINITY;
    items.forEach((item, index) => {
      const r = item.getBoundingClientRect();
      const itemCenter = r.left + r.width / 2;
      const dist = Math.abs(itemCenter - center);
      if (dist < bestDist) {
        bestDist = dist;
        bestIndex = index;
      }
    });
    return bestIndex;
  };

  dotsHost.innerHTML = "";
  const dots = items.map((_, index) => {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "carousel-dot";
    btn.setAttribute("aria-label", `Ir al video ${index + 2}`);
    btn.addEventListener("click", () => scrollToIndex(index));
    dotsHost.appendChild(btn);
    return btn;
  });

  const sync = () => {
    const idx = getActiveIndex();
    dots.forEach((dot, i) => {
      dot.classList.toggle("is-active", i === idx);
      if (i === idx) dot.setAttribute("aria-current", "true");
      else dot.removeAttribute("aria-current");
    });
    prev.disabled = idx === 0;
    next.disabled = idx === items.length - 1;
  };

  let rafId = 0;
  const requestSync = () => {
    if (rafId) return;
    rafId = window.requestAnimationFrame(() => {
      rafId = 0;
      sync();
    });
  };

  viewport.addEventListener("scroll", requestSync, { passive: true });
  prev.addEventListener("click", () => scrollToIndex(getActiveIndex() - 1));
  next.addEventListener("click", () => scrollToIndex(getActiveIndex() + 1));

  let pointerDown = false;
  let isDragging = false;
  let dragStartX = 0;
  let dragStartY = 0;
  let dragStartScrollLeft = 0;
  let pointerId = null;
  let dragMoved = false;
  let suppressClicksUntil = 0;
  let dragRaf = 0;
  let pendingScrollLeft = null;

  viewport.addEventListener("pointerdown", (e) => {
    if (e.button !== 0) return;
    pointerDown = true;
    dragMoved = false;
    pointerId = e.pointerId;
    dragStartX = e.clientX;
    dragStartY = e.clientY;
    dragStartScrollLeft = viewport.scrollLeft;
  });

  viewport.addEventListener(
    "click",
    (e) => {
      if (Date.now() <= suppressClicksUntil) {
        e.preventDefault();
        e.stopPropagation();
      }
    },
    { capture: true }
  );

  viewport.addEventListener("pointermove", (e) => {
    if (!pointerDown) return;
    const dx = e.clientX - dragStartX;
    const dy = e.clientY - dragStartY;
    if (!isDragging) {
      if (Math.abs(dx) < 10) return;
      if (Math.abs(dy) > Math.abs(dx)) return;
      isDragging = true;
      dragMoved = true;
      viewport.classList.add("is-dragging");
      try {
        viewport.setPointerCapture(e.pointerId);
      } catch {}
    }
    e.preventDefault();
    pendingScrollLeft = dragStartScrollLeft - dx;
    if (!dragRaf) {
      dragRaf = window.requestAnimationFrame(() => {
        dragRaf = 0;
        if (typeof pendingScrollLeft === "number") viewport.scrollLeft = pendingScrollLeft;
      });
    }
  });

  const endDrag = (e) => {
    if (!pointerDown) return;
    pointerDown = false;
    pointerId = null;
    if (dragRaf) {
      window.cancelAnimationFrame(dragRaf);
      dragRaf = 0;
    }
    pendingScrollLeft = null;
    if (isDragging) {
      isDragging = false;
      viewport.classList.remove("is-dragging");
      suppressClicksUntil = Date.now() + 420;
    }
    try {
      viewport.releasePointerCapture(e.pointerId);
    } catch {}
    sync();
  };

  viewport.addEventListener("pointerup", endDrag);
  viewport.addEventListener("pointercancel", endDrag);

  sync();
};

const initCourseLinks = () => {
  const cards = $$(".course-card");
  if (!cards.length) return;
  if (isStaticHost) return;

  const openPdf = (card) => {
    const url = card.getAttribute("data-pdf");
    if (!url) return;
    window.open(url, "_blank", "noreferrer");
  };

  cards.forEach((card) => {
    card.addEventListener("click", (e) => {
      const target = e.target;
      if (target instanceof HTMLElement && (target.closest("a") || target.closest("button"))) return;
      openPdf(card);
    });
    card.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        openPdf(card);
      }
    });
  });
};

const initComingSoonCourses = () => {
  if (!isStaticHost) return;
  const modal = $("#coming-soon-modal");
  const okBtn = $("#coming-soon-ok");
  const closeBtn = $("#coming-soon-close");
  if (!modal || !(okBtn instanceof HTMLElement) || !(closeBtn instanceof HTMLElement)) return;

  const setOpen = (open) => {
    modal.hidden = !open;
    if (open) window.setTimeout(() => okBtn.focus(), 0);
  };

  okBtn.addEventListener("click", () => setOpen(false));
  closeBtn.addEventListener("click", () => setOpen(false));
  modal.addEventListener("click", (e) => {
    if (e.target === modal) setOpen(false);
  });
  window.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !modal.hidden) setOpen(false);
  });

  const cards = $$(".course-card");
  cards.forEach((card) => {
    card.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      setOpen(true);
    });
    card.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        setOpen(true);
      }
    });
    const link = card.querySelector(".course-actions a");
    if (link instanceof HTMLAnchorElement) {
      link.href = "#";
      link.removeAttribute("target");
      link.addEventListener("click", (e) => {
        e.preventDefault();
        setOpen(true);
      });
    }
  });
};
const initFloatingCta = () => {
  const button = $("#float-cta");
  const modal = $("#wa-modal");
  const confirmBtn = $("#wa-confirm");
  const cancelBtn = $("#wa-cancel");
  if (!button || !modal || !(confirmBtn instanceof HTMLElement) || !(cancelBtn instanceof HTMLElement)) return;

  const setOpen = (open) => {
    modal.hidden = !open;
    button.setAttribute("aria-expanded", open ? "true" : "false");
    if (open) {
      window.setTimeout(() => confirmBtn.focus(), 0);
    } else {
      window.setTimeout(() => button.focus(), 0);
    }
  };

  button.addEventListener("click", () => setOpen(true));
  cancelBtn.addEventListener("click", () => setOpen(false));

  modal.addEventListener("click", (e) => {
    if (e.target === modal) setOpen(false);
  });

  window.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !modal.hidden) setOpen(false);
  });

  confirmBtn.addEventListener("click", () => {
    window.location.href =
      "https://web.whatsapp.com/send/?phone=59177787803&text=Hola%20On%20The%20Road%20To%20Safety.%20Me%20gustar%C3%ADa%20recibir%20informaci%C3%B3n%20detallada%20sobre%20sus%20cursos%20de%20Manejo%20Defensivo.%20%C2%BFPodr%C3%ADan%20enviarme%20los%20horarios%20y%20costos%20disponibles%3F%20Gracias.";
  });

  setOpen(false);
};

const initInstructorModal = () => {
  const avatar = $("#instructor-avatar");
  const modal = $("#instructor-modal");
  const closeBtn = $("#instructor-close");
  if (!(avatar instanceof HTMLElement) || !modal || !(closeBtn instanceof HTMLElement)) return;

  const setOpen = (open) => {
    modal.hidden = !open;
    avatar.setAttribute("aria-expanded", open ? "true" : "false");
    if (open) {
      window.setTimeout(() => closeBtn.focus(), 0);
    } else {
      window.setTimeout(() => avatar.focus(), 0);
    }
  };

  avatar.addEventListener("click", () => setOpen(true));
  closeBtn.addEventListener("click", () => setOpen(false));

  modal.addEventListener("click", (e) => {
    if (e.target === modal) setOpen(false);
  });

  window.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !modal.hidden) setOpen(false);
  });

  setOpen(false);
};

const initAiChat = () => {
  if (document.getElementById("ai-chat")) return;
  if (!document.body) {
    window.addEventListener("DOMContentLoaded", initAiChat, { once: true });
    return;
  }

  const root = document.createElement("div");
  root.id = "ai-chat";
  root.className = "ai-chat";

  const toggle = document.createElement("button");
  toggle.type = "button";
  toggle.className = "ai-chat-toggle";
  toggle.setAttribute("aria-label", "Abrir asistente virtual");
  toggle.setAttribute("aria-expanded", "false");
  toggle.innerHTML =
    '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 18l-3 3V6a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v7a3 3 0 0 1-3 3H9l-2 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8 8h8M8 11h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';

  const panel = document.createElement("div");
  panel.className = "ai-chat-panel";
  panel.setAttribute("role", "dialog");
  panel.setAttribute("aria-label", "Asistente virtual");

  const head = document.createElement("div");
  head.className = "ai-chat-head";

  const title = document.createElement("div");
  title.className = "ai-chat-title";
  title.innerHTML = "<strong>LUNAI</strong><span>Asistente virtual</span>";

  const close = document.createElement("button");
  close.type = "button";
  close.className = "ai-chat-close";
  close.setAttribute("aria-label", "Cerrar chat");
  close.innerHTML =
    '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6.5 6.5l11 11M17.5 6.5l-11 11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';

  head.appendChild(title);
  head.appendChild(close);

  const messages = document.createElement("div");
  messages.className = "ai-chat-messages";

  const form = document.createElement("form");
  form.className = "ai-chat-form";
  form.autocomplete = "off";

  const input = document.createElement("textarea");
  input.className = "ai-chat-input";
  input.rows = 1;
  input.placeholder = "Escribe tu pregunta…";
  input.setAttribute("aria-label", "Mensaje");

  const send = document.createElement("button");
  send.type = "submit";
  send.className = "ai-chat-send";
  send.textContent = "Enviar";

  form.appendChild(input);
  form.appendChild(send);

  panel.appendChild(head);
  panel.appendChild(messages);
  panel.appendChild(form);

  root.appendChild(panel);
  root.appendChild(toggle);
  document.body.appendChild(root);

  const history = [];
  const maxHistory = 24;

  const phone = "59177787803";
  const verifyNscUrl = "https://www.nsc.org/instructor-lookup/results?InstructorID=2306066";
  const infoText =
    "Hola On The Road To Safety. Me gustaría recibir información detallada sobre sus cursos de Manejo Defensivo. ¿Podrían enviarme los horarios y costos disponibles? Gracias.";
  const quoteText =
    "Hola On The Road To Safety. Quisiera solicitar una cotización. ¿Podrían indicarme horarios, costos y disponibilidad? Gracias.";

  const getWhatsAppUrl = (text) => {
    const encoded = encodeURIComponent(String(text || ""));
    const isMobile = /Android|iPhone|iPad|iPod|Mobi/i.test(navigator.userAgent || "");
    if (isMobile) return `https://wa.me/${phone}?text=${encoded}`;
    return `https://web.whatsapp.com/send/?phone=${phone}&text=${encoded}`;
  };

  const normalizeMessage = (content) => {
    if (typeof content === "string") return { text: content, actions: [] };
    if (!content || typeof content !== "object") return { text: "", actions: [] };
    const text = typeof content.text === "string" ? content.text : String(content.text || "");
    const actions = Array.isArray(content.actions) ? content.actions : [];
    const safeActions = actions
      .map((a) => ({
        label: typeof a?.label === "string" ? a.label : "",
        href: typeof a?.href === "string" ? a.href : "",
      }))
      .filter((a) => a.label && a.href);
    return { text, actions: safeActions };
  };

  const renderMessage = (el, content) => {
    const msg = normalizeMessage(content);
    el.textContent = "";

    const textEl = document.createElement("div");
    textEl.className = "ai-msg-text";
    textEl.textContent = msg.text;
    el.appendChild(textEl);

    if (msg.actions.length) {
      const actionsEl = document.createElement("div");
      actionsEl.className = "ai-msg-actions";
      msg.actions.forEach((action) => {
        const a = document.createElement("a");
        a.className = "ai-msg-action";
        a.href = action.href;
        const isOnPageAnchor = typeof action.href === "string" && action.href.startsWith("#");
        if (!isOnPageAnchor) {
          a.target = "_blank";
          a.rel = "noreferrer";
        }
        a.textContent = action.label;
        actionsEl.appendChild(a);
      });
      el.appendChild(actionsEl);
    }
  };

  const createMsgEl = (role, content) => {
    const el = document.createElement("div");
    el.className = `ai-msg ${role === "user" ? "user" : "bot"}`;
    renderMessage(el, content);
    return el;
  };

  const trimHistory = () => {
    while (history.length > maxHistory) {
      history.shift();
      if (messages.firstElementChild) messages.firstElementChild.remove();
    }
  };

  const scrollToBottom = () => {
    messages.scrollTop = messages.scrollHeight;
  };

  const addMessage = (role, content) => {
    const el = createMsgEl(role, content);
    messages.appendChild(el);
    const msg = normalizeMessage(content);
    history.push({ role, content: msg.text });
    trimHistory();
    scrollToBottom();
    return el;
  };

  const setOpen = (open) => {
    panel.classList.toggle("is-open", open);
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    toggle.setAttribute("aria-label", open ? "Cerrar asistente virtual" : "Abrir asistente virtual");
    if (open) {
      window.setTimeout(() => input.focus(), 0);
    } else {
      window.setTimeout(() => toggle.focus(), 0);
    }
  };

  const autoResize = () => {
    input.style.height = "auto";
    input.style.height = `${Math.min(input.scrollHeight, 110)}px`;
  };

  const callAi = async (messageText) => {
    const endpoints = ["./gemini-chat.php"];
    const payload = {
      message: messageText,
      history: history.slice(-20),
      system:
        'Eres LUNAI, el asistente virtual del sitio web "On The Road To Safety". Fuiste creado y entrenado (prompts) por JACL. Responde siempre en español, con lenguaje natural, cercano y variado, como una persona real, evitando sonar robótico o repetir la misma frase en todas las respuestas (por ejemplo, no empieces siempre con "Te entiendo"). Enfócate en cursos corporativos de Manejo Defensivo, seguridad vial y control de riesgos. Tu estilo debe ayudar a resolver dudas y también orientar a solicitar el servicio (crear necesidad con ejemplos reales: riesgo, incidentes, costos, cumplimiento y evidencias), sin exagerar ni inventar información. Respaldo: el instructor JHON NEVER MANCACHI MAYTA está avalado como "DDC Professional Truck Driver Instructor LatAm" y puede verificarse en NSC (https://www.nsc.org/instructor-lookup/results?InstructorID=2306066). También existe respaldo operativo por la empresa de logística y transporte de Alvaro Pérez. Si preguntan tu nombre, incluye al inicio: "Me llamo LUNAI." Si piden cotización, precios, costos u horarios, indícale que se gestiona por WhatsApp y pide datos básicos (empresa/ciudad/cantidad de conductores/unidades). Si algo está fuera del tema, responde breve y luego conéctalo con seguridad vial/cursos/navegación del sitio.',
    };

    for (const url of endpoints) {
      try {
        const res = await fetch(url, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
        const data = await res.json().catch(() => null);
        if (!res.ok) {
          console.error("[LUNAI] Gemini endpoint error", { url, status: res.status, data });
          continue;
        }
        if (data && data.ok && typeof data.text === "string") return data.text;
        console.error("[LUNAI] Gemini bad payload", { url, data });
      } catch (err) {
        console.error("[LUNAI] Gemini fetch failed", err);
      }
    }

    throw new Error("no_endpoint");
  };

  const getLocalAnswer = () => null;

  addMessage("assistant", {
    text:
      "Hola, soy LUNAI.\n\nEstoy aquí para resolver dudas y ayudarte a reducir riesgo en ruta con entrenamiento práctico y evidencias.\n\nRespaldo: JHON NEVER MANCACHI MAYTA (DDC Professional Truck Driver Instructor LatAm) verificable en NSC, y respaldo operativo de la empresa de logística y transporte de Alvaro Pérez.\n\n¿Tu necesidad es capacitación, cumplimiento o bajar incidentes?",
    actions: [
      { label: "Ver cursos", href: "#cursos" },
      { label: "Verificar instructor (NSC)", href: verifyNscUrl },
      { label: "Solicitar cotización", href: getWhatsAppUrl(quoteText) },
    ],
  });

  toggle.addEventListener("click", () => {
    const open = !panel.classList.contains("is-open");
    setOpen(open);
  });

  close.addEventListener("click", () => setOpen(false));

  window.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && panel.classList.contains("is-open")) setOpen(false);
  });

  input.addEventListener("input", autoResize);
  autoResize();

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;
    input.value = "";
    autoResize();

    addMessage("user", text);

    const local = getLocalAnswer(text);
    if (local) {
      addMessage("assistant", local);
      return;
    }

    send.disabled = true;
    input.disabled = true;
    const placeholder = createMsgEl("assistant", "Escribiendo…");
    messages.appendChild(placeholder);
    scrollToBottom();

    try {
      const answer = await callAi(text);
      renderMessage(placeholder, answer);
      history.push({ role: "assistant", content: answer });
      trimHistory();
      scrollToBottom();
    } catch {
      const fallback = {
        text:
          "Puedo ayudarte con cursos, metodología, certificados o cotización.\n\nDime qué necesitas y te guío. Si buscas precios/horarios, lo gestionamos por WhatsApp.",
        actions: [
          { label: "Ver cursos", href: "#cursos" },
          { label: "Ver metodología", href: "#metodologia" },
          { label: "Ir a Certificados", href: "./Certificados/" },
          { label: "Solicitar cotización", href: getWhatsAppUrl(quoteText) },
        ],
      };
      renderMessage(placeholder, fallback);
      history.push({ role: "assistant", content: fallback.text });
      trimHistory();
      scrollToBottom();
    } finally {
      send.disabled = false;
      input.disabled = false;
      input.focus();
    }
  });

  setOpen(false);
};

const initQuoteForm = () => {
  const form = $("#quote-form");
  const note = $("#quote-note");
  const fillBtn = $("#fill-from-course");
  if (!form) return;

  const setNote = (message) => {
    if (!note) return;
    note.textContent = message;
  };

  const endpoints = ["./cotizaciones.php?public=1", "../cotizaciones.php?public=1", "../../cotizaciones.php?public=1"];

  const getPrimaryButton = () => $("button[type='submit']", form);

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const submit = getPrimaryButton();
    if (submit) submit.disabled = true;

    setNote("Enviando tu solicitud...");

    for (const url of endpoints) {
      try {
        const res = await fetch(url, { method: "POST", body: new FormData(form), credentials: "same-origin" });
        const data = await res.json().catch(() => null);
        if (res.ok && data && data.ok === true) {
          form.reset();
          setNote("Solicitud enviada. Te contactaremos pronto.");
          showToast("Solicitud guardada");
          window.setTimeout(() => setNote(""), 2800);
          if (submit) submit.disabled = false;
          return;
        }
        if (data && typeof data.message === "string" && data.message.trim()) {
          setNote(data.message.trim());
          if (submit) submit.disabled = false;
          return;
        }
      } catch {}
    }

    setNote("No se pudo enviar en este momento. Intenta de nuevo.");
    if (submit) submit.disabled = false;
  });

  if (fillBtn) {
    fillBtn.addEventListener("click", () => {
      const selected = $("#curso");
      if (!(selected instanceof HTMLSelectElement)) return;
      const messageEl = $("#mensaje");
      if (!(messageEl instanceof HTMLTextAreaElement)) return;
      const text = messageEl.value.trim();
      const prefix = `Curso de interés: ${selected.value}\n`;
      messageEl.value = text ? `${prefix}\n${text}` : prefix;
      messageEl.focus();
      showToast("Curso agregado al mensaje");
    });
  }
};

const initContactForm = () => {
  const form = $("#contact-form");
  const input = $("#contact-email");
  if (!form || !(input instanceof HTMLInputElement)) return;

  const endpoints = ["./contacto.php", "../contacto.php", "../../contacto.php"];

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const correo = input.value.trim();
    if (!correo) {
      input.focus();
      return;
    }
    if (!input.checkValidity()) {
      input.reportValidity();
      return;
    }

    const submit = $("button[type='submit']", form);
    if (submit instanceof HTMLButtonElement) submit.disabled = true;

    const fd = new FormData();
    fd.set("correo", correo);

    for (const url of endpoints) {
      try {
        const res = await fetch(url, { method: "POST", body: fd, credentials: "same-origin" });
        const data = await res.json().catch(() => null);
        if (res.ok && data && data.ok === true) {
          form.reset();
          showToast("Listo. Te contactaremos pronto.");
          if (submit instanceof HTMLButtonElement) submit.disabled = false;
          return;
        }
        if (data && typeof data.message === "string" && data.message.trim()) {
          showToast(data.message.trim());
          if (submit instanceof HTMLButtonElement) submit.disabled = false;
          return;
        }
      } catch {}
    }

    const to = "cotizaciones@ontheroadtosafety.com";
    const subject = "Contacto - On The Road To Safety";
    const body = `Hola, me gustaría recibir información.\n\nMi correo: ${correo}\n`;
    window.location.href = `mailto:${encodeURIComponent(to)}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    if (submit instanceof HTMLButtonElement) submit.disabled = false;
  });
};

const certLocalDb = (() => {
  const dbName = "otrts_certificados";
  const storeName = "certs";

  const normalizeSpaces = (value) => String(value ?? "").trim().replace(/\s+/g, " ");
  const stripDiacritics = (value) => {
    try {
      return value.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    } catch {
      return value;
    }
  };
  const normalizeName = (value) => stripDiacritics(normalizeSpaces(value)).toLowerCase();
  const normalizeCi = (value) => String(value ?? "").trim().replace(/\s+/g, "").replace(/[^a-z0-9]+/gi, "").toUpperCase();

  const open = () =>
    new Promise((resolve, reject) => {
      if (!("indexedDB" in window)) {
        reject(new Error("indexedDB not available"));
        return;
      }
      const req = indexedDB.open(dbName, 1);
      req.onupgradeneeded = () => {
        const db = req.result;
        if (db.objectStoreNames.contains(storeName)) return;
        const store = db.createObjectStore(storeName, { keyPath: "id", autoIncrement: true });
        store.createIndex("ci_clean", "ci_clean", { unique: false });
        store.createIndex("nombre_clean", "nombre_clean", { unique: false });
      };
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error || new Error("indexedDB error"));
    });

  const add = async ({ nombre, ci, pdfBlob, pdfName }) => {
    const db = await open();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, "readwrite");
      const store = tx.objectStore(storeName);
      const rec = {
        nombre: normalizeSpaces(nombre),
        nombre_clean: normalizeName(nombre),
        ci: normalizeSpaces(ci),
        ci_clean: normalizeCi(ci),
        pdf_blob: pdfBlob,
        pdf_name: pdfName || "certificado.pdf",
        created_at: Date.now(),
      };
      const req = store.add(rec);
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error || new Error("No se pudo guardar"));
    });
  };

  const find = async (query) => {
    const qName = normalizeName(query);
    const qCi = normalizeCi(query);
    const db = await open();

    if (qCi) {
      const byCi = await new Promise((resolve) => {
        const tx = db.transaction(storeName, "readonly");
        const idx = tx.objectStore(storeName).index("ci_clean");
        const req = idx.getAll(qCi);
        req.onsuccess = () => resolve(Array.isArray(req.result) ? req.result : []);
        req.onerror = () => resolve([]);
      });
      if (byCi.length) return byCi.sort((a, b) => (b.created_at || 0) - (a.created_at || 0))[0];
    }

    const all = await new Promise((resolve) => {
      const tx = db.transaction(storeName, "readonly");
      const store = tx.objectStore(storeName);
      const req = store.getAll();
      req.onsuccess = () => resolve(Array.isArray(req.result) ? req.result : []);
      req.onerror = () => resolve([]);
    });

    const matches = all.filter((r) => String(r?.nombre_clean || "").includes(qName));
    if (!matches.length) return null;
    matches.sort((a, b) => (b.created_at || 0) - (a.created_at || 0));
    return matches[0];
  };

  return { add, find, normalizeName, normalizeCi };
})();

const initFileDropzones = () => {
  const zones = $$(".dropzone[aria-controls]");
  if (!zones.length) return;

  const isPdfFile = (file) => {
    if (!file) return false;
    const name = String(file.name || "").toLowerCase();
    if (file.type === "application/pdf") return true;
    return name.endsWith(".pdf");
  };

  const setFilesOnInput = (input, files) => {
    try {
      const dt = new DataTransfer();
      Array.from(files || []).forEach((f) => dt.items.add(f));
      input.files = dt.files;
      input.dispatchEvent(new Event("change", { bubbles: true }));
      return true;
    } catch {
      return false;
    }
  };

  zones.forEach((zone) => {
    const controlId = zone.getAttribute("aria-controls") || "";
    const input = controlId ? document.getElementById(controlId) : null;
    if (!(input instanceof HTMLInputElement) || input.type !== "file") return;

    const field = zone.closest(".field");
    const fileNameEl = field ? $(".dropzone-file", field) : null;
    const renderSelected = () => {
      const f = input.files && input.files[0];
      zone.hidden = Boolean(f);

      if (!fileNameEl) return;
      fileNameEl.innerHTML = "";
      fileNameEl.hidden = !f;
      if (!f) return;

      const icon = document.createElement("span");
      icon.className = "file-pill-icon";
      icon.innerHTML =
        '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M14 2v6h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M9 13h6M9 17h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

      const name = document.createElement("span");
      name.className = "file-pill-name";
      name.textContent = f.name || "archivo.pdf";

      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "file-pill-remove";
      remove.setAttribute("aria-label", "Quitar archivo");
      remove.innerHTML =
        '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>';
      remove.addEventListener("click", (e) => {
        e.preventDefault();
        if (input.disabled) return;
        input.value = "";
        input.dispatchEvent(new Event("change", { bubbles: true }));
      });

      fileNameEl.append(icon, name, remove);
    };

    renderSelected();
    input.addEventListener("change", renderSelected);

    const openPicker = () => {
      if (input.disabled) return;
      input.click();
    };

    zone.addEventListener("click", (e) => {
      e.preventDefault();
      openPicker();
    });

    zone.addEventListener("keydown", (e) => {
      if (e.key !== "Enter" && e.key !== " ") return;
      e.preventDefault();
      openPicker();
    });

    const setDrag = (on) => zone.classList.toggle("is-dragover", on);

    zone.addEventListener("dragenter", (e) => {
      e.preventDefault();
      setDrag(true);
    });

    zone.addEventListener("dragover", (e) => {
      e.preventDefault();
      setDrag(true);
    });

    zone.addEventListener("dragleave", (e) => {
      const related = e.relatedTarget;
      if (related instanceof Node && zone.contains(related)) return;
      setDrag(false);
    });

    zone.addEventListener("drop", (e) => {
      e.preventDefault();
      setDrag(false);
      const dt = e.dataTransfer;
      const files = dt && dt.files ? dt.files : null;
      const file = files && files[0] ? files[0] : null;
      if (!file) return;
      if (!isPdfFile(file)) {
        showToast("Solo se permiten archivos PDF");
        return;
      }
      if (!setFilesOnInput(input, [file])) {
        showToast("No se pudo cargar el archivo. Selecciónalo manualmente.");
        openPicker();
      }
    });
  });
};

const initRegisterCertificate = () => {
  const form = $("#cert-register-form");
  const nameInput = $("#reg-nombre");
  const ciInput = $("#reg-ci");
  const fileInput = $("#reg-pdf");
  const note = $("#reg-note");
  if (!form || !(form instanceof HTMLFormElement)) return;
  if (!(nameInput instanceof HTMLInputElement)) return;
  if (!(ciInput instanceof HTMLInputElement)) return;
  if (!(fileInput instanceof HTMLInputElement)) return;

  const setNote = (message) => {
    if (!note) return;
    note.textContent = message;
  };

  const tryPhpUpload = async () => {
    const fd = new FormData();
    fd.set("nombre", nameInput.value.trim());
    fd.set("ci", ciInput.value.trim());
    const file = fileInput.files && fileInput.files[0];
    if (file) fd.set("pdf", file, file.name);
    const endpoints = ["./registrar-certificado.php?json=1", "../registrar-certificado.php?json=1"];

    for (const url of endpoints) {
      try {
        const res = await fetch(url, { method: "POST", body: fd });
        if (!res.ok) continue;
        const data = await res.json().catch(() => null);
        if (data && data.ok) return data;
      } catch {}
    }

    throw new Error("No se pudo conectar al servidor");
  };

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const nombre = nameInput.value.trim();
    const ci = ciInput.value.trim();
    const file = fileInput.files && fileInput.files[0];
    if (!nombre || !ci || !file) {
      showToast("Completa los campos y sube el PDF");
      return;
    }
    if (file.type !== "application/pdf" && !String(file.name || "").toLowerCase().endsWith(".pdf")) {
      showToast("El archivo debe ser PDF");
      return;
    }

    setNote("Registrando…");

    try {
      const result = await tryPhpUpload();
      if (result && typeof result.pdf_url === "string" && result.pdf_url.trim()) {
        setNote(`Certificado registrado: ${result.pdf_url}`);
      } else {
        setNote("Certificado registrado.");
      }
      showToast("Certificado registrado");
      window.setTimeout(() => {
        window.location.href = "../Certificados/";
      }, 450);
      return;
    } catch {
      setNote("No se pudo registrar. Ejecuta el sitio con PHP y MySQL.");
      showToast("No se pudo registrar");
    }
  });
};

const initCertificates = () => {
  const form = $("#cert-form");
  const input = $("#cert-name");
  const note = $("#cert-note");
  if (!form || !(form instanceof HTMLFormElement) || !(input instanceof HTMLInputElement)) return;

  const setNote = (message) => {
    if (!note) return;
    note.textContent = message;
  };

  const normalizeSpaces = (value) => String(value ?? "").trim().replace(/\s+/g, " ");

  const stripPdf = (value) => {
    const v = normalizeSpaces(value);
    return v.toLowerCase().endsWith(".pdf") ? v.slice(0, -4).trim() : v;
  };

  const stripDiacritics = (value) => {
    try {
      return value.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    } catch {
      return value;
    }
  };

  const sentenceCase = (value) => {
    const v = normalizeSpaces(value);
    if (!v) return v;
    return v.charAt(0).toUpperCase() + v.slice(1).toLowerCase();
  };

  const titleCase = (value) =>
    normalizeSpaces(value)
      .split(" ")
      .map((w) => (w ? w.charAt(0).toUpperCase() + w.slice(1).toLowerCase() : ""))
      .join(" ");

  const slugify = (value, separator) =>
    stripDiacritics(String(value ?? ""))
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, separator)
      .replace(new RegExp(`^\\${separator}+|\\${separator}+$`, "g"), "");

  const uniq = (items) => Array.from(new Set(items.map((x) => String(x || "").trim()).filter(Boolean)));

  const checkExists = async (url) => {
    try {
      const head = await fetch(url, { method: "HEAD", cache: "no-store" });
      if (head.ok) return true;
      if (head.status !== 405 && head.status !== 501) return false;
    } catch {}

    try {
      const get = await fetch(url, { method: "GET", cache: "no-store" });
      return get.ok;
    } catch {
      return false;
    }
  };

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const raw = stripPdf(input.value);
    if (!raw) {
      showToast("Escribe tu nombre para buscar tu certificado");
      input.focus();
      return;
    }

    const preOpened = window.open("about:blank", "_blank");
    if (preOpened) preOpened.opener = null;
    const openUrl = (url) => {
      if (preOpened && !preOpened.closed) {
        try {
          preOpened.location.replace(url);
          preOpened.focus();
          return;
        } catch {}
      }
      const opened = window.open(url, "_blank");
      if (!opened) window.location.href = url;
    };

    const query = normalizeSpaces(raw);
    const base = stripDiacritics(normalizeSpaces(raw));
    const candidates = uniq([
      base,
      sentenceCase(base),
      titleCase(base),
      base.toLowerCase(),
      base.toUpperCase(),
      slugify(base, "-"),
      slugify(base, "_"),
    ]).map((v) => (v.toLowerCase().endsWith(".pdf") ? v : `${v}.pdf`));

    const certFolder = new URL("../Certificados/", window.location.href);
    const dirs = [certFolder.pathname.replace(/\/$/, ""), "/certificados", "/Certificados"];
    setNote("Buscando tu certificado…");

    try {
      const res = await fetch(`../certificados.php?q=${encodeURIComponent(query)}`, { cache: "no-store" });
      const data = await res.json().catch(() => null);
      if (data && data.ok && typeof data.url === "string" && data.url.trim()) {
        setNote("Abriendo tu certificado…");
        openUrl(data.url);
        window.setTimeout(() => setNote(""), 2200);
        return;
      }
    } catch {}

    for (const dir of dirs) {
      for (const filename of candidates) {
        const url = `${dir}/${encodeURIComponent(filename)}`;
        const ok = await checkExists(url);
        if (!ok) continue;
        setNote("Abriendo tu certificado…");
        openUrl(url);
        window.setTimeout(() => setNote(""), 2200);
        return;
      }
    }

    try {
      const found = await certLocalDb.find(query);
      if (found && found.pdf_blob) {
        setNote("Abriendo tu certificado…");
        const objectUrl = URL.createObjectURL(found.pdf_blob);
        openUrl(objectUrl);
        window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60000);
        window.setTimeout(() => setNote(""), 2200);
        return;
      }
    } catch {}

    if (preOpened && !preOpened.closed) {
      try {
        preOpened.close();
      } catch {}
    }
    setNote("No encontramos tu certificado. Verifica el nombre e inténtalo de nuevo.");
  });
};

initPageLoader();
initYear();
initMobileNav();
initDisablePhpLinks();
initAuthNav();
initCotizacionesBell();
initBrandReload();
initTopAnchors();
initRevealOnScroll();
initAutoplayVideosInView();
initTipsCarousel();
initCourseLinks();
initComingSoonCourses();
initFloatingCta();
initInstructorModal();
initAiChat();
initQuoteForm();
initContactForm();
initCertificates();
initFileDropzones();
initRegisterCertificate();
