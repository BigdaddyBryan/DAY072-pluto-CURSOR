document.addEventListener("DOMContentLoaded", function () {
  const createImageDiv = document.getElementById("createImage");

  if (createImageDiv) {
    createImageDiv.addEventListener("dragenter", function (event) {
      event.preventDefault();
      createImageDiv.classList.add("drag-over");
    });

    createImageDiv.addEventListener("dragover", function (event) {
      event.preventDefault();
      createImageDiv.classList.add("drag-over");
    });

    createImageDiv.addEventListener("dragleave", function (event) {
      event.preventDefault();
      createImageDiv.classList.remove("drag-over");
    });

    createImageDiv.addEventListener("drop", function (event) {
      event.preventDefault();
      createImageDiv.classList.remove("drag-over");
      const files = event.dataTransfer.files;
      const uploadInput =
        document.getElementById("bgInput") ||
        document.getElementById("imageInput");
      if (files.length > 0 && uploadInput) {
        uploadInput.files = files;
        handleFileChange(uploadInput);
      }
    });
  }

  if (document.getElementById("deactivateAllVisible")) {
    const deactivateAllButton = document.getElementById("deactivateAll");
    if (deactivateAllButton) {
      deactivateAllButton.style.display = "block";
    }
  }

  initializeOneTimeGroupPicker();
  initializeOneTimeTimingMode();
  restoreAdminUiState();
});

function adminText(path, fallback) {
  if (typeof getUiText === "function") {
    return getUiText(path, fallback);
  }
  return fallback;
}

function adminFormat(template, values = {}) {
  if (typeof formatUiText === "function") {
    return formatUiText(template, values);
  }
  return String(template || "");
}

function getCurrentAdminState() {
  const activeSectionElement = document.querySelector(
    ".adminSectionOverlay.active",
  );
  const sectionId = activeSectionElement
    ? activeSectionElement.id.replace("section-", "")
    : null;

  return {
    sectionId,
    scrollY: window.scrollY || window.pageYOffset || 0,
  };
}

function persistAdminUiState(preferredSectionId = null) {
  const state = getCurrentAdminState();
  if (preferredSectionId) {
    state.sectionId = preferredSectionId;
  }

  try {
    sessionStorage.setItem("adminUiState", JSON.stringify(state));
  } catch {}
}

function findTriggerForSection(sectionId) {
  if (!sectionId) {
    return null;
  }

  return document.querySelector(
    `.adminDropdownTrigger[onclick*="'${sectionId}'"]`,
  );
}

function restoreAdminUiState() {
  let sectionId = null;
  let scrollY = null;

  const urlParams = new URLSearchParams(window.location.search);
  const querySection = (urlParams.get("section") || "").trim();
  const queryScroll = Number.parseInt(urlParams.get("scroll") || "", 10);

  if (querySection) {
    sectionId = querySection;
  }
  if (Number.isFinite(queryScroll) && queryScroll >= 0) {
    scrollY = queryScroll;
  }

  if (!sectionId || scrollY === null) {
    try {
      const stored = JSON.parse(sessionStorage.getItem("adminUiState") || "{}");
      if (!sectionId && typeof stored.sectionId === "string") {
        sectionId = stored.sectionId;
      }
      if (scrollY === null && Number.isFinite(stored.scrollY)) {
        scrollY = stored.scrollY;
      }
    } catch {}
  }

  if (sectionId) {
    const trigger = findTriggerForSection(sectionId);
    openAdminSection(sectionId, trigger);
  }

  if (Number.isFinite(scrollY) && scrollY >= 0) {
    window.requestAnimationFrame(() => {
      window.scrollTo(0, scrollY);
    });
  }

  if (urlParams.has("section") || urlParams.has("scroll")) {
    const cleanUrl = window.location.pathname;
    window.history.replaceState({}, "", cleanUrl);
  }

  try {
    sessionStorage.removeItem("adminUiState");
  } catch {}
}

function reloadAdminWithState(preferredSectionId = null, delayMs = 0) {
  persistAdminUiState(preferredSectionId);
  const doReload = () => {
    if (typeof window.reloadPageFast === "function") {
      window.reloadPageFast({ delay: 0 });
      return;
    }
    window.location.reload();
  };

  if (delayMs > 0) {
    window.setTimeout(doReload, delayMs);
    return;
  }

  doReload();
}

let selectAllButton = document.getElementById("selectAllButton");
let deselectAllButton = document.getElementById("deselectAllButton");
let deleteSelectedButton = document.getElementById("deleteSelectedButton");

