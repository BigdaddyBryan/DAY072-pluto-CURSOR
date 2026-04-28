let filter = {
  tags: [],
  groups: [],
  roles: [],
  sort: "latest_modified",
  limit: 10,
  offset: 0,
  search: "",
  searchType: "all",
};

function getFilterSnapshotForMultiSelect() {
  return {
    tags: Array.isArray(filter.tags) ? [...filter.tags] : [],
    groups: Array.isArray(filter.groups) ? [...filter.groups] : [],
    roles: Array.isArray(filter.roles) ? [...filter.roles] : [],
    sort: String(filter.sort || "latest_modified"),
    limit: Number.isFinite(Number(filter.limit)) ? Number(filter.limit) : 10,
    offset: Number.isFinite(Number(filter.offset)) ? Number(filter.offset) : 0,
    search: String(filter.search || ""),
    searchType: String(filter.searchType || "all"),
  };
}

window.getMultiSelectFilterSnapshot = getFilterSnapshotForMultiSelect;

function _escapeHtml(str) {
  const div = document.createElement("div");
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}

let currentPage = 1;

let pageLocation = null;

let pages = 0;

let debounceTimer = null;

let currentAbortController = null;
let activeFetchRequestId = 0;

/* ── Infinite scroll state (mobile links only) ── */
let _infiniteScrollActive = false;
let _infiniteScrollTotal = 0;
let _infiniteScrollLoaded = 0;
let _infiniteScrollFetching = false;
let _infiniteScrollObserver = null;
const _infiniteScrollMQ = window.matchMedia("(max-width: 768px)");

const COMPONENT_CACHE_LIMIT = 500;
const linkContainerHtmlCache = new Map();
const visitorContainerHtmlCache = new Map();
const groupContainerHtmlCache = new Map();
const userContainerHtmlCache = new Map();

// Select all page containers

let shownSelect = null;
let pageContainers = [];

let optionOriginalLabels = {};
let lastShownCount = null;

const icons = {
  alphabet_asc: "&#xf05bd;",
  alphabet_desc: "&#xf05bf;",
  latest_visit: "person_pin_circle",
  favorite: "star",
  latest: "&#xf1547;",
  oldest: "&#xf1548;",
  most_visit: "trending_up",
  least_visit: "trending_down",
  latest_modified: "update",
  most_visits_today: "person_pin_circle",
  archived: "archive",
  most_links: "trending_up",
  least_links: "trending_down",
};

const reverseOrders = {
  alphabet_asc: "alphabet_desc",
  alphabet_desc: "alphabet_asc",
  latest: "oldest",
  oldest: "latest",
  most_visit: "least_visit",
  least_visit: "most_visit",
  most_links: "least_links",
  least_links: "most_links",
};

const ALLOWED_LIMITS = [10, 20, 50, 100];
const SORTS_BY_PAGE = {
  links: [
    "alphabet_asc",
    "alphabet_desc",
    "latest_visit",
    "favorite",
    "latest",
    "oldest",
    "most_visit",
    "least_visit",
    "latest_modified",
    "most_visits_today",
    "archived",
  ],
  visitors: [
    "alphabet_asc",
    "alphabet_desc",
    "latest_visit",
    "latest",
    "oldest",
    "most_visit",
    "least_visit",
    "latest_modified",
    "most_visits_today",
  ],
  groups: [
    "alphabet_asc",
    "alphabet_desc",
    "latest",
    "oldest",
    "latest_modified",
    "most_links",
    "least_links",
  ],
  users: [
    "alphabet_asc",
    "alphabet_desc",
    "latest_visit",
    "latest",
    "oldest",
    "latest_modified",
  ],
};

const SORT_LABELS = {
  alphabet_asc: {
    key: "search.sort_option_alphabet_asc",
    fallback: "Alphabetical (A-Z)",
  },
  alphabet_desc: {
    key: "search.sort_option_alphabet_desc",
    fallback: "Alphabetical (Z-A)",
  },
  latest_visit: {
    key: "search.sort_option_latest_visit",
    fallback: "Latest Visited",
  },
  favorite: {
    key: "search.sort_option_favorite",
    fallback: "Favorited",
  },
  latest: {
    key: "search.sort_option_latest",
    fallback: "Latest Added",
  },
  oldest: {
    key: "search.sort_option_oldest",
    fallback: "Oldest Added",
  },
  most_visit: {
    key: "search.sort_option_most_visit",
    fallback: "Most Visited",
  },
  least_visit: {
    key: "search.sort_option_least_visit",
    fallback: "Least Visited",
  },
  latest_modified: {
    key: "search.sort_option_latest_modified",
    fallback: "Latest Modified",
  },
  most_visits_today: {
    key: "search.sort_option_most_visits_today",
    fallback: "Most Visits Today",
  },
  archived: {
    key: "search.sort_option_archived",
    fallback: "Archived",
  },
  most_links: {
    key: "search.sort_option_most_links",
    fallback: "Most Linked",
  },
  least_links: {
    key: "search.sort_option_least_links",
    fallback: "Least Linked",
  },
};

const USER_ROLE_FILTER_OPTIONS = [
  "viewer",
  "limited",
  "user",
  "admin",
  "superadmin",
];

const USER_ROLE_LABELS = {
  viewer: {
    key: "search.role_viewer",
    fallback: "Viewer",
  },
  limited: {
    key: "search.role_limited",
    fallback: "Limited",
  },
  user: {
    key: "search.role_user",
    fallback: "User",
  },
  admin: {
    key: "search.role_admin",
    fallback: "Admin",
  },
  superadmin: {
    key: "search.role_superadmin",
    fallback: "Superadmin",
  },
};

