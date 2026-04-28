const DESTINATION_DRAG_SUPPRESS_MS = 360;
const DESTINATION_DRAG_SELECTOR =
  ".destinationDragZone[data-horizontal-drag='true']";
const DESTINATION_DRAG_INERTIA_MIN_SPEED = 0.05;
const DESTINATION_DRAG_INERTIA_FRICTION = 0.92;
const destinationUrlDragState = {
  activeZone: null,
  startX: 0,
  startY: 0,
  startScrollLeft: 0,
  lastClientX: 0,
  lastMoveTs: 0,
  velocityX: 0,
  inertiaRaf: null,
  dragging: false,
  suppressUntil: 0,
  suppressZone: null,
};

function getDestinationDragZone(target) {
  if (!target || typeof target.closest !== "function") {
    return null;
  }
  return target.closest(DESTINATION_DRAG_SELECTOR);
}

function clearDestinationTextSelection() {
  const selection =
    typeof window.getSelection === "function" ? window.getSelection() : null;
  if (selection && selection.rangeCount > 0) {
    selection.removeAllRanges();
  }
}

function stopDestinationDragInertia() {
  if (!destinationUrlDragState.inertiaRaf) {
    return;
  }

  cancelAnimationFrame(destinationUrlDragState.inertiaRaf);
  destinationUrlDragState.inertiaRaf = null;
}

function markDestinationDragSuppressed(zone) {
  if (!zone) {
    return;
  }

  destinationUrlDragState.suppressZone = zone;
  destinationUrlDragState.suppressUntil =
    Date.now() + DESTINATION_DRAG_SUPPRESS_MS;
}

window.shouldSuppressSelectionToggle = function (target) {
  if (Date.now() > destinationUrlDragState.suppressUntil) {
    return false;
  }

  const zone =
    destinationUrlDragState.suppressZone || getDestinationDragZone(target);
  if (!zone) {
    return false;
  }

  return !!zone.contains(target);
};

