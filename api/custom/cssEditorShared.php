<?php

if (!function_exists('customCssEditorTokenSchema')) {
    function customCssEditorTokenSchema()
    {
        return [
            ['name' => 'color-accent', 'legacy' => 'primary-color', 'label' => 'Accent Color', 'type' => 'color'],
            ['name' => 'color-accent-soft', 'legacy' => 'primary-color-light', 'label' => 'Accent Color Light', 'type' => 'color'],
            ['name' => 'color-accent-strong', 'legacy' => 'primary-color-dark', 'label' => 'Accent Color Dark', 'type' => 'color'],
            ['name' => 'color-accent-rgb', 'legacy' => 'primary-color-rgb', 'label' => 'Accent Color RGB', 'type' => 'rgb', 'derivedFrom' => 'color-accent', 'editable' => false],
            ['name' => 'surface-canvas', 'legacy' => 'background-color-light', 'label' => 'Surface Canvas', 'type' => 'color'],
            ['name' => 'surface-base', 'legacy' => 'background-color', 'label' => 'Surface Base', 'type' => 'color'],
            ['name' => 'surface-muted', 'legacy' => 'background-color-dark', 'label' => 'Surface Muted', 'type' => 'color'],
            ['name' => 'surface-elevated', 'legacy' => 'background-color-required', 'label' => 'Surface Elevated', 'type' => 'color'],
            ['name' => 'surface-hover', 'legacy' => 'background-color-hover', 'label' => 'Surface Hover', 'type' => 'color'],
            ['name' => 'text-primary', 'legacy' => 'text-color', 'label' => 'Text Primary', 'type' => 'color'],
            ['name' => 'text-secondary', 'legacy' => 'text-color-secondary', 'label' => 'Text Secondary', 'type' => 'color'],
            ['name' => 'text-strong', 'legacy' => 'text-color-light', 'label' => 'Text Strong', 'type' => 'color'],
            ['name' => 'text-inverse', 'legacy' => 'text-color-dark', 'label' => 'Text Inverse', 'type' => 'color'],
            ['name' => 'text-primary-rgb', 'legacy' => 'text-color-rgb', 'label' => 'Text Primary RGB', 'type' => 'rgb', 'derivedFrom' => 'text-primary', 'editable' => false],
            ['name' => 'border-default', 'legacy' => 'border-color', 'label' => 'Border Default', 'type' => 'color'],
            ['name' => 'border-soft', 'legacy' => 'border-color-light', 'label' => 'Border Soft', 'type' => 'color'],
            ['name' => 'border-strong', 'legacy' => 'border-color-dark', 'label' => 'Border Strong', 'type' => 'color'],
            ['name' => 'surface-tooltip', 'legacy' => 'tooltip-color', 'label' => 'Surface Tooltip', 'type' => 'color'],
            ['name' => 'shadow-soft', 'legacy' => '', 'label' => 'Shadow Soft', 'type' => 'shadow'],
            ['name' => 'shadow-medium', 'legacy' => '', 'label' => 'Shadow Medium', 'type' => 'shadow'],
            ['name' => 'shadow-strong', 'legacy' => '', 'label' => 'Shadow Strong', 'type' => 'shadow'],
            ['name' => 'radius-sm', 'legacy' => 'border-radius', 'label' => 'Radius Small', 'type' => 'text'],
            ['name' => 'radius-md', 'legacy' => 'border-radius-secondaire', 'label' => 'Radius Medium', 'type' => 'text'],
            ['name' => 'radius-lg', 'legacy' => 'secondary-radius', 'label' => 'Radius Large', 'type' => 'text'],
            ['name' => 'surface-text-highlight', 'legacy' => 'text-background', 'label' => 'Surface Text Highlight', 'type' => 'color'],
            ['name' => 'motion-duration-base', 'legacy' => 'general-transition', 'label' => 'Motion Duration Base', 'type' => 'text'],
            ['name' => 'surface-version', 'legacy' => 'version', 'label' => 'Surface Version', 'type' => 'color'],
            ['name' => 'color-white', 'legacy' => 'light-color', 'label' => 'Color White', 'type' => 'color'],
            ['name' => 'action-primary-bg', 'legacy' => 'button-primary-color', 'label' => 'Action Primary Background', 'type' => 'color'],
            ['name' => 'action-primary-fg', 'legacy' => 'button-primary-text-color', 'label' => 'Action Primary Text', 'type' => 'color'],
            ['name' => 'action-primary-hover-bg', 'legacy' => 'button-primary-hover', 'label' => 'Action Primary Hover', 'type' => 'color'],
            ['name' => 'action-danger-bg', 'legacy' => 'button-danger-color', 'label' => 'Action Danger Background', 'type' => 'color'],
            ['name' => 'action-danger-fg', 'legacy' => 'button-danger-text-color', 'label' => 'Action Danger Text', 'type' => 'color'],
            ['name' => 'action-danger-hover-bg', 'legacy' => 'button-danger-hover', 'label' => 'Action Danger Hover', 'type' => 'color'],
            ['name' => 'action-danger-rgb', 'legacy' => 'button-danger-color-rgb', 'label' => 'Action Danger RGB', 'type' => 'rgb', 'derivedFrom' => 'action-danger-bg', 'editable' => false],
            ['name' => 'action-secondary-bg', 'legacy' => 'button-secondary-color', 'label' => 'Action Secondary Background', 'type' => 'color'],
            ['name' => 'action-secondary-fg', 'legacy' => 'button-secondary-text-color', 'label' => 'Action Secondary Text', 'type' => 'color'],
            ['name' => 'action-secondary-hover-bg', 'legacy' => 'button-secondary-hover', 'label' => 'Action Secondary Hover', 'type' => 'color'],
        ];
    }
}

if (!function_exists('customCssEditorResolveCategory')) {
    function customCssEditorResolveCategory($tokenName)
    {
        $normalized = strtolower(trim((string) $tokenName));

        if (strpos($normalized, 'color-') === 0) {
            return 'color';
        }

        if (strpos($normalized, 'surface-') === 0) {
            return 'surface';
        }

        if (strpos($normalized, 'text-') === 0) {
            return 'text';
        }

        if (strpos($normalized, 'border-') === 0) {
            return 'border';
        }

        if (strpos($normalized, 'shadow-') === 0) {
            return 'shadow';
        }

        if (strpos($normalized, 'radius-') === 0) {
            return 'radius';
        }

        if (strpos($normalized, 'motion-') === 0) {
            return 'motion';
        }

        if (strpos($normalized, 'action-') === 0) {
            return 'actions';
        }

        return 'other';
    }
}

