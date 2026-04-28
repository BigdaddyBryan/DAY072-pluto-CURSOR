(function () {
  const globalScope = window;
  const languageStorageKey = "uiLanguage";
  const supportedLanguages = ["en", "nl"];

  const readStoredLanguage = function () {
    try {
      const stored = String(
        globalScope.localStorage?.getItem(languageStorageKey) || "",
      )
        .trim()
        .toLowerCase();
      return supportedLanguages.includes(stored) ? stored : "";
    } catch (error) {
      return "";
    }
  };

  const readPath = function (obj, path) {
    if (!obj || typeof path !== "string" || path.trim() === "") {
      return null;
    }

    const normalized = path.trim();
    const candidates = [normalized];
    if (normalized.startsWith("js.") && normalized.length > 3) {
      candidates.push(normalized.slice(3));
    }

    for (const candidate of candidates) {
      const parts = candidate.split(".");
      let cursor = obj;
      let found = true;

      for (const part of parts) {
        if (!cursor || typeof cursor !== "object" || !(part in cursor)) {
          found = false;
          break;
        }
        cursor = cursor[part];
      }

      if (found && typeof cursor === "string") {
        return cursor;
      }
    }

    return null;
  };

  const replacePlaceholders = function (template, params) {
    if (typeof template !== "string" || !params || typeof params !== "object") {
      return template;
    }

    return Object.keys(params).reduce(function (output, key) {
      const token = `{${key}}`;
      return output.split(token).join(String(params[key]));
    }, template);
  };

  const state = {
    lang: String(globalScope.__I18N_LANG || "en").toLowerCase(),
    texts:
      globalScope.__I18N_TEXTS && typeof globalScope.__I18N_TEXTS === "object"
        ? globalScope.__I18N_TEXTS
        : globalScope.__UI_TEXT__ && typeof globalScope.__UI_TEXT__ === "object"
          ? globalScope.__UI_TEXT__
          : {},
  };

  const t = function (key, fallback = "", params = null) {
    const value = readPath(state.texts, key);
    const resolved = value !== null ? value : fallback;
    return replacePlaceholders(resolved, params);
  };

  const applyTranslations = function (root) {
    const target = root && root.querySelectorAll ? root : document;

    target.querySelectorAll("[data-i18n]").forEach(function (el) {
      const key = el.getAttribute("data-i18n");
      const fallback =
        el.getAttribute("data-i18n-fallback") || el.textContent || "";
      el.textContent = t(key, fallback);
    });

    target.querySelectorAll("[data-i18n-placeholder]").forEach(function (el) {
      const key = el.getAttribute("data-i18n-placeholder");
      const fallback = el.getAttribute("placeholder") || "";
      el.setAttribute("placeholder", t(key, fallback));
    });

    target.querySelectorAll("[data-i18n-title]").forEach(function (el) {
      const key = el.getAttribute("data-i18n-title");
      const fallback = el.getAttribute("title") || "";
      el.setAttribute("title", t(key, fallback));
    });

    target.querySelectorAll("[data-i18n-aria-label]").forEach(function (el) {
      const key = el.getAttribute("data-i18n-aria-label");
      const fallback = el.getAttribute("aria-label") || "";
      el.setAttribute("aria-label", t(key, fallback));
    });

    target.querySelectorAll("[data-i18n-value]").forEach(function (el) {
      const key = el.getAttribute("data-i18n-value");
      const fallback = el.value || "";
      el.value = t(key, fallback);
    });

    document.documentElement.lang = state.lang;
  };

  const syncLanguageSwitchState = function () {
    const normalized = supportedLanguages.includes(state.lang)
      ? state.lang
      : "en";
    const switcher = document.getElementById("uiLanguageSwitch");
    const toggle = document.getElementById("uiLanguageToggle");
    const langLabel = document.getElementById("navLanguageLabel");

    if (toggle) {
      toggle.setAttribute("data-active-lang", normalized);
    }

    if (switcher) {
      switcher.setAttribute(
        "aria-checked",
        normalized === "nl" ? "true" : "false",
      );
    }

    if (langLabel) {
      langLabel.textContent = normalized === "nl" ? "Nederlands" : "Engels";
    }
  };

  const setLanguage = async function (lang) {
    const next = String(lang || "")
      .trim()
      .toLowerCase();
    if (!next || next === state.lang) {
      return true;
    }

    const response = await fetch("/setLanguage", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ lang: next }),
    });

    const payload = await response.json().catch(function () {
      return {};
    });

    if (!response.ok || payload.success !== true) {
      return false;
    }

    state.lang = String(payload.lang || next).toLowerCase();
    state.texts =
      payload.texts && typeof payload.texts === "object"
        ? payload.texts
        : state.texts;

    try {
      globalScope.localStorage?.setItem(languageStorageKey, state.lang);
    } catch (error) {
      // Ignore storage errors (private mode, quota, or blocked storage)
    }

    globalScope.__I18N_LANG = state.lang;
    globalScope.__I18N_TEXTS = state.texts;
    globalScope.__UI_TEXT__ = state.texts;

    const langInput = document.getElementById("uiLanguageCurrent");
    if (langInput) {
      langInput.value = state.lang;
    }

    syncLanguageSwitchState();

    applyTranslations(document);
    document.dispatchEvent(
      new CustomEvent("i18n:changed", { detail: { lang: state.lang } }),
    );
    return true;
  };

  const initializeLanguageSwitcher = function () {
    const switcher = document.getElementById("uiLanguageSwitch");
    const toggle = document.getElementById("uiLanguageToggle");
    const languageItem = switcher?.closest(".navLanguageItem") || null;
    if (!switcher) {
      return;
    }

    const stopPropagation = function (event) {
      event.stopPropagation();
    };

    ["click", "mousedown", "touchstart", "pointerdown"].forEach(
      function (evtName) {
        if (toggle) {
          toggle.addEventListener(evtName, stopPropagation);
        }
        if (languageItem) {
          languageItem.addEventListener(evtName, stopPropagation);
        }
        switcher.addEventListener(evtName, function (event) {
          stopPropagation(event);
        });
      },
    );

    syncLanguageSwitchState();

    switcher.addEventListener("click", async function (event) {
      event.preventDefault();

      const previousValue = state.lang;
      const nextLanguage = state.lang === "nl" ? "en" : "nl";
      const ok = await setLanguage(nextLanguage);
      if (!ok) {
        state.lang = previousValue;
        syncLanguageSwitchState();
      }
    });

    if (toggle) {
      toggle
        .querySelectorAll(".navLangOption[data-lang]")
        .forEach(function (option) {
          ["mousedown", "touchstart", "pointerdown"].forEach(
            function (evtName) {
              option.addEventListener(evtName, stopPropagation);
            },
          );

          option.addEventListener("click", async function (event) {
            event.preventDefault();
            stopPropagation(event);

            const requestedLanguage = String(
              option.getAttribute("data-lang") || "",
            )
              .trim()
              .toLowerCase();
            if (!supportedLanguages.includes(requestedLanguage)) {
              return;
            }

            const previousValue = state.lang;
            const ok = await setLanguage(requestedLanguage);
            if (!ok) {
              state.lang = previousValue;
              syncLanguageSwitchState();
            }
          });
        });
    }

    if (languageItem) {
      languageItem.addEventListener("click", async function (event) {
        if (
          event.target &&
          event.target.closest("#uiLanguageSwitch, .navLangOption[data-lang]")
        ) {
          return;
        }

        event.preventDefault();
        stopPropagation(event);

        const previousValue = state.lang;
        const nextLanguage = state.lang === "nl" ? "en" : "nl";
        const ok = await setLanguage(nextLanguage);
        if (!ok) {
          state.lang = previousValue;
          syncLanguageSwitchState();
        }
      });
    }
  };

  globalScope.i18n = {
    t,
    setLanguage,
    apply: applyTranslations,
    getLang: function () {
      return state.lang;
    },
  };

  document.addEventListener("DOMContentLoaded", function () {
    applyTranslations(document);
    initializeLanguageSwitcher();

    const preferredLanguage = readStoredLanguage();
    if (preferredLanguage && preferredLanguage !== state.lang) {
      setLanguage(preferredLanguage).catch(function () {
        // Keep server language when preference cannot be applied
      });
    }
  });
})();
