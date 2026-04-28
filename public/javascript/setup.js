/* ═══════════════════════════════════════════════════════════════
 *  Setup Wizard — Client-side color engine, wizard navigation,
 *  live preview, contrast checking, and form submission.
 * ═══════════════════════════════════════════════════════════════ */

(function () {
  "use strict";

  /* ── Color Utilities ── */

  function hexToRgb(hex) {
    hex = (hex || "").replace(/^#/, "");
    if (hex.length === 3)
      hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    if (hex.length !== 6) return null;
    return [
      parseInt(hex.slice(0, 2), 16),
      parseInt(hex.slice(2, 4), 16),
      parseInt(hex.slice(4, 6), 16),
    ];
  }

  function rgbToHex(r, g, b) {
    return (
      "#" +
      [r, g, b]
        .map((c) =>
          Math.max(0, Math.min(255, Math.round(c)))
            .toString(16)
            .padStart(2, "0"),
        )
        .join("")
    );
  }

  function rgbToHsl(r, g, b) {
    r /= 255;
    g /= 255;
    b /= 255;
    const max = Math.max(r, g, b),
      min = Math.min(r, g, b);
    let h,
      s,
      l = (max + min) / 2;

    if (max === min) {
      h = s = 0;
    } else {
      const d = max - min;
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
      if (max === r) h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
      else if (max === g) h = ((b - r) / d + 2) / 6;
      else h = ((r - g) / d + 4) / 6;
    }
    return [h, s, l];
  }

  function hslToRgb(h, s, l) {
    if (s === 0) {
      const v = Math.round(l * 255);
      return [v, v, v];
    }
    const hue2rgb = (p, q, t) => {
      if (t < 0) t += 1;
      if (t > 1) t -= 1;
      if (t < 1 / 6) return p + (q - p) * 6 * t;
      if (t < 1 / 2) return q;
      if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
      return p;
    };
    const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
    const p = 2 * l - q;
    return [
      Math.round(hue2rgb(p, q, h + 1 / 3) * 255),
      Math.round(hue2rgb(p, q, h) * 255),
      Math.round(hue2rgb(p, q, h - 1 / 3) * 255),
    ];
  }

  function mixColors(hex1, hex2, ratio) {
    const c1 = hexToRgb(hex1),
      c2 = hexToRgb(hex2);
    if (!c1 || !c2) return hex1;
    ratio = Math.max(0, Math.min(1, ratio));
    return rgbToHex(
      c1[0] + (c2[0] - c1[0]) * ratio,
      c1[1] + (c2[1] - c1[1]) * ratio,
      c1[2] + (c2[2] - c1[2]) * ratio,
    );
  }

  function adjustLightness(hex, delta) {
    const rgb = hexToRgb(hex);
    if (!rgb) return hex;
    const [h, s, l] = rgbToHsl(rgb[0], rgb[1], rgb[2]);
    const [r, g, b] = hslToRgb(h, s, Math.max(0, Math.min(1, l + delta)));
    return rgbToHex(r, g, b);
  }

  function relativeLuminance(hex) {
    const rgb = hexToRgb(hex);
    if (!rgb) return 0;
    const channels = rgb.map((c) => {
      c = c / 255;
      return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
  }

  function contrastRatio(hex1, hex2) {
    const l1 = relativeLuminance(hex1);
    const l2 = relativeLuminance(hex2);
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
  }

  function autoFixFg(fg, bg, minRatio) {
    if (contrastRatio(fg, bg) >= minRatio) return fg;
    const bgLum = relativeLuminance(bg);
    const direction = bgLum > 0.5 ? -0.03 : 0.03;
    let adjusted = fg;
    for (let i = 0; i < 50; i++) {
      adjusted = adjustLightness(adjusted, direction);
      if (contrastRatio(adjusted, bg) >= minRatio) return adjusted;
    }
    return bgLum > 0.5 ? "#000000" : "#ffffff";
  }

  function hexToRgbString(hex) {
    const rgb = hexToRgb(hex);
    if (!rgb) return "0, 0, 0";
    return rgb.join(", ");
  }

  /* ── Theme Generation (mirrors PHP) ── */

  function generateTheme(accent, canvas, text, border, isDark) {
    const t = {};

    t["color-accent"] = accent;
    t["color-accent-soft"] = mixColors(canvas, accent, 0.3);
    t["color-accent-strong"] = adjustLightness(accent, -0.12);
    t["color-accent-rgb"] = hexToRgbString(accent);

    t["surface-canvas"] = canvas;
    t["surface-base"] = mixColors(canvas, text, 0.02);
    t["surface-muted"] = isDark
      ? adjustLightness(canvas, -0.1)
      : mixColors(canvas, text, 0.1);
    t["surface-elevated"] = mixColors(canvas, border, isDark ? 0.18 : 0.14);
    t["surface-hover"] = mixColors(canvas, text, 0.04);
    t["surface-text-highlight"] = mixColors(
      mixColors(canvas, text, 0.06),
      accent,
      0.08,
    );
    t["surface-tooltip"] = isDark ? mixColors(canvas, text, 0.06) : canvas;
    t["surface-version"] = mixColors(
      mixColors(canvas, border, 0.12),
      accent,
      0.05,
    );

    t["text-primary"] = text;
    t["text-secondary"] = mixColors(text, canvas, 0.2);
    t["text-strong"] = mixColors(text, canvas, 0.2);
    t["text-inverse"] = isDark ? "#ffffff" : "#000000";
    t["text-primary-rgb"] = hexToRgbString(text);

    t["border-default"] = border;
    t["border-soft"] = mixColors(border, canvas, 0.45);
    t["border-strong"] = mixColors(border, text, 0.3);

    if (isDark) {
      t["shadow-soft"] = "0 1px 2px rgba(0, 0, 0, 0.22)";
      t["shadow-medium"] = "0 8px 20px rgba(0, 0, 0, 0.35)";
      t["shadow-strong"] = "0 18px 52px rgba(0, 0, 0, 0.46)";
    } else {
      t["shadow-soft"] = "0 1px 2px rgba(0, 0, 0, 0.06)";
      t["shadow-medium"] = "0 8px 20px rgba(0, 0, 0, 0.18)";
      t["shadow-strong"] = "0 18px 52px rgba(0, 0, 0, 0.28)";
    }

    t["radius-sm"] = "5px";
    t["radius-md"] = "10px";
    t["radius-lg"] = "15px";
    t["motion-duration-base"] = "0.3s";
    t["color-white"] = isDark ? mixColors(text, canvas, 0.05) : "#ffffff";

    t["action-primary-bg"] = mixColors(canvas, border, 0.22);
    t["action-primary-fg"] = autoFixFg(text, t["action-primary-bg"], 4.5);
    t["action-primary-hover-bg"] = adjustLightness(
      t["action-primary-bg"],
      isDark ? 0.06 : -0.06,
    );

    const dangerBase = isDark ? "#a94646" : "#dc5b5b";
    t["action-danger-bg"] = dangerBase;
    t["action-danger-fg"] = autoFixFg("#ffffff", dangerBase, 4.5);
    t["action-danger-hover-bg"] = adjustLightness(
      dangerBase,
      isDark ? 0.08 : -0.08,
    );
    t["action-danger-rgb"] = hexToRgbString(dangerBase);

    t["action-secondary-bg"] = mixColors(canvas, border, 0.16);
    t["action-secondary-fg"] = autoFixFg(text, t["action-secondary-bg"], 4.5);
    t["action-secondary-hover-bg"] = adjustLightness(
      t["action-secondary-bg"],
      isDark ? 0.06 : -0.06,
    );

    return t;
  }

  function checkContrast(tokens) {
    const pairs = [
      [["text-primary", "Text"], ["surface-canvas", "Canvas"], 4.5, "AA"],
      [["text-secondary", "Secondary"], ["surface-base", "Base"], 4.5, "AA"],
      [["color-accent", "Accent"], ["surface-canvas", "Canvas"], 3.0, "AA-UI"],
      [
        ["action-primary-fg", "Btn Text"],
        ["action-primary-bg", "Btn BG"],
        4.5,
        "AA",
      ],
      [
        ["action-danger-fg", "Danger"],
        ["action-danger-bg", "Danger BG"],
        4.5,
        "AA",
      ],
    ];

    return pairs.map(([fg, bg, min, level]) => {
      const fgVal = tokens[fg[0]] || "#000000";
      const bgVal = tokens[bg[0]] || "#ffffff";
      const ratio = contrastRatio(fgVal, bgVal);
      return {
        fgKey: fg[0],
        bgKey: bg[0],
        fgLabel: fg[1],
        bgLabel: bg[1],
        ratio: Math.round(ratio * 100) / 100,
        passes: ratio >= min,
        level,
        required: min,
      };
    });
  }

  /* ── Wizard State ── */

  const SETUP_STORAGE_KEY = "neptunus_setup_wizard_state_v4";
  const SETUP_STORAGE_KEEP_KEY = "neptunus_setup_wizard_keep_v1";
  const SETUP_STORAGE_MAX_AGE_MS = 12 * 60 * 60 * 1000;
  let preserveDraftOnLeave = false;

  const state = {
    step: 0,
    totalSteps: 0,
    isFreshInstall: false,
    hasExistingTheme: false,
    colorsOptional: false,
    colorsDecision: "custom",
    language: "en",
    dbAction: "keep",
    light: {
      accent: "#00cd87",
      canvas: "#ffffff",
      text: "#222831",
      border: "#c8d2dc",
    },
    dark: {
      accent: "#00c887",
      canvas: "#20252c",
      text: "#f1ede6",
      border: "#4a525e",
    },
    presets: {},
    selectedPreset: "default",
    brandingMode: "default",
    admin: { name: "", email: "", password: "" },
    brandingUploaded: {
      logoLight: false,
      logoDark: false,
      faviconLight: false,
      faviconDark: false,
    },
    hasDirtyChanges: false,
    applyInProgress: false,
    idempotencyKey: "",
  };

  /* ── DOM Helpers ── */

  function $(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }
  function $$(sel, ctx) {
    return [...(ctx || document).querySelectorAll(sel)];
  }

  function getUiText(key, fallback) {
    if (typeof window.setupUiText === "object" && window.setupUiText[key]) {
      return window.setupUiText[key];
    }
    return fallback || key;
  }

  function isHexColor(value) {
    return /^#[0-9a-fA-F]{6}$/.test((value || "").trim());
  }

  function normalizeHexColor(value, fallback) {
    const source = (value || "").trim();
    if (isHexColor(source)) {
      return source.toLowerCase();
    }
    return fallback;
  }

  function createIdempotencyKey() {
    if (
      typeof crypto !== "undefined" &&
      typeof crypto.randomUUID === "function"
    ) {
      return crypto.randomUUID();
    }
    return "setup-" + Date.now() + "-" + Math.random().toString(16).slice(2);
  }

  function ensureIdempotencyKey() {
    if (!state.idempotencyKey) {
      state.idempotencyKey = createIdempotencyKey();
    }
    return state.idempotencyKey;
  }

  function persistState() {
    try {
      const payload = {
        savedAt: Date.now(),
        state: {
          step: state.step,
          isFreshInstall: state.isFreshInstall,
          dbAction: state.dbAction,
          colorsDecision: state.colorsDecision,
          selectedPreset: state.selectedPreset,
          brandingMode: state.brandingMode,
          light: state.light,
          dark: state.dark,
          admin: state.admin,
          brandingUploaded: state.brandingUploaded,
          hasDirtyChanges: state.hasDirtyChanges,
          idempotencyKey: state.idempotencyKey,
        },
      };
      sessionStorage.setItem(SETUP_STORAGE_KEY, JSON.stringify(payload));
    } catch (e) {
      // ignore storage failures
    }
  }

  function clearPersistedState() {
    try {
      sessionStorage.removeItem(SETUP_STORAGE_KEY);
    } catch (e) {
      // ignore storage failures
    }
  }

  function markPersistedStateForNextLoad() {
    preserveDraftOnLeave = true;
    try {
      sessionStorage.setItem(SETUP_STORAGE_KEEP_KEY, "1");
    } catch (e) {
      // ignore storage failures
    }
  }

  function consumePersistedStateRestoreFlag() {
    try {
      const shouldRestore =
        sessionStorage.getItem(SETUP_STORAGE_KEEP_KEY) === "1";
      sessionStorage.removeItem(SETUP_STORAGE_KEEP_KEY);
      return shouldRestore;
    } catch (e) {
      return false;
    }
  }

  function restorePersistedState() {
    try {
      const raw = sessionStorage.getItem(SETUP_STORAGE_KEY);
      if (!raw) {
        return false;
      }

      const parsed = JSON.parse(raw);
      if (!parsed || !parsed.state || !parsed.savedAt) {
        clearPersistedState();
        return false;
      }

      if (Date.now() - Number(parsed.savedAt || 0) > SETUP_STORAGE_MAX_AGE_MS) {
        clearPersistedState();
        return false;
      }

      const saved = parsed.state;
      if (!!saved.isFreshInstall !== state.isFreshInstall) {
        clearPersistedState();
        return false;
      }

      if (typeof saved.step === "number") {
        state.step = Math.max(0, saved.step);
      }

      if (typeof saved.dbAction === "string") {
        state.dbAction = saved.dbAction;
      }

      if (
        saved.colorsDecision === "keep" ||
        saved.colorsDecision === "custom"
      ) {
        state.colorsDecision = saved.colorsDecision;
      }

      if (typeof saved.selectedPreset === "string") {
        state.selectedPreset = saved.selectedPreset;
      }

      if (saved.brandingMode === "custom" || saved.brandingMode === "default") {
        state.brandingMode = saved.brandingMode;
      }

      if (saved.light && typeof saved.light === "object") {
        state.light.accent = normalizeHexColor(
          saved.light.accent,
          state.light.accent,
        );
        state.light.canvas = normalizeHexColor(
          saved.light.canvas,
          state.light.canvas,
        );
        state.light.text = normalizeHexColor(
          saved.light.text,
          state.light.text,
        );
        state.light.border = normalizeHexColor(
          saved.light.border,
          state.light.border,
        );
      }

      if (saved.dark && typeof saved.dark === "object") {
        state.dark.accent = normalizeHexColor(
          saved.dark.accent,
          state.dark.accent,
        );
        state.dark.canvas = normalizeHexColor(
          saved.dark.canvas,
          state.dark.canvas,
        );
        state.dark.text = normalizeHexColor(saved.dark.text, state.dark.text);
        state.dark.border = normalizeHexColor(
          saved.dark.border,
          state.dark.border,
        );
      }

      if (saved.admin && typeof saved.admin === "object") {
        state.admin.name = (saved.admin.name || "").toString();
        state.admin.email = (saved.admin.email || "").toString();
        state.admin.password = (saved.admin.password || "").toString();
      }

      if (
        saved.brandingUploaded &&
        typeof saved.brandingUploaded === "object"
      ) {
        state.brandingUploaded.logoLight = !!saved.brandingUploaded.logoLight;
        state.brandingUploaded.logoDark = !!saved.brandingUploaded.logoDark;
        state.brandingUploaded.faviconLight =
          !!saved.brandingUploaded.faviconLight;
        state.brandingUploaded.faviconDark =
          !!saved.brandingUploaded.faviconDark;
      }

      state.hasDirtyChanges = !!saved.hasDirtyChanges;

      if (typeof saved.idempotencyKey === "string") {
        state.idempotencyKey = saved.idempotencyKey;
      }

      return true;
    } catch (e) {
      clearPersistedState();
      return false;
    }
  }

  function markDirty() {
    state.hasDirtyChanges = true;
    persistState();
  }

  function updatePresetModeBadge() {
    const badge = $("#setupPresetModeBadge");
    if (!badge) return;

    if (state.selectedPreset === "current") {
      badge.textContent = getUiText("setup_preset_current", "Current theme");
      badge.classList.remove("is-custom");
      badge.classList.add("is-preset");
      return;
    }

    if (state.selectedPreset === "custom") {
      badge.textContent = getUiText("setup_preset_custom", "Custom colors");
      badge.classList.remove("is-preset");
      badge.classList.add("is-custom");
      return;
    }

    const preset = state.presets[state.selectedPreset];
    const presetLabel =
      preset && preset.label ? preset.label : state.selectedPreset;
    badge.textContent = presetLabel;
    badge.classList.remove("is-custom");
    badge.classList.add("is-preset");
  }

  function updatePresetRestoreButtonLabel() {
    const resetBtn = $(".setupPresetResetBtn");
    if (!resetBtn) return;

    const label = state.presets.current
      ? getUiText("setup_preset_restore_current", "Restore current theme")
      : getUiText("setup_preset_restore_default", "Restore default preset");

    const labelEl = resetBtn.querySelector(".setupPresetResetBtnLabel");
    if (labelEl) {
      labelEl.textContent = label;
    }
  }

  function switchToCustomPreset(silent) {
    if (state.selectedPreset === "custom") {
      return;
    }

    state.selectedPreset = "custom";
    state.colorsDecision = "custom";
    const select = $("#setupPresetSelect");
    if (select) {
      select.value = "custom";
    }
    updatePresetModeBadge();
    updateColorsKeepBanner();

    if (!silent) {
      showSnackbar(
        getUiText("setup_snack_colors_custom", "Custom colors active"),
        "success",
      );
    }
  }

  function updateColorInputsFromState(theme) {
    $$('.setupColorInput[data-theme="' + theme + '"]').forEach((row) => {
      const prop = row.dataset.prop;
      const ci = row.querySelector('input[type="color"]');
      const ti = row.querySelector('input[type="text"]');
      if (!prop) return;
      if (ci) ci.value = state[theme][prop];
      if (ti) ti.value = state[theme][prop];
    });
  }

  function updateDbOptionUi() {
    $$(".setupDbOption").forEach((opt) => {
      opt.classList.toggle(
        "is-selected",
        opt.dataset.action === state.dbAction,
      );
    });
  }

  function updateColorsKeepBanner() {
    const banner = $("#setupColorsKeepBanner");
    if (!banner) return;
    const colorsPanel = $('.setupStepPanel[data-step="colors"]');
    const isColorsStepActive = !!(
      colorsPanel && colorsPanel.classList.contains("is-active")
    );
    const shouldShow =
      state.colorsOptional &&
      state.colorsDecision === "keep" &&
      isColorsStepActive;
    banner.classList.toggle("is-visible", shouldShow);
  }

  function updateAdminInputsFromState() {
    const nameInput = $("#setupAdminName");
    const emailInput = $("#setupAdminEmail");
    const passwordInput = $("#setupAdminPassword");

    if (nameInput) nameInput.value = state.admin.name;
    if (emailInput) emailInput.value = state.admin.email;
    if (passwordInput) passwordInput.value = state.admin.password;
  }

  function hasCustomBrandingUploads() {
    const b = state.brandingUploaded;
    return !!(b.logoLight || b.logoDark || b.faviconLight || b.faviconDark);
  }

  function requiresBackupForApply() {
    if (state.isFreshInstall) {
      return false;
    }

    return (
      state.dbAction === "reset" ||
      state.colorsDecision !== "keep" ||
      (state.brandingMode === "custom" && hasCustomBrandingUploads())
    );
  }

  function updateBrandingModeUi() {
    const isCustom = state.brandingMode === "custom";

    $$("[data-branding-mode-btn]").forEach((btn) => {
      btn.classList.toggle(
        "is-active",
        btn.dataset.brandingModeBtn === state.brandingMode,
      );
    });

    const gridWrap = $("#setupBrandingGridWrap");
    const defaultNotice = $("#setupBrandingDefaultNotice");
    const modeHint = $("#setupBrandingModeHint");

    if (gridWrap) {
      gridWrap.classList.toggle("is-hidden", !isCustom);
    }

    if (defaultNotice) {
      defaultNotice.classList.toggle("is-hidden", isCustom);
    }

    if (modeHint) {
      modeHint.textContent = isCustom
        ? getUiText(
            "setup_branding_mode_custom_hint",
            "Upload logo and favicon variants below. Uploaded assets are stored immediately.",
          )
        : getUiText(
            "setup_branding_mode_default_hint",
            "Default branding stays active and this preference will be saved.",
          );
    }
  }

  function isAdminStepRequired() {
    return state.isFreshInstall || state.dbAction === "reset";
  }

  function getVisiblePanels() {
    return $$(".setupStepPanel").filter((panel) => !panel.hidden);
  }

  function getVisibleProgressSteps() {
    return $$(".setupProgressStep").filter((step) => !step.hidden);
  }

  function syncProgressStepNumbers() {
    const visibleSteps = getVisibleProgressSteps();

    visibleSteps.forEach((step, index) => {
      const dot = step.querySelector(".setupProgressDot");
      if (dot) {
        dot.textContent = String(index + 1);
      }

      const label = step.querySelector(".setupProgressLabel");
      const labelText = label ? (label.textContent || "").trim() : "";
      step.setAttribute(
        "aria-label",
        index + 1 + (labelText ? ". " + labelText : ""),
      );
    });
  }

  function updateBackupNoticeVisibility(activePanel) {
    const backupNotice = $(".setupBackupNotice");
    if (!backupNotice) {
      return;
    }

    const isReviewStep = !!(
      activePanel && activePanel.dataset.step === "review"
    );

    const needsBackup = requiresBackupForApply();

    backupNotice.classList.toggle("is-visible", isReviewStep && needsBackup);
  }

  function bindProgressStepNavigation() {
    $$(".setupProgressStep").forEach((step) => {
      step.setAttribute("role", "button");

      const activateStep = () => {
        const visibleSteps = getVisibleProgressSteps();
        const targetIndex = visibleSteps.indexOf(step);
        if (targetIndex < 0 || targetIndex >= state.step) {
          return;
        }
        goToStep(targetIndex, true);
      };

      step.addEventListener("click", activateStep);
      step.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          activateStep();
        }
      });
    });
  }

  function syncAdminStepVisibility() {
    const allPanels = $$(".setupStepPanel");
    const allProgressSteps = $$(".setupProgressStep");
    const adminPanelIndex = allPanels.findIndex(
      (panel) => panel.dataset.step === "admin",
    );

    if (adminPanelIndex < 0) {
      return;
    }

    const adminPanel = allPanels[adminPanelIndex];
    const adminProgressStep = allProgressSteps[adminPanelIndex] || null;
    const shouldShowAdminStep = isAdminStepRequired();

    if (adminPanel) {
      adminPanel.hidden = !shouldShowAdminStep;
      if (!shouldShowAdminStep) {
        adminPanel.classList.remove("is-active");
      }
    }

    if (adminProgressStep) {
      adminProgressStep.hidden = !shouldShowAdminStep;
      if (!shouldShowAdminStep) {
        adminProgressStep.classList.remove("is-active", "is-completed");
      }
    }

    state.totalSteps = getVisiblePanels().length;
    syncProgressStepNumbers();
  }

  function parseJsonSafe(response) {
    return response.json().catch(() => null);
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, (char) => {
      const map = {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      };
      return map[char] || char;
    });
  }

  /* ── Snackbar ── */

  let snackbarTimer = null;
  let snackbarQueue = [];
  let snackbarVisible = false;

  function _flushSnackbar() {
    if (snackbarQueue.length === 0) {
      snackbarVisible = false;
      return;
    }
    const { message, type } = snackbarQueue.shift();
    const el = document.getElementById("setupSnackbar");
    if (!el) {
      snackbarVisible = false;
      return;
    }
    snackbarVisible = true;
    el.textContent = message;
    el.className = "setupSnackbar is-visible" + (type ? " is-" + type : "");
    clearTimeout(snackbarTimer);
    snackbarTimer = setTimeout(() => {
      el.classList.remove("is-visible");
      setTimeout(_flushSnackbar, 260);
    }, 4000);
  }

  function showSnackbar(message, type) {
    if (snackbarVisible) {
      // Replace or queue: if queue already has an item, replace it to keep it brief
      if (snackbarQueue.length > 0) {
        snackbarQueue[snackbarQueue.length - 1] = { message, type };
      } else {
        snackbarQueue.push({ message, type });
      }
      return;
    }
    snackbarQueue.push({ message, type });
    _flushSnackbar();
  }

  /* ── Step Validation ── */

  function validateCurrentStep() {
    const panels = getVisiblePanels();
    const panel = panels[state.step];
    if (!panel) return true;

    const stepId = panel.dataset.step;

    // Admin step: require email + password
    if (stepId === "admin" && isAdminStepRequired()) {
      const email = state.admin.email.trim();
      const password = state.admin.password;

      function setFieldError(inputEl, hasError) {
        const group = inputEl ? inputEl.closest(".setupFormGroup") : null;
        if (group) {
          group.classList.toggle("has-error", hasError);
          if (hasError) {
            inputEl.addEventListener("input", function clearErr() {
              group.classList.remove("has-error");
              inputEl.removeEventListener("input", clearErr);
            });
          }
        }
      }

      if (!email) {
        showSnackbar(
          getUiText(
            "setup_err_email_required",
            "Please enter an email address for the admin account.",
          ),
          "error",
        );
        const emailInput = $("#setupAdminEmail");
        setFieldError(emailInput, true);
        if (emailInput) emailInput.focus();
        return false;
      }

      // Basic email pattern
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showSnackbar(
          getUiText(
            "setup_err_email_invalid",
            "Please enter a valid email address.",
          ),
          "error",
        );
        const emailInput = $("#setupAdminEmail");
        setFieldError(emailInput, true);
        if (emailInput) emailInput.focus();
        return false;
      }

      if (!password || password.length < 6) {
        showSnackbar(
          getUiText(
            "setup_err_password_short",
            "Password must be at least 6 characters.",
          ),
          "error",
        );
        const pwInput = $("#setupAdminPassword");
        setFieldError(pwInput, true);
        if (pwInput) pwInput.focus();
        return false;
      }
    }

    return true;
  }

  /* ── Step Navigation ── */

  function goToStep(index, skipValidation) {
    const panels = getVisiblePanels();
    const steps = getVisibleProgressSteps();
    const totalVisible = panels.length;

    if (index < 0 || index >= totalVisible) return;

    // Validate current step before moving forward
    if (!skipValidation && index > state.step && !validateCurrentStep()) {
      return;
    }

    state.step = Math.max(0, Math.min(totalVisible - 1, index));

    $$(".setupStepPanel").forEach((panel) => {
      panel.classList.remove("is-active");
    });
    if (panels[state.step]) {
      panels[state.step].classList.add("is-active");
    }

    $$(".setupProgressStep").forEach((step) => {
      step.classList.remove("is-active", "is-completed");
    });
    steps.forEach((step, i) => {
      step.classList.toggle("is-active", i === state.step);
      step.classList.toggle("is-completed", i < state.step);
      step.tabIndex = i < state.step ? 0 : -1;
      step.setAttribute("aria-disabled", i < state.step ? "false" : "true");
    });

    // Update buttons
    const btnPrev = $(".setupBtn-prev");
    const btnNext = $(".setupBtn-next");
    const btnApply = $(".setupBtn-apply");

    if (btnPrev) btnPrev.classList.toggle("is-hidden", state.step === 0);
    if (btnNext)
      btnNext.classList.toggle("is-hidden", state.step >= totalVisible - 1);
    if (btnApply)
      btnApply.classList.toggle("is-hidden", state.step < totalVisible - 1);

    // Update step title
    const stepTitle = $(".setupCardTitle");
    const stepSubtitle = $(".setupCardSubtitle");
    const panel = panels[state.step];
    if (panel && stepTitle) {
      stepTitle.textContent = panel.dataset.stepTitle || "";
    }
    if (panel && stepSubtitle) {
      stepSubtitle.textContent = panel.dataset.stepSubtitle || "";
    }

    // If colors step, refresh preview
    if (panel && panel.dataset.step === "colors") {
      refreshPreview("light");
      refreshPreview("dark");
      updateColorsKeepBanner();
    } else {
      updateColorsKeepBanner();
    }

    // If review step, build review
    if (panel && panel.dataset.step === "review") {
      buildReview();
    }

    if (panel && panel.dataset.step === "branding") {
      updateBrandingModeUi();
    }

    updateBackupNoticeVisibility(panel);

    persistState();
  }

  /* ── Color Input Binding ── */

  function bindColorInputs() {
    $$(".setupColorInput").forEach((row) => {
      const colorInput = row.querySelector('input[type="color"]');
      const textInput = row.querySelector('input[type="text"]');
      const theme = row.dataset.theme;
      const prop = row.dataset.prop;

      if (!colorInput || !textInput || !theme || !prop) return;

      colorInput.addEventListener("input", () => {
        textInput.value = colorInput.value;
        state[theme][prop] = colorInput.value;
        switchToCustomPreset(true);
        onColorsChanged(theme);
      });

      textInput.addEventListener("input", () => {
        const val = textInput.value.trim();
        if (/^#[0-9a-fA-F]{6}$/.test(val)) {
          colorInput.value = val;
          state[theme][prop] = val;
          switchToCustomPreset(true);
          onColorsChanged(theme);
        }
      });

      textInput.addEventListener("blur", () => {
        let val = textInput.value.trim();
        if (val && !val.startsWith("#")) val = "#" + val;
        if (/^#[0-9a-fA-F]{6}$/.test(val)) {
          textInput.value = val;
          colorInput.value = val;
          state[theme][prop] = val;
          switchToCustomPreset(true);
          onColorsChanged(theme);
        } else {
          textInput.value = state[theme][prop];
          colorInput.value = state[theme][prop];
        }
      });
    });
  }

  function onColorsChanged(theme) {
    refreshPreview(theme);
    markDirty();
  }

  /* ── Live Preview ── */

  function refreshPreview(theme) {
    const colors = state[theme];
    const isDark = theme === "dark";
    const tokens = generateTheme(
      colors.accent,
      colors.canvas,
      colors.text,
      colors.border,
      isDark,
    );

    // Update preview strip
    const preview = $(`.setupPreview[data-theme="${theme}"]`);
    if (preview) {
      const bar = preview.querySelector(".setupPreviewBar");
      const content = preview.querySelector(".setupPreviewContent");
      const btnPrimary = preview.querySelector(".setupPreviewBtn.is-primary");
      const btnDanger = preview.querySelector(".setupPreviewBtn.is-danger");
      const accentDot = preview.querySelector(".setupPreviewAccent");

      if (bar) {
        bar.style.background = tokens["surface-base"];
        bar.style.color = tokens["text-primary"];
        bar.style.borderBottom = "1px solid " + tokens["border-default"];
      }
      if (content) {
        content.style.background = tokens["surface-canvas"];
        content.style.color = tokens["text-primary"];
      }
      if (btnPrimary) {
        btnPrimary.style.background = tokens["action-primary-bg"];
        btnPrimary.style.color = tokens["action-primary-fg"];
      }
      if (btnDanger) {
        btnDanger.style.background = tokens["action-danger-bg"];
        btnDanger.style.color = tokens["action-danger-fg"];
      }
      if (accentDot) {
        accentDot.style.background = tokens["color-accent"];
      }
    }

    // Update contrast results
    const results = checkContrast(tokens);
    const container = $(`.setupContrastResults[data-theme="${theme}"]`);
    if (container) {
      const rows = container.querySelectorAll(".setupContrastRow");
      results.forEach((r, i) => {
        if (!rows[i]) return;
        const badge = rows[i].querySelector(".setupContrastBadge");
        if (badge) {
          badge.className =
            "setupContrastBadge " + (r.passes ? "is-pass" : "is-fail");
          badge.textContent =
            r.ratio.toFixed(1) + ":1 " + (r.passes ? "\u2713" : "\u2717");
        }
      });
    }
  }

  /* ── Presets ── */

  function applyPreset(presetKey, options) {
    const opts = options || {};
    const shouldPersist = opts.persist !== false;
    const shouldMarkDirty = opts.markDirty !== false;
    const shouldSetCustomDecision = opts.setCustomDecision !== false;

    if (!presetKey || presetKey === "custom") {
      state.selectedPreset = "custom";
      if (shouldSetCustomDecision) {
        state.colorsDecision = "custom";
      }
      updatePresetModeBadge();
      updateColorsKeepBanner();
      if (shouldMarkDirty) {
        markDirty();
      } else if (shouldPersist) {
        persistState();
      }
      return;
    }

    const preset = state.presets[presetKey];
    if (!preset) {
      return;
    }

    state.selectedPreset = presetKey;

    ["light", "dark"].forEach((theme) => {
      const colors = preset[theme];
      if (!colors) return;

      Object.keys(colors).forEach((key) => {
        state[theme][key] = colors[key];
      });

      updateColorInputsFromState(theme);
      refreshPreview(theme);
    });

    updatePresetModeBadge();

    if (shouldSetCustomDecision) {
      if (presetKey === "current") {
        state.colorsDecision = state.colorsOptional ? "keep" : "custom";
      } else {
        state.colorsDecision = "custom";
      }
    }
    updateColorsKeepBanner();

    if (shouldMarkDirty) {
      markDirty();
    } else if (shouldPersist) {
      persistState();
    }
  }

  function bindPresets() {
    const select = $("#setupPresetSelect");
    const resetBtn = $(".setupPresetResetBtn");

    if (select) {
      select.addEventListener("change", () => {
        const value = (select.value || "custom").trim();
        applyPreset(value);
      });
    }

    if (resetBtn) {
      resetBtn.addEventListener("click", () => {
        const restoreKey = state.presets.current ? "current" : "default";
        applyPreset(restoreKey);
        const presetSelect = $("#setupPresetSelect");
        if (presetSelect) {
          presetSelect.value = restoreKey;
        }
        showSnackbar(
          restoreKey === "current"
            ? getUiText(
                "setup_snack_preset_restored_current",
                "Current theme restored.",
              )
            : getUiText(
                "setup_snack_preset_restored_default",
                "Default preset restored.",
              ),
          "success",
        );
      });
    }

    updatePresetModeBadge();
    updatePresetRestoreButtonLabel();
  }

  /* ── Database Options ── */

  function bindDbOptions() {
    $$(".setupDbOption").forEach((opt) => {
      opt.tabIndex = 0;
      opt.setAttribute("role", "button");

      const activateOption = () => {
        $$(".setupDbOption").forEach((o) => o.classList.remove("is-selected"));
        opt.classList.add("is-selected");
        state.dbAction = opt.dataset.action;

        syncAdminStepVisibility();
        goToStep(state.step, true);

        markDirty();
      };

      opt.addEventListener("click", () => {
        activateOption();
      });

      opt.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          activateOption();
        }
      });
    });
  }

  function bindBrandingMode() {
    $$("[data-branding-mode-btn]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const mode = (btn.dataset.brandingModeBtn || "").trim();
        if (mode !== "default" && mode !== "custom") {
          return;
        }
        if (state.brandingMode === mode) {
          return;
        }

        state.brandingMode = mode;
        updateBrandingModeUi();

        const panel = getVisiblePanels()[state.step] || null;
        updateBackupNoticeVisibility(panel);
        markDirty();
      });
    });
  }

  /* ── Admin Account Binding ── */

  function bindAdminForm() {
    const nameInput = $("#setupAdminName");
    const emailInput = $("#setupAdminEmail");
    const passwordInput = $("#setupAdminPassword");

    if (nameInput)
      nameInput.addEventListener("input", () => {
        state.admin.name = nameInput.value;
        markDirty();
      });
    if (emailInput)
      emailInput.addEventListener("input", () => {
        state.admin.email = emailInput.value;
        markDirty();
      });
    if (passwordInput)
      passwordInput.addEventListener("input", () => {
        state.admin.password = passwordInput.value;
        markDirty();
      });

    // Password visibility toggle
    const toggleBtn = $(".setupPasswordToggle");
    if (toggleBtn && passwordInput) {
      toggleBtn.addEventListener("click", () => {
        const isPassword = passwordInput.type === "password";
        passwordInput.type = isPassword ? "text" : "password";
        const icon = toggleBtn.querySelector(".material-icons");
        if (icon) {
          icon.textContent = isPassword ? "visibility_off" : "visibility";
        }
        passwordInput.focus();
      });
    }
  }

  /* ── Build Review ── */

  function buildReview() {
    const reviewContainer = $(".setupReviewContent");
    if (!reviewContainer) return;

    let html = "";

    // Database
    html += '<div class="setupReviewSection">';
    html +=
      '<h4><i class="material-icons">storage</i> ' +
      getUiText("setup_review_database", "Database") +
      "</h4>";
    const dbLabels = {
      keep: getUiText("setup_db_keep", "Keep current database"),
      reset: getUiText("setup_db_reset", "Reset to clean slate"),
      fresh: getUiText("setup_db_fresh", "Fresh database"),
    };
    html +=
      "<p>" + escapeHtml(dbLabels[state.dbAction] || state.dbAction) + "</p>";
    html += "</div>";

    // Colors
    html += '<div class="setupReviewSection">';
    html +=
      '<h4><i class="material-icons">palette</i> ' +
      getUiText("setup_review_colors", "Theme Colors") +
      "</h4>";
    const selectedPreset = state.selectedPreset || "custom";
    const selectedPresetLabel =
      selectedPreset === "custom"
        ? getUiText("setup_preset_custom", "Custom colors")
        : (state.presets[selectedPreset] &&
            state.presets[selectedPreset].label) ||
          selectedPreset;
    html +=
      "<p><strong>" +
      getUiText("setup_review_preset", "Preset") +
      ":</strong> " +
      escapeHtml(selectedPresetLabel) +
      "</p>";
    if (state.colorsDecision === "keep" && state.colorsOptional) {
      html +=
        '<p class="setupReviewNote setupReviewNote-muted">' +
        escapeHtml(
          getUiText(
            "setup_review_colors_kept",
            "Existing theme colors will be kept unchanged.",
          ),
        ) +
        "</p>";
    }
    html +=
      "<p><strong>" +
      getUiText("setup_light_theme", "Light") +
      ":</strong></p>";
    html += '<div class="setupReviewSwatches">';
    const swatchLabels = {
      accent: "Accent",
      canvas: "Background",
      text: "Text",
      border: "Border",
    };
    ["accent", "canvas", "text", "border"].forEach((key) => {
      const colorValue = escapeHtml(state.light[key]);
      html +=
        '<span class="setupReviewSwatch" style="background:' +
        colorValue +
        '" title="' +
        escapeHtml(swatchLabels[key] || key) +
        ": " +
        colorValue +
        '"></span>';
    });
    html += "</div>";
    html +=
      "<p><strong>" + getUiText("setup_dark_theme", "Dark") + ":</strong></p>";
    html += '<div class="setupReviewSwatches">';
    ["accent", "canvas", "text", "border"].forEach((key) => {
      const colorValue = escapeHtml(state.dark[key]);
      html +=
        '<span class="setupReviewSwatch" style="background:' +
        colorValue +
        '" title="' +
        escapeHtml(swatchLabels[key] || key) +
        ": " +
        colorValue +
        '"></span>';
    });
    html += "</div>";

    // Contrast summary
    const lightTokens = generateTheme(
      state.light.accent,
      state.light.canvas,
      state.light.text,
      state.light.border,
      false,
    );
    const darkTokens = generateTheme(
      state.dark.accent,
      state.dark.canvas,
      state.dark.text,
      state.dark.border,
      true,
    );
    const lightResults = checkContrast(lightTokens);
    const darkResults = checkContrast(darkTokens);
    const lightPass = lightResults.filter((r) => r.passes).length;
    const darkPass = darkResults.filter((r) => r.passes).length;
    const allPasses =
      lightPass === lightResults.length && darkPass === darkResults.length;

    html += '<p class="setupReviewContrastSummary">';
    if (allPasses) {
      html +=
        '<span class="is-pass">\u2713 ' +
        escapeHtml(
          getUiText("setup_review_contrast_pass", "All contrast checks pass"),
        ) +
        "</span>";
    } else {
      html +=
        '<span class="is-fail">\u2717 ' +
        escapeHtml(
          getUiText(
            "setup_review_contrast_fail",
            "Some contrast checks fail - auto-fix will be applied",
          ),
        ) +
        "</span>";
    }
    html += "</p>";
    html += "</div>";

    // Branding
    html += '<div class="setupReviewSection">';
    html +=
      '<h4><i class="material-icons">branding_watermark</i> ' +
      getUiText("setup_review_branding", "Branding") +
      "</h4>";
    const b = state.brandingUploaded;
    const brandCount = [
      b.logoLight,
      b.logoDark,
      b.faviconLight,
      b.faviconDark,
    ].filter(Boolean).length;
    if (state.brandingMode !== "custom") {
      html +=
        "<p>" +
        escapeHtml(
          getUiText(
            "setup_branding_default_notice",
            "Default branding is selected. You can switch to custom branding at any time.",
          ),
        ) +
        "</p>";
    } else if (brandCount > 0) {
      const assetsCountTemplate = getUiText(
        "setup_review_assets_count",
        "{n}/4 assets uploaded",
      );
      html +=
        "<p>" +
        escapeHtml(assetsCountTemplate.replace("{n}", String(brandCount))) +
        "</p>";
    } else {
      html +=
        '<p class="setupReviewMuted">' +
        escapeHtml(
          getUiText(
            "setup_review_no_branding",
            "No custom branding uploaded (using defaults)",
          ),
        ) +
        "</p>";
    }
    html += "</div>";

    // Admin (fresh install or reset)
    if (isAdminStepRequired() && state.admin.email) {
      html += '<div class="setupReviewSection">';
      html +=
        '<h4><i class="material-icons">person</i> ' +
        getUiText("setup_review_admin", "Admin Account") +
        "</h4>";
      html +=
        "<p>" +
        escapeHtml(
          state.admin.name || getUiText("setup_review_no_name", "(no name)"),
        ) +
        " &mdash; " +
        escapeHtml(state.admin.email) +
        "</p>";
      html += "</div>";
    }

    // Backup notice
    if (requiresBackupForApply()) {
      html += '<div class="setupReviewSection">';
      html +=
        '<h4><i class="material-icons">backup</i> ' +
        getUiText("setup_review_backup", "Backup") +
        "</h4>";
      html +=
        '<p class="setupReviewMuted">' +
        escapeHtml(
          getUiText(
            "setup_review_backup_notice",
            "A full backup will be created automatically before changes are applied.",
          ),
        ) +
        "</p>";
      html += "</div>";
    }

    reviewContainer.innerHTML = html;
  }

  /* ── Apply (Submit Wizard) ── */

  async function applySetup() {
    if (state.applyInProgress) {
      return;
    }

    state.applyInProgress = true;
    ensureIdempotencyKey();
    persistState();

    const applyBtn = $(".setupBtn-apply");
    if (applyBtn) {
      applyBtn.disabled = true;
      applyBtn.innerHTML =
        '<span class="setupSpinner"></span> ' +
        getUiText("setup_applying", "Applying...");
    }

    try {
      const idempotencyHeaders = {
        "X-Idempotency-Key": state.idempotencyKey,
        "X-Setup-Idempotency-Key": state.idempotencyKey,
      };

      // Step 1: Database action
      if (state.dbAction === "reset") {
        const res = await fetch("/resetProject", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            ...idempotencyHeaders,
          },
          body: JSON.stringify({ resetDatabase: true, resetColors: false }),
        });
        const data = await parseJsonSafe(res);
        if (!res.ok || (data && data.success === false)) {
          throw new Error(
            (data && data.message) ||
              getUiText("setup_err_db_reset_failed", "Database reset failed."),
          );
        }
      }

      // Step 2: Apply colors
      if (state.colorsDecision !== "keep") {
        const res = await fetch("/generateTheme", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            ...idempotencyHeaders,
          },
          body: JSON.stringify({
            action: "apply",
            light: state.light,
            dark: state.dark,
            autoFix: true,
          }),
        });
        const themeData = await parseJsonSafe(res);
        if (!res.ok || (themeData && themeData.success === false)) {
          throw new Error(
            (themeData && themeData.message) ||
              getUiText("setup_err_theme_failed", "Theme generation failed."),
          );
        }
      }

      const prefRes = await fetch("/saveSetupPreferences", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          ...idempotencyHeaders,
        },
        body: JSON.stringify({
          brandingMode: state.brandingMode,
          selectedPreset: state.selectedPreset,
        }),
      });
      const prefData = await parseJsonSafe(prefRes);
      if (!prefRes.ok || (prefData && prefData.success === false)) {
        throw new Error(
          (prefData && prefData.message) ||
            getUiText(
              "setup_err_preferences_failed",
              "Failed to save setup preferences.",
            ),
        );
      }

      let redirectAfterSuccess = state.isFreshInstall ? "/links" : "/admin";

      // Step 3: Create admin user when the flow ends with an empty users table
      const shouldCreateAdmin = isAdminStepRequired();
      if (shouldCreateAdmin) {
        const email = (state.admin.email || "").trim();
        const password = state.admin.password || "";
        if (!email || !password || password.length < 6) {
          throw new Error(
            getUiText(
              "setup_err_admin_required",
              "Please fill in a valid admin account before clicking Apply.",
            ),
          );
        }
      }

      if (shouldCreateAdmin) {
        const formData = new FormData();
        formData.append("name", state.admin.name || "Admin");
        formData.append("email", state.admin.email);
        formData.append("password", state.admin.password);
        formData.append("role", "superadmin");
        formData.append("family_name", "");

        const regRes = await fetch("/register", {
          method: "POST",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
            ...idempotencyHeaders,
          },
          body: formData,
        });

        const regData = await parseJsonSafe(regRes);
        if (!regRes.ok || !regData || regData.success !== true) {
          throw new Error(
            (regData && regData.message) ||
              getUiText(
                "setup_err_admin_failed",
                "Admin account creation failed.",
              ),
          );
        }

        if (regData.redirect && typeof regData.redirect === "string") {
          redirectAfterSuccess = regData.redirect;
        }
      }

      // Done — show success then redirect
      state.hasDirtyChanges = false;
      state.applyInProgress = false;
      clearPersistedState();
      showSnackbar(
        getUiText("setup_success_complete", "Setup completed successfully!"),
        "success",
      );
      setTimeout(() => {
        window.location.href = redirectAfterSuccess;
      }, 900);
    } catch (err) {
      state.applyInProgress = false;
      console.error("Setup error:", err);
      showSnackbar(
        err.message ||
          getUiText("setup_err_generic", "An error occurred during setup."),
        "error",
      );
      if (applyBtn) {
        applyBtn.disabled = false;
        applyBtn.innerHTML =
          '<i class="material-icons">check</i> ' +
          getUiText("setup_apply", "Apply & Finish");
      }
      persistState();
    }
  }

  /* ── Language Switch ── */

  function bindLanguage() {
    $$(".setupLangBtn").forEach((btn) => {
      btn.addEventListener("click", () => {
        const lang = btn.dataset.lang;
        if (!lang) return;
        persistState();
        markPersistedStateForNextLoad();
        // Reload with language parameter
        const url = new URL(window.location);
        url.searchParams.set("lang", lang);
        url.searchParams.set("setupStep", String(state.step || 0));
        window.location.href = url.toString();
      });
    });
  }

  /* ── Branding Uploads ── */

  function bindBrandingUploads() {
    $$(".setupBrandingUploadBtn").forEach((btn) => {
      const card = btn.closest(".setupBrandingCard");
      const input = card ? card.querySelector('input[type="file"]') : null;
      if (!input) return;

      btn.addEventListener("click", () => {
        if (state.brandingMode !== "custom") {
          showSnackbar(
            getUiText(
              "setup_snack_branding_switch_custom",
              "Switch to Custom branding to upload assets.",
            ),
            "error",
          );
          return;
        }
        input.click();
      });

      input.addEventListener("change", async () => {
        if (!input.files || !input.files.length) return;
        const file = input.files[0];
        const assetType = input.dataset.assetType;
        const theme = input.dataset.theme;

        const formData = new FormData();
        formData.append("assetFile", file);
        formData.append("type", assetType);
        formData.append("theme", theme);

        ensureIdempotencyKey();

        try {
          const res = await fetch("/uploadBrandAsset", {
            method: "POST",
            headers: {
              "X-Requested-With": "XMLHttpRequest",
              "X-Idempotency-Key": state.idempotencyKey,
            },
            body: formData,
          });
          const data = await res.json();
          if (data.success) {
            // Construct asset path from type/theme
            const assetPath =
              assetType === "logo"
                ? "/custom/images/logo/logo-" + theme + ".svg"
                : "/custom/images/icons/favicon-" + theme + ".svg";
            // Update preview image
            const previewDiv = card.querySelector(".setupBrandingPreview");
            if (previewDiv) {
              previewDiv.innerHTML =
                '<img src="' +
                assetPath +
                "?v=" +
                Date.now() +
                '" alt="' +
                assetType +
                " " +
                theme +
                '">';
            }
            // Track upload status
            const key =
              assetType + theme.charAt(0).toUpperCase() + theme.slice(1);
            state.brandingUploaded[key] = true;
            state.brandingMode = "custom";
            updateBrandingModeUi();
            // Mark card as uploaded
            if (card) card.classList.add("is-uploaded");
            const panel = getVisiblePanels()[state.step] || null;
            updateBackupNoticeVisibility(panel);
            markDirty();
            showSnackbar(
              getUiText("setup_snack_upload_success", "Upload successful!"),
              "success",
            );
          } else {
            showSnackbar(
              data.message ||
                getUiText("setup_snack_upload_failed", "Upload failed."),
              "error",
            );
          }
        } catch (e) {
          console.error("Brand upload error:", e);
          showSnackbar(
            getUiText(
              "setup_snack_upload_retry",
              "Upload failed. Please try again.",
            ),
            "error",
          );
        }

        input.value = "";
      });
    });
  }

  /* ── Init ── */

  function bindUnloadGuard() {
    window.addEventListener("beforeunload", (event) => {
      if (!state.hasDirtyChanges || state.applyInProgress) {
        return;
      }
      event.preventDefault();
      event.returnValue = "";
    });
  }

  function bindExitStateReset() {
    const cancelLink = $(".setupCancelLink");
    if (cancelLink) {
      cancelLink.addEventListener("click", () => {
        state.hasDirtyChanges = false;
        clearPersistedState();
      });
    }

    window.addEventListener("pagehide", () => {
      if (state.applyInProgress || preserveDraftOnLeave) {
        return;
      }
      clearPersistedState();
    });
  }

  function init() {
    const wizard = $(".setupWizard");
    if (!wizard) return;

    state.isFreshInstall = wizard.dataset.freshInstall === "1";
    state.hasExistingTheme = wizard.dataset.hasExistingTheme === "1";
    state.brandingMode =
      wizard.dataset.brandingMode === "custom" ? "custom" : "default";
    state.language = document.documentElement.lang || "en";
    const colorsPanel = $('.setupStepPanel[data-step="colors"]');
    state.colorsOptional = !!(
      colorsPanel && colorsPanel.dataset.colorsOptional === "1"
    );

    // Set default dbAction based on context
    if (state.isFreshInstall) {
      state.dbAction = "fresh";
    }

    // Load presets from data attribute
    try {
      state.presets = JSON.parse(wizard.dataset.presets || "{}");
    } catch (e) {
      state.presets = {};
    }

    // Init color state from inputs
    $$(".setupColorInput").forEach((row) => {
      const theme = row.dataset.theme;
      const prop = row.dataset.prop;
      const val = row.querySelector('input[type="text"]')?.value;
      if (theme && prop && val) {
        state[theme][prop] = normalizeHexColor(val, state[theme][prop]);
      }
    });

    const shouldRestoreDraft = consumePersistedStateRestoreFlag();
    if (!shouldRestoreDraft) {
      clearPersistedState();
    }
    const restored = shouldRestoreDraft ? restorePersistedState() : false;

    if (!restored) {
      if (state.colorsOptional && state.presets.current) {
        state.selectedPreset = "current";
        state.colorsDecision = "keep";
      } else {
        state.selectedPreset = "default";
        state.colorsDecision = "custom";
      }
    }

    syncAdminStepVisibility();

    // Sync state back to UI
    updateDbOptionUi();
    updateAdminInputsFromState();
    updateBrandingModeUi();

    const presetSelect = $("#setupPresetSelect");
    if (presetSelect) {
      if (
        state.selectedPreset &&
        presetSelect.querySelector(
          'option[value="' + state.selectedPreset + '"]',
        )
      ) {
        presetSelect.value = state.selectedPreset;
      } else {
        state.selectedPreset =
          state.colorsOptional && state.presets.current ? "current" : "default";
        presetSelect.value = state.selectedPreset;
      }
    }

    if (
      state.selectedPreset !== "custom" &&
      state.presets[state.selectedPreset]
    ) {
      applyPreset(state.selectedPreset, {
        persist: false,
        markDirty: false,
        setCustomDecision: false,
      });
    } else {
      ["light", "dark"].forEach((theme) => {
        updateColorInputsFromState(theme);
        refreshPreview(theme);
      });
      updatePresetModeBadge();
    }

    if (!restored) {
      state.hasDirtyChanges = false;
      persistState();
    }

    bindColorInputs();
    bindPresets();
    bindProgressStepNavigation();
    bindDbOptions();
    bindBrandingMode();
    bindAdminForm();
    bindLanguage();
    bindBrandingUploads();
    bindUnloadGuard();
    bindExitStateReset();

    // Navigation buttons
    const btnPrev = $(".setupBtn-prev");
    const btnNext = $(".setupBtn-next");
    const btnApply = $(".setupBtn-apply");

    if (btnPrev)
      btnPrev.addEventListener("click", () => goToStep(state.step - 1, true));
    if (btnNext)
      btnNext.addEventListener("click", () => goToStep(state.step + 1));
    if (btnApply) btnApply.addEventListener("click", () => applySetup());

    // Restore previous step from draft (or URL query) when available.
    const stepFromUrl = Number(
      new URL(window.location.href).searchParams.get("setupStep") || "0",
    );
    const initialStep =
      Number.isFinite(stepFromUrl) && stepFromUrl > state.step
        ? stepFromUrl
        : state.step;
    goToStep(initialStep || 0, true);

    if (restored) {
      showSnackbar("Concept hersteld.", "success");
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
