// ============================================================================
// GMAIL-STYLE MULTI-SELECT KEYBOARD NAVIGATION
// ============================================================================

let kbFocusedIndex = -1;
let kbStarPending = false;
let kbStarPendingTimer = null;

// Shift+drag brush selection state
let shiftDragActive = false;
let shiftDragSelected = new Set();
let shiftDragMode = "select";

function getSelectableContainers() {
  return Array.from(document.querySelectorAll(".container")).filter(
    (el) => el.querySelector(".checkbox") && el.offsetParent !== null,
  );
}

function clearKbFocus() {
  document
    .querySelectorAll(".kb-focused")
    .forEach((el) => el.classList.remove("kb-focused"));
  kbFocusedIndex = -1;
}

function setKbFocus(index) {
  const containers = getSelectableContainers();
  clearKbFocus();

  if (index < 0 || index >= containers.length) {
    kbFocusedIndex = -1;
    return;
  }

  kbFocusedIndex = index;
  containers[index].classList.add("kb-focused");
  containers[index].scrollIntoView({ block: "nearest", behavior: "auto" });
}

function isTypingContext() {
  const active = document.activeElement;
  if (!active) {
    return false;
  }

  const tag = (active.tagName || "").toLowerCase();
  if (tag === "input" || tag === "textarea" || tag === "select") {
    return true;
  }

  return active.isContentEditable === true;
}

function isSelectionModeUiActive() {
  if (
    typeof window.isSelectionModeActive === "function" &&
    window.isSelectionModeActive()
  ) {
    return true;
  }

  if (document.body.classList.contains("selection-mode")) {
    return true;
  }

  return !!document.querySelector(".container.formActive");
}

function triggerClick(selector) {
  const element = document.querySelector(selector);
  if (!element) {
    return false;
  }

  element.click();
  return true;
}

function navigateTo(path) {
  if (window.location.pathname !== path) {
    window.location.href = path;
  }
}

function openShortcutsModal() {
  if (document.getElementById("modalContainer")) {
    return;
  }

  if (typeof createModal === "function") {
    createModal("/keyboardShortcuts");
  }
}

function adjustShownLimit(direction) {
  const shownSelect =
    document.getElementById("shownSelect") ||
    document.getElementById("mobileLimitSelect");
  if (!shownSelect) {
    return;
  }

  const currentValue = parseInt(shownSelect.value || "0", 10);
  const optionValues = Array.from(shownSelect.options)
    .map((option) => parseInt(option.value || "0", 10))
    .filter((value) => Number.isFinite(value) && value > 0)
    .sort((a, b) => a - b);

  const currentIndex = optionValues.findIndex(
    (value) => value === currentValue,
  );
  if (currentIndex === -1) {
    return;
  }

  const targetIndex =
    direction > 0
      ? Math.min(optionValues.length - 1, currentIndex + 1)
      : Math.max(0, currentIndex - 1);

  if (targetIndex === currentIndex) {
    return;
  }

  const nextValue = String(optionValues[targetIndex]);
  shownSelect.value = nextValue;
  shownSelect.dispatchEvent(new Event("change", { bubbles: true }));

  const mobileLimitSelect = document.getElementById("mobileLimitSelect");
  if (mobileLimitSelect) {
    mobileLimitSelect.value = nextValue;
  }
}

function clearSearchAndFilters() {
  if (triggerClick("#clearFilter")) {
    return;
  }

  triggerClick("#resetFilter");
}

function hasActiveSearchOrFilters() {
  const searchInput = document.getElementById("searchbar");
  const hasSearchText =
    !!searchInput &&
    typeof searchInput.value === "string" &&
    searchInput.value.trim().length > 0;

  const activeFilters = document.getElementById("activeFilters");
  const hasFilterChips = !!activeFilters?.querySelector(".tagContainer");

  const clearBtn = document.getElementById("clearFilter");
  const resetBtn = document.getElementById("resetFilter");
  const hasClearState = !!clearBtn && !clearBtn.classList.contains("inactive");
  const hasResetState = !!resetBtn && !resetBtn.classList.contains("inactive");

  return hasSearchText || hasFilterChips || hasClearState || hasResetState;
}