if (!function_exists('customCssEditorCategoryDefinitions')) {
    function customCssEditorCategoryDefinitions()
    {
        return [
            'color' => [
                'label' => customCssEditorUiText('admin.css_category_color', 'Color'),
                'description' => customCssEditorUiText('admin.css_category_color_desc', 'Primary accents and utility colors.'),
            ],
            'surface' => [
                'label' => customCssEditorUiText('admin.css_category_surface', 'Surface'),
                'description' => customCssEditorUiText('admin.css_category_surface_desc', 'Canvas and layered backgrounds used across the UI.'),
            ],
            'text' => [
                'label' => customCssEditorUiText('admin.css_category_text', 'Text'),
                'description' => customCssEditorUiText('admin.css_category_text_desc', 'Readable text tones for content and emphasis.'),
            ],
            'border' => [
                'label' => customCssEditorUiText('admin.css_category_border', 'Border'),
                'description' => customCssEditorUiText('admin.css_category_border_desc', 'Divider and stroke contrast levels.'),
            ],
            'shadow' => [
                'label' => customCssEditorUiText('admin.css_category_shadow', 'Shadow'),
                'description' => customCssEditorUiText('admin.css_category_shadow_desc', 'Depth and elevation values for floating surfaces.'),
            ],
            'radius' => [
                'label' => customCssEditorUiText('admin.css_category_radius', 'Radius'),
                'description' => customCssEditorUiText('admin.css_category_radius_desc', 'Corner roundness scale for components.'),
            ],
            'motion' => [
                'label' => customCssEditorUiText('admin.css_category_motion', 'Motion'),
                'description' => customCssEditorUiText('admin.css_category_motion_desc', 'Base transition timing used in interactions.'),
            ],
            'actions' => [
                'label' => customCssEditorUiText('admin.css_category_actions', 'Actions'),
                'description' => customCssEditorUiText('admin.css_category_actions_desc', 'Button and intent colors for actions.'),
            ],
        ];
    }
}

if (!function_exists('customCssEditorGroupEntriesByCategory')) {
    function customCssEditorGroupEntriesByCategory($entries)
    {
        $grouped = [];
        $definitions = customCssEditorCategoryDefinitions();

        foreach ($definitions as $categoryId => $meta) {
            $grouped[$categoryId] = [
                'id' => (string) $categoryId,
                'label' => (string) ($meta['label'] ?? ucfirst((string) $categoryId)),
                'description' => (string) ($meta['description'] ?? ''),
                'entries' => [],
            ];
        }

        foreach ($entries as $entry) {
            $categoryId = strtolower((string) ($entry['category'] ?? ''));

            if ($categoryId === '' || !isset($grouped[$categoryId])) {
                $categoryId = 'other';
            }

            if (!isset($grouped[$categoryId])) {
                $grouped[$categoryId] = [
                    'id' => 'other',
                    'label' => customCssEditorUiText('admin.css_category_other', 'Other'),
                    'description' => '',
                    'entries' => [],
                ];
            }

            $grouped[$categoryId]['entries'][] = $entry;
        }

        $result = [];
        foreach ($grouped as $group) {
            if (!empty($group['entries'])) {
                $result[] = $group;
            }
        }

        return $result;
    }
}

if (!function_exists('customCssEditorDefaultValues')) {
    function customCssEditorDefaultValues($theme)
    {
        $darkDefaults = [
            'color-accent' => '#00c887',
            'color-accent-soft' => '#22d9a6',
            'color-accent-strong' => '#00956b',
            'color-accent-rgb' => '0, 200, 135',
            'surface-canvas' => '#20252c',
            'surface-base' => '#2a3038',
            'surface-muted' => '#000000',
            'surface-elevated' => '#2a3a4d',
            'surface-hover' => '#313a45',
            'text-primary' => '#f1ede6',
            'text-secondary' => '#ffffff',
            'text-strong' => '#ffffff',
            'text-inverse' => '#ffffff',
            'text-primary-rgb' => '241, 237, 230',
            'border-default' => '#4a525e',
            'border-soft' => '#697585',
            'border-strong' => '#2a2f38',
            'surface-tooltip' => '#2f343d',
            'shadow-soft' => '0 1px 2px rgba(0, 0, 0, 0.22)',
            'shadow-medium' => '0 8px 20px rgba(0, 0, 0, 0.35)',
            'shadow-strong' => '0 18px 52px rgba(0, 0, 0, 0.46)',
            'radius-sm' => '5px',
            'radius-md' => '10px',
            'radius-lg' => '15px',
            'surface-text-highlight' => '#3a424e',
            'motion-duration-base' => '0.3s',
            'surface-version' => '#30435a',
            'color-white' => '#f1ede6',
            'action-primary-bg' => '#324459',
            'action-primary-fg' => '#f5f0e8',
            'action-primary-hover-bg' => '#40556f',
            'action-danger-bg' => '#a94646',
            'action-danger-fg' => '#ffffff',
            'action-danger-hover-bg' => '#c15757',
            'action-danger-rgb' => '169, 70, 70',
            'action-secondary-bg' => '#46505d',
            'action-secondary-fg' => '#f1ede6',
            'action-secondary-hover-bg' => '#606d7f',
        ];

        $lightDefaults = [
            'color-accent' => '#00cd87',
            'color-accent-soft' => '#b9f5e2',
            'color-accent-strong' => '#00a97f',
            'color-accent-rgb' => '0, 205, 135',
            'surface-canvas' => '#ffffff',
            'surface-base' => '#fafbfd',
            'surface-muted' => '#e8edf2',
            'surface-elevated' => '#dfe8ed',
            'surface-hover' => '#eef3f7',
            'text-primary' => '#222831',
            'text-secondary' => '#3e4652',
            'text-strong' => '#3e4652',
            'text-inverse' => '#000000',
            'text-primary-rgb' => '34, 40, 49',
            'border-default' => '#c8d2dc',
            'border-soft' => '#e4ebf2',
            'border-strong' => '#9cabb9',
            'surface-tooltip' => '#ffffff',
            'shadow-soft' => '0 1px 2px rgba(0, 0, 0, 0.06)',
            'shadow-medium' => '0 8px 20px rgba(0, 0, 0, 0.18)',
            'shadow-strong' => '0 18px 52px rgba(0, 0, 0, 0.28)',
            'radius-sm' => '5px',
            'radius-md' => '10px',
            'radius-lg' => '15px',
            'surface-text-highlight' => '#e3edf2',
            'motion-duration-base' => '0.3s',
            'surface-version' => '#e3eaf1',
            'color-white' => '#ffffff',
            'action-primary-bg' => '#dde6ef',
            'action-primary-fg' => '#26303b',
            'action-primary-hover-bg' => '#c3d2df',
            'action-danger-bg' => '#dc5b5b',
            'action-danger-fg' => '#ffffff',
            'action-danger-hover-bg' => '#c14d4d',
            'action-danger-rgb' => '220, 91, 91',
            'action-secondary-bg' => '#e7edf4',
            'action-secondary-fg' => '#2d3744',
            'action-secondary-hover-bg' => '#d0dbe8',
        ];

        return $theme === 'dark' ? $darkDefaults : $lightDefaults;
    }
}