if (typeof window.showThemedConfirm !== "function") {
  window.showThemedConfirm = function (
    message,
    { confirmText = "Yes", cancelText = "No", title = "Please confirm" } = {},
  ) {
    return new Promise((resolve) => {
      const restoreFocusTarget =
        document.activeElement instanceof HTMLElement
          ? document.activeElement
          : null;

      const escapeText = (value) =>
        String(value ?? "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/\"/g, "&quot;")
          .replace(/'/g, "&#39;");

      const existing = document.getElementById("themedConfirmModal");
      if (existing) {
        existing.remove();
      }

      const html = `
        <div id="themedConfirmModal" class="confirmationBackground">
          <div class="modal-content confirmModal" role="dialog" aria-modal="true" aria-label="Confirmation dialog">
            <div class="modalWindowControls">
              <button type="button" class="themedConfirmClose modalWindowControl crossIcon material-icons" aria-label="Close modal">close</button>
            </div>
            <div class="deleteConfirmModal">
              <div class="deleteConfirmIcon" aria-hidden="true">
                <i class="material-icons">warning_amber</i>
              </div>
              <h3 class="deleteConfirmTitle">${escapeText(title)}</h3>
              <p class="deleteConfirmBody">${escapeText(message)}</p>
              <div class="modal-footer deleteConfirmActions confirmationButtons">
                <button type="button" class="deleteBtn confirmSafeBtn">${escapeText(cancelText)}</button>
                <button type="button" class="deleteBtn confirmDangerBtn">${escapeText(confirmText)}</button>
              </div>
            </div>
          </div>
        </div>
      `;

      document.body.insertAdjacentHTML("beforeend", html);

      const modal = document.getElementById("themedConfirmModal");
      const modalContent = modal?.querySelector(".confirmModal");
      const closeButton = modal?.querySelector(".themedConfirmClose");
      const confirmButton = modal?.querySelector(".confirmDangerBtn");
      const cancelButton = modal?.querySelector(".confirmSafeBtn");
      let closed = false;

      const close = (result) => {
        if (closed) {
          return;
        }

        closed = true;
        document.removeEventListener("keydown", handleEscape);
        modalContent?.removeEventListener("keydown", handleEnterDefault);
        modal?.remove();

        if (restoreFocusTarget && document.body.contains(restoreFocusTarget)) {
          requestAnimationFrame(() => {
            try {
              restoreFocusTarget.focus({ preventScroll: true });
            } catch {
              // Ignore focus restore failures for detached/non-focusable nodes.
            }
          });
        }

        resolve(result);
      };

      const handleEscape = (event) => {
        if (event.key === "Escape") {
          event.stopImmediatePropagation();
          close(false);
        }
      };

      const handleEnterDefault = (event) => {
        if (
          event.key !== "Enter" ||
          event.defaultPrevented ||
          event.ctrlKey ||
          event.metaKey ||
          event.altKey ||
          event.shiftKey
        ) {
          return;
        }

        if (
          event.target &&
          typeof event.target.closest === "function" &&
          event.target.closest(
            "button, a, input, textarea, select, [role='button']",
          )
        ) {
          return;
        }

        event.preventDefault();
        cancelButton?.click();
      };

      requestAnimationFrame(() => {
        cancelButton?.focus({ preventScroll: true });
      });

      confirmButton?.addEventListener("click", () => close(true));
      cancelButton?.addEventListener("click", () => close(false));
      closeButton?.addEventListener("click", () => close(false));
      modal?.addEventListener("click", (event) => {
        if (!modalContent?.contains(event.target)) {
          close(false);
        }
      });
      modalContent?.addEventListener("keydown", handleEnterDefault);
      document.addEventListener("keydown", handleEscape);
    });
  };
}

function getOneTimeCreateSettings() {
  const expirationSelect = document.getElementById("oneTimeExpirationDays");
  const useAdvancedTimingCheckbox = document.getElementById(
    "oneTimeUseAdvancedTiming",
  );
  const validFromInput = document.getElementById("oneTimeValidFrom");
  const validUntilInput = document.getElementById("oneTimeValidUntil");
  const sessionMinutesSelect = document.getElementById("oneTimeSessionMinutes");
  const roleSelect = document.getElementById("oneTimeRole");
  const autoCopyCheckbox = document.getElementById("oneTimeAutoCopy");

  const expirationDays = Number.parseInt(expirationSelect?.value ?? "7", 10);
  const useAdvancedTiming = !!useAdvancedTimingCheckbox?.checked;
  const validFrom = (validFromInput?.value || "").trim();
  const validUntil = (validUntilInput?.value || "").trim();
  const sessionMinutes = Number.parseInt(
    sessionMinutesSelect?.value ?? "60",
    10,
  );
  const role = (roleSelect?.value || "viewer").trim();
  const selectedGroups = Array.from(
    document.querySelectorAll(".oneTimeGroupCheckbox:checked"),
  )
    .map((checkbox) => Number.parseInt(checkbox.value, 10))
    .filter((groupId) => Number.isFinite(groupId) && groupId > 0);

  return {
    expiration_days: Number.isFinite(expirationDays) ? expirationDays : 7,
    use_advanced_timing: useAdvancedTiming,
    valid_from: validFrom,
    valid_until: validUntil,
    session_minutes: Number.isFinite(sessionMinutes) ? sessionMinutes : 60,
    role: role || "viewer",
    group_ids: selectedGroups,
    autoCopy: autoCopyCheckbox ? autoCopyCheckbox.checked : true,
  };
}

function initializeOneTimeGroupPicker() {
  const searchInput = document.getElementById("oneTimeGroupSearch");
  const allGroupsCheckbox = document.getElementById("oneTimeAllGroupsCheckbox");
  const allGroupsOption = allGroupsCheckbox
    ? allGroupsCheckbox.closest(".oneTimeGroupOption")
    : null;
  const groupCountLabel = document.getElementById("oneTimeGroupCount");
  const optionElements = Array.from(
    document.querySelectorAll(".oneTimeGroupOption"),
  );
  const checkboxElements = Array.from(
    document.querySelectorAll(".oneTimeGroupCheckbox"),
  );

  if (
    !searchInput ||
    !allGroupsCheckbox ||
    !allGroupsOption ||
    !groupCountLabel
  ) {
    return;
  }

  const updateSummary = () => {
    const selectedCheckboxes = checkboxElements.filter(
      (checkbox) => checkbox.checked,
    );

    if (selectedCheckboxes.length === 0) {
      allGroupsCheckbox.checked = true;
      allGroupsOption.classList.add("active");
      groupCountLabel.textContent = adminText(
        "js.admin.all_groups",
        "All groups",
      );
      return;
    }

    allGroupsCheckbox.checked = false;
    allGroupsOption.classList.remove("active");
    if (selectedCheckboxes.length === 1) {
      groupCountLabel.textContent =
        selectedCheckboxes[0].dataset.title ||
        adminText("js.admin.one_group_selected", "1 group selected");
      return;
    }

    groupCountLabel.textContent = adminFormat(
      adminText("js.admin.groups_selected", "{count} groups selected"),
      { count: selectedCheckboxes.length },
    );
  };

  const filterGroups = () => {
    const query = searchInput.value.trim().toLowerCase();

    optionElements.forEach((optionElement) => {
      const title = optionElement.dataset.title || "";
      if (optionElement.classList.contains("oneTimeGroupOptionAll")) {
        optionElement.style.display = "flex";
        return;
      }
      optionElement.style.display = title.includes(query) ? "flex" : "none";
    });
  };

  allGroupsCheckbox.addEventListener("change", () => {
    if (!allGroupsCheckbox.checked) {
      if (checkboxElements.every((checkbox) => !checkbox.checked)) {
        allGroupsCheckbox.checked = true;
      }
      updateSummary();
      return;
    }

    checkboxElements.forEach((checkbox) => {
      checkbox.checked = false;
    });
    allGroupsOption.classList.add("active");
    updateSummary();
  });

  checkboxElements.forEach((checkbox) => {
    checkbox.addEventListener("change", () => {
      if (checkbox.checked) {
        allGroupsCheckbox.checked = false;
        allGroupsOption.classList.remove("active");
      }

      updateSummary();
    });
  });

  searchInput.addEventListener("input", filterGroups);

  updateSummary();
}

function initializeOneTimeTimingMode() {
  const useAdvancedTimingCheckbox = document.getElementById(
    "oneTimeUseAdvancedTiming",
  );
  const advancedTimingFields = document.getElementById(
    "oneTimeAdvancedTimingFields",
  );

  if (!useAdvancedTimingCheckbox || !advancedTimingFields) {
    return;
  }

  const updateTimingVisibility = () => {
    advancedTimingFields.style.display = useAdvancedTimingCheckbox.checked
      ? "flex"
      : "none";
  };

  useAdvancedTimingCheckbox.addEventListener("change", updateTimingVisibility);
  updateTimingVisibility();
}

function setCreateButtonsState(isLoading) {
  const createButtons = document.querySelectorAll(".createTokenButton");

  createButtons.forEach((button) => {
    if (button.id === "deactivateAll") {
      return;
    }

    if (isLoading) {
      button.dataset.originalHtml = button.innerHTML;
      button.textContent = adminText(
        "js.admin.creating_link",
        "Creating link...",
      );
      button.disabled = true;
      button.classList.add("loading");
      return;
    }

    button.innerHTML = button.dataset.originalHtml || button.innerHTML;
    button.disabled = false;
    button.classList.remove("loading");
  });
}

async function requestOneTimeLink() {
  const settings = getOneTimeCreateSettings();

  let expirationDays = settings.expiration_days;
  let validFrom = null;
  let validUntil = null;
  let sessionMinutes = null;

  if (settings.use_advanced_timing) {
    if (!settings.valid_from || !settings.valid_until) {
      throw new Error("Please select both start and end date/time.");
    }

    const validFromDate = new Date(settings.valid_from);
    const validUntilDate = new Date(settings.valid_until);
    if (
      Number.isNaN(validFromDate.getTime()) ||
      Number.isNaN(validUntilDate.getTime())
    ) {
      throw new Error("Invalid date/time value provided.");
    }

    if (validUntilDate <= validFromDate) {
      throw new Error("End date/time must be later than start date/time.");
    }

    const windowMs = validUntilDate.getTime() - validFromDate.getTime();
    expirationDays = Math.max(1, Math.ceil(windowMs / (1000 * 60 * 60 * 24)));
    validFrom = settings.valid_from;
    validUntil = settings.valid_until;
    sessionMinutes = settings.session_minutes;
  }

  const response = await fetch("/createOneTime", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      expiration_days: expirationDays,
      valid_from: validFrom,
      valid_until: validUntil,
      session_minutes: sessionMinutes,
      role: settings.role,
      group_ids: settings.group_ids,
    }),
  });

  const data = await response.json();
  return { response, data, settings };
}