document.addEventListener("DOMContentLoaded", function () {
  const searchBar = document.getElementById("searchbar");
  if (searchBar) {
    searchBar.focus();
  }

  document.addEventListener("keydown", function (event) {
    const key = (event.key || "").toLowerCase();
    const ctrl = event.ctrlKey;
    const alt = event.altKey;
    const shift = event.shiftKey;
    const win = event.metaKey;
    const typing = isTypingContext();

    if (key === "escape" && document.getElementById("themedConfirmModal")) {
      return;
    }

    if (key === "escape" && document.getElementById("confirmationModal")) {
      event.preventDefault();
      const cancelUnsavedBtn =
        document.getElementById("cancelUnsavedBtn") ||
        document.getElementById("stayOnModalBtn") ||
        document.getElementById("unsavedCloseBtn");
      cancelUnsavedBtn?.click();
      return;
    }

    // Enter: do not interfere when a themed confirmation dialog is showing
    if (key === "enter" && document.getElementById("themedConfirmModal")) {
      return;
    }

    // Enter: let unsaved confirmation modal own key handling
    if (key === "enter" && document.getElementById("confirmationModal")) {
      return;
    }

    // Enter: submit in filter modal
    if (key === "enter" && document.getElementById("submitTagGroup")) {
      event.preventDefault();
      document.getElementById("submitTagGroup").click();
      return;
    }

    // Esc: close modal, clear active search/filters, otherwise toggle nav
    if (key === "escape") {
      event.preventDefault();

      // Close filter/sort overlays first (they share id="modalContainer")
      const tagGroupClose = document.querySelector(".closeTagGroupModal");
      if (tagGroupClose) {
        event.stopPropagation();
        event.stopImmediatePropagation();
        tagGroupClose.click();
        return;
      }

      const sortModalClose = document.querySelector(".closeSortModal");
      if (sortModalClose) {
        event.stopPropagation();
        event.stopImmediatePropagation();
        sortModalClose.click();
        return;
      }

      if (document.getElementById("modalContainer")) {
        event.stopPropagation();
        event.stopImmediatePropagation();

        const confirmModal = document.getElementById("confirmationModal");
        if (confirmModal) {
          const cancelUnsavedBtn =
            document.getElementById("cancelUnsavedBtn") ||
            document.getElementById("stayOnModalBtn") ||
            document.getElementById("unsavedCloseBtn");
          cancelUnsavedBtn?.click();
          return;
        }

        if (typeof window.handleModalClose === "function") {
          window.handleModalClose(false);
          return;
        }

        // Fallback when handleModalClose is unavailable
        document.getElementById("confirmationModal")?.remove();
        const modalContainer = document.getElementById("modalContainer");
        if (modalContainer) {
          modalContainer.remove();
          if (typeof unlockBodyScroll === "function") {
            unlockBodyScroll();
          }
        }
        return;
      }

      clearKbFocus();

      // Exit selection mode first if active
      if (isSelectionModeUiActive()) {
        if (typeof window.clearSelectionState === "function") {
          window.clearSelectionState();
        }
        document.body.classList.remove("selection-mode");
        document.body.classList.remove("shift-dragging");
        return;
      }

      if (hasActiveSearchOrFilters()) {
        clearSearchAndFilters();
        return;
      }

      const navToggle = document.querySelector(".nav-toggle");
      if (navToggle) {
        navToggle.click();
      }
      return;
    }

    // Ctrl + Shift + M: toggle dark/light mode
    if (ctrl && shift && key === "m") {
      event.preventDefault();
      if (typeof switchDark === "function") {
        switchDark();
      }
      return;
    }

    // Ctrl + ?: open shortcuts popup
    if (ctrl && (key === "/" || key === "?")) {
      event.preventDefault();
      openShortcutsModal();
      return;
    }

    if (typing) {
      kbStarPending = false;
      return;
    }

    // ── Gmail-style multi-select shortcuts ──────────────────────────────────

    // Shift+J: move focus to next item AND select it (extend selection)
    if (shift && key === "j") {
      event.preventDefault();
      const containers = getSelectableContainers();
      if (containers.length > 0) {
        const nextIndex =
          kbFocusedIndex < containers.length - 1 ? kbFocusedIndex + 1 : 0;
        setKbFocus(nextIndex);
        const cb = containers[nextIndex]?.querySelector(".checkbox");
        if (cb && !cb.checked && typeof multiSelect === "function") {
          multiSelect(cb.id, null);
        }
      }
      return;
    }

    // Shift+K: move focus to previous item AND select it (extend selection)
    if (shift && key === "k") {
      event.preventDefault();
      const containers = getSelectableContainers();
      if (containers.length > 0) {
        const prevIndex =
          kbFocusedIndex > 0 ? kbFocusedIndex - 1 : containers.length - 1;
        setKbFocus(prevIndex);
        const cb = containers[prevIndex]?.querySelector(".checkbox");
        if (cb && !cb.checked && typeof multiSelect === "function") {
          multiSelect(cb.id, null);
        }
      }
      return;
    }

    // j: move focus to next item
    if (key === "j") {
      event.preventDefault();
      const containers = getSelectableContainers();
      if (containers.length > 0) {
        setKbFocus(
          kbFocusedIndex < containers.length - 1 ? kbFocusedIndex + 1 : 0,
        );
      }
      return;
    }

    // k: move focus to previous item
    if (key === "k") {
      event.preventDefault();
      const containers = getSelectableContainers();
      if (containers.length > 0) {
        setKbFocus(
          kbFocusedIndex > 0 ? kbFocusedIndex - 1 : containers.length - 1,
        );
      }
      return;
    }

    // x: toggle selection of keyboard-focused item
    if (key === "x") {
      event.preventDefault();
      const containers = getSelectableContainers();
      const target = kbFocusedIndex >= 0 ? containers[kbFocusedIndex] : null;
      if (target) {
        const checkbox = target.querySelector(".checkbox");
        if (checkbox) {
          checkbox.click();
        }
      }
      return;
    }

    // *: start of sequence shortcut (Gmail: * a = select all, * n = none)
    if (key === "*") {
      event.preventDefault();
      kbStarPending = true;
      if (kbStarPendingTimer) clearTimeout(kbStarPendingTimer);
      kbStarPendingTimer = setTimeout(() => {
        kbStarPending = false;
        kbStarPendingTimer = null;
      }, 1500);
      return;
    }

    // * → a: select all visible items
    if (kbStarPending && key === "a") {
      event.preventDefault();
      kbStarPending = false;
      if (kbStarPendingTimer) {
        clearTimeout(kbStarPendingTimer);
        kbStarPendingTimer = null;
      }
      const selectAll = document.getElementById("selectAll");
      if (selectAll) {
        selectAll.checked = true;
        if (typeof multiSelect === "function") multiSelect("select-all");
      }
      return;
    }

    // * → n: deselect all (none)
    if (kbStarPending && key === "n") {
      event.preventDefault();
      kbStarPending = false;
      if (kbStarPendingTimer) {
        clearTimeout(kbStarPendingTimer);
        kbStarPendingTimer = null;
      }
      if (typeof clearSelectionState === "function") clearSelectionState();
      clearKbFocus();
      return;
    }

    // Enter: open/close the keyboard-focused card
    if (!typing && key === "enter" && kbFocusedIndex >= 0) {
      const containers = getSelectableContainers();
      const focused = containers[kbFocusedIndex];
      if (focused) {
        event.preventDefault();
        const switchContainer = focused.matches(
          ".linkSwitch, .userSwitch, .visitorSwitch",
        )
          ? focused
          : focused.querySelector(".linkSwitch, .userSwitch, .visitorSwitch");
        if (switchContainer) {
          switchContainer.classList.toggle("open");
        }
        return;
      }
    }

    // ────────────────────────────────────────────────────────────────────────

    // Ctrl + Alt + +: create new link
    if (ctrl && alt && (key === "+" || key === "=" || key === "add")) {
      event.preventDefault();
      if (!triggerClick("#createLink")) {
        triggerClick("#createUser");
      }
      return;
    }

    // Ctrl + Alt + S: snap to searchbar
    if (ctrl && alt && key === "s") {
      event.preventDefault();
      const input = document.getElementById("searchbar");
      if (input) {
        input.focus();
        input.select();
      }
      return;
    }

    // Ctrl + Shift + S: toggle sidebar/menu
    if (ctrl && shift && key === "s") {
      event.preventDefault();
      triggerClick(".nav-toggle");
      return;
    }

    // Ctrl + Win + F: open filter options modal
    if (ctrl && win && key === "f") {
      event.preventDefault();
      triggerClick("#filterTagsGroups");
      return;
    }

    // Ctrl + Win + S: open sorting options
    if (ctrl && win && key === "s") {
      event.preventDefault();
      triggerClick("#sortLinks");
      return;
    }

    // Ctrl + > / <: next and previous page
    if (ctrl && (key === ">" || key === ".")) {
      event.preventDefault();
      triggerClick("#nextPage");
      return;
    }

    if (ctrl && (key === "<" || key === ",")) {
      event.preventDefault();
      triggerClick("#previousPage");
      return;
    }

    // Ctrl + Alt navigation shortcuts
    if (ctrl && alt && key === "l") {
      event.preventDefault();
      navigateTo("/links");
      return;
    }

    if (ctrl && alt && key === "u") {
      event.preventDefault();
      navigateTo("/users");
      return;
    }

    if (ctrl && alt && key === "v") {
      event.preventDefault();
      navigateTo("/visitors");
      return;
    }

    if (ctrl && alt && key === "a") {
      event.preventDefault();
      navigateTo("/admin");
      return;
    }

    if (ctrl && alt && key === "g") {
      event.preventDefault();
      navigateTo("/groups");
      return;
    }

    // Ctrl + Alt + C: toggle compact/list mode
    if (ctrl && alt && key === "c") {
      event.preventDefault();
      if (typeof switchList === "function") {
        switchList();
      }
      return;
    }

    // Ctrl + Shift + Up/Down: adjust amount shown on page
    if (ctrl && shift && key === "arrowup") {
      event.preventDefault();
      adjustShownLimit(1);
      return;
    }

    if (ctrl && shift && key === "arrowdown") {
      event.preventDefault();
      adjustShownLimit(-1);
      return;
    }

    // Ctrl + Win + C: clear search and filters
    if (ctrl && win && key === "c") {
      event.preventDefault();
      clearSearchAndFilters();
      return;
    }

    // Keep existing legacy shortcuts
    if (key === "`") {
      event.preventDefault();
      const input = document.getElementById("searchbar");
      if (input) {
        input.focus();
        input.select();
      }
      return;
    }

    if (key === "arrowright") {
      triggerClick("#nextPage");
      return;
    }

    if (key === "arrowleft") {
      triggerClick("#previousPage");
    }
  });

  // ── Shift+drag brush selection ─────────────────────────────────────────────
  // Hold Shift + drag the mouse over cards to bulk-select them in one pass.

  document.addEventListener("mousedown", function (event) {
    if (!event.shiftKey || event.button !== 0) return;

    if (
      event.target.closest(
        "button, a, input, select, textarea, .linkForm, .userForm, .visitorForm, .popup-modal, .tagContainer",
      )
    ) {
      return;
    }

    const container = event.target.closest(".container");
    if (!container) return;
    const checkbox = container.querySelector(".checkbox:not([id='selectAll'])");
    if (!checkbox) return;

    event.preventDefault(); // prevent text-highlight while dragging
    shiftDragActive = true;
    document.body.classList.add("shift-dragging");
    shiftDragSelected.clear();
    shiftDragSelected.add(container);

    shiftDragMode = checkbox.checked ? "deselect" : "select";

    if (
      ((shiftDragMode === "select" && !checkbox.checked) ||
        (shiftDragMode === "deselect" && checkbox.checked)) &&
      typeof multiSelect === "function"
    ) {
      multiSelect(checkbox.id, null);
    }
  });

  document.addEventListener("mouseover", function (event) {
    if (!shiftDragActive) return;

    if (!event.shiftKey) {
      shiftDragActive = false;
      shiftDragSelected.clear();
      document.body.classList.remove("shift-dragging");
      return;
    }

    // If the left mouse button is no longer held, cancel the drag
    if (!(event.buttons & 1)) {
      shiftDragActive = false;
      shiftDragSelected.clear();
      document.body.classList.remove("shift-dragging");
      return;
    }
    const container = event.target.closest(".container");
    if (!container || shiftDragSelected.has(container)) return;
    const checkbox = container.querySelector(".checkbox:not([id='selectAll'])");
    if (!checkbox) return;

    if (
      (shiftDragMode === "select" && checkbox.checked) ||
      (shiftDragMode === "deselect" && !checkbox.checked)
    ) {
      return;
    }

    shiftDragSelected.add(container);
    if (typeof multiSelect === "function") multiSelect(checkbox.id, null);
  });

  document.addEventListener("mouseup", function () {
    shiftDragActive = false;
    shiftDragSelected.clear();
    shiftDragMode = "select";
    document.body.classList.remove("shift-dragging");
  });

  document.addEventListener("keyup", function (event) {
    if (event.key !== "Shift") {
      return;
    }
    shiftDragActive = false;
    shiftDragSelected.clear();
    shiftDragMode = "select";
    document.body.classList.remove("shift-dragging");
  });
});
