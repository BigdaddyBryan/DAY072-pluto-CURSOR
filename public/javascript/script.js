let changes = false;
let snackbar = {};
let checked = [];
let multiGroupManager = null;
let lastChecked = null;
let multiPageLocation = null;
let lockedScrollY = 0;
let multiSelectHideTimer = null;
const MULTI_SELECT_HIDE_DELAY_MS = 240;
const modalContentCache = new Map();
let pendingModalLink = null;
const SUPER_ALL_SOFT_WARNING_LIMIT = 1000;
const SNACKBAR_DEFAULT_DURATION_MS = 7500;
const SNACKBAR_UNDO_DURATION_MS = 15000;
let superAllSelection = {
  enabled: false,
  total: 0,
  excludedIds: new Set(),
  filterSnapshot: null,
  signature: "",
};

// Shared debounce utility – available to all scripts loaded after script.js
function _debounce(fn, delay = 150) {
  let timer;
  return function (...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}

function getUiText(path, fallback = "") {
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

function formatUiText(template, values = {}) {
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

// ============================================================================
// FOCUS MANAGEMENT
// ============================================================================

let _modalTriggerElement = null;
let _modalFocusTrap = null;

const FOCUSABLE_SELECTOR = [
  "a[href]",
  "button:not([disabled])",
  'input:not([disabled]):not([type="hidden"])',
  "textarea:not([disabled])",
  "select:not([disabled])",
  '[tabindex]:not([tabindex="-1"])',
].join(", ");

function trapFocus(container) {
  function getVisibleFocusable() {
    return Array.from(container.querySelectorAll(FOCUSABLE_SELECTOR)).filter(
      (el) => el.offsetParent !== null,
    );
  }

  function handler(event) {
    if (event.key !== "Tab") return;

    const focusable = getVisibleFocusable();
    if (focusable.length === 0) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey) {
      if (
        document.activeElement === first ||
        !container.contains(document.activeElement)
      ) {
        event.preventDefault();
        last.focus();
      }
    } else {
      if (
        document.activeElement === last ||
        !container.contains(document.activeElement)
      ) {
        event.preventDefault();
        first.focus();
      }
    }
  }

  container.addEventListener("keydown", handler);
  return {
    release() {
      container.removeEventListener("keydown", handler);
    },
  };
}
window.trapFocus = trapFocus;

function restoreModalFocus() {
  if (_modalFocusTrap) {
    _modalFocusTrap.release();
    _modalFocusTrap = null;
  }

  const target = _modalTriggerElement;
  _modalTriggerElement = null;

  if (target && document.body.contains(target)) {
    requestAnimationFrame(() => {
      try {
        target.focus({ preventScroll: true });
      } catch {
        // Ignore if element is no longer focusable.
      }
    });
  }
}

function escapeModalText(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

if (typeof window.showThemedConfirm !== "function") {
  window.showThemedConfirm = function (
    message,
    {
      title = getUiText("js.common.confirmation", "Please confirm"),
      confirmText = getUiText("js.common.yes", "Yes"),
      cancelText = getUiText("js.common.no", "No"),
    } = {},
  ) {
    return new Promise((resolve) => {
      const restoreFocusTarget =
        document.activeElement instanceof HTMLElement
          ? document.activeElement
          : null;

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
              <h3 class="deleteConfirmTitle">${escapeModalText(title)}</h3>
              <p class="deleteConfirmBody">${escapeModalText(message)}</p>
              <div class="modal-footer deleteConfirmActions confirmationButtons">
                <button type="button" class="deleteBtn confirmSafeBtn">${escapeModalText(cancelText)}</button>
                <button type="button" class="deleteBtn confirmDangerBtn">${escapeModalText(confirmText)}</button>
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
      const confirmTrap = modalContent ? trapFocus(modalContent) : null;
      let closed = false;

      const close = (result) => {
        if (closed) {
          return;
        }

        closed = true;
        if (confirmTrap) confirmTrap.release();
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

if (typeof window.showUnsavedChangesPrompt !== "function") {
  window.showUnsavedChangesPrompt = function ({
    title = getUiText(
      "js.common.unsaved_changes_title",
      "Save changes before closing?",
    ),
    message = getUiText(
      "js.common.unsaved_changes_lost",
      "Any unsaved changes will be lost.",
    ),
    saveText = getUiText("js.common.save", "Save"),
    discardText = getUiText("js.common.dont_save", "Don't save"),
    cancelText = getUiText("js.common.cancel", "Cancel"),
  } = {}) {
    return new Promise((resolve) => {
      const restoreFocusTarget =
        document.activeElement instanceof HTMLElement
          ? document.activeElement
          : null;

      const existing = document.getElementById("confirmationModal");
      if (existing) {
        existing.remove();
      }

      const escapedTitle = escapeModalText(title);

      const html = `
        <div id="confirmationModal" class="confirmationBackground unsavedConfirmationBackground">
          <div class="modal-content unsavedConfirmModal" role="dialog" aria-modal="true" aria-label="${escapedTitle}">
            <div class="modalWindowControls">
              <button type="button" id="unsavedCloseBtn" class="modalWindowControl crossIcon material-icons" aria-label="Close modal">close</button>
            </div>
            <div class="deleteConfirmModal unsavedConfirmContent">
              <div class="unsavedConfirmIcon" aria-hidden="true">
                <i class="material-icons">warning_amber</i>
              </div>
              <h3 class="deleteConfirmTitle unsavedConfirmTitle">${escapedTitle}</h3>
              <p class="deleteConfirmBody unsavedConfirmBody">${escapeModalText(message)}</p>
              <div class="modal-footer deleteConfirmActions unsavedConfirmButtons">
                <button type="button" id="cancelUnsavedBtn" class="deleteBtn confirmProceedBtn unsavedCancelBtn">${escapeModalText(cancelText)}</button>
                <div class="unsavedConfirmActionsGroup">
                  <button type="button" id="discardChangesBtn" class="deleteBtn confirmDangerBtn">${escapeModalText(discardText)}</button>
                  <button type="button" id="saveChangesBtn" class="deleteBtn confirmSafeBtn">${escapeModalText(saveText)}</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      `;

      document.body.insertAdjacentHTML("beforeend", html);

      const modal = document.getElementById("confirmationModal");
      const modalContent = modal?.querySelector(".unsavedConfirmModal");
      const closeButton = document.getElementById("unsavedCloseBtn");
      const cancelButton = document.getElementById("cancelUnsavedBtn");
      const discardButton = document.getElementById("discardChangesBtn");
      const saveButton = document.getElementById("saveChangesBtn");
      const unsavedTrap = modalContent ? trapFocus(modalContent) : null;
      let closed = false;

      const close = (action) => {
        if (closed) {
          return;
        }

        closed = true;
        if (unsavedTrap) unsavedTrap.release();
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

        resolve(action);
      };

      const handleEscape = (event) => {
        if (event.key === "Escape") {
          event.stopImmediatePropagation();
          close("cancel");
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

      closeButton?.addEventListener("click", () => close("cancel"));
      cancelButton?.addEventListener("click", () => close("cancel"));
      discardButton?.addEventListener("click", () => close("discard"));
      saveButton?.addEventListener("click", () => close("save"));
      modalContent?.addEventListener("keydown", handleEnterDefault);
      document.addEventListener("keydown", handleEscape);
    });
  };
}

function getCurrentPageLocation() {
  if (!multiPageLocation) {
    multiPageLocation =
      document.getElementById("page")?.value ||
      document.getElementById("pageLocation")?.value ||
      "";
  }
  return multiPageLocation;
}

function getTotalCountForPage() {
  const page = getCurrentPageLocation();
  if (page === "links") {
    return parseInt(
      document.getElementById("linkCount")?.textContent || "0",
      10,
    );
  }
  if (page === "visitors") {
    return parseInt(
      document.getElementById("visitorCount")?.textContent || "0",
      10,
    );
  }
  if (page === "groups") {
    return parseInt(
      document.getElementById("groupCount")?.textContent || "0",
      10,
    );
  }
  if (page === "users") {
    return parseInt(
      document.getElementById("userCount")?.textContent || "0",
      10,
    );
  }
  return 0;
}

function normalizeSuperAllFilterSnapshot(snapshot) {
  const source = snapshot && typeof snapshot === "object" ? snapshot : {};
  return {
    tags: Array.isArray(source.tags)
      ? source.tags
          .map((value) => String(value || "").trim())
          .filter((value) => value.length > 0)
      : [],
    groups: Array.isArray(source.groups)
      ? source.groups
          .map((value) => String(value || "").trim())
          .filter((value) => value.length > 0)
      : [],
    roles: Array.isArray(source.roles)
      ? source.roles
          .map((value) =>
            String(value || "")
              .trim()
              .toLowerCase(),
          )
          .filter((value) => value.length > 0)
      : [],
    sort: String(source.sort || "latest_modified"),
    limit: Number.parseInt(source.limit || "10", 10) || 10,
    offset: Number.parseInt(source.offset || "0", 10) || 0,
    search: String(source.search || ""),
    searchType: String(source.searchType || "all"),
  };
}

function getCurrentFilterSnapshotForSuperAll() {
  if (typeof window.getMultiSelectFilterSnapshot === "function") {
    return normalizeSuperAllFilterSnapshot(
      window.getMultiSelectFilterSnapshot(),
    );
  }

  return normalizeSuperAllFilterSnapshot({
    tags: [],
    groups: [],
    roles: [],
    sort: "latest_modified",
    limit:
      Number.parseInt(document.getElementById("shownSelect")?.value, 10) || 10,
    offset: 0,
    search: "",
    searchType: "all",
  });
}

function getSuperAllSignature(filterSnapshot) {
  const normalized = normalizeSuperAllFilterSnapshot(filterSnapshot);
  const uniqueSorted = (values) =>
    [...new Set(values)].sort((a, b) => a.localeCompare(b));
  return JSON.stringify({
    tags: uniqueSorted(normalized.tags),
    groups: uniqueSorted(normalized.groups),
    roles: uniqueSorted(normalized.roles),
    search: normalized.search.trim().toLowerCase(),
    searchType: normalized.searchType,
  });
}

function isSuperAllSupportedPage(page = getCurrentPageLocation()) {
  return page === "links" || page === "users";
}

function isSuperAllModeEnabled() {
  return isSuperAllSupportedPage() && superAllSelection.enabled;
}

function isSuperAllLinksModeEnabled() {
  // Backward-compatible alias used across existing selection code.
  return isSuperAllModeEnabled();
}

function getSuperAllSelectedCount() {
  if (!isSuperAllLinksModeEnabled()) {
    return 0;
  }

  return Math.max(
    0,
    Number.parseInt(superAllSelection.total, 10) -
      superAllSelection.excludedIds.size,
  );
}

function resetSuperAllSelectionState() {
  superAllSelection.enabled = false;
  superAllSelection.total = 0;
  superAllSelection.excludedIds = new Set();
  superAllSelection.filterSnapshot = null;
  superAllSelection.signature = "";
  document.body.classList.remove("selection-superall-mode");
}

function enableSuperAllSelectionForLinks() {
  if (!isSuperAllSupportedPage()) {
    toggleSelectionForVisibleItems(true);
    return;
  }

  const totalCount = getTotalCountForPage();
  if (!Number.isFinite(totalCount) || totalCount <= 0) {
    createSnackbar(
      getUiText("js.search.no_items_selected", "No items selected"),
      "error",
    );
    return;
  }

  const filterSnapshot = getCurrentFilterSnapshotForSuperAll();
  superAllSelection.enabled = true;
  superAllSelection.total = totalCount;
  superAllSelection.excludedIds = new Set();
  superAllSelection.filterSnapshot = filterSnapshot;
  superAllSelection.signature = getSuperAllSignature(filterSnapshot);
  document.body.classList.add("selection-superall-mode");

  if (totalCount > SUPER_ALL_SOFT_WARNING_LIMIT) {
    createSnackbar(
      formatUiText(
        getUiText(
          "js.search.superall_large_selection_warning",
          "Large selection: {count} items will be affected.",
        ),
        { count: totalCount },
      ),
      "warning",
    );
  }

  syncVisibleSelection();
  syncSelectionModeClasses();
  updateMultiSelectSummary();
}

window.handleSelectionFilterMutation = function (nextFilterSnapshot) {
  if (!isSuperAllLinksModeEnabled()) {
    return;
  }

  const nextSignature = getSuperAllSignature(nextFilterSnapshot);
  if (nextSignature === superAllSelection.signature) {
    return;
  }

  clearSelectionState();
  createSnackbar(
    getUiText(
      "js.search.superall_cleared_filter_change",
      "All-results selection was cleared after filter changes.",
    ),
  );
};

function isSelectionModeActive() {
  if (isSuperAllLinksModeEnabled()) {
    return getSuperAllSelectedCount() > 0;
  }

  return Array.isArray(checked) && checked.length > 0;
}

function clearSelectionState() {
  checked = [];
  resetSuperAllSelectionState();

  document.querySelectorAll(".checkbox").forEach((checkbox) => {
    checkbox.checked = false;
    checkbox.classList.remove("active");
  });

  document.querySelectorAll(".container").forEach((container) => {
    container.classList.remove("formActive");
  });

  document
    .querySelectorAll(".linkContainer, .userContainer, .visitorContainer")
    .forEach((row) => row.classList.remove("is-selected"));

  document.body.classList.remove("selection-mode");
  document.body.classList.remove("shift-dragging");

  const selectAll = document.getElementById("selectAll");
  if (selectAll) {
    selectAll.checked = false;
  }

  const masterCb = document.getElementById("multiMasterCheckbox");
  if (masterCb) {
    masterCb.checked = false;
    masterCb.classList.remove("indeterminate");
  }

  // Reset icon button active states
  document.querySelectorAll(".multiActionBtn.active").forEach((btn) => {
    btn.classList.remove("active");
  });

  setMasterMenuState(false);
  hideTooltipPortal();

  clearMultiGroupSelection();

  updateMultiSelectSummary();
}

window.isSelectionModeActive = isSelectionModeActive;
window.clearSelectionState = clearSelectionState;

function enterSelectionMode() {
  if (isSelectionModeActive()) {
    clearSelectionState();
    return;
  }
  // Click the first visible checkbox to enter selection mode naturally
  const first = Array.from(
    document.querySelectorAll(".checkbox:not(#selectAll)"),
  ).find((cb) => cb.offsetParent !== null && !cb.disabled);

  if (!first) {
    createSnackbar(
      getUiText("js.common.no_items_selected", "No items selected"),
    );
    return;
  }

  first.click();
}
window.enterSelectionMode = enterSelectionMode;

function syncVisibleSelection() {
  const superAllEnabled = isSuperAllLinksModeEnabled();
  const selectedSet = new Set((checked || []).map((item) => String(item)));
  const excludedSet = superAllSelection.excludedIds;
  const checkboxes = document.querySelectorAll(".checkbox");
  let visibleCount = 0;
  let visibleSelectedCount = 0;
  const nextVisibleSelectedIds = [];

  checkboxes.forEach((checkbox) => {
    if (checkbox.id === "selectAll") {
      return;
    }

    const currentId = checkbox.id.split("-")[1];
    if (!currentId) {
      return;
    }

    const idString = String(currentId);
    const isSelected = superAllEnabled
      ? !excludedSet.has(idString)
      : selectedSet.has(idString);

    checkbox.checked = isSelected;
    checkbox.classList.toggle("active", isSelected);

    const row = checkbox.closest(
      ".linkContainer, .userContainer, .visitorContainer",
    );
    if (row) {
      row.classList.toggle("is-selected", isSelected);
    }

    visibleCount++;
    if (isSelected) {
      visibleSelectedCount++;
      nextVisibleSelectedIds.push(idString);
    }
  });

  if (superAllEnabled) {
    checked = nextVisibleSelectedIds;
  }

  const selectAllCheckbox = document.getElementById("selectAll");
  if (selectAllCheckbox) {
    selectAllCheckbox.checked =
      visibleCount > 0 && visibleSelectedCount === visibleCount;
  }
}

function isMultiActionDropdownOpen() {
  return document
    .getElementById("multiActionDropdown")
    ?.classList.contains("active");
}

function isMasterMenuOpen() {
  return document
    .getElementById("multiMasterMenu")
    ?.classList.contains("active");
}

function syncSelectionPopoverBodyClass() {
  document.body.classList.toggle(
    "selection-popover-open",
    Boolean(isMultiActionDropdownOpen() || isMasterMenuOpen()),
  );
}

function setMasterMenuState(isOpen) {
  const masterMenu = document.getElementById("multiMasterMenu");
  const masterMenuButton = document.getElementById("multiMasterMenuButton");

  if (masterMenu) {
    masterMenu.classList.toggle("active", Boolean(isOpen));
    masterMenu.setAttribute("aria-hidden", isOpen ? "false" : "true");
  }

  if (masterMenuButton) {
    masterMenuButton.classList.toggle("active", Boolean(isOpen));
    masterMenuButton.setAttribute("aria-expanded", isOpen ? "true" : "false");
  }

  if (!isOpen) {
    hideTooltipPortal();
  }

  syncSelectionPopoverBodyClass();
}

function closeSelectionPopovers() {
  setMasterMenuState(false);
  closeMultiActionDropdown();
  hideTooltipPortal();
}

window.closeSelectionPopovers = closeSelectionPopovers;

function showMultiSelectBar(multiSelectBar, selectionModeHeader) {
  if (multiSelectHideTimer) {
    clearTimeout(multiSelectHideTimer);
    multiSelectHideTimer = null;
  }

  multiSelectBar?.classList.remove("closing");
  selectionModeHeader?.classList.remove("closing");
  multiSelectBar?.classList.add("active");
  selectionModeHeader?.classList.add("active");
}

function hideMultiSelectBar(multiSelectBar, selectionModeHeader) {
  if (!multiSelectBar && !selectionModeHeader) {
    return;
  }

  if (multiSelectHideTimer) {
    clearTimeout(multiSelectHideTimer);
    multiSelectHideTimer = null;
  }

  const wasVisible = Boolean(
    multiSelectBar?.classList.contains("active") ||
    selectionModeHeader?.classList.contains("active"),
  );

  if (!wasVisible) {
    multiSelectBar?.classList.remove("active", "closing");
    selectionModeHeader?.classList.remove("active", "closing");
    clearMultiSelectFloatingPosition();
    return;
  }

  multiSelectBar?.classList.add("closing");
  selectionModeHeader?.classList.add("closing");

  multiSelectHideTimer = setTimeout(() => {
    multiSelectBar?.classList.remove("active", "closing");
    selectionModeHeader?.classList.remove("active", "closing");
    multiSelectHideTimer = null;
    clearMultiSelectFloatingPosition();
  }, MULTI_SELECT_HIDE_DELAY_MS);
}

function updateMultiSelectSummary() {
  const totalSelectedEl = document.getElementById("totalSelected");
  const selectAllTextEl = document.getElementById("selectAllText");
  const selectionModeCount = document.getElementById("selectionModeCount");
  const selectionModeHeader = document.getElementById("selectionModeHeader");
  const multiSelectBar = document.getElementById("multiSelect");
  const selectAllContainers = document.querySelectorAll(".selectAll");
  const masterCb = document.getElementById("multiMasterCheckbox");
  const page = getCurrentPageLocation();
  const superAllEnabled = isSuperAllLinksModeEnabled();
  const superAllSelectedCount = getSuperAllSelectedCount();

  if (!totalSelectedEl || !selectAllTextEl || !Array.isArray(checked)) {
    return;
  }

  // Update master checkbox state
  if (masterCb) {
    const visibleIds = Array.from(document.querySelectorAll(".checkbox"))
      .filter((checkbox) => checkbox.id !== "selectAll")
      .map((checkbox) => String(checkbox.id.split("-")[1] || ""))
      .filter((id) => id.length > 0);

    const selectedSet = new Set((checked || []).map((item) => String(item)));
    const visibleSelectedCount = visibleIds.filter((id) =>
      superAllEnabled
        ? !superAllSelection.excludedIds.has(id)
        : selectedSet.has(id),
    ).length;
    const allVisibleSelected =
      visibleIds.length > 0 && visibleSelectedCount === visibleIds.length;
    const hasAnySelection = superAllEnabled
      ? superAllSelectedCount > 0
      : checked.length > 0;

    if (!hasAnySelection) {
      masterCb.checked = false;
      masterCb.classList.remove("indeterminate");
    } else if (allVisibleSelected) {
      masterCb.checked = true;
      masterCb.classList.remove("indeterminate");
    } else {
      masterCb.checked = false;
      masterCb.classList.add("indeterminate");
    }
  }

  if (!isSelectionModeActive()) {
    totalSelectedEl.innerHTML = "";
    selectAllTextEl.innerHTML = getUiText("js.search.select_all", "Select all");
    if (selectionModeCount) {
      selectionModeCount.innerHTML = `0 ${getUiText("js.search.selected", "selected")}`;
    }
    selectAllContainers.forEach((container) =>
      container.classList.remove("active"),
    );
    hideMultiSelectBar(multiSelectBar, selectionModeHeader);

    setMasterMenuState(false);

    if (page === "links") {
      selectAllContainers.forEach((container) => {
        container.style.display = "none";
      });
    }

    scheduleMultiSelectFloatingPositionUpdate();
    return;
  }

  const limit = parseInt(
    document.getElementById("shownSelect")?.value || "10",
    10,
  );
  const selectedCount = superAllEnabled
    ? superAllSelectedCount
    : checked.length;
  const totalCount = getTotalCountForPage();

  if (superAllEnabled) {
    const excludedCount = superAllSelection.excludedIds.size;
    const allResultsText = getUiText("js.search.all_results", "all results");
    const excludedText =
      excludedCount > 0
        ? `, ${excludedCount} ${getUiText("js.search.excluded", "excluded")}`
        : "";

    totalSelectedEl.innerHTML = `${selectedCount} ${getUiText("js.search.selected", "selected")} (${allResultsText}${excludedText})`;
    selectAllTextEl.innerHTML = getUiText(
      "js.search.all_results_selected",
      "All results selected",
    );
  } else {
    const pagesCovered = Math.max(
      1,
      Math.ceil(selectedCount / Math.max(1, limit)),
    );
    totalSelectedEl.innerHTML =
      pagesCovered > 1
        ? `${selectedCount} ${getUiText("js.search.selected", "selected")} (${pagesCovered} ${getUiText("js.search.pages", "pages")})`
        : `${selectedCount} ${getUiText("js.search.selected", "selected")}`;
    selectAllTextEl.innerHTML =
      selectedCount >= Math.max(1, totalCount)
        ? getUiText("js.search.deselect_all", "Deselect all")
        : getUiText("js.search.select_all", "Select all");
  }

  if (selectionModeCount) {
    const newCountText = `${selectedCount} ${getUiText("js.search.selected", "selected")}`;
    if (selectionModeCount.innerHTML !== newCountText) {
      selectionModeCount.innerHTML = newCountText;
      selectionModeCount.classList.remove("count-bump");
      void selectionModeCount.offsetWidth;
      selectionModeCount.classList.add("count-bump");
      selectionModeCount.addEventListener(
        "animationend",
        () => selectionModeCount.classList.remove("count-bump"),
        { once: true },
      );
    }
  }

  selectAllContainers.forEach((container) => container.classList.add("active"));

  showMultiSelectBar(multiSelectBar, selectionModeHeader);

  if (page === "links") {
    selectAllContainers.forEach((container) => {
      container.style.display = "";
    });
  }

  scheduleMultiSelectFloatingPositionUpdate();
}

window.__syncMultiSelectUI = function () {
  syncVisibleSelection();
  updateMultiSelectSummary();
};

async function logoutUserAllDevices(userId) {
  const parsedUserId = parseInt(userId, 10);
  if (!Number.isFinite(parsedUserId) || parsedUserId <= 0) {
    createSnackbar(
      getUiText("js.common.invalid_user_id", "Invalid user id"),
      "error",
    );
    return;
  }

  let confirmed = true;
  if (typeof window.showThemedConfirm === "function") {
    confirmed = await window.showThemedConfirm(
      getUiText(
        "js.common.confirm_logout_all_devices",
        "Sign out this user everywhere?",
      ),
    );
  } else {
    confirmed = window.confirm(
      getUiText(
        "js.common.confirm_logout_all_devices",
        "Sign out this user everywhere?",
      ),
    );
  }

  if (!confirmed) {
    return;
  }

  try {
    const response = await fetch("/revokeAllUserDeviceSessions", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ user_id: parsedUserId }),
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.success !== true) {
      throw new Error(payload.message || "Request failed");
    }

    const count = Number.isFinite(payload.count)
      ? payload.count
      : parseInt(payload.count || "0", 10) || 0;

    createSnackbar(
      count > 0
        ? formatUiText(
            getUiText(
              "js.common.logged_out_device_sessions",
              "Signed out {count} device session(s)",
            ),
            { count },
          )
        : getUiText(
            "js.common.no_active_device_sessions",
            "No active device sessions to sign out",
          ),
    );

    if (payload.logout) {
      window.location.href = "/home";
    }
  } catch (error) {
    createSnackbar(
      getUiText(
        "js.common.could_not_logout_devices",
        "Could not sign out devices",
      ),
      "error",
    );
  }
}

function lockBodyScroll() {
  lockedScrollY = window.scrollY || window.pageYOffset || 0;
  document.body.style.position = "fixed";
  document.body.style.top = `-${lockedScrollY}px`;
  document.body.style.left = "0";
  document.body.style.right = "0";
  document.body.style.width = "100%";
  document.body.style.overflow = "hidden";
}

function unlockBodyScroll() {
  const scrollY = Math.abs(parseInt(document.body.style.top || "0", 10)) || 0;
  document.body.style.position = "";
  document.body.style.top = "";
  document.body.style.left = "";
  document.body.style.right = "";
  document.body.style.width = "";
  document.body.style.overflow = "";
  window.scrollTo(0, scrollY);
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

async function getFileContents(filePath) {
  try {
    const response = await fetch(filePath);
    return await response.json();
  } catch (error) {
    throw error;
  }
}

function getUiLanguageCode() {
  const source =
    (window.i18n && typeof window.i18n.getLang === "function"
      ? window.i18n.getLang()
      : window.__I18N_LANG || document.documentElement.lang || "en") || "en";
  const normalized = String(source).trim().toLowerCase();
  if (normalized.startsWith("nl")) {
    return "nl";
  }
  return "en";
}

async function loadSnackbarCatalog(locale = null) {
  const language =
    locale && String(locale).trim() !== ""
      ? String(locale).trim().toLowerCase().slice(0, 2)
      : getUiLanguageCode();

  try {
    snackbar = await getFileContents(
      `../custom/json/snackbar.${language}.json`,
    );
    if (!snackbar || typeof snackbar !== "object") {
      throw new Error("Invalid snackbar catalog");
    }
    return;
  } catch (error) {
    try {
      snackbar = await getFileContents("../custom/json/snackbar.json");
      if (!snackbar || typeof snackbar !== "object") {
        snackbar = {};
      }
    } catch {
      snackbar = {};
    }
  }
}

function createSnackbar(message, options = null) {
  let undo = null;
  let type = "";
  let duration = SNACKBAR_DEFAULT_DURATION_MS;

  const getSnackbarStack = () => {
    let stack = document.getElementById("snackbarStack");
    if (!stack) {
      stack = document.createElement("div");
      stack.id = "snackbarStack";
      stack.className = "snackbarStack";
      document.body.appendChild(stack);
    }
    return stack;
  };

  if (typeof options === "string") {
    const normalized = options.trim().toLowerCase();
    if (["error", "success", "warning", "info"].includes(normalized)) {
      type = normalized;
    } else if (options.trim().length > 0) {
      undo = options;
    }
  } else if (typeof options === "boolean") {
    type = options ? "error" : "info";
  } else if (options && typeof options === "object") {
    if (typeof options.undo === "string" && options.undo.trim().length > 0) {
      undo = options.undo;
    }
    if (typeof options.type === "string") {
      const normalizedType = options.type.trim().toLowerCase();
      if (["error", "success", "warning", "info"].includes(normalizedType)) {
        type = normalizedType;
      }
    }
    if (Number.isFinite(options.duration) && options.duration > 0) {
      duration = options.duration;
    }
  }

  if (undo) {
    duration = Math.max(duration, SNACKBAR_UNDO_DURATION_MS);
  }

  const snackbarEl = document.createElement("div");
  snackbarEl.classList.add("snackbar");
  if (type) {
    snackbarEl.classList.add(`snackbar-${type}`);
  }
  snackbarEl.setAttribute("role", "status");
  snackbarEl.setAttribute(
    "aria-live",
    type === "error" ? "assertive" : "polite",
  );

  const messageElement = document.createElement("span");
  messageElement.classList.add("snackbarMessage");
  messageElement.textContent = String(message ?? "");
  snackbarEl.appendChild(messageElement);

  if (undo) {
    const undoPath = String(undo).trim();
    let undoHref = "";
    if (undoPath.startsWith("/restore")) {
      undoHref = undoPath;
    } else if (undoPath.startsWith("?")) {
      undoHref = "/restore" + undoPath;
    } else if (undoPath.startsWith("/")) {
      undoHref = "/restore" + undoPath;
    } else {
      undoHref = "/restore?" + undoPath;
    }

    const a = document.createElement("a");
    a.classList.add("undoAction");
    a.innerHTML = getUiText("js.common.undo", "Undo");
    a.href = undoHref;
    snackbarEl.appendChild(a);
    snackbarEl.classList.add("undo");
  }

  const closeIcon = document.createElement("i");
  closeIcon.classList.add("material-icons", "closeSnackbar");
  closeIcon.innerHTML = "close";
  closeIcon.setAttribute("role", "button");
  closeIcon.setAttribute("tabindex", "0");

  const closeSnackbar = () => {
    snackbarEl.classList.add("hide");
    setTimeout(() => {
      snackbarEl.remove();
      const stack = document.getElementById("snackbarStack");
      if (stack && stack.children.length === 0) {
        stack.remove();
      }
    }, 450);
  };

  closeIcon.addEventListener("click", closeSnackbar);
  closeIcon.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      closeSnackbar();
    }
  });

  snackbarEl.appendChild(closeIcon);
  const stack = getSnackbarStack();
  const incomingHasUndo = snackbarEl.classList.contains("undo");
  while (stack.children.length >= 3) {
    const candidates = Array.from(stack.children);
    const removable = incomingHasUndo
      ? candidates.find((child) => !child.classList.contains("undo"))
      : candidates.find((child) => !child.classList.contains("undo")) ||
        candidates[0];

    (removable || stack.firstElementChild)?.remove();
  }
  stack.appendChild(snackbarEl);

  let remaining = duration;
  let dismissStartedAt = Date.now();
  let dismissTimer = null;

  const startDismissTimer = () => {
    dismissStartedAt = Date.now();
    dismissTimer = setTimeout(closeSnackbar, Math.max(200, remaining));
  };

  const pauseDismissTimer = () => {
    if (!dismissTimer) return;
    clearTimeout(dismissTimer);
    dismissTimer = null;
    remaining -= Date.now() - dismissStartedAt;
  };

  const resumeDismissTimer = () => {
    if (dismissTimer || remaining <= 0) return;
    startDismissTimer();
  };

  snackbarEl.addEventListener("mouseenter", pauseDismissTimer);
  snackbarEl.addEventListener("mouseleave", resumeDismissTimer);
  snackbarEl.addEventListener("focusin", pauseDismissTimer);
  snackbarEl.addEventListener("focusout", resumeDismissTimer);

  startDismissTimer();
}

function getMultiEntityLabel(page) {
  const labels = {
    links: "links",
    groups: "groups",
    users: "users",
    visitors: "visitors",
  };
  return labels[page] || "items";
}

function applyMultiEntityLabel(template, page) {
  const entityLabel = getMultiEntityLabel(page);
  return String(template || "").replace(/links/gi, entityLabel);
}

function queueSnackbarAfterReload(message, undo = null, duration = null) {
  const payload = { message, undo };
  if (Number.isFinite(duration) && duration > 0) {
    payload.duration = duration;
  }
  sessionStorage.setItem("postReloadSnackbar", JSON.stringify(payload));
}
window.queueSnackbarAfterReload = queueSnackbarAfterReload;

function reloadPageFast({ delay = 0, preserveScroll = false } = {}) {
  if (window.__reloadPending === true) {
    return;
  }
  window.__reloadPending = true;

  if (preserveScroll) {
    try {
      sessionStorage.setItem(
        "postReloadScrollY",
        String(
          Math.max(0, Math.floor(window.scrollY || window.pageYOffset || 0)),
        ),
      );
    } catch {}
  }

  // Keep current background during navigation to avoid light flashes.
  try {
    const computed = window.getComputedStyle(document.body);
    const currentBg = computed && computed.backgroundColor;
    if (currentBg) {
      document.documentElement.style.backgroundColor = currentBg;
      document.body.style.backgroundColor = currentBg;
    }
  } catch {}

  document.body.classList.add("app-reloading");

  const runReload = () => window.location.reload();
  if (delay > 0) {
    window.setTimeout(runReload, delay);
    return;
  }

  window.requestAnimationFrame(runReload);
}
window.reloadPageFast = reloadPageFast;

function refreshCurrentPage(delay = 0) {
  reloadPageFast({ delay: Math.max(0, Number(delay) || 0) });
}

function bindPostMutationRefresh() {
  const forms = document.querySelectorAll(
    '#modalContainer form[method="POST"]',
  );
  forms.forEach((form) => {
    if (
      form.id === "loginForm" ||
      form.dataset.skipRefreshAfterMutation === "true"
    ) {
      return;
    }

    if (form.dataset.refreshBound === "true") {
      return;
    }

    form.dataset.refreshBound = "true";
    form.addEventListener("submit", () => {
      sessionStorage.setItem("refreshAfterMutation", "1");
    });
  });
}

function closeAllSelect(elmnt) {
  const selectItems = document.getElementsByClassName("select-items");
  const selectSelected = document.getElementsByClassName("select-selected");

  for (let i = 0; i < selectSelected.length; i++) {
    if (elmnt !== selectSelected[i]) {
      selectSelected[i].classList.remove("select-arrow-active");
    }
  }

  for (let j = 0; j < selectItems.length; j++) {
    if (
      elmnt !==
      selectItems[j].parentNode.getElementsByClassName("select-selected")[0]
    ) {
      selectItems[j].classList.add("select-hide");
    }
  }
}

// ============================================================================
// CUSTOM SELECT INITIALIZATION
// ============================================================================

function initializeCustomSelect(customSelect, onSelectCallback = null) {
  customSelect
    .querySelectorAll(".select-selected, .select-items")
    .forEach((node) => node.remove());

  const select = customSelect.getElementsByTagName("select")[0];
  if (!select) {
    return;
  }

  const options = select.options;
  const selectedOption = options[select.selectedIndex];

  const selectedItem = document.createElement("DIV");
  selectedItem.setAttribute("class", "select-selected");
  selectedItem.innerHTML = selectedOption.innerHTML;
  customSelect.appendChild(selectedItem);

  const optionList = document.createElement("DIV");
  optionList.setAttribute("class", "select-items select-hide");

  for (let j = 0; j < options.length; j++) {
    const option = options[j];
    const optionItem = document.createElement("DIV");
    optionItem.classList.add("select-options");
    optionItem.innerHTML = option.innerHTML;

    if (j === select.selectedIndex) {
      optionItem.classList.add("same-as-selected");
    }

    optionItem.addEventListener("click", function () {
      const select =
        this.parentNode.parentNode.getElementsByTagName("select")[0];
      const selectedText = this.innerHTML;

      for (let k = 0; k < select.length; k++) {
        if (select.options[k].innerHTML === selectedText) {
          select.selectedIndex = k;
          selectedItem.innerHTML = selectedText;

          const sameAsSelected =
            this.parentNode.getElementsByClassName("same-as-selected");
          for (let m = 0; m < sameAsSelected.length; m++) {
            sameAsSelected[m].classList.remove("same-as-selected");
          }

          this.classList.add("same-as-selected");
          break;
        }
      }

      select.dispatchEvent(new Event("change", { bubbles: true }));

      if (onSelectCallback) {
        onSelectCallback();
      }

      selectedItem.click();
    });

    optionList.appendChild(optionItem);
  }

  customSelect.appendChild(optionList);

  selectedItem.addEventListener("click", function (e) {
    e.stopPropagation();
    closeAllSelect(this);
    this.nextSibling.classList.toggle("select-hide");
    this.classList.toggle("select-arrow-active");
  });
}

function initializeAllCustomSelects() {
  const customSelects = document.getElementsByClassName("custom-select");

  for (let i = 0; i < customSelects.length; i++) {
    const isMultiActionSelector =
      !!customSelects[i].querySelector("#multiSelector");
    const onSelect = isMultiActionSelector
      ? () => {
          const filteredMulti = document.getElementById("multiTagGroupList");
          if (filteredMulti) {
            filteredMulti.innerHTML = "";
            filteredMulti.style.display = "none";
          }
          const filteredGroups = document.getElementById("multiGroupList");
          if (filteredGroups) {
            filteredGroups.innerHTML = "";
            filteredGroups.classList.remove("active");
          }
          toggleMultiInputVisibility();
        }
      : null;

    initializeCustomSelect(customSelects[i], onSelect);
  }
}

// ============================================================================
// TAG/GROUP MANAGEMENT UTILITIES
// ============================================================================

class TagGroupManager {
  constructor(type, config) {
    this.type = type; // 'tag' or 'group'
    this.config = config;
    this.array = [];
    this.currentIndex = -1;
    this.items = [];
    this._debounceTimer = null;

    this.input = document.getElementById(config.inputId);
    this.container = document.getElementById(config.containerId);
    this.hiddenInput = document.getElementById(config.hiddenInputId);
    this.list = document.getElementById(config.listId);

    this.boundBackspaceHandler = this.handleBackspace.bind(this);
    this.boundInputHandler = this.handleInput.bind(this);
    this.boundEnterHandler = this.handleEnterKey.bind(this);
    this.boundOutsideClickHandler = this.handleOutsideClick.bind(this);
    this.boundEscapeCloseHandler = this.handleEscapeClose.bind(this);
    this.boundRepositionHandler = this.positionList.bind(this);

    this.setupEventListeners();
  }

  setupEventListeners() {
    this.input.addEventListener("focusout", () => {
      this.input.removeEventListener("keydown", this.boundBackspaceHandler);
    });

    this.input.addEventListener("focus", () => {
      this.input.addEventListener("keydown", this.boundBackspaceHandler);
      this.positionList();
      this.fetchSuggestions("");
    });

    this.input.addEventListener("click", () => {
      this.positionList();
      this.fetchSuggestions(this.input.value.trim());
    });

    this.input.addEventListener("input", this.boundInputHandler);
    this.input.addEventListener("keydown", this.boundEnterHandler);

    document.body.addEventListener("click", this.boundOutsideClickHandler);
    document.body.addEventListener("keydown", this.boundEscapeCloseHandler);
  }

  normalizeItemTitle(value) {
    return String(value || "")
      .replace(/\s+/g, " ")
      .trim();
  }

  hasItem(title) {
    const normalizedTitle = this.normalizeItemTitle(title).toLowerCase();
    if (!normalizedTitle) {
      return false;
    }

    return this.array.some(
      (item) => this.normalizeItemTitle(item).toLowerCase() === normalizedTitle,
    );
  }

  positionList() {
    if (!this.list) {
      return;
    }

    const useOverlayDropdown =
      this.config?.overlayList === true ||
      this.list.dataset.overlayDropdown === "true" ||
      this.list.closest(".linkGroupListAnchor") !== null;

    this.list.style.position = useOverlayDropdown ? "absolute" : "relative";
    this.list.style.left = useOverlayDropdown ? "0" : "auto";
    this.list.style.top = useOverlayDropdown ? "calc(100% + 4px)" : "auto";
    this.list.style.width = "100%";
    this.list.style.maxWidth = "100%";
    this.list.style.maxHeight = "200px";
    this.list.style.zIndex = useOverlayDropdown ? "9999" : "";
  }

  handleOutsideClick(event) {
    const target = event.target;
    const clickedInput = target === this.input || this.input.contains(target);
    const clickedList = target === this.list || this.list.contains(target);
    const clickedContainer =
      target === this.container || this.container.contains(target);

    if (clickedInput || clickedList || clickedContainer) {
      return;
    }

    this.clearList();
  }

  handleEscapeClose(event) {
    if (event.key === "Escape") {
      this.clearList();
    }
  }

  handleBackspace(event) {
    if (event.key === "Backspace" && this.input.value.length === 0) {
      const containers = document.querySelectorAll(
        `.${this.config.containerClass}`,
      );
      if (containers.length > 0) {
        const lastContainer = containers[0];
        const name = this.normalizeItemTitle(
          lastContainer.querySelector("p")?.textContent || "",
        );
        const index = this.array.findIndex(
          (item) =>
            this.normalizeItemTitle(item).toLowerCase() === name.toLowerCase(),
        );

        if (index > -1) {
          this.array.splice(index, 1);
        }

        lastContainer.remove();
        this.updateHiddenInput();
      }
    }
  }

  handleInput(event) {
    event.preventDefault();
    const query = this.input.value.trim();

    clearTimeout(this._debounceTimer);
    this._debounceTimer = setTimeout(() => {
      this.fetchSuggestions(query);
    }, 200);
  }

  async fetchSuggestions(query = "") {
    const normalizedQuery = typeof query === "string" ? query : "";

    try {
      const response = await fetch(this.config.fetchEndpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ query: normalizedQuery }),
      });

      const data = await response.text();
      this.handleResponse(data, normalizedQuery);
    } catch (err) {
      if (this.type === "group" && normalizedQuery.trim().length > 0) {
        this.renderList([], normalizedQuery);
        return;
      }

      this.clearList();
    }
  }

  handleResponse(data, query = "") {
    this.list.innerHTML = "";

    let items = [];
    if (data && data.trim()) {
      try {
        items = JSON.parse(data);
      } catch {
        this.clearList();
        return;
      }
    }

    if (!Array.isArray(items)) {
      this.clearList();
      return;
    }

    this.renderList(items, query);
    this.enableKeyboardNavigation();
  }

  renderList(items, query = "") {
    if (!Array.isArray(items)) {
      this.clearList();
      return;
    }

    const normalizedQuery = this.normalizeItemTitle(query);
    const normalizedItems = items
      .map((item) => {
        const title = this.normalizeItemTitle(item?.title);
        if (!title) {
          return null;
        }

        return { ...item, title };
      })
      .filter((item) => !!item)
      .filter(
        (item, index, array) =>
          array.findIndex(
            (candidate) =>
              candidate.title.toLowerCase() === item.title.toLowerCase(),
          ) === index,
      );

    const isGroupType = this.type === "group";

    if (normalizedItems.length === 0 && !isGroupType) {
      this.clearList();
      return;
    }

    normalizedItems.forEach((item) => {
      const div = document.createElement("div");
      div.className = `${this.type}Item`;
      div.id = `${this.type}Item-${item.title}`;
      div.textContent = item.title;
      div.setAttribute("tabindex", "0");

      div.addEventListener("click", (event) => {
        if (this.hasItem(item.title)) return;

        event.stopPropagation();
        this.addItem(item.title);
      });

      this.list.appendChild(div);
    });

    if (this.type === "group") {
      const existingTitles = new Set(
        normalizedItems.map((item) => item.title.toLowerCase()),
      );

      const createActionRow = document.createElement("div");
      createActionRow.className = "groupCreateActionRow";

      const createActionButton = document.createElement("button");
      createActionButton.type = "button";
      createActionButton.className = "groupCreateActionBtn";

      const plusIcon = document.createElement("span");
      plusIcon.className = "material-icons groupCreateIcon";
      plusIcon.textContent = "add";

      const btnText = document.createElement("span");

      const canCreate =
        normalizedQuery.length > 0 &&
        !this.hasItem(normalizedQuery) &&
        !existingTitles.has(normalizedQuery.toLowerCase());

      if (normalizedQuery.length === 0) {
        btnText.textContent = getUiText(
          "js.groups.type_to_create_group",
          "Type a name to create a new group",
        );
        createActionButton.disabled = true;
        createActionButton.classList.add("placeholder");
      } else if (!canCreate) {
        btnText.textContent = getUiText(
          "js.groups.group_already_exists",
          "Group already exists",
        );
        createActionButton.disabled = true;
        createActionButton.classList.add("exists");
      } else {
        btnText.textContent = formatUiText(
          getUiText("js.groups.create_group_action", 'Create group "{name}"'),
          { name: normalizedQuery },
        );
        createActionButton.disabled = false;
        createActionButton.classList.add("can-create");
      }

      createActionButton.appendChild(plusIcon);
      createActionButton.appendChild(btnText);

      createActionButton.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (!canCreate) {
          return;
        }

        this.addItem(normalizedQuery);
      });

      createActionRow.appendChild(createActionButton);
      this.list.appendChild(createActionRow);
    }

    this.positionList();
    this.list.classList.add("active");
    this.items = document.querySelectorAll(`.${this.type}Item`);
    this.currentIndex = -1;
  }

  addItem(title) {
    const normalizedTitle = this.normalizeItemTitle(title);
    if (!normalizedTitle || this.hasItem(normalizedTitle)) return;

    this.array.push(normalizedTitle);

    const div = document.createElement("div");
    div.classList.add(this.config.containerClass);

    const p = document.createElement("p");
    p.textContent = normalizedTitle;

    const cross = document.createElement("span");
    cross.classList.add("material-icons", "closeTag");
    cross.textContent = "close";

    div.appendChild(p);
    div.appendChild(cross);
    this.container.insertBefore(div, this.container.firstChild);

    if (this.config?.horizontalScroll === true) {
      requestAnimationFrame(() => {
        this.container.scrollLeft = this.container.scrollWidth;
      });
    }

    this.input.value = "";
    this.clearList();
    this.updateHiddenInput();
    this.attachRemoveHandlers();
  }

  attachRemoveHandlers() {
    const containers = document.querySelectorAll(
      `.${this.config.containerClass}`,
    );
    const boundHandler =
      this._boundRemoveClick ||
      (this._boundRemoveClick = this.handleRemoveClick.bind(this));
    containers.forEach((container) => {
      container.removeEventListener("click", boundHandler);
      container.addEventListener("click", boundHandler);
    });
  }

  handleRemoveClick(event) {
    const container = event.currentTarget;
    const name = this.normalizeItemTitle(
      container.querySelector("p")?.textContent || "",
    );
    const index = this.array.findIndex(
      (item) =>
        this.normalizeItemTitle(item).toLowerCase() === name.toLowerCase(),
    );

    if (index > -1) {
      this.array.splice(index, 1);
    }

    container.remove();
    this.updateHiddenInput();
  }

  handleEnterKey(event) {
    if (event.key !== "Enter") return;

    const isFocused =
      document.activeElement === this.input || this.input.value.length > 0;

    if (!isFocused) return;

    event.preventDefault();

    if (this.hasItem(this.input.value)) return;
    if (document.querySelector(`.${this.type}Item:focus`)) return;

    if (this.input.value.length > 0) {
      this.addItem(this.input.value);
    }
  }

  enableKeyboardNavigation() {
    const handler = (event) => {
      if (event.key === "ArrowDown") {
        event.preventDefault();
        if (this.currentIndex < this.items.length - 1) {
          this.currentIndex++;
          this.items[this.currentIndex].setAttribute("tabindex", "-1");
          this.items[this.currentIndex].focus();
        }
      } else if (event.key === "ArrowUp") {
        event.preventDefault();
        if (this.currentIndex > 0) {
          this.currentIndex--;
          this.items[this.currentIndex].focus();
        } else if (this.currentIndex === 0) {
          this.currentIndex--;
          this.input.focus();
        }
      } else if (event.key === "Enter" && this.currentIndex >= 0) {
        this.items[this.currentIndex].click();
        this.input.focus();
        document.body.removeEventListener("keydown", handler);
      }
    };

    document.body.removeEventListener("keydown", handler);
    document.body.addEventListener("keydown", handler);
  }

  updateHiddenInput() {
    this.hiddenInput.value = JSON.stringify(this.array);
  }

  clearList() {
    this.list.innerHTML = "";
    this.list.classList.remove("active");
  }

  loadPresetItems(selector) {
    const presetElements = document.querySelectorAll(selector);
    presetElements.forEach((el) => {
      const presetTitle = this.normalizeItemTitle(el.textContent || "");
      if (presetTitle && !this.hasItem(presetTitle)) {
        this.array.push(presetTitle);
      }
    });
    this.updateHiddenInput();
    this.attachRemoveHandlers();
  }

  finalizeOnSubmit() {
    const pendingValue = this.normalizeItemTitle(this.input.value);
    if (pendingValue.length > 0 && !this.hasItem(pendingValue)) {
      this.array.push(pendingValue);
    }
    this.updateHiddenInput();
  }
}

// ============================================================================
// VALIDATION UTILITIES
// ============================================================================

async function validateField(fieldId, endpoint, warningId, data) {
  const input = document.getElementById(fieldId);
  const warning = document.getElementById(warningId);

  if (!input || !warning) return;

  input.addEventListener("input", async function () {
    if (input.value.length === 0) {
      warning.classList.remove("active");
      return;
    }

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data(input.value)),
      });

      const result = await response.text();
      warning.classList.toggle("active", result !== "false");
    } catch (err) {
      warning.classList.remove("active");
    }
  });
}

