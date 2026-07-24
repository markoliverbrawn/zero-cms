<?php
// src/Modules/Admin/Views/block_builder.php

use Zero\Core\App;

$blockField = $blockBuilderField ?? 'content';
$rawContent = $record->{$blockField} ?? '';
$blocks = json_decode($rawContent ?: '[]', true);
if (!is_array($blocks)) $blocks = [];

// Dynamically fetch allowed/filtered list of blocks from the model trait settings
$modelClass = App::getRegisteredModels()[$modelName] ?? null;
$allowedBlocks = $modelClass && method_exists($modelClass, 'getAllowedBlocks') ? $modelClass::getAllowedBlocks() : null;

$preRenderedTemplates = [];
foreach (App::getRegisteredBlocks() as $type => $config) {
    // If the model enforces a filtered whitelist of blocks, skip non-matching types
    if ($allowedBlocks !== null && !in_array($type, $allowedBlocks)) {
        continue;
    }

    $adminView = $config['admin_view'] ?? APPLICATION_ROOT . '/src/Modules/Admin/Views/blocks/' . $type . '.php';
    if (file_exists($adminView)) {
        // Buffering mocked context fields rendering
        $blockTitle = '';
        $blockContent = '';
        $block = ['items' => [], 'images' => [], 'image_path' => '', 'image_position' => 'right', 'duration' => 5000];
        $items = [];
        $duration = 5000;

        ob_start();
        include $adminView;
        $preRenderedTemplates[$type] = ob_get_clean();
    } else {
        $preRenderedTemplates[$type] = '';
    }
}