async function createOneTime(event) {
  if (event) {
    event.preventDefault();
  }

  setCreateButtonsState(true);

  try {
    const { data, settings } = await requestOneTimeLink();

    if (!data.success || !data.url) {
      createSnackbar(
        adminFormat(
          adminText("js.one_time.error_with_message", "✗ Error: {message}"),
          {
            message:
              data.message ||
              adminText(
                "js.one_time.failed_create_link",
                "Failed to create link",
              ),
          },
        ),
        "error",
      );
      return;
    }

    if (settings.autoCopy) {
      try {
        await navigator.clipboard.writeText(data.url);
        createSnackbar(
          adminText(
            "js.one_time.link_created_copied",
            "✓ Link created and copied to clipboard",
          ),
        );
      } catch {
        createSnackbar(
          adminText(
            "js.one_time.link_created_copy_failed_manual",
            "✓ Link created. Copy failed, click the link field to copy manually.",
          ),
        );
      }
    } else {
      createSnackbar(
        adminText(
          "js.one_time.link_created_successfully",
          "✓ Link created successfully",
        ),
      );
    }

    refreshTokensList();
  } catch (error) {
    createSnackbar(
      adminFormat(
        adminText("js.one_time.error_with_message", "✗ Error: {message}"),
        {
          message:
            error?.message ||
            adminText(
              "js.one_time.failed_create_access_link",
              "Failed to create access link",
            ),
        },
      ),
      "error",
    );
  } finally {
    setCreateButtonsState(false);
  }
}

async function createOneTimeAndOpen(event) {
  if (event) {
    event.preventDefault();
  }

  const pendingTab = window.open("", "_blank");
  if (pendingTab) {
    pendingTab.opener = null;
    pendingTab.document.title = adminText(
      "js.one_time.opening_access_link",
      "Opening access link...",
    );
    pendingTab.document.body.innerHTML = `<p style="font-family: sans-serif; padding: 16px;">${adminText(
      "js.one_time.generating_access_link",
      "Generating access link...",
    )}</p>`;
  }

  setCreateButtonsState(true);

  try {
    const { data, settings } = await requestOneTimeLink();

    if (!data.success || !data.url) {
      if (pendingTab) {
        pendingTab.close();
      }
      createSnackbar(
        adminFormat(
          adminText("js.one_time.error_with_message", "✗ Error: {message}"),
          {
            message:
              data.message ||
              adminText(
                "js.one_time.failed_create_link",
                "Failed to create link",
              ),
          },
        ),
        "error",
      );
      return;
    }

    if (settings.autoCopy) {
      try {
        await navigator.clipboard.writeText(data.url);
      } catch {}
    }

    if (pendingTab) {
      pendingTab.location.href = data.url;
    } else {
      window.open(data.url, "_blank", "noopener,noreferrer");
    }
    createSnackbar(
      adminText(
        "js.one_time.access_link_created_opened_new_tab",
        "✓ Access link created and opened in a new tab",
      ),
    );
    refreshTokensList();
  } catch (error) {
    if (pendingTab) {
      pendingTab.close();
    }
    createSnackbar(
      adminFormat(
        adminText("js.one_time.error_with_message", "✗ Error: {message}"),
        {
          message:
            error?.message ||
            adminText(
              "js.one_time.failed_create_access_link",
              "Failed to create access link",
            ),
        },
      ),
      "error",
    );
  } finally {
    setCreateButtonsState(false);
  }
}

async function deleteOneTime(token, event) {
  // Confirm deletion
  if (
    !(await window.showThemedConfirm(
      adminText(
        "js.one_time.confirm_delete_access_link",
        "Are you sure you want to delete this access link?",
      ),
    ))
  ) {
    return;
  }

  const tokenContainer = document.querySelector(`[data-token="${token}"]`);
  if (tokenContainer) {
    tokenContainer.style.opacity = "0.5";
  }

  fetch("/deleteOneTime", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ token: token }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        createSnackbar(
          adminText("js.one_time.access_link_deleted", "✓ Access link deleted"),
        );
        refreshTokensList();
      } else {
        createSnackbar(
          adminFormat(
            adminText("js.one_time.error_with_message", "✗ Error: {message}"),
            {
              message:
                data.message ||
                adminText("js.one_time.failed_delete", "Failed to delete"),
            },
          ),
          "error",
        );
        if (tokenContainer) {
          tokenContainer.style.opacity = "1";
        }
      }
    })
    .catch((error) => {
      createSnackbar(
        adminText(
          "js.one_time.failed_delete_access_link",
          "✗ Failed to delete access link",
        ),
        "error",
      );
      if (tokenContainer) {
        tokenContainer.style.opacity = "1";
      }
    });
}

async function deactivateOneTime(token, event) {
  // Confirm deactivation
  if (
    !(await window.showThemedConfirm(
      adminText(
        "js.one_time.confirm_deactivate_access_link",
        "Are you sure you want to deactivate this access link?",
      ),
    ))
  ) {
    return;
  }

  const tokenContainer = document.querySelector(`[data-token="${token}"]`);
  if (tokenContainer) {
    tokenContainer.style.opacity = "0.5";
  }

  fetch("/deactivateOneTime", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ token: token }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        createSnackbar(
          adminText(
            "js.one_time.access_link_deactivated",
            "✓ Access link deactivated",
          ),
        );
        refreshTokensList();
      } else {
        createSnackbar(
          adminFormat(
            adminText("js.one_time.error_with_message", "✗ Error: {message}"),
            {
              message:
                data.message ||
                adminText(
                  "js.one_time.failed_deactivate",
                  "Failed to deactivate",
                ),
            },
          ),
          "error",
        );
        if (tokenContainer) {
          tokenContainer.style.opacity = "1";
        }
      }
    })
    .catch((error) => {
      createSnackbar(
        adminText(
          "js.one_time.failed_deactivate_access_link",
          "✗ Failed to deactivate access link",
        ),
        "error",
      );
      if (tokenContainer) {
        tokenContainer.style.opacity = "1";
      }
    });
}

