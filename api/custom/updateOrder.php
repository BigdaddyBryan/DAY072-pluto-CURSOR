<?php
function updateOrder($order)
{
  // Extract the order array from the input
  $order = isset($order['order']) && is_array($order['order']) ? $order['order'] : [];

  // Set the directory where the images are located
  $imageDirectory = __DIR__ . '/../../public/custom/images/slideshow/';

  if (!is_dir($imageDirectory)) {
    echo json_encode([]);
    return;
  }

  // Get the current images from the directory
  $scannedImages = scandir($imageDirectory);
  $images = $scannedImages === false ? [] : array_values(array_diff($scannedImages, ['.', '..'])); // Remove the current and parent directory entries

  // Step 1: Create an array to hold the new names
  $newNames = [];

  // Step 2: Prepare the final names based on the new order
  foreach ($order as $index => $image) {
    // Extract the original name without any numeric prefix
    $originalImageName = preg_replace('/^\d+-/', '', $image);

    // Generate the final image name (e.g., 0-originalImageName.extension)
    $finalImageName = $index . '-' . $originalImageName;

    // Save the final name in the new names array
    $newNames[$image] = $finalImageName;
  }

  // Step 3: Rename files to the final names
  foreach ($newNames as $oldName => $finalName) {
    // Get the old image file path
    $oldImagePath = $imageDirectory . $oldName;

    // Get the final image file path
    $finalImagePath = $imageDirectory . $finalName;

    // Only rename if the old file exists and the new name is different
    if (file_exists($oldImagePath)) {
      // Debugging output
      if (!file_exists($finalImagePath)) {
        rename($oldImagePath, $finalImagePath);
      } else {
      }
    } else {
    }
  }

  // After renaming, return the sorted list of image names
  $scannedSortedImages = scandir($imageDirectory);
  $sortedImages = $scannedSortedImages === false ? [] : array_values(array_diff($scannedSortedImages, ['.', '..'])); // Remove directory entries

  echo json_encode(array_values($sortedImages)); // Return the new sorted list of files
}