document.addEventListener("DOMContentLoaded", function () {
  const mobile = window.innerWidth <= 768;

  window.onload = function () {
    document.getElementById("searchbar").focus();
  };

  function setSelectionOverlayActive(active) {
    const isSelectionMode = document.body.classList.contains("selection-mode");
    if (!isSelectionMode) {
      document.body.classList.remove("selection-filter-overlay-open");
      return;
    }

    if (active && typeof window.closeSelectionPopovers === "function") {
      window.closeSelectionPopovers();
    }

    document.body.classList.toggle(
      "selection-filter-overlay-open",
      Boolean(active),
    );
  }

  function createContainerCacheKey(type, payload) {
    const base = payload && typeof payload === "object" ? payload : {};
    try {
      return `${type}:${JSON.stringify(base)}`;
    } catch {
      return `${type}:fallback`;
    }
  }

  function setCachedHtml(cache, key, html) {
    if (!key || typeof html !== "string" || html.trim().length === 0) {
      return;
    }

    if (cache.has(key)) {
      cache.delete(key);
    }
    cache.set(key, html);

    if (cache.size > COMPONENT_CACHE_LIMIT) {
      const oldestKey = cache.keys().next().value;
      cache.delete(oldestKey);
    }
  }

  function htmlToElement(html) {
    if (typeof html !== "string" || html.trim().length === 0) {
      return null;
    }

    const template = document.createElement("template");
    template.innerHTML = html.trim();
    return template.content.firstElementChild;
  }

  function getCachedElement(cache, key) {
    if (!key || !cache.has(key)) {
      return null;
    }

    const cachedHtml = cache.get(key);
    cache.delete(key);
    cache.set(key, cachedHtml);

    return htmlToElement(cachedHtml);
  }

  function nextFrame() {
    return new Promise((resolve) => requestAnimationFrame(resolve));
  }

  async function deleteTagOrGroupById(type, id) {
    const comp = type === "tag" ? "tags" : "groups";

    try {
      const response = await fetch("/deleteLink", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          delete: true,
          comp,
          id: Number(id),
        }),
      });

      const raw = await response.text();
      const data = raw ? JSON.parse(raw) : {};
      return data && data.status === "success";
    } catch {
      return false;
    }
  }

  // Delete tag or group function
  async function deleteTagOrGroup(type, name, id = null) {
    const parsedId = Number.parseInt(id, 10);
    if (Number.isFinite(parsedId) && parsedId > 0) {
      const deletedById = await deleteTagOrGroupById(type, parsedId);
      if (deletedById) {
        return true;
      }
    }

    const endpoints =
      type === "tag"
        ? ["/deleteTag", "/api/tags/deleteTag.php"]
        : ["/deleteGroup", "/api/groups/deleteGroup.php"];

    for (const endpoint of endpoints) {
      try {
        const response = await fetch(endpoint, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ title: name }),
        });

        const raw = await response.text();
        let data = {};

        try {
          data = raw ? JSON.parse(raw) : {};
        } catch {
          data = {
            success: false,
            message: raw || "Unexpected server response",
          };
        }

        if (data.success === true) {
          return true;
        }

        const isLikelyMissingRoute = response.status === 404;
        if (isLikelyMissingRoute) {
          continue;
        }

        createSnackbar(
          data.message ||
            (typeof getUiText === "function"
              ? getUiText(
                  "js.filter.failed_delete_item",
                  "Failed to delete item",
                )
              : "Failed to delete item"),
          "error",
        );
        return false;
      } catch (error) {
        console.error("Delete error:", error);
      }
    }

    createSnackbar(
      typeof getUiText === "function"
        ? getUiText("js.filter.error_deleting_item", "Error deleting item")
        : "Error deleting item",
      "error",
    );
    return false;
  }

  function normalizeLimit(value, fallback = 10) {
    const parsed = Number.parseInt(value, 10);
    return ALLOWED_LIMITS.includes(parsed) ? parsed : fallback;
  }

  function getAllowedSortsForPage(page) {
    return SORTS_BY_PAGE[page] || SORTS_BY_PAGE.links;
  }

  function normalizeSort(sortValue, page, fallback = "latest_modified") {
    const candidate = String(sortValue || "").trim();
    const allowed = getAllowedSortsForPage(page);
    return allowed.includes(candidate) ? candidate : fallback;
  }

  function getSortLabel(sortValue) {
    const config = SORT_LABELS[sortValue] || null;
    if (!config) {
      return String(sortValue || "latest_modified").replace(/_/g, " ");
    }

    if (typeof getUiText === "function") {
      return getUiText(config.key, config.fallback);
    }

    return config.fallback;
  }

  function normalizeRoleFilterValue(value) {
    const normalized = String(value || "")
      .trim()
      .toLowerCase();
    if (!normalized) {
      return "";
    }

    return USER_ROLE_FILTER_OPTIONS.includes(normalized) ? normalized : "";
  }

  function sanitizeRoleFilterValues() {
    const values = Array.isArray(filter.roles) ? filter.roles : [];
    filter.roles = [
      ...new Set(
        values
          .map((value) => normalizeRoleFilterValue(value))
          .filter((value) => value.length > 0),
      ),
    ];
  }

  function getRoleFilterLabel(roleValue) {
    const normalized = normalizeRoleFilterValue(roleValue);
    if (!normalized) {
      return String(roleValue || "");
    }

    const config = USER_ROLE_LABELS[normalized] || null;
    if (!config) {
      return normalized;
    }

    if (typeof getUiText === "function") {
      return getUiText(config.key, config.fallback);
    }

    return config.fallback;
  }

  function updateSortLabelUI() {
    const sortLabel = getSortLabel(filter.sort);

    const tooltip = document.getElementById("sortOptionTooltip");
    if (tooltip) {
      tooltip.textContent = sortLabel;
    }

    const sortDropdownLabel = document.getElementById("sortDropdownLabel");
    if (sortDropdownLabel) {
      sortDropdownLabel.textContent = sortLabel;
    }
  }

  function loadFilterStateFromStorage() {
    if (!pageLocation) {
      return;
    }

    try {
      const raw = localStorage.getItem("filter_" + pageLocation);
      if (!raw) {
        return;
      }

      const stored = JSON.parse(raw);
      if (!stored || typeof stored !== "object") {
        return;
      }

      if (Object.prototype.hasOwnProperty.call(stored, "limit")) {
        filter.limit = normalizeLimit(stored.limit, filter.limit);
      }

      if (Object.prototype.hasOwnProperty.call(stored, "sort")) {
        filter.sort = normalizeSort(stored.sort, pageLocation, filter.sort);
      }
    } catch (error) {
      // Ignore malformed storage and continue with defaults.
    }
  }

  function clearFilteredTags() {
    document.getElementById("activeFilters").innerHTML = "";
  }

  function filterTagsFront() {
    const chips = [];

    filter.tags.forEach(function (tag) {
      chips.push({
        id: `${String(tag)}-shown`,
        label: String(tag),
        removeFn: "removeTagFilter",
      });
    });

    filter.groups.forEach(function (group) {
      chips.push({
        id: `${String(group)}-shown`,
        label: String(group),
        removeFn: "removeGroupFilter",
      });
    });

    sanitizeRoleFilterValues();
    filter.roles.forEach(function (role) {
      chips.push({
        id: `${role}-role-shown`,
        label: getRoleFilterLabel(role),
        removeFn: "removeRoleFilter",
      });
    });

    let html = ``;
    if (chips.length > 5) {
      chips.slice(0, 5).forEach(function (chip) {
        html += `<div class="tagContainer" id="${_escapeHtml(chip.id)}" onclick="${chip.removeFn}(this.id)">${_escapeHtml(chip.label)}</div>`;
      });
      html += `<div class="tagContainer" id="moreTags" onclick="toggleTags()">+${
        chips.length - 5
      }</div>`;
    } else {
      chips.forEach(function (chip) {
        html += `<div class="tagContainer tagFilterContainer" id="${_escapeHtml(chip.id)}" onclick="${chip.removeFn}(this.id)"><div class="tagFilterText">${_escapeHtml(chip.label)}</div><i class="day-icons tagFilterIcon">&#xf0156;</i></div>`;
      });
    }

    document.getElementById("activeFilters").innerHTML = html;

    // Sync Collection dropdown label in multi-select bar
    var collectionLabel = document.getElementById("collectionDropdownLabel");
    if (collectionLabel) {
      if (filter.groups.length === 1) {
        collectionLabel.textContent = filter.groups[0];
      } else if (filter.groups.length > 1) {
        collectionLabel.textContent =
          filter.groups[0] + " +" + (filter.groups.length - 1);
      } else {
        collectionLabel.textContent =
          collectionLabel.getAttribute("data-default") || "Collection";
      }
    }
  }

  function updateFilterFromURL() {
    const params = new URLSearchParams(window.location.search);

    if (params.has("sort")) {
      filter.sort = normalizeSort(
        params.get("sort"),
        pageLocation,
        filter.sort,
      );
    }
    if (params.has("limit")) {
      filter.limit = normalizeLimit(params.get("limit"), filter.limit);
    }
    if (params.has("offset")) {
      const parsedOffset = Number.parseInt(params.get("offset"), 10);
      filter.offset =
        Number.isFinite(parsedOffset) && parsedOffset >= 0 ? parsedOffset : 0;
    }

    if (params.has("search")) filter.search = params.get("search");
    // PARKED FEATURE: search-type selector is removed from visible UI.
    // Selector markup is parked in pages/components/parked/searchTypeSelector.php
    // Keep search type fixed until that selector is intentionally restored.
    filter.searchType = "all";
    if (params.has("tags")) filter.tags = params.getAll("tags");
    if (params.has("groups")) filter.groups = params.getAll("groups");
    if (params.has("roles")) {
      filter.roles = params
        .getAll("roles")
        .map((value) => normalizeRoleFilterValue(value))
        .filter((value) => value.length > 0);
    }

    return (
      params.has("sort") ||
      params.has("limit") ||
      params.has("offset") ||
      params.has("search") ||
      params.has("tags") ||
      params.has("groups") ||
      params.has("roles")
    );
  }

  function clearFilterQueryFromURL() {
    if (!window.location.search || window.location.search.length === 0) {
      return;
    }

    const cleanUrl = `${window.location.pathname}${window.location.hash || ""}`;
    history.replaceState(null, "", cleanUrl);
  }

  function hasActiveInitialFilter(state) {
    if (!state) return false;
    return (
      (state.tags && state.tags.length > 0) ||
      (state.groups && state.groups.length > 0) ||
      (state.roles && state.roles.length > 0) ||
      state.sort !== "latest_modified" ||
      state.offset !== 0 ||
      state.search !== ""
    );
  }

  function adjustPages() {
    let countElementId = "";
    if (pageLocation === "links") {
      countElementId = "linkCount";
    } else if (pageLocation === "visitors") {
      countElementId = "visitorCount";
    } else if (pageLocation === "groups") {
      countElementId = "groupCount";
    } else if (pageLocation === "users") {
      countElementId = "userCount";
    }

    if (countElementId) {
      const countEl = document.getElementById(countElementId);
      const count = countEl ? parseInt(countEl.innerHTML) : 0;
      pages = Math.ceil(count / (filter.limit || 1));

      if (pageLocation === "groups") {
        const groupsContainer = document.getElementById("groupsContainer");
        const renderedGroups = groupsContainer
          ? groupsContainer.querySelectorAll(".outerLinkContainer").length
          : 0;
        if (renderedGroups === 0) {
          pages = 0;
        }
      }

      if (!Number.isFinite(pages) || pages < 0) {
        pages = 0;
      }

      if (pages === 0) {
        currentPage = 0;
        filter.offset = 0;
      } else {
        if (currentPage > pages) {
          currentPage = pages;
          filter.offset = (currentPage - 1) * filter.limit;
        }
        if (currentPage < 1) {
          currentPage = 1;
          filter.offset = 0;
        }
      }

      // Remove active class from all pages before setting the new one
      document
        .querySelectorAll(".activePage")
        .forEach((page) => page.classList.remove("activePage"));

      // Add activePage class to the correct page in all containers
      if (currentPage > 0) {
        document
          .querySelectorAll(`[data-page="${currentPage - 1}"]`)
          .forEach((page) => page.classList.add("activePage"));
      }

      document.querySelectorAll(".pageContainer").forEach((container) => {
        container.style.display = pages <= 1 ? "none" : "flex";
      });

      document
        .querySelectorAll(".shownContainer.bottomPage")
        .forEach((container) => {
          container.style.display = pages <= 1 ? "none" : "flex";
        });
    }
  }

  pageLocation = document.getElementById("page")
    ? document.getElementById("page").value
    : pageLocation;

  const pageLocationInput = document.getElementById("pageLocation");
  const serverInitialLimit = normalizeLimit(
    pageLocationInput?.dataset?.initialLimit,
    filter.limit,
  );
  const serverInitialSort = normalizeSort(
    pageLocationInput?.dataset?.initialSort,
    pageLocation,
    filter.sort,
  );

  filter.limit = serverInitialLimit;
  filter.sort = serverInitialSort;

  loadFilterStateFromStorage();

  const hasUrlFilters = updateFilterFromURL();
  let shouldInitialFetch =
    hasActiveInitialFilter(filter) || filter.limit !== serverInitialLimit;

  if (Number.isFinite(filter.offset) && filter.offset > 0) {
    currentPage = Math.floor(filter.offset / Math.max(1, filter.limit)) + 1;
  }

  sanitizeRoleFilterValues();

  if (hasUrlFilters) {
    shouldInitialFetch = true;
    clearFilterQueryFromURL();
  }

  // Users are server-filtered/paginated; fetch once on load so list and pager
  // always start in sync with the active limit/sort preferences.
  if (pageLocation === "users") {
    shouldInitialFetch = true;
  }

  if (filter.search.length > 0) {
    const sb = document.getElementById("searchbar");
    if (sb) {
      sb.value = filter.search;
    }
    const sl = document.getElementById("searchLabel");
    if (sl) sl.classList.add("focusedLabel");
  }

  if (
    (filter.tags && filter.tags.length > 0) ||
    (filter.groups && filter.groups.length > 0) ||
    (filter.roles && filter.roles.length > 0)
  ) {
    filterTagsFront();
  }

  if (shouldInitialFetch) {
    fetchData();
  }

  shownSelect = document.getElementById("shownSelect");
  const mobileLimitSelect = document.getElementById("mobileLimitSelect");
  const pageSizeDropdownBtn = document.getElementById("pageSizeDropdownBtn");
  const allLimitSelects = [shownSelect, mobileLimitSelect].filter(Boolean);

  const rememberOptionLabels = (selectEl) => {
    for (let opt of selectEl.options) {
      if (!optionOriginalLabels[opt.value]) {
        optionOriginalLabels[opt.value] = opt.text.replace(/^✓\s+/u, "");
      }
    }
  };

  const syncLimitSelectValues = (value) => {
    allLimitSelects.forEach((selectEl) => {
      const hasMatchingValue = Array.from(selectEl.options).some(
        (opt) => opt.value === value,
      );
      if (hasMatchingValue) {
        selectEl.value = value;
      }
    });
    // Also update the custom dropdown button label
    if (pageSizeDropdownBtn) {
      var label = pageSizeDropdownBtn.querySelector("#pageSizeDropdownLabel");
      if (label) label.textContent = value;
      pageSizeDropdownBtn.dataset.value = value;
    }
  };

  const renderLimitOptionLabels = (selectedLabelOverride = null) => {
    allLimitSelects.forEach((selectEl) => {
      for (let opt of selectEl.options) {
        const baseLabel =
          optionOriginalLabels[opt.value] || opt.text.replace(/^✓\s+/u, "");
        optionOriginalLabels[opt.value] = baseLabel;

        if (opt.value === selectEl.value) {
          const selectedLabel =
            typeof selectedLabelOverride === "string" &&
            selectedLabelOverride.trim() !== ""
              ? selectedLabelOverride
              : baseLabel;
          opt.text = `✓ ${selectedLabel}`;
        } else {
          opt.text = baseLabel;
        }
      }
    });
  };

  const applyLimitChange = (value) => {
    filter.limit = normalizeLimit(value, filter.limit);
    filter.offset = 0;
    currentPage = 1;

    syncLimitSelectValues(String(filter.limit));
    renderLimitOptionLabels();

    adjustPages();
    adjustAllPageFronts();
    fetchData();
  };

  allLimitSelects.forEach(rememberOptionLabels);

  filter.limit = normalizeLimit(filter.limit, 10);
  syncLimitSelectValues(String(filter.limit));
  renderLimitOptionLabels();

  shownSelect?.addEventListener("change", function () {
    applyLimitChange(shownSelect.value);
  });

  mobileLimitSelect?.addEventListener("change", function () {
    applyLimitChange(mobileLimitSelect.value);
  });

  // --- Page Size custom dropdown (desktop) ---
  if (pageSizeDropdownBtn) {
    pageSizeDropdownBtn.addEventListener("click", function (e) {
      e.stopPropagation();

      // Close sort dropdown if open
      var existingSort = document.getElementById("sortDropdownPanel");
      if (existingSort) existingSort.remove();

      // Toggle: if already open, close it
      var existing = document.getElementById("pageSizeDropdownPanel");
      if (existing) {
        existing.remove();
        return;
      }

      var options = [10, 20, 50, 100];
      var currentValue = String(filter.limit);

      var panel = document.createElement("div");
      panel.id = "pageSizeDropdownPanel";
      panel.className = "sortDropdownPanel";

      options.forEach(function (val) {
        var item = document.createElement("div");
        item.className = "sortDropdownItem";
        item.dataset.value = String(val);

        var label = document.createElement("span");
        label.className = "sortDropdownItemLabel";
        label.textContent = String(val);
        item.appendChild(label);

        var check = document.createElement("i");
        check.className = "material-icons sortDropdownCheck";
        check.textContent = "check";
        item.appendChild(check);

        if (String(val) === currentValue) {
          item.classList.add("selected");
        }

        item.addEventListener("click", function () {
          panel.remove();
          applyLimitChange(val);
        });

        panel.appendChild(item);
      });

      // Position below button
      var rect = pageSizeDropdownBtn.getBoundingClientRect();
      panel.style.top = rect.bottom + 4 + "px";
      panel.style.right = window.innerWidth - rect.right + "px";
      panel.style.minWidth = rect.width + "px";
      document.body.appendChild(panel);

      // Close on click outside
      function closeDropdown(ev) {
        if (!panel.contains(ev.target) && ev.target !== pageSizeDropdownBtn) {
          panel.remove();
          document.removeEventListener("click", closeDropdown, true);
          document.removeEventListener("keydown", escClose, true);
        }
      }
      function escClose(ev) {
        if (ev.key === "Escape") {
          ev.stopPropagation();
          ev.stopImmediatePropagation();
          panel.remove();
          document.removeEventListener("click", closeDropdown, true);
          document.removeEventListener("keydown", escClose, true);
          pageSizeDropdownBtn.focus();
        }
      }
      document.addEventListener("click", closeDropdown, true);
      document.addEventListener("keydown", escClose, true);
    });
  }

  allLimitSelects.forEach((selectEl) => {
    const refreshCheckState = () => renderLimitOptionLabels();
    selectEl.addEventListener("mousedown", refreshCheckState);
    selectEl.addEventListener("focus", refreshCheckState);
    selectEl.addEventListener("keydown", function (e) {
      if (
        e.key === " " ||
        e.key === "Enter" ||
        e.key === "ArrowDown" ||
        e.key === "ArrowUp"
      ) {
        refreshCheckState();
      }
    });
    selectEl.addEventListener("blur", function () {
      renderLimitOptionLabels();
    });
  });

  // helper kept for compatibility with existing fetchData calls
  function applyShownToSelect(shownCount) {
    lastShownCount = shownCount;
    renderLimitOptionLabels();
  }

  // expose helper so fetchData can call it later
  window.__updateShownLabel = applyShownToSelect;

  // determine initial pages based on counts
  adjustPages();

  // initialize search placeholder with count and sort dropdown label
  updateSearchPlaceholderCount();
  initSortDropdownLabel();

  document.addEventListener("i18n:changed", function () {
    updateSearchPlaceholderCount();
    updateSortLabelUI();
    updateFilterButtonIndicator();
  });

  pageContainers = document.querySelectorAll(".pageContainer");

  // normalize visible page buttons on first render (e.g. 20 total + 10 shown => 2 pages)
  adjustAllPageFronts();

  pageContainers.forEach(function (pageContainer) {
    let pageButtons = pageContainer.querySelectorAll(".pageButton");

    pageButtons.forEach(function (page) {
      page.addEventListener("click", function () {
        const action = page.getAttribute("data-action");

        let oldPage = currentPage;
        switch (action) {
          case "first":
            currentPage = 1;
            break;
          case "previous":
            if (currentPage === 1) return;
            currentPage -= 1;
            break;
          case "next":
            if (currentPage === pages) return;
            currentPage += 1;
            break;
          case "last":
            currentPage = pages;
            break;
          default:
            // guard against missing data-page attribute (for 'of' links etc.)
            if (!page.hasAttribute("data-page")) return;
            currentPage = parseInt(page.getAttribute("data-page")) + 1;
            break;
        }
        filter.offset = (currentPage - 1) * filter.limit;

        // Update all containers' pagination
        adjustAllPageFronts();

        // Sync active page class across all containers
        document
          .querySelectorAll(".activePage")
          .forEach((active) => active.classList.remove("activePage"));
        document
          .querySelectorAll(`[data-page="${currentPage - 1}"]`)
          .forEach((active) => active.classList.add("activePage"));

        fetchData();
      });
    });
  });

  // Collection dropdown in multi-select bar: reuse filter modal
  var collectionDropdownBtn = document.getElementById("collectionDropdownBtn");
  if (collectionDropdownBtn) {
    // Store default label text
    var collLabel = document.getElementById("collectionDropdownLabel");
    if (collLabel && !collLabel.getAttribute("data-default")) {
      collLabel.setAttribute("data-default", collLabel.textContent);
    }
    collectionDropdownBtn.addEventListener("click", function () {
      var filterBtn = document.getElementById("filterTagsGroups");
      if (filterBtn) filterBtn.click();
    });
  }

  if (pageLocation != "groups") {
    const filterTagsGroupsBtn = document.getElementById("filterTagsGroups");
    if (filterTagsGroupsBtn) {
      filterTagsGroupsBtn.addEventListener("click", function () {
        if (
          document.getElementById("modalContainer") ||
          document.getElementById("sortModal")
        ) {
          return;
        }

        const filterTrigger =
          document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;

        // Save current filter state
        let oldTagsFilter = filter.tags;
        let oldGroupsFilter = filter.groups;

        let link = "/getTagGroupModal";
        if (pageLocation === "visitors") {
          link = "/getTagGroupModal?comp=visitors";
        } else if (pageLocation === "links") {
          link = "/getTagGroupModal?comp=links";
        }
        fetch(link, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ filter: filter }),
        })
          .then((response) => response.text())
          .then((data) => {
            var parser = new DOMParser();
            var doc = parser.parseFromString(data, "text/html");
            var element = doc.body.firstElementChild;
            if (!element) {
              return;
            }
            document.body.appendChild(element);
            setSelectionOverlayActive(true);

            const activeTagGroupModal = element;
            const activeTagGroupModalContent =
              activeTagGroupModal?.querySelector(".tagGroupModalContent");

            const tagGroupSearchInput = activeTagGroupModal.querySelector(
              "#tagGroupSearchInput",
            );
            const tagToggleTags =
              activeTagGroupModal.querySelector("#tagToggleTags");
            const tagToggleGroups =
              activeTagGroupModal.querySelector("#tagToggleGroups");
            const tagGroupSection =
              activeTagGroupModal.querySelector("#tagGroup");
            const groupGroupSection =
              activeTagGroupModal.querySelector("#groupGroup");
            const tagsDropdown =
              activeTagGroupModal.querySelector("#tagsDropdown");
            const groupsDropdown =
              activeTagGroupModal.querySelector("#groupsDropdown");
            const tagsArrow = activeTagGroupModal.querySelector("#tagsArrow");
            const groupsArrow =
              activeTagGroupModal.querySelector("#groupsArrow");
            const deleteTagGroupButton =
              activeTagGroupModal.querySelector("#deleteTagGroup");
            const tagsDropdownContent = activeTagGroupModal.querySelector(
              "#tagsDropdownContent",
            );
            const groupsDropdownContent = activeTagGroupModal.querySelector(
              "#groupsDropdownContent",
            );
            const tagIdByTitle = new Map();
            const groupIdByTitle = new Map();
            const modalIsAdmin =
              activeTagGroupModalContent?.getAttribute("data-is-admin") ===
              "true";

            const deletedTagGroup =
              activeTagGroupModal.querySelector("#deletedTagGroup");
            const deletedTagsDropdown = activeTagGroupModal.querySelector(
              "#deletedTagsDropdown",
            );
            const deletedTagsDropdownContent =
              activeTagGroupModal.querySelector("#deletedTagsDropdownContent");
            const deletedTagsArrow =
              activeTagGroupModal.querySelector("#deletedTagsArrow");
            let currentTagGroupSearchTerm = "";

            function normalizeFilterLabel(value) {
              return String(value ?? "").trim();
            }

            function sanitizeSelectedFilterValues() {
              const nextTags = Array.isArray(filter.tags) ? filter.tags : [];
              const nextGroups = Array.isArray(filter.groups)
                ? filter.groups
                : [];

              filter.tags = [
                ...new Set(
                  nextTags
                    .map((value) => normalizeFilterLabel(value))
                    .filter((value) => value.length > 0),
                ),
              ];
              filter.groups = [
                ...new Set(
                  nextGroups
                    .map((value) => normalizeFilterLabel(value))
                    .filter((value) => value.length > 0),
                ),
              ];
            }

            function registerTagId(title, id) {
              const safeTitle = String(title || "").trim();
              const safeId = Number.parseInt(id, 10);
              if (!safeTitle || !Number.isFinite(safeId) || safeId <= 0) {
                return;
              }
              tagIdByTitle.set(safeTitle, safeId);
            }

            function registerGroupId(title, id) {
              const safeTitle = String(title || "").trim();
              const safeId = Number.parseInt(id, 10);
              if (!safeTitle || !Number.isFinite(safeId) || safeId <= 0) {
                return;
              }
              groupIdByTitle.set(safeTitle, safeId);
            }

            async function hydrateDeleteMaps() {
              try {
                const response = await fetch("/fetchTagsGroups", {
                  method: "POST",
                  headers: {
                    "Content-Type": "application/json",
                  },
                  body: JSON.stringify({ comp: pageLocation }),
                });

                const payload = await response.json();
                const tags = Array.isArray(payload?.tags) ? payload.tags : [];
                const groups = Array.isArray(payload?.groups)
                  ? payload.groups
                  : [];

                tags.forEach((tag) => registerTagId(tag?.title, tag?.id));
                groups.forEach((group) =>
                  registerGroupId(group?.title, group?.id),
                );
              } catch {
                return;
              }
            }

            function getDeleteTargetContext() {
              return {
                selectedTags: [...filter.tags],
                selectedGroups: [...filter.groups],
              };
            }

            function syncSelectedFilterStateFromDom() {
              if (!activeTagGroupModal) {
                return;
              }

              const selectedTags = Array.from(
                tagsDropdownContent?.querySelectorAll(".filterTag.selected") ||
                  [],
              )
                .map((node) =>
                  normalizeFilterLabel(node.dataset.value || node.id || ""),
                )
                .filter((value) => value.length > 0);

              const selectedGroups = Array.from(
                groupsDropdownContent?.querySelectorAll(
                  ".filterGroup.selected",
                ) || [],
              )
                .map((node) =>
                  normalizeFilterLabel(node.dataset.value || node.id || ""),
                )
                .filter((value) => value.length > 0);

              filter.tags = [...new Set(selectedTags)];
              filter.groups = [...new Set(selectedGroups)];
            }

            function syncTagGroupArrowState() {
              const tagsOpen =
                !!tagGroupSection && tagGroupSection.style.display !== "none";
              const groupsOpen =
                !!groupGroupSection &&
                groupGroupSection.style.display !== "none";

              if (tagsArrow) {
                tagsArrow.textContent = tagsOpen
                  ? "keyboard_arrow_up"
                  : "keyboard_arrow_down";
              }

              if (groupsArrow) {
                groupsArrow.textContent = groupsOpen
                  ? "keyboard_arrow_up"
                  : "keyboard_arrow_down";
              }

              if (tagsDropdown) {
                tagsDropdown.setAttribute(
                  "aria-expanded",
                  tagsOpen ? "true" : "false",
                );
              }

              if (groupsDropdown) {
                groupsDropdown.setAttribute(
                  "aria-expanded",
                  groupsOpen ? "true" : "false",
                );
              }
            }

            function setTagGroupToggle(target) {
              const showTags = target !== "groups";

              if (tagGroupSection) {
                tagGroupSection.style.display = showTags ? "" : "none";
                if (showTags) {
                  tagGroupSection.classList.add("show");
                }
              }

              if (groupGroupSection) {
                groupGroupSection.style.display = showTags ? "none" : "";
                if (!showTags) {
                  groupGroupSection.classList.add("show");
                }
              }

              if (tagToggleTags) {
                tagToggleTags.classList.toggle("is-active", showTags);
                tagToggleTags.setAttribute(
                  "aria-selected",
                  showTags ? "true" : "false",
                );
              }

              if (tagToggleGroups) {
                tagToggleGroups.classList.toggle("is-active", !showTags);
                tagToggleGroups.setAttribute(
                  "aria-selected",
                  !showTags ? "true" : "false",
                );
              }

              syncTagGroupArrowState();
            }

            function updateTagGroupCounts() {
              const tagsCount = filter.tags.length;
              const groupsCount = filter.groups.length;
              const total = tagsCount + groupsCount;

              const tagsCountNode =
                activeTagGroupModal.querySelector("#tagsSelectedCount");
              const groupsCountNode = activeTagGroupModal.querySelector(
                "#groupsSelectedCount",
              );
              const submitNode =
                activeTagGroupModal.querySelector("#submitTagGroup");
              const deleteNode =
                activeTagGroupModal.querySelector("#deleteTagGroup");

              if (tagsCountNode) {
                tagsCountNode.textContent = String(tagsCount);
              }

              if (groupsCountNode) {
                groupsCountNode.textContent = String(groupsCount);
              }

              if (submitNode) {
                const filterLabel =
                  typeof getUiText === "function"
                    ? getUiText("modals.tag_group.filter", "Filter")
                    : "Filter";
                submitNode.textContent =
                  total > 0 ? `${filterLabel} (${total})` : filterLabel;
              }

              if (deleteNode) {
                const deleteContext = getDeleteTargetContext();
                const deleteCount =
                  deleteContext.selectedTags.length +
                  deleteContext.selectedGroups.length;
                const deleteLabel =
                  typeof getUiText === "function"
                    ? getUiText("modals.tag_group.delete", "Delete")
                    : "Delete";
                deleteNode.textContent =
                  deleteCount > 0
                    ? `${deleteLabel} (${deleteCount})`
                    : deleteLabel;
                deleteNode.disabled = deleteCount === 0;
              }
            }

            function applyTagGroupSearch() {
              const term = currentTagGroupSearchTerm.trim().toLowerCase();
              const items = activeTagGroupModal.querySelectorAll(
                ".filterTag, .filterGroup",
              );

              items.forEach(function (item) {
                const text = String(
                  item.dataset.value || item.textContent || "",
                )
                  .trim()
                  .toLowerCase();
                const shouldShow = term === "" || text.includes(term);
                item.style.display = shouldShow ? "" : "none";
              });
            }

            function bindSelectionDelegates() {
              if (
                tagsDropdownContent &&
                tagsDropdownContent.dataset.selectionBound !== "true"
              ) {
                tagsDropdownContent.dataset.selectionBound = "true";
                tagsDropdownContent.addEventListener("click", function (event) {
                  if (event.target.closest(".tagRenameBtn")) {
                    return;
                  }
                  const tagNode = event.target.closest(".filterTag");
                  if (!tagNode || !tagsDropdownContent.contains(tagNode)) {
                    return;
                  }
                  if (tagNode.classList.contains("tagRenaming")) {
                    return;
                  }

                  tagNode.classList.toggle("selected");
                  syncSelectedFilterStateFromDom();
                  updateTagGroupCounts();
                });
              }

              if (
                groupsDropdownContent &&
                groupsDropdownContent.dataset.selectionBound !== "true"
              ) {
                groupsDropdownContent.dataset.selectionBound = "true";
                groupsDropdownContent.addEventListener(
                  "click",
                  function (event) {
                    if (event.target.closest(".tagRenameBtn")) {
                      return;
                    }
                    const groupNode = event.target.closest(".filterGroup");
                    if (
                      !groupNode ||
                      !groupsDropdownContent.contains(groupNode)
                    ) {
                      return;
                    }
                    if (groupNode.classList.contains("tagRenaming")) {
                      return;
                    }

                    groupNode.classList.toggle("selected");
                    syncSelectedFilterStateFromDom();
                    updateTagGroupCounts();
                  },
                );
              }
            }

            // --- Inline rename (admin only, desktop only) ---
            var isDesktop =
              typeof window.matchMedia === "function" &&
              window.matchMedia("(min-width: 769px)").matches;

            function renderItemContent(el, title, type) {
              el.innerHTML = "";
              if (modalIsAdmin && isDesktop) {
                var label = document.createElement("span");
                label.className = "tagLabel";
                label.textContent = title;
                el.appendChild(label);
                var renameBtn = document.createElement("button");
                renameBtn.className = "tagRenameBtn";
                renameBtn.type = "button";
                renameBtn.title =
                  typeof getUiText === "function"
                    ? getUiText(
                        type === "group"
                          ? "js.filter.rename_group"
                          : "js.filter.rename_tag",
                        "Rename",
                      )
                    : "Rename";
                renameBtn.innerHTML = '<i class="material-icons">edit</i>';
                el.appendChild(renameBtn);
              } else {
                el.textContent = title;
              }
            }

            function bindRenameDelegates() {
              if (!modalIsAdmin || !isDesktop) {
                return;
              }

              if (
                tagsDropdownContent &&
                tagsDropdownContent.dataset.renameBound !== "true"
              ) {
                tagsDropdownContent.dataset.renameBound = "true";
                tagsDropdownContent.addEventListener("click", function (event) {
                  var renameBtn = event.target.closest(".tagRenameBtn");
                  if (!renameBtn) {
                    return;
                  }
                  event.stopPropagation();
                  var el = renameBtn.closest(".filterTag");
                  if (!el || el.classList.contains("tagRenaming")) {
                    return;
                  }
                  startInlineRename(el, "tag");
                });
              }

              if (
                groupsDropdownContent &&
                groupsDropdownContent.dataset.renameBound !== "true"
              ) {
                groupsDropdownContent.dataset.renameBound = "true";
                groupsDropdownContent.addEventListener(
                  "click",
                  function (event) {
                    var renameBtn = event.target.closest(".tagRenameBtn");
                    if (!renameBtn) {
                      return;
                    }
                    event.stopPropagation();
                    var el = renameBtn.closest(".filterGroup");
                    if (!el || el.classList.contains("tagRenaming")) {
                      return;
                    }
                    startInlineRename(el, "group");
                  },
                );
              }
            }

            function startInlineRename(el, type) {
              var itemId = el.dataset.itemId;
              var currentValue = el.dataset.value || "";
              if (!itemId) {
                return;
              }

              el.classList.add("tagRenaming");
              el.innerHTML = "";

              var input = document.createElement("input");
              input.type = "text";
              input.className = "tagRenameInput";
              input.value = currentValue;
              el.appendChild(input);
              input.focus();
              input.select();

              var finished = false;
              function finishRename(save) {
                if (finished) {
                  return;
                }
                finished = true;
                var newValue = input.value.trim();
                if (!save || !newValue || newValue === currentValue) {
                  el.classList.remove("tagRenaming");
                  renderItemContent(el, currentValue, type);
                  return;
                }
                saveInlineRename(el, itemId, currentValue, newValue, type);
              }

              input.addEventListener("keydown", function (e) {
                if (e.key === "Enter") {
                  e.preventDefault();
                  e.stopPropagation();
                  finishRename(true);
                } else if (e.key === "Escape") {
                  e.preventDefault();
                  e.stopPropagation();
                  e.stopImmediatePropagation();
                  finishRename(false);
                }
              });

              input.addEventListener("blur", function () {
                setTimeout(function () {
                  finishRename(true);
                }, 150);
              });
            }

            async function saveInlineRename(
              el,
              itemId,
              oldTitle,
              newTitle,
              type,
            ) {
              var endpoint = type === "group" ? "/renameGroup" : "/renameTag";
              var idMap = type === "group" ? groupIdByTitle : tagIdByTitle;
              var filterArr = type === "group" ? filter.groups : filter.tags;
              var successKey =
                type === "group"
                  ? "js.filter.group_renamed"
                  : "js.filter.tag_renamed";
              var successFallback =
                type === "group"
                  ? "Group renamed successfully"
                  : "Tag renamed successfully";
              var failKey =
                type === "group"
                  ? "js.filter.group_rename_failed"
                  : "js.filter.rename_failed";
              var failFallback =
                type === "group"
                  ? "Failed to rename group"
                  : "Failed to rename tag";

              try {
                var response = await fetch(endpoint, {
                  method: "POST",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({
                    id: Number(itemId),
                    title: newTitle,
                  }),
                });
                var result = await response.json();
                if (result && result.success) {
                  var normalizedNew = normalizeFilterLabel(newTitle);
                  el.classList.remove("tagRenaming");
                  el.id = normalizedNew;
                  el.dataset.value = normalizedNew;
                  renderItemContent(el, normalizedNew, type);

                  idMap.delete(normalizeFilterLabel(oldTitle));
                  idMap.set(normalizedNew, Number(itemId));

                  var normalizedOld = normalizeFilterLabel(oldTitle);
                  var idx = filterArr.indexOf(normalizedOld);
                  if (idx !== -1) {
                    filterArr[idx] = normalizedNew;
                  }
                  syncSelectedFilterStateFromDom();

                  if (typeof createSnackbar === "function") {
                    createSnackbar(
                      typeof getUiText === "function"
                        ? getUiText(successKey, successFallback)
                        : successFallback,
                      "success",
                    );
                  }
                } else {
                  el.classList.remove("tagRenaming");
                  renderItemContent(el, oldTitle, type);
                  if (typeof createSnackbar === "function") {
                    createSnackbar(
                      (result && result.message) ||
                        (typeof getUiText === "function"
                          ? getUiText(failKey, failFallback)
                          : failFallback),
                      "error",
                    );
                  }
                }
              } catch (err) {
                el.classList.remove("tagRenaming");
                renderItemContent(el, oldTitle, type);
                if (typeof createSnackbar === "function") {
                  createSnackbar(
                    typeof getUiText === "function"
                      ? getUiText(failKey, failFallback)
                      : failFallback,
                    "error",
                  );
                }
              }
            }

            let tagGroupSearchDebounce = null;
            if (tagGroupSearchInput) {
              tagGroupSearchInput.addEventListener("input", function (event) {
                currentTagGroupSearchTerm = String(event.target.value || "");
                clearTimeout(tagGroupSearchDebounce);
                tagGroupSearchDebounce = setTimeout(applyTagGroupSearch, 120);
              });

              requestAnimationFrame(function () {
                tagGroupSearchInput.focus();
              });
            }

            if (tagToggleTags) {
              tagToggleTags.addEventListener("click", function () {
                setTagGroupToggle("tags");
              });
            }

            if (tagToggleGroups) {
              tagToggleGroups.addEventListener("click", function () {
                setTagGroupToggle("groups");
              });
            }

            setTagGroupToggle("tags");
            bindSelectionDelegates();
            bindRenameDelegates();
            hydrateDeleteMaps();
            sanitizeSelectedFilterValues();

            loadTagsGroups();

            updateTagGroupCounts();

            activeTagGroupModal
              .querySelectorAll(".closeTagGroupModal")
              .forEach(function (close) {
                close.addEventListener("click", function () {
                  activeTagGroupModal.remove();
                  setSelectionOverlayActive(false);
                  // Reset filter to previous state
                  filter.tags = oldTagsFilter;
                  filter.groups = oldGroupsFilter;
                  restoreFilterTriggerFocus();
                });
              });

            if (tagGroupSection) {
              tagGroupSection.classList.add("show");
            }

            if (groupGroupSection) {
              groupGroupSection.classList.add("show");
            }

            syncTagGroupArrowState();

            // Focus trap for filter modal
            let filterModalTrap = null;
            if (
              activeTagGroupModalContent &&
              typeof window.trapFocus === "function"
            ) {
              filterModalTrap = window.trapFocus(activeTagGroupModalContent);
            }

            const restoreFilterTriggerFocus = function () {
              if (filterModalTrap) {
                filterModalTrap.release();
                filterModalTrap = null;
              }
              if (filterTrigger && document.body.contains(filterTrigger)) {
                requestAnimationFrame(function () {
                  try {
                    filterTrigger.focus({ preventScroll: true });
                  } catch (e) {}
                });
              }
            };

            // Deleted tags/groups toggle and loading
            if (deletedTagsDropdown) {
              deletedTagsDropdown.addEventListener("click", function () {
                deletedTagGroup?.classList.toggle("show");
                if (deletedTagsArrow) {
                  deletedTagsArrow.textContent =
                    deletedTagGroup?.classList.contains("show")
                      ? "keyboard_arrow_up"
                      : "keyboard_arrow_down";
                }
              });
            }

            if (modalIsAdmin && deletedTagsDropdownContent) {
              loadDeletedTagsGroups();
            }

            async function loadDeletedTagsGroups() {
              try {
                const response = await fetch("/getDeletedTagsGroups", {
                  method: "POST",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({}),
                });
                const data = await response.json();
                const deletedTags = Array.isArray(data?.tags) ? data.tags : [];
                const deletedGroups = Array.isArray(data?.groups)
                  ? data.groups
                  : [];
                const allDeleted = [
                  ...deletedTags.map(function (t) {
                    return {
                      type: "tag",
                      id: t.table_id,
                      archiveId: t.id,
                      title: t.title,
                      created_at: t.created_at,
                    };
                  }),
                  ...deletedGroups.map(function (g) {
                    return {
                      type: "group",
                      id: g.table_id,
                      archiveId: g.id,
                      title: g.title,
                      created_at: g.created_at,
                    };
                  }),
                ];

                deletedTagsDropdownContent.innerHTML = "";

                if (allDeleted.length === 0) {
                  deletedTagGroup.style.display = "none";
                  return;
                }

                deletedTagGroup.style.display = "";
                var deletedTitle =
                  activeTagGroupModal.querySelector("#deletedTagsTitle");
                if (deletedTitle) {
                  deletedTitle.textContent =
                    "Recently removed (" + allDeleted.length + ")";
                }

                allDeleted.forEach(function (item) {
                  var row = document.createElement("div");
                  row.classList.add("deletedTagRow");

                  var label = document.createElement("span");
                  label.classList.add("deletedTagLabel");
                  label.textContent = item.title;

                  var typeBadge = document.createElement("span");
                  typeBadge.classList.add("deletedTagType");
                  typeBadge.textContent = item.type;

                  var actionWrap = document.createElement("div");
                  actionWrap.classList.add("deletedTagActions");

                  var restoreBtn = document.createElement("button");
                  restoreBtn.type = "button";
                  restoreBtn.classList.add("restoreDeletedTagButton");
                  restoreBtn.textContent = "Restore";
                  restoreBtn.addEventListener("click", async function (e) {
                    e.stopPropagation();
                    if (restoreBtn.disabled) {
                      return;
                    }

                    restoreBtn.disabled = true;
                    var endpoint =
                      item.type === "tag" ? "/restoreTag" : "/restoreGroup";
                    try {
                      var res = await fetch(endpoint, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({
                          id: item.id,
                          title: item.title,
                        }),
                      });

                      var raw = await res.text();
                      var result = null;

                      if (raw) {
                        try {
                          result = JSON.parse(raw);
                        } catch (parseError) {
                          throw new Error(
                            "Restore failed: invalid server response.",
                          );
                        }
                      }

                      if (!res.ok) {
                        throw new Error(
                          (result && result.message) ||
                            "Restore failed (" + res.status + ").",
                        );
                      }

                      if (!result || result.success !== true) {
                        throw new Error(
                          (result && result.message) || "Restore failed.",
                        );
                      }

                      if (result.success) {
                        row.remove();
                        loadTagsGroups();
                        if (typeof createSnackbar === "function") {
                          createSnackbar(
                            result.message || item.title + " restored",
                            "success",
                          );
                        }
                        // Update count or hide section
                        var remaining =
                          deletedTagsDropdownContent.children.length;
                        if (remaining === 0) {
                          deletedTagGroup.style.display = "none";
                        } else if (deletedTitle) {
                          deletedTitle.textContent =
                            "Recently removed (" + remaining + ")";
                        }
                      }
                    } catch (err) {
                      if (typeof createSnackbar === "function") {
                        createSnackbar(
                          (err && err.message) || "Restore failed",
                          "error",
                        );
                      }
                    } finally {
                      if (document.body.contains(restoreBtn)) {
                        restoreBtn.disabled = false;
                      }
                    }
                  });

                  actionWrap.appendChild(typeBadge);
                  actionWrap.appendChild(restoreBtn);
                  row.appendChild(label);
                  row.appendChild(actionWrap);
                  deletedTagsDropdownContent.appendChild(row);
                });
              } catch (err) {
                deletedTagGroup.style.display = "none";
              }
            }

            const submitTagGroupBtn =
              activeTagGroupModal.querySelector("#submitTagGroup");
            if (submitTagGroupBtn) {
              submitTagGroupBtn.addEventListener("click", function () {
                filter.offset = 0;
                currentPage = 1;
                filterTagsFront();
                adjustPages();
                adjustAllPageFronts();
                fetchData();
                activeTagGroupModal.remove();
                setSelectionOverlayActive(false);
                restoreFilterTriggerFocus();
              });
            }

            function loadTagsGroups() {
              if (filter.groups.length > 0 || filter.tags.length > 0) {
                fetch("/fetchRelatedTags", {
                  method: "POST",
                  headers: {
                    "Content-Type": "application/json",
                  },
                  body: JSON.stringify({
                    groups: filter.groups,
                    tags: filter.tags,
                    comp: pageLocation,
                  }),
                })
                  .then((response) => response.json())
                  .then((data) => {
                    let groupDropdown = groupsDropdownContent;
                    groupDropdown.innerHTML = "";
                    let tagDropdown = tagsDropdownContent;
                    tagDropdown.innerHTML = "";
                    const selectedTags = new Set(
                      (Array.isArray(filter.tags) ? filter.tags : [])
                        .map((value) => normalizeFilterLabel(value))
                        .filter((value) => value.length > 0),
                    );
                    const selectedGroups = new Set(
                      (Array.isArray(filter.groups) ? filter.groups : [])
                        .map((value) => normalizeFilterLabel(value))
                        .filter((value) => value.length > 0),
                    );

                    const tags = Array.isArray(data?.tags) ? data.tags : [];
                    tags.forEach(function (tag) {
                      const normalizedTag = normalizeFilterLabel(tag);
                      if (!normalizedTag) {
                        return;
                      }

                      let tagElement = document.createElement("div");
                      tagElement.classList.add("tagContainer");
                      tagElement.classList.add("filterTag");
                      if (selectedTags.has(normalizedTag)) {
                        tagElement.classList.add("selected");
                      }
                      tagElement.id = normalizedTag;
                      tagElement.dataset.value = normalizedTag;
                      const mappedTagId = tagIdByTitle.get(normalizedTag);
                      if (Number.isFinite(mappedTagId)) {
                        tagElement.dataset.itemId = String(mappedTagId);
                      }
                      renderItemContent(tagElement, normalizedTag, "tag");
                      tagDropdown.appendChild(tagElement);
                    });
                    const groups = Array.isArray(data?.groups)
                      ? data.groups
                      : [];
                    groups.forEach(function (group) {
                      const normalizedGroup = normalizeFilterLabel(group);
                      if (!normalizedGroup) {
                        return;
                      }

                      let groupElement = document.createElement("div");
                      groupElement.classList.add("tagContainer");
                      groupElement.classList.add("filterGroup");
                      if (selectedGroups.has(normalizedGroup)) {
                        groupElement.classList.add("selected");
                      }
                      groupElement.id = normalizedGroup;
                      groupElement.dataset.value = normalizedGroup;
                      const mappedGroupId = groupIdByTitle.get(normalizedGroup);
                      if (Number.isFinite(mappedGroupId)) {
                        groupElement.dataset.itemId = String(mappedGroupId);
                      }
                      renderItemContent(groupElement, normalizedGroup, "group");
                      groupDropdown.appendChild(groupElement);
                    });

                    syncSelectedFilterStateFromDom();
                    updateTagGroupCounts();
                    applyTagGroupSearch();
                  });
              } else {
                fetch("/fetchTagsGroups", {
                  method: "POST",
                  headers: {
                    "Content-Type": "application/json",
                  },
                  body: JSON.stringify({ comp: pageLocation }),
                })
                  .then((response) => response.text())
                  .then((data) => {
                    let tagGroupData = JSON.parse(data);
                    let tagDropdown = tagsDropdownContent;
                    tagDropdown.innerHTML = "";
                    let groupDropdown = groupsDropdownContent;
                    groupDropdown.innerHTML = "";
                    const selectedTags = new Set(
                      (Array.isArray(filter.tags) ? filter.tags : [])
                        .map((value) => normalizeFilterLabel(value))
                        .filter((value) => value.length > 0),
                    );
                    const selectedGroups = new Set(
                      (Array.isArray(filter.groups) ? filter.groups : [])
                        .map((value) => normalizeFilterLabel(value))
                        .filter((value) => value.length > 0),
                    );

                    const tags = Array.isArray(tagGroupData?.tags)
                      ? tagGroupData.tags
                      : [];
                    tags.forEach(function (tag) {
                      const normalizedTag = normalizeFilterLabel(tag?.title);
                      if (!normalizedTag) {
                        return;
                      }

                      registerTagId(normalizedTag, tag.id);
                      let tagElement = document.createElement("div");
                      tagElement.classList.add("tagContainer");
                      tagElement.classList.add("filterTag");
                      if (selectedTags.has(normalizedTag)) {
                        tagElement.classList.add("selected");
                      }
                      tagElement.id = normalizedTag;
                      tagElement.dataset.value = normalizedTag;
                      if (Number.isFinite(Number.parseInt(tag.id, 10))) {
                        tagElement.dataset.itemId = String(tag.id);
                      }
                      renderItemContent(tagElement, normalizedTag, "tag");
                      tagDropdown.appendChild(tagElement);
                    });

                    const groups = Array.isArray(tagGroupData?.groups)
                      ? tagGroupData.groups
                      : [];
                    groups.forEach(function (group) {
                      const normalizedGroup = normalizeFilterLabel(
                        group?.title,
                      );
                      if (!normalizedGroup) {
                        return;
                      }

                      registerGroupId(normalizedGroup, group.id);
                      let groupElement = document.createElement("div");
                      groupElement.classList.add("tagContainer");
                      groupElement.classList.add("filterGroup");
                      if (selectedGroups.has(normalizedGroup)) {
                        groupElement.classList.add("selected");
                      }
                      groupElement.id = normalizedGroup;
                      groupElement.dataset.value = normalizedGroup;
                      if (Number.isFinite(Number.parseInt(group.id, 10))) {
                        groupElement.dataset.itemId = String(group.id);
                      }
                      renderItemContent(groupElement, normalizedGroup, "group");
                      groupDropdown.appendChild(groupElement);
                    });

                    syncSelectedFilterStateFromDom();
                    updateTagGroupCounts();
                    applyTagGroupSearch();
                  });
              }
            }

            if (deleteTagGroupButton && modalIsAdmin) {
              deleteTagGroupButton.addEventListener("click", async function () {
                const deleteContext = getDeleteTargetContext();
                const tagsToDelete = [...new Set(deleteContext.selectedTags)];
                const groupsToDelete = [
                  ...new Set(deleteContext.selectedGroups),
                ];
                const totalToDelete =
                  tagsToDelete.length + groupsToDelete.length;

                if (!totalToDelete) {
                  createSnackbar(
                    typeof getUiText === "function"
                      ? getUiText(
                          "js.filter.select_items_to_delete",
                          "Select one or more items to delete",
                        )
                      : "Select one or more items to delete",
                    "error",
                  );
                  return;
                }

                const confirmMessageKey =
                  "js.filter.confirm_delete_selected_items";
                const confirmFallback = "Weet je zeker? / Are you sure?";
                const confirmMessage = getUiText(
                  confirmMessageKey,
                  confirmFallback,
                );
                const confirmed =
                  typeof window.showThemedConfirm === "function"
                    ? await window.showThemedConfirm(confirmMessage)
                    : window.confirm(confirmMessage);

                if (!confirmed) {
                  return;
                }

                let successCount = 0;
                const failedTags = [];
                const failedGroups = [];

                for (const itemName of tagsToDelete) {
                  const tagId = tagIdByTitle.get(itemName);
                  const ok = await deleteTagOrGroup("tag", itemName, tagId);
                  if (ok) {
                    successCount += 1;
                  } else {
                    failedTags.push(itemName);
                  }
                }

                for (const itemName of groupsToDelete) {
                  const groupId = groupIdByTitle.get(itemName);
                  const ok = await deleteTagOrGroup("group", itemName, groupId);
                  if (ok) {
                    successCount += 1;
                  } else {
                    failedGroups.push(itemName);
                  }
                }

                filter.tags = failedTags;
                filter.groups = failedGroups;

                loadTagsGroups();
                if (modalIsAdmin && deletedTagsDropdownContent) {
                  loadDeletedTagsGroups();
                }
                filter.offset = 0;
                currentPage = 1;
                filterTagsFront();
                adjustPages();
                adjustAllPageFronts();
                fetchData();

                if (successCount === totalToDelete) {
                  createSnackbar(
                    getUiText(
                      "js.filter.deleted_selected_items",
                      "Selected items deleted",
                    ),
                    "success",
                  );
                  return;
                }

                createSnackbar(
                  getUiText(
                    "js.filter.failed_delete_some_items",
                    "Some selected items could not be deleted",
                  ),
                  "error",
                );
              });
            }
          })
          .catch((err) => {
            return;
          });
      });
    }
  }

  const openFilterPanelBtn = document.getElementById("openFilterPanel");
  if (openFilterPanelBtn) {
    openFilterPanelBtn.addEventListener("click", function (event) {
      event.stopPropagation();

      if (
        document.getElementById("modalContainer") ||
        document.getElementById("sortModal")
      ) {
        return;
      }

      const existingSort = document.getElementById("sortDropdownPanel");
      if (existingSort) existingSort.remove();
      const existingPageSize = document.getElementById("pageSizeDropdownPanel");
      if (existingPageSize) existingPageSize.remove();

      const existingPanel = document.getElementById("unifiedFilterPanel");
      if (existingPanel) {
        existingPanel.remove();
        return;
      }

      sanitizeRoleFilterValues();

      const panel = document.createElement("div");
      panel.id = "unifiedFilterPanel";
      panel.className = "sortDropdownPanel unifiedFilterPanel";

      let hasSections = false;

      // ── Tags & Groups section ──────────────────────────────
      if (pageLocation !== "groups") {
        const section = document.createElement("div");
        section.className = "filterPanelSection";

        const sectionLabel = document.createElement("div");
        sectionLabel.className = "filterPanelSectionLabel";
        sectionLabel.textContent = getUiText(
          "search.filter_tags_groups_section",
          "Tags & Groups",
        );
        section.appendChild(sectionLabel);

        const browseItem = document.createElement("div");
        browseItem.className = "sortDropdownItem filterPanelBrowseItem";

        const browseIcon = document.createElement("i");
        browseIcon.className = "material-icons";
        browseIcon.textContent = "filter_alt";
        browseItem.appendChild(browseIcon);

        const browseLabel = document.createElement("span");
        browseLabel.className = "sortDropdownItemLabel";
        browseLabel.textContent = getUiText(
          "search.browse_tags_groups",
          "Browse tags & groups",
        );
        browseItem.appendChild(browseLabel);

        const tagGroupCount = filter.tags.length + filter.groups.length;
        if (tagGroupCount > 0) {
          const countBadge = document.createElement("span");
          countBadge.className = "filterPanelActiveBadge";
          countBadge.textContent = String(tagGroupCount);
          browseItem.appendChild(countBadge);
        }

        const browseArrow = document.createElement("i");
        browseArrow.className = "material-icons filterPanelArrow";
        browseArrow.textContent = "chevron_right";
        browseItem.appendChild(browseArrow);

        browseItem.addEventListener("click", function (e) {
          e.stopPropagation();
          closeUnifiedPanel(false);
          const hiddenFilterBtn = document.getElementById("filterTagsGroups");
          if (hiddenFilterBtn) hiddenFilterBtn.click();
        });

        section.appendChild(browseItem);
        panel.appendChild(section);
        hasSections = true;
      }

      // ── Roles section (users page only) ───────────────────
      if (pageLocation === "users") {
        if (hasSections) {
          const divider = document.createElement("div");
          divider.className = "filterPanelDivider";
          panel.appendChild(divider);
        }

        const section = document.createElement("div");
        section.className = "filterPanelSection";

        const sectionLabel = document.createElement("div");
        sectionLabel.className = "filterPanelSectionLabel";
        sectionLabel.textContent = getUiText(
          "search.filter_roles_section",
          "Roles",
        );
        section.appendChild(sectionLabel);

        const selectedRoles = new Set(filter.roles);

        USER_ROLE_FILTER_OPTIONS.forEach(function (roleValue) {
          const item = document.createElement("div");
          item.className = "sortDropdownItem roleFilterDropdownItem";
          item.dataset.roleValue = roleValue;

          const icon = document.createElement("i");
          icon.className = "material-icons";
          icon.textContent = "manage_accounts";
          item.appendChild(icon);

          const label = document.createElement("span");
          label.className = "sortDropdownItemLabel";
          label.textContent = getRoleFilterLabel(roleValue);
          item.appendChild(label);

          const check = document.createElement("i");
          check.className = "material-icons sortDropdownCheck";
          check.textContent = "check";
          item.appendChild(check);

          if (selectedRoles.has(roleValue)) {
            item.classList.add("selected");
          }

          item.addEventListener("click", function (itemEvent) {
            itemEvent.stopPropagation();

            if (selectedRoles.has(roleValue)) {
              selectedRoles.delete(roleValue);
              item.classList.remove("selected");
            } else {
              selectedRoles.add(roleValue);
              item.classList.add("selected");
            }

            filter.roles = USER_ROLE_FILTER_OPTIONS.filter((candidate) =>
              selectedRoles.has(candidate),
            );
            resetPaginationToFirstPage();
            filterTagsFront();
            adjustPages();
            adjustAllPageFronts();
            updateFilterButtonIndicator();
            fetchData();
          });

          section.appendChild(item);
        });

        panel.appendChild(section);
        hasSections = true;
      }

      if (!hasSections) {
        return;
      }

      const rect = openFilterPanelBtn.getBoundingClientRect();
      panel.style.top = rect.bottom + 4 + "px";
      panel.style.left = rect.left + "px";
      panel.style.minWidth = Math.max(260, rect.width + 160) + "px";
      document.body.appendChild(panel);

      function closeUnifiedPanel(restoreFocus) {
        if (!panel.isConnected) return;
        panel.remove();
        document.removeEventListener("click", closeOnOutsideClick, true);
        document.removeEventListener("keydown", closeOnEscape, true);
        if (restoreFocus) openFilterPanelBtn.focus();
      }

      function closeOnOutsideClick(clickEvent) {
        if (
          panel.contains(clickEvent.target) ||
          openFilterPanelBtn.contains(clickEvent.target)
        ) {
          return;
        }
        closeUnifiedPanel(false);
      }

      function closeOnEscape(keyEvent) {
        if (keyEvent.key !== "Escape") return;
        keyEvent.stopPropagation();
        keyEvent.stopImmediatePropagation();
        closeUnifiedPanel(true);
      }

      document.addEventListener("click", closeOnOutsideClick, true);
      document.addEventListener("keydown", closeOnEscape, true);
    });
  }

  if (pageLocation === "links") {
    pages = Math.ceil(
      parseInt(document.getElementById("linkCount").innerHTML) / filter.limit,
    );
  } else if (pageLocation === "visitors") {
    pages = Math.ceil(
      parseInt(document.getElementById("visitorCount").innerHTML) /
        filter.limit,
    );
  } else if (pageLocation === "groups") {
    pages = Math.ceil(
      parseInt(document.getElementById("groupCount").innerHTML) / filter.limit,
    );
  } else if (pageLocation === "users") {
    pages = Math.ceil(
      parseInt(document.getElementById("userCount").innerHTML) / filter.limit,
    );
  }
  // Function to adjust all containers
  function adjustAllPageFronts() {
    pageContainers.forEach((pageContainer) => {
      adjustPageFront(pageContainer);
    });
  }

  function resetPaginationToFirstPage() {
    currentPage = 1;
    filter.offset = 0;

    document
      .querySelectorAll(".activePage")
      .forEach((active) => active.classList.remove("activePage"));
    document
      .querySelectorAll('[data-page="0"]')
      .forEach((active) => active.classList.add("activePage"));

    adjustAllPageFronts();
  }

  // Function to adjust a single page container
  function adjustPageFront(pageContainer) {
    let pageButtons = pageContainer.querySelectorAll(".page");
    let allPageButtons = pageContainer.querySelectorAll(".pageButton");
    let ids = [];

    if (pages <= 1) {
      allPageButtons.forEach((btn) => (btn.style.display = "none"));
      pageContainer.style.display = "none";
      return;
    }

    pageContainer.style.display = "";
    allPageButtons.forEach((btn) => (btn.style.display = "inline-flex"));

    if (pages < 3) {
      // Display 1..pages for small page ranges
      ids = Array.from({ length: pages }, (_, i) => i + 1);
    } else {
      if (currentPage === 1) {
        // Starting at the first page
        ids = [currentPage, currentPage + 1, currentPage + 2];
      } else if (currentPage === pages) {
        // Ending at the last page
        ids = [currentPage - 2, currentPage - 1, currentPage];
      } else {
        // Middle pages
        ids = [currentPage - 1, currentPage, currentPage + 1];
      }
    }

    // Update button text and data attributes for each page button
    pageButtons.forEach((btn, i) => {
      if (i < ids.length) {
        btn.innerHTML = ids[i];
        btn.setAttribute("data-page", ids[i] - 1); // Sync data-page across all containers
        btn.style.display = "inline-block"; // Show button if needed
      } else {
        btn.style.display = "none"; // Hide extra buttons
      }
    });
  }

  // PARKED FEATURE: keep API no-op while selector is hidden.
  window.adjustSearchType = function () {
    filter.searchType = "all";
    fetchData();
  };

  function normalizeFilterChipValue(value) {
    if (typeof value !== "string") {
      return "";
    }

    if (value.endsWith("-shown")) {
      return value.slice(0, -6);
    }

    return value;
  }

  window.removeGroupFilter = function (group) {
    const normalizedGroup = normalizeFilterChipValue(group);
    const index = filter.groups.indexOf(normalizedGroup);
    if (index > -1) {
      filter.groups.splice(index, 1);
      filter.offset = 0;
      currentPage = 1;
      filterTagsFront();
      adjustPages();
      adjustAllPageFronts();
      fetchData();
    }
  };

  window.removeTagFilter = function (tag) {
    const normalizedTag = normalizeFilterChipValue(tag);
    const index = filter.tags.indexOf(normalizedTag);
    if (index > -1) {
      filter.tags.splice(index, 1);
      filter.offset = 0;
      currentPage = 1;
      filterTagsFront();
      adjustPages();
      adjustAllPageFronts();
      fetchData();
    }
  };

  function normalizeRoleFilterChipValue(value) {
    const normalized = normalizeFilterChipValue(value);
    if (normalized.endsWith("-role")) {
      return normalized.slice(0, -5);
    }
    return normalized;
  }

  window.removeRoleFilter = function (role) {
    const normalizedRole = normalizeRoleFilterChipValue(role);
    const index = filter.roles.indexOf(normalizedRole);
    if (index > -1) {
      filter.roles.splice(index, 1);
      filter.offset = 0;
      currentPage = 1;
      filterTagsFront();
      adjustPages();
      adjustAllPageFronts();
      updateRoleFilterButtonIndicator();
      fetchData();
    }
  };

  function isFilterActive() {
    if (!filter) return false;

    return (
      filter.tags.length > 0 ||
      filter.groups.length > 0 ||
      filter.roles.length > 0 ||
      filter.sort !== "latest_modified" ||
      filter.search !== ""
    );
  }

  function updateSearchInputState() {
    const searchBarEl = document.getElementById("searchBar");
    if (!searchBarEl) {
      return;
    }

    searchBarEl.classList.toggle(
      "searchActive",
      typeof filter.search === "string" && filter.search.trim().length > 0,
    );
  }

  // Update the search placeholder to include the total count, e.g. "Search... (465)"
  function updateSearchPlaceholderCount() {
    var sl = document.getElementById("searchLabel");
    if (!sl) return;
    var countId =
      pageLocation === "links"
        ? "linkCount"
        : pageLocation === "visitors"
          ? "visitorCount"
          : pageLocation === "groups"
            ? "groupCount"
            : pageLocation === "users"
              ? "userCount"
              : null;
    if (!countId) return;
    var countEl = document.getElementById(countId);
    if (!countEl) return;
    var count = countEl.textContent || countEl.innerHTML;
    var baseLabel =
      typeof getUiText === "function"
        ? getUiText("search.placeholder", "Search")
        : "Search";
    sl.textContent = baseLabel + "... (" + count.trim() + ")";
  }

  // Also update the sort dropdown label from stored filter on page load
  function initSortDropdownLabel() {
    updateSortLabelUI();
  }

  function getFilterButtonBaseLabel() {
    return typeof getUiText === "function"
      ? getUiText("search.filter_tags_groups", "Filter by tags and groups")
      : "Filter by tags and groups";
  }

  function getRoleFilterButtonBaseLabel() {
    return typeof getUiText === "function"
      ? getUiText("search.filter_roles", "Filter by role")
      : "Filter by role";
  }

  function updateRoleFilterButtonIndicator() {
    updateFilterButtonIndicator();
  }

  function updateFilterButtonIndicator() {
    // Users page: unified panel button counts tags + groups + roles
    // Other pages: original filter_alt button counts tags + groups only
    const unifiedBtn = document.getElementById("openFilterPanel");
    const tagsGroupsBtn = document.getElementById("filterTagsGroups");

    const tagCount = Array.isArray(filter.tags) ? filter.tags.length : 0;
    const groupCount = Array.isArray(filter.groups) ? filter.groups.length : 0;
    const roleCount = Array.isArray(filter.roles) ? filter.roles.length : 0;

    if (
      unifiedBtn &&
      !unifiedBtn.hidden &&
      unifiedBtn.style.display !== "none"
    ) {
      const activeCount = tagCount + groupCount + roleCount;
      const baseLabel = getUiText("search.open_filter_panel", "Filters");
      unifiedBtn.classList.toggle("inactive", activeCount === 0);
      unifiedBtn.classList.toggle("has-active-filters", activeCount > 0);
      if (activeCount > 0) {
        unifiedBtn.setAttribute(
          "data-active-count",
          String(Math.min(activeCount, 99)),
        );
        unifiedBtn.setAttribute("aria-label", `${baseLabel} (${activeCount})`);
      } else {
        unifiedBtn.removeAttribute("data-active-count");
        unifiedBtn.setAttribute("aria-label", baseLabel);
      }
    } else if (tagsGroupsBtn && tagsGroupsBtn.style.display !== "none") {
      const activeCount = tagCount + groupCount;
      const baseLabel = getFilterButtonBaseLabel();
      tagsGroupsBtn.classList.toggle("inactive", activeCount === 0);
      tagsGroupsBtn.classList.toggle("has-active-filters", activeCount > 0);
      if (activeCount > 0) {
        tagsGroupsBtn.setAttribute(
          "data-active-count",
          String(Math.min(activeCount, 99)),
        );
        tagsGroupsBtn.setAttribute(
          "aria-label",
          `${baseLabel} (${activeCount})`,
        );
      } else {
        tagsGroupsBtn.removeAttribute("data-active-count");
        tagsGroupsBtn.setAttribute("aria-label", baseLabel);
      }
    }
  }

  function updateClearFilterButton() {
    const clearBtn = document.getElementById("clearFilter");
    const resetBtn = document.getElementById("resetFilter");
    if (!clearBtn && !resetBtn) return;

    const hasSearch =
      typeof filter.search === "string" && filter.search.trim().length > 0;
    const hasCollectionFilter =
      filter.tags.length > 0 ||
      filter.groups.length > 0 ||
      filter.roles.length > 0;
    const hasSortChange = filter.sort !== "latest_modified";

    const showClear = hasSearch || hasCollectionFilter;
    const showReset = hasSortChange || showClear;

    function setButtonAndContainerState(buttonEl, shouldShow) {
      if (!buttonEl) return;

      const wrapper = buttonEl.closest(".clearContainer, .resetContainer");

      buttonEl.classList.toggle("inactive", !shouldShow);
      buttonEl.classList.toggle("mobileHide", !shouldShow);
      buttonEl.style.display = shouldShow ? "" : "none";
      buttonEl.setAttribute("aria-hidden", shouldShow ? "false" : "true");

      if (wrapper) {
        wrapper.style.display = shouldShow ? "" : "none";
        wrapper.setAttribute("aria-hidden", shouldShow ? "false" : "true");
      }
    }

    if (clearBtn) {
      setButtonAndContainerState(clearBtn, showClear);
    }

    if (resetBtn) {
      setButtonAndContainerState(resetBtn, showReset);
    }

    updateFilterButtonIndicator();

    // Show/hide inline clear button inside search bar
    var inlineClearBtn = document.getElementById("searchBarClearBtn");
    if (inlineClearBtn) {
      inlineClearBtn.style.display =
        showClear || hasSortChange ? "inline-flex" : "none";
    }
  }

  document
    .getElementById("clearFilter")
    ?.addEventListener("click", function () {
      if (!isFilterActive()) {
        return;
      }

      // Reset filter logic
      filter.tags = [];
      filter.groups = [];
      filter.roles = [];
      filter.sort = "latest_modified";
      filter.offset = 0;
      filter.search = "";
      currentPage = 1;
      const sortIconEl = document.getElementById("sortIcon");
      if (sortIconEl) {
        sortIconEl.innerHTML = "update";
        sortIconEl.classList.add("material-icons");
      }
      updateSortLabelUI();
      const activeEl = document.querySelector(".activePage");
      if (activeEl) activeEl.classList.remove("activePage");
      const sb = document.getElementById("searchbar");
      if (sb) sb.value = "";
      updateSearchInputState();
      adjustPages();
      adjustAllPageFronts();
      updateClearFilterButton(); // Update the Clear Filter button state after reset
      fetchData();
      clearFilteredTags();
    });

  document
    .getElementById("resetFilter")
    ?.addEventListener("click", function () {
      if (!isFilterActive()) {
        return;
      }

      filter.tags = [];
      filter.groups = [];
      filter.roles = [];
      filter.sort = "latest_modified";
      filter.offset = 0;
      filter.search = "";
      filter.searchType = "all";
      currentPage = 1;

      const sortIconEl = document.getElementById("sortIcon");
      if (sortIconEl) {
        sortIconEl.innerHTML = "update";
        sortIconEl.classList.add("material-icons");
      }
      updateSortLabelUI();

      const activeEl = document.querySelector(".activePage");
      if (activeEl) activeEl.classList.remove("activePage");

      const sb = document.getElementById("searchbar");
      if (sb) sb.value = "";

      clearFilteredTags();
      updateSearchInputState();
      adjustPages();
      adjustAllPageFronts();
      updateClearFilterButton();
      fetchData();
    });

  // Single click: open sort dropdown/modal (was previously on dblclick)
  const sortLinksBtn = document.getElementById("sortLinks");
  if (sortLinksBtn) {
    sortLinksBtn.addEventListener("click", function () {
      if (
        document.getElementById("sortModal") ||
        document.getElementById("modalContainer")
      ) {
        return;
      }

      // Close any open dropdowns
      var existingSort = document.getElementById("sortDropdownPanel");
      if (existingSort) existingSort.remove();
      var existingPageSize = document.getElementById("pageSizeDropdownPanel");
      if (existingPageSize) existingPageSize.remove();

      const sortTrigger =
        document.activeElement instanceof HTMLElement
          ? document.activeElement
          : null;

      const restoreSortFocus = function () {
        if (sortTrigger && document.body.contains(sortTrigger)) {
          requestAnimationFrame(function () {
            try {
              sortTrigger.focus({ preventScroll: true });
            } catch (e) {}
          });
        }
      };

      fetch("/getSortModal", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ filter: filter, comp: pageLocation }),
      })
        .then((response) => response.text())
        .then((data) => {
          var parser = new DOMParser();
          var doc = parser.parseFromString(data, "text/html");
          var element = doc.body.firstChild;
          document.body.appendChild(element);
          setSelectionOverlayActive(true);
          // mark current sort as selected in modal
          const sel = document.getElementById(filter.sort);
          if (sel) sel.classList.add("selected");

          document
            .querySelectorAll(".closeSortModal")
            .forEach(function (close) {
              close.addEventListener("click", function () {
                document.getElementById("sortModal").remove();
                setSelectionOverlayActive(false);
                restoreSortFocus();
              });
            });

          document.querySelectorAll(".sortOption").forEach(function (option) {
            option.addEventListener("click", function () {
              filter.sort = normalizeSort(option.id, pageLocation, filter.sort);
              resetPaginationToFirstPage();
              const appliedSortOption =
                document.getElementById(filter.sort) || option;
              let icon;
              if (
                appliedSortOption.firstElementChild.classList.contains(
                  "day-icons",
                )
              ) {
                icon = `&#x${appliedSortOption.firstElementChild.id};`;
                document
                  .getElementById("sortIcon")
                  .classList.remove("material-icons");
              } else {
                icon = appliedSortOption.firstElementChild.innerHTML;
                document
                  .getElementById("sortIcon")
                  .classList.add("material-icons");
              }
              document.getElementById("sortModal").remove();
              setSelectionOverlayActive(false);
              restoreSortFocus();
              document.getElementById("sortIcon").innerHTML = icon;
              updateSortLabelUI();
              fetchData();
            });
          });
        })
        .catch((err) => {
          return;
        });
    });

    // Double click: quick toggle sort order (was previously on click)
    sortLinksBtn.addEventListener("dblclick", function () {
      const activeSortIcon = icons[filter.sort] || "update";
      if (activeSortIcon.startsWith("&#x")) {
        document.getElementById("sortIcon").classList.remove("material-icons");
      } else {
        document.getElementById("sortIcon").classList.add("material-icons");
      }
      if (reverseOrders[filter.sort] !== undefined) {
        filter.sort = reverseOrders[filter.sort];
        document.getElementById("sortIcon").innerHTML = icons[filter.sort];
      } else {
        filter.sort = "latest_modified";
        document.getElementById("sortIcon").innerHTML = "update";
      }
      resetPaginationToFirstPage();
      updateSortLabelUI();
      fetchData();
    });
  }

  // --- Sort dropdown (desktop) ---
  var sortDropdownBtn = document.getElementById("sortDropdownBtn");
  var _sortDropdownFetching = false;
  if (sortDropdownBtn) {
    sortDropdownBtn.addEventListener("click", function (e) {
      e.stopPropagation();

      // Close page-size dropdown if open
      var existingPageSize = document.getElementById("pageSizeDropdownPanel");
      if (existingPageSize) existingPageSize.remove();

      // If dropdown already open, close it
      var existing = document.getElementById("sortDropdownPanel");
      if (existing) {
        existing.remove();
        return;
      }

      // Prevent double-fetch
      if (_sortDropdownFetching) return;

      // If a modal is already open, don't open dropdown
      if (
        document.getElementById("sortModal") ||
        document.getElementById("modalContainer")
      ) {
        return;
      }

      _sortDropdownFetching = true;

      fetch("/getSortModal", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ filter: filter, comp: pageLocation }),
      })
        .then(function (response) {
          return response.text();
        })
        .then(function (data) {
          _sortDropdownFetching = false;

          // If panel was opened by another click while we were fetching, bail
          if (document.getElementById("sortDropdownPanel")) return;

          var parser = new DOMParser();
          var doc = parser.parseFromString(data, "text/html");
          var sortOptions = doc.querySelectorAll(".sortOption");

          // Build dropdown panel
          var panel = document.createElement("div");
          panel.id = "sortDropdownPanel";
          panel.className = "sortDropdownPanel";

          sortOptions.forEach(function (option) {
            var sortId = option.id;

            var item = document.createElement("div");
            item.className = "sortDropdownItem";
            item.id = "sdp-" + sortId;
            item.dataset.sortId = sortId;

            // Copy icon from option
            var icon = option.querySelector("i");
            if (icon) {
              var iconClone = icon.cloneNode(true);
              iconClone.removeAttribute("id");
              item.appendChild(iconClone);
            }

            // Copy label from option
            var labelEl = option.querySelector("p");
            var label = document.createElement("span");
            label.className = "sortDropdownItemLabel";
            label.textContent = labelEl
              ? labelEl.textContent
              : option.dataset.sortLabel || sortId;
            item.appendChild(label);

            // Check mark
            var check = document.createElement("i");
            check.className = "material-icons sortDropdownCheck";
            check.textContent = "check";
            item.appendChild(check);

            // Mark selected
            if (sortId === filter.sort) {
              item.classList.add("selected");
            }

            item.addEventListener("click", function () {
              filter.sort = normalizeSort(sortId, pageLocation, filter.sort);
              resetPaginationToFirstPage();
              var sortIconEl = document.getElementById("sortIcon");
              if (sortIconEl) {
                var origIcon = option.querySelector("i");
                if (origIcon && origIcon.classList.contains("day-icons")) {
                  sortIconEl.classList.remove("material-icons");
                  sortIconEl.innerHTML = "&#x" + origIcon.id + ";";
                } else if (origIcon) {
                  sortIconEl.classList.add("material-icons");
                  sortIconEl.innerHTML = origIcon.innerHTML;
                }
              }
              panel.remove();
              updateSortLabelUI();
              fetchData();
            });

            panel.appendChild(item);
          });

          // Position relative to button
          var rect = sortDropdownBtn.getBoundingClientRect();
          panel.style.top = rect.bottom + 4 + "px";
          panel.style.left = rect.left + "px";
          panel.style.minWidth = rect.width + "px";
          document.body.appendChild(panel);

          // Close on click outside
          function closeDropdown(ev) {
            if (!panel.contains(ev.target) && ev.target !== sortDropdownBtn) {
              panel.remove();
              document.removeEventListener("click", closeDropdown, true);
              document.removeEventListener("keydown", escClose, true);
            }
          }
          function escClose(ev) {
            if (ev.key === "Escape") {
              ev.stopPropagation();
              ev.stopImmediatePropagation();
              panel.remove();
              document.removeEventListener("click", closeDropdown, true);
              document.removeEventListener("keydown", escClose, true);
              sortDropdownBtn.focus();
            }
          }
          document.addEventListener("click", closeDropdown, true);
          document.addEventListener("keydown", escClose, true);
        })
        .catch(function () {
          _sortDropdownFetching = false;
        });
    });
  }

  let searchbar = document.getElementById("searchbar");

  if (searchbar) {
    searchbar.addEventListener("input", function () {
      if (searchbar.value.length > 0) {
        document.querySelector(".searchLabel")?.classList.add("focusedLabel");
      } else {
        document
          .querySelector(".searchLabel")
          ?.classList.remove("focusedLabel");
      }

      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        let value = searchbar.value.toLowerCase();

        filter.offset = 0;
        currentPage = 1;
        if (document.querySelector(".activePage")) {
          document.querySelector(".activePage").classList.remove("activePage");
        }

        filter.search = value;
        updateSearchInputState();

        fetchData();
      }, 300); // sensible debounce for mobile
    });

    updateSearchInputState();
  }

  document.body.addEventListener("click", function (event) {
    let target = event.target;

    // Traverse up the DOM tree to find the parent div with the desired class
    while (
      target &&
      !target.classList.contains("filterByTag") &&
      !target.classList.contains("filterByGroup")
    ) {
      target = target.parentElement;
    }

    // If no valid target is found, exit the function
    if (!target) return;

    if (target.classList.contains("filterByTag")) {
      const tag = target;
      let tagID = tag.id.split("-")[0];
      if (filter.tags.includes(tagID)) {
        filter.tags.splice(filter.tags.indexOf(tagID), 1);
      } else {
        filter.tags.push(tagID);
      }
      filter.offset = 0;
      currentPage = 1;
      filterTagsFront();
      adjustAllPageFronts();
      adjustPages();
      fetchData();
    }

    if (target.classList.contains("filterByGroup")) {
      const group = target;
      let groupID = group.id.split("-")[0];
      if (filter.groups.includes(groupID)) {
        filter.groups.splice(filter.groups.indexOf(groupID), 1);
      } else {
        filter.groups.push(groupID);
      }
      filter.offset = 0;
      currentPage = 1;
      filterTagsFront();
      adjustAllPageFronts();
      adjustPages();
      fetchData();
    }
  });

  function fetchData() {
    if (typeof window.handleSelectionFilterMutation === "function") {
      window.handleSelectionFilterMutation(getFilterSnapshotForMultiSelect());
    }

    /* Infinite scroll: always start from page 1 on a fresh fetchData call */
    if (_infiniteScrollActive) {
      filter.offset = 0;
      currentPage = 1;
    }

    if (currentAbortController) {
      currentAbortController.abort();
    }

    currentAbortController = new AbortController();
    const { signal } = currentAbortController;
    const requestId = ++activeFetchRequestId;

    fetch("/filter?comp=" + pageLocation, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ filter: filter }),
      signal: signal, // Attach the abort signal
    })
      .then((response) => response.text())
      .then((data) => {
        if (requestId !== activeFetchRequestId) {
          return;
        }

        updateFilterButtonIndicator();

        if (filter.sort !== "latest_modified") {
          document.getElementById("sortLinks").classList.remove("inactive");
        } else {
          document.getElementById("sortLinks").classList.add("inactive");
        }

        if (pageLocation === "links") {
          try {
            const shouldResetSelection =
              typeof window.isSelectionModeActive === "function"
                ? !window.isSelectionModeActive()
                : !document.body.classList.contains("selection-mode");

            if (shouldResetSelection) {
              multiSelect("reset-filter");
            }

            let links = JSON.parse(data);
            const totalLinks = Number.parseInt(links.total, 10);
            if (!Number.isFinite(totalLinks) || !Array.isArray(links.links)) {
              return;
            }
            document.getElementById("linkCount").innerHTML = totalLinks;
            const mobileTotalEl = document.getElementById("mobileTotalCount");
            if (mobileTotalEl) mobileTotalEl.innerHTML = totalLinks;
            updateSearchPlaceholderCount();
            adjustPages();
            adjustAllPageFronts();
            links = links.links;

            /* Infinite scroll: track totals for append-mode loading */
            if (_infiniteScrollActive) {
              _resetInfiniteScroll();
              _infiniteScrollTotal = totalLinks;
              _infiniteScrollLoaded = links.length;
            }

            let container = document.getElementById("linksContainer");
            container.innerHTML = ""; // Clear the container
            if (links.length > 0) {
              showSkeletons(container, Math.min(links.length, 5));
            }
            document.getElementById("dataCount").innerHTML = links.length;
            const mobileDataEl = document.getElementById("mobileDataCount");
            if (mobileDataEl) mobileDataEl.innerHTML = links.length;
            // Update the select display label to indicate how many items are being shown
            if (window.__updateShownLabel)
              window.__updateShownLabel(links.length);

            if (links.length === 0 && filter.sort === "favorite") {
              renderFavoriteEmptyState(container);
            }

            // Load links in chunks
            const linkChunkSize =
              filter.limit >= 80 ? 12 : filter.limit >= 40 ? 8 : 5;
            loadLinksInChunks(links, linkChunkSize, signal);

            /* Infinite scroll: show end indicator if first batch has all items */
            if (
              _infiniteScrollActive &&
              _infiniteScrollLoaded >= _infiniteScrollTotal
            ) {
              _showInfiniteScrollEnd();
            }
          } catch (err) {
            return;
          }
        } else if (pageLocation === "visitors") {
          try {
            let visitors = JSON.parse(data);
            const totalVisitors = Number.parseInt(visitors.total, 10);
            if (
              !Number.isFinite(totalVisitors) ||
              !Array.isArray(visitors.visitors)
            ) {
              return;
            }
            document.getElementById("visitorCount").innerHTML = totalVisitors;
            const mobileTotalElVisitors =
              document.getElementById("mobileTotalCount");
            if (mobileTotalElVisitors)
              mobileTotalElVisitors.innerHTML = totalVisitors;
            updateSearchPlaceholderCount();
            adjustPages();
            adjustAllPageFronts();
            visitors = visitors.visitors;
            let container = document.getElementById("visitorsContainer");
            container.innerHTML = ""; // Clear the container
            document.getElementById("dataCount").innerHTML = visitors.length;
            const mobileDataElVisitors =
              document.getElementById("mobileDataCount");
            if (mobileDataElVisitors)
              mobileDataElVisitors.innerHTML = visitors.length;
            if (window.__updateShownLabel)
              window.__updateShownLabel(visitors.length);

            const visitorChunkSize =
              filter.limit >= 80 ? 12 : filter.limit >= 40 ? 8 : 5;
            loadVisitorsInChunks(visitors, visitorChunkSize, signal);
          } catch (err) {
            return;
          }
        } else if (pageLocation === "groups") {
          try {
            let groups = JSON.parse(data);
            const totalGroups = Number.parseInt(groups.total, 10);
            if (
              !Number.isFinite(totalGroups) ||
              !Array.isArray(groups.groups)
            ) {
              return;
            }
            document.getElementById("groupCount").innerHTML = totalGroups;
            const mobileTotalElGroups =
              document.getElementById("mobileTotalCount");
            if (mobileTotalElGroups)
              mobileTotalElGroups.innerHTML = totalGroups;
            updateSearchPlaceholderCount();
            adjustPages();
            adjustAllPageFronts();
            groups = groups.groups;
            let container = document.getElementById("groupsContainer");
            container.innerHTML = ""; // Clear the container
            document.getElementById("dataCount").innerHTML = groups.length;
            const mobileDataElGroups =
              document.getElementById("mobileDataCount");
            if (mobileDataElGroups)
              mobileDataElGroups.innerHTML = groups.length;
            if (window.__updateShownLabel)
              window.__updateShownLabel(groups.length);

            loadGroups(groups, container, signal);
          } catch (err) {
            return;
          }
        } else if (pageLocation === "users") {
          try {
            let usersPayload = JSON.parse(data);
            const totalUsers = Number.parseInt(usersPayload.total, 10);
            if (
              !Number.isFinite(totalUsers) ||
              !Array.isArray(usersPayload.users)
            ) {
              return;
            }

            const userCountEl = document.getElementById("userCount");
            if (userCountEl) {
              userCountEl.innerHTML = totalUsers;
            }

            const mobileTotalElUsers =
              document.getElementById("mobileTotalCount");
            if (mobileTotalElUsers) {
              mobileTotalElUsers.innerHTML = totalUsers;
            }

            updateSearchPlaceholderCount();
            adjustPages();
            adjustAllPageFronts();

            const users = usersPayload.users;
            const container = document.getElementById("usersContainer");
            if (!container) {
              return;
            }
            container.innerHTML = "";

            const dataCountEl = document.getElementById("dataCount");
            if (dataCountEl) {
              dataCountEl.innerHTML = users.length;
            }
            const mobileDataElUsers =
              document.getElementById("mobileDataCount");
            if (mobileDataElUsers) {
              mobileDataElUsers.innerHTML = users.length;
            }
            if (window.__updateShownLabel) {
              window.__updateShownLabel(users.length);
            }

            const userChunkSize =
              filter.limit >= 80 ? 12 : filter.limit >= 40 ? 8 : 5;
            loadUsersInChunks(users, userChunkSize, signal);
          } catch (err) {
            return;
          }
        }

        clearFilterQueryFromURL();

        updateSortLabelUI();

        localStorage.setItem("filter_" + pageLocation, JSON.stringify(filter));
        updateClearFilterButton();
      })
      .catch((err) => {
        return;
      });
  }

  window.fetchData = fetchData;

  function createSkeletonCard() {
    const outer = document.createElement("div");
    outer.className = "outerLinkContainer skeleton-card";
    outer.innerHTML = `
      <div class="linkContainer">
        <div class="sk-line sk-title"></div>
        <div class="sk-line sk-url"></div>
        <div class="sk-tags">
          <div class="sk-tag"></div>
          <div class="sk-tag"></div>
        </div>
      </div>`;
    return outer;
  }

  function showSkeletons(container, count) {
    for (let i = 0; i < count; i++) {
      container.appendChild(createSkeletonCard());
    }
  }

  function renderFavoriteEmptyState(container) {
    if (!container) {
      return;
    }

    const emptyState = document.createElement("div");
    emptyState.className = "favoriteEmptyState";
    emptyState.textContent =
      typeof getUiText === "function"
        ? getUiText("js.filter.no_favorite_links_yet", "No favorite links yet.")
        : "No favorite links yet.";
    container.appendChild(emptyState);
  }

  function reapplyLayoutStateAfterRender() {
    if (typeof window.applyCurrentLayoutState === "function") {
      window.applyCurrentLayoutState();
      return;
    }

    if (typeof window.syncListModeControls === "function") {
      window.syncListModeControls();
    }
  }

  async function loadLinksInChunks(links, chunkSize, signal) {
    window.forceRefreshGroupsPage = function () {
      if (pageLocation !== "groups") {
        return;
      }

      const groupCountEl = document.getElementById("groupCount");
      const currentTotal = Number.parseInt(
        groupCountEl?.textContent || "0",
        10,
      );
      const nextTotal = Number.isFinite(currentTotal)
        ? Math.max(0, currentTotal - 1)
        : 0;

      if (groupCountEl) {
        groupCountEl.textContent = String(nextTotal);
      }

      const mobileTotalEl = document.getElementById("mobileTotalCount");
      if (mobileTotalEl) {
        mobileTotalEl.textContent = String(nextTotal);
      }

      if (nextTotal === 0) {
        currentPage = 0;
        filter.offset = 0;
      } else {
        const maxPage = Math.max(1, Math.ceil(nextTotal / (filter.limit || 1)));
        currentPage = Math.min(Math.max(currentPage, 1), maxPage);
        filter.offset = (currentPage - 1) * filter.limit;
      }

      adjustPages();
      adjustAllPageFronts();
      fetchData();
    };

    const container = document.getElementById("linksContainer");

    try {
      const elements = await fetchContainerBatch(
        "link",
        links,
        "/linkContainerBatch",
        linkContainerHtmlCache,
        signal,
      );

      container.querySelectorAll(".skeleton-card").forEach((el) => el.remove());

      const fragment = document.createDocumentFragment();
      for (const el of elements) {
        if (el) fragment.appendChild(el);
      }
      container.appendChild(fragment);
    } catch (err) {
      if (err.name === "AbortError") return;
    }

    checkTitleLength(container || document);
    if (typeof window.collapseInlineTags === "function") {
      window.collapseInlineTags(2, container || document);
    }
    reapplyLayoutStateAfterRender();

    if (typeof window.__syncMultiSelectUI === "function") {
      window.__syncMultiSelectUI();
    }
  }

  updateClearFilterButton();
  window.addEventListener("resize", _debounce(updateClearFilterButton, 200));

  async function loadVisitorsInChunks(visitors, chunkSize, signal) {
    const container = document.getElementById("visitorsContainer");

    try {
      const elements = await fetchContainerBatch(
        "visitor",
        visitors,
        "/visitorContainerBatch",
        visitorContainerHtmlCache,
        signal,
      );

      const fragment = document.createDocumentFragment();
      for (const el of elements) {
        if (el) fragment.appendChild(el);
      }
      container.appendChild(fragment);
    } catch (err) {
      if (err.name === "AbortError") return;
    }

    reapplyLayoutStateAfterRender();

    if (typeof window.__syncMultiSelectUI === "function") {
      window.__syncMultiSelectUI();
    }
  }

  async function loadUsersInChunks(users, chunkSize, signal) {
    const container = document.getElementById("usersContainer");

    try {
      const elements = await fetchContainerBatch(
        "user",
        users,
        "/userContainerBatch",
        userContainerHtmlCache,
        signal,
      );

      const fragment = document.createDocumentFragment();
      for (const el of elements) {
        if (el) fragment.appendChild(el);
      }
      container.appendChild(fragment);
    } catch (err) {
      if (err.name === "AbortError") return;
    }

    reapplyLayoutStateAfterRender();

    if (typeof window.__syncMultiSelectUI === "function") {
      window.__syncMultiSelectUI();
    }
  }

  async function loadGroups(groups, container, signal) {
    container.innerHTML = "";

    try {
      const elements = await fetchContainerBatch(
        "group",
        groups,
        "/groupContainerBatch",
        groupContainerHtmlCache,
        signal,
      );

      const fragment = document.createDocumentFragment();
      for (const el of elements) {
        if (el) fragment.appendChild(el);
      }
      container.appendChild(fragment);
    } catch (err) {
      if (err.name === "AbortError") return;
    }

    reapplyLayoutStateAfterRender();

    if (typeof window.__syncMultiSelectUI === "function") {
      window.__syncMultiSelectUI();
    }
  }

  /**
   * Batch-fetch container HTML for multiple items in a single HTTP request.
   * Falls back to individual fetches if the batch endpoint fails.
   */
  async function fetchContainerBatch(type, items, batchUrl, cache, signal) {
    const results = new Array(items.length);
    const uncachedIndices = [];
    const uncachedItems = [];

    // Check cache first
    for (let i = 0; i < items.length; i++) {
      const cacheKey = createContainerCacheKey(type, items[i]);
      const cached = getCachedElement(cache, cacheKey);
      if (cached) {
        results[i] = cached;
      } else {
        uncachedIndices.push(i);
        uncachedItems.push(items[i]);
      }
    }

    if (uncachedItems.length === 0) return results;

    // Build batch payload key name (links, visitors, or groups)
    const payloadKey = type === "visitor" ? "visitors" : type + "s";

    const response = await fetch(batchUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ [payloadKey]: uncachedItems }),
      signal: signal,
    });

    if (!response.ok) {
      throw new Error(`Batch HTTP ${response.status}`);
    }

    const data = await response.json();
    if (data.html && Array.isArray(data.html)) {
      for (let j = 0; j < data.html.length; j++) {
        const html = data.html[j];
        const element = htmlToElement(html);
        results[uncachedIndices[j]] = element;
        const key = createContainerCacheKey(type, uncachedItems[j]);
        setCachedHtml(cache, key, html);
      }
    }

    return results;
  }

  // Keep individual fetchers as fallback for single-item renders
  async function fetchVisitorContainer(visitor, signal) {
    const cacheKey = createContainerCacheKey("visitor", visitor);
    const cachedElement = getCachedElement(visitorContainerHtmlCache, cacheKey);
    if (cachedElement) {
      return cachedElement;
    }

    try {
      let response = await fetch("/visitorContainer", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ visitor: visitor }),
        signal: signal,
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      let data = await response.text();
      const element = htmlToElement(data);
      if (!element) {
        return null;
      }

      setCachedHtml(visitorContainerHtmlCache, cacheKey, data);
      return element;
    } catch (err) {
      return null;
    }
  }

  async function fetchGroupContainer(group, signal) {
    const cacheKey = createContainerCacheKey("group", group);
    const cachedElement = getCachedElement(groupContainerHtmlCache, cacheKey);
    if (cachedElement) {
      return cachedElement;
    }

    try {
      let response = await fetch("/groupContainer", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ group: group }),
        signal: signal,
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      let data = await response.text();
      const element = htmlToElement(data);
      if (!element) {
        return null;
      }

      setCachedHtml(groupContainerHtmlCache, cacheKey, data);
      return element;
    } catch (err) {
      return null;
    }
  }

  async function fetchLinkContainer(link, signal) {
    const cacheKey = createContainerCacheKey("link", link);
    const cachedElement = getCachedElement(linkContainerHtmlCache, cacheKey);
    if (cachedElement) {
      return cachedElement;
    }

    try {
      let response = await fetch("/linkContainer", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ link: link }),
        signal: signal,
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      let data = await response.text();
      const element = htmlToElement(data);
      if (!element) {
        return null;
      }

      setCachedHtml(linkContainerHtmlCache, cacheKey, data);
      return element;
    } catch (err) {
      return null;
    }
  }

  /* ═══════════════════════════════════════════════════════════════════
     Infinite scroll — mobile links page only
     ═══════════════════════════════════════════════════════════════════ */

  function _isInfiniteScrollEligible() {
    return pageLocation === "links" && _infiniteScrollMQ.matches;
  }

  function _resetInfiniteScroll() {
    _infiniteScrollLoaded = 0;
    _infiniteScrollTotal = 0;
    _infiniteScrollFetching = false;
    const spinner = document.getElementById("infiniteScrollSpinner");
    if (spinner) spinner.classList.remove("loading");
    const sentinel = document.getElementById("infiniteScrollSentinel");
    if (sentinel) {
      sentinel
        .querySelectorAll(".infiniteScrollEnd")
        .forEach((el) => el.remove());
    }
  }

  function _activateInfiniteScroll() {
    if (_infiniteScrollActive) return;
    _infiniteScrollActive = true;
    document.body.classList.add("infinite-scroll-active");

    const sentinel = document.getElementById("infiniteScrollSentinel");
    if (!sentinel) return;
    sentinel.style.display = "";

    _infiniteScrollObserver = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (
            entry.isIntersecting &&
            !_infiniteScrollFetching &&
            _infiniteScrollLoaded < _infiniteScrollTotal
          ) {
            _fetchNextInfiniteScrollPage();
          }
        }
      },
      { rootMargin: "200px" },
    );
    _infiniteScrollObserver.observe(sentinel);
  }

  function _deactivateInfiniteScroll() {
    if (!_infiniteScrollActive) return;
    _infiniteScrollActive = false;
    document.body.classList.remove("infinite-scroll-active");

    if (_infiniteScrollObserver) {
      _infiniteScrollObserver.disconnect();
      _infiniteScrollObserver = null;
    }
    const sentinel = document.getElementById("infiniteScrollSentinel");
    if (sentinel) sentinel.style.display = "none";
    _resetInfiniteScroll();
  }

  async function _fetchNextInfiniteScrollPage() {
    if (_infiniteScrollFetching) return;
    if (_infiniteScrollLoaded >= _infiniteScrollTotal) return;

    _infiniteScrollFetching = true;
    const spinner = document.getElementById("infiniteScrollSpinner");
    if (spinner) spinner.classList.add("loading");

    filter.offset = _infiniteScrollLoaded;
    const nextAbort = new AbortController();

    try {
      const response = await fetch("/filter?comp=links", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ filter: filter }),
        signal: nextAbort.signal,
      });

      const data = await response.text();
      const parsed = JSON.parse(data);
      if (!Array.isArray(parsed.links)) return;

      const links = parsed.links;
      if (links.length === 0) {
        _infiniteScrollLoaded = _infiniteScrollTotal;
        _showInfiniteScrollEnd();
        return;
      }

      const container = document.getElementById("linksContainer");
      const elements = await fetchContainerBatch(
        "link",
        links,
        "/linkContainerBatch",
        linkContainerHtmlCache,
        nextAbort.signal,
      );

      const fragment = document.createDocumentFragment();
      for (const el of elements) {
        if (el) fragment.appendChild(el);
      }
      container.appendChild(fragment);

      _infiniteScrollLoaded += links.length;

      // Update data count display
      const dataCountEl = document.getElementById("dataCount");
      if (dataCountEl) dataCountEl.innerHTML = _infiniteScrollLoaded;
      const mobileDataEl = document.getElementById("mobileDataCount");
      if (mobileDataEl) mobileDataEl.innerHTML = _infiniteScrollLoaded;

      checkTitleLength(container);
      if (typeof window.collapseInlineTags === "function") {
        window.collapseInlineTags(2, container);
      }
      reapplyLayoutStateAfterRender();
      if (typeof window.__syncMultiSelectUI === "function") {
        window.__syncMultiSelectUI();
      }

      if (_infiniteScrollLoaded >= _infiniteScrollTotal) {
        _showInfiniteScrollEnd();
      }
    } catch (err) {
      if (err.name === "AbortError") return;
    } finally {
      _infiniteScrollFetching = false;
      if (spinner) spinner.classList.remove("loading");
    }
  }

  function _showInfiniteScrollEnd() {
    const sentinel = document.getElementById("infiniteScrollSentinel");
    if (!sentinel) return;
    const spinner = document.getElementById("infiniteScrollSpinner");
    if (spinner) spinner.classList.remove("loading");
    if (sentinel.querySelector(".infiniteScrollEnd")) return;
    const endMsg = document.createElement("div");
    endMsg.className = "infiniteScrollEnd";
    endMsg.textContent =
      typeof getUiText === "function"
        ? getUiText("js.filter.all_links_loaded", "All links loaded")
        : "All links loaded";
    sentinel.appendChild(endMsg);
  }

  /* Track infinite-scroll loaded count after initial fetchData load */
  window._onLinksChunkLoaded = function (count) {
    if (_infiniteScrollActive) {
      _infiniteScrollLoaded = count;
    }
  };

  /* React to viewport changes: activate/deactivate on resize */
  _infiniteScrollMQ.addEventListener("change", () => {
    if (_isInfiniteScrollEligible()) {
      _resetInfiniteScroll();
      _activateInfiniteScroll();
      filter.offset = 0;
      currentPage = 1;
      fetchData();
    } else {
      _deactivateInfiniteScroll();
      adjustPages();
      adjustAllPageFronts();
    }
  });

  /* Initial activation */
  if (_isInfiniteScrollEligible()) {
    _activateInfiniteScroll();
  }
});