$preRenderedSettingsTemplates = [];
foreach (App::getRegisteredBlocks() as $type => $config) {
    if ($allowedBlocks !== null && !in_array($type, $allowedBlocks)) {
        continue;
    }
    $settingsPath = dirname(__FILE__) . '/blocks/admin/settings/' . $type . '.php';
    if (file_exists($settingsPath)) {
        $block = [];
        ob_start();
        include $settingsPath;
        $preRenderedSettingsTemplates[$type] = ob_get_clean();
    }
}
?>
<div class="block-builder-container">
    <div class="block-builder-header">
        <div>
            <label style="display: block; font-weight: bold; margin: 0; font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-color, #0f172a);"><?php echo htmlspecialchars($fieldConfig['label'] ?? 'Content', ENT_QUOTES, "UTF-8"); ?></label>
        </div>
        <button type="button" class="btn-toggle-preview-inline" id="btn-toggle-inserter">
            <span class="icon-svg icon-svg-14">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            </span>
            <span class="btn-text">Add Content Block</span>
        </button>
    </div>

    <!-- Hidden serialized JSON payload output -->
    <input type="hidden" name="<?php echo htmlspecialchars($blockField, ENT_QUOTES, 'UTF-8'); ?>" id="block-builder-output" value="<?php echo htmlspecialchars($rawContent, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Interactive Page Builder Blocks List Container -->
    <div id="blocks-container" class="blocks-container">
        <?php foreach ($blocks as $index => $block): ?>
            <?php
            $type = $block['type'] ?? 'text';
            // Skip rendering if block is not in the model's allowed list
            if ($allowedBlocks !== null && !in_array($type, $allowedBlocks)) {
                continue;
            }

            $blockConfig = App::getRegisteredBlocks()[$type] ?? [];
            $label = $blockConfig['label'] ?? $type;
            $icon = $blockConfig['icon'] ?? 'file';
            $adminView = $blockConfig['admin_view'] ?? APPLICATION_ROOT . '/src/Modules/Admin/Views/blocks/' . $type . '.php';
            
            // Get unique block ID or generate a random one
            $randomId = $block['block_id'] ?? 'blk_' . substr(md5(uniqid(rand(), true)), 0, 16);
            ?>
            <div class="block-item collapsed" data-type="<?php echo $type; ?>">
                <div class="block-header">
                    <div class="block-header-title-area">
                        <span class="icon-svg block-toggle-indicator icon-svg-14">
                            <?php echo App::svg('chevron-right'); ?>
                        </span>
                        <div class="block-header-icon-wrapper <?php echo $type; ?>-icon">
                            <?php echo App::svg($icon); ?>
                        </div>
                        <div class="block-header-text-container">
                            <h4 class="block-header-title"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></h4>
                            <span class="block-preview-excerpt"><?php echo htmlspecialchars(strip_tags($block['title'] ?? 'Section ' . ($index + 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <div class="block-actions">
                        <button type="button" class="btn-block-settings" title="Row Settings">
                            <span class="icon-svg icon-svg-14">
                                <?php echo App::svg('settings'); ?>
                            </span>
                        </button>
                        <button type="button" class="btn-move-up" title="Move Up">
                            <span class="icon-svg icon-svg-14">
                                <?php echo App::svg('arrow-up'); ?>
                            </span>
                        </button>
                        <button type="button" class="btn-move-down" title="Move Down">
                            <span class="icon-svg icon-svg-14">
                                <?php echo App::svg('arrow-down'); ?>
                            </span>
                        </button>
                        <button type="button" class="btn-delete" title="Delete">
                            <span class="icon-svg icon-svg-14">
                                <?php echo App::svg('trash-2'); ?>
                            </span>
                        </button>
                    </div>
                </div>
                <div class="block-body">
                    <div class="block-row-settings">
                        <?php
                        $settingsPath = dirname(__FILE__) . '/blocks/admin/settings/' . $type . '.php';
                        if (file_exists($settingsPath)):
                        ?>
                            <div class="block-settings-wrapper">
                                <h4>
                                    <span class="icon-svg icon-svg-14"><?php echo App::svg('settings'); ?></span>
                                    <span>Block Settings</span>
                                </h4>
                                <?php include $settingsPath; ?>
                                <small>Note: Block-specific layout settings may not affect the preview panel.</small>
                            </div>
                        <?php endif; ?>

                        <div class="row-settings-wrapper <?php echo file_exists($settingsPath) ? 'has-siblings' : ''; ?>">
                            <h4>
                                <span class="icon-svg icon-svg-14"><?php echo App::svg('settings'); ?></span>
                                <span>Row Settings</span>
                            </h4>
                            <div class="form-group">
                                <label>Add Space Before (Top Margin):</label>
                                <select class="block-space_before-select">
                                    <option value="none" <?php echo ($block['space_before'] ?? '') === 'none' ? 'selected' : ''; ?>>None (0px)</option>
                                    <option value="small" <?php echo ($block['space_before'] ?? '') === 'small' ? 'selected' : ''; ?>>Small (24px)</option>
                                    <option value="medium" <?php echo ($block['space_before'] ?? '') === 'medium' ? 'selected' : ''; ?>>Medium (48px)</option>
                                    <option value="large" <?php echo ($block['space_before'] ?? '') === 'large' ? 'selected' : ''; ?>>Large (80px)</option>
                                    <option value="xlarge" <?php echo ($block['space_before'] ?? '') === 'xlarge' ? 'selected' : ''; ?>>Extra Large (120px)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Add Space After (Bottom Margin):</label>
                                <select class="block-space_after-select">
                                    <option value="none" <?php echo ($block['space_after'] ?? '') === 'none' ? 'selected' : ''; ?>>None (0px)</option>
                                    <option value="small" <?php echo ($block['space_after'] ?? '') === 'small' ? 'selected' : ''; ?>>Small (24px)</option>
                                    <option value="medium" <?php echo ($block['space_after'] ?? '') === 'medium' ? 'selected' : ''; ?>>Medium (48px)</option>
                                    <option value="large" <?php echo ($block['space_after'] ?? '') === 'large' ? 'selected' : ''; ?>>Large (80px)</option>
                                    <option value="xlarge" <?php echo ($block['space_after'] ?? '') === 'xlarge' ? 'selected' : ''; ?>>Extra Large (120px)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Title Display:</label>
                                <select class="block-hide_title-select">
                                    <option value="0" <?php echo ($block['hide_title'] ?? '') === '0' ? 'selected' : ''; ?>>Show Title (H2)</option>
                                    <option value="1" <?php echo ($block['hide_title'] ?? '') === '1' ? 'selected' : ''; ?>>Hide Title</option>
                                </select>
                            </div>
                            <small>Note: Row spacing changes will not affect the preview panel.</small>
                        </div>
                    </div>
                    <div class="block-fields-col">
                        <button type="button" class="btn-toggle-preview-inline btn-show-preview-trigger" style="display: none; margin-bottom: 15px;">Show Live Preview</button>
                        <input type="hidden" class="block-id-input" value="<?php echo htmlspecialchars($randomId, ENT_QUOTES, 'UTF-8'); ?>">
                        
                        <?php
                        if (file_exists($adminView)) {
                            $blockTitle = $block['title'] ?? '';
                            $blockContent = $block['content'] ?? '';
                            $items = $block['items'] ?? [];
                            $duration = $block['duration'] ?? 5000;
                            include $adminView;
                        }
                        ?>
                    </div>
                    <div class="block-live-preview-col">
                        <div class="block-live-preview-header">
                            <span>Live Preview</span>
                            <div class="block-preview-viewport-controls">
                                <button type="button" class="btn-viewport active" data-viewport="desktop" title="Desktop Viewport">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                </button>
                                <button type="button" class="btn-viewport" data-viewport="tablet" title="Tablet Viewport">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                                </button>
                                <button type="button" class="btn-viewport" data-viewport="mobile" title="Mobile Viewport">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                                </button>
                            </div>
                            <button type="button" class="btn-toggle-preview-inline btn-hide-preview-trigger">Hide Preview</button>
                        </div>
                        <div class="block-live-preview-iframe-wrapper">
                            <iframe class="block-live-preview-iframe" src="about:blank"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Sliding Inserter Panel Drawer (Populated dynamically from core App registered blocks) -->
    <div id="inserter-panel" class="inserter-panel">
        <h4 class="inserter-panel-title">Select Content Block Type</h4>
        <div class="inserter-panel-grid">
            <?php foreach (App::getRegisteredBlocks() as $type => $config): ?>
                <?php
                if ($allowedBlocks !== null && !in_array($type, $allowedBlocks)) {
                    continue;
                }
                ?>
                <div class="block-select-card" data-type="<?php echo $type; ?>">
                    <div class="icon-wrapper-svg">
                        <?php echo App::svg($config['icon']); ?>
                    </div>
                    <div class="block-select-card-meta">
                        <h4 class="block-select-card-title"><?php echo htmlspecialchars($config['label'], ENT_QUOTES, 'UTF-8'); ?></h4>
                        <p class="block-select-card-desc"><?php echo htmlspecialchars($config['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script nonce="<?php echo \Zero\Core\App::getNonce(); ?>">
window.REGISTERED_BLOCK_TEMPLATES = <?php echo json_encode($preRenderedTemplates); ?>;
window.REGISTERED_BLOCK_SETTINGS_TEMPLATES = <?php echo json_encode($preRenderedSettingsTemplates); ?>;
window.SVG_CHEVRON_RIGHT = <?php echo json_encode(App::svg('chevron-right')); ?>;
window.SVG_ARROW_UP = <?php echo json_encode(App::svg('arrow-up')); ?>;
window.SVG_ARROW_DOWN = <?php echo json_encode(App::svg('arrow-down')); ?>;
window.SVG_TRASH_2 = <?php echo json_encode(App::svg('trash-2')); ?>;
window.SVG_SETTINGS = <?php echo json_encode(App::svg('settings')); ?>;
</script>
<script src="/assets/js/admin/block_builder.js"></script>
