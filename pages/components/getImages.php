<?php

$directory = __DIR__ . '/../../public/custom/images/slideshow';
$images = [];
if (is_dir($directory)) {
  $scannedImages = scandir($directory);
  $images = $scannedImages === false ? [] : array_values(array_diff($scannedImages, ['.', '..']));
}
?>




<div class="customImageContainer createImage" id="createImage" onclick="openBackgroundUpload()" role="button" tabindex="0" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openBackgroundUpload();}">
  <i class="material-icons">add</i>
</div>
<?php
$acceptedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
foreach ($images as $image) {
  if (!in_array(pathinfo($image, PATHINFO_EXTENSION), $acceptedExtensions)) {
    continue;
  }
  $filePath = __DIR__ . '/../../public/custom/images/slideshow/' . $image;
  if (!is_file($filePath)) {
    continue;
  }
  $imageUrl = '/custom/images/slideshow/' . rawurlencode($image);
  $fileSize = filesize($filePath); // Get the file size in bytes
  $fileSizeKB = round($fileSize / 1024, 2); // Convert to KB and round to 2 decimal places
?>
  <div class="customImageContainer" draggable="true" id="<?= $image ?>">
    <input type="checkbox" name="imageSelect" id="checkbox-<?= $image ?>" value="<?= $image ?>"
      class="imageRadio linkCheckbox" onchange="imageMultiSelect(event, this.value)">
    <img id="src-<?= $image ?>" src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" data-src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Background image" class="customImage" loading="lazy" decoding="async">
    <div class="customImageOverlay" onclick="createSnackbar('Double click to delete.')"
      ondblclick="deleteImage('<?= $image ?>')">
      <i class="material-icons overlayText">delete</i>
      <p class="overlayText"><?= $fileSizeKB ?> KB</p>
    </div>
  </div>
<?php
}
?>