<?php
if (!checkAdmin()) {
  http_response_code(401);
  exit;
}

require_once __DIR__ . '/cssEditorShared.php';

$theme = 'light';
$sourceCssPath = __DIR__ . '/../../custom/custom/css/custom-light.css';
$publicCssPath = __DIR__ . '/../../public/custom/css/custom-light.css';
$schema = customCssEditorTokenSchema();
$defaults = customCssEditorDefaultValues($theme);
$lightCss = customCssEditorReadContent($sourceCssPath, $publicCssPath);
$cssEntries = customCssEditorBuildEntries(
  $schema,
  customCssEditorParseVariables($lightCss),
  $defaults
);
$groupedCssEntries = customCssEditorGroupEntriesByCategory($cssEntries);

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
  $validationErrors = [];
  $cssEntries = customCssEditorApplyPostEntries($cssEntries, $_POST, $validationErrors);

  if (!empty($validationErrors)) {
    if (customCssEditorIsJsonRequest()) {
      customCssEditorRespondJson(422, [
        'success' => false,
        'message' => customCssEditorUiText('admin.css_validation_failed', 'Please fix invalid fields before saving.'),
        'errors' => $validationErrors,
      ]);
    }

    http_response_code(422);
    echo htmlspecialchars(customCssEditorUiText('admin.css_validation_failed', 'Please fix invalid fields before saving.'), ENT_QUOTES, 'UTF-8');
    exit;
  }

  $newCssContent = customCssEditorRenderCss($cssEntries);
  $saveSuccessful = customCssEditorWriteFiles([$sourceCssPath, $publicCssPath], $newCssContent);

  if (!$saveSuccessful) {
    $errorMessage = customCssEditorUiText('admin.error_uploading_css_file', 'Failed to update CSS file');
    if (customCssEditorIsJsonRequest()) {
      customCssEditorRespondJson(500, [
        'success' => false,
        'message' => $errorMessage,
      ]);
    }

    http_response_code(500);
    echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
    exit;
  }

  if (customCssEditorIsJsonRequest()) {
    customCssEditorRespondJson(200, [
      'success' => true,
      'message' => customCssEditorUiText('admin.css_updated_successfully', 'CSS updated successfully'),
      'theme' => $theme,
    ]);
  }

  $returnSection = isset($_POST['admin_section']) ? trim((string) $_POST['admin_section']) : 'customStyling';
  if ($returnSection === '') {
    $returnSection = 'customStyling';
  }

  $returnScroll = isset($_POST['admin_scroll']) ? (int) $_POST['admin_scroll'] : 0;
  if ($returnScroll < 0) {
    $returnScroll = 0;
  }

  $query = http_build_query([
    'section' => $returnSection,
    'scroll' => $returnScroll,
  ]);

  header('Location: /admin?' . $query);
  exit;
}
?>

<form method="POST" action="/custom-css-editor?comp=light" id="customCssEditorFormLight" class="customCssEditorForm"
  data-custom-css-editor="true" data-theme="light" novalidate>
  <div class="customCssEditorHeader">
    <div class="customCssEditorTitleRow">
      <h2 class="customCssEditorTitle"><?= htmlspecialchars(customCssEditorUiText('admin.edit_light_css', 'Edit Light CSS'), ENT_QUOTES, 'UTF-8'); ?></h2>
      <span class="customCssEditorThemeBadge"><?= htmlspecialchars(customCssEditorUiText('admin.light_theme', 'Light Theme'), ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <p class="customCssEditorSubtitle"><?= htmlspecialchars(customCssEditorUiText('admin.css_editor_helper', 'Adjust design tokens and save to apply changes instantly.'), ENT_QUOTES, 'UTF-8'); ?></p>
  </div>

  <input type="hidden" name="submit" value="submit">
  <input type="hidden" name="admin_section" id="adminSectionState" value="customStyling">
  <input type="hidden" name="admin_scroll" id="adminScrollState" value="0">
  <div class="customCssEditorSections">
    <?php foreach ($groupedCssEntries as $group): ?>
      <?php
      $groupId = htmlspecialchars((string) ($group['id'] ?? 'other'), ENT_QUOTES, 'UTF-8');
      $groupLabel = htmlspecialchars((string) ($group['label'] ?? 'Other'), ENT_QUOTES, 'UTF-8');
      $groupDescription = htmlspecialchars((string) ($group['description'] ?? ''), ENT_QUOTES, 'UTF-8');
      ?>
      <section class="customCssTokenSection" data-token-category="<?= $groupId; ?>">
        <div class="customCssTokenSectionHeader">
          <h3><?= $groupLabel; ?></h3>
          <?php if ($groupDescription !== ''): ?>
            <p><?= $groupDescription; ?></p>
          <?php endif; ?>
        </div>

        <div class="customCssTokenGrid">
          <?php foreach (($group['entries'] ?? []) as $entry): ?>
            <?php
            if (array_key_exists('editable', $entry) && $entry['editable'] === false) {
              continue;
            }

            $entryName = htmlspecialchars((string) $entry['name'], ENT_QUOTES, 'UTF-8');
            $entryLabel = htmlspecialchars((string) $entry['label'], ENT_QUOTES, 'UTF-8');
            $entryValue = htmlspecialchars((string) $entry['value'], ENT_QUOTES, 'UTF-8');
            $entryId = 'light-css-token-' . (string) $entry['name'];
            $entryIdEscaped = htmlspecialchars($entryId, ENT_QUOTES, 'UTF-8');
            $colorPickerId = $entryId . '-color';
            $colorPickerIdEscaped = htmlspecialchars($colorPickerId, ENT_QUOTES, 'UTF-8');
            $showColorPicker = ((string) $entry['type'] === 'color') && customCssEditorIsHexColor((string) $entry['value']);
            $colorPickerLabel = str_replace(
              '%label%',
              (string) $entry['label'],
              customCssEditorUiText('admin.color_picker_for', 'Pick color for %label%')
            );
            $colorPickerLabelEscaped = htmlspecialchars($colorPickerLabel, ENT_QUOTES, 'UTF-8');
            ?>
            <div class="linkInputContainer customCssTokenField" data-token-category="<?= $groupId; ?>">
              <input class="customCssTokenInput" placeholder="" type="text" id="<?= $entryIdEscaped; ?>"
                name="<?= $entryName; ?>" value="<?= $entryValue; ?>" autocomplete="off" spellcheck="false" />
              <label class="customCssTokenLabel" for="<?= $entryIdEscaped; ?>"><?= $entryLabel; ?></label>
              <span class="customCssTokenMeta">--<?= $entryName; ?></span>
              <?php if ($showColorPicker): ?>
                <input type="color" class="customCssTokenColor" value="<?= $entryValue; ?>" id="<?= $colorPickerIdEscaped; ?>"
                  aria-label="<?= $colorPickerLabelEscaped; ?>" title="<?= $colorPickerLabelEscaped; ?>"
                  onchange="document.getElementById('<?= $entryIdEscaped; ?>').value = this.value; document.getElementById('<?= $entryIdEscaped; ?>').dispatchEvent(new Event('input', { bubbles: true }));" />
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
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