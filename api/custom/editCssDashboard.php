<?php
if (!checkAdmin()) {
    http_response_code(401);
    exit;
}

require_once __DIR__ . '/cssEditorShared.php';

function renderCustomCssThemeForm($theme, $groupedCssEntries)
{
    $themeSafe = htmlspecialchars((string) $theme, ENT_QUOTES, 'UTF-8');
    $formId = 'customCssEditorForm' . ucfirst((string) $theme);
    $formIdEscaped = htmlspecialchars($formId, ENT_QUOTES, 'UTF-8');
    $action = '/custom-css-editor?comp=' . rawurlencode((string) $theme);
    $actionEscaped = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
?>
    <form method="POST" action="<?= $actionEscaped; ?>" id="<?= $formIdEscaped; ?>"
        class="customCssEditorForm customCssThemeForm" data-custom-css-editor="true" data-theme="<?= $themeSafe; ?>" novalidate>
        <input type="hidden" name="submit" value="submit">
        <input type="hidden" name="admin_section" id="adminSectionState" value="customStyling">
        <input type="hidden" name="admin_scroll" id="adminScrollState" value="0">

        <div class="customCssEditorSections">
            <?php foreach ($groupedCssEntries as $group): ?>
                <?php
                $groupIdRaw = (string) ($group['id'] ?? 'other');
                $groupId = htmlspecialchars($groupIdRaw, ENT_QUOTES, 'UTF-8');
                $groupLabel = htmlspecialchars((string) ($group['label'] ?? 'Other'), ENT_QUOTES, 'UTF-8');
                $groupDescription = htmlspecialchars((string) ($group['description'] ?? ''), ENT_QUOTES, 'UTF-8');
                ?>
                <details class="customCssTokenSection" data-token-category="<?= $groupId; ?>" open>
                    <summary class="customCssTokenSectionHeader">
                        <h3><?= $groupLabel; ?></h3>
                        <?php if ($groupDescription !== ''): ?>
                            <p><?= $groupDescription; ?></p>
                        <?php endif; ?>
                    </summary>

                    <div class="customCssTokenGrid">
                        <?php foreach (($group['entries'] ?? []) as $entry): ?>
                            <?php
                            if (array_key_exists('editable', $entry) && $entry['editable'] === false) {
                                continue;
                            }

                            $entryNameRaw = (string) ($entry['name'] ?? '');
                            if ($entryNameRaw === '') {
                                continue;
                            }

                            $entryLabelRaw = (string) ($entry['label'] ?? $entryNameRaw);
                            $entryValueRaw = (string) ($entry['value'] ?? '');
                            $entryTypeRaw = strtolower((string) ($entry['type'] ?? 'text'));

                            $entryName = htmlspecialchars($entryNameRaw, ENT_QUOTES, 'UTF-8');
                            $entryLabel = htmlspecialchars($entryLabelRaw, ENT_QUOTES, 'UTF-8');
                            $entryValue = htmlspecialchars($entryValueRaw, ENT_QUOTES, 'UTF-8');
                            $entryType = htmlspecialchars($entryTypeRaw, ENT_QUOTES, 'UTF-8');

                            $entryId = $theme . '-css-token-' . $entryNameRaw;
                            $entryIdEscaped = htmlspecialchars($entryId, ENT_QUOTES, 'UTF-8');

                            $colorPickerId = $entryId . '-color';
                            $colorPickerIdEscaped = htmlspecialchars($colorPickerId, ENT_QUOTES, 'UTF-8');

                            $showColorPicker = $entryTypeRaw === 'color';
                            $colorPickerLabel = str_replace(
                                '%label%',
                                $entryLabelRaw,
                                customCssEditorUiText('admin.color_picker_for', 'Pick color for %label%')
                            );
                            $colorPickerLabelEscaped = htmlspecialchars($colorPickerLabel, ENT_QUOTES, 'UTF-8');
                            $copyLabelEscaped = htmlspecialchars(
                                customCssEditorUiText('admin.copy_current_value', 'Copy current value'),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                            <div class="linkInputContainer customCssTokenField" data-token-category="<?= $groupId; ?>" data-token-type="<?= $entryType; ?>">
                                <input
                                    class="customCssTokenInput"
                                    placeholder=""
                                    type="text"
                                    id="<?= $entryIdEscaped; ?>"
                                    name="<?= $entryName; ?>"
                                    value="<?= $entryValue; ?>"
                                    autocomplete="off"
                                    spellcheck="false"
                                    data-token-type="<?= $entryType; ?>"
                                    data-token-name="<?= $entryName; ?>"
                                    data-token-label="<?= $entryLabel; ?>" />

                                <label class="customCssTokenLabel" for="<?= $entryIdEscaped; ?>"><?= $entryLabel; ?></label>
                                <span class="customCssTokenMeta">--<?= $entryName; ?></span>
                                <button
                                    type="button"
                                    class="customCssTokenCopy material-icons"
                                    data-copy-token-value="true"
                                    aria-label="<?= $copyLabelEscaped; ?>"
                                    title="<?= $copyLabelEscaped; ?>">content_copy</button>
                                <p class="customCssTokenError" aria-live="polite"></p>

                                <?php if ($showColorPicker): ?>
                                    <span class="customCssTokenHoverSwatch" aria-hidden="true"></span>
                                    <input
                                        type="color"
                                        class="customCssTokenColor"
                                        value="<?= customCssEditorIsHexColor($entryValueRaw) ? htmlspecialchars((string) strtolower($entryValueRaw), ENT_QUOTES, 'UTF-8') : '#000000'; ?>"
                                        id="<?= $colorPickerIdEscaped; ?>"
                                        aria-label="<?= $colorPickerLabelEscaped; ?>"
                                        title="<?= $colorPickerLabelEscaped; ?>" />
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>

        <div class="customCssEditorActions">
            <button class="submitButton" type="submit"
                data-default-label="<?= htmlspecialchars(customCssEditorUiText('admin.update_css', 'Update CSS'), ENT_QUOTES, 'UTF-8'); ?>"
                data-pending-label="<?= htmlspecialchars(customCssEditorUiText('admin.updating_css', 'Updating CSS...'), ENT_QUOTES, 'UTF-8'); ?>">
                <?= htmlspecialchars(customCssEditorUiText('admin.update_css', 'Update CSS'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
        </div>
    </form>
<?php
}

$schema = customCssEditorTokenSchema();

$lightDefaults = customCssEditorDefaultValues('light');
$lightCss = customCssEditorReadContent(
    __DIR__ . '/../../custom/custom/css/custom-light.css',
    __DIR__ . '/../../public/custom/css/custom-light.css'
);
$lightEntries = customCssEditorBuildEntries(
    $schema,
    customCssEditorParseVariables($lightCss),
    $lightDefaults
);
$lightGroupedEntries = customCssEditorGroupEntriesByCategory($lightEntries);

$darkDefaults = customCssEditorDefaultValues('dark');
$darkCss = customCssEditorReadContent(
    __DIR__ . '/../../custom/custom/css/custom-dark.css',
    __DIR__ . '/../../public/custom/css/custom-dark.css'
);
$darkEntries = customCssEditorBuildEntries(
    $schema,
    customCssEditorParseVariables($darkCss),
    $darkDefaults
);
$darkGroupedEntries = customCssEditorGroupEntriesByCategory($darkEntries);
?>

<div class="customCssDashboard" data-custom-css-editor-dashboard="true">
    <div class="customCssEditorHeader">
        <div class="customCssEditorTitleRow">
            <h2 class="customCssEditorTitle"><?= htmlspecialchars(customCssEditorUiText('admin.custom_styling_dashboard', 'Custom Styling Dashboard'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <span class="customCssEditorThemeBadge"><?= htmlspecialchars(customCssEditorUiText('admin.light_and_dark', 'Light + Dark'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <p class="customCssEditorSubtitle"><?= htmlspecialchars(customCssEditorUiText('admin.css_editor_dashboard_helper', 'Edit both themes in one place, validate values, and import or export styles without leaving this modal.'), ENT_QUOTES, 'UTF-8'); ?></p>
    </div>

    <div class="customCssDashboardToolbar">
        <div class="customCssEditorTabs" role="tablist" aria-label="<?= htmlspecialchars(customCssEditorUiText('admin.theme_tabs', 'Theme tabs'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="customCssEditorTab is-active" data-theme-tab="light" role="tab" aria-selected="true">
                <i class="material-icons" aria-hidden="true">light_mode</i>
                <?= htmlspecialchars(customCssEditorUiText('admin.light_theme', 'Light Theme'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <button type="button" class="customCssEditorTab" data-theme-tab="dark" role="tab" aria-selected="false">
                <i class="material-icons" aria-hidden="true">dark_mode</i>
                <?= htmlspecialchars(customCssEditorUiText('admin.dark_theme', 'Dark Theme'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
        </div>

        <div class="customCssFormatSwitch" role="group" aria-label="<?= htmlspecialchars(customCssEditorUiText('admin.color_format', 'Color format'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="customCssFormatButton is-active" data-color-format="hex">HEX</button>
            <button type="button" class="customCssFormatButton" data-color-format="rgb">RGB</button>
        </div>

        <div class="customCssTransferActions">
            <input type="file" id="customCssDashboardZipUpload" accept=".zip" oninput="uploadCustomCSS('customCssDashboardZipUpload')">
            <button type="button" class="submitButton" onclick="downloadCustomFolder()">
                <i class="material-icons">folder_zip</i>
                <?= htmlspecialchars(customCssEditorUiText('admin.download_zip', 'Download ZIP'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <button type="button" class="submitButton" onclick="document.getElementById('customCssDashboardZipUpload').click()">
                <i class="material-icons">upload_file</i>
                <?= htmlspecialchars(customCssEditorUiText('admin.upload_zip', 'Upload ZIP'), ENT_QUOTES, 'UTF-8'); ?>
            </button>

            <input type="file" id="customCssDashboardThemeUpload" accept=".css" oninput="uploadCustomThemeCss(window.getActiveCustomCssTheme ? window.getActiveCustomCssTheme() : 'light', 'customCssDashboardThemeUpload')">
            <button type="button" class="submitButton" onclick="downloadCustomThemeCss(window.getActiveCustomCssTheme ? window.getActiveCustomCssTheme() : 'light')">
                <i class="material-icons">download</i>
                <?= htmlspecialchars(customCssEditorUiText('admin.download_css_theme', 'Download Theme CSS'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <button type="button" class="submitButton" onclick="document.getElementById('customCssDashboardThemeUpload').click()">
                <i class="material-icons">upload</i>
                <?= htmlspecialchars(customCssEditorUiText('admin.upload_css_theme', 'Import Theme CSS'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
        </div>
    </div>

    <div class="customCssThemePanels">
        <section class="customCssThemePanel is-active" data-theme-panel="light">
            <?php renderCustomCssThemeForm('light', $lightGroupedEntries); ?>
        </section>

        <section class="customCssThemePanel" data-theme-panel="dark" hidden>
            <?php renderCustomCssThemeForm('dark', $darkGroupedEntries); ?>
        </section>
    </div>
</div>