(function initDestinationUrlMouseDrag() {
  const DRAG_START_THRESHOLD = 5;

  const clearDragState = () => {
    destinationUrlDragState.activeZone?.classList.remove("is-dragging-url");
    document.body.classList.remove("is-horizontal-dragging-url");
    destinationUrlDragState.activeZone = null;
    destinationUrlDragState.dragging = false;
    destinationUrlDragState.startScrollLeft = 0;
    destinationUrlDragState.lastClientX = 0;
    destinationUrlDragState.lastMoveTs = 0;
    destinationUrlDragState.velocityX = 0;
  };

  const startDestinationDragInertia = (zone, velocityX) => {
    stopDestinationDragInertia();

    let speed = Number.isFinite(velocityX) ? velocityX : 0;
    if (Math.abs(speed) < DESTINATION_DRAG_INERTIA_MIN_SPEED) {
      return;
    }

    let lastTs = performance.now();
    const step = (ts) => {
      const dt = Math.max(1, ts - lastTs);
      lastTs = ts;

      const before = zone.scrollLeft;
      zone.scrollLeft -= speed * dt;
      const after = zone.scrollLeft;

      const atEdge =
        zone.scrollLeft <= 0 ||
        zone.scrollLeft >= zone.scrollWidth - zone.clientWidth;

      if (Math.abs(after - before) < 0.1 && atEdge) {
        destinationUrlDragState.inertiaRaf = null;
        return;
      }

      speed *= Math.pow(DESTINATION_DRAG_INERTIA_FRICTION, dt / 16.67);
      if (Math.abs(speed) < 0.02) {
        destinationUrlDragState.inertiaRaf = null;
        return;
      }

      destinationUrlDragState.inertiaRaf = requestAnimationFrame(step);
    };

    destinationUrlDragState.inertiaRaf = requestAnimationFrame(step);
  };

  document.addEventListener(
    "mousedown",
    (event) => {
      if (event.button !== 0) {
        return;
      }

      const zone = getDestinationDragZone(event.target);
      if (!zone || zone.scrollWidth <= zone.clientWidth + 1) {
        return;
      }

      event.preventDefault();
      clearDestinationTextSelection();

      stopDestinationDragInertia();

      destinationUrlDragState.activeZone = zone;
      destinationUrlDragState.startX = event.clientX;
      destinationUrlDragState.startY = event.clientY;
      destinationUrlDragState.startScrollLeft = zone.scrollLeft;
      destinationUrlDragState.lastClientX = event.clientX;
      destinationUrlDragState.lastMoveTs = performance.now();
      destinationUrlDragState.velocityX = 0;
      destinationUrlDragState.dragging = false;
    },
    true,
  );

  document.addEventListener(
    "mousemove",
    (event) => {
      const zone = destinationUrlDragState.activeZone;
      if (!zone) {
        return;
      }

      if ((event.buttons & 1) !== 1) {
        finalizeDrag();
        return;
      }

      const dx = event.clientX - destinationUrlDragState.startX;
      const dy = event.clientY - destinationUrlDragState.startY;

      if (!destinationUrlDragState.dragging) {
        if (
          Math.abs(dx) < DRAG_START_THRESHOLD &&
          Math.abs(dy) < DRAG_START_THRESHOLD
        ) {
          return;
        }

        if (Math.abs(dy) > Math.abs(dx) * 1.25) {
          clearDragState();
          return;
        }

        destinationUrlDragState.dragging = true;
        zone.classList.add("is-dragging-url");
        document.body.classList.add("is-horizontal-dragging-url");
        clearDestinationTextSelection();
      }

      const now = performance.now();
      const dt = Math.max(1, now - destinationUrlDragState.lastMoveTs);
      const deltaX = event.clientX - destinationUrlDragState.lastClientX;
      destinationUrlDragState.velocityX = deltaX / dt;
      destinationUrlDragState.lastClientX = event.clientX;
      destinationUrlDragState.lastMoveTs = now;

      clearDestinationTextSelection();
      zone.scrollLeft = destinationUrlDragState.startScrollLeft - dx;
      event.preventDefault();
    },
    true,
  );

  const finalizeDrag = () => {
    const zone = destinationUrlDragState.activeZone;
    if (!zone) {
      return;
    }

    if (destinationUrlDragState.dragging) {
      markDestinationDragSuppressed(zone);
      startDestinationDragInertia(zone, destinationUrlDragState.velocityX);
      clearDestinationTextSelection();
    }

    clearDragState();
  };

  document.addEventListener("mouseup", finalizeDrag, true);
  window.addEventListener("mouseup", finalizeDrag, true);
  window.addEventListener("blur", finalizeDrag, true);

  document.addEventListener(
    "selectstart",
    (event) => {
      if (!destinationUrlDragState.dragging) {
        return;
      }

      if (getDestinationDragZone(event.target)) {
        event.preventDefault();
      }
    },
    true,
  );

  document.addEventListener(
    "selectionchange",
    () => {
      if (destinationUrlDragState.activeZone) {
        clearDestinationTextSelection();
      }
    },
    true,
  );

  document.addEventListener(
    "click",
    (event) => {
      const zone = getDestinationDragZone(event.target);
      if (!zone) {
        return;
      }

      if (window.shouldSuppressSelectionToggle?.(event.target)) {
        clearDestinationTextSelection();
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
      }
    },
    true,
  );

  document.addEventListener(
    "dragstart",
    (event) => {
      if (getDestinationDragZone(event.target)) {
        event.preventDefault();
      }
    },
    true,
  );
})();

