document.addEventListener("DOMContentLoaded", function () {
  // Use event delegation — single click handler on body
  document.body.addEventListener("click", function (event) {
    const link = event.target.closest(".editLink");
    if (!link) return;

    const url = link.href || link.getAttribute("href");
    if (!url || url === "#") return;
    event.preventDefault();

    fetch(url)
      .then((response) => response.text())
      .then((data) => {
        let modalContainer = document.getElementById("modalContainer");
        modalContainer.innerHTML = data;
        modalContainer.style.display = "block";

        document.querySelectorAll(".closeEditModal").forEach(function (close) {
          close.addEventListener("click", function () {
            document.getElementById("modalContainer").innerHTML = "";
          });
        });

        let editTagsArray = [];
        let editGroupsArray = [];

        let editTagsInput = document.getElementById("hiddenEditTags");
        let editGroupsInput = document.getElementById("hiddenEditGroups");

        let editTagCon = document.querySelectorAll(".editTagContainer");
        let editGroupCon = document.querySelectorAll(".editGroupContainer");

        if (document.querySelectorAll(".presetTag")) {
          document.querySelectorAll(".presetTag").forEach(function (tag) {
            editTagsArray.push(tag.innerHTML);
          });
          editTagsInput.value = JSON.stringify(editTagsArray);
          removeTag();
        }

        if (document.querySelectorAll(".presetGroup")) {
          document.querySelectorAll(".presetGroup").forEach(function (group) {
            editGroupsArray.push(group.innerHTML);
          });
          editGroupsInput.value = JSON.stringify(editGroupsArray);
          removeGroup();
        }

        let editTags = document.getElementById("editLinkTags");
        let editTagsContainer = document.getElementById("editTagsContainer");

        editTags.addEventListener("focusout", function () {
          if (editTags.value.length > 0) {
            editTagsArray.push(editTags.value);
            let div = document.createElement("div");
            div.classList.add("editTagContainer");
            let p = document.createElement("p");
            p.innerHTML = editTags.value;
            div.appendChild(p);
            editTagsContainer.insertBefore(div, editTagsContainer.firstChild);
            editTags.value = "";
            editTagCon = document.querySelectorAll(".editTagContainer");

            if (editTags.value.length > 0) {
              editTagsArray.push(editTags.value);
            }
            editTagsInput.value = JSON.stringify(editTagsArray);
            // remove tag when clicked
            removeTag();
          }
        });

        function removeTag() {
          editTagCon.forEach(function (tag) {
            tag.addEventListener("click", function () {
              let tagName = tag.querySelector("p").innerHTML;
              let index = editTagsArray.indexOf(tagName);
              editTagsArray.splice(index, 1);
              tag.remove();
              editTagsInput.value = JSON.stringify(editTagsArray);
            });
          });
        }

        let editGroups = document.getElementById("editLinkGroups");
        let editGroupsContainer = document.getElementById(
          "editGroupsContainer",
        );

        editGroups.addEventListener("focusout", function () {
          if (editGroups.value.length > 0) {
            editGroupsArray.push(editGroups.value);
            let div = document.createElement("div");
            div.classList.add("editGroupContainer");
            let p = document.createElement("p");
            p.innerHTML = editGroups.value;
            div.appendChild(p);
            editGroupsContainer.insertBefore(
              div,
              editGroupsContainer.firstChild,
            );
            editGroups.value = "";
            editGroupCon = document.querySelectorAll(".editGroupContainer");

            if (editGroups.value.length > 0) {
              editGroupsArray.push(editGroups.value);
            }
            editGroupsInput.value = JSON.stringify(editGroupsArray);
            // remove group when clicked
            removeGroup();
          }
        });

        function removeGroup() {
          editGroupCon.forEach(function (group) {
            group.addEventListener("click", function () {
              let groupName = group.querySelector("p").innerHTML;
              let index = editGroupsArray.indexOf(groupName);
              editGroupsArray.splice(index, 1);
              group.remove();
              editGroupsInput.value = JSON.stringify(editGroupsArray);
            });
          });
        }
      })
      .catch((error) => {
        console.error("Failed to load edit modal:", error);
      });
  });
});