function parseShortlinkConflictResponse(rawResponse) {
  const trimmed = String(rawResponse || "").trim();
  if (!trimmed || trimmed === "false" || trimmed === "null") {
    return { hasConflict: false, conflictType: null };
  }

  try {
    const parsed = JSON.parse(trimmed);
    if (!parsed || parsed === false) {
      return { hasConflict: false, conflictType: null };
    }

    const rawType =
      typeof parsed.conflict_type === "string" ? parsed.conflict_type : "";
    const conflictType = rawType === "alias" ? "alias" : "primary";

    return { hasConflict: true, conflictType };
  } catch {
    return { hasConflict: true, conflictType: "primary" };
  }
}

function setWarningMessage(warningElement, message) {
  if (!warningElement) {
    return;
  }

  const messageElement =
    warningElement.querySelector("#warningMessage") ||
    warningElement.querySelector("[data-default-message]");
  if (messageElement) {
    messageElement.textContent = message;
  }
}

function setupShortlinkConflictValidation(options) {
  const {
    fieldId,
    warningId,
    endpoint,
    includeCurrentLinkId,
    allowAliasOverwrite,
  } = options;

  const input = document.getElementById(fieldId);
  const warning = document.getElementById(warningId);

  const state = {
    hasConflict: false,
    conflictType: null,
  };

  if (!input || !warning) {
    return {
      state,
      isOverwriteEnabled: () => false,
    };
  }

  const messageElement =
    warning.querySelector("#warningMessage") ||
    warning.querySelector("[data-default-message]");
  const defaultMessage =
    messageElement?.getAttribute("data-default-message") ||
    getUiText("modals.links.shortlink_exists", "Shortlink already exists");
  const primaryConflictMessage = getUiText(
    "modals.links.shortlink_primary_conflict",
    "This shortlink is already active on another link",
  );
  const aliasConflictMessage = getUiText(
    "modals.links.alias_exists_can_overwrite",
    "This shortlink exists as an alias in history",
  );

  const overwriteContainer = allowAliasOverwrite
    ? document.getElementById("overwriteAliasOption")
    : null;
  const overwriteCheckbox = allowAliasOverwrite
    ? document.getElementById("overwriteAliasCheck")
    : null;
  const overwriteHiddenInput = allowAliasOverwrite
    ? document.getElementById("overwriteAlias")
    : null;

  const syncOverwriteValue = () => {
    if (!overwriteHiddenInput || !overwriteCheckbox) {
      return;
    }
    overwriteHiddenInput.value = overwriteCheckbox.checked ? "1" : "0";
  };

  const hideOverwriteOption = (resetChecked = true) => {
    if (!overwriteContainer) {
      return;
    }

    overwriteContainer.classList.remove("active");
    if (resetChecked && overwriteCheckbox) {
      overwriteCheckbox.checked = false;
    }
    syncOverwriteValue();
  };

  const showOverwriteOption = () => {
    if (!overwriteContainer) {
      return;
    }
    overwriteContainer.classList.add("active");
    syncOverwriteValue();
  };

  if (overwriteCheckbox && overwriteCheckbox.dataset.bound !== "true") {
    overwriteCheckbox.dataset.bound = "true";
    overwriteCheckbox.addEventListener("change", () => {
      syncOverwriteValue();
    });
  }

  hideOverwriteOption(true);
  setWarningMessage(warning, defaultMessage);

  input.addEventListener("input", async function () {
    const shortlinkValue = (input.value || "").trim();
    if (!shortlinkValue) {
      state.hasConflict = false;
      state.conflictType = null;
      warning.classList.remove("active");
      setWarningMessage(warning, defaultMessage);
      hideOverwriteOption(true);
      return;
    }

    const payload = {
      shortlink: shortlinkValue,
    };

    if (typeof includeCurrentLinkId === "function") {
      const currentLinkId = includeCurrentLinkId();
      if (currentLinkId) {
        payload.currentLinkId = currentLinkId;
      }
    }

    try {
      const response = await fetch(endpoint || "/getShortlink", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const rawResult = await response.text();
      const parsedResult = parseShortlinkConflictResponse(rawResult);
      state.hasConflict = parsedResult.hasConflict;
      state.conflictType = parsedResult.conflictType;

      warning.classList.toggle("active", parsedResult.hasConflict);

      if (!parsedResult.hasConflict) {
        setWarningMessage(warning, defaultMessage);
        hideOverwriteOption(true);
        return;
      }

      if (parsedResult.conflictType === "alias") {
        setWarningMessage(warning, aliasConflictMessage);
        if (allowAliasOverwrite) {
          showOverwriteOption();
        } else {
          hideOverwriteOption(true);
        }
        return;
      }

      setWarningMessage(warning, primaryConflictMessage);
      hideOverwriteOption(true);
    } catch {
      state.hasConflict = false;
      state.conflictType = null;
      warning.classList.remove("active");
      setWarningMessage(warning, defaultMessage);
      hideOverwriteOption(true);
    }
  });

  return {
    state,
    isOverwriteEnabled: () => Boolean(overwriteCheckbox?.checked),
  };
}

// ============================================================================
// MODAL FUNCTIONS
// ============================================================================

function readModalFieldValue(field) {
  if (!field) {
    return "";
  }

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
}

function bindModalDirtyTracking(modalContainer) {
  const trackedFields = new Set();
  const initialValues = new WeakMap();

  const isTrackableField = (field) => {
    if (!(field instanceof HTMLElement)) {
      return false;
    }

    if (!field.matches("input, textarea, select")) {
      return false;
    }

    if (field.disabled || field.closest("#confirmationModal")) {
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

  const rememberInitialValue = (field) => {
    if (!isTrackableField(field)) {
      return;
    }

    trackedFields.add(field);

    if (!initialValues.has(field)) {
      initialValues.set(field, readModalFieldValue(field));
    }
  };

  modalContainer
    .querySelectorAll(
      "#modal-content input, #modal-content textarea, #modal-content select",
    )
    .forEach((field) => {
      rememberInitialValue(field);
    });

  changes = false;

  const syncDirtyState = () => {
    let dirty = false;

    trackedFields.forEach((field) => {
      if (dirty || !field || !field.isConnected) {
        return;
      }

      if (!isTrackableField(field)) {
        return;
      }

      const baseline = initialValues.get(field);
      if (readModalFieldValue(field) !== baseline) {
        dirty = true;
      }
    });

    changes = dirty;
  };

  const getEventField = (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
      return null;
    }

    const field = target.closest("input, textarea, select");
    if (!field || !modalContainer.contains(field)) {
      return null;
    }

    if (field.closest("#confirmationModal")) {
      return null;
    }

    return field;
  };

  const handleValueMutation = (event) => {
    const field = getEventField(event);
    if (!field) {
      return;
    }

    rememberInitialValue(field);
    syncDirtyState();
  };

  modalContainer.addEventListener("focusin", (event) => {
    const field = getEventField(event);
    if (!field) {
      return;
    }

    rememberInitialValue(field);
  });

  modalContainer.addEventListener("input", handleValueMutation);
  modalContainer.addEventListener("change", handleValueMutation);
  modalContainer.addEventListener("reset", () => {
    requestAnimationFrame(() => {
      trackedFields.forEach((field) => {
        if (field && field.isConnected && isTrackableField(field)) {
          initialValues.set(field, readModalFieldValue(field));
        }
      });
      changes = false;
    });
  });
}

function closeModalContainerAndCleanup() {
  document.getElementById("confirmationModal")?.remove();
  const modalContainer = document.getElementById("modalContainer");
  if (modalContainer) {
    modalContainer.remove();
  }
  unlockBodyScroll();
  restoreModalFocus();
}

function getPreferredSubmitter(form) {
  return form?.querySelector(
    'button[type="submit"]:not([disabled]), input[type="submit"]:not([disabled])',
  );
}

function findModalSubmitForm() {
  const modalContent = document.getElementById("modal-content");
  if (!modalContent) {
    return null;
  }

  const explicitForm = modalContent.querySelector(
    'form[data-unsaved-submit="true"], form[data-primary-submit="true"]',
  );
  if (explicitForm) {
    return explicitForm;
  }

  const activeElement =
    document.activeElement instanceof Element ? document.activeElement : null;
  const activeForm = activeElement?.closest("form");
  if (activeForm && modalContent.contains(activeForm)) {
    return activeForm;
  }

  const forms = Array.from(modalContent.querySelectorAll("form"));
  if (forms.length === 1) {
    return forms[0];
  }

  return forms.find((form) => getPreferredSubmitter(form)) || forms[0] || null;
}

function submitModalChanges() {
  const form = findModalSubmitForm();
  if (!form) {
    return false;
  }

  if (typeof form.reportValidity === "function" && !form.reportValidity()) {
    return false;
  }

  const submitter = getPreferredSubmitter(form);

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
    // requestSubmit can throw in some legacy edge-cases.
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
}

function createModal(modalLink, size = "small") {
  if (
    document.getElementById("modalContainer") ||
    pendingModalLink === modalLink
  ) {
    return;
  }

  _modalTriggerElement =
    document.activeElement instanceof HTMLElement
      ? document.activeElement
      : null;

  pendingModalLink = modalLink;

  getModalContent(modalLink)
    .then((data) => {
      const allowFullscreenToggle =
        !/id=["']loginForm["']/i.test(data) &&
        !/class=["'][^"']*\bloginForm\b[^"']*["']/i.test(data);

      const sizeToggleButton = allowFullscreenToggle
        ? '<button type="button" class="toggleModalSize modalWindowControl modalSizeToggle material-icons" aria-label="Expand modal">fullscreen</button>'
        : "";

      const html = `
        <div id="modalContainer" class="modal">
          <div class="modalBackground closeModal"></div>
          <div class="modal-content" id="modal-content">
            <div class="modalWindowControls">
              ${sizeToggleButton}
              <button type="button" class="closeModal modalWindowControl crossIcon material-icons" aria-label="Close modal">close</button>
            </div>
            ${data}
          </div>
        </div>
      `;
      document.body.insertAdjacentHTML("beforeend", html);
      lockBodyScroll();

      if (size === "large") {
        document.getElementById("modal-content").classList.add("large");
      }

      const modalContainer = document.getElementById("modalContainer");
      const isCustomCssEditorModal =
        /data-custom-css-editor=["']true["']/i.test(data);

      changes = false;

      if (!isCustomCssEditorModal && modalContainer) {
        bindModalDirtyTracking(modalContainer);
      }

      modalContainer?.querySelectorAll(".closeModal").forEach((close) => {
        close.addEventListener("click", handleModalClose);
      });

      attachModalResizeHandlers();
      bindPostMutationRefresh();

      loadModalSpecificEvents();

      requestAnimationFrame(() => {
        const mc = document.getElementById("modal-content");
        if (!mc) return;
        _modalFocusTrap = trapFocus(mc);
        if (!mc.contains(document.activeElement)) {
          const firstInput = mc.querySelector(
            'input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled])',
          );
          const closeBtn = mc.querySelector(".closeModal");
          (firstInput || closeBtn)?.focus({ preventScroll: true });
        }
      });
    })
    .catch(() => {
      createSnackbar(
        getUiText("js.common.could_not_open_modal", "Could not open modal"),
        "error",
      );
    })
    .finally(() => {
      pendingModalLink = null;
    });
}

function getModalContent(modalLink) {
  const bypassCache =
    modalLink.includes("comp=aliasHistory") ||
    /\/(?:custom-css-editor|css)(?:\?comp=(dark|light|dashboard|branding))?\b/i.test(
      modalLink,
    );

  if (!bypassCache && modalContentCache.has(modalLink)) {
    return Promise.resolve(modalContentCache.get(modalLink));
  }

  return fetch(modalLink)
    .then((response) => response.text())
    .then((data) => {
      if (!bypassCache) {
        modalContentCache.set(modalLink, data);
      }
      return data;
    });
}

function prefetchModal(modalLink) {
  getModalContent(modalLink).catch(() => {});
}

function attachModalResizeHandlers() {
  document.querySelectorAll(".toggleModalSize").forEach((toggleBtn) => {
    if (toggleBtn.dataset.bound === "true") {
      return;
    }

    toggleBtn.dataset.bound = "true";

    toggleBtn.addEventListener("click", (event) => {
      event.stopPropagation();

      const modalContainer = document.getElementById("modalContainer");
      if (!modalContainer) {
        return;
      }

      const modalContent = modalContainer.matches(".popup-modal-content")
        ? modalContainer
        : modalContainer.querySelector(
            "#modal-content, .modal-content, .modal-popup",
          );

      if (!modalContent) {
        return;
      }

      const isFullscreen = modalContent.classList.toggle("modal-fullscreen");
      toggleBtn.textContent = isFullscreen ? "fullscreen_exit" : "fullscreen";
    });
  });
}

async function handleModalClose(forceClose = false) {
  if (forceClose === true) {
    changes = false;
    closeModalContainerAndCleanup();
    return;
  }

  if (!document.getElementById("modalContainer")) {
    return;
  }

  if (changes) {
    if (document.getElementById("confirmationModal")) {
      return;
    }

    const action =
      typeof window.showUnsavedChangesPrompt === "function"
        ? await window.showUnsavedChangesPrompt()
        : "discard";

    if (action === "save") {
      const submitted = submitModalChanges();
      if (!submitted && typeof createSnackbar === "function") {
        createSnackbar(
          getUiText(
            "js.common.could_not_save_changes",
            "Could not save changes",
          ),
          "error",
        );
      }
      return;
    }

    if (action === "discard") {
      changes = false;
      closeModalContainerAndCleanup();
    }

    return;
  } else {
    closeModalContainerAndCleanup();
  }
}
window.handleModalClose = handleModalClose;

function loadModalSpecificEvents() {
  const modalContent = document.getElementById("modal-content");
  if (modalContent && document.querySelector(".aliasHistoryModal")) {
    modalContent.classList.add("aliasHistoryWide");
  }

  loadCustomCssEditorEvents();
  loadBrandAssetDashboardEvents();

  if (
    document.getElementById("createUserForm") ||
    document.getElementById("editUserForm")
  ) {
    loadCreateUserEvents();
  }

  if (
    document.getElementById("editLinkForm") ||
    document.getElementById("editUserForm")
  ) {
    loadEditModalEvents();
  }

  if (
    document.getElementById("createLinkForm") ||
    document.getElementById("createUserForm")
  ) {
    loadCreateModalEvents();
  }

  if (document.getElementById("qrCode")) {
    loadQrEvents();
  }

  if (document.getElementById("aliasHistoryList")) {
    loadAliasHistoryEvents();
  }
}

function loadBrandAssetDashboardEvents() {
  const dashboardRoot = document.querySelector(
    ".brandAssetDashboard[data-brand-asset-dashboard='true']",
  );
  if (!dashboardRoot || dashboardRoot.dataset.bound === "true") {
    return;
  }

  dashboardRoot.dataset.bound = "true";

  const modalContent = document.getElementById("modal-content");
  if (modalContent) {
    modalContent.classList.add("brandAssetDashboardModal");
  }

  const tabButtons = Array.from(
    dashboardRoot.querySelectorAll(".brandAssetThemeTab[data-brand-theme-tab]"),
  );
  const activeThemeBadge = dashboardRoot.querySelector(
    "[data-brand-active-theme-label]",
  );
  const logoPreview = dashboardRoot.querySelector(
    "[data-brand-preview='logo']",
  );
  const faviconPreview = dashboardRoot.querySelector(
    "[data-brand-preview='favicon']",
  );
  const currentThemeLabels = Array.from(
    dashboardRoot.querySelectorAll("[data-brand-current-theme]"),
  );
  const uploadButtons = Array.from(
    dashboardRoot.querySelectorAll("[data-brand-upload-trigger]"),
  );

  const normalizeTheme = (theme) => (theme === "dark" ? "dark" : "light");

  const getResolvedPageTheme = () => {
    const scheme = String(document.documentElement?.style?.colorScheme || "");
    if (scheme === "dark" || scheme === "light") {
      return scheme;
    }

    const bodyPreference = String(
      document.body?.dataset?.themePreference || "",
    );
    if (bodyPreference === "dark" || bodyPreference === "light") {
      return bodyPreference;
    }

    if (bodyPreference === "system") {
      try {
        return window.matchMedia("(prefers-color-scheme: dark)").matches
          ? "dark"
          : "light";
      } catch {
        return "light";
      }
    }

    try {
      const stored = localStorage.getItem("resolvedTheme") || "";
      if (stored === "dark" || stored === "light") {
        return stored;
      }
    } catch {
      return "light";
    }

    return "light";
  };

  const themeLabelText = (theme) =>
    normalizeTheme(theme) === "dark"
      ? getUiText("js.admin.dark_theme", "Dark Theme")
      : getUiText("js.admin.light_theme", "Light Theme");

  const updatePreviews = (theme) => {
    const nextTheme = normalizeTheme(theme);
    const logoSrc =
      nextTheme === "dark"
        ? dashboardRoot.dataset.logoDarkSrc
        : dashboardRoot.dataset.logoLightSrc;
    const faviconSrc =
      nextTheme === "dark"
        ? dashboardRoot.dataset.faviconDarkSrc
        : dashboardRoot.dataset.faviconLightSrc;

    if (logoPreview && logoSrc) {
      logoPreview.src = logoSrc;
    }

    if (faviconPreview && faviconSrc) {
      faviconPreview.src = faviconSrc;
    }

    const themeText = themeLabelText(nextTheme);
    if (activeThemeBadge) {
      activeThemeBadge.textContent = themeText;
    }

    currentThemeLabels.forEach((label) => {
      label.textContent = themeText;
    });
  };

  const persistThemeMode = (mode) => {
    fetch("/darkMode", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ mode }),
    }).catch(() => {});
  };

  const syncDashboardThemeToPage = (theme) => {
    const nextTheme = normalizeTheme(theme);

    if (getResolvedPageTheme() === nextTheme) {
      return;
    }

    if (typeof window.applyTheme === "function") {
      window.applyTheme(nextTheme);
    } else {
      document.documentElement.style.colorScheme = nextTheme;
      if (document.body?.dataset) {
        document.body.dataset.themePreference = nextTheme;
      }
    }

    persistThemeMode(nextTheme);
  };

  const setActiveTheme = (theme, syncPageTheme = true) => {
    const nextTheme = normalizeTheme(theme);
    dashboardRoot.dataset.activeTheme = nextTheme;

    tabButtons.forEach((button) => {
      const isActive = button.dataset.brandThemeTab === nextTheme;
      button.classList.toggle("is-active", isActive);
      button.setAttribute("aria-selected", isActive ? "true" : "false");
    });

    updatePreviews(nextTheme);

    if (syncPageTheme) {
      syncDashboardThemeToPage(nextTheme);
    }
  };

  const getActiveTheme = () =>
    normalizeTheme(dashboardRoot.dataset.activeTheme || getResolvedPageTheme());

  tabButtons.forEach((button) => {
    button.addEventListener("click", () => {
      setActiveTheme(button.dataset.brandThemeTab || "light");
    });
  });

  uploadButtons.forEach((button) => {
    const assetType = String(button.dataset.brandUploadTrigger || "").trim();
    const input = dashboardRoot.querySelector(
      `[data-brand-upload-input='${assetType}']`,
    );
    if (!assetType || !input) {
      return;
    }

    button.addEventListener("click", () => {
      input.click();
    });

    if (input.dataset.bound === "true") {
      return;
    }

    input.dataset.bound = "true";
    input.addEventListener("input", () => {
      if (typeof uploadBrandAsset === "function") {
        uploadBrandAsset(assetType, getActiveTheme(), input.id);
        return;
      }

      createSnackbar(
        getUiText(
          "js.admin.error_uploading_brand_asset",
          "Error uploading brand asset",
        ),
        "error",
      );
    });
  });

  setActiveTheme(getActiveTheme(), false);
  window.getActiveBrandAssetTheme = getActiveTheme;
}

function loadCustomCssEditorEvents() {
  const forms = Array.from(
    document.querySelectorAll(
      "form.customCssEditorForm[data-custom-css-editor='true']",
    ),
  );
  if (forms.length === 0) {
    return;
  }

  const modalContent = document.getElementById("modal-content");
  if (modalContent) {
    modalContent.classList.add("customCssEditorModal");
  }

  const dashboardRoot = document.querySelector(
    ".customCssDashboard[data-custom-css-editor-dashboard='true']",
  );

  const colorInputRegex =
    /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$|^rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)$|^\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}$/;
  const radiusRegex = /^(?:0|0px|\d+(?:\.\d+)?px)$/i;
  const motionRegex = /^\d+(?:\.\d+)?s$/i;

  const parseRgb = (value) => {
    const candidate = String(value || "").trim();
    if (!candidate) {
      return null;
    }

    const match = candidate.match(
      /^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$|^(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})$/i,
    );
    if (!match) {
      return null;
    }

    const values =
      match[1] !== undefined
        ? [match[1], match[2], match[3]]
        : [match[4], match[5], match[6]];

    const ints = values.map((item) => Number.parseInt(item, 10));
    if (ints.some((item) => !Number.isFinite(item) || item < 0 || item > 255)) {
      return null;
    }

    return ints;
  };

  const normalizeHex = (value) => {
    const candidate = String(value || "")
      .trim()
      .toLowerCase();
    if (!/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/.test(candidate)) {
      return null;
    }

    if (candidate.length === 4) {
      return `#${candidate[1]}${candidate[1]}${candidate[2]}${candidate[2]}${candidate[3]}${candidate[3]}`;
    }

    return candidate;
  };

  const normalizeColorToHex = (value) => {
    const asHex = normalizeHex(value);
    if (asHex) {
      return asHex;
    }

    const rgb = parseRgb(value);
    if (!rgb) {
      return null;
    }

    const hex = rgb.map((item) => item.toString(16).padStart(2, "0")).join("");
    return `#${hex}`;
  };

  const hexToRgbString = (hexValue) => {
    const normalized = normalizeHex(hexValue);
    if (!normalized || normalized.length < 7) {
      return null;
    }

    const r = Number.parseInt(normalized.slice(1, 3), 16);
    const g = Number.parseInt(normalized.slice(3, 5), 16);
    const b = Number.parseInt(normalized.slice(5, 7), 16);
    if ([r, g, b].some((item) => Number.isNaN(item))) {
      return null;
    }

    return `${r}, ${g}, ${b}`;
  };

  const splitShadowLayers = (value) => {
    const layers = [];
    let depth = 0;
    let buffer = "";

    for (const char of String(value || "")) {
      if (char === "(") {
        depth += 1;
      } else if (char === ")" && depth > 0) {
        depth -= 1;
      }

      if (char === "," && depth === 0) {
        const trimmed = buffer.trim();
        if (trimmed) {
          layers.push(trimmed);
        }
        buffer = "";
      } else {
        buffer += char;
      }
    }

    const trimmed = buffer.trim();
    if (trimmed) {
      layers.push(trimmed);
    }

    return layers;
  };

  const isPxValue = (part) => {
    const candidate = String(part || "").trim();
    return candidate === "0" || /^-?(?:\d+|\d*\.\d+)px$/i.test(candidate);
  };

  const isValidShadowValue = (value) => {
    const candidate = String(value || "").trim();
    if (!candidate) {
      return false;
    }

    if (candidate.toLowerCase() === "none") {
      return true;
    }

    const layers = splitShadowLayers(candidate);
    if (!layers.length) {
      return false;
    }

    return layers.every((layer) => {
      const tokens = layer.split(/\s+/).filter(Boolean);
      if (!tokens.length) {
        return false;
      }

      let cursor = tokens[0].toLowerCase() === "inset" ? 1 : 0;
      let lengthCount = 0;
      while (
        cursor < tokens.length &&
        isPxValue(tokens[cursor]) &&
        lengthCount < 4
      ) {
        cursor += 1;
        lengthCount += 1;
      }

      return lengthCount >= 2;
    });
  };

  const getColorFormat = () => {
    const format = dashboardRoot?.dataset.colorFormat || "hex";
    return format === "rgb" ? "rgb" : "hex";
  };

  const setFieldError = (field, message) => {
    const container = field.closest(".customCssTokenField");
    const errorElement = container?.querySelector(".customCssTokenError");
    if (errorElement) {
      errorElement.textContent = message || "";
    }
    container?.classList.toggle("is-invalid", Boolean(message));
  };

  const updateColorFieldVisuals = (field) => {
    if (field?.dataset?.tokenType !== "color") {
      return null;
    }

    const normalizedHex = normalizeColorToHex(field.value);
    const colorPicker = field
      .closest(".customCssTokenField")
      ?.querySelector(".customCssTokenColor");
    const hoverSwatch = field
      .closest(".customCssTokenField")
      ?.querySelector(".customCssTokenHoverSwatch");

    if (normalizedHex) {
      field.dataset.hexValue = normalizedHex;
      if (colorPicker) {
        colorPicker.value = normalizedHex;
      }
      if (hoverSwatch) {
        hoverSwatch.style.backgroundColor = normalizedHex;
      }
      if (getColorFormat() === "hex") {
        field.value = normalizedHex;
      }
      setFieldError(field, "");
      return normalizedHex;
    }

    if (String(field.value || "").trim() !== "") {
      setFieldError(
        field,
        getUiText(
          "js.admin.css_validation_color",
          "Use HEX (#00cd87) or RGB (0, 205, 135).",
        ),
      );
    } else {
      setFieldError(field, "");
    }

    return null;
  };

  const applyColorDisplayMode = (field, format) => {
    if (field?.dataset?.tokenType !== "color") {
      return;
    }

    const normalizedHex =
      field.dataset.hexValue || normalizeColorToHex(field.value);
    if (!normalizedHex) {
      return;
    }

    field.dataset.hexValue = normalizedHex;
    if (format === "rgb") {
      const rgb = hexToRgbString(normalizedHex);
      if (rgb) {
        field.value = rgb;
      }
      return;
    }

    field.value = normalizedHex;
  };

  const trackedFields = forms.flatMap((form) =>
    Array.from(form.querySelectorAll("input, textarea, select")).filter(
      (field) => {
        if (!field?.name) {
          return false;
        }

        return field.type !== "hidden";
      },
    ),
  );

  const initialValues = new Map();

  const readFieldValue = (field) => {
    if (field.type === "checkbox" || field.type === "radio") {
      return field.checked ? "1" : "0";
    }

    if (field.dataset?.tokenType === "color") {
      const normalizedHex =
        field.dataset.hexValue || normalizeColorToHex(field.value);
      return normalizedHex || String(field.value || "");
    }

    return String(field.value ?? "");
  };

  const captureInitialValues = () => {
    trackedFields.forEach((field) => {
      if (field.dataset?.tokenType === "color") {
        const normalizedHex = normalizeColorToHex(field.value);
        if (normalizedHex) {
          field.dataset.hexValue = normalizedHex;
        }
      }

      initialValues.set(field, readFieldValue(field));
    });
  };

  const syncDirtyState = () => {
    changes = trackedFields.some(
      (field) => readFieldValue(field) !== initialValues.get(field),
    );
  };

  captureInitialValues();
  changes = false;

  trackedFields.forEach((field) => {
    if (field.dataset?.tokenType === "color") {
      updateColorFieldVisuals(field);
      applyColorDisplayMode(field, getColorFormat());
    }

    field.addEventListener("input", () => {
      if (field.dataset?.tokenType === "color") {
        updateColorFieldVisuals(field);
      }

      if (field.dataset?.tokenType === "shadow") {
        const value = String(field.value || "").trim();
        if (value === "" || isValidShadowValue(value)) {
          setFieldError(field, "");
        } else {
          setFieldError(
            field,
            getUiText(
              "js.admin.css_validation_shadow",
              "Use shadow values with px units or none.",
            ),
          );
        }
      }

      syncDirtyState();
    });

    field.addEventListener("change", () => {
      if (field.dataset?.tokenType === "color") {
        updateColorFieldVisuals(field);
      }
      syncDirtyState();
    });

    if (field.dataset?.tokenType === "color") {
      const colorPicker = field
        .closest(".customCssTokenField")
        ?.querySelector(".customCssTokenColor");

      colorPicker?.addEventListener("input", () => {
        const nextHex = normalizeHex(colorPicker.value);
        if (!nextHex) {
          return;
        }

        field.dataset.hexValue = nextHex;
        applyColorDisplayMode(field, getColorFormat());
        setFieldError(field, "");
        syncDirtyState();
      });
    }
  });

  if (dashboardRoot && dashboardRoot.dataset.bound !== "true") {
    dashboardRoot.dataset.bound = "true";

    const tabButtons = Array.from(
      dashboardRoot.querySelectorAll(".customCssEditorTab[data-theme-tab]"),
    );
    const panels = Array.from(
      dashboardRoot.querySelectorAll(".customCssThemePanel[data-theme-panel]"),
    );

    const getResolvedPageTheme = () => {
      const scheme = String(document.documentElement?.style?.colorScheme || "");
      if (scheme === "dark" || scheme === "light") {
        return scheme;
      }

      const bodyPreference = String(
        document.body?.dataset?.themePreference || "",
      );
      if (bodyPreference === "dark" || bodyPreference === "light") {
        return bodyPreference;
      }

      if (bodyPreference === "system") {
        try {
          return window.matchMedia("(prefers-color-scheme: dark)").matches
            ? "dark"
            : "light";
        } catch {
          return "light";
        }
      }

      try {
        const stored = localStorage.getItem("resolvedTheme") || "";
        if (stored === "dark" || stored === "light") {
          return stored;
        }
      } catch {
        return "light";
      }

      return "light";
    };

    const persistThemeMode = (mode) => {
      fetch("/darkMode", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ mode }),
      }).catch(() => {});
    };

    const syncDashboardThemeToPage = (theme) => {
      const nextTheme = theme === "dark" ? "dark" : "light";

      if (getResolvedPageTheme() === nextTheme) {
        return;
      }

      if (typeof window.applyTheme === "function") {
        window.applyTheme(nextTheme);
      } else {
        document.documentElement.style.colorScheme = nextTheme;
        if (document.body?.dataset) {
          document.body.dataset.themePreference = nextTheme;
        }
      }

      persistThemeMode(nextTheme);
    };

    const setActiveTheme = (theme, syncPageTheme = true) => {
      const nextTheme = theme === "dark" ? "dark" : "light";
      dashboardRoot.dataset.activeTheme = nextTheme;

      tabButtons.forEach((button) => {
        const isActive = button.dataset.themeTab === nextTheme;
        button.classList.toggle("is-active", isActive);
        button.setAttribute("aria-selected", isActive ? "true" : "false");
      });

      panels.forEach((panel) => {
        const isActive = panel.dataset.themePanel === nextTheme;
        panel.classList.toggle("is-active", isActive);
        panel.hidden = !isActive;
      });

      if (syncPageTheme) {
        syncDashboardThemeToPage(nextTheme);
      }
    };

    tabButtons.forEach((button) => {
      button.addEventListener("click", () => {
        setActiveTheme(button.dataset.themeTab || "light");
      });
    });

    const formatButtons = Array.from(
      dashboardRoot.querySelectorAll(
        ".customCssFormatButton[data-color-format]",
      ),
    );
    const setColorFormat = (format) => {
      const normalizedFormat = format === "rgb" ? "rgb" : "hex";
      dashboardRoot.dataset.colorFormat = normalizedFormat;

      formatButtons.forEach((button) => {
        const isActive = button.dataset.colorFormat === normalizedFormat;
        button.classList.toggle("is-active", isActive);
        button.setAttribute("aria-pressed", isActive ? "true" : "false");
      });

      trackedFields
        .filter((field) => field.dataset?.tokenType === "color")
        .forEach((field) => applyColorDisplayMode(field, normalizedFormat));
    };

    formatButtons.forEach((button) => {
      button.addEventListener("click", () => {
        setColorFormat(button.dataset.colorFormat || "hex");
      });
    });

    const initialTheme =
      dashboardRoot.dataset.activeTheme ||
      getResolvedPageTheme() ||
      tabButtons.find((button) => button.classList.contains("is-active"))
        ?.dataset.themeTab ||
      "light";

    setActiveTheme(initialTheme, false);
    setColorFormat(
      dashboardRoot.dataset.colorFormat ||
        formatButtons.find((button) => button.classList.contains("is-active"))
          ?.dataset.colorFormat ||
        "hex",
    );

    window.getActiveCustomCssTheme = () =>
      dashboardRoot.dataset.activeTheme === "dark" ? "dark" : "light";
  }

  if (modalContent && modalContent.dataset.copyTokenBound !== "true") {
    modalContent.dataset.copyTokenBound = "true";
    modalContent.addEventListener("click", async (event) => {
      const copyButton = event.target.closest("[data-copy-token-value='true']");
      if (!copyButton) {
        return;
      }

      const field = copyButton
        .closest(".customCssTokenField")
        ?.querySelector(".customCssTokenInput");
      const value = String(field?.value || "").trim();
      if (!value) {
        return;
      }

      try {
        await navigator.clipboard.writeText(value);
        createSnackbar(
          getUiText("js.admin.css_value_copied", "Value copied"),
          "success",
        );
      } catch {
        createSnackbar(
          getUiText("js.admin.css_value_copy_failed", "Could not copy value"),
          "error",
        );
      }
    });
  }

  const validateForm = (form) => {
    let isValid = true;
    const fields = Array.from(
      form.querySelectorAll(".customCssTokenInput[data-token-name]"),
    );

    fields.forEach((field) => {
      const tokenType = (field.dataset.tokenType || "text").toLowerCase();
      const tokenName = (field.dataset.tokenName || "").toLowerCase();
      const value = String(field.value || "").trim();

      if (tokenType === "color") {
        if (!colorInputRegex.test(value)) {
          setFieldError(
            field,
            getUiText(
              "js.admin.css_validation_color",
              "Use HEX (#00cd87) or RGB (0, 205, 135).",
            ),
          );
          isValid = false;
          return;
        }

        const normalizedHex = normalizeColorToHex(value);
        if (!normalizedHex) {
          setFieldError(
            field,
            getUiText(
              "js.admin.css_validation_color",
              "Use HEX (#00cd87) or RGB (0, 205, 135).",
            ),
          );
          isValid = false;
          return;
        }

        field.dataset.hexValue = normalizedHex;
        setFieldError(field, "");
        return;
      }

      if (tokenType === "shadow") {
        if (!isValidShadowValue(value)) {
          setFieldError(
            field,
            getUiText(
              "js.admin.css_validation_shadow",
              "Use shadow values with px units or none.",
            ),
          );
          isValid = false;
          return;
        }

        setFieldError(field, "");
        return;
      }

      if (tokenName.startsWith("radius-")) {
        if (!radiusRegex.test(value)) {
          setFieldError(
            field,
            getUiText(
              "js.admin.css_validation_radius",
              "Radius must be 0 or a px value like 10px.",
            ),
          );
          isValid = false;
          return;
        }

        setFieldError(field, "");
        return;
      }

      if (tokenName === "motion-duration-base") {
        if (!motionRegex.test(value)) {
          setFieldError(
            field,
            getUiText(
              "js.admin.css_validation_motion",
              "Duration must be in seconds, for example 0.3s.",
            ),
          );
          isValid = false;
          return;
        }

        setFieldError(field, "");
        return;
      }

      setFieldError(field, "");
    });

    return isValid;
  };

  forms.forEach((form) => {
    if (form.dataset.bound === "true") {
      return;
    }

    form.dataset.bound = "true";

    const sectionStateInput = form.querySelector("#adminSectionState");
    const scrollStateInput = form.querySelector("#adminScrollState");
    const submitButton = form.querySelector("button[type='submit']");
    const defaultLabel = submitButton?.dataset.defaultLabel || "Update CSS";
    const pendingLabel =
      submitButton?.dataset.pendingLabel || "Updating CSS...";

    form.addEventListener("keydown", (event) => {
      const isSaveShortcut =
        (event.ctrlKey || event.metaKey) &&
        !event.altKey &&
        String(event.key || "").toLowerCase() === "s";

      if (isSaveShortcut) {
        event.preventDefault();
        if (form.dataset.submitting !== "true") {
          if (typeof form.requestSubmit === "function") {
            form.requestSubmit();
          } else {
            submitButton?.click();
          }
        }
        return;
      }

      if (event.key === "Enter" && event.target?.tagName !== "TEXTAREA") {
        event.preventDefault();
      }
    });

    form.addEventListener("submit", async (event) => {
      event.preventDefault();

      if (form.dataset.submitting === "true") {
        return;
      }

      if (!validateForm(form)) {
        createSnackbar(
          getUiText(
            "js.admin.css_validation_failed",
            "Please fix invalid fields before saving.",
          ),
          "error",
        );
        return;
      }

      if (sectionStateInput) {
        sectionStateInput.value = "customStyling";
      }

      if (scrollStateInput) {
        scrollStateInput.value = String(
          Math.max(0, Math.floor(window.scrollY || window.pageYOffset || 0)),
        );
      }

      const colorFields = Array.from(
        form.querySelectorAll(".customCssTokenInput[data-token-type='color']"),
      );
      const restoreDisplayValues = colorFields.map((field) => ({
        field,
        displayValue: String(field.value || ""),
      }));

      colorFields.forEach((field) => {
        const normalizedHex =
          field.dataset.hexValue || normalizeColorToHex(field.value);
        if (normalizedHex) {
          field.value = normalizedHex;
        }
      });

      form.dataset.submitting = "true";
      form.classList.add("is-submitting");
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = pendingLabel;
      }

      try {
        const response = await fetch(form.action, {
          method: "POST",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json",
          },
          body: new FormData(form),
        });

        const payload = await response.json().catch(() => ({}));

        if (payload?.errors && typeof payload.errors === "object") {
          Object.entries(payload.errors).forEach(([name, message]) => {
            const field = Array.from(form.querySelectorAll("[name]")).find(
              (item) => item.name === name,
            );
            if (field) {
              setFieldError(field, String(message || ""));
            }
          });
        }

        if (!response.ok || payload?.success !== true) {
          throw new Error(
            payload?.message ||
              getUiText("js.admin.failed_update_css", "Failed to update CSS"),
          );
        }

        captureInitialValues();
        syncDirtyState();

        for (const cacheKey of [...modalContentCache.keys()]) {
          if (
            /^\/(?:custom-css-editor|css)\?comp=(dark|light|dashboard)\b/i.test(
              cacheKey,
            )
          ) {
            modalContentCache.delete(cacheKey);
          }
        }

        createSnackbar(
          payload?.message ||
            getUiText(
              "js.admin.css_updated_successfully",
              "CSS updated successfully",
            ),
          "success",
        );
      } catch (error) {
        createSnackbar(
          error?.message ||
            getUiText("js.admin.failed_update_css", "Failed to update CSS"),
          "error",
        );
      } finally {
        restoreDisplayValues.forEach(({ field, displayValue }) => {
          field.value = displayValue;
          applyColorDisplayMode(field, getColorFormat());
        });

        form.dataset.submitting = "false";
        form.classList.remove("is-submitting");
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = defaultLabel;
        }

        syncDirtyState();
      }
    });
  });
}

