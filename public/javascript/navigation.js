const strokes = document.querySelectorAll(".stroke");
const navContainer = document.querySelector(".navContainer");
const navIcon = document.querySelector("#nav-icon3");
const backgroundDarken = document.querySelector(".backgroundDarken");
const NAV_COMPACT_BREAKPOINT = 768;

function setNavigationOpen(isOpen) {
  if (!navContainer || !backgroundDarken) {
    return;
  }

  navContainer.classList.toggle("nav-inactive", !isOpen);
  navIcon?.classList.toggle("open", isOpen);
  backgroundDarken.style.display = isOpen ? "block" : "none";

  strokes.forEach((stroke) => {
    stroke.classList.toggle("stroke-active", isOpen);
  });
}

document.querySelectorAll(".nav-toggle").forEach((navLink) => {
  navLink.addEventListener("click", (event) => {
    event.preventDefault();
    const shouldOpen = navContainer?.classList.contains("nav-inactive");
    setNavigationOpen(Boolean(shouldOpen));
  });
});

backgroundDarken?.addEventListener("click", () => {
  setNavigationOpen(false);
});

window.addEventListener(
  "resize",
  _debounce(() => {
    if (!navContainer || !backgroundDarken) {
      return;
    }

    if (window.innerWidth <= NAV_COMPACT_BREAKPOINT) {
      navContainer.classList.remove("nav-inactive");
      backgroundDarken.style.display = "none";
      navIcon?.classList.remove("open");
      strokes.forEach((stroke) => stroke.classList.remove("stroke-active"));
    } else if (!navContainer.classList.contains("nav-inactive")) {
      backgroundDarken.style.display = "block";
    }
  }, 200),
);

if (
  navContainer &&
  backgroundDarken &&
  window.innerWidth <= NAV_COMPACT_BREAKPOINT
) {
  navContainer.classList.remove("nav-inactive");
  backgroundDarken.style.display = "none";
  navIcon?.classList.remove("open");
}

window.requestAnimationFrame(() => {
  document.body.classList.add("nav-ready");
});

function toggleSecondaire(event) {
  if (event) {
    event.stopPropagation();
  }

  const secondaryNav = document.getElementById("secondaryNav");
  if (!secondaryNav) {
    return;
  }

  const willOpen = !secondaryNav.classList.contains("active");
  secondaryNav.classList.toggle("active", willOpen);

  if (willOpen) {
    document.body.addEventListener("click", closeSecondaire);
  } else {
    document.body.removeEventListener("click", closeSecondaire);
  }
}

function closeSecondaire(event) {
  const secondaryNav = document.getElementById("secondaryNav");
  const profileContainer =
    document.querySelector(".profileNavCon") ||
    document.querySelector(".userImageContainer");

  if (!secondaryNav || !profileContainer) {
    return;
  }

  if (profileContainer.contains(event.target)) {
    return;
  }

  secondaryNav.classList.remove("active");
  document.body.removeEventListener("click", closeSecondaire);
}

window.addEventListener(
  "resize",
  _debounce(() => {
    const secondaryNav = document.getElementById("secondaryNav");
    if (!secondaryNav) {
      return;
    }

    if (window.innerWidth <= NAV_COMPACT_BREAKPOINT) {
      secondaryNav.classList.remove("active");
      document.body.removeEventListener("click", closeSecondaire);
    }
  }, 200),
);

document.addEventListener("keydown", (event) => {
  if (event.key !== "Escape") {
    return;
  }

  // Let modal/overlay handlers take priority
  if (
    document.getElementById("themedConfirmModal") ||
    document.getElementById("modalContainer") ||
    document.getElementById("sortModal")
  ) {
    return;
  }

  const secondaryNav = document.getElementById("secondaryNav");
  if (!secondaryNav || !secondaryNav.classList.contains("active")) {
    return;
  }

  event.stopImmediatePropagation();
  secondaryNav.classList.remove("active");
  document.body.removeEventListener("click", closeSecondaire);
});

function resolveSystemTheme() {
  try {
    return window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light";
  } catch (e) {
    return "light";
  }
}

function setThemeCookie(preference) {
  document.cookie =
    "theme=" +
    encodeURIComponent(preference) +
    ";path=/;max-age=31536000;SameSite=Lax";
}