async function deactivateAllOneTimes(event) {
  // Confirm bulk deactivation
  if (
    !(await window.showThemedConfirm(
      adminText(
        "js.one_time.confirm_deactivate_all_unused_access_links",
        "Are you sure you want to deactivate ALL unused access links?",
      ),
    ))
  ) {
    return;
  }

  const button = document.getElementById("deactivateAll");
  if (button) {
    button.style.opacity = "0.5";
    button.disabled = true;
  }

  fetch("/deactivateAllOneTimes", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        createSnackbar(
          adminFormat(
            adminText("js.one_time.success_with_message", "✓ {message}"),
            {
              message:
                data.message ||
                adminText("js.one_time.links_deactivated", "Links deactivated"),
            },
          ),
        );
        refreshTokensList();
      } else {
        createSnackbar(
          adminFormat(
            adminText("js.one_time.error_with_message", "✗ Error: {message}"),
            {
              message:
                data.message ||
                adminText(
                  "js.one_time.failed_deactivate",
                  "Failed to deactivate",
                ),
            },
          ),
          "error",
        );
      }
    })
    .catch((error) => {
      createSnackbar(
        adminText(
          "js.one_time.failed_deactivate_access_links",
          "✗ Failed to deactivate access links",
        ),
        "error",
      );
    })
    .finally(() => {
      if (button) {
        button.style.opacity = "1";
        button.disabled = false;
      }
    });
}

/**
 * Refresh tokens list without page reload
 */
function refreshTokensList() {
  // Small delay for visual feedback
  setTimeout(() => {
    reloadAdminWithState("oneTimeLogin");
  }, 500);
}

/**
 * Attach event listeners to dynamically loaded token elements
 */
function attachTokenListeners() {
  // Reattach click handlers to copy buttons
  document.querySelectorAll(".adminInput").forEach((input) => {
    input.addEventListener("click", function (e) {
      const value = this.querySelector("input").value;
      copyLink(value, e);
    });
  });
}

function openBackgroundUpload() {
  const uploadInput =
    document.getElementById("bgInput") || document.getElementById("imageInput");

  if (!uploadInput) {
    createSnackbar(
      adminText("js.admin.upload_input_not_found", "✗ Upload input not found"),
      "error",
    );
    return;
  }

  uploadInput.click();
}

function handleFileChange(source) {
  let fileInput = null;

  if (source?.target?.files) {
    fileInput = source.target;
  } else if (source?.files) {
    fileInput = source;
  }

  if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
    const bgInput = document.getElementById("bgInput");
    const imageInput = document.getElementById("imageInput");
    if (bgInput && bgInput.files && bgInput.files.length > 0) {
      fileInput = bgInput;
    } else if (imageInput && imageInput.files && imageInput.files.length > 0) {
      fileInput = imageInput;
    }
  }

  if (!fileInput) {
    return;
  }

  const files = Array.from(fileInput.files); // Convert FileList to an array
  if (files.length === 0) {
    return;
  }

  files.forEach((file) => {
    const formData = new FormData();
    formData.append("image", file);

    fetch("/uploadImage", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((result) => {
        if (!result || result.error) {
          throw new Error(
            result?.error ||
              adminText(
                "js.admin.failed_upload_image",
                "Failed to upload image",
              ),
          );
        }

        fetch("/getImages")
          .then((response) => response.text())
          .then((data) => {
            document.getElementById("customImageGrid").innerHTML = data;
            createSnackbar(
              adminText(
                "js.admin.image_uploaded_success",
                "Image uploaded successfully",
              ),
            );
          });
      })
      .catch((error) =>
        createSnackbar(
          "✗ " +
            (error?.message ||
              adminText(
                "js.admin.failed_upload_image",
                "Failed to upload image",
              )),
          "error",
        ),
      );
  });

  fileInput.value = "";
}

async function cleanupMedia() {
  if (
    !(await window.showThemedConfirm(
      adminText(
        "js.admin.confirm_media_cleanup",
        "Are you sure you want to clean up media? This removes slideshow archive folders, keeps only the 3 newest login backgrounds, and deletes unused group and profile images.",
      ),
    ))
  ) {
    return;
  }

  const btn = document.getElementById("cleanupMediaBtn");
  if (btn) btn.disabled = true;

  try {
    const response = await fetch("/cleanupMedia", { method: "POST" });
    const result = await response.json();

    if (result.error) {
      throw new Error(result.error);
    }

    const msg = adminText(
      "js.admin.media_cleanup_done",
      "Cleaned up %deleted% files, kept %kept%",
    )
      .replace("%deleted%", result.deleted)
      .replace("%kept%", result.kept);
    createSnackbar(msg);

    // Refresh background image grid if visible
    const container = document.getElementById("customImageGrid");
    if (container) {
      container.innerHTML = "";
      fetchImages();
    }
  } catch (err) {
    createSnackbar(
      adminText("js.admin.media_cleanup_failed", "Could not clean up media") +
        (err.message ? ": " + err.message : ""),
      "error",
    );
  } finally {
    if (btn) btn.disabled = false;
  }
}

async function cleanupFragments() {
  if (
    !(await window.showThemedConfirm(
      adminText(
        "js.admin.confirm_cleanup_fragments",
        "Delete all unused tags from the database? This only removes tags that are not linked to any link, user, or visitor.",
      ),
    ))
  ) {
    return;
  }

  const btn = document.getElementById("cleanupFragmentsBtn");
  if (btn) btn.disabled = true;

  try {
    const response = await fetch("/cleanupFragments", { method: "POST" });
    const result = await response.json();

    if (!response.ok || result.error) {
      throw new Error(result.error || "Cleanup failed");
    }

    const msg = adminText(
      "js.admin.cleanup_fragments_done",
      "Deleted %deleted% unused tag(s).",
    ).replace("%deleted%", String(result.deleted || 0));
    createSnackbar(msg, "success");
  } catch (err) {
    createSnackbar(
      adminText(
        "js.admin.cleanup_fragments_failed",
        "Could not clean up unused tags",
      ) + (err?.message ? ": " + err.message : ""),
      "error",
    );
  } finally {
    if (btn) btn.disabled = false;
  }
}