function loadAliasHistoryEvents() {
  const listElement = document.getElementById("aliasHistoryList");
  if (!listElement || listElement.dataset.bound === "true") {
    return;
  }

  listElement.dataset.bound = "true";

  listElement.addEventListener("click", async (event) => {
    const copyButton = event.target.closest("[data-copy-alias-url]");
    if (copyButton) {
      const aliasUrl = copyButton.getAttribute("data-copy-alias-url") || "";
      if (!aliasUrl) {
        return;
      }

      try {
        await navigator.clipboard.writeText(aliasUrl);
        createSnackbar(
          getUiText("js.links.alias_url_copied", "Alias URL copied"),
          "success",
        );
      } catch {
        createSnackbar(
          getUiText(
            "js.links.could_not_copy_alias_url",
            "Could not copy alias URL",
          ),
          "error",
        );
      }
      return;
    }

    const deleteButton = event.target.closest("[data-delete-alias]");
    if (deleteButton) {
      const alias = deleteButton.getAttribute("data-delete-alias") || "";
      const linkId = Number.parseInt(
        listElement.getAttribute("data-link-id") || "0",
        10,
      );

      if (!alias || !Number.isFinite(linkId) || linkId <= 0) {
        return;
      }

      const confirmed =
        typeof window.showThemedConfirm === "function"
          ? await window.showThemedConfirm(
              getUiText("js.links.confirm_delete_alias", "Delete this alias?"),
            )
          : true;

      if (!confirmed) {
        return;
      }

      deleteButton.disabled = true;

      try {
        const response = await fetch("/deleteLinkAlias", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ link_id: linkId, alias }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok || payload.success !== true) {
          throw new Error(
            payload.message ||
              getUiText(
                "js.links.failed_delete_alias",
                "Failed to delete alias",
              ),
          );
        }

        const aliasItem = deleteButton.closest(".aliasHistoryItem");
        if (aliasItem) {
          aliasItem.remove();
        }

        if (!listElement.querySelector(".aliasHistoryItem")) {
          const emptyState = document.createElement("div");
          emptyState.className = "aliasHistoryEmpty";
          emptyState.textContent = getUiText(
            "modals.alias_history.no_aliases_yet",
            "No previous aliases yet.",
          );
          const parent = listElement.parentElement;
          listElement.remove();
          parent?.appendChild(emptyState);
        }

        createSnackbar(
          getUiText("js.links.alias_deleted", "Alias deleted"),
          "success",
        );
        refreshCurrentPage(0);
      } catch (error) {
        createSnackbar(
          error?.message ||
            getUiText("js.links.failed_delete_alias", "Failed to delete alias"),
          "error",
        );
      } finally {
        deleteButton.disabled = false;
      }
      return;
    }

    const restoreButton = event.target.closest("[data-restore-alias]");
    if (!restoreButton) {
      return;
    }

    const alias = restoreButton.getAttribute("data-restore-alias") || "";
    const linkId = Number.parseInt(
      listElement.getAttribute("data-link-id") || "0",
      10,
    );
    if (!alias || !Number.isFinite(linkId) || linkId <= 0) {
      return;
    }

    const confirmed =
      typeof window.showThemedConfirm === "function"
        ? await window.showThemedConfirm(
            getUiText(
              "js.links.confirm_restore_alias",
              "Make this alias active again?",
            ),
          )
        : true;

    if (!confirmed) {
      return;
    }

    restoreButton.disabled = true;

    try {
      const response = await fetch("/restoreLinkAlias", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ link_id: linkId, alias }),
      });
      const payload = await response.json().catch(() => ({}));

      if (!response.ok || payload.success !== true) {
        throw new Error(
          payload.message ||
            getUiText(
              "js.links.failed_restore_alias",
              "Failed to restore alias",
            ),
        );
      }

      createSnackbar(
        getUiText("js.links.alias_restored", "Alias restored"),
        "success",
      );

      const modal = document.getElementById("modalContainer");
      if (modal) {
        modal.remove();
      }
      unlockBodyScroll();

      refreshCurrentPage(0);
    } catch (error) {
      createSnackbar(
        error?.message ||
          getUiText("js.links.failed_restore_alias", "Failed to restore alias"),
        "error",
      );
    } finally {
      restoreButton.disabled = false;
    }
  });
}

