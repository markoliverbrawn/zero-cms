<?php
/**
 * File: src/Views/components/BenchmarkWidget.php
 * Architectural Purpose: Render engine for Zero CMS widescreen performance telemetry benchmark diagnostics.
 * Package: Zero\Views\Components
 * Systemic Role: Displays system metrics (query log, count, page runtime) via a draggable overlay widget.
 */

use Zero\Support\Str;

?>
<!-- Custom Zero-Dependency Widescreen Benchmark Stats -->
<div id="db-benchmark-widget" style="position: fixed; bottom: 20px; right: 20px; width: 450px; max-height: 400px; background: #0f172a; border: 1px solid #1e293b; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); font-family: monospace; z-index: 99999999; color: #f8fafc; overflow: hidden; display: flex; flex-direction: column;">
    <!-- Header (Click to toggle) -->
    <div id="db-benchmark-header" style="padding: 12px 18px; background: #1e293b; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none;">
        <div style="font-weight: bold; font-size: 0.82rem; display: flex; align-items: center; gap: 8px;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#00ffcc" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <span>SYSTEM BENCHMARK</span>
        </div>
        <div style="font-size: 0.76rem; color: #00ffcc; font-weight: bold;">
            <strong><?php echo $queryCount; ?></strong> Qs in <strong><?php echo number_format($totalTime * 1000, 2); ?>ms</strong> | Page: <strong><?php echo number_format($totalPageTime * 1000, 2); ?>ms</strong>
        </div>
    </div>
    <!-- Body (Collapsible scroll drawer) -->
    <div id="db-benchmark-body" style="display: none; padding: 15px; overflow-y: auto; flex-grow: 1; max-height: 320px; font-size: 0.75rem; background: #0b0f19;">
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php if (empty($queryLog)): ?>
                <div style="color: #64748b; font-style: italic; text-align: center; padding: 15px 0;">No queries run in this request.</div>
            <?php else: ?>
                <?php foreach ($queryLog as $idx => $q): ?>
                    <div style="border-bottom: 1px solid #1e293b; padding-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; color: #64748b; margin-bottom: 4px; font-size: 0.68rem; font-weight: bold;">
                            <span>QUERY #<?php echo $idx + 1; ?></span>
                            <span style="color: #00ffcc; font-weight: bold;"><?php echo number_format($q['duration'] * 1000, 2); ?>ms</span>
                        </div>
                        <div style="color: #ffffff; white-space: pre-wrap; word-break: break-all; margin-bottom: 6px; line-height: 1.4; background: #151d30; padding: 6px; border-radius: 4px; border: 1px solid #1e293b; font-family: monospace; font-size: 0.72rem;"><?php echo Str::escape($q['sql']); ?></div>
                        <?php if (!empty($q['params'])): ?>
                            <div style="color: #94a3b8; font-size: 0.68rem; word-break: break-all; background: #0d121f; padding: 4px; border-radius: 2px;">
                                <strong style="color: #ff0055;">BINDS:</strong> <?php echo Str::escape(json_encode($q['params'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<script nonce="<?php echo $nonce; ?>">
document.addEventListener('DOMContentLoaded', () => {
    const widget = document.getElementById('db-benchmark-widget');
    const header = document.getElementById('db-benchmark-header');
    const body = document.getElementById('db-benchmark-body');
    if (!widget || !header || !body) return;

    let isExpanded = false;
    let isDragging = false;
    let hasMoved = false;
    let currentX = 0;
    let currentY = 0;
    let initialX = 0;
    let initialY = 0;
    let xOffset = 0;
    let yOffset = 0;

    // Header Toggle Expand/Collapse (Only if we didn't drag!)
    header.addEventListener('click', (e) => {
        if (hasMoved) {
            e.preventDefault();
            e.stopPropagation();
            return;
        }
        isExpanded = !isExpanded;
        body.style.display = isExpanded ? 'block' : 'none';
    });

    // Vanilla Drag and Drop Logic
    header.addEventListener('mousedown', dragStart);
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', dragEnd);

    header.addEventListener('touchstart', dragStart, { passive: true });
    document.addEventListener('touchmove', drag, { passive: false });
    document.addEventListener('touchend', dragEnd);

    function dragStart(e) {
        hasMoved = false;
        if (e.type === 'touchstart') {
            initialX = e.touches[0].clientX - xOffset;
            initialY = e.touches[0].clientY - yOffset;
        } else {
            initialX = e.clientX - xOffset;
            initialY = e.clientY - yOffset;
        }

        if (e.target === header || header.contains(e.target)) {
            isDragging = true;
            header.style.cursor = 'grabbing';
        }
    }

    function drag(e) {
        if (!isDragging) return;

        if (e.cancelable) {
            e.preventDefault();
        }

        let clientX, clientY;
        if (e.type === 'touchmove') {
            clientX = e.touches[0].clientX;
            clientY = e.touches[0].clientY;
        } else {
            clientX = e.clientX;
            clientY = e.clientY;
        }

        currentX = clientX - initialX;
        currentY = clientY - initialY;

        // Threshold to distinguish dragging from clicking
        if (Math.abs(currentX - xOffset) > 5 || Math.abs(currentY - yOffset) > 5) {
            hasMoved = true;
        }

        xOffset = currentX;
        yOffset = currentY;

        widget.style.transform = `translate(${currentX}px, ${currentY}px)`;
    }

    function dragEnd() {
        if (!isDragging) return;
        initialX = currentX;
        initialY = currentY;
        isDragging = false;
        header.style.cursor = 'grab';
    }

    header.style.cursor = 'grab';
});
</script>