function syncThemeToggleControl(preference, resolvedTheme = "") {
  const themeSwitch = document.getElementById("uiThemeSwitch");
  if (!themeSwitch) {
    return;
  }

  const normalized = ["light", "dark", "system"].includes(preference)
    ? preference
    : "light";
  const resolved =
    resolvedTheme ||
    (normalized === "system" ? resolveSystemTheme() : normalized);

  themeSwitch.setAttribute(
    "aria-checked",
    resolved === "dark" ? "true" : "false",
  );
  themeSwitch.dataset.themePreference = normalized;
}

function initializeThemeToggleControl() {
  const themeSwitch = document.getElementById("uiThemeSwitch");
  const themeItem = themeSwitch?.closest(".navThemeItem") || null;
  if (!themeSwitch) {
    return;
  }

  // Click is handled via inline onclick on the button/row in PHP.
  // Only sync aria-checked state here via mousedown to prevent stale UI.
  ["mousedown", "touchstart", "pointerdown"].forEach((evtName) => {
    themeSwitch.addEventListener(evtName, (event) => {
      event.stopPropagation();
    });

    themeItem?.addEventListener(evtName, (event) => {
      event.stopPropagation();
    });
  });
}

function applyTheme(preference) {
  const modeLink = document.getElementById("darkMode");
  const logoLink = document.getElementById("logoLink");
  const faviconLink = document.getElementById("themeFavicon");
  const darkSwitchLabel = document.getElementById("darkSwitch");
  const themeInput = document.getElementById("themePreference");
  const preloadLink = document.getElementById("themePreload");

  const normalized = ["light", "dark", "system"].includes(preference)
    ? preference
    : "light";
  const resolved = normalized === "system" ? resolveSystemTheme() : normalized;

  if (modeLink) {
    const href =
      resolved === "dark"
        ? modeLink.dataset.darkHref || "/custom/css/custom-dark.css"
        : modeLink.dataset.lightHref || "/custom/css/custom-light.css";

    if (modeLink.getAttribute("href")?.split("?")[0] !== href.split("?")[0]) {
      modeLink.href = href;
    }
  }

  if (preloadLink && modeLink) {
    const oppositeHref =
      resolved === "dark"
        ? modeLink.dataset.lightHref || "/custom/css/custom-light.css"
        : modeLink.dataset.darkHref || "/custom/css/custom-dark.css";
    preloadLink.href = oppositeHref;
  }

  if (logoLink) {
    const nextLogo =
      resolved === "dark"
        ? logoLink.dataset.darkLogoSrc || "/custom/images/logo/logo-dark.svg"
        : logoLink.dataset.lightLogoSrc || "/custom/images/logo/logo-light.svg";
    logoLink.src = nextLogo;
  }

  if (faviconLink) {
    const nextIcon =
      resolved === "dark"
        ? faviconLink.dataset.darkIconHref || "/custom/images/icons/favicon.svg"
        : faviconLink.dataset.lightIconHref ||
          "/custom/images/icons/favicon.svg";
    faviconLink.href = nextIcon;
  }

  if (themeInput) {
    themeInput.value = normalized;
  }

  if (darkSwitchLabel) {
    darkSwitchLabel.textContent =
      normalized === "system"
        ? navText("js.navigation.system_theme", "System theme")
        : resolved === "dark"
          ? navText("js.navigation.light_mode", "Light mode")
          : navText("js.navigation.dark_mode", "Dark mode");
  }

  document.body.dataset.themePreference = normalized;
  document.documentElement.style.colorScheme = resolved;

  try {
    localStorage.setItem("themePreference", normalized);
    localStorage.setItem("resolvedTheme", resolved);
  } catch (e) {}

  setThemeCookie(normalized);
  syncThemeToggleControl(normalized, resolved);
}

async function switchDark() {
  if (window.__themeSwitchPending) return;
  window.__themeSwitchPending = true;

  const current = document.body.dataset.themePreference || "light";
  const resolved = current === "system" ? resolveSystemTheme() : current;
  const next = resolved === "dark" ? "light" : "dark";

  document.body.classList.add("theme-switching");
  applyTheme(next);

  setTimeout(() => {
    document.body.classList.remove("theme-switching");
    window.__themeSwitchPending = false;
  }, 400);

  try {
    const response = await fetch("/darkMode", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ mode: next }),
    });

    if (!response.ok && typeof createSnackbar === "function") {
      createSnackbar(
        navText(
          "js.navigation.theme_preference_could_not_be_saved",
          "Theme preference could not be saved",
        ),
        "error",
      );
    }
  } catch (e) {
    if (typeof createSnackbar === "function") {
      createSnackbar(
        navText(
          "js.navigation.theme_preference_could_not_be_saved",
          "Theme preference could not be saved",
        ),
        "error",
      );
    }
  }
}