document.addEventListener("DOMContentLoaded", function () {
  // Your existing JavaScript code here

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
      event.target.closest(".popup-modal") ||
      event.target.closest(".favContainer") ||
      event.target.closest(".showQr")
    );
  }

  function getSelectionModeState() {
    if (typeof window.isSelectionModeActive === "function") {
      return window.isSelectionModeActive();
    }
    return false;
  }

  function prefersReducedMotion() {
    return !!window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches;
  }

  function syncLinkBodyWrapperState(container) {
    const wrapper = container?.querySelector(".linkBodyWrapper");
    if (!wrapper) {
      return;
    }

    if (container.classList.contains("open")) {
      wrapper.style.height = "auto";
      wrapper.style.opacity = "1";
      return;
    }

    wrapper.style.height = "0px";
    wrapper.style.opacity = "0";
  }

  function toggleLinkContainerOpen(container) {
    const wrapper = container?.querySelector(".linkBodyWrapper");
    if (!wrapper) {
      container?.classList.toggle("open");
      return;
    }

    const nextOpen = !container.classList.contains("open");
    if (prefersReducedMotion()) {
      container.classList.toggle("open", nextOpen);
      syncLinkBodyWrapperState(container);
      return;
    }

    const transitionKey = String(Date.now() + Math.random());
    wrapper.dataset.linkToggleTransition = transitionKey;
    const finalizeTransition = () => {
      if (wrapper.dataset.linkToggleTransition !== transitionKey) {
        return;
      }
      wrapper.style.willChange = "";
      if (nextOpen) {
        wrapper.style.height = "auto";
        wrapper.style.opacity = "1";
      } else {
        container.classList.remove("open");
        wrapper.style.height = "0px";
        wrapper.style.opacity = "0";
      }
      wrapper.removeEventListener("transitionend", onTransitionEnd);
      clearTimeout(fallbackTimer);
    };
    const onTransitionEnd = (event) => {
      if (event.target === wrapper && event.propertyName === "height") {
        finalizeTransition();
      }
    };
    const fallbackTimer = window.setTimeout(finalizeTransition, 420);
    wrapper.addEventListener("transitionend", onTransitionEnd);

    wrapper.style.willChange = "height, opacity";
    wrapper.style.overflow = "hidden";

    if (nextOpen) {
      const startHeight = wrapper.getBoundingClientRect().height;
      container.classList.add("open");
      const targetHeight = wrapper.scrollHeight;
      wrapper.style.height = `${startHeight}px`;
      wrapper.style.opacity = startHeight > 0 ? "1" : "0";
      void wrapper.offsetHeight;
      requestAnimationFrame(() => {
        if (wrapper.dataset.linkToggleTransition !== transitionKey) {
          return;
        }
        wrapper.style.height = `${targetHeight}px`;
        wrapper.style.opacity = "1";
      });
      return;
    }

    const startHeight = wrapper.getBoundingClientRect().height || wrapper.scrollHeight;
    wrapper.style.height = `${startHeight}px`;
    wrapper.style.opacity = "1";
    void wrapper.offsetHeight;
    requestAnimationFrame(() => {
      if (wrapper.dataset.linkToggleTransition !== transitionKey) {
        return;
      }
      wrapper.style.height = "0px";
      wrapper.style.opacity = "0";
    });
  }

  function addEventListeners() {
    // Use event delegation for .linkSwitch elements
    document.body.addEventListener("click", function (event) {
      if (event.target.closest(".linkSwitch")) {
        let openSwitch = event.target.closest(".linkSwitch");

        const checkbox = event.target.closest(".checkbox");
        if (checkbox && openSwitch.contains(checkbox)) {
          if (openSwitch.classList.contains("open")) {
            openSwitch.classList.remove("open");
          }
          return;
        }

        if (getSelectionModeState()) {
          if (isInteractiveTarget(event)) {
            return;
          }

          const linkCheckbox = openSwitch.querySelector(".checkbox");
          if (linkCheckbox && typeof window.multiSelect === "function") {
            event.preventDefault();
            window.multiSelect(linkCheckbox.id, event);
          }
          return;
        }

        if (isInteractiveTarget(event) || event.target.closest(".linkForm")) {
          return;
        }

        toggleLinkContainerOpen(openSwitch);
      }
    });

    // ── Long-press / hold to enter multi-select ───────────────────────────
    // Use event delegation on document.body so newly fetched cards (AJAX)
    // automatically work without needing to re-bind on every fetchData().
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
          // Skip interactive children
          if (
            event.target.closest(
              "a, button, input, select, textarea, .linkForm, .favContainer, .showQr, .popup-modal",
            )
          )
            return;
          if (getDestinationDragZone(event.target)) return;
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
        if (getDestinationDragZone(event.target)) return;
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
    })();
  }

  document.querySelectorAll(".linkContainer").forEach(syncLinkBodyWrapperState);
  addEventListeners();

  checkTitleLength();

  // Check if pagination is visible and adjust Create Link button accordingly
  function adjustCreateLinkPosition() {
    const newLinkContainer = document.querySelector(".newLinkContainer");
    if (!newLinkContainer) return;

    // Pagination exists if the bottom page container has visible page buttons
    const bottomPage = document.querySelector(".shownContainer.bottomPage");
    const hasPagination =
      bottomPage !== null && bottomPage.querySelector(".pageButton") !== null;
    newLinkContainer.classList.toggle("with-pagination", hasPagination);
  }

  // Call the function on page load
  adjustCreateLinkPosition();

  // Also call when the window is resized (in case pagination visibility changes)
  window.addEventListener("resize", _debounce(adjustCreateLinkPosition, 200));
});