if (!function_exists('customCssEditorReadContent')) {
    function customCssEditorReadContent($sourcePath, $publicPath)
    {
        if (is_file($sourcePath)) {
            return (string) file_get_contents($sourcePath);
        }

        if (is_file($publicPath)) {
            return (string) file_get_contents($publicPath);
        }

        return ":root {\n}\n";
    }
}

if (!function_exists('customCssEditorParseVariables')) {
    function customCssEditorParseVariables($css)
    {
        $variables = [];
        if (!is_string($css) || $css === '') {
            return $variables;
        }

        if (preg_match_all('/--([a-z0-9\-]+)\s*:\s*([^;]+);/i', $css, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = strtolower(trim((string) $match[1]));
                $value = customCssEditorNormalizeValue((string) $match[2]);
                if ($name !== '' && $value !== '') {
                    $variables[$name] = $value;
                }
            }
        }

        return $variables;
    }
}

if (!function_exists('customCssEditorNormalizeValue')) {
    function customCssEditorNormalizeValue($value)
    {
        $normalized = trim((string) $value);
        $normalized = preg_replace('/[\r\n\t]+/', ' ', $normalized);
        $normalized = preg_replace('/\s{2,}/', ' ', (string) $normalized);
        $normalized = str_replace(['{', '}'], '', (string) $normalized);
        $semiPos = strpos((string) $normalized, ';');
        if ($semiPos !== false) {
            $normalized = substr((string) $normalized, 0, $semiPos);
        }
        return trim((string) $normalized);
    }
}

if (!function_exists('customCssEditorParseRgbColor')) {
    function customCssEditorParseRgbColor($value)
    {
        $candidate = trim((string) $value);
        if ($candidate === '') {
            return null;
        }

        $pattern = '/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$|^(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})$/i';
        if (!preg_match($pattern, $candidate, $matches)) {
            return null;
        }

        $parts = [];
        if ($matches[1] !== '' || $matches[2] !== '' || $matches[3] !== '') {
            $parts = [$matches[1], $matches[2], $matches[3]];
        } else {
            $parts = [$matches[4], $matches[5], $matches[6]];
        }

        $result = [];
        foreach ($parts as $part) {
            $int = (int) $part;
            if ($int < 0 || $int > 255) {
                return null;
            }
            $result[] = $int;
        }

        return $result;
    }
}

if (!function_exists('customCssEditorNormalizeHexColor')) {
    function customCssEditorNormalizeHexColor($value)
    {
        $candidate = strtolower(trim((string) $value));
        if (!customCssEditorIsHexColor($candidate)) {
            return null;
        }

        if (strlen($candidate) === 4) {
            return sprintf(
                '#%1$s%1$s%2$s%2$s%3$s%3$s',
                $candidate[1],
                $candidate[2],
                $candidate[3]
            );
        }

        return $candidate;
    }
}

if (!function_exists('customCssEditorNormalizeColorValue')) {
    function customCssEditorNormalizeColorValue($value)
    {
        $hex = customCssEditorNormalizeHexColor($value);
        if ($hex !== null) {
            return $hex;
        }

        $rgb = customCssEditorParseRgbColor($value);
        if ($rgb !== null) {
            return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
        }

        return null;
    }
}

if (!function_exists('customCssEditorColorToRgbString')) {
    function customCssEditorColorToRgbString($value)
    {
        $rgb = customCssEditorParseRgbColor($value);
        if ($rgb !== null) {
            return $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2];
        }

        $hex = customCssEditorNormalizeHexColor($value);
        if ($hex === null) {
            return null;
        }

        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));
        return $r . ', ' . $g . ', ' . $b;
    }
}

if (!function_exists('customCssEditorIsPxValue')) {
    function customCssEditorIsPxValue($value)
    {
        $candidate = strtolower(trim((string) $value));
        return $candidate === '0' || preg_match('/^-?(?:\d+|\d*\.\d+)px$/', $candidate) === 1;
    }
}

if (!function_exists('customCssEditorSplitOnOuterCommas')) {
    function customCssEditorSplitOnOuterCommas($value)
    {
        $layers = [];
        $buffer = '';
        $depth = 0;
        $chars = str_split((string) $value);

        foreach ($chars as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')' && $depth > 0) {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $layers[] = $trimmed;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $layers[] = $trimmed;
        }

        return $layers;
    }
}

if (!function_exists('customCssEditorIsShadowColor')) {
    function customCssEditorIsShadowColor($value)
    {
        $candidate = strtolower(trim((string) $value));
        if ($candidate === '') {
            return true;
        }

        if ($candidate === 'transparent' || $candidate === 'currentcolor') {
            return true;
        }

        if (customCssEditorIsHexColor($candidate)) {
            return true;
        }

        if (preg_match('/^var\(\s*--[a-z0-9\-]+\s*\)$/', $candidate) === 1) {
            return true;
        }

        if (customCssEditorParseRgbColor($candidate) !== null) {
            return true;
        }

        if (preg_match('/^rgba\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*(0|0?\.\d+|1(?:\.0+)?)\s*\)$/', $candidate) === 1) {
            return true;
        }

        return false;
    }
}

if (!function_exists('customCssEditorNormalizeShadowValue')) {
    function customCssEditorNormalizeShadowValue($value)
    {
        $normalized = customCssEditorNormalizeValue($value);
        if ($normalized === '') {
            return null;
        }

        if (strtolower($normalized) === 'none') {
            return 'none';
        }

        $layers = customCssEditorSplitOnOuterCommas($normalized);
        if (empty($layers)) {
            return null;
        }

        $normalizedLayers = [];
        foreach ($layers as $layer) {
            $parts = preg_split('/\s+/', trim($layer));
            if (!is_array($parts) || empty($parts)) {
                return null;
            }

            $cursor = 0;
            if (strtolower((string) ($parts[0] ?? '')) === 'inset') {
                $cursor = 1;
            }

            $lengthCount = 0;
            $lengthTokens = [];
            while (isset($parts[$cursor]) && customCssEditorIsPxValue((string) $parts[$cursor]) && $lengthCount < 4) {
                $lengthTokens[] = (string) $parts[$cursor];
                $lengthCount++;
                $cursor++;
            }

            if ($lengthCount < 2) {
                return null;
            }

            $colorTokens = array_slice($parts, $cursor);
            $colorValue = trim(implode(' ', $colorTokens));
            if (!customCssEditorIsShadowColor($colorValue)) {
                return null;
            }

            $layerParts = [];
            if (strtolower((string) ($parts[0] ?? '')) === 'inset') {
                $layerParts[] = 'inset';
            }
            $layerParts = array_merge($layerParts, $lengthTokens);
            if ($colorValue !== '') {
                $layerParts[] = $colorValue;
            }

            $normalizedLayers[] = implode(' ', $layerParts);
        }

        return implode(', ', $normalizedLayers);
    }
}