function createPopupModal(modalLink, anchor, event) {
  if (
    document.getElementById("modalContainer") ||
    event.target.classList.contains("closeModal")
  ) {
    return;
  }

  _modalTriggerElement =
    document.activeElement instanceof HTMLElement
      ? document.activeElement
      : null;

  fetch(modalLink)
    .then((response) => response.text())
    .then((data) => {
      const html = `
        <div id="modalContainer" class="modal">
          <div class="modalBackground closeModal"></div>
          <div class="modal-content modal-popup" id="modal-content">
            <div class="modalWindowControls modalWindowControlsPopup">
              <button type="button" class="closeModal modalWindowControl crossIcon material-icons" aria-label="Close modal">close</button>
            </div>
            ${data}
          </div>
        </div>
      `;
      document.body.insertAdjacentHTML("beforeend", html);
      lockBodyScroll();

      document.querySelectorAll(".closeModal").forEach((close) => {
        close.addEventListener("click", () => {
          const container = document.getElementById("modalContainer");
          if (container) {
            container.remove();
            unlockBodyScroll();
            restoreModalFocus();
          }
        });
      });

      loadPopupSpecificEvents();

      requestAnimationFrame(() => {
        const mc = document.getElementById("modal-content");
        if (!mc) return;
        _modalFocusTrap = trapFocus(mc);
        if (!mc.contains(document.activeElement)) {
          const closeBtn = mc.querySelector(".closeModal");
          closeBtn?.focus({ preventScroll: true });
        }
      });
    });
}

function loadPopupSpecificEvents() {
  if (document.getElementById("qrCode")) loadQrEvents();
  if (document.getElementById("deleteLinkId")) loadDeleteEvents();
  if (document.getElementById("statusCheck")) loadStatusEvents();
}

// ============================================================================
// MODAL EVENT LOADERS
// ============================================================================

function loadCreateUserEvents() {
  const customSelects = document.getElementsByClassName("custom-select");
  for (let i = 0; i < customSelects.length; i++) {
    initializeCustomSelect(customSelects[i]);
  }

  const logoutAllDevicesBtn = document.getElementById("logoutAllDevicesBtn");
  if (logoutAllDevicesBtn && logoutAllDevicesBtn.dataset.bound !== "true") {
    logoutAllDevicesBtn.dataset.bound = "true";

    logoutAllDevicesBtn.addEventListener("click", () => {
      const userId = parseInt(logoutAllDevicesBtn.dataset.userId || "0", 10);
      logoutUserAllDevices(userId);
    });
  }
}

function initUserPictureUploadUI() {
  const configs = [
    {
      inputId: "pictureFile",
      previewId: "picturePreviewCreate",
      statusId: "pictureStatusCreate",
      errorId: "pictureErrorCreate",
      emptyLabel: "No file selected",
    },
    {
      inputId: "pictureFileEdit",
      previewId: "picturePreviewEdit",
      statusId: "pictureStatusEdit",
      errorId: "pictureErrorEdit",
      emptyLabel: "No new file selected",
    },
  ];

  const maxSizeBytes = 5 * 1024 * 1024;
  const allowedTypes = new Set([
    "image/jpeg",
    "image/png",
    "image/gif",
    "image/webp",
  ]);

  configs.forEach((config) => {
    const input = document.getElementById(config.inputId);
    if (!input || input.dataset.uploadUiBound === "true") {
      return;
    }

    const preview = document.getElementById(config.previewId);
    const status = document.getElementById(config.statusId);
    const error = document.getElementById(config.errorId);
    const defaultPreviewSrc =
      preview?.getAttribute("src") || "/images/user.jpg";

    const clearError = () => {
      if (!error) return;
      error.textContent = "";
      error.classList.remove("active");
    };

    const showError = (message) => {
      if (!error) return;
      error.textContent = message;
      error.classList.add("active");
    };

    input.addEventListener("change", () => {
      clearError();
      const file = input.files && input.files[0] ? input.files[0] : null;

      if (!file) {
        if (status) status.textContent = config.emptyLabel;
        if (preview) preview.src = defaultPreviewSrc;
        return;
      }

      if (!allowedTypes.has(file.type)) {
        input.value = "";
        if (status) status.textContent = config.emptyLabel;
        if (preview) preview.src = defaultPreviewSrc;
        showError("Only JPG, PNG, GIF or WEBP images are allowed.");
        return;
      }

      if (file.size > maxSizeBytes) {
        input.value = "";
        if (status) status.textContent = config.emptyLabel;
        if (preview) preview.src = defaultPreviewSrc;
        showError(
          getUiText("js.common.image_max_5mb", "Image must be 5MB or smaller."),
        );
        return;
      }

      if (status) {
        status.textContent = formatUiText(
          getUiText("js.common.selected_file", "Selected: {file}"),
          { file: file.name },
        );
      }

      if (preview) {
        const objectUrl = URL.createObjectURL(file);
        preview.src = objectUrl;
        preview.onload = () => URL.revokeObjectURL(objectUrl);
      }
    });

    input.dataset.uploadUiBound = "true";
  });
}

function ensureSuggestionListOverlay(containerId, listId) {
  const container = document.getElementById(containerId);
  const list = document.getElementById(listId);

  if (!container || !list) {
    return;
  }

  let anchor = container.closest(".linkGroupListAnchor");
  if (!anchor) {
    anchor = document.createElement("div");
    anchor.className = "linkGroupListAnchor";

    const parent = container.parentElement;
    if (!parent) {
      return;
    }

    parent.insertBefore(anchor, container);
    anchor.appendChild(container);
  }

  if (list.parentElement !== anchor) {
    anchor.appendChild(list);
  }

  // Force overlay behavior so opening suggestions never changes modal height.
  list.dataset.overlayDropdown = "true";
}

