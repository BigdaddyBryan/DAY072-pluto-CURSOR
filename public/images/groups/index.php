<?php
// All errors found by the error handler
$errors = [];
// Route to the content directory
$contentRoute = "../content/";
// Route to the custom directory
$customRoute = "../" . $directory . "/custom/";
$customContentRoute = $customRoute . "content/";

require $contentRoute . "functions.php";
require $contentRoute . "variables/variables.php";
require $contentRoute . "variables/variableErrorHandler.php";

// If there are no errors in the content.json file it opens the main.php file
if (empty($errors) === true) {
    require $contentRoute . "pages/main.php";
} else {
    require $contentRoute . "pages/error.php";
}
?>