if (!function_exists('customCssEditorValidateTokenValue')) {
    function customCssEditorValidateTokenValue($entry, $submittedValue, &$errorMessage = '')
    {
        $name = strtolower((string) ($entry['name'] ?? ''));
        $type = strtolower((string) ($entry['type'] ?? 'text'));
        $value = customCssEditorNormalizeValue($submittedValue);
        $errorMessage = '';

        if ($type === 'color') {
            $normalized = customCssEditorNormalizeColorValue($value);
            if ($normalized === null) {
                $errorMessage = customCssEditorUiText(
                    'admin.css_validation_color',
                    'Use HEX (#00cd87) or RGB (0, 205, 135).'
                );
                return null;
            }

            return $normalized;
        }

        if ($type === 'rgb') {
            $rgb = customCssEditorColorToRgbString($value);
            if ($rgb === null) {
                $errorMessage = customCssEditorUiText(
                    'admin.css_validation_rgb',
                    'Use RGB format like 0, 205, 135.'
                );
                return null;
            }

            return $rgb;
        }

        if ($type === 'shadow') {
            $shadow = customCssEditorNormalizeShadowValue($value);
            if ($shadow === null) {
                $errorMessage = customCssEditorUiText(
                    'admin.css_validation_shadow',
                    'Use shadow values with px units or none.'
                );
                return null;
            }

            return $shadow;
        }

        if (strpos($name, 'radius-') === 0) {
            if (preg_match('/^(?:0|0px|\d+(?:\.\d+)?px)$/i', $value) !== 1) {
                $errorMessage = customCssEditorUiText(
                    'admin.css_validation_radius',
                    'Radius must be 0 or a px value like 10px.'
                );
                return null;
            }

            return strtolower($value);
        }

        if ($name === 'motion-duration-base') {
            if (preg_match('/^\d+(?:\.\d+)?s$/i', $value) !== 1) {
                $errorMessage = customCssEditorUiText(
                    'admin.css_validation_motion',
                    'Duration must be in seconds, for example 0.3s.'
                );
                return null;
            }

            return strtolower($value);
        }

        return $value;
    }
}

if (!function_exists('customCssEditorApplyDerivedValues')) {
    function customCssEditorApplyDerivedValues($entries)
    {
        $valueMap = [];
        foreach ($entries as $entry) {
            $name = strtolower((string) ($entry['name'] ?? ''));
            if ($name !== '') {
                $valueMap[$name] = (string) ($entry['value'] ?? '');
            }
        }

        foreach ($entries as $index => $entry) {
            $type = strtolower((string) ($entry['type'] ?? 'text'));
            $derivedFrom = strtolower((string) ($entry['derivedFrom'] ?? ''));
            if ($type !== 'rgb' || $derivedFrom === '') {
                continue;
            }

            $sourceValue = (string) ($valueMap[$derivedFrom] ?? '');
            $derivedRgb = customCssEditorColorToRgbString($sourceValue);
            if ($derivedRgb === null) {
                continue;
            }

            $entries[$index]['value'] = $derivedRgb;
            $entryName = strtolower((string) ($entry['name'] ?? ''));
            if ($entryName !== '') {
                $valueMap[$entryName] = $derivedRgb;
            }
        }

        return $entries;
    }
}