function copyLink(link, event) {
  event.preventDefault();
  navigator.clipboard.writeText(link).then(
    function () {
      createSnackbar(snackbar.copyLink);
    },
    function () {
      const message =
        typeof getUiText === "function"
          ? getUiText("js.links.could_not_copy_link", "Could not copy link")
          : "Could not copy link";
      createSnackbar(message);
    },
  );
}

function favourite(link) {
  let linkId = link.split("-")[1];
  let favContainer = document.getElementById(link);
  let star = favContainer.firstElementChild;

  fetch("/favourite", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ linkId: linkId }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.favourite) {
        star.style.color = "var(--primary-color)";
        createSnackbar(snackbar.addFavourite);
      } else {
        star.style.color = "";
        createSnackbar(snackbar.removeFavourite);
      }
    });
}

// Backup reminders and execution are handled on the Admin page.

function switchStatus(id, status) {
  fetch("/switchLinkStatus", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ id: id, status: status }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "active") {
        createSnackbar(snackbar.activeStatus);
      } else if (data.status === "archived") {
        createSnackbar(snackbar.archivedStatus);
      }
      const linkContainer = document.getElementById(`link${data.id}`);
      if (linkContainer?.firstElementChild) {
        linkContainer.firstElementChild.classList.toggle("archived");
      }
      const statusElement = document.getElementById(`status-${data.id}`);
      if (statusElement) {
        statusElement.onclick = function () {
          switchStatus(data.id, data.status === "active" ? 1 : 0);
        };
      }
    });
}

let startX, startY, isScrolling;
let activeHorizontalDragZone = null;
let isHorizontalDragGesture = false;

// Function to handle touch start event
function touchStart(event) {
  startX = event.touches[0].clientX; // Get the initial touch position
  startY = event.touches[0].clientY; // Get the initial vertical touch position
  isScrolling = undefined; // Reset the scrolling flag
  activeHorizontalDragZone?.classList.remove("is-dragging-url");
  document.body.classList.remove("is-horizontal-dragging-url");
  activeHorizontalDragZone = getDestinationDragZone(event.target);
  isHorizontalDragGesture = false;
  clearDestinationTextSelection();
}

// Function to check if an element has a horizontal scrollbar
function hasHorizontalScrollbar(element) {
  return !!element && element.scrollWidth > element.clientWidth;
}

// Function to handle touch move event
function touchMove(event) {
  const moveX = event.touches[0].clientX;
  const moveY = event.touches[0].clientY;
  const dx = Math.abs(moveX - startX);
  const dy = Math.abs(moveY - startY);

  // Determine if the user is scrolling vertically
  if (isScrolling === undefined) {
    isScrolling = dy > dx;
  }

  if (activeHorizontalDragZone && dx > 12 && dx > dy * 1.25) {
    if (!isHorizontalDragGesture) {
      activeHorizontalDragZone.classList.add("is-dragging-url");
      document.body.classList.add("is-horizontal-dragging-url");
    }
    isHorizontalDragGesture = true;
    markDestinationDragSuppressed(activeHorizontalDragZone);
    clearDestinationTextSelection();
  }
}

// Function to handle touch end event
function touchEnd(event) {
  const dragZone =
    activeHorizontalDragZone || getDestinationDragZone(event.target);

  if (dragZone && isHorizontalDragGesture) {
    markDestinationDragSuppressed(dragZone);
    dragZone.classList.remove("is-dragging-url");
    document.body.classList.remove("is-horizontal-dragging-url");
    clearDestinationTextSelection();
    activeHorizontalDragZone = null;
    isHorizontalDragGesture = false;
    return;
  }

  // Interacting with the destination URL zone should never trigger page swipe.
  if (dragZone && hasHorizontalScrollbar(dragZone)) {
    dragZone.classList.remove("is-dragging-url");
    document.body.classList.remove("is-horizontal-dragging-url");
    clearDestinationTextSelection();
    activeHorizontalDragZone = null;
    isHorizontalDragGesture = false;
    return;
  }

  if (isScrolling) {
    dragZone?.classList.remove("is-dragging-url");
    document.body.classList.remove("is-horizontal-dragging-url");
    clearDestinationTextSelection();
    activeHorizontalDragZone = null;
    isHorizontalDragGesture = false;
    return; // If the user is scrolling vertically, do nothing
  }

  const endX = event.changedTouches[0].clientX; // Get the final touch position
  const swipeThreshold = 50; // Minimum distance to qualify as a swipe

  const targetElement = dragZone || event.target;

  // Check if the target element has a horizontal scrollbar
  if (hasHorizontalScrollbar(targetElement)) {
    // Get the scroll positions of the target element
    const scrollLeft = targetElement.scrollLeft;
    const maxScrollLeft = targetElement.scrollWidth - targetElement.clientWidth;

    // Ignore swipe if the user is interacting with the scrollbar
    if (startX > scrollLeft && startX < maxScrollLeft) {
      activeHorizontalDragZone = null;
      isHorizontalDragGesture = false;
      document.body.classList.remove("is-horizontal-dragging-url");
      clearDestinationTextSelection();
      return;
    }
  }

  if (startX - endX > swipeThreshold) {
    // Swipe left
    const nextPage = document.getElementById("nextPage");
    if (nextPage) nextPage.click();
  } else if (endX - startX > swipeThreshold) {
    // Swipe right
    const previousPage = document.getElementById("previousPage");
    if (previousPage) previousPage.click();
  }

  activeHorizontalDragZone = null;
  isHorizontalDragGesture = false;
  dragZone?.classList.remove("is-dragging-url");
  document.body.classList.remove("is-horizontal-dragging-url");
  clearDestinationTextSelection();
}