function deleteImage(imageName) {
  fetch(`/deleteImage?imageName=${imageName}`)
    .then((response) => response.text())
    .then((data) => {
      const image = document.getElementById(imageName);
      if (image) {
        image.remove();
      }

      selectedImages = selectedImages.filter((img) => img !== imageName);
      imageContainers = document.querySelectorAll(
        '.customImageContainer[draggable="true"]',
      );
      order = Array.from(imageContainers).map((container) => container.id);
      refreshImageSelectionState();

      let container = document.getElementById("customImageGrid");
      container.innerHTML = "";
      fetchImages();
      createSnackbar(
        adminText(
          "js.admin.image_deleted_success",
          "Image deleted successfully",
        ),
      );
    })
    .catch(() =>
      createSnackbar(
        "✗ " +
          adminText("js.admin.failed_delete_image", "Failed to delete image"),
        "error",
      ),
    );
}

function copyLink(link, event) {
  event.preventDefault();
  navigator.clipboard.writeText(link).then(
    function () {
      const prefix = adminText(
        "js.admin.link_copied_prefix",
        "Link copied to clipboard: ",
      );
      createSnackbar(prefix + link);
    },
    function () {
      createSnackbar(
        adminText("js.links.could_not_copy_link", "Could not copy link"),
        "error",
      );
    },
  );
}

let order = []; // Initialize the order array
let imageContainers = document.querySelectorAll(
  '.customImageContainer[draggable="true"]',
);

// Populate the initial order array based on the current DOM structure
imageContainers.forEach((container) => {
  order.push(container.id);
});

imageContainers.forEach((item) => {
  item.addEventListener("dragstart", dragStart);
  item.addEventListener("drop", dropped);
  item.addEventListener("dragenter", cancelDefault);
  item.addEventListener("dragover", cancelDefault);

  // Add hold event listeners for multi-select
  let holdTimeout;
  item.addEventListener("mousedown", function (event) {
    holdTimeout = setTimeout(() => {
      hold(event, item);
    }, 1000); // 1 second hold time
  });

  item.addEventListener("mouseup", function () {
    clearTimeout(holdTimeout);
  });

  item.addEventListener("mouseleave", function () {
    clearTimeout(holdTimeout);
  });
});

function dragStart(e) {
  e.dataTransfer.setData("text/plain", e.target.id);
}

function dropped(e) {
  cancelDefault(e);

  const target = e.target.closest(".customImageContainer"); // Ensure we get the correct target container
  if (!target) return; // Guard clause for invalid drop target

  const droppedId = e.dataTransfer.getData("text/plain");
  const droppedItem = document.getElementById(droppedId);
  const oldIndex = order.indexOf(droppedId); // Find the old index from the order array
  const newIndex = Array.from(imageContainers).indexOf(target); // Get the new index

  // Remove and reinsert dragged item
  if (newIndex < oldIndex) {
    target.parentNode.insertBefore(droppedItem, target);
  } else {
    target.parentNode.insertBefore(droppedItem, target.nextSibling);
  }

  // Update the order array
  order.splice(oldIndex, 1); // Remove old index
  order.splice(newIndex, 0, droppedId); // Insert at new index

  // Fetch updated order
  fetch("/updateOrder", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ order }),
  })
    .then((response) => response.json()) // Ensure the response is parsed as JSON
    .then((data) => {
      // Update the order array with new names based on response
      order = data; // Update the local order array with the response
      // Refresh the imageContainers NodeList after DOM manipulation
      fetchImages();
    })
    .catch(() =>
      createSnackbar(
        adminText(
          "js.admin.failed_update_image_order",
          "✗ Failed to update image order",
        ),
        "error",
      ),
    );
}

function shuffle(array) {
  let currentIndex = array.length,
    randomIndex;

  // While there remain elements to shuffle...
  while (currentIndex != 0) {
    // Pick a remaining element...
    randomIndex = Math.floor(Math.random() * currentIndex);
    currentIndex--;

    // And swap it with the current element.
    [array[currentIndex], array[randomIndex]] = [
      array[randomIndex],
      array[currentIndex],
    ];
  }

  return array;
}

function randomizeImages() {
  order = shuffle(order);

  fetch("/updateOrder", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ order }),
  })
    .then((response) => response.json()) // Ensure the response is parsed as JSON
    .then((data) => {
      // Update the order array with new names based on response
      order = data; // Update the local order array with the response
      // Refresh the imageContainers NodeList after DOM manipulation
      fetchImages();
    })
    .catch(() =>
      createSnackbar(
        adminText(
          "js.admin.failed_update_image_order",
          "✗ Failed to update image order",
        ),
        "error",
      ),
    );
}

function fetchImages() {
  fetch("/getImages")
    .then((response) => response.text())
    .then((data) => {
      document.getElementById("customImageGrid").innerHTML = data;

      document.querySelectorAll("img.customImage").forEach((img) => {
        if (!img.getAttribute("src")) {
          const fallbackSrc = img.getAttribute("data-src");
          if (fallbackSrc) {
            img.setAttribute("src", fallbackSrc);
          }
        }
      });

      imageContainers = document.querySelectorAll(
        '.customImageContainer[draggable="true"]',
      );

      imageContainers.forEach((item) => {
        item.addEventListener("dragstart", dragStart);
        item.addEventListener("drop", dropped);
        item.addEventListener("dragenter", cancelDefault);
        item.addEventListener("dragover", cancelDefault);
      });
    });
}

function cancelDefault(e) {
  e.preventDefault();
  e.stopPropagation();
  return false;
}

function hold(e, item) {
  e.preventDefault();
  imageMultiSelect(e, item.id);
}

let selectedImages = [];
function refreshImageSelectionState() {
  if (!selectAllButton || !deselectAllButton || !deleteSelectedButton) {
    return;
  }

  selectedImages = selectedImages.filter((imageName) => {
    const image = document.getElementById(imageName);
    return !!image;
  });

  const selectedCount = selectedImages.length;
  const totalCount = imageContainers.length;

  if (selectedCount === 0) {
    selectAllButton.style.display = totalCount > 0 ? "block" : "none";
    deselectAllButton.style.display = "none";
    deleteSelectedButton.style.display = "none";
  } else if (selectedCount === totalCount) {
    selectAllButton.style.display = "none";
    deselectAllButton.style.display = "block";
    deleteSelectedButton.style.display = "block";
  } else {
    selectAllButton.style.display = "block";
    deselectAllButton.style.display = "block";
    deleteSelectedButton.style.display = "block";
  }
}

function imageMultiSelect(event, imageName) {
  event.preventDefault();
  const image = document.getElementById(imageName);
  image.classList.toggle("selected");
  if (image.classList.contains("selected")) {
    document.getElementById(`checkbox-${imageName}`).checked = true;
    selectedImages.push(imageName);
  } else {
    document.getElementById(`checkbox-${imageName}`).checked = false;
    selectedImages = selectedImages.filter((img) => img !== imageName);
  }

  refreshImageSelectionState();
}

function selectAll() {
  selectedImages = [];
  imageContainers.forEach((image) => {
    image.classList.add("selected");
    document.getElementById(`checkbox-${image.id}`).checked = true;
    selectedImages.push(image.id);
  });

  refreshImageSelectionState();
}