if (!function_exists('customCssEditorBuildEntries')) {
    function customCssEditorBuildEntries($schema, $variables, $defaults)
    {
        $entries = [];
        foreach ($schema as $token) {
            $name = (string) ($token['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $legacyName = strtolower((string) ($token['legacy'] ?? ''));
            $normalizedName = strtolower($name);
            $type = (string) ($token['type'] ?? 'text');
            $editable = array_key_exists('editable', $token) ? (bool) $token['editable'] : true;
            $derivedFrom = strtolower((string) ($token['derivedFrom'] ?? ''));

            $value = '';
            if (array_key_exists($normalizedName, $variables)) {
                $value = $variables[$normalizedName];
            } elseif ($legacyName !== '' && array_key_exists($legacyName, $variables)) {
                $value = $variables[$legacyName];
            } elseif (array_key_exists($name, $defaults)) {
                $value = (string) $defaults[$name];
            }

            $normalizedValue = customCssEditorNormalizeValue($value);
            if (strtolower($type) === 'color') {
                $normalizedColor = customCssEditorNormalizeColorValue($normalizedValue);
                if ($normalizedColor !== null) {
                    $normalizedValue = $normalizedColor;
                }
            }

            $entries[] = [
                'name' => $name,
                'legacy' => $legacyName,
                'category' => customCssEditorResolveCategory($name),
                'label' => (string) ($token['label'] ?? $name),
                'type' => $type,
                'editable' => $editable,
                'derivedFrom' => $derivedFrom,
                'value' => $normalizedValue,
            ];
        }

        return customCssEditorApplyDerivedValues($entries);
    }
}

if (!function_exists('customCssEditorApplyPostEntries')) {
    function customCssEditorApplyPostEntries($entries, $postData, &$errors = [])
    {
        $errors = [];

        foreach ($entries as $index => $entry) {
            $editable = !array_key_exists('editable', $entry) || (bool) $entry['editable'];
            if (!$editable) {
                continue;
            }

            $name = (string) ($entry['name'] ?? '');
            if ($name === '' || !array_key_exists($name, $postData)) {
                continue;
            }

            $submittedValue = customCssEditorNormalizeValue((string) $postData[$name]);
            if ($submittedValue === '') {
                continue;
            }

            $validationError = '';
            $validatedValue = customCssEditorValidateTokenValue($entry, $submittedValue, $validationError);
            if ($validatedValue === null) {
                $errors[$name] = $validationError !== ''
                    ? $validationError
                    : customCssEditorUiText('admin.css_validation_generic', 'Invalid value.');
                continue;
            }

            $entries[$index]['value'] = $validatedValue;
        }

        return customCssEditorApplyDerivedValues($entries);
    }
}

if (!function_exists('customCssEditorRenderCss')) {
    function customCssEditorRenderCss($entries)
    {
        $entries = customCssEditorApplyDerivedValues($entries);
        $lines = [":root {"];

        foreach ($entries as $entry) {
            $name = (string) ($entry['name'] ?? '');
            $type = strtolower((string) ($entry['type'] ?? 'text'));
            $value = customCssEditorNormalizeValue((string) ($entry['value'] ?? ''));
            if ($name === '' || $value === '') {
                continue;
            }

            if ($type === 'color') {
                $normalizedColor = customCssEditorNormalizeColorValue($value);
                if ($normalizedColor !== null) {
                    $value = $normalizedColor;
                }
            } elseif ($type === 'shadow') {
                $normalizedShadow = customCssEditorNormalizeShadowValue($value);
                if ($normalizedShadow !== null) {
                    $value = $normalizedShadow;
                }
            }

            $lines[] = "    --{$name}: {$value};";
        }

        $lines[] = '';
        $lines[] = '    /* Legacy aliases (compatibility layer) */';

        $legacySeen = [];
        foreach ($entries as $entry) {
            $legacyName = strtolower((string) ($entry['legacy'] ?? ''));
            $name = (string) ($entry['name'] ?? '');
            if ($legacyName === '' || $name === '' || isset($legacySeen[$legacyName])) {
                continue;
            }

            $legacySeen[$legacyName] = true;
            $lines[] = "    --{$legacyName}: var(--{$name});";
        }

        $lines[] = '    --color: var(--text-primary) !important;';
        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }
}

if (!function_exists('customCssEditorWriteFiles')) {
    function customCssEditorWriteFiles($paths, $content)
    {
        $written = false;
        $seen = [];

        foreach ($paths as $path) {
            $path = (string) $path;
            if ($path === '') {
                continue;
            }

            $normalizedPath = str_replace('\\', '/', $path);
            if (isset($seen[$normalizedPath])) {
                continue;
            }

            $seen[$normalizedPath] = true;
            $directory = dirname($path);
            if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
                return false;
            }

            if (@file_put_contents($path, $content) === false) {
                return false;
            }

            $written = true;
        }

        return $written;
    }
}

if (!function_exists('customCssEditorIsHexColor')) {
    function customCssEditorIsHexColor($value)
    {
        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', trim((string) $value)) === 1;
    }
}

if (!function_exists('customCssEditorIsJsonRequest')) {
    function customCssEditorIsJsonRequest()
    {
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $acceptHeader = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

        return $requestedWith === 'xmlhttprequest' || str_contains($acceptHeader, 'application/json');
    }
}

if (!function_exists('customCssEditorUiText')) {
    function customCssEditorUiText($path, $fallback)
    {
        if (function_exists('uiText')) {
            return (string) uiText($path, $fallback);
        }

        return (string) $fallback;
    }
}

if (!function_exists('customCssEditorRespondJson')) {
    function customCssEditorRespondJson($statusCode, $payload)
    {
        http_response_code((int) $statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/* ───────────────────────────────────────────────────────────────
 *  Auto-Theme Color Engine
 *  Generates all 37+ CSS tokens from 4 base colors.
 * ─────────────────────────────────────────────────────────────── */

if (!function_exists('colorEngineHexToRgb')) {
    function colorEngineHexToRgb($hex)
    {
        $hex = ltrim(trim((string) $hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return null;
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}

if (!function_exists('colorEngineRgbToHex')) {
    function colorEngineRgbToHex($r, $g, $b)
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, (int) round($r))), max(0, min(255, (int) round($g))), max(0, min(255, (int) round($b))));
    }
}

if (!function_exists('colorEngineRgbToHsl')) {
    function colorEngineRgbToHsl($r, $g, $b)
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0, 0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        if ($max === $r) {
            $h = (($g - $b) / $d) + ($g < $b ? 6 : 0);
        } elseif ($max === $g) {
            $h = (($b - $r) / $d) + 2;
        } else {
            $h = (($r - $g) / $d) + 4;
        }

        return [$h / 6, $s, $l];
    }
}

if (!function_exists('colorEngineHslToRgb')) {
    function colorEngineHslToRgb($h, $s, $l)
    {
        if ($s == 0) {
            $v = (int) round($l * 255);
            return [$v, $v, $v];
        }

        $hue2rgb = function ($p, $q, $t) {
            if ($t < 0) $t += 1;
            if ($t > 1) $t -= 1;
            if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
            if ($t < 1 / 2) return $q;
            if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
            return $p;
        };

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        return [
            (int) round($hue2rgb($p, $q, $h + 1 / 3) * 255),
            (int) round($hue2rgb($p, $q, $h) * 255),
            (int) round($hue2rgb($p, $q, $h - 1 / 3) * 255),
        ];
    }
}

if (!function_exists('colorEngineMix')) {
    /** Mix two hex colors. $ratio 0 = all $hex1, 1 = all $hex2. */
    function colorEngineMix($hex1, $hex2, $ratio)
    {
        $c1 = colorEngineHexToRgb($hex1);
        $c2 = colorEngineHexToRgb($hex2);
        if (!$c1 || !$c2) return $hex1;
        $ratio = max(0, min(1, (float) $ratio));
        return colorEngineRgbToHex(
            $c1[0] + ($c2[0] - $c1[0]) * $ratio,
            $c1[1] + ($c2[1] - $c1[1]) * $ratio,
            $c1[2] + ($c2[2] - $c1[2]) * $ratio
        );
    }
}

if (!function_exists('colorEngineAdjustLightness')) {
    /** Shift lightness by $delta (-1..1). Negative = darker. */
    function colorEngineAdjustLightness($hex, $delta)
    {
        $rgb = colorEngineHexToRgb($hex);
        if (!$rgb) return $hex;
        [$h, $s, $l] = colorEngineRgbToHsl($rgb[0], $rgb[1], $rgb[2]);
        $l = max(0, min(1, $l + (float) $delta));
        [$r, $g, $b] = colorEngineHslToRgb($h, $s, $l);
        return colorEngineRgbToHex($r, $g, $b);
    }
}

if (!function_exists('colorEngineRelativeLuminance')) {
    function colorEngineRelativeLuminance($hex)
    {
        $rgb = colorEngineHexToRgb($hex);
        if (!$rgb) return 0;
        $channels = [];
        foreach ($rgb as $c) {
            $c = $c / 255;
            $channels[] = $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}

if (!function_exists('colorEngineContrastRatio')) {
    /** WCAG 2.1 contrast ratio between two hex colors. */
    function colorEngineContrastRatio($hex1, $hex2)
    {
        $l1 = colorEngineRelativeLuminance($hex1);
        $l2 = colorEngineRelativeLuminance($hex2);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);
        return ($lighter + 0.05) / ($darker + 0.05);
    }
}

if (!function_exists('colorEngineAutoFixForeground')) {
    /**
     * If $fg on $bg does not meet $minRatio, progressively adjust $fg
     * (darken or lighten depending on which direction yields better contrast)
     * until it passes or max iterations reached.
     */
    function colorEngineAutoFixForeground($fg, $bg, $minRatio = 4.5)
    {
        if (colorEngineContrastRatio($fg, $bg) >= $minRatio) {
            return $fg;
        }

        $bgLum = colorEngineRelativeLuminance($bg);
        // Decide direction: if bg is dark, lighten fg; if bg is light, darken fg
        $direction = $bgLum > 0.5 ? -0.03 : 0.03;

        $adjusted = $fg;
        for ($i = 0; $i < 50; $i++) {
            $adjusted = colorEngineAdjustLightness($adjusted, $direction);
            if (colorEngineContrastRatio($adjusted, $bg) >= $minRatio) {
                return $adjusted;
            }
        }

        // Fallback: use black or white
        return $bgLum > 0.5 ? '#000000' : '#ffffff';
    }
}

if (!function_exists('colorEngineHexToRgbString')) {
    function colorEngineHexToRgbString($hex)
    {
        $rgb = colorEngineHexToRgb($hex);
        if (!$rgb) return '0, 0, 0';
        return $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2];
    }
}

if (!function_exists('generateThemeFromBaseColors')) {
    /**
     * Generate all 37+ CSS tokens from 4 base colors.
     *
     * @param string $accent  Primary brand color hex (e.g. #00cd87)
     * @param string $canvas  Main background hex (e.g. #ffffff light, #20252c dark)
     * @param string $text    Primary text color hex (e.g. #222831 light, #f1ede6 dark)
     * @param string $border  Base border color hex (e.g. #c8d2dc light, #4a525e dark)
     * @param bool   $isDark  Whether this is a dark theme
     * @return array Associative array of token-name => value
     */
    function generateThemeFromBaseColors($accent, $canvas, $text, $border, $isDark = false)
    {
        $tokens = [];

        // ── Color (accent family) ──
        $tokens['color-accent'] = $accent;
        $tokens['color-accent-soft'] = colorEngineMix($canvas, $accent, 0.30);
        $tokens['color-accent-strong'] = colorEngineAdjustLightness($accent, -0.12);
        $tokens['color-accent-rgb'] = colorEngineHexToRgbString($accent);

        // ── Surface ──
        $tokens['surface-canvas'] = $canvas;
        $tokens['surface-base'] = colorEngineMix($canvas, $text, 0.02);
        $tokens['surface-muted'] = $isDark ? colorEngineAdjustLightness($canvas, -0.10) : colorEngineMix($canvas, $text, 0.10);
        $tokens['surface-elevated'] = colorEngineMix($canvas, $border, $isDark ? 0.18 : 0.14);
        $tokens['surface-hover'] = colorEngineMix($canvas, $text, 0.04);
        $tokens['surface-text-highlight'] = colorEngineMix(colorEngineMix($canvas, $text, 0.06), $accent, 0.08);
        $tokens['surface-tooltip'] = $isDark ? colorEngineMix($canvas, $text, 0.06) : $canvas;
        $tokens['surface-version'] = colorEngineMix(colorEngineMix($canvas, $border, 0.12), $accent, 0.05);

        // ── Text ──
        $tokens['text-primary'] = $text;
        $tokens['text-secondary'] = colorEngineMix($text, $canvas, 0.20);
        $tokens['text-strong'] = colorEngineMix($text, $canvas, 0.20);
        $tokens['text-inverse'] = $isDark ? '#ffffff' : '#000000';
        $tokens['text-primary-rgb'] = colorEngineHexToRgbString($text);

        // ── Border ──
        $tokens['border-default'] = $border;
        $tokens['border-soft'] = colorEngineMix($border, $canvas, 0.45);
        $tokens['border-strong'] = colorEngineMix($border, $text, 0.30);

        // ── Shadow ──
        if ($isDark) {
            $tokens['shadow-soft'] = '0 1px 2px rgba(0, 0, 0, 0.22)';
            $tokens['shadow-medium'] = '0 8px 20px rgba(0, 0, 0, 0.35)';
            $tokens['shadow-strong'] = '0 18px 52px rgba(0, 0, 0, 0.46)';
        } else {
            $tokens['shadow-soft'] = '0 1px 2px rgba(0, 0, 0, 0.06)';
            $tokens['shadow-medium'] = '0 8px 20px rgba(0, 0, 0, 0.18)';
            $tokens['shadow-strong'] = '0 18px 52px rgba(0, 0, 0, 0.28)';
        }

        // ── Radius ──
        $tokens['radius-sm'] = '5px';
        $tokens['radius-md'] = '10px';
        $tokens['radius-lg'] = '15px';

        // ── Motion ──
        $tokens['motion-duration-base'] = '0.3s';

        // ── Color White ──
        $tokens['color-white'] = $isDark ? colorEngineMix($text, $canvas, 0.05) : '#ffffff';

        // ── Actions ──
        $tokens['action-primary-bg'] = colorEngineMix($canvas, $border, 0.22);
        $tokens['action-primary-fg'] = colorEngineAutoFixForeground($text, $tokens['action-primary-bg'], 4.5);
        $tokens['action-primary-hover-bg'] = colorEngineAdjustLightness($tokens['action-primary-bg'], $isDark ? 0.06 : -0.06);

        $dangerBase = $isDark ? '#a94646' : '#dc5b5b';
        $tokens['action-danger-bg'] = $dangerBase;
        $tokens['action-danger-fg'] = colorEngineAutoFixForeground('#ffffff', $dangerBase, 4.5);
        $tokens['action-danger-hover-bg'] = colorEngineAdjustLightness($dangerBase, $isDark ? 0.08 : -0.08);
        $tokens['action-danger-rgb'] = colorEngineHexToRgbString($dangerBase);

        $tokens['action-secondary-bg'] = colorEngineMix($canvas, $border, 0.16);
        $tokens['action-secondary-fg'] = colorEngineAutoFixForeground($text, $tokens['action-secondary-bg'], 4.5);
        $tokens['action-secondary-hover-bg'] = colorEngineAdjustLightness($tokens['action-secondary-bg'], $isDark ? 0.06 : -0.06);

        return $tokens;
    }
}

if (!function_exists('colorEngineCheckContrast')) {
    /**
     * Check all critical contrast pairs and return results.
     * @param array $tokens Associative array of generated tokens
     * @return array Array of { pair: [fg, bg], labels: [fgLabel, bgLabel], ratio: float, passes: bool, level: string }
     */
    function colorEngineCheckContrast($tokens)
    {
        $pairs = [
            [['text-primary', 'Text Primary'], ['surface-canvas', 'Surface Canvas'], 4.5, 'AA'],
            [['text-secondary', 'Text Secondary'], ['surface-base', 'Surface Base'], 4.5, 'AA'],
            [['text-primary', 'Text Primary'], ['surface-base', 'Surface Base'], 4.5, 'AA'],
            [['color-accent', 'Accent'], ['surface-canvas', 'Surface Canvas'], 3.0, 'AA-UI'],
            [['action-primary-fg', 'Button Text'], ['action-primary-bg', 'Button BG'], 4.5, 'AA'],
            [['action-danger-fg', 'Danger Text'], ['action-danger-bg', 'Danger BG'], 4.5, 'AA'],
            [['action-secondary-fg', 'Secondary Text'], ['action-secondary-bg', 'Secondary BG'], 4.5, 'AA'],
        ];

        $results = [];
        foreach ($pairs as [$fg, $bg, $minRatio, $level]) {
            $fgVal = $tokens[$fg[0]] ?? '#000000';
            $bgVal = $tokens[$bg[0]] ?? '#ffffff';
            $ratio = colorEngineContrastRatio($fgVal, $bgVal);
            $results[] = [
                'pair' => [$fg[0], $bg[0]],
                'labels' => [$fg[1], $bg[1]],
                'ratio' => round($ratio, 2),
                'passes' => $ratio >= $minRatio,
                'level' => $level,
                'required' => $minRatio,
            ];
        }
        return $results;
    }
}

if (!function_exists('colorEngineAutoFixAll')) {
    /**
     * Auto-fix all contrast issues in generated tokens.
     * Modifies foreground colors to meet WCAG requirements.
     * @param array $tokens Associative array of generated tokens
     * @return array Fixed tokens
     */
    function colorEngineAutoFixAll($tokens)
    {
        // Fix text on surfaces
        $tokens['text-primary'] = colorEngineAutoFixForeground($tokens['text-primary'], $tokens['surface-canvas'], 4.5);
        $tokens['text-primary-rgb'] = colorEngineHexToRgbString($tokens['text-primary']);
        $tokens['text-secondary'] = colorEngineAutoFixForeground($tokens['text-secondary'], $tokens['surface-base'] ?? $tokens['surface-canvas'], 4.5);
        $tokens['text-strong'] = colorEngineAutoFixForeground($tokens['text-strong'], $tokens['surface-base'] ?? $tokens['surface-canvas'], 4.5);

        // Fix action colors
        $tokens['action-primary-fg'] = colorEngineAutoFixForeground($tokens['action-primary-fg'], $tokens['action-primary-bg'], 4.5);
        $tokens['action-danger-fg'] = colorEngineAutoFixForeground($tokens['action-danger-fg'], $tokens['action-danger-bg'], 4.5);
        $tokens['action-secondary-fg'] = colorEngineAutoFixForeground($tokens['action-secondary-fg'], $tokens['action-secondary-bg'], 4.5);

        return $tokens;
    }
}

if (!function_exists('colorEnginePresets')) {
    /**
     * Return built-in color presets.
     * Each preset has 4 base colors for both light and dark themes.
     */
    function colorEnginePresets()
    {
        return [
            'default' => [
                'label' => 'Default',
                'light' => ['accent' => '#00cd87', 'canvas' => '#ffffff', 'text' => '#222831', 'border' => '#c8d2dc'],
                'dark' => ['accent' => '#00c887', 'canvas' => '#20252c', 'text' => '#f1ede6', 'border' => '#4a525e'],
            ],
            'ocean' => [
                'label' => 'Ocean',
                'light' => ['accent' => '#0891b2', 'canvas' => '#f8fafc', 'text' => '#1e293b', 'border' => '#cbd5e1'],
                'dark' => ['accent' => '#06b6d4', 'canvas' => '#0f172a', 'text' => '#e2e8f0', 'border' => '#334155'],
            ],
            'sunset' => [
                'label' => 'Sunset',
                'light' => ['accent' => '#ea580c', 'canvas' => '#fffbeb', 'text' => '#292524', 'border' => '#d6d3d1'],
                'dark' => ['accent' => '#f97316', 'canvas' => '#1c1917', 'text' => '#fafaf9', 'border' => '#44403c'],
            ],
            'forest' => [
                'label' => 'Forest',
                'light' => ['accent' => '#16a34a', 'canvas' => '#f0fdf4', 'text' => '#1a2e1a', 'border' => '#bbdfc8'],
                'dark' => ['accent' => '#22c55e', 'canvas' => '#14201a', 'text' => '#dcfce7', 'border' => '#2d4a37'],
            ],
            'midnight' => [
                'label' => 'Midnight',
                'light' => ['accent' => '#7c3aed', 'canvas' => '#faf5ff', 'text' => '#1e1b4b', 'border' => '#c4b5fd'],
                'dark' => ['accent' => '#a78bfa', 'canvas' => '#120f24', 'text' => '#e8e0f7', 'border' => '#3b3264'],
            ],
            'arctic' => [
                'label' => 'Arctic',
                'light' => ['accent' => '#0ea5e9', 'canvas' => '#f5fbff', 'text' => '#0f2940', 'border' => '#c6deee'],
                'dark' => ['accent' => '#38bdf8', 'canvas' => '#112131', 'text' => '#e6f4fb', 'border' => '#365067'],
            ],
            'desert' => [
                'label' => 'Desert',
                'light' => ['accent' => '#c17b2e', 'canvas' => '#fff9f2', 'text' => '#3c2c1d', 'border' => '#e0c9ad'],
                'dark' => ['accent' => '#d89a57', 'canvas' => '#2b2118', 'text' => '#f5e7d8', 'border' => '#6f553d'],
            ],
            'lavender' => [
                'label' => 'Lavender',
                'light' => ['accent' => '#8b5cf6', 'canvas' => '#faf7ff', 'text' => '#2d1f4d', 'border' => '#d8c8ff'],
                'dark' => ['accent' => '#a78bfa', 'canvas' => '#1b1630', 'text' => '#ece7ff', 'border' => '#51467f'],
            ],
            'ember' => [
                'label' => 'Ember',
                'light' => ['accent' => '#dc2626', 'canvas' => '#fff7f7', 'text' => '#3b1111', 'border' => '#efc2c2'],
                'dark' => ['accent' => '#f87171', 'canvas' => '#241515', 'text' => '#fde8e8', 'border' => '#6a3a3a'],
            ],
            'sakura' => [
                'label' => 'Sakura',
                'light' => ['accent' => '#ec4899', 'canvas' => '#fff8fc', 'text' => '#3d1d33', 'border' => '#f5c9df'],
                'dark' => ['accent' => '#f472b6', 'canvas' => '#261824', 'text' => '#fdebf5', 'border' => '#6d4462'],
            ],
            'mint' => [
                'label' => 'Mint',
                'light' => ['accent' => '#10b981', 'canvas' => '#f2fdf9', 'text' => '#173a30', 'border' => '#bbe5d6'],
                'dark' => ['accent' => '#34d399', 'canvas' => '#162923', 'text' => '#dcfcef', 'border' => '#3f6a5d'],
            ],
            'steel' => [
                'label' => 'Steel',
                'light' => ['accent' => '#64748b', 'canvas' => '#f8fafc', 'text' => '#1f2937', 'border' => '#cdd6e2'],
                'dark' => ['accent' => '#94a3b8', 'canvas' => '#1b2430', 'text' => '#e8edf4', 'border' => '#4b596e'],
            ],
            'amber' => [
                'label' => 'Amber',
                'light' => ['accent' => '#d97706', 'canvas' => '#fffbeb', 'text' => '#3f2a0b', 'border' => '#ecd8ae'],
                'dark' => ['accent' => '#f59e0b', 'canvas' => '#2a2315', 'text' => '#fdefd1', 'border' => '#6b5a36'],
            ],
            'cobalt' => [
                'label' => 'Cobalt',
                'light' => ['accent' => '#2563eb', 'canvas' => '#f5f8ff', 'text' => '#1e2b48', 'border' => '#c8d4ef'],
                'dark' => ['accent' => '#3b82f6', 'canvas' => '#151d30', 'text' => '#e5eeff', 'border' => '#3f5274'],
            ],
            'olive' => [
                'label' => 'Olive',
                'light' => ['accent' => '#6b8e23', 'canvas' => '#fafcf4', 'text' => '#28321a', 'border' => '#d7dfc1'],
                'dark' => ['accent' => '#84cc16', 'canvas' => '#1d2416', 'text' => '#edf7dc', 'border' => '#4c5e37'],
            ],
            'berry' => [
                'label' => 'Berry',
                'light' => ['accent' => '#be185d', 'canvas' => '#fff7fb', 'text' => '#3b1628', 'border' => '#efcade'],
                'dark' => ['accent' => '#e11d75', 'canvas' => '#26161d', 'text' => '#fde8f3', 'border' => '#6c3e52'],
            ],
            'charcoal' => [
                'label' => 'Charcoal',
                'light' => ['accent' => '#374151', 'canvas' => '#fafafa', 'text' => '#202226', 'border' => '#d5d7db'],
                'dark' => ['accent' => '#6b7280', 'canvas' => '#181a1f', 'text' => '#eceff4', 'border' => '#444b57'],
            ],
            'pearl' => [
                'label' => 'Pearl',
                'light' => ['accent' => '#0d9488', 'canvas' => '#fbffff', 'text' => '#1f3133', 'border' => '#c4dfe0'],
                'dark' => ['accent' => '#14b8a6', 'canvas' => '#152426', 'text' => '#e8f8f7', 'border' => '#3d6163'],
            ],
            'wave' => [
                'label' => 'Wave',
                'light' => ['accent' => '#0284c7', 'canvas' => '#f5fbff', 'text' => '#1a3240', 'border' => '#bfd8e7'],
                'dark' => ['accent' => '#0ea5e9', 'canvas' => '#132735', 'text' => '#e5f4fd', 'border' => '#396177'],
            ],
            'orchid' => [
                'label' => 'Orchid',
                'light' => ['accent' => '#a21caf', 'canvas' => '#fff8ff', 'text' => '#35183a', 'border' => '#e8c9ea'],
                'dark' => ['accent' => '#d946ef', 'canvas' => '#25152a', 'text' => '#f9e9fd', 'border' => '#6a3f73'],
            ],
            'copper' => [
                'label' => 'Copper',
                'light' => ['accent' => '#b45309', 'canvas' => '#fffaf6', 'text' => '#3a2819', 'border' => '#e7cdb9'],
                'dark' => ['accent' => '#d97706', 'canvas' => '#261d16', 'text' => '#fceedd', 'border' => '#6c503a'],
            ],
            'meadow' => [
                'label' => 'Meadow',
                'light' => ['accent' => '#22c55e', 'canvas' => '#f5fdf7', 'text' => '#1d3424', 'border' => '#c8e8d0'],
                'dark' => ['accent' => '#4ade80', 'canvas' => '#17251c', 'text' => '#e8fceF', 'border' => '#45624f'],
            ],
            'aurora' => [
                'label' => 'Aurora',
                'light' => ['accent' => '#14b8a6', 'canvas' => '#f5fffd', 'text' => '#173933', 'border' => '#bfe7df'],
                'dark' => ['accent' => '#2dd4bf', 'canvas' => '#132724', 'text' => '#e4fbf7', 'border' => '#3d6861'],
            ],
            'mono' => [
                'label' => 'Mono',
                'light' => ['accent' => '#52525b', 'canvas' => '#fcfcfd', 'text' => '#1f1f23', 'border' => '#d7d8dd'],
                'dark' => ['accent' => '#a1a1aa', 'canvas' => '#1a1b20', 'text' => '#ececf0', 'border' => '#4b4d57'],
            ],
        ];
    }
}

if (!function_exists('customSyncUpdateState')) {
    /**
     * Update sync state files after writing to both custom/custom/ and public/custom/.
     * Prevents secure.php from triggering an expensive full delete+re-copy.
     */
    function customSyncUpdateState()
    {
        $secureFolder = __DIR__ . '/../../custom/custom/';
        $localUpdatedPath = __DIR__ . '/../../public/localUpdated.php';
        $isUpdatedPath = __DIR__ . '/../../custom/isUpdated.php';
        $syncTime = time();

        $currentFileList = [];
        if (is_dir($secureFolder)) {
            $ri = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($secureFolder, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($ri as $file) {
                if ($file->isFile()) {
                    $currentFileList[] = str_replace($secureFolder, '/', str_replace('\\', '/', $file->getPathname()));
                }
            }
            sort($currentFileList);
        }

        $fileListExport = var_export($currentFileList, true);
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $localContent = "<?php\n\$lastModifiedTime = $syncTime;\n\$source = '$host';\n\$lastSyncCheck = $syncTime;\n\$localFileList = $fileListExport;\n";
        $updateContent = "<?php\n\$lastModifiedTime = $syncTime;\n\$storedFileList = $fileListExport;\n";

        @file_put_contents($localUpdatedPath, $localContent);
        @file_put_contents($isUpdatedPath, $updateContent);
    }
}
