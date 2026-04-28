document.addEventListener("DOMContentLoaded", function () {
  const usersText = function (path, fallback) {
    if (typeof getUiText === "function") {
      return getUiText(path, fallback);
    }
    return fallback;
  };

  const usersFormat = function (template, values) {
    if (typeof formatUiText === "function") {
      return formatUiText(template, values);
    }
    return String(template || "");
  };

  const formatRelativeTime = function (timestampSeconds) {
    const unix = Number.parseInt(timestampSeconds, 10);
    if (!Number.isFinite(unix) || unix <= 0) {
      return usersText("js.profile.unknown", "Unknown");
    }

    const delta = Math.max(0, Math.floor(Date.now() / 1000) - unix);
    if (delta < 60) {
      return usersText("js.profile.just_now", "Just now");
    }
    if (delta < 3600) {
      return usersFormat(
        usersText("js.profile.minutes_ago", "{count} min ago"),
        {
          count: Math.floor(delta / 60),
        },
      );
    }
    if (delta < 86400) {
      const hours = Math.floor(delta / 3600);
      return usersFormat(
        usersText(
          hours === 1
            ? "js.profile.hours_ago_singular"
            : "js.profile.hours_ago_plural",
          hours === 1 ? "{count} hour ago" : "{count} hours ago",
        ),
        {
          count: hours,
        },
      );
    }
    const days = Math.floor(delta / 86400);
    return usersFormat(
      usersText(
        days === 1
          ? "js.profile.days_ago_singular"
          : "js.profile.days_ago_plural",
        days === 1 ? "{count} day ago" : "{count} days ago",
      ),
      {
        count: days,
      },
    );
  };

  const formatExactDateTime = function (timestampSeconds) {
    const unix = Number.parseInt(timestampSeconds, 10);
    if (!Number.isFinite(unix) || unix <= 0) {
      return usersText("js.profile.unknown", "Unknown");
    }

    const date = new Date(unix * 1000);
    return new Intl.DateTimeFormat(undefined, {
      year: "numeric",
      month: "short",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    }).format(date);
  };

  const parseUserAgentLabel = function (userAgent) {
    const ua = String(userAgent || "").toLowerCase();

    let browser = usersText("js.profile.unknown_browser", "Unknown browser");
    if (ua.includes("edg/")) browser = "Edge";
    else if (ua.includes("opr/") || ua.includes("opera")) browser = "Opera";
    else if (ua.includes("chrome/") && !ua.includes("edg/")) browser = "Chrome";
    else if (ua.includes("safari/") && !ua.includes("chrome/"))
      browser = "Safari";
    else if (ua.includes("firefox/")) browser = "Firefox";

    let os = usersText("js.profile.unknown_os", "Unknown OS");
    if (ua.includes("windows")) os = "Windows";
    else if (ua.includes("android")) os = "Android";
    else if (ua.includes("iphone") || ua.includes("ipad") || ua.includes("ios"))
      os = "iOS";
    else if (ua.includes("mac os") || ua.includes("macintosh")) os = "macOS";
    else if (ua.includes("linux")) os = "Linux";

    return usersFormat(
      usersText("js.profile.browser_on_os", "{browser} on {os}"),
      {
        browser,
        os,
      },
    );
  };

  const setUserPanelLoading = function (panel, isLoading) {
    if (!panel) {
      return;
    }
    const refreshBtn = panel.querySelector("[data-load-user-sessions]");
    if (refreshBtn) {
      refreshBtn.disabled = isLoading;
    }
  };

  const renderUserSessions = function (panel, sessions) {
    if (!panel) {
      return;
    }

    const listEl = panel.querySelector("[data-user-session-list]");
    const countEl = panel.querySelector("[data-user-session-count]");
    const lastSeenEl = panel.querySelector("[data-user-last-seen]");
    if (!listEl) {
      return;
    }

    const activeSessions = (Array.isArray(sessions) ? sessions : []).filter(
      (session) => !session.revoked,
    );

    if (countEl) {
      countEl.textContent = String(activeSessions.length);
    }

    const latestSeen = activeSessions.reduce((maxValue, session) => {
      const ts = Number.parseInt(session.last_seen_at || "0", 10);
      return Number.isFinite(ts) && ts > maxValue ? ts : maxValue;
    }, 0);
    if (lastSeenEl) {
      lastSeenEl.textContent =
        latestSeen > 0
          ? formatRelativeTime(latestSeen)
          : usersText("js.users.never", "Never");
    }

    if (activeSessions.length === 0) {
      listEl.innerHTML = `<div class="userDeviceEmpty">${usersText("js.profile.no_active_sessions", "No active sessions found.")}</div>`;
      panel.dataset.sessionsLoaded = "true";
      return;
    }

    const html = activeSessions
      .map((session) => {
        const sessionId = String(session.session_id || "");
        const uaLabel = parseUserAgentLabel(session.user_agent);
        const ipText = String(
          session.ip || usersText("js.profile.unknown_ip", "Unknown IP"),
        );
        const loginAt = formatExactDateTime(session.created_at);
        const currentBadge = session.is_current
          ? `<span class="userDeviceStatusIndicator userDeviceStatusCurrent">${usersText("js.users.current_admin_session", "Current admin session")}</span>`
          : "";

        return `
          <div class="userDeviceSessionItem" data-session-id="${sessionId}">
            <div class="userDeviceSessionItemHead">
              <div class="userDeviceSessionTitle">${uaLabel}</div>
              ${currentBadge}
            </div>
            <div class="userDeviceSessionMeta">
              <span><i class="material-icons">language</i>${ipText}</span>
              <span><i class="material-icons">schedule</i>${usersFormat(usersText("js.profile.seen", "Seen {value}"), { value: formatRelativeTime(session.last_seen_at) })}</span>
              <span><i class="material-icons">event</i>${usersFormat(usersText("js.profile.created", "Created {value}"), { value: loginAt })}</span>
            </div>
            <span class="tooltip userDeviceRevokeTooltip">
              <button type="button" class="userDeviceRevokeButton" data-revoke-user-session="${sessionId}">${usersText("js.profile.logout_device", "Sign out device")}</button>
              <span class="tooltiptext">${usersText("js.users.tooltip_sign_out_device", "Sign out this device session")}</span>
            </span>
          </div>
        `;
      })
      .join("");

    listEl.innerHTML = html;
    panel.dataset.sessionsLoaded = "true";
  };

  const loadSessionsForUserPanel = async function (panel, userId) {
    if (!panel || !Number.isFinite(userId) || userId <= 0) {
      return;
    }

    setUserPanelLoading(panel, true);
    try {
      const response = await fetch(
        `/deviceSessions?user_id=${encodeURIComponent(String(userId))}`,
      );
      const payload = await response.json().catch(() => ({}));

      if (!response.ok || payload.success !== true) {
        throw new Error(
          payload.message ||
            usersText(
              "js.profile.could_not_load_sessions",
              "Could not load sessions.",
            ),
        );
      }

      renderUserSessions(panel, payload.sessions);
    } catch (error) {
      const listEl = panel.querySelector("[data-user-session-list]");
      if (listEl) {
        listEl.innerHTML = `<div class="userDeviceEmpty">${usersText("js.profile.could_not_load_sessions", "Could not load sessions.")}</div>`;
      }
    } finally {
      setUserPanelLoading(panel, false);
    }
  };

  function isInteractiveTarget(event) {
    return (
      event.target.tagName === "BUTTON" ||
      event.target.tagName === "A" ||
      event.target.tagName === "I" ||
      event.target.tagName === "INPUT" ||
      event.target.tagName === "SELECT" ||
      event.target.tagName === "TEXTAREA" ||
      event.target.classList.contains("tagContainer") ||
      event.target.parentElement?.classList.contains("tagContainer") ||
      event.target.closest(".popup-modal")
    );
  }

  function getSelectionModeState() {
    if (typeof window.isSelectionModeActive === "function") {
      return window.isSelectionModeActive();
    }
    return false;
  }

  function addEventListeners() {
    let lastDisabledSelectionNoticeAt = 0;

    function notifyDisabledSelectionAttempt() {
      if (typeof createSnackbar !== "function") {
        return;
      }

      const now = Date.now();
      if (now - lastDisabledSelectionNoticeAt < 1100) {
        return;
      }

      lastDisabledSelectionNoticeAt = now;
      createSnackbar(
        usersText(
          "js.users.cannot_select_own_account",
          "You cannot select your own account",
        ),
      );
    }

    // Use event delegation for .linkSwitch elements
    document.body.addEventListener("click", function (event) {
      const refreshBtn = event.target.closest("[data-load-user-sessions]");
      if (refreshBtn) {
        const userId = Number.parseInt(
          refreshBtn.getAttribute("data-load-user-sessions") || "0",
          10,
        );
        const panel = refreshBtn.closest(".userDevicePanel");
        loadSessionsForUserPanel(panel, userId);
        return;
      }

      const revokeBtn = event.target.closest("[data-revoke-user-session]");
      if (revokeBtn) {
        const panel = revokeBtn.closest(".userDevicePanel");
        const userId = Number.parseInt(
          panel?.getAttribute("data-user-id") || "0",
          10,
        );
        const sessionId = revokeBtn.getAttribute("data-revoke-user-session");
        if (!panel || !Number.isFinite(userId) || userId <= 0 || !sessionId) {
          return;
        }

        revokeBtn.disabled = true;
        fetch("/revokeDeviceSession", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            user_id: userId,
            session_id: sessionId,
          }),
        })
          .then((response) =>
            response
              .json()
              .catch(() => ({}))
              .then((payload) => ({ response, payload })),
          )
          .then(({ response, payload }) => {
            if (!response.ok || payload.success !== true) {
              throw new Error(
                payload.message ||
                  usersText(
                    "js.profile.could_not_revoke_session",
                    "Could not revoke session",
                  ),
              );
            }

            if (typeof createSnackbar === "function") {
              createSnackbar(
                payload.message ||
                  usersText(
                    "js.profile.device_session_revoked",
                    "Device session revoked",
                  ),
              );
            }
            return loadSessionsForUserPanel(panel, userId);
          })
          .catch(() => {
            if (typeof createSnackbar === "function") {
              createSnackbar(
                usersText(
                  "js.users.could_not_revoke_device_session",
                  "Could not revoke device session",
                ),
                "error",
              );
            }
          })
          .finally(() => {
            revokeBtn.disabled = false;
          });
        return;
      }

      if (event.target.closest(".userSwitch")) {
        let openSwitch = event.target.closest(".userSwitch");
        const userCheckbox = openSwitch.querySelector(".checkbox");

        const checkbox = event.target.closest(".checkbox");
        if (checkbox && openSwitch.contains(checkbox)) {
          if (openSwitch.classList.contains("open")) {
            openSwitch.classList.remove("open");
          }
          return;
        }

        const checkboxVisualHit = event.target.closest(
          ".userForm, .checkBoxBackground, .checkIcon",
        );
        if (
          checkboxVisualHit &&
          userCheckbox &&
          openSwitch.contains(checkboxVisualHit)
        ) {
          if (userCheckbox.disabled) {
            notifyDisabledSelectionAttempt();
            return;
          }

          if (openSwitch.classList.contains("open")) {
            openSwitch.classList.remove("open");
          }

          // Native input clicks already trigger inline multiSelect handler.
          if (event.target !== userCheckbox) {
            event.preventDefault();
            if (typeof window.multiSelect === "function") {
              window.multiSelect(userCheckbox.id, event);
            } else {
              userCheckbox.click();
            }
          }
          return;
        }

        if (getSelectionModeState()) {
          if (isInteractiveTarget(event)) {
            return;
          }

          if (userCheckbox?.disabled) {
            notifyDisabledSelectionAttempt();
            return;
          }

          if (userCheckbox && typeof window.multiSelect === "function") {
            event.preventDefault();
            window.multiSelect(userCheckbox.id, event);
          }
          return;
        }

        if (isInteractiveTarget(event) || event.target.closest(".userForm")) {
          return;
        }
        openSwitch.classList.toggle("open");

        if (openSwitch.classList.contains("open")) {
          const panel = openSwitch.querySelector(".userDevicePanel");
          const userId = Number.parseInt(
            panel?.getAttribute("data-user-id") || "0",
            10,
          );
          if (panel && panel.dataset.sessionsLoaded !== "true") {
            loadSessionsForUserPanel(panel, userId);
          }
        }
      }
    });

    // Long-press on touch: enter multi-select on users cards like links page.
    (function () {
      const HOLD_DELAY = 500;
      const MOVE_THRESHOLD = 10;
      const SUPPRESS_POST_HOLD_CLICK_MS = 320;

      let activeContainer = null;
      let holdTimer = null;
      let holdFired = false;
      let startTouchX = 0;
      let startTouchY = 0;
      let suppressClickUntil = 0;
      let suppressClickContainer = null;

      function fireHold(container, event) {
        holdFired = true;
        container.classList.remove("link-pressing");
        const checkbox = container.querySelector(".checkbox");
        if (!checkbox || checkbox.disabled) {
          return;
        }

        if (navigator.vibrate) {
          navigator.vibrate(50);
        }

        if (typeof window.multiSelect === "function") {
          window.multiSelect(checkbox.id, event);
        } else {
          checkbox.click();
        }
      }

      function cancelHold() {
        clearTimeout(holdTimer);
        holdTimer = null;
        if (activeContainer) {
          activeContainer.classList.remove("link-pressing");
          activeContainer = null;
        }
      }

      document.body.addEventListener(
        "click",
        function (event) {
          if (Date.now() > suppressClickUntil) {
            return;
          }

          if (
            suppressClickContainer &&
            suppressClickContainer.contains(event.target)
          ) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
          }
        },
        true,
      );

      document.body.addEventListener(
        "touchstart",
        function (event) {
          const container = event.target.closest(".userContainer");
          if (!container) {
            return;
          }

          if (getSelectionModeState()) {
            return;
          }

          if (
            event.target.closest(
              "a, button, input, select, textarea, .userForm, .popup-modal",
            )
          ) {
            return;
          }

          activeContainer = container;
          holdFired = false;
          const touch = event.touches[0];
          startTouchX = touch.clientX;
          startTouchY = touch.clientY;
          container.classList.add("link-pressing");
          holdTimer = setTimeout(() => fireHold(container, event), HOLD_DELAY);
        },
        { passive: true },
      );

      document.body.addEventListener(
        "touchmove",
        function (event) {
          if (!activeContainer) {
            return;
          }

          const touch = event.touches[0];
          const dx = Math.abs(touch.clientX - startTouchX);
          const dy = Math.abs(touch.clientY - startTouchY);
          if (dx > MOVE_THRESHOLD || dy > MOVE_THRESHOLD) {
            cancelHold();
          }
        },
        { passive: true },
      );

      function finishTouchInteraction() {
        if (holdFired && activeContainer) {
          suppressClickUntil = Date.now() + SUPPRESS_POST_HOLD_CLICK_MS;
          suppressClickContainer = activeContainer;
        }

        cancelHold();
        holdFired = false;
      }

      document.body.addEventListener(
        "touchend",
        function (event) {
          const fired = holdFired;
          finishTouchInteraction();
          if (fired) {
            event.preventDefault();
          }
        },
        { passive: false },
      );
      document.body.addEventListener("touchcancel", finishTouchInteraction, {
        passive: true,
      });

      // Desktop hold-to-select parity with links page.
      document.body.addEventListener("mousedown", function (event) {
        const container = event.target.closest(".userContainer");
        if (!container) {
          return;
        }

        if (getSelectionModeState()) {
          return;
        }

        if (
          event.target.closest(
            "a, button, input, select, textarea, .userForm, .popup-modal",
          )
        ) {
          return;
        }

        if (
          event.shiftKey ||
          document.body.classList.contains("shift-dragging")
        ) {
          return;
        }

        activeContainer = container;
        holdFired = false;
        startTouchX = event.clientX;
        startTouchY = event.clientY;
        container.classList.add("link-pressing");
        holdTimer = setTimeout(() => fireHold(container, event), HOLD_DELAY);
      });

      document.body.addEventListener("mousemove", function (event) {
        if (!activeContainer) {
          return;
        }

        const dx = Math.abs(event.clientX - startTouchX);
        const dy = Math.abs(event.clientY - startTouchY);
        if (dx > MOVE_THRESHOLD || dy > MOVE_THRESHOLD) {
          cancelHold();
        }
      });

      document.body.addEventListener("mouseup", function () {
        if (holdFired && activeContainer) {
          suppressClickContainer = activeContainer;
          suppressClickUntil = Date.now() + SUPPRESS_POST_HOLD_CLICK_MS;
          window.setTimeout(() => {
            if (Date.now() >= suppressClickUntil) {
              suppressClickUntil = 0;
              suppressClickContainer = null;
            }
          }, SUPPRESS_POST_HOLD_CLICK_MS + 20);
        }
        cancelHold();
        holdFired = false;
      });

      document.body.addEventListener("mouseleave", function () {
        cancelHold();
        holdFired = false;
      });
    })();

    document.body.addEventListener("keydown", function (event) {
      if (event.key !== "Enter" && event.key !== " ") return;
      const titleContainer = event.target.closest(".titleContainer");
      if (!titleContainer) return;
      const userSwitch = titleContainer.closest(".userSwitch");
      if (!userSwitch) return;

      if (getSelectionModeState()) {
        return;
      }

      event.preventDefault();
      userSwitch.classList.toggle("open");
      const isNowOpen = userSwitch.classList.contains("open");
      titleContainer.setAttribute("aria-expanded", String(isNowOpen));
      if (isNowOpen) {
        const panel = userSwitch.querySelector(".userDevicePanel");
        const userId = Number.parseInt(
          panel?.getAttribute("data-user-id") || "0",
          10,
        );
        if (panel && panel.dataset.sessionsLoaded !== "true") {
          loadSessionsForUserPanel(panel, userId);
        }
      }
    });
  }
  addEventListeners();
});
