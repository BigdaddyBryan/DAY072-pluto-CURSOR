<?php
$directory = __DIR__ . '/../../public/custom/images/slideshow';
$images = is_dir($directory) ? scandir($directory) : [];
$images = array_filter($images, function ($image) use ($directory) {
  if ($image === '.' || $image === '..') {
    return false;
  }

  $fullPath = $directory . '/' . $image;
  if (!is_file($fullPath)) {
    return false;
  }

  $extension = strtolower(pathinfo($image, PATHINFO_EXTENSION));
  return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg'], true);
});

$oneTimeLoginError = '';
if (isset($_SESSION['errorType']) && $_SESSION['errorType'] === 'login' && !empty($_SESSION['error'])) {
  $oneTimeLoginError = (string) $_SESSION['error'];
  unset($_SESSION['error'], $_SESSION['errorType']);
}



// Get the google Client ID from the config folder
$clientId = json_decode(file_get_contents(__DIR__ . '/../../custom/googleSSO.json'), true)['clientId'];

$forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$requestScheme = (
  $forwardedProto === 'https'
  || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
) ? 'https' : 'http';

$requestHost = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
$currentOrigin = $requestHost !== '' ? ($requestScheme . '://' . $requestHost) : '';
$fallbackOrigin = isset($shortlinkBaseUrl) ? trim((string) $shortlinkBaseUrl) : '';
$googleLoginBase = $currentOrigin !== '' ? $currentOrigin : $fallbackOrigin;
$googleLoginUri = rtrim($googleLoginBase, '/') . '/googleLogin';
$currentYear = (int) date('Y');
$copyrightStartYear = isset($copyrightStartYear) ? (int) $copyrightStartYear : 2024;
if ($copyrightStartYear > 0 && $copyrightStartYear < $currentYear) {
  $copyrightYearLabel = $copyrightStartYear . ' - ' . $currentYear;
} else {
  $copyrightYearLabel = (string) $currentYear;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="color-scheme" content="light dark">
  <style>
    html,
    body {
      background-color: #f4f7fb;
    }
  </style>
  <script>
    (function() {
      var preference = "light";
      try {
        preference = localStorage.getItem("themePreference") || "light";
      } catch (error) {}

      var resolvedTheme = preference;
      if (resolvedTheme === "system") {
        try {
          resolvedTheme = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
        } catch (error) {
          resolvedTheme = "light";
        }
      }

      if (resolvedTheme === "dark") {
        document.documentElement.style.backgroundColor = "#0b1220";
      } else {
        document.documentElement.style.backgroundColor = "#f4f7fb";
      }
      document.documentElement.style.colorScheme = resolvedTheme === "dark" ? "dark" : "light";
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" href="/fonts/material-icons.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap">
  <title><?= $titles['home']['title'] ?></title>
  <link rel="stylesheet" href="css/custom.css">
  <link rel="stylesheet" href="custom/css/custom-light.css">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/modal.css">
  <link rel="stylesheet" href="/css/material-icons.css">
  <link rel="stylesheet" href="css/mobile.css">
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="icon" href="images/icons/favicon.svg" type="image/svg+xml">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name='description' content='Just a random description of my website.'>
</head>

<body>
  <?php if ($oneTimeLoginError !== '') { ?>
    <input type="hidden" id="oneTimeLoginError" value="<?= htmlspecialchars($oneTimeLoginError, ENT_QUOTES, 'UTF-8') ?>">
  <?php } ?>

  <div class="backgroundContainer" id="slideshow">
    <?php
    $firstImage = true;
    foreach ($images as $image) {
    ?>
      <img src="custom/images/slideshow/<?= $image ?>" alt="<?= htmlspecialchars(uiText('home.background_alt', 'Background image'), ENT_QUOTES, 'UTF-8') ?>"
        class="background <?= $firstImage ? 'fade-in' : '' ?>" style="<?= $firstImage ? '' : 'display: none;' ?>">
    <?php
      $firstImage = false;
    }
    ?>
  </div>
  <div onclick="createModal('/loginModal')" class="normalLoginButton" id="loginButton">
    <i class="material-icons">login</i>
    <a class="loginButton"><?= htmlspecialchars(uiText('home.login', 'Login'), ENT_QUOTES, 'UTF-8') ?></a>
  </div>
  <div class="copyright" id="copyright">
    <p>
      <span class="copyrightYear">&copy; <?= htmlspecialchars($copyrightYearLabel, ENT_QUOTES, 'UTF-8') ?></span><br>
      <span class="copyrightText"><?= htmlspecialchars(uiText('home.copyright', 'All rights reserved - Daylinq'), ENT_QUOTES, 'UTF-8') ?></span>
    </p>
  </div>
  <div id="g_id_onload" data-client_id="<?= htmlspecialchars($clientId, ENT_QUOTES, 'UTF-8') ?>" data-login_uri="<?= htmlspecialchars($googleLoginUri, ENT_QUOTES, 'UTF-8') ?>" data-auto_prompt="false">
  </div>
  <div class="g_id_signin gSignIn" data-type="standard" data-text="sign_in_with"></div>
  <script>
    window.__UI_TEXT__ = <?= uiTextJson('', []) ?>;
  </script>
  <script src="/javascript/home.js"></script>
  <script src="https://accounts.google.com/gsi/client" async></script>
</body>
<script src="/javascript/script.js?v=<?= $version ?>"></script>
<script>
  document.addEventListener("DOMContentLoaded", function() {
    const oneTimeLoginErrorInput = document.getElementById('oneTimeLoginError');
    if (oneTimeLoginErrorInput && oneTimeLoginErrorInput.value) {
      // Show after paint so it remains visible even right after a redirect.
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          createSnackbar('✗ ' + oneTimeLoginErrorInput.value, {
            type: 'error',
            duration: 5000
          });
        });
      });
    }

    const copyrightElement = document.getElementById('copyright');

    // Function to calculate the luminance of a color
    function getLuminance(color) {
      const rgb = color.match(/\d+/g).map(Number);
      const [r, g, b] = rgb.map(c => {
        c /= 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
      });
      return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    }

    // Function to update the text color based on the background color
    function updateTextColor() {
      const backgroundColor = window.getComputedStyle(copyrightElement).backgroundColor;
      const luminance = getLuminance(backgroundColor);
      const textColor = luminance > 0.5 ? 'black' : 'white';
      copyrightElement.style.color = textColor;
    }

    // Initial color update
    updateTextColor();

    // Observe changes to the background color
    const observer = new MutationObserver(updateTextColor);
    observer.observe(copyrightElement, {
      attributes: true,
      attributeFilter: ['style']
    });

    // Periodically check for changes in the background color
    setInterval(updateTextColor, 1000);

    const images = document.querySelectorAll("#slideshow .background");
    let currentIndex = 0;

    // Image change function
    function changeImage() {
      const currentImage = images[currentIndex];
      currentImage.classList.add('fade-out');
      currentImage.classList.remove('fade-in');

      setTimeout(() => {
        currentImage.style.display = 'none';
        currentImage.classList.remove('fade-out');

        currentIndex = (currentIndex + 1) % images.length;
        const nextImage = images[currentIndex];
        nextImage.style.display = 'block';

        // Ensure the browser registers the display change before adding the fade-in class
        setTimeout(() => {
          nextImage.classList.add('fade-in');
        }, 10); // Small delay to ensure the display change is registered

        setTimeout(() => {
          nextImage.classList.remove('fade-in');
        }, 7000); // Match this duration with the CSS transition duration
      }, 1000); // Match this duration with the CSS transition duration
    }

    setInterval(changeImage, 8000); // Change image every 8 seconds
  });
</script>

</html>