function loadEditModalEvents() {
  const modalContent = document.getElementById("modal-content");
  if (modalContent && document.getElementById("shortlinkEdit")) {
    modalContent.classList.add("shortlinkEditModal");
  }

  ensureSuggestionListOverlay("editGroupsContainer", "groupList");

  const tagManager = new TagGroupManager("tag", {
    inputId: "editLinkTags",
    containerId: "editTagsContainer",
    hiddenInputId: "hiddenEditTags",
    listId: "tagList",
    containerClass: "editTagContainer",
    fetchEndpoint: "/getTags",
  });

  const groupManager = new TagGroupManager("group", {
    inputId: "editLinkGroups",
    containerId: "editGroupsContainer",
    hiddenInputId: "hiddenEditGroups",
    listId: "groupList",
    containerClass: "editGroupContainer",
    fetchEndpoint: "/getGroups",
  });

  tagManager.loadPresetItems(".presetTag");
  groupManager.loadPresetItems(".presetGroup");

  validateField("editLinkTitle", "/getTitle", "warningTitle", (title) => ({
    title,
  }));

  const shortlinkValidation = setupShortlinkConflictValidation({
    fieldId: "shortlinkEdit",
    warningId: "warning",
    endpoint: "/getShortlink",
    includeCurrentLinkId: () =>
      document.querySelector('#editLinkForm input[name="id"]')?.value || "",
    allowAliasOverwrite: false,
  });

  const editForm = document.getElementById("editLinkForm");
  if (editForm) {
    editForm.addEventListener("submit", (event) => {
      if (shortlinkValidation.state.hasConflict) {
        event.preventDefault();
        document.getElementById("shortlinkEdit")?.focus();
        createSnackbar(
          getUiText(
            "modals.links.shortlink_exists",
            "Shortlink already exists",
          ),
          "error",
        );
        return;
      }

      tagManager.finalizeOnSubmit();
      groupManager.finalizeOnSubmit();
    });
  }

  attachUrlValidationOnSubmit("editLinkForm", "editLinkURL");
}

function loadCreateModalEvents() {
  ensureSuggestionListOverlay("linkGroupsContainer", "groupList");

  const tagManager = new TagGroupManager("tag", {
    inputId: "linkTags",
    containerId: "linkTagsContainer",
    hiddenInputId: "hiddenTags",
    listId: "tagList",
    containerClass: "createTagContainer",
    fetchEndpoint: "/getTags",
  });

  const groupManager = new TagGroupManager("group", {
    inputId: "linkGroups",
    containerId: "linkGroupsContainer",
    hiddenInputId: "hiddenGroups",
    listId: "groupList",
    containerClass: "createGroupContainer",
    fetchEndpoint: "/getGroups",
  });

  validateField("linkTitle", "/getTitle", "warningTitle", (title) => ({
    title,
  }));

  const shortlinkValidation = setupShortlinkConflictValidation({
    fieldId: "shortlinkCreate",
    warningId: "warning",
    endpoint: "/getShortlink",
    allowAliasOverwrite: true,
  });

  const createForm = document.getElementById("createLinkForm");
  if (createForm) {
    createForm.addEventListener("submit", async (event) => {
      event.preventDefault();

      if (createForm.dataset.submitting === "true") {
        return;
      }

      const isAliasConflict =
        shortlinkValidation.state.conflictType === "alias";
      const allowOverwrite =
        isAliasConflict && shortlinkValidation.isOverwriteEnabled();

      if (shortlinkValidation.state.hasConflict && !allowOverwrite) {
        document.getElementById("shortlinkCreate")?.focus();

        const conflictMessage = isAliasConflict
          ? getUiText(
              "modals.links.enable_alias_overwrite_first",
              "Enable alias overwrite first to use this shortlink",
            )
          : getUiText(
              "modals.links.shortlink_primary_conflict",
              "This shortlink is already active on another link",
            );

        createSnackbar(conflictMessage, "error");
        return;
      }

      const urlInput = document.getElementById("linkURL");
      if (urlInput && !isValidHttpUrl(urlInput.value || "")) {
        urlInput.focus();
        createSnackbar(
          getUiText(
            "js.common.enter_valid_http_url",
            "Please enter a valid http/https URL",
          ),
          "error",
        );
        return;
      }

      tagManager.finalizeOnSubmit();
      groupManager.finalizeOnSubmit();

      const submitButton = document.getElementById("submitLink");
      const originalButtonText = submitButton?.innerHTML || "";
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = getUiText(
          "js.one_time.creating",
          "Creating...",
        );
      }

      createForm.dataset.submitting = "true";

      try {
        const formData = new FormData(createForm);
        formData.set(
          "overwrite_alias",
          shortlinkValidation.isOverwriteEnabled() ? "1" : "0",
        );

        const response = await fetch("/createLink", {
          method: "POST",
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
          body: formData,
        });

        const result = await response.json().catch(() => ({}));
        if (!response.ok || result.success !== true) {
          throw new Error(
            result.message ||
              getUiText(
                "js.one_time.failed_create_link",
                "Failed to create link",
              ),
          );
        }

        changes = false;
        document.getElementById("modalContainer")?.remove();
        unlockBodyScroll();

        createSnackbar(
          getUiText(
            "js.one_time.link_created_successfully",
            "Link created successfully",
          ),
          "success",
        );
        refreshCurrentPage(0);
      } catch (error) {
        const message =
          error?.message ||
          getUiText("js.one_time.failed_create_link", "Failed to create link");

        createSnackbar(message, "error");

        const warningElement = document.getElementById("warning");
        const shortlinkInput = document.getElementById("shortlinkCreate");
        const normalizedMessage = String(message).toLowerCase();

        if (
          warningElement &&
          shortlinkInput &&
          (normalizedMessage.includes("shortlink") ||
            normalizedMessage.includes("alias"))
        ) {
          warningElement.classList.add("active");
          shortlinkInput.focus();
        }
      } finally {
        delete createForm.dataset.submitting;
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.innerHTML = originalButtonText;
        }
      }
    });
  }
}

function isValidHttpUrl(value) {
  if (typeof value !== "string") {
    return false;
  }

  try {
    const parsed = new URL(value.trim());
    return parsed.protocol === "http:" || parsed.protocol === "https:";
  } catch {
    return false;
  }
}

function attachUrlValidationOnSubmit(formId, urlInputId) {
  const form = document.getElementById(formId);
  const urlInput = document.getElementById(urlInputId);

  if (!form || !urlInput || form.dataset.urlValidationAttached === "true") {
    return;
  }

  form.dataset.urlValidationAttached = "true";

  form.addEventListener("submit", function (event) {
    const value = (urlInput.value || "").trim();
    if (!isValidHttpUrl(value)) {
      event.preventDefault();
      urlInput.focus();
      createSnackbar(
        getUiText(
          "js.common.enter_valid_http_url",
          "Please enter a valid http/https URL",
        ),
        "error",
      );
    }
  });
}

function loadDeleteEvents() {
  const idEl = document.getElementById("deleteLinkId");
  const compEl = document.getElementById("deleteComp");
  const deleteBtn = document.getElementById("deleteTrue");
  const keepBtn = document.getElementById("deleteFalse");
  const modalContent = document.getElementById("modal-content");
  if (!idEl || !compEl || !deleteBtn || !keepBtn) return;

  const id = idEl.value;
  const url = "/deleteLink";
  const comp = compEl.value;
  let isSubmitting = false;

  const closeDeleteModal = () => {
    document.getElementById("modalContainer")?.remove();
    unlockBodyScroll();
    restoreModalFocus();
  };

  const setButtonsDisabled = (disabled) => {
    deleteBtn.disabled = disabled;
    keepBtn.disabled = disabled;
    deleteBtn.setAttribute("aria-busy", disabled ? "true" : "false");
  };

  // Delete confirmation is not an "unsaved changes" workflow.
  changes = false;

  // Safe-by-default: keep focus on the non-destructive action.
  requestAnimationFrame(() => {
    keepBtn.focus({ preventScroll: true });
  });

  keepBtn.addEventListener("click", () => {
    if (isSubmitting) {
      return;
    }
    closeDeleteModal();
  });

  const isInteractiveDeleteTarget = (target) => {
    if (!target || typeof target.closest !== "function") {
      return false;
    }

    return Boolean(
      target.closest("button, a, input, textarea, select, [role='button']"),
    );
  };

  modalContent?.addEventListener("keydown", (event) => {
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

    // Do not override explicit focused controls (including delete button).
    if (isInteractiveDeleteTarget(event.target)) {
      return;
    }

    event.preventDefault();
    keepBtn.click();
  });

  deleteBtn.addEventListener("click", async () => {
    if (isSubmitting) {
      return;
    }

    isSubmitting = true;
    setButtonsDisabled(true);

    try {
      const response = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ delete: true, id, comp }),
      });

      if (!response.ok || response.redirected) {
        throw new Error("Delete request failed");
      }

      const raw = await response.text();
      let payload = null;
      if (raw && raw.trim().length > 0) {
        try {
          payload = JSON.parse(raw);
        } catch {
          payload = null;
        }
      }

      if (payload && payload.status && payload.status !== "success") {
        throw new Error("Delete was not successful");
      }

      closeDeleteModal();

      const snackbarMessages = {
        links: { element: `link${id}`, message: snackbar.deleteLink },
        visitors: { element: `visitor${id}`, message: snackbar.deleteVisitor },
        users: { element: `user${id}`, message: snackbar.deleteUser },
        groups: { element: `group${id}`, message: snackbar.deleteGroup },
      };

      const config = snackbarMessages[comp];
      if (config) {
        const element = document.getElementById(config.element);
        if (element) {
          element.remove();
        }

        if (comp === "groups") {
          const undoGroupMsg = (
            snackbar.multiSelectDeleteGroups ||
            "%link_amount% group(s) deleted. Click Undo to restore."
          ).replace("%link_amount%", 1);
          const undoUrl = `?comp=groups&ids=${id}`;
          queueSnackbarAfterReload(undoGroupMsg, undoUrl);
          refreshCurrentPage(0);
        } else {
          const undoPath = comp === "links" ? `?comp=${comp}&id=${id}` : null;
          queueSnackbarAfterReload(
            getUiText("js.common.action_completed", "Action completed"),
            undoPath,
          );
          refreshCurrentPage(0);
        }
      }
    } catch (err) {
      createSnackbar(getUiText("js.common.action_failed", "Action failed"));
    } finally {
      isSubmitting = false;
      if (document.body.contains(deleteBtn)) {
        setButtonsDisabled(false);
      }
    }
  });
}

function loadQrEvents() {
  const copyBtn = document.getElementById("copyQR");
  const qrCode = document.getElementById("qrCode");
  if (!copyBtn || !qrCode) return;

  copyBtn.addEventListener("click", async () => {
    const image = qrCode.src;

    try {
      const response = await fetch(image);
      const blob = await response.blob();
      if (typeof ClipboardItem === "undefined") {
        createSnackbar(
          getUiText(
            "js.common.copy_not_supported_browser",
            "Copy not supported on this browser",
          ),
        );
        return;
      }
      const item = new ClipboardItem({ "image/png": blob });
      await navigator.clipboard.write([item]);
      createSnackbar(snackbar.copyQRImage);
    } catch (err) {
      createSnackbar(
        getUiText("js.common.could_not_copy_qr", "Could not copy QR image"),
      );
    }
  });
}

function loadStatusEvents() {
  const statusCheck = document.getElementById("statusCheck");
  const statusCheckId = document.getElementById("statusCheckId");
  const statusText = document.getElementById("statusText");
  if (!statusCheck || !statusCheckId || !statusText) return;

  statusCheck.addEventListener("change", async () => {
    const data = {
      id: statusCheckId.value,
      status: statusCheck.checked ? 1 : 0,
    };

    try {
      const response = await fetch("/switchLinkStatus", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ data }),
      });

      const rawResult = await response.text();
      let statusValue = "";
      try {
        const parsedResult = JSON.parse(rawResult);
        statusValue = String(parsedResult?.status || "")
          .trim()
          .toLowerCase();
      } catch {
        statusValue = String(rawResult || "")
          .trim()
          .toLowerCase();
      }

      createSnackbar(snackbar.switchStatus);
      statusText.innerHTML =
        statusValue === "active"
          ? getUiText("links.active", "Active")
          : statusValue === "archived"
            ? getUiText("links.archived", "Archived")
            : statusValue;
      refreshCurrentPage();
    } catch (err) {
      createSnackbar(
        getUiText("js.common.status_update_failed", "Status update failed"),
      );
    }
  });
}

// ============================================================================
// MULTI-SELECT FUNCTIONALITY
// ============================================================================

function syncSelectionModeClasses() {
  const multiSelectBar = document.getElementById("multiSelect");
  const selectionModeHeader = document.getElementById("selectionModeHeader");
  const selectAllEl = document.getElementById("selectAll");
  const wasSelectionMode = document.body.classList.contains("selection-mode");

  function closeAllOpenAccordions() {
    document
      .querySelectorAll(
        ".linkSwitch.open, .userSwitch.open, .visitorSwitch.open, .container.open",
      )
      .forEach((container) => container.classList.remove("open"));
  }

  if (isSelectionModeActive()) {
    if (!wasSelectionMode) {
      closeAllOpenAccordions();
    }

    showMultiSelectBar(multiSelectBar, selectionModeHeader);
    document.body.classList.add("selection-mode");
    document.querySelectorAll(".container").forEach((container) => {
      container.classList.add("formActive");
    });
    return;
  }

  hideMultiSelectBar(multiSelectBar, selectionModeHeader);
  document.body.classList.remove("selection-mode");
  document.querySelectorAll(".container").forEach((container) => {
    container.classList.remove("formActive");
  });
  if (selectAllEl) {
    selectAllEl.checked = false;
  }
  clearMultiGroupSelection();
}

function multiSelect(id, event) {
  if (event) {
    event.stopPropagation();
  }

  const clickedCheckbox =
    event && event.target && event.target.closest
      ? event.target.closest(".checkbox")
      : null;
  if (clickedCheckbox) {
    const parentContainer = clickedCheckbox.closest(".container");
    if (parentContainer?.classList.contains("open")) {
      parentContainer.classList.remove("open");
    }
  }

  id = String(id || "").split("-")[1] || String(id || "");
  const page = getCurrentPageLocation();

  const multiSelectBar = document.getElementById("multiSelect");
  const selectAllEl = document.getElementById("selectAll");
  const selectAllTextEl = document.getElementById("selectAllText");
  const totalSelectedEl = document.getElementById("totalSelected");

  if (id === "filter") {
    clearSelectionState();
    return checked;
  }

  if (id === "all") {
    const visibleCheckboxes = Array.from(document.querySelectorAll(".checkbox"))
      .filter((checkbox) => checkbox.id !== "selectAll")
      .filter((checkbox) => checkbox.offsetParent !== null);
    const visibleIds = visibleCheckboxes
      .map((checkbox) => String(checkbox.id.split("-")[1] || ""))
      .filter((visibleId) => visibleId.length > 0);
    const shouldSelectVisible = !!document.getElementById("selectAll")?.checked;

    if (isSuperAllModeEnabled()) {
      if (shouldSelectVisible) {
        visibleIds.forEach((visibleId) => {
          superAllSelection.excludedIds.delete(visibleId);
        });
      } else {
        visibleIds.forEach((visibleId) => {
          superAllSelection.excludedIds.add(visibleId);
        });
      }

      checked = shouldSelectVisible ? visibleIds : [];

      if (selectAllTextEl) {
        selectAllTextEl.innerHTML = getUiText(
          "js.search.all_results_selected",
          "All results selected",
        );
      }

      if (getSuperAllSelectedCount() <= 0) {
        clearSelectionState();
        return checked;
      }
    } else {
      if (shouldSelectVisible) {
        visibleCheckboxes.forEach((checkbox) => {
          checkbox.checked = true;
          checkbox.classList.add("active");
          const visibleId = checkbox.id.split("-")[1];
          if (!checked.includes(visibleId)) {
            checked.push(visibleId);
          }
        });
        if (selectAllTextEl) {
          selectAllTextEl.innerHTML = getUiText(
            "js.search.deselect_all",
            "Deselect all",
          );
        }
      } else {
        visibleCheckboxes.forEach((checkbox) => {
          checkbox.checked = false;
          checkbox.classList.remove("active");
        });
        checked = checked.filter(
          (selectedId) => !visibleIds.includes(String(selectedId)),
        );
        if (selectAllTextEl) {
          selectAllTextEl.innerHTML = getUiText(
            "js.search.select_all",
            "Select all",
          );
        }
      }
    }
  } else {
    const checkbox =
      document.getElementById(`checkbox-${id}`) ||
      document.getElementById(`id-${id}`);

    if (event && event.type === "click" && event.shiftKey && lastChecked) {
      const checkboxes = document.querySelectorAll(".checkbox");
      let inBetween = false;

      checkboxes.forEach((cb) => {
        const cbId = cb.id.split("-")[1];
        if (cbId === id || cbId === lastChecked) {
          inBetween = !inBetween;
        }
        if (inBetween || cbId === id || cbId === lastChecked) {
          cb.checked = true;
          if (!checked.includes(cbId)) {
            checked.push(cbId);
          }
          if (isSuperAllModeEnabled()) {
            superAllSelection.excludedIds.delete(String(cbId));
          }
        }
      });
    } else {
      const index = checked.indexOf(id);
      if (index > -1) {
        checked.splice(index, 1);
        if (isSuperAllModeEnabled()) {
          superAllSelection.excludedIds.add(String(id));
        }
        if (checkbox) {
          checkbox.checked = false;
        }
        checkbox?.classList.remove("active");
      } else {
        checked.push(id);
        if (isSuperAllModeEnabled()) {
          superAllSelection.excludedIds.delete(String(id));
        }
        if (checkbox) {
          checkbox.checked = true;
        }
        checkbox?.classList.add("active");
      }

      // Micro-pop animation on the toggled checkbox
      if (checkbox) {
        checkbox.classList.remove("cb-pop");
        void checkbox.offsetWidth;
        checkbox.classList.add("cb-pop");
        checkbox.addEventListener(
          "animationend",
          () => checkbox.classList.remove("cb-pop"),
          { once: true },
        );
      }
    }
    lastChecked = id;
  }

  checked = [...new Set((checked || []).map((value) => String(value)))];

  if (isSuperAllModeEnabled() && getSuperAllSelectedCount() <= 0) {
    clearSelectionState();
    return checked;
  }

  if (totalSelectedEl) {
    const selectedCount = isSuperAllModeEnabled()
      ? getSuperAllSelectedCount()
      : checked.length;
    totalSelectedEl.innerHTML = `${selectedCount} ${getUiText("js.search.selected", "selected")}`;
  }

  syncSelectionModeClasses();

  updateMultiSelectSummary();
  syncVisibleSelection();

  return checked;
}

function toggleSelectionForVisibleItems(forceSelect) {
  const selectAll = document.getElementById("selectAll");
  const visibleCheckboxes = Array.from(document.querySelectorAll(".checkbox"))
    .filter((checkbox) => checkbox.id !== "selectAll")
    .filter((checkbox) => checkbox.offsetParent !== null);

  const allIds = visibleCheckboxes
    .map((checkbox) => String(checkbox.id.split("-")[1] || ""))
    .filter((id) => id.length > 0);

  if (allIds.length === 0) {
    return;
  }

  const selected = new Set((checked || []).map((item) => String(item)));
  const allSelected = allIds.every((id) => selected.has(id));
  const shouldSelect =
    typeof forceSelect === "boolean" ? forceSelect : !allSelected;

  if (isSuperAllModeEnabled()) {
    allIds.forEach((id) => {
      if (shouldSelect) {
        superAllSelection.excludedIds.delete(id);
      } else {
        superAllSelection.excludedIds.add(id);
      }
    });

    checked = shouldSelect ? [...allIds] : [];

    if (selectAll) {
      selectAll.checked = shouldSelect;
    }

    if (getSuperAllSelectedCount() <= 0) {
      clearSelectionState();
      return;
    }

    syncSelectionModeClasses();
    updateMultiSelectSummary();
    syncVisibleSelection();
    return;
  }

  visibleCheckboxes.forEach((checkbox) => {
    const id = String(checkbox.id.split("-")[1] || "");
    if (!id) {
      return;
    }

    checkbox.checked = shouldSelect;
    checkbox.classList.toggle("active", shouldSelect);

    if (shouldSelect) {
      selected.add(id);
    } else {
      selected.delete(id);
    }
  });

  checked = Array.from(selected);

  if (selectAll) {
    selectAll.checked = shouldSelect;
  }

  syncSelectionModeClasses();
  updateMultiSelectSummary();
  syncVisibleSelection();
}

function setMobileMultiDropdownOpenState(isOpen) {
  if (!window.matchMedia || !window.matchMedia("(max-width: 768px)").matches) {
    document.body.classList.remove("mobile-multi-dropdown-open");
    return;
  }

  document.body.classList.toggle("mobile-multi-dropdown-open", !!isOpen);
}

let multiSelectPositionTicking = false;

function getMultiSelectStickyTopOffset() {
  if (window.matchMedia && window.matchMedia("(max-width: 768px)").matches) {
    return document.body.classList.contains("mobile-nav-hidden") ? 0 : 60;
  }

  // Tablet range (769px–1100px): header is 60px, same as mobile header
  if (window.matchMedia && window.matchMedia("(max-width: 1100px)").matches) {
    return 60;
  }

  return 70;
}

function clearMultiSelectFloatingPosition() {
  const multiSelectBar = document.getElementById("multiSelect");
  multiSelectBar?.style.removeProperty("--multi-select-live-top");
  document.body.style.removeProperty("--multi-select-reserve-space");
}

function updateMultiSelectFloatingPosition() {
  const multiSelectBar = document.getElementById("multiSelect");
  if (!multiSelectBar || !multiSelectBar.classList.contains("active")) {
    clearMultiSelectFloatingPosition();
    return;
  }

  const stickyTop = getMultiSelectStickyTopOffset();
  const searchContainer = document.querySelector(".searchContainer");
  const desiredTop = searchContainer
    ? Math.round(searchContainer.getBoundingClientRect().bottom + 8)
    : stickyTop;
  const clampedTop = Math.max(stickyTop, desiredTop);

  multiSelectBar.style.setProperty(
    "--multi-select-live-top",
    `${clampedTop}px`,
  );

  const reserveHeight = Math.max(
    46,
    Math.ceil(multiSelectBar.getBoundingClientRect().height || 0),
  );
  document.body.style.setProperty(
    "--multi-select-reserve-space",
    `${reserveHeight + 8}px`,
  );
}

function scheduleMultiSelectFloatingPositionUpdate() {
  if (multiSelectPositionTicking) {
    return;
  }

  multiSelectPositionTicking = true;
  requestAnimationFrame(() => {
    updateMultiSelectFloatingPosition();
    multiSelectPositionTicking = false;
  });
}

let multiDropdownDebounceTimer = null;
let multiDropdownRequestId = 0;
let multiDropdownAbortController = null;
const multiActionSelections = {
  tagSync: [],
  groupSync: [],
  tagAdd: [],
  tagDel: [],
  groupAdd: [],
  groupDel: [],
  roleSet: [],
};
const multiActionSyncDraft = {
  tagSync: {},
  groupSync: {},
};
let activeMultiAction = "";
let multiActionScope = "union";

function getMultiActionSelectionPopulationCount() {
  if (isSuperAllModeEnabled()) {
    return Math.max(0, getSuperAllSelectedCount());
  }

  return Math.max(0, Array.isArray(checked) ? checked.length : 0);
}

function isDropdownMultiAction(action) {
  return [
    "tagSync",
    "groupSync",
    "tagAdd",
    "tagDel",
    "groupAdd",
    "groupDel",
    "roleSet",
  ].includes(action);
}

function getRoleLabel(roleKey) {
  const normalized = String(roleKey || "")
    .trim()
    .toLowerCase();
  if (!normalized) {
    return getUiText("users.role_unknown", "Unknown");
  }

  const fallbackByRole = {
    viewer: "Viewer",
    limited: "Limited",
    user: "User",
    admin: "Admin",
    superadmin: "Superadmin",
  };

  return getUiText(
    `search.role_${normalized}`,
    fallbackByRole[normalized] || normalized,
  );
}

