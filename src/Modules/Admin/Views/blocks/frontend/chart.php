<?php
// src/Modules/Admin/Views/blocks/frontend/chart.php

$title = $block['title'] ?? '';
$items = $block['items'] ?? [];

if (empty($items)) {
    return;
}

// 1. Resolve maximum value to dynamically scale bar widths and grid lines
$maxVal = 0.0001; // Avoid divide by zero
foreach ($items as $item) {
    $val = floatval($item['value'] ?? 0);
    if ($val > $maxVal) {
        $maxVal = $val;
    }
}

$barContainerWidth = 350;
$rowHeight = 50;
$paddingTop = 30; // Extra room for the grid line headers at the top of the chart!
$svgHeight = count($items) * $rowHeight + $paddingTop + 10;
?>
<div class="block-chart-container">
    <?php if (!empty($title)): ?>
        <h3 class="block-chart-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
    <?php endif; ?>

    <!-- Beautiful Dashboard Window Header Toolbar -->
    <div class="block-chart-header">
        <div class="header-mac-dots">
            <span class="dot red"></span>
            <span class="dot yellow"></span>
            <span class="dot green"></span>
        </div>
        <div class="header-status">
            <span class="status-pulse"></span>
            <span class="status-label">LIVE METRICS</span>
        </div>
    </div>

    <div class="block-chart-svg-wrapper">
        <svg viewBox="0 0 600 <?php echo $svgHeight; ?>" width="100%" height="100%" class="block-chart-svg">
            <defs>
                <!-- Glowing neon-blue gradient for Zero CMS -->
                <linearGradient id="zero-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#00f0ff" />
                    <stop offset="100%" stop-color="#3b82f6" />
                </linearGradient>
                <!-- Sleek neutral slate gradient for other CMS platforms -->
                <linearGradient id="neutral-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#475569" />
                    <stop offset="100%" stop-color="#1e293b" />
                </linearGradient>
            </defs>

            <!-- 2. Draw vertical background grid lines (25%, 50%, 75%, 100%) -->
            <?php
            $gridLines = [0.25, 0.50, 0.75, 1.00];
            foreach ($gridLines as $ratio):
                $lineX = 180 + $ratio * $barContainerWidth;
                $lineVal = round($ratio * $maxVal, 1);
            ?>
                <line x1="<?php echo $lineX; ?>" y1="20" x2="<?php echo $lineX; ?>" y2="<?php echo $svgHeight - 10; ?>" stroke="rgba(255, 255, 255, 0.05)" stroke-dasharray="3 3" />
                <text x="<?php echo $lineX; ?>" y="12" class="chart-grid-text" fill="var(--text-muted, #94a3b8)" text-anchor="middle"><?php echo $lineVal; ?></text>
            <?php endforeach; ?>

            <!-- 3. Draw rows -->
            <?php foreach ($items as $index => $item): 
                $label = $item['label'] ?? '';
                $val = floatval($item['value'] ?? 0);
                
                $rowY = $index * $rowHeight + $paddingTop;
                $barWidth = ($val / $maxVal) * $barContainerWidth;
                
                // Color highlight Zero CMS bar with primary brand gradient, others with slate gradient
                $isZeroCms = (strpos(strtolower($label), 'zero') !== false);
                $barFill = $isZeroCms ? 'url(#zero-gradient)' : 'url(#neutral-gradient)';
                $textColor = $isZeroCms ? 'var(--primary-color, #00f0ff)' : 'var(--text-color, #d4e4fa)';
                ?>
                <!-- Group representing a single bar row -->
                <g class="chart-row-group">
                    <!-- Label -->
                    <text x="10" y="<?php echo $rowY + 12; ?>" class="chart-label" fill="var(--text-color, #d4e4fa)"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></text>
                    
                    <!-- Background Bar Track -->
                    <rect x="180" y="<?php echo $rowY; ?>" width="<?php echo $barContainerWidth; ?>" height="20" rx="3" fill="rgba(255, 255, 255, 0.01)" stroke="rgba(255, 255, 255, 0.03)" stroke-width="1" />
                    
                    <!-- Animated Filled Value Bar -->
                    <rect x="180" y="<?php echo $rowY; ?>" width="<?php echo $barWidth; ?>" height="20" rx="3" fill="<?php echo $barFill; ?>" class="chart-fill-bar" />
                    
                    <!-- Numeric Value Display -->
                    <text x="<?php echo 180 + $barWidth + 10; ?>" y="<?php echo $rowY + 12; ?>" class="chart-value-text" fill="<?php echo $textColor; ?>"><?php echo htmlspecialchars($item['value'], ENT_QUOTES, 'UTF-8'); ?></text>
                </g>
            <?php endforeach; ?>
        </svg>
    </div>
</div>
