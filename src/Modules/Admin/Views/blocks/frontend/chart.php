<?php
use Zero\Support\Str;
// src/Modules/Admin/Views/blocks/frontend/chart.php

$title = $block['title'] ?? '';
$items = $block['items'] ?? [];
$chartLayout = $block['chart_layout'] ?? 'horizontal';

if (empty($items)) {
    return;
}

// Resolve maximum value to dynamically scale bar dimensions and grid lines
$maxVal = 0.0001;
foreach ($items as $item) {
    $val = floatval($item['value'] ?? 0);
    if ($val > $maxVal) {
        $maxVal = $val;
    }
}
?>
<div class="block-chart-container">
    <?php if (!empty($title)): ?>
        <h3 class="block-chart-title"><?php echo Str::escape($title); ?></h3>
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
        <?php if ($chartLayout === 'vertical'): 
            $svgHeight = 360;
            $plotHeight = 240;
            $baselineY = 290;
            $paddingLeft = 80;
            $plotWidth = 480;
            ?>
            <svg viewBox="0 0 600 <?php echo $svgHeight; ?>" width="100%" height="100%" class="block-chart-svg">
                <defs>
                    <linearGradient id="zero-gradient-vertical" x1="0%" y1="100%" x2="0%" y2="0%">
                        <stop offset="0%" stop-color="#3b82f6" />
                        <stop offset="100%" stop-color="#00f0ff" />
                    </linearGradient>
                    <linearGradient id="neutral-gradient-vertical" x1="0%" y1="100%" x2="0%" y2="0%">
                        <stop offset="0%" stop-color="#1e293b" />
                        <stop offset="100%" stop-color="#475569" />
                    </linearGradient>
                </defs>

                <!-- 1. Draw horizontal background grid lines (25%, 50%, 75%, 100%) -->
                <?php
                $gridLines = [0.25, 0.50, 0.75, 1.00];
                foreach ($gridLines as $ratio):
                    $lineY = $baselineY - ($ratio * $plotHeight);
                    $lineVal = round($ratio * $maxVal, 1);
                ?>
                    <line x1="<?php echo $paddingLeft; ?>" y1="<?php echo $lineY; ?>" x2="<?php echo $paddingLeft + $plotWidth; ?>" y2="<?php echo $lineY; ?>" stroke="rgba(255, 255, 255, 0.05)" stroke-dasharray="3 3" />
                    <text x="<?php echo $paddingLeft - 10; ?>" y="<?php echo $lineY + 4; ?>" class="chart-grid-text" fill="var(--text-muted, #94a3b8)" text-anchor="end"><?php echo $lineVal; ?></text>
                <?php endforeach; ?>

                <!-- 2. Draw columns -->
                <?php
                $itemCount = count($items);
                $slotWidth = $plotWidth / $itemCount;
                $barWidth = min(50, $slotWidth * 0.6);
                
                foreach ($items as $index => $item):
                    $label = $item['label'] ?? '';
                    $val = floatval($item['value'] ?? 0);
                    
                    $barHeight = ($val / $maxVal) * $plotHeight;
                    $barX = $paddingLeft + ($index * $slotWidth) + (($slotWidth - $barWidth) / 2);
                    $barY = $baselineY - $barHeight;
                    
                    $isZeroCms = (strpos(strtolower($label), 'zero') !== false);
                    $barFill = $isZeroCms ? 'url(#zero-gradient-vertical)' : 'url(#neutral-gradient-vertical)';
                    $textColor = $isZeroCms ? 'var(--primary-color, #00f0ff)' : 'var(--text-color, #d4e4fa)';
                    ?>
                    <g class="chart-row-group">
                        <!-- Background Column Track -->
                        <rect x="<?php echo $barX; ?>" y="<?php echo $baselineY - $plotHeight; ?>" width="<?php echo $barWidth; ?>" height="<?php echo $plotHeight; ?>" rx="3" fill="rgba(255, 255, 255, 0.01)" stroke="rgba(255, 255, 255, 0.03)" stroke-width="1" />
                        
                        <!-- Animated Filled Column Bar -->
                        <rect x="<?php echo $barX; ?>" y="<?php echo $barY; ?>" width="<?php echo $barWidth; ?>" height="<?php echo $barHeight; ?>" rx="3" fill="<?php echo $barFill; ?>" class="chart-fill-bar" />
                        
                        <!-- Label below baseline (Split dynamically into <tspan> tags to enable SVG text wrapping) -->
                        <text x="<?php echo $barX + ($barWidth / 2); ?>" y="<?php echo $baselineY + 20; ?>" class="chart-label" fill="var(--text-color, #d4e4fa)" text-anchor="middle">
                            <?php 
                            $words = explode(' ', $label);
                            foreach ($words as $wIndex => $word): 
                            ?>
                                <tspan x="<?php echo $barX + ($barWidth / 2); ?>" dy="<?php echo $wIndex === 0 ? '0' : '15'; ?>"><?php echo Str::escape($word); ?></tspan>
                            <?php endforeach; ?>
                        </text>
                        
                        <!-- Numeric Value Display above column -->
                        <text x="<?php echo $barX + ($barWidth / 2); ?>" y="<?php echo $barY - 8; ?>" class="chart-value-text" fill="<?php echo $textColor; ?>" text-anchor="middle"><?php echo Str::escape($item['value']); ?></text>
                    </g>
                <?php endforeach; ?>
                
                <!-- Baseline -->
                <line x1="<?php echo $paddingLeft; ?>" y1="<?php echo $baselineY; ?>" x2="<?php echo $paddingLeft + $plotWidth; ?>" y2="<?php echo $baselineY; ?>" stroke="rgba(255, 255, 255, 0.15)" stroke-width="1" />
            </svg>
        <?php else: 
            // Horizontal Bar Chart (legacy)
            $rowHeight = 50;
            $paddingTop = 30;
            $barContainerWidth = 350;
            $svgHeight = count($items) * $rowHeight + $paddingTop + 10;
            ?>
            <svg viewBox="0 0 600 <?php echo $svgHeight; ?>" width="100%" height="100%" class="block-chart-svg">
                <defs>
                    <linearGradient id="zero-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#00f0ff" />
                        <stop offset="100%" stop-color="#3b82f6" />
                    </linearGradient>
                    <linearGradient id="neutral-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#475569" />
                        <stop offset="100%" stop-color="#1e293b" />
                    </linearGradient>
                </defs>

                <!-- Draw vertical background grid lines -->
                <?php
                $gridLines = [0.25, 0.50, 0.75, 1.00];
                foreach ($gridLines as $ratio):
                    $lineX = 180 + $ratio * $barContainerWidth;
                    $lineVal = round($ratio * $maxVal, 1);
                ?>
                    <line x1="<?php echo $lineX; ?>" y1="20" x2="<?php echo $lineX; ?>" y2="<?php echo $svgHeight - 10; ?>" stroke="rgba(255, 255, 255, 0.05)" stroke-dasharray="3 3" />
                    <text x="<?php echo $lineX; ?>" y="12" class="chart-grid-text" fill="var(--text-muted, #94a3b8)" text-anchor="middle"><?php echo $lineVal; ?></text>
                <?php endforeach; ?>

                <!-- Draw rows -->
                <?php foreach ($items as $index => $item): 
                    $label = $item['label'] ?? '';
                    $val = floatval($item['value'] ?? 0);
                    
                    $rowY = $index * $rowHeight + $paddingTop;
                    $barWidth = ($val / $maxVal) * $barContainerWidth;
                    
                    $isZeroCms = (strpos(strtolower($label), 'zero') !== false);
                    $barFill = $isZeroCms ? 'url(#zero-gradient)' : 'url(#neutral-gradient)';
                    $textColor = $isZeroCms ? 'var(--primary-color, #00f0ff)' : 'var(--text-color, #d4e4fa)';
                    ?>
                    <g class="chart-row-group">
                        <text x="10" y="<?php echo $rowY + 12; ?>" class="chart-label" fill="var(--text-color, #d4e4fa)"><?php echo Str::escape($label); ?></text>
                        <rect x="180" y="<?php echo $rowY; ?>" width="<?php echo $barContainerWidth; ?>" height="20" rx="3" fill="rgba(255, 255, 255, 0.01)" stroke="rgba(255, 255, 255, 0.03)" stroke-width="1" />
                        <rect x="180" y="<?php echo $rowY; ?>" width="<?php echo $barWidth; ?>" height="20" rx="3" fill="<?php echo $barFill; ?>" class="chart-fill-bar" />
                        <text x="<?php echo 180 + $barWidth + 10; ?>" y="<?php echo $rowY + 12; ?>" class="chart-value-text" fill="<?php echo $textColor; ?>"><?php echo Str::escape($item['value']); ?></text>
                    </g>
                <?php endforeach; ?>
            </svg>
        <?php endif; ?>
    </div>
</div>
