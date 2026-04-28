<?php
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

checkUser();

$message = $_GET['message'] ?? uiText('errors.unknown_user', 'Who are you?');
$safeMessage = htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8');

include 'components/navigation.php';

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= $domain ?></title>
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/mobile.css">
  <link rel="stylesheet" href="/css/material-icons.css">
  <link rel="stylesheet" href="/css/modal.css">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body>
  <div class="errorContainer">
    <h1><?= $safeMessage ?></h1>
  </div>
</body>

</html>