document.addEventListener("DOMContentLoaded", () => {
  initializeThemeToggleControl();

  let preference = "";
  try {
    preference = localStorage.getItem("themePreference") || "";
  } catch (e) {}

  if (!["light", "dark", "system"].includes(preference)) {
    preference =
      document.getElementById("themePreference")?.value ||
      document.body.dataset.themePreference ||
      "light";
  }

  applyTheme(preference);

  try {
    window
      .matchMedia("(prefers-color-scheme: dark)")
      .addEventListener("change", () => {
        if (document.body.dataset.themePreference === "system") {
          applyTheme("system");
        }
      });
  } catch (e) {}
});

function switchList() {
  const linkHeaders = document.querySelectorAll(".linkHeader");
  const visitorHeaders = document.querySelectorAll(".visitorHeader");

  if (linkHeaders.length > 0) {
    linkHeaders.forEach((link) => {
      link.classList.toggle("viewMode");
      const container = link.closest(".linkContainer");
      if (container) container.classList.toggle("view-mode");
    });
  } else if (visitorHeaders.length > 0) {
    visitorHeaders.forEach((visitor) => {
      visitor.classList.toggle("visitorViewMode");
      const container = visitor.closest(".visitorContainer");
      if (container) container.classList.toggle("view-mode");
    });
  }

  syncListModeControls();

  fetch("/viewMode", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
  });
}

function notifyProfile(message, isError = false) {
  if (typeof createSnackbar === "function") {
    createSnackbar(message, isError);
    return;
  }

  if (isError) {
    console.error(message);
  } else {
    console.log(message);
  }
}

function navText(path, fallback = "") {
  if (
    window.i18n &&
    typeof window.i18n.t === "function" &&
    typeof path === "string"
  ) {
    return window.i18n.t(path, fallback);
  }

  const source = window.__I18N_TEXTS || window.__UI_TEXT__;
  if (!source || typeof path !== "string" || path.length === 0) {
    return fallback;
  }

  const readPath = (target, lookupPath) =>
    lookupPath.split(".").reduce((cursor, segment) => {
      if (!cursor || typeof cursor !== "object") {
        return undefined;
      }
      return cursor[segment];
    }, target);

  const value = readPath(source, path);
  if (typeof value === "string") {
    return value;
  }

  if (path.startsWith("js.") && path.length > 3) {
    const legacyValue = readPath(source, path.slice(3));
    if (typeof legacyValue === "string") {
      return legacyValue;
    }
  }

  return fallback;
}

function navFormat(template, values = {}) {
  return String(template || "").replace(
    /\{([a-zA-Z0-9_]+)\}/g,
    (match, key) => {
      if (!Object.prototype.hasOwnProperty.call(values, key)) {
        return match;
      }
      return String(values[key]);
    },
  );
}