function isSyncMultiAction(action) {
  return action === "tagSync" || action === "groupSync";
}

function isRemoveMultiAction(action) {
  return action === "tagDel" || action === "groupDel";
}

function invalidateMultiDropdownRequests() {
  clearTimeout(multiDropdownDebounceTimer);
  multiDropdownDebounceTimer = null;
  multiDropdownRequestId += 1;

  if (multiDropdownAbortController) {
    multiDropdownAbortController.abort();
    multiDropdownAbortController = null;
  }
}

function updateMultiActionScopeUI(action) {
  const toggle = document.getElementById("multiActionScopeToggle");
  if (!toggle) {
    return;
  }

  const show = isRemoveMultiAction(action) && !isSyncMultiAction(action);
  toggle.classList.toggle("active", show);

  toggle.querySelectorAll(".multiActionScopeBtn").forEach((btn) => {
    const scope = btn.getAttribute("data-scope") || "union";
    btn.classList.toggle("active", show && scope === multiActionScope);
  });
}

function normalizeMultiChoice(value) {
  return String(value || "").trim();
}

function clearMultiSyncDraft(action = "") {
  if (
    action &&
    Object.prototype.hasOwnProperty.call(multiActionSyncDraft, action)
  ) {
    multiActionSyncDraft[action] = {};
    return;
  }

  Object.keys(multiActionSyncDraft).forEach((key) => {
    multiActionSyncDraft[key] = {};
  });
}

function normalizeMultiSyncState(selectedCount) {
  const totalSelected = getMultiActionSelectionPopulationCount();

  if (selectedCount <= 0) {
    return "none";
  }

  if (totalSelected > 0 && selectedCount >= totalSelected) {
    return "all";
  }

  return "some";
}

function getMultiSyncDraftEntry(action, title, selectedCount = 0) {
  if (!isSyncMultiAction(action)) {
    return null;
  }

  const normalizedTitle = normalizeMultiChoice(title);
  if (!normalizedTitle) {
    return null;
  }

  const key = normalizedTitle.toLowerCase();
  const draft = multiActionSyncDraft[action];
  if (!draft[key]) {
    const initialState = normalizeMultiSyncState(selectedCount);
    draft[key] = {
      title: normalizedTitle,
      initialState,
      currentState: initialState,
    };
  } else if (draft[key].title !== normalizedTitle) {
    draft[key].title = normalizedTitle;
  }

  return draft[key];
}

function setMultiSyncDraftState(action, title, nextState) {
  if (!isSyncMultiAction(action)) {
    return;
  }

  if (!["none", "some", "all"].includes(nextState)) {
    return;
  }

  const entry = getMultiSyncDraftEntry(action, title, 0);
  if (!entry) {
    return;
  }

  entry.currentState = nextState;
}

function getMultiSyncChanges(action) {
  if (!isSyncMultiAction(action)) {
    return { add: [], remove: [], changedCount: 0 };
  }

  const draft = multiActionSyncDraft[action] || {};
  const add = [];
  const remove = [];

  Object.values(draft).forEach((entry) => {
    if (!entry || !entry.title) {
      return;
    }

    const initialState = entry.initialState || "none";
    const currentState = entry.currentState || "none";
    if (initialState === currentState) {
      return;
    }

    if (currentState === "all") {
      add.push(entry.title);
      return;
    }

    if (currentState === "none") {
      remove.push(entry.title);
    }
  });

  const uniqueValues = (values) =>
    values.filter(
      (value, index, arr) =>
        arr.findIndex((item) => item.toLowerCase() === value.toLowerCase()) ===
        index,
    );

  const addUnique = uniqueValues(add);
  const removeUnique = uniqueValues(remove);
  return {
    add: addUnique,
    remove: removeUnique,
    changedCount: addUnique.length + removeUnique.length,
  };
}

function getMultiSelectionValues(action) {
  if (!isDropdownMultiAction(action)) {
    return [];
  }

  if (isSyncMultiAction(action)) {
    const syncChanges = getMultiSyncChanges(action);
    return [...syncChanges.add, ...syncChanges.remove];
  }

  return (multiActionSelections[action] || [])
    .map((item) => normalizeMultiChoice(item))
    .filter(
      (item, index, arr) =>
        item.length > 0 &&
        arr.findIndex((val) => val.toLowerCase() === item.toLowerCase()) ===
          index,
    );
}

function toggleMultiSelectionValue(action, value, shouldSelect) {
  if (!isDropdownMultiAction(action) || isSyncMultiAction(action)) {
    return;
  }

  const normalizedValue = normalizeMultiChoice(value);
  if (!normalizedValue) {
    return;
  }

  const values = getMultiSelectionValues(action);
  const existingIndex = values.findIndex(
    (item) => item.toLowerCase() === normalizedValue.toLowerCase(),
  );

  if (shouldSelect && existingIndex === -1) {
    values.push(normalizedValue);
  }

  if (!shouldSelect && existingIndex > -1) {
    values.splice(existingIndex, 1);
  }

  multiActionSelections[action] = values;
}

function updateMultiDropdownApplyState() {
  const applyButton = document.getElementById("multiActionDropdownApply");
  if (!applyButton) {
    return;
  }

  const count = isSyncMultiAction(activeMultiAction)
    ? getMultiSyncChanges(activeMultiAction).changedCount
    : getMultiSelectionValues(activeMultiAction).length;
  const isDisabled = count === 0;
  applyButton.disabled = isDisabled;
  applyButton.classList.toggle("disabled", isDisabled);

  const label = applyButton.querySelector(".multiButtonLabel");
  if (label) {
    const base = getUiText("search.apply", "Apply");
    label.textContent = count > 0 ? `${base} (${count})` : base;
  }

  updateMultiDropdownSummary(activeMultiAction);
}

function updateMultiDropdownSummary(action, totalOptions = null) {
  const summary = document.getElementById("multiActionDropdownSummary");
  if (!summary) {
    return;
  }

  /* Also update the header changes badge */
  const headerChanges = document.getElementById("selectionModeChanges");
  const headerChangesCount = document.getElementById(
    "selectionModeChangesCount",
  );

  if (isSyncMultiAction(action)) {
    const syncChanges = getMultiSyncChanges(action);
    const totalPart =
      Number.isFinite(totalOptions) && totalOptions > 0
        ? `/${totalOptions}`
        : "";
    summary.textContent = `${syncChanges.changedCount}${totalPart} ${getUiText("js.multi.pending_changes", "changes")}`;

    if (headerChanges && headerChangesCount) {
      if (syncChanges.changedCount > 0) {
        headerChangesCount.textContent = syncChanges.changedCount;
        headerChanges.style.display = "";
      } else {
        headerChanges.style.display = "none";
      }
    }
    return;
  }

  if (headerChanges) {
    headerChanges.style.display = "none";
  }

  const selectedCount = getMultiSelectionValues(action).length;
  const selectedLabel = getUiText("js.search.selected", "selected");

  const totalPart =
    Number.isFinite(totalOptions) && totalOptions > 0 ? `/${totalOptions}` : "";

  const scopePart = isRemoveMultiAction(action)
    ? ` - ${
        multiActionScope === "intersection"
          ? getUiText("js.multi.scope_all", "All selected")
          : getUiText("js.multi.scope_any", "Any selected")
      }`
    : "";

  summary.textContent = `${selectedCount}${totalPart} ${selectedLabel}${scopePart}`;
}

function updateMultiCreateGroupButton(action) {
  const createButton = document.getElementById(
    "multiActionDropdownCreateGroup",
  );
  const footer = document.querySelector(".multiActionDropdownFooter");

  if (!createButton) {
    return;
  }

  const shouldShow = action === "groupAdd" || action === "groupSync";
  createButton.hidden = !shouldShow;
  createButton.disabled = !shouldShow;
  createButton.setAttribute("aria-hidden", shouldShow ? "false" : "true");
  footer?.classList.toggle("has-create", shouldShow);
}

function openCreateGroupModalFromMultiDropdown() {
  const dropdownSearch = document.getElementById("multiActionDropdownSearch");
  const initialTitle = normalizeMultiChoice(dropdownSearch?.value || "");

  closeMultiActionDropdown();
  createModal("/createModal?comp=groups");

  if (!initialTitle) {
    return;
  }

  let attempts = 0;
  const maxAttempts = 30;

  const syncInitialValue = () => {
    const titleInput = document.getElementById("editLinkTitle");
    if (!titleInput) {
      attempts += 1;
      if (attempts < maxAttempts) {
        requestAnimationFrame(syncInitialValue);
      }
      return;
    }

    titleInput.value = initialTitle;
    titleInput.focus();
    titleInput.setSelectionRange(initialTitle.length, initialTitle.length);
  };

  requestAnimationFrame(syncInitialValue);
}

function getMultiActionTitle(action) {
  switch (action) {
    case "delete":
      return getUiText("search.delete", "Delete");
    case "archive":
      return getUiText("search.archive", "Archive");
    case "tagSync":
      return getUiText("search.manage_tags", "Manage tags");
    case "groupSync":
      return getUiText("search.manage_groups", "Manage Groups");
    case "tagAdd":
      return getUiText("search.add_tag", "Add tag");
    case "tagDel":
      return getUiText("search.delete_tag", "Delete tag");
    case "groupAdd":
      return getUiText("search.add_to_group", "Add to group");
    case "groupDel":
      return getUiText("search.delete_group", "Delete group");
    case "roleSet":
      return getUiText("users.set_role", "Set role");
    case "logout":
      return getUiText("users.sign_out_everywhere", "Sign out everywhere");
    default:
      return getUiText("search.apply", "Apply");
  }
}

function closeMultiActionDropdown() {
  const dropdown = document.getElementById("multiActionDropdown");
  const list = document.getElementById("multiActionDropdownList");
  const search = document.getElementById("multiActionDropdownSearch");
  const error = document.getElementById("multiActionDropdownError");

  invalidateMultiDropdownRequests();

  if (dropdown) {
    dropdown.classList.remove("active");
    dropdown.setAttribute("aria-hidden", "true");
  }

  if (list) {
    list.innerHTML = "";
  }

  if (search) {
    search.value = "";
  }

  if (error) {
    error.textContent = "";
    error.classList.remove("active");
  }

  document.querySelectorAll(".multiActionBtn.active").forEach((btn) => {
    btn.classList.remove("active");
  });

  activeMultiAction = "";
  updateMultiCreateGroupButton(activeMultiAction);
  updateMultiActionScopeUI(activeMultiAction);
  setMobileMultiDropdownOpenState(false);
  syncSelectionPopoverBodyClass();
  hideTooltipPortal();
}

function clearMultiGroupSelection() {
  Object.keys(multiActionSelections).forEach((key) => {
    multiActionSelections[key] = [];
  });
  clearMultiSyncDraft();

  const selector = document.getElementById("multiSelector");
  if (selector && selector.options.length > 0) {
    selector.value = selector.options[0].value;
  }

  multiActionScope = "union";

  closeMultiActionDropdown();
  updateMultiDropdownApplyState();
}

function renderMultiActionDropdownList(data, action, query) {
  const list = document.getElementById("multiActionDropdownList");
  if (!list) {
    return;
  }

  const selectionPopulationCount = getMultiActionSelectionPopulationCount();

  updateMultiActionScopeUI(action);

  const normalizedQuery = normalizeMultiChoice(query);
  const selectedValues = getMultiSelectionValues(action);
  const selectedSet = new Set(selectedValues.map((item) => item.toLowerCase()));

  let options = Array.isArray(data) ? data : [];

  // For add/manage actions, allow creating new tag/group from the search text.
  if (
    (action === "tagAdd" ||
      action === "groupAdd" ||
      action === "tagSync" ||
      action === "groupSync") &&
    normalizedQuery.length > 0
  ) {
    const hasExactMatch = options.some(
      (item) =>
        normalizeMultiChoice(item?.title).toLowerCase() ===
        normalizedQuery.toLowerCase(),
    );

    if (!hasExactMatch) {
      options = [{ title: normalizedQuery, is_custom: true }, ...options];
    }
  }

  list.innerHTML = "";

  // Keep one delegated listener on the list and swap the active updater per render.
  list.__multiActionQuickToggleUpdate = null;
  if (list.dataset.quickToggleChangeBound !== "true") {
    list.dataset.quickToggleChangeBound = "true";
    list.addEventListener("change", (event) => {
      if (!event.target?.classList?.contains("multiActionOptionCheckbox")) {
        return;
      }

      const updater = list.__multiActionQuickToggleUpdate;
      if (typeof updater === "function") {
        updater();
      }
    });
  }

  if (options.length === 0) {
    const noCommonInAllMode =
      isRemoveMultiAction(action) &&
      multiActionScope === "intersection" &&
      selectionPopulationCount > 1;

    if (noCommonInAllMode) {
      const empty = document.createElement("div");
      empty.className = "multiActionEmpty multiActionEmptyWithAction";

      const message = document.createElement("p");
      message.className = "multiActionEmptyText";
      message.textContent = getUiText(
        "js.multi.no_common_items_all_scope",
        "No shared items in 'All' mode. Try 'Any'.",
      );

      const switchScopeBtn = document.createElement("button");
      switchScopeBtn.type = "button";
      switchScopeBtn.className = "multiActionEmptyAction";
      switchScopeBtn.textContent = getUiText(
        "js.multi.switch_to_any",
        "Switch to Any",
      );
      switchScopeBtn.addEventListener("click", () => {
        multiActionScope = "union";
        updateMultiActionScopeUI(action);
        fetchmultiDropdown(query);
      });

      empty.appendChild(message);
      empty.appendChild(switchScopeBtn);
      list.appendChild(empty);
    } else {
      const empty = document.createElement("div");
      empty.className = "multiActionEmpty";
      empty.textContent = getUiText("js.multi.no_items_to_remove", "No items");
      list.appendChild(empty);
    }

    updateMultiDropdownApplyState();
    updateMultiDropdownSummary(action, 0);
    return;
  }

  let renderedOptionsCount = 0;

  const getSyncPreviewCount = (state, baseSelectedCount) => {
    const totalSelected = selectionPopulationCount;
    const normalizedBase = Math.min(
      totalSelected,
      Math.max(0, Number(baseSelectedCount) || 0),
    );

    if (state === "all") {
      return totalSelected;
    }

    if (state === "none") {
      return 0;
    }

    return normalizedBase;
  };

  const updateSyncOptionPreview = (row, checkbox, entry, baseSelectedCount) => {
    if (!row || !checkbox || !entry) {
      return;
    }

    const state = entry.currentState || "none";
    const totalSelected = selectionPopulationCount;
    const previewCount = getSyncPreviewCount(state, baseSelectedCount);

    checkbox.checked = state === "all";
    checkbox.indeterminate = state === "some";

    row.classList.toggle("is-partial", state === "some");
    row.classList.toggle("is-dirty", entry.currentState !== entry.initialState);

    let meta = row.querySelector(".multiActionOptionMeta");
    const shouldShowMeta =
      totalSelected > 0 && (Number(baseSelectedCount) > 0 || state !== "none");

    if (shouldShowMeta) {
      if (!meta) {
        meta = document.createElement("span");
        meta.className = "multiActionOptionMeta";
        row.appendChild(meta);
      }
      meta.textContent = `${previewCount}/${totalSelected}`;
    } else if (meta) {
      meta.remove();
    }
  };

  // Add quick toggle row with one adaptive button (check all / uncheck all)
  if (options.length > 1 && action !== "roleSet") {
    const toggleRow = document.createElement("div");
    toggleRow.className = "multiActionQuickToggle";

    const toggleBtn = document.createElement("button");
    toggleBtn.type = "button";
    toggleBtn.className = "multiActionQuickBtn multiActionQuickToggleBtn";

    const applyBulkSelection = (shouldSelect) => {
      list.querySelectorAll(".multiActionOption").forEach((row) => {
        const title = normalizeMultiChoice(row.dataset.value || "");
        const checkbox = row.querySelector(".multiActionOptionCheckbox");
        if (!title || !checkbox) {
          return;
        }

        if (isSyncMultiAction(action)) {
          const baseSelectedCount = Number(
            row.dataset.initialSelectedCount || 0,
          );
          checkbox.indeterminate = false;
          checkbox.checked = shouldSelect;
          setMultiSyncDraftState(action, title, shouldSelect ? "all" : "none");
          const entry = getMultiSyncDraftEntry(
            action,
            title,
            baseSelectedCount,
          );
          updateSyncOptionPreview(row, checkbox, entry, baseSelectedCount);
          return;
        }

        if (checkbox.checked === shouldSelect) {
          return;
        }

        checkbox.checked = shouldSelect;
        toggleMultiSelectionValue(action, title, shouldSelect);
      });

      updateMultiDropdownApplyState();
    };

    const updateQuickToggleState = () => {
      const optionCheckboxes = Array.from(
        list.querySelectorAll(".multiActionOptionCheckbox"),
      );
      const total = optionCheckboxes.length;
      const selectedCount = optionCheckboxes.filter((cb) => cb.checked).length;
      const hasIndeterminate = optionCheckboxes.some((cb) => cb.indeterminate);
      const shouldSelectAll = selectedCount < total || hasIndeterminate;

      toggleBtn.dataset.mode = shouldSelectAll ? "check" : "uncheck";
      toggleBtn.textContent = shouldSelectAll
        ? getUiText("js.multi.select_all", "Select all")
        : getUiText("js.multi.deselect_all", "Deselect all");
      toggleBtn.classList.toggle("is-uncheck", !shouldSelectAll);
      toggleBtn.disabled = total === 0;
    };

    toggleBtn.addEventListener("click", () => {
      const shouldSelect = toggleBtn.dataset.mode !== "uncheck";
      applyBulkSelection(shouldSelect);
      updateQuickToggleState();
    });

    toggleRow.appendChild(toggleBtn);
    list.appendChild(toggleRow);

    list.__multiActionQuickToggleUpdate = updateQuickToggleState;
  }

  options.forEach((item) => {
    const title = normalizeMultiChoice(item?.title);
    if (!title) {
      return;
    }
    renderedOptionsCount++;

    const row = document.createElement("label");
    row.className = "multiActionOption";
    row.dataset.value = title;
    row.dataset.initialSelectedCount = String(
      Number(item?.selected_count || 0),
    );
    if (item?.is_custom) {
      row.classList.add("multiActionOptionCreate");
    }

    const checkbox = document.createElement("input");
    checkbox.type = "checkbox";
    checkbox.className = "multiActionOptionCheckbox";

    const selectedCount = Number(item?.selected_count || 0);
    let syncEntry = null;
    if (isSyncMultiAction(action)) {
      syncEntry = getMultiSyncDraftEntry(action, title, selectedCount);
      const state = syncEntry?.currentState || "none";
      checkbox.checked = state === "all";
      checkbox.indeterminate = state === "some";
    } else {
      checkbox.checked = selectedSet.has(title.toLowerCase());
    }

    const titleEl = document.createElement("span");
    titleEl.className = "multiActionOptionTitle";
    titleEl.textContent = action === "roleSet" ? getRoleLabel(title) : title;

    row.appendChild(checkbox);
    row.appendChild(titleEl);

    if (isSyncMultiAction(action)) {
      updateSyncOptionPreview(row, checkbox, syncEntry, selectedCount);
    }

    if ((action === "tagDel" || action === "groupDel") && selectedCount > 0) {
      const meta = document.createElement("span");
      meta.className = "multiActionOptionMeta";
      meta.textContent = `${selectedCount}/${selectionPopulationCount}`;
      row.appendChild(meta);
    }

    checkbox.addEventListener("change", () => {
      if (action === "roleSet") {
        const nextValues = checkbox.checked ? [title] : [];
        setMultiSelectionValues(action, nextValues);

        list.querySelectorAll(".multiActionOption").forEach((rowNode) => {
          const rowValue = normalizeMultiChoice(rowNode.dataset.value || "");
          const rowCheckbox = rowNode.querySelector(
            ".multiActionOptionCheckbox",
          );
          if (!rowCheckbox) {
            return;
          }
          rowCheckbox.checked = nextValues.includes(rowValue);
          rowCheckbox.indeterminate = false;
        });

        updateMultiDropdownApplyState();
        updateMultiDropdownSummary(action, renderedOptionsCount);
        return;
      }

      if (isSyncMultiAction(action)) {
        const entry = getMultiSyncDraftEntry(action, title, selectedCount);
        if (!entry) {
          return;
        }

        const current = entry.currentState;
        let nextState;

        if (current === "some") {
          // Partial/minus: clicking always marks this value as checked for all selected.
          nextState = "all";
        } else {
          // Checked or empty: regular two-state toggle.
          nextState = current === "all" ? "none" : "all";
        }

        setMultiSyncDraftState(action, title, nextState);
        updateSyncOptionPreview(row, checkbox, entry, selectedCount);
        updateMultiDropdownApplyState();
        return;
      }

      toggleMultiSelectionValue(action, title, checkbox.checked);
      updateMultiDropdownApplyState();
    });

    list.appendChild(row);
  });

  updateMultiDropdownApplyState();

  const quickToggleUpdater = list.__multiActionQuickToggleUpdate;
  if (typeof quickToggleUpdater === "function") {
    quickToggleUpdater();
  }

  updateMultiDropdownSummary(action, renderedOptionsCount);
}

function fetchmultiDropdown(value) {
  const selector = document.getElementById("multiSelector");
  const action = activeMultiAction || selector?.value || "";
  const query = normalizeMultiChoice(value);

  if (!isDropdownMultiAction(action)) {
    return;
  }

  clearTimeout(multiDropdownDebounceTimer);
  multiDropdownDebounceTimer = setTimeout(() => {
    const requestId = ++multiDropdownRequestId;
    const requestedAction = action;
    const requestChecked = Array.isArray(checked) ? [...checked] : [];

    if (multiDropdownAbortController) {
      multiDropdownAbortController.abort();
    }

    multiDropdownAbortController = new AbortController();

    const requestBody = {
      value: query,
      checked: requestChecked,
      type: requestedAction,
      comp: getCurrentPageLocation(),
      scope: isRemoveMultiAction(requestedAction) ? multiActionScope : "union",
    };

    if (isSuperAllModeEnabled()) {
      requestBody.superall = {
        enabled: true,
        filter:
          superAllSelection.filterSnapshot ||
          getCurrentFilterSnapshotForSuperAll(),
        excludedIds: Array.from(superAllSelection.excludedIds),
      };
    }

    fetch("/multiSelectDropdown", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      signal: multiDropdownAbortController.signal,
      body: JSON.stringify(requestBody),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      })
      .then((rawData) => {
        if (
          requestId !== multiDropdownRequestId ||
          requestedAction !== activeMultiAction
        ) {
          return;
        }

        const data =
          typeof rawData === "string" ? JSON.parse(rawData) : rawData;

        if (!Array.isArray(data)) {
          const inputError = document.getElementById(
            "multiActionDropdownError",
          );
          if (inputError) {
            inputError.textContent = String(
              data?.message ||
                getUiText(
                  "js.common.multi_select_action_failed",
                  "Multi-select action failed",
                ),
            );
            inputError.classList.add("active");
          }
          renderMultiActionDropdownList([], requestedAction, query);
          return;
        }

        renderMultiActionDropdownList(data, requestedAction, query);
      })
      .catch((error) => {
        if (error?.name === "AbortError") {
          return;
        }

        if (
          requestId !== multiDropdownRequestId ||
          requestedAction !== activeMultiAction
        ) {
          return;
        }

        renderMultiActionDropdownList([], requestedAction, query);
      })
      .finally(() => {
        if (requestId === multiDropdownRequestId) {
          multiDropdownAbortController = null;
        }
      });
  }, 160);
}

function openMultiActionDropdown(action) {
  const selector = document.getElementById("multiSelector");
  const dropdown = document.getElementById("multiActionDropdown");
  const title = document.getElementById("multiActionDropdownTitle");
  const search = document.getElementById("multiActionDropdownSearch");
  const error = document.getElementById("multiActionDropdownError");

  if (!selector || !dropdown || !isDropdownMultiAction(action)) {
    return;
  }

  setMasterMenuState(false);
  hideTooltipPortal();

  selector.value = action;
  activeMultiAction = action;
  multiActionScope = "union";
  if (isSyncMultiAction(action)) {
    clearMultiSyncDraft(action);
  }

  if (title) {
    title.textContent = getMultiActionTitle(action);
  }

  if (error) {
    error.textContent = "";
    error.classList.remove("active");
  }

  updateMultiCreateGroupButton(action);

  dropdown.classList.add("active");
  dropdown.setAttribute("aria-hidden", "false");
  setMobileMultiDropdownOpenState(true);

  document.querySelectorAll(".multiActionBtn.active").forEach((btn) => {
    btn.classList.remove("active");
  });

  const activeBtn = document.querySelector(
    `.multiActionBtn[data-action="${action}"]`,
  );
  if (activeBtn) {
    activeBtn.classList.add("active");
  }

  updateMultiActionScopeUI(action);
  updateMultiDropdownSummary(action);
  fetchmultiDropdown(search?.value || "");
  updateMultiDropdownApplyState();
  syncSelectionPopoverBodyClass();
}

