<?php

/**
 * Setup Wizard — Multi-step page for fresh installs and reconfiguration.
 * Accessible at /setup (auto-redirect for fresh installs, or via admin panel).
 */

require_once __DIR__ . '/../api/custom/cssEditorShared.php';

// Detect fresh install (no users in DB)
$isFreshInstall = false;
try {
    $checkPdo = connectToDatabase();
    $checkStmt = $checkPdo->query("SELECT COUNT(*) FROM users");
    $userCount = (int) $checkStmt->fetchColumn();
    closeConnection($checkPdo);
    $isFreshInstall = ($userCount === 0);
} catch (Exception $e) {
    $isFreshInstall = true;
}

// If not fresh install and not superadmin, redirect
if (!$isFreshInstall) {
    if (!function_exists('checkSuperAdmin') || !checkSuperAdmin()) {
        header('Location: /');
        exit;
    }
}

// Theme setup (CSS may not exist on fresh install)
$lightThemeCssPath = __DIR__ . '/../public/custom/css/custom-light.css';
$darkThemeCssPath = __DIR__ . '/../public/custom/css/custom-dark.css';
$lightThemeExists = is_file($lightThemeCssPath);
$darkThemeExists = is_file($darkThemeCssPath);
$lightThemeHref = $lightThemeExists ? '/custom/css/custom-light.css?v=' . filemtime($lightThemeCssPath) : '';
$darkThemeHref = $darkThemeExists ? '/custom/css/custom-dark.css?v=' . filemtime($darkThemeCssPath) : '';

$cookieTheme = isset($_COOKIE['theme']) ? strtolower(trim((string) $_COOKIE['theme'])) : '';
$themePreference = in_array($cookieTheme, ['light', 'dark', 'system'], true) ? $cookieTheme : 'light';
$initialTheme = $themePreference === 'system'
    ? (isset($_COOKIE['resolvedTheme']) && $_COOKIE['resolvedTheme'] === 'dark' ? 'dark' : 'light')
    : $themePreference;
$preloadBackground = $initialTheme === 'dark' ? '#0b1220' : '#f4f7fb';

$resolvedLightThemeHref = $lightThemeHref !== '' ? $lightThemeHref : $darkThemeHref;
$resolvedDarkThemeHref = $darkThemeHref !== '' ? $darkThemeHref : $lightThemeHref;
$initialThemeHref = $initialTheme === 'dark' ? $resolvedDarkThemeHref : $resolvedLightThemeHref;
$preloadThemeHref = $initialTheme === 'dark' ? $resolvedLightThemeHref : $resolvedDarkThemeHref;

// Load current defaults
$lightDefaults = customCssEditorDefaultValues('light');
$darkDefaults = customCssEditorDefaultValues('dark');

// Parse current custom CSS to get actual values (if files exist)
$lightCurrent = ['accent' => '#00cd87', 'canvas' => '#ffffff', 'text' => '#222831', 'border' => '#c8d2dc'];
$darkCurrent = ['accent' => '#00c887', 'canvas' => '#20252c', 'text' => '#f1ede6', 'border' => '#4a525e'];

if ($lightThemeExists) {
    $parsed = customCssEditorParseVariables(file_get_contents($lightThemeCssPath));
    if (!empty($parsed['color-accent'])) $lightCurrent['accent'] = $parsed['color-accent'];
    if (!empty($parsed['surface-canvas'])) $lightCurrent['canvas'] = $parsed['surface-canvas'];
    if (!empty($parsed['text-primary'])) $lightCurrent['text'] = $parsed['text-primary'];
    if (!empty($parsed['border-default'])) $lightCurrent['border'] = $parsed['border-default'];
}
if ($darkThemeExists) {
    $parsed = customCssEditorParseVariables(file_get_contents($darkThemeCssPath));
    if (!empty($parsed['color-accent'])) $darkCurrent['accent'] = $parsed['color-accent'];
    if (!empty($parsed['surface-canvas'])) $darkCurrent['canvas'] = $parsed['surface-canvas'];
    if (!empty($parsed['text-primary'])) $darkCurrent['text'] = $parsed['text-primary'];
    if (!empty($parsed['border-default'])) $darkCurrent['border'] = $parsed['border-default'];
}

// Presets
$presets = colorEnginePresets();
$hasExistingTheme = !$isFreshInstall && ($lightThemeExists || $darkThemeExists);
if ($hasExistingTheme) {
    $currentPresetLabel = function_exists('uiText') ? uiText('setup.preset_current', 'Current theme') : 'Current theme';
    $currentPreset = [
        'label' => $currentPresetLabel,
        'light' => $lightCurrent,
        'dark' => $darkCurrent,
    ];
    $presets = array_merge(['current' => $currentPreset], $presets);
}
$initialPresetKey = $hasExistingTheme ? 'current' : 'default';
$presetsJson = json_encode($presets, JSON_UNESCAPED_SLASHES);

$setupPreferencesPath = __DIR__ . '/../custom/custom/json/setup-wizard-preferences.json';
$initialBrandingMode = 'default';
if (is_file($setupPreferencesPath)) {
    $prefsRaw = file_get_contents($setupPreferencesPath);
    $prefsData = json_decode((string) $prefsRaw, true);
    if (is_array($prefsData) && (($prefsData['brandingMode'] ?? '') === 'custom')) {
        $initialBrandingMode = 'custom';
    }
}

