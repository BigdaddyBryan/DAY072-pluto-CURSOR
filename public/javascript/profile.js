document.addEventListener("DOMContentLoaded", function () {
  const profileText = function (path, fallback) {
    if (typeof getUiText === "function") {
      return getUiText(path, fallback);
    }
    return fallback;
  };

  const profileFormat = function (template, values) {
    if (typeof formatUiText === "function") {
      return formatUiText(template, values);
    }
    return String(template || "");
  };

  const photoInput = document.getElementById("profilePhotoInput");
  const preview = document.getElementById("profileAvatarPreview");
  const passwordForm = document.getElementById("profilePasswordForm");
  const visibilityButtons = document.querySelectorAll(".profileShowPass");
  const deviceListEl = document.getElementById("profileDeviceList");
  const refreshDeviceSessionsBtn = document.getElementById(
    "refreshDeviceSessions",
  );
  const revokeOtherSessionsBtn = document.getElementById(
    "revokeOtherSessionsBtn",
  );
  const deviceSessionCountEl = document.getElementById("deviceSessionCount");
  const deviceSessionLastSeenEl = document.getElementById(
    "deviceSessionLastSeen",
  );
  const identityForm = document.getElementById("profileIdentityForm");
  const photoForm = photoInput?.form || null;

  const trackedForms = [identityForm, passwordForm, photoForm].filter(
    (form, index, forms) =>
      form && forms.indexOf(form) === index && form instanceof HTMLFormElement,
  );

  const unsavedState = {
    hasChanges: false,
    allowNavigation: false,
    promptOpen: false,
    lastActiveForm: null,
  };

  const trackedFieldsByForm = new Map();
  const fieldBaselines = new WeakMap();

  const isTrackableField = function (field) {
    if (!(field instanceof HTMLElement)) {
      return false;
    }

    if (!field.matches("input, textarea, select") || field.disabled) {
      return false;
    }

    if (field.tagName === "INPUT") {
      const type = String(field.type || "").toLowerCase();
      if (
        type === "hidden" ||
        type === "submit" ||
        type === "button" ||
        type === "reset"
      ) {
        return false;
      }
    }

    return true;
  };

  const readFieldValue = function (field) {
    if (field.tagName === "INPUT") {
      const type = String(field.type || "").toLowerCase();

      if (type === "checkbox" || type === "radio") {
        return field.checked ? "1" : "0";
      }

      if (type === "file") {
        const files = field.files;
        if (!files || files.length === 0) {
          return "";
        }

        return Array.from(files)
          .map((file) => `${file.name}:${file.size}:${file.lastModified}`)
          .join("|");
      }
    }

    if (field.tagName === "SELECT" && field.multiple) {
      return Array.from(field.selectedOptions)
        .map((option) => option.value)
        .join("|");
    }

    return String(field.value ?? "");
  };

  const ensureTrackedField = function (form, field) {
    if (!isTrackableField(field)) {
      return;
    }

    const list = trackedFieldsByForm.get(form) || [];
    if (!list.includes(field)) {
      list.push(field);
      trackedFieldsByForm.set(form, list);
    }

    if (!fieldBaselines.has(field)) {
      fieldBaselines.set(field, readFieldValue(field));
    }
  };

  const collectInitialFormState = function (form) {
    const fields = Array.from(
      form.querySelectorAll("input, textarea, select"),
    ).filter(isTrackableField);

    trackedFieldsByForm.set(form, fields);
    fields.forEach((field) => {
      fieldBaselines.set(field, readFieldValue(field));
    });
  };

  const formHasChanges = function (form) {
    const fields = trackedFieldsByForm.get(form) || [];
    return fields.some((field) => {
      if (!field || !field.isConnected || !isTrackableField(field)) {
        return false;
      }

      return readFieldValue(field) !== fieldBaselines.get(field);
    });
  };

  const syncUnsavedState = function () {
    unsavedState.hasChanges = trackedForms.some((form) => formHasChanges(form));
  };

  const getSubmitControl = function (form) {
    return form.querySelector(
      'button[type="submit"]:not([disabled]), input[type="submit"]:not([disabled])',
    );
  };

  const getPreferredDirtyForm = function () {
    if (
      unsavedState.lastActiveForm &&
      trackedForms.includes(unsavedState.lastActiveForm) &&
      formHasChanges(unsavedState.lastActiveForm)
    ) {
      return unsavedState.lastActiveForm;
    }

    return trackedForms.find((form) => formHasChanges(form)) || null;
  };

  const submitProfileForm = function (form) {
    if (!(form instanceof HTMLFormElement)) {
      return false;
    }

    if (typeof form.reportValidity === "function" && !form.reportValidity()) {
      return false;
    }

    const submitter = getSubmitControl(form);

    try {
      if (typeof form.requestSubmit === "function") {
        if (submitter) {
          form.requestSubmit(submitter);
        } else {
          form.requestSubmit();
        }
        return true;
      }
    } catch {
      // requestSubmit can throw in some edge-cases.
    }

    if (submitter && typeof submitter.click === "function") {
      submitter.click();
      return true;
    }

    if (typeof form.checkValidity === "function" && !form.checkValidity()) {
      if (typeof form.reportValidity === "function") {
        form.reportValidity();
      }
      return false;
    }

    form.submit();
    return true;
  };

  const requestUnsavedAction = async function () {
    if (typeof window.showUnsavedChangesPrompt === "function") {
      return window.showUnsavedChangesPrompt({
        title: profileText(
          "js.common.unsaved_changes_title",
          "Save changes before closing?",
        ),
        message: profileText(
          "js.common.unsaved_changes_lost",
          "Any unsaved changes will be lost.",
        ),
        saveText: profileText("js.common.save", "Save"),
        discardText: profileText("js.common.dont_save", "Don't save"),
        cancelText: profileText("js.common.cancel", "Cancel"),
      });
    }

    const leaveConfirmed = window.confirm(
      profileText(
        "js.common.unsaved_changes_lost",
        "Any unsaved changes will be lost.",
      ),
    );

    return leaveConfirmed ? "discard" : "cancel";
  };

  if (trackedForms.length > 0) {
    trackedForms.forEach((form) => {
      collectInitialFormState(form);

      form.addEventListener("focusin", function (event) {
        const target = event.target;
        if (!(target instanceof Element)) {
          return;
        }

        const field = target.closest("input, textarea, select");
        if (!field || !form.contains(field)) {
          return;
        }

        unsavedState.lastActiveForm = form;
        ensureTrackedField(form, field);
      });

      const handleFieldMutation = function (event) {
        const target = event.target;
        if (!(target instanceof Element)) {
          return;
        }

        const field = target.closest("input, textarea, select");
        if (!field || !form.contains(field)) {
          return;
        }

        unsavedState.lastActiveForm = form;
        ensureTrackedField(form, field);
        syncUnsavedState();
      };

      form.addEventListener("input", handleFieldMutation);
      form.addEventListener("change", handleFieldMutation);

      form.addEventListener("reset", function () {
        requestAnimationFrame(() => {
          collectInitialFormState(form);
          syncUnsavedState();
        });
      });
    });

    syncUnsavedState();

    identityForm?.addEventListener("submit", function () {
      unsavedState.allowNavigation = true;
      unsavedState.hasChanges = false;
    });

    photoForm?.addEventListener("submit", function () {
      unsavedState.allowNavigation = true;
      unsavedState.hasChanges = false;
    });

    window.addEventListener("beforeunload", function (event) {
      if (!unsavedState.hasChanges || unsavedState.allowNavigation) {
        return;
      }

      event.preventDefault();
      event.returnValue = "";
    });

    document.addEventListener("click", async function (event) {
      if (
        !unsavedState.hasChanges ||
        unsavedState.allowNavigation ||
        unsavedState.promptOpen ||
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
      ) {
        return;
      }

      const target = event.target;
      if (!(target instanceof Element)) {
        return;
      }

      const anchor = target.closest("a[href]");
      if (!anchor) {
        return;
      }

      if (anchor.closest("#confirmationModal")) {
        return;
      }

      if (anchor.target && anchor.target.toLowerCase() !== "_self") {
        return;
      }

      if (anchor.hasAttribute("download")) {
        return;
      }

      const href = anchor.getAttribute("href");
      if (
        !href ||
        href.startsWith("#") ||
        href.startsWith("javascript:") ||
        href.startsWith("mailto:") ||
        href.startsWith("tel:")
      ) {
        return;
      }

      event.preventDefault();

      unsavedState.promptOpen = true;
      try {
        const action = await requestUnsavedAction();
        if (action === "discard") {
          unsavedState.allowNavigation = true;
          window.location.href = anchor.href;
          return;
        }

        if (action === "save") {
          const preferredForm = getPreferredDirtyForm();
          const submitted = submitProfileForm(preferredForm);
          if (!submitted && typeof createSnackbar === "function") {
            createSnackbar(
              profileText(
                "js.common.could_not_save_changes",
                "Could not save changes",
              ),
              "error",
            );
          }
        }
      } finally {
        unsavedState.promptOpen = false;
      }
    });
  }

  visibilityButtons.forEach(function (button) {
    button.addEventListener("click", function (event) {
      event.preventDefault();
      event.stopPropagation();

      const targetId = button.getAttribute("data-target");
      if (!targetId) {
        return;
      }

      const input = document.getElementById(targetId);
      const icon = button.querySelector("i");
      if (!input || !icon) {
        return;
      }

      const toText = input.type === "password";
      input.type = toText ? "text" : "password";
      icon.textContent = toText ? "visibility" : "visibility_off";
      input.focus();
    });
  });

  const formatRelativeTime = function (timestampSeconds) {
    const unix = Number.parseInt(timestampSeconds, 10);
    if (!Number.isFinite(unix) || unix <= 0) {
      return profileText("js.profile.unknown", "Unknown");
    }

    const delta = Math.max(0, Math.floor(Date.now() / 1000) - unix);
    if (delta < 60) {
      return profileText("js.profile.just_now", "Just now");
    }
    if (delta < 3600) {
      return profileFormat(
        profileText("js.profile.minutes_ago", "{count} min ago"),
        {
          count: Math.floor(delta / 60),
        },
      );
    }
    if (delta < 86400) {
      const hours = Math.floor(delta / 3600);
      return profileFormat(
        profileText(
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
    return profileFormat(
      profileText(
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
      return profileText("js.profile.unknown", "Unknown");
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

    let browser = profileText("js.profile.unknown_browser", "Unknown browser");
    if (ua.includes("edg/")) browser = "Edge";
    else if (ua.includes("opr/") || ua.includes("opera")) browser = "Opera";
    else if (ua.includes("chrome/") && !ua.includes("edg/")) browser = "Chrome";
    else if (ua.includes("safari/") && !ua.includes("chrome/"))
      browser = "Safari";
    else if (ua.includes("firefox/")) browser = "Firefox";

    let os = profileText("js.profile.unknown_os", "Unknown OS");
    if (ua.includes("windows")) os = "Windows";
    else if (ua.includes("android")) os = "Android";
    else if (ua.includes("iphone") || ua.includes("ipad") || ua.includes("ios"))
      os = "iOS";
    else if (ua.includes("mac os") || ua.includes("macintosh")) os = "macOS";
    else if (ua.includes("linux")) os = "Linux";

    return profileFormat(
      profileText("js.profile.browser_on_os", "{browser} on {os}"),
      {
        browser,
        os,
      },
    );
  };

  const setDeviceControlsLoading = function (isLoading) {
    if (refreshDeviceSessionsBtn) {
      refreshDeviceSessionsBtn.disabled = isLoading;
    }
    if (revokeOtherSessionsBtn) {
      revokeOtherSessionsBtn.disabled = isLoading;
    }
  };

  const renderDeviceSessions = function (sessions) {
    if (!deviceListEl) {
      return;
    }

    const list = Array.isArray(sessions) ? sessions : [];
    const activeSessions = list.filter(function (session) {
      return !session.revoked;
    });

    if (deviceSessionCountEl) {
      deviceSessionCountEl.textContent = String(activeSessions.length);
    }

    if (deviceSessionLastSeenEl) {
      const latestSeen = activeSessions.reduce(function (maxValue, session) {
        const ts = Number.parseInt(session.last_seen_at || "0", 10);
        return Number.isFinite(ts) && ts > maxValue ? ts : maxValue;
      }, 0);
      deviceSessionLastSeenEl.textContent =
        latestSeen > 0 ? formatRelativeTime(latestSeen) : "-";
    }

    if (activeSessions.length === 0) {
      deviceListEl.innerHTML = `<div class="profileDeviceListEmpty">${profileText("js.profile.no_active_sessions", "No active sessions found.")}</div>`;
      return;
    }

    const html = activeSessions
      .map(function (session) {
        const sessionId = String(session.session_id || "");
        const sessionLabel = parseUserAgentLabel(session.user_agent);
        const ipText = session.ip
          ? String(session.ip)
          : profileText("js.profile.unknown_ip", "Unknown IP");
        const createdAt = formatExactDateTime(session.created_at);
        const seenAt = formatRelativeTime(session.last_seen_at);
        const currentBadge = session.is_current
          ? `<span class="profileDeviceCurrent">${profileText("js.profile.this_device", "This device")}</span>`
          : "";

        return `
        <div class="profileDeviceItem" data-session-id="${sessionId}">
          <div class="profileDeviceItemHeader">
            <div class="profileDeviceTitle">${sessionLabel}</div>
            ${currentBadge}
          </div>
          <div class="profileDeviceMeta">
            <span><i class="material-icons">language</i>${ipText}</span>
            <span><i class="material-icons">schedule</i>${profileFormat(profileText("js.profile.seen", "Seen {value}"), { value: seenAt })}</span>
            <span><i class="material-icons">event</i>${profileFormat(profileText("js.profile.created", "Created {value}"), { value: createdAt })}</span>
          </div>
          ${session.is_current ? "" : `<button type="button" class="profileDeviceRevoke" data-revoke-session="${sessionId}">${profileText("js.profile.logout_device", "Log out device")}</button>`}
        </div>
      `;
      })
      .join("");

    deviceListEl.innerHTML = html;
  };

  const loadDeviceSessions = async function () {
    if (!deviceListEl) {
      return;
    }

    setDeviceControlsLoading(true);
    try {
      const response = await fetch("/deviceSessions");
      const payload = await response.json().catch(function () {
        return {};
      });

      if (!response.ok || payload.success !== true) {
        throw new Error(
          payload.message ||
            profileText(
              "js.profile.could_not_load_sessions",
              "Could not load sessions.",
            ),
        );
      }

      renderDeviceSessions(payload.sessions);
    } catch (error) {
      deviceListEl.innerHTML = `<div class="profileDeviceListEmpty">${profileText("js.profile.could_not_load_sessions", "Could not load sessions.")}</div>`;
    } finally {
      setDeviceControlsLoading(false);
    }
  };

  if (deviceListEl) {
    deviceListEl.addEventListener("click", async function (event) {
      const revokeBtn = event.target.closest("[data-revoke-session]");
      if (!revokeBtn) {
        return;
      }

      const sessionId = revokeBtn.getAttribute("data-revoke-session");
      if (!sessionId) {
        return;
      }

      revokeBtn.disabled = true;
      try {
        const response = await fetch("/revokeDeviceSession", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ session_id: sessionId }),
        });

        const payload = await response.json().catch(function () {
          return {};
        });

        if (!response.ok || payload.success !== true) {
          throw new Error(
            payload.message ||
              profileText(
                "js.profile.could_not_revoke_session",
                "Could not revoke session",
              ),
          );
        }

        if (typeof createSnackbar === "function") {
          createSnackbar(
            payload.message ||
              profileText(
                "js.profile.device_session_revoked",
                "Device session revoked",
              ),
          );
        }
        if (payload.logout) {
          window.location.href = "/home";
          return;
        }
        await loadDeviceSessions();
      } catch (error) {
        if (typeof createSnackbar === "function") {
          createSnackbar(
            profileText(
              "js.profile.could_not_log_out_this_device",
              "Could not log out this device.",
            ),
            "error",
          );
        }
      } finally {
        revokeBtn.disabled = false;
      }
    });

    refreshDeviceSessionsBtn?.addEventListener("click", function () {
      loadDeviceSessions();
    });

    revokeOtherSessionsBtn?.addEventListener("click", async function () {
      revokeOtherSessionsBtn.disabled = true;
      try {
        const response = await fetch("/revokeOtherDeviceSessions", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
        });

        const payload = await response.json().catch(function () {
          return {};
        });

        if (!response.ok || payload.success !== true) {
          throw new Error(
            payload.message ||
              profileText(
                "js.profile.could_not_revoke_sessions",
                "Could not revoke sessions",
              ),
          );
        }

        if (typeof createSnackbar === "function") {
          const count = Number.parseInt(payload.count || "0", 10);
          createSnackbar(
            count > 0
              ? profileFormat(
                  profileText(
                    "js.profile.logged_out_other_sessions",
                    "Logged out {count} other device session(s)",
                  ),
                  { count },
                )
              : profileText(
                  "js.profile.no_other_active_devices",
                  "No other active devices found",
                ),
          );
        }
        await loadDeviceSessions();
      } catch (error) {
        if (typeof createSnackbar === "function") {
          createSnackbar(
            profileText(
              "js.profile.could_not_log_out_other_devices",
              "Could not log out other devices.",
            ),
            "error",
          );
        }
      } finally {
        revokeOtherSessionsBtn.disabled = false;
      }
    });

    loadDeviceSessions();
  }

  if (passwordForm) {
    const passwordNotice = document.getElementById("passwordFormNotice");
    const currentPasswordError = document.getElementById(
      "currentPasswordError",
    );
    const passwordSubmitBtn = document.getElementById("passwordSubmitBtn");
    const passwordSubmitTooltipText = document.getElementById(
      "passwordSubmitTooltipText",
    );

    const updatePasswordButtonState = function () {
      if (!passwordSubmitBtn) return;

      const currentPassword = document.getElementById("currentPassword");
      const newPassword = document.getElementById("newPassword");
      const confirmPassword = document.getElementById("confirmPassword");

      const currentValue = currentPassword ? currentPassword.value : "";
      const newValue = newPassword ? newPassword.value : "";
      const confirmValue = confirmPassword ? confirmPassword.value : "";
      const currentRequired =
        currentPassword && currentPassword.hasAttribute("required");

      let reason = "";

      if (currentRequired && currentValue.trim() === "") {
        reason = profileText(
          "js.profile.tooltip_current_required",
          "Enter your current password",
        );
      } else if (newValue.length === 0) {
        reason = profileText(
          "js.profile.tooltip_new_required",
          "Enter a new password",
        );
      } else if (newValue.length < 8) {
        reason = profileText(
          "js.profile.tooltip_new_min",
          "New password must be at least 8 characters",
        );
      } else if (confirmValue.length === 0) {
        reason = profileText(
          "js.profile.tooltip_confirm_required",
          "Confirm your new password",
        );
      } else if (newValue !== confirmValue) {
        reason = profileText(
          "js.profile.tooltip_mismatch",
          "Passwords do not match",
        );
      }

      const isValid = reason === "";
      passwordSubmitBtn.disabled = !isValid;
      if (passwordSubmitTooltipText) {
        passwordSubmitTooltipText.textContent = isValid ? "" : reason;
      }
    };

    passwordForm
      .querySelectorAll(".profilePasswordInput")
      .forEach(function (input) {
        input.addEventListener("input", updatePasswordButtonState);
      });

    updatePasswordButtonState();

    const showPasswordNotice = function (message, type) {
      if (!passwordNotice) {
        return;
      }

      passwordNotice.textContent = message;
      passwordNotice.classList.remove("is-error", "is-success");
      passwordNotice.classList.add(
        type === "success" ? "is-success" : "is-error",
      );
      passwordNotice.hidden = false;
    };

    const clearPasswordNotice = function () {
      if (!passwordNotice) {
        return;
      }

      passwordNotice.hidden = true;
      passwordNotice.textContent = "";
      passwordNotice.classList.remove("is-error", "is-success");
    };

    const setCurrentPasswordError = function (message) {
      const currentPassword = document.getElementById("currentPassword");
      const currentPasswordField = currentPassword?.closest(
        ".profilePasswordField",
      );

      if (currentPasswordField) {
        currentPasswordField.classList.add("profileInputInvalid");
      }

      if (currentPasswordError) {
        currentPasswordError.textContent = message;
        currentPasswordError.hidden = false;
      }
    };

    const clearCurrentPasswordError = function () {
      const currentPassword = document.getElementById("currentPassword");
      const currentPasswordField = currentPassword?.closest(
        ".profilePasswordField",
      );

      if (currentPasswordField) {
        currentPasswordField.classList.remove("profileInputInvalid");
      }

      if (currentPasswordError) {
        currentPasswordError.hidden = true;
      }
    };

    passwordForm.addEventListener("submit", async function (event) {
      event.preventDefault();

      const currentPassword = document.getElementById("currentPassword");
      const newPassword = document.getElementById("newPassword");
      const confirmPassword = document.getElementById("confirmPassword");
      const submitButton = passwordForm.querySelector('button[type="submit"]');

      const currentValue = currentPassword ? currentPassword.value : "";
      const newValue = newPassword ? newPassword.value : "";
      const confirmValue = confirmPassword ? confirmPassword.value : "";

      clearPasswordNotice();
      clearCurrentPasswordError();

      if (newValue.length < 8) {
        showPasswordNotice(
          profileText(
            "js.profile.new_password_min",
            "New password must be at least 8 characters.",
          ),
          "error",
        );
        newPassword?.focus();
        return;
      }

      if (newValue !== confirmValue) {
        showPasswordNotice(
          profileText(
            "js.profile.new_password_mismatch",
            "New password and confirmation do not match.",
          ),
          "error",
        );
        confirmPassword?.focus();
        return;
      }

      if (
        currentPassword &&
        currentPassword.hasAttribute("required") &&
        currentValue.trim() === ""
      ) {
        setCurrentPasswordError(
          profileText(
            "js.profile.current_password_required",
            "Current password is required.",
          ),
        );
        showPasswordNotice(
          profileText(
            "js.profile.current_password_required",
            "Current password is required.",
          ),
          "error",
        );
        currentPassword.focus();
        return;
      }

      if (submitButton) {
        submitButton.disabled = true;
      }

      try {
        const formData = new FormData(passwordForm);
        const response = await fetch(passwordForm.action, {
          method: "POST",
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
          body: formData,
        });

        const payload = await response.json().catch(function () {
          return {};
        });

        const success = response.ok && payload.success === true;
        const message =
          typeof payload.message === "string" && payload.message.trim() !== ""
            ? payload.message
            : success
              ? profileText(
                  "js.profile.password_updated",
                  "Password updated successfully.",
                )
              : profileText(
                  "js.profile.could_not_update_password",
                  "Could not update password.",
                );

        if (!success) {
          if (
            payload.code === "CURRENT_PASSWORD_INCORRECT" ||
            payload.code === "CURRENT_PASSWORD_REQUIRED"
          ) {
            setCurrentPasswordError(message);
            currentPassword?.focus();
          }
          showPasswordNotice(message, "error");
          return;
        }

        showPasswordNotice(message, "success");
        passwordForm.reset();
        updatePasswordButtonState();
        syncUnsavedState();

        if (passwordForm.dataset.hasPassword === "false") {
          setTimeout(() => location.reload(), 1500);
          return;
        }
      } catch (error) {
        showPasswordNotice(
          profileText(
            "js.profile.could_not_update_password_retry",
            "Could not update password. Please try again.",
          ),
          "error",
        );
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
        }
      }
    });

    document
      .getElementById("currentPassword")
      ?.addEventListener("input", function () {
        clearCurrentPasswordError();
      });
  }

  if (!photoInput || !preview) {
    return;
  }

  photoInput.addEventListener("change", function () {
    const file = photoInput.files && photoInput.files[0];
    if (!file) {
      return;
    }

    const allowedMime = ["image/jpeg", "image/png", "image/gif", "image/webp"];
    if (!allowedMime.includes(file.type)) {
      createSnackbar(
        profileText(
          "js.profile.unsupported_image_type",
          "Unsupported image type.",
        ),
        "error",
      );
      photoInput.value = "";
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      createSnackbar(
        profileText(
          "js.profile.image_too_large",
          "Image must be smaller than 5MB.",
        ),
        "error",
      );
      photoInput.value = "";
      return;
    }

    const reader = new FileReader();
    reader.onload = function (event) {
      if (event.target && typeof event.target.result === "string") {
        preview.src = event.target.result;
      }
    };
    reader.readAsDataURL(file);
  });
});