function toggleMultiInputVisibility() {
  const selector = document.getElementById("multiSelector");
  if (!selector) {
    return;
  }

  if (isDropdownMultiAction(selector.value)) {
    openMultiActionDropdown(selector.value);
    return;
  }

  closeMultiActionDropdown();
}

window.toggleMultiInputVisibility = toggleMultiInputVisibility;

function confirmMulti(modalLink, anchor, event, page) {
  if (document.getElementById("modalContainer")) {
    return;
  }

  if (event?.target?.classList?.contains("closeModal")) {
    return;
  }

  _modalTriggerElement =
    document.activeElement instanceof HTMLElement
      ? document.activeElement
      : null;

  const selector = document.getElementById("multiSelector");
  const inputError = document.getElementById("multiActionDropdownError");
  const action = selector?.value || "";
  const selectedValues = getMultiSelectionValues(action);
  const selectedRole = action === "roleSet" ? selectedValues[0] || "" : "";
  const inputValue = selectedValues.join(", ");
  const syncChanges = isSyncMultiAction(action)
    ? getMultiSyncChanges(action)
    : { add: [], remove: [], changedCount: 0 };
  const selectedCount = getMultiActionSelectionPopulationCount();

  if (inputError) {
    inputError.textContent = "";
    inputError.classList.remove("active");
  }

  if (isSyncMultiAction(action) && syncChanges.changedCount === 0) {
    if (inputError) {
      inputError.textContent = getUiText(
        "js.common.no_changes_selected",
        "No changes selected",
      );
      inputError.classList.add("active");
    }
    createSnackbar(
      getUiText("js.common.no_changes_selected", "No changes selected"),
    );
    return;
  }

  if (
    isDropdownMultiAction(action) &&
    !isSyncMultiAction(action) &&
    selectedValues.length === 0
  ) {
    const emptySelectionMessage =
      action === "roleSet"
        ? getUiText("users.select_one_role", "Select one role")
        : getUiText(
            "js.common.enter_tag_or_group",
            "Please enter a tag or group",
          );

    if (inputError) {
      inputError.textContent = emptySelectionMessage;
      inputError.classList.add("active");
    }
    createSnackbar(emptySelectionMessage);
    return;
  }

  if (action === "roleSet" && selectedValues.length > 1) {
    const roleSelectionError = getUiText(
      "users.select_one_role",
      "Select one role",
    );
    if (inputError) {
      inputError.textContent = roleSelectionError;
      inputError.classList.add("active");
    }
    createSnackbar(roleSelectionError);
    return;
  }

  fetch(modalLink)
    .then((response) => response.text())
    .then((data) => {
      const selectedRole = action === "roleSet" ? selectedValues[0] || "" : "";
      const selectedRoleLabel = getRoleLabel(selectedRole);

      const messageMap = {
        delete: applyMultiEntityLabel(
          snackbar.confirmMultiSelectDelete,
          page,
        ).replace("%link_amount%", selectedCount),
        archive: getUiText(
          "js.multi.confirm_archive",
          "Archive %link_amount% selected item(s)?",
        ).replace("%link_amount%", selectedCount),
        tagSync: getUiText(
          "js.multi.confirm_tag_changes",
          "Apply tag changes to %link_amount% item(s)?",
        ).replace("%link_amount%", selectedCount),
        groupSync: getUiText(
          "js.multi.confirm_group_changes",
          "Apply group changes to %link_amount% item(s)?",
        ).replace("%link_amount%", selectedCount),
        tagAdd: applyMultiEntityLabel(snackbar.confirmMultiSelectTagAdd, page)
          .replace("%link_amount%", selectedCount)
          .replace("%tag%", inputValue),
        tagDel: applyMultiEntityLabel(snackbar.confirmMultiSelectTagDel, page)
          .replace("%link_amount%", selectedCount)
          .replace("%tag%", inputValue),
        groupAdd: applyMultiEntityLabel(
          snackbar.confirmMultiSelectGroupAdd,
          page,
        )
          .replace("%link_amount%", selectedCount)
          .replace("%tag%", inputValue),
        groupDel: applyMultiEntityLabel(
          snackbar.confirmMultiSelectGroupDel,
          page,
        )
          .replace("%link_amount%", selectedCount)
          .replace("%tag%", inputValue),
        roleSet: getUiText(
          "users.confirm_set_role",
          "Set role to {role} for {count} user(s)?",
        )
          .replace("{role}", selectedRoleLabel)
          .replace("{count}", selectedCount),
        logout: getUiText(
          "users.confirm_sign_out_everywhere_selected",
          "Sign out selected users on all devices?",
        ),
      };

      const message =
        messageMap[selector.value] ||
        getUiText(
          "js.common.action_completed_successfully",
          "Action completed successfully",
        );

      const escapeHtml = (value) =>
        String(value ?? "")
          .replaceAll("&", "&amp;")
          .replaceAll("<", "&lt;")
          .replaceAll(">", "&gt;")
          .replaceAll('"', "&quot;")
          .replaceAll("'", "&#39;");

      const previewValues = selectedValues.slice(0, 8);
      const remainingValuesCount = Math.max(0, selectedValues.length - 8);
      const previewAddValues = syncChanges.add.slice(0, 4);
      const previewRemoveValues = syncChanges.remove.slice(0, 4);
      const remainingSyncValuesCount = Math.max(
        0,
        syncChanges.changedCount -
          (previewAddValues.length + previewRemoveValues.length),
      );
      const scopeLabel = isRemoveMultiAction(action)
        ? multiActionScope === "intersection"
          ? getUiText("js.multi.scope_all", "All selected")
          : getUiText("js.multi.scope_any", "Any selected")
        : "";
      const previewLabel = getMultiActionTitle(action);

      const defaultPreviewRows = `
        <div class="confirmMultiPreviewRow">
          <span class="confirmPreviewKey">${getUiText("search.action", "Action")}</span>
          <span class="confirmPreviewValue">${escapeHtml(previewLabel)}</span>
        </div>
        <div class="confirmMultiPreviewRow">
          <span class="confirmPreviewKey">${getUiText("js.search.selected", "Selected")}</span>
          <span class="confirmPreviewValue">${selectedCount}</span>
        </div>
      `;

      const syncRows = isSyncMultiAction(action)
        ? `
          <div class="confirmMultiPreviewRow">
            <span class="confirmPreviewKey">${getUiText("js.multi.add_count", "Add")}</span>
            <span class="confirmPreviewValue">${syncChanges.add.length}</span>
          </div>
          <div class="confirmMultiPreviewRow">
            <span class="confirmPreviewKey">${getUiText("js.multi.remove_count", "Remove")}</span>
            <span class="confirmPreviewValue">${syncChanges.remove.length}</span>
          </div>
        `
        : "";

      const scopeRow =
        !isSyncMultiAction(action) && scopeLabel
          ? `<div class="confirmMultiPreviewRow"><span class="confirmPreviewKey">${getUiText("search.scope", "Scope")}</span><span class="confirmPreviewValue">${escapeHtml(scopeLabel)}</span></div>`
          : "";

      const syncChips = isSyncMultiAction(action)
        ? `<div class="confirmMultiPreviewChips">${previewAddValues
            .map(
              (item) =>
                `<span class="confirmPreviewChip add">+ ${escapeHtml(item)}</span>`,
            )
            .join("")}${previewRemoveValues
            .map(
              (item) =>
                `<span class="confirmPreviewChip remove">- ${escapeHtml(item)}</span>`,
            )
            .join("")}${
            remainingSyncValuesCount > 0
              ? `<span class="confirmPreviewChip more">+${remainingSyncValuesCount}</span>`
              : ""
          }</div>`
        : "";

      const defaultChips =
        !isSyncMultiAction(action) && previewValues.length > 0
          ? `<div class="confirmMultiPreviewChips">${previewValues
              .map(
                (item) =>
                  `<span class="confirmPreviewChip">${escapeHtml(action === "roleSet" ? getRoleLabel(item) : item)}</span>`,
              )
              .join("")}${
              remainingValuesCount > 0
                ? `<span class="confirmPreviewChip more">+${remainingValuesCount}</span>`
                : ""
            }</div>`
          : "";

      const previewHtml = `
        <div class="confirmMultiPreview">
          ${defaultPreviewRows}
          ${syncRows}
          ${scopeRow}
          ${isSyncMultiAction(action) ? syncChips : defaultChips}
        </div>
      `;

      const isDestructiveAction = [
        "delete",
        "archive",
        "tagDel",
        "groupDel",
      ].includes(action);

      const confirmActionClass = isDestructiveAction
        ? "confirmDangerBtn"
        : "confirmSafeBtn";

      const cancelBtnClass = isDestructiveAction
        ? "confirmSafeBtn"
        : "confirmSecondaryBtn";

      const cancelBtnLabel = isDestructiveAction
        ? getUiText("js.common.no", "No")
        : getUiText("js.common.cancel", "Cancel");

      const confirmIconName = isDestructiveAction ? "warning_amber" : "info";

      const confirmActionLabelByType = {
        delete: getUiText("modals.delete.actions.delete", "Yes, delete"),
        archive: getUiText("search.archive", "Archive"),
        tagDel: getUiText("search.delete_tag", "Delete tag"),
        groupDel: getUiText("search.delete_group", "Delete group"),
        tagAdd: getUiText("search.add_tag", "Add tag"),
        groupAdd: getUiText("search.add_to_group", "Add to group"),
        tagSync: getUiText("search.apply", "Apply"),
        groupSync: getUiText("search.apply", "Apply"),
        roleSet: getUiText("users.apply_role", "Apply role"),
        logout: getUiText("users.sign_out_everywhere", "Sign out everywhere"),
      };

      const confirmActionLabel =
        confirmActionLabelByType[action] || getUiText("search.apply", "Apply");

      const html = `
        <div id="modalContainer" class="modal">
          <div class="modalBackground closeModal"></div>
          <div class="modal-content modal-popup" id="modal-content">
            <div class="modalWindowControls modalWindowControlsPopup">
              <button type="button" class="closeModal modalWindowControl crossIcon material-icons" aria-label="Close modal">close</button>
            </div>
            <div class="confirmMulti deleteConfirmModal">
              <div class="deleteConfirmIcon${isDestructiveAction ? "" : " deleteConfirmIconProceed"}" aria-hidden="true">
                <i class="material-icons">${confirmIconName}</i>
              </div>
              <h3 class="deleteConfirmTitle">${escapeHtml(getUiText("js.common.confirmation", "Please confirm"))}</h3>
              <p class="deleteConfirmBody">${escapeHtml(message)}</p>
              ${previewHtml}
              <div class="confirmButtons modal-footer deleteConfirmActions">
                <button class="confirmButton deleteBtn ${cancelBtnClass} closeModal" id="multiConfirmCancel">${escapeHtml(cancelBtnLabel)}</button>
                <button class="confirmButton deleteBtn ${confirmActionClass}" id="multiConfirmSubmit">${escapeHtml(confirmActionLabel)}</button>
              </div>
            </div>
          </div>
        </div>
      `;

      document.body.insertAdjacentHTML("beforeend", html);
      lockBodyScroll();

      const modalContent = document.getElementById("modal-content");
      const submitButton = document.getElementById("multiConfirmSubmit");
      const cancelButton = document.getElementById("multiConfirmCancel");

      _modalFocusTrap = modalContent ? trapFocus(modalContent) : null;

      requestAnimationFrame(() => {
        cancelButton?.focus({ preventScroll: true });
      });

      submitButton?.addEventListener("click", () => {
        submitMulti(page);
      });

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

      modalContent?.addEventListener("keydown", handleEnterDefault);

      document.querySelectorAll(".closeModal").forEach((close) => {
        close.addEventListener("click", () => {
          const container = document.getElementById("modalContainer");
          if (container) {
            modalContent?.removeEventListener("keydown", handleEnterDefault);
            container.remove();
            unlockBodyScroll();
            restoreModalFocus();
          }
        });
      });
    });
}

function submitMulti(page) {
  const modal = document.getElementById("modalContainer");
  if (modal) modal.remove();
  unlockBodyScroll();
  restoreModalFocus();

  const selector = document.getElementById("multiSelector");
  const inputError = document.getElementById("multiActionDropdownError");
  const action = selector?.value || "";
  const selectedValues = getMultiSelectionValues(action);
  const inputValue = selectedValues.join(", ");
  const syncChanges = isSyncMultiAction(action)
    ? getMultiSyncChanges(action)
    : { add: [], remove: [], changedCount: 0 };
  const isSuperAllMode = isSuperAllModeEnabled();
  const selectedCount = isSuperAllMode
    ? getSuperAllSelectedCount()
    : checked.length;

  if (inputError) {
    inputError.textContent = "";
    inputError.classList.remove("active");
  }

  if (!Array.isArray(checked) || selectedCount <= 0) {
    createSnackbar(
      getUiText("js.common.no_items_selected", "No items selected"),
    );
    return;
  }

  if (isSyncMultiAction(action) && syncChanges.changedCount === 0) {
    if (inputError) {
      inputError.textContent = getUiText(
        "js.common.no_changes_selected",
        "No changes selected",
      );
      inputError.classList.add("active");
    }
    createSnackbar(
      getUiText("js.common.no_changes_selected", "No changes selected"),
    );
    return;
  }

  if (
    isDropdownMultiAction(action) &&
    !isSyncMultiAction(action) &&
    selectedValues.length === 0
  ) {
    const emptySelectionMessage =
      action === "roleSet"
        ? getUiText("users.select_one_role", "Select one role")
        : getUiText(
            "js.common.enter_tag_or_group",
            "Please enter a tag or group",
          );

    if (inputError) {
      inputError.textContent = emptySelectionMessage;
      inputError.classList.add("active");
    }
    createSnackbar(emptySelectionMessage);
    return;
  }

  if (action === "roleSet" && selectedValues.length > 1) {
    const roleSelectionError = getUiText(
      "users.select_one_role",
      "Select one role",
    );
    if (inputError) {
      inputError.textContent = roleSelectionError;
      inputError.classList.add("active");
    }
    createSnackbar(roleSelectionError);
    return;
  }

  const multi = {
    type: action,
    ids: checked,
  };

  if (isSuperAllMode) {
    multi.superall = {
      enabled: true,
      filter:
        superAllSelection.filterSnapshot ||
        getCurrentFilterSnapshotForSuperAll(),
      excludedIds: Array.from(superAllSelection.excludedIds),
    };
  }

  if (isSyncMultiAction(action)) {
    multi.add = syncChanges.add;
    multi.remove = syncChanges.remove;
  } else if (isDropdownMultiAction(action)) {
    multi.values = selectedValues;
    // Backward compatibility with existing API handling
    if (selectedValues.length === 1) {
      multi.input = selectedValues[0];
    }
    if (action === "groupAdd") {
      multi.groups = selectedValues;
    }
  }

  fetch("/multiSelect", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ data: multi, comp: page }),
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      const responseAffectedCount = Number.parseInt(data?.affectedCount, 10);
      const actionCount = Number.isFinite(responseAffectedCount)
        ? responseAffectedCount
        : selectedCount;
      const selectedRoleLabel = getRoleLabel(selectedRole);

      const messageMap = {
        delete: applyMultiEntityLabel(snackbar.multiSelectDelete, page).replace(
          "%link_amount%",
          actionCount,
        ),
        archive: getUiText(
          "js.multi.archive_success",
          "%link_amount% item(s) archived",
        ).replace("%link_amount%", actionCount),
        tagSync: getUiText(
          "js.multi.updated_tags_summary",
          "Updated tags for %link_amount% item(s). +%add%, -%remove%",
        )
          .replace("%link_amount%", actionCount)
          .replace("%add%", syncChanges.add.length)
          .replace("%remove%", syncChanges.remove.length),
        groupSync: getUiText(
          "js.multi.updated_groups_summary",
          "Updated groups for %link_amount% item(s). +%add%, -%remove%",
        )
          .replace("%link_amount%", actionCount)
          .replace("%add%", syncChanges.add.length)
          .replace("%remove%", syncChanges.remove.length),
        tagAdd: applyMultiEntityLabel(snackbar.multiSelectTagAdd, page)
          .replace("%link_amount%", actionCount)
          .replace("%tag%", inputValue),
        tagDel: applyMultiEntityLabel(snackbar.multiSelectTagDel, page)
          .replace("%link_amount%", actionCount)
          .replace("%tag%", inputValue),
        groupAdd: applyMultiEntityLabel(snackbar.multiSelectGroupAdd, page)
          .replace("%link_amount%", actionCount)
          .replace("%tag%", inputValue),
        groupDel: applyMultiEntityLabel(snackbar.multiSelectGroupDel, page)
          .replace("%link_amount%", actionCount)
          .replace("%tag%", inputValue),
        roleSet: getUiText(
          "users.role_set_success",
          "Updated role to {role} for {count} user(s).",
        )
          .replace("{role}", selectedRoleLabel)
          .replace("{count}", actionCount),
        logout: getUiText(
          "common.logged_out_device_sessions",
          "Logged out {count} device session(s)",
        ).replace("{count}", actionCount),
      };

      const message =
        messageMap[action] ||
        getUiText(
          "js.common.action_completed_successfully",
          "Action completed successfully",
        );

      // Build snackbar message and optional undo URL.
      // Always queue for after reload — never create before reload (prevents flash).
      let queuedMessage = message;
      let queuedUndoUrl = null;

      // Pick up undo URL from backend response (works for ANY action type)
      if (
        Boolean(data.undoEligible) &&
        typeof data.undoUrl === "string" &&
        data.undoUrl.trim().length > 0
      ) {
        queuedUndoUrl = data.undoUrl.trim();
      }

      // Override message for specific action types with custom undo wording
      if (
        page === "groups" &&
        action === "delete" &&
        Array.isArray(data.deletedIds) &&
        data.deletedIds.length > 0
      ) {
        queuedMessage = (
          snackbar.multiSelectDeleteGroups ||
          "%link_amount% group(s) deleted. Click Undo to restore."
        ).replace("%link_amount%", data.deletedIds.length);
        if (!queuedUndoUrl) {
          queuedUndoUrl = "?comp=groups&ids=" + data.deletedIds.join(",");
        }
      } else if (
        page === "links" &&
        (action === "delete" || action === "archive")
      ) {
        queuedMessage =
          action === "delete"
            ? (
                snackbar.multiSelectDeleteLinks ||
                getUiText(
                  "js.multi.delete_undo_success",
                  "%link_amount% item(s) deleted. Click Undo to restore.",
                )
              ).replace("%link_amount%", actionCount)
            : (
                snackbar.multiSelectArchiveLinks ||
                getUiText(
                  "js.multi.archive_undo_success",
                  "%link_amount% link(s) archived. Click Undo to restore.",
                )
              ).replace("%link_amount%", actionCount);
      }

      queueSnackbarAfterReload(queuedMessage, queuedUndoUrl);
      refreshCurrentPage(0);

      clearMultiGroupSelection();
      document.querySelectorAll(".container").forEach((link) => {
        link.classList.remove("formActive");
      });
      clearSelectionState();
    })
    .catch(() => {
      createSnackbar(
        getUiText(
          "js.common.multi_select_action_failed",
          "Multi-select action failed",
        ),
      );
    });
}

