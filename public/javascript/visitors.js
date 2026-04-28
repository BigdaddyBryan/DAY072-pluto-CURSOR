document.addEventListener("DOMContentLoaded", function () {
  // Your existing JavaScript code here

  function addEventListeners() {
    // Use event delegation for .linkSwitch elements
    document.body.addEventListener("click", function (event) {
      if (event.target.closest(".visitorSwitch")) {
        let openSwitch = event.target.closest(".visitorSwitch");
        if (
          event.target.tagName === "BUTTON" ||
          event.target.tagName === "A" ||
          event.target.tagName === "I" ||
          event.target.classList.contains("tagContainer") ||
          event.target.parentElement?.classList.contains("tagContainer") ||
          event.target.closest(".popup-modal") ||
          event.target.closest(".visitorForm")
        ) {
          return;
        }
        openSwitch.classList.toggle("open");
      }
    });
  }
  addEventListeners();

  function adjustCreateLinkPosition() {
    const newLinkContainer = document.querySelector(".newLinkContainer");
    if (!newLinkContainer) return;

    const bottomPage = document.querySelector(".shownContainer.bottomPage");
    const hasPagination =
      bottomPage !== null &&
      bottomPage.offsetParent !== null &&
      bottomPage.querySelector(".pageButton") !== null;

    newLinkContainer.classList.toggle("with-pagination", hasPagination);
  }

  adjustCreateLinkPosition();
  window.addEventListener("resize", _debounce(adjustCreateLinkPosition, 200));
});

function copyVisitor(link, event) {
  event.preventDefault();
  navigator.clipboard.writeText(link).then(
    function () {
      const message =
        typeof formatUiText === "function"
          ? formatUiText(
              typeof getUiText === "function"
                ? getUiText(
                    "js.visitors.ip_copied",
                    "IP copied to clipboard: {ip}",
                  )
                : "IP copied to clipboard: {ip}",
              { ip: link },
            )
          : `IP copied to clipboard: ${link}`;
      createSnackbar(message);
    },
    function () {
      createSnackbar(
        typeof getUiText === "function"
          ? getUiText("js.visitors.could_not_copy_ip", "Could not copy IP")
          : "Could not copy IP",
      );
    },
  );
}