function deselectAll() {
  imageContainers.forEach((image) => {
    image.classList.remove("selected");
    document.getElementById(`checkbox-${image.id}`).checked = false;
  });

  selectedImages = [];
  refreshImageSelectionState();
}

function deleteSelected() {
  if (selectedImages.length === 0) {
    createSnackbar(
      adminText("js.admin.no_images_selected", "No images selected"),
    );
    return;
  }

  const imagesToDelete = [...selectedImages];
  selectedImages = [];
  refreshImageSelectionState();

  imagesToDelete.forEach((image) => {
    deleteImage(image);
  });
}

async function uploadCustom404() {
  // Get the file input element
  const fileInput = document.getElementById("404Input");

  // Check if the user selected a file
  if (fileInput.files.length === 0) {
    createSnackbar(
      adminText("js.admin.no_file_selected", "No file selected"),
      "error",
    );
    return;
  }

  // Get the selected file
  const file = fileInput.files[0];

  // Create a FormData object and append the file
  const formData = new FormData();
  formData.append("file", file);

  try {
    // Send the POST request to upload the HTML file
    const response = await fetch("/uploadCustom404", {
      method: "POST",
      body: formData,
    });

    // Check if the upload was successful
    if (response.ok) {
      const result = await response.text();
    } else {
      createSnackbar(
        adminText(
          "js.admin.failed_upload_custom_404_file",
          "Failed to upload custom 404 file",
        ),
        "error",
      );
    }
  } catch (error) {
    createSnackbar(
      adminText(
        "js.admin.error_uploading_custom_404_file",
        "Error uploading custom 404 file",
      ),
      "error",
    );
  }
}

async function deleteCustom404() {
  if (
    !(await window.showThemedConfirm(
      adminText(
        "js.admin.confirm_delete_custom_404_page",
        "Are you sure you want to delete the custom 404 page?",
      ),
    ))
  ) {
    return;
  }

  if (
    !(await window.showThemedConfirm(
      adminText(
        "js.admin.confirm_delete_custom_404_page_irreversible",
        "This action cannot be undone. Confirm delete custom 404 page?",
      ),
    ))
  ) {
    return;
  }

  fetch("/deleteCustom404", {
    method: "POST",
  })
    .then((response) => response.text())
    .then((data) => {
      if (typeof window.queueSnackbarAfterReload === "function") {
        window.queueSnackbarAfterReload(
          adminText(
            "js.admin.custom_404_page_deleted",
            "Custom 404 page deleted",
          ),
          null,
        );
      }
      reloadAdminWithState("customStyling");
    })
    .catch(() =>
      createSnackbar(
        adminText(
          "js.admin.failed_delete_custom_404_page",
          "✗ Failed to delete custom 404 page",
        ),
        "error",
      ),
    );
}

function downloadCustomFolder() {
  window.location.href = "/downloadCustom";
}

async function uploadCustomCSS(inputId = "cssUploadInput") {
  const fileInput = document.getElementById(inputId);
  if (!fileInput) {
    createSnackbar(
      adminText("js.admin.upload_input_not_found", "Upload input not found"),
      "error",
    );
    return;
  }

  if (fileInput.files.length === 0) {
    createSnackbar(adminText("js.admin.no_file_selected", "No file selected"));
    return;
  }

  const file = fileInput.files[0];

  // Validate that it's a zip file
  const fileName = (file.name || "").toLowerCase();
  const allowedZipTypes = [
    "application/zip",
    "application/x-zip-compressed",
    "application/octet-stream",
    "",
  ];
  if (!fileName.endsWith(".zip") || !allowedZipTypes.includes(file.type)) {
    createSnackbar(
      adminText(
        "js.admin.upload_valid_zip_file",
        "Please upload a valid zip file",
      ),
    );
    return;
  }

  const formData = new FormData();
  formData.append("customZip", file);

  try {
    const response = await fetch("/uploadCustom", {
      method: "POST",
      body: formData,
    });

    if (response.ok) {
      if (typeof window.queueSnackbarAfterReload === "function") {
        window.queueSnackbarAfterReload(
          adminText(
            "js.admin.css_uploaded_successfully",
            "CSS uploaded successfully",
          ),
          null,
        );
      } else {
        createSnackbar(
          adminText(
            "js.admin.css_uploaded_successfully",
            "CSS uploaded successfully",
          ),
        );
      }
      fileInput.value = "";
      reloadAdminWithState("customStyling");
    } else {
      const errorText = await response.text();
      createSnackbar(
        adminFormat(
          adminText(
            "js.admin.upload_failed_with_message",
            "Upload failed: {message}",
          ),
          {
            message: errorText,
          },
        ),
      );
    }
  } catch (error) {
    createSnackbar(
      adminText(
        "js.admin.error_uploading_css_file",
        "Error uploading CSS file",
      ),
    );
  }
}

function downloadCustomThemeCss(theme = "light") {
  const normalizedTheme = theme === "dark" ? "dark" : "light";
  window.location.href = `/downloadCustomThemeCss?theme=${encodeURIComponent(normalizedTheme)}`;
}

async function uploadCustomThemeCss(
  theme = "light",
  inputId = "customCssDashboardThemeUpload",
) {
  const fileInput = document.getElementById(inputId);
  if (!fileInput) {
    createSnackbar(
      adminText("js.admin.upload_input_not_found", "Upload input not found"),
      "error",
    );
    return;
  }

  if (fileInput.files.length === 0) {
    createSnackbar(adminText("js.admin.no_file_selected", "No file selected"));
    return;
  }

  const normalizedTheme = theme === "dark" ? "dark" : "light";
  const file = fileInput.files[0];
  const fileName = (file.name || "").toLowerCase();
  const allowedCssTypes = [
    "text/css",
    "text/plain",
    "application/octet-stream",
    "",
  ];
  if (!fileName.endsWith(".css") || !allowedCssTypes.includes(file.type)) {
    createSnackbar(
      adminText(
        "js.admin.upload_valid_css_file",
        "Please upload a valid CSS file",
      ),
      "error",
    );
    return;
  }

  const formData = new FormData();
  formData.append("themeCss", file);

  try {
    const response = await fetch(
      `/uploadCustomThemeCss?theme=${encodeURIComponent(normalizedTheme)}`,
      {
        method: "POST",
        body: formData,
      },
    );

    const payload = await response.json().catch(async () => ({
      success: response.ok,
      message: await response.text().catch(() => ""),
    }));

    if (!response.ok || payload?.success === false) {
      throw new Error(
        payload?.message ||
          adminText(
            "js.admin.error_uploading_css_file",
            "Error uploading CSS file",
          ),
      );
    }

    fileInput.value = "";

    if (typeof window.queueSnackbarAfterReload === "function") {
      window.queueSnackbarAfterReload(
        payload?.message ||
          adminText(
            "js.admin.css_uploaded_successfully",
            "CSS uploaded successfully",
          ),
        null,
      );
    } else {
      createSnackbar(
        payload?.message ||
          adminText(
            "js.admin.css_uploaded_successfully",
            "CSS uploaded successfully",
          ),
        "success",
      );
    }

    reloadAdminWithState("customStyling");
  } catch (error) {
    createSnackbar(
      error?.message ||
        adminText(
          "js.admin.error_uploading_css_file",
          "Error uploading CSS file",
        ),
      "error",
    );
  }
}