document.addEventListener("DOMContentLoaded", function () {
  getCurrentPageLocation();
  updateMultiSelectSummary();

  const selector = document.getElementById("multiSelector");
  const inputError = document.getElementById("multiActionDropdownError");
  const masterCheckbox = document.getElementById("multiMasterCheckbox");
  const masterMenuButton = document.getElementById("multiMasterMenuButton");
  const masterMenu = document.getElementById("multiMasterMenu");
  const mobileSelectionQuickActions = document.getElementById(
    "mobileSelectionQuickActions",
  );
  const dropdownSearch = document.getElementById("multiActionDropdownSearch");
  const dropdownApply = document.getElementById("multiActionDropdownApply");
  const dropdownCreateGroup = document.getElementById(
    "multiActionDropdownCreateGroup",
  );

  const closeMasterMenu = () => {
    setMasterMenuState(false);
  };

  const openMasterMenu = () => {
    closeMultiActionDropdown();
    hideTooltipPortal();
    setMasterMenuState(true);
  };

  const applyMasterMenuMode = (mode) => {
    if (mode === "all-filtered") {
      enableSuperAllSelectionForLinks();
      return;
    }

    if (mode === "all") {
      if (isSuperAllLinksModeEnabled()) {
        resetSuperAllSelectionState();
      }
      toggleSelectionForVisibleItems(true);
      return;
    }

    if (mode === "none") {
      clearSelectionState();
    }
  };

  // Master checkbox: toggle all / deselect all (Gmail-style)
  if (masterCheckbox) {
    masterCheckbox.addEventListener("change", function (event) {
      event.stopPropagation();
      const shouldSelect =
        this.classList.contains("indeterminate") || this.checked;
      this.classList.remove("indeterminate");

      if (isSuperAllModeEnabled() && !shouldSelect) {
        clearSelectionState();
        return;
      }

      toggleSelectionForVisibleItems(shouldSelect);
    });
  }

  if (masterMenuButton && masterMenu) {
    masterMenuButton.addEventListener("click", function (event) {
      event.preventDefault();
      event.stopPropagation();
      if (masterMenu.classList.contains("active")) {
        closeMasterMenu();
      } else {
        openMasterMenu();
      }
    });

    masterMenu.querySelectorAll(".multiMasterMenuItem").forEach((item) => {
      item.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        applyMasterMenuMode(item.getAttribute("data-mode") || "");
        closeMasterMenu();
      });
    });
  }

  if (mobileSelectionQuickActions) {
    mobileSelectionQuickActions
      .querySelectorAll(".mobileSelectionQuickBtn[data-mode]")
      .forEach((btn) => {
        btn.addEventListener("click", function (event) {
          event.preventDefault();
          event.stopPropagation();
          applyMasterMenuMode(btn.getAttribute("data-mode") || "");
        });
      });
  }

  // Icon action buttons: wire up click handlers
  document
    .querySelectorAll(".multiActionBtn[data-action]")
    .forEach(function (btn) {
      btn.addEventListener("click", function (event) {
        const action = btn.dataset.action;
        const page = getCurrentPageLocation();

        if (!isSelectionModeActive()) {
          createSnackbar(
            getUiText("js.common.no_items_selected", "No items selected"),
          );
          return;
        }

        if (
          isDropdownMultiAction(action) &&
          btn.classList.contains("active") &&
          document
            .getElementById("multiActionDropdown")
            ?.classList.contains("active")
        ) {
          closeMultiActionDropdown();
          return;
        }

        // For direct actions: trigger confirmation directly
        if (["delete", "archive", "logout"].includes(action)) {
          if (selector) {
            selector.value = action;
          }
          closeMultiActionDropdown();
          confirmMulti("/confirmMulti", btn, event, page);
          return;
        }

        openMultiActionDropdown(action);
      });
    });

  if (selector && inputError) {
    selector.addEventListener("change", function () {
      inputError.textContent = "";
      inputError.classList.remove("active");
      updateMultiCreateGroupButton(selector.value || "");
      updateMultiDropdownApplyState();
    });
  }

  document
    .querySelectorAll(".multiActionScopeBtn[data-scope]")
    .forEach((btn) => {
      btn.addEventListener("click", function () {
        const scope = btn.getAttribute("data-scope") || "union";
        multiActionScope = scope === "intersection" ? "intersection" : "union";
        updateMultiActionScopeUI(activeMultiAction);
        updateMultiDropdownSummary(activeMultiAction);
        fetchmultiDropdown(
          document.getElementById("multiActionDropdownSearch")?.value || "",
        );
      });
    });

  if (dropdownSearch) {
    dropdownSearch.addEventListener("input", function () {
      if (inputError) {
        inputError.textContent = "";
        inputError.classList.remove("active");
      }
      fetchmultiDropdown(this.value);
    });

    dropdownSearch.addEventListener("focus", function () {
      fetchmultiDropdown(this.value);
    });

    dropdownSearch.addEventListener("keydown", function (event) {
      if (event.key === "Enter") {
        event.preventDefault();
        dropdownApply?.click();
      }
      if (event.key === "Escape") {
        event.preventDefault();
        event.stopPropagation();
        closeMultiActionDropdown();
      }
    });
  }

  if (dropdownApply) {
    dropdownApply.addEventListener("click", function (event) {
      if (!isSelectionModeActive()) {
        createSnackbar(
          getUiText("js.common.no_items_selected", "No items selected"),
        );
        return;
      }
      confirmMulti(
        "/confirmMulti",
        dropdownApply,
        event,
        getCurrentPageLocation(),
      );
    });
  }

  if (dropdownCreateGroup) {
    dropdownCreateGroup.addEventListener("click", function (event) {
      event.preventDefault();
      event.stopPropagation();
      openCreateGroupModalFromMultiDropdown();
    });
  }

  updateMultiCreateGroupButton(activeMultiAction);

  toggleMultiInputVisibility();

  // Close Gmail-style tag/group dropdown when clicking outside
  document.addEventListener(
    "pointerdown",
    function (event) {
      const dropdown = document.getElementById("multiActionDropdown");
      if (!dropdown || !dropdown.classList.contains("active")) {
        return;
      }

      const clickedInDropdown = dropdown.contains(event.target);
      const clickedActionButton = !!event.target.closest(
        ".multiActionBtn[data-action]",
      );

      if (!clickedInDropdown && !clickedActionButton) {
        closeMultiActionDropdown();
      }
    },
    true,
  );

  document.addEventListener(
    "pointerdown",
    function (event) {
      if (!masterMenu?.classList.contains("active")) {
        return;
      }

      if (masterMenu.contains(event.target)) {
        return;
      }

      if (masterMenuButton?.contains(event.target)) {
        return;
      }

      closeMasterMenu();
    },
    true,
  );

  window.addEventListener(
    "resize",
    _debounce(() => {
      if (!window.matchMedia("(max-width: 768px)").matches) {
        setMobileMultiDropdownOpenState(false);
      }

      scheduleMultiSelectFloatingPositionUpdate();
    }, 200),
  );

  window.addEventListener(
    "scroll",
    () => {
      scheduleMultiSelectFloatingPositionUpdate();
    },
    { passive: true },
  );

  scheduleMultiSelectFloatingPositionUpdate();

  // Keyboard shortcuts in selection mode
  document.addEventListener("keydown", function (event) {
    if (!isSelectionModeActive()) {
      return;
    }

    const target = event.target;
    const isTypingTarget =
      target instanceof HTMLInputElement ||
      target instanceof HTMLTextAreaElement ||
      target instanceof HTMLSelectElement ||
      target?.isContentEditable === true;

    if (
      (event.key === "a" || event.key === "A") &&
      !event.ctrlKey &&
      !event.metaKey &&
      !event.altKey &&
      !isTypingTarget
    ) {
      event.preventDefault();
      toggleSelectionForVisibleItems(true);
      return;
    }

    if (event.key === "Escape") {
      if (masterMenu?.classList.contains("active")) {
        event.preventDefault();
        closeMasterMenu();
        return;
      }

      const dropdown = document.getElementById("multiActionDropdown");
      if (dropdown?.classList.contains("active")) {
        event.preventDefault();
        closeMultiActionDropdown();
        return;
      }

      clearSelectionState();
    }
  });

  document.addEventListener(
    "click",
    function (event) {
      if (!isSelectionModeActive()) {
        return;
      }

      // Keep top controls (filter/sort/search/multi bar actions) fully clickable in selection mode.
      if (
        event.target.closest(
          ".searchContainer, .filtersContainer, .filterContainer, .sortContainer, .clearContainer, .resetContainer, .selectModeContainer, .selectionBulkControls, .multiActionIcons, #filterTagsGroups, #sortLinks, #clearFilter, #resetFilter, #enterSelectMode, #multiMasterCheckbox, #multiMasterMenuButton, #multiMasterMenu, .multiMasterMenuItem",
        )
      ) {
        return;
      }

      const destinationDragZone = event.target.closest(
        ".destinationDragZone[data-horizontal-drag='true']",
      );
      if (destinationDragZone) {
        const selectedText = String(
          window.getSelection?.().toString().trim() || "",
        );
        if (selectedText.length > 0) {
          window.getSelection?.().removeAllRanges?.();
          event.preventDefault();
          return;
        }

        if (
          typeof window.shouldSuppressSelectionToggle === "function" &&
          window.shouldSuppressSelectionToggle(event.target)
        ) {
          return;
        }

        if (destinationDragZone.scrollWidth > destinationDragZone.clientWidth) {
          return;
        }
      }

      const checkboxForm = event.target.closest(
        ".linkForm, .userForm, .visitorForm",
      );
      if (checkboxForm) {
        const formCheckbox = checkboxForm.querySelector(".checkbox");
        if (!formCheckbox) {
          return;
        }

        // Native checkbox clicks already call multiSelect via inline handler.
        if (event.target === formCheckbox) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
        multiSelect(formCheckbox.id, event);
        return;
      }

      const container = event.target.closest(
        ".linkContainer, .userContainer, .visitorContainer",
      );
      if (!container) {
        return;
      }

      if (
        event.target.closest(
          "a, button, input, textarea, select, label, .action, .popup-modal, .tagContainer, .favContainer",
        )
      ) {
        return;
      }

      const checkbox = container.querySelector(".checkbox");
      if (!checkbox) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      checkbox.click();
    },
    true,
  );
});

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

function switchPassword() {
  const password = document.getElementById("password");
  const eye = document.getElementById("eyeIcon");

  if (password.type === "password") {
    password.type = "text";
    eye.innerHTML = "visibility";
  } else {
    password.type = "password";
    eye.innerHTML = "visibility_off";
  }
}

function scrollUp() {
  window.scrollTo({ top: 0, behavior: "smooth" });
}

function createBackup() {
  if (typeof window.setAdminBackupBusy === "function") {
    window.setAdminBackupBusy(true);
  }

  fetch("/backup", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({}),
  })
    .then(async (response) => {
      const data = await response.json().catch(() => ({}));
      return { response, data };
    })
    .then(({ response, data }) => {
      if (data.status === "success") {
        createSnackbar(data.message || snackbar.backupMessage);
      } else if (data.status === "locked") {
        createSnackbar(data.message || "Backup is already running.");
      } else if (!response.ok || data.status === "error") {
        createSnackbar(data.message || snackbar.backupError);
      } else {
        createSnackbar(snackbar.backupMessage);
      }
    })
    .catch(() => {
      createSnackbar(snackbar.backupError);
    })
    .finally(() => {
      if (typeof window.setAdminBackupBusy === "function") {
        window.setAdminBackupBusy(false);
      }
      if (typeof window.refreshAdminBackupStatus === "function") {
        window.refreshAdminBackupStatus();
      }
    });
}

function checkTitleLength(root = document) {
  const scope =
    root && typeof root.querySelectorAll === "function" ? root : document;
  scope.querySelectorAll(".linkTitle").forEach((title) => {
    const isLong = title.textContent.length > 10;
    title.classList.toggle("longTitle", isLong);
    const header = title.closest(".linkHeader");
    if (header) header.classList.toggle("short-title", !isLong);
  });
}

function collapseInlineTags(limit = 2, root = document) {
  const scope =
    root && typeof root.querySelectorAll === "function" ? root : document;
  scope.querySelectorAll(".titleTags").forEach((container) => {
    // Collapse tags
    const tagEls = Array.from(
      container.querySelectorAll(".tagContainer.filterByTag"),
    );
    if (tagEls.length > limit) {
      const names = tagEls.map((el) => {
        const p = el.querySelector("p");
        return p ? p.textContent.trim() : el.textContent.trim();
      });

      tagEls.forEach((el, idx) => {
        if (idx >= limit) el.remove();
      });

      if (!container.querySelector(".collapsedPlus.tag-plus")) {
        const plus = document.createElement("div");
        plus.className = "tagContainer tooltip collapsedPlus tag-plus";

        const p = document.createElement("p");
        p.className = "tag";
        p.textContent = `+ ${names.length - limit}`;
        plus.appendChild(p);

        const span = document.createElement("span");
        span.className = "tooltiptext";
        span.innerHTML = `<p>${names.slice(limit).join(", ")}</p>`;
        plus.appendChild(span);

        const ref =
          container.querySelectorAll(".tagContainer.filterByTag")[limit - 1] ||
          container.querySelector(".tagContainer.filterByTag");
        if (ref && ref.nextSibling) {
          ref.parentNode.insertBefore(plus, ref.nextSibling);
        } else {
          container.appendChild(plus);
        }
      }
    }

    // Collapse groups
    const groupEls = Array.from(
      container.querySelectorAll(".tagContainer.filterByGroup"),
    );
    if (groupEls.length > limit) {
      const names = groupEls.map((el) => {
        const p = el.querySelector("p");
        return p ? p.textContent.trim() : el.textContent.trim();
      });

      groupEls.forEach((el, idx) => {
        if (idx >= limit) el.remove();
      });

      if (!container.querySelector(".collapsedPlus.group-plus")) {
        const plus = document.createElement("div");
        plus.className = "tagContainer tooltip collapsedPlus group-plus";

        const p = document.createElement("p");
        p.className = "group";
        p.textContent = `+ ${names.length - limit}`;
        plus.appendChild(p);

        const span = document.createElement("span");
        span.className = "tooltiptext";
        span.innerHTML = `<p>${names.slice(limit).join(", ")}</p>`;
        plus.appendChild(span);

        const ref =
          container.querySelectorAll(".tagContainer.filterByGroup")[
            limit - 1
          ] || container.querySelector(".tagContainer.filterByGroup");
        if (ref && ref.nextSibling) {
          ref.parentNode.insertBefore(plus, ref.nextSibling);
        } else {
          container.appendChild(plus);
        }
      }
    }
  });
}

let tooltipPortalEl = null;
let tooltipActiveTrigger = null;

function ensureTooltipPortalElement() {
  if (tooltipPortalEl && document.body.contains(tooltipPortalEl)) {
    return tooltipPortalEl;
  }

  tooltipPortalEl = document.getElementById("globalTooltipPortal");
  if (!tooltipPortalEl) {
    tooltipPortalEl = document.createElement("div");
    tooltipPortalEl.id = "globalTooltipPortal";
    document.body.appendChild(tooltipPortalEl);
  }

  return tooltipPortalEl;
}

function positionTooltipPortal(trigger) {
  const portal = ensureTooltipPortalElement();
  if (!portal || !trigger) {
    return;
  }

  const triggerRect = trigger.getBoundingClientRect();
  const viewportPadding = 10;
  const gap = 8;

  const portalWidth = portal.offsetWidth;
  const portalHeight = portal.offsetHeight;

  let top = triggerRect.top - portalHeight - gap;
  if (top < viewportPadding) {
    top = triggerRect.bottom + gap;
  }

  if (top + portalHeight > window.innerHeight - viewportPadding) {
    top = Math.max(viewportPadding, triggerRect.top - portalHeight - gap);
  }

  let left = triggerRect.left + triggerRect.width / 2 - portalWidth / 2;
  left = Math.max(
    viewportPadding,
    Math.min(left, window.innerWidth - portalWidth - viewportPadding),
  );

  portal.style.left = `${Math.round(left)}px`;
  portal.style.top = `${Math.round(top)}px`;
}

function showTooltipPortal(trigger) {
  if (!trigger || !trigger.isConnected) {
    return;
  }

  if (isMultiActionDropdownOpen() || isMasterMenuOpen()) {
    return;
  }

  const textElement = trigger.querySelector(".tooltiptext");
  if (!textElement) {
    return;
  }

  const safeContent = String(
    textElement.innerText || textElement.textContent || "",
  ).trim();
  if (safeContent.length === 0) {
    return;
  }

  const portal = ensureTooltipPortalElement();
  portal.textContent = safeContent;
  portal.classList.add("visible");

  tooltipActiveTrigger = trigger;
  positionTooltipPortal(trigger);
}

function hideTooltipPortal() {
  if (tooltipPortalEl) {
    tooltipPortalEl.classList.remove("visible");
  }
  tooltipActiveTrigger = null;
}

function initializeGlobalTooltipPortal() {
  if (document.documentElement.dataset.tooltipPortalInitialized === "true") {
    return;
  }

  document.documentElement.dataset.tooltipPortalInitialized = "true";
  document.body.classList.add("tooltip-portal-enabled");
  ensureTooltipPortalElement();

  document.addEventListener(
    "mouseover",
    function (event) {
      const trigger = event.target.closest(".tooltip");
      if (!trigger) {
        return;
      }

      if (event.relatedTarget && trigger.contains(event.relatedTarget)) {
        return;
      }

      showTooltipPortal(trigger);
    },
    true,
  );

  document.addEventListener(
    "mouseout",
    function (event) {
      if (!tooltipActiveTrigger) {
        return;
      }

      const leavingTrigger = event.target.closest(".tooltip");
      if (!leavingTrigger || leavingTrigger !== tooltipActiveTrigger) {
        return;
      }

      if (
        event.relatedTarget &&
        tooltipActiveTrigger.contains(event.relatedTarget)
      ) {
        return;
      }

      hideTooltipPortal();
    },
    true,
  );

  document.addEventListener(
    "focusin",
    function (event) {
      const trigger = event.target.closest(".tooltip");
      if (!trigger) {
        return;
      }

      showTooltipPortal(trigger);
    },
    true,
  );

  document.addEventListener(
    "focusout",
    function (event) {
      if (!tooltipActiveTrigger) {
        return;
      }

      const blurTrigger = event.target.closest(".tooltip");
      if (!blurTrigger || blurTrigger !== tooltipActiveTrigger) {
        return;
      }

      if (
        event.relatedTarget &&
        tooltipActiveTrigger.contains(event.relatedTarget)
      ) {
        return;
      }

      hideTooltipPortal();
    },
    true,
  );

  document.addEventListener(
    "pointerdown",
    function (event) {
      if (!tooltipActiveTrigger) {
        return;
      }

      if (event.target.closest(".tooltip")) {
        return;
      }

      hideTooltipPortal();
    },
    true,
  );

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      hideTooltipPortal();
    }
  });

  let tooltipRafPending = false;
  const repositionActiveTooltip = () => {
    if (!tooltipActiveTrigger) {
      return;
    }

    if (!tooltipActiveTrigger.isConnected) {
      hideTooltipPortal();
      return;
    }

    if (tooltipRafPending) return;
    tooltipRafPending = true;
    requestAnimationFrame(() => {
      tooltipRafPending = false;
      if (tooltipActiveTrigger && tooltipActiveTrigger.isConnected) {
        positionTooltipPortal(tooltipActiveTrigger);
      }
    });
  };

  window.addEventListener("scroll", repositionActiveTooltip, {
    capture: true,
    passive: true,
  });
  window.addEventListener("resize", _debounce(repositionActiveTooltip, 100));
}

// ============================================================================
// INITIALIZATION
// ============================================================================

loadSnackbarCatalog();

document.addEventListener("i18n:changed", (event) => {
  const nextLang = event?.detail?.lang;
  loadSnackbarCatalog(nextLang);
});

// Close all select boxes when clicking outside
document.addEventListener("click", () => closeAllSelect(null));

// DOM Content Loaded
document.addEventListener("DOMContentLoaded", function () {
  initializeAllCustomSelects();
  initializeGlobalTooltipPortal();

  if (sessionStorage.getItem("refreshAfterMutation") === "1") {
    sessionStorage.removeItem("refreshAfterMutation");
  }

  const queuedScrollY = parseInt(
    sessionStorage.getItem("postReloadScrollY") || "",
    10,
  );
  if (Number.isFinite(queuedScrollY) && queuedScrollY >= 0) {
    window.requestAnimationFrame(() => {
      window.scrollTo(0, queuedScrollY);
      sessionStorage.removeItem("postReloadScrollY");
    });
  }

  const globalUiNoticeInput = document.getElementById("globalUiNotice");
  const queuedSnackbar = sessionStorage.getItem("postReloadSnackbar");
  if (queuedSnackbar) {
    try {
      const data = JSON.parse(queuedSnackbar);
      if (data && data.message) {
        const queuedDuration = Number(data.duration);
        if (typeof data.undo === "string" && data.undo.trim().length > 0) {
          createSnackbar(data.message, {
            undo: data.undo,
            duration:
              Number.isFinite(queuedDuration) && queuedDuration > 0
                ? queuedDuration
                : SNACKBAR_UNDO_DURATION_MS,
          });
        } else if (Number.isFinite(queuedDuration) && queuedDuration > 0) {
          createSnackbar(data.message, { duration: queuedDuration });
        } else {
          createSnackbar(data.message);
        }
      }
    } catch {
      createSnackbar(
        getUiText("js.common.action_completed", "Action completed"),
      );
    }
    sessionStorage.removeItem("postReloadSnackbar");
  }

  if (globalUiNoticeInput && globalUiNoticeInput.value) {
    const noticeType = (
      globalUiNoticeInput.dataset.type || "info"
    ).toLowerCase();
    const prefix =
      noticeType === "error" ? "✗ " : noticeType === "success" ? "✓ " : "";
    createSnackbar(prefix + globalUiNoticeInput.value);
  }

  const pageInput = document.getElementById("page");
  const currentPage = pageInput ? pageInput.value : null;

  document.addEventListener("submit", async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.id !== "loginForm") {
      return;
    }

    event.preventDefault();

    if (form.dataset.submitting === "true") {
      return;
    }

    form.dataset.submitting = "true";
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton ? submitButton.textContent : "";

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = getUiText(
        "js.auth.logging_in",
        "Logging in...",
      );
    }

    try {
      const response = await fetch(form.action || "/login", {
        method: "POST",
        body: new FormData(form),
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json",
        },
      });

      let data = {};
      try {
        data = await response.json();
      } catch {
        data = {};
      }

      if (!response.ok || !data.success) {
        createSnackbar(
          `X ${
            data.error ||
            getUiText(
              "js.auth.invalid_email_or_password",
              "Invalid email or password",
            )
          }`,
          {
            type: "error",
            duration: 5000,
          },
        );
        return;
      }

      window.location.href = data.redirect || "/";
    } catch {
      createSnackbar(
        getUiText(
          "js.common.network_error_try_again",
          "X Network error. Please try again.",
        ),
        {
          type: "error",
          duration: 5000,
        },
      );
    } finally {
      form.dataset.submitting = "false";
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent =
          originalButtonText || getUiText("auth.login", "Login");
      }
    }
  });

  document.body.classList.remove("mobile-nav-visible");
  document.body.classList.remove("mobile-nav-hidden");

  // Mobile header scroll behavior
  if (window.innerWidth < 768) {
    const header = document.querySelector(".header");
    const headerSpacer = document.querySelector(".headerSpacer");
    const navContainer = document.querySelector(".navContainer");
    const EDGE_VISIBILITY_THRESHOLD = 12;
    const SHOW_HIDE_DELTA_THRESHOLD = 8;
    const HIDE_AFTER_SCROLL_Y = 90;
    let mobileNavVisible = true;
    let lastScrollTop = Math.max(
      0,
      window.pageYOffset || document.documentElement.scrollTop || 0,
    );
    let navTicking = false;

    const syncMobileNavVisibilityClass = (visible) => {
      document.body.classList.toggle("mobile-nav-visible", !!visible);
      document.body.classList.toggle("mobile-nav-hidden", !visible);
    };

    const clearMobileNavVisibilityClass = () => {
      document.body.classList.remove("mobile-nav-visible");
      document.body.classList.remove("mobile-nav-hidden");
    };

    const isEditableElementFocused = () => {
      const active = document.activeElement;
      if (!active) {
        return false;
      }

      if (
        active.tagName === "INPUT" ||
        active.tagName === "TEXTAREA" ||
        active.tagName === "SELECT"
      ) {
        return true;
      }

      return active.isContentEditable === true;
    };

    const setMobileNavVisible = (visible) => {
      syncMobileNavVisibilityClass(visible);
      scheduleMultiSelectFloatingPositionUpdate();

      if (!header || !navContainer || mobileNavVisible === visible) {
        return;
      }

      mobileNavVisible = visible;

      if (visible) {
        header.classList.remove("header-up");
        navContainer.classList.remove("navContainer-down");
        headerSpacer?.classList.remove("headerSpacer-collapsed");
      } else {
        header.classList.add("header-up");
        navContainer.classList.add("navContainer-down");
        headerSpacer?.classList.add("headerSpacer-collapsed");
      }
    };

    const hasActiveSelectionMode = () => {
      const multiSelectBar = document.getElementById("multiSelect");
      if (multiSelectBar?.classList.contains("active")) {
        return true;
      }

      return !!document.querySelector(".container.formActive:not(.open)");
    };

    const hasTextSelection = () => {
      const selection = window.getSelection?.();
      if (!selection || selection.isCollapsed) {
        return false;
      }

      return selection.toString().trim().length > 0;
    };

    const isProfileMenuOpen = () => {
      return !!document.querySelector("#secondaryNav.active");
    };

    const applyMobileNavVisibility = () => {
      if (!header || !navContainer) {
        return;
      }

      if (
        document.getElementById("modalContainer") ||
        isProfileMenuOpen() ||
        isEditableElementFocused() ||
        hasActiveSelectionMode() ||
        hasTextSelection()
      ) {
        setMobileNavVisible(true);
        return;
      }

      const scrollTop =
        window.pageYOffset || document.documentElement.scrollTop;
      const viewportHeight =
        window.innerHeight || document.documentElement.clientHeight;
      const fullHeight = Math.max(
        document.body.scrollHeight,
        document.documentElement.scrollHeight,
      );

      const atTop = scrollTop <= EDGE_VISIBILITY_THRESHOLD;
      const distanceFromBottom = fullHeight - (scrollTop + viewportHeight);
      const atBottom = distanceFromBottom <= EDGE_VISIBILITY_THRESHOLD;

      if (atTop || atBottom || scrollTop <= HIDE_AFTER_SCROLL_Y) {
        setMobileNavVisible(true);
        lastScrollTop = scrollTop;
        return;
      }

      const scrollDelta = scrollTop - lastScrollTop;
      if (scrollDelta > SHOW_HIDE_DELTA_THRESHOLD) {
        setMobileNavVisible(false);
      } else if (scrollDelta < -SHOW_HIDE_DELTA_THRESHOLD) {
        setMobileNavVisible(true);
      }

      lastScrollTop = scrollTop;
    };

    applyMobileNavVisibility();
    window.addEventListener(
      "scroll",
      () => {
        if (navTicking) {
          return;
        }
        navTicking = true;
        window.requestAnimationFrame(() => {
          applyMobileNavVisibility();
          navTicking = false;
        });
      },
      {
        passive: true,
      },
    );
    window.addEventListener(
      "resize",
      _debounce(() => {
        if (window.innerWidth >= 768) {
          clearMobileNavVisibilityClass();
          return;
        }

        lastScrollTop = Math.max(
          0,
          window.pageYOffset || document.documentElement.scrollTop || 0,
        );
        applyMobileNavVisibility();
      }, 150),
    );
  }

  // Scroll behavior for rocket and add link button
  let scrollTimeout;
  let rocketRafPending = false;
  const rocketEl = document.getElementById("rocketContainer");
  const addLinkEl = document.getElementById("createLink");
  window.addEventListener(
    "scroll",
    function () {
      if (rocketRafPending) return;
      rocketRafPending = true;
      requestAnimationFrame(function () {
        rocketRafPending = false;
        const scrollTop =
          window.pageYOffset || document.documentElement.scrollTop;
        const rocketThreshold = currentPage === "admin" ? 0 : 200;

        if (scrollTop > rocketThreshold) {
          if (rocketEl) rocketEl.classList.add("active");
          if (addLinkEl && currentPage && currentPage !== "visitors") {
            addLinkEl.classList.add("hide");
          }

          if (currentPage === "links") {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function () {
              if (rocketEl) rocketEl.classList.remove("active");
              setTimeout(function () {
                if (addLinkEl) addLinkEl.classList.remove("hide");
              }, 300);
            }, 5000);
          }
        } else {
          if (rocketEl) rocketEl.classList.remove("active");
          if (addLinkEl) addLinkEl.classList.remove("hide");
        }
      });
    },
    { passive: true },
  );

  if (currentPage === "links") {
    prefetchModal("/createModal?comp=links");

    const createLinkButton = document.getElementById("createLink");
    if (createLinkButton) {
      createLinkButton.addEventListener("pointerdown", (event) => {
        if (event.pointerType === "mouse" && event.button !== 0) {
          return;
        }

        event.preventDefault();
        createModal("/createModal?comp=links");
      });
    }
  }
});
