/**
 * One-Time Login Management
 * Simple, direct functionality without dependencies
 */

if (typeof window.showThemedConfirm !== "function") {
  window.showThemedConfirm = function (
    message,
    {
      title = oneTimeText("js.common.confirmation", "Please confirm"),
      confirmText = oneTimeText("js.common.yes", "Yes"),
      cancelText = oneTimeText("js.common.no", "No"),
    } = {},
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

function oneTimeText(path, fallback) {
  if (typeof getUiText === "function") {
    return getUiText(path, fallback);
  }
  return fallback;
}

function oneTimeFormat(template, values = {}) {
  if (typeof formatUiText === "function") {
    return formatUiText(template, values);
  }
  return String(template || "");
}

function notifyOneTimeMessage(message, type = "info") {
  if (typeof window.createSnackbar === "function") {
    window.createSnackbar(message, type);
    return;
  }
  alert(message);
}

function reloadOneTimePage() {
  if (typeof window.reloadPageFast === "function") {
    window.reloadPageFast({ delay: 0 });
    return;
  }
  location.reload();
}

// Create One-Time Login Link
function createOneTimeLogin() {
  const button = document.querySelector('[onclick*="createOneTimeLogin"]');
  if (button) {
    button.disabled = true;
    button.textContent = oneTimeText("js.one_time.creating", "Creating...");
  }

  const formData = new FormData();
  formData.append("action", "create");

  fetch("/createOneTime", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        // Copy to clipboard
        navigator.clipboard.writeText(data.url).catch(() => {});

        notifyOneTimeMessage(
          oneTimeText(
            "js.one_time.link_created_copied",
            "✓ Link created and copied to clipboard",
          ),
          "success",
        );
        reloadOneTimePage();
      } else {
        notifyOneTimeMessage(
          oneTimeFormat(
            oneTimeText("js.one_time.error_with_message", "✗ Error: {message}"),
            {
              message:
                data.message ||
                oneTimeText(
                  "js.one_time.failed_create_link",
                  "Failed to create link",
                ),
            },
          ),
          "error",
        );
      }
    })
    .catch(() => {
      notifyOneTimeMessage(
        oneTimeText(
          "js.one_time.error_creating_link",
          "✗ Error creating link.",
        ),
        "error",
      );
    })
    .finally(() => {
      if (button) {
        button.disabled = false;
        button.textContent = oneTimeText(
          "js.one_time.create_new_login_link",
          "+ Create New Login Link",
        );
      }
    });
}

// Delete One-Time Login Link
async function deleteOneTimeLogin(token) {
  if (
    !(await window.showThemedConfirm(
      oneTimeText(
        "js.one_time.confirm_delete_login_link",
        "Delete this login link?",
      ),
    ))
  ) {
    return;
  }

  const formData = new FormData();
  formData.append("action", "delete");
  formData.append("token", token);

  fetch("/deleteOneTime", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        notifyOneTimeMessage(
          oneTimeText("js.one_time.link_deleted", "✓ Link deleted"),
          "success",
        );
        reloadOneTimePage();
      } else {
        notifyOneTimeMessage(
          oneTimeFormat(
            oneTimeText("js.one_time.error_with_message", "✗ Error: {message}"),
            {
              message:
                data.message ||
                oneTimeText("js.one_time.failed_delete", "Failed to delete"),
            },
          ),
          "error",
        );
      }
    })
    .catch(() => {
      notifyOneTimeMessage(
        oneTimeText(
          "js.one_time.error_deleting_link",
          "✗ Error deleting link.",
        ),
        "error",
      );
    });
}

// Deactivate One-Time Login Link
async function deactivateOneTimeLogin(token) {
  if (
    !(await window.showThemedConfirm(
      oneTimeText(
        "js.one_time.confirm_deactivate_login_link",
        "Deactivate this login link?",
      ),
    ))
  ) {
    return;
  }

  const formData = new FormData();
  formData.append("action", "deactivate");
  formData.append("token", token);

  fetch("/deactivateOneTime", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        notifyOneTimeMessage(
          oneTimeText("js.one_time.link_deactivated", "✓ Link deactivated"),
          "success",
        );
        reloadOneTimePage();
      } else {
        notifyOneTimeMessage(
          oneTimeFormat(
            oneTimeText("js.one_time.error_with_message", "✗ Error: {message}"),
            {
              message:
                data.message ||
                oneTimeText(
                  "js.one_time.failed_deactivate",
                  "Failed to deactivate",
                ),
            },
          ),
          "error",
        );
      }
    })
    .catch(() => {
      notifyOneTimeMessage(
        oneTimeText(
          "js.one_time.error_deactivating_link",
          "✗ Error deactivating link.",
        ),
        "error",
      );
    });
}

// Deactivate All One-Time Login Links
async function deactivateAllOneTimeLogins() {
  if (
    !(await window.showThemedConfirm(
      oneTimeText(
        "js.one_time.confirm_deactivate_all_unused",
        "Deactivate ALL unused login links?",
      ),
    ))
  ) {
    return;
  }

  const formData = new FormData();
  formData.append("action", "deactivateAll");

  fetch("/deactivateAllOneTimes", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        notifyOneTimeMessage(
          oneTimeFormat(
            oneTimeText("js.one_time.success_with_message", "✓ {message}"),
            {
              message:
                data.message ||
                oneTimeText(
                  "js.one_time.links_deactivated",
                  "Links deactivated",
                ),
            },
          ),
          "success",
        );
        reloadOneTimePage();
      } else {
        notifyOneTimeMessage(
          oneTimeFormat(
            oneTimeText("js.one_time.error_with_message", "✗ Error: {message}"),
            {
              message:
                data.message ||
                oneTimeText(
                  "js.one_time.failed_deactivate",
                  "Failed to deactivate",
                ),
            },
          ),
          "error",
        );
      }
    })
    .catch(() => {
      notifyOneTimeMessage(
        oneTimeText("js.one_time.generic_error", "✗ Error."),
        "error",
      );
    });
}