async function uploadBrandAsset(
  assetType = "logo",
  theme = "light",
  inputId = "brandAssetUploadInput",
) {
  const fileInput = document.getElementById(inputId);
  if (!fileInput) {
    createSnackbar(
      adminText("js.admin.upload_input_not_found", "Upload input not found"),
      "error",
    );
    return;
  }

  if (fileInput.files.length === 0) {
    createSnackbar(adminText("js.admin.no_file_selected", "No file selected"));
    return;
  }

  const normalizedType = assetType === "favicon" ? "favicon" : "logo";
  const normalizedTheme = theme === "dark" ? "dark" : "light";
  const file = fileInput.files[0];
  const fileName = (file.name || "").toLowerCase();
  const allowedSvgTypes = [
    "image/svg+xml",
    "text/plain",
    "application/xml",
    "text/xml",
    "application/octet-stream",
    "",
  ];

  if (!fileName.endsWith(".svg") || !allowedSvgTypes.includes(file.type)) {
    createSnackbar(
      adminText(
        "js.admin.upload_valid_svg_file",
        "Please upload a valid SVG file",
      ),
      "error",
    );
    return;
  }

  const formData = new FormData();
  formData.append("assetFile", file);

  try {
    const response = await fetch(
      `/uploadBrandAsset?type=${encodeURIComponent(normalizedType)}&theme=${encodeURIComponent(normalizedTheme)}`,
      {
        method: "POST",
        body: formData,
      },
    );

    const payload = await response.json().catch(async () => ({
      success: response.ok,
      message: await response.text().catch(() => ""),
    }));

    if (!response.ok || payload?.success === false) {
      throw new Error(
        payload?.message ||
          adminText(
            "js.admin.error_uploading_brand_asset",
            "Error uploading brand asset",
          ),
      );
    }

    fileInput.value = "";

    const successMessage =
      payload?.message ||
      adminText(
        "js.admin.brand_asset_updated_successfully",
        "Brand asset updated successfully",
      );

    if (typeof window.queueSnackbarAfterReload === "function") {
      window.queueSnackbarAfterReload(successMessage, null);
    } else {
      createSnackbar(successMessage, "success");
    }

    reloadAdminWithState("customStyling");
  } catch (error) {
    createSnackbar(
      error?.message ||
        adminText(
          "js.admin.error_uploading_brand_asset",
          "Error uploading brand asset",
        ),
      "error",
    );
  }
}

function toggleDropdowns(item) {
  item.classList.toggle("open");
}

function formatBackupDateTime(timestampMs) {
  const timestamp = Number.parseInt(timestampMs, 10);
  if (!Number.isFinite(timestamp) || timestamp <= 0) {
    return "-";
  }

  const date = new Date(timestamp);
  if (Number.isNaN(date.getTime())) {
    return "-";
  }

  return date.toLocaleString();
}

function formatBackupBytes(byteCount) {
  const bytes = Number.parseInt(byteCount, 10);
  if (!Number.isFinite(bytes) || bytes < 0) {
    return "-";
  }

  if (bytes < 1024) {
    return `${bytes} B`;
  }

  const units = ["KB", "MB", "GB"];
  let value = bytes / 1024;
  let unitIndex = 0;
  while (value >= 1024 && unitIndex < units.length - 1) {
    value /= 1024;
    unitIndex += 1;
  }

  return `${value.toFixed(value >= 10 ? 1 : 2)} ${units[unitIndex]}`;
}

function escapeBackupHtml(value) {
  const temp = document.createElement("div");
  temp.textContent = value == null ? "" : String(value);
  return temp.innerHTML;
}

function setBackupStatusNotice(message, type = "neutral") {
  const notice = document.getElementById("backupStatusNotice");
  if (!notice) return;

  notice.textContent = message;
  notice.className = "backupStatusNotice";

  const allowed = ["neutral", "success", "warning", "error"];
  notice.classList.add(
    `backupStatusNotice-${allowed.includes(type) ? type : "neutral"}`,
  );
}

function setAdminBackupBusy(isBusy) {
  const btn = document.getElementById("backupCreateButton");
  if (!btn) return;

  btn.disabled = !!isBusy;
  const icon = btn.querySelector(".material-icons");
  if (icon) icon.textContent = isBusy ? "hourglass_top" : "backup";
}

async function deleteBackupSnapshot(snapshotId) {
  if (
    !(await window.showThemedConfirm(
      adminText(
        "js.admin.confirm_delete_snapshot",
        "Are you sure you want to delete this snapshot? This cannot be undone.",
      ),
    ))
  ) {
    return;
  }

  try {
    const response = await fetch("/backup-delete", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ snapshotId }),
    });
    const result = await response.json();

    if (!response.ok || result.status !== "success") {
      throw new Error(result.message || "Delete failed");
    }

    createSnackbar(adminText("js.admin.snapshot_deleted", "Snapshot deleted."));
    refreshAdminBackupStatus();
  } catch (err) {
    createSnackbar(
      adminText(
        "js.admin.failed_delete_snapshot",
        "Could not delete snapshot.",
      ),
      "error",
    );
  }
}

function downloadBackupSnapshot(snapshotId) {
  window.location.href =
    "/backup-download?id=" + encodeURIComponent(snapshotId);
}