// Attach touch event listeners
document.addEventListener("touchstart", touchStart);
document.addEventListener("touchmove", touchMove);
document.addEventListener("touchend", touchEnd);

// ===========================
// Pull-to-refresh
// ===========================
(function () {
  const PULL_THRESHOLD = 80; // px down to trigger
  const PULL_MAX = 110; // max visual travel

  let ptStartY = 0;
  let ptPulling = false;
  let ptTriggered = false;
  let indicator = null;

  function getIndicator() {
    if (!indicator) {
      indicator = document.getElementById("ptrIndicator");
    }
    return indicator;
  }

  function setPullProgress(dist) {
    const el = getIndicator();
    if (!el) return;
    const clamped = Math.min(dist, PULL_MAX);
    const translateY = clamped * 0.55 - 52; // slides into view from -52px
    const opacity = Math.min(dist / PULL_THRESHOLD, 1);
    const rotation = (dist / PULL_THRESHOLD) * 280;
    el.style.transform = `translateX(-50%) translateY(${translateY}px)`;
    el.style.opacity = String(opacity);
    const icon = el.querySelector(".ptrIcon");
    if (icon) {
      icon.style.transform =
        dist >= PULL_THRESHOLD ? "" : `rotate(${rotation}deg)`;
    }
  }

  function resetPTR(delay) {
    const el = getIndicator();
    if (!el) return;
    setTimeout(function () {
      el.style.transform = "translateX(-50%) translateY(-52px)";
      el.style.opacity = "0";
      el.classList.remove("ptrRefreshing");
      const icon = el.querySelector(".ptrIcon");
      if (icon) icon.style.transform = "";
      ptPulling = false;
      ptTriggered = false;
    }, delay || 0);
  }

  function onPTRStart(e) {
    if (window.scrollY > 5) return;
    ptStartY = e.touches[0].clientY;
    ptPulling = false;
    ptTriggered = false;
  }

  function onPTRMove(e) {
    if (ptTriggered) return;
    const dy = e.touches[0].clientY - ptStartY;
    if (window.scrollY === 0 && dy > 8) {
      ptPulling = true;
      setPullProgress(dy);
    } else if (ptPulling && dy <= 0) {
      resetPTR();
    }
  }

  function onPTREnd(e) {
    if (!ptPulling) return;
    const dy = e.changedTouches[0].clientY - ptStartY;
    if (dy >= PULL_THRESHOLD && !ptTriggered) {
      ptTriggered = true;
      const el = getIndicator();
      if (el) el.classList.add("ptrRefreshing");
      if (navigator.vibrate) navigator.vibrate(30);
      if (typeof window.fetchData === "function") {
        window.fetchData();
      }
      resetPTR(900);
    } else {
      resetPTR();
    }
  }

  // Create the indicator element once DOM is ready
  function createIndicator() {
    if (document.getElementById("ptrIndicator")) return;
    const el = document.createElement("div");
    el.id = "ptrIndicator";
    el.innerHTML = '<span class="ptrIcon material-icons">refresh</span>';
    document.body.appendChild(el);
    indicator = el;
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", createIndicator);
  } else {
    createIndicator();
  }

  document.addEventListener("touchstart", onPTRStart, { passive: true });
  document.addEventListener("touchmove", onPTRMove, { passive: true });
  document.addEventListener("touchend", onPTREnd, { passive: true });
})();
