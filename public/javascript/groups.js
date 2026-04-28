document.addEventListener("DOMContentLoaded", function () {
  function initGroupViewControls() {
    const isGroupsPage = document.getElementById("page")?.value === "groups";
    if (!isGroupsPage) {
      return;
    }

    const body = document.body;
    body.classList.add("page-groups");
    const viewButtons = document.querySelectorAll(".groupViewToggle");
    const sizeControl = document.getElementById("groupSizeControl");
    const sizeInput = document.getElementById("groupThumbSize");
    const modeStorageKey = "groupsViewMode";
    const legacyModeStorageKey = "groups_layout_mode";
    const sizeStorageKey = "groupsThumbSize";
    const legacySizeStorageKey = "groups_thumbnail_size";
    const allowedModes = ["compact", "detailed", "thumbnail"];

    const applyMode = (mode) => {
      const nextMode = allowedModes.includes(mode) ? mode : "detailed";

      body.classList.remove("mode-compact", "mode-detailed", "mode-thumbnail");
      body.classList.add(`mode-${nextMode}`);

      viewButtons.forEach((button) => {
        button.classList.toggle("active", button.dataset.view === nextMode);
        button.setAttribute(
          "aria-pressed",
          button.dataset.view === nextMode ? "true" : "false",
        );
      });

      if (sizeControl) {
        sizeControl.classList.toggle("active", nextMode === "thumbnail");
      }

      localStorage.setItem(modeStorageKey, nextMode);
      localStorage.setItem(legacyModeStorageKey, nextMode);
    };

    const applyThumbSize = (sizeValue) => {
      const parsedSize = Number.parseInt(sizeValue, 10);
      const safeSize = Number.isFinite(parsedSize)
        ? Math.min(420, Math.max(220, parsedSize))
        : 300;

      body.style.setProperty("--group-thumb-size", `${safeSize}px`);
      if (sizeInput) {
        sizeInput.value = String(safeSize);
      }
      localStorage.setItem(sizeStorageKey, String(safeSize));
      localStorage.setItem(legacySizeStorageKey, String(safeSize));
    };

    const storedSize =
      localStorage.getItem(sizeStorageKey) ||
      localStorage.getItem(legacySizeStorageKey);
    applyThumbSize(storedSize || "300");

    const storedMode =
      localStorage.getItem(modeStorageKey) ||
      localStorage.getItem(legacyModeStorageKey);
    const isMobile = window.matchMedia("(max-width: 768px)").matches;
    applyMode(isMobile ? "compact" : storedMode || "detailed");

    viewButtons.forEach((button) => {
      button.addEventListener("click", () => {
        applyMode(button.dataset.view);
      });
    });

    sizeInput?.addEventListener("input", function () {
      applyThumbSize(this.value);
    });
  }

  function addEventListeners() {
    // Use event delegation for .linkSwitch elements
    document.body.addEventListener("click", function (event) {
      if (event.target.closest(".linkSwitch")) {
        let openSwitch = event.target.closest(".linkSwitch");

        // Skip clicks on interactive elements
        if (
          event.target.tagName === "BUTTON" ||
          event.target.tagName === "A" ||
          event.target.tagName === "I" ||
          event.target.classList.contains("tagContainer") ||
          event.target.parentElement?.classList.contains("tagContainer") ||
          event.target.closest(".popup-modal") ||
          event.target.closest(".linkForm") ||
          event.target.closest(".favContainer") ||
          event.target.closest(".groupQuickActions")
        ) {
          return;
        }

        // Compact/thumbnail: open group preview modal
        if (
          document.body.classList.contains("mode-thumbnail") ||
          document.body.classList.contains("mode-compact")
        ) {
          const card = openSwitch.closest(".outerLinkContainer");
          if (card) openGroupPreview(card);
          return;
        }
        openSwitch.classList.toggle("open");
      }
    });

    // ── Long-press / hold to enter multi-select ───────────────────────────
    // Use event delegation on document.body so cards remain responsive
    // even after the filter/sort refreshes the DOM.
    (function () {
      const HOLD_DELAY = 500;
      const MOVE_THRESHOLD = 10;

      let activeContainer = null;
      let holdTimer = null;
      let holdFired = false;
      let startTouchX = 0;
      let startTouchY = 0;
      let suppressClickUntil = 0;
      let suppressClickContainer = null;
      const SUPPRESS_POST_HOLD_CLICK_MS = 320;

      function fireHold(container, event) {
        holdFired = true;
        container.classList.remove("link-pressing");
        const checkbox = container.querySelector(".checkbox");
        if (checkbox) {
          if (navigator.vibrate) navigator.vibrate(50);
          if (typeof window.multiSelect === "function") {
            window.multiSelect(checkbox.id, event);
          } else {
            checkbox.click();
          }
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

      // Some browsers dispatch a delayed synthetic click after a long-press.
      // Suppress only that short post-hold window so normal checkbox clicks keep working.
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

      // ── Touch ──────────────────────────────────────────────────────────
      document.body.addEventListener(
        "touchstart",
        function (event) {
          const container = event.target.closest(".linkContainer");
          if (!container) return;
          if (
            event.target.closest(
              "a, button, input, select, textarea, .linkForm, .favContainer, .showQr, .popup-modal",
            )
          )
            return;
          if (document.body.classList.contains("shift-dragging")) return;

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
          if (!activeContainer) return;
          const touch = event.touches[0];
          const dx = Math.abs(touch.clientX - startTouchX);
          const dy = Math.abs(touch.clientY - startTouchY);
          if (dx > MOVE_THRESHOLD || dy > MOVE_THRESHOLD) {
            cancelHold();
          }
        },
        { passive: true },
      );

      document.body.addEventListener("touchend", function (event) {
        if (!activeContainer) return;
        const fired = holdFired;
        cancelHold();
        if (fired) {
          event.preventDefault();
        }
      });

      document.body.addEventListener("touchcancel", cancelHold);

      // ── Mouse (desktop long-press) ─────────────────────────────────────
      document.body.addEventListener("mousedown", function (event) {
        const container = event.target.closest(".linkContainer");
        if (!container) return;
        if (
          event.target.closest(
            "a, button, input, select, textarea, .linkForm, .favContainer, .showQr, .popup-modal",
          )
        )
          return;
        if (
          event.shiftKey ||
          document.body.classList.contains("shift-dragging")
        )
          return;

        activeContainer = container;
        holdFired = false;
        startTouchX = event.clientX;
        startTouchY = event.clientY;
        container.classList.add("link-pressing");
        holdTimer = setTimeout(() => fireHold(container, event), HOLD_DELAY);
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
      });
      document.body.addEventListener("mouseleave", cancelHold);

      // Block native context menu on group containers so long-press
      // triggers multi-select instead of the browser image context menu.
      document.body.addEventListener("contextmenu", function (event) {
        if (event.target.closest(".linkContainer")) {
          event.preventDefault();
        }
      });
    })();
  }
  addEventListeners();
  initGroupViewControls();

  function adjustCreateLinkPosition() {
    const paginationContainers = document.querySelectorAll(
      ".shownContainer.bottomPage",
    );
    const newLinkContainer = document.querySelector(".newLinkContainer");

    if (paginationContainers.length > 0 && newLinkContainer) {
      const hasPagination = Array.from(paginationContainers).some(
        (container) => container.offsetParent !== null,
      );

      if (hasPagination) {
        newLinkContainer.classList.add("with-pagination");
      } else {
        newLinkContainer.classList.remove("with-pagination");
      }
    }
  }

  adjustCreateLinkPosition();
  window.addEventListener("resize", _debounce(adjustCreateLinkPosition, 200));
});

function notifyGroupMessage(message, type = "info") {
  if (typeof window.createSnackbar === "function") {
    window.createSnackbar(message, type);
    return;
  }
  alert(message);
}

function checkFile(value) {
  var file = value.files[0];
  if (!file) {
    return;
  }

  var fileType = file["type"];
  var validImageTypes = ["image/gif", "image/jpeg", "image/png"];

  if (!validImageTypes.includes(fileType)) {
    value.value = "";
    notifyGroupMessage("Please upload a valid image file", "error");
    return;
  }

  if (file.size > 5000000) {
    value.value = "";
    notifyGroupMessage("File size should be less than 5MB", "error");
    return;
  }

  var reader = new FileReader();
  reader.onload = function (event) {
    var img = new Image();
    img.onload = function () {
      var canvas = document.createElement("canvas");
      var ctx = canvas.getContext("2d");
      canvas.width = 250;
      canvas.height = 250;
      ctx.drawImage(img, 0, 0, 250, 250);
      var resizedImage = canvas.toDataURL();
      // Use the resizedImage as needed
    };
    img.src = event.target.result;
  };
  reader.readAsDataURL(file);

  document.getElementById("editImagePreview").src = URL.createObjectURL(file);

  const removeImageCheckbox = document.getElementById("remove-group-image");
  if (removeImageCheckbox) {
    removeImageCheckbox.checked = false;
  }
}

function toggleGroupImageRemoval(checkbox) {
  const preview = document.getElementById("editImagePreview");
  if (!preview) {
    return;
  }

  const defaultSrc =
    preview.getAttribute("data-default-src") ||
    "/../../../public/images/groups/default.png";
  const currentSrc = preview.getAttribute("data-current-src") || defaultSrc;
  const fileInput = document.getElementById("file-upload");

  if (checkbox.checked) {
    preview.src = defaultSrc;
    if (fileInput) {
      fileInput.value = "";
    }
    return;
  }

  preview.src = currentSrc;
}

function openGroupPreview(card) {
  // Prevent stacking
  if (document.getElementById("groupPreviewModal")) return;

  const id = card.dataset.groupId;
  const title = card.dataset.groupTitle || "";
  const description = card.dataset.groupDescription || "";
  const linkCount = card.dataset.groupLinks || "0";
  const created = card.dataset.groupCreated || "";
  const modified = card.dataset.groupModified || "";
  const image = card.dataset.groupImage;
  const isAdmin = card.dataset.isAdmin === "1";
  const imgSrc = image
    ? `/images/groups/${image}`
    : "/images/groups/default.png";

  const safeTitle = escapeHtml(title);
  const safeDesc = escapeHtml(description);

  let actionsHtml = "";
  if (isAdmin) {
    actionsHtml += `<button class="groupPreviewAction" onclick="document.getElementById('groupPreviewModal').remove();unlockBodyScroll();createModal('/editModal?id=${id}&comp=groups')"><i class="material-icons">edit</i> ${typeof getUiText === "function" ? escapeHtml(getUiText("groups.edit", "Edit")) : "Edit"}</button>`;
  }
  actionsHtml += `<a class="groupPreviewAction" href="/?group=${encodeURIComponent(title)}"><i class="material-icons">open_in_new</i> ${typeof getUiText === "function" ? escapeHtml(getUiText("groups.show_links", "Show Links")) : "Show Links"}</a>`;
  if (isAdmin) {
    actionsHtml += `<button class="groupPreviewAction groupPreviewActionDanger" onclick="document.getElementById('groupPreviewModal').remove();unlockBodyScroll();createPopupModal('/deleteLink?id=${id}&comp=groups', this, event)"><i class="material-icons">delete</i> ${typeof getUiText === "function" ? escapeHtml(getUiText("groups.delete_group", "Delete Group")) : "Delete Group"}</button>`;
  }

  const html = `
    <div id="groupPreviewModal" class="modal">
      <div class="modalBackground" id="groupPreviewBackdrop"></div>
      <div class="groupPreviewContent">
        <button class="groupPreviewClose" id="groupPreviewCloseBtn"><i class="material-icons">close</i></button>
        <div class="groupPreviewImage">
          <img src="${imgSrc}" onerror="this.onerror=null;this.src='/images/groups/default.png'" alt="">
        </div>
        <div class="groupPreviewBody">
          <h2 class="groupPreviewTitle">${safeTitle}</h2>
          ${safeDesc ? `<p class="groupPreviewDescription">${safeDesc}</p>` : ""}
          <div class="groupPreviewMeta">
            <span class="groupPreviewMetaItem"><i class="material-icons">link</i> ${escapeHtml(linkCount)}</span>
            <span class="groupPreviewMetaItem"><i class="material-icons">event</i> ${escapeHtml(created)}</span>
            <span class="groupPreviewMetaItem"><i class="material-icons">update</i> ${escapeHtml(modified)}</span>
          </div>
          <div class="groupPreviewActions">${actionsHtml}</div>
        </div>
      </div>
    </div>
  `;

  document.body.insertAdjacentHTML("beforeend", html);
  lockBodyScroll();

  document
    .getElementById("groupPreviewBackdrop")
    .addEventListener("click", closeGroupPreview);
  document
    .getElementById("groupPreviewCloseBtn")
    .addEventListener("click", closeGroupPreview);
}

function closeGroupPreview() {
  const modal = document.getElementById("groupPreviewModal");
  if (modal) {
    modal.remove();
    unlockBodyScroll();
  }
}

function escapeHtml(str) {
  const div = document.createElement("div");
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}
