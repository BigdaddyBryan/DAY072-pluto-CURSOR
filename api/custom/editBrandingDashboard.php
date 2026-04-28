<?php
if (!checkAdmin()) {
    http_response_code(401);
    exit;
}

require_once __DIR__ . '/cssEditorShared.php';

$buildAssetHref = static function ($absolutePath, $publicHref) {
    if (!is_file($absolutePath)) {
        return '';
    }

    return $publicHref . '?v=' . rawurlencode((string) filemtime($absolutePath));
};

$logoLightPath = __DIR__ . '/../../public/custom/images/logo/logo-light.svg';
$logoDarkPath = __DIR__ . '/../../public/custom/images/logo/logo-dark.svg';
$logoLightHref = $buildAssetHref($logoLightPath, '/custom/images/logo/logo-light.svg');
$logoDarkHref = $buildAssetHref($logoDarkPath, '/custom/images/logo/logo-dark.svg');
if ($logoLightHref === '' && $logoDarkHref !== '') {
    $logoLightHref = $logoDarkHref;
}
if ($logoDarkHref === '' && $logoLightHref !== '') {
    $logoDarkHref = $logoLightHref;
}
if ($logoLightHref === '') {
    $logoLightHref = '/custom/images/logo/logo-light.svg';
}
if ($logoDarkHref === '') {
    $logoDarkHref = '/custom/images/logo/logo-dark.svg';
}

$fallbackFaviconPath = __DIR__ . '/../../public/custom/images/icons/favicon.svg';
$fallbackFaviconHref = $buildAssetHref($fallbackFaviconPath, '/custom/images/icons/favicon.svg');
if ($fallbackFaviconHref === '') {
    $fallbackFaviconHref = '/custom/images/icons/favicon.svg';
}

$faviconLightPath = __DIR__ . '/../../public/custom/images/icons/favicon-light.svg';
$faviconDarkPath = __DIR__ . '/../../public/custom/images/icons/favicon-dark.svg';
$faviconLightHref = $buildAssetHref($faviconLightPath, '/custom/images/icons/favicon-light.svg');
$faviconDarkHref = $buildAssetHref($faviconDarkPath, '/custom/images/icons/favicon-dark.svg');
if ($faviconLightHref === '') {
    $faviconLightHref = $fallbackFaviconHref;
}
if ($faviconDarkHref === '') {
    $faviconDarkHref = $fallbackFaviconHref;
}

$lightThemeLabel = customCssEditorUiText('admin.light_theme', 'Light Theme');
$darkThemeLabel = customCssEditorUiText('admin.dark_theme', 'Dark Theme');
$replaceForTheme = customCssEditorUiText('admin.replace_for_theme', 'Replace for');
?>

<div
    class="brandAssetDashboard"
    data-brand-asset-dashboard="true"
    data-active-theme="light"
    data-logo-light-src="<?= htmlspecialchars($logoLightHref, ENT_QUOTES, 'UTF-8'); ?>"
    data-logo-dark-src="<?= htmlspecialchars($logoDarkHref, ENT_QUOTES, 'UTF-8'); ?>"
    data-favicon-light-src="<?= htmlspecialchars($faviconLightHref, ENT_QUOTES, 'UTF-8'); ?>"
    data-favicon-dark-src="<?= htmlspecialchars($faviconDarkHref, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="brandAssetHeader">
        <div class="brandAssetTitleRow">
            <h2 class="brandAssetTitle"><?= htmlspecialchars(customCssEditorUiText('admin.branding_dashboard', 'Branding Dashboard'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <span class="brandAssetThemeBadge" data-brand-active-theme-label><?= htmlspecialchars($lightThemeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <p class="brandAssetSubtitle"><?= htmlspecialchars(customCssEditorUiText('admin.branding_dashboard_helper', 'Manage logo and favicon for both themes from one simple dashboard.'), ENT_QUOTES, 'UTF-8'); ?></p>
    </div>

    <div class="brandAssetToolbar">
        <div class="customCssEditorTabs brandAssetThemeTabs" role="tablist" aria-label="<?= htmlspecialchars(customCssEditorUiText('admin.theme_tabs', 'Theme tabs'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="customCssEditorTab brandAssetThemeTab is-active" data-brand-theme-tab="light" role="tab" aria-selected="true">
                <i class="material-icons" aria-hidden="true">light_mode</i>
                <?= htmlspecialchars($lightThemeLabel, ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <button type="button" class="customCssEditorTab brandAssetThemeTab" data-brand-theme-tab="dark" role="tab" aria-selected="false">
                <i class="material-icons" aria-hidden="true">dark_mode</i>
                <?= htmlspecialchars($darkThemeLabel, ENT_QUOTES, 'UTF-8'); ?>
            </button>
        </div>

        <p class="brandAssetHint"><?= htmlspecialchars(customCssEditorUiText('admin.branding_svg_hint', 'Upload SVG files only. Changes are applied immediately.'), ENT_QUOTES, 'UTF-8'); ?></p>
    </div>

    <div class="brandAssetGrid">
        <article class="brandAssetCard" data-brand-asset-type="logo">
            <div class="brandAssetPreviewWrap brandAssetPreviewWrapLogo">
                <img
                    class="brandAssetPreview brandAssetPreviewLogo"
                    data-brand-preview="logo"
                    src="<?= htmlspecialchars($logoLightHref, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?= htmlspecialchars(customCssEditorUiText('admin.logo_preview', 'Logo preview'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="brandAssetCardBody">
                <h3><?= htmlspecialchars(customCssEditorUiText('admin.brand_logo', 'Site Logo'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?= htmlspecialchars(customCssEditorUiText('admin.brand_logo_desc', 'Shown in the main navigation and header.'), ENT_QUOTES, 'UTF-8'); ?></p>

                <div class="brandAssetCardActions">
                    <input type="file" id="brandAssetLogoUpload" data-brand-upload-input="logo" accept=".svg,image/svg+xml">
                    <button type="button" class="submitButton" data-brand-upload-trigger="logo">
                        <i class="material-icons" aria-hidden="true">upload_file</i>
                        <?= htmlspecialchars($replaceForTheme, ENT_QUOTES, 'UTF-8'); ?>
                        <span data-brand-current-theme><?= htmlspecialchars($lightThemeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                </div>
            </div>
        </article>

        <article class="brandAssetCard" data-brand-asset-type="favicon">
            <div class="brandAssetPreviewWrap brandAssetPreviewWrapFavicon">
                <img
                    class="brandAssetPreview brandAssetPreviewFavicon"
                    data-brand-preview="favicon"
                    src="<?= htmlspecialchars($faviconLightHref, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?= htmlspecialchars(customCssEditorUiText('admin.favicon_preview', 'Favicon preview'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="brandAssetCardBody">
                <h3><?= htmlspecialchars(customCssEditorUiText('admin.brand_favicon', 'Site Favicon'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?= htmlspecialchars(customCssEditorUiText('admin.brand_favicon_desc', 'Shown in browser tabs and bookmarks.'), ENT_QUOTES, 'UTF-8'); ?></p>

                <div class="brandAssetCardActions">
                    <input type="file" id="brandAssetFaviconUpload" data-brand-upload-input="favicon" accept=".svg,image/svg+xml">
                    <button type="button" class="submitButton" data-brand-upload-trigger="favicon">
                        <i class="material-icons" aria-hidden="true">upload</i>
                        <?= htmlspecialchars($replaceForTheme, ENT_QUOTES, 'UTF-8'); ?>
                        <span data-brand-current-theme><?= htmlspecialchars($lightThemeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                </div>
            </div>
        </article>
    </div>
</div>