function formatSessionDate(unixSeconds) {
  const value = Number(unixSeconds);
  if (!Number.isFinite(value) || value <= 0) {
    return navText("js.navigation.unknown", "Unknown");
  }

  const date = new Date(value * 1000);
  const locale =
    (window.i18n && typeof window.i18n.getLang === "function"
      ? window.i18n.getLang()
      : document.documentElement.lang) || "en";

  return date.toLocaleString(locale, {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function detectSessionDevice(userAgent) {
  const ua = String(userAgent || "").toLowerCase();
  if (!ua) {
    return navText("js.navigation.unknown_device", "Unknown device");
  }
  if (
    ua.includes("iphone") ||
    ua.includes("android") ||
    ua.includes("mobile")
  ) {
    return navText("js.navigation.mobile", "Mobile");
  }
  if (ua.includes("ipad") || ua.includes("tablet")) {
    return navText("js.navigation.tablet", "Tablet");
  }
  if (
    ua.includes("windows") ||
    ua.includes("macintosh") ||
    ua.includes("linux")
  ) {
    return navText("js.navigation.desktop", "Desktop");
  }
  return navText("js.navigation.unknown_device", "Unknown device");
}

async function loadDeviceHistory() {
  const listElement = document.getElementById("deviceHistoryList");
  const summaryElement = document.getElementById("deviceHistorySummary");

  if (!listElement || !summaryElement) {
    return;
  }

  summaryElement.textContent = navText(
    "js.navigation.loading_device_sessions",
    "Loading device sessions...",
  );
  listElement.innerHTML = "";

  try {
    const response = await fetch("/deviceSessions", {
      method: "GET",
      headers: { "Content-Type": "application/json" },
    });
    const payload = await response.json();

    if (!response.ok || payload.success !== true) {
      summaryElement.textContent = navText(
        "js.navigation.could_not_load_device_history",
        "Could not load device history",
      );
      return;
    }

    const sessions = Array.isArray(payload.sessions) ? payload.sessions : [];
    const activeSessions = sessions.filter((session) => !session.revoked);
    const currentSessions = activeSessions.filter(
      (session) => session.is_current,
    );

    summaryElement.textContent = navFormat(
      navText(
        "js.navigation.active_devices_summary",
        "{active} active device(s), {current} current",
      ),
      { active: activeSessions.length, current: currentSessions.length },
    );

    if (sessions.length === 0) {
      listElement.innerHTML = `<p class="profileSectionHint">${navText("js.navigation.no_device_sessions", "No device sessions found yet.")}</p>`;
      return;
    }

    const limitedSessions = sessions.slice(0, 8);
    listElement.innerHTML = limitedSessions
      .map((session) => {
        const deviceType = detectSessionDevice(session.user_agent);
        const currentBadge = session.is_current
          ? `<span class="deviceHistoryBadge">${navText("js.navigation.current", "Current")}</span>`
          : "";
        const revokedSuffix = session.revoked
          ? ` (${navText("js.navigation.revoked", "revoked")})`
          : "";
        return `
          <div class="deviceHistoryItem">
            <div class="deviceHistoryTop">
              <p class="deviceHistoryTitle">${deviceType}${revokedSuffix}</p>
              ${currentBadge}
            </div>
            <p class="deviceHistoryMeta">${navText("js.navigation.login", "Login")}: ${formatSessionDate(session.created_at)}</p>
            <p class="deviceHistoryMeta">${navText("js.navigation.last_seen", "Last seen")}: ${formatSessionDate(session.last_seen_at)}</p>
            <p class="deviceHistoryMeta">${navText("js.navigation.ip", "IP")}: ${String(session.ip || navText("js.navigation.unknown", "Unknown"))}</p>
          </div>
        `;
      })
      .join("");
  } catch (error) {
    summaryElement.textContent = navText(
      "js.navigation.could_not_load_device_history",
      "Could not load device history",
    );
  }
}

async function logoutOtherDevices() {
  try {
    const response = await fetch("/revokeOtherDeviceSessions", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
    });

    const payload = await response.json();
    if (!response.ok || payload.success !== true) {
      notifyProfile(
        payload.message ||
          navText(
            "js.navigation.could_not_logout_other_devices",
            "Could not logout other devices",
          ),
        true,
      );
      return;
    }

    notifyProfile(
      payload.message ||
        navText(
          "js.navigation.other_devices_logged_out",
          "Other devices logged out",
        ),
    );
    loadDeviceHistory();
  } catch (error) {
    notifyProfile(
      navText(
        "js.navigation.could_not_logout_other_devices",
        "Could not logout other devices",
      ),
      true,
    );
  }
}

function isCompactViewEnabled() {
  const firstLinkHeader = document.querySelector(".linkHeader");
  if (firstLinkHeader) {
    return firstLinkHeader.classList.contains("viewMode");
  }

  const firstVisitorHeader = document.querySelector(".visitorHeader");
  if (firstVisitorHeader) {
    return firstVisitorHeader.classList.contains("visitorViewMode");
  }

  return null;
}

function syncListModeControls() {
  const listSwitch = document.getElementById("listSwitch");
  const profileCompactView = document.getElementById("profileCompactView");
  const compactEnabled = isCompactViewEnabled();

  if (listSwitch) {
    listSwitch.textContent = compactEnabled
      ? navText("js.navigation.view_mode", "View mode")
      : navText("js.navigation.list_mode", "List mode");
  }

  if (profileCompactView && compactEnabled !== null) {
    profileCompactView.checked = compactEnabled;
  }
}

document.addEventListener("i18n:changed", () => {
  const currentPreference =
    document.body.dataset.themePreference ||
    document.getElementById("themePreference")?.value ||
    "light";
  applyTheme(currentPreference);
  syncListModeControls();
  loadDeviceHistory();
});

async function saveProfilePreferences() {
  const compactViewInput = document.getElementById("profileCompactView");
  const limitInput = document.getElementById("profileItemsPerPage");

  if (!compactViewInput || !limitInput) {
    return;
  }

  const payload = {
    compactView: compactViewInput.checked,
    limit: Number(limitInput.value),
  };

  try {
    const response = await fetch("/profilePreferences", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
      notifyProfile(
        data.message ||
          navText(
            "js.navigation.failed_save_profile_preferences",
            "Failed to save profile preferences",
          ),
        true,
      );
      return;
    }

    const compactEnabled = payload.compactView;
    document.querySelectorAll(".linkHeader").forEach((header) => {
      header.classList.toggle("viewMode", compactEnabled);
      const container = header.closest(".linkContainer");
      if (container) container.classList.toggle("view-mode", compactEnabled);
    });

    document.querySelectorAll(".visitorHeader").forEach((header) => {
      header.classList.toggle("visitorViewMode", compactEnabled);
      const container = header.closest(".visitorContainer");
      if (container) container.classList.toggle("view-mode", compactEnabled);
    });
    syncListModeControls();

    const shownSelect = document.getElementById("shownSelect");
    if (shownSelect && shownSelect.value !== String(payload.limit)) {
      shownSelect.value = String(payload.limit);
      shownSelect.dispatchEvent(new Event("change", { bubbles: true }));
    }

    notifyProfile(
      navText(
        "js.navigation.profile_preferences_saved",
        "Profile preferences saved",
      ),
    );
  } catch (error) {
    notifyProfile(
      navText(
        "js.navigation.could_not_save_profile_preferences",
        "Could not save profile preferences",
      ),
      true,
    );
  }
}

async function saveProfilePassword() {
  const currentPassword = document.getElementById("profileCurrentPassword");
  const newPassword = document.getElementById("profileNewPassword");
  const confirmPassword = document.getElementById("profileConfirmPassword");

  if (!currentPassword || !newPassword || !confirmPassword) {
    return;
  }

  if (!newPassword.value || !confirmPassword.value || !currentPassword.value) {
    notifyProfile(
      navText(
        "js.navigation.fill_all_password_fields",
        "Please fill in all password fields",
      ),
      true,
    );
    return;
  }

  if (newPassword.value !== confirmPassword.value) {
    notifyProfile(
      navText(
        "js.navigation.new_passwords_no_match",
        "New passwords do not match",
      ),
      true,
    );
    return;
  }

  if (newPassword.value.length < 8) {
    notifyProfile(
      navText(
        "js.navigation.new_password_min_8",
        "New password must be at least 8 characters",
      ),
      true,
    );
    return;
  }

  try {
    const response = await fetch("/changeProfilePassword", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        currentPassword: currentPassword.value,
        newPassword: newPassword.value,
        confirmPassword: confirmPassword.value,
      }),
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
      notifyProfile(
        data.message ||
          navText(
            "js.navigation.could_not_update_password",
            "Could not update password",
          ),
        true,
      );
      return;
    }

    currentPassword.value = "";
    newPassword.value = "";
    confirmPassword.value = "";
    notifyProfile(
      navText(
        "js.navigation.password_updated_success",
        "Password updated successfully",
      ),
    );
  } catch (error) {
    notifyProfile(
      navText(
        "js.navigation.could_not_update_password",
        "Could not update password",
      ),
      true,
    );
  }
}

document.addEventListener("DOMContentLoaded", () => {
  syncListModeControls();

  const savePreferencesButton = document.getElementById(
    "saveProfilePreferences",
  );
  const savePasswordButton = document.getElementById("saveProfilePassword");
  const refreshDeviceHistoryButton = document.getElementById(
    "refreshDeviceHistory",
  );
  const logoutOtherDevicesButton = document.getElementById(
    "logoutOtherDevicesButton",
  );

  savePreferencesButton?.addEventListener("click", saveProfilePreferences);
  savePasswordButton?.addEventListener("click", saveProfilePassword);
  refreshDeviceHistoryButton?.addEventListener("click", loadDeviceHistory);
  logoutOtherDevicesButton?.addEventListener("click", logoutOtherDevices);

  loadDeviceHistory();
});