async function refreshAdminBackupStatus() {
  const section = document.getElementById("section-backups");
  if (!section) return;

  try {
    setBackupStatusNotice(
      adminText("admin.loading_backup_status", "Loading backup status..."),
      "neutral",
    );

    const response = await fetch(`/backup-status?ts=${Date.now()}`, {
      method: "GET",
      cache: "no-store",
    });
    const payload = await response.json();

    if (!response.ok || payload?.status !== "success") {
      throw new Error(payload?.message || "Failed to load backup status.");
    }

    const data = payload?.data || {};
    const state = data?.state || {};
    const snapshots = Array.isArray(data?.snapshots) ? data.snapshots : [];
    const logLines = Array.isArray(data?.logLines) ? data.logLines : [];

    // Render snapshots
    const snapshotsList = document.getElementById("backupSnapshotsList");
    if (snapshotsList) {
      if (snapshots.length === 0) {
        snapshotsList.innerHTML = `<p class="backupListEmpty">${escapeBackupHtml(adminText("admin.no_snapshots_found", "No snapshots found."))}</p>`;
      } else {
        snapshotsList.innerHTML = snapshots
          .map((s) => {
            const id = escapeBackupHtml(s?.snapshotId || "-");
            const date = escapeBackupHtml(formatBackupDateTime(s?.createdAt));
            const reason = escapeBackupHtml(s?.reason || "-");
            const by = escapeBackupHtml(s?.requestedBy || "-");
            const dbSize = escapeBackupHtml(formatBackupBytes(s?.databaseSize));
            const rawId = escapeBackupHtml(s?.snapshotId || "");
            const type = s?.type || "full";
            const typeLabel =
              type === "db"
                ? escapeBackupHtml(
                    adminText("admin.backup_type_db", "Database"),
                  )
                : escapeBackupHtml(adminText("admin.backup_type_full", "Full"));
            const typeIcon = type === "db" ? "storage" : "folder_copy";
            const customLine =
              type === "full" && s?.customFiles != null
                ? `<p class="backupSnapshotMeta">Custom: ${escapeBackupHtml(`${s.customFiles} files / ${formatBackupBytes(s.customBytes)}`)}</p>`
                : "";

            return `<div class="backupSnapshotCard">
              <div class="backupSnapshotInfo">
                <p class="backupSnapshotTitle"><i class="material-icons">${typeIcon}</i> <span class="backupTypeBadge backupTypeBadge-${type}">${typeLabel}</span> ${id}</p>
                <p class="backupSnapshotMeta">${date} · ${reason} · ${by}</p>
                <p class="backupSnapshotMeta">DB: ${dbSize}</p>
                ${customLine}
              </div>
              <div class="backupSnapshotActions">
                <button class="submitButton" onclick="downloadBackupSnapshot('${rawId}')"><i class="material-icons">download</i> ${escapeBackupHtml(adminText("admin.download", "Download"))}</button>
                <button class="submitButton backupDeleteButton" onclick="deleteBackupSnapshot('${rawId}')"><i class="material-icons">delete</i> ${escapeBackupHtml(adminText("admin.delete", "Delete"))}</button>
              </div>
            </div>`;
          })
          .join("");
      }
    }

    // Render log
    const logOutput = document.getElementById("backupLogOutput");
    if (logOutput) {
      logOutput.textContent =
        logLines.length > 0
          ? logLines.join("\n")
          : adminText(
              "admin.no_backup_log_entries",
              "No backup log entries yet.",
            );
    }

    // Set status notice
    const latestStatus = String(state?.latestStatus || "unknown");
    if (latestStatus === "success") {
      setBackupStatusNotice(
        adminText(
          "admin.backup_status_success",
          "Backup system healthy. Last backup completed successfully.",
        ),
        "success",
      );
    } else if (latestStatus === "error") {
      setBackupStatusNotice(
        adminText(
          "admin.backup_status_error",
          "Last backup ended with an error. Check the activity log.",
        ),
        "error",
      );
    } else {
      setBackupStatusNotice(
        adminText(
          "admin.backup_status_neutral",
          "No backups yet. Create your first backup above.",
        ),
        "neutral",
      );
    }
  } catch (error) {
    setBackupStatusNotice(
      adminText(
        "admin.failed_to_load_backup_status",
        "Failed to load backup status.",
      ),
      "error",
    );
  }
}

window.refreshAdminBackupStatus = refreshAdminBackupStatus;
window.setAdminBackupBusy = setAdminBackupBusy;

function openAdminSection(id, element) {
  const section = document.getElementById("section-" + id);
  const wasActive = section && section.classList.contains("active");

  // Close ALL sections and deactivate ALL triggers
  document
    .querySelectorAll(".adminSectionOverlay")
    .forEach((el) => el.classList.remove("active"));
  document
    .querySelectorAll(".adminDropdownTrigger")
    .forEach((el) => el.classList.remove("active"));

  // If it wasn't already active, open it
  if (!wasActive && section) {
    section.classList.add("active");
    if (element) {
      element.classList.add("active");
    }

    if (id === "backups") {
      refreshAdminBackupStatus();
    }

    // Smooth scroll to the section start if needed
    // section.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function closeAdminSection(id) {
  const section = document.getElementById("section-" + id);
  if (section) {
    section.classList.remove("active");
  }
  // Also remove active class from any triggers
  document
    .querySelectorAll(".adminDropdownTrigger")
    .forEach((el) => el.classList.remove("active"));
}

function isCurrentUserSuperAdmin() {
  const body = document.body;
  const role =
    body && body.dataset && typeof body.dataset.userRole === "string"
      ? body.dataset.userRole.toLowerCase()
      : "";
  return role === "superadmin";
}

// ── Import Deployment Bundle ──
async function importBundle(input) {
  if (!input || !input.files || input.files.length === 0) return;
  const file = input.files[0];
  if (!file.name.toLowerCase().endsWith(".zip")) {
    createSnackbar(
      adminText("js.admin.invalid_bundle", "Please select a .zip bundle"),
      "error",
    );
    input.value = "";
    return;
  }
  if (!isCurrentUserSuperAdmin()) {
    createSnackbar(
      adminText(
        "js.admin.superadmin_required",
        "Only superadmin can perform this action.",
      ),
      "error",
    );
    input.value = "";
    return;
  }
  if (
    !confirm(
      adminText(
        "js.admin.import_bundle_confirm",
        "Import this bundle? This will replace your current database and custom files. This cannot be undone.",
      ),
    )
  ) {
    input.value = "";
    return;
  }
  const fd = new FormData();
  fd.append("bundle", file);
  try {
    const res = await fetch("/importBundle", { method: "POST", body: fd });
    const data = await res.json();
    if (data.success) {
      createSnackbar(
        adminText("js.admin.import_success", "Bundle imported successfully"),
        "success",
      );
      setTimeout(() => location.reload(), 1200);
    } else {
      createSnackbar(data.message || "Import failed", "error");
    }
  } catch (err) {
    createSnackbar("Import failed: " + err.message, "error");
  }
  input.value = "";
}

// ── Reset Project ──
async function resetProject() {
  if (!isCurrentUserSuperAdmin()) {
    createSnackbar(
      adminText(
        "js.admin.superadmin_required",
        "Only superadmin can perform this action.",
      ),
      "error",
    );
    return;
  }
  if (
    !confirm(
      adminText(
        "js.admin.reset_project_confirm",
        "Reset the project? This will delete the database and reset all colors to defaults. This cannot be undone.",
      ),
    )
  ) {
    return;
  }
  if (
    !confirm(
      adminText(
        "js.admin.reset_project_confirm2",
        "Are you absolutely sure? All data will be permanently deleted.",
      ),
    )
  ) {
    return;
  }
  try {
    const res = await fetch("/resetProject", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ resetDatabase: true, resetColors: true }),
    });
    const data = await res.json();
    if (data.success) {
      createSnackbar(
        adminText(
          "js.admin.reset_success",
          "Project reset. Redirecting to setup...",
        ),
        "success",
      );
      setTimeout(() => (window.location.href = "/setup"), 1500);
    } else {
      createSnackbar(data.message || "Reset failed", "error");
    }
  } catch (err) {
    createSnackbar("Reset failed: " + err.message, "error");
  }
}