// i18n
$uiTextStrings = [
    'setup_welcome_title' => function_exists('uiText') ? uiText('setup.welcome_title', 'Welcome to Setup') : 'Welcome to Setup',
    'setup_welcome_subtitle' => function_exists('uiText') ? uiText('setup.welcome_subtitle', 'Configure your deployment in a few simple steps.') : 'Configure your deployment in a few simple steps.',
    'setup_step_welcome' => function_exists('uiText') ? uiText('setup.step_welcome', 'Welcome') : 'Welcome',
    'setup_step_database' => function_exists('uiText') ? uiText('setup.step_database', 'Database') : 'Database',
    'setup_step_colors' => function_exists('uiText') ? uiText('setup.step_colors', 'Colors') : 'Colors',
    'setup_step_branding' => function_exists('uiText') ? uiText('setup.step_branding', 'Branding') : 'Branding',
    'setup_step_admin' => function_exists('uiText') ? uiText('setup.step_admin', 'Admin') : 'Admin',
    'setup_step_review' => function_exists('uiText') ? uiText('setup.step_review', 'Review') : 'Review',
    'setup_apply' => function_exists('uiText') ? uiText('setup.apply', 'Apply & Finish') : 'Apply & Finish',
    'setup_applying' => function_exists('uiText') ? uiText('setup.applying', 'Applying...') : 'Applying...',
    'setup_next' => function_exists('uiText') ? uiText('setup.next', 'Next') : 'Next',
    'setup_back' => function_exists('uiText') ? uiText('setup.back', 'Back') : 'Back',
    'setup_light_theme' => function_exists('uiText') ? uiText('setup.light_theme', 'Light Theme') : 'Light Theme',
    'setup_dark_theme' => function_exists('uiText') ? uiText('setup.dark_theme', 'Dark Theme') : 'Dark Theme',
    'setup_db_keep' => function_exists('uiText') ? uiText('setup.db_keep', 'Keep current database') : 'Keep current database',
    'setup_db_reset' => function_exists('uiText') ? uiText('setup.db_reset', 'Reset to clean slate') : 'Reset to clean slate',
    'setup_db_fresh' => function_exists('uiText') ? uiText('setup.db_fresh', 'Fresh database') : 'Fresh database',
    'setup_review_database' => function_exists('uiText') ? uiText('setup.review_database', 'Database') : 'Database',
    'setup_review_colors' => function_exists('uiText') ? uiText('setup.review_colors', 'Theme Colors') : 'Theme Colors',
    'setup_review_admin' => function_exists('uiText') ? uiText('setup.review_admin', 'Admin Account') : 'Admin Account',
    'setup_review_branding' => function_exists('uiText') ? uiText('setup.review_branding', 'Branding') : 'Branding',
    'setup_review_backup' => function_exists('uiText') ? uiText('setup.review_backup', 'Backup') : 'Backup',
    'setup_review_preset' => function_exists('uiText') ? uiText('setup.review_preset', 'Preset') : 'Preset',
    'setup_review_colors_kept' => function_exists('uiText') ? uiText('setup.review_colors_kept', 'Existing theme colors will be kept unchanged.') : 'Existing theme colors will be kept unchanged.',
    'setup_review_contrast_pass' => function_exists('uiText') ? uiText('setup.review_contrast_pass', 'All contrast checks pass') : 'All contrast checks pass',
    'setup_review_contrast_fail' => function_exists('uiText') ? uiText('setup.review_contrast_fail', 'Some contrast checks fail - auto-fix will be applied') : 'Some contrast checks fail - auto-fix will be applied',
    'setup_review_no_branding' => function_exists('uiText') ? uiText('setup.review_no_branding', 'No custom branding uploaded (using defaults)') : 'No custom branding uploaded (using defaults)',
    'setup_review_assets_count' => function_exists('uiText') ? uiText('setup.review_assets_count', '{n}/4 assets uploaded') : '{n}/4 assets uploaded',
    'setup_review_backup_notice' => function_exists('uiText') ? uiText('setup.review_backup_notice', 'A full backup will be created automatically before changes are applied.') : 'A full backup will be created automatically before changes are applied.',
    'setup_review_no_name' => function_exists('uiText') ? uiText('setup.review_no_name', '(no name)') : '(no name)',
    'setup_preset_label' => function_exists('uiText') ? uiText('setup.preset_label', 'Theme Preset') : 'Theme Preset',
    'setup_preset_current' => function_exists('uiText') ? uiText('setup.preset_current', 'Current theme') : 'Current theme',
    'setup_preset_custom' => function_exists('uiText') ? uiText('setup.preset_custom', 'Custom colors') : 'Custom colors',
    'setup_preset_hint' => function_exists('uiText') ? uiText('setup.preset_hint', 'Choose a preset as a starting point. As soon as you adjust colors manually, the wizard switches to Custom.') : 'Choose a preset as a starting point. As soon as you adjust colors manually, the wizard switches to Custom.',
    'setup_preset_reset' => function_exists('uiText') ? uiText('setup.preset_reset', 'Reset to selected preset') : 'Reset to selected preset',
    'setup_preset_restore_current' => function_exists('uiText') ? uiText('setup.preset_restore_current', 'Restore current theme') : 'Restore current theme',
    'setup_preset_restore_default' => function_exists('uiText') ? uiText('setup.preset_restore_default', 'Restore default preset') : 'Restore default preset',
    'setup_colors_keep_notice' => function_exists('uiText') ? uiText('setup.colors_keep_notice', 'Your current theme colors are kept. Only adjust below if you want to change them.') : 'Your current theme colors are kept. Only adjust below if you want to change them.',
    'setup_snack_colors_custom' => function_exists('uiText') ? uiText('setup.snack_colors_custom', 'Custom colors active') : 'Custom colors active',
    'setup_snack_preset_reset_default' => function_exists('uiText') ? uiText('setup.snack_preset_reset_default', 'Reset to Default preset.') : 'Reset to Default preset.',
    'setup_snack_preset_reapplied' => function_exists('uiText') ? uiText('setup.snack_preset_reapplied', 'Preset re-applied.') : 'Preset re-applied.',
    'setup_snack_preset_restored_current' => function_exists('uiText') ? uiText('setup.snack_preset_restored_current', 'Current theme restored.') : 'Current theme restored.',
    'setup_snack_preset_restored_default' => function_exists('uiText') ? uiText('setup.snack_preset_restored_default', 'Default preset restored.') : 'Default preset restored.',
    'setup_snack_upload_success' => function_exists('uiText') ? uiText('setup.snack_upload_success', 'Upload successful!') : 'Upload successful!',
    'setup_snack_upload_failed' => function_exists('uiText') ? uiText('setup.snack_upload_failed', 'Upload failed.') : 'Upload failed.',
    'setup_snack_upload_retry' => function_exists('uiText') ? uiText('setup.snack_upload_retry', 'Upload failed. Please try again.') : 'Upload failed. Please try again.',
    'setup_snack_branding_switch_custom' => function_exists('uiText') ? uiText('setup.snack_branding_switch_custom', 'Switch to Custom branding to upload assets.') : 'Switch to Custom branding to upload assets.',
    'setup_branding_mode_default' => function_exists('uiText') ? uiText('setup.branding_mode_default', 'Use default branding') : 'Use default branding',
    'setup_branding_mode_custom' => function_exists('uiText') ? uiText('setup.branding_mode_custom', 'Upload custom branding') : 'Upload custom branding',
    'setup_branding_mode_default_hint' => function_exists('uiText') ? uiText('setup.branding_mode_default_hint', 'Default branding stays active and this preference will be saved.') : 'Default branding stays active and this preference will be saved.',
    'setup_branding_mode_custom_hint' => function_exists('uiText') ? uiText('setup.branding_mode_custom_hint', 'Upload logo and favicon variants below. Uploaded assets are stored immediately.') : 'Upload logo and favicon variants below. Uploaded assets are stored immediately.',
    'setup_branding_default_notice' => function_exists('uiText') ? uiText('setup.branding_default_notice', 'Default branding is selected. You can switch to custom branding at any time.') : 'Default branding is selected. You can switch to custom branding at any time.',
    'setup_err_email_required' => function_exists('uiText') ? uiText('setup.err_email_required', 'Please enter an email address for the admin account.') : 'Please enter an email address for the admin account.',
    'setup_err_email_invalid' => function_exists('uiText') ? uiText('setup.err_email_invalid', 'Please enter a valid email address.') : 'Please enter a valid email address.',
    'setup_err_password_short' => function_exists('uiText') ? uiText('setup.err_password_short', 'Password must be at least 6 characters.') : 'Password must be at least 6 characters.',
    'setup_err_admin_required' => function_exists('uiText') ? uiText('setup.err_admin_required', 'Please fill in a valid admin account before clicking Apply.') : 'Please fill in a valid admin account before clicking Apply.',
    'setup_err_db_reset_failed' => function_exists('uiText') ? uiText('setup.err_db_reset_failed', 'Database reset failed.') : 'Database reset failed.',
    'setup_err_theme_failed' => function_exists('uiText') ? uiText('setup.err_theme_failed', 'Theme generation failed.') : 'Theme generation failed.',
    'setup_err_preferences_failed' => function_exists('uiText') ? uiText('setup.err_preferences_failed', 'Failed to save setup preferences.') : 'Failed to save setup preferences.',
    'setup_err_admin_failed' => function_exists('uiText') ? uiText('setup.err_admin_failed', 'Admin account creation failed.') : 'Admin account creation failed.',
    'setup_err_generic' => function_exists('uiText') ? uiText('setup.err_generic', 'An error occurred during setup.') : 'An error occurred during setup.',
    'setup_success_complete' => function_exists('uiText') ? uiText('setup.success_complete', 'Setup completed successfully!') : 'Setup completed successfully!',
];
$uiTextJson = json_encode($uiTextStrings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// Steps definition
$steps = [];
$steps[] = ['id' => 'welcome', 'label' => $uiTextStrings['setup_step_welcome']];
$steps[] = ['id' => 'database', 'label' => $uiTextStrings['setup_step_database']];
$steps[] = ['id' => 'colors', 'label' => $uiTextStrings['setup_step_colors']];
$steps[] = ['id' => 'branding', 'label' => $uiTextStrings['setup_step_branding']];
$steps[] = ['id' => 'admin', 'label' => $uiTextStrings['setup_step_admin']];
$steps[] = ['id' => 'review', 'label' => $uiTextStrings['setup_step_review']];

// Branding asset paths
$logoLightPath = __DIR__ . '/../public/custom/images/logo/logo-light.svg';
$logoDarkPath = __DIR__ . '/../public/custom/images/logo/logo-dark.svg';
$faviconLightPath = __DIR__ . '/../public/custom/images/icons/favicon-light.svg';
$faviconDarkPath = __DIR__ . '/../public/custom/images/icons/favicon-dark.svg';

$logoLightSrc = is_file($logoLightPath) ? '/custom/images/logo/logo-light.svg?v=' . filemtime($logoLightPath) : '';
$logoDarkSrc = is_file($logoDarkPath) ? '/custom/images/logo/logo-dark.svg?v=' . filemtime($logoDarkPath) : '';
$faviconLightSrc = is_file($faviconLightPath) ? '/custom/images/icons/favicon-light.svg?v=' . filemtime($faviconLightPath) : '';
$faviconDarkSrc = is_file($faviconDarkPath) ? '/custom/images/icons/favicon-dark.svg?v=' . filemtime($faviconDarkPath) : '';

$setupCssPath = __DIR__ . '/../public/css/setup.css';
$setupJsPath = __DIR__ . '/../public/javascript/setup.js';
$setupCssHref = '/css/setup.css' . (is_file($setupCssPath) ? '?v=' . filemtime($setupCssPath) : '');
$setupJsHref = '/javascript/setup.js' . (is_file($setupJsPath) ? '?v=' . filemtime($setupJsPath) : '');

$esc = function ($str) {
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
};
$t = function ($key, $fallback) {
    return htmlspecialchars(
        function_exists('uiText') ? uiText('setup.' . $key, $fallback) : $fallback,
        ENT_QUOTES,
        'UTF-8'
    );
};
?>
<!DOCTYPE html>
<html lang="<?= function_exists('uiLocale') ? uiLocale() : 'en' ?>">

<head>
    <meta name="color-scheme" content="light dark">
    <style>
        html,
        body {
            background-color: <?= $esc($preloadBackground) ?>;
        }
    </style>
    <script>
        (function() {
            var serverPreference = "<?= $esc($themePreference) ?>";
            var preference = serverPreference;

            try {
                var storedPreference = localStorage.getItem("themePreference");
                if (storedPreference) {
                    preference = storedPreference;
                }
            } catch (error) {}

            if (
                preference !== "light" &&
                preference !== "dark" &&
                preference !== "system"
            ) {
                preference =
                    serverPreference === "light" ||
                    serverPreference === "dark" ||
                    serverPreference === "system" ?
                    serverPreference :
                    "light";
            }

            var resolvedTheme = preference;
            if (resolvedTheme === "system") {
                try {
                    var cachedResolvedTheme = localStorage.getItem("resolvedTheme");
                    if (cachedResolvedTheme === "dark" || cachedResolvedTheme === "light") {
                        resolvedTheme = cachedResolvedTheme;
                    }
                } catch (error) {}

                try {
                    resolvedTheme = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
                } catch (error) {
                    resolvedTheme = "light";
                }
            }

            if (resolvedTheme !== "dark" && resolvedTheme !== "light") {
                resolvedTheme = "light";
            }

            document.documentElement.style.backgroundColor = resolvedTheme === "dark" ? "#0b1220" : "#f4f7fb";
            document.documentElement.style.colorScheme = resolvedTheme;

            window.addEventListener("DOMContentLoaded", function() {
                var modeLink = document.getElementById("darkMode");
                if (!modeLink) {
                    return;
                }

                var lightHref = modeLink.getAttribute("data-light-href") || "";
                var darkHref = modeLink.getAttribute("data-dark-href") || "";
                var nextHref = resolvedTheme === "dark" ? darkHref : lightHref;
                if (nextHref) {
                    modeLink.setAttribute("href", nextHref);
                }
            });

            try {
                localStorage.setItem("resolvedTheme", resolvedTheme);
                if (
                    preference === "light" ||
                    preference === "dark" ||
                    preference === "system"
                ) {
                    localStorage.setItem("themePreference", preference);
                }
                document.cookie = "theme=" + encodeURIComponent(preference) + ";path=/;max-age=31536000;SameSite=Lax";
                document.cookie = "resolvedTheme=" + encodeURIComponent(resolvedTheme) + ";path=/;max-age=31536000;SameSite=Lax";
            } catch (error) {}
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t('page_title', 'Setup Wizard') ?></title>
    <?php if ($initialThemeHref): ?>
        <link
            id="darkMode"
            rel="stylesheet"
            data-light-href="<?= $esc($resolvedLightThemeHref) ?>"
            data-dark-href="<?= $esc($resolvedDarkThemeHref) ?>"
            href="<?= $esc($initialThemeHref) ?>">
    <?php endif; ?>
    <?php if ($preloadThemeHref && $preloadThemeHref !== $initialThemeHref): ?>
        <link
            id="themePreload"
            rel="preload"
            as="style"
            href="<?= $esc($preloadThemeHref) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/css/material-icons.css">
    <link rel="stylesheet" href="<?= $esc($setupCssHref) ?>">
    <?php if (!$lightThemeExists && !$darkThemeExists): ?>
        <style>
            /* Inline fallback for fresh installs without custom CSS */
            :root {
                --color-accent: #00cd87;
                --surface-canvas: #ffffff;
                --surface-base: #fafbfd;
                --surface-muted: #e8edf2;
                --text-primary: #222831;
                --text-secondary: #3e4652;
                --border-default: #c8d2dc;
                --border-soft: #e4ebf2;
                --radius-sm: 5px;
                --radius-md: 10px;
                --radius-lg: 15px;
                --shadow-soft: 0 1px 2px rgba(0, 0, 0, 0.06);
                --shadow-medium: 0 8px 20px rgba(0, 0, 0, 0.18);
                --motion-duration-base: 0.3s;
                --action-primary-bg: #dde6ef;
                --action-primary-fg: #26303b;
                --action-danger-bg: #dc5b5b;
                --action-danger-fg: #ffffff;
            }
        </style>
    <?php endif; ?>
</head>

<body class="setupBody" data-theme-preference="<?= $esc($themePreference) ?>">
    <div class="setupWizard"
        data-fresh-install="<?= $isFreshInstall ? '1' : '0' ?>"
        data-has-existing-theme="<?= $hasExistingTheme ? '1' : '0' ?>"
        data-branding-mode="<?= $esc($initialBrandingMode) ?>"
        data-presets="<?= $esc($presetsJson) ?>">

        <script>
            window.setupUiText = <?= $uiTextJson ?>;
        </script>

        <!-- ═══ Progress Bar ═══ -->
        <div class="setupProgressBar">
            <div class="setupProgress">
                <?php foreach ($steps as $i => $step): ?>
                    <div class="setupProgressStep<?= $i === 0 ? ' is-active' : '' ?>" data-step-index="<?= $i ?>">
                        <?php if ($i > 0): ?><div class="setupProgressLine"></div><?php endif; ?>
                        <div class="setupProgressDot"><?= $i + 1 ?></div>
                        <span class="setupProgressLabel"><?= $esc($step['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ═══ Card ═══ -->
        <div class="setupMain">
            <div class="setupCard">
                <h2 class="setupCardTitle"><?= $t('welcome_title', 'Welcome to Setup') ?></h2>
                <p class="setupCardSubtitle"><?= $t('welcome_subtitle', 'Configure your deployment in a few simple steps.') ?></p>

                <div class="setupError"></div>

                <!-- ─── Step 1: Welcome ─── -->
                <div class="setupStepPanel is-active" data-step="welcome"
                    data-step-title="<?= $t('welcome_title', 'Welcome to Setup') ?>"
                    data-step-subtitle="<?= $t('welcome_subtitle', 'Configure your deployment in a few simple steps.') ?>">

                    <div class="setupLangPicker">
                        <button class="setupLangBtn<?= (function_exists('uiLocale') ? uiLocale() : 'en') === 'en' ? ' is-active' : '' ?>" data-lang="en">English</button>
                        <button class="setupLangBtn<?= (function_exists('uiLocale') ? uiLocale() : 'en') === 'nl' ? ' is-active' : '' ?>" data-lang="nl">Nederlands</button>
                    </div>

                    <div class="setupWelcomeGrid">
                        <div class="setupWelcomeCard">
                            <i class="material-icons">storage</i>
                            <div>
                                <h4><?= $t('welcome_database', 'Database') ?></h4>
                                <p><?= $t('welcome_database_desc', 'Start fresh, reset to clean slate, or keep your existing data.') ?></p>
                            </div>
                        </div>
                        <div class="setupWelcomeCard">
                            <i class="material-icons">palette</i>
                            <div>
                                <h4><?= $t('welcome_colors', 'Auto Theme Colors') ?></h4>
                                <p><?= $t('welcome_colors_desc', 'Choose a preset or fine-tune your palette — your full theme is generated automatically with WCAG contrast checking.') ?></p>
                            </div>
                        </div>
                        <div class="setupWelcomeCard">
                            <i class="material-icons">branding_watermark</i>
                            <div>
                                <h4><?= $t('welcome_branding', 'Branding') ?></h4>
                                <p><?= $t('welcome_branding_desc', 'Upload your logo and favicon for light and dark themes.') ?></p>
                            </div>
                        </div>
                        <div class="setupWelcomeCard">
                            <i class="material-icons">folder</i>
                            <div>
                                <h4><?= $t('welcome_custom_folder', 'Custom Folder') ?></h4>
                                <p><?= $t('welcome_custom_folder_desc', 'Deployment uses your custom folder and branding files directly.') ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── Step 2: Database ─── -->
                <div class="setupStepPanel" data-step="database"
                    data-step-title="<?= $t('database_title', 'Database') ?>"
                    data-step-subtitle="<?= $t('database_subtitle', 'Choose how to handle your database for this deployment.') ?>">

                    <div class="setupDbOptions">
                        <?php if ($isFreshInstall): ?>
                            <div class="setupDbOption is-selected" data-action="fresh">
                                <i class="material-icons">fiber_new</i>
                                <div>
                                    <h4><?= $t('db_fresh', 'Fresh Database') ?></h4>
                                    <p><?= $t('db_fresh_desc', 'Start with an empty database. You will create an admin account in the next step.') ?></p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="setupDbOption is-selected" data-action="keep">
                                <i class="material-icons">check_circle</i>
                                <div>
                                    <h4><?= $t('db_keep', 'Keep Current Database') ?></h4>
                                    <p><?= $t('db_keep_desc', 'Keep your existing data, users, and links intact.') ?></p>
                                </div>
                            </div>
                            <div class="setupDbOption" data-action="reset">
                                <i class="material-icons">restart_alt</i>
                                <div>
                                    <h4><?= $t('db_reset', 'Reset to Clean Slate') ?></h4>
                                    <p><?= $t('db_reset_desc', 'Delete all data and start fresh. This cannot be undone.') ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ─── Step 3: Colors ─── -->
                <div class="setupStepPanel" data-step="colors"
                    data-colors-optional="<?= $hasExistingTheme ? '1' : '0' ?>"
                    data-step-title="<?= $t('colors_title', 'Theme Colors') ?>"
                    data-step-subtitle="<?= $t('colors_subtitle', 'Choose 4 base colors per theme. All other colors are generated automatically with contrast checking.') ?>">

                    <div class="setupColorsKeepBanner" id="setupColorsKeepBanner">
                        <i class="material-icons">info</i>
                        <span><?= $t('colors_keep_notice', 'Your current theme colors are kept. Only adjust below if you want to change them.') ?></span>
                    </div>

                    <!-- Presets -->
                    <div class="setupPresetSelectorWrap">
                        <div class="setupPresetSelectorHeader">
                            <label class="setupPresetLabel" for="setupPresetSelect"><?= $t('preset_label', 'Theme Preset') ?></label>
                            <span class="setupPresetMode is-preset" id="setupPresetModeBadge"><?= $esc($presets[$initialPresetKey]['label'] ?? 'Default') ?></span>
                        </div>
                        <div class="setupPresetSelectorControls">
                            <select id="setupPresetSelect" class="setupPresetSelect" aria-label="<?= $t('preset_label', 'Theme Preset') ?>">
                                <option value="custom"><?= $t('preset_custom', 'Custom colors') ?></option>
                                <?php foreach ($presets as $key => $preset): ?>
                                    <option value="<?= $esc($key) ?>" <?= $key === $initialPresetKey ? ' selected' : '' ?>><?= $esc($preset['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="setupBtn setupBtn-secondary setupPresetResetBtn">
                                <i class="material-icons">restore</i>
                                <span class="setupPresetResetBtnLabel"><?= $hasExistingTheme ? $t('preset_restore_current', 'Restore current theme') : $t('preset_restore_default', 'Restore default preset') ?></span>
                            </button>
                        </div>
                        <p class="setupPresetHint"><?= $t('preset_hint', 'Choose a preset as a starting point. As soon as you adjust colors manually, the wizard switches to Custom.') ?></p>
                    </div>

                    <div class="setupColorGrid">
                        <!-- Light Theme -->
                        <div class="setupThemeColumn">
                            <h3><i class="material-icons">light_mode</i> <?= $t('light_theme', 'Light Theme') ?></h3>

                            <?php foreach (['accent' => 'Accent', 'canvas' => 'Background', 'text' => 'Text', 'border' => 'Border'] as $prop => $label): ?>
                                <div class="setupColorInput" data-theme="light" data-prop="<?= $prop ?>">
                                    <label><?= $t('color_' . $prop, $label) ?></label>
                                    <input type="color" value="<?= $esc($lightCurrent[$prop]) ?>">
                                    <input type="text" value="<?= $esc($lightCurrent[$prop]) ?>" maxlength="7" spellcheck="false">
                                </div>
                            <?php endforeach; ?>

                            <!-- Preview -->
                            <div class="setupPreview" data-theme="light">
                                <div class="setupPreviewBar">
                                    <span class="setupPreviewAccent" style="background:<?= $esc($lightCurrent['accent']) ?>"></span>
                                    <?= $t('preview', 'Preview') ?>
                                </div>
                                <div class="setupPreviewContent">
                                    <p><?= $t('preview_text', 'This is how your interface will look with these colors.') ?></p>
                                    <button class="setupPreviewBtn is-primary"><?= $t('preview_button', 'Button') ?></button>
                                    <button class="setupPreviewBtn is-danger"><?= $t('preview_danger', 'Delete') ?></button>
                                </div>
                            </div>

                            <!-- Contrast -->
                            <div class="setupContrastResults" data-theme="light">
                                <h4><?= $t('contrast_check', 'Contrast Check') ?></h4>
                                <div class="setupContrastRow"><span><?= $t('contrast_text_canvas', 'Text / Canvas') ?></span> <span class="setupContrastBadge is-pass">—</span></div>
                                <div class="setupContrastRow"><span><?= $t('contrast_secondary_base', 'Secondary / Base') ?></span> <span class="setupContrastBadge is-pass">—</span></div>
                                <div class="setupContrastRow"><span><?= $t('contrast_accent_canvas', 'Accent / Canvas') ?></span> <span class="setupContrastBadge is-pass">—</span></div>
                                <div class="setupContrastRow"><span><?= $t('contrast_btn', 'Button Text / BG') ?></span> <span class="setupContrastBadge is-pass">—</span></div>
                                <div class="setupContrastRow"><span><?= $t('contrast_danger', 'Danger Text / BG') ?></span> <span class="setupContrastBadge is-pass">—</span></div>
                            </div>
                        </div>

                        <!-- Dark Theme -->
                        <div class="setupThemeColumn">
                            <h3><i class="material-icons">dark_mode</i> <?= $t('dark_theme', 'Dark Theme') ?></h3>

                            <?php foreach (['accent' => 'Accent', 'canvas' => 'Background', 'text' => 'Text', 'border' => 'Border'] as $prop => $label): ?>
                                <div class="setupColorInput" data-theme="dark" data-prop="<?= $prop ?>">
                                    <label><?= $t('color_' . $prop, $label) ?></label>
                                    <input type="color" value="<?= $esc($darkCurrent[$prop]) ?>">
                                    <input type="text" value="<?= $esc($darkCurrent[$prop]) ?>" maxlength="7" spellcheck="false">
                                </div>
                            <?php endforeach; ?>

                            <!-- Preview -->
                            <div class="setupPreview" data-theme="dark">
                                <div class="setupPreviewBar">
                                    <span class="setupPreviewAccent" style="background:<?= $esc($darkCurrent['accent']) ?>"></span>
                                    <?= $t('preview', 'Preview') ?>
                                </div>
                                <div class="setupPreviewContent">
                                    <p><?= $t('preview_text', 'This is how your interface will look with these colors.') ?></p>
                                    <button class="setupPreviewBtn is-primary"><?= $t('preview_button', 'Button') ?></button>
                                    <button class="setupPreviewBtn is-danger"><?= $t('preview_danger', 'Delete') ?></button>
                                </div>
                            </div>

                            <!-- Contrast -->
                            <div class="setupContrastResults" data-theme="dark">
                                <h4><?= $t('contrast_check', 'Contrast Check') ?></h4>
                                <div class="setupContrastRow"><span><?= $t('contrast_text_canvas', 'Text / Canvas') ?></span> <span class="setupContrastBadge is-pass">—</span></div>
                                <div class="setupContrastRow"><span><?= $t('contrast_secondary_base', 'Secondary / Base') ?></span> <span class="setupContrastBadge is-pass">—</span></div>
                                <div class="setupContrastRow"><span><?= $t('contrast_accent_canvas', 'Accent / Canvas') ?></span> <span class="setupContrastBadge is-pass">—</span></div>
                                <div class="setupContrastRow"><span><?= $t('contrast_btn', 'Button Text / BG') ?></span> <span class="setupContrastBadge is-pass">—</span></div>
                                <div class="setupContrastRow"><span><?= $t('contrast_danger', 'Danger Text / BG') ?></span> <span class="setupContrastBadge is-pass">—</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── Step 4: Branding ─── -->
                <div class="setupStepPanel" data-step="branding"
                    data-step-title="<?= $t('branding_title', 'Branding') ?>"
                    data-step-subtitle="<?= $t('branding_subtitle', 'Upload your logo and favicon. SVG format recommended.') ?>">

                    <div class="setupBrandingModeSwitch">
                        <button
                            type="button"
                            class="setupBrandingModeBtn<?= $initialBrandingMode === 'default' ? ' is-active' : '' ?>"
                            data-branding-mode-btn="default">
                            <?= $t('branding_mode_default', 'Use default branding') ?>
                        </button>
                        <button
                            type="button"
                            class="setupBrandingModeBtn<?= $initialBrandingMode === 'custom' ? ' is-active' : '' ?>"
                            data-branding-mode-btn="custom">
                            <?= $t('branding_mode_custom', 'Upload custom branding') ?>
                        </button>
                    </div>

                    <p class="setupBrandingModeHint" id="setupBrandingModeHint">
                        <?= $initialBrandingMode === 'custom' ? $t('branding_mode_custom_hint', 'Upload logo and favicon variants below. Uploaded assets are stored immediately.') : $t('branding_mode_default_hint', 'Default branding stays active and this preference will be saved.') ?>
                    </p>

                    <p class="setupBrandingDefaultNotice<?= $initialBrandingMode === 'custom' ? ' is-hidden' : '' ?>" id="setupBrandingDefaultNotice">
                        <?= $t('branding_default_notice', 'Default branding is selected. You can switch to custom branding at any time.') ?>
                    </p>

                    <div class="setupBrandingGridWrap<?= $initialBrandingMode === 'default' ? ' is-hidden' : '' ?>" id="setupBrandingGridWrap">

                        <div class="setupBrandingGrid">
                            <div class="setupBrandingCard<?= $logoLightSrc ? ' is-uploaded' : '' ?>">
                                <h4><i class="material-icons setupBrandingThemeIcon">light_mode</i> <?= $t('logo_light', 'Logo (Light Theme)') ?></h4>
                                <div class="setupBrandingPreview">
                                    <?php if ($logoLightSrc): ?>
                                        <img src="<?= $esc($logoLightSrc) ?>" alt="Light logo">
                                    <?php else: ?>
                                        <i class="material-icons setupBrandingPlaceholderIcon">image</i>
                                    <?php endif; ?>
                                </div>
                                <input type="file" class="setupHiddenFileInput" accept=".svg" data-asset-type="logo" data-theme="light">
                                <button class="setupBtn setupBtn-secondary setupBrandingUploadBtn"><i class="material-icons">upload</i> <?= $t('upload', 'Upload') ?></button>
                            </div>

                            <div class="setupBrandingCard<?= $logoDarkSrc ? ' is-uploaded' : '' ?>">
                                <h4><i class="material-icons setupBrandingThemeIcon">dark_mode</i> <?= $t('logo_dark', 'Logo (Dark Theme)') ?></h4>
                                <div class="setupBrandingPreview">
                                    <?php if ($logoDarkSrc): ?>
                                        <img src="<?= $esc($logoDarkSrc) ?>" alt="Dark logo">
                                    <?php else: ?>
                                        <i class="material-icons setupBrandingPlaceholderIcon">image</i>
                                    <?php endif; ?>
                                </div>
                                <input type="file" class="setupHiddenFileInput" accept=".svg" data-asset-type="logo" data-theme="dark">
                                <button class="setupBtn setupBtn-secondary setupBrandingUploadBtn"><i class="material-icons">upload</i> <?= $t('upload', 'Upload') ?></button>
                            </div>

                            <div class="setupBrandingCard<?= $faviconLightSrc ? ' is-uploaded' : '' ?>">
                                <h4><i class="material-icons setupBrandingThemeIcon">light_mode</i> <?= $t('favicon_light', 'Favicon (Light Theme)') ?></h4>
                                <div class="setupBrandingPreview is-favicon">
                                    <?php if ($faviconLightSrc): ?>
                                        <img src="<?= $esc($faviconLightSrc) ?>" alt="Light favicon">
                                    <?php else: ?>
                                        <i class="material-icons setupBrandingPlaceholderIcon is-favicon">image</i>
                                    <?php endif; ?>
                                </div>
                                <input type="file" class="setupHiddenFileInput" accept=".svg" data-asset-type="favicon" data-theme="light">
                                <button class="setupBtn setupBtn-secondary setupBrandingUploadBtn"><i class="material-icons">upload</i> <?= $t('upload', 'Upload') ?></button>
                            </div>

                            <div class="setupBrandingCard<?= $faviconDarkSrc ? ' is-uploaded' : '' ?>">
                                <h4><i class="material-icons setupBrandingThemeIcon">dark_mode</i> <?= $t('favicon_dark', 'Favicon (Dark Theme)') ?></h4>
                                <div class="setupBrandingPreview is-favicon">
                                    <?php if ($faviconDarkSrc): ?>
                                        <img src="<?= $esc($faviconDarkSrc) ?>" alt="Dark favicon">
                                    <?php else: ?>
                                        <i class="material-icons setupBrandingPlaceholderIcon is-favicon">image</i>
                                    <?php endif; ?>
                                </div>
                                <input type="file" class="setupHiddenFileInput" accept=".svg" data-asset-type="favicon" data-theme="dark">
                                <button class="setupBtn setupBtn-secondary setupBrandingUploadBtn"><i class="material-icons">upload</i> <?= $t('upload', 'Upload') ?></button>
                            </div>
                        </div>

                        <p class="setupBrandingHint"><?= $t('branding_hint', 'SVG files recommended. You can skip this step and configure branding later.') ?></p>
                    </div>
                </div>

                <!-- ─── Step 5: Admin Account ─── -->
                <div class="setupStepPanel" data-step="admin"
                    data-step-title="<?= $t('admin_title', 'Create Admin Account') ?>"
                    data-step-subtitle="<?= $t('admin_subtitle', 'Set up the first admin account to access the dashboard.') ?>">

                    <div class="setupAdminFormWrap">
                        <div class="setupFormRow">
                            <div class="setupFormGroup">
                                <label for="setupAdminName"><?= $t('admin_name', 'Name (optional)') ?></label>
                                <input type="text" id="setupAdminName" autocomplete="name">
                            </div>
                            <div class="setupFormGroup">
                                <label for="setupAdminEmail"><?= $t('admin_email', 'Email') ?></label>
                                <input type="email" id="setupAdminEmail" autocomplete="email" required>
                            </div>
                        </div>
                        <div class="setupFormGroup">
                            <label for="setupAdminPassword"><?= $t('admin_password', 'Password') ?></label>
                            <div class="setupPasswordWrap">
                                <input type="password" id="setupAdminPassword" autocomplete="new-password" required minlength="6">
                                <button type="button" class="setupPasswordToggle" aria-label="Toggle password visibility">
                                    <i class="material-icons">visibility</i>
                                </button>
                            </div>
                            <p class="setupFieldHint"><?= $t('admin_password_hint', 'Minimum 6 characters.') ?></p>
                        </div>
                    </div>
                </div>

                <!-- ─── Step N: Review ─── -->
                <div class="setupStepPanel" data-step="review"
                    data-step-title="<?= $t('review_title', 'Review & Apply') ?>"
                    data-step-subtitle="<?= $t('review_subtitle', 'Check your settings and apply them.') ?>">

                    <div class="setupReviewContent">
                        <!-- Filled by JS -->
                    </div>
                </div>

                <div class="setupActions">
                    <button class="setupBtn setupBtn-secondary setupBtn-prev is-hidden">
                        <i class="material-icons">arrow_back</i> <?= $t('back', 'Back') ?>
                    </button>
                    <div class="setupActionsSpacer"></div>
                    <?php if (!$isFreshInstall): ?>
                        <a href="/admin" class="setupBtn setupBtn-secondary setupCancelLink">
                            <i class="material-icons">close</i> <?= $t('cancel', 'Cancel') ?>
                        </a>
                    <?php endif; ?>
                    <button class="setupBtn setupBtn-primary setupBtn-next">
                        <?= $t('next', 'Next') ?> <i class="material-icons">arrow_forward</i>
                    </button>
                    <button class="setupBtn setupBtn-primary setupBtn-apply is-hidden">
                        <i class="material-icons">check</i> <?= $t('apply', 'Apply & Finish') ?>
                    </button>
                </div>

                <?php if (!$isFreshInstall): ?>
                    <div class="setupBackupNotice">
                        <i class="material-icons">info</i>
                        <span><?= $t('backup_notice', 'A full backup will be created automatically before any changes are applied.') ?></span>
                    </div>
                <?php endif; ?>
            </div><!-- /.setupCard -->
        </div><!-- /.setupMain -->
    </div>

    <!-- Snackbar -->
    <div class="setupSnackbar" id="setupSnackbar"></div>

    <script src="<?= $esc($setupJsHref) ?>"></script>
</body